<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConnectedPaymentIntentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only fields - payment intents are created via API, not manually
                TextInput::make('stripe_id')
                    ->label('Payment Intent ID')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('formatted_amount')
                    ->label('Amount')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('status')
                    ->label('Status')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('capture_method')
                    ->label('Capture Method')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextInput::make('confirmation_method')
                    ->label('Confirmation Method')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextInput::make('description')
                    ->label('Description')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('receipt_email')
                    ->label('Receipt Email')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('succeeded_at')
                    ->label('Succeeded At')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i:s') : '-'),

                TextInput::make('canceled_at')
                    ->label('Canceled At')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i:s') : '-'),

                TextInput::make('cancellation_reason')
                    ->label('Cancellation Reason')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn ($record) => $record && $record->canceled_at),
            ]);
    }
}
