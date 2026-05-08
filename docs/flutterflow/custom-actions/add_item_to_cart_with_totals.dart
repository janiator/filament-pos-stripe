Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
) async {
  if (product == null) return;

  final qty = quantity ?? 1;
  final currentCart = FFAppState().cart;
  final variantId = variants?.id ?? 0;
  final variantIdString = variantId != 0 ? variantId.toString() : '';

  // Get price: use variant price if available, otherwise product price
  final unitPrice =
      variants?.variantPrice?.amount ?? product.productPrice.amount ?? 0;
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
  } else {
    // Add new item to cart
    // Generate unique ID for cart item
    final cartItemId = DateTime.now().millisecondsSinceEpoch.toString();

    final newCartItem = CartItemsStruct(
      cartItemId: cartItemId,
      cartItemProductId: product.id.toString(),
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

    final updatedItems = List<CartItemsStruct>.from(currentCart.cartItems);
    updatedItems.add(newCartItem);

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

  // Update cart totals after adding/updating item
  await updateCartTotals();
}

