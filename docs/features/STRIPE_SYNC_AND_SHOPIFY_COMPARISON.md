# Stripe Sync & Shopify Structure Comparison

## Stripe Sync Status

### ✅ What IS Synced to Stripe:

1. **Product Fields:**
   - `name` → Stripe `name`
   - `description` → Stripe `description`
   - `active` → Stripe `active`
   - `type` → Stripe `type`
   - `shippable` → Stripe `shippable`
   - `url` → Stripe `url`
   - `package_dimensions` → Stripe `package_dimensions`
   - `statement_descriptor` → Stripe `statement_descriptor`
   - `tax_code` → Stripe `tax_code`
   - `unit_label` → Stripe `unit_label`
   - `product_meta` → Stripe `metadata` (all key-value pairs)
   - `images` → Stripe `images` (uploaded via File API)
   - `default_price` → Stripe `default_price`

2. **Prices (Variants):**
   - Each variant creates a separate Stripe Price
   - Price metadata includes SKU and barcode
   - Prices are linked to the product

3. **Sync Triggers:**
   - ✅ Product creation → Creates in Stripe
   - ✅ Product update → Updates in Stripe (via listener)
   - ✅ Image upload → Uploads to Stripe File API
   - ✅ Price changes → Creates/updates Stripe prices

### ❌ What is NOT Synced to Stripe:

1. **Variants as Separate Entities:**
   - Stripe doesn't have a native "variant" concept
   - Variants are represented as separate prices (which we do)
   - Variant options (Size, Color, etc.) are stored locally only
   - Variant inventory is NOT synced (Stripe doesn't support inventory)

2. **Inventory Tracking:**
   - Inventory quantity is stored locally only
   - Inventory policy is stored locally only
   - Stripe has no inventory management system

3. **Variant-Specific Data:**
   - Variant options (option1_name, option1_value, etc.) are local only
   - Variant weight is local only
   - Variant images are stored in `image_url` but not synced to Stripe

## Shopify vs Our Structure

### Shopify CSV Fields → Our Model Mapping

| Shopify Field | Our Field | Location | Synced to Stripe? |
|--------------|-----------|---------|-------------------|
| Handle | product_meta['handle'] | JSON | ✅ Yes (as metadata) |
| Title | name | Direct | ✅ Yes |
| Body (HTML) | description | Direct (HTML stripped) | ✅ Yes |
| Vendor | product_meta['vendor'] | JSON | ✅ Yes (as metadata) |
| Product Category | product_meta['category'] | JSON | ✅ Yes (as metadata) |
| Type | type | Direct | ✅ Yes |
| Tags | product_meta['tags'] | JSON | ✅ Yes (as metadata) |
| Published | active | Direct | ✅ Yes |
| Option1 Name | variant.option1_name | Variant table | ❌ No (local only) |
| Option1 Value | variant.option1_value | Variant table | ❌ No (local only) |
| Option2 Name | variant.option2_name | Variant table | ❌ No (local only) |
| Option2 Value | variant.option2_value | Variant table | ❌ No (local only) |
| Option3 Name | variant.option3_name | Variant table | ❌ No (local only) |
| Option3 Value | variant.option3_value | Variant table | ❌ No (local only) |
| Variant SKU | variant.sku | Variant table | ✅ Yes (in price metadata) |
| Variant Price | variant.price_amount + price | Variant + Price | ✅ Yes (as Stripe price) |
| Variant Compare At Price | variant.compare_at_price_amount | Variant table | ❌ No (local only) |
| Variant Barcode | variant.barcode | Variant table | ✅ Yes (in price metadata) |
| Variant Grams | variant.weight_grams | Variant table | ❌ No (local only) |
| Variant Inventory | variant.inventory_quantity | Variant table | ❌ No (Stripe doesn't support) |
| Image Src | images (media library) | Media + images array | ✅ Yes (uploaded to Stripe) |
| Variant Image | variant.image_url | Variant table | ❌ No (local only) |
| SEO Title | ❌ Missing | - | ❌ No |
| SEO Description | ❌ Missing | - | ❌ No |
| Metafields | product_meta (partial) | JSON | ✅ Yes (as metadata) |

### Missing Fields in Our System

1. **SEO Fields:**
   - SEO Title
   - SEO Description

2. **Product Meta Fields (not in Filament form):**
   - Vendor
   - Tags
   - Handle
   - Category
   - Custom metafields

3. **Variant-Specific:**
   - Compare at price (stored but not editable in Filament)
   - Variant images (stored but not synced to Stripe)

## Current Sync Flow

### Product Creation:
1. Create product locally
2. Create product in Stripe (via `CreateConnectedProductInStripe`)
3. Upload images to Stripe File API
4. Create prices for variants
5. Set default price

### Product Update:
1. Update local product
2. Listener detects changes
3. Job syncs to Stripe (via `UpdateConnectedProductToStripe`)
4. Images re-uploaded if changed
5. Metadata synced

### Variant Creation:
1. Create variant locally
2. Create Stripe price (if not exists)
3. Link variant to price
4. Variant options stored locally only

## Recommendations

1. **Add product_meta editing to Filament** ✅ (Will implement)
2. **Add SEO fields** (Optional - can add if needed)
3. **Add variant compare_at_price to Filament** (Already in model, just need form field)
4. **Consider syncing variant options to price metadata** (For better Stripe integration)

