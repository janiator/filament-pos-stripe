<?php

namespace App\Models;

use App\Enums\PowerOfficeMappingBasis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripletexAccountMapping extends Model
{
    /** @use HasFactory<\Database\Factories\TripletexAccountMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'tripletex_integration_id',
        'basis_type',
        'basis_key',
        'basis_label',
        'sales_account_no',
        'vat_account_no',
        'fees_account_no',
        'tips_account_no',
        'cash_account_no',
        'card_clearing_account_no',
        'rounding_account_no',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basis_type' => PowerOfficeMappingBasis::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TripletexAccountMapping $mapping): void {
            if ($mapping->store_id === null && $mapping->tripletex_integration_id !== null) {
                $mapping->store_id = TripletexIntegration::query()
                    ->whereKey($mapping->tripletex_integration_id)
                    ->value('store_id');
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TripletexIntegration::class, 'tripletex_integration_id');
    }
}
