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
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Configure this in .env
        $secret =
            config('services.stripe.connect_webhook_secret')
            ?? config('services.stripe.webhook_secret')
            ?? env('STRIPE_WEBHOOK_SECRET');

        if (! $secret) {
            // Misconfiguration – don't throw to Stripe, just log.
            report(new \RuntimeException('Stripe webhook secret not configured.'));
            return response('Webhook misconfigured', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret
            );
        } catch (UnexpectedValueException $e) {
            // Invalid payload
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            return response('Invalid signature', 400);
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
