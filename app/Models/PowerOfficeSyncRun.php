<?php

namespace App\Models;

use App\Enums\PowerOfficeSyncRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PowerOfficeSyncRun extends Model
{
    /** @use HasFactory<\Database\Factories\PowerOfficeSyncRunFactory> */
    use HasFactory;

    protected $fillable = [
        'power_office_integration_id',
        'store_id',
        'pos_session_id',
        'status',
        'idempotency_key',
        'request_payload',
        'response_payload',
        'journal_voucher_no',
        'attempts',
        'started_at',
        'finished_at',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PowerOfficeSyncRunStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'journal_voucher_no' => 'integer',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(PowerOfficeIntegration::class, 'power_office_integration_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }
}
