<?php

namespace App\Filament\Resources\PosDevices\RelationManagers;

use App\Models\PosEvent;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PosEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'posEvents';

    protected static ?string $title = 'POS Events';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_code')
            ->columns([
                Tables\Columns\TextColumn::make('event_code')
                    ->label('Event Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('posSession.session_number')
                    ->label('Session')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Occurred At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_code')
                    ->label('Event Code')
                    ->options([
                        PosEvent::EVENT_APPLICATION_START => 'Application Start',
                        PosEvent::EVENT_APPLICATION_SHUTDOWN => 'Application Shutdown',
                        PosEvent::EVENT_EMPLOYEE_LOGIN => 'Employee Login',
                        PosEvent::EVENT_EMPLOYEE_LOGOUT => 'Employee Logout',
                        PosEvent::EVENT_CASH_DRAWER_OPEN => 'Cash Drawer Open',
                        PosEvent::EVENT_CASH_DRAWER_CLOSE => 'Cash Drawer Close',
                        PosEvent::EVENT_X_REPORT => 'X Report',
                        PosEvent::EVENT_Z_REPORT => 'Z Report',
                        PosEvent::EVENT_SALES_RECEIPT => 'Sales Receipt',
                        PosEvent::EVENT_RETURN_RECEIPT => 'Return Receipt',
                        PosEvent::EVENT_SESSION_OPENED => 'Session Opened',
                        PosEvent::EVENT_SESSION_CLOSED => 'Session Closed',
                    ]),
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'application' => 'Application',
                        'user' => 'User',
                        'drawer' => 'Drawer',
                        'report' => 'Report',
                        'transaction' => 'Transaction',
                        'payment' => 'Payment',
                        'session' => 'Session',
                    ]),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
