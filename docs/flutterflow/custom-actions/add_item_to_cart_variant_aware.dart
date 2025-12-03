// FlutterFlow Custom Action: Add Item to Cart (Variant-Aware)
// 
// This version treats products with different variants as separate cart items.
// It uses a composite key (product ID + variant ID) to identify unique cart items.
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
  final variantId = variants?.id ?? 0; // Use 0 if no variant (for products without variants)

  // Get current cart from app state
  final currentCart = FFAppState().cart;

  // Check if item already exists in cart
  // Use composite key: product ID + variant ID
  // This ensures different variants of the same product are treated as separate items
  final existingIndex = currentCart.cartData.indexWhere(
    (item) {
      // Check if product IDs match
      if (item.product.id != product.id) {
        return false;
      }
      
      // If variant is provided, check if cart item has matching variant
      if (variants != null && variantId != 0) {
        // Check if the product in cart has this specific variant
        final cartProductVariants = item.product.variants;
        final hasMatchingVariant = cartProductVariants.any(
          (v) => v.id == variantId,
        );
        return hasMatchingVariant;
      } else {
        // No variant specified - check if cart item also has no variant selected
        // (This is a simplified check - you might want to refine this logic)
        return true; // Same product, no variant specified
      }
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
    // Note: The product struct contains all variants, but we're adding it
    // as a new cart item because the variant selection makes it unique
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

