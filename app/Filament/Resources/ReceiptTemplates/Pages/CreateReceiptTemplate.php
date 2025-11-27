<?php

namespace App\Filament\Resources\ReceiptTemplates\Pages;

use App\Filament\Resources\ReceiptTemplates\ReceiptTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\File;

class CreateReceiptTemplate extends CreateRecord
{
    protected static string $resource = ReceiptTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If no content provided and template type is set, try to load from file
        if (empty($data['content']) && !empty($data['template_type'])) {
            $templatePath = base_path('resources/receipt-templates/epson');
            $templateFiles = [
                'sales' => 'sales-receipt.xml',
                'return' => 'return-receipt.xml',
                'copy' => 'copy-receipt.xml',
                'steb' => 'steb-receipt.xml',
                'provisional' => 'provisional-receipt.xml',
                'training' => 'training-receipt.xml',
                'delivery' => 'delivery-receipt.xml',
            ];

            $filename = $templateFiles[$data['template_type']] ?? null;
            if ($filename) {
                $filePath = $templatePath . '/' . $filename;
                if (File::exists($filePath)) {
                    $data['content'] = File::get($filePath);
                    $data['version'] = '1.0';
                }
            }
        }

        // Set created_by and updated_by
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
