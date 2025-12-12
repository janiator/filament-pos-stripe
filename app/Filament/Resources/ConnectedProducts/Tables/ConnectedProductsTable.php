<?php

namespace App\Filament\Resources\ConnectedProducts\Tables;

use App\Models\ConnectedProduct;
use App\Models\Vendor;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ConnectedProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'prices', 'variants', 'vendor']))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('-'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->badge()
                    ->color('success')
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
                    }),

                TextColumn::make('compare_at_price_amount')
                    ->label('Compare at Price')
                    ->badge()
                    ->color('gray')
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
                    ->formatStateUsing(function ($state) {
                        return $state ? $state . '%' : '-';
                    })
                    ->toggleable(),

                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?: '0'),

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

                TextColumn::make('product_code')
                    ->label('Product Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('-')
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

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Service')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->url(fn (ConnectedProduct $record) => $record->store
                        ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                        : null)
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
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('stripe_account_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),

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
                    ->preload(),

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
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
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
