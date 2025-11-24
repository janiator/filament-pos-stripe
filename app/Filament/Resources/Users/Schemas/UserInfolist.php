<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        TextEntry::make('name')
                            ->icon(Heroicon::OutlinedUser),

                        TextEntry::make('email')
                            ->label('Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable(),

                        IconEntry::make('email_verified_at')
                            ->label('Email Verified')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->separator(',')
                            ->icon(Heroicon::OutlinedShieldCheck),
                    ])
                    ->columns(2),

                Section::make('Store Access')
                    ->schema([
                        TextEntry::make('stores_count')
                            ->label('Number of Stores')
                            ->state(fn ($record) => $record->stores->count())
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedBuildingStorefront),
                    ])
                    ->visible(fn ($record) => $record->stores->count() > 0),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
