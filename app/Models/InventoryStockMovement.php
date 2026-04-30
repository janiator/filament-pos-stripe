<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockMovement extends Model
{
    public const REASON_SALE = 'sale';

    public const REASON_REFUND = 'refund';

    public const REASON_MANUAL_ADJUST = 'manual_adjust';

    public const REASON_IMPORT = 'import';

    protected $fillable = [
        'store_id',
        'product_variant_id',
        'quantity_delta',
        'reason',
        'connected_charge_id',
        'pos_event_id',
        'refund_reference',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function connectedCharge(): BelongsTo
    {
        return $this->belongsTo(ConnectedCharge::class);
    }

    public function posEvent(): BelongsTo
    {
        return $this->belongsTo(PosEvent::class);
    }
}
