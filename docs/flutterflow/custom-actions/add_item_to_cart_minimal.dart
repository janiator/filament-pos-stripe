// MINIMAL VERSION - Use this if the full version doesn't work
// Paste ONLY the code below between FlutterFlow's comment markers

final currentCart = FFAppState().cart;
final newItemId = DateTime.now().millisecondsSinceEpoch.toString();
final variantId = variant?.id ?? '';

final cartItem = CartItemStruct(
  id: newItemId,
  productId: product.id,
  variantId: variantId,
  productName: product.name,
  productImageUrl: product.imageUrl ?? '',
  unitPrice: variant?.price ?? product.price,
  quantity: quantity,
  originalPrice: variant?.price ?? product.price,
  discountAmount: null,
  discountReason: null,
  articleGroupCode: product.articleGroupCode ?? '',
  productCode: product.productCode ?? '',
  metadata: null,
);

final existingIndex = currentCart.items.indexWhere(
  (item) => item.productId == product.id && item.variantId == variantId,
);

if (existingIndex >= 0) {
  final existingItem = currentCart.items[existingIndex];
  final updatedItem = CartItemStruct(
    id: existingItem.id,
    productId: existingItem.productId,
    variantId: existingItem.variantId,
    productName: existingItem.productName,
    productImageUrl: existingItem.productImageUrl,
    unitPrice: existingItem.unitPrice,
    quantity: existingItem.quantity + quantity,
    originalPrice: existingItem.originalPrice,
    discountAmount: existingItem.discountAmount,
    discountReason: existingItem.discountReason,
    articleGroupCode: existingItem.articleGroupCode,
    productCode: existingItem.productCode,
    metadata: existingItem.metadata,
  );
  
  final updatedItems = List<CartItemStruct>.from(currentCart.items);
  updatedItems[existingIndex] = updatedItem;
  
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      id: currentCart.id,
      posSessionId: currentCart.posSessionId,
      items: updatedItems,
      discounts: currentCart.discounts,
      tipAmount: currentCart.tipAmount,
      customerId: currentCart.customerId,
      customerName: currentCart.customerName,
      createdAt: currentCart.createdAt,
      updatedAt: DateTime.now(),
      metadata: currentCart.metadata,
    );
  });
} else {
  final updatedItems = List<CartItemStruct>.from(currentCart.items);
  updatedItems.add(cartItem);
  
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      id: currentCart.id,
      posSessionId: currentCart.posSessionId,
      items: updatedItems,
      discounts: currentCart.discounts,
      tipAmount: currentCart.tipAmount,
      customerId: currentCart.customerId,
      customerName: currentCart.customerName,
      createdAt: currentCart.createdAt,
      updatedAt: DateTime.now(),
      metadata: currentCart.metadata,
    );
  });
}

