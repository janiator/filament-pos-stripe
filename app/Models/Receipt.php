<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'pos_session_id',
        'charge_id',
        'user_id',
        'receipt_number',
        'receipt_type',
        'original_receipt_id',
        'receipt_data',
        'printed',
        'printed_at',
        'reprint_count',
    ];

    protected $casts = [
        'receipt_data' => 'array',
        'printed' => 'boolean',
        'printed_at' => 'datetime',
        'reprint_count' => 'integer',
    ];

    /**
     * Get the store for this receipt
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the POS session for this receipt
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * Get the charge for this receipt
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(ConnectedCharge::class, 'charge_id');
    }

    /**
     * Get the user (cashier) for this receipt
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the original receipt (for returns/copies)
     */
    public function originalReceipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'original_receipt_id');
    }

    /**
     * Generate next receipt number for a store
     */
    public static function generateReceiptNumber(int $storeId, string $receiptType = 'sales'): string
    {
        $config = config('receipts.types.' . $receiptType, ['prefix' => 'X']);
        $prefix = $config['prefix'] ?? 'X';

        // Get last receipt number for this store and type
        $lastReceipt = static::where('store_id', $storeId)
            ->where('receipt_type', $receiptType)
            ->orderBy('receipt_number', 'desc')
            ->first();

        if ($lastReceipt) {
            // Extract number from receipt number (format: STOREID-PREFIX-000001)
            // Try to extract the sequential number
            $pattern = '/' . preg_quote($storeId . '-' . $prefix . '-', '/') . '(\d+)/';
            if (preg_match($pattern, $lastReceipt->receipt_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            } else {
                // Fallback: try to extract any number at the end
                if (preg_match('/(\d+)$/', $lastReceipt->receipt_number, $matches)) {
                    $nextNumber = (int) $matches[1] + 1;
                } else {
                    $nextNumber = 1;
                }
            }
        } else {
            $nextNumber = 1;
        }

        // Format: STOREID-PREFIX-000001
        return sprintf('%d-%s-%06d', $storeId, $prefix, $nextNumber);
    }

    /**
     * Mark as printed
     */
    public function markAsPrinted(): void
    {
        $this->printed = true;
        $this->printed_at = now();
        $this->save();
    }

    /**
     * Increment reprint count
     */
    public function incrementReprint(): void
    {
        $this->reprint_count++;
        $this->save();
    }
}
