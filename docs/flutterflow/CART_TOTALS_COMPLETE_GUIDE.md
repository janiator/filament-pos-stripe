# Complete Guide: Adding Cart Totals to FlutterFlow

This guide covers two approaches:
1. **Adding computed properties directly to ShoppingCartStruct** (recommended)
2. **Creating a custom action to calculate totals** (alternative/backup)

---

## Part 1: Adding Computed Properties to ShoppingCartStruct

### Step 1: Open ShoppingCartStruct in FlutterFlow

1. In FlutterFlow, go to **Custom Data Types** (or **Data Types**)
2. Find and click on **ShoppingCartStruct**
3. Look for a **Custom Code** tab or section

### Step 2: Add Custom Code

FlutterFlow may have different ways to add custom code:

#### Option A: Custom Code Tab
1. Click on the **Custom Code** tab in the struct editor
2. You should see the generated struct code
3. Find the line with `bool hasCartMetadata() => _cartMetadata != null;`
4. Add the computed properties code after that line

#### Option B: Custom Code Section
1. Look for a **"Custom Code"** or **"Additional Code"** section
2. Some FlutterFlow versions have a separate area for custom code
3. Paste the code there

#### Option C: Direct File Edit (If Available)
1. If FlutterFlow allows direct file editing, open:
   `lib/backend/schema/structs/shopping_cart_struct.dart`
2. Add the code after line 162

### Step 3: Code to Add

Add this code **after** `hasCartMetadata()` and **before** `static ShoppingCartStruct fromMap`:

```dart
  bool hasCartMetadata() => _cartMetadata != null;

  // ==========================================
  // COMPUTED PROPERTIES - Cart Totals
  // ==========================================

  /// Total line price: Sum of all (unit price × quantity) before discounts
  int get totalLinePrice {
    return cartItems.fold(0, (sum, item) => 
      sum + (item.cartItemUnitPrice * item.cartItemQuantity)
    );
  }

  /// Total discounts applied to individual items
  int get totalItemDiscounts {
    return cartItems.fold(0, (sum, item) => 
      sum + ((item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity)
    );
  }

  /// Total discounts applied to the entire cart
  int get totalCartDiscounts {
    return cartDiscounts.fold(0, (sum, discount) => 
      sum + discount.cartDiscountAmount
    );
  }

  /// Combined total of all discounts (item + cart)
  int get totalDiscount => totalItemDiscounts + totalCartDiscounts;

  /// Subtotal excluding tax: Line price minus all discounts
  int get subtotalExcludingTax => totalLinePrice - totalDiscount;

  /// Total tax amount (25% VAT in Norway)
  int get totalTax => (subtotalExcludingTax * 0.25).round();

  /// Final total including tax and tip
  int get totalCartPrice => subtotalExcludingTax + totalTax + (cartTipAmount ?? 0);

  /// Total number of items in cart
  int get totalItemCount {
    return cartItems.fold(0, (sum, item) => sum + item.cartItemQuantity);
  }

  /// Check if cart is empty
  bool get isEmpty => cartItems.isEmpty;

  /// Check if cart has items
  bool get hasItems => cartItems.isNotEmpty;

  static ShoppingCartStruct fromMap(Map<String, dynamic> data) =>
```

### Step 4: Verify

After adding, the computed properties will be available as:
- `cart.totalLinePrice`
- `cart.totalDiscount`
- `cart.subtotalExcludingTax`
- `cart.totalTax`
- `cart.totalCartPrice`
- etc.

---

## Part 2: Creating Custom Action to Calculate Totals

If you prefer using a custom action (or if computed properties don't work), follow these steps:

### Step 1: Create the Custom Action

1. In FlutterFlow, go to **Custom Code** → **Actions**
2. Click **+ Add Action** or **Create New Action**
3. Name it: `updateCartTotals`
4. Set **Return Type**: `void` (or no return type)
5. **No parameters needed** (it reads from `FFAppState().cart`)

### Step 2: Add the Action Code

Paste this code in the action editor:

```dart
// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart'; // Imports other custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

/// Update cart totals by recalculating all values
/// This action recalculates totals and updates the cart's updatedAt timestamp
Future updateCartTotals() async {
  final cart = FFAppState().cart;
  
  // Calculate totals
  int totalLinePrice = 0;
  int totalItemDiscounts = 0;
  int totalCartDiscounts = 0;
  
  // Calculate line items totals
  for (var item in cart.cartItems) {
    final linePrice = item.cartItemUnitPrice * item.cartItemQuantity;
    totalLinePrice += linePrice;
    
    final itemDiscount = (item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity;
    totalItemDiscounts += itemDiscount;
  }
  
  // Calculate cart-level discounts
  for (var discount in cart.cartDiscounts) {
    totalCartDiscounts += discount.cartDiscountAmount;
  }
  
  // Calculate final totals
  final totalDiscount = totalItemDiscounts + totalCartDiscounts;
  final subtotalExcludingTax = totalLinePrice - totalDiscount;
  final totalTax = (subtotalExcludingTax * 0.25).round();
  final totalCartPrice = subtotalExcludingTax + totalTax + (cart.cartTipAmount ?? 0);
  
  // Note: If you're using computed properties, you don't need to store these
  // But if you want to cache them, you could add fields to CartMetadataStruct
  // For now, totals are calculated on-demand via computed properties
  
  // Update the cart's updatedAt timestamp
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      cartId: cart.cartId,
      cartPosSessionId: cart.cartPosSessionId,
      cartItems: cart.cartItems,
      cartDiscounts: cart.cartDiscounts,
      cartTipAmount: cart.cartTipAmount,
      cartCustomerId: cart.cartCustomerId,
      cartCustomerName: cart.cartCustomerName,
      cartCreatedAt: cart.cartCreatedAt,
      cartUpdatedAt: getCurrentTimestamp.toString(),
      cartMetadata: cart.cartMetadata,
    );
  });
}
```

**Note**: This action mainly updates the `cartUpdatedAt` timestamp. If you're using computed properties (Part 1), the totals are calculated automatically and you don't need to store them.

---

## Part 3: Calling the Action from Other Custom Actions

### Step 1: Update `addItemToCart` Action

Open your `addItemToCart` custom action and add a call to `updateCartTotals()` at the end:

```dart
Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
) async {
  if (product == null) return;

  final qty = quantity ?? 1;
  final currentCart = FFAppState().cart;
  final variantId = variants?.id ?? 0;
  final variantIdString = variantId != 0 ? variantId.toString() : '';

  // ... existing code for adding item to cart ...

  // After updating the cart, call updateCartTotals
  await updateCartTotals();
}
```

### Step 2: Update Other Cart Actions

Add the same call to other cart-related actions:

#### `removeItemFromCart`
```dart
Future removeItemFromCart(String cartItemId) async {
  // ... existing removal code ...
  
  // Update totals after removal
  await updateCartTotals();
}
```

#### `updateItemQuantity`
```dart
Future updateItemQuantity(String cartItemId, int quantity) async {
  // ... existing update code ...
  
  // Update totals after quantity change
  await updateCartTotals();
}
```

#### `applyCartDiscount`
```dart
Future applyCartDiscount(CartDiscountsStruct discount) async {
  // ... existing discount code ...
  
  // Update totals after discount
  await updateCartTotals();
}
```

#### `removeCartDiscount`
```dart
Future removeCartDiscount(String discountId) async {
  // ... existing removal code ...
  
  // Update totals after discount removal
  await updateCartTotals();
}
```

#### `updateCartTip`
```dart
Future updateCartTip(int tipAmount) async {
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      // ... update tip amount ...
      cartTipAmount: tipAmount,
      // ... other fields ...
    );
  });
  
  // Update totals after tip change
  await updateCartTotals();
}
```

### Step 3: Import the Action

Make sure your custom actions import other actions:

```dart
import 'index.dart'; // Imports other custom actions
```

This line should already be in your custom actions. It allows you to call `updateCartTotals()` from other actions.

---

## Part 4: Complete Example - Updated `addItemToCart`

Here's how your complete `addItemToCart` should look with the totals update:

```dart
// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart'; // Imports other custom actions
import '/flutter_flow/custom_functions.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
) async {
  if (product == null) return;

  final qty = quantity ?? 1;
  final currentCart = FFAppState().cart;
  final variantId = variants?.id ?? 0;
  final variantIdString = variantId != 0 ? variantId.toString() : '';

  // Get price: use variant price if available, otherwise product price
  final unitPrice =
      variants?.variantPrice?.amount ?? product.productPrice.amount ?? 0;
  final originalPrice = unitPrice;

  // Get product image
  String productImageUrl = '';
  if (variants != null && variants.imageUrl.isNotEmpty) {
    productImageUrl = variants.imageUrl;
  } else if (product.images.isNotEmpty && product.images.first.isNotEmpty) {
    productImageUrl = product.images.first;
  }

  // Check if item already exists in cart
  final existingIndex = currentCart.cartItems.indexWhere(
    (item) {
      if (item.cartItemProductId != product.id.toString()) {
        return false;
      }
      return item.cartItemVariantId == variantIdString;
    },
  );

  if (existingIndex >= 0) {
    // Update existing item quantity
    final existingItem = currentCart.cartItems[existingIndex];
    final updatedItem = CartItemsStruct(
      cartItemId: existingItem.cartItemId,
      cartItemProductId: existingItem.cartItemProductId,
      cartItemVariantId: existingItem.cartItemVariantId,
      cartItemProductName: existingItem.cartItemProductName,
      cartItemProductImageUrl: existingItem.cartItemProductImageUrl,
      cartItemUnitPrice: existingItem.cartItemUnitPrice,
      cartItemQuantity: existingItem.cartItemQuantity + qty,
      cartItemOriginalPrice: existingItem.cartItemOriginalPrice,
      cartItemDiscountAmount: existingItem.cartItemDiscountAmount,
      cartItemDiscountReason: existingItem.cartItemDiscountReason,
      cartItemArticleGroupCode: existingItem.cartItemArticleGroupCode,
      cartItemProductCode: existingItem.cartItemProductCode,
      cartItemMetadata: existingItem.cartItemMetadata,
    );

    final updatedItems = List<CartItemsStruct>.from(currentCart.cartItems);
    updatedItems[existingIndex] = updatedItem;

    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        cartId: currentCart.cartId,
        cartPosSessionId: currentCart.cartPosSessionId,
        cartItems: updatedItems,
        cartDiscounts: currentCart.cartDiscounts,
        cartTipAmount: currentCart.cartTipAmount,
        cartCustomerId: currentCart.cartCustomerId,
        cartCustomerName: currentCart.cartCustomerName,
        cartCreatedAt: currentCart.cartCreatedAt,
        cartUpdatedAt: getCurrentTimestamp.toString(),
        cartMetadata: currentCart.cartMetadata,
      );
    });
  } else {
    // Add new item to cart
    final cartItemId = DateTime.now().millisecondsSinceEpoch.toString();

    final newCartItem = CartItemsStruct(
      cartItemId: cartItemId,
      cartItemProductId: product.id.toString(),
      cartItemVariantId: variantIdString,
      cartItemProductName: product.name,
      cartItemProductImageUrl: productImageUrl,
      cartItemUnitPrice: unitPrice,
      cartItemQuantity: qty,
      cartItemOriginalPrice: originalPrice,
      cartItemDiscountAmount: null,
      cartItemDiscountReason: null,
      cartItemArticleGroupCode: product.taxCode ?? '',
      cartItemProductCode: product.stripeProductId ?? '',
      cartItemMetadata: null,
    );

    final updatedItems = List<CartItemsStruct>.from(currentCart.cartItems);
    updatedItems.add(newCartItem);

    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        cartId: currentCart.cartId,
        cartPosSessionId: currentCart.cartPosSessionId,
        cartItems: updatedItems,
        cartDiscounts: currentCart.cartDiscounts,
        cartTipAmount: currentCart.cartTipAmount,
        cartCustomerId: currentCart.cartCustomerId,
        cartCustomerName: currentCart.cartCustomerName,
        cartCreatedAt: currentCart.cartCreatedAt,
        cartUpdatedAt: getCurrentTimestamp.toString(),
        cartMetadata: currentCart.cartMetadata,
      );
    });
  }

  // Update cart totals after adding/updating item
  await updateCartTotals();
}
```

---

## Part 5: Using Totals in Your UI

### If Using Computed Properties (Part 1):

```dart
final cart = FFAppState().cart;

// Display totals directly
Text(
  formatNumber(
    cart.totalCartPrice / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
)

Text('Subtotal: ${formatNumber(cart.subtotalExcludingTax / 100.0, ...)}')
Text('Tax: ${formatNumber(cart.totalTax / 100.0, ...)}')
Text('Discount: ${formatNumber(cart.totalDiscount / 100.0, ...)}')
```

### If Using Custom Action (Part 2):

You'll still use computed properties (if added) or call `calculateCartTotals()` action that returns a map.

---

## Summary

**Recommended Approach:**
1. ✅ Add computed properties to `ShoppingCartStruct` (Part 1)
2. ✅ Create `updateCartTotals()` action to update timestamp (Part 2)
3. ✅ Call `updateCartTotals()` from all cart-modifying actions (Part 3)

**Why this approach?**
- Computed properties calculate totals automatically
- No need to manually calculate and store totals
- Always up-to-date when you access `cart.totalCartPrice`
- `updateCartTotals()` just updates the timestamp for tracking

**Alternative:**
If computed properties don't work in FlutterFlow, use the `calculateCartTotals()` action that returns a map of totals.

