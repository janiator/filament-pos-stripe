<?php

namespace App\Filament\Resources\Receipts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Information')
                    ->schema([
                        Select::make('store_id')
                            ->relationship('store', 'name', modifyQueryUsing: function ($query) {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant && $tenant->slug !== 'visivo-admin') {
                                        $query->where('stores.id', $tenant->id);
                                    }
                                } catch (\Throwable $e) {
                                    // Fallback if Filament facade not available
                                }
                            })
                            ->required()
                            ->default(fn () => \Filament\Facades\Filament::getTenant()?->id)
                            ->searchable()
                            ->preload()
                            ->visible(function () {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    return $tenant && $tenant->slug === 'visivo-admin';
                                } catch (\Throwable $e) {
                                    return false;
                                }
                            })
                            ->disabled(fn ($record) => $record !== null),

                        Select::make('pos_session_id')
                            ->relationship('posSession', 'session_number', modifyQueryUsing: function ($query) {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant && $tenant->slug !== 'visivo-admin') {
                                        $query->where('pos_sessions.store_id', $tenant->id);
                                    }
                                } catch (\Throwable $e) {
                                    // Fallback if Filament facade not available
                                }
                            })
                            ->searchable()
                            ->preload(),

                        Select::make('charge_id')
                            ->relationship(
                                'charge',
                                'id', // Use 'id' as the title attribute (required by Filament, but we override with getOptionLabelUsing)
                                modifyQueryUsing: function ($query) {
                                    try {
                                        $tenant = \Filament\Facades\Filament::getTenant();
                                        if ($tenant && $tenant->slug !== 'visivo-admin' && $tenant->stripe_account_id) {
                                            $query->where('connected_charges.stripe_account_id', $tenant->stripe_account_id);
                                        }
                                    } catch (\Throwable $e) {
                                        // Fallback if Filament facade not available
                                    }
                                }
                            )
                            ->getOptionLabelUsing(function ($value) {
                                $charge = \App\Models\ConnectedCharge::find($value);
                                if (!$charge) {
                                    return 'Unknown Charge';
                                }
                                // Handle null stripe_charge_id (cash payments)
                                if ($charge->stripe_charge_id) {
                                    return $charge->stripe_charge_id . ' - ' . number_format($charge->amount / 100, 2) . ' ' . strtoupper($charge->currency);
                                }
                                // For cash payments, show charge ID and amount
                                return 'Cash Payment #' . $charge->id . ' - ' . number_format($charge->amount / 100, 2) . ' ' . strtoupper($charge->currency);
                            })
                            ->searchable()
                            ->preload(),

                        Select::make('user_id')
                            ->relationship('user', 'name', modifyQueryUsing: function ($query) {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant && $tenant->slug !== 'visivo-admin') {
                                        $query->whereHas('stores', function ($q) use ($tenant) {
                                            $q->where('stores.id', $tenant->id);
                                        });
                                    }
                                } catch (\Throwable $e) {
                                    // Fallback if Filament facade not available
                                }
                            })
                            ->label('Cashier')
                            ->searchable()
                            ->preload(),

                        TextInput::make('receipt_number')
                            ->label('Receipt Number')
                            ->required()
                            ->disabled()
                            ->helperText('Automatically generated'),

                        Select::make('receipt_type')
                            ->label('Receipt Type')
                            ->options([
                                'sales' => 'Sales Receipt',
                                'return' => 'Return Receipt',
                                'copy' => 'Copy Receipt',
                                'steb' => 'STEB Receipt',
                                'provisional' => 'Provisional Receipt',
                                'training' => 'Training Receipt',
                                'delivery' => 'Delivery Receipt',
                            ])
                            ->required(),

                        Select::make('original_receipt_id')
                            ->relationship('originalReceipt', 'receipt_number')
                            ->label('Original Receipt')
                            ->searchable()
                            ->preload()
                            ->helperText('For returns/copies'),
                    ])
                    ->columns(2),

                Section::make('Receipt Data')
                    ->schema([
                        KeyValue::make('receipt_data')
                            ->label('Receipt Data (JSON)')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Print Status')
                    ->schema([
                        Toggle::make('printed')
                            ->label('Printed')
                            ->default(false),

                        DateTimePicker::make('printed_at')
                            ->label('Printed At')
                            ->disabled(fn ($record) => !$record || !$record->printed),

                        TextInput::make('reprint_count')
                            ->label('Reprint Count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
