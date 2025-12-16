<?php

namespace App\Filament\Resources\PosSessions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PosSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Session Information')
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
                            ->disabled(fn ($record) => $record !== null || ($record && $record->status === 'closed')),

                        Select::make('pos_device_id')
                            ->relationship('posDevice', 'device_name', modifyQueryUsing: function ($query) {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant && $tenant->slug !== 'visivo-admin') {
                                        $query->where('pos_devices.store_id', $tenant->id);
                                    }
                                } catch (\Throwable $e) {
                                    // Fallback if Filament facade not available
                                }
                            })
                            ->label('Pos device')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn ($record) => $record && $record->status === 'closed')
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                // If status is open, check for existing open session
                                if ($get('status') === 'open' && $state) {
                                    $existingSession = \App\Models\PosSession::where('pos_device_id', $state)
                                        ->where('status', 'open')
                                        ->first();
                                    
                                    if ($existingSession) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Device has open session')
                                            ->warning()
                                            ->body("This device already has an open session: {$existingSession->session_number}. Please close it first.")
                                            ->send();
                                    }
                                }
                            }),

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
                            ->preload()
                            ->default(fn () => auth()->id())
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),

                        TextInput::make('session_number')
                            ->label('Session Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Automatically generated'),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'open' => 'Open',
                                'closed' => 'Closed',
                            ])
                            ->required()
                            ->default('open')
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),
                    ])
                    ->columns(2),

                Section::make('Timing')
                    ->schema([
                        DateTimePicker::make('opened_at')
                            ->label('Opened At')
                            ->required()
                            ->default(now())
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),

                        DateTimePicker::make('closed_at')
                            ->label('Closed At')
                            ->disabled(fn ($record) => $record && $record->status !== 'closed'),
                    ])
                    ->columns(2),

                Section::make('Cash Management')
                    ->schema([
                        TextInput::make('opening_balance')
                            ->label('Opening Balance')
                            ->numeric()
                            ->default(0)
                            ->suffix('kr')
                            ->helperText('Starting cash in drawer')
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),

                        TextInput::make('expected_cash')
                            ->label('Expected Cash')
                            ->numeric()
                            ->disabled()
                            ->suffix('kr')
                            ->helperText('Opening balance + cash from transactions'),

                        TextInput::make('actual_cash')
                            ->label('Actual Cash')
                            ->numeric()
                            ->suffix('kr')
                            ->helperText('Cash counted at closing')
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),

                        TextInput::make('cash_difference')
                            ->label('Cash Difference')
                            ->numeric()
                            ->disabled()
                            ->suffix('kr')
                            ->helperText('Difference between expected and actual'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('opening_notes')
                            ->label('Opening Notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),

                        Textarea::make('closing_notes')
                            ->label('Closing Notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && $record->status === 'closed'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
