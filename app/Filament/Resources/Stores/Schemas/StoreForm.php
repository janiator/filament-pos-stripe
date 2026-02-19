<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StoreForm
{
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
            ]);
    }
}
