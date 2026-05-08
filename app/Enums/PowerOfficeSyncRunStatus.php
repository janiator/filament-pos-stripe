<?php

namespace App\Enums;

enum PowerOfficeSyncRunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }
}
