<?php

namespace App\Filament\Pages;

use App\Actions\EventTickets\MapWebflowItemToEventTicketData;
use App\Filament\Resources\EventTickets\Concerns\ProcessesEventTicketPaymentLinks;
use App\Filament\Resources\EventTickets\EventTicketResource;
use App\Models\ConnectedPaymentLink;
use App\Models\EventTicket;
use App\Models\Store;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Facades\FilamentView;
use Positiv\FilamentWebflow\Filament\Pages\WebflowCollectionItemsPage;
use Positiv\FilamentWebflow\Filament\Pages\WebflowItemEditPage as BaseWebflowItemEditPage;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Support\WebflowSchemaFormBuilder;

class WebflowItemEditPage extends BaseWebflowItemEditPage
{
    use ProcessesEventTicketPaymentLinks;

    /** @var array<string, mixed> */
    public array $eventTicketData = [];

    public function mount(int|string $item): void
    {
        parent::mount($item);

        $record = $this->getRecord();
        $collection = $record?->collection;
        if (! $collection instanceof WebflowCollection || ! $collection->use_for_event_tickets) {
            return;
        }

        $tenant = Filament::getTenant();
        if (! $tenant instanceof Store) {
            return;
        }

        $eventTicket = EventTicket::query()
            ->where('webflow_item_id', $record->id)
            ->where('store_id', $tenant->id)
            ->first();

        if ($eventTicket) {
            $this->eventTicketData = $eventTicket->toArray();
            $this->eventTicketData['ticket_2_enabled'] = ((int) ($eventTicket->ticket_2_available ?? 0)) > 0 || ! empty($eventTicket->ticket_2_payment_link_id);
        } else {
            $this->eventTicketData = array_merge(
                MapWebflowItemToEventTicketData::map($record),
                [
                    'store_id' => $tenant->id,
                    'webflow_item_id' => $record->id,
                    'ticket_2_enabled' => true,
                ]
            );
            unset($this->eventTicketData['ticket_1_sold'], $this->eventTicketData['ticket_2_sold'], $this->eventTicketData['is_sold_out']);
        }

        $this->data['_eventTicket'] = $this->eventTicketData;
        $formSchema = $this->getSchema('form');
        if ($formSchema !== null) {
            $formSchema->fill($this->data);
        }
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        $record = $this->getRecord();
        if (! $record) {
            return $schema->components([]);
        }

        $collection = $record->collection;
        if (! $collection instanceof WebflowCollection) {
            return $schema->components([]);
        }

        $this->data = $this->dataWithSchemaKeys($this->data);
        $components = WebflowSchemaFormBuilder::build($collection);
        if ($collection->use_for_event_tickets) {
            $components[] = Section::make('Event ticket')
                ->schema($this->getEventTicketFormSchema())
                ->statePath('_eventTicket')
                ->collapsible()
                ->columns(2);
        }

        if (empty($components)) {
            return $schema->statePath('data')->components([]);
        }

        return $schema
            ->statePath('data')
            ->model($record)
            ->components($components);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function getEventTicketFormSchema(): array
    {
        return [
            Section::make('Event details')->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('slug')->maxLength(255),
                Textarea::make('description')->rows(4),
                TextInput::make('image_url')->url()->maxLength(65535),
                DateTimePicker::make('event_date'),
                TextInput::make('event_time')->maxLength(255),
                TextInput::make('venue')->maxLength(255),
            ])->columns(2),
            Section::make('Ticket 1')->schema([
                TextInput::make('ticket_1_label')->default('Billett 1')->maxLength(255),
                Select::make('ticket_1_payment_link_mode')
                    ->label('Payment link')
                    ->options([
                        'existing' => 'Use existing payment link',
                        'new' => 'Create new payment link',
                    ])
                    ->default('existing')
                    ->live()
                    ->dehydrated(false),
                Select::make('ticket_1_payment_link_id')
                    ->label('Existing payment link')
                    ->options(fn () => EventTicketResource::paymentLinkOptionsForTenant())
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
                TextInput::make('ticket_1_new_label')
                    ->label('New payment link: label')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => $get('ticket_1_payment_link_mode') === 'new')
                    ->dehydrated(false),
                TextInput::make('ticket_1_new_price_nok')
                    ->label('New payment link: price (NOK)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->visible(fn (Get $get) => $get('ticket_1_payment_link_mode') === 'new')
                    ->dehydrated(false),
                TextInput::make('ticket_1_available')->label('Max to sell')->numeric()->minValue(0),
                TextInput::make('ticket_1_sold')->label('Amount sold')->numeric()->minValue(0)->default(0)->dehydrated(true),
                Hidden::make('ticket_1_price_id'),
            ])->columns(2),
            Section::make('Ticket 2')->schema([
                Toggle::make('ticket_2_enabled')->label('Enable ticket 2')->default(true)->live()->dehydrated(false),
                TextInput::make('ticket_2_label')->maxLength(255)->visible(fn (Get $get) => (bool) $get('ticket_2_enabled')),
                Select::make('ticket_2_payment_link_mode')
                    ->label('Payment link')
                    ->options([
                        'existing' => 'Use existing payment link',
                        'new' => 'Create new payment link',
                    ])
                    ->default('existing')
                    ->live()
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled'))
                    ->dehydrated(false),
                Select::make('ticket_2_payment_link_id')
                    ->label('Existing payment link')
                    ->options(fn () => EventTicketResource::paymentLinkOptionsForTenant())
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
                TextInput::make('ticket_2_new_label')
                    ->label('New payment link: label')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled') && $get('ticket_2_payment_link_mode') === 'new')
                    ->dehydrated(false),
                TextInput::make('ticket_2_new_price_nok')
                    ->label('New payment link: price (NOK)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled') && $get('ticket_2_payment_link_mode') === 'new')
                    ->dehydrated(false),
                TextInput::make('ticket_2_available')
                    ->label('Max to sell')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled')),
                TextInput::make('ticket_2_sold')
                    ->label('Amount sold')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->visible(fn (Get $get) => (bool) $get('ticket_2_enabled'))
                    ->dehydrated(true),
                Hidden::make('ticket_2_price_id'),
            ])->columns(2),
            Hidden::make('store_id')->default(fn () => Filament::getTenant()?->id),
            Hidden::make('webflow_item_id')->default(fn () => $this->getRecord()?->id),
            Toggle::make('is_archived')->default(false),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendNotification = true): void
    {
        $record = $this->getRecord();
        $collection = $record?->collection;
        $formSchema = $this->getSchema('form');
        $fullState = $formSchema ? $formSchema->getState() : $this->data;

        $eventTicketState = $fullState['_eventTicket'] ?? null;
        if ($eventTicketState !== null && $collection instanceof WebflowCollection && $collection->use_for_event_tickets) {
            unset($fullState['_eventTicket']);
            $tenant = Filament::getTenant();
            if ($tenant instanceof Store) {
                $eventTicketState = $this->processPaymentLinkModes($eventTicketState, $tenant);
                $eventTicketState['store_id'] = $tenant->id;
                $eventTicketState['webflow_item_id'] = $record->id;
                if (! ($eventTicketState['ticket_2_enabled'] ?? true)) {
                    $eventTicketState['ticket_2_label'] = null;
                    $eventTicketState['ticket_2_available'] = 0;
                    $eventTicketState['ticket_2_payment_link_id'] = null;
                    $eventTicketState['ticket_2_price_id'] = null;
                }
                unset($eventTicketState['ticket_2_enabled'], $eventTicketState['ticket_1_payment_link_mode'], $eventTicketState['ticket_1_new_label'], $eventTicketState['ticket_1_new_price_nok'], $eventTicketState['ticket_2_payment_link_mode'], $eventTicketState['ticket_2_new_label'], $eventTicketState['ticket_2_new_price_nok']);
                $eventTicket = EventTicket::query()->updateOrCreate(
                    [
                        'webflow_item_id' => $record->id,
                        'store_id' => $tenant->id,
                    ],
                    $eventTicketState
                );
                $eventTicket->updateSoldOutStatus();
                $this->syncPaymentLinkQuantities($eventTicket);
            }
        } else {
            unset($fullState['_eventTicket']);
        }

        $this->data = $fullState;
        $record->field_data = is_array($fullState) ? $fullState : [];
        $this->syncMediaUrlsToFieldData($record);
        $record->save();

        if ($shouldSendNotification) {
            Notification::make()
                ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
                ->success()
                ->send();
            $user = auth()->user();
            if ($user) {
                Notification::make()
                    ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
                    ->success()
                    ->sendToDatabase($user);
            }
        }

        if ($shouldRedirect) {
            $url = WebflowCollectionItemsPage::getUrl(
                ['collection' => $record->collection?->id],
                true,
                null,
                Filament::getTenant()
            );
            $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = parent::getHeaderActions();
        $record = $this->getRecord();
        $collection = $record?->collection;
        if ($collection instanceof WebflowCollection && $collection->use_for_event_tickets && $record) {
            $actions[] = \Filament\Actions\Action::make('syncFromWebflow')
                ->label('Sync from Webflow')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $item = $this->getRecord();
                    if (! $item) {
                        return;
                    }
                    $tenant = Filament::getTenant();
                    if (! $tenant instanceof Store) {
                        return;
                    }
                    $eventTicket = EventTicket::query()
                        ->where('webflow_item_id', $item->id)
                        ->where('store_id', $tenant->id)
                        ->first();
                    $data = MapWebflowItemToEventTicketData::map($item);
                    $contentOnly = [
                        'name', 'slug', 'description', 'image_url', 'event_date', 'event_time', 'venue',
                        'ticket_1_label', 'ticket_1_available', 'ticket_2_label', 'ticket_2_available',
                    ];
                    $update = array_intersect_key($data, array_flip($contentOnly));
                    if ($eventTicket) {
                        $eventTicket->update($update);
                        $this->eventTicketData = $eventTicket->fresh()->toArray();
                        $this->eventTicketData['ticket_2_enabled'] = ((int) ($eventTicket->ticket_2_available ?? 0)) > 0 || ! empty($eventTicket->ticket_2_payment_link_id);
                    } else {
                        $this->eventTicketData = array_merge($this->eventTicketData, $update, [
                            'store_id' => $tenant->id,
                            'webflow_item_id' => $item->id,
                            'ticket_2_enabled' => true,
                        ]);
                    }
                    $formSchema = $this->getSchema('form');
                    if ($formSchema !== null) {
                        $formSchema->fill(['_eventTicket' => $this->eventTicketData]);
                    }
                    Notification::make()
                        ->title('Synced from Webflow')
                        ->body('Event details have been updated from the Webflow CMS item.')
                        ->success()
                        ->send();
                });
        }

        return $actions;
    }

    /**
     * Sync quantity_max and quantity_sold to ConnectedPaymentLink for the event ticket's payment links.
     */
    protected function syncPaymentLinkQuantities(EventTicket $eventTicket): void
    {
        foreach (['ticket_1', 'ticket_2'] as $slot) {
            $linkId = $eventTicket->{"{$slot}_payment_link_id"};
            if (empty($linkId)) {
                continue;
            }
            $link = ConnectedPaymentLink::where('stripe_payment_link_id', $linkId)->first();
            if ($link) {
                $link->quantity_max = $eventTicket->{"{$slot}_available"};
                $link->quantity_sold = (int) ($eventTicket->{"{$slot}_sold"} ?? 0);
                $link->save();
            }
        }
    }
}
