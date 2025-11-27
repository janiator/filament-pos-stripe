<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;

class ViewReceiptPreview extends ViewRecord
{
    protected static string $resource = ReceiptResource::class;

    protected string $view = 'filament.resources.receipts.pages.view-receipt-preview';

    public function getTitle(): string | Htmlable
    {
        return 'Receipt Preview: ' . $this->record->receipt_number;
    }

    public function getPreviewUrl(): string
    {
        return route('receipts.preview', ['id' => $this->record->id]);
    }

    public function getXmlUrl(): string
    {
        return route('receipts.xml.simple', ['id' => $this->record->id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit Receipt')
                ->icon('heroicon-o-pencil')
                ->url(ReceiptResource::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),
            Action::make('download_xml')
                ->label('Download XML')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('receipts.xml.simple', ['id' => $this->record->id]))
                ->openUrlInNewTab()
                ->color('success'),
        ];
    }
}

