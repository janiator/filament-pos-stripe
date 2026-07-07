<?php

namespace App\Enums;

enum PowerOfficeVoucherPostingMode: string
{
    case Direct = 'direct';
    case JournalEntry = 'journal_entry';

    public function ledgerPostPath(): string
    {
        return match ($this) {
            self::Direct => '/Vouchers/ManualJournals',
            self::JournalEntry => '/JournalEntryVouchers/ManualJournals',
        };
    }

    public function postsDirectlyToLedger(): bool
    {
        return $this === self::Direct;
    }
}
