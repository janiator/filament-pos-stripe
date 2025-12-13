<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use App\Services\ProductZipImporter;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ImportProductsZip extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ConnectedProductResource::class;

    protected string $view = 'filament.resources.connected-products.pages.import-products-zip';

    public ?array $data = [];
    
    public ?array $previewData = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('upload')
                        ->label('Upload ZIP')
                        ->description('Upload your products export ZIP file')
                        ->schema([
                            FileUpload::make('zip_file')
                                ->label('ZIP File')
                                ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                                ->disk('local')
                                ->directory('imports')
                                ->visibility('private')
                                ->required()
                                ->helperText('Upload a products export ZIP file created with the products:export command')
                                ->columnSpanFull(),
                        ]),

                    Step::make('preview')
                        ->label('Preview')
                        ->description('Review what will be imported')
                        ->schema([
                            View::make('filament.resources.connected-products.pages.import-zip-preview')
                                ->key('import-zip-preview'),
                        ])
                        ->beforeValidation(function () {
                            // Parse ZIP when entering preview step
                            $this->previewZip();
                        }),

                    Step::make('options')
                        ->label('Options')
                        ->description('Configure import options')
                        ->schema([
                            Checkbox::make('update_existing')
                                ->label('Update existing products/collections')
                                ->helperText('If checked, existing products and collections will be updated. Otherwise, they will be skipped.')
                                ->default(false),
                        ]),

                    Step::make('import')
                        ->label('Import')
                        ->description('Confirm and import products')
                        ->schema([
                            // Import confirmation
                        ]),
                ])
                ->submitAction(
                    new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button
                            wire:click="importProducts"
                            size="lg"
                            color="success"
                        >
                            Import Products & Collections
                        </x-filament::button>
                    BLADE))
                )
            ]);
    }

    protected function previewZip(): void
    {
        $data = $this->form->getState();
        $zipPath = $data['zip_file'] ?? null;
        
        if (!$zipPath) {
            return;
        }

        $fullPath = Storage::disk('local')->path($zipPath);
        
        if (!file_exists($fullPath)) {
            Notification::make()
                ->danger()
                ->title('ZIP file not found')
                ->body('The uploaded ZIP file could not be found.')
                ->send();
            return;
        }

        try {
            $importer = new ProductZipImporter();
            $this->previewData = $importer->preview($fullPath);
            
            // Store in session as backup
            session(['zip_import_preview' => $this->previewData]);
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error reading ZIP file')
                ->body($e->getMessage())
                ->send();
            $this->previewData = null;
        }
    }

    public function importProducts(): void
    {
        $store = \Filament\Facades\Filament::getTenant();
        
        if (!$store || !$store->stripe_account_id) {
            Notification::make()
                ->danger()
                ->title('Store not configured')
                ->body('The current store does not have a Stripe account configured.')
                ->send();
            return;
        }
        
        $data = $this->form->getState();
        $zipPath = $data['zip_file'] ?? null;
        $update = $data['update_existing'] ?? false;

        if (!$zipPath) {
            Notification::make()
                ->danger()
                ->title('Missing required data')
                ->body('Please upload a ZIP file.')
                ->send();
            return;
        }

        $fullPath = Storage::disk('local')->path($zipPath);
        
        if (!file_exists($fullPath)) {
            Notification::make()
                ->danger()
                ->title('ZIP file not found')
                ->body('The uploaded ZIP file could not be found.')
                ->send();
            return;
        }

        try {
            $importer = new ProductZipImporter();
            $result = $importer->import($fullPath, $store, $update, false);

            $stats = $result['stats'];
            $message = sprintf(
                "Import completed! Collections: %d created, %d updated, %d skipped. Products: %d created, %d updated, %d skipped.",
                $stats['collections']['imported'],
                $stats['collections']['updated'],
                $stats['collections']['skipped'],
                $stats['products']['imported'],
                $stats['products']['updated'],
                $stats['products']['skipped']
            );

            Notification::make()
                ->success()
                ->title('Import completed')
                ->body($message)
                ->send();

            // Clean up uploaded file
            Storage::disk('local')->delete($zipPath);

            // Redirect to products list
            $this->redirect(ConnectedProductResource::getUrl('index'));
        } catch (\Exception $e) {
            // Clean up uploaded file on error
            if ($zipPath && Storage::disk('local')->exists($zipPath)) {
                Storage::disk('local')->delete($zipPath);
            }

            Notification::make()
                ->danger()
                ->title('Import failed')
                ->body('Failed to import ZIP file: ' . $e->getMessage())
                ->send();
        }
    }

    public function getPreviewData(): ?array
    {
        return $this->previewData ?? session('zip_import_preview');
    }
}
