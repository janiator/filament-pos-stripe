# Cart Totals Calculation Implementation Guide

## Overview

This guide explains how to add calculated cart totals to your FlutterFlow POS app. The totals include:
- **Total Line Price**: Sum of all item prices × quantities (before discounts)
- **Total Item Discounts**: Sum of discounts applied to individual items
- **Total Cart Discounts**: Sum of discounts applied to the entire cart
- **Total Discount**: Combined total of all discounts
- **Subtotal Excluding Tax**: Line price minus all discounts
- **Total Tax**: Tax amount (25% VAT in Norway)
- **Total Cart Price**: Final total including tax and tip

## Implementation Steps

### Step 1: Create Custom Action

1. In FlutterFlow, go to **Custom Code** → **Actions**
2. Create a new action named `calculateCartTotals`
3. Set return type to `Map<String, int>`
4. Paste the code from `docs/flutterflow/custom-actions/calculate_cart_totals.dart`

### Step 2: Usage in FlutterFlow

#### Option A: Call Action and Store in Variables

When you need to display cart totals:

1. Add an **Action** block
2. Call `calculateCartTotals`
3. Store the result in a local variable (e.g., `cartTotals`)
4. Access values like:
   - `cartTotals['totalLinePrice']`
   - `cartTotals['totalDiscount']`
   - `cartTotals['subtotalExcludingTax']`
   - `cartTotals['totalTax']`
   - `cartTotals['totalCartPrice']`

#### Option B: Display Directly in UI

In any widget that displays totals:

1. Use a **Custom Function** or **Action** block
2. Call `calculateCartTotals()`
3. Format the values using `formatNumber()`:
   ```dart
   formatNumber(
     cartTotals['totalCartPrice'] / 100.0,
     formatType: FormatType.custom,
     format: '\'kr \'#.##\',-\'',
     locale: 'nb_no',
   )
   ```

### Step 3: Example Usage in Checkout Screen

```dart
// In your checkout page widget
final cartTotals = await calculateCartTotals();

// Display subtotal
Text(
  formatNumber(
    cartTotals['subtotalExcludingTax'] / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
)

// Display tax
Text(
  formatNumber(
    cartTotals['totalTax'] / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
)

// Display total
Text(
  formatNumber(
    cartTotals['totalCartPrice'] / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
)
```

## Calculation Details

### Tax Calculation

The tax is calculated using Norway's standard VAT rate of 25%:

```dart
totalTax = (subtotalExcludingTax * 0.25).round()
```

**Note**: This assumes prices are stored excluding tax. If your prices include tax, use:
```dart
totalTax = (subtotalExcludingTax * 0.25 / 1.25).round()
```

### Price Format

All prices are stored in **øre** (smallest currency unit):
- 1 NOK = 100 øre
- When displaying, divide by 100: `price / 100.0`

### Discount Calculation

Discounts are calculated in two parts:
1. **Item Discounts**: Applied per item (stored in `cartItemDiscountAmount`)
2. **Cart Discounts**: Applied to the entire cart (stored in `cartDiscounts` list)

Total discount = Item discounts + Cart discounts

## Alternative: Add Computed Properties to Cart Struct

If you prefer computed properties instead of a function, you can add getter methods to the `ShoppingCartStruct` in FlutterFlow's custom code:

```dart
// Add to ShoppingCartStruct class
int get totalLinePrice {
  return cartItems.fold(0, (sum, item) => 
    sum + (item.cartItemUnitPrice * item.cartItemQuantity)
  );
}

int get totalItemDiscounts {
  return cartItems.fold(0, (sum, item) => 
    sum + ((item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity)
  );
}

int get totalCartDiscounts {
  return cartDiscounts.fold(0, (sum, discount) => 
    sum + discount.cartDiscountAmount
  );
}

int get totalDiscount => totalItemDiscounts + totalCartDiscounts;

int get subtotalExcludingTax => totalLinePrice - totalDiscount;

int get totalTax => (subtotalExcludingTax * 0.25).round();

int get totalCartPrice => subtotalExcludingTax + totalTax + (cartTipAmount ?? 0);
```

**Note**: FlutterFlow may not support adding custom getters to structs directly. Use the custom action approach if this doesn't work.

## Testing

1. Add items to cart with different prices and quantities
2. Apply item-level discounts
3. Apply cart-level discounts
4. Add a tip
5. Verify all totals calculate correctly:
   - Line price = sum of (price × quantity)
   - Discounts = sum of item discounts + cart discounts
   - Subtotal = line price - discounts
   - Tax = subtotal × 0.25
   - Total = subtotal + tax + tip

## Notes

- All amounts are in **øre** (divide by 100 for display)
- Tax rate is fixed at 25% (Norwegian standard VAT)
- The calculation assumes prices exclude tax
- Discounts are applied before tax calculation
- Tip is added after tax

