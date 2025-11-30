# Variant Stripe Products Implementation

## Overview

Variants are now synced to Stripe as **separate Products** (not just Prices), while still being displayed as related variants in Filament. This aligns with Stripe's recommended approach while maintaining our POS-friendly UI structure.

## Architecture Changes

### Before:
- 1 Stripe Product per product group
- Multiple Stripe Prices (one per variant) linked to the same product
- Variant options stored locally only

### After:
- 1 Stripe Product per variant (e.g., "T-shirt - Large Red", "T-shirt - Small Blue")
- Each variant product has its own Price
- Variant options included in product name and metadata
- Variants still grouped under main product in Filament

## Database Changes

### Migration: `2025_11_27_122301_add_stripe_product_id_to_product_variants_table.php`
- Added `stripe_product_id` column to `product_variants` table
- Each variant now stores its own Stripe Product ID

## Code Changes

### 1. Model Updates

**`app/Models/ProductVariant.php`**
- Added `stripe_product_id` to `$fillable`
- Added `booted()` method with event listeners:
  - **Created**: Creates Stripe Product and Price when variant is created
  - **Updated**: Syncs changes to Stripe Product
  - **Deleting**: Archives Stripe Product (can't delete if used)

### 2. New Actions

**`app/Actions/ConnectedProducts/CreateVariantProductInStripe.php`**
- Creates a Stripe Product for each variant
- Product name: "Product Name - Variant Options" (e.g., "T-shirt - Large Red")
- Includes variant options, SKU, barcode in metadata
- Links to parent product via metadata
- Handles variant-specific images

**`app/Actions/ConnectedProducts/UpdateVariantProductToStripe.php`**
- Updates variant Stripe Product when variant changes
- Syncs name, active status, shipping, metadata, images

### 3. New Jobs

**`app/Jobs/SyncVariantProductToStripeJob.php`**
- Queued job to sync variant products asynchronously
- Prevents blocking UI during Stripe API calls

### 4. Service Updates

**`app/Services/ShopifyCsvImporter.php`**
- Updated to create separate Stripe Products for each variant
- Creates variant record first, then Stripe Product, then Price
- Links all three together

### 5. Filament Updates

**`app/Filament/Resources/ConnectedProducts/RelationManagers/VariantsRelationManager.php`**
- Added `stripe_product_id` column to table
- Shows both Stripe Product ID and Price ID
- Variants still displayed as related records (not separate products)

### 6. API Updates

**`app/Http/Controllers/Api/ProductsController.php`**
- Added `stripe_product_id` to variant API responses
- POS systems can now use variant Stripe Product IDs directly

## Stripe Product Structure

Each variant creates a Stripe Product with:

```json
{
  "name": "T-shirt - Large Red",
  "type": "good",
  "active": true,
  "shippable": true,
  "metadata": {
    "source": "variant",
    "parent_product_id": "123",
    "variant_id": "456",
    "option1_name": "Size",
    "option1_value": "Large",
    "option2_name": "Color",
    "option2_value": "Red",
    "sku": "TSHIRT-L-RED",
    "barcode": "1234567890",
    "parent_vendor": "Acme Corp",
    "parent_tags": "clothing, t-shirt"
  },
  "images": ["https://example.com/image.jpg"]
}
```

## Benefits

1. ✅ **Full Stripe Product Features**: Variants can use all Stripe Product features (not limited to Price object)
2. ✅ **Better Stripe Dashboard**: Each variant is clearly visible in Stripe Dashboard
3. ✅ **POS-Friendly UI**: Variants still grouped in Filament relation manager
4. ✅ **Flexible Integration**: Works with platforms expecting separate products
5. ✅ **Automatic Sync**: Variants automatically sync to Stripe on create/update

## Migration Notes

### Existing Variants

Existing variants without `stripe_product_id` will:
- Create Stripe Product automatically when saved next time
- Or can be bulk-migrated with a command (if needed)

### Backward Compatibility

- API responses include both `stripe_product_id` and `stripe_price_id`
- POS systems can use either ID depending on their needs
- Main product structure unchanged

## Usage Examples

### Creating a Variant in Filament

1. Go to Product → Edit → Variants tab
2. Click "New" to create variant
3. Fill in options, price, inventory
4. Save → Automatically creates Stripe Product and Price

### Importing from Shopify

1. Import CSV via wizard
2. Each variant automatically creates:
   - Local `ProductVariant` record
   - Stripe Product (with variant name)
   - Stripe Price (linked to variant product)

### API Response

```json
{
  "product": {
    "id": 1,
    "name": "T-shirt",
    "variants": [
      {
        "id": 1,
        "stripe_product_id": "prod_abc123",
        "stripe_price_id": "price_xyz789",
        "variant_name": "Large / Red",
        "price": {
          "amount": 2999,
          "currency": "NOK"
        }
      }
    ]
  }
}
```

## Testing

To test the implementation:

1. Create a product with variants in Filament
2. Check Stripe Dashboard - should see separate products for each variant
3. Verify variant names include options (e.g., "Product - Large Red")
4. Update variant - should sync to Stripe
5. Import Shopify CSV - should create variant products

## Future Enhancements

- Bulk migration command for existing variants
- Option to sync variant images to Stripe
- Variant-specific product descriptions
- Sync variant inventory to Stripe metadata (if needed)

