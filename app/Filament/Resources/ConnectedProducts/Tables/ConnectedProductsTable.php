<?php

namespace App\Filament\Resources\ConnectedProducts\Tables;

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Actions\ConnectedProducts\ResolveProductVatRate;
use App\Actions\ConnectedProducts\UpdateConnectedProductToStripe;
use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\ArticleGroupCode;
use App\Models\ConnectedProduct;
use App\Models\Vendor;
use App\Services\ProductZipExporter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ConnectedProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'prices', 'variants', 'vendor', 'media']))
            ->columns([
                ImageColumn::make('image')
                    ->label(__('filament.connected_products.table.image'))
                    ->getStateUsing(function (ConnectedProduct $record): ?string {
                        $url = $record->getFirstMediaUrl('images');
                        if ($url !== '') {
                            return $url;
                        }
                        $images = $record->images;
                        if (is_array($images) && isset($images[0]) && $images[0] !== '') {
                            return $images[0];
                        }

                        return null;
                    })
                    ->square()
                    ->imageSize(40)
                    ->toggleable(isToggledHiddenByDefault: false),

                // Primary Information Group
                TextColumn::make('name')
                    ->label(__('Name'))
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
                    ->placeholder(__('-'))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('vendor.name')
                    ->label(__('filament.connected_products.table.brand'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (ConnectedProduct $record) => $record->vendor
                        ? \App\Filament\Resources\Vendors\VendorResource::getUrl('edit', ['record' => $record->vendor])
                        : null)
                    ->placeholder(__('—'))
                    ->toggleable(),

                TextColumn::make('description')
                    ->label(__('Description'))
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('active')
                    ->label(__('filament.connected_products.table.visibility'))
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                // Pricing Group
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(CAST(price AS NUMERIC), 0) {$direction}");
                    })
                    ->formatStateUsing(function ($state, ConnectedProduct $record) {
                        // Prefer stored price column (source of truth); then fall back to default_price
                        $stored = $record->getRawOriginal('price');
                        if ($stored !== null && $stored !== '') {
                            $currency = strtoupper($record->getRawOriginal('currency') ?: $record->currency ?? 'NOK');

                            return number_format((float) str_replace(',', '.', (string) $stored), 2, '.', '').' '.$currency;
                        }
                        if ($record->default_price && $record->stripe_account_id) {
                            $defaultPrice = \App\Models\ConnectedPrice::where('stripe_price_id', $record->default_price)
                                ->where('stripe_account_id', $record->stripe_account_id)
                                ->first();
                            if ($defaultPrice && $defaultPrice->unit_amount) {
                                $currency = strtoupper($defaultPrice->currency ?? 'NOK');

                                return number_format($defaultPrice->unit_amount / 100, 2, '.', '').' '.$currency;
                            }
                        }

                        return $state ? (number_format((float) $state, 2, '.', '').' '.strtoupper($record->currency ?? 'NOK')) : '-';
                    })
                    ->alignment(Alignment::End)
                    ->toggleable(),

                TextColumn::make('product_code')
                    ->label(__('filament.connected_products.table.sku'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder(__('—'))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('inventory_total')
                    ->label(__('filament.connected_products.table.stock'))
                    ->alignment(Alignment::End)
                    ->state(function (ConnectedProduct $record): string {
                        $variants = $record->variants;
                        if ($variants->isEmpty()) {
                            return '—';
                        }

                        return (string) $variants->sum('inventory_quantity');
                    })
                    ->visible(fn (): bool => Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Inventory))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('compare_at_price_amount')
                    ->label(__('Compare at Price'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->formatStateUsing(function ($state, ConnectedProduct $record) {
                        if (! $state) {
                            return '-';
                        }
                        $currency = strtoupper($record->currency ?? 'NOK');

                        return number_format($state / 100, 2, '.', '').' '.$currency;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('discount_percentage')
                    ->label(__('Discount'))
                    ->badge()
                    ->color('danger')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('compare_at_price_amount', $direction);
                    })
                    ->formatStateUsing(function ($state) {
                        return $state ? $state.'%' : '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Product Details Group
                TextColumn::make('variants_count')
                    ->label(__('Variants'))
                    ->counts('variants')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?: '0')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Service')
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->url(fn (ConnectedProduct $record) => $record->store
                        ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                // Additional Information Group
                TextColumn::make('article_group_code')
                    ->label(__('Article Group'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder(__('-'))
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('shippable')
                    ->label(__('Shippable'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('no_price_in_pos')
                    ->label(__('No Price in POS'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_product_id')
                    ->label(__('Product ID'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Status Filters
                TernaryFilter::make('active')
                    ->label(__('Active'))
                    ->placeholder(__('All'))
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('shippable')
                    ->label(__('Shippable'))
                    ->placeholder(__('All'))
                    ->trueLabel('Shippable only')
                    ->falseLabel('Not shippable'),

                TernaryFilter::make('no_price_in_pos')
                    ->label(__('No Price in POS'))
                    ->placeholder(__('All'))
                    ->trueLabel('No price in POS')
                    ->falseLabel('Has price in POS'),

                // Relationship Filters
                SelectFilter::make('stripe_account_id')
                    ->label(__('Store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('vendor_id')
                    ->label(__('Vendor'))
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
                    ->label(__('Has Variants'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (! isset($data['has_variants']) || $data['has_variants'] === null) {
                            return $query;
                        }

                        return $data['has_variants']
                            ? $query->has('variants')
                            : $query->doesntHave('variants');
                    })
                    ->form([
                        Select::make('has_variants')
                            ->label(__('Variants'))
                            ->options([
                                true => 'Has variants',
                                false => 'No variants',
                            ])
                            ->placeholder(__('All')),
                    ]),

                // Product Details Filters
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options([
                        'service' => 'Service',
                        'good' => 'Good',
                    ])
                    ->multiple(),

                Filter::make('price_range')
                    ->label(__('Price Range'))
                    ->form([
                        TextInput::make('price_from')
                            ->label(__('From'))
                            ->numeric()
                            ->prefix(__('NOK')),
                        TextInput::make('price_to')
                            ->label(__('To'))
                            ->numeric()
                            ->prefix(__('NOK')),
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
                    ->label(__('Has Discount'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (! isset($data['has_discount']) || $data['has_discount'] === null) {
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
                            ->label(__('Discount'))
                            ->options([
                                true => 'Has discount',
                                false => 'No discount',
                            ])
                            ->placeholder(__('All')),
                    ]),

                // Date Filters
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label(__('Created from')),
                        DatePicker::make('created_until')
                            ->label(__('Created until')),
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
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('toggleActive')
                        ->label(fn (ConnectedProduct $record): string => $record->active
                            ? __('filament.connected_products.actions.deactivate')
                            : __('filament.connected_products.actions.activate'))
                        ->icon(fn (ConnectedProduct $record) => $record->active
                            ? Heroicon::EyeSlash
                            : Heroicon::Eye)
                        ->color('gray')
                        ->schema([
                            Toggle::make('active')
                                ->label(__('Active'))
                                ->helperText(__('Active products are visible in Stripe')),
                        ])
                        ->fillForm(fn (ConnectedProduct $record): array => [
                            'active' => $record->active,
                        ])
                        ->action(function (ConnectedProduct $record, array $data): void {
                            $record->update(['active' => (bool) $data['active']]);

                            Notification::make()
                                ->success()
                                ->title(__('filament.connected_products.notifications.status_updated_title'))
                                ->body(__('filament.connected_products.notifications.status_updated_body'))
                                ->send();
                        }),
                ])
                    ->iconButton()
                    ->icon(Heroicon::EllipsisVertical)
                    ->tooltip(__('filament.connected_products.actions.row')),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->persistColumnSearchesInSession()
            ->persistSearchInSession()
            ->deferLoading()
            ->poll('30s')
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkEdit')
                        ->label(__('Bulk Edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->form(function (Collection $records) {
                            $count = $records->count();

                            return [
                                // Info text
                                TextInput::make('info')
                                    ->label(__('Selected Products'))
                                    ->default("You are editing {$count} product(s). Only fields you enable will be updated.")
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                // Status & Visibility
                                TextInput::make('status_section_label')
                                    ->label(__('Status & Visibility'))
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_active')
                                    ->label(__('Update Active Status'))
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('active')
                                    ->label(__('Active'))
                                    ->default(true)
                                    ->disabled(fn ($get) => ! $get('update_active'))
                                    ->dehydrated(fn ($get) => $get('update_active'))
                                    ->columnSpan(1),

                                Checkbox::make('update_shippable')
                                    ->label(__('Update Shippable'))
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('shippable')
                                    ->label(__('Shippable'))
                                    ->default(false)
                                    ->disabled(fn ($get) => ! $get('update_shippable'))
                                    ->dehydrated(fn ($get) => $get('update_shippable'))
                                    ->columnSpan(1),

                                Checkbox::make('update_no_price_in_pos')
                                    ->label(__('Update No Price in POS'))
                                    ->live()
                                    ->columnSpan(1),

                                Toggle::make('no_price_in_pos')
                                    ->label(__('No Price in POS'))
                                    ->default(false)
                                    ->disabled(fn ($get) => ! $get('update_no_price_in_pos'))
                                    ->dehydrated(fn ($get) => $get('update_no_price_in_pos'))
                                    ->columnSpan(1),

                                // Product Details
                                TextInput::make('product_details_section_label')
                                    ->label(__('Product Details'))
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_type')
                                    ->label(__('Update Product Type'))
                                    ->live()
                                    ->columnSpan(1),

                                Select::make('type')
                                    ->label(__('Type'))
                                    ->options([
                                        'service' => 'Service',
                                        'good' => 'Good',
                                    ])
                                    ->disabled(fn ($get) => ! $get('update_type'))
                                    ->dehydrated(fn ($get) => $get('update_type'))
                                    ->columnSpan(1),

                                Checkbox::make('update_vendor')
                                    ->label(__('Update Vendor'))
                                    ->live()
                                    ->columnSpan(1),

                                Select::make('vendor_id')
                                    ->label(__('Vendor'))
                                    ->options(function () {
                                        try {
                                            $tenant = \Filament\Facades\Filament::getTenant();
                                            $stripeAccountId = $tenant?->stripe_account_id;

                                            if (! $stripeAccountId) {
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
                                    ->placeholder(__('No vendor'))
                                    ->disabled(fn ($get) => ! $get('update_vendor'))
                                    ->dehydrated(fn ($get) => $get('update_vendor'))
                                    ->columnSpan(1),

                                Checkbox::make('update_product_code')
                                    ->label(__('Update Product Code'))
                                    ->live()
                                    ->columnSpan(1),

                                TextInput::make('product_code')
                                    ->label(__('Product Code (PLU)'))
                                    ->maxLength(50)
                                    ->disabled(fn ($get) => ! $get('update_product_code'))
                                    ->dehydrated(fn ($get) => $get('update_product_code'))
                                    ->columnSpan(1),

                                Checkbox::make('update_article_group_code')
                                    ->label(__('Update Article Group Code'))
                                    ->live()
                                    ->columnSpan(1),

                                Select::make('article_group_code')
                                    ->label(__('Article Group Code (SAF-T)'))
                                    ->options(function () {
                                        // Get tenant's stripe_account_id
                                        $stripeAccountId = null;
                                        try {
                                            $tenant = \Filament\Facades\Filament::getTenant();
                                            $stripeAccountId = $tenant?->stripe_account_id;
                                        } catch (\Throwable $e) {
                                            // Fallback
                                        }

                                        $query = \App\Models\ArticleGroupCode::query();

                                        // Show store-specific codes and global standard codes
                                        if ($stripeAccountId) {
                                            $query->where(function ($q) use ($stripeAccountId) {
                                                $q->where('stripe_account_id', $stripeAccountId)
                                                    ->orWhere(function ($q2) {
                                                        $q2->whereNull('stripe_account_id')
                                                            ->where('is_standard', true);
                                                    });
                                            });
                                        } else {
                                            // If no stripe_account_id, return global standard codes
                                            $query->whereNull('stripe_account_id')
                                                ->where('is_standard', true);
                                        }

                                        return $query->where('active', true)
                                            ->orderBy('sort_order', 'asc')
                                            ->orderBy('code', 'asc')
                                            ->get()
                                            ->mapWithKeys(function ($record) {
                                                return [$record->code => $record->code.' - '.$record->name];
                                            });
                                    })
                                    ->getSearchResultsUsing(function (string $search) {
                                        // Get tenant's stripe_account_id
                                        $stripeAccountId = null;
                                        try {
                                            $tenant = \Filament\Facades\Filament::getTenant();
                                            $stripeAccountId = $tenant?->stripe_account_id;
                                        } catch (\Throwable $e) {
                                            // Fallback
                                        }

                                        $query = \App\Models\ArticleGroupCode::query()
                                            ->where(function ($q) use ($search) {
                                                $q->where('code', 'like', "%{$search}%")
                                                    ->orWhere('name', 'like', "%{$search}%");
                                            });

                                        // Show store-specific codes and global standard codes
                                        if ($stripeAccountId) {
                                            $query->where(function ($q) use ($stripeAccountId) {
                                                $q->where('stripe_account_id', $stripeAccountId)
                                                    ->orWhere(function ($q2) {
                                                        $q2->whereNull('stripe_account_id')
                                                            ->where('is_standard', true);
                                                    });
                                            });
                                        } else {
                                            // If no stripe_account_id, return global standard codes
                                            $query->whereNull('stripe_account_id')
                                                ->where('is_standard', true);
                                        }

                                        return $query->where('active', true)
                                            ->orderBy('sort_order', 'asc')
                                            ->orderBy('code', 'asc')
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($record) {
                                                return [$record->code => $record->code.' - '.$record->name];
                                            });
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $code = \App\Models\ArticleGroupCode::where('code', $value)->first();

                                        return $code ? $code->code.' - '.$code->name : $value;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->placeholder(__('Select article group'))
                                    ->disabled(fn ($get) => ! $get('update_article_group_code'))
                                    ->dehydrated(fn ($get) => $get('update_article_group_code'))
                                    ->columnSpan(1),

                                // Pricing
                                TextInput::make('pricing_section_label')
                                    ->label(__('Pricing'))
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_price')
                                    ->label(__('Update Price'))
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('price')
                                    ->label(__('Price'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix(__('kr'))
                                    ->disabled(fn ($get) => ! $get('update_price'))
                                    ->dehydrated(fn ($get) => $get('update_price'))
                                    ->columnSpan(1)
                                    ->visible(fn ($get) => $get('update_price')),

                                Select::make('currency')
                                    ->label(__('Currency'))
                                    ->options([
                                        'nok' => 'NOK (kr)',
                                        'usd' => 'USD ($)',
                                        'eur' => 'EUR (€)',
                                        'sek' => 'SEK',
                                        'dkk' => 'DKK',
                                    ])
                                    ->default('nok')
                                    ->disabled(fn ($get) => ! $get('update_price'))
                                    ->dehydrated(fn ($get) => $get('update_price'))
                                    ->columnSpan(1)
                                    ->visible(fn ($get) => $get('update_price')),

                                Checkbox::make('update_compare_at_price')
                                    ->label(__('Update Compare at Price'))
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('compare_at_price_decimal')
                                    ->label(__('Compare at Price'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix(__('kr'))
                                    ->helperText(__('Leave empty to clear compare at price'))
                                    ->disabled(fn ($get) => ! $get('update_compare_at_price'))
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
                                    ->label(__('Clear compare at price for all products'))
                                    ->helperText(__('Check this to remove compare at price from all selected products'))
                                    ->disabled(fn ($get) => ! $get('update_compare_at_price'))
                                    ->dehydrated(fn ($get) => $get('update_compare_at_price') && $get('clear_compare_at_price'))
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('update_compare_at_price')),

                                // Collections
                                TextInput::make('collections_section_label')
                                    ->label(__('Collections'))
                                    ->default('')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('update_collections')
                                    ->label(__('Update Collections'))
                                    ->live()
                                    ->columnSpanFull(),

                                CheckboxList::make('collections')
                                    ->label(__('Collections'))
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
                                    ->helperText(__('Select collections to add to all products. Existing collections will be preserved unless you use "Replace Collections" mode.'))
                                    ->disabled(fn ($get) => ! $get('update_collections'))
                                    ->dehydrated(fn ($get) => $get('update_collections'))
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('update_collections')),

                                Checkbox::make('replace_collections')
                                    ->label(__('Replace existing collections'))
                                    ->helperText(__('If checked, selected products will only have the collections you select above. If unchecked, collections will be added to existing ones.'))
                                    ->disabled(fn ($get) => ! $get('update_collections'))
                                    ->dehydrated(fn ($get) => $get('update_collections'))
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('update_collections')),

                                // Clear Fields
                                TextInput::make('clear_fields_section_label')
                                    ->label(__('Clear Fields'))
                                    ->default('Use these options to clear specific fields from all selected products')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Checkbox::make('clear_vendor')
                                    ->label(__('Clear vendor (set to no vendor)'))
                                    ->helperText(__('Remove vendor assignment from all selected products'))
                                    ->columnSpanFull(),

                                Checkbox::make('clear_product_code')
                                    ->label(__('Clear product code'))
                                    ->helperText(__('Remove product code from all selected products'))
                                    ->columnSpanFull(),

                                Checkbox::make('clear_article_group_code')
                                    ->label(__('Clear article group code'))
                                    ->helperText(__('Remove article group code from all selected products'))
                                    ->columnSpanFull(),
                            ];
                        })
                        ->action(function (Collection $records, array $data): void {
                            $updates = [];
                            $updatedCount = 0;

                            // Status & Visibility
                            if (! empty($data['update_active'])) {
                                $updates['active'] = (bool) ($data['active'] ?? false);
                            }

                            if (! empty($data['update_shippable'])) {
                                $updates['shippable'] = (bool) ($data['shippable'] ?? false);
                            }

                            if (! empty($data['update_no_price_in_pos'])) {
                                $updates['no_price_in_pos'] = (bool) ($data['no_price_in_pos'] ?? false);
                            }

                            // Product Details
                            if (! empty($data['update_type']) && isset($data['type'])) {
                                $updates['type'] = $data['type'];
                            }

                            if (! empty($data['update_vendor'])) {
                                if (! empty($data['clear_vendor'])) {
                                    $updates['vendor_id'] = null;
                                } elseif (isset($data['vendor_id'])) {
                                    $updates['vendor_id'] = $data['vendor_id'];
                                }
                            }

                            if (! empty($data['update_product_code'])) {
                                if (! empty($data['clear_product_code'])) {
                                    $updates['product_code'] = null;
                                } elseif (isset($data['product_code'])) {
                                    $updates['product_code'] = $data['product_code'];
                                }
                            }

                            if (! empty($data['update_article_group_code'])) {
                                $stripeAccountId = $records->first()?->stripe_account_id
                                    ?? \Filament\Facades\Filament::getTenant()?->stripe_account_id;
                                $resolveVat = app(ResolveProductVatRate::class);
                                if (! empty($data['clear_article_group_code'])) {
                                    $updates['article_group_code'] = null;
                                    $updates['vat_percent'] = null;
                                } elseif (isset($data['article_group_code'])) {
                                    $updates['article_group_code'] = $data['article_group_code'];
                                    $vatPercent = $resolveVat->vatPercentFromArticleGroupCode(
                                        $data['article_group_code'],
                                        $stripeAccountId
                                    );
                                    $updates['vat_percent'] = $vatPercent;
                                }
                            }

                            // Pricing
                            if (! empty($data['update_price'])) {
                                if (isset($data['price']) && $data['price'] !== null && $data['price'] !== '') {
                                    $updates['price'] = str_replace(',', '.', str_replace(' ', '', (string) $data['price']));
                                }
                                if (isset($data['currency'])) {
                                    $updates['currency'] = $data['currency'];
                                }
                            }

                            if (! empty($data['update_compare_at_price'])) {
                                if (! empty($data['clear_compare_at_price'])) {
                                    $updates['compare_at_price_amount'] = null;
                                } elseif (isset($data['compare_at_price_decimal']) && $data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                                    $updates['compare_at_price_amount'] = (int) round((float) $data['compare_at_price_decimal'] * 100);
                                } elseif (isset($data['compare_at_price_amount'])) {
                                    $updates['compare_at_price_amount'] = $data['compare_at_price_amount'];
                                }
                            }

                            // Apply updates
                            if (! empty($updates)) {
                                $updatedCount = ConnectedProduct::whereIn('id', $records->pluck('id'))
                                    ->update($updates);
                            }

                            // Handle collections
                            if (! empty($data['update_collections']) && isset($data['collections'])) {
                                $collectionIds = is_array($data['collections']) ? $data['collections'] : [];
                                $replaceMode = ! empty($data['replace_collections']);

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
                            if ($updatedCount > 0 || ! empty($data['update_collections'])) {
                                $message = "Successfully updated {$updatedCount} product(s)";
                                if (! empty($data['update_collections'])) {
                                    $message .= ' and updated collections';
                                }

                                Notification::make()
                                    ->success()
                                    ->title(__('Bulk edit completed'))
                                    ->body($message)
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title(__('No changes made'))
                                    ->body('Please enable at least one field to update.')
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading(__('Bulk Edit Products'))
                        ->modalDescription(__('Select which fields to update for the selected products'))
                        ->modalSubmitActionLabel(__('Apply Changes'))
                        ->modalWidth('4xl'),

                    BulkAction::make('setArticleGroupCode')
                        ->label(__('Set Article Group Code'))
                        ->icon('heroicon-o-tag')
                        ->color('info')
                        ->form([
                            Select::make('article_group_code')
                                ->label(__('Article Group Code (SAF-T)'))
                                ->options(function () {
                                    $stripeAccountId = \Filament\Facades\Filament::getTenant()?->stripe_account_id ?? null;
                                    $query = ArticleGroupCode::query();
                                    if ($stripeAccountId) {
                                        $query->where(function ($q) use ($stripeAccountId) {
                                            $q->where('stripe_account_id', $stripeAccountId)
                                                ->orWhere(fn ($q2) => $q2->whereNull('stripe_account_id')->where('is_standard', true));
                                        });
                                    } else {
                                        $query->whereNull('stripe_account_id')->where('is_standard', true);
                                    }

                                    return $query->where('active', true)
                                        ->orderBy('sort_order', 'asc')
                                        ->orderBy('code', 'asc')
                                        ->get()
                                        ->mapWithKeys(fn ($record) => [$record->code => $record->code.' - '.$record->name]);
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    $stripeAccountId = \Filament\Facades\Filament::getTenant()?->stripe_account_id ?? null;
                                    $query = ArticleGroupCode::query()
                                        ->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                                    if ($stripeAccountId) {
                                        $query->where(function ($q) use ($stripeAccountId) {
                                            $q->where('stripe_account_id', $stripeAccountId)
                                                ->orWhere(fn ($q2) => $q2->whereNull('stripe_account_id')->where('is_standard', true));
                                        });
                                    } else {
                                        $query->whereNull('stripe_account_id')->where('is_standard', true);
                                    }

                                    return $query->where('active', true)
                                        ->orderBy('sort_order', 'asc')
                                        ->orderBy('code', 'asc')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($record) => [$record->code => $record->code.' - '.$record->name]);
                                })
                                ->getOptionLabelUsing(function ($value) {
                                    $record = ArticleGroupCode::where('code', $value)->first();

                                    return $record ? $record->code.' - '.$record->name : $value;
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder(__('Select article group'))
                                ->required()
                                ->helperText(__('VAT rate will be updated from the article group default when set.')),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $code = $data['article_group_code'] ?? null;
                            if (! $code) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Article group code required'))
                                    ->send();

                                return;
                            }
                            $stripeAccountId = $records->first()?->stripe_account_id
                                ?? \Filament\Facades\Filament::getTenant()?->stripe_account_id;
                            $resolveVat = app(ResolveProductVatRate::class);
                            $vatPercent = $resolveVat->vatPercentFromArticleGroupCode($code, $stripeAccountId);
                            $updates = [
                                'article_group_code' => $code,
                                'vat_percent' => $vatPercent,
                            ];
                            $updated = ConnectedProduct::whereIn('id', $records->pluck('id'))->update($updates);
                            Notification::make()
                                ->success()
                                ->title(__('Article group code set'))
                                ->body("Article group code and VAT have been updated for {$updated} product(s).")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Article group code set'),

                    BulkAction::make('setVendor')
                        ->label(__('Set Vendor'))
                        ->icon('heroicon-o-building-storefront')
                        ->color('info')
                        ->form([
                            Select::make('vendor_id')
                                ->label(__('Vendor'))
                                ->options(function () {
                                    // Get stripe_account_id from tenant (all products in list are from same tenant)
                                    try {
                                        $tenant = \Filament\Facades\Filament::getTenant();
                                        $stripeAccountId = $tenant?->stripe_account_id;

                                        if (! $stripeAccountId) {
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
                                ->placeholder(__('Select a vendor'))
                                ->helperText(__('Select the vendor to assign to all selected products'))
                                ->required()
                                ->live(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $vendorId = $data['vendor_id'] ?? null;

                            if (! $vendorId) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Vendor selection required'))
                                    ->body('Please select a vendor to assign.')
                                    ->send();

                                return;
                            }

                            $updated = ConnectedProduct::whereIn('id', $records->pluck('id'))
                                ->update(['vendor_id' => $vendorId]);

                            Notification::make()
                                ->success()
                                ->title(__('Vendor assigned'))
                                ->body("Vendor has been assigned to {$updated} product(s).")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Vendor assigned successfully'),

                    BulkAction::make('exportAsZip')
                        ->label(__('Export as ZIP'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            if (! $tenant || ! $tenant->slug) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Store required'))
                                    ->body('Export is only available in a store context.')
                                    ->send();

                                return;
                            }

                            try {
                                $exporter = app(ProductZipExporter::class);
                                $zipPath = $exporter->export($records, false);

                                $token = Str::random(64);
                                Cache::put('product-export-download:'.$token, $zipPath, now()->addMinutes(10));

                                $downloadUrl = route('products.export-download', [
                                    'tenant' => $tenant->slug,
                                    'token' => $token,
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title(__('Export ready'))
                                    ->body('Your product export ZIP is ready. Use the button below to download it. You can import this file in another store via Import ZIP.')
                                    ->actions([
                                        Action::make('download')
                                            ->label(__('Download ZIP'))
                                            ->url($downloadUrl)
                                            ->openUrlInNewTab(),
                                    ])
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Export failed'))
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('syncToStripe')
                        ->label(__('Create/Update in Stripe'))
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('success')
                        ->modalHeading(__('Create or update products in Stripe'))
                        ->modalDescription(__('Products without a Stripe ID will be created in Stripe. Products that already exist in Stripe will be updated. Requires a store (stripe_account_id).'))
                        ->modalSubmitActionLabel(__('Sync to Stripe'))
                        ->action(function (Collection $records): void {
                            $createAction = new CreateConnectedProductInStripe;
                            $updateAction = new UpdateConnectedProductToStripe;
                            $syncPriceAction = new \App\Actions\ConnectedPrices\SyncProductPrice;
                            $created = 0;
                            $updated = 0;
                            $skipped = 0;
                            $errors = [];

                            foreach ($records as $product) {
                                if (! $product->stripe_account_id) {
                                    $skipped++;
                                    $errors[] = "{$product->name} (ID {$product->id}): no store assigned.";

                                    continue;
                                }

                                try {
                                    if (! $product->stripe_product_id) {
                                        $stripeProductId = $createAction($product);
                                        if ($stripeProductId) {
                                            $product->stripe_product_id = $stripeProductId;
                                            $product->saveQuietly();
                                            $created++;
                                            $updateAction($product);
                                            $syncPriceAction($product);
                                        } else {
                                            $skipped++;
                                            $errors[] = "{$product->name}: create in Stripe failed.";
                                        }
                                    } else {
                                        $updateAction($product);
                                        $syncPriceAction($product);
                                        $updated++;
                                    }
                                } catch (\Throwable $e) {
                                    $skipped++;
                                    $errors[] = "{$product->name}: ".$e->getMessage();
                                    report($e);
                                }
                            }

                            $parts = array_filter([
                                $created > 0 ? "{$created} created" : null,
                                $updated > 0 ? "{$updated} updated" : null,
                                $skipped > 0 ? "{$skipped} skipped" : null,
                            ]);
                            $body = implode(', ', $parts).'.';
                            if (! empty($errors)) {
                                $body .= ' '.implode(' ', array_slice($errors, 0, 5));
                                if (count($errors) > 5) {
                                    $body .= ' … and '.(count($errors) - 5).' more.';
                                }
                            }

                            $notification = Notification::make()
                                ->title(__('Stripe sync completed'))
                                ->body($body);
                            if ($created > 0 || $updated > 0) {
                                $notification->success();
                            } elseif ($skipped > 0) {
                                $notification->warning();
                            } else {
                                $notification->danger();
                            }
                            $notification->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
