<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\HtmlString;
use Illuminate\Support\Facades\Blade;

class OnboardStoreWizard
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    // Step 1: Basic Store Information
                    Step::make('basic_info')
                        ->label('Basic Information')
                        ->description('Enter the fundamental store details')
                        ->schema([
                            TextInput::make('name')
                                ->label('Store Name')
                                ->required()
                                ->maxLength(255)
                                ->helperText('The name of the store as it will appear in the system'),

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->unique(\App\Models\Store::class, 'email', ignoreRecord: true)
                                ->helperText('Email address for the store (required by Stripe)'),

                            TextInput::make('organisasjonsnummer')
                                ->label('Organisasjonsnummer')
                                ->maxLength(255)
                                ->helperText('Organization number (org.nr.) used on receipts (optional)'),
                        ]),

                    // Step 2: Commission Configuration
                    Step::make('commission')
                        ->label('Commission')
                        ->description('Configure the platform fee structure')
                        ->schema([
                            Radio::make('commission_type')
                                ->label('Commission Type')
                                ->options([
                                    'percentage' => 'Percentage',
                                    'fixed' => 'Fixed (minor units)',
                                ])
                                ->default('percentage')
                                ->inline()
                                ->live()
                                ->required(),

                            TextInput::make('commission_rate')
                                ->label('Commission Rate')
                                ->required()
                                ->numeric()
                                ->default(0)
                                ->helperText(function (Get $get) {
                                    return $get('commission_type') === 'percentage'
                                        ? 'Enter whole percentage (e.g. 5 = 5%).'
                                        : 'Enter fixed fee in minor units (e.g. 500 = 5.00).';
                                })
                                ->suffix(function (Get $get) {
                                    return $get('commission_type') === 'percentage' ? '%' : 'units';
                                }),
                        ]),

                    // Step 3: Stripe Connect Account Setup
                    Step::make('stripe')
                        ->label('Stripe Account')
                        ->description('Set up Stripe Connect account')
                        ->schema([
                            Radio::make('stripe_setup_type')
                                ->label('Stripe Account Setup')
                                ->options([
                                    'create' => 'Create New Stripe Account',
                                    'link' => 'Link Existing Stripe Account',
                                ])
                                ->default('create')
                                ->inline()
                                ->live()
                                ->required(),

                            TextInput::make('stripe_account_id')
                                ->label('Stripe Account ID')
                                ->placeholder('acct_xxx')
                                ->visible(fn (Get $get) => $get('stripe_setup_type') === 'link')
                                ->required(fn (Get $get) => $get('stripe_setup_type') === 'link')
                                ->helperText('Enter the existing Stripe Connect account ID (e.g. acct_xxx)')
                                ->validationMessages([
                                    'required' => 'Stripe account ID is required when linking an existing account.',
                                ]),
                        ]),

                    // Step 4: Initial Settings Configuration
                    Step::make('settings')
                        ->label('Settings')
                        ->description('Configure basic POS settings')
                        ->schema([
                            Select::make('currency')
                                ->label('Currency')
                                ->options([
                                    'nok' => 'NOK - Norwegian Krone',
                                    'eur' => 'EUR - Euro',
                                    'usd' => 'USD - US Dollar',
                                    'sek' => 'SEK - Swedish Krona',
                                    'dkk' => 'DKK - Danish Krone',
                                ])
                                ->default('nok')
                                ->required(),

                            Select::make('timezone')
                                ->label('Timezone')
                                ->options([
                                    'Europe/Oslo' => 'Europe/Oslo (Norway)',
                                    'Europe/Stockholm' => 'Europe/Stockholm (Sweden)',
                                    'Europe/Copenhagen' => 'Europe/Copenhagen (Denmark)',
                                    'Europe/Amsterdam' => 'Europe/Amsterdam (Netherlands)',
                                    'Europe/London' => 'Europe/London (UK)',
                                    'UTC' => 'UTC',
                                ])
                                ->default('Europe/Oslo')
                                ->searchable()
                                ->required(),

                            Select::make('locale')
                                ->label('Locale')
                                ->options([
                                    'nb' => 'Norwegian (Bokmål)',
                                    'nn' => 'Norwegian (Nynorsk)',
                                    'en' => 'English',
                                    'sv' => 'Swedish',
                                    'da' => 'Danish',
                                ])
                                ->default('nb')
                                ->required(),

                            TextInput::make('default_vat_rate')
                                ->label('Default VAT Rate')
                                ->numeric()
                                ->default(25.00)
                                ->step(0.01)
                                ->suffix('%')
                                ->helperText('Default VAT rate for receipts (e.g. 25.00 = 25%)')
                                ->required(),

                            Toggle::make('tax_included')
                                ->label('Tax Included in Prices')
                                ->default(false)
                                ->helperText('Whether prices include tax by default'),

                            Toggle::make('tips_enabled')
                                ->label('Tips Enabled')
                                ->default(true)
                                ->helperText('Allow customers to add tips to transactions'),
                        ]),

                    // Step 5: User Assignment
                    Step::make('users')
                        ->label('Users')
                        ->description('Assign users to this store')
                        ->schema([
                            Select::make('user_ids')
                                ->label('Users')
                                ->options(function () {
                                    return \App\Models\User::query()
                                        ->get()
                                        ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])
                                        ->toArray();
                                })
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->helperText('Select users who should have access to this store')
                                ->required()
                                ->minItems(1)
                                ->validationMessages([
                                    'required' => 'At least one user must be assigned to the store.',
                                ]),
                        ]),

                    // Step 6: Review & Complete
                    Step::make('review')
                        ->label('Review')
                        ->description('Review all information before completing setup')
                        ->schema([
                            View::make('filament.resources.stores.pages.onboard-store-review')
                                ->key('review-summary'),
                        ]),
                ])
                ->submitAction(
                    new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button
                            wire:click="completeSetup"
                            size="lg"
                            color="success"
                            type="button"
                        >
                            Complete Setup
                        </x-filament::button>
                    BLADE))
                )
            ]);
    }
}



