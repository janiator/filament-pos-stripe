<?php

namespace App\Filament\Resources\EventTickets;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\EventTickets\Pages\CreateEventTicket;
use App\Filament\Resources\EventTickets\Pages\EditEventTicket;
use App\Filament\Resources\EventTickets\Pages\ListEventTickets;
use App\Models\EventTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EventTicketResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = EventTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.payments');
    }

    protected static ?string $slug = 'event-tickets';

    public static function getModelLabel(): string
    {
        return 'Event Ticket';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Event Tickets';
    }

    public static function getNavigationLabel(): string
    {
        return 'Event Tickets';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                $query->where('store_id', $tenant->id);
            }
        } catch (\Throwable $e) {
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Event details')->schema([
                \Filament\Forms\Components\TextInput::make('name')->required()->maxLength(255),
                \Filament\Forms\Components\TextInput::make('slug')->maxLength(255),
                \Filament\Forms\Components\Textarea::make('description')->rows(4),
                \Filament\Forms\Components\TextInput::make('image_url')->url()->maxLength(65535),
                \Filament\Forms\Components\DateTimePicker::make('event_date'),
                \Filament\Forms\Components\TextInput::make('event_time')->maxLength(255),
                \Filament\Forms\Components\TextInput::make('venue')->maxLength(255),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('Ticket 1')->schema([
                \Filament\Forms\Components\TextInput::make('ticket_1_label')->default('Billett 1')->maxLength(255),
                \Filament\Forms\Components\TextInput::make('ticket_1_available')->numeric()->minValue(0),
                \Filament\Forms\Components\TextInput::make('ticket_1_payment_link_id')->maxLength(255),
                \Filament\Forms\Components\TextInput::make('ticket_1_price_id')->maxLength(255),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('Ticket 2')->schema([
                \Filament\Forms\Components\TextInput::make('ticket_2_label')->maxLength(255),
                \Filament\Forms\Components\TextInput::make('ticket_2_available')->numeric()->minValue(0),
                \Filament\Forms\Components\TextInput::make('ticket_2_payment_link_id')->maxLength(255),
                \Filament\Forms\Components\TextInput::make('ticket_2_price_id')->maxLength(255),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('Webflow')->schema([
                \Filament\Forms\Components\Select::make('webflow_item_id')
                    ->label('Webflow CMS item')
                    ->relationship(
                        name: 'webflowItem',
                        titleAttribute: 'id',
                        modifyQueryUsing: function ($query) {
                            $tenantId = \Filament\Facades\Filament::getTenant()?->id;
                            if ($query === null || $tenantId === null) {
                                return $query;
                            }

                            return $query->whereHas('collection.site', fn ($sq) => $sq->where('store_id', $tenantId));
                        }
                    )
                    ->searchable()
                    ->preload(),
            ]),
            \Filament\Forms\Components\Hidden::make('store_id')->default(fn () => \Filament\Facades\Filament::getTenant()?->id),
            \Filament\Forms\Components\Toggle::make('is_archived')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('event_date')->dateTime()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('ticket_1_sold')->label('T1 sold')->suffix(fn ($record) => $record->ticket_1_available ? ' / '.$record->ticket_1_available : ''),
                \Filament\Tables\Columns\TextColumn::make('ticket_2_sold')->label('T2 sold')->suffix(fn ($record) => $record->ticket_2_available ? ' / '.$record->ticket_2_available : ''),
                \Filament\Tables\Columns\IconColumn::make('is_sold_out')->label('Sold out')->boolean(),
                \Filament\Tables\Columns\IconColumn::make('is_archived')->boolean(),
            ])
            ->defaultSort('event_date', 'desc')
            ->filters([])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventTickets::route('/'),
            'create' => CreateEventTicket::route('/create'),
            'edit' => EditEventTicket::route('/{record}/edit'),
        ];
    }
}
