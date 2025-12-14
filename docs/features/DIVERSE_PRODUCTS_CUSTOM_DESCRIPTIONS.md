# Diverse Products and Custom Descriptions

## Overview

This document describes the implementation of custom descriptions for diverse products or products without price in the POS system. This feature allows stores to add text descriptions for items that don't fit standard product categories, ensuring proper documentation on receipts and compliance with Norwegian receipt requirements.

## Use Cases

1. **Diverse Products**: Items that vary significantly and can't be categorized as a single product (e.g., "Various items", "Customer selection")
2. **Products Without Price**: Items that require custom pricing at the point of sale
3. **Custom Services**: Services that need descriptive text rather than a product name
4. **Special Items**: Items that need additional context beyond the product name

## Implementation

### API Changes

#### Request (Creating Purchases)

When creating a purchase, cart items can now include an optional `description` field:

```json
{
  "cart": {
    "items": [
      {
        "product_id": 123,
        "quantity": 1,
        "unit_price": 5000,
        "description": "Various items - customer selection"
      }
    ],
    "total": 5000
  }
}
```

**Field Details:**
- `description` (optional, string, max 500 characters)
- Used on receipts instead of product name when provided
- Useful for diverse products or products without price

#### Response (Purchase Details)

Purchase items now include a `purchase_item_description` field:

```json
{
  "purchase_items": [
    {
      "purchase_item_product_id": "123",
      "purchase_item_product_name": "Diverse Product",
      "purchase_item_description": "Various items - customer selection",
      "purchase_item_unit_price": 5000,
      "purchase_item_quantity": 1
    }
  ]
}
```

### Backend Implementation

#### 1. Validation (`PurchasesController`)

- Added validation for `cart.items.*.description` field
- Maximum length: 500 characters
- Optional field (nullable)

#### 2. Item Enrichment (`PurchaseService`)

The `enrichCartItemsWithProductSnapshots` method now:
- Stores custom description if provided
- Uses custom description as the primary `name` field for receipts
- Falls back to product name if no custom description is provided
- Preserves both `description` and `product_name` for reference

#### 3. Receipt Generation (`ReceiptGenerationService`)

Receipt generation now:
- Prioritizes custom description over product name
- Uses `item['description']` → `item['product_name']` → `'Vare'` fallback chain
- Ensures all items have a `name` field for receipt display

#### 4. Receipt Templates (`ReceiptTemplateService`)

Receipt template service:
- Uses custom description when formatting items
- Falls back to product name if description is not available
- Maintains compatibility with existing receipts

## Compliance Considerations

### Norwegian Receipt Requirements (Kassasystemforskriften)

The implementation ensures compliance with Norwegian receipt requirements:

1. **Item Description on Receipts** (§ 2-8-4):
   - All items must have a clear description on receipts
   - Custom descriptions provide flexibility for diverse products
   - System ensures every item has a name/description

2. **Product Information**:
   - Product name is preserved for reference (`purchase_item_product_name`)
   - Custom description is used for display (`purchase_item_description`)
   - Both are stored in purchase metadata for audit trail

3. **Audit Trail**:
   - Custom descriptions are stored in purchase metadata
   - Historical accuracy is maintained (snapshot at purchase time)
   - Receipts show what was actually purchased

### Best Practices

1. **Use Custom Descriptions When**:
   - Product doesn't have a fixed price (`no_price_in_pos` = true)
   - Item is a diverse selection that varies
   - Additional context is needed beyond product name

2. **Keep Descriptions Clear**:
   - Use descriptive text (e.g., "Various items - customer selection")
   - Keep within 500 character limit
   - Ensure descriptions are meaningful for customers and auditors

3. **Product Setup**:
   - Create products with `no_price_in_pos` flag for diverse items
   - Use generic product names (e.g., "Diverse Product", "Custom Service")
   - Add specific details via custom description at purchase time

## Frontend Implementation (FlutterFlow)

### Adding Custom Description Field

1. **Cart Item Structure**:
   ```dart
   {
     'product_id': 123,
     'quantity': 1,
     'unit_price': 5000,
     'description': 'Various items - customer selection' // Optional
   }
   ```

2. **UI Considerations**:
   - Show text input field when product has `no_price_in_pos` = true
   - Allow cashier to enter custom description
   - Validate description length (max 500 characters)
   - Display description in cart preview

3. **Purchase Flow**:
   - Include `description` in cart items when creating purchase
   - Display custom description in purchase confirmation
   - Show custom description on receipt preview

## Examples

### Example 1: Diverse Product Purchase

**Product Setup:**
- Product ID: 123
- Name: "Diverse Product"
- `no_price_in_pos`: true

**Purchase:**
```json
{
  "cart": {
    "items": [
      {
        "product_id": 123,
        "quantity": 1,
        "unit_price": 5000,
        "description": "Various items - customer selection"
      }
    ],
    "total": 5000
  }
}
```

**Receipt Display:**
- Item name: "Various items - customer selection"
- Product name (for reference): "Diverse Product"

### Example 2: Custom Service

**Product Setup:**
- Product ID: 456
- Name: "Custom Service"
- `no_price_in_pos`: true

**Purchase:**
```json
{
  "cart": {
    "items": [
      {
        "product_id": 456,
        "quantity": 1,
        "unit_price": 15000,
        "description": "Custom repair service - laptop screen replacement"
      }
    ],
    "total": 15000
  }
}
```

**Receipt Display:**
- Item name: "Custom repair service - laptop screen replacement"
- Product name (for reference): "Custom Service"

## Migration Notes

- Existing purchases without custom descriptions continue to work
- System falls back to product name if description is not provided
- No database migration required (uses existing metadata structure)
- Backward compatible with existing API clients

## Related Features

- **Products Without Price**: See `no_price_in_pos` flag in Products API
- **Receipt Generation**: See `ReceiptGenerationService` documentation
- **Purchase Flow**: See `PURCHASE_FLOW.md` documentation

## API Documentation

Full API documentation is available in `api-spec.yaml`:
- Request schema: `PurchaseCart.items[].description`
- Response schema: `Purchase.purchase_items[].purchase_item_description`

