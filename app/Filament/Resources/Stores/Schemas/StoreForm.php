<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Services\MeranoConnectionService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function supportsMeranoConfiguration(): bool
    {
        return app(MeranoConnectionService::class)->supportsStoreConfiguration();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Store Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('This field will sync to Stripe when saved'),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record && $record->stripe_account_id)
                            ->helperText(fn ($record) => $record && $record->stripe_account_id
                                ? 'Email cannot be synced to Stripe for connected accounts. Update it in the Stripe Dashboard or Connect onboarding flow.'
                                : 'Email address for the store'),

                        TextInput::make('organisasjonsnummer')
                            ->label('Organisasjonsnummer')
                            ->maxLength(255)
                            ->helperText('Organization number (org.nr.) used on receipts'),

                        TextInput::make('address')
                            ->label('Store address')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Address shown on receipts (e.g. street, postcode and city)'),

                        TextInput::make('z_report_email')
                            ->label('Z-Report Email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Email address to automatically receive Z-reports when POS sessions are closed'),

                        FileUpload::make('logo_path')
                            ->label('Store Logo')
                            ->image()
                            ->optimize('webp')
                            ->maxImageWidth(1024)
                            ->maxImageHeight(1024)
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->disk('public')
                            ->directory('store-logos')
                            ->visibility('public')
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->helperText('Upload a logo for this store. The logo will be displayed on receipts. Max 5MB. JPEG, PNG, WebP, and GIF are supported.')
                            ->columnSpanFull(),

                        TextInput::make('receipt_logo_max_width_dots')
                            ->label('Receipt logo max width (dots)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(576)
                            ->nullable()
                            ->placeholder('Default: 384')
                            ->helperText('Optional. Max width of the logo on receipts in printer dots (80mm receipt = 576). Leave empty for system default.'),

                        TextInput::make('receipt_logo_max_height_dots')
                            ->label('Receipt logo max height (dots)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(400)
                            ->nullable()
                            ->placeholder('Default: 200')
                            ->helperText('Optional. Max height of the logo on receipts in printer dots. Leave empty for system default.'),
                    ]),

                Section::make('Commission Configuration')
                    ->schema([
                        Radio::make('commission_type')
                            ->label('Commission type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Fixed (minor units)',
                            ])
                            ->default('percentage')
                            ->inline()
                            ->live(),

                        TextInput::make('commission_rate')
                            ->label('Commission rate')
                            ->required()
                            ->numeric()
                            ->helperText(function (Get $get) {
                                return $get('commission_type') === 'percentage'
                                    ? 'Enter whole percentage (e.g. 5 = 5%).'
                                    : 'Enter fixed fee in minor units (e.g. 500 = 5.00).';
                            })
                            ->suffix(function (Get $get) {
                                return $get('commission_type') === 'percentage' ? '%' : 'units';
                            }),
                    ]),

                Section::make('Stripe Configuration')
                    ->schema([
                        TextInput::make('stripe_account_id')
                            ->label('Stripe account ID')
                            ->helperText('Set automatically after connecting the store to Stripe (e.g. acct_xxx).')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Terminal Provider Configuration')
                    ->schema([
                        Select::make('default_terminal_provider')
                            ->label('Default terminal provider')
                            ->options([
                                'stripe' => 'Stripe',
                                'verifone' => 'Verifone',
                            ])
                            ->required()
                            ->default('stripe')
                            ->helperText('Used by POS clients as default terminal provider when none is explicitly selected.'),

                        TextInput::make('verifone_api_base_url')
                            ->label('Verifone API base URL')
                            ->nullable()
                            ->url()
                            ->placeholder('https://domainname.com')
                            ->helperText('Base URL used for Verifone POS Cloud NEXO requests.'),

                        TextInput::make('verifone_user_uid')
                            ->label('Verifone User UID')
                            ->nullable()
                            ->maxLength(255)
                            ->helperText('UID used together with API key for Verifone authorization.'),

                        TextInput::make('verifone_api_key')
                            ->label('Verifone API Key')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->afterStateHydrated(function ($component): void {
                                $component->state('');
                            })
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn ($record) => filled($record?->verifone_api_key)
                                ? 'Stored encrypted. A key is already saved; leave blank to keep it, or enter a new value to replace it.'
                                : 'Stored encrypted. Leave blank to keep the existing key.'),

                        TextInput::make('verifone_encoded_basic_auth')
                            ->label('Verifone pre-encoded Basic auth')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->afterStateHydrated(function ($component): void {
                                $component->state('');
                            })
                            ->dehydrated(fn (?string $state, Get $get): bool => filled($state) || (bool) $get('clear_verifone_encoded_basic_auth'))
                            ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => (bool) $get('clear_verifone_encoded_basic_auth') ? null : $state)
                            ->helperText(fn ($record) => filled($record?->verifone_encoded_basic_auth)
                                ? 'Optional override. A token is already saved. Leave blank to keep it, or enter a new value to replace it.'
                                : 'Optional override. Paste only the base64 token (without "Basic "). When set, this is used instead of User UID + API key. Stored encrypted.'),

                        Toggle::make('clear_verifone_encoded_basic_auth')
                            ->label('Clear saved pre-encoded Basic auth token')
                            ->default(false)
                            ->dehydrated(false)
                            ->visible(fn ($record): bool => filled($record?->verifone_encoded_basic_auth))
                            ->helperText('Enable this when you want to remove the stored override and use Verifone User UID + API key instead.'),

                        TextInput::make('verifone_site_entity_id')
                            ->label('Verifone Site Entity ID')
                            ->nullable()
                            ->maxLength(255)
                            ->helperText('Required when using parent tokens to scope terminal API calls.'),

                        TextInput::make('verifone_sale_id')
                            ->label('Default Verifone Sale ID')
                            ->nullable()
                            ->maxLength(255)
                            ->helperText('Used as fallback sale ID for Verifone payment and status requests.'),

                        TextInput::make('verifone_operator_id')
                            ->label('Default Verifone Operator ID')
                            ->nullable()
                            ->maxLength(255)
                            ->helperText('Used as fallback operator ID in payment requests.'),

                        Toggle::make('verifone_terminal_simulator')
                            ->label('Use Verifone terminal simulator')
                            ->default(false)
                            ->helperText('Adds simulator header for Verifone API calls in non-production testing.'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Merano Integration')
                    ->schema([
                        TextInput::make('merano_base_url')
                            ->label('Merano Base URL')
                            ->url()
                            ->nullable()
                            ->placeholder('https://merano.example.com')
                            ->helperText('Merano API base URL without a trailing slash. Only used when the Merano Booking add-on is active.'),

                        TextInput::make('merano_pos_api_token')
                            ->label('Merano POS API Token')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->afterStateHydrated(function ($component): void {
                                $component->state('');
                            })
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('POS_API_TOKEN from Merano. Stored encrypted. Leave blank to keep the current token.'),

                        Select::make('merano_ticket_connected_product_id')
                            ->label('Merano ticket product')
                            ->relationship(
                                name: 'meranoTicketProduct',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query, $get, $record) {
                                    if ($record && $record->stripe_account_id) {
                                        return $query->where('stripe_account_id', $record->stripe_account_id);
                                    }

                                    return $query->whereRaw('1 = 0');
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Product used as the cart line when adding Merano bookings in the POS. Used by the ticket-product API and automatic add-to-cart.'),

                        TextInput::make('reports_api_token')
                            ->label('Merano Reports Token')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->afterStateHydrated(function ($component): void {
                                $component->state('');
                            })
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Token Merano uses to pull kiosk-sales reports from this store. Use "Generate Reports Token" in the header to create one.'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record
                        && self::supportsMeranoConfiguration()
                        && Addon::storeHasActiveAddon($record->id, AddonType::MeranoBooking)),
            ]);
    }
}
