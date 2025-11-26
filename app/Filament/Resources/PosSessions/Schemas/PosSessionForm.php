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
                            ->relationship('store', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('pos_device_id')
                            ->relationship('posDevice', 'device_name')
                            ->searchable()
                            ->preload()
                            ->reactive()
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
                            ->relationship('user', 'name')
                            ->label('Cashier')
                            ->searchable()
                            ->preload(),

                        TextInput::make('session_number')
                            ->label('Session Number')
                            ->required()
                            ->disabled()
                            ->helperText('Automatically generated'),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'open' => 'Open',
                                'closed' => 'Closed',
                                'abandoned' => 'Abandoned',
                            ])
                            ->required()
                            ->default('open'),
                    ])
                    ->columns(2),

                Section::make('Timing')
                    ->schema([
                        DateTimePicker::make('opened_at')
                            ->label('Opened At')
                            ->required()
                            ->default(now()),

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
                            ->helperText('Starting cash in drawer'),

                        TextInput::make('expected_cash')
                            ->label('Expected Cash')
                            ->numeric()
                            ->disabled()
                            ->suffix('kr')
                            ->helperText('Calculated from cash transactions'),

                        TextInput::make('actual_cash')
                            ->label('Actual Cash')
                            ->numeric()
                            ->suffix('kr')
                            ->helperText('Cash counted at closing'),

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
                            ->columnSpanFull(),

                        Textarea::make('closing_notes')
                            ->label('Closing Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
