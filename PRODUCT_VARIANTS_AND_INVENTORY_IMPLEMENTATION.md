# Product Variants and Inventory Implementation

## Overview

This document describes the implementation of product variants and inventory tracking in the POS system, including integration with Stripe and Filament admin panel.

## ✅ Completed Features

### 1. Product Variants Model

**File:** `app/Models/ProductVariant.php`

- Stores variant-specific data (options, SKU, barcode, pricing)
- Links to `ConnectedProduct` and `ConnectedPrice`
- Tracks inventory quantity and policy
- Supports up to 3 variant options (e.g., Size, Color, Material)
- Includes weight, shipping, and tax information

**Key Fields:**
- `option1_name`, `option1_value`, `option2_name`, `option2_value`, `option3_name`, `option3_value`
- `sku`, `barcode`
- `price_amount`, `compare_at_price_amount`, `currency`
- `inventory_quantity`, `inventory_policy`, `inventory_management`
- `weight_grams`, `requires_shipping`, `taxable`

### 2. Database Migration

**File:** `database/migrations/2025_01_27_120000_create_product_variants_table.php`

- Creates `product_variants` table with all necessary fields
- Indexes for performance (SKU, barcode, product_id)
- Unique constraint on SKU per account

### 3. Model Relationships

**Updated Models:**
- `ConnectedProduct` → `hasMany(ProductVariant)`
- `ConnectedPrice` → `hasOne(ProductVariant)`
- `ProductVariant` → `belongsTo(ConnectedProduct)` and `belongsTo(ConnectedPrice)`

### 4. Shopify CSV Import Integration

**File:** `app/Services/ShopifyCsvImporter.php`

- Parses Shopify CSV format with variants
- Creates `ProductVariant` records for each variant
- Links variants to Stripe prices
- Downloads variant-specific images
- Handles inventory data from Shopify

**Image Handling:**
- ✅ Images are downloaded from Shopify URLs using `addMediaFromUrl()`
- ✅ Images are stored in Spatie Media Library (`images` collection)
- ✅ Images are uploaded to Stripe after download
- ✅ Variant-specific images are supported

### 5. Filament Admin Panel

**File:** `app/Filament/Resources/ConnectedProducts/RelationManagers/VariantsRelationManager.php`

- Full CRUD interface for managing variants
- Inventory quantity editing
- Stock status indicators (in stock/out of stock)
- Discount percentage display
- SKU and barcode management
- Option values editing (Size, Color, etc.)

**Features:**
- Real-time inventory tracking
- Visual stock status (green for in stock, red for out of stock)
- Search and filter by SKU, barcode, stock status
- Bulk actions for variants

### 6. API Endpoints

#### Product API Updates

**File:** `app/Http/Controllers/Api/ProductsController.php`

**Updated Endpoints:**
- `GET /api/products` - Now includes variants and inventory data
- `GET /api/products/{id}` - Returns full variant information with inventory

**Response Format:**
```json
{
  "product": {
    "id": 1,
    "name": "Product Name",
    "variants": [
      {
        "id": 1,
        "sku": "SKU-001",
        "variant_name": "Large / Red",
        "price": {
          "amount": 5999,
          "amount_formatted": "59.99",
          "currency": "NOK"
        },
        "inventory": {
          "quantity": 10,
          "in_stock": true,
          "policy": "deny",
          "tracked": true
        }
      }
    ],
    "inventory": {
      "tracked": true,
      "total_quantity": 10,
      "in_stock_variants": 1,
      "out_of_stock_variants": 0,
      "all_in_stock": true
    }
  }
}
```

#### Inventory Management API

**File:** `app/Http/Controllers/Api/InventoryController.php`

**New Endpoints:**

1. **Update Variant Inventory**
   ```
   PUT /api/variants/{variantId}/inventory
   Body: {
     "inventory_quantity": 10,
     "inventory_policy": "deny",
     "inventory_management": "shopify"
   }
   ```

2. **Adjust Inventory (Add/Subtract)**
   ```
   POST /api/variants/{variantId}/inventory/adjust
   Body: {
     "quantity": -5,  // Negative to subtract, positive to add
     "reason": "Sale",
     "note": "Sold 5 units"
   }
   ```

3. **Set Inventory Quantity**
   ```
   POST /api/variants/{variantId}/inventory/set
   Body: {
     "quantity": 20,
     "reason": "Stock count",
     "note": "Physical inventory count"
   }
   ```

4. **Get Product Inventory**
   ```
   GET /api/products/{productId}/inventory
   Returns: All variants with inventory data for the product
   ```

5. **Bulk Update Inventory**
   ```
   POST /api/inventory/bulk-update
   Body: {
     "variants": [
       {"variant_id": 1, "quantity": 10},
       {"variant_id": 2, "quantity": 5}
     ]
   }
   ```

### 7. Route Registration

**File:** `routes/api.php`

All inventory endpoints are registered under authenticated routes:
- `/api/products/{product}/inventory`
- `/api/variants/{variant}/inventory/*`
- `/api/inventory/bulk-update`

## Image Handling Verification

✅ **Confirmed:** Images from Shopify are:
1. Downloaded using `addMediaFromUrl()` from Spatie Media Library
2. Stored in the `images` media collection
3. Uploaded to Stripe after download
4. Accessible via media library URLs
5. Variant-specific images are supported

**Code Location:** `app/Services/ShopifyCsvImporter.php::downloadAndAddImages()`

## Missing Features (Not Implemented)

The following features are **not** currently implemented but could be added:

1. **Inventory Adjustment History** - No audit trail of inventory changes
2. **Low Stock Alerts** - No notifications when inventory is low
3. **Inventory Reservations** - No system for reserving inventory during checkout
4. **Multi-location Inventory** - No support for tracking inventory across multiple locations
5. **Inventory Sync with Stripe** - Stripe doesn't natively support inventory, so this is handled locally

## Usage Examples

### Creating a Variant via Filament

1. Navigate to Products → Select a product
2. Click on "Variants" tab
3. Click "New Variant"
4. Fill in:
   - Option names and values (e.g., Size: Large, Color: Red)
   - SKU and barcode
   - Price
   - Inventory quantity
   - Inventory policy (deny/continue)

### Updating Inventory via API

```bash
# Adjust inventory (subtract 5 units)
curl -X POST https://api.example.com/api/variants/1/inventory/adjust \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": -5,
    "reason": "Sale",
    "note": "Sold 5 units"
  }'

# Set inventory to specific quantity
curl -X POST https://api.example.com/api/variants/1/inventory/set \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 20,
    "reason": "Stock count"
  }'
```

## Database Schema

```sql
product_variants
├── id
├── connected_product_id (FK)
├── stripe_account_id
├── stripe_price_id (FK to connected_prices)
├── sku (unique per account)
├── barcode
├── option1_name, option1_value
├── option2_name, option2_value
├── option3_name, option3_value
├── price_amount (in cents)
├── compare_at_price_amount (in cents)
├── currency
├── weight_grams
├── inventory_quantity (nullable)
├── inventory_policy (deny/continue)
├── inventory_management
├── requires_shipping
├── taxable
├── image_url
├── metadata (JSON)
├── active
└── timestamps
```

## Integration with Stripe

- **Products:** Each product has a `stripe_product_id`
- **Prices:** Each variant links to a `ConnectedPrice` via `stripe_price_id`
- **Inventory:** Managed locally (Stripe doesn't support inventory tracking)
- **Images:** Uploaded to Stripe after being downloaded from Shopify

## Next Steps (Optional Enhancements)

1. Add inventory adjustment history/audit log
2. Implement low stock alerts
3. Add inventory reservations for pending orders
4. Create inventory reports and analytics
5. Add barcode scanning support in POS
6. Implement inventory sync with external systems

