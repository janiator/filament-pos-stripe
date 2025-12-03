// FlutterFlow Custom Action: Add Item to Cart
// 
// Updated to match your actual schema:
// - Uses CartDataStruct (not CartItemStruct)
// - Uses cartData (not items)
//
// Function signature:
Future addItemToCart(dynamic product, int quantity, [dynamic variant]) async {
  // Get current cart from app state
  final currentCart = FFAppState().cart;

  // Generate unique ID for new item
  final newItemId = DateTime.now().millisecondsSinceEpoch.toString();

  // Determine variant ID (empty string if null)
  final variantId = variant?.id ?? '';

  // Create cart item using CartDataStruct
  final cartItem = CartDataStruct(
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

  // Check if item already exists in cart
  final existingIndex = currentCart.cartData.indexWhere(
    (item) => item.productId == product.id && item.variantId == variantId,
  );

  if (existingIndex >= 0) {
    // Update existing item quantity
    final existingItem = currentCart.cartData[existingIndex];
    final updatedItem = CartDataStruct(
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
    
    final updatedItems = List<CartDataStruct>.from(currentCart.cartData);
    updatedItems[existingIndex] = updatedItem;
    
    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        id: currentCart.id,
        cartData: updatedItems,
        employee: currentCart.employee,
        customer: currentCart.customer,
      );
    });
  } else {
    // Add new item to cart
    final updatedItems = List<CartDataStruct>.from(currentCart.cartData);
    updatedItems.add(cartItem);
    
    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        id: currentCart.id,
        cartData: updatedItems,
        employee: currentCart.employee,
        customer: currentCart.customer,
      );
    });
  }
}

