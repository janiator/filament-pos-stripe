// FlutterFlow Custom Action: Add Item to Cart
// 
// Complete working version matching your function signature
//
// Function signature:
// Future addItemToCart(ProductStruct? product, VariantsStruct? variants, int? quantity) async {

Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
) async {
  // Validate required parameters
  if (product == null) {
    return; // Can't add null product
  }
  
  final qty = quantity ?? 1; // Default to 1 if quantity is null

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
      quantity: existingItem.quantity + qty,
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
      quantity: qty,
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
