<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable as CashierBillable;
use Lanos\CashierConnect\Billable as ConnectBillable;
use Lanos\CashierConnect\Contracts\StripeAccount;

class Store extends Model implements StripeAccount
{
    use HasFactory;
    use CashierBillable;
    use ConnectBillable;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'z_report_email',
        'organisasjonsnummer',
        'logo_path',
        'commission_type',
        'commission_rate',
        'stripe_account_id',
    ];

    protected $casts = [
        'commission_rate' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->slug)) {
                $store->slug = Str::slug($store->name ?? 'store-' . $store->id);
            }
        });

        static::saved(function (Store $store) {
            // Use saved event to ensure it fires for both create and update
            // Only sync on update (not create)
            if ($store->wasRecentlyCreated) {
                return;
            }
            
            $listener = new \App\Listeners\SyncStoreToStripeListener();
            $listener->handle($store);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function terminalLocations()
    {
        return $this->hasMany(\App\Models\TerminalLocation::class);
    }

    public function terminalReaders()
    {
        return $this->hasMany(\App\Models\TerminalReader::class);
    }

    /**
     * Get POS devices for this store
     */
    public function posDevices()
    {
        return $this->hasMany(\App\Models\PosDevice::class);
    }

    /**
     * Get POS sessions for this store
     */
    public function posSessions()
    {
        return $this->hasMany(\App\Models\PosSession::class);
    }

    /**
     * Get POS session closings for this store
     */
    public function posSessionClosings()
    {
        return $this->hasMany(\App\Models\PosSessionClosing::class);
    }

    /**
     * Get the connected charges for this store
     */
    public function connectedCharges()
    {
        return $this->hasMany(\App\Models\ConnectedCharge::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected transfers for this store
     */
    public function connectedTransfers()
    {
        return $this->hasMany(\App\Models\ConnectedTransfer::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected payment methods for this store
     */
    public function connectedPaymentMethods()
    {
        return $this->hasMany(\App\Models\ConnectedPaymentMethod::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected payment links for this store
     */
    public function connectedPaymentLinks()
    {
        return $this->hasMany(\App\Models\ConnectedPaymentLink::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected customers for this store
     */
    public function connectedCustomers()
    {
        return $this->hasMany(\App\Models\ConnectedCustomer::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected subscriptions for this store
     */
    public function connectedSubscriptions()
    {
        return $this->hasMany(\App\Models\ConnectedSubscription::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected products for this store
     */
    public function connectedProducts()
    {
        return $this->hasMany(\App\Models\ConnectedProduct::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the discounts for this store
     */
    public function discounts()
    {
        return $this->hasMany(\App\Models\Discount::class);
    }

    /**
     * Get the coupons for this store
     */
    public function coupons()
    {
        return $this->hasMany(\App\Models\Coupon::class);
    }

    /**
     * Get the payment methods for this store
     */
    public function paymentMethods()
    {
        return $this->hasMany(\App\Models\PaymentMethod::class);
    }

    /**
     * Get enabled payment methods for this store
     */
    public function enabledPaymentMethods()
    {
        return $this->hasMany(\App\Models\PaymentMethod::class)->enabled()->ordered();
    }

    /**
     * Get the users that belong to this store (tenant)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get the product declarations for this store
     */
    public function productDeclarations()
    {
        return $this->hasMany(\App\Models\ProductDeclaration::class);
    }

    /**
     * Get the active product declaration for this store
     */
    public function activeProductDeclaration()
    {
        return $this->hasOne(\App\Models\ProductDeclaration::class)->where('is_active', true);
    }

    /**
     * Get stores for syncing based on current tenant
     * Returns current store, or all stores if on admin store
     */
    public static function getStoresForSync(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            
            // If no tenant or admin store, sync all stores
            if (!$tenant || $tenant->slug === 'visivo-admin') {
                return static::whereNotNull('stripe_account_id')->get();
            }
            
            // Otherwise, sync only the current store
            return static::whereNotNull('stripe_account_id')
                ->where('id', $tenant->id)
                ->get();
        } catch (\Throwable $e) {
            // Fallback: if Filament facade not available, return all stores
            return static::whereNotNull('stripe_account_id')->get();
        }
    }
}
