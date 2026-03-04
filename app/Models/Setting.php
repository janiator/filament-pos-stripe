<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        // Receipt settings
        'auto_print_receipts',
        'default_receipt_template_id',
        'receipt_printer_type',
        'receipt_number_format',
        'default_vat_rate',
        // Cash drawer settings
        'cash_drawer_auto_open',
        'cash_drawer_open_duration_ms',
        // General POS settings
        'currency',
        'timezone',
        'locale',
        'tax_included',
        'tips_enabled',
        'gift_card_expiration_days',
        // Additional settings stored as JSON
        'additional_settings',
    ];

    protected $casts = [
        'auto_print_receipts' => 'boolean',
        'cash_drawer_auto_open' => 'boolean',
        'cash_drawer_open_duration_ms' => 'integer',
        'default_vat_rate' => 'decimal:2',
        'tax_included' => 'boolean',
        'tips_enabled' => 'boolean',
        'gift_card_expiration_days' => 'integer',
        'additional_settings' => 'array',
    ];

    /**
     * Get the store that owns these settings
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the default receipt template
     */
    public function defaultReceiptTemplate(): BelongsTo
    {
        return $this->belongsTo(ReceiptTemplate::class, 'default_receipt_template_id');
    }

    /**
     * Get or create settings for a store
     */
    public static function getForStore(int $storeId): self
    {
        return static::firstOrCreate(
            ['store_id' => $storeId],
            [
                'auto_print_receipts' => config('pos.auto_print_receipts', false),
                'cash_drawer_auto_open' => config('pos.cash_drawer.auto_open', true),
                'cash_drawer_open_duration_ms' => config('pos.cash_drawer.open_duration_ms', 250),
                'receipt_printer_type' => config('receipts.printer_type', 'epson'),
                'receipt_number_format' => config('receipts.number_format', '{store_id}-{type}-{number:06d}'),
                'default_vat_rate' => config('receipts.default_vat_rate', 25.0),
                'currency' => 'nok',
                'timezone' => config('app.timezone', 'Europe/Oslo'),
                'locale' => config('app.locale', 'nb'),
                'tax_included' => false,
                'tips_enabled' => true,
            ]
        );
    }
}
