<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->visibleOn('create'),

                TextInput::make('password')
                    ->label('New Password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->helperText('Leave blank to keep current password')
                    ->visibleOn('edit'),

                DateTimePicker::make('email_verified_at')
                    ->label('Email Verified At')
                    ->default(fn () => now())
                    ->helperText('Set to verify the user\'s email address'),

                Select::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Assign roles to the user. Super admins have access to all stores.'),
                
                Section::make('Active API Tokens')
                    ->schema([
                        View::make('filament.resources.users.components.api-tokens')
                            ->key('api-tokens')
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->visibleOn('edit')
                    ->collapsible()
                    ->icon(Heroicon::OutlinedKey),
            ]);
    }
}
