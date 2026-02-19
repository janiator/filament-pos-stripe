<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use App\Services\ReceiptTemplateService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewReceiptPreview extends ViewRecord
{
    protected static string $resource = ReceiptResource::class;

    protected string $view = 'filament.resources.receipts.pages.view-receipt-preview';

    public function getTitle(): string|Htmlable
    {
        return 'Receipt Preview: '.$this->record->receipt_number;
    }

    public function getXmlUrl(): string
    {
        return route('receipts.xml.simple', ['id' => $this->record->id]);
    }

    /**
     * Returns receipt XML formatted for readable preview: pretty-printed and base64 image content replaced with a placeholder.
     */
    public function getFormattedReceiptXml(): string
    {
        $raw = app(ReceiptTemplateService::class)->renderReceipt($this->record);

        $placeholder = '[Base64 image data omitted for display]';
        $xml = preg_replace('/<image([^>]*)>\K[\s\S]*?(?=<\/image>)/', $placeholder, $raw);

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (@$dom->loadXML($xml) === false) {
            return $xml;
        }

        return $dom->saveXML();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_xml')
                ->label('Download XML')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(route('receipts.xml.simple', ['id' => $this->record->id]))
                ->openUrlInNewTab()
                ->color('success'),
        ];
    }
}
