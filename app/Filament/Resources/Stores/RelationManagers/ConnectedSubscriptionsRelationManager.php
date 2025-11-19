<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Filament\Resources\ConnectedSubscriptions\ConnectedSubscriptionResource;
use App\Models\ConnectedSubscription;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'connectedSubscriptions';

    protected static ?string $title = 'Subscriptions';

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
            ->modifyQueryUsing(fn ($query) => $query->where('stripe_account_id', $this->ownerRecord->stripe_account_id)
                ->with(['customer']))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
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
            ->headerActions([
                // Subscriptions are typically created via API
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ConnectedSubscriptionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn ($record) => ConnectedSubscriptionResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
