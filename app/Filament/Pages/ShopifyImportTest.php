<?php

namespace App\Filament\Pages;

use App\Services\ShopifyCsvImporter;
use BackedEnum;
use UnitEnum;
use Filament\Actions;
use Filament\Forms\Components as Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShopifyImportTest extends Page implements HasForms
{
    use InteractsWithForms;

    /** ================================================================
     *  Filament meta
     * ================================================================ */
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string                 $navigationLabel = 'Shopify CSV Import';
    protected static ?string                 $title           = 'Shopify CSV Import';
    protected static string|UnitEnum|null    $navigationGroup = 'Dev';

    /**
     * Explicit view binding – compatible with Filament v4
     */
    public function getView(): string
    {
        return 'filament.pages.shopify-import-test';
    }

    /** ================================================================
     *  Public state (Livewire)
     * ================================================================ */

    public ?array $formData = [
        'csv_path'          => null,
        'stripe_account_id' => null,
        'download_images'   => false,
    ];

    public ?array $parseResult  = null;
    public ?array $importResult = null;

    /**
     * Import progress summary (used by Alpine + Blade).
     */
    public array $importProgress = [
        'status'          => 'idle',   // idle | pending | running | finished | failed
        'current'         => 0,
        'total'           => 0,
        'percent'         => 0,
        'imported'        => 0,
        'skipped'         => 0,
        'errors'          => 0,
        'download_images' => false,
    ];

    /**
     * Console lines for the Alpine console.
     *
     * Each entry: ['time' => '12:34:56', 'message' => '...']
     */
    public array $importConsole = [];

    /** ================================================================
     *  Boot / access control
     * ================================================================ */

    public function mount(): void
    {
        $this->form->fill([
            'csv_path'          => null,
            'stripe_account_id' => '',
            'download_images'   => false,
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        if (property_exists($user, 'is_super_admin') && (bool) $user->is_super_admin) {
            return true;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    /** ================================================================
     *  Filament v4 Schema-based form
     * ================================================================ */

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
                    ->helperText('Connected account id for this store tenant, e.g. acct_123...')
                    ->required(),

                Forms\Toggle::make('download_images')
                    ->label('Download & upload product images')
                    ->helperText('If enabled, product image URLs are downloaded (Spatie) and uploaded to Stripe.')
                    ->inline(false),
            ]);
    }

    /** ================================================================
     *  Header actions (for Filament, even though UI uses custom buttons)
     * ================================================================ */
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
        ];
    }

    /** ================================================================
     *  ACTION: Parse CSV only
     * ================================================================ */

    public function parseCsv(): void
    {
        $data = $this->form->getState();

        $csvPath         = $data['csv_path'] ?? null;
        $stripeAccountId = trim((string) ($data['stripe_account_id'] ?? ''));

        if (! $csvPath || ! $stripeAccountId) {
            Notification::make()
                ->title('Missing data')
                ->body('Upload a CSV and set Stripe Account ID first.')
                ->danger()
                ->send();

            return;
        }

        $absolute = Storage::disk('local')->path($csvPath);

        try {
            $importer = new ShopifyCsvImporter();

            $parsed = $importer->parse($absolute);

            $this->parseResult  = $parsed;
            $this->importResult = null;

            $total = (int) ($parsed['total_products'] ?? count($parsed['products'] ?? []));

            $this->importProgress = [
                'status'          => 'pending',
                'current'         => 0,
                'total'           => $total,
                'percent'         => 0,
                'imported'        => 0,
                'skipped'         => 0,
                'errors'          => 0,
                'download_images' => (bool) ($data['download_images'] ?? false),
            ];

            $this->importConsole[] = [
                'time'    => now()->format('H:i:s'),
                'message' => "Parse complete – {$total} products found",
            ];

            Notification::make()
                ->title('Parse complete')
                ->body('CSV parsed successfully.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->parseResult               = null;
            $this->importProgress['status']  = 'failed';
            $this->importProgress['total']   = 0;
            $this->importProgress['current'] = 0;
            $this->importProgress['percent'] = 0;

            $this->importConsole[] = [
                'time'    => now()->format('H:i:s'),
                'message' => 'Parse failed: '.$e->getMessage(),
            ];

            Notification::make()
                ->title('Parse failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('ShopifyImportTest parse failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** ================================================================
     *  ACTION: Run full import
     * ================================================================ */

    public function runImport(): void
    {
        $data = $this->form->getState();

        $csvPath         = $data['csv_path'] ?? null;
        $stripeAccountId = trim((string) ($data['stripe_account_id'] ?? ''));
        $downloadImages  = (bool) ($data['download_images'] ?? false);

        if (! $csvPath || ! $stripeAccountId) {
            Notification::make()
                ->title('Missing data')
                ->body('Upload a CSV and set Stripe Account ID first.')
                ->danger()
                ->send();

            return;
        }

        $absolute = Storage::disk('local')->path($csvPath);

        $this->importProgress['status']          = 'running';
        $this->importProgress['current']         = 0;
        $this->importProgress['percent']         = 0;
        $this->importProgress['download_images'] = $downloadImages;

        $this->importConsole[] = [
            'time'    => now()->format('H:i:s'),
            'message' => 'Import started…',
        ];

        try {
            $importer = new ShopifyCsvImporter();

            $result = $importer->import(
                $absolute,
                $stripeAccountId,
                function (
                    int $index,
                    int $total,
                    array $productData,
                    int $imported,
                    int $skipped,
                    int $errorCount
                ): void {
                    $this->importProgress['current']  = $index;
                    $this->importProgress['total']    = $total;
                    $this->importProgress['percent']  = $total > 0
                        ? (int) floor(($index / $total) * 100)
                        : 0;
                    $this->importProgress['imported'] = $imported;
                    $this->importProgress['skipped']  = $skipped;
                    $this->importProgress['errors']   = $errorCount;

                    $this->importConsole[] = [
                        'time'    => now()->format('H:i:s'),
                        'message' => "Product {$index}/{$total}: ".($productData['title'] ?? '…'),
                    ];
                },
                $downloadImages,
            );

            // Keep parse stats visible even if Parse step was skipped.
            if (! $this->parseResult) {
                try {
                    $this->parseResult = $importer->parse($absolute);
                } catch (\Throwable) {
                    // ignore
                }
            }

            $this->importResult = $result;

            $istats        = $result['stats']['import'] ?? [];
            $totalProducts = (int) ($istats['total_products']
                ?? $this->importProgress['total']
                ?? 0);

            $this->importProgress['status']   = 'finished';
            $this->importProgress['current']  = $totalProducts;
            $this->importProgress['total']    = $totalProducts;
            $this->importProgress['percent']  = $totalProducts > 0 ? 100 : 0;
            $this->importProgress['imported'] = (int) ($istats['imported'] ?? $result['imported'] ?? 0);
            $this->importProgress['skipped']  = (int) ($istats['skipped'] ?? $result['skipped'] ?? 0);
            $this->importProgress['errors']   = (int) ($result['error_count'] ?? $istats['error_count'] ?? 0);

            $this->importConsole[] = [
                'time'    => now()->format('H:i:s'),
                'message' => 'Import completed.',
            ];

            Notification::make()
                ->title('Import complete')
                ->body('Stripe import finished.')
                ->success()
                ->send();

            Log::info('ShopifyImportTest import completed', [
                'stats' => $result['stats'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->importResult              = null;
            $this->importProgress['status']  = 'failed';

            $this->importConsole[] = [
                'time'    => now()->format('H:i:s'),
                'message' => 'Import failed: '.$e->getMessage(),
            ];

            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('ShopifyImportTest import failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
        }
    }
}
