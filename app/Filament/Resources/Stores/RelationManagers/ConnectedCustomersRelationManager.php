<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource;
use App\Models\ConnectedCustomer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConnectedCustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'connectedCustomers';

    protected static ?string $title = 'Customers';

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
            ->modifyQueryUsing(fn ($query) => $query->where('stripe_account_id', $this->ownerRecord->stripe_account_id))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('-'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedEnvelope)
                    ->placeholder('-'),

                TextColumn::make('subscriptions_count')
                    ->label('Subscriptions')
                    ->counts('subscriptions')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('stripe_customer_id')
                    ->label('Customer ID')
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
                // Customers are typically created via API
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ConnectedCustomerResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn ($record) => ConnectedCustomerResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
