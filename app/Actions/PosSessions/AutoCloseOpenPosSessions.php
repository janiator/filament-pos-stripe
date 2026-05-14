<?php

namespace App\Actions\PosSessions;

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class AutoCloseOpenPosSessions
{
    /**
     * Close open POS sessions, optionally limited to stores that enabled auto-close in Settings.
     *
     * @return array{closed: int, skipped: int, failed: int}
     */
    public function __invoke(string $closingNotes, bool $onlyForStoresWithAutoCloseEnabled = true): array
    {
        $stats = [
            'closed' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $query = PosSession::query()
            ->where('status', 'open')
            ->when(
                $onlyForStoresWithAutoCloseEnabled,
                fn (Builder $builder): Builder => $builder->whereHas(
                    'store.settings',
                    fn (Builder $settingsQuery): Builder => $settingsQuery->where(
                        'auto_close_open_sessions_daily',
                        true
                    )
                )
            )
            ->orderBy('id');

        $query
            ->chunkById(100, function ($sessions) use ($closingNotes, &$stats): void {
                foreach ($sessions as $session) {
                    if (! $session->canBeClosed()) {
                        $stats['skipped']++;

                        continue;
                    }

                    $expectedCash = $session->calculateExpectedCash();

                    if (! $session->close($expectedCash, $closingNotes)) {
                        $stats['failed']++;
                        Log::warning('pos.auto_close_sessions: failed to close session', [
                            'pos_session_id' => $session->id,
                        ]);

                        continue;
                    }

                    try {
                        PosSessionsTable::persistZReportSnapshotAfterClose($session->fresh());
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        Log::warning('pos.auto_close_sessions: session closed but Z-report snapshot failed', [
                            'pos_session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }

                    $stats['closed']++;
                }
            });

        return $stats;
    }
}
