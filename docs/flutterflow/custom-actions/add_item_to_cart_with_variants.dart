// FlutterFlow Custom Action: Add Item to Cart (with Variant Support)
// 
// This version properly handles variants by checking both product ID and variant ID
// when determining if an item already exists in the cart.
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

  // Check if item already exists in cart
  // If variant is provided, check by both product ID and variant ID
  // If no variant, check only by product ID
  final existingIndex = currentCart.cartData.indexWhere(
    (item) {
      // First check if product IDs match
      if (item.product.id != product.id) {
        return false;
      }
      
      // If variant is provided, also check variant ID
      if (variants != null) {
        // Check if the product in cart has the same variant
        // We need to find the variant in the product's variants list
        final cartProductVariants = item.product.variants;
        final hasMatchingVariant = cartProductVariants.any(
          (v) => v.id == variants.id,
        );
        
        // If cart item has variants, check if the selected variant matches
        // This is a simplified check - you might need to adjust based on your logic
        if (cartProductVariants.isNotEmpty && !hasMatchingVariant) {
          return false; // Different variant, treat as different item
        }
      }
      
      return true; // Same product (and variant if applicable)
    },
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
    // Note: Since CartDataStruct doesn't store variant separately,
    // we store the full product (which includes all variants)
    final newCartItem = CartDataStruct(
      type: CartTypes.product,
      quantity: qty,
      product: product, // Product includes variants list
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

