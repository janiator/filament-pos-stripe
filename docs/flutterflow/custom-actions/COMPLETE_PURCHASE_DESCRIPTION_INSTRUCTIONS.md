# Complete Purchase Action - Description Field Instructions

## Problem

FlutterFlow cannot parse the action if you try to access a field that doesn't exist in the struct. If `cartItemDescription` doesn't exist in `CartItemsStruct`, accessing `cartItem.cartItemDescription` causes a compile-time error, making the action unparseable.

## Solution

Use the appropriate version based on whether you've added the `cartItemDescription` field to `CartItemsStruct`:

### Option 1: If `cartItemDescription` Field EXISTS

**Use:** `complete_pos_purchase_with_description_direct_field.dart`

**Code to use:**
```dart
// Get description if present (for diverse products or products without price)
String? description = cartItem.cartItemDescription;

// If description is null or empty, try metadata as fallback
if (description == null || description.isEmpty) {
  try {
    final metadata = cartItem.cartItemMetadata;
    if (metadata != null && metadata is Map<String, dynamic>) {
      final metaDescription = metadata['description'];
      if (metaDescription != null && metaDescription is String && metaDescription.isNotEmpty) {
        description = metaDescription;
      }
    }
  } catch (e) {
    description = null;
  }
}

// Normalize: convert empty string to null
if (description != null && description.isEmpty) {
  description = null;
}
```

### Option 2: If `cartItemDescription` Field DOES NOT EXIST

**Use:** `complete_pos_purchase_with_description_metadata_only.dart`

**Code to use:**
```dart
// Get description if present (for diverse products or products without price)
// Using metadata approach since cartItemDescription field doesn't exist
String? description;

try {
  final metadata = cartItem.cartItemMetadata;
  if (metadata != null && metadata is Map<String, dynamic>) {
    final metaDescription = metadata['description'];
    if (metaDescription != null && metaDescription is String && metaDescription.isNotEmpty) {
      description = metaDescription;
    }
  }
} catch (e) {
  description = null;
}

// Normalize: convert empty string to null
if (description != null && description.isEmpty) {
  description = null;
}
```

## How to Determine Which Version to Use

### Check if Field Exists

1. Go to FlutterFlow → Data Types → CartItemsStruct
2. Look for a field named `cartItemDescription`
3. If it exists → Use **Option 1**
4. If it doesn't exist → Use **Option 2**

## Step-by-Step Instructions

### If Using Option 1 (Field Exists)

1. Open your `completePosPurchase` action in FlutterFlow
2. Find the section where cart items are built (around line 148)
3. Replace the description handling code with Option 1 code above
4. Save the action
5. It should compile without errors

### If Using Option 2 (Field Doesn't Exist)

1. Open your `completePosPurchase` action in FlutterFlow
2. Find the section where cart items are built (around line 148)
3. Replace the description handling code with Option 2 code above
4. Save the action
5. It should compile without errors

**Important:** Make sure your `addItemToCart` action stores descriptions in metadata if using Option 2!

## Complete Code Context

The description code should be placed right after getting `discountAmount` and before building `cartItemMap`:

```dart
// Discount amount in øre
final discountAmount = cartItem.cartItemDiscountAmount ?? 0;

// ← INSERT DESCRIPTION CODE HERE (Option 1 or Option 2)

final cartItemMap = <String, dynamic>{
  'product_id': productId,
  'variant_id': variantId,
  'quantity': cartItem.cartItemQuantity,
  'unit_price': unitPrice,
  'discount_amount': discountAmount,
  'tax_rate': 0.25,
  'tax_inclusive': true,
};

// Add description if provided (for diverse products)
if (description != null && description.isNotEmpty) {
  cartItemMap['description'] = description;
}

cartItems.add(cartItemMap);
```

## Troubleshooting

### Error: "The action is empty or cannot be parsed"

**Cause:** You're trying to access `cartItemDescription` but the field doesn't exist.

**Solution:** Use Option 2 (metadata-only approach)

### Error: "Undefined name 'cartItemDescription'"

**Cause:** Same as above - field doesn't exist.

**Solution:** Use Option 2 (metadata-only approach)

### Description is always null

**If using Option 1:**
- Check that `cartItemDescription` field exists in `CartItemsStruct`
- Check that `addItemToCart` is setting `cartItemDescription`
- Verify the field is not null when adding items

**If using Option 2:**
- Check that `addItemToCart` is storing description in `cartItemMetadata['description']`
- Verify metadata structure is correct
- Check that description is being set when adding items

## Recommendation

**Best Practice:** Add `cartItemDescription` field to `CartItemsStruct` and use Option 1. This is cleaner and more explicit.

**Quick Fix:** If you can't modify the struct right now, use Option 2 with metadata.



