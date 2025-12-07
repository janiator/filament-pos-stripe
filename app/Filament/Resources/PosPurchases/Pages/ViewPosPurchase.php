<?php

namespace App\Filament\Resources\PosPurchases\Pages;

use App\Filament\Resources\PosPurchases\PosPurchaseResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewPosPurchase extends ViewRecord
{
    protected static string $resource = PosPurchaseResource::class;

    public function getTitle(): string | Htmlable
    {
        $chargeId = $this->record->stripe_charge_id ?? 'Cash #' . $this->record->id;
        return 'Purchase: ' . $chargeId;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_receipt')
                ->label('View Receipt')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('primary')
                ->url(fn () => $this->record->receipt
                    ? \App\Filament\Resources\Receipts\ReceiptResource::getUrl('preview', ['record' => $this->record->receipt])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->receipt !== null)
                ->tooltip('Open receipt in a new tab'),
        ];
    }
}
