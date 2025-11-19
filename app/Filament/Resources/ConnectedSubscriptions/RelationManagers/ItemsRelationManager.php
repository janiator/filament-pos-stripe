<?php

namespace App\Filament\Resources\ConnectedSubscriptions\RelationManagers;

use App\Models\ConnectedSubscriptionItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Subscription Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->schema([
                        TextEntry::make('product_name')
                            ->label('Product')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->size(TextSize::Large)
                            ->weight('bold')
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                return $product?->name ?? $record->connected_product ?? '-';
                            })
                            ->url(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                if ($product && class_exists(\App\Filament\Resources\ConnectedProducts\ConnectedProductResource::class)) {
                                    return \App\Filament\Resources\ConnectedProducts\ConnectedProductResource::getUrl('view', ['record' => $product]);
                                }
                                return null;
                            }),

                        TextEntry::make('product_description')
                            ->label('Product Description')
                            ->placeholder('-')
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                return $product?->description ?? null;
                            })
                            ->visible(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                return $product && $product->description;
                            }),

                        TextEntry::make('product_type')
                            ->label('Product Type')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                return $product?->type ? ucfirst($product->type) : '-';
                            })
                            ->visible(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                return $product && $product->type;
                            }),

                        IconEntry::make('product_active')
                            ->label('Product Active')
                            ->boolean()
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                return $product?->active ?? false;
                            })
                            ->icon(fn ($state) => $state
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($state) => $state ? 'success' : 'danger')
                            ->visible(function (ConnectedSubscriptionItem $record) {
                                $product = $record->product();
                                return $product !== null;
                            }),
                    ])
                    ->columns(3),

                Section::make('Pricing Information')
                    ->schema([
                        TextEntry::make('price_amount')
                            ->label('Price')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success')
                            ->weight('bold')
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                if (!$price) {
                                    return '-';
                                }
                                
                                $amount = method_exists($price, 'getFormattedAmountAttribute') 
                                    ? $price->formatted_amount 
                                    : number_format($price->unit_amount / 100, 2) . ' ' . strtoupper($price->currency ?? 'USD');
                                
                                if ($price->type === 'recurring' && $price->recurring_description) {
                                    return "{$amount} / {$price->recurring_description}";
                                }
                                
                                return $amount;
                            }),

                        TextEntry::make('price_type')
                            ->label('Price Type')
                            ->badge()
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                return $price?->type ? ucfirst($price->type) : '-';
                            })
                            ->colors([
                                'success' => 'recurring',
                                'info' => 'one_time',
                            ])
                            ->visible(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                return $price && $price->type;
                            }),

                        TextEntry::make('billing_interval')
                            ->label('Billing Interval')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                return $price?->recurring_description ?? '-';
                            })
                            ->visible(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                return $price && $price->type === 'recurring' && $price->recurring_description;
                            }),

                        TextEntry::make('currency')
                            ->label('Currency')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                return $price?->currency ? strtoupper($price->currency) : '-';
                            }),

                        TextEntry::make('billing_scheme')
                            ->label('Billing Scheme')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                return $price?->billing_scheme ? ucfirst(str_replace('_', ' ', $price->billing_scheme)) : '-';
                            })
                            ->visible(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                return $price && $price->billing_scheme;
                            }),
                    ])
                    ->columns(3),

                Section::make('Subscription Item Details')
                    ->schema([
                        TextEntry::make('quantity')
                            ->label('Quantity')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->size(TextSize::Large),

                        TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->badge()
                            ->color('success')
                            ->size(TextSize::Large)
                            ->weight('bold')
                            ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                                $price = $record->price();
                                if (!$price) {
                                    return '-';
                                }
                                
                                $unitAmount = $price->unit_amount ?? 0;
                                $quantity = $record->quantity ?? 1;
                                $total = ($unitAmount * $quantity) / 100;
                                $currency = strtoupper($price->currency ?? 'USD');
                                
                                return number_format($total, 2) . ' ' . $currency;
                            }),

                        TextEntry::make('stripe_id')
                            ->label('Stripe Item ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-'),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),
                    ])
                    ->columns(3),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('connected_product')
                            ->label('Product ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-'),

                        TextEntry::make('connected_price')
                            ->label('Price ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-'),

                        TextEntry::make('connected_subscription_id')
                            ->label('Subscription ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->where('connected_subscription_id', $this->ownerRecord->id)
                    ->with('subscription'); // Eager load subscription to avoid N+1 in product/price methods
            })
            ->columns([
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->state(function (ConnectedSubscriptionItem $record) {
                        $product = $record->product();
                        return $product?->name ?? $record->connected_product ?? '-';
                    })
                    ->description(function (ConnectedSubscriptionItem $record) {
                        $product = $record->product();
                        if ($product && $product->description) {
                            return \Illuminate\Support\Str::limit($product->description, 60);
                        }
                        return null;
                    })
                    ->url(function (ConnectedSubscriptionItem $record) {
                        $product = $record->product();
                        if ($product && class_exists(\App\Filament\Resources\ConnectedProducts\ConnectedProductResource::class)) {
                            return \App\Filament\Resources\ConnectedProducts\ConnectedProductResource::getUrl('view', ['record' => $product]);
                        }
                        return null;
                    })
                    ->wrap(),

                TextColumn::make('price')
                    ->label('Price & Billing')
                    ->badge()
                    ->color('success')
                    ->state(function (ConnectedSubscriptionItem $record) {
                        $price = $record->price();
                        if (!$price) {
                            return $record->connected_price ?? '-';
                        }
                        
                        $amount = method_exists($price, 'getFormattedAmountAttribute') 
                            ? $price->formatted_amount 
                            : number_format($price->unit_amount / 100, 2) . ' ' . strtoupper($price->currency ?? 'USD');
                        
                        if ($price->type === 'recurring' && $price->recurring_description) {
                            return "{$amount} / {$price->recurring_description}";
                        }
                        
                        return $amount;
                    })
                    ->description(function (ConnectedSubscriptionItem $record) {
                        $price = $record->price();
                        if (!$price) {
                            return null;
                        }
                        
                        $details = [];
                        if ($price->type) {
                            $details[] = ucfirst($price->type);
                        }
                        if ($price->currency) {
                            $details[] = strtoupper($price->currency);
                        }
                        if ($price->billing_scheme) {
                            $details[] = ucfirst(str_replace('_', ' ', $price->billing_scheme));
                        }
                        
                        return !empty($details) ? implode(' • ', $details) : null;
                    }),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->default(1),

                TextColumn::make('total')
                    ->label('Total')
                    ->badge()
                    ->color('gray')
                    ->state(function (ConnectedSubscriptionItem $record) {
                        $price = $record->price();
                        if (!$price) {
                            return '-';
                        }
                        
                        $unitAmount = $price->unit_amount ?? 0;
                        $quantity = $record->quantity ?? 1;
                        $total = ($unitAmount * $quantity) / 100;
                        $currency = strtoupper($price->currency ?? 'USD');
                        
                        return number_format($total, 2) . ' ' . $currency;
                    })
                    ->description('Quantity × Unit Price')
                    ->toggleable(),

                TextColumn::make('stripe_id')
                    ->label('Item ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('connected_product')
                    ->label('Product ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('connected_price')
                    ->label('Price ID')
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
                //
            ])
            ->headerActions([
                // Items are typically managed through Stripe API
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
