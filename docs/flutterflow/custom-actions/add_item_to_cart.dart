// FlutterFlow Custom Action: Add Item to Cart
// 
// Parameters:
//   - product (Product type) - Required
//   - quantity (Double) - Default: 1.0 (supports decimals for continuous units)
//   - variant (ProductVariant) - Optional
//
// This action adds an item to the shopping cart or updates quantity if item already exists

void addItemToCart(
  dynamic product,
  double quantity,
  dynamic variant,
) {
  // Get current cart from app state
  final currentCart = FFAppState().cart;

  // Generate unique ID for new item
  final newItemId = DateTime.now().millisecondsSinceEpoch.toString();

  // Create cart item
  final cartItem = CartItemStruct(
    id: newItemId,
    productId: product.id,
    variantId: variant?.id ?? '',
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

  // Check if item already exists
  final existingIndex = currentCart.items.indexWhere(
    (item) => item.productId == product.id && 
              item.variantId == (variant?.id ?? ''),
  );

  if (existingIndex >= 0) {
    // Update quantity of existing item
    final existingItem = currentCart.items[existingIndex];
    final updatedItem = CartItemStruct(
      id: existingItem.id,
      productId: existingItem.productId,
      variantId: existingItem.variantId,
      productName: existingItem.productName,
      productImageUrl: existingItem.productImageUrl,
      unitPrice: existingItem.unitPrice,
      quantity: existingItem.quantity + quantity, // Both are now double
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
    // Add new item
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
}

