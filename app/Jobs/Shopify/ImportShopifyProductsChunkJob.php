<?php

namespace App\Jobs\Shopify;

use App\Models\ConnectedProduct;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportShopifyProductsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public string $runId;
    public string $stripeAccountId;
    public string $jsonRelativePath;
    public int $offset;
    public int $limit;
    public string $currency;
    public bool $downloadImages;
    public bool $updateExisting;

    public int $timeout = 1200; // 20 min (chunked anyway)
    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(
        string $runId,
        string $stripeAccountId,
        string $jsonRelativePath,
        int $offset,
        int $limit,
        string $currency = 'nok',
        bool $downloadImages = false,
        bool $updateExisting = true,
    ) {
        $this->runId = $runId;
        $this->stripeAccountId = $stripeAccountId;
        $this->jsonRelativePath = $jsonRelativePath;
        $this->offset = max(0, $offset);
        $this->limit = max(1, $limit);
        $this->currency = strtolower(trim($currency ?: 'nok'));
        $this->downloadImages = (bool) $downloadImages;
        $this->updateExisting = (bool) $updateExisting;
    }

    public function handle(): void
    {
        // If the batch was cancelled, stop cleanly.
        if ($this->batch()?->cancelled()) {
            $this->pushConsole('Chunk cancelled (batch cancelled).', 'warn');
            return;
        }

        $data = $this->loadRunJson();
        $products = (array) ($data['products'] ?? []);
        $slice = array_slice($products, $this->offset, $this->limit);

        if (empty($slice)) {
            $this->pushConsole("Chunk empty (offset={$this->offset}, limit={$this->limit}).", 'warn');
            return;
        }

        $this->pushConsole("Chunk start (offset={$this->offset}, limit=" . count($slice) . ").", 'info');

        foreach ($slice as $p) {
            if ($this->batch()?->cancelled()) {
                $this->pushConsole('Chunk aborted (batch cancelled).', 'warn');
                return;
            }

            $handle = (string) Arr::get($p, 'handle', '');
            $title  = (string) Arr::get($p, 'title', '');

            if ($handle === '') {
                $this->tickProgress('skipped', [
                    'title' => $title ?: '—',
                    'handle' => '',
                    'status' => 'skipped',
                    'variant_count' => (int) Arr::get($p, 'variant_count', 0),
                    'image_count' => $this->imageCount($p),
                    'message' => 'Missing handle (skipped).',
                ]);
                continue;
            }

            try {
                // ---------------------------------------------------------------------------------
                // IMPORTANT:
                // You probably already have “real” Stripe sync logic somewhere.
                // This implementation focuses on:
                //  - stable progress tracking
                //  - DB dedupe by (stripe_account_id + handle)
                //  - per-product diagnostics
                //
                // If you have a dedicated service, replace the “DB upsert” section with your service call.
                // ---------------------------------------------------------------------------------

                $variantCount = $this->variantCount($p);
                $imageCount   = $this->imageCount($p);

                $existing = ConnectedProduct::query()
                    ->where('stripe_account_id', $this->stripeAccountId)
                    ->where('handle', $handle)
                    ->first();

                if ($existing && ! $this->updateExisting) {
                    $this->tickProgress('skipped', [
                        'title' => $title ?: $handle,
                        'handle' => $handle,
                        'status' => 'skipped',
                        'variant_count' => $variantCount,
                        'image_count' => $imageCount,
                        'message' => 'Exists and update_existing=false (skipped).',
                    ]);
                    continue;
                }

                // Minimal “upsert” into your local DB (safe + traceable)
                $payloadExtra = [
                    'shopify' => [
                        'title' => $title,
                        'vendor' => (string) Arr::get($p, 'vendor', ''),
                        'type' => (string) Arr::get($p, 'type', ''),
                        'tags' => (string) Arr::get($p, 'tags', ''),
                        'variant_count' => $variantCount,
                        'image_count' => $imageCount,
                        'min_price' => Arr::get($p, 'variant_min_price'),
                        'max_price' => Arr::get($p, 'variant_max_price'),
                    ],
                    'import' => [
                        'run_id' => $this->runId,
                        'currency' => $this->currency,
                        'download_images' => $this->downloadImages,
                        'update_existing' => $this->updateExisting,
                        'at' => now()->toIso8601String(),
                        'chunk' => ['offset' => $this->offset, 'limit' => $this->limit],
                    ],
                ];

                if ($existing) {
                    $existing->title = $title ?: ($existing->title ?? $handle);
                    $existing->extra = array_merge((array) ($existing->extra ?? []), $payloadExtra);
                    $existing->save();

                    $this->tickProgress('updated', [
                        'title' => $title ?: $handle,
                        'handle' => $handle,
                        'status' => 'updated',
                        'variant_count' => $variantCount,
                        'image_count' => $imageCount,
                        'message' => 'Updated local record (replace with Stripe sync if desired).',
                    ]);
                } else {
                    $row = new ConnectedProduct();
                    $row->stripe_account_id = $this->stripeAccountId;
                    $row->handle = $handle;
                    $row->title = $title ?: $handle;
                    $row->extra = $payloadExtra;

                    // If your model has these columns, keep them safe:
                    if (property_exists($row, 'stripe_product_id')) {
                        $row->stripe_product_id = $row->stripe_product_id ?? null;
                    }

                    $row->save();

                    $this->tickProgress('created', [
                        'title' => $title ?: $handle,
                        'handle' => $handle,
                        'status' => 'created',
                        'variant_count' => $variantCount,
                        'image_count' => $imageCount,
                        'message' => 'Created local record (replace with Stripe sync if desired).',
                    ]);
                }
            } catch (Throwable $e) {
                $this->tickProgress('error', [
                    'title' => $title ?: $handle,
                    'handle' => $handle,
                    'status' => 'error',
                    'variant_count' => $this->variantCount($p),
                    'image_count' => $this->imageCount($p),
                    'message' => $e->getMessage(),
                ], $e);
            }
        }

        $this->pushConsole("Chunk done (offset={$this->offset}).", 'ok');
    }

    public function failed(Throwable $e): void
    {
        // If the whole job fails catastrophically
        $this->pushConsole('Chunk failed: ' . $e->getMessage(), 'err');
        $this->appendError('Job failed: ' . $e->getMessage(), $e);
    }

    /* ------------------------------------------
     * Cache / diagnostics helpers
     * ------------------------------------------ */

    protected function baseKey(): string
    {
        return "shopify_import:{$this->runId}";
    }

    protected function key(string $suffix): string
    {
        return $this->baseKey() . ':' . $suffix;
    }

    protected function ttl()
    {
        return now()->addHours(12);
    }

    protected function pushConsole(string $message, string $level = 'info'): void
    {
        $line = [
            'time' => now()->format('H:i:s'),
            'level' => $level,
            'message' => $message,
        ];

        $console = Cache::get($this->key('console'), []);
        if (! is_array($console)) {
            $console = [];
        }
        $console[] = $line;

        Cache::put($this->key('console'), array_slice($console, -250), $this->ttl());
    }

    protected function tickProgress(string $kind, array $recentItem, ?Throwable $e = null): void
    {
        $progress = Cache::get($this->key('progress'), []);
        if (! is_array($progress)) {
            $progress = [];
        }

        $progress['status'] = $progress['status'] ?? 'running';
        $progress['total'] = (int) ($progress['total'] ?? 0);
        $progress['current'] = (int) ($progress['current'] ?? 0);
        $progress['errors'] = (int) ($progress['errors'] ?? 0);
        $progress['created'] = (int) ($progress['created'] ?? 0);
        $progress['updated'] = (int) ($progress['updated'] ?? 0);
        $progress['skipped'] = (int) ($progress['skipped'] ?? 0);
        $progress['imported'] = (int) ($progress['imported'] ?? 0);

        $progress['current']++;

        if ($kind === 'created') {
            $progress['created']++;
            $progress['imported']++;
        } elseif ($kind === 'updated') {
            $progress['updated']++;
            $progress['imported']++;
        } elseif ($kind === 'skipped') {
            $progress['skipped']++;
        } else {
            $progress['errors']++;
        }

        $total = max(0, (int) ($progress['total'] ?? 0));
        $progress['percent'] = $total > 0 ? (int) round(($progress['current'] / $total) * 100) : 0;

        Cache::put($this->key('progress'), $progress, $this->ttl());

        // recent
        $recentItem['at'] = now()->toIso8601String();
        $recent = Cache::get($this->key('recent'), []);
        if (! is_array($recent)) {
            $recent = [];
        }
        $recent[] = $recentItem;
        Cache::put($this->key('recent'), array_slice($recent, -30), $this->ttl());

        // per product + errors
        if ($kind === 'error') {
            $this->appendError("[{$recentItem['handle']}] {$recentItem['message']}", $e);
        } else {
            $this->appendPerProduct($recentItem);
        }
    }

    protected function appendPerProduct(array $item): void
    {
        $result = Cache::get($this->key('result'), []);
        if (! is_array($result)) {
            $result = [];
        }
        $per = $result['per_product'] ?? [];
        if (! is_array($per)) {
            $per = [];
        }
        $per[] = $item;
        $result['per_product'] = array_slice($per, -200);
        Cache::put($this->key('result'), $result, $this->ttl());
    }

    protected function appendError(string $message, ?Throwable $e = null): void
    {
        $result = Cache::get($this->key('result'), []);
        if (! is_array($result)) {
            $result = [];
        }

        $errors = $result['errors'] ?? [];
        if (! is_array($errors)) {
            $errors = [];
        }
        $errors[] = $message;
        $result['errors'] = array_slice($errors, -200);

        // Last error diagnostics
        $result['last_error'] = [
            'message' => $message,
            'at' => now()->toIso8601String(),
            'exception' => $e ? [
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_head' => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 25)),
            ] : null,
        ];

        Cache::put($this->key('result'), $result, $this->ttl());

        Log::error('Shopify import product error', [
            'run_id' => $this->runId,
            'stripe_account_id' => $this->stripeAccountId,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'message' => $message,
            'exception' => $e ? $e->getMessage() : null,
        ]);
    }

    protected function loadRunJson(): array
    {
        if (! Storage::disk('local')->exists($this->jsonRelativePath)) {
            throw new \RuntimeException("Run JSON not found: {$this->jsonRelativePath}");
        }

        $raw = Storage::disk('local')->get($this->jsonRelativePath);
        $decoded = json_decode($raw ?: '[]', true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Run JSON is invalid JSON.');
        }

        return $decoded;
    }

    protected function variantCount(array $p): int
    {
        $vc = Arr::get($p, 'variant_count');
        if (is_numeric($vc)) {
            return (int) $vc;
        }
        $variants = Arr::get($p, 'variants', []);
        return is_array($variants) ? count($variants) : 0;
    }

    protected function imageCount(array $p): int
    {
        $images = Arr::get($p, 'images', null);
        if (is_array($images)) {
            return count($images);
        }
        $ic = Arr::get($p, 'image_count');
        return is_numeric($ic) ? (int) $ic : 0;
    }
}
