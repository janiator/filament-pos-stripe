<?php

namespace App\Jobs\Shopify;

use App\Models\ConnectedProduct;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Throwable;

class ImportShopifyProductsChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * IMPORTANT:
     * Do NOT redeclare $queue here (Queueable trait already defines it).
     * Use onQueue() instead.
     */
    private const DEFAULT_QUEUE = 'shopify-import';

    public int $timeout = 0;
    public int $tries   = 1;

    public function __construct(
        public string $runId,
        public string $stripeAccountId,
        public string $jsonRelativePath,
        public int $offset,
        public int $limit,
        public string $currency = 'nok',
        public bool $includeImages = true,        // include image URLs to Stripe product.images
        public bool $strictImageCheck = true,     // validate URLs before sending
        public bool $updateExisting = true,       // update product+prices if exists; else skip
    ) {
        $this->onQueue(self::DEFAULT_QUEUE);
    }

    private const TTL_HOURS   = 12;
    private const CONSOLE_MAX = 600;
    private const RECENT_MAX  = 40;

    private static function ttl(): \DateTimeInterface
    {
        return now()->addHours(self::TTL_HOURS);
    }

    private static function cacheKey(string $runId, string $suffix): string
    {
        return "shopify_import:{$runId}:{$suffix}";
    }

    private function pushConsole(string $message, string $level = 'info'): void
    {
        $key = self::cacheKey($this->runId, 'console');

        $line = [
            'time'    => now()->format('H:i:s'),
            'level'   => $level,
            'message' => $message,
        ];

        $console = Cache::get($key, []);
        if (! is_array($console)) {
            $console = [];
        }

        $console[] = $line;

        Cache::put($key, array_slice($console, -self::CONSOLE_MAX), self::ttl());
    }

    private function addRecent(array $item): void
    {
        $key = self::cacheKey($this->runId, 'recent');

        $recent = Cache::get($key, []);
        if (! is_array($recent)) {
            $recent = [];
        }

        $recent[] = $item;

        Cache::put($key, array_slice($recent, -self::RECENT_MAX), self::ttl());
    }

    private function withProgressLock(callable $fn): array
    {
        $lockKey = self::cacheKey($this->runId, 'progress_lock');

        try {
            $lock = Cache::lock($lockKey, 8);

            return $lock->block(3, function () use ($fn) {
                return $fn();
            });
        } catch (Throwable $e) {
            // If cache driver doesn't support locks, fallback.
            return $fn();
        }
    }

    private function bumpProgress(array $delta): array
    {
        return $this->withProgressLock(function () use ($delta) {
            $key = self::cacheKey($this->runId, 'progress');

            $p = Cache::get($key, []);
            if (! is_array($p)) {
                $p = [];
            }

            foreach ($delta as $k => $v) {
                $p[$k] = (int) ($p[$k] ?? 0) + (int) $v;
            }

            $p['last_tick_at'] = now()->toIso8601String();

            $total = (int) ($p['total'] ?? 0);
            $cur   = (int) ($p['current'] ?? 0);
            $p['percent'] = $total > 0 ? (int) floor(($cur / $total) * 100) : 0;

            // Throughput + ETA
            $startedAt = (string) ($p['started_at'] ?? '');
            if ($startedAt !== '') {
                try {
                    $started = Carbon::parse($startedAt);
                    $elapsed = max(1, $started->diffInSeconds(now()));
                    $ratePerMin = ($cur / $elapsed) * 60;

                    $p['rate_per_min'] = (int) round($ratePerMin);

                    $remaining = max(0, $total - $cur);
                    $etaSec = $ratePerMin > 0 ? (int) round(($remaining / $ratePerMin) * 60) : null;
                    $p['eta_seconds'] = $etaSec;
                } catch (Throwable $e) {
                    // ignore
                }
            }

            Cache::put($key, $p, self::ttl());

            return $p;
        });
    }

    private function setFailed(string $message, ?Throwable $e = null): void
    {
        $this->withProgressLock(function () {
            $progressKey = self::cacheKey($this->runId, 'progress');
            $p = Cache::get($progressKey, []);
            if (! is_array($p)) {
                $p = [];
            }
            $p['status'] = 'failed';
            Cache::put($progressKey, $p, self::ttl());
            return $p;
        });

        $resultKey = self::cacheKey($this->runId, 'result');
        $result = Cache::get($resultKey, []);
        if (! is_array($result)) {
            $result = [];
        }

        $at = now()->toIso8601String();
        $result['last_error'] = [
            'message' => $message,
            'at'      => $at,
        ];

        if ($e) {
            $result['last_error']['exception'] = [
                'class'      => get_class($e),
                'code'       => $e->getCode(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace_head' => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 24)),
            ];
        }

        $errors = (array) ($result['errors'] ?? []);
        $errors[] = "{$at} — {$message}";
        $result['errors'] = array_slice($errors, -80);

        Cache::put($resultKey, $result, self::ttl());

        $this->pushConsole($message, 'err');

        Log::error('ImportShopifyProductsChunkJob failed', [
            'run_id' => $this->runId,
            'msg'    => $message,
            'ex'     => $e ? $e->getMessage() : null,
        ]);
    }

    private function appendPerProduct(array $row): void
    {
        $key = self::cacheKey($this->runId, 'result');

        $result = Cache::get($key, []);
        if (! is_array($result)) {
            $result = [];
        }

        $pp = $result['per_product'] ?? [];
        if (! is_array($pp)) {
            $pp = [];
        }

        $pp[] = $row;

        $result['per_product'] = array_slice($pp, -700);

        Cache::put($key, $result, self::ttl());
    }

    private function stripeSecret(): string
    {
        // Robust: prefer config, fallback to env if config cache stale.
        $secret = trim((string) (config('services.stripe.secret') ?: env('STRIPE_SECRET') ?: ''));

        // Some people store it as STRIPE_SECRET_KEY or STRIPE_API_KEY etc - optional fallback:
        if ($secret === '') {
            $secret = trim((string) (env('STRIPE_API_KEY') ?: env('STRIPE_SECRET_KEY') ?: ''));
        }

        return $secret;
    }

    private function normalizeImageUrl(?string $url): string
    {
        $u = trim((string) $url);
        if ($u === '') return '';

        if (str_starts_with($u, '//')) {
            $u = 'https:' . $u;
        }

        if (str_starts_with($u, 'http://')) {
            $u = 'https://' . substr($u, 7);
        }

        $u = preg_replace('/\s+/', '', $u) ?: $u;

        return $u;
    }

    private function validateImageUrl(string $url): array
    {
        $url = $this->normalizeImageUrl($url);
        if ($url === '') return [false, 'empty', $url];

        if (! str_starts_with($url, 'https://')) return [false, 'not_https', $url];

        $cacheKey = 'shopify_import:image_ok:' . sha1($url);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('ok', $cached)) {
            return [(bool) $cached['ok'], (string) ($cached['why'] ?? 'cached'), $url];
        }

        try {
            $base = Http::timeout(10)->withOptions(['allow_redirects' => true]);

            $resp = $base->head($url);
            if (in_array($resp->status(), [405, 403, 400], true)) {
                $resp = $base->withHeaders(['Range' => 'bytes=0-2048'])->get($url);
            }

            if (! in_array($resp->status(), [200, 206], true)) {
                Cache::put($cacheKey, ['ok' => false, 'why' => 'http_' . $resp->status()], now()->addHours(6));
                return [false, 'http_' . $resp->status(), $url];
            }

            $ct = (string) $resp->header('Content-Type');
            if ($ct === '' || ! str_starts_with(strtolower($ct), 'image/')) {
                $why = 'content_type_' . ($ct ?: 'missing');
                Cache::put($cacheKey, ['ok' => false, 'why' => $why], now()->addHours(6));
                return [false, $why, $url];
            }

            $cl = (string) $resp->header('Content-Length');
            if ($cl !== '' && is_numeric($cl)) {
                $bytes = (int) $cl;
                if ($bytes > 15 * 1024 * 1024) {
                    Cache::put($cacheKey, ['ok' => false, 'why' => 'too_large'], now()->addHours(6));
                    return [false, 'too_large', $url];
                }
            }

            Cache::put($cacheKey, ['ok' => true, 'why' => 'ok'], now()->addHours(12));
            return [true, 'ok', $url];
        } catch (Throwable $e) {
            $why = 'exception_' . Str::limit($e->getMessage(), 90, '');
            Cache::put($cacheKey, ['ok' => false, 'why' => $why], now()->addHours(2));
            return [false, $why, $url];
        }
    }

    private function pickImageUrls(array $product): array
    {
        $images = [];

        if (isset($product['images']) && is_array($product['images'])) {
            foreach ($product['images'] as $img) {
                if (is_string($img)) $images[] = $img;
                elseif (is_array($img) && isset($img['src']) && is_string($img['src'])) $images[] = $img['src'];
                elseif (is_array($img) && isset($img['url']) && is_string($img['url'])) $images[] = $img['url'];
            }
        }

        if (isset($product['image']) && is_string($product['image'])) $images[] = $product['image'];
        if (isset($product['image_src']) && is_string($product['image_src'])) $images[] = $product['image_src'];

        $uniq = [];
        foreach ($images as $u) {
            $u = $this->normalizeImageUrl($u);
            if ($u === '') continue;
            if (isset($uniq[$u])) continue;
            $uniq[$u] = true;
        }

        return array_slice(array_keys($uniq), 0, 8);
    }

    private function parseMoneyToFloat(mixed $value): ?float
    {
        if ($value === null) return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $s = trim((string) $value);
        if ($s === '') return null;

        $s = str_replace("\xc2\xa0", ' ', $s);
        $s = str_replace(' ', '', $s);

        if (str_contains($s, ',') && ! str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        if (! is_numeric($s)) return null;

        return (float) $s;
    }

    private function variantKey(array $variant, string $handle): string
    {
        $id = (string) ($variant['id'] ?? $variant['variant_id'] ?? '');
        $sku = (string) ($variant['sku'] ?? '');

        $opt1 = (string) ($variant['option1'] ?? $variant['option_1'] ?? '');
        $opt2 = (string) ($variant['option2'] ?? $variant['option_2'] ?? '');
        $opt3 = (string) ($variant['option3'] ?? $variant['option_3'] ?? '');

        $raw = $id !== '' ? $id : ($sku !== '' ? $sku : trim($opt1 . '|' . $opt2 . '|' . $opt3));
        if ($raw === '') $raw = 'default';

        $key = 'shp:' . $handle . ':' . $raw;

        return Str::limit($key, 180, '');
    }

    private function unitAmountFromVariant(array $variant): ?int
    {
        $price = $variant['price'] ?? $variant['variant_price'] ?? $variant['price_amount'] ?? null;

        $f = $this->parseMoneyToFloat($price);
        if ($f === null) return null;

        $amount = (int) round($f * 100);
        if ($amount <= 0) return null;

        return $amount;
    }

    private function syncVariantPrice(
        StripeClient $stripe,
        string $stripeProductId,
        string $handle,
        array $variant,
        string $currency,
        array &$variantsMap,
    ): array {
        $vKey = $this->variantKey($variant, $handle);
        $unitAmount = $this->unitAmountFromVariant($variant);

        $title = (string) ($variant['title'] ?? '');
        $sku   = (string) ($variant['sku'] ?? '');
        $barcode = (string) ($variant['barcode'] ?? '');

        if ($unitAmount === null) {
            return [
                'ok' => false,
                'vkey' => $vKey,
                'why' => 'missing_or_invalid_price',
            ];
        }

        $existing = $variantsMap[$vKey] ?? null;
        $existingPriceId = is_array($existing) ? (string) ($existing['price_id'] ?? '') : '';
        $existingAmount  = is_array($existing) ? (int) ($existing['unit_amount'] ?? 0) : 0;
        $existingCurr    = is_array($existing) ? (string) ($existing['currency'] ?? '') : '';

        if ($existingPriceId !== '' && $existingAmount === $unitAmount && strtolower($existingCurr) === strtolower($currency)) {
            return [
                'ok' => true,
                'vkey' => $vKey,
                'action' => 'reused',
                'price_id' => $existingPriceId,
                'unit_amount' => $unitAmount,
            ];
        }

        if ($existingPriceId !== '') {
            try {
                $stripe->prices->update(
                    $existingPriceId,
                    ['active' => false],
                    ['stripe_account' => $this->stripeAccountId]
                );
            } catch (Throwable $e) {
                // best effort
            }
        }

        $payload = [
            'product'     => $stripeProductId,
            'currency'    => strtolower($currency),
            'unit_amount' => $unitAmount,
            'metadata'    => array_filter([
                'source'         => 'shopify_csv_import',
                'shopify_handle' => $handle,
                'variant_key'    => $vKey,
                'variant_title'  => $title !== '' ? $title : null,
                'sku'            => $sku !== '' ? $sku : null,
                'barcode'        => $barcode !== '' ? $barcode : null,
                'option1'        => (string) ($variant['option1'] ?? $variant['option_1'] ?? ''),
                'option2'        => (string) ($variant['option2'] ?? $variant['option_2'] ?? ''),
                'option3'        => (string) ($variant['option3'] ?? $variant['option_3'] ?? ''),
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        $price = $stripe->prices->create($payload, ['stripe_account' => $this->stripeAccountId]);

        $variantsMap[$vKey] = [
            'price_id'    => $price->id,
            'unit_amount' => $unitAmount,
            'currency'    => strtolower($currency),
            'sku'         => $sku,
            'title'       => $title,
            'updated_at'  => now()->toIso8601String(),
        ];

        return [
            'ok' => true,
            'vkey' => $vKey,
            'action' => ($existingPriceId !== '' ? 'replaced' : 'created'),
            'price_id' => $price->id,
            'unit_amount' => $unitAmount,
        ];
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            $this->pushConsole("Chunk cancelled (offset {$this->offset})", 'warn');
            return;
        }

        // Avoid spam if secret is missing: log only once per run.
        $secret = $this->stripeSecret();
        if ($secret === '') {
            $flagKey = self::cacheKey($this->runId, 'stripe_secret_missing');
            $first = Cache::add($flagKey, 1, self::ttl());

            if ($first) {
                $this->setFailed('Missing STRIPE secret (services.stripe.secret / env STRIPE_SECRET). Clear config cache + restart workers.');
            }

            return;
        }

        $this->pushConsole(
            "Chunk start offset={$this->offset} limit={$this->limit} queue=" . ($this->queue ?? 'default') . " stripe_secret=LOADED(len=" . strlen($secret) . ")",
            'info'
        );

        try {
            $raw = Storage::disk('local')->get($this->jsonRelativePath);
            $json = json_decode($raw, true);

            $all = $json['products'] ?? [];
            if (! is_array($all)) $all = [];

            $slice = array_slice($all, $this->offset, $this->limit);

            $stripe = new StripeClient($secret);

            foreach ($slice as $idx => $product) {
                $title  = (string) ($product['title'] ?? '');
                $handle = (string) ($product['handle'] ?? '');
                $vendor = (string) ($product['vendor'] ?? '');
                $type   = (string) ($product['type'] ?? '');
                $tags   = (string) ($product['tags'] ?? '');

                $display = $title !== '' ? $title : ($handle !== '' ? $handle : '—');

                if ($handle === '') {
                    $this->pushConsole("Skip product '{$display}': missing handle", 'warn');
                    $this->bumpProgress(['current' => 1, 'errors' => 1]);
                    $this->appendPerProduct([
                        'title'   => $display,
                        'handle'  => '',
                        'status'  => 'error',
                        'message' => 'Missing handle (skipped).',
                    ]);
                    continue;
                }

                $variants = $product['variants'] ?? [];
                if (! is_array($variants)) $variants = [];
                $variantCount = (int) ($product['variant_count'] ?? count($variants));

                $imageUrls = $this->includeImages ? $this->pickImageUrls($product) : [];
                $imagesTotal = count($imageUrls);

                $this->pushConsole("Product {$handle}: start (variants={$variantCount}, images={$imagesTotal})", 'info');

                $validUrls = [];
                $invalid   = 0;

                if ($this->includeImages && $imagesTotal > 0) {
                    if ($this->strictImageCheck) {
                        foreach ($imageUrls as $u) {
                            [$ok, $why, $norm] = $this->validateImageUrl($u);
                            if ($ok) {
                                $validUrls[] = $norm;
                                $this->pushConsole("Product {$handle}: image OK {$norm}", 'ok');
                            } else {
                                $invalid++;
                                $this->pushConsole("Product {$handle}: image REJECT {$why} {$norm}", 'warn');
                            }
                        }
                    } else {
                        $validUrls = $imageUrls;
                        $this->pushConsole("Product {$handle}: strict check OFF, sending {$imagesTotal} image URLs", 'warn');
                    }
                }

                $this->bumpProgress([
                    'images_total'   => $imagesTotal,
                    'images_valid'   => count($validUrls),
                    'images_invalid' => $invalid,
                ]);

                try {
                    $productCode = 'shopify:' . $handle;

                    $existing = ConnectedProduct::query()
                        ->where('stripe_account_id', $this->stripeAccountId)
                        ->where('product_code', $productCode)
                        ->first();

                    $existingStripeId = $existing?->stripe_product_id ?? null;
                    $hasRealStripeProduct = is_string($existingStripeId) && str_starts_with($existingStripeId, 'prod_');

                    // If there is already a real Stripe product and user disabled update_existing → skip
                    if ($existing && $hasRealStripeProduct && ! $this->updateExisting) {
                        $this->pushConsole("Product {$handle}: EXISTS in Stripe and update_existing=OFF -> skipped", 'warn');

                        $this->bumpProgress(['current' => 1, 'skipped' => 1, 'imported' => 1]);

                        $this->addRecent([
                            'title'         => $display,
                            'handle'        => $handle,
                            'status'        => 'skipped',
                            'variant_count' => $variantCount,
                            'image_count'   => $imagesTotal,
                            'message'       => 'Skipped (already in Stripe & update_existing=OFF)',
                            'at'            => now()->toIso8601String(),
                        ]);

                        $this->appendPerProduct([
                            'title'         => $display,
                            'handle'        => $handle,
                            'status'        => 'skipped',
                            'variant_count' => $variantCount,
                            'image_count'   => $imagesTotal,
                            'message'       => 'Skipped (already in Stripe & update_existing=OFF)',
                        ]);

                        continue;
                    }

                    $payload = [
                        'name'        => $title !== '' ? $title : $handle,
                        'metadata'    => array_filter([
                            'source'         => 'shopify_csv_import',
                            'shopify_handle' => $handle,
                            'vendor'         => $vendor !== '' ? $vendor : null,
                            'type'           => $type !== '' ? $type : null,
                        ], fn ($v) => $v !== null && $v !== ''),
                    ];

                    if ($this->includeImages && ! empty($validUrls)) {
                        $payload['images'] = $validUrls;
                    }

                    // Decide whether to update existing Stripe product or create new
                    $stripeProductId = '';
                    $status = 'created';

                    if ($hasRealStripeProduct) {
                        $stripeProductId = (string) $existingStripeId;

                        $this->pushConsole("Product {$handle}: updating Stripe product {$stripeProductId}", 'info');

                        $stripeProduct = $stripe->products->update(
                            $stripeProductId,
                            $payload,
                            ['stripe_account' => $this->stripeAccountId]
                        );

                        $status = 'updated';
                        $this->bumpProgress(['updated' => 1]);
                    } else {
                        $this->pushConsole("Product {$handle}: creating Stripe product", 'info');

                        $stripeProduct = $stripe->products->create(
                            $payload,
                            ['stripe_account' => $this->stripeAccountId]
                        );

                        $stripeProductId = (string) $stripeProduct->id;
                        $status = 'created';
                        $this->bumpProgress(['created' => 1]);
                    }

                    // Upsert into ConnectedProduct (this is the "real" sync)
                    $cp = $existing ?: new ConnectedProduct();
                    $cp->stripe_account_id = $this->stripeAccountId;
                    $cp->product_code      = $productCode;
                    $cp->name              = $payload['name'];
                    if ($type !== '') {
                        $cp->type = $type;
                    } elseif (! $cp->type) {
                        $cp->type = 'shopify_product';
                    }

                    $cp->stripe_product_id = $stripeProductId;
                    $cp->currency          = $this->currency ?: ($cp->currency ?: 'nok');
                    $cp->active            = true;

                    $productMeta = (array) ($cp->product_meta ?? []);
                    $productMeta['source'] = $productMeta['source'] ?? 'shopify_csv_import';

                    $shopifyMeta = (array) ($productMeta['shopify'] ?? []);
                    $shopifyMeta['handle'] = $handle;
                    $shopifyMeta['vendor'] = $vendor;
                    $shopifyMeta['type']   = $type;
                    $shopifyMeta['tags']   = $tags;
                    $shopifyMeta['title']  = $title;

                    $shopifyMeta['variant_count'] = $variantCount;
                    $shopifyMeta['image_count']   = $imagesTotal;
                    $shopifyMeta['images_valid']  = count($validUrls);
                    $shopifyMeta['images_invalid']= $invalid;

                    $variantsMap = (array) data_get($shopifyMeta, 'variants_map', []);

                    $pricesCreated  = 0;
                    $pricesReused   = 0;
                    $pricesReplaced = 0;
                    $variantsOk     = 0;
                    $variantsBad    = 0;

                    if (! empty($variants)) {
                        $this->pushConsole("Product {$handle}: syncing {$variantCount} variant prices…", 'info');

                        foreach ($variants as $variant) {
                            if (! is_array($variant)) continue;

                            $res = $this->syncVariantPrice(
                                stripe: $stripe,
                                stripeProductId: $stripeProductId,
                                handle: $handle,
                                variant: $variant,
                                currency: $this->currency,
                                variantsMap: $variantsMap,
                            );

                            if (! ($res['ok'] ?? false)) {
                                $variantsBad++;
                                $this->pushConsole("Product {$handle}: variant price FAIL {$res['why']} ({$res['vkey']})", 'warn');
                                continue;
                            }

                            $variantsOk++;
                            $action = (string) ($res['action'] ?? 'ok');

                            if ($action === 'created') $pricesCreated++;
                            elseif ($action === 'reused') $pricesReused++;
                            elseif ($action === 'replaced') $pricesReplaced++;

                            $this->pushConsole("Product {$handle}: variant {$res['vkey']} price {$action} ({$res['price_id']})", 'ok');
                        }
                    } else {
                        $this->pushConsole("Product {$handle}: no variants array found (skipping prices)", 'warn');
                    }

                    data_set($shopifyMeta, 'variants_map', $variantsMap);

                    $productMeta['shopify'] = $shopifyMeta;
                    $cp->product_meta = $productMeta;
                    $cp->save();

                    $this->bumpProgress([
                        'variants_total'   => $variantCount,
                        'variants_ok'      => $variantsOk,
                        'variants_bad'     => $variantsBad,
                        'prices_created'   => $pricesCreated,
                        'prices_reused'    => $pricesReused,
                        'prices_replaced'  => $pricesReplaced,
                    ]);

                    $this->bumpProgress(['current' => 1, 'imported' => 1]);

                    $this->addRecent([
                        'title'         => $payload['name'],
                        'handle'        => $handle,
                        'status'        => $status,
                        'variant_count' => $variantCount,
                        'image_count'   => $imagesTotal,
                        'message'       => "Stripe product {$status} ({$stripeProductId}) · prices: +{$pricesCreated} ~{$pricesReused} ↻{$pricesReplaced}",
                        'at'            => now()->toIso8601String(),
                    ]);

                    $this->appendPerProduct([
                        'title'            => $payload['name'],
                        'handle'           => $handle,
                        'status'           => $status,
                        'stripe_product_id'=> $stripeProductId,
                        'variant_count'    => $variantCount,
                        'variants_ok'      => $variantsOk,
                        'variants_bad'     => $variantsBad,
                        'prices_created'   => $pricesCreated,
                        'prices_reused'    => $pricesReused,
                        'prices_replaced'  => $pricesReplaced,
                        'image_count'      => $imagesTotal,
                        'images_valid'     => count($validUrls),
                        'images_invalid'   => $invalid,
                        'message'          => "Stripe product {$status}",
                    ]);

                    $this->pushConsole("Product {$handle}: done (product={$stripeProductId})", 'info');
                } catch (Throwable $e) {
                    $this->bumpProgress(['current' => 1, 'errors' => 1]);

                    $msg = "Product failed ({$handle}): " . $e->getMessage();
                    $this->pushConsole($msg, 'err');

                    $this->addRecent([
                        'title'         => $display,
                        'handle'        => $handle,
                        'status'        => 'error',
                        'variant_count' => $variantCount,
                        'image_count'   => $imagesTotal,
                        'message'       => $e->getMessage(),
                        'at'            => now()->toIso8601String(),
                    ]);

                    $this->appendPerProduct([
                        'title'         => $display,
                        'handle'        => $handle,
                        'status'        => 'error',
                        'variant_count' => $variantCount,
                        'image_count'   => $imagesTotal,
                        'message'       => $e->getMessage(),
                    ]);

                    Log::error('Chunk product failed', [
                        'run_id' => $this->runId,
                        'handle' => $handle,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

            $this->pushConsole("Chunk done offset={$this->offset}", 'ok');
        } catch (Throwable $e) {
            $this->setFailed('Chunk crashed: ' . $e->getMessage(), $e);
        }
    }
}
