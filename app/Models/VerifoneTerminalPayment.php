<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifoneTerminalPayment extends Model
{
    /** @use HasFactory<\Database\Factories\VerifoneTerminalPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'store_id',
        'verifone_terminal_id',
        'pos_session_id',
        'pos_device_id',
        'service_id',
        'sale_id',
        'poiid',
        'amount_minor',
        'currency',
        'status',
        'provider_payment_reference',
        'provider_transaction_id',
        'provider_message',
        'request_payload',
        'response_payload',
        'status_payload',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'status_payload' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(VerifoneTerminal::class, 'verifone_terminal_id');
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }
}
