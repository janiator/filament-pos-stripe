// FlutterFlow Custom Action: Add Item to Cart
// 
// Working version with correct function signature
//
// Function signature:
Future addItemToCart(ProductStruct? product, int quantity, [VariantsStruct? variant]) async {
  // Get current cart from app state
  final currentCart = FFAppState().cart;

  // Check if item already exists in cart (by product ID)
  final existingIndex = currentCart.cartData.indexWhere(
    (item) => item.product.id == product.id,
  );

  if (existingIndex >= 0) {
    // Update existing item quantity
    final existingItem = currentCart.cartData[existingIndex];
    final updatedItem = CartDataStruct(
      type: existingItem.type,
      quantity: existingItem.quantity + quantity,
      product: existingItem.product,
      customer: existingItem.customer,
      lineDiscountType: existingItem.lineDiscountType,
      lineDiscountAmount: existingItem.lineDiscountAmount,
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
    final newCartItem = CartDataStruct(
      type: CartTypes.product,
      quantity: quantity,
      product: product,
      customer: null,
      lineDiscountType: null,
      lineDiscountAmount: null,
    );
    
    final updatedItems = List<CartDataStruct>.from(currentCart.cartData);
    updatedItems.add(newCartItem);
    
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

