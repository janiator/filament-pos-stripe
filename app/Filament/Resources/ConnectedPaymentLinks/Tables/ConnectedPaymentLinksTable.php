<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Tables;

use App\Actions\ConnectedPaymentLinks\UpdateConnectedPaymentLinkInStripe;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedPaymentLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'price']))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('Unnamed')
                    ->wrap(),

                TextColumn::make('url')
                    ->label('URL')
                    ->copyable()
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab()
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedLink)
                    ->wrap(),

                TextColumn::make('link_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'direct' ? 'info' : 'gray')
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('price.formatted_amount')
                    ->label('Price')
                    ->badge()
                    ->color('success')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_payment_link_id')
                    ->label('Payment Link ID')
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
                SelectFilter::make('link_type')
                    ->label('Type')
                    ->options([
                        'direct' => 'Direct',
                        'destination' => 'Destination',
                    ]),

                \Filament\Tables\Filters\TernaryFilter::make('active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                ViewAction::make(),
                // Payment links can be edited, but we'll keep it view-only for now
                // EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Deactivate')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Payment Links')
                        ->modalDescription('Are you sure you want to deactivate the selected payment links? They will be deactivated in Stripe but not deleted. You can reactivate them later.')
                        ->action(function ($records) {
                            $action = new UpdateConnectedPaymentLinkInStripe();
                            $deactivatedCount = 0;

                            foreach ($records as $record) {
                                $record->active = false;
                                $record->save();
                                $action($record, false);
                                $deactivatedCount++;
                            }

                            Notification::make()
                                ->title('Payment links deactivated')
                                ->body("{$deactivatedCount} payment link(s) have been deactivated.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
