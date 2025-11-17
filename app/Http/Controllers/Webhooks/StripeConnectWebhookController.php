<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Stores\HandleStripeAccountWebhook;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stripe\Account;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeConnectWebhookController extends Controller
{
    public function __invoke(Request $request, HandleStripeAccountWebhook $handler)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Configure this in .env
        $secret =
            config('services.stripe.connect_webhook_secret')
            ?? config('services.stripe.webhook_secret')
            ?? env('STRIPE_WEBHOOK_SECRET');

        if (! $secret) {
            // Misconfiguration – don’t throw to Stripe, just log.
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

        // Optionally, you could check $event->livemode vs app env.

        switch ($event->type) {
            case 'account.created':
            case 'account.updated':
                /** @var Account $account */
                $account = $event->data->object;
                $handler($account);
                break;

            case 'account.deleted':
                /** @var Account $account */
                $account = $event->data->object;
                $handler->handleDeleted($account);
                break;

            default:
                // Ignore other event types
                break;
        }

        return response()->json(['received' => true]);
    }
}
