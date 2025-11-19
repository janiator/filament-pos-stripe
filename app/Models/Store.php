<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'email',
        'commission_type',
        'commission_rate',
        'stripe_account_id',
        'team_id',
    ];

    protected $casts = [
        'commission_rate' => 'integer',
    ];

    protected static function booted(): void
    {
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

    public function terminalLocations()
    {
        return $this->hasMany(\App\Models\TerminalLocation::class);
    }

    public function terminalReaders()
    {
        return $this->hasMany(\App\Models\TerminalReader::class);
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
        if (!class_exists(\App\Models\ConnectedCustomer::class)) {
            return $this->hasMany(\App\Models\ConnectedCustomer::class, 'stripe_account_id', 'stripe_account_id');
        }
        return $this->hasMany(\App\Models\ConnectedCustomer::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected subscriptions for this store
     */
    public function connectedSubscriptions()
    {
        if (!class_exists(\App\Models\ConnectedSubscription::class)) {
            return $this->hasMany(\App\Models\ConnectedSubscription::class, 'stripe_account_id', 'stripe_account_id');
        }
        return $this->hasMany(\App\Models\ConnectedSubscription::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the connected products for this store
     */
    public function connectedProducts()
    {
        if (!class_exists(\App\Models\ConnectedProduct::class)) {
            return $this->hasMany(\App\Models\ConnectedProduct::class, 'stripe_account_id', 'stripe_account_id');
        }
        return $this->hasMany(\App\Models\ConnectedProduct::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the team that owns this store
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get stores for syncing based on current tenant
     * Returns current team's store, or all stores if on admin team
     */
    public static function getStoresForSync(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            
            // If no tenant or admin team, sync all stores
            if (!$tenant || $tenant->slug === 'visivo-admin') {
                return static::whereNotNull('stripe_account_id')->get();
            }
            
            // Otherwise, sync only the current team's store
            return static::whereNotNull('stripe_account_id')
                ->where('team_id', $tenant->id)
                ->get();
        } catch (\Throwable $e) {
            // Fallback: if Filament facade not available, return all stores
            return static::whereNotNull('stripe_account_id')->get();
        }
    }
}
