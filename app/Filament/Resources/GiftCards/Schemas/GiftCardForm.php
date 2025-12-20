<?php

namespace App\Filament\Resources\GiftCards\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class GiftCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => \Filament\Facades\Filament::getTenant()?->id),
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true)
                    ->default(fn () => \App\Models\GiftCard::generateCode())
                    ->helperText('Unique gift card code'),
                TextInput::make('initial_amount_kroner')
                    ->label('Initial Amount (NOK)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->step(0.01)
                    ->helperText('Amount in Norwegian Kroner')
                    ->default(fn ($get) => $get('initial_amount') ? $get('initial_amount') / 100 : null)
                    ->afterStateUpdated(function ($state, callable $set, $get) {
                        // Convert to øre when setting initial_amount
                        if ($state !== null) {
                            $set('initial_amount', (int) round($state * 100));
                        }
                    })
                    ->dehydrated(false),
                TextInput::make('initial_amount')
                    ->label('Initial Amount (øre)')
                    ->numeric()
                    ->required()
                    ->hidden()
                    ->dehydrated(),
                TextInput::make('balance_kroner')
                    ->label('Current Balance (NOK)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Current balance in Norwegian Kroner')
                    ->default(fn ($get) => $get('balance') ? $get('balance') / 100 : null)
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Convert to øre when setting balance
                        if ($state !== null) {
                            $set('balance', (int) round($state * 100));
                        }
                    })
                    ->dehydrated(false),
                TextInput::make('balance')
                    ->label('Current Balance (øre)')
                    ->numeric()
                    ->required()
                    ->hidden()
                    ->dehydrated(),
                Select::make('currency')
                    ->label('Currency')
                    ->options([
                        'nok' => 'NOK',
                        'usd' => 'USD',
                        'eur' => 'EUR',
                    ])
                    ->default('nok')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'redeemed' => 'Redeemed',
                        'expired' => 'Expired',
                        'voided' => 'Voided',
                        'refunded' => 'Refunded',
                    ])
                    ->required()
                    ->default('active'),
                DateTimePicker::make('purchased_at')
                    ->label('Purchased At')
                    ->required()
                    ->default(now())
                    ->afterStateUpdated(function ($state, callable $set, $get) {
                        // Set expires_at to 1 year after purchased_at if not already set
                        if ($state && !$get('expires_at')) {
                            $storeId = $get('store_id');
                            $expirationDays = 365; // Default 1 year
                            
                            if ($storeId) {
                                $store = \App\Models\Store::find($storeId);
                                if ($store) {
                                    $settings = \App\Models\Setting::getForStore($store->id);
                                    $expirationDays = $settings->gift_card_expiration_days ?? 365;
                                }
                            }
                            
                            $expiresAt = \Carbon\Carbon::parse($state)->addDays($expirationDays);
                            $set('expires_at', $expiresAt);
                        }
                    }),
                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->nullable()
                    ->helperText('Leave empty for no expiration'),
                Select::make('customer_id')
                    ->label('Customer')
                    ->relationship(
                        'customer',
                        'name',
                        modifyQueryUsing: function ($query, $get, $record) {
                            $storeId = $get('store_id') ?? $record?->store_id;
                            if ($storeId) {
                                $store = \App\Models\Store::find($storeId);
                                if ($store) {
                                    $query->where('stripe_account_id', $store->stripe_account_id);
                                }
                            }
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
