<?php

namespace App\Services\Shopify;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShopifyImportRun
{
    // keep runs around for operator UI
    public const TTL_HOURS = 12;

    public const CONSOLE_MAX = 400;
    public const RECENT_MAX  = 30;
    public const ERRORS_MAX  = 200;

    protected static function ttl()
    {
        return now()->addHours(self::TTL_HOURS);
    }

    protected static function base(string $runId): string
    {
        return "shopify_import:{$runId}";
    }

    protected static function key(string $runId, string $suffix): string
    {
        return self::base($runId) . ':' . $suffix;
    }

    public static function initRun(
        string $runId,
        int $total,
        string $currency,
        int $chunkSize,
        bool $downloadImages,
        bool $updateExisting,
        ?array $plan = null,
        ?string $imagesDisk = null,
    ): void {
        Cache::put(self::key($runId, 'progress'), [
            'status'          => 'running',
            'current'         => 0,
            'total'           => $total,
            'percent'         => 0,
            'imported'        => 0,
            'skipped'         => 0,
            'updated'         => 0,
            'created'         => 0,
            'errors'          => 0,

            'img_attempted'   => 0,
            'img_ok'          => 0,
            'img_invalid'     => 0,
            'img_failed'      => 0,
            'img_too_large'   => 0,

            'download_images' => $downloadImages,
            'update_existing' => $updateExisting,
            'currency'        => $currency,
            'chunk_size'      => $chunkSize,
            'run_id'          => $runId,
            'batch_id'        => null,
            'images_disk'     => $imagesDisk,
        ], self::ttl());

        Cache::put(self::key($runId, 'console'), [], self::ttl());
        Cache::put(self::key($runId, 'recent'), [], self::ttl());

        Cache::put(self::key($runId, 'result'), [
            'run_id'      => $runId,
            'batch_id'    => null,
            'finished_at' => null,
            'stats'       => [
                'import' => [
                    'total_products' => $total,
                    'imported'       => 0,
                    'skipped'        => 0,
                    'updated'        => 0,
                    'created'        => 0,
                    'error_count'    => 0,
                ],
                'images' => [
                    'attempted'   => 0,
                    'ok'          => 0,
                    'invalid'     => 0,
                    'failed'      => 0,
                    'too_large'   => 0,
                ],
            ],
            'last_error'  => null, // structured diagnostics
            'errors'      => [],
            'per_product' => [],
            'plan'        => $plan,
        ], self::ttl());
    }

    public static function attachBatchId(string $runId, string $batchId): void
    {
        $p = self::getProgress($runId) ?: [];
        $p['batch_id'] = $batchId;
        Cache::put(self::key($runId, 'progress'), $p, self::ttl());

        $r = self::getResult($runId) ?: [];
        $r['batch_id'] = $batchId;
        Cache::put(self::key($runId, 'result'), $r, self::ttl());
    }

    public static function getProgress(string $runId): ?array
    {
        $v = Cache::get(self::key($runId, 'progress'));
        return is_array($v) ? $v : null;
    }

    public static function putProgress(string $runId, array $progress): void
    {
        Cache::put(self::key($runId, 'progress'), $progress, self::ttl());
    }

    public static function getConsole(string $runId): array
    {
        $v = Cache::get(self::key($runId, 'console'), []);
        return is_array($v) ? array_slice($v, -self::CONSOLE_MAX) : [];
    }

    public static function clearConsole(string $runId): void
    {
        Cache::put(self::key($runId, 'console'), [], self::ttl());
    }

    public static function pushConsole(string $runId, string $message, string $level = 'info'): void
    {
        $line = [
            'time'    => now()->format('H:i:s'),
            'level'   => $level,
            'message' => $message,
        ];

        $console = self::getConsole($runId);
        $console[] = $line;

        Cache::put(self::key($runId, 'console'), array_slice($console, -self::CONSOLE_MAX), self::ttl());
    }

    // convenience when you want to push to local array + (optional) cache later
    public static function pushConsoleLocal(object $page, string $message, string $level = 'info'): void
    {
        $page->importConsole[] = [
            'time'    => now()->format('H:i:s'),
            'level'   => $level,
            'message' => $message,
        ];
    }

    public static function getRecent(string $runId): array
    {
        $v = Cache::get(self::key($runId, 'recent'), []);
        return is_array($v) ? array_slice($v, -self::RECENT_MAX) : [];
    }

    public static function pushRecent(string $runId, array $item): void
    {
        $recent = self::getRecent($runId);
        $recent[] = $item;
        Cache::put(self::key($runId, 'recent'), array_slice($recent, -self::RECENT_MAX), self::ttl());
    }

    public static function getResult(string $runId): ?array
    {
        $v = Cache::get(self::key($runId, 'result'));
        return is_array($v) ? $v : null;
    }

    public static function recordPerProduct(string $runId, array $item): void
    {
        $r = self::getResult($runId) ?: [];
        $pp = $r['per_product'] ?? [];
        if (! is_array($pp)) $pp = [];
        $pp[] = $item;

        // keep last N only (UI doesn’t need infinite)
        $r['per_product'] = array_slice($pp, -400);

        Cache::put(self::key($runId, 'result'), $r, self::ttl());
    }

    public static function addError(string $runId, string $message): void
    {
        $r = self::getResult($runId) ?: [];
        $errs = $r['errors'] ?? [];
        if (! is_array($errs)) $errs = [];
        $errs[] = $message;
        $r['errors'] = array_slice($errs, -self::ERRORS_MAX);
        Cache::put(self::key($runId, 'result'), $r, self::ttl());
    }

    public static function bumpImageStats(string $runId, array $delta): void
    {
        $p = self::getProgress($runId) ?: [];
        foreach ($delta as $k => $v) {
            $p[$k] = (int)($p[$k] ?? 0) + (int)$v;
        }
        Cache::put(self::key($runId, 'progress'), $p, self::ttl());

        $r = self::getResult($runId) ?: [];
        $img = data_get($r, 'stats.images', []);
        if (! is_array($img)) $img = [];
        foreach ($delta as $k => $v) {
            // map progress keys -> stats keys
            $map = [
                'img_attempted' => 'attempted',
                'img_ok'        => 'ok',
                'img_invalid'   => 'invalid',
                'img_failed'    => 'failed',
                'img_too_large' => 'too_large',
            ];
            if (isset($map[$k])) {
                $img[$map[$k]] = (int)($img[$map[$k]] ?? 0) + (int)$v;
            }
        }
        data_set($r, 'stats.images', $img);
        Cache::put(self::key($runId, 'result'), $r, self::ttl());
    }

    public static function bumpProgress(string $runId, array $delta, ?int $total = null): void
    {
        $p = self::getProgress($runId) ?: [];
        foreach ($delta as $k => $v) {
            $p[$k] = (int)($p[$k] ?? 0) + (int)$v;
        }
        if ($total !== null) $p['total'] = $total;

        $cur = (int)($p['current'] ?? 0);
        $tot = max(0, (int)($p['total'] ?? 0));
        $p['percent'] = $tot > 0 ? (int) floor(($cur / $tot) * 100) : 0;

        Cache::put(self::key($runId, 'progress'), $p, self::ttl());

        // also keep summary in result
        $r = self::getResult($runId) ?: [];
        data_set($r, 'stats.import.total_products', $tot);
        data_set($r, 'stats.import.imported', (int)($p['imported'] ?? 0));
        data_set($r, 'stats.import.skipped',  (int)($p['skipped'] ?? 0));
        data_set($r, 'stats.import.updated',  (int)($p['updated'] ?? 0));
        data_set($r, 'stats.import.created',  (int)($p['created'] ?? 0));
        data_set($r, 'stats.import.error_count', (int)($p['errors'] ?? 0));
        Cache::put(self::key($runId, 'result'), $r, self::ttl());
    }

    public static function markDispatchFailed(string $runId, Throwable $e): void
    {
        self::pushConsole($runId, 'Dispatch failed: ' . $e->getMessage(), 'err');

        $p = self::getProgress($runId) ?: [];
        $p['status']  = 'failed';
        $p['percent'] = 0;
        Cache::put(self::key($runId, 'progress'), $p, self::ttl());

        $r = self::getResult($runId) ?: [];
        $r['last_error'] = self::formatThrowable($e, 'dispatch');
        self::addError($runId, '[dispatch] ' . $e->getMessage());
        Cache::put(self::key($runId, 'result'), $r, self::ttl());
    }

    public static function markFailed(string $runId, ?string $batchId, Throwable $e): void
    {
        self::pushConsole($runId, 'Batch failed: ' . $e->getMessage(), 'err');

        $p = self::getProgress($runId) ?: [];
        $p['status']   = 'failed';
        $p['batch_id'] = $batchId ?? ($p['batch_id'] ?? null);
        Cache::put(self::key($runId, 'progress'), $p, self::ttl());

        $r = self::getResult($runId) ?: [];
        $r['batch_id'] = $batchId ?? ($r['batch_id'] ?? null);
        $r['last_error'] = self::formatThrowable($e, 'batch');
        self::addError($runId, '[batch] ' . $e->getMessage());
        Cache::put(self::key($runId, 'result'), $r, self::ttl());

        Log::error('Shopify import batch failed', [
            'run_id'   => $runId,
            'batch_id' => $batchId,
            'error'    => $e->getMessage(),
        ]);
    }

    public static function finalizeFromBatch(string $runId, Batch $batch): void
    {
        $p = self::getProgress($runId) ?: [];
        $p['batch_id'] = $batch->id;

        // if allowFailures, batch can be "finished" with failures
        $hasFailures = method_exists($batch, 'hasFailures') ? $batch->hasFailures() : false;
        $p['status']  = $hasFailures ? 'failed' : 'finished';
        $p['percent'] = 100;

        Cache::put(self::key($runId, 'progress'), $p, self::ttl());

        $r = self::getResult($runId) ?: [];
        $r['batch_id']    = $batch->id;
        $r['finished_at'] = now()->toIso8601String();

        Cache::put(self::key($runId, 'result'), $r, self::ttl());

        self::pushConsole($runId, $hasFailures ? 'Batch finished with failures.' : 'Batch finished successfully.', $hasFailures ? 'warn' : 'ok');
    }

    protected static function formatThrowable(Throwable $e, string $stage): array
    {
        $trace = $e->getTraceAsString();
        $head  = implode("\n", array_slice(explode("\n", $trace), 0, 35));

        return [
            'stage'     => $stage,
            'message'   => $e->getMessage(),
            'at'        => now()->toIso8601String(),
            'exception' => [
                'class'      => get_class($e),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace_head' => $head,
            ],
        ];
    }
}
