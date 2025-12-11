<?php

namespace App\Http\Controllers\Api;

use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Models\ProductVariant;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchasesController extends BaseApiController
{
    protected PurchaseService $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * List purchases for the current store
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $query = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->with(['posSession', 'store', 'receipt', 'customer']);

        // Filter by POS session (ignore if null)
        if ($request->has('pos_session_id') && $request->get('pos_session_id') !== null) {
            $query->where('pos_session_id', $request->get('pos_session_id'));
        }

        // Filter by status (ignore if null)
        if ($request->has('status') && $request->get('status') !== null) {
            $query->where('status', $request->get('status'));
        }

        // Filter by payment method (ignore if null)
        if ($request->has('payment_method') && $request->get('payment_method') !== null) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // Filter by date range (ignore if null)
        if ($request->has('from_date') && $request->get('from_date') !== null) {
            $query->whereDate('created_at', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date') && $request->get('to_date') !== null) {
            $query->whereDate('created_at', '<=', $request->get('to_date'));
        }

        // Filter by paid_at date range (ignore if null)
        if ($request->has('paid_from_date') && $request->get('paid_from_date') !== null) {
            $query->whereDate('paid_at', '>=', $request->get('paid_from_date'));
        }
        if ($request->has('paid_to_date') && $request->get('paid_to_date') !== null) {
            $query->whereDate('paid_at', '<=', $request->get('paid_to_date'));
        }

        // Filter by customer (ignore if null) - customer_id is the database ID
        if ($request->has('customer_id') && $request->get('customer_id') !== null) {
            $customerId = (int) $request->get('customer_id');
            // Use whereHas to filter by customer database ID
            $query->whereHas('customer', function ($q) use ($customerId, $store) {
                $q->where('stripe_connected_customer_mappings.id', $customerId)
                  ->where('stripe_connected_customer_mappings.stripe_account_id', $store->stripe_account_id);
            });
        }

        // Filter by freetext search term if provided
        // Searches across charge fields, customer info, receipt numbers, transaction codes, etc.
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search, $store) {
                // Search in charge fields
                // If search is numeric, try exact match on ID first
                if (is_numeric($search)) {
                    $q->where('id', '=', (int) $search);
                }
                $q->orWhere('stripe_charge_id', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('transaction_code', 'ilike', "%{$search}%")
                  ->orWhere('payment_code', 'ilike', "%{$search}%")
                  ->orWhere('article_group_code', 'ilike', "%{$search}%")
                  // Search in customer fields
                  ->orWhereHas('customer', function ($customerQuery) use ($search, $store) {
                      $customerQuery->where('stripe_connected_customer_mappings.stripe_account_id', $store->stripe_account_id)
                          ->where(function ($cq) use ($search) {
                              $cq->where('stripe_connected_customer_mappings.name', 'ilike', "%{$search}%")
                                 ->orWhere('stripe_connected_customer_mappings.email', 'ilike', "%{$search}%")
                                 ->orWhere('stripe_connected_customer_mappings.phone', 'ilike', "%{$search}%")
                                 ->orWhere('stripe_connected_customer_mappings.stripe_customer_id', 'ilike', "%{$search}%");
                          });
                  })
                  // Search in receipt number
                  ->orWhereHas('receipt', function ($receiptQuery) use ($search) {
                      $receiptQuery->where('receipt_number', 'ilike', "%{$search}%");
                  });
            });
        }

        $perPage = min($request->get('per_page', 20), 100); // Max 100 per page
        $purchases = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'purchases' => $purchases->getCollection()->map(function ($purchase) {
                return $this->formatPurchaseResponse($purchase);
            }),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    /**
     * Get a single purchase
     *
     * @param Request $request
     * @param string|int $id
     * @return JsonResponse
     */
    public function show(Request $request, string|int $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        // Validate that id is numeric
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid purchase ID'], 400);
        }

        $purchase = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->where('id', (int) $id)
            ->whereNotNull('pos_session_id')
            ->with(['posSession', 'store', 'receipt', 'customer'])
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        return response()->json([
            'purchase' => $this->formatPurchaseResponse($purchase),
        ]);
    }

    /**
     * Update customer for a purchase
     *
     * @param Request $request
     * @param string|int $id
     * @return JsonResponse
     */
    public function updateCustomer(Request $request, string|int $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        // Validate that id is numeric
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid purchase ID'], 400);
        }

        $purchase = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->where('id', (int) $id)
            ->whereNotNull('pos_session_id')
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        // Validate request - customer_id is optional (can be null to remove customer)
        // customer_id is the database ID (integer)
        $validator = Validator::make($request->all(), [
            'customer_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customerId = $request->input('customer_id');
        $stripeCustomerId = null;

        // If customer_id is provided, verify customer exists and belongs to the store
        if ($customerId !== null) {
            $customer = \App\Models\ConnectedCustomer::where('stripe_account_id', $store->stripe_account_id)
                ->where('id', (int) $customerId)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }

            $stripeCustomerId = $customer->stripe_customer_id;
        }

        // Update the purchase with the customer (or set to null to remove)
        $purchase->update([
            'stripe_customer_id' => $stripeCustomerId,
        ]);

        // Reload relationships
        $purchase->load(['posSession', 'store', 'receipt', 'customer']);

        return response()->json([
            'success' => true,
            'message' => 'Customer registered to purchase successfully',
            'purchase' => $this->formatPurchaseResponse($purchase),
        ]);
    }

    /**
     * Format purchase for API response with purchase_ prefixed nested structures
     *
     * @param ConnectedCharge $purchase
     * @return array
     */
    protected function formatPurchaseResponse(ConnectedCharge $purchase): array
    {
        $data = [
            'id' => $purchase->id,
            'stripe_charge_id' => $purchase->stripe_charge_id,
            'amount' => $purchase->amount,
            'amount_refunded' => $purchase->amount_refunded,
            'currency' => $purchase->currency,
            'status' => $purchase->status,
            'payment_method' => $purchase->payment_method,
            'description' => $purchase->description,
            'failure_code' => $purchase->failure_code,
            'failure_message' => $purchase->failure_message,
            'captured' => $purchase->captured,
            'refunded' => $purchase->refunded,
            'paid' => $purchase->paid,
            'paid_at' => $this->formatDateTimeOslo($purchase->paid_at),
            'charge_type' => $purchase->charge_type,
            'application_fee_amount' => $purchase->application_fee_amount ?? null,
            'tip_amount' => $purchase->tip_amount ?? null,
            'transaction_code' => $purchase->transaction_code,
            'payment_code' => $purchase->payment_code,
            'article_group_code' => $purchase->article_group_code,
            'created_at' => $this->formatDateTimeOslo($purchase->created_at),
            'updated_at' => $this->formatDateTimeOslo($purchase->updated_at),
        ];

        // Add nested structures with purchase_ prefix
        if ($purchase->posSession) {
            $data['purchase_session'] = [
                'id' => $purchase->posSession->id,
                'session_number' => $purchase->posSession->session_number,
                'status' => $purchase->posSession->status,
                'opened_at' => $this->formatDateTimeOslo($purchase->posSession->opened_at),
                'closed_at' => $this->formatDateTimeOslo($purchase->posSession->closed_at),
            ];
        }

        if ($purchase->store) {
            $data['purchase_store'] = [
                'id' => $purchase->store->id,
                'name' => $purchase->store->name,
                'slug' => $purchase->store->slug,
            ];
        }

        if ($purchase->receipt) {
            $data['purchase_receipt'] = [
                'id' => $purchase->receipt->id,
                'receipt_number' => $purchase->receipt->receipt_number,
                'receipt_type' => $purchase->receipt->receipt_type,
                'printed' => $purchase->receipt->printed,
                'printed_at' => $this->formatDateTimeOslo($purchase->receipt->printed_at),
                'reprint_count' => $purchase->receipt->reprint_count,
            ];
        }

        // Always return purchase_customer as an object (never null)
        // If no customer, return object with null values
        // Match the structure from customers API
        if ($purchase->customer) {
            // Transform customer to match customers API structure
            $customerData = $purchase->customer->makeHidden(['model', 'model_id', 'model_uuid'])->toArray();
            
            // Rename address to customer_address
            if (isset($customerData['address'])) {
                $customerData['customer_address'] = $customerData['address'];
                unset($customerData['address']);
            }
            
            $data['purchase_customer'] = $customerData;
        } else {
            $data['purchase_customer'] = [
                'id' => null,
                'stripe_customer_id' => null,
                'stripe_account_id' => null,
                'name' => null,
                'email' => null,
                'phone' => null,
                'profile_image_url' => null,
                'customer_address' => null,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        // Extract purchase items and related data from metadata
        // Clean metadata to remove Stripe internal properties
        $metadata = $purchase->metadata ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        }
        
        // Filter out Stripe internal properties (those with null bytes)
        $cleanMetadata = [];
        if (is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                // Skip Stripe internal properties (contain null bytes or start with special chars)
                if (strpos($key, "\0") !== false || strpos($key, '*') !== false) {
                    continue;
                }
                // Only include scalar values or arrays of scalar values
                if (is_scalar($value) || is_array($value)) {
                    $cleanMetadata[$key] = $value;
                }
            }
        }

        // Extract items for enrichment (before removing from metadata)
        $items = $cleanMetadata['items'] ?? [];
        
        // Remove items from metadata since we have a separate purchase_items field
        unset($cleanMetadata['items']);
        
        // Set cleaned metadata (without items)
        $data['purchase_metadata'] = !empty($cleanMetadata) ? $cleanMetadata : null;

        // Add purchase items - enrich with product information
        if (!empty($items) && is_array($items)) {
            $data['purchase_items'] = $this->enrichPurchaseItems($items, $purchase->stripe_account_id);
            
            // Calculate totals from items if metadata values are missing or 0
            $calculatedSubtotal = 0;
            $calculatedDiscounts = 0;
            
            foreach ($items as $item) {
                $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                $discountAmount = isset($item['discount_amount']) ? (int) $item['discount_amount'] : 0;
                
                $lineSubtotal = $unitPrice * $quantity;
                $lineDiscount = $discountAmount * $quantity;
                
                $calculatedSubtotal += $lineSubtotal;
                $calculatedDiscounts += $lineDiscount;
            }
        } else {
            $data['purchase_items'] = [];
            $calculatedSubtotal = 0;
            $calculatedDiscounts = 0;
        }

        // Add purchase discounts
        if (isset($cleanMetadata['discounts']) && is_array($cleanMetadata['discounts'])) {
            $data['purchase_discounts'] = $cleanMetadata['discounts'];
            // Calculate cart-level discounts total
            $cartLevelDiscounts = 0;
            foreach ($cleanMetadata['discounts'] as $discount) {
                if (isset($discount['amount']) && is_numeric($discount['amount'])) {
                    $cartLevelDiscounts += (int) $discount['amount'];
                }
            }
            // Add cart-level discounts to calculated discounts
            $calculatedDiscounts += $cartLevelDiscounts;
        } else {
            $data['purchase_discounts'] = [];
        }

        // Add purchase totals - use calculated values if metadata values are missing or incorrectly 0
        // If metadata is missing (null), calculate from items
        // If metadata is 0 but we have items that would produce non-zero values, recalculate (handles incorrect metadata)
        $metadataSubtotal = isset($cleanMetadata['subtotal']) ? (int) $cleanMetadata['subtotal'] : null;
        $metadataDiscounts = isset($cleanMetadata['total_discounts']) ? (int) $cleanMetadata['total_discounts'] : null;
        $metadataTax = isset($cleanMetadata['total_tax']) ? (int) $cleanMetadata['total_tax'] : null;
        $metadataTip = isset($cleanMetadata['tip_amount']) ? (int) $cleanMetadata['tip_amount'] : null;
        
        // Use calculated subtotal if metadata is missing OR if metadata is 0 but we have items
        if ($metadataSubtotal === null || ($metadataSubtotal === 0 && $calculatedSubtotal > 0)) {
            $data['purchase_subtotal'] = $calculatedSubtotal;
        } else {
            $data['purchase_subtotal'] = $metadataSubtotal;
        }
        
        // Use calculated discounts if metadata is missing OR if metadata is 0 but we have calculated discounts
        if ($metadataDiscounts === null || ($metadataDiscounts === 0 && $calculatedDiscounts > 0)) {
            $data['purchase_total_discounts'] = $calculatedDiscounts;
        } else {
            $data['purchase_total_discounts'] = $metadataDiscounts;
        }
        
        // Calculate tax from subtotal if metadata is missing OR if metadata is 0 but we have a subtotal
        // Prices are tax-inclusive, so we extract tax: Tax = Subtotal × (Tax Rate / (1 + Tax Rate))
        // Default 25% VAT in Norway
        $subtotalAfterDiscounts = $data['purchase_subtotal'] - $data['purchase_total_discounts'];
        if ($metadataTax === null || ($metadataTax === 0 && $subtotalAfterDiscounts > 0)) {
            // Calculate tax from subtotal (after discounts, tax-inclusive)
            $taxRate = 0.25; // 25% VAT default
            $calculatedTax = $subtotalAfterDiscounts > 0 
                ? (int) round($subtotalAfterDiscounts * ($taxRate / (1 + $taxRate)))
                : 0;
            $data['purchase_total_tax'] = $calculatedTax;
        } else {
            $data['purchase_total_tax'] = $metadataTax;
        }
        
        // Use metadata tip (or 0 if not set)
        $data['purchase_tip_amount'] = $metadataTip ?? 0;
        
        // Extract note from metadata if present
        $data['purchase_note'] = isset($cleanMetadata['note']) && !empty($cleanMetadata['note']) 
            ? $cleanMetadata['note'] 
            : null;
        
        // Add purchase payments - list of payments connected to this purchase
        $data['purchase_payments'] = $this->formatPurchasePayments($purchase);
        
        // Clean outcome to remove Stripe internal properties
        $outcome = $purchase->outcome ?? null;
        if ($outcome && is_array($outcome)) {
            $cleanOutcome = [];
            foreach ($outcome as $key => $value) {
                // Skip Stripe internal properties
                if (strpos($key, "\0") !== false || strpos($key, '*') !== false) {
                    continue;
                }
                // Only include useful outcome fields
                if (in_array($key, ['type', 'network_status', 'reason', 'risk_level', 'seller_message', 'advice_code', 'network_advice_code', 'network_decline_code'])) {
                    $cleanOutcome[$key] = $value;
                }
            }
            $data['outcome'] = !empty($cleanOutcome) ? $cleanOutcome : null;
        } else {
            $data['outcome'] = null;
        }

        return $data;
    }

    /**
     * Enrich purchase items with product information
     * Uses stored product snapshots from metadata first (for historical accuracy),
     * falls back to current product data if snapshot is missing (backward compatibility)
     *
     * @param array $items
     * @param string $stripeAccountId
     * @return array
     */
    protected function enrichPurchaseItems(array $items, string $stripeAccountId): array
    {
        if (empty($items)) {
            return [];
        }

        // Check if items already have product snapshots (new purchases)
        $hasSnapshots = !empty($items[0]['product_name'] ?? null);

        // If snapshots exist, use them directly (preserves historical data)
        if ($hasSnapshots) {
            return array_map(function ($item) {
                $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
                $discountAmount = isset($item['discount_amount']) ? (int) $item['discount_amount'] : 0;
                // original_price is stored as integer (øre) in snapshot, or calculate if missing
                $originalPrice = isset($item['original_price']) ? (int) $item['original_price'] : ($discountAmount > 0 ? ($unitPrice + $discountAmount) : null);

                // Format item with purchase_item_ prefix for FlutterFlow
                return [
                    'purchase_item_id' => $item['id'] ?? null,
                    'purchase_item_product_id' => isset($item['product_id']) ? (string) $item['product_id'] : null,
                    'purchase_item_variant_id' => isset($item['variant_id']) ? (string) $item['variant_id'] : null,
                    'purchase_item_product_name' => $item['product_name'] ?? null,
                    'purchase_item_product_image_url' => $item['product_image_url'] ?? null,
                    'purchase_item_unit_price' => $unitPrice,
                    'purchase_item_quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                    'purchase_item_original_price' => $originalPrice,
                    'purchase_item_discount_amount' => $discountAmount > 0 ? $discountAmount : null,
                    'purchase_item_discount_reason' => $item['discount_reason'] ?? null,
                    'purchase_item_article_group_code' => $item['article_group_code'] ?? null,
                    'purchase_item_product_code' => $item['product_code'] ?? null,
                    'purchase_item_metadata' => isset($item['metadata']) && is_array($item['metadata']) 
                        ? $item['metadata'] 
                        : null,
                ];
            }, $items);
        }

        // Fallback: enrich from current product data (for old purchases without snapshots)
        // Collect all product IDs and variant IDs
        $productIds = [];
        $variantIds = [];
        
        foreach ($items as $item) {
            if (isset($item['product_id'])) {
                $productIds[] = (int) $item['product_id'];
            }
            if (isset($item['variant_id'])) {
                $variantIds[] = (int) $item['variant_id'];
            }
        }

        // Fetch products and variants in bulk
        $products = ConnectedProduct::whereIn('id', array_unique($productIds))
            ->where('stripe_account_id', $stripeAccountId)
            ->get()
            ->keyBy('id');

        $variants = ProductVariant::whereIn('id', array_unique($variantIds))
            ->where('stripe_account_id', $stripeAccountId)
            ->get()
            ->keyBy('id');

        // Enrich each item from current product data
        return array_map(function ($item) use ($products, $variants) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
            
            $product = $productId ? ($products[$productId] ?? null) : null;
            $variant = $variantId ? ($variants[$variantId] ?? null) : null;

            // Get product name (from variant if available, otherwise product)
            $productName = null;
            if ($variant && $variant->product) {
                $productName = $variant->product->name;
                if ($variant->variant_name !== 'Default') {
                    $productName .= ' - ' . $variant->variant_name;
                }
            } elseif ($product) {
                $productName = $product->name;
            }

            // Get product image URL
            $productImageUrl = null;
            if ($variant && $variant->image_url) {
                $productImageUrl = $variant->image_url;
            } elseif ($product) {
                // Get first image from product
                if ($product->hasMedia('images')) {
                    $firstMedia = $product->getMedia('images')->first();
                    if ($firstMedia) {
                        $productImageUrl = URL::temporarySignedRoute(
                            'api.products.images.serve',
                            now()->addDay(),
                            [
                                'product' => $product->id,
                                'media' => $firstMedia->id,
                            ]
                        );
                    }
                } elseif ($product->images && is_array($product->images) && !empty($product->images)) {
                    $productImageUrl = $product->images[0];
                }
            }

            // Calculate original price (unit_price + discount_amount if discount exists)
            $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
            $discountAmount = isset($item['discount_amount']) ? (int) $item['discount_amount'] : 0;
            $originalPrice = $discountAmount > 0 ? ($unitPrice + $discountAmount) : null;

            // Get article group code and product code
            $articleGroupCode = null;
            $productCode = null;
            if ($variant && $variant->product) {
                $articleGroupCode = $variant->product->article_group_code;
                $productCode = $variant->product->product_code;
            } elseif ($product) {
                $articleGroupCode = $product->article_group_code;
                $productCode = $product->product_code;
            }

            // Format item with purchase_item_ prefix for FlutterFlow
            return [
                'purchase_item_id' => $item['id'] ?? null,
                'purchase_item_product_id' => $productId ? (string) $productId : null,
                'purchase_item_variant_id' => $variantId ? (string) $variantId : null,
                'purchase_item_product_name' => $productName,
                'purchase_item_product_image_url' => $productImageUrl,
                'purchase_item_unit_price' => $unitPrice,
                'purchase_item_quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                'purchase_item_original_price' => $originalPrice,
                'purchase_item_discount_amount' => $discountAmount > 0 ? $discountAmount : null,
                'purchase_item_discount_reason' => $item['discount_reason'] ?? null,
                'purchase_item_article_group_code' => $articleGroupCode,
                'purchase_item_product_code' => $productCode,
                'purchase_item_metadata' => isset($item['metadata']) && is_array($item['metadata']) 
                    ? $item['metadata'] 
                    : null,
            ];
        }, $items);
    }

    /**
     * Get available payment methods for a store
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Try to get store from Filament tenant first, then fall back to user's current store
        try {
            $store = \Filament\Facades\Filament::getTenant();
        } catch (\Throwable $e) {
            $store = null;
        }
        
        // If no tenant, try user's current store
        if (!$store) {
            $store = $user->currentStore();
        }

        if (!$store) {
            return response()->json([
                'message' => 'No store found',
            ], 404);
        }

        // Get POS-suitable payment methods by default
        // Can be overridden with ?pos_only=false query parameter
        $posOnly = $request->boolean('pos_only', true);
        
        $query = PaymentMethod::where('store_id', $store->id)
            ->enabled();
            
        if ($posOnly) {
            $query->posSuitable();
        }
        
        $paymentMethods = $query->ordered()->get();

        return response()->json([
            'data' => $paymentMethods,
        ]);
    }

    /**
     * Process a purchase
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Check if this is a split payment request
        $isSplitPayment = $request->has('payments') && is_array($request->input('payments'));

        if ($isSplitPayment) {
            return $this->processSplitPayment($request);
        }

        // Single payment validation
        $validator = Validator::make($request->all(), [
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'payment_method_code' => ['required', 'string'],
            'cart' => ['required', 'array'],
            'cart.items' => ['required', 'array', 'min:1'],
            'cart.items.*.product_id' => ['required', 'integer'],
            'cart.items.*.quantity' => ['required', 'integer', 'min:1'],
            'cart.items.*.unit_price' => ['required', 'integer', 'min:0'],
            'cart.total' => ['required', 'integer', 'min:1'],
            'cart.currency' => ['nullable', 'string', 'size:3'],
            'cart.customer_id' => ['nullable', 'integer'],
            'cart.note' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Custom validation: if customer_id is provided, verify it exists and belongs to store
        // Note: Ignore 0 values (FlutterFlow sometimes sets 0 instead of null)
        $validator->after(function ($validator) use ($request) {
            $customerId = $request->input('cart.customer_id');
            
            // Ignore null, empty, or 0 values
            if ($customerId !== null && $customerId !== '' && $customerId !== 0) {
                // Get store from POS session
                $posSessionId = $request->input('pos_session_id');
                if ($posSessionId) {
                    $posSession = \App\Models\PosSession::find($posSessionId);
                    if ($posSession && $posSession->store) {
                        $customer = \App\Models\ConnectedCustomer::where('id', (int) $customerId)
                            ->where('stripe_account_id', $posSession->store->stripe_account_id)
                            ->exists();
                        
                        if (!$customer) {
                            $validator->errors()->add(
                                'cart.customer_id',
                                'Customer not found or does not belong to this store'
                            );
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Get POS session
        $posSession = PosSession::findOrFail($validated['pos_session_id']);

        // Verify user has access to this session's store
        $user = $request->user();
        
        // Try to get store from Filament tenant first, then fall back to user's current store
        try {
            $userStore = \Filament\Facades\Filament::getTenant();
        } catch (\Throwable $e) {
            $userStore = null;
        }
        
        // If no tenant, try user's current store
        if (!$userStore) {
            $userStore = $user->currentStore();
        }
        
        if (!$userStore || $posSession->store_id !== $userStore->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to POS session',
            ], 403);
        }

        // Verify session is open
        if ($posSession->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'POS session is not open',
            ], 422);
        }

        // Get payment method
        $paymentMethod = PaymentMethod::where('store_id', $posSession->store_id)
            ->where('code', $validated['payment_method_code'])
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }

        if (!$paymentMethod->enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method is not enabled',
            ], 422);
        }

        // For Stripe payments, require payment_intent_id in metadata
        if ($paymentMethod->provider === 'stripe') {
            $paymentIntentId = $validated['metadata']['payment_intent_id'] ?? null;
            if (!$paymentIntentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment intent ID is required for Stripe payments',
                ], 422);
            }
        }

        try {
            // Process purchase
            $result = $this->purchaseService->processPurchase(
                $posSession,
                $paymentMethod,
                $validated['cart'],
                $validated['metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'charge' => [
                        'id' => $result['charge']->id,
                        'stripe_charge_id' => $result['charge']->stripe_charge_id,
                        'amount' => $result['charge']->amount,
                        'currency' => $result['charge']->currency,
                        'status' => $result['charge']->status,
                        'payment_method' => $result['charge']->payment_method,
                        'paid_at' => $this->formatDateTimeOslo($result['charge']->paid_at),
                    ],
                    'receipt' => [
                        'id' => $result['receipt']->id,
                        'receipt_number' => $result['receipt']->receipt_number,
                        'receipt_type' => $result['receipt']->receipt_type,
                    ],
                    'pos_event' => [
                        'id' => $result['pos_event']->id,
                        'event_code' => $result['pos_event']->event_code,
                        'transaction_code' => $result['charge']->transaction_code,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete payment for a deferred purchase
     * Used for purchases that were created with deferred payment (e.g., dry cleaning)
     *
     * @param Request $request
     * @param string|int $id Charge ID
     * @return JsonResponse
     */
    public function completePayment(Request $request, string|int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method_code' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Get charge
        $charge = ConnectedCharge::findOrFail($id);

        // Verify user has access to this charge's store
        $user = $request->user();
        
        // Try to get store from Filament tenant first, then fall back to user's current store
        try {
            $userStore = \Filament\Facades\Filament::getTenant();
        } catch (\Throwable $e) {
            $userStore = null;
        }
        
        // If no tenant, try user's current store
        if (!$userStore) {
            $userStore = $user->currentStore();
        }
        
        if (!$userStore || $charge->store->id !== $userStore->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to charge',
            ], 403);
        }

        // Verify charge is pending
        if ($charge->status !== 'pending' || $charge->paid) {
            return response()->json([
                'success' => false,
                'message' => 'Charge is not pending or already paid',
            ], 422);
        }

        // Get payment method
        $paymentMethod = PaymentMethod::where('store_id', $charge->store->id)
            ->where('code', $validated['payment_method_code'])
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }

        if (!$paymentMethod->enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method is not enabled',
            ], 422);
        }

        // For Stripe payments, require payment_intent_id in metadata
        if ($paymentMethod->provider === 'stripe') {
            $paymentIntentId = $validated['metadata']['payment_intent_id'] ?? null;
            if (!$paymentIntentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment intent ID is required for Stripe payments',
                ], 422);
            }
        }

        try {
            // Complete deferred payment
            $result = $this->purchaseService->completeDeferredPayment(
                $charge,
                $paymentMethod,
                $validated['metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'charge' => [
                        'id' => $result['charge']->id,
                        'stripe_charge_id' => $result['charge']->stripe_charge_id,
                        'amount' => $result['charge']->amount,
                        'currency' => $result['charge']->currency,
                        'status' => $result['charge']->status,
                        'payment_method' => $result['charge']->payment_method,
                        'paid_at' => $this->formatDateTimeOslo($result['charge']->paid_at),
                    ],
                    'receipt' => [
                        'id' => $result['receipt']->id,
                        'receipt_number' => $result['receipt']->receipt_number,
                        'receipt_type' => $result['receipt']->receipt_type,
                    ],
                    'pos_event' => [
                        'id' => $result['pos_event']->id,
                        'event_code' => $result['pos_event']->event_code,
                        'transaction_code' => $result['charge']->transaction_code,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment completion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a split payment purchase
     *
     * @param Request $request
     * @return JsonResponse
     */
    protected function processSplitPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_code' => ['required', 'string'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.metadata' => ['nullable', 'array'],
            'cart' => ['required', 'array'],
            'cart.items' => ['required', 'array', 'min:1'],
            'cart.items.*.product_id' => ['required', 'integer'],
            'cart.items.*.quantity' => ['required', 'integer', 'min:1'],
            'cart.items.*.unit_price' => ['required', 'integer', 'min:0'],
            'cart.total' => ['required', 'integer', 'min:1'],
            'cart.currency' => ['nullable', 'string', 'size:3'],
            'cart.customer_id' => ['nullable', 'integer'],
            'cart.note' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Custom validation: payment amounts must sum to cart total
        $validator->after(function ($validator) use ($request) {
            $payments = $request->input('payments', []);
            $cartTotal = $request->input('cart.total', 0);
            $totalPaid = array_sum(array_column($payments, 'amount'));

            if ($totalPaid !== $cartTotal) {
                $validator->errors()->add(
                    'payments',
                    "Payment amounts (${totalPaid}) must equal cart total (${cartTotal})"
                );
            }

            // Validate customer_id if provided
            // Note: Ignore 0 values (FlutterFlow sometimes sets 0 instead of null)
            $customerId = $request->input('cart.customer_id');
            if ($customerId !== null && $customerId !== '' && $customerId !== 0) {
                $posSessionId = $request->input('pos_session_id');
                if ($posSessionId) {
                    $posSession = \App\Models\PosSession::find($posSessionId);
                    if ($posSession && $posSession->store) {
                        $customer = \App\Models\ConnectedCustomer::where('id', (int) $customerId)
                            ->where('stripe_account_id', $posSession->store->stripe_account_id)
                            ->exists();
                        
                        if (!$customer) {
                            $validator->errors()->add(
                                'cart.customer_id',
                                'Customer not found or does not belong to this store'
                            );
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Get POS session
        $posSession = PosSession::findOrFail($validated['pos_session_id']);

        // Verify user has access to this session's store
        $user = $request->user();
        
        try {
            $userStore = \Filament\Facades\Filament::getTenant();
        } catch (\Throwable $e) {
            $userStore = null;
        }
        
        if (!$userStore) {
            $userStore = $user->currentStore();
        }
        
        if (!$userStore || $posSession->store_id !== $userStore->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to POS session',
            ], 403);
        }

        // Verify session is open
        if ($posSession->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'POS session is not open',
            ], 422);
        }

        // Validate all payment methods
        foreach ($validated['payments'] as $index => $paymentData) {
            $paymentMethod = PaymentMethod::where('store_id', $posSession->store_id)
                ->where('code', $paymentData['payment_method_code'])
                ->first();

            if (!$paymentMethod) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment method not found: {$paymentData['payment_method_code']}",
                ], 404);
            }

            if (!$paymentMethod->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment method is not enabled: {$paymentData['payment_method_code']}",
                ], 422);
            }

            // For Stripe payments, require payment_intent_id
            if ($paymentMethod->provider === 'stripe') {
                $paymentIntentId = $paymentData['metadata']['payment_intent_id'] ?? null;
                if (!$paymentIntentId) {
                    return response()->json([
                        'success' => false,
                        'message' => "Payment intent ID is required for Stripe payment at index {$index}",
                    ], 422);
                }
            }
        }

        try {
            // Process split purchase
            $result = $this->purchaseService->processSplitPurchase(
                $posSession,
                $validated['payments'],
                $validated['cart'],
                $validated['metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'charges' => array_map(function ($charge) {
                        return [
                            'id' => $charge->id,
                            'stripe_charge_id' => $charge->stripe_charge_id,
                            'amount' => $charge->amount,
                            'currency' => $charge->currency,
                            'status' => $charge->status,
                            'payment_method' => $charge->payment_method,
                            'paid_at' => $this->formatDateTimeOslo($charge->paid_at),
                        ];
                    }, $result['charges']),
                    'receipt' => [
                        'id' => $result['receipt']->id,
                        'receipt_number' => $result['receipt']->receipt_number,
                        'receipt_type' => $result['receipt']->receipt_type,
                    ],
                    'pos_event' => [
                        'id' => $result['pos_event']->id,
                        'event_code' => $result['pos_event']->event_code,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a pending purchase
     * 
     * Cancels a purchase that is in pending status (e.g., deferred payments).
     * Changes the status to 'cancelled' and logs a void transaction event.
     *
     * @param Request $request
     * @param string|int $id Purchase ID
     * @return JsonResponse
     */
    public function cancel(Request $request, string|int $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        // Validate that id is numeric
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid purchase ID'], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Find the purchase
        $purchase = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->where('id', (int) $id)
            ->whereNotNull('pos_session_id')
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        // Validate purchase is pending
        if ($purchase->status !== 'pending' || $purchase->paid) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase is not pending or already paid',
            ], 422);
        }

        // Check if purchase is already refunded
        if ($purchase->refunded) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a purchase that has already been refunded',
            ], 422);
        }

        // Get POS session
        $posSession = $purchase->posSession;
        if (!$posSession || $posSession->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'POS session is not open',
            ], 422);
        }

        // Cancel Stripe payment intent if it exists (for deferred payments)
        $paymentIntentCancelled = false;
        $paymentIntentError = null;
        if ($purchase->stripe_payment_intent_id) {
            $cancelResult = $this->purchaseService->cancelPaymentIntent(
                $purchase->stripe_payment_intent_id,
                $store->stripe_account_id
            );
            $paymentIntentCancelled = $cancelResult['cancelled'];
            $paymentIntentError = $cancelResult['error'];
        }

        // Log void transaction event (13014)
        \App\Models\PosEvent::create([
            'store_id' => $store->id,
            'pos_device_id' => $posSession->pos_device_id,
            'pos_session_id' => $posSession->id,
            'user_id' => $request->user()->id,
            'related_charge_id' => $purchase->id,
            'event_code' => \App\Models\PosEvent::EVENT_VOID_TRANSACTION,
            'event_type' => 'transaction',
            'description' => "Purchase cancelled: {$purchase->description}",
            'event_data' => [
                'charge_id' => $purchase->id,
                'stripe_charge_id' => $purchase->stripe_charge_id,
                'stripe_payment_intent_id' => $purchase->stripe_payment_intent_id,
                'payment_intent_cancelled' => $paymentIntentCancelled,
                'payment_intent_error' => $paymentIntentError,
                'original_amount' => $purchase->amount,
                'original_status' => $purchase->status,
                'reason' => $validated['reason'] ?? null,
            ],
            'occurred_at' => now(),
        ]);

        // Update purchase status to cancelled and add cancellation metadata
        $metadata = $purchase->metadata ?? [];
        $metadata['cancelled'] = true;
        $metadata['cancelled_at'] = $this->formatDateTimeOslo(now());
        $metadata['cancelled_by'] = $request->user()->id;
        $metadata['cancellation_reason'] = $validated['reason'] ?? null;
        if ($paymentIntentCancelled) {
            $metadata['payment_intent_cancelled'] = true;
            $metadata['payment_intent_cancelled_at'] = $this->formatDateTimeOslo(now());
        }
        if ($paymentIntentError) {
            $metadata['payment_intent_cancellation_error'] = $paymentIntentError;
        }

        $purchase->update([
            'status' => 'cancelled',
            'failure_message' => $validated['reason'] ?? 'Purchase cancelled',
            'metadata' => $metadata,
        ]);

        // Reload relationships
        $purchase->load(['posSession', 'store', 'receipt', 'customer']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase cancelled successfully',
            'purchase' => $this->formatPurchaseResponse($purchase),
        ]);
    }

    /**
     * Refund a purchase
     * 
     * Processes refunds for both Stripe and cash payments.
     * For Stripe payments, creates a refund in Stripe.
     * For cash payments, updates local records only (manual refund process).
     * 
     * Generates return receipt automatically and logs POS event (13013).
     * Updates POS session totals.
     * 
     * Supports full and partial refunds.
     *
     * @param Request $request
     * @param string|int $id Purchase ID
     * @return JsonResponse
     */
    public function refund(Request $request, string|int $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        // Validate that id is numeric
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid purchase ID'], 400);
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Find the purchase
        $purchase = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->where('id', (int) $id)
            ->whereNotNull('pos_session_id')
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        // Validate purchase can be refunded
        if ($purchase->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot refund a cancelled purchase',
            ], 422);
        }

        if ($purchase->status === 'pending' || !$purchase->paid) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot refund a purchase that has not been paid',
            ], 422);
        }

        // Validate refund amount if provided
        if (isset($validated['amount'])) {
            $refundAmount = (int) $validated['amount'];
            $remainingRefundable = $purchase->amount - $purchase->amount_refunded;
            
            if ($refundAmount > $remainingRefundable) {
                return response()->json([
                    'success' => false,
                    'message' => "Refund amount ({$refundAmount}) exceeds remaining refundable amount ({$remainingRefundable})",
                ], 422);
            }
        }

        // Get POS session
        $posSession = $purchase->posSession;
        if (!$posSession || $posSession->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'POS session is not open',
            ], 422);
        }

        try {
            // Process refund
            $result = $this->purchaseService->processRefund(
                $purchase,
                $validated['amount'] ?? null,
                $validated['reason'] ?? null,
                $request->user()->id
            );

            // Reload relationships
            $result['charge']->load(['posSession', 'store', 'receipt', 'customer']);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => [
                    'charge' => [
                        'id' => $result['charge']->id,
                        'stripe_charge_id' => $result['charge']->stripe_charge_id,
                        'amount' => $result['charge']->amount,
                        'amount_refunded' => $result['charge']->amount_refunded,
                        'currency' => $result['charge']->currency,
                        'status' => $result['charge']->status,
                        'refunded' => $result['charge']->refunded,
                        'payment_method' => $result['charge']->payment_method,
                    ],
                    'receipt' => [
                        'id' => $result['receipt']->id,
                        'receipt_number' => $result['receipt']->receipt_number,
                        'receipt_type' => $result['receipt']->receipt_type,
                    ],
                    'pos_event' => [
                        'id' => $result['pos_event']->id,
                        'event_code' => $result['pos_event']->event_code,
                        'description' => $result['pos_event']->description,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format payments connected to a purchase
     * For most purchases, there will be one payment (the charge itself)
     * This is structured as an array to allow for future expansion (e.g., split payments)
     *
     * @param ConnectedCharge $purchase
     * @return array
     */
    protected function formatPurchasePayments(ConnectedCharge $purchase): array
    {
        $payments = [];

        // The charge itself represents the primary payment
        $payment = [
            'id' => $purchase->id,
            'stripe_charge_id' => $purchase->stripe_charge_id,
            'stripe_payment_intent_id' => $purchase->stripe_payment_intent_id,
            'amount' => $purchase->amount,
            'amount_refunded' => $purchase->amount_refunded,
            'currency' => $purchase->currency,
            'status' => $purchase->status,
            'method' => $purchase->payment_method,
            'code' => $purchase->payment_code,
            'transaction_code' => $purchase->transaction_code,
            'captured' => $purchase->captured,
            'refunded' => $purchase->refunded,
            'paid' => $purchase->paid,
            'paid_at' => $this->formatDateTimeOslo($purchase->paid_at),
            'tip_amount' => $purchase->tip_amount ?? null,
            'application_fee_amount' => $purchase->application_fee_amount ?? null,
            'description' => $purchase->description,
            'failure_code' => $purchase->failure_code,
            'failure_message' => $purchase->failure_message,
            'created_at' => $this->formatDateTimeOslo($purchase->created_at),
        ];

        // If there's a payment intent, try to get additional details
        if ($purchase->stripe_payment_intent_id) {
            $paymentIntent = \App\Models\ConnectedPaymentIntent::where('stripe_id', $purchase->stripe_payment_intent_id)
                ->where('stripe_account_id', $purchase->stripe_account_id)
                ->first();

            if ($paymentIntent) {
                $payment['payment_method_id'] = $paymentIntent->stripe_payment_method_id;
                $payment['capture_method'] = $paymentIntent->capture_method;
                $payment['confirmation_method'] = $paymentIntent->confirmation_method;
                $payment['receipt_email'] = $paymentIntent->receipt_email;
                $payment['statement_descriptor'] = $paymentIntent->statement_descriptor;
                $payment['succeeded_at'] = $paymentIntent->succeeded_at ? $this->formatDateTimeOslo($paymentIntent->succeeded_at) : null;
            }
        }

        $payments[] = $payment;

        return $payments;
    }
}
