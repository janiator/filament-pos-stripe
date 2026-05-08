<?php

namespace App\Models;

use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripletexSyncRun extends Model
{
    /** @use HasFactory<\Database\Factories\TripletexSyncRunFactory> */
    use HasFactory;

    protected $fillable = [
        'tripletex_integration_id',
        'store_id',
        'sync_type',
        'pos_session_id',
        'store_stripe_payout_id',
        'status',
        'idempotency_key',
        'request_payload',
        'response_payload',
        'tripletex_voucher_id',
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
            'sync_type' => TripletexSyncType::class,
            'status' => TripletexSyncRunStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TripletexIntegration::class, 'tripletex_integration_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function storeStripePayout(): BelongsTo
    {
        return $this->belongsTo(StoreStripePayout::class);
    }
}
