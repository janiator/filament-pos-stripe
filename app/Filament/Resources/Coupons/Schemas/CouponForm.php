<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Select::make('store_id')
                            ->label('Store')
                            ->relationship('store', 'name')
                            ->required()
                            ->default(fn () => \Filament\Facades\Filament::getTenant()?->id)
                            ->searchable()
                            ->preload()
                            ->hiddenOn(['create', 'edit']),

                        TextInput::make('code')
                            ->label('Coupon Code')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->afterStateUpdated(fn ($state, $set) => $set('code', $state ? strtoupper($state) : $state))
                            ->helperText('Customers will enter this code to redeem the coupon'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active coupons can be redeemed'),
                    ]),

                Section::make('Discount Value')
                    ->schema([
                        Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed_amount' => 'Fixed Amount',
                            ])
                            ->required()
                            ->default('percentage')
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('discount_value', null)),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('discount_value')
                                    ->label(fn ($get) => $get('discount_type') === 'percentage' ? 'Percentage (%)' : 'Amount (in cents)')
                                    ->numeric()
                                    ->required()
                                    ->step(fn ($get) => $get('discount_type') === 'percentage' ? 0.1 : 1)
                                    ->minValue(0)
                                    ->maxValue(fn ($get) => $get('discount_type') === 'percentage' ? 100 : null)
                                    ->helperText(fn ($get) => $get('discount_type') === 'percentage' 
                                        ? 'Enter percentage (0-100)' 
                                        : 'Enter amount in cents (e.g., 1000 = 10.00)'),

                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'nok' => 'NOK',
                                        'usd' => 'USD',
                                        'eur' => 'EUR',
                                    ])
                                    ->default('nok')
                                    ->required()
                                    ->visible(fn ($get) => $get('discount_type') === 'fixed_amount'),
                            ]),
                    ]),

                Section::make('Duration')
                    ->schema([
                        Select::make('duration')
                            ->label('Duration')
                            ->options([
                                'once' => 'Once',
                                'repeating' => 'Repeating',
                                'forever' => 'Forever',
                            ])
                            ->required()
                            ->default('once')
                            ->live(),

                        TextInput::make('duration_in_months')
                            ->label('Duration (Months)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->required()
                            ->visible(fn ($get) => $get('duration') === 'repeating')
                            ->helperText('Number of months the discount applies'),
                    ]),

                Section::make('Expiration & Limits')
                    ->schema([
                        DateTimePicker::make('redeem_by')
                            ->label('Redeem By')
                            ->helperText('Last date the coupon can be redeemed'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('max_redemptions')
                                    ->label('Maximum Redemptions')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Total number of times this coupon can be used'),

                                TextInput::make('minimum_amount')
                                    ->label('Minimum Amount (in cents)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Minimum purchase amount required'),

                                Select::make('minimum_amount_currency')
                                    ->label('Minimum Amount Currency')
                                    ->options([
                                        'nok' => 'NOK',
                                        'usd' => 'USD',
                                        'eur' => 'EUR',
                                    ])
                                    ->default('nok')
                                    ->visible(fn ($get) => $get('minimum_amount') > 0),
                            ]),
                    ]),
            ]);
    }
}
