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
        $validator = Validator::make($request->all(), [
            'customer_id' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customerId = $request->input('customer_id');

        // If customer_id is provided, verify customer exists and belongs to the store
        if ($customerId !== null) {
            $customer = \App\Models\ConnectedCustomer::where('stripe_account_id', $store->stripe_account_id)
                ->where('stripe_customer_id', $customerId)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }
        }

        // Update the purchase with the customer (or set to null to remove)
        $purchase->update([
            'stripe_customer_id' => $customerId,
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
        if ($purchase->customer) {
            // Format address as string if available
            $addressString = null;
            if ($purchase->customer->address && is_array($purchase->customer->address)) {
                $addressParts = array_filter([
                    $purchase->customer->address['line1'] ?? null,
                    $purchase->customer->address['line2'] ?? null,
                    $purchase->customer->address['city'] ?? null,
                    $purchase->customer->address['state'] ?? null,
                    $purchase->customer->address['postal_code'] ?? null,
                    $purchase->customer->address['country'] ?? null,
                ]);
                $addressString = !empty($addressParts) ? implode(', ', $addressParts) : null;
            }
            
            $data['purchase_customer'] = [
                'id' => $purchase->customer->id,
                'stripe_customer_id' => $purchase->customer->stripe_customer_id,
                'name' => $purchase->customer->name,
                'email' => $purchase->customer->email,
                'phone' => $purchase->customer->phone,
                'address' => $addressString,
            ];
        } else {
            $data['purchase_customer'] = [
                'id' => null,
                'stripe_customer_id' => null,
                'name' => null,
                'email' => null,
                'phone' => null,
                'address' => null,
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
        } else {
            $data['purchase_discounts'] = [];
        }

        // Add purchase totals - use calculated values if metadata values are 0 or missing
        $metadataSubtotal = isset($cleanMetadata['subtotal']) ? (int) $cleanMetadata['subtotal'] : 0;
        $metadataDiscounts = isset($cleanMetadata['total_discounts']) ? (int) $cleanMetadata['total_discounts'] : 0;
        $metadataTax = isset($cleanMetadata['total_tax']) ? (int) $cleanMetadata['total_tax'] : 0;
        $metadataTip = isset($cleanMetadata['tip_amount']) ? (int) $cleanMetadata['tip_amount'] : 0;
        
        // Use calculated subtotal if metadata subtotal is 0 or missing
        $data['purchase_subtotal'] = ($metadataSubtotal > 0) ? $metadataSubtotal : $calculatedSubtotal;
        
        // Use calculated discounts if metadata discounts are 0 or missing
        $data['purchase_total_discounts'] = ($metadataDiscounts > 0) ? $metadataDiscounts : $calculatedDiscounts;
        
        // Use metadata tax (or 0 if not set)
        $data['purchase_total_tax'] = $metadataTax;
        
        // Use metadata tip (or 0 if not set)
        $data['purchase_tip_amount'] = $metadataTip;
        
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
                            now()->addHour(),
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
