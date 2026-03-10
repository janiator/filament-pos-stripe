<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Receipt Template Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for receipt template rendering. Currently supports
    | Epson ePOS XML format for TM-m30III printers.
    |
    */

    'printer_type' => env('RECEIPT_PRINTER_TYPE', 'epson'),

    'template_path' => env('RECEIPT_TEMPLATE_PATH', 'resources/receipt-templates'),

    'epson' => [
        'template_path' => base_path('resources/receipt-templates/epson'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt Numbering
    |--------------------------------------------------------------------------
    |
    | Format for receipt numbers. Available placeholders:
    | {store_id} - Store ID
    | {type} - Receipt type prefix (S, R, C, etc.)
    | {number} - Sequential number (zero-padded)
    |
    */

    'number_format' => '{store_id}-{type}-{number:06d}',

    /*
    |--------------------------------------------------------------------------
    | VAT Configuration
    |--------------------------------------------------------------------------
    |
    | Default VAT rate for Norway (25%)
    |
    */

    'default_vat_rate' => 25.0,

    /*
     |--------------------------------------------------------------------------
     | Reprint Limits (Kassasystemforskriften § 2-8-4 Compliance)
     |--------------------------------------------------------------------------
     |
     | According to Kassasystemforskriften § 2-8-4:
     | - Original sales receipts can only be printed once (no reprints allowed)
     | - Only one copy receipt can be printed per original receipt
     | - STEB receipts can be printed multiple times (exception for tax-free shops)
     |
     | This configuration applies to STEB receipts only.
     | Other receipt types follow strict single-print rules.
     |
     */

    'max_reprints_steb' => env('RECEIPT_MAX_REPRINTS_STEB', 10),

    /*
     |--------------------------------------------------------------------------
     | Store logo on receipts
     |--------------------------------------------------------------------------
     |
     | Uploaded store logos are scaled to fit within these bounds (in printer
     | dots, 203 dpi). Keeps the logo a reasonable header size; full receipt
     | width is 576 dots (80mm at 203 dpi). When narrower than receipt width,
     | the logo is centered unless logo_center_on_receipt is false.
     |
     */

    'logo_max_width_dots' => env('RECEIPT_LOGO_MAX_WIDTH_DOTS', 384),

    'logo_max_height_dots' => env('RECEIPT_LOGO_MAX_HEIGHT_DOTS', 200),

    'receipt_width_dots' => env('RECEIPT_WIDTH_DOTS', 576),

    'logo_center_on_receipt' => env('RECEIPT_LOGO_CENTER', true),

    /*
     |--------------------------------------------------------------------------
     | Receipt Types
     |--------------------------------------------------------------------------
     |
     | Available receipt types and their prefixes for numbering
     |
     */

    'types' => [
        'sales' => [
            'prefix' => 'S',
            'label' => 'Salgskvittering',
        ],
        'return' => [
            'prefix' => 'R',
            'label' => 'Returkvittering',
        ],
        'copy' => [
            'prefix' => 'C',
            'label' => 'Kopikvittering',
        ],
        'steb' => [
            'prefix' => 'STEB',
            'label' => 'STEB-kvittering',
        ],
        'provisional' => [
            'prefix' => 'P',
            'label' => 'Foreløpig kvittering',
        ],
        'training' => [
            'prefix' => 'T',
            'label' => 'Treningskvittering',
        ],
        'delivery' => [
            'prefix' => 'D',
            'label' => 'Utleveringskvittering',
        ],
        'freeticket' => [
            'prefix' => 'FT',
            'label' => 'Gratisbillett',
        ],
        'ticket' => [
            'prefix' => 'TKT',
            'label' => 'Billett',
        ],
        'correction' => [
            'prefix' => 'CORR',
            'label' => 'Korrigeringskvittering',
        ],
    ],
];
