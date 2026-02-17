<?php

namespace App\Filament\Resources\EventTickets;

use App\Actions\EventTickets\MapWebflowItemToEventTicketData;
use App\Enums\AddonType;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\EventTickets\Pages\CreateEventTicket;
use App\Filament\Resources\EventTickets\Pages\EditEventTicket;
use App\Filament\Resources\EventTickets\Pages\ListEventTickets;
use App\Models\Addon;
use App\Models\ConnectedPaymentLink;
use App\Models\EventTicket;
use App\Models\Store;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Support\WebflowFieldData;

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

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return false;
        }

        return Addon::query()
            ->where('store_id', $tenant->getKey())
            ->where('type', AddonType::EventTickets)
            ->where('is_active', true)
            ->exists();
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
            \Filament\Schemas\Components\Section::make('Webflow')->schema([
                \Filament\Forms\Components\Select::make('webflow_item_id')
                    ->label('Webflow CMS item')
                    ->required()
                    ->live()
                    ->relationship(
                        name: 'webflowItem',
                        titleAttribute: 'id',
                        modifyQueryUsing: function ($query) {
                            $tenantId = Filament::getTenant()?->id;
                            if ($query === null || $tenantId === null) {
                                return $query;
                            }

                            return $query->whereHas('collection.site.addon', fn ($sq) => $sq->where('store_id', $tenantId));
                        }
                    )
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $name = WebflowFieldData::displayNameFromFieldData($record->field_data ?? []);
                        if ($name !== '') {
                            return $name;
                        }

                        return $record->webflow_item_id ?? 'Item #'.$record->id;
                    })
                    ->searchable()
                    ->preload()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (! $state) {
                            return;
                        }
                        $item = WebflowItem::find($state);
                        if (! $item) {
                            return;
                        }
                        $data = MapWebflowItemToEventTicketData::map($item);
                        foreach ($data as $key => $value) {
                            if (! in_array($key, ['ticket_1_sold', 'ticket_2_sold', 'is_sold_out', 'is_archived'], true)) {
                                $set($key, $value);
                            }
                        }
                    }),
            ])->description('Select the Webflow CMS item for this event. Event details below will be filled from Webflow.'),
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
                \Filament\Forms\Components\Select::make('ticket_1_payment_link_mode')
                    ->label('Payment link')
                    ->options([
                        'existing' => 'Use existing payment link',
                        'new' => 'Create new payment link',
                    ])
                    ->default('existing')
                    ->live()
                    ->dehydrated(false),
                \Filament\Forms\Components\Select::make('ticket_1_payment_link_id')
                    ->label('Existing payment link')
                    ->options(fn () => static::paymentLinkOptionsForTenant())
                    ->searchable()
                    ->visible(fn (Get $get) => $get('ticket_1_payment_link_mode') === 'existing')
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (! $state) {
                            return;
                        }
                        $link = ConnectedPaymentLink::where('stripe_payment_link_id', $state)->first();
                        if ($link?->stripe_price_id) {
                            $set('ticket_1_price_id', $link->stripe_price_id);
                        }
                    }),
                \Filament\Forms\Components\TextInput::make('ticket_1_new_label')
                    ->label('New payment link: label')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => $get('ticket_1_payment_link_mode') === 'new')
                    ->dehydrated(false),
                \Filament\Forms\Components\TextInput::make('ticket_1_new_price_nok')
                    ->label('New payment link: price (NOK)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->visible(fn (Get $get) => $get('ticket_1_payment_link_mode') === 'new')
                    ->dehydrated(false),
                \Filament\Forms\Components\TextInput::make('ticket_1_available')
                    ->label('Max to sell')
                    ->numeric()
                    ->minValue(0),
                \Filament\Forms\Components\TextInput::make('ticket_1_sold')
                    ->label('Amount sold')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(true),
                \Filament\Forms\Components\Hidden::make('ticket_1_price_id'),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('Ticket 2')->schema([
                \Filament\Forms\Components\Toggle::make('ticket_2_enabled')
                    ->label('Enable ticket 2')
                    ->default(true)
                    ->live()
                    ->dehydrated(false),
                \Filament\Forms\Components\TextInput::make('ticket_2_label')->maxLength(255)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled')),
                \Filament\Forms\Components\Select::make('ticket_2_payment_link_mode')
                    ->label('Payment link')
                    ->options([
                        'existing' => 'Use existing payment link',
                        'new' => 'Create new payment link',
                    ])
                    ->default('existing')
                    ->live()
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled'))
                    ->dehydrated(false),
                \Filament\Forms\Components\Select::make('ticket_2_payment_link_id')
                    ->label('Existing payment link')
                    ->options(fn () => static::paymentLinkOptionsForTenant())
                    ->searchable()
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled') && $get('ticket_2_payment_link_mode') === 'existing')
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (! $state) {
                            return;
                        }
                        $link = ConnectedPaymentLink::where('stripe_payment_link_id', $state)->first();
                        if ($link?->stripe_price_id) {
                            $set('ticket_2_price_id', $link->stripe_price_id);
                        }
                    }),
                \Filament\Forms\Components\TextInput::make('ticket_2_new_label')
                    ->label('New payment link: label')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled') && $get('ticket_2_payment_link_mode') === 'new')
                    ->dehydrated(false),
                \Filament\Forms\Components\TextInput::make('ticket_2_new_price_nok')
                    ->label('New payment link: price (NOK)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled') && $get('ticket_2_payment_link_mode') === 'new')
                    ->dehydrated(false),
                \Filament\Forms\Components\TextInput::make('ticket_2_available')
                    ->label('Max to sell')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled')),
                \Filament\Forms\Components\TextInput::make('ticket_2_sold')
                    ->label('Amount sold')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled'))
                    ->dehydrated(true),
                \Filament\Forms\Components\Hidden::make('ticket_2_price_id'),
            ])->columns(2),
            \Filament\Forms\Components\Hidden::make('store_id')->default(fn () => Filament::getTenant()?->id),
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
            ->emptyStateHeading('No events yet')
            ->emptyStateDescription('Create one by linking a Webflow CMS item (Create button) or sync existing events from Webflow (Sync from Webflow). Make sure you have pulled your events collection under Webflow CMS first.')
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

    /**
     * @return array<string, string>
     */
    public static function paymentLinkOptionsForTenant(): array
    {
        $store = Filament::getTenant();
        if (! $store instanceof Store || ! $store->stripe_account_id) {
            return [];
        }

        return ConnectedPaymentLink::query()
            ->where('stripe_account_id', $store->stripe_account_id)
            ->where('active', true)
            ->get()
            ->mapWithKeys(fn (ConnectedPaymentLink $link) => [
                $link->stripe_payment_link_id => $link->name ?? $link->url ?? $link->stripe_payment_link_id,
            ])
            ->all();
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
