<?php

namespace App\Filament\Resources\ReceiptTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class ReceiptTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Group::make([
                            Select::make('store_id')
                                ->label('Store')
                                ->helperText('Leave empty for global template (applies to all stores)')
                                ->relationship('store', 'name')
                                ->searchable()
                                ->preload()
                                ->placeholder('Global (all stores)')
                                ->nullable(),
                            
                            Select::make('template_type')
                                ->label('Template Type')
                                ->options([
                                    'sales' => 'Sales Receipt',
                                    'return' => 'Return Receipt',
                                    'copy' => 'Copy Receipt',
                                    'steb' => 'STEB Receipt',
                                    'provisional' => 'Provisional Receipt',
                                    'training' => 'Training Receipt',
                                    'delivery' => 'Delivery Receipt',
                                ])
                                ->required()
                                ->disabled(fn ($record) => $record !== null)
                                ->dehydrated(fn ($record) => $record === null),
                            
                            Toggle::make('is_custom')
                                ->label('Custom Template')
                                ->helperText('Mark as custom to prevent overwriting when seeding from files')
                                ->default(false)
                                ->disabled(fn ($record) => $record === null || !$record->is_custom),
                        ]),
                    ]),
                
                Textarea::make('content')
                    ->label('Template Content (XML)')
                    ->required()
                    ->rows(30)
                    ->extraAttributes([
                        'style' => 'font-family: monospace; font-size: 13px;',
                    ])
                    ->helperText('Epson ePOS XML template with Mustache syntax for dynamic content')
                    ->columnSpanFull(),
            ]);
    }
}
