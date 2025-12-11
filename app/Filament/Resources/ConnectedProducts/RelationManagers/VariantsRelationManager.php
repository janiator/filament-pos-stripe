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

    /**
     * Only show variants relation manager for variable products
     */
    public function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record instanceof \App\Models\ConnectedProduct && $record->isVariable();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert cents to decimal for display (formatStateUsing handles this now)
        // But we still need to ensure it's set for initial load
        if (isset($data['price_amount']) && $data['price_amount'] !== null && $data['price_amount'] > 0) {
            $data['price_amount'] = $data['price_amount'] / 100;
        }
        if (isset($data['compare_at_price_amount']) && $data['compare_at_price_amount'] !== null && $data['compare_at_price_amount'] > 0) {
            $data['compare_at_price_amount'] = $data['compare_at_price_amount'] / 100;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // dehydrateStateUsing handles the conversion now, but ensure stripe_account_id is set
        if (!isset($data['stripe_account_id'])) {
            $data['stripe_account_id'] = $this->ownerRecord->stripe_account_id;
        }

        return $data;
    }
    
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Ensure conversion happens even if mutateFormDataBeforeSave wasn't called
        // Convert price_decimal to price_amount if present
        if (isset($data['price_decimal'])) {
            if ($data['price_decimal'] !== null && $data['price_decimal'] !== '') {
                $data['price_amount'] = (int) round((float) $data['price_decimal'] * 100);
            } else {
                $data['price_amount'] = null;
            }
            unset($data['price_decimal']);
        }
        
        // Convert compare_at_price_decimal to compare_at_price_amount if present
        if (isset($data['compare_at_price_decimal'])) {
            if ($data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                $data['compare_at_price_amount'] = (int) round((float) $data['compare_at_price_decimal'] * 100);
            } else {
                $data['compare_at_price_amount'] = null;
            }
            unset($data['compare_at_price_decimal']);
        }
        
        // Debug logging
        \Log::debug('VariantsRelationManager handleRecordUpdate', [
            'record_id' => $record->id,
            'data_keys' => array_keys($data),
            'price_amount' => $data['price_amount'] ?? 'NOT SET',
            'price_decimal' => $data['price_decimal'] ?? 'NOT SET',
            'current_price_amount' => $record->price_amount,
        ]);
        
        // Call parent to handle the update
        $result = parent::handleRecordUpdate($record, $data);
        
        // Debug after save
        $result->refresh();
        \Log::debug('VariantsRelationManager after update', [
            'record_id' => $result->id,
            'saved_price_amount' => $result->price_amount,
        ]);
        
        return $result;
    }
    
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Ensure conversion happens even if mutateFormDataBeforeSave wasn't called
        // Convert price_decimal to price_amount if present
        if (isset($data['price_decimal'])) {
            if ($data['price_decimal'] !== null && $data['price_decimal'] !== '') {
                $data['price_amount'] = (int) round((float) $data['price_decimal'] * 100);
            } else {
                $data['price_amount'] = null;
            }
            unset($data['price_decimal']);
        }
        
        // Convert compare_at_price_decimal to compare_at_price_amount if present
        if (isset($data['compare_at_price_decimal'])) {
            if ($data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                $data['compare_at_price_amount'] = (int) round((float) $data['compare_at_price_decimal'] * 100);
            } else {
                $data['compare_at_price_amount'] = null;
            }
            unset($data['compare_at_price_decimal']);
        }
        
        // Call parent to handle the creation
        return parent::handleRecordCreation($data);
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
                        Grid::make(2)
                            ->schema([
                                TextInput::make('price_amount')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix(fn ($get) => strtoupper($get('currency') ?? 'NOK'))
                                    ->helperText('Enter price in decimal format (e.g., 99.99). Leave empty for custom price input on POS.')
                                    ->live()
                                    ->afterStateHydrated(function ($state, $set, $get, $record) {
                                        // Convert cents to decimal for display
                                        if ($state !== null && $record && $record->price_amount) {
                                            $set('price_amount', $record->price_amount / 100);
                                        }
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        // Convert decimal input to cents for storage
                                        if ($state !== null && $state !== '') {
                                            return (int) round((float) $state * 100);
                                        }
                                        return null;
                                    })
                                    ->formatStateUsing(function ($state) {
                                        // Convert cents to decimal for display
                                        if ($state !== null && $state > 0) {
                                            return $state / 100;
                                        }
                                        return null;
                                    }),

                                TextInput::make('compare_at_price_amount')
                                    ->label('Compare At Price')
                                    ->numeric()
                                    ->prefix(fn ($get) => strtoupper($get('currency') ?? 'NOK'))
                                    ->helperText('Original price for showing discounts')
                                    ->live()
                                    ->afterStateHydrated(function ($state, $set, $get, $record) {
                                        // Convert cents to decimal for display
                                        if ($state !== null && $record && $record->compare_at_price_amount) {
                                            $set('compare_at_price_amount', $record->compare_at_price_amount / 100);
                                        }
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        // Convert decimal input to cents for storage
                                        if ($state !== null && $state !== '') {
                                            return (int) round((float) $state * 100);
                                        }
                                        return null;
                                    })
                                    ->formatStateUsing(function ($state) {
                                        // Convert cents to decimal for display
                                        if ($state !== null && $state > 0) {
                                            return $state / 100;
                                        }
                                        return null;
                                    })
                                    ->visible(fn ($get) => ($get('price_amount') ?? 0) > 0),

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

                        Toggle::make('no_price_in_pos')
                            ->label('No Price in POS')
                            ->helperText('Enable this to allow custom price input on POS. When enabled, the price field can be left empty and will not sync to Stripe.')
                            ->default(false)
                            ->columnSpanFull(),

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
