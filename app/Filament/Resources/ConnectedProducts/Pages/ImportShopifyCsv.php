<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use App\Services\ShopifyCsvImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ImportShopifyCsv extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ConnectedProductResource::class;

    protected string $view = 'filament.resources.connected-products.pages.import-shopify-csv';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Step::make('upload')
                        ->label('Upload CSV')
                        ->description('Upload your Shopify product export CSV file')
                        ->schema([
                            Select::make('stripe_account_id')
                                ->label('Store')
                                ->options(function () {
                                    return \App\Models\Store::whereNotNull('stripe_account_id')
                                        ->pluck('name', 'stripe_account_id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Select the store/connected account to import products into')
                                ->columnSpanFull(),

                            FileUpload::make('csv_file')
                                ->label('CSV File')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
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
                            // Preview will be shown in the view
                        ])
                        ->afterValidation(function () {
                            // Parse CSV and prepare preview data when entering preview step
                            $this->parseCsv();
                        }),

                    Step::make('import')
                        ->label('Import')
                        ->description('Confirm and import products')
                        ->schema([
                            // Import confirmation
                        ]),
                ])
                ->submitAction(
                    Action::make('import')
                        ->label('Import Products')
                        ->action('importProducts')
                )
            ])
            ->statePath('data');
    }

    protected function parseCsv(): void
    {
        $data = $this->form->getState();
        $csvPath = $data['csv_file'] ?? null;
        
        if (!$csvPath) {
            return;
        }

        $fullPath = Storage::disk('local')->path($csvPath);
        
        if (!file_exists($fullPath)) {
            Notification::make()
                ->danger()
                ->title('CSV file not found')
                ->body('The uploaded CSV file could not be found.')
                ->send();
            return;
        }

        try {
            // Store parsed data in session for preview
            $importer = new ShopifyCsvImporter();
            $parsedData = $importer->parse($fullPath);
            
            session(['shopify_import_preview' => $parsedData]);
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error parsing CSV')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function importProducts(): void
    {
        $data = $this->form->getState();
        $csvPath = $data['csv_file'] ?? null;
        $stripeAccountId = $data['stripe_account_id'] ?? null;

        if (!$csvPath || !$stripeAccountId) {
            Notification::make()
                ->danger()
                ->title('Missing required data')
                ->body('Please ensure CSV file and store are selected.')
                ->send();
            return;
        }

        $fullPath = Storage::disk('local')->path($csvPath);
        
        if (!file_exists($fullPath)) {
            Notification::make()
                ->danger()
                ->title('CSV file not found')
                ->body('The uploaded CSV file could not be found.')
                ->send();
            return;
        }

        $importer = new ShopifyCsvImporter();
        $result = $importer->import($fullPath, $stripeAccountId);

        // Clean up uploaded file
        Storage::disk('local')->delete($csvPath);

        Notification::make()
            ->success()
            ->title('Import completed')
            ->body("Successfully imported {$result['imported']} products. {$result['skipped']} skipped. {$result['errors']} errors.")
            ->send();

        // Redirect to products list
        $this->redirect(ConnectedProductResource::getUrl('index'));
    }

    public function getPreviewData(): ?array
    {
        return session('shopify_import_preview');
    }
}

