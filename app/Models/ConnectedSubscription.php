<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectedSubscription extends Model
{
    use HasFactory;

    protected $table = 'connected_subscriptions';

    protected $fillable = [
        'name',
        'stripe_id',
        'stripe_status',
        'connected_price_id',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'current_period_start',
        'current_period_end',
        'billing_cycle_anchor',
        'cancel_at_period_end',
        'collection_method',
        'currency',
        'stripe_customer_id',
        'stripe_account_id',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'billing_cycle_anchor' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'quantity' => 'integer',
    ];

    /**
     * Get the store that owns this subscription via stripe_account_id
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    /**
     * Get the customer for this subscription
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            ConnectedCustomer::class,
            'stripe_customer_id',
            'stripe_customer_id'
        )->where('stripe_account_id', $this->stripe_account_id);
    }

    /**
     * Get the subscription items
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConnectedSubscriptionItem::class);
    }

    /**
     * Determine if the subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->stripe_status, ['active', 'trialing']);
    }

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
}
