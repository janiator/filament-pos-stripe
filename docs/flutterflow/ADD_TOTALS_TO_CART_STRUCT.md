# Adding Calculated Totals to ShoppingCartStruct

## Overview

This guide shows how to add computed properties (getters) to the `ShoppingCartStruct` so you can access totals directly from the cart object, e.g., `cart.totalCartPrice` instead of calling a separate function.

## Implementation

### Step 1: Open ShoppingCartStruct in FlutterFlow

1. Go to **Custom Data Types** in FlutterFlow
2. Find and open `ShoppingCartStruct`
3. Look for the **Custom Code** section or where you can add custom code to the struct

### Step 2: Add Computed Properties

Add the following getter methods to the `ShoppingCartStruct` class. These should be added after the existing getters but before the `fromMap` method:

```dart
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
  /// Formula: tax = subtotal * 0.25
  /// Note: This assumes prices exclude tax
  int get totalTax => (subtotalExcludingTax * 0.25).round();

  /// Final total including tax and tip
  int get totalCartPrice => subtotalExcludingTax + totalTax + (cartTipAmount ?? 0);

  /// Total number of items in cart (sum of quantities)
  int get totalItemCount {
    return cartItems.fold(0, (sum, item) => sum + item.cartItemQuantity);
  }

  /// Check if cart is empty
  bool get isEmpty => cartItems.isEmpty;

  /// Check if cart has items
  bool get hasItems => cartItems.isNotEmpty;
```

### Step 3: Where to Add the Code

In FlutterFlow, you'll need to add this code in the **Custom Code** section of the `ShoppingCartStruct`. The exact location depends on FlutterFlow's UI, but it should be:

- After all the field getters/setters
- Before the `fromMap` static method
- Inside the `ShoppingCartStruct` class

### Step 4: Alternative - Direct File Edit (If FlutterFlow Supports It)

If FlutterFlow allows you to edit the struct file directly, add the computed properties after line 162 (after `hasCartMetadata()`) and before line 165 (before `static ShoppingCartStruct fromMap`):

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

## Usage

Once added, you can use the computed properties directly:

```dart
final cart = FFAppState().cart;

// Access totals directly
final linePrice = cart.totalLinePrice;
final discount = cart.totalDiscount;
final subtotal = cart.subtotalExcludingTax;
final tax = cart.totalTax;
final total = cart.totalCartPrice;
final itemCount = cart.totalItemCount;

// Display formatted
Text(
  formatNumber(
    cart.totalCartPrice / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
)
```

## Available Properties

| Property | Type | Description |
|----------|------|-------------|
| `totalLinePrice` | `int` | Sum of all (unit price × quantity) before discounts |
| `totalItemDiscounts` | `int` | Sum of discounts on individual items |
| `totalCartDiscounts` | `int` | Sum of cart-level discounts |
| `totalDiscount` | `int` | Combined total of all discounts |
| `subtotalExcludingTax` | `int` | Line price minus all discounts |
| `totalTax` | `int` | Tax amount (25% VAT) |
| `totalCartPrice` | `int` | Final total including tax and tip |
| `totalItemCount` | `int` | Total number of items (sum of quantities) |
| `isEmpty` | `bool` | True if cart has no items |
| `hasItems` | `bool` | True if cart has items |

## Notes

- All amounts are in **øre** (divide by 100 for display)
- Tax rate is fixed at 25% (Norwegian standard VAT)
- Calculations assume prices exclude tax
- Discounts are applied before tax calculation
- Tip is added after tax
- These are computed properties (getters), so they calculate on-demand
- They don't affect serialization (won't appear in `toMap()` or `fromMap()`)

## Testing

1. Add items to cart with different prices and quantities
2. Apply item-level discounts
3. Apply cart-level discounts
4. Add a tip
5. Verify all totals:
   ```dart
   print('Line Price: ${cart.totalLinePrice}');
   print('Discount: ${cart.totalDiscount}');
   print('Subtotal: ${cart.subtotalExcludingTax}');
   print('Tax: ${cart.totalTax}');
   print('Total: ${cart.totalCartPrice}');
   ```

## Important

⚠️ **FlutterFlow may regenerate struct files**, which could remove custom code. If this happens:
1. Keep a backup of your custom code
2. Re-add it after FlutterFlow regenerates
3. Consider using the separate `calculateCartTotals` action if struct modifications are not persistent

