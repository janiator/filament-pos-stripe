<?php

return [
    'connected_products' => [
        'stats' => [
            'total_products' => 'Total products',
            'inventory_units' => 'Inventory (variants)',
            'average_price' => 'Average price',
        ],
        'table' => [
            'image' => 'Image',
            'brand' => 'Brand',
            'visibility' => 'Visibility',
            'sku' => 'SKU',
            'stock' => 'Stock',
        ],
        'actions' => [
            'row' => 'Actions',
            'toggle_active' => 'Visibility',
            'activate' => 'Set active',
            'deactivate' => 'Set inactive',
        ],
        'notifications' => [
            'status_updated_title' => 'Product status updated',
            'status_updated_body' => 'The product active status has been updated successfully.',
        ],
        'form' => [
            'track_inventory' => 'Track inventory',
            'track_inventory_help' => 'When enabled, variant quantities are enforced at checkout if the Inventory add-on is on for this store.',
        ],
    ],
    'tripletex' => [
        'payout_voucher_preview_description' => 'Ledger lines for this Stripe payout. Turn on account resolution to call Tripletex and include the exact JSON for POST /ledger/voucher. Ticket revenue from the POS is posted on Tripletex when a register session closes (Z-report voucher preview), not here. Advance or web ticket lines appear on this payout only when “External ticket sales” is enabled in Tripletex ledger settings and charges without a POS session satisfy the metadata (and optional description regex) rules; metadata from the Stripe charge and from the payout balance-transaction mirror is combined when matching.',
    ],
    'resources' => [
        'store' => [
            'tenant_menu' => 'Store information',
        ],
    ],
];
