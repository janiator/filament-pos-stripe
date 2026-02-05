<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedCharge;
use App\Models\Store;
use Stripe\Charge;

/**
 * Processes Stripe charge webhooks. Looks up by stripe_payment_intent_id first when
 * present; when found, updates that row and preserves pos_session_id and other POS
 * fields so webhook and POS-created charges are merged without duplicates or lost session.
 */
class HandleChargeWebhook
{
    /** @var list<string> POS-only fields never overwritten by webhook when existing value is non-null (same as SyncConnectedChargesFromStripe). */
    private const PRESERVED_POS_FIELDS = [
        'pos_session_id',
        'transaction_code',
        'payment_code',
        'tip_amount',
        'article_group_code',
    ];

    public function handle(Charge $charge, string $eventType, ?string $accountId = null): void
    {
        // Find the store by stripe_account_id from the event or charge
        // For Connect webhooks, the account ID comes from the event
        // Fallback to charge fields for direct/destination charges
        if (! $accountId) {
            $accountId = $charge->on_behalf_of ?? $charge->destination;
        }

        if (! $accountId) {
            \Log::warning('Charge webhook received but no account ID found', [
                'charge_id' => $charge->id,
                'event_type' => $eventType,
                'charge_on_behalf_of' => $charge->on_behalf_of ?? null,
                'charge_destination' => $charge->destination ?? null,
            ]);

            return;
        }

        $store = Store::where('stripe_account_id', $accountId)->first();

        if (! $store) {
            // Log available stores for debugging
            $availableStores = Store::whereNotNull('stripe_account_id')
                ->pluck('stripe_account_id', 'id')
                ->toArray();

            \Log::warning('Charge webhook received but store not found', [
                'charge_id' => $charge->id,
                'account_id' => $accountId,
                'available_stores' => array_keys($availableStores),
                'available_account_ids' => array_values($availableStores),
                'charge_object_keys' => array_keys((array) $charge),
            ]);

            return;
        }

        \Log::info('Processing charge webhook', [
            'charge_id' => $charge->id,
            'account_id' => $accountId,
            'store_id' => $store->id,
            'event_type' => $eventType,
        ]);

        $data = $this->buildChargeData($charge, $store->stripe_account_id);

        // Look up by payment_intent_id first when present: merge into existing row and preserve POS fields
        if ($charge->payment_intent) {
            $existing = ConnectedCharge::where('stripe_payment_intent_id', $charge->payment_intent)
                ->where('stripe_account_id', $store->stripe_account_id)
                ->first();

            if ($existing) {
                $existing->fill($data);
                $existing->stripe_account_id = $store->stripe_account_id;
                foreach (self::PRESERVED_POS_FIELDS as $field) {
                    if ($existing->getRawOriginal($field) !== null) {
                        $existing->$field = $existing->getRawOriginal($field);
                    }
                }
                $existing->save();

                \Log::info('Charge webhook merged by payment_intent_id', [
                    'charge_id' => $charge->id,
                    'charge_record_id' => $existing->id,
                ]);

                return;
            }
        }

        // No row found by payment_intent_id: updateOrCreate by (stripe_charge_id, stripe_account_id)
        $chargeRecord = ConnectedCharge::where('stripe_charge_id', $charge->id)
            ->where('stripe_account_id', $store->stripe_account_id)
            ->first();

        $wasCreated = false;
        if ($chargeRecord) {
            $chargeRecord->fill($data);
            $chargeRecord->stripe_account_id = $store->stripe_account_id;
            foreach (self::PRESERVED_POS_FIELDS as $field) {
                if ($chargeRecord->getRawOriginal($field) !== null) {
                    $chargeRecord->$field = $chargeRecord->getRawOriginal($field);
                }
            }
            $chargeRecord->save();
        } else {
            $chargeRecord = ConnectedCharge::create($data);
            $wasCreated = true;
        }

        \Log::info('Charge webhook processed successfully', [
            'charge_id' => $charge->id,
            'charge_record_id' => $chargeRecord->id,
            'was_created' => $wasCreated,
        ]);
    }

    /**
     * Build webhook payload (Stripe fields only). metadata/outcome are stored as plain
     * arrays to avoid persisting Stripe SDK internals (e.g. \0*\0_opts).
     *
     * @return array<string, mixed>
     */
    private function buildChargeData(Charge $charge, string $stripeAccountId): array
    {
        return [
            'stripe_charge_id' => $charge->id,
            'stripe_account_id' => $stripeAccountId,
            'stripe_customer_id' => $charge->customer ?? null,
            'stripe_payment_intent_id' => $charge->payment_intent ?? null,
            'amount' => $charge->amount,
            'amount_refunded' => $charge->amount_refunded ?? 0,
            'currency' => $charge->currency,
            'status' => $charge->status,
            'payment_method' => $charge->payment_method_details?->type ?? null,
            'description' => $charge->description,
            'failure_code' => $charge->failure_code,
            'failure_message' => $charge->failure_message,
            'captured' => $charge->captured ?? true,
            'refunded' => $charge->refunded ?? false,
            'paid' => $charge->paid ?? false,
            'paid_at' => ($charge->paid && $charge->created) ? date('Y-m-d H:i:s', $charge->created) : null,
            'metadata' => $this->stripeObjectToPlainArray($charge->metadata),
            'outcome' => $this->stripeObjectToPlainArray($charge->outcome),
            'charge_type' => $charge->on_behalf_of ? 'destination' : 'direct',
            'application_fee_amount' => $charge->application_fee_amount ?? null,
        ];
    }

    /**
     * Convert Stripe object to plain key/value array so we do not persist SDK internals.
     *
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
