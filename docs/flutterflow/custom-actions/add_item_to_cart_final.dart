// FlutterFlow Custom Action: Add Item to Cart
// 
// Updated to match your actual CartDataStruct schema:
// - Uses ProductStruct directly (not individual fields)
// - Uses quantity, type, lineDiscountType, lineDiscountAmount
//
// Function signature:
Future addItemToCart(dynamic product, int quantity, [dynamic variant]) async {
  // Get current cart from app state
  final currentCart = FFAppState().cart;

  // Convert product to ProductStruct if needed (or use directly if already ProductStruct)
  final productStruct = product is ProductStruct ? product : ProductStruct.fromMap(product.toMap());

  // Check if item already exists in cart (by product ID)
  final existingIndex = currentCart.cartData.indexWhere(
    (item) => item.product.id == productStruct.id,
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
      type: null, // Set to appropriate CartTypes value if needed
      quantity: quantity,
      product: productStruct,
      customer: null, // Or set to currentCart.customer if needed
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

