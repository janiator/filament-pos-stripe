# FlutterFlow custom actions

## Summary

Yes, update `addItemToCart` function should be updated to handle custom price input. Here's the recommended approach:

1. **Update Function Signature**: Add `customPrice` parameter
2. **Check if item already exists**: Use the existing pattern
3. **Update existing item quantity**: Follow the same structure

## Recommended Changes

```dart
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
    cartItemId: existingItem.cartItemId,
    cartItemProductId: existingItem.cartItemProductId,
    cartItemVariantId: existingItem.cartItemVariantId,
    cartItemProductName: existingItem.cartItemProductName,
    cartItemProductImageUrl: existingItem.cartItemProductImageUrl,
    cartItemUnitPrice: existingItem.cartItemUnitPrice,
    cartItemQuantity: existingItem.cartItemQuantity + qty,
    cartItemOriginalPrice: existingItem.cartItemOriginalPrice,
    cartItemDiscountAmount: existingItem.cartItemDiscountAmount,
    cartItemDiscountReason: existingItem.cartItemDiscountReason,
    cartItemArticleGroupCode: existingItem.cartItemArticleGroupCode,
    cartItemProductCode: existingItem.cartItemProductCode,
    cartItemMetadata: existingItem.cartItemMetadata,
  );

  final updatedItems = List<CartItemsStruct>.from(currentCart.cartItems);
  updatedItems[existingIndex] = updatedItem;

  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      cartId: currentCart.cartId,
      cartPosSessionId: currentCart.cartPosSessionId,
      cartItems: updatedItems,
      cartDiscounts: currentCart.cartDiscounts,
      cartTipAmount: currentCart.cartTipAmount,
      cartCustomerId: currentCart.cartCustomerId,
      cartCustomerName: currentCart.cartCustomerName,
      cartCreatedAt: currentCart.cartCreatedAt,
      cartUpdatedAt: getCurrentTimestamp.toString(),
      cartMetadata: currentCart.cartMetadata,
    );
  });
}




