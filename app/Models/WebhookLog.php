<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'store_id',
        'stripe_account_id',
        'event_type',
        'event_id',
        'account_id',
        'processed',
        'message',
        'warnings',
        'errors',
        'request_data',
        'response_data',
        'http_status_code',
        'error_message',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'warnings' => 'array',
        'errors' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'http_status_code' => 'integer',
    ];

    /**
     * Get the store that owns this webhook log
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Keep only the most recent 100 records per store
     */
    public static function cleanupOldRecords(?int $storeId = null): void
    {
        $query = static::query();
        
        if ($storeId) {
            $query->where('store_id', $storeId);
        }
        
        // Get stores that have more than 100 records
        $storesWithExcess = $query->select('store_id')
            ->whereNotNull('store_id')
            ->groupBy('store_id')
            ->havingRaw('COUNT(*) > 100')
            ->pluck('store_id');
        
        foreach ($storesWithExcess as $storeIdToClean) {
            // Get IDs of records to keep (most recent 100)
            $idsToKeep = static::where('store_id', $storeIdToClean)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->pluck('id');
            
            // Delete older records
            static::where('store_id', $storeIdToClean)
                ->whereNotIn('id', $idsToKeep)
                ->delete();
        }
        
        // Also clean up records without store_id (limit to 1000 total)
        $orphanedCount = static::whereNull('store_id')->count();
        if ($orphanedCount > 1000) {
            $idsToKeep = static::whereNull('store_id')
                ->orderBy('created_at', 'desc')
                ->limit(1000)
                ->pluck('id');
            
            static::whereNull('store_id')
                ->whereNotIn('id', $idsToKeep)
                ->delete();
        }
    }
}
