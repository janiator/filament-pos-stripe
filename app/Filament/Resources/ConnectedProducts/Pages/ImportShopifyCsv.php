<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use App\Jobs\ImportShopifyProductJob;
use App\Services\ShopifyCsvImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

class ImportShopifyCsv extends Page implements HasForms
{
    use InteractsWithForms;

    /** Attach to ConnectedProductResource */
    protected static string $resource = ConnectedProductResource::class;

    /**
     * IMPORTANT:
     * Non-static, so it matches Filament\Pages\Page::$view
     * and avoids the “Cannot redeclare non static $view as static” fatal.
     */
    protected string $view = 'filament.resources.connected-products.pages.import-shopify-csv';

    /** Wizard / form state */
    public ?array $data = [];

    /** Parsed CSV preview payload */
    public ?array $previewData = null;

    /**
     * Make page accessible for all authenticated users.
     * Signature must match parent: canAccess(array $parameters = []): bool
     */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        // Initialize state at `data`
        $this->form->fill();
    }

    /**
     * Filament v3 Schemas form – wizard with 3 steps:
     * 1) Upload CSV
     * 2) Preview
     * 3) Import
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('upload')
                        ->label('Upload CSV')
                        ->description('Upload your Shopify product export CSV file')
                        ->schema([
                            FileUpload::make('csv_file')
                                ->label('CSV file')
                                ->acceptedFileTypes([
                                    'text/csv',
                                    'text/plain',
                                    'application/vnd.ms-excel',
                                ])
                                ->disk('local')
                                ->directory('imports')
                                ->visibility('private')
                                ->required()
                                ->helperText('Upload your Shopify product export CSV file')
                                ->columnSpanFull(),
                        ]),

                    Step::make('preview')
                        ->label('Preview')
                        ->description('Review the products that will be imported')
                        ->schema([
                            View::make('filament.resources.connected-products.pages.preview-table')
                                ->key('preview-table'),
                        ])
                        ->beforeValidation(function (): void {
                            // Parse CSV when entering the preview step
                            $this->parseCsv();
                        }),

                    Step::make('import')
                        ->label('Import')
                        ->description('Confirm and import products')
                        ->schema([]),
                ])->submitAction(
                    new HtmlString(
                        Blade::render(<<<'BLADE'
                            <x-filament::button
                                wire:click="importProducts"
                                size="lg"
                                color="success"
                            >
                                Import products
                            </x-filament::button>
                        BLADE)
                    )
                ),
            ]);
    }

    /**
     * Parse the uploaded CSV and store preview structure.
     */
    protected function parseCsv(): void
    {
        $data    = $this->form->getState();
        $csvPath = $data['csv_file'] ?? null;

        if (! $csvPath) {
            return;
        }

        $fullPath = Storage::disk('local')->path($csvPath);

        if (! file_exists($fullPath)) {
            Notification::make()
                ->danger()
                ->title('CSV file not found')
                ->body('The uploaded CSV file could not be found.')
                ->send();

            $this->previewData = null;

            return;
        }

        try {
            $importer          = new ShopifyCsvImporter();
            $this->previewData = $importer->parse($fullPath);

            // Backup for the preview Blade
            session(['shopify_import_preview' => $this->previewData]);
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error parsing CSV')
                ->body($e->getMessage())
                ->send();

            $this->previewData = null;
        }
    }

    /**
     * Dispatch batch jobs to import all parsed products into Stripe.
     */
    public function importProducts(): void
    {
        $store = \Filament\Facades\Filament::getTenant();

        if (! $store || ! $store->stripe_account_id) {
            Notification::make()
                ->danger()
                ->title('Store not configured')
                ->body('The current store does not have a Stripe account configured.')
                ->send();

            return;
        }

        $data            = $this->form->getState();
        $csvPath         = $data['csv_file'] ?? null;
        $stripeAccountId = $store->stripe_account_id;

        if (! $csvPath) {
            Notification::make()
                ->danger()
                ->title('Missing required data')
                ->body('Please upload a CSV file.')
                ->send();

            return;
        }

        $fullPath = Storage::disk('local')->path($csvPath);

        if (! file_exists($fullPath)) {
            Notification::make()
                ->danger()
                ->title('CSV file not found')
                ->body('The uploaded CSV file could not be found.')
                ->send();

            return;
        }

        try {
            $importer = new ShopifyCsvImporter();
            $parsed   = $importer->parse($fullPath);
            $products = $parsed['products'] ?? [];

            if (empty($products)) {
                Notification::make()
                    ->warning()
                    ->title('No products found')
                    ->body('The CSV file does not contain any products to import.')
                    ->send();

                return;
            }

            $jobs = [];
            foreach ($products as $productData) {
                $jobs[] = new ImportShopifyProductJob($productData, $stripeAccountId);
            }

            Bus::batch($jobs)
                ->name('Import Shopify Products')
                ->allowFailures()
                ->then(function (Batch $batch) use ($csvPath): void {
                    // Clean up CSV after successful batch
                    Storage::disk('local')->delete($csvPath);
                })
                ->catch(function (Batch $batch, Throwable $e): void {
                    \Log::error('Shopify import batch failed', [
                        'batch_id' => $batch->id,
                        'error'    => $e->getMessage(),
                    ]);
                })
                ->finally(function (Batch $batch) use ($csvPath): void {
                    // Ensure cleanup even if batch fails
                    if (Storage::disk('local')->exists($csvPath)) {
                        Storage::disk('local')->delete($csvPath);
                    }
                })
                ->dispatch();

            Notification::make()
                ->success()
                ->title('Import started')
                ->body('Queued ' . count($jobs) . ' products for import. The import will run in the background.')
                ->send();

            // Back to list
            $this->redirect(ConnectedProductResource::getUrl('index'));
        } catch (\Exception $e) {
            if ($csvPath && Storage::disk('local')->exists($csvPath)) {
                Storage::disk('local')->delete($csvPath);
            }

            Notification::make()
                ->danger()
                ->title('Import failed')
                ->body('Failed to parse CSV file: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Helper for your Blade preview view.
     */
    public function getPreviewData(): ?array
    {
        return $this->previewData ?? session('shopify_import_preview');
    }
}
