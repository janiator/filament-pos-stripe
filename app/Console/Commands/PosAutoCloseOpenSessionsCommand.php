<?php

namespace App\Console\Commands;

use App\Actions\PosSessions\AutoCloseOpenPosSessions;
use Illuminate\Console\Command;

class PosAutoCloseOpenSessionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pos:auto-close-open-sessions
                            {--force : Close all open sessions (ignores per-store Settings toggle)}';

    /**
     * @var string
     */
    protected $description = 'Close open POS sessions for stores that enabled daily auto-close in Settings (scheduled daily; use --force to close all open sessions)';

    public function handle(AutoCloseOpenPosSessions $autoCloseOpenPosSessions): int
    {
        $forced = (bool) $this->option('force');
        $notes = (string) config('pos.auto_close_sessions.closing_notes');
        $stats = $autoCloseOpenPosSessions($notes, ! $forced);

        $this->info(sprintf(
            'Auto-close finished: %d closed, %d skipped, %d failed.',
            $stats['closed'],
            $stats['skipped'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
