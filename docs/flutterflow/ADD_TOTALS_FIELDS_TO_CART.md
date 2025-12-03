# Adding Total Fields to ShoppingCartStruct in FlutterFlow UI

## Step 1: Add Fields to ShoppingCartStruct

In FlutterFlow UI, go to **Custom Data Types** → **ShoppingCartStruct** and add these new fields:

### Fields to Add:

| Field Name | Type | Nullable | Default Value | Description |
|------------|------|----------|---------------|-------------|
| `cartTotalLinePrice` | Integer | No | `0` | Sum of all (unit price × quantity) before discounts |
| `cartTotalItemDiscounts` | Integer | No | `0` | Sum of discounts on individual items |
| `cartTotalCartDiscounts` | Integer | No | `0` | Sum of cart-level discounts |
| `cartTotalDiscount` | Integer | No | `0` | Combined total of all discounts |
| `cartSubtotalExcludingTax` | Integer | No | `0` | Line price minus all discounts |
| `cartTotalTax` | Integer | No | `0` | Tax amount (25% VAT) |
| `cartTotalCartPrice` | Integer | No | `0` | Final total including tax and tip |

### How to Add in FlutterFlow:

1. Open **Custom Data Types** in FlutterFlow
2. Find and click **ShoppingCartStruct**
3. Click **+ Add Field** (or similar button)
4. For each field:
   - **Name**: Use the field name from the table above (e.g., `cartTotalLinePrice`)
   - **Type**: Select **Integer** (or **Number**)
   - **Nullable**: Set to **No** (or uncheck nullable)
   - **Default Value**: Set to `0`
5. Repeat for all 7 fields

### Field Naming Convention:

FlutterFlow will automatically prefix with `cart` since it's in `ShoppingCartStruct`, so the actual field names in code will be:
- `cartTotalLinePrice`
- `cartTotalItemDiscounts`
- `cartTotalCartDiscounts`
- `cartTotalDiscount`
- `cartSubtotalExcludingTax`
- `cartTotalTax`
- `cartTotalCartPrice`

---

## Step 2: Update the `updateCartTotals` Action

After adding the fields, update the `updateCartTotals` custom action to calculate and store these values:

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

/// Calculate and update all cart totals
/// This action calculates totals and stores them in the cart struct
Future updateCartTotals() async {
  final cart = FFAppState().cart;
  
  // Initialize totals
  int totalLinePrice = 0;
  int totalItemDiscounts = 0;
  int totalCartDiscounts = 0;
  
  // Calculate line items totals
  for (var item in cart.cartItems) {
    // Line price = unit price * quantity
    final linePrice = item.cartItemUnitPrice * item.cartItemQuantity;
    totalLinePrice += linePrice;
    
    // Item discount = discount amount * quantity
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
  final totalTax = (subtotalExcludingTax * 0.25).round(); // 25% VAT
  final totalCartPrice = subtotalExcludingTax + totalTax + (cart.cartTipAmount ?? 0);
  
  // Update cart with calculated totals
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
      // Add the calculated totals
      cartTotalLinePrice: totalLinePrice,
      cartTotalItemDiscounts: totalItemDiscounts,
      cartTotalCartDiscounts: totalCartDiscounts,
      cartTotalDiscount: totalDiscount,
      cartSubtotalExcludingTax: subtotalExcludingTax,
      cartTotalTax: totalTax,
      cartTotalCartPrice: totalCartPrice,
    );
  });
}
```

---

## Step 3: Update Constructor Calls

After adding the fields, you'll need to update all places where `ShoppingCartStruct` is created to include the new fields. The easiest way is to set them to `0` initially, and they'll be calculated by `updateCartTotals()`.

### In `addItemToCart`:

```dart
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
    // Initialize totals to 0, will be calculated by updateCartTotals()
    cartTotalLinePrice: 0,
    cartTotalItemDiscounts: 0,
    cartTotalCartDiscounts: 0,
    cartTotalDiscount: 0,
    cartSubtotalExcludingTax: 0,
    cartTotalTax: 0,
    cartTotalCartPrice: 0,
  );
});

// Then call updateCartTotals() to calculate and store the actual values
await updateCartTotals();
```

**OR** better yet, preserve the existing totals when updating:

```dart
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
    // Preserve existing totals temporarily (will be recalculated)
    cartTotalLinePrice: currentCart.cartTotalLinePrice,
    cartTotalItemDiscounts: currentCart.cartTotalItemDiscounts,
    cartTotalCartDiscounts: currentCart.cartTotalCartDiscounts,
    cartTotalDiscount: currentCart.cartTotalDiscount,
    cartSubtotalExcludingTax: currentCart.cartSubtotalExcludingTax,
    cartTotalTax: currentCart.cartTotalTax,
    cartTotalCartPrice: currentCart.cartTotalCartPrice,
  );
});

// Recalculate totals after cart update
await updateCartTotals();
```

---

## Step 4: Usage in UI

After adding the fields and updating the action, you can access totals directly:

```dart
final cart = FFAppState().cart;

// Display totals
Text(
  formatNumber(
    cart.cartTotalCartPrice / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
)

Text('Subtotal: ${formatNumber(cart.cartSubtotalExcludingTax / 100.0, ...)}')
Text('Tax: ${formatNumber(cart.cartTotalTax / 100.0, ...)}')
Text('Discount: ${formatNumber(cart.cartTotalDiscount / 100.0, ...)}')
```

---

## Summary

1. ✅ Add 7 integer fields to `ShoppingCartStruct` in FlutterFlow UI
2. ✅ Update `updateCartTotals()` to calculate and store values in these fields
3. ✅ Call `updateCartTotals()` after any cart modification
4. ✅ Access totals via `cart.cartTotalCartPrice`, etc.

---

## Field Summary Table

| Field Name | Calculation |
|------------|-------------|
| `cartTotalLinePrice` | Sum of (unit price × quantity) for all items |
| `cartTotalItemDiscounts` | Sum of (item discount × quantity) for all items |
| `cartTotalCartDiscounts` | Sum of all cart-level discounts |
| `cartTotalDiscount` | `cartTotalItemDiscounts + cartTotalCartDiscounts` |
| `cartSubtotalExcludingTax` | `cartTotalLinePrice - cartTotalDiscount` |
| `cartTotalTax` | `cartSubtotalExcludingTax * 0.25` (25% VAT) |
| `cartTotalCartPrice` | `cartSubtotalExcludingTax + cartTotalTax + cartTipAmount` |

