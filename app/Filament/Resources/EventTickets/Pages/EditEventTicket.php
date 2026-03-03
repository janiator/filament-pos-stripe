<?php

namespace App\Filament\Resources\EventTickets\Pages;

use App\Actions\EventTickets\MapWebflowItemToEventTicketData;
use App\Filament\Resources\EventTickets\Concerns\ProcessesEventTicketPaymentLinks;
use App\Filament\Resources\EventTickets\EventTicketResource;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEventTicket extends EditRecord
{
    use ProcessesEventTicketPaymentLinks;

    protected static string $resource = EventTicketResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['ticket_2_enabled'] = ((int) ($data['ticket_2_available'] ?? 0)) > 0 || ! empty($data['ticket_2_payment_link_id']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $store = Filament::getTenant();
        $rawState = $this->form->getRawState();

        return $this->processPaymentLinkModes(array_merge($rawState, $data), $store instanceof Store ? $store : null);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromWebflow')
                ->label('Sync from Webflow')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $this->record->webflowItem !== null)
                ->action(function (): void {
                    $item = $this->record->webflowItem;
                    if (! $item) {
                        return;
                    }
                    $data = MapWebflowItemToEventTicketData::map($item);
                    $contentOnly = [
                        'name', 'slug', 'description', 'image_url', 'event_date', 'event_time', 'venue',
                        'ticket_1_label', 'ticket_1_available', 'ticket_2_label', 'ticket_2_available',
                    ];
                    $update = array_intersect_key($data, array_flip($contentOnly));
                    $this->record->update($update);
                    $record = $this->record->fresh();
                    $formData = $record->toArray();
                    $formData['ticket_2_enabled'] = ((int) ($record->ticket_2_available ?? 0)) > 0 || ! empty($record->ticket_2_payment_link_id);
                    $this->form->fill($formData);
                    Notification::make()
                        ->title('Synced from Webflow')
                        ->body('Event details have been updated from the linked Webflow CMS item.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
