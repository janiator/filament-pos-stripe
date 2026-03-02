<?php

namespace App\Filament\Pages;

use App\Jobs\Shopify\ImportShopifyProductsChunkJob;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
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
use Carbon\Carbon;

class ShopifyImportTest extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string               $navigationLabel = 'Shopify CSV Import';
    protected static ?string               $title           = 'Shopify CSV Import';
    protected static string|UnitEnum|null  $navigationGroup = 'Dev';

    public function getView(): string
    {
        return 'filament.pages.shopify-import-test';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    private const CACHE_TTL_HOURS = 12;
    private const CONSOLE_MAX     = 600;
    private const RECENT_MAX      = 40;

    private const IMPORT_QUEUE = 'shopify-import';

    public array $formData = [
        'csv_path'           => null,
        'stripe_account_id'  => '',
        'currency'           => 'nok',
        'include_images'     => true,
        'strict_image_check' => true,
        'update_existing'    => true,
        'chunk_size'         => 10,
    ];

    public ?array $parseResult  = null;
    public ?array $planResult   = null;
    public ?array $importResult = null;

    public ?string $currentRunId   = null;
    public ?string $currentBatchId = null;

    public array $importProgress = [
        'status'            => 'idle',
        'current'           => 0,
        'total'             => 0,
        'percent'           => 0,

        'imported'          => 0,
        'skipped'           => 0,
        'updated'           => 0,
        'created'           => 0,
        'errors'            => 0,

        'images_total'      => 0,
        'images_valid'      => 0,
        'images_invalid'    => 0,

        'variants_total'    => 0,
        'variants_ok'       => 0,
        'variants_bad'      => 0,
        'prices_created'    => 0,
        'prices_reused'     => 0,
        'prices_replaced'   => 0,

        'started_at'        => null,
        'last_tick_at'      => null,
        'rate_per_min'      => 0,
        'eta_seconds'       => null,

        'include_images'     => false,
        'strict_image_check' => true,
        'update_existing'    => true,
        'currency'           => 'nok',
        'chunk_size'         => 10,

        'queue'             => self::IMPORT_QUEUE,
        'run_id'            => null,
        'batch_id'          => null,
    ];

    public array $importConsole  = [];
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

                Forms\Toggle::make('include_images')
                    ->label('Include product images (Stripe product.images as URLs)')
                    ->helperText('We only send validated HTTPS image URLs to Stripe (optional).')
                    ->inline(false)
                    ->default(true),

                Forms\Toggle::make('strict_image_check')
                    ->label('Strict image URL validation (recommended)')
                    ->helperText('Checks https, status 200/206, content-type image/*, size sanity. Invalid URLs are NOT sent.')
                    ->inline(false)
                    ->default(true),

                Forms\Toggle::make('update_existing')
                    ->label('Update existing products/prices')
                    ->helperText('If enabled: update Stripe product and replace prices if amounts changed. If disabled: existing handle is skipped.')
                    ->inline(false)
                    ->default(true),

                Forms\TextInput::make('chunk_size')
                    ->label('Chunk size (products per job)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(200)
                    ->default(10)
                    ->helperText('Default 10. Jobs run on queue: ' . self::IMPORT_QUEUE)
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

            Actions\Action::make('importLocal')
                ->label('Import to CMS (local only)')
                ->color('warning')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('importLocal'),

            Actions\Action::make('runImport')
                ->label('Sync to Stripe (queue)')
                ->color('success')
                ->icon('heroicon-o-arrow-up-tray')
                ->action('runImport'),

            Actions\Action::make('resetRunState')
                ->label('Reset view')
                ->color('gray')
                ->icon('heroicon-o-arrow-path')
                ->action('resetRunState'),
        ];
    }

    /* -----------------------------------------------------------------
     |   Helpers for cache keys, TTL, batches
     | ----------------------------------------------------------------- */

    private static function ttl(): \DateTimeInterface
    {
        return now()->addHours(self::CACHE_TTL_HOURS);
    }

    private static function cacheKey(string $runId, string $suffix): string
    {
        return "shopify_import:{$runId}:{$suffix}";
    }

    private static function batchMapKey(string $batchId): string
    {
        return "shopify_import:batch_map:{$batchId}:run_id";
    }

    public static function batchThen(Batch $batch): void
    {
        $runId = (string) Cache::get(self::batchMapKey($batch->id), '');
        if ($runId === '') return;

        $progress = Cache::get(self::cacheKey($runId, 'progress'), []);
        if (! is_array($progress)) $progress = [];

        if (($progress['status'] ?? null) !== 'failed') {
            $progress['status']  = 'finished';
            $progress['percent'] = 100;
        }

        $progress['batch_id']     = $batch->id;
        $progress['last_tick_at'] = now()->toIso8601String();

        Cache::put(self::cacheKey($runId, 'progress'), $progress, self::ttl());

        $result = Cache::get(self::cacheKey($runId, 'result'), []);
        if (! is_array($result)) $result = [];

        $result['finished_at'] = now()->toIso8601String();
        $result['batch_id']    = $batch->id;

        $result['stats']['import'] = [
            'total_products'   => (int) ($progress['total'] ?? 0),
            'imported'         => (int) ($progress['imported'] ?? 0),
            'skipped'          => (int) ($progress['skipped'] ?? 0),
            'updated'          => (int) ($progress['updated'] ?? 0),
            'created'          => (int) ($progress['created'] ?? 0),
            'error_count'      => (int) ($progress['errors'] ?? 0),
            'images_total'     => (int) ($progress['images_total'] ?? 0),
            'images_valid'     => (int) ($progress['images_valid'] ?? 0),
            'images_invalid'   => (int) ($progress['images_invalid'] ?? 0),
            'variants_total'   => (int) ($progress['variants_total'] ?? 0),
            'variants_ok'      => (int) ($progress['variants_ok'] ?? 0),
            'variants_bad'     => (int) ($progress['variants_bad'] ?? 0),
            'prices_created'   => (int) ($progress['prices_created'] ?? 0),
            'prices_reused'    => (int) ($progress['prices_reused'] ?? 0),
            'prices_replaced'  => (int) ($progress['prices_replaced'] ?? 0),
            'rate_per_min'     => (int) ($progress['rate_per_min'] ?? 0),
        ];

        Cache::put(self::cacheKey($runId, 'result'), $result, self::ttl());

        $console = Cache::get(self::cacheKey($runId, 'console'), []);
        if (! is_array($console)) $console = [];
        $console[] = [
            'time'    => now()->format('H:i:s'),
            'level'   => 'ok',
            'message' => 'Batch finished.',
        ];
        Cache::put(self::cacheKey($runId, 'console'), array_slice($console, -self::CONSOLE_MAX), self::ttl());
    }

    public static function batchCatch(Batch $batch, Throwable $e): void
    {
        $runId = (string) Cache::get(self::batchMapKey($batch->id), '');
        if ($runId === '') return;

        $progress = Cache::get(self::cacheKey($runId, 'progress'), []);
        if (! is_array($progress)) $progress = [];
        $progress['status']   = 'failed';
        $progress['batch_id'] = $batch->id ?? ($progress['batch_id'] ?? null);

        Cache::put(self::cacheKey($runId, 'progress'), $progress, self::ttl());

        $result = Cache::get(self::cacheKey($runId, 'result'), []);
        if (! is_array($result)) $result = [];

        $at = now()->toIso8601String();
        $result['last_error'] = [
            'message' => "Batch failed: {$e->getMessage()}",
            'at'      => $at,
            'exception' => [
                'class'      => get_class($e),
                'code'       => $e->getCode(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace_head' => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 24)),
            ],
        ];

        $errors = (array) ($result['errors'] ?? []);
        $errors[] = "{$at} — Batch failed: {$e->getMessage()}";
        $result['errors'] = array_slice($errors, -80);

        Cache::put(self::cacheKey($runId, 'result'), $result, self::ttl());

        $console = Cache::get(self::cacheKey($runId, 'console'), []);
        if (! is_array($console)) $console = [];
        $console[] = [
            'time'    => now()->format('H:i:s'),
            'level'   => 'err',
            'message' => 'Batch failed: ' . $e->getMessage(),
        ];
        Cache::put(self::cacheKey($runId, 'console'), array_slice($console, -self::CONSOLE_MAX), self::ttl());

        Log::error('ShopifyImportTest batch failed', [
            'run_id'   => $runId,
            'batch_id' => $batch->id ?? null,
            'error'    => $e->getMessage(),
        ]);
    }

    public static function batchFinally(Batch $batch): void
    {
        $runId = (string) Cache::get(self::batchMapKey($batch->id), '');
        if ($runId === '') return;

        $progress = Cache::get(self::cacheKey($runId, 'progress'), []);
        if (! is_array($progress)) $progress = [];
        $progress['batch_id'] = $batch->id;

        Cache::put(self::cacheKey($runId, 'progress'), $progress, self::ttl());

        $result = Cache::get(self::cacheKey($runId, 'result'), []);
        if (! is_array($result)) $result = [];
        $result['batch_id'] = $batch->id;

        Cache::put(self::cacheKey($runId, 'result'), $result, self::ttl());
    }

    /* -----------------------------------------------------------------
     |   Small helpers
     | ----------------------------------------------------------------- */

    protected function normalizedFormState(): array
    {
        $data = $this->form->getState() ?: [];

        $csvPath          = $data['csv_path'] ?? null;
        $stripeAccountId  = trim((string) ($data['stripe_account_id'] ?? ''));
        $currency         = strtolower(trim((string) ($data['currency'] ?? 'nok')));
        $includeImages    = (bool) ($data['include_images'] ?? true);
        $strictImageCheck = (bool) ($data['strict_image_check'] ?? true);
        $updateExisting   = (bool) ($data['update_existing'] ?? true);
        $chunkSize        = (int) ($data['chunk_size'] ?? 10);

        $currency  = $currency !== '' ? $currency : 'nok';
        $chunkSize = max(1, min(200, $chunkSize));

        return compact(
            'csvPath',
            'stripeAccountId',
            'currency',
            'includeImages',
            'strictImageCheck',
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

    private function pushConsole(string $message, string $level = 'info', ?string $runId = null): void
    {
        $rid = $runId ?: $this->currentRunId;

        $line = [
            'time'    => now()->format('H:i:s'),
            'level'   => $level,
            'message' => $message,
        ];

        $this->importConsole[] = $line;
        $this->importConsole = array_slice($this->importConsole, -self::CONSOLE_MAX);

        if (! $rid) return;

        $key = self::cacheKey($rid, 'console');
        $console = Cache::get($key, []);
        if (! is_array($console)) $console = [];
        $console[] = $line;

        Cache::put($key, array_slice($console, -self::CONSOLE_MAX), self::ttl());
    }

    private function failRun(?string $runId, string $message, ?Throwable $e = null): void
    {
        $rid = $runId ?: $this->currentRunId;
        $at = now()->toIso8601String();

        $payload = [
            'message' => $message,
            'at'      => $at,
        ];

        if ($e) {
            $payload['exception'] = [
                'class'      => get_class($e),
                'code'       => $e->getCode(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace_head' => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 24)),
            ];
        }

        $this->importProgress['status'] = 'failed';

        if ($rid) {
            Cache::put(self::cacheKey($rid, 'progress'), array_merge($this->importProgress, [
                'status' => 'failed',
            ]), self::ttl());

            $result = Cache::get(self::cacheKey($rid, 'result'), []);
            if (! is_array($result)) $result = [];
            $result['last_error'] = $payload;

            $errors = (array) ($result['errors'] ?? []);
            $errors[] = "{$at} — {$message}";
            $result['errors'] = array_slice($errors, -80);

            Cache::put(self::cacheKey($rid, 'result'), $result, self::ttl());
        }

        $this->pushConsole($message, 'err', $rid);

        Notification::make()
            ->title('Import failed')
            ->body($message)
            ->danger()
            ->send();

        Log::error('ShopifyImportTest failure', [
            'run_id' => $rid,
            'msg'    => $message,
            'ex'     => $e ? $e->getMessage() : null,
        ]);
    }

    /* -----------------------------------------------------------------
     |   CSV parsing – inlined ShopifyCsvImporter
     |   - Groups by Handle
     |   - Keeps Body (HTML) EXACTLY as in CSV
     |   - Collects variants + images + stats
     | ----------------------------------------------------------------- */

    /**
     * Parse money from CSV into float (e.g. "299,00" → 299.0).
     */
    private function parseMoneyToFloat(mixed $value): ?float
    {
        if ($value === null) return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $s = trim((string) $value);
        if ($s === '') return null;

        // Remove non-breaking space and normal spaces
        $s = str_replace("\xc2\xa0", ' ', $s);
        $s = str_replace(' ', '', $s);

        // If there's a comma and no dot → treat comma as decimal separator
        if (str_contains($s, ',') && ! str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } else {
            // Otherwise drop commas (thousands separator)
            $s = str_replace(',', '', $s);
        }

        if (! is_numeric($s)) return null;

        return (float) $s;
    }

    private function csvBool(mixed $value, bool $default = false): bool
    {
        $v = strtolower(trim((string) $value));
        if ($v === 'true' || $v === '1' || $v === 'yes') return true;
        if ($v === 'false' || $v === '0' || $v === 'no') return false;
        return $default;
    }

    /**
     * Inlined Shopify CSV parser → returns:
     * [
     *   'products' => [
     *      [
     *        'handle'        => string,
     *        'title'         => string,
     *        'body_html'     => string (EXACT from CSV),
     *        'vendor'        => string,
     *        'type'          => string,
     *        'tags'          => string,
     *        'status'        => string,
     *        'published'     => string,
     *        'variants'      => [...],
     *        'variant_count' => int,
     *        'images'        => [...],
     *        'image_count'   => int,
     *      ],
     *   ],
     *   'stats' => [...]
     * ]
     */
    private function parseShopifyCsvFile(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            throw new \RuntimeException("CSV file not readable: {$absolutePath}");
        }

        $fh = fopen($absolutePath, 'r');
        if (! $fh) {
            throw new \RuntimeException('Failed to open CSV file.');
        }

        $header = fgetcsv($fh);
        if (! is_array($header)) {
            fclose($fh);
            throw new \RuntimeException('CSV header row not found.');
        }

        $header = array_map('trim', $header);

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $assoc = [];
            foreach ($header as $i => $key) {
                if ($key === '') continue;
                $assoc[$key] = $row[$i] ?? null;
            }

            // Skip completely empty lines
            $nonEmpty = false;
            foreach ($assoc as $val) {
                if ($val !== null && $val !== '') {
                    $nonEmpty = true;
                    break;
                }
            }
            if (! $nonEmpty) continue;

            $rows[] = $assoc;
        }

        fclose($fh);

        $products            = [];
        $handleCounts        = [];
        $missingHandle       = 0;
        $variantsTotal       = 0;
        $variantsMissingPrice= 0;
        $imagesTotal         = 0;
        $productsWithImages  = 0;
        $productsWithoutImages = 0;

        foreach ($rows as $r) {
            $handle = trim((string) ($r['Handle'] ?? ''));

            if ($handle === '') {
                $missingHandle++;
                continue;
            }

            $handleCounts[$handle] = ($handleCounts[$handle] ?? 0) + 1;

            if (! isset($products[$handle])) {
                $products[$handle] = [
                    'handle'        => $handle,
                    'title'         => (string) ($r['Title'] ?? ''),
                    // DO NOT PARSE description – keep CSV Body (HTML) as-is
                    'body_html'     => (string) ($r['Body (HTML)'] ?? ''),
                    'vendor'        => (string) ($r['Vendor'] ?? ''),
                    'product_category' => (string) ($r['Product Category'] ?? ''),
                    'type'          => (string) ($r['Type'] ?? ''),
                    'tags'          => (string) ($r['Tags'] ?? ''),
                    'published'     => (string) ($r['Published'] ?? ''),
                    'status'        => (string) ($r['Status'] ?? ''),

                    'variants'      => [],
                    'variant_count' => 0,

                    'images'        => [],
                    'image_count'   => 0,

                    'variant_min_price' => null,
                    'variant_max_price' => null,
                ];
            }

            $product =& $products[$handle];

            // --- Images per row ---
            $imageSrc      = trim((string) ($r['Image Src'] ?? ''));
            $variantImage  = trim((string) ($r['Variant Image'] ?? ''));

            if ($imageSrc !== '') {
                $product['images'][] = $imageSrc;
            }
            if ($variantImage !== '') {
                $product['images'][] = $variantImage;
            }

            // --- Variant per row ---
            $variantPriceStr = $r['Variant Price'] ?? null;
            $variantPrice    = $this->parseMoneyToFloat($variantPriceStr);
            $variantAmount   = $variantPrice !== null ? (int) round($variantPrice * 100) : null;

            if ($variantAmount === null || $variantAmount <= 0) {
                $variantsMissingPrice++;
            }

            $variant = [
                'id'                 => $r['Variant ID'] ?? null,
                'variant_id'         => $r['Variant ID'] ?? null,
                'sku'                => $r['Variant SKU'] ?? null,
                'barcode'            => $r['Variant Barcode'] ?? null,

                'price'              => $r['Variant Price'] ?? null,
                'compare_at_price'   => $r['Variant Compare At Price'] ?? null,
                'tax_code'           => $r['Variant Tax Code'] ?? null,

                'inventory_quantity' => $r['Variant Inventory Qty'] ?? null,
                'inventory_policy'   => $r['Variant Inventory Policy'] ?? null,

                'requires_shipping'  => $this->csvBool($r['Variant Requires Shipping'] ?? null, true),
                'taxable'            => $this->csvBool($r['Variant Taxable'] ?? null, true),

                'weight'             => $r['Variant Grams'] ?? null,
                'weight_unit'        => $r['Variant Weight Unit'] ?? null,

                // Options: both name/value and simple option1/2/3 for Stripe metadata
                'option1_name'       => $r['Option1 Name'] ?? null,
                'option1_value'      => $r['Option1 Value'] ?? null,
                'option1'            => $r['Option1 Value'] ?? null,

                'option2_name'       => $r['Option2 Name'] ?? null,
                'option2_value'      => $r['Option2 Value'] ?? null,
                'option2'            => $r['Option2 Value'] ?? null,

                'option3_name'       => $r['Option3 Name'] ?? null,
                'option3_value'      => $r['Option3 Value'] ?? null,
                'option3'            => $r['Option3 Value'] ?? null,
            ];

            $product['variants'][] = $variant;
            $variantsTotal++;

            if ($variantAmount !== null && $variantAmount > 0) {
                if ($product['variant_min_price'] === null || $variantAmount < $product['variant_min_price']) {
                    $product['variant_min_price'] = $variantAmount;
                }
                if ($product['variant_max_price'] === null || $variantAmount > $product['variant_max_price']) {
                    $product['variant_max_price'] = $variantAmount;
                }
            }
        }

        // Finalize per-product stats
        foreach ($products as &$product) {
            $product['variant_count'] = count($product['variants']);

            $images = [];
            foreach ($product['images'] as $u) {
                $u = trim((string) $u);
                if ($u === '') continue;
                $images[$u] = true;
            }
            $product['images']      = array_values(array_keys($images));
            $product['image_count'] = count($product['images']);

            if ($product['image_count'] > 0) {
                $productsWithImages++;
            } else {
                $productsWithoutImages++;
            }

            $imagesTotal += $product['image_count'];

            if ($product['variant_min_price'] !== null) {
                $product['variant_min_price'] = $product['variant_min_price'] / 100;
            }
            if ($product['variant_max_price'] !== null) {
                $product['variant_max_price'] = $product['variant_max_price'] / 100;
            }
        }
        unset($product);

        $duplicateHandles = 0;
        foreach ($handleCounts as $cnt) {
            if ($cnt > 1) {
                $duplicateHandles += ($cnt - 1);
            }
        }

        return [
            'products' => array_values($products),
            'stats'    => [
                'total_products'          => count($products),
                'variants_total'          => $variantsTotal,
                'images_total'            => $imagesTotal,
                'missing_handle'          => $missingHandle,
                'duplicate_handles'       => $duplicateHandles,
                'products_with_images'    => $productsWithImages,
                'products_without_images' => $productsWithoutImages,
                'variants_missing_price'  => $variantsMissingPrice,
            ],
        ];
    }

    /* -----------------------------------------------------------------
     |   Step 1: Parse CSV → plan
     | ----------------------------------------------------------------- */

    public function parseCsv(): void
    {
        $s = $this->normalizedFormState();

        if (! $this->ensureRequiredOrNotify($s['csvPath'], $s['stripeAccountId'])) {
            return;
        }

        $absolute = Storage::disk('local')->path($s['csvPath']);

        try {
            // INLINE IMPORTER
            $parsed = $this->parseShopifyCsvFile($absolute);

            $products = $parsed['products'] ?? [];
            if (! is_array($products)) $products = [];

            // Map existing products by Shopify handle
            $handles = array_values(array_filter(array_map(
                fn ($p) => (string) ($p['handle'] ?? ''),
                $products
            )));

            $existingMap = [];
            if (! empty($handles)) {
                $productCodes = array_map(
                    fn (string $h) => 'shopify:' . $h,
                    $handles,
                );

                $existing = ConnectedProduct::query()
                    ->where('stripe_account_id', $s['stripeAccountId'])
                    ->whereIn('product_code', $productCodes)
                    ->get(['id', 'product_code', 'name', 'updated_at', 'stripe_product_id', 'product_meta']);

                foreach ($existing as $row) {
                    $meta   = (array) ($row->product_meta ?? []);
                    $handle = (string) data_get($meta, 'shopify.handle', '');

                    if ($handle === '' && is_string($row->product_code)) {
                        if (str_starts_with($row->product_code, 'shopify:')) {
                            $handle = substr($row->product_code, strlen('shopify:'));
                        } else {
                            $handle = (string) $row->product_code;
                        }
                    }

                    if ($handle !== '') {
                        $existingMap[$handle] = $row;
                    }
                }
            }

            $handleCounts        = [];
            $missingHandle       = 0;
            $productsWithImages  = 0;
            $productsNoImages    = 0;
            $variantsMissingPrice= 0;
            $variantsTotal       = 0;

            foreach ($products as $p) {
                $h = (string) ($p['handle'] ?? '');
                if ($h === '') { $missingHandle++; continue; }
                $handleCounts[$h] = ($handleCounts[$h] ?? 0) + 1;

                $imgCount = (int) ($p['image_count'] ?? 0);
                if ($imgCount > 0) $productsWithImages++; else $productsNoImages++;

                $variantCount = (int) ($p['variant_count'] ?? count($p['variants'] ?? []));
                $variantsTotal += $variantCount;

                if (! empty($p['variants']) && is_array($p['variants'])) {
                    foreach ($p['variants'] as $v) {
                        $price = $v['price'] ?? null;
                        $f = $this->parseMoneyToFloat($price);
                        if ($f === null || $f <= 0) {
                            $variantsMissingPrice++;
                        }
                    }
                }
            }

            $duplicateHandles = 0;
            foreach ($handleCounts as $cnt) {
                if ($cnt > 1) $duplicateHandles += ($cnt - 1);
            }

            $plan = [
                'total_products'          => count($products),
                'existing'                => 0,
                'new'                     => 0,
                'would_update'            => 0,
                'will_skip'               => 0,
                'items'                   => [],
                'existing_items'          => [],
                'missing_handle'          => $missingHandle,
                'duplicate_handles'       => $duplicateHandles,
                'products_with_images'    => $productsWithImages,
                'products_without_images' => $productsNoImages,
                'variants_total'          => $variantsTotal,
                'variants_missing_price'  => $variantsMissingPrice,
            ];

            foreach ($products as $p) {
                $handle = (string) ($p['handle'] ?? '');
                if ($handle === '') continue;

                $variantCount = (int) ($p['variant_count'] ?? count($p['variants'] ?? []));
                $imageCount   = (int) ($p['image_count'] ?? 0);

                $exists = isset($existingMap[$handle]);
                $diffs  = [];

                if ($exists) {
                    $plan['existing']++;

                    $existingRow   = $existingMap[$handle];
                    $existingTitle = (string) ($existingRow->name ?? '');
                    $newTitle      = (string) ($p['title'] ?? '');
                    if ($existingTitle !== '' && $newTitle !== '' && $existingTitle !== $newTitle) {
                        $diffs[] = 'title';
                    }

                    $exMeta = (array) ($existingRow->product_meta ?? []);
                    $exVar  = (int) data_get($exMeta, 'shopify.variant_count', 0);
                    $exImg  = (int) data_get($exMeta, 'shopify.image_count', 0);

                    if ($exVar && $variantCount && $exVar !== $variantCount) $diffs[] = 'variants';
                    if ($exImg && $imageCount && $exImg !== $imageCount)     $diffs[] = 'images';

                    $action = $s['updateExisting'] ? 'update' : 'skip';
                    if ($s['updateExisting']) $plan['would_update']++;
                    else $plan['will_skip']++;

                    $plan['existing_items'][] = [
                        'handle'            => $handle,
                        'title'             => (string) ($p['title'] ?? ''),
                        'stripe_product_id' => (string) ($existingRow->stripe_product_id ?? ''),
                        'updated_at'        => optional($existingRow->updated_at)->toDateTimeString(),
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

            // Merge inline stats with our computed stats
            $stats = (array) ($parsed['stats'] ?? []);
            $stats['missing_handle']          = $missingHandle;
            $stats['duplicate_handles']       = $duplicateHandles;
            $stats['products_with_images']    = $productsWithImages;
            $stats['products_without_images'] = $productsNoImages;
            $stats['variants_total']          = $variantsTotal;
            $stats['variants_missing_price']  = $variantsMissingPrice;

            $parsed['stats'] = $stats;

            $this->parseResult  = $parsed;
            $this->planResult   = $plan;
            $this->importResult = null;

            $this->importProgress = array_merge($this->importProgress, [
                'status'              => 'pending',
                'current'             => 0,
                'total'               => (int) $plan['total_products'],
                'percent'             => 0,

                'imported'            => 0,
                'skipped'             => 0,
                'updated'             => 0,
                'created'             => 0,
                'errors'              => 0,

                'images_total'        => (int) ($stats['images_total'] ?? 0),
                'images_valid'        => 0,
                'images_invalid'      => 0,

                'variants_total'      => (int) ($stats['variants_total'] ?? 0),
                'variants_ok'         => 0,
                'variants_bad'        => 0,
                'prices_created'      => 0,
                'prices_reused'       => 0,
                'prices_replaced'     => 0,

                'started_at'          => null,
                'last_tick_at'        => null,
                'rate_per_min'        => 0,
                'eta_seconds'         => null,

                'include_images'      => $s['includeImages'],
                'strict_image_check'  => $s['strictImageCheck'],
                'update_existing'     => $s['updateExisting'],
                'currency'            => $s['currency'],
                'chunk_size'          => $s['chunkSize'],
                'queue'               => self::IMPORT_QUEUE,
            ]);

            $this->importConsole = [];
            $this->pushConsole(
                "Parse complete — {$plan['total_products']} products (new: {$plan['new']}, existing: {$plan['existing']}, would update: {$plan['would_update']}, will skip: {$plan['will_skip']})",
                'ok'
            );

            if (! empty($stats)) {
                $this->pushConsole(
                    sprintf(
                        'Stats: Produkter %d · Varianter %d · Bilder %d · Duplikat handles %d · Manglende handle %d · Varianter uten pris %d',
                        (int) ($stats['total_products'] ?? 0),
                        (int) ($stats['variants_total'] ?? 0),
                        (int) ($stats['images_total'] ?? 0),
                        (int) ($stats['duplicate_handles'] ?? 0),
                        (int) ($stats['missing_handle'] ?? 0),
                        (int) ($stats['variants_missing_price'] ?? 0),
                    ),
                    'info'
                );
            }

            Notification::make()
                ->title('Parse complete')
                ->body('CSV parsed and import plan generated.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            $this->parseResult  = null;
            $this->planResult   = null;
            $this->importResult = null;

            $this->importProgress['status']  = 'failed';
            $this->importProgress['total']   = 0;
            $this->importProgress['current'] = 0;
            $this->importProgress['percent'] = 0;

            $this->pushConsole('Parse failed: ' . $e->getMessage(), 'err');

            Notification::make()
                ->title('Parse failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('ShopifyImportTest parse failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }
    }

    /* -----------------------------------------------------------------
     |   Step 2: LOCAL IMPORT (CMS ONLY)
     |   - Creates/updates ConnectedProduct
     |   - Populates images JSON
     |   - Creates ProductVariant rows
     |   - NO STRIPE CALLS
     | ----------------------------------------------------------------- */

    /**
     * Helper: identity key for variants to avoid duplicates.
     */
    private function variantIdentityKey(?string $sku, ?string $o1, ?string $v1, ?string $o2, ?string $v2, ?string $o3, ?string $v3): string
    {
        $parts = [
            trim((string) $sku),
            trim((string) $o1), trim((string) $v1),
            trim((string) $o2), trim((string) $v2),
            trim((string) $o3), trim((string) $v3),
        ];

        return sha1(implode('|', $parts));
    }

    /**
     * Create/update local ProductVariant rows for one ConnectedProduct.
     */
    private function syncLocalVariantsForProduct(
        ConnectedProduct $product,
        array $variants,
        string $currency,
        int &$created,
        int &$updated,
        int &$skipped
    ): void {
        if (empty($variants)) {
            return;
        }

        $existing = ProductVariant::query()
            ->where('connected_product_id', $product->id)
            ->where('stripe_account_id', $product->stripe_account_id)
            ->get();

        $existingIndex = [];
        foreach ($existing as $ev) {
            $key = $this->variantIdentityKey(
                $ev->sku,
                $ev->option1_name,
                $ev->option1_value,
                $ev->option2_name,
                $ev->option2_value,
                $ev->option3_name,
                $ev->option3_value,
            );
            $existingIndex[$key] = $ev;
        }

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                $skipped++;
                continue;
            }

            $sku = $variant['sku'] ?? null;

            $opt1Name  = $variant['option1_name']  ?? null;
            $opt1Value = $variant['option1_value'] ?? null;
            $opt2Name  = $variant['option2_name']  ?? null;
            $opt2Value = $variant['option2_value'] ?? null;
            $opt3Name  = $variant['option3_name']  ?? null;
            $opt3Value = $variant['option3_value'] ?? null;

            $key = $this->variantIdentityKey(
                $sku,
                $opt1Name, $opt1Value,
                $opt2Name, $opt2Value,
                $opt3Name, $opt3Value,
            );

            $priceFloat  = $this->parseMoneyToFloat($variant['price'] ?? null);
            $priceAmount = $priceFloat !== null && $priceFloat > 0 ? (int) round($priceFloat * 100) : 0;

            $inventoryQty     = $variant['inventory_quantity'] ?? null;
            $inventoryQty     = $inventoryQty !== null && $inventoryQty !== '' ? (int) $inventoryQty : null;
            $inventoryPolicy  = $variant['inventory_policy'] ?? 'deny';

            $requiresShipping = array_key_exists('requires_shipping', $variant)
                ? (bool) $variant['requires_shipping']
                : true;
            $taxable          = array_key_exists('taxable', $variant)
                ? (bool) $variant['taxable']
                : true;

            if (isset($existingIndex[$key])) {
                /** @var ProductVariant $pv */
                $pv = $existingIndex[$key];

                $pv->sku                 = $sku;
                $pv->option1_name        = $opt1Name;
                $pv->option1_value       = $opt1Value;
                $pv->option2_name        = $opt2Name;
                $pv->option2_value       = $opt2Value;
                $pv->option3_name        = $opt3Name;
                $pv->option3_value       = $opt3Value;
                $pv->price_amount        = $priceAmount;
                $pv->currency            = $currency;
                $pv->inventory_quantity  = $inventoryQty;
                $pv->inventory_policy    = $inventoryPolicy;
                $pv->requires_shipping   = $requiresShipping;
                $pv->taxable             = $taxable;
                $pv->active              = true;

                $pv->save();
                $updated++;
            } else {
                ProductVariant::create([
                    'connected_product_id' => $product->id,
                    'stripe_account_id'    => $product->stripe_account_id,
                    'option1_name'         => $opt1Name,
                    'option1_value'        => $opt1Value,
                    'option2_name'         => $opt2Name,
                    'option2_value'        => $opt2Value,
                    'option3_name'         => $opt3Name,
                    'option3_value'        => $opt3Value,
                    'sku'                  => $sku,
                    'price_amount'         => $priceAmount,
                    'currency'             => $currency,
                    'inventory_quantity'   => $inventoryQty,
                    'inventory_policy'     => $inventoryPolicy,
                    'requires_shipping'    => $requiresShipping,
                    'taxable'              => $taxable,
                    'active'               => true,
                ]);

                $created++;
            }
        }
    }

    public function importLocal(): void
    {
        $s = $this->normalizedFormState();

        if (! $this->ensureRequiredOrNotify($s['csvPath'], $s['stripeAccountId'])) {
            return;
        }

        try {
            // Ensure we have parsed data
            if (! $this->parseResult || ! $this->planResult) {
                $this->parseCsv();
                if (! $this->parseResult || ! $this->planResult) {
                    return;
                }
                $s = $this->normalizedFormState();
            }

            $products = $this->parseResult['products'] ?? [];
            if (! is_array($products) || count($products) === 0) {
                Notification::make()
                    ->title('No products')
                    ->body('Nothing to import to CMS.')
                    ->warning()
                    ->send();
                return;
            }

            $createdProducts = 0;
            $updatedProducts = 0;
            $skippedProducts = 0;
            $errors          = 0;

            $variantsCreated = 0;
            $variantsUpdated = 0;
            $variantsSkipped = 0;

            foreach ($products as $p) {
                $handle = (string) ($p['handle'] ?? '');
                $title  = (string) ($p['title'] ?? '');
                $vendor = (string) ($p['vendor'] ?? '');
                $type   = (string) ($p['type'] ?? '');
                $tags   = (string) ($p['tags'] ?? '');
                $status = (string) ($p['status'] ?? '');
                $body   = (string) ($p['body_html'] ?? '');

                if ($handle === '' && $title === '') {
                    $skippedProducts++;
                    continue;
                }

                $productCode = $handle !== ''
                    ? ('shopify:' . $handle)
                    : ('shopify:__no_handle:' . md5($title));

                $cp = ConnectedProduct::query()
                    ->where('stripe_account_id', $s['stripeAccountId'])
                    ->where('product_code', $productCode)
                    ->first();

                $isNew = false;

                if (! $cp) {
                    $cp = new ConnectedProduct();
                    $cp->stripe_account_id = $s['stripeAccountId'];

                    // Placeholder so sqlite NOT NULL stripe_product_id is satisfied.
                    // Real Stripe product id will be set later by the queue job.
                    $cp->stripe_product_id = 'local:' . Str::uuid();
                    $cp->product_code      = $productCode;
                    $isNew = true;
                }

                // Basic fields
                $cp->name        = $title !== '' ? $title : $handle;
                // DO NOT PARSE description – keep CSV Body (HTML) as-is
                $cp->description = $body !== '' ? $body : ($cp->description ?? '');

                if ($type !== '') {
                    $cp->type = $type;
                } elseif (! $cp->type) {
                    // fallback type for Shopify imports
                    $cp->type = 'shopify_product';
                }

                $cp->active   = true;
                $cp->currency = $s['currency'] ?: ($cp->currency ?: 'nok');

                // Images (JSON column on ConnectedProduct)
                if (! empty($p['images']) && is_array($p['images'])) {
                    $cp->images = array_values(array_unique(array_filter($p['images'])));
                }

                // Product meta
                $meta = (array) ($cp->product_meta ?? []);
                $meta['source'] = $meta['source'] ?? 'shopify_csv_import';

                $shopifyMeta = (array) ($meta['shopify'] ?? []);
                $shopifyMeta['handle']         = $handle;
                $shopifyMeta['vendor']         = $vendor;
                $shopifyMeta['type']           = $type;
                $shopifyMeta['tags']           = $tags;
                $shopifyMeta['title']          = $title;
                $shopifyMeta['status']         = $status;
                $shopifyMeta['variant_count']  = (int) ($p['variant_count'] ?? count($p['variants'] ?? []));
                $shopifyMeta['image_count']    = (int) ($p['image_count'] ?? 0);
                $shopifyMeta['images']         = $p['images'] ?? [];

                $meta['shopify'] = $shopifyMeta;
                $cp->product_meta = $meta;

                $cp->save();

                $variants = $p['variants'] ?? [];
                if (is_array($variants) && count($variants) > 0) {
                    $this->syncLocalVariantsForProduct(
                        $cp,
                        $variants,
                        $s['currency'],
                        $variantsCreated,
                        $variantsUpdated,
                        $variantsSkipped
                    );
                }

                if ($isNew) $createdProducts++; else $updatedProducts++;
            }

            $stats = (array) ($this->parseResult['stats'] ?? []);

            $statsLine = sprintf(
                'Produkter %d · Varianter %d · Bilder %d · Duplikat handles %d · Manglende handle %d · Varianter uten pris %d',
                (int) ($stats['total_products'] ?? ($createdProducts + $updatedProducts + $skippedProducts)),
                (int) ($stats['variants_total'] ?? ($variantsCreated + $variantsUpdated)),
                (int) ($stats['images_total'] ?? 0),
                (int) ($stats['duplicate_handles'] ?? 0),
                (int) ($stats['missing_handle'] ?? 0),
                (int) ($stats['variants_missing_price'] ?? 0),
            );

            $msg = "Local import complete. Created {$createdProducts}, updated {$updatedProducts}, skipped {$skippedProducts}, errors {$errors}.";

            $this->pushConsole($statsLine, 'info');
            $this->pushConsole(
                "Variants: created {$variantsCreated}, updated {$variantsUpdated}, skipped {$variantsSkipped}.",
                'info'
            );
            $this->pushConsole($msg, 'ok');

            Notification::make()
                ->title('Local import')
                ->body($statsLine . ' ' . $msg)
                ->success()
                ->send();
        } catch (Throwable $e) {
            $this->pushConsole('Local import failed: ' . $e->getMessage(), 'err');

            Notification::make()
                ->title('Local import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('ShopifyImportTest local import failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }
    }

    /* -----------------------------------------------------------------
     |   Step 3: Queue import (Stripe sync) – unchanged logic
     | ----------------------------------------------------------------- */

    public function runImport(): void
    {
        $s = $this->normalizedFormState();

        if (! $this->ensureRequiredOrNotify($s['csvPath'], $s['stripeAccountId'])) {
            return;
        }

        // job_batches table exists?
        try {
            DB::table('job_batches')->limit(1)->get();
        } catch (Throwable $e) {
            $this->failRun(null, "Missing job_batches table. Run: php artisan queue:batches-table && php artisan migrate", $e);
            return;
        }

        // cache table exists if CACHE_STORE=database
        if (config('cache.default') === 'database') {
            try {
                DB::table(config('cache.stores.database.table', 'cache'))->limit(1)->get();
            } catch (Throwable $e) {
                $this->failRun(null, "Missing cache table for CACHE_STORE=database. Run: php artisan cache:table && php artisan migrate", $e);
                return;
            }
        }

        try {
            if (! $this->parseResult || ! $this->planResult) {
                $this->parseCsv();
                if (! $this->parseResult || ! $this->planResult) return;
            }

            $products = $this->parseResult['products'] ?? [];
            if (! is_array($products)) $products = [];
            $total = count($products);

            if ($total <= 0) {
                Notification::make()
                    ->title('No products found')
                    ->body('Parsed CSV contains 0 products.')
                    ->warning()
                    ->send();
                return;
            }

            $runId = (string) Str::uuid();
            $this->currentRunId = $runId;

            $jsonRel = "tmp/shopify-import/runs/{$runId}.json";
            Storage::disk('local')->put($jsonRel, json_encode([
                'meta' => [
                    'stripe_account_id'   => $s['stripeAccountId'],
                    'currency'            => $s['currency'],
                    'include_images'      => $s['includeImages'],
                    'strict_image_check'  => $s['strictImageCheck'],
                    'update_existing'     => $s['updateExisting'],
                    'queue'               => self::IMPORT_QUEUE,
                    'created_at'          => now()->toIso8601String(),
                ],
                'products' => $products,
            ], JSON_UNESCAPED_UNICODE));

            $startedAt = now()->toIso8601String();

            Cache::put(self::cacheKey($runId, 'progress'), [
                'status'              => 'running',
                'current'             => 0,
                'total'               => $total,
                'percent'             => 0,

                'imported'            => 0,
                'skipped'             => 0,
                'updated'             => 0,
                'created'             => 0,
                'errors'              => 0,

                'images_total'        => 0,
                'images_valid'        => 0,
                'images_invalid'      => 0,

                'variants_total'      => 0,
                'variants_ok'         => 0,
                'variants_bad'        => 0,
                'prices_created'      => 0,
                'prices_reused'       => 0,
                'prices_replaced'     => 0,

                'started_at'          => $startedAt,
                'last_tick_at'        => null,
                'rate_per_min'        => 0,
                'eta_seconds'         => null,

                'include_images'      => $s['includeImages'],
                'strict_image_check'  => $s['strictImageCheck'],
                'update_existing'     => $s['updateExisting'],
                'currency'            => $s['currency'],
                'chunk_size'          => $s['chunkSize'],
                'queue'               => self::IMPORT_QUEUE,

                'run_id'              => $runId,
                'batch_id'            => null,
            ], self::ttl());

            Cache::put(self::cacheKey($runId, 'console'), [[
                'time'    => now()->format('H:i:s'),
                'level'   => 'info',
                'message' => "Import start: run={$runId} total={$total} chunk={$s['chunkSize']} currency={$s['currency']} images=" . ($s['includeImages'] ? 'ON' : 'OFF') . " strict=" . ($s['strictImageCheck'] ? 'ON' : 'OFF') . " update=" . ($s['updateExisting'] ? 'ON' : 'OFF') . " queue=" . self::IMPORT_QUEUE,
            ], [
                'time'    => now()->format('H:i:s'),
                'level'   => 'info',
                'message' => "Worker: php artisan queue:work --queue=" . self::IMPORT_QUEUE . ",default --sleep=1 --tries=1 --timeout=0",
            ]], self::ttl());

            Cache::put(self::cacheKey($runId, 'recent'), [], self::ttl());

            Cache::put(self::cacheKey($runId, 'result'), [
                'run_id'      => $runId,
                'batch_id'    => null,
                'finished_at' => null,
                'last_error'  => null,
                'stats'       => [
                    'import' => [
                        'total_products'   => $total,
                        'imported'         => 0,
                        'skipped'          => 0,
                        'updated'          => 0,
                        'created'          => 0,
                        'error_count'      => 0,
                        'images_total'     => 0,
                        'images_valid'     => 0,
                        'images_invalid'   => 0,
                        'variants_total'   => 0,
                        'variants_ok'      => 0,
                        'variants_bad'     => 0,
                        'prices_created'   => 0,
                        'prices_reused'    => 0,
                        'prices_replaced'  => 0,
                        'rate_per_min'     => 0,
                    ],
                ],
                'errors'      => [],
                'per_product' => [],
                'plan'        => $this->planResult,
            ], self::ttl());

            $jobs = [];
            for ($offset = 0; $offset < $total; $offset += $s['chunkSize']) {
                $jobs[] = (new ImportShopifyProductsChunkJob(
                    runId: $runId,
                    stripeAccountId: $s['stripeAccountId'],
                    jsonRelativePath: $jsonRel,
                    offset: $offset,
                    limit: min($s['chunkSize'], $total - $offset),
                    currency: $s['currency'],
                    includeImages: $s['includeImages'],
                    strictImageCheck: $s['strictImageCheck'],
                    updateExisting: $s['updateExisting'],
                ))->onQueue(self::IMPORT_QUEUE);
            }

            $batch = Bus::batch($jobs)
                ->name("Shopify CSV → Stripe ({$s['stripeAccountId']})")
                ->allowFailures()
                ->then([self::class, 'batchThen'])
                ->catch([self::class, 'batchCatch'])
                ->finally([self::class, 'batchFinally'])
                ->onQueue(self::IMPORT_QUEUE)
                ->dispatch();

            $this->currentBatchId = $batch->id;

            Cache::put(self::batchMapKey($batch->id), $runId, self::ttl());

            $progress = Cache::get(self::cacheKey($runId, 'progress'), []);
            if (is_array($progress)) {
                $progress['batch_id'] = $batch->id;
                Cache::put(self::cacheKey($runId, 'progress'), $progress, self::ttl());
            }

            $result = Cache::get(self::cacheKey($runId, 'result'), []);
            if (is_array($result)) {
                $result['batch_id'] = $batch->id;
                Cache::put(self::cacheKey($runId, 'result'), $result, self::ttl());
            }

            $this->importProgress = Cache::get(self::cacheKey($runId, 'progress'), $this->importProgress);
            $this->importConsole  = Cache::get(self::cacheKey($runId, 'console'), []);
            $this->recentProducts = Cache::get(self::cacheKey($runId, 'recent'), []);

            Notification::make()
                ->title('Import queued')
                ->body("Queued {$total} products in " . count($jobs) . " jobs on queue: " . self::IMPORT_QUEUE)
                ->success()
                ->send();
        } catch (Throwable $e) {
            $this->failRun($this->currentRunId, 'Dispatch failed: ' . $e->getMessage(), $e);
        }
    }

    /* -----------------------------------------------------------------
     |   Poll / reset helpers
     | ----------------------------------------------------------------- */

    public function refreshProgress(): void
    {
        if (! $this->currentRunId) return;

        $runId = $this->currentRunId;

        $progress = Cache::get(self::cacheKey($runId, 'progress'));
        if (is_array($progress)) {
            $this->importProgress = array_merge($this->importProgress, $progress);
        }

        $console = Cache::get(self::cacheKey($runId, 'console'));
        if (is_array($console)) {
            $this->importConsole = array_slice($console, -self::CONSOLE_MAX);
        }

        $recent = Cache::get(self::cacheKey($runId, 'recent'));
        if (is_array($recent)) {
            $this->recentProducts = array_slice($recent, -self::RECENT_MAX);
        }

        $status = (string) ($this->importProgress['status'] ?? 'idle');
        if ($status === 'finished' || $status === 'failed') {
            $result = Cache::get(self::cacheKey($runId, 'result'));
            if (is_array($result)) {
                $this->importResult = $result;
            }
        }
    }

    public function clearConsole(): void
    {
        $this->importConsole = [];
        if ($this->currentRunId) {
            Cache::put(self::cacheKey($this->currentRunId, 'console'), [], self::ttl());
        }
    }

    public function resetRunState(): void
    {
        $this->parseResult  = null;
        $this->planResult   = null;
        $this->importResult = null;

        $this->currentRunId   = null;
        $this->currentBatchId = null;

        $this->importConsole  = [];
        $this->recentProducts = [];

        $this->importProgress = [
            'status'            => 'idle',
            'current'           => 0,
            'total'             => 0,
            'percent'           => 0,

            'imported'          => 0,
            'skipped'           => 0,
            'updated'           => 0,
            'created'           => 0,
            'errors'            => 0,

            'images_total'      => 0,
            'images_valid'      => 0,
            'images_invalid'    => 0,

            'variants_total'    => 0,
            'variants_ok'       => 0,
            'variants_bad'      => 0,
            'prices_created'    => 0,
            'prices_reused'     => 0,
            'prices_replaced'   => 0,

            'started_at'        => null,
            'last_tick_at'      => null,
            'rate_per_min'      => 0,
            'eta_seconds'       => null,

            'include_images'     => false,
            'strict_image_check' => true,
            'update_existing'    => true,
            'currency'           => 'nok',
            'chunk_size'         => 10,

            'queue'             => self::IMPORT_QUEUE,
            'run_id'            => null,
            'batch_id'          => null,
        ];

        Notification::make()
            ->title('Reset')
            ->body('View state reset (cache from old runs remains until TTL expires).')
            ->success()
            ->send();
    }
}
