<?php

namespace App\Enums;

enum TripletexSyncRunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }
}
