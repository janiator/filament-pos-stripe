<?php

namespace App\Filament\Resources\ReceiptTemplates\Pages;

use App\Filament\Resources\ReceiptTemplates\ReceiptTemplateResource;
use App\Filament\Resources\ReceiptTemplates\Widgets\ReceiptTemplatePreviewWidget;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\File;

class EditReceiptTemplate extends EditRecord
{
    protected static string $resource = ReceiptTemplateResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            ReceiptTemplatePreviewWidget::make(['receiptTemplateId' => $this->record->id]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetToDefault')
                ->label('Reset to Default')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset Template to Default')
                ->modalDescription('This will replace the current template with the default from the file. This action cannot be undone.')
                ->action('resetToDefault'),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        // Use record's template_type if not in form data (e.g., if field is disabled)
        if (! isset($data['template_type']) && $this->record) {
            $data['template_type'] = $this->record->template_type;
        }

        // Only auto-detect custom status if is_custom is not explicitly set in the form
        // This allows users to manually disable the custom flag
        if (! isset($data['is_custom'])) {
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

            $templateType = $data['template_type'] ?? null;
            $filename = $templateType ? ($templateFiles[$templateType] ?? null) : null;
            if ($filename && File::exists($templatePath.'/'.$filename)) {
                $defaultContent = File::get($templatePath.'/'.$filename);
                // Only mark as custom if content actually differs
                if (isset($data['content']) && trim($data['content']) !== trim($defaultContent)) {
                    $data['is_custom'] = true;
                } else {
                    $data['is_custom'] = false;
                }
            }
        } else {
            // Ensure boolean value
            $data['is_custom'] = (bool) $data['is_custom'];
        }

        return $data;
    }

    public function resetToDefault(): void
    {
        $record = $this->record;
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

        $filename = $templateFiles[$record->template_type] ?? null;

        if (! $filename) {
            Notification::make()
                ->danger()
                ->title('Template type not found')
                ->send();

            return;
        }

        $filePath = $templatePath.'/'.$filename;

        if (! File::exists($filePath)) {
            Notification::make()
                ->danger()
                ->title('Default template file not found')
                ->body("File: {$filename}")
                ->send();

            return;
        }

        $defaultContent = File::get($filePath);

        $record->update([
            'content' => $defaultContent,
            'is_custom' => false,
            'version' => '1.0',
            'updated_by' => auth()->id(),
        ]);

        $this->form->fill($record->toArray());

        Notification::make()
            ->success()
            ->title('Template reset to default')
            ->body('The template has been reset to the default version from the file.')
            ->send();
    }
}
