<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Tables;

use App\Models\ConnectedSubscription;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['store']);
                if (class_exists(\App\Models\ConnectedCustomer::class)) {
                    $query->with(['customer']);
                }
                // Eager load price and product for subscription name
                if (class_exists(\App\Models\ConnectedPrice::class)) {
                    $query->with(['price.product']);
                }
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Subscription')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(function (ConnectedSubscription $record) {
                        // Try to build a meaningful subscription name
                        $parts = [];
                        
                        // Get product name from price
                        if ($record->connected_price_id) {
                            $price = $record->price;
                            
                            // Filter by account_id if price is loaded
                            if ($price && $price->stripe_account_id === $record->stripe_account_id) {
                                $product = $price->product;
                                
                                if ($product && $product->name) {
                                    $parts[] = $product->name;
                                    
                                    // Add recurring interval if applicable
                                    if ($price->type === 'recurring' && $price->recurring_description) {
                                        $parts[] = "({$price->recurring_description})";
                                    }
                                }
                            }
                        }
                        
                        // Fallback to customer name if no product name
                        if (empty($parts) && $record->customer) {
                            $parts[] = $record->customer->name ?? $record->customer->email ?? 'Subscription';
                        }
                        
                        // Final fallback
                        if (empty($parts)) {
                            $parts[] = $record->name ?: 'Subscription';
                        }
                        
                        return implode(' ', $parts);
                    })
                    ->description(fn (ConnectedSubscription $record) => $record->customer 
                        ? ($record->customer->name ?? $record->customer->email ?? $record->stripe_customer_id)
                        : $record->stripe_customer_id)
                    ->placeholder('-'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->placeholder(fn (ConnectedSubscription $record) => $record->customer?->email ?? $record->customer?->name ?? $record->stripe_customer_id ?? 'Unknown')
                    ->description(fn (ConnectedSubscription $record) => $record->customer?->email)
                    ->url(fn (ConnectedSubscription $record) => $record->customer && class_exists(\App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource::class)
                        ? \App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource::getUrl('view', ['record' => $record->customer])
                        : null)
                    ->wrap(),

                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => ['active', 'trialing'],
                        'warning' => 'past_due',
                        'danger' => ['canceled', 'unpaid', 'incomplete'],
                        'info' => 'incomplete_expired',
                    ])
                    ->sortable(),

                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->badge()
                    ->color('success')
                    ->placeholder('-')
                    ->formatStateUsing(function (ConnectedSubscription $record) {
                        if ($record->connected_price_id && class_exists(\App\Models\ConnectedPrice::class)) {
                            $price = \App\Models\ConnectedPrice::where('stripe_price_id', $record->connected_price_id)
                                ->where('stripe_account_id', $record->stripe_account_id)
                                ->first();
                            if ($price && method_exists($price, 'getFormattedAmountAttribute')) {
                                return $price->formatted_amount;
                            }
                        }
                        return '-';
                    }),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->default(1),

                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn ($record) => $record->trial_ends_at && $record->trial_ends_at->isFuture() ? 'warning' : null)
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn ($record) => $record->ends_at && $record->ends_at->isPast() ? 'danger' : null)
                    ->toggleable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_id')
                    ->label('Subscription ID')
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
                SelectFilter::make('stripe_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'trialing' => 'Trialing',
                        'past_due' => 'Past Due',
                        'canceled' => 'Canceled',
                        'unpaid' => 'Unpaid',
                        'incomplete' => 'Incomplete',
                        'incomplete_expired' => 'Incomplete Expired',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
