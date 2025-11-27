<?php

namespace App\Filament\Resources\ConnectedProducts\RelationManagers;

use App\Filament\Resources\ConnectedProducts\Actions\BulkCreateVariantsAction;
use App\Models\ProductVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert cents to decimal for display
        if (isset($data['price_amount'])) {
            $data['price_decimal'] = $data['price_amount'] / 100;
        }
        if (isset($data['compare_at_price_amount'])) {
            $data['compare_at_price_decimal'] = $data['compare_at_price_amount'] / 100;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert decimal to cents for storage
        if (isset($data['price_decimal'])) {
            $data['price_amount'] = (int) round($data['price_decimal'] * 100);
            unset($data['price_decimal']);
        }
        if (isset($data['compare_at_price_decimal'])) {
            if ($data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                $data['compare_at_price_amount'] = (int) round($data['compare_at_price_decimal'] * 100);
            } else {
                $data['compare_at_price_amount'] = null;
            }
            unset($data['compare_at_price_decimal']);
        }
        
        // Ensure stripe_account_id is set
        if (!isset($data['stripe_account_id'])) {
            $data['stripe_account_id'] = $this->ownerRecord->stripe_account_id;
        }
        
        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Variant Options')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('option1_name')
                                    ->label('Option 1 Name')
                                    ->placeholder('e.g., Size')
                                    ->maxLength(255),

                                TextInput::make('option1_value')
                                    ->label('Option 1 Value')
                                    ->placeholder('e.g., Large')
                                    ->maxLength(255),

                                TextInput::make('option2_name')
                                    ->label('Option 2 Name')
                                    ->placeholder('e.g., Color')
                                    ->maxLength(255),

                                TextInput::make('option2_value')
                                    ->label('Option 2 Value')
                                    ->placeholder('e.g., Red')
                                    ->maxLength(255),

                                TextInput::make('option3_name')
                                    ->label('Option 3 Name')
                                    ->placeholder('e.g., Material')
                                    ->maxLength(255),

                                TextInput::make('option3_value')
                                    ->label('Option 3 Value')
                                    ->placeholder('e.g., Cotton')
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Pricing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('price_decimal')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix(fn ($get) => strtoupper($get('currency') ?? 'NOK'))
                                    ->helperText('Enter price in decimal format (e.g., 99.99)')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        // Convert decimal to cents
                                        if ($state !== null && $state !== '') {
                                            $set('price_amount', (int) round($state * 100));
                                        }
                                    })
                                    ->default(fn ($record) => $record ? $record->price_amount / 100 : null),

                                TextInput::make('compare_at_price_decimal')
                                    ->label('Compare At Price')
                                    ->numeric()
                                    ->prefix(fn ($get) => strtoupper($get('currency') ?? 'NOK'))
                                    ->helperText('Original price for showing discounts')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set) {
                                        // Convert decimal to cents
                                        if ($state !== null && $state !== '') {
                                            $set('compare_at_price_amount', (int) round($state * 100));
                                        } else {
                                            $set('compare_at_price_amount', null);
                                        }
                                    })
                                    ->default(fn ($record) => $record && $record->compare_at_price_amount ? $record->compare_at_price_amount / 100 : null)
                                    ->visible(fn ($get) => $get('price_decimal') > 0),

                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'nok' => 'NOK',
                                        'usd' => 'USD',
                                        'eur' => 'EUR',
                                    ])
                                    ->default('nok')
                                    ->required()
                                    ->live(),
                            ]),

                        // Hidden fields for actual storage (in cents)
                        TextInput::make('price_amount')
                            ->hidden()
                            ->dehydrated(),

                        TextInput::make('compare_at_price_amount')
                            ->hidden()
                            ->dehydrated(),
                    ])
                    ->collapsible(),

                Section::make('Identification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        return $rule->where('stripe_account_id', $this->ownerRecord->stripe_account_id);
                                    }),

                                TextInput::make('barcode')
                                    ->label('Barcode')
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Inventory')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('inventory_quantity')
                                    ->label('Inventory Quantity')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Leave empty if not tracking inventory')
                                    ->live(),

                                Select::make('inventory_policy')
                                    ->label('Inventory Policy')
                                    ->options([
                                        'deny' => 'Deny (prevent sales when out of stock)',
                                        'continue' => 'Continue (allow backorders)',
                                    ])
                                    ->default('deny'),

                                TextInput::make('inventory_management')
                                    ->label('Inventory Management')
                                    ->placeholder('e.g., shopify')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Physical Properties')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('weight_grams')
                                    ->label('Weight (grams)')
                                    ->numeric()
                                    ->helperText('Enter weight in grams'),

                                Toggle::make('requires_shipping')
                                    ->label('Requires Shipping')
                                    ->default(true),

                                Toggle::make('taxable')
                                    ->label('Taxable')
                                    ->default(true),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('active')
                                    ->label('Active')
                                    ->default(true),

                                TextInput::make('image_url')
                                    ->label('Variant Image URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->helperText('URL to variant-specific image'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant_name')
                    ->label('Variant')
                    ->badge()
                    ->color('primary')
                    ->searchable(['option1_value', 'option2_value', 'option3_value'])
                    ->description(fn ($record) => $record->sku ? "SKU: {$record->sku}" : null),

                TextColumn::make('formatted_price')
                    ->label('Price')
                    ->badge()
                    ->color('success')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('price_amount', $direction);
                    }),

                TextColumn::make('compare_at_price_amount')
                    ->label('Compare At')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2) . ' NOK' : '-')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}%" : '-')
                    ->badge()
                    ->color('danger')
                    ->toggleable(),

                TextColumn::make('inventory_quantity')
                    ->label('Stock')
                    ->formatStateUsing(function ($state, $record) {
                        if ($state === null) {
                            return 'Not tracked';
                        }
                        return $state > 0 
                            ? "<span class='text-success-600 font-semibold'>{$state}</span>" 
                            : "<span class='text-danger-600 font-semibold'>Out of stock</span>";
                    })
                    ->html()
                    ->sortable()
                    ->toggleable()
                    ->searchable(),

                IconColumn::make('in_stock')
                    ->label('In Stock')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('stripe_product_id')
                    ->label('Stripe Product ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not synced'),

                TextColumn::make('stripe_price_id')
                    ->label('Stripe Price ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not synced'),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('in_stock')
                    ->label('In Stock')
                    ->placeholder('All')
                    ->trueLabel('In stock only')
                    ->falseLabel('Out of stock only'),
            ])
            ->headerActions([
                BulkCreateVariantsAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('price_amount')
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
