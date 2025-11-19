<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_payment_method_id',
        'stripe_account_id',
        'stripe_customer_id',
        'type',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
        'billing_details_name',
        'billing_details_email',
        'billing_details_address',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'card_exp_month' => 'integer',
        'card_exp_year' => 'integer',
        'is_default' => 'boolean',
        'billing_details_address' => 'array',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    public function customer(): ?BelongsTo
    {
        if (!class_exists(\App\Models\ConnectedCustomer::class)) {
            return null;
        }
        // We can't use whereColumn in belongsTo with eager loading, so we'll handle the constraint
        // in the eager loading closure or filter after loading
        return $this->belongsTo(\App\Models\ConnectedCustomer::class, 'stripe_customer_id', 'stripe_customer_id');
    }

    public function getCardDisplayAttribute(): string
    {
        if ($this->type !== 'card') {
            return ucfirst($this->type);
        }

        $brand = $this->card_brand ? ucfirst($this->card_brand) : 'Card';
        $last4 = $this->card_last4 ? " •••• {$this->card_last4}" : '';
        $exp = ($this->card_exp_month && $this->card_exp_year) 
            ? " ({$this->card_exp_month}/{$this->card_exp_year})" 
            : '';

        return "{$brand}{$last4}{$exp}";
    }

    public function team()
    {
        return $this->store?->team;
    }
}
