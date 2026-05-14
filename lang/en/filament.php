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
        'external_ticket_metadata_keys_placeholder' => 'Leave empty for default (booking_id, eventKey, or event_key)',
        'external_ticket_metadata_keys_help' => 'Leave empty: a charge qualifies if any of booking_id, eventKey, or event_key is non-empty in merged metadata (OR). If you enter keys, every listed key must be present (AND). Comma-separated.',
        'vat_types_section_description' => 'Optional overrides per rate and for the output-VAT line. If you leave a field empty, the voucher uses the VAT type returned on each Tripletex ledger account when accounts are resolved (GET /ledger/account), which matches locked accounts. Fill a value only when you need a different Tripletex vatType id than the account default.',
    ],
    'resources' => [
        'store' => [
            'tenant_menu' => 'Store information',
        ],
        'store_stripe_balance_transaction' => [
            'actions' => [
                'sync' => 'Sync from Stripe',
                'sync_heading' => 'Sync balance transactions from Stripe',
                'sync_description_tenant' => 'Queues one background job per store (stripe-sync queue). Refresh when workers finish.',
                'sync_description_all' => 'Queues background jobs for every connected Stripe account. May take a while to complete.',
            ],
            'notifications' => [
                'sync_no_stores_title' => 'No stores to sync',
                'sync_no_stores_body' => 'No store with a Stripe Connect account was found for this context.',
                'sync_batch_name' => 'Filament: Stripe balance transactions',
                'sync_queued_title' => 'Balance transaction sync queued',
                'sync_queued_body' => ':count job(s) were queued (batch #:batch). Refresh this page after workers finish.',
            ],
        ],
        'store_stripe_payout' => [
            'actions' => [
                'sync_balance_for_payout' => 'Sync Stripe balance rows for this payout',
                'sync_balance_for_payout_heading' => 'Sync balance transactions for this payout?',
                'sync_balance_for_payout_description' => 'Fetches balance transactions from Stripe filtered to this payout id (reliable for fees and Tripletex payout voucher detail). Runs on the stripe-sync queue; refresh the Tripletex preview after the job finishes.',
            ],
            'notifications' => [
                'sync_balance_for_payout_queued_title' => 'Payout balance sync queued',
                'sync_balance_for_payout_queued_body' => 'Stripe balance transactions for :payout will be mirrored shortly.',
                'sync_balance_for_payout_missing_store_title' => 'Cannot queue sync',
                'sync_balance_for_payout_missing_store_body' => 'This payout has no store with a connected Stripe account.',
            ],
        ],
    ],
];
