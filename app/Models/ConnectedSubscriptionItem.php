<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedSubscriptionItem extends Model
{
    use HasFactory;

    protected $table = 'connected_subscription_items';

    protected $fillable = [
        'connected_subscription_id',
        'stripe_id',
        'connected_product',
        'connected_price',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription that owns this item
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ConnectedSubscription::class, 'connected_subscription_id');
    }

    /**
     * Get the product for this subscription item
     */
    public function product(): ?ConnectedProduct
    {
        if (! $this->connected_product) {
            return null;
        }

        $subscription = $this->subscription;
        if (! $subscription) {
            return null;
        }

        return ConnectedProduct::where('stripe_product_id', $this->connected_product)
            ->where('stripe_account_id', $subscription->stripe_account_id)
            ->first();
    }

    /**
     * Get the price for this subscription item
     */
    public function price(): ?ConnectedPrice
    {
        if (! $this->connected_price) {
            return null;
        }

        $subscription = $this->subscription;
        if (! $subscription) {
            return null;
        }

        return ConnectedPrice::where('stripe_price_id', $this->connected_price)
            ->where('stripe_account_id', $subscription->stripe_account_id)
            ->first();
    }
}
