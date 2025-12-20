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

1. **Stripe IDs**: By default, Stripe IDs are excluded from exports since they are server-specific. Products and variants will need to be synced to Stripe after import, which will create new Stripe IDs.

2. **Store Matching**: The import process matches products and collections by name. Make sure your store names are consistent or use the `--store` option to specify the target store.

3. **Media Files**: All media files are copied to the `storage/app/public` directory. Make sure the storage link is set up (`php artisan storage:link`).

4. **Existing Data**: By default, existing products and collections are skipped. Use `--update` to update existing records instead.

5. **Vendor IDs**: Vendor IDs are preserved if they exist in both environments. If a vendor doesn't exist in the target environment, the `vendor_id` will be set to null.

6. **Stripe Sync**: After importing, you may want to sync products to Stripe to create Stripe products and prices:
   ```bash
   php artisan stripe:sync-products
   ```

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
- Products imported without Stripe IDs will need to be synced to Stripe
- Use the Filament admin panel or sync commands to create Stripe products

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

The manifest.json structure:
```json
{
  "export_date": "2024-01-15T12:34:56Z",
  "store": {
    "id": 1,
    "name": "Test Store",
    "slug": "test-store"
  },
  "collections": [...],
  "products": [...],
  "product_collection_relations": [...],
  "include_stripe_ids": false
}
```



