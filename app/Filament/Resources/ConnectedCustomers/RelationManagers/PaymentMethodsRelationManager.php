<?php

namespace App\Filament\Resources\ConnectedCustomers\RelationManagers;

use App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource;
use App\Models\ConnectedPaymentMethod;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class PaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentMethods';

    protected static ?string $title = 'Payment Methods';

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
            ->modifyQueryUsing(fn ($query) => $query->where('stripe_customer_id', $this->ownerRecord->stripe_customer_id)
                ->where('stripe_account_id', $this->ownerRecord->stripe_account_id))
            ->columns([
                TextColumn::make('card_display')
                    ->label('Payment Method')
                    ->searchable(['card_brand', 'card_last4'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color('gray'),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('billing_details_name')
                    ->label('Billing Name')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('billing_details_email')
                    ->label('Billing Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_payment_method_id')
                    ->label('Payment Method ID')
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
                // Payment methods are typically created via Stripe.js
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ConnectedPaymentMethodResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn ($record) => ConnectedPaymentMethodResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

