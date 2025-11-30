<?php

namespace App\Filament\Resources\Discounts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiscountForm
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
                            ->searchable()
                            ->preload()
                            ->visibleOn('create'),

                        TextInput::make('title')
                            ->label('Discount Title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active discounts will be applied automatically'),
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

                Section::make('Schedule')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Starts At')
                                    ->helperText('When the discount becomes active'),

                                DateTimePicker::make('ends_at')
                                    ->label('Ends At')
                                    ->helperText('When the discount expires')
                                    ->after('starts_at'),
                            ]),
                    ]),

                Section::make('Customer Selection')
                    ->schema([
                        Select::make('customer_selection')
                            ->label('Customer Selection')
                            ->options([
                                'all' => 'All Customers',
                                'specific_customers' => 'Specific Customers',
                            ])
                            ->default('all')
                            ->required()
                            ->live(),

                        Select::make('customer_ids')
                            ->label('Customers')
                            ->multiple()
                            ->relationship('store.connectedCustomers', 'name', fn ($query) => $query->whereNotNull('stripe_customer_id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('customer_selection') === 'specific_customers'),
                    ]),

                Section::make('Minimum Requirements')
                    ->schema([
                        Select::make('minimum_requirement_type')
                            ->label('Minimum Requirement')
                            ->options([
                                'none' => 'No Minimum',
                                'minimum_purchase_amount' => 'Minimum Purchase Amount',
                                'minimum_quantity' => 'Minimum Quantity',
                            ])
                            ->default('none')
                            ->required()
                            ->live(),

                        TextInput::make('minimum_requirement_value')
                            ->label(fn ($get) => match($get('minimum_requirement_type')) {
                                'minimum_purchase_amount' => 'Minimum Amount (in cents)',
                                'minimum_quantity' => 'Minimum Quantity',
                                default => 'Value',
                            })
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->visible(fn ($get) => $get('minimum_requirement_type') !== 'none'),
                    ]),

                Section::make('Product Selection')
                    ->schema([
                        Select::make('applicable_to')
                            ->label('Applicable To')
                            ->options([
                                'all_products' => 'All Products',
                                'specific_products' => 'Specific Products',
                            ])
                            ->default('all_products')
                            ->required()
                            ->live(),

                        Select::make('product_ids')
                            ->label('Products')
                            ->multiple()
                            ->relationship('store.connectedProducts', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('applicable_to') === 'specific_products'),
                    ]),

                Section::make('Usage Limits')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('usage_limit')
                                    ->label('Total Usage Limit')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Maximum number of times this discount can be used'),

                                TextInput::make('usage_limit_per_customer')
                                    ->label('Per Customer Limit')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Maximum number of times per customer'),
                            ]),

                        TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority discounts are applied first'),
                    ]),
            ]);
    }
}
