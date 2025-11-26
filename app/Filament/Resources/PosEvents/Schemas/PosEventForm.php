<?php

namespace App\Filament\Resources\PosEvents\Schemas;

use App\Models\PosEvent;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PosEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('pos_device_id')
                    ->relationship('posDevice', 'device_name')
                    ->searchable()
                    ->preload(),

                Select::make('pos_session_id')
                    ->relationship('posSession', 'session_number')
                    ->searchable()
                    ->preload(),

                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Select::make('event_code')
                    ->label('Event Code')
                    ->options([
                        PosEvent::EVENT_APPLICATION_START => '13001 - Application Start',
                        PosEvent::EVENT_APPLICATION_SHUTDOWN => '13002 - Application Shutdown',
                        PosEvent::EVENT_EMPLOYEE_LOGIN => '13003 - Employee Login',
                        PosEvent::EVENT_EMPLOYEE_LOGOUT => '13004 - Employee Logout',
                        PosEvent::EVENT_CASH_DRAWER_OPEN => '13005 - Cash Drawer Open',
                        PosEvent::EVENT_CASH_DRAWER_CLOSE => '13006 - Cash Drawer Close',
                        PosEvent::EVENT_X_REPORT => '13008 - X Report',
                        PosEvent::EVENT_Z_REPORT => '13009 - Z Report',
                        PosEvent::EVENT_SALES_RECEIPT => '13012 - Sales Receipt',
                        PosEvent::EVENT_RETURN_RECEIPT => '13013 - Return Receipt',
                        PosEvent::EVENT_VOID_TRANSACTION => '13014 - Void Transaction',
                        PosEvent::EVENT_CORRECTION_RECEIPT => '13015 - Correction Receipt',
                        PosEvent::EVENT_CASH_PAYMENT => '13016 - Cash Payment',
                        PosEvent::EVENT_CARD_PAYMENT => '13017 - Card Payment',
                        PosEvent::EVENT_MOBILE_PAYMENT => '13018 - Mobile Payment',
                        PosEvent::EVENT_OTHER_PAYMENT => '13019 - Other Payment',
                        PosEvent::EVENT_SESSION_OPENED => '13020 - Session Opened',
                        PosEvent::EVENT_SESSION_CLOSED => '13021 - Session Closed',
                    ])
                    ->required()
                    ->searchable(),

                Select::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'application' => 'Application',
                        'user' => 'User',
                        'drawer' => 'Drawer',
                        'report' => 'Report',
                        'transaction' => 'Transaction',
                        'payment' => 'Payment',
                        'session' => 'Session',
                        'other' => 'Other',
                    ])
                    ->required(),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),

                Select::make('related_charge_id')
                    ->relationship('relatedCharge', 'stripe_charge_id')
                    ->searchable()
                    ->preload(),

                KeyValue::make('event_data')
                    ->label('Event Data')
                    ->columnSpanFull(),

                DateTimePicker::make('occurred_at')
                    ->label('Occurred At')
                    ->required()
                    ->default(now()),
            ]);
    }
}
