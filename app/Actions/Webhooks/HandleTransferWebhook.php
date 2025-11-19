<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedTransfer;
use App\Models\Store;
use Stripe\Transfer;

class HandleTransferWebhook
{
    public function handle(Transfer $transfer, string $eventType, ?string $accountId = null): void
    {
        // For transfers, the destination is the connected account
        $accountId = $accountId ?? $transfer->destination;
        
        if (!$accountId) {
            \Log::warning('Transfer webhook received but no account ID found', [
                'transfer_id' => $transfer->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            \Log::warning('Transfer webhook received but store not found', [
                'transfer_id' => $transfer->id,
                'account_id' => $accountId,
            ]);
            return;
        }

        $data = [
            'stripe_transfer_id' => $transfer->id,
            'stripe_account_id' => $store->stripe_account_id,
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'status' => $transfer->status ?? 'pending',
            'description' => $transfer->description,
            'metadata' => $transfer->metadata ? (array) $transfer->metadata : null,
        ];

        ConnectedTransfer::updateOrCreate(
            [
                'stripe_transfer_id' => $transfer->id,
                'stripe_account_id' => $store->stripe_account_id,
            ],
            $data
        );
    }
}

