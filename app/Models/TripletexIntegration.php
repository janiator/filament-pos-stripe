<?php

namespace App\Models;

use App\Enums\PowerOfficeMappingBasis;
use App\Enums\TripletexEnvironment;
use App\Enums\TripletexIntegrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripletexIntegration extends Model
{
    /** @use HasFactory<\Database\Factories\TripletexIntegrationFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'status',
        'environment',
        'consumer_token',
        'employee_token',
        'mapping_basis',
        'sync_enabled',
        'auto_sync_on_z_report',
        'auto_sync_payouts',
        'z_report_include_settlement',
        'last_synced_at',
        'last_error',
        'settings',
        'period_preview_state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TripletexIntegrationStatus::class,
            'environment' => TripletexEnvironment::class,
            'mapping_basis' => PowerOfficeMappingBasis::class,
            'consumer_token' => 'encrypted',
            'employee_token' => 'encrypted',
            'sync_enabled' => 'boolean',
            'auto_sync_on_z_report' => 'boolean',
            'auto_sync_payouts' => 'boolean',
            'z_report_include_settlement' => 'boolean',
            'last_synced_at' => 'datetime',
            'settings' => 'array',
            'period_preview_state' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TripletexIntegration $integration): void {
            if ($integration->getAttribute('environment') === null) {
                $integration->setAttribute('environment', TripletexEnvironment::Test);
            }

            if ($integration->getAttribute('mapping_basis') === null) {
                $integration->setAttribute('mapping_basis', PowerOfficeMappingBasis::Vat);
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function accountMappings(): HasMany
    {
        return $this->hasMany(TripletexAccountMapping::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(TripletexSyncRun::class);
    }

    public function isConnected(): bool
    {
        return $this->status === TripletexIntegrationStatus::Connected
            && filled($this->consumer_token)
            && filled($this->employee_token);
    }
}
