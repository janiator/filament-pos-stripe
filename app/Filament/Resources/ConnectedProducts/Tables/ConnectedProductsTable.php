<?php

namespace App\Filament\Resources\ConnectedProducts\Tables;

use App\Models\ConnectedProduct;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Shreejan\ActionableColumn\Tables\Columns\ActionableColumn;

class ConnectedProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'prices', 'variants', 'vendor']))
            ->columns([
                // Primary Information Group
                ActionableColumn::make('name')
                    ->label('Name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query->where('name', 'ilike', "%{$search}%")
                                ->orWhere('description', 'ilike', "%{$search}%")
                                ->orWhere('product_code', 'ilike', "%{$search}%")
                                ->orWhere('stripe_product_id', 'ilike', "%{$search}%")
                                ->orWhereHas('vendor', function (Builder $query) use ($search) {
                                    $query->where('name', 'ilike', "%{$search}%");
                                })
                                ->orWhereHas('store', function (Builder $query) use ($search) {
                                    $query->where('name', 'ilike', "%{$search}%");
                                });
                        });
                    })
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('-')
                    ->actionIcon(Heroicon::PencilSquare)
                    ->actionIconColor('primary')
                    ->clickableColumn()
                    ->tapAction(
                        Action::make('editName')
                            ->label('Edit Name')
                            ->tooltip('Click to edit product name')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Product Name')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->fillForm(fn (ConnectedProduct $record) => [
                                'name' => $record->name,
                            ])
                            ->action(function (ConnectedProduct $record, array $data) {
                                $record->update(['name' => $data['name']]);
                                
                                Notification::make()
                                    ->success()
                                    ->title('Product name updated')
                                    ->body('The product name has been updated successfully.')
                                    ->send();
                            })
                    ),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                ActionableColumn::make('active')
                    ->label('Active')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->sortable()
                    ->actionIcon(Heroicon::CheckCircle)
                    ->actionIconColor('success')
                    ->clickableColumn()
                    ->tapAction(
                        Action::make('toggleActive')
                            ->label('Toggle Active Status')
                            ->tooltip('Click to toggle active status')
                            ->schema([
                                Toggle::make('active')
                                    ->label('Active')
                                    ->helperText('Active products are visible in Stripe'),
                            ])
                            ->fillForm(fn (ConnectedProduct $record) => [
                                'active' => $record->active,
                            ])
                            ->action(function (ConnectedProduct $record, array $data) {
                                $record->update(['active' => $data['active']]);
                                
                                Notification::make()
                                    ->success()
                                    ->title('Product status updated')
                                    ->body('The product active status has been updated successfully.')
                                    ->send();
                            })
                    ),

                // Pricing Group
                ActionableColumn::make('price')
                    ->label('Price')
                    ->badge()
                    ->color('success')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(CAST(price AS NUMERIC), 0) {$direction}");
                    })
                    ->formatStateUsing(function ($state, ConnectedProduct $record) {
                        if (!$state) {
                            // Try to get from default_price
                            if ($record->default_price && $record->stripe_account_id) {
                                $defaultPrice = \App\Models\ConnectedPrice::where('stripe_price_id', $record->default_price)
                                    ->where('stripe_account_id', $record->stripe_account_id)
                                    ->first();
                                
                                if ($defaultPrice && $defaultPrice->unit_amount) {
                                    $currency = strtoupper($defaultPrice->currency ?? 'NOK');
                                    return number_format($defaultPrice->unit_amount / 100, 2, '.', '') . ' ' . $currency;
                                }
                            }
                            return '-';
                        }
                        
                        $currency = strtoupper($record->currency ?? 'NOK');
                        // Price is already in decimal format from the accessor
                        return number_format((float) $state, 2, '.', '') . ' ' . $currency;
                    })
                    ->toggleable()
                    ->actionIcon(Heroicon::CurrencyDollar)
                    ->actionIconColor('success')
                    ->clickableColumn()
                    ->tapAction(
                        Action::make('editPrice')
                            ->label('Edit Price')
                            ->tooltip('Click to edit product price')
                            ->schema([
                                TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix(fn ($get, $record) => strtoupper($get('currency') ?? ($record?->currency ?? 'NOK')))
                                    ->helperText('Enter the product price. This will create or update the Stripe price automatically.'),
                                
                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'nok' => 'NOK (kr)',
                                        'usd' => 'USD ($)',
                                        'eur' => 'EUR (€)',
                                        'sek' => 'SEK',
                                        'dkk' => 'DKK',
                                    ])
                                    ->helperText('Currency for this product'),
                            ])
                            ->fillForm(function (ConnectedProduct $record) {
                                // Get current price value
                                $price = $record->price;
                                if (!$price && $record->default_price && $record->stripe_account_id) {
                                    $defaultPrice = \App\Models\ConnectedPrice::where('stripe_price_id', $record->default_price)
                                        ->where('stripe_account_id', $record->stripe_account_id)
                                        ->first();
                                    
                                    if ($defaultPrice && $defaultPrice->unit_amount) {
                                        $price = number_format($defaultPrice->unit_amount / 100, 2, '.', '');
                                    }
                                }
                                
                                return [
                                    'price' => $price,
                                    'currency' => $record->currency ?: 'nok',
                                ];
                            })
                            ->action(function (ConnectedProduct $record, array $data) {
                                $updates = [];
                                
                                // Convert price to string format for storage (handle empty values)
                                if (isset($data['price']) && $data['price'] !== null && $data['price'] !== '') {
                                    $updates['price'] = str_replace(',', '.', str_replace(' ', '', (string) $data['price']));
                                } elseif (isset($data['price']) && ($data['price'] === null || $data['price'] === '')) {
                                    // Allow clearing the price
                                    $updates['price'] = null;
                                }
                                
                                if (isset($data['currency'])) {
                                    $updates['currency'] = $data['currency'];
                                }
                                
                                if (!empty($updates)) {
                                    $record->update($updates);
                                    
                                    Notification::make()
                                        ->success()
                                        ->title('Product price updated')
                                        ->body('The product price has been updated successfully.')
                                        ->send();
                                }
                            })
                    ),

                TextColumn::make('compare_at_price_amount')
                    ->label('Compare at Price')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->formatStateUsing(function ($state, ConnectedProduct $record) {
                        if (!$state) {
                            return '-';
                        }
                        $currency = strtoupper($record->currency ?? 'NOK');
                        return number_format($state / 100, 2, '.', '') . ' ' . $currency;
                    })
                    ->toggleable(),

                TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->badge()
                    ->color('danger')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('compare_at_price_amount', $direction);
                    })
                    ->formatStateUsing(function ($state) {
                        return $state ? $state . '%' : '-';
                    })
                    ->toggleable(),

                // Product Details Group
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?: '0')
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Service')
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->url(fn (ConnectedProduct $record) => $record->vendor
                        ? \App\Filament\Resources\Vendors\VendorResource::getUrl('edit', ['record' => $record->vendor])
                        : null)
                    ->placeholder('No vendor')
                    ->toggleable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->url(fn (ConnectedProduct $record) => $record->store
                        ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                        : null)
                    ->toggleable(),

                // Additional Information Group
                ActionableColumn::make('product_code')
                    ->label('Product Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('-')
                    ->actionIcon(Heroicon::Hashtag)
                    ->actionIconColor('gray')
                    ->clickableColumn()
                    ->tapAction(
                        Action::make('editProductCode')
                            ->label('Edit Product Code')
                            ->tooltip('Click to edit product code (PLU)')
                            ->schema([
                                TextInput::make('product_code')
                                    ->label('Product Code (PLU)')
                                    ->maxLength(50)
                                    ->helperText('PLU code (BasicType-02)'),
                            ])
                            ->fillForm(fn (ConnectedProduct $record) => [
                                'product_code' => $record->product_code,
                            ])
                            ->action(function (ConnectedProduct $record, array $data) {
                                $record->update(['product_code' => $data['product_code'] ?? null]);
                                
                                Notification::make()
                                    ->success()
                                    ->title('Product code updated')
                                    ->body('The product code has been updated successfully.')
                                    ->send();
                            })
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('article_group_code')
                    ->label('Article Group')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('shippable')
                    ->label('Shippable')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('no_price_in_pos')
                    ->label('No Price in POS')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_product_id')
                    ->label('Product ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Status Filters
                TernaryFilter::make('active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('shippable')
                    ->label('Shippable')
                    ->placeholder('All')
                    ->trueLabel('Shippable only')
                    ->falseLabel('Not shippable'),

                TernaryFilter::make('no_price_in_pos')
                    ->label('No Price in POS')
                    ->placeholder('All')
                    ->trueLabel('No price in POS')
                    ->falseLabel('Has price in POS'),

                // Relationship Filters
                SelectFilter::make('stripe_account_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name', modifyQueryUsing: function ($query) {
                        try {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            if ($tenant && $tenant->slug !== 'visivo-admin' && $tenant->stripe_account_id) {
                                return $query->where('stripe_account_id', $tenant->stripe_account_id)
                                    ->where('active', true);
                            }
                        } catch (\Throwable $e) {
                            // Fallback if Filament facade not available
                        }
                        return $query->where('active', true);
                    })
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('has_variants')
                    ->label('Has Variants')
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['has_variants']) || $data['has_variants'] === null) {
                            return $query;
                        }

                        return $data['has_variants']
                            ? $query->has('variants')
                            : $query->doesntHave('variants');
                    })
                    ->form([
                        Select::make('has_variants')
                            ->label('Variants')
                            ->options([
                                true => 'Has variants',
                                false => 'No variants',
                            ])
                            ->placeholder('All'),
                    ]),

                // Product Details Filters
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'service' => 'Service',
                        'good' => 'Good',
                    ])
                    ->multiple(),

                Filter::make('price_range')
                    ->label('Price Range')
                    ->form([
                        TextInput::make('price_from')
                            ->label('From')
                            ->numeric()
                            ->prefix('NOK'),
                        TextInput::make('price_to')
                            ->label('To')
                            ->numeric()
                            ->prefix('NOK'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('price', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('price', '<=', $price),
                            );
                    }),

                Filter::make('has_discount')
                    ->label('Has Discount')
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['has_discount']) || $data['has_discount'] === null) {
                            return $query;
                        }

                        return $data['has_discount']
                            ? $query->whereNotNull('compare_at_price_amount')
                                ->where('compare_at_price_amount', '>', 0)
                            : $query->where(function (Builder $query) {
                                $query->whereNull('compare_at_price_amount')
                                    ->orWhere('compare_at_price_amount', '<=', 0);
                            });
                    })
                    ->form([
                        Select::make('has_discount')
                            ->label('Discount')
                            ->options([
                                true => 'Has discount',
                                false => 'No discount',
                            ])
                            ->placeholder('All'),
                    ]),

                // Date Filters
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Created from'),
                        DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->persistColumnSearchesInSession()
            ->persistSearchInSession()
            ->deferLoading()
            ->poll('30s')
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkEdit')
                        ->label('Bulk Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->form(function (Collection $records) {
                            $count = $records->count();
                            
                            return [
                                // Info text
                                TextInput::make('info')
                                    ->label('Selected Products')
                                    ->default("You are editing {$count} product(s). Only fields you enable will be updated.")
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                // Status & Visibility
                                TextInput::make('status_section_label')
                                    ->label('Status & Visibility')
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_active')
                                    ->label('Update Active Status')
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('active')
                                    ->label('Active')
                                    ->default(true)
                                    ->disabled(fn ($get) => !$get('update_active'))
                                    ->dehydrated(fn ($get) => $get('update_active'))
                                    ->columnSpan(1),

                                Checkbox::make('update_shippable')
                                    ->label('Update Shippable')
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('shippable')
                                    ->label('Shippable')
                                    ->default(false)
                                    ->disabled(fn ($get) => !$get('update_shippable'))
                                    ->dehydrated(fn ($get) => $get('update_shippable'))
                                    ->columnSpan(1),

                                Checkbox::make('update_no_price_in_pos')
                                    ->label('Update No Price in POS')
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('no_price_in_pos')
                                    ->label('No Price in POS')
                                    ->default(false)
                                    ->disabled(fn ($get) => !$get('update_no_price_in_pos'))
                                    ->dehydrated(fn ($get) => $get('update_no_price_in_pos'))
                                    ->columnSpan(1),

                                // Product Details
                                TextInput::make('product_details_section_label')
                                    ->label('Product Details')
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_type')
                                    ->label('Update Product Type')
                                    ->live()
                                    ->columnSpan(1),

                                Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        'service' => 'Service',
                                        'good' => 'Good',
                                    ])
                                    ->disabled(fn ($get) => !$get('update_type'))
                                    ->dehydrated(fn ($get) => $get('update_type'))
                                    ->columnSpan(1),

                                Checkbox::make('update_vendor')
                                    ->label('Update Vendor')
                                    ->live()
                                    ->columnSpan(1),

                                Select::make('vendor_id')
                                    ->label('Vendor')
                                    ->options(function () {
                                        try {
                                            $tenant = \Filament\Facades\Filament::getTenant();
                                            $stripeAccountId = $tenant?->stripe_account_id;
                                            
                                            if (!$stripeAccountId) {
                                                return [];
                                            }

                                            return Vendor::where('stripe_account_id', $stripeAccountId)
                                                ->where('active', true)
                                                ->orderBy('name', 'asc')
                                                ->pluck('name', 'id');
                                        } catch (\Throwable $e) {
                                            return [];
                                        }
                                    })
                                    ->searchable()
                                    ->placeholder('No vendor')
                                    ->disabled(fn ($get) => !$get('update_vendor'))
                                    ->dehydrated(fn ($get) => $get('update_vendor'))
                                    ->columnSpan(1),

                                Checkbox::make('update_product_code')
                                    ->label('Update Product Code')
                                    ->live()
                                    ->columnSpan(1),

                                TextInput::make('product_code')
                                    ->label('Product Code (PLU)')
                                    ->maxLength(50)
                                    ->disabled(fn ($get) => !$get('update_product_code'))
                                    ->dehydrated(fn ($get) => $get('update_product_code'))
                                    ->columnSpan(1),

                                Checkbox::make('update_article_group_code')
                                    ->label('Update Article Group Code')
                                    ->live()
                                    ->columnSpan(1),

                                Select::make('article_group_code')
                                    ->label('Article Group Code (SAF-T)')
                                    ->relationship(
                                        'articleGroupCode',
                                        'name',
                                        modifyQueryUsing: function ($query) {
                                            // Get tenant's stripe_account_id
                                            $stripeAccountId = null;
                                            try {
                                                $tenant = \Filament\Facades\Filament::getTenant();
                                                $stripeAccountId = $tenant?->stripe_account_id;
                                            } catch (\Throwable $e) {
                                                // Fallback
                                            }

                                            // Show store-specific codes and global standard codes
                                            if ($stripeAccountId) {
                                                return $query->where(function ($q) use ($stripeAccountId) {
                                                    $q->where('stripe_account_id', $stripeAccountId)
                                                      ->orWhere(function ($q2) {
                                                          $q2->whereNull('stripe_account_id')
                                                             ->where('is_standard', true);
                                                      });
                                                })
                                                ->where('active', true)
                                                ->orderBy('sort_order', 'asc')
                                                ->orderBy('code', 'asc');
                                            }

                                            // If no stripe_account_id, return global standard codes
                                            return $query->whereNull('stripe_account_id')
                                                ->where('is_standard', true)
                                                ->where('active', true)
                                                ->orderBy('sort_order', 'asc')
                                                ->orderBy('code', 'asc');
                                        }
                                    )
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return $record->code . ' - ' . $record->name;
                                    })
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->placeholder('Select article group')
                                    ->disabled(fn ($get) => !$get('update_article_group_code'))
                                    ->dehydrated(fn ($get) => $get('update_article_group_code'))
                                    ->columnSpan(1),

                                // Pricing
                                TextInput::make('pricing_section_label')
                                    ->label('Pricing')
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_price')
                                    ->label('Update Price')
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix('kr')
                                    ->disabled(fn ($get) => !$get('update_price'))
                                    ->dehydrated(fn ($get) => $get('update_price'))
                                    ->columnSpan(1)
                                    ->visible(fn ($get) => $get('update_price')),

                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'nok' => 'NOK (kr)',
                                        'usd' => 'USD ($)',
                                        'eur' => 'EUR (€)',
                                        'sek' => 'SEK',
                                        'dkk' => 'DKK',
                                    ])
                                    ->default('nok')
                                    ->disabled(fn ($get) => !$get('update_price'))
                                    ->dehydrated(fn ($get) => $get('update_price'))
                                    ->columnSpan(1)
                                    ->visible(fn ($get) => $get('update_price')),

                                Checkbox::make('update_compare_at_price')
                                    ->label('Update Compare at Price')
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('compare_at_price_decimal')
                                    ->label('Compare at Price')
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix('kr')
                                    ->helperText('Leave empty to clear compare at price')
                                    ->disabled(fn ($get) => !$get('update_compare_at_price'))
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($state, $set) {
                                        if ($state !== null && $state !== '') {
                                            $set('compare_at_price_amount', (int) round($state * 100));
                                        } else {
                                            $set('compare_at_price_amount', null);
                                        }
                                    })
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('update_compare_at_price')),

                                Checkbox::make('clear_compare_at_price')
                                    ->label('Clear compare at price for all products')
                                    ->helperText('Check this to remove compare at price from all selected products')
                                    ->disabled(fn ($get) => !$get('update_compare_at_price'))
                                    ->dehydrated(fn ($get) => $get('update_compare_at_price') && $get('clear_compare_at_price'))
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('update_compare_at_price')),

                                // Collections
                                TextInput::make('collections_section_label')
                                    ->label('Collections')
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_collections')
                                    ->label('Update Collections')
                                    ->live()
                                    ->columnSpanFull(),

                                CheckboxList::make('collections')
                                    ->label('Collections')
                                    ->options(function () {
                                        try {
                                            $tenant = \Filament\Facades\Filament::getTenant();
                                            $stripeAccountId = $tenant?->stripe_account_id;
                                            
                                            if ($stripeAccountId) {
                                                return \App\Models\Collection::where('stripe_account_id', $stripeAccountId)
                                                    ->orderBy('name', 'asc')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            }
                                        } catch (\Throwable $e) {
                                            // Fallback
                                        }
                                        return [];
                                    })
                                    ->searchable()
                                    ->helperText('Select collections to add to all products. Existing collections will be preserved unless you use "Replace Collections" mode.')
                                    ->disabled(fn ($get) => !$get('update_collections'))
                                    ->dehydrated(fn ($get) => $get('update_collections'))
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('update_collections')),

                                Checkbox::make('replace_collections')
                                    ->label('Replace existing collections')
                                    ->helperText('If checked, selected products will only have the collections you select above. If unchecked, collections will be added to existing ones.')
                                    ->disabled(fn ($get) => !$get('update_collections'))
                                    ->dehydrated(fn ($get) => $get('update_collections'))
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('update_collections')),

                                // Clear Fields
                                TextInput::make('clear_fields_section_label')
                                    ->label('Clear Fields')
                                    ->default('Use these options to clear specific fields from all selected products')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('clear_vendor')
                                    ->label('Clear vendor (set to no vendor)')
                                    ->helperText('Remove vendor assignment from all selected products')
                                    ->columnSpanFull(),

                                Checkbox::make('clear_product_code')
                                    ->label('Clear product code')
                                    ->helperText('Remove product code from all selected products')
                                    ->columnSpanFull(),

                                Checkbox::make('clear_article_group_code')
                                    ->label('Clear article group code')
                                    ->helperText('Remove article group code from all selected products')
                                    ->columnSpanFull(),
                            ];
                        })
                        ->action(function (Collection $records, array $data): void {
                            $updates = [];
                            $updatedCount = 0;

                            // Status & Visibility
                            if (!empty($data['update_active'])) {
                                $updates['active'] = (bool) ($data['active'] ?? false);
                            }

                            if (!empty($data['update_shippable'])) {
                                $updates['shippable'] = (bool) ($data['shippable'] ?? false);
                            }

                            if (!empty($data['update_no_price_in_pos'])) {
                                $updates['no_price_in_pos'] = (bool) ($data['no_price_in_pos'] ?? false);
                            }

                            // Product Details
                            if (!empty($data['update_type']) && isset($data['type'])) {
                                $updates['type'] = $data['type'];
                            }

                            if (!empty($data['update_vendor'])) {
                                if (!empty($data['clear_vendor'])) {
                                    $updates['vendor_id'] = null;
                                } elseif (isset($data['vendor_id'])) {
                                    $updates['vendor_id'] = $data['vendor_id'];
                                }
                            }

                            if (!empty($data['update_product_code'])) {
                                if (!empty($data['clear_product_code'])) {
                                    $updates['product_code'] = null;
                                } elseif (isset($data['product_code'])) {
                                    $updates['product_code'] = $data['product_code'];
                                }
                            }

                            if (!empty($data['update_article_group_code'])) {
                                if (!empty($data['clear_article_group_code'])) {
                                    $updates['article_group_code'] = null;
                                } elseif (isset($data['article_group_code'])) {
                                    $updates['article_group_code'] = $data['article_group_code'];
                                }
                            }

                            // Pricing
                            if (!empty($data['update_price'])) {
                                if (isset($data['price']) && $data['price'] !== null && $data['price'] !== '') {
                                    $updates['price'] = str_replace(',', '.', str_replace(' ', '', (string) $data['price']));
                                }
                                if (isset($data['currency'])) {
                                    $updates['currency'] = $data['currency'];
                                }
                            }

                            if (!empty($data['update_compare_at_price'])) {
                                if (!empty($data['clear_compare_at_price'])) {
                                    $updates['compare_at_price_amount'] = null;
                                } elseif (isset($data['compare_at_price_decimal']) && $data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                                    $updates['compare_at_price_amount'] = (int) round((float) $data['compare_at_price_decimal'] * 100);
                                } elseif (isset($data['compare_at_price_amount'])) {
                                    $updates['compare_at_price_amount'] = $data['compare_at_price_amount'];
                                }
                            }

                            // Apply updates
                            if (!empty($updates)) {
                                $updatedCount = ConnectedProduct::whereIn('id', $records->pluck('id'))
                                    ->update($updates);
                            }

                            // Handle collections
                            if (!empty($data['update_collections']) && isset($data['collections'])) {
                                $collectionIds = is_array($data['collections']) ? $data['collections'] : [];
                                $replaceMode = !empty($data['replace_collections']);

                                foreach ($records as $product) {
                                    if ($replaceMode) {
                                        // Replace all collections
                                        $product->collections()->sync($collectionIds);
                                    } else {
                                        // Add to existing collections
                                        $product->collections()->syncWithoutDetaching($collectionIds);
                                    }
                                }
                            }

                            // Show notification
                            if ($updatedCount > 0 || !empty($data['update_collections'])) {
                                $message = "Successfully updated {$updatedCount} product(s)";
                                if (!empty($data['update_collections'])) {
                                    $message .= " and updated collections";
                                }

                                Notification::make()
                                    ->success()
                                    ->title('Bulk edit completed')
                                    ->body($message)
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('No changes made')
                                    ->body('Please enable at least one field to update.')
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading('Bulk Edit Products')
                        ->modalDescription('Select which fields to update for the selected products')
                        ->modalSubmitActionLabel('Apply Changes')
                        ->modalWidth('4xl'),

                    BulkAction::make('setVendor')
                        ->label('Set Vendor')
                        ->icon('heroicon-o-building-storefront')
                        ->color('info')
                        ->form([
                            Select::make('vendor_id')
                                ->label('Vendor')
                                ->options(function () {
                                    // Get stripe_account_id from tenant (all products in list are from same tenant)
                                    try {
                                        $tenant = \Filament\Facades\Filament::getTenant();
                                        $stripeAccountId = $tenant?->stripe_account_id;
                                        
                                        if (!$stripeAccountId) {
                                            return [];
                                        }

                                        return Vendor::where('stripe_account_id', $stripeAccountId)
                                            ->where('active', true)
                                            ->orderBy('name', 'asc')
                                            ->pluck('name', 'id');
                                    } catch (\Throwable $e) {
                                        // Fallback if Filament facade not available
                                        return [];
                                    }
                                })
                                ->searchable()
                                ->placeholder('Select a vendor')
                                ->helperText('Select the vendor to assign to all selected products')
                                ->required()
                                ->live(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $vendorId = $data['vendor_id'] ?? null;
                            
                            if (!$vendorId) {
                                Notification::make()
                                    ->danger()
                                    ->title('Vendor selection required')
                                    ->body('Please select a vendor to assign.')
                                    ->send();
                                return;
                            }

                            $updated = ConnectedProduct::whereIn('id', $records->pluck('id'))
                                ->update(['vendor_id' => $vendorId]);

                            Notification::make()
                                ->success()
                                ->title('Vendor assigned')
                                ->body("Vendor has been assigned to {$updated} product(s).")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Vendor assigned successfully'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
