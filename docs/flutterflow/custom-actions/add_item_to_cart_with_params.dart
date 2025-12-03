// FlutterFlow Custom Action: Add Item to Cart
// 
// STEP 1: Update the function signature to include parameters
// Replace: Future addItemToCart() async {
// With: Future addItemToCart(dynamic product, int quantity, [dynamic variant]) async {
//
// STEP 2: Then paste the code below inside the function body

// Get current cart from app state
final currentCart = FFAppState().cart;

// Generate unique ID for new item
final newItemId = DateTime.now().millisecondsSinceEpoch.toString();

// Determine variant ID (empty string if null)
final variantId = variant?.id ?? '';

// Create cart item
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

// Check if item already exists in cart
final existingIndex = currentCart.items.indexWhere(
  (item) => item.productId == product.id && item.variantId == variantId,
);

if (existingIndex >= 0) {
  // Update existing item quantity
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
      updatedAt: getCurrentTimestamp,
      metadata: currentCart.metadata,
    );
  });
} else {
  // Add new item to cart
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
      updatedAt: getCurrentTimestamp,
      metadata: currentCart.metadata,
    );
  });
}

