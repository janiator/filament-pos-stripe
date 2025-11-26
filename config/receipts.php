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
    ],
];

