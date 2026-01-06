<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('commission_type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'percentage',
                        'warning' => 'fixed',
                    ])
                    ->sortable(),

                TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->formatStateUsing(function (Store $record): string {
                        if ($record->commission_type === 'percentage') {
                            return "{$record->commission_rate}%";
                        }

                        return number_format($record->commission_rate / 100, 2);
                    })
                    ->sortable(),

                TextColumn::make('stripe_account_id')
                    ->label('Stripe account')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('connected_to_stripe')
                    ->label('Connected to Stripe')
                    ->trueLabel('Connected')
                    ->falseLabel('Not connected')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('stripe_account_id'),
                        false: fn ($query) => $query->whereNull('stripe_account_id'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('syncEverything')
                    ->label('Sync Everything from Stripe')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Everything from Stripe')
                    ->modalDescription('This will sync all data (customers, products, subscriptions, charges, transfers, payment methods, payment links, and terminal devices) from Stripe for this store. The sync will run in the background.')
                    ->action(function (Store $record) {
                        // Dispatch job instead of running synchronously
                        \App\Jobs\SyncStoreEverythingFromStripeJob::dispatch($record);

                        Notification::make()
                            ->title('Sync queued')
                            ->body('The sync has been queued and will run in the background. You can check the progress in the queue dashboard.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Store $record): bool => !empty($record->stripe_account_id)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
