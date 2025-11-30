# FlutterFlow JSON Import Guide

## Overview

These JSON files are formatted to match your API response structure (snake_case, nested objects). FlutterFlow can use these to infer the custom data types.

## Import Instructions

### Method 1: Import from API Response (Recommended)

1. **Create a test API endpoint** that returns these example structures, OR
2. **Use FlutterFlow's "Import from API" feature**:
   - Go to **Data Types** → **Custom Data Types**
   - Click **+ Add Custom Data Type**
   - Select **Import from API** or **Import from JSON**
   - Paste the JSON from one of the example files
   - FlutterFlow will infer the structure

### Method 2: Direct JSON Import

1. Go to **Data Types** → **Custom Data Types**
2. Click **+ Add Custom Data Type**
3. Look for **Import** or **From JSON** option
4. Copy and paste the JSON content

## Import Order

**IMPORTANT**: Import in this order:

1. **First**: `CartItem_API_Example.json`
   - This creates the `CartItem` type
   - FlutterFlow will infer fields from the JSON structure

2. **Second**: `CartDiscount_API_Example.json`
   - This creates the `CartDiscount` type

3. **Third**: `ShoppingCart_API_Example.json`
   - This creates the `ShoppingCart` type
   - It references `CartItem` and `CartDiscount` in the nested arrays

## Field Mapping

FlutterFlow will map the JSON fields as follows. All fields are prefixed with `cart_` to clearly distinguish them from similar structures:

### CartItem
- `cart_item_id` → String
- `cart_item_product_id` → String (productId)
- `cart_item_variant_id` → String? (variantId, nullable)
- `cart_item_product_name` → String (productName)
- `cart_item_product_image_url` → String? (productImageUrl, nullable)
- `cart_item_unit_price` → int (unitPrice)
- `cart_item_quantity` → int
- `cart_item_original_price` → int? (originalPrice, nullable)
- `cart_item_discount_amount` → int? (discountAmount, nullable)
- `cart_item_discount_reason` → String? (discountReason, nullable)
- `cart_item_article_group_code` → String? (articleGroupCode, nullable)
- `cart_item_product_code` → String? (productCode, nullable)
- `cart_item_metadata` → JSON? (nullable)

### CartDiscount
- `cart_discount_id` → String
- `cart_discount_type` → String
- `cart_discount_coupon_id` → String? (couponId, nullable)
- `cart_discount_coupon_code` → String? (couponCode, nullable)
- `cart_discount_description` → String
- `cart_discount_amount` → int
- `cart_discount_percentage` → double? (nullable)
- `cart_discount_reason` → String? (nullable)
- `cart_discount_requires_approval` → bool (requiresApproval)

### ShoppingCart
- `cart_id` → String
- `cart_pos_session_id` → String? (posSessionId, nullable)
- `cart_items` → List<CartItem>
- `cart_discounts` → List<CartDiscount>
- `cart_tip_amount` → int? (tipAmount, nullable)
- `cart_customer_id` → String? (customerId, nullable)
- `cart_customer_name` → String? (customerName, nullable)
- `cart_created_at` → DateTime (createdAt)
- `cart_updated_at` → DateTime (updatedAt)
- `cart_metadata` → JSON? (nullable)

## Notes

1. **Snake_case to camelCase**: FlutterFlow may automatically convert field names, or you may need to adjust them after import

2. **Nullable Fields**: Fields with `null` values in the example will be marked as optional/nullable

3. **List Types**: The `items` and `discounts` arrays will be recognized as `List<CartItem>` and `List<CartDiscount>` respectively

4. **DateTime**: The `created_at` and `updated_at` fields use ISO 8601 format and will be recognized as DateTime

5. **JSON Type**: The `metadata` field contains a JSON object and will be recognized as JSON type

## Verification After Import

After importing, verify:

- [ ] CartItem has 13 fields (all prefixed with `cart_item_`)
- [ ] CartDiscount has 9 fields (all prefixed with `cart_discount_`)
- [ ] ShoppingCart has 10 fields (all prefixed with `cart_`)
- [ ] ShoppingCart.cart_items is type `List<CartItem>`
- [ ] ShoppingCart.cart_discounts is type `List<CartDiscount>`
- [ ] All nullable fields are marked as optional
- [ ] DateTime fields are correctly typed
- [ ] Field names maintain `cart_` prefix (FlutterFlow may auto-convert to camelCase but prefix should remain)

## If Import Doesn't Work

If FlutterFlow doesn't support direct JSON import, you can:

1. **Use the API Response method**: Create a test endpoint that returns these structures, then use FlutterFlow's API integration to import
2. **Manual creation**: Use the field tables in `FLUTTERFLOW_MANUAL_SETUP.md`
3. **Copy field by field**: Use the JSON as a reference while manually creating each field

## Example API Endpoint for Testing

If you want to create a test endpoint for FlutterFlow to import from:

```php
// routes/api.php
Route::get('/test/cart-structure', function () {
    return response()->json([
        'cart_item' => [
            'cart_item_id' => '550e8400-e29b-41d4-a716-446655440000',
            'cart_item_product_id' => 'prod_123',
            'cart_item_variant_id' => 'var_456',
            'cart_item_product_name' => 'Coffee',
            'cart_item_product_image_url' => 'https://example.com/images/coffee.jpg',
            'cart_item_unit_price' => 5000,
            'cart_item_quantity' => 2,
            'cart_item_original_price' => 5000,
            'cart_item_discount_amount' => 500,
            'cart_item_discount_reason' => 'Manager override',
            'cart_item_article_group_code' => '01',
            'cart_item_product_code' => 'COFFEE001',
            'cart_item_metadata' => ['notes' => 'Extra hot'],
        ],
        'cart_discount' => [
            'cart_discount_id' => '660e8400-e29b-41d4-a716-446655440001',
            'cart_discount_type' => 'coupon',
            'cart_discount_coupon_id' => 'coupon_789',
            'cart_discount_coupon_code' => 'SUMMER2024',
            'cart_discount_description' => 'Summer Sale Discount',
            'cart_discount_amount' => 2000,
            'cart_discount_percentage' => 10.0,
            'cart_discount_reason' => null,
            'cart_discount_requires_approval' => false,
        ],
        'shopping_cart' => [
            // ... full cart structure from ShoppingCart_API_Example.json
        ],
    ]);
});
```

Then in FlutterFlow, you can import from this API endpoint.

---

## Files Included

- `CartItem_API_Example.json` - Example CartItem structure
- `CartDiscount_API_Example.json` - Example CartDiscount structure  
- `ShoppingCart_API_Example.json` - Complete ShoppingCart with nested items and discounts

All files use snake_case to match your API format.

