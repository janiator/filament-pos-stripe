<?php

namespace App\Filament\Resources\ReceiptTemplates\Widgets;

use App\Models\ReceiptTemplate;
use App\Services\ReceiptTemplateService;
use Filament\Widgets\Widget;

class ReceiptTemplatePreviewWidget extends Widget
{
    protected string $view = 'filament.resources.receipt-templates.widgets.receipt-template-preview-widget';

    protected int|string|array $columnSpan = 'full';

    public ?int $receiptTemplateId = null;

    public function getPreviewHtml(): string
    {
        if (! $this->receiptTemplateId) {
            return '<p class="text-gray-500 dark:text-gray-400">Save the template to see a preview.</p>';
        }

        $template = ReceiptTemplate::find($this->receiptTemplateId);
        if (! $template) {
            return '<p class="text-red-600 dark:text-red-400">Template not found.</p>';
        }

        return app(ReceiptTemplateService::class)->renderTemplatePreviewAsHtml(
            $template->content,
            $template->template_type
        );
    }
}
