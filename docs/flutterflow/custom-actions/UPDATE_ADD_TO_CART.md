# Update addItemToCart Function

## Summary

Yes, the `addItemToCart` function should be updated to handle custom price input. Here's what needs to change:

## Required Changes

### 1. Update Function Signature

Add `customPrice` parameter:

```dart
Future addItemToCart(
  ProductStruct? product,
  int? quantity,
) async {
  if (product == null) return;
}

### 2. Key Changes:

- Check if item already exists in cart (by product ID + variant ID)
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
      cartItemDiscountAmount: null,
      cartItemDiscountReason: null,
      cartItemArticleGroupCode: existingItem.cartItemArticleGroupCode,
      cartItemProductCode: existingItem.cartItemProductCode,
      cartItemMetadata: null,
    );
  }
}




