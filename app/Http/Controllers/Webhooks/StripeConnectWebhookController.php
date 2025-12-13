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
        $secret =
            config('services.stripe.connect_webhook_secret')
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
            
            \Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'signature_present' => !empty($signature),
                'signature_length' => strlen($signature ?? ''),
                'signature_preview' => $signaturePreview,
                'signature_parts_count' => count($signatureParts),
                'payload_length' => strlen($payload),
                'secret_configured' => !empty($secret),
                'secret_length' => strlen($secret ?? ''),
            ]);
            return response()->json([
                'message' => $e->getMessage() ?: 'Invalid signature',
            ], 400);
        }

        // Get the account ID from the event (for Connect webhooks)
        $accountId = $event->account ?? null;

        // Optionally, you could check $event->livemode vs app env.

        try {
            switch ($event->type) {
                // Account events
                case 'account.created':
                case 'account.updated':
                    /** @var Account $account */
                    $account = $event->data->object;
                    $accountHandler($account);
                    break;

                case 'account.deleted':
                    /** @var Account $account */
                    $account = $event->data->object;
                    $accountHandler->handleDeleted($account);
                    break;

                // Customer events
                case 'customer.created':
                case 'customer.updated':
                    /** @var Customer $customer */
                    $customer = $event->data->object;
                    $customerHandler->handle($customer, $event->type, $accountId);
                    break;

                case 'customer.deleted':
                    // Handle customer deletion if needed
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
                    $subscriptionHandler->handle($subscription, $event->type, $accountId);
                    break;

                // Product events
                case 'product.created':
                case 'product.updated':
                case 'product.deleted':
                    /** @var Product $product */
                    $product = $event->data->object;
                    $productHandler->handle($product, $event->type, $accountId);
                    break;

                // Price events
                case 'price.created':
                case 'price.updated':
                case 'price.deleted':
                    /** @var Price $price */
                    $price = $event->data->object;
                    $priceHandler->handle($price, $event->type, $accountId);
                    break;

                // Charge events
                case 'charge.created':
                case 'charge.updated':
                case 'charge.refunded':
                case 'charge.refund.updated':
                    /** @var Charge $charge */
                    $charge = $event->data->object;
                    $chargeHandler->handle($charge, $event->type);
                    break;

                // Payment method events
                case 'payment_method.attached':
                case 'payment_method.detached':
                case 'payment_method.updated':
                case 'payment_method.automatically_updated':
                    /** @var PaymentMethod $paymentMethod */
                    $paymentMethod = $event->data->object;
                    $paymentMethodHandler->handle($paymentMethod, $event->type, $accountId);
                    break;

                // Payment link events
                case 'payment_link.created':
                case 'payment_link.updated':
                    /** @var PaymentLink $paymentLink */
                    $paymentLink = $event->data->object;
                    $paymentLinkHandler->handle($paymentLink, $event->type, $accountId);
                    break;

                // Transfer events
                case 'transfer.created':
                case 'transfer.updated':
                case 'transfer.reversed':
                case 'transfer.paid':
                case 'transfer.failed':
                    /** @var Transfer $transfer */
                    $transfer = $event->data->object;
                    $transferHandler->handle($transfer, $event->type, $accountId);
                    break;

                default:
                    // Log unhandled events for debugging
                    \Log::debug('Unhandled webhook event', [
                        'type' => $event->type,
                        'account_id' => $accountId,
                    ]);
                    break;
            }
        } catch (\Throwable $e) {
            \Log::error('Error handling webhook event', [
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            report($e);
            // Still return 200 to Stripe to prevent retries
        }

        return response()->json(['received' => true]);
    }
}
