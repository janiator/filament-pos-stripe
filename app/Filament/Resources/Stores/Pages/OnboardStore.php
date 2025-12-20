<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Actions\Stores\SyncStoreToStripe;
use App\Filament\Resources\Stores\Schemas\OnboardStoreWizard;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class OnboardStore extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = StoreResource::class;

    protected string $view = 'filament.resources.stores.pages.onboard-store';

    public ?array $data = [];

    public ?Store $store = null;

    public function mount(): void
    {
        // Only allow super admins
        $user = auth()->user();
        if (!$user || !$this->isSuperAdmin($user)) {
            abort(403, 'Only super admins can onboard new stores.');
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return OnboardStoreWizard::configure($schema);
    }

    public function completeSetup(): void
    {
        try {
            // Validate the form first
            $this->form->validate();

            $data = $this->form->getState();

            DB::beginTransaction();

            // Create the store
            $store = Store::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'organisasjonsnummer' => $data['organisasjonsnummer'] ?? null,
                'commission_type' => $data['commission_type'],
                'commission_rate' => $data['commission_rate'],
                'slug' => Str::slug($data['name']),
            ]);

            // Handle Stripe account setup
            if ($data['stripe_setup_type'] === 'link') {
                // Validate and link existing account
                $stripeAccountId = $data['stripe_account_id'] ?? null;
                if ($stripeAccountId) {
                    $this->validateStripeAccount($stripeAccountId);
                    $store->stripe_account_id = $stripeAccountId;
                    $store->save();
                }
            } else {
                // Create new Stripe account
                $this->createStripeAccount($store);
            }

            // Create settings
            Setting::create([
                'store_id' => $store->id,
                'currency' => $data['currency'] ?? 'nok',
                'timezone' => $data['timezone'] ?? 'Europe/Oslo',
                'locale' => $data['locale'] ?? 'nb',
                'default_vat_rate' => $data['default_vat_rate'] ?? 25.00,
                'tax_included' => $data['tax_included'] ?? false,
                'tips_enabled' => $data['tips_enabled'] ?? true,
                'auto_print_receipts' => config('pos.auto_print_receipts', false),
                'cash_drawer_auto_open' => config('pos.cash_drawer.auto_open', true),
                'cash_drawer_open_duration_ms' => config('pos.cash_drawer.open_duration_ms', 250),
                'receipt_printer_type' => config('receipts.printer_type', 'epson'),
                'receipt_number_format' => config('receipts.number_format', '{store_id}-{type}-{number:06d}'),
            ]);

            // Attach users to store
            $userIds = $data['user_ids'] ?? [];
            if (!empty($userIds)) {
                $store->users()->attach($userIds);
                
                // Set the first user's current store if they don't have one
                foreach ($userIds as $userId) {
                    $user = User::find($userId);
                    if ($user && !$user->current_store_id) {
                        $user->current_store_id = $store->id;
                        $user->save();
                    }
                }
            }

            DB::commit();

            Notification::make()
                ->success()
                ->title('Store onboarded successfully')
                ->body("Store '{$store->name}' has been set up and is ready to use.")
                ->send();

            // Redirect to the store view page
            $this->redirect(StoreResource::getUrl('view', ['record' => $store]));

        } catch (Throwable $e) {
            DB::rollBack();

            Notification::make()
                ->danger()
                ->title('Failed to onboard store')
                ->body($e->getMessage())
                ->send();

            report($e);
        }
    }

    protected function createStripeAccount(Store $store): void
    {
        $secret = config('cashier.secret') ?? config('services.stripe.secret');

        if (!$secret) {
            throw new \Exception('Stripe secret key is not configured.');
        }

        $stripe = new StripeClient($secret);

        try {
            $account = $stripe->accounts->create([
                'type' => 'standard',
                'email' => $store->email,
                'business_profile' => [
                    'name' => $store->name,
                ],
            ]);

            $store->stripe_account_id = $account->id;
            $store->save();
        } catch (ApiErrorException $e) {
            throw new \Exception("Failed to create Stripe account: {$e->getMessage()}");
        }
    }

    protected function validateStripeAccount(string $stripeAccountId): void
    {
        $secret = config('cashier.secret') ?? config('services.stripe.secret');

        if (!$secret) {
            throw new \Exception('Stripe secret key is not configured.');
        }

        $stripe = new StripeClient($secret);

        try {
            $account = $stripe->accounts->retrieve($stripeAccountId);

            // Check if account is already linked to another store
            $existingStore = Store::where('stripe_account_id', $stripeAccountId)->first();
            if ($existingStore) {
                throw new \Exception("This Stripe account is already linked to store: {$existingStore->name}");
            }
        } catch (ApiErrorException $e) {
            if ($e->getStripeCode() === 'resource_missing') {
                throw new \Exception("Stripe account '{$stripeAccountId}' not found. Please check the account ID.");
            }
            throw new \Exception("Failed to validate Stripe account: {$e->getMessage()}");
        }
    }

    protected function isSuperAdmin($user): bool
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant) {
                return $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists();
            }
            return $user->hasRole('super_admin');
        } catch (\Throwable $e) {
            return $user->hasRole('super_admin');
        }
    }

    public function getReviewData(): array
    {
        $data = $this->form->getState();

        $userIds = $data['user_ids'] ?? [];
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'email']);

        return [
            'store' => [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
                'organisasjonsnummer' => $data['organisasjonsnummer'] ?? null,
            ],
            'commission' => [
                'type' => $data['commission_type'] ?? 'percentage',
                'rate' => $data['commission_rate'] ?? 0,
            ],
            'stripe' => [
                'setup_type' => $data['stripe_setup_type'] ?? 'create',
                'account_id' => $data['stripe_account_id'] ?? null,
            ],
            'settings' => [
                'currency' => $data['currency'] ?? 'nok',
                'timezone' => $data['timezone'] ?? 'Europe/Oslo',
                'locale' => $data['locale'] ?? 'nb',
                'default_vat_rate' => $data['default_vat_rate'] ?? 25.00,
                'tax_included' => $data['tax_included'] ?? false,
                'tips_enabled' => $data['tips_enabled'] ?? true,
            ],
            'users' => $users,
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('completeSetup')
                ->label('Complete Setup')
                ->color('success')
                ->size('lg')
                ->action('completeSetup')
                ->requiresConfirmation()
                ->modalHeading('Complete Store Onboarding')
                ->modalDescription('Are you sure you want to complete the store setup? This will create the store with all configured settings.')
                ->modalSubmitActionLabel('Yes, Complete Setup'),
        ];
    }

    public function getCachedFormActions(): array
    {
        return $this->getFormActions();
    }

    public function hasFullWidthFormActions(): bool
    {
        return false;
    }
}



