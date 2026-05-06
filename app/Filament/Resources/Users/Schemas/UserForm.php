<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
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
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->visibleOn('create'),

                TextInput::make('password')
                    ->label(__('New Password'))
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->helperText(__('Leave blank to keep current password'))
                    ->visibleOn('edit'),

                DateTimePicker::make('email_verified_at')
                    ->label(__('Email Verified At'))
                    ->default(fn () => now())
                    ->helperText(__('Set to verify the user\'s email address')),

                Select::make('roles')
                    ->label(__('Roles'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText(__('Assign roles to the user. Super admins have access to all stores. Other users must be attached to stores under the Stores relation so tenant URLs and impersonation work.')),

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
