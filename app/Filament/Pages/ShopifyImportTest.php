<?php

namespace App\Filament\Pages;

use App\Jobs\Shopify\ImportShopifyProductsChunkJob;
use App\Models\ConnectedProduct;
use App\Services\ShopifyCsvImporter;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components as Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

class ShopifyImportTest extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string               $navigationLabel = 'Shopify CSV Import';
    protected static ?string               $title           = 'Shopify CSV Import';
    protected static string|UnitEnum|null  $navigationGroup = 'Dev';

    private const CACHE_TTL_HOURS = 12;
    private const CONSOLE_MAX = 300;
    private const RECENT_MAX = 30;

    public function getView(): string
    {
        return 'filament.pages.shopify-import-test';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public array $formData = [
        'csv_path'          => null,
        'stripe_account_id' => '',
        'currency'          => 'nok',
        'download_images'   => false,
        'update_existing'   => true,
        'chunk_size'        => 25,
    ];

    public ?array $parseResult  = null;
    public ?array $planResult   = null;
    public ?array $importResult = null;

    public ?string $currentRunId   = null;
    public ?string $currentBatchId = null;

    public array $importProgress = [
        'status'          => 'idle',
        'current'         => 0,
        'total'           => 0,
        'percent'         => 0,
        'imported'        => 0,
        'skipped'         => 0,
        'updated'         => 0,
        'created'         => 0,
        'errors'          => 0,
        'download_images' => false,
        'update_existing' => true,
        'currency'        => 'nok',
        'chunk_size'      => 25,
        'run_id'          => null,
        'batch_id'        => null,
    ];

    public array $importConsole = [];
    public array $recentProducts = [];

    public function mount(): void
    {
        $this->form->fill($this->formData);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) return false;

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) return true;
        if (property_exists($user, 'is_super_admin') && (bool) $user->is_super_admin) return true;
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;

        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('formData')
            ->schema([
                Forms\FileUpload::make('csv_path')
                    ->label('Shopify CSV')
                    ->helperText('Export from Shopify → Products → Export as CSV.')
                    ->disk('local')
                    ->directory('tmp/shopify-import')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['text/csv', 'text/plain', '.csv'])
                    ->required(),

                Forms\TextInput::make('stripe_account_id')
                    ->label('Stripe Account ID')
                    ->placeholder('acct_...')
                    ->helperText('Connected account id, e.g. acct_123...')
                    ->required(),

                Forms\TextInput::make('currency')
                    ->label('Currency')
                    ->placeholder('nok')
                    ->helperText('Stripe currency (lowercase), e.g. nok, sek, eur.')
                    ->default('nok')
                    ->required(),

                Forms\Toggle::make('download_images')
                    ->label('Download product images')
                    ->helperText('If enabled, jobs will attempt to include image handling (implementation depends on your pipeline).')
                    ->inline(false),

                Forms\Toggle::make('update_existing')
                    ->label('Update existing products')
                    ->helperText('If enabled, existing products (same handle) will be updated. If disabled, they will be skipped.')
                    ->inline(false)
                    ->default(true),

                Forms\TextInput::make('chunk_size')
                    ->label('Chunk size')
                    ->numeric()
                    ->minValue(5)
                    ->maxValue(200)
                    ->default(25)
                    ->helperText('How many products each queue job processes. 25–50 is usually ideal.')
                    ->required(),
            ]);
    }

    public function getHeaderActions(): array
    {
        return [
            Actions\Action::make('parseCsv')
                ->label('Parse CSV')
                ->icon('heroicon-o-magnifying-glass')
                ->action('parseCsv'),

            Actions\Action::make('runImport')
                ->label('Run Import')
                ->color('success')
                ->icon('heroicon-o-arrow-up-tray')
                ->action('runImport'),

            Actions\Action::make('resetRun')
                ->label('Reset view')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('resetRunState'),
        ];
    }

    /* ----------------------------
     * TTL / keys / console helpers
     * ---------------------------- */

    protected function ttl()
    {
        return now()->addHours(self::CACHE_TTL_HOURS);
    }

    protected function cacheBase(?string $runId = null): string
    {
        $rid = $runId ?: (string) ($this->currentRunId ?: 'none');
        return "shopify_import:{$rid}";
    }

    protected function cacheKey(string $suffix, ?string $runId = null): string
    {
        return $this->cacheBase($runId) . ':' . $suffix;
    }

    protected function pushConsole(string $message, string $level = 'info', ?string $runId = null): void
    {
        $line = [
            'time'    => now()->format('H:i:s'),
            'level'   => $level,
            'message' => $message,
        ];

        $this->importConsole[] = $line;
        $this->importConsole = array_slice($this->importConsole, -self::CONSOLE_MAX);

        $rid = $runId ?: $this->currentRunId;
        if ($rid) {
            $console = Cache::get($this->cacheKey('console', $rid), []);
            if (! is_array($console)) $console = [];
            $console[] = $line;
            Cache::put($this->cacheKey('console', $rid), array_slice($console, -self::CONSOLE_MAX), $this->ttl());
        }
    }

    protected function storeLastError(string $runId, string $message, ?Throwable $e = null): void
    {
        $payload = [
            'message' => $message,
            'at' => now()->toIso8601String(),
            'exception' => $e ? [
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_head' => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 25)),
            ] : null,
        ];

        Cache::put($this->cacheKey('last_error', $runId), $payload, $this->ttl());

        // Mirror into result for the UI
        $result = Cache::get($this->cacheKey('result', $runId), []);
        if (! is_array($result)) $result = [];
        $result['last_error'] = $payload;
        Cache::put($this->cacheKey('result', $runId), $result, $this->ttl());
    }

    /* ----------------------------
     * Normalization / preflight
     * ---------------------------- */

    protected function normalizedFormState(): array
    {
        $data = $this->form->getState() ?: [];

        $csvPath         = $data['csv_path'] ?? null;
        $stripeAccountId = trim((string) ($data['stripe_account_id'] ?? ''));
        $currency        = strtolower(trim((string) ($data['currency'] ?? 'nok')));
        $downloadImages  = (bool) ($data['download_images'] ?? false);
        $updateExisting  = (bool) ($data['update_existing'] ?? true);
        $chunkSize       = (int) ($data['chunk_size'] ?? 25);

        $currency  = $currency !== '' ? $currency : 'nok';
        $chunkSize = max(5, min(200, $chunkSize));

        return compact(
            'csvPath',
            'stripeAccountId',
            'currency',
            'downloadImages',
            'updateExisting',
            'chunkSize'
        );
    }

    protected function ensureRequiredOrNotify(?string $csvPath, string $stripeAccountId): bool
    {
        if (! $csvPath || $stripeAccountId === '') {
            Notification::make()
                ->title('Missing data')
                ->body('Upload a CSV and set Stripe Account ID first.')
                ->danger()
                ->send();
            return false;
        }
        return true;
    }

    protected function preflightOrThrow(string $csvPath, string $stripeAccountId): void
    {
        if (! Storage::disk('local')->exists($csvPath)) {
            throw new \RuntimeException("CSV not found on disk: {$csvPath}");
        }

        $abs = Storage::disk('local')->path($csvPath);
        if (! is_readable($abs)) {
            throw new \RuntimeException("CSV is not readable: {$abs}");
        }

        // Batches table sanity check (common “why batch doesn't work” pain)
        try {
            DB::table('job_batches')->limit(1)->get();
        } catch (Throwable $e) {
            throw new \RuntimeException(
                "Missing/invalid job_batches table. Run: php artisan queue:batches-table && php artisan migrate",
                0,
                $e
            );
        }

        // Job must be Batchable (your exact error)
        $uses = class_uses(ImportShopifyProductsChunkJob::class) ?: [];
        if (! in_array(\Illuminate\Bus\Batchable::class, $uses, true)) {
            throw new \RuntimeException('Import job is not Batchable. Add "use Illuminate\Bus\Batchable;" to the job.');
        }

        // Stripe account id format sanity
        if (! Str::startsWith($stripeAccountId, 'acct_')) {
            // Not fatal, but warn in console
            $this->pushConsole("Warning: Stripe Account ID does not start with acct_ ({$stripeAccountId})", 'warn');
        }
    }

    /* ----------------------------
     * Parse CSV (with richer diagnostics)
     * ---------------------------- */

    public function parseCsv(): void
    {
        $s = $this->normalizedFormState();

        if (! $this->ensureRequiredOrNotify($s['csvPath'], $s['stripeAccountId'])) {
            return;
        }

        try {
            $this->preflightOrThrow($s['csvPath'], $s['stripeAccountId']);
        } catch (Throwable $e) {
            $this->pushConsole('Preflight failed: ' . $e->getMessage(), 'err');
            Notification::make()->title('Preflight failed')->body($e->getMessage())->danger()->send();
            Log::error('ShopifyImportTest preflight failed', ['error' => $e->getMessage()]);
            return;
        }

        $absolute = Storage::disk('local')->path($s['csvPath']);

        try {
            $importer = new ShopifyCsvImporter();
            $parsed   = $importer->parse($absolute);

            $products = $parsed['products'] ?? [];

            // Extra diagnostics
            $handles = array_values(array_filter(array_map(fn ($p) => (string) ($p['handle'] ?? ''), $products)));
            $dupes = [];
            if (! empty($handles)) {
                $counts = array_count_values($handles);
                foreach ($counts as $h => $c) {
                    if ($c > 1) $dupes[] = ['handle' => $h, 'count' => $c];
                }
            }

            // Existing map
            $existingMap = [];
            try {
                if (! empty($handles)) {
                    $existing = ConnectedProduct::query()
                        ->where('stripe_account_id', $s['stripeAccountId'])
                        ->whereIn('handle', $handles)
                        ->get(['id', 'handle', 'title', 'updated_at', 'stripe_product_id', 'extra']);

                    foreach ($existing as $row) {
                        $existingMap[(string) $row->handle] = $row;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('ShopifyImportTest: existing ConnectedProduct lookup failed', ['error' => $e->getMessage()]);
            }

            $plan = [
                'total_products' => count($products),
                'existing'       => 0,
                'new'            => 0,
                'would_update'   => 0,
                'will_skip'      => 0,
                'duplicates'     => $dupes,
                'items'          => [],
                'existing_items' => [],
            ];

            foreach ($products as $p) {
                $handle = (string) ($p['handle'] ?? '');
                if ($handle === '') continue;

                $variantCount = (int) ($p['variant_count'] ?? count($p['variants'] ?? []));
                $imageCount   = is_array($p['images'] ?? null) ? count($p['images']) : (int) ($p['image_count'] ?? 0);

                $exists = isset($existingMap[$handle]);
                $diffs  = [];

                if ($exists) {
                    $plan['existing']++;

                    $existingTitle = (string) ($existingMap[$handle]->title ?? '');
                    $newTitle      = (string) ($p['title'] ?? '');
                    if ($existingTitle !== '' && $newTitle !== '' && $existingTitle !== $newTitle) $diffs[] = 'title';

                    $exExtra = (array) ($existingMap[$handle]->extra ?? []);
                    $exVar   = (int) data_get($exExtra, 'shopify.variant_count', 0);
                    $exImg   = (int) data_get($exExtra, 'shopify.image_count', 0);

                    if ($exVar && $variantCount && $exVar !== $variantCount) $diffs[] = 'variants';
                    if ($exImg && $imageCount && $exImg !== $imageCount)     $diffs[] = 'images';

                    $action = $s['updateExisting'] ? 'update' : 'skip';
                    if ($s['updateExisting']) $plan['would_update']++;
                    else $plan['will_skip']++;

                    $plan['existing_items'][] = [
                        'handle'            => $handle,
                        'title'             => (string) ($p['title'] ?? ''),
                        'stripe_product_id' => (string) ($existingMap[$handle]->stripe_product_id ?? ''),
                        'updated_at'        => optional($existingMap[$handle]->updated_at)->toDateTimeString(),
                    ];
                } else {
                    $plan['new']++;
                    $action = 'create';
                }

                $plan['items'][] = [
                    'title'         => (string) ($p['title'] ?? ''),
                    'handle'        => $handle,
                    'vendor'        => (string) ($p['vendor'] ?? ''),
                    'type'          => (string) ($p['type'] ?? ''),
                    'variant_count' => $variantCount,
                    'image_count'   => $imageCount,
                    'min_price'     => $p['variant_min_price'] ?? null,
                    'max_price'     => $p['variant_max_price'] ?? null,
                    'action'        => $action,
                    'diffs'         => $diffs,
                ];
            }

            $this->parseResult  = $parsed;
            $this->planResult   = $plan;
            $this->importResult = null;

            $this->importProgress = array_merge($this->importProgress, [
                'status'          => 'pending',
                'current'         => 0,
                'total'           => (int) $plan['total_products'],
                'percent'         => 0,
                'imported'        => 0,
                'skipped'         => 0,
                'updated'         => 0,
                'created'         => 0,
                'errors'          => 0,
                'download_images' => $s['downloadImages'],
                'update_existing' => $s['updateExisting'],
                'currency'        => $s['currency'],
                'chunk_size'      => $s['chunkSize'],
            ]);

            $this->importConsole = [];
            $this->pushConsole(
                "Parse OK: {$plan['total_products']} products | new={$plan['new']} existing={$plan['existing']} update={$plan['would_update']} skip={$plan['will_skip']}",
                'ok'
            );

            if (! empty($dupes)) {
                $this->pushConsole("Warning: duplicate handles found (" . count($dupes) . "). Import will dedupe by handle.", 'warn');
            }

            Notification::make()->title('Parse complete')->body('CSV parsed and plan generated.')->success()->send();
        } catch (Throwable $e) {
            $this->parseResult = null;
            $this->planResult  = null;
            $this->importResult = null;

            $this->importProgress['status'] = 'failed';
            $this->pushConsole('Parse failed: ' . $e->getMessage(), 'err');

            Notification::make()->title('Parse failed')->body($e->getMessage())->danger()->send();

            Log::error('ShopifyImportTest parse failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }
    }

    /* ----------------------------
     * Run import (no default Laravel error pages)
     * ---------------------------- */

    public function runImport(): void
    {
        $s = $this->normalizedFormState();

        if (! $this->ensureRequiredOrNotify($s['csvPath'], $s['stripeAccountId'])) {
            return;
        }

        try {
            $this->preflightOrThrow($s['csvPath'], $s['stripeAccountId']);
        } catch (Throwable $e) {
            $this->pushConsole('Preflight failed: ' . $e->getMessage(), 'err');
            Notification::make()->title('Preflight failed')->body($e->getMessage())->danger()->send();
            Log::error('ShopifyImportTest preflight failed', ['error' => $e->getMessage()]);
            return;
        }

        // Ensure parse exists
        if (! $this->parseResult || ! $this->planResult) {
            $this->parseCsv();
            if (! $this->parseResult || ! $this->planResult) return;
        }

        $products = $this->parseResult['products'] ?? [];
        $total    = count($products);

        if ($total <= 0) {
            Notification::make()->title('No products found')->body('Parsed CSV contains 0 products.')->warning()->send();
            return;
        }

        $runId = (string) Str::uuid();
        $this->currentRunId = $runId;

        // Write run JSON for jobs
        $jsonRel = "tmp/shopify-import/runs/{$runId}.json";
        Storage::disk('local')->put($jsonRel, json_encode([
            'meta' => [
                'stripe_account_id' => $s['stripeAccountId'],
                'currency' => $s['currency'],
                'download_images' => $s['downloadImages'],
                'update_existing' => $s['updateExisting'],
                'created_at' => now()->toIso8601String(),
                'source_csv' => $s['csvPath'],
            ],
            'products' => $products,
        ], JSON_UNESCAPED_UNICODE));

        // init cache
        Cache::put($this->cacheKey('progress', $runId), [
            'status'          => 'running',
            'current'         => 0,
            'total'           => $total,
            'percent'         => 0,
            'imported'        => 0,
            'skipped'         => 0,
            'updated'         => 0,
            'created'         => 0,
            'errors'          => 0,
            'download_images' => $s['downloadImages'],
            'update_existing' => $s['updateExisting'],
            'currency'        => $s['currency'],
            'chunk_size'      => $s['chunkSize'],
            'run_id'          => $runId,
            'batch_id'        => null,
        ], $this->ttl());

        Cache::put($this->cacheKey('console', $runId), [], $this->ttl());
        Cache::put($this->cacheKey('recent', $runId), [], $this->ttl());
        Cache::put($this->cacheKey('result', $runId), [
            'run_id'      => $runId,
            'batch_id'    => null,
            'finished_at' => null,
            'stats'       => ['import' => [
                'total_products' => $total, 'imported' => 0, 'skipped' => 0, 'updated' => 0, 'created' => 0, 'error_count' => 0,
            ]],
            'errors' => [],
            'per_product' => [],
            'plan' => $this->planResult,
            'last_error' => null,
        ], $this->ttl());

        $this->pushConsole("Import start: run={$runId} total={$total} chunk={$s['chunkSize']} currency={$s['currency']} images=" . ($s['downloadImages'] ? 'ON' : 'OFF') . " update=" . ($s['updateExisting'] ? 'ON' : 'OFF'), 'info', $runId);

        // build jobs
        $jobs = [];
        for ($offset = 0; $offset < $total; $offset += $s['chunkSize']) {
            $jobs[] = new ImportShopifyProductsChunkJob(
                runId: $runId,
                stripeAccountId: $s['stripeAccountId'],
                jsonRelativePath: $jsonRel,
                offset: $offset,
                limit: min($s['chunkSize'], $total - $offset),
                currency: $s['currency'],
                downloadImages: $s['downloadImages'],
                updateExisting: $s['updateExisting'],
            );
        }

        try {
            $batch = Bus::batch($jobs)
                ->name("Shopify CSV Import ({$s['stripeAccountId']})")
                ->allowFailures()
                ->then(function (Batch $batch) use ($runId): void {
                    $progress = Cache::get($this->cacheKey('progress', $runId), []);
                    if (! is_array($progress)) $progress = [];
                    $progress['status'] = 'finished';
                    $progress['percent'] = 100;
                    $progress['batch_id'] = $batch->id;
                    Cache::put($this->cacheKey('progress', $runId), $progress, $this->ttl());

                    $result = Cache::get($this->cacheKey('result', $runId), []);
                    if (! is_array($result)) $result = [];
                    $result['finished_at'] = now()->toIso8601String();
                    $result['batch_id'] = $batch->id;
                    $result['stats']['import'] = [
                        'total_products' => (int) ($progress['total'] ?? 0),
                        'imported'       => (int) ($progress['imported'] ?? 0),
                        'skipped'        => (int) ($progress['skipped'] ?? 0),
                        'updated'        => (int) ($progress['updated'] ?? 0),
                        'created'        => (int) ($progress['created'] ?? 0),
                        'error_count'    => (int) ($progress['errors'] ?? 0),
                    ];
                    Cache::put($this->cacheKey('result', $runId), $result, $this->ttl());

                    $this->pushConsole('Batch finished.', 'ok', $runId);
                })
                ->catch(function (Batch $batch, Throwable $e) use ($runId): void {
                    $progress = Cache::get($this->cacheKey('progress', $runId), []);
                    if (! is_array($progress)) $progress = [];
                    $progress['status'] = 'failed';
                    $progress['batch_id'] = $batch->id;
                    Cache::put($this->cacheKey('progress', $runId), $progress, $this->ttl());

                    $this->pushConsole('Batch failed: ' . $e->getMessage(), 'err', $runId);
                    $this->storeLastError($runId, 'Batch failed: ' . $e->getMessage(), $e);

                    Log::error('ShopifyImportTest batch failed', [
                        'run_id' => $runId,
                        'batch_id' => $batch->id,
                        'error' => $e->getMessage(),
                    ]);
                })
                ->finally(function (Batch $batch) use ($runId): void {
                    $progress = Cache::get($this->cacheKey('progress', $runId), []);
                    if (! is_array($progress)) $progress = [];
                    $progress['batch_id'] = $batch->id;
                    Cache::put($this->cacheKey('progress', $runId), $progress, $this->ttl());

                    $result = Cache::get($this->cacheKey('result', $runId), []);
                    if (! is_array($result)) $result = [];
                    $result['batch_id'] = $batch->id;
                    Cache::put($this->cacheKey('result', $runId), $result, $this->ttl());
                })
                ->dispatch();

            $this->currentBatchId = $batch->id;

            // hydrate UI
            $this->importProgress = Cache::get($this->cacheKey('progress', $runId), $this->importProgress);
            $this->importConsole  = Cache::get($this->cacheKey('console', $runId), []);
            $this->recentProducts = Cache::get($this->cacheKey('recent', $runId), []);

            Notification::make()
                ->title('Import queued')
                ->body("Queued {$total} products in " . count($jobs) . " jobs. Make sure queue workers are running.")
                ->success()
                ->send();
        } catch (Throwable $e) {
            // This is the key: no default Laravel error page
            $this->pushConsole('Dispatch failed: ' . $e->getMessage(), 'err', $runId);

            Cache::put($this->cacheKey('progress', $runId), array_merge(Cache::get($this->cacheKey('progress', $runId), []), [
                'status' => 'failed',
            ]), $this->ttl());

            $this->storeLastError($runId, 'Dispatch failed: ' . $e->getMessage(), $e);

            Notification::make()
                ->title('Import failed to start')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('ShopifyImportTest dispatch failed', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public function refreshProgress(): void
    {
        if (! $this->currentRunId) return;

        $runId = $this->currentRunId;

        $progress = Cache::get($this->cacheKey('progress', $runId));
        if (is_array($progress)) $this->importProgress = array_merge($this->importProgress, $progress);

        $console = Cache::get($this->cacheKey('console', $runId));
        if (is_array($console)) $this->importConsole = array_slice($console, -self::CONSOLE_MAX);

        $recent = Cache::get($this->cacheKey('recent', $runId));
        if (is_array($recent)) $this->recentProducts = array_slice($recent, -self::RECENT_MAX);

        $status = (string) ($this->importProgress['status'] ?? 'idle');
        if ($status === 'finished' || $status === 'failed') {
            $result = Cache::get($this->cacheKey('result', $runId));
            if (is_array($result)) $this->importResult = $result;
        }
    }

    public function clearConsole(): void
    {
        $this->importConsole = [];

        if ($this->currentRunId) {
            Cache::put($this->cacheKey('console', $this->currentRunId), [], $this->ttl());
        }
    }

    public function resetRunState(): void
    {
        $this->currentRunId = null;
        $this->currentBatchId = null;
        $this->parseResult = null;
        $this->planResult = null;
        $this->importResult = null;

        $this->importProgress = array_merge($this->importProgress, [
            'status' => 'idle',
            'current' => 0,
            'total' => 0,
            'percent' => 0,
            'imported' => 0,
            'skipped' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => 0,
            'run_id' => null,
            'batch_id' => null,
        ]);

        $this->importConsole = [];
        $this->recentProducts = [];

        Notification::make()->title('Reset')->body('View state reset.')->success()->send();
    }
}
