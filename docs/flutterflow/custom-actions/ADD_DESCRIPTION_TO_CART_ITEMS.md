# Adding Description Field to Cart Items

## Overview

This guide explains how to add support for custom descriptions in cart items for diverse products or products without price. The description will be used on receipts instead of the product name when provided.

## Implementation Options

### Option 1: Add `cartItemDescription` Field to CartItemsStruct (Recommended)

**Pros:**
- Clean, explicit field
- Easy to access and display
- Matches API structure

**Cons:**
- Requires updating FlutterFlow schema

**Steps:**
1. In FlutterFlow, go to your `CartItemsStruct` schema
2. Add a new field:
   - Name: `cartItemDescription`
   - Type: `String`
   - Required: `false` (nullable)
   - Default: `null`
3. Use the implementation in `add_item_to_cart_with_description.dart`

### Option 2: Store in Metadata (Fallback)

**Pros:**
- No schema changes needed
- Works with existing structure

**Cons:**
- Less explicit
- Requires accessing metadata to get description

**Steps:**
1. Use the implementation in `add_item_to_cart_with_description_metadata_fallback.dart`
2. When accessing description, read from `cartItemMetadata['description']`
3. When sending to API, extract from metadata and include in `description` field

## Usage in FlutterFlow

### 1. Update the Custom Action

Copy the appropriate implementation:
- **Option 1**: `add_item_to_cart_with_description.dart`
- **Option 2**: `add_item_to_cart_with_description_metadata_fallback.dart`

### 2. Update Action Parameters

The action now accepts:
- `product` (ProductStruct?)
- `variants` (VariantsStruct?)
- `quantity` (int?)
- `customPrice` (int?) - Required if `no_price_in_pos` is true
- `description` (String?) - **NEW**: Optional description for diverse products

### 3. UI Implementation

When adding items with custom prices, show a text input for description:

```dart
// Example: Show description input when no_price_in_pos is true
if (product.noPriceInPos || variant.noPriceInPos) {
  TextFormField(
    decoration: InputDecoration(
      labelText: 'Item Description',
      hintText: 'Describe what was purchased (e.g., "Various items - customer selection")',
      helperText: 'Recommended for diverse products',
    ),
    maxLength: 500,
    onChanged: (value) {
      setState(() {
        itemDescription = value;
      });
    },
  );
}
```

### 4. Call the Action

```dart
await action_blocks.addItemToCart(
  product: selectedProduct,
  variants: selectedVariant,
  quantity: 1,
  customPrice: customPriceInOre, // Required if no_price_in_pos is true
  description: itemDescription, // Optional but recommended
);
```

## Integration with Purchase API

When creating a purchase, extract the description from cart items:

### If using Option 1 (cartItemDescription field):

```dart
final cartItems = FFAppState().cart.cartItems.map((item) {
  return {
    'product_id': int.parse(item.cartItemProductId),
    'variant_id': item.cartItemVariantId.isNotEmpty 
        ? int.parse(item.cartItemVariantId) 
        : null,
    'quantity': item.cartItemQuantity,
    'unit_price': item.cartItemUnitPrice,
    'description': item.cartItemDescription, // Include description
  };
}).toList();
```

### If using Option 2 (metadata):

```dart
final cartItems = FFAppState().cart.cartItems.map((item) {
  final metadata = item.cartItemMetadata as Map<String, dynamic>?;
  return {
    'product_id': int.parse(item.cartItemProductId),
    'variant_id': item.cartItemVariantId.isNotEmpty 
        ? int.parse(item.cartItemVariantId) 
        : null,
    'quantity': item.cartItemQuantity,
    'unit_price': item.cartItemUnitPrice,
    'description': metadata?['description'], // Extract from metadata
  };
}).toList();
```

## Best Practices

1. **Show Description Input for Diverse Products:**
   - Display text input when `no_price_in_pos` is true
   - Make it prominent but not required
   - Show helper text explaining its purpose

2. **Validate Description:**
   - Maximum length: 500 characters
   - Trim whitespace
   - Show character count if needed

3. **Display in Cart:**
   - Show description in cart items if provided
   - Use description instead of product name when available
   - Fall back to product name if description is empty

4. **Receipt Display:**
   - Description will automatically appear on receipts
   - Backend handles the fallback logic
   - No additional frontend changes needed

## Example Flow

1. **User selects product with `no_price_in_pos = true`**
2. **UI shows:**
   - Custom price input (required)
   - Description input (optional but recommended)
3. **User enters:**
   - Price: 5000 øre (50.00 NOK)
   - Description: "Various items - customer selection"
4. **Action called:**
   ```dart
   await addItemToCart(
     product: product,
     variants: null,
     quantity: 1,
     customPrice: 5000,
     description: "Various items - customer selection",
   );
   ```
5. **Item added to cart with description**
6. **On purchase:**
   - Description sent to API in `cart.items[].description`
   - Backend stores it in purchase metadata
   - Receipt shows: "Various items - customer selection"

## Compliance Notes

- Descriptions help with Norwegian receipt compliance (Kassasystemforskriften)
- Clear item descriptions are required on receipts
- Custom descriptions provide flexibility for diverse products
- System ensures every item has a description (custom or product name)

## Troubleshooting

### Description not appearing on receipt
- Check that description is included in purchase request
- Verify backend is receiving `cart.items[].description` field
- Check purchase metadata to confirm description was stored

### CartItemDescription field not found
- If using Option 1, ensure field is added to CartItemsStruct schema
- If field doesn't exist, use Option 2 (metadata approach)

### Description lost when updating cart
- Ensure description is preserved when updating existing items
- Check that description is included in CartItemsStruct updates

