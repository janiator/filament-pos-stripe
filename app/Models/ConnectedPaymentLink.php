<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedPaymentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_payment_link_id',
        'stripe_account_id',
        'stripe_price_id',
        'name',
        'description',
        'url',
        'active',
        'link_type',
        'application_fee_percent',
        'application_fee_amount',
        'after_completion_redirect_url',
        'line_items',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'application_fee_percent' => 'integer',
        'application_fee_amount' => 'integer',
        'line_items' => 'array',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stripe_account_id', 'stripe_account_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(ConnectedPrice::class, 'stripe_price_id', 'stripe_price_id')
            ->where('stripe_account_id', $this->stripe_account_id);
    }

    public function team()
    {
        return $this->store?->team;
    }
}
