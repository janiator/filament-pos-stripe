<?php

namespace App\Filament\Resources\ConnectedSubscriptions\RelationManagers;

use App\Models\ConnectedSubscriptionItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('connected_subscription_id', $this->ownerRecord->id))
            ->columns([
                TextColumn::make('connected_product')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
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

                TextColumn::make('connected_price')
                    ->label('Price')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(function (ConnectedSubscriptionItem $record) {
                        $price = $record->price();
                        if ($price && method_exists($price, 'getFormattedAmountAttribute')) {
                            return $price->formatted_amount;
                        }
                        return '-';
                    }),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('stripe_id')
                    ->label('Item ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('connected_product')
                    ->label('Product ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => false), // Hide since we show it as formatted above

                TextColumn::make('connected_price')
                    ->label('Price ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn () => false), // Hide since we show it as formatted above

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
