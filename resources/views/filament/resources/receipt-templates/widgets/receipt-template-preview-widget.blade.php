<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Receipt preview
        </x-slot>
        <x-slot name="description">
            Preview with sample data. Save changes to update.
        </x-slot>
        <style>
            .receipt-preview-paper {
                max-width: 320px;
                margin: 0 auto;
                padding: 1.5rem;
                font-family: ui-monospace, monospace;
                font-size: 0.875rem;
                line-height: 1.35;
                background: #fff;
                color: #111;
                border: 1px solid #e5e7eb;
                border-radius: 0.25rem;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            }
            .dark .receipt-preview-paper {
                background: #f9fafb;
                color: #111;
                border-color: #d1d5db;
            }
            .receipt-preview-paper .receipt-line {
                word-break: break-word;
            }
            .receipt-preview-paper .receipt-image-placeholder {
                text-align: center;
                padding: 0.5rem 0;
                color: #6b7280;
                font-size: 0.75rem;
            }
            .receipt-preview-paper .receipt-barcode {
                letter-spacing: 0.15em;
                font-weight: 600;
            }
        </style>
        <div class="flex justify-center py-6 bg-gray-100 dark:bg-gray-900 rounded-lg">
            {!! $this->getPreviewHtml() !!}
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
