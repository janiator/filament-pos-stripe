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
                            {--force : Run even when POS_AUTO_CLOSE_SESSIONS_DAILY is disabled; closes all open sessions (ignores per-store Settings toggle)}';

    /**
     * @var string
     */
    protected $description = 'Close open POS sessions for stores that enabled daily auto-close in Settings (optional schedule; off by default)';

    public function handle(AutoCloseOpenPosSessions $autoCloseOpenPosSessions): int
    {
        $enabled = (bool) config('pos.auto_close_sessions.enabled');
        $forced = (bool) $this->option('force');

        if (! $enabled && ! $forced) {
            $this->info('POS daily auto-close is disabled. Set POS_AUTO_CLOSE_SESSIONS_DAILY=true or use --force.');

            return self::SUCCESS;
        }

        $notes = (string) config('pos.auto_close_sessions.closing_notes');
        $onlyRespectStoreToggle = ! $forced;
        $stats = $autoCloseOpenPosSessions($notes, $onlyRespectStoreToggle);

        $this->info(sprintf(
            'Auto-close finished: %d closed, %d skipped, %d failed.',
            $stats['closed'],
            $stats['skipped'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
