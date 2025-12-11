<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyImportRun extends Model
{
    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'status',
        'total_products',
        'imported',
        'skipped',
        'error_count',
        'current_index',
        'current_title',
        'current_handle',
        'current_category',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getProgressPercentAttribute(): int
    {
        if (! $this->total_products || $this->total_products <= 0) {
            return 0;
        }

        $done = max($this->imported + $this->skipped, $this->current_index);
        return (int) floor(($done / $this->total_products) * 100);
    }
}
