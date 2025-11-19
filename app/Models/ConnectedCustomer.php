<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectedCustomer extends Model
{
    use HasFactory;

    protected $table = 'stripe_connected_customer_mappings';

    protected $fillable = [
        'model',
        'model_id',
        'model_uuid',
        'stripe_customer_id',
        'stripe_account_id',
        'name',
        'email',
    ];

    protected static function booted(): void
    {
        static::saved(function (ConnectedCustomer $customer) {
            // Use saved event to ensure it fires for both create and update
            // Only sync on update (not create)
            if ($customer->wasRecentlyCreated) {
                return;
            }
            
            $listener = new \App\Listeners\SyncConnectedCustomerToStripeListener();
            $listener->handle($customer);
        });
    }

    /**
     * Get the store that owns this customer via stripe_account_id
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the subscriptions for this customer
     */
    public function subscriptions(): HasMany
    {
        $accountId = $this->stripe_account_id;
        return $this->hasMany(ConnectedSubscription::class, 'stripe_customer_id', 'stripe_customer_id')
            ->where('connected_subscriptions.stripe_account_id', $accountId);
    }

    /**
     * Get the payment methods for this customer
     */
    public function paymentMethods(): HasMany
    {
        if (!class_exists(\App\Models\ConnectedPaymentMethod::class)) {
            return $this->hasMany(\App\Models\ConnectedPaymentMethod::class, 'stripe_customer_id', 'stripe_customer_id')
                ->where('connected_payment_methods.stripe_account_id', $this->stripe_account_id);
        }
        return $this->hasMany(\App\Models\ConnectedPaymentMethod::class, 'stripe_customer_id', 'stripe_customer_id')
            ->where('connected_payment_methods.stripe_account_id', $this->stripe_account_id);
    }

}
