<?php

namespace App\Filament\Pages;

use App\Services\ShopifyCsvImporter;
use BackedEnum;
use UnitEnum;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShopifyImportTest extends Page implements HasForms
{
    use InteractsWithForms;

    // One-line: must match Filament base property union types to avoid PHP 8.4 fatals.
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Shopify CSV Test';
    protected static ?string $title = 'Shopify CSV Test Import';
    protected static string|UnitEnum|null $navigationGroup = 'Dev';

    public ?array $formData = [];
    public ?array $parseResult = null;
    public ?array $importResult = null;

    public function getView(): string
    {
        return 'filament.pages.shopify-import-test';
    }

    public function mount(): void
    {
        $this->form->fill([
            'stripe_account_id' => '',
            'csv_path' => null,
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canAccess(): bool
    {
        return static::isSuperAdmin();
    }

    protected static function isSuperAdmin(): bool
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('formData')
            ->schema([
                Section::make('CSV Input')
                    ->schema([
                        FileUpload::make('csv_path')
                            ->label('Shopify CSV')
                            ->disk('local')
                            ->directory('tmp/shopify-import')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', '.csv'])
                            ->required(),

                        TextInput::make('stripe_account_id')
                            ->label('Stripe Account ID')
                            ->helperText('Connected account id for this store tenant')
                            ->placeholder('acct_...')
                            ->required(),
                    ]),
            ]);
    }

    public function parseCsv(): void
    {
        $data = $this->form->getState();

        $csvPath = $data['csv_path'] ?? null;
        $stripeAccountId = trim((string) ($data['stripe_account_id'] ?? ''));

        if (! $csvPath || ! $stripeAccountId) {
            Notification::make()
                ->title('Missing data')
                ->danger()
                ->body('Please upload a CSV and provide Stripe Account ID.')
                ->send();
            return;
        }

        $absolute = Storage::disk('local')->path($csvPath);

        try {
            $importer = new ShopifyCsvImporter();

            $parsed = $importer->parse($absolute);

            $this->parseResult = $parsed;
            $this->importResult = null;

            Notification::make()
                ->title('Parse complete')
                ->success()
                ->body('Parse stats are available below.')
                ->send();

            Log::info('ShopifyImportTest parse completed', [
                'stripe_account_id' => $stripeAccountId,
                'stats' => $parsed['stats'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->parseResult = null;

            Notification::make()
                ->title('Parse failed')
                ->danger()
                ->body($e->getMessage())
                ->send();

            Log::error('ShopifyImportTest parse failed', [
                'stripe_account_id' => $stripeAccountId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function runImport(): void
    {
        $data = $this->form->getState();

        $csvPath = $data['csv_path'] ?? null;
        $stripeAccountId = trim((string) ($data['stripe_account_id'] ?? ''));

        if (! $csvPath || ! $stripeAccountId) {
            Notification::make()
                ->title('Missing data')
                ->danger()
                ->body('Please upload a CSV and provide Stripe Account ID.')
                ->send();
            return;
        }

        $absolute = Storage::disk('local')->path($csvPath);

        try {
            $importer = new ShopifyCsvImporter();

            $result = $importer->import($absolute, $stripeAccountId);

            // Keep parse stats visible even if user skips the Parse step.
            if (! $this->parseResult) {
                try {
                    $this->parseResult = $importer->parse($absolute);
                } catch (\Throwable) {
                    // ok
                }
            }

            $this->importResult = $result;

            Notification::make()
                ->title('Import complete')
                ->success()
                ->body('Import stats are available below.')
                ->send();

            Log::info('ShopifyImportTest import completed', [
                'stripe_account_id' => $stripeAccountId,
                'stats' => $result['stats'] ?? null,
                'error_count' => $result['error_count'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->importResult = null;

            Notification::make()
                ->title('Import failed')
                ->danger()
                ->body($e->getMessage())
                ->send();

            Log::error('ShopifyImportTest import failed', [
                'stripe_account_id' => $stripeAccountId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
