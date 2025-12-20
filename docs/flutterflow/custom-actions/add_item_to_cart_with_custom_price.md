# FlutterFlow: Add Item to Cart with Custom Price Support

## Overview

This guide shows how to update the `addItemToCart` custom action to support custom price input for products/variants where `no_price_in_pos` is `true`.

## Updated Function Signature

Add an optional `customPrice` parameter (in øre):

```dart
Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
  int? customPrice, // NEW: Optional custom price in øre (required if no_price_in_pos is true)
) async {
```

## Updated Price Logic

Replace the current price logic:

**BEFORE:**
```dart
// Get price: use variant price if available, otherwise product price
final unitPrice =
    variants?.variantPrice?.amount ?? product.productPrice.amount ?? 0;
```

**AFTER:**
```dart
// Check if custom price is required (no_price_in_pos is true)
final requiresCustomPrice = variants?.noPriceInPos ?? product.noPriceInPos ?? false;

// Validate custom price if required
if (requiresCustomPrice) {
  if (customPrice == null || customPrice <= 0) {
    throw Exception('Custom price is required for this product/variant. Please provide customPrice parameter.');
  }
}

// Get price: use custom price if provided, otherwise use variant/product price
final unitPrice = customPrice ?? 
    (variants?.variantPrice?.amount ?? product.productPrice?.amount ?? 0);

// Validate that price is set
if (unitPrice <= 0) {
  throw Exception('Price must be greater than 0');
}
```

## Complete Updated Function

```dart
Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
  int? customPrice, // NEW: Optional custom price in øre
) async {
  if (product == null) return;

  final qty = quantity ?? 1;
  final currentCart = FFAppState().cart;
  final variantId = variants?.id ?? 0;
  final variantIdString = variantId != 0 ? variantId.toString() : '';

  // Check if custom price is required (no_price_in_pos is true)
  final requiresCustomPrice = variants?.noPriceInPos ?? product.noPriceInPos ?? false;
  
  if (requiresCustomPrice) {
    // If no_price_in_pos is true, customPrice must be provided
    if (customPrice == null || customPrice <= 0) {
      throw Exception('Custom price is required for this product/variant. Please provide customPrice parameter.');
    }
  }

  // Get price: use custom price if provided, otherwise use variant/product price
  final unitPrice = customPrice ?? 
      (variants?.variantPrice?.amount ?? product.productPrice?.amount ?? 0);

  // Validate that price is set
  if (unitPrice <= 0) {
    throw Exception('Price must be greater than 0');
  }

  final originalPrice = unitPrice;

  // Get product image: use variant image if available, otherwise first product image
  String productImageUrl = '';
  if (variants != null && variants.imageUrl.isNotEmpty) {
    productImageUrl = variants.imageUrl;
  } else if (product.images.isNotEmpty && product.images.first.isNotEmpty) {
    productImageUrl = product.images.first;
  }

  // Check if item already exists in cart (by product ID + variant ID)
  final existingIndex = currentCart.cartItems.indexWhere(
    (item) {
      // Check if product IDs match
      if (item.cartItemProductId != product.id.toString()) {
        return false;
      }
      // Check if variant IDs match (both empty string means no variant)
      return item.cartItemVariantId == variantIdString;
    },
  );

  if (existingIndex >= 0) {
    // Update existing item quantity
    final existingItem = currentCart.cartItems[existingIndex];
    final updatedItem = CartItemsStruct(
      cartItemId: existingItem.cartItemProductId,
      cartItemVariantId: variantIdString,
      cartItemProductName: product.name,
      cartItemProductImageUrl: productImageUrl,
      cartItemUnitPrice: unitPrice,
      cartItemQuantity: qty,
      cartItemOriginalPrice: originalPrice,
      cartItemDiscountAmount: null,
      cartItemDiscountReason: null,
      cartItemArticleGroupCode: product.taxCode ?? '',
      cartItemProductCode: product.stripeProductId ?? '',
      cartItemMetadata: null,
    );
  }
}
```

## UI Flow

1. **In FlutterFlow UI:**
   - Before calling `addItemToCart`, check `no_price_in_pos`
   - If `true`, show a price input dialog
   - Pass the custom price to the action

2. **Example UI Check:**

```dart
  // Check if item already exists in cart (by product ID + variant ID)
  if (existingIndex >= 0) {
    // Update existing item quantity
    final existingItem = currentCart.cartItems[existingIndex];
    final updatedItem = CartItemsStruct(
      cartItemId: existingItem.cartItemId,
      cartItemProductId: existingItem.cartItemProductId,
      cartItemVariantId: existingItem.cartItemVariantId,
      cartItemProductName: existingItem.cartItemProductName,
      cartItemProductImageUrl: existingItem.cartItemProductImageUrl,
      cartItemUnitPrice: existingItem.cartItemUnitPrice,
      cartItemQuantity: existingItem.cartItemQuantity,
      cartItemOriginalPrice: existingItem.cartItemOriginalPrice,
      cartItemDiscountAmount: existingItem.cartItemDiscountAmount,
      cartItemDiscountReason: existingItem.cartItemDiscountReason,
      cartItemArticleGroupCode: existingItem.cartItemArticleGroupCode,
      cartItemProductCode: existingItem.cartItemProductCode,
      cartItemMetadata: null,
    );
  }
}



