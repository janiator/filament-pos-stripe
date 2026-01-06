<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Stores\HandleStripeAccountWebhook;
use App\Actions\Webhooks\HandleChargeWebhook;
use App\Actions\Webhooks\HandleCustomerWebhook;
use App\Actions\Webhooks\HandlePaymentLinkWebhook;
use App\Actions\Webhooks\HandlePaymentMethodWebhook;
use App\Actions\Webhooks\HandlePriceWebhook;
use App\Actions\Webhooks\HandleProductWebhook;
use App\Actions\Webhooks\HandleSubscriptionWebhook;
use App\Actions\Webhooks\HandleTransferWebhook;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentLink;
use Stripe\PaymentMethod;
use Stripe\Price;
use Stripe\Product;
use Stripe\Subscription;
use Stripe\Transfer;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeConnectWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        HandleStripeAccountWebhook $accountHandler,
        HandleChargeWebhook $chargeHandler,
        HandleCustomerWebhook $customerHandler,
        HandleSubscriptionWebhook $subscriptionHandler,
        HandleProductWebhook $productHandler,
        HandlePriceWebhook $priceHandler,
        HandlePaymentMethodWebhook $paymentMethodHandler,
        HandlePaymentLinkWebhook $paymentLinkHandler,
        HandleTransferWebhook $transferHandler
    ) {
        // Get raw payload - must be raw content, not parsed JSON
        // Use getContent() first, fallback to php://input if empty (in case middleware consumed it)
        $payload = $request->getContent();
        if (empty($payload)) {
            $payload = file_get_contents('php://input');
        }
        
        // Get signature header - check multiple sources
        // Laravel normalizes headers, but Stripe sends "Stripe-Signature"
        // Try to get raw headers first (before Laravel processing)
        $signature = null;
        
        // Method 1: Try PHP's native getallheaders() function (if available)
        if (function_exists('getallheaders')) {
            $rawHeaders = getallheaders();
            if ($rawHeaders) {
                foreach ($rawHeaders as $key => $value) {
                    if (strtolower($key) === 'stripe-signature') {
                        $signature = $value;
                        break;
                    }
                }
            }
        }
        
        // Method 2: Try apache_request_headers() (Apache-specific)
        if (empty($signature) && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if ($apacheHeaders) {
                foreach ($apacheHeaders as $key => $value) {
                    if (strtolower($key) === 'stripe-signature') {
                        $signature = $value;
                        break;
                    }
                }
            }
        }
        
        // Method 3: Check Laravel's header bag
        if (empty($signature)) {
            $signature = $request->header('Stripe-Signature');
        }
        
        // Method 4: Check $_SERVER directly (Laravel converts headers to HTTP_* format)
        if (empty($signature)) {
            // Check HTTP_STRIPE_SIGNATURE (Laravel converts "Stripe-Signature" to "HTTP_STRIPE_SIGNATURE")
            $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;
        }
        
        // Method 5: Check all Laravel headers case-insensitively
        if (empty($signature)) {
            $allHeaders = $request->headers->all();
            foreach ($allHeaders as $key => $value) {
                if (strtolower(str_replace('_', '-', $key)) === 'stripe-signature') {
                    $signature = is_array($value) ? ($value[0] ?? null) : $value;
                    break;
                }
            }
        }
        
        // Method 6: Last resort - check $_SERVER for any variation
        if (empty($signature)) {
            foreach ($_SERVER as $key => $value) {
                if (stripos($key, 'STRIPE_SIGNATURE') !== false || stripos($key, 'STRIPE-SIGNATURE') !== false) {
                    $signature = $value;
                    break;
                }
            }
        }
        
        // Trim whitespace from signature if found
        if (!empty($signature)) {
            $signature = trim($signature);
        }
        
        // Check if signature is empty or just whitespace
        // empty() will catch null, false, 0, '', '0', [], etc.
        // But we also want to catch strings that are only whitespace
        $signatureIsValid = !empty($signature) && strlen(trim($signature ?? '')) > 0;

        // Validate signature header is present and not empty
        if (!$signatureIsValid) {
            // Collect all HTTP headers from $_SERVER for debugging
            $httpHeaders = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0 || stripos($key, 'STRIPE') !== false) {
                    $httpHeaders[$key] = $value;
                }
            }
            
            // Get all HTTP_* keys for debugging
            $httpKeys = [];
            foreach (array_keys($_SERVER) as $key) {
                if (strpos($key, 'HTTP_') === 0) {
                    $httpKeys[] = $key;
                }
            }
            
            // Log comprehensive debugging information
            \Log::error('Stripe webhook signature header missing', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'laravel_headers' => $request->headers->all(),
                'server_headers' => $httpHeaders,
                'has_content' => !empty($payload),
                'content_length' => strlen($payload ?? ''),
                'content_type' => $request->header('Content-Type'),
                'all_http_keys' => $httpKeys,
            ]);
            // Return 400 (Bad Request) - this indicates the request is malformed
            // Note: If you're seeing 403 (Forbidden) in Stripe's dashboard, it might be:
            // 1. A proxy/load balancer (like Herd's Nginx) stripping the header
            // 2. Server-level configuration blocking the request
            // 3. The header being stripped before it reaches Laravel
            return response()->json([
                'message' => 'No signatures found matching the expected signature for payload',
            ], 400);
        }

        // Configure this in .env
        // Cashier For Connect uses CONNECT_WEBHOOK_SECRET, but we also support STRIPE_CONNECT_WEBHOOK_SECRET
        $secret =
            config('cashierconnect.webhook.secret')
            ?? config('services.stripe.connect_webhook_secret')
            ?? env('CONNECT_WEBHOOK_SECRET')
            ?? env('STRIPE_CONNECT_WEBHOOK_SECRET')
            ?? config('services.stripe.webhook_secret')
            ?? env('STRIPE_WEBHOOK_SECRET');

        if (! $secret) {
            // Misconfiguration – don't throw to Stripe, just log.
            \Log::error('Stripe webhook secret not configured');
            report(new \RuntimeException('Stripe webhook secret not configured.'));
            return response()->json([
                'message' => 'Webhook misconfigured',
            ], 500);
        }

        // Validate payload is not empty
        if (empty($payload)) {
            \Log::error('Stripe webhook payload is empty', [
                'signature' => $signature ? 'present' : 'missing',
            ]);
            return response()->json([
                'message' => 'Invalid payload',
            ], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret
            );
        } catch (UnexpectedValueException $e) {
            // Invalid payload
            \Log::error('Stripe webhook invalid payload', [
                'error' => $e->getMessage(),
                'payload_length' => strlen($payload),
                'signature_present' => !empty($signature),
            ]);
            return response()->json([
                'message' => 'Invalid payload',
            ], 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature - log with signature format info (truncated for security)
            $signaturePreview = $signature ? (substr($signature, 0, 50) . '...') : 'missing';
            $signatureParts = $signature ? explode(',', $signature) : [];
            
            // Check if the error is about missing signatures (this should have been caught earlier)
            $isMissingSignatureError = stripos($e->getMessage(), 'No signatures found') !== false;
            
            \Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'is_missing_signature_error' => $isMissingSignatureError,
                'signature_present' => !empty($signature),
                'signature_length' => strlen($signature ?? ''),
                'signature_preview' => $signaturePreview,
                'signature_parts_count' => count($signatureParts),
                'payload_length' => strlen($payload),
                'secret_configured' => !empty($secret),
                'secret_length' => strlen($secret ?? ''),
            ]);
            
            // Return the exact error message from Stripe
            // Note: If you see 403 in Stripe dashboard but we return 400, check:
            // 1. Proxy/load balancer converting status codes
            // 2. Server-level configuration blocking requests
            // 3. Middleware returning 403 before reaching this controller
            return response()->json([
                'message' => $e->getMessage() ?: 'Invalid signature',
            ], 400);
        }

        // Get the account ID from the event (for Connect webhooks)
        $accountId = $event->account ?? null;

        // Log webhook event for debugging
        \Log::info('Stripe webhook event received', [
            'event_type' => $event->type,
            'event_id' => $event->id,
            'account_id_from_event' => $accountId,
            'livemode' => $event->livemode ?? false,
            'api_version' => $event->api_version ?? null,
        ]);

        // Warn if account ID is missing for Connect webhooks (except account events)
        if (!$accountId && !in_array($event->type, ['account.created', 'account.updated', 'account.deleted'])) {
            \Log::warning('Stripe webhook event missing account ID - may not be a Connect webhook', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'note' => 'This webhook endpoint is configured for Stripe Connect. If account_id is null, the webhook may be from the platform account instead of a connected account.',
            ]);
        }

        // Optionally, you could check $event->livemode vs app env.

        // Track processing result for response
        $result = [
            'received' => true,
            'event_type' => $event->type,
            'event_id' => $event->id,
            'account_id' => $accountId,
            'processed' => false,
            'message' => null,
            'warnings' => [],
            'errors' => [],
        ];

        try {
            switch ($event->type) {
                // Account events
                case 'account.created':
                case 'account.updated':
                    /** @var Account $account */
                    $account = $event->data->object;
                    $accountHandler($account);
                    $result['processed'] = true;
                    $result['message'] = "Account {$account->id} processed successfully";
                    break;

                case 'account.deleted':
                    /** @var Account $account */
                    $account = $event->data->object;
                    $accountHandler->handleDeleted($account);
                    $result['processed'] = true;
                    $result['message'] = "Account {$account->id} deletion processed";
                    break;

                // Customer events
                case 'customer.created':
                case 'customer.updated':
                    /** @var Customer $customer */
                    $customer = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event';
                    }
                    $customerHandler->handle($customer, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Customer {$customer->id} processed";
                    break;

                case 'customer.deleted':
                    // Handle customer deletion if needed
                    $result['processed'] = true;
                    $result['message'] = 'Customer deletion event received (not implemented)';
                    break;

                // Subscription events
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                case 'customer.subscription.paused':
                case 'customer.subscription.resumed':
                case 'customer.subscription.trial_will_end':
                    /** @var Subscription $subscription */
                    $subscription = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event';
                    }
                    $subscriptionHandler->handle($subscription, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Subscription {$subscription->id} ({$event->type}) processed";
                    break;

                // Product events
                case 'product.created':
                case 'product.updated':
                case 'product.deleted':
                    /** @var Product $product */
                    $product = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event';
                    }
                    $productHandler->handle($product, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Product {$product->id} ({$event->type}) processed";
                    break;

                // Price events
                case 'price.created':
                case 'price.updated':
                case 'price.deleted':
                    /** @var Price $price */
                    $price = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event';
                    }
                    $priceHandler->handle($price, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Price {$price->id} ({$event->type}) processed";
                    break;

                // Charge events
                case 'charge.created':
                case 'charge.updated':
                case 'charge.succeeded':
                case 'charge.pending':
                case 'charge.failed':
                case 'charge.captured':
                case 'charge.refunded':
                case 'charge.refund.updated':
                    /** @var Charge $charge */
                    $charge = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event, attempting to extract from charge object';
                    }
                    $chargeHandler->handle($charge, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Charge {$charge->id} ({$event->type}) processed";
                    break;

                // Payment method events
                case 'payment_method.attached':
                case 'payment_method.detached':
                case 'payment_method.updated':
                case 'payment_method.automatically_updated':
                    /** @var PaymentMethod $paymentMethod */
                    $paymentMethod = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event';
                    }
                    $paymentMethodHandler->handle($paymentMethod, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Payment method {$paymentMethod->id} ({$event->type}) processed";
                    break;

                // Payment link events
                case 'payment_link.created':
                case 'payment_link.updated':
                    /** @var PaymentLink $paymentLink */
                    $paymentLink = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event';
                    }
                    $paymentLinkHandler->handle($paymentLink, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Payment link {$paymentLink->id} ({$event->type}) processed";
                    break;

                // Transfer events
                case 'transfer.created':
                case 'transfer.updated':
                case 'transfer.reversed':
                case 'transfer.paid':
                case 'transfer.failed':
                    /** @var Transfer $transfer */
                    $transfer = $event->data->object;
                    if (!$accountId) {
                        $result['warnings'][] = 'No account ID found in event, attempting to extract from transfer object';
                    }
                    $transferHandler->handle($transfer, $event->type, $accountId);
                    $result['processed'] = true;
                    $result['message'] = "Transfer {$transfer->id} ({$event->type}) processed";
                    break;

                default:
                    // Log unhandled events for debugging
                    \Log::debug('Unhandled webhook event', [
                        'type' => $event->type,
                        'account_id' => $accountId,
                    ]);
                    $result['warnings'][] = "Event type '{$event->type}' is not handled";
                    $result['message'] = "Unhandled event type: {$event->type}";
                    break;
            }
        } catch (\Throwable $e) {
            \Log::error('Error handling webhook event', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            report($e);
            $result['errors'][] = $e->getMessage();
            $result['message'] = "Error processing {$event->type}: {$e->getMessage()}";
            // Still return 200 to Stripe to prevent retries, but include error in response
        }

        // Add warning if account ID was missing
        if (!$accountId && !in_array($event->type, ['account.created', 'account.updated', 'account.deleted'])) {
            $result['warnings'][] = 'No account ID found in event - this may indicate the webhook is from the platform account instead of a connected account';
        }

        // Ensure we have a message if none was set
        if (empty($result['message'])) {
            $result['message'] = $result['processed'] 
                ? "Event {$event->type} processed successfully" 
                : "Event {$event->type} received but not processed";
        }

        // Find store by account ID for logging
        $store = null;
        if ($accountId) {
            $store = \App\Models\Store::where('stripe_account_id', $accountId)->first();
        }

        // Save webhook log to database
        try {
            // Check if table exists before trying to save
            if (!\Illuminate\Support\Facades\Schema::hasTable('webhook_logs')) {
                \Log::warning('webhook_logs table does not exist - run migration first', [
                    'event_id' => $event->id,
                ]);
            } else {
                $webhookLog = \App\Models\WebhookLog::create([
                    'store_id' => $store?->id,
                    'stripe_account_id' => $accountId,
                    'event_type' => $event->type,
                    'event_id' => $event->id,
                    'account_id' => $accountId,
                    'processed' => $result['processed'],
                    'message' => $result['message'],
                    'warnings' => !empty($result['warnings']) ? $result['warnings'] : null,
                    'errors' => !empty($result['errors']) ? $result['errors'] : null,
                    'request_data' => [
                        'event_type' => $event->type,
                        'event_id' => $event->id,
                        'livemode' => $event->livemode ?? false,
                        'api_version' => $event->api_version ?? null,
                        'object_type' => get_class($event->data->object),
                    ],
                    'response_data' => $result,
                    'http_status_code' => 200,
                    'error_message' => !empty($result['errors']) ? implode('; ', $result['errors']) : null,
                ]);

                // Cleanup old records (keep max 100 per store)
                if ($store) {
                    \App\Models\WebhookLog::cleanupOldRecords($store->id);
                }
            }
        } catch (\Throwable $e) {
            // Log error but don't fail the webhook response
            \Log::error('Failed to save webhook log', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'event_id' => $event->id,
                'event_type' => $event->type,
                'account_id' => $accountId,
                'store_id' => $store?->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            report($e);
        }

        // Return response with explicit content type and headers
        // Note: Stripe dashboard may not always display response bodies for 200 status codes
        // but the data will be in the response for debugging purposes
        return response()->json($result, 200, [
            'Content-Type' => 'application/json',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
