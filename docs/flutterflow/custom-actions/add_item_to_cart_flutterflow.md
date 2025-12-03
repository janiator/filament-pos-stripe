# FlutterFlow Custom Action: Add Item to Cart

## Setup Instructions

1. In FlutterFlow, go to **Custom Code** → **Custom Actions**
2. Click **+ Add Action**
3. Name: `addItemToCart`
4. **Parameters** (add these in FlutterFlow UI):
   - `product` - Type: `Product` (or your product data type) - Required
   - `quantity` - Type: `Integer` - Required, Default: 1
   - `variant` - Type: `ProductVariant` (or your variant data type) - Optional

5. **Return Type**: Leave empty (void action)

6. **Action Code**: Copy the code below and paste it **ONLY between the comment markers** in FlutterFlow

## Code to Paste

**IMPORTANT**: Only paste the code between `/// MODIFY CODE ONLY BELOW THIS LINE` and `/// MODIFY CODE ONLY ABOVE THIS LINE` in FlutterFlow. Do NOT modify the boilerplate comments or import statements.

```dart
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
```

## Alternative: If `getCurrentTimestamp` doesn't work

If you get an error about `getCurrentTimestamp`, replace it with `DateTime.now()`:

```dart
updatedAt: DateTime.now(),
```

## Troubleshooting

### If you still get parsing errors:

1. **Start with minimal code** - First, try just this to verify the action works:
   ```dart
   final currentCart = FFAppState().cart;
   FFAppState().update(() {
     FFAppState().cart = currentCart;
   });
   ```

2. **Check parameter names** - Make sure the parameter names in FlutterFlow UI match exactly:
   - `product` (lowercase)
   - `quantity` (lowercase)
   - `variant` (lowercase, optional)

3. **Verify data types** - Ensure `CartItemStruct` and `ShoppingCartStruct` are imported/available in FlutterFlow

4. **Check for syntax errors** - Look for:
   - Missing commas
   - Unclosed parentheses
   - Incorrect null handling (`??` vs `?`)

5. **Try without optional variant** - First test with just `product` and `quantity`, then add `variant` later

## Step-by-Step Debugging

1. Create the action with just one line: `final test = FFAppState().cart;`
2. Save - if it works, add the next line
3. Continue adding lines one at a time until you find the problematic line
4. Once identified, fix that specific line

