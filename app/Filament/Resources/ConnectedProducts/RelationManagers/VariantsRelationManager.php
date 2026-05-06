<?php

namespace App\Filament\Resources\ConnectedProducts\RelationManagers;

use App\Enums\AddonType;
use App\Filament\Resources\ConnectedProducts\Actions\BulkCreateVariantsAction;
use App\Models\Addon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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
        if (! isset($data['stripe_account_id'])) {
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

        // Call parent to handle the update
        $result = parent::handleRecordUpdate($record, $data);

        $result->refresh();

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
                                    ->label(__('Option 1 Name'))
                                    ->placeholder(__('e.g., Size'))
                                    ->maxLength(255),

                                TextInput::make('option1_value')
                                    ->label(__('Option 1 Value'))
                                    ->placeholder(__('e.g., Large'))
                                    ->maxLength(255),

                                TextInput::make('option2_name')
                                    ->label(__('Option 2 Name'))
                                    ->placeholder(__('e.g., Color'))
                                    ->maxLength(255),

                                TextInput::make('option2_value')
                                    ->label(__('Option 2 Value'))
                                    ->placeholder(__('e.g., Red'))
                                    ->maxLength(255),

                                TextInput::make('option3_name')
                                    ->label(__('Option 3 Name'))
                                    ->placeholder(__('e.g., Material'))
                                    ->maxLength(255),

                                TextInput::make('option3_value')
                                    ->label(__('Option 3 Value'))
                                    ->placeholder(__('e.g., Cotton'))
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Pricing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('price_amount')
                                    ->label(__('Price'))
                                    ->numeric()
                                    ->prefix(fn ($get) => strtoupper($get('currency') ?? 'NOK'))
                                    ->helperText(__('Enter price in decimal format (e.g., 99.99). Leave empty for custom price input on POS.'))
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
                                    ->label(__('Compare At Price'))
                                    ->numeric()
                                    ->prefix(fn ($get) => strtoupper($get('currency') ?? 'NOK'))
                                    ->helperText(__('Original price for showing discounts'))
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
                                    ->label(__('Currency'))
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
                            ->label(__('No Price in POS'))
                            ->helperText(__('Enable this to allow custom price input on POS. When enabled, the price field can be left empty and will not sync to Stripe.'))
                            ->default(false)
                            ->columnSpanFull(),

                    ])
                    ->collapsible(),

                Section::make('Identification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('sku')
                                    ->label(__('SKU'))
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        return $rule->where('stripe_account_id', $this->ownerRecord->stripe_account_id);
                                    }),

                                TextInput::make('barcode')
                                    ->label(__('Barcode'))
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Inventory')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('inventory_quantity')
                                    ->label(__('Inventory Quantity'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('Leave empty if not tracking inventory'))
                                    ->live(),

                                Select::make('inventory_policy')
                                    ->label(__('Inventory Policy'))
                                    ->options([
                                        'deny' => 'Deny (prevent sales when out of stock)',
                                        'continue' => 'Continue (allow backorders)',
                                    ])
                                    ->default('deny'),

                                TextInput::make('inventory_management')
                                    ->label(__('Inventory Management'))
                                    ->placeholder(__('e.g., shopify'))
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->visible(fn (): bool => Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Inventory)
                        && (bool) $this->ownerRecord->track_inventory)
                    ->collapsible(),

                Section::make('Physical Properties')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('weight_grams')
                                    ->label(__('Weight (grams)'))
                                    ->numeric()
                                    ->helperText(__('Enter weight in grams')),

                                Toggle::make('requires_shipping')
                                    ->label(__('Requires Shipping'))
                                    ->default(true),

                                Toggle::make('taxable')
                                    ->label(__('Taxable'))
                                    ->default(true),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('active')
                                    ->label(__('Active'))
                                    ->default(true),

                                TextInput::make('image_url')
                                    ->label(__('Variant Image URL'))
                                    ->url()
                                    ->maxLength(255)
                                    ->helperText(__('URL to variant-specific image')),
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
                    ->label(__('Variant'))
                    ->badge()
                    ->color('primary')
                    ->searchable(['option1_value', 'option2_value', 'option3_value'])
                    ->description(fn ($record) => $record->sku ? "SKU: {$record->sku}" : null),

                TextColumn::make('formatted_price')
                    ->label(__('Price'))
                    ->badge()
                    ->color('success')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('price_amount', $direction);
                    }),

                TextColumn::make('compare_at_price_amount')
                    ->label(__('Compare At'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2).' NOK' : '-')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('discount_percentage')
                    ->label(__('Discount'))
                    ->formatStateUsing(fn ($state) => $state ? "{$state}%" : '-')
                    ->badge()
                    ->color('danger')
                    ->toggleable(),

                TextColumn::make('inventory_quantity')
                    ->label(__('Stock'))
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
                    ->searchable()
                    ->visible(fn (): bool => Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Inventory)
                        && (bool) $this->ownerRecord->track_inventory),

                IconColumn::make('in_stock')
                    ->label(__('In Stock'))
                    ->boolean()
                    ->toggleable()
                    ->visible(fn (): bool => Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Inventory)
                        && (bool) $this->ownerRecord->track_inventory),

                IconColumn::make('active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('stripe_product_id')
                    ->label(__('Stripe Product ID'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder(__('Not synced')),

                TextColumn::make('stripe_price_id')
                    ->label(__('Stripe Price ID'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder(__('Not synced')),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('Active'))
                    ->placeholder(__('All'))
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('in_stock')
                    ->label(__('In Stock'))
                    ->placeholder(__('All'))
                    ->trueLabel('In stock only')
                    ->falseLabel('Out of stock only')
                    ->visible(fn (): bool => Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Inventory)
                        && (bool) $this->ownerRecord->track_inventory),
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
