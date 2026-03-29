<?php

namespace App\Models;

use App\Enums\PowerOfficeEnvironment;
use App\Enums\PowerOfficeIntegrationStatus;
use App\Enums\PowerOfficeMappingBasis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PowerOfficeIntegration extends Model
{
    /** @use HasFactory<\Database\Factories\PowerOfficeIntegrationFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'status',
        'environment',
        'client_key',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'mapping_basis',
        'auto_sync_on_z_report',
        'sync_enabled',
        'last_onboarded_at',
        'onboarding_completed_at',
        'last_synced_at',
        'last_error',
        'settings',
        'onboarding_state_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PowerOfficeIntegrationStatus::class,
            'environment' => PowerOfficeEnvironment::class,
            'mapping_basis' => PowerOfficeMappingBasis::class,
            'client_key' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'auto_sync_on_z_report' => 'boolean',
            'sync_enabled' => 'boolean',
            'last_onboarded_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PowerOfficeIntegration $integration): void {
            if ($integration->getAttribute('environment') === null) {
                $integration->setAttribute('environment', PowerOfficeEnvironment::Dev);
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
        return $this->hasMany(PowerOfficeAccountMapping::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(PowerOfficeSyncRun::class);
    }

    public function isConnected(): bool
    {
        return $this->status === PowerOfficeIntegrationStatus::Connected
            && filled($this->client_key);
    }
}
