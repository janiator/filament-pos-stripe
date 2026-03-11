# Product and Collection Export/Import

This feature allows you to export products, collections (categories), variants, and all associated assets from one server and import them into another server. This is particularly useful for transferring data from a test/staging environment to production.

## Commands

### Export: `products:export`

Exports all products, collections, variants, and media files to a zip archive.

**Usage:**
```bash
php artisan products:export [options]
```

**Options:**
- `--store=`: Store slug or ID to export products from (optional, uses first store if not specified)
- `--output=`: Output file path (optional, defaults to `storage/app/products-export-{timestamp}.zip`)
- `--include-stripe-ids`: Include Stripe IDs in export (not recommended for cross-server transfer)

**Example:**
```bash
# Export from default store
php artisan products:export

# Export from specific store
php artisan products:export --store=my-store

# Export to specific file
php artisan products:export --output=/path/to/export.zip
```

**What gets exported:**
- All collections (categories) with their metadata and images
- All products with their metadata, pricing, and images
- All product variants with their metadata and images
- Product-collection relationships (which products belong to which collections)
- All media files (product images, collection images, variant images)

**Note:** By default, Stripe IDs are excluded from the export since they are server-specific. If you need to preserve Stripe IDs (e.g., for backup purposes), use the `--include-stripe-ids` flag.

### Bulk Export (Filament)

From the **Products** list in the Filament admin (per store), you can select one or more products and use the **Export as ZIP** bulk action. This creates a zip file containing only the selected products, their variants, linked collections, and media. The file format is the same as the full export, so you can import it via the **Import ZIP** page or `products:import` in another store or database.

- Select the products you want to transfer, then choose **Export as ZIP** from the bulk actions menu.
- A success notification appears with a **Download ZIP** button; the download link is valid for 10 minutes.
- Use **Import ZIP** (or `products:import`) on the target store to import the file.

### Import: `products:import`

Imports products, collections, variants, and media files from a zip archive.

**Usage:**
```bash
php artisan products:import <file> [options]
```

**Arguments:**
- `file`: Path to the export zip file

**Options:**
- `--store=`: Store slug or ID to import products to (optional, uses first store if not specified)
- `--update`: Update existing products/collections instead of skipping them
- `--dry-run`: Show what would be imported without actually importing

**Example:**
```bash
# Import to default store (skip existing)
php artisan products:import storage/app/products-export-2024-01-15-123456.zip

# Import to specific store with update mode
php artisan products:import storage/app/products-export-2024-01-15-123456.zip --store=my-store --update

# Dry run to see what would be imported
php artisan products:import storage/app/products-export-2024-01-15-123456.zip --dry-run
```

**Import Behavior:**
- **Collections**: Matched by name. If a collection with the same name exists and `--update` is not used, it will be skipped.
- **Products**: Matched by name. If a product with the same name exists and `--update` is not used, it will be skipped.
- **Variants**: Matched by SKU. If a variant with the same SKU exists, it will be updated.
- **Product-Collection Relations**: Linked based on product and collection names. Existing relations are not duplicated.
- **Media Files**: All images are copied to the appropriate storage locations.

## Workflow: Test to Production

### Step 1: Export from Test Server

On your test/staging server:

```bash
php artisan products:export --store=test-store --output=products-export.zip
```

This creates a zip file containing:
- `manifest.json` - All product and collection data
- `media/` - All image files organized by type

### Step 2: Transfer the File

Transfer the zip file to your production server using your preferred method (SCP, SFTP, etc.):

```bash
scp products-export.zip production-server:/path/to/storage/app/
```

### Step 3: Import to Production Server

On your production server:

```bash
# First, do a dry run to see what will be imported
php artisan products:import storage/app/products-export.zip --store=production-store --dry-run

# If everything looks good, run the actual import
php artisan products:import storage/app/products-export.zip --store=production-store --update
```

## Important Notes

1. **Stripe IDs and env-specific data**: Exports **never** include Stripe product/price IDs or store IDs by default (use `--include-stripe-ids` on the export command only for same-environment backup). The import ignores any Stripe IDs in the zip when the manifest has `include_stripe_ids: false`, so cross-env transfers are safe.

2. **Stripe sync after import**: When you import via the Filament **Import ZIP** page, jobs are queued automatically to verify each product on the connected Stripe account and create or recreate it if missing. Ensure a queue worker is running (`php artisan queue:work` or Horizon). You can also run manually:
   - `php artisan products:ensure-stripe-ids {store?}` — queue a check for all products (in the given store or all stores) that lack a Stripe ID or default price; each job verifies the product exists on the connected account and recreates it if not.
   - `php artisan products:ensure-stripe-ids {store?} --verify-all` — same but also verify products that already have a Stripe ID (recreate if deleted on Stripe).
   - `php artisan products:ensure-stripe-ids {store?} --sync` — run the checks synchronously instead of queueing.
   - `php artisan stripe:sync-products-to-stripe {store?}` — create all products/variants without Stripe IDs in Stripe (no existence check).

3. **Store Matching**: The import process matches products and collections by name. Use the `--store` option to specify the target store.

4. **Media Files**: All media files are copied to the `storage/app/public` directory. Make sure the storage link is set up (`php artisan storage:link`).

5. **Existing Data**: By default, existing products and collections are skipped. Use `--update` to update existing records instead.

6. **Vendor IDs**: Vendors are matched or created by name in the target store; vendor IDs from the export are not used when transferring between environments.

## Troubleshooting

### Import fails with "Store not found"
- Make sure the store exists in the target environment
- Use the `--store` option with the correct store slug or ID

### Images not showing after import
- Make sure the storage link is set up: `php artisan storage:link`
- Check file permissions on `storage/app/public`
- Verify that media files were extracted from the zip correctly

### Products not linking to collections
- Check that both product and collection names match exactly
- Verify the manifest.json contains the product-collection relations

### Stripe sync issues after import
- Ensure a queue worker is running so `EnsureProductStripeIdJob` runs after import.
- To run checks manually: `php artisan products:ensure-stripe-ids --sync` or `php artisan stripe:sync-products-to-stripe`.

## File Structure

The export zip file contains:

```
products-export-YYYY-MM-DD-HHMMSS.zip
├── manifest.json                    # All data (collections, products, variants, relations)
└── media/
    ├── collections/
    │   └── collection-image.jpg
    ├── products/
    │   └── product-{id}-image.jpg
    └── variants/
        └── variant-{id}-image.jpg
```

The manifest.json structure (when `include_stripe_ids` is false, `store.id` is omitted):
```json
{
  "export_date": "2024-01-15T12:34:56Z",
  "store": {
    "name": "Test Store",
    "slug": "test-store"
  },
  "collections": [...],
  "products": [...],
  "product_collection_relations": [...],
  "include_stripe_ids": false
}
```




