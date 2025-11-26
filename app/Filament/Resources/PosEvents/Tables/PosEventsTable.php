<?php

namespace App\Filament\Resources\PosEvents\Tables;

use App\Models\PosEvent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PosEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_code')
                    ->label('Event Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('posSession.session_number')
                    ->label('Session')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable(),
                TextColumn::make('occurred_at')
                    ->label('Occurred At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event_code')
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
                SelectFilter::make('event_type')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Events should not be deletable for audit trail
                ]),
            ]);
    }
}
