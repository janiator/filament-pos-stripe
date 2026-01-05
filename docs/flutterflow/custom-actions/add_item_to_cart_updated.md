# Updated addItemToCart Function with Custom Price Support

## Changes Required

### 1. Update Function Signature

Add `customPrice` parameter (in øre, integer):

```dart
Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
  int? customPrice, // NEW: Optional custom price in øre
) async {
```

### 2. Add Price Validation Logic

Add this code **right after** the `variantIdString` line and **before** the `unitPrice` line:

```dart
  final variantId = variants?.id ?? 0;
  final variantIdString = variantId != 0 ? variantId.toString() : '';

  // NEW: Check if custom price is required
  final requiresCustomPrice = variants?.noPriceInPos ?? product.noPriceInPos ?? false;
  
  if (requiresCustomPrice) {
    if (customPrice == null || customPrice <= 0) {
      throw Exception('Custom price is required for this product/variant. Please provide customPrice parameter.');
    }
  }

  // Get price: use custom price if provided, otherwise use variant/product price
  final unitPrice = customPrice ?? 
      (variants?.variantPrice?.amount ?? product.productPrice?.amount ?? 0);
```

### 3. Replace Existing Price Logic

**FIND THIS LINE:**
```dart
  final unitPrice =
      variants?.variantPrice?.amount ?? product.productPrice.amount ?? 0;
```

**REPLACE WITH:**
```dart
  // Get price: use custom price if provided, otherwise use variant/product price
  final unitPrice = customPrice ?? 
      (variants?.variantPrice?.amount ?? product.productPrice?.amount ?? 0);
```

## Complete Code Block to Add

Add this code block **right after** `final variantIdString` and **before** the price logic:

```dart
  // Check if custom price is required (no_price_in_pos is true)
  final requiresCustomPrice = variants?.noPriceInPos ?? product.noPriceInPos ?? false;
  
  if (requiresCustomPrice) {
    if (customPrice == null || customPrice <= 0) {
      throw Exception('Custom price is required for this product/variant. Please provide customPrice parameter.');
    }
  }
```

## Summary

- **Function signature**: Add `int? customPrice` parameter
- **Price logic**: Check `no_price_in_pos` and use `customPrice` if provided
- **Validation**: Ensure `customPrice` is provided when `no_price_in_pos` is true




