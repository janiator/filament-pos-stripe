<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptTemplate extends Model
{
    protected $fillable = [
        'store_id',
        'template_type',
        'content',
        'is_custom',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
    ];

    /**
     * Get the store that owns this template (null for global templates)
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user who created this template
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this template
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get template for a specific store and type, or global if not found
     */
    public static function getTemplate(?int $storeId, string $templateType): ?self
    {
        // First try to get store-specific template
        if ($storeId) {
            $template = self::where('store_id', $storeId)
                ->where('template_type', $templateType)
                ->first();
            
            if ($template) {
                return $template;
            }
        }

        // Fall back to global template (store_id is null)
        return self::whereNull('store_id')
            ->where('template_type', $templateType)
            ->first();
    }
}
