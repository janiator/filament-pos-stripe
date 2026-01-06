<?php

namespace App\Actions\ConnectedPaymentLinks;

use App\Models\ConnectedPaymentLink;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;
use Illuminate\Support\Facades\Log;

class UpdateConnectedPaymentLinkInStripe
{
    public function __invoke(ConnectedPaymentLink $paymentLink, bool $notify = false): bool
    {
        try {
            if (! $paymentLink->stripe_payment_link_id) {
                if ($notify) {
                    Notification::make()
                        ->title('Payment link update failed')
                        ->body('Payment link does not have a Stripe ID.')
                        ->danger()
                        ->send();
                }
                return false;
            }

            $store = Store::where('stripe_account_id', $paymentLink->stripe_account_id)->first();

            if (! $store || ! $store->hasStripeAccount()) {
                if ($notify) {
                    Notification::make()
                        ->title('Store not connected')
                        ->body('This store is not connected to Stripe.')
                        ->danger()
                        ->send();
                }
                return false;
            }

            $secret = config('cashier.secret') ?? config('services.stripe.secret');

            if (! $secret) {
                if ($notify) {
                    Notification::make()
                        ->title('Stripe not configured')
                        ->body('No Stripe secret key found.')
                        ->danger()
                        ->send();
                }
                return false;
            }

            $stripe = new StripeClient($secret);

            // Update payment link active status in Stripe
            $stripe->paymentLinks->update(
                $paymentLink->stripe_payment_link_id,
                [
                    'active' => $paymentLink->active,
                ],
                ['stripe_account' => $store->stripe_account_id]
            );

            if ($notify) {
                $status = $paymentLink->active ? 'activated' : 'deactivated';
                Notification::make()
                    ->title('Payment link updated')
                    ->body("Payment link has been {$status} in Stripe.")
                    ->success()
                    ->send();
            }

            Log::info('Updated payment link in Stripe', [
                'payment_link_id' => $paymentLink->id,
                'stripe_payment_link_id' => $paymentLink->stripe_payment_link_id,
                'active' => $paymentLink->active,
            ]);

            return true;
        } catch (AccountNotFoundException $e) {
            if ($notify) {
                Notification::make()
                    ->title('Payment link update failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            Log::error('Failed to update payment link in Stripe', [
                'payment_link_id' => $paymentLink->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $errorMessage = $e->getMessage();

            if ($notify) {
                Notification::make()
                    ->title('Payment link update failed')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }

            Log::error('Failed to update payment link in Stripe', [
                'error' => $errorMessage,
                'stripe_error' => $e->getMessage(),
                'payment_link_id' => $paymentLink->id,
            ]);

            report($e);
            return false;
        } catch (Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Payment link update failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            Log::error('Failed to update payment link in Stripe', [
                'error' => $e->getMessage(),
                'payment_link_id' => $paymentLink->id,
            ]);

            report($e);
            return false;
        }
    }
}
