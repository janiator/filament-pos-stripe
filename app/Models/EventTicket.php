<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Positiv\FilamentWebflow\Models\WebflowItem;

class EventTicket extends Model
{
    protected $fillable = [
        'store_id',
        'webflow_item_id',
        'name',
        'slug',
        'description',
        'image_url',
        'event_date',
        'event_time',
        'venue',
        'ticket_1_label',
        'ticket_1_available',
        'ticket_1_sold',
        'ticket_1_payment_link_id',
        'ticket_1_price_id',
        'ticket_2_label',
        'ticket_2_available',
        'ticket_2_sold',
        'ticket_2_payment_link_id',
        'ticket_2_price_id',
        'is_sold_out',
        'is_archived',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'is_sold_out' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function webflowItem(): BelongsTo
    {
        return $this->belongsTo(WebflowItem::class, 'webflow_item_id');
    }

    public static function findByPaymentLinkId(string $paymentLinkId): ?self
    {
        return static::where('ticket_1_payment_link_id', $paymentLinkId)
            ->orWhere('ticket_2_payment_link_id', $paymentLinkId)
            ->first();
    }

    public function incrementSoldForPaymentLink(string $paymentLinkId, int $quantity = 1): void
    {
        if ($this->ticket_1_payment_link_id === $paymentLinkId) {
            $this->increment('ticket_1_sold', $quantity);
        }
        if ($this->ticket_2_payment_link_id === $paymentLinkId) {
            $this->increment('ticket_2_sold', $quantity);
        }
        $this->refresh();
        $this->updateSoldOutStatus();
    }

    public function updateSoldOutStatus(): void
    {
        $soldOut = true;
        if ($this->ticket_1_available !== null && $this->ticket_1_sold < $this->ticket_1_available) {
            $soldOut = false;
        }
        if ($this->ticket_2_available !== null && $this->ticket_2_sold < $this->ticket_2_available) {
            $soldOut = false;
        }
        if ($this->ticket_1_available === null && $this->ticket_2_available === null) {
            $soldOut = false;
        }
        $this->update(['is_sold_out' => $soldOut]);
    }
}
