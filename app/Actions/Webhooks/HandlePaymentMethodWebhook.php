<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedPaymentMethod;
use App\Models\Store;
use Stripe\PaymentMethod;

class HandlePaymentMethodWebhook
{
    public function handle(PaymentMethod $paymentMethod, string $eventType, ?string $accountId = null): void
    {
        if (! $accountId) {
            \Log::warning('Payment method webhook received but no account ID provided', [
                'payment_method_id' => $paymentMethod->id,
            ]);

            return;
        }

        $store = Store::where('stripe_account_id', $accountId)->first();

        if (! $store) {
            \Log::warning('Payment method webhook received but store not found', [
                'payment_method_id' => $paymentMethod->id,
                'account_id' => $accountId,
            ]);

            return;
        }

        $isDetachEvent = $eventType === 'payment_method.detached';

        $existingPaymentMethod = ConnectedPaymentMethod::query()
            ->where('stripe_payment_method_id', $paymentMethod->id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->first();

        if ($isDetachEvent) {
            $resolvedStripeCustomerId = null;
        } else {
            $resolvedStripeCustomerId = $paymentMethod->customer ?: $existingPaymentMethod?->stripe_customer_id;
        }

        if (! $isDetachEvent && ! $resolvedStripeCustomerId) {
            \Log::warning('Skipping payment method webhook because stripe customer id is missing', [
                'payment_method_id' => $paymentMethod->id,
                'event_type' => $eventType,
                'account_id' => $store->stripe_account_id,
            ]);

            return;
        }

        $data = [
            'stripe_payment_method_id' => $paymentMethod->id,
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_customer_id' => $resolvedStripeCustomerId,
            'type' => $paymentMethod->type,
            'card_brand' => $paymentMethod->card->brand ?? null,
            'card_last4' => $paymentMethod->card->last4 ?? null,
            'card_exp_month' => $paymentMethod->card->exp_month ?? null,
            'card_exp_year' => $paymentMethod->card->exp_year ?? null,
            'metadata' => $this->stripeObjectToPlainArray($paymentMethod->metadata),
        ];

        if ($existingPaymentMethod) {
            $existingPaymentMethod->fill($data);
            $existingPaymentMethod->save();

            return;
        }

        if ($isDetachEvent) {
            return;
        }

        ConnectedPaymentMethod::query()->create($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stripeObjectToPlainArray(mixed $obj): ?array
    {
        if ($obj === null) {
            return null;
        }

        if (is_object($obj) && method_exists($obj, 'toArray')) {
            $arr = $obj->toArray();

            return is_array($arr) ? $arr : null;
        }

        $encoded = json_encode($obj);

        if ($encoded === false) {
            return null;
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : null;
    }
}
