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
    'resources' => [
        'store' => [
            'tenant_menu' => 'Store information',
        ],
    ],
];
