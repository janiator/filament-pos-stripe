<?php

namespace App\Filament\Resources\EventTickets\Pages;

use App\Actions\EventTickets\MapWebflowItemToEventTicketData;
use App\Filament\Resources\EventTickets\Concerns\ProcessesEventTicketPaymentLinks;
use App\Filament\Resources\EventTickets\EventTicketResource;
use App\Models\Store;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Positiv\FilamentWebflow\Models\WebflowItem;

class CreateEventTicket extends CreateRecord
{
    use ProcessesEventTicketPaymentLinks;

    protected static string $resource = EventTicketResource::class;

    public function mount(): void
    {
        parent::mount();

        $webflowItemId = request()->query('webflow_item_id');
        if ($webflowItemId && is_numeric($webflowItemId)) {
            $item = WebflowItem::find((int) $webflowItemId);
            if ($item) {
                $data = MapWebflowItemToEventTicketData::map($item);
                $data['webflow_item_id'] = $item->id;
                unset($data['ticket_1_sold'], $data['ticket_2_sold'], $data['is_sold_out']);
                $this->form->fill($data);
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $store = Filament::getTenant();
        if ($store instanceof Store) {
            $data['store_id'] = $store->getKey();
        }

        $rawState = $this->form->getRawState();
        $data = $this->processPaymentLinkModes(array_merge($rawState, $data), $store instanceof Store ? $store : null);

        return $data;
    }
}
