# Cart-Level Discount Management

## Overview

This guide explains how to apply cart-level discounts in the POS system. Cart-level discounts are applied to the entire cart subtotal (after item-level discounts).

## Custom Action: `applyCartDiscount`

### Function Signature

```dart
Future applyCartDiscount(
  String discountType,
  double discountValue,
  String? discountReason,
) async
```

### Parameters

- `discountType` (String) - One of:
  - `"Ingen"` - Remove all cart discounts
  - `"Prosent"` - Percentage discount (0-100)
  - `"Verdi"` - Fixed amount discount in øre
- `discountValue` (double) - The discount value:
  - For `"Prosent"`: Percentage 0-100 (e.g., `10` for 10%)
  - For `"Verdi"`: Amount in øre (e.g., `5000` for 50.00 NOK)
  - For `"Ingen"`: Ignored
- `discountReason` (String?) - Optional reason for the discount

### Behavior

1. **"Ingen" (Remove)**:
   - Removes all cart-level discounts
   - Recalculates cart totals

2. **"Prosent" (Percentage)**:
   - Calculates discount from cart subtotal: `(cartSubtotal × discountValue / 100)`
   - Ensures discount doesn't exceed cart subtotal
   - Stores both `cartDiscountAmount` and `cartDiscountPercentage`

3. **"Verdi" (Fixed Amount)**:
   - Uses `discountValue` directly as discount amount in øre
   - Ensures discount doesn't exceed cart subtotal
   - Stores only `cartDiscountAmount` (percentage is null)

### Cart Subtotal Calculation

The discount is calculated from the cart subtotal, which is:
```
cartSubtotal = cartTotalLinePrice - cartTotalItemDiscounts
```

This means:
- Cart discounts are applied **after** item-level discounts
- The discount is calculated from the remaining amount after item discounts

### Discount Validation

- **Percentage discount**: Automatically capped at 100%
- **Amount discount**: Automatically capped at cart subtotal
- If discount would exceed cart subtotal, it's set to the cart subtotal

## Usage Examples

### Apply 10% Cart Discount

```dart
await applyCartDiscount(
  'Prosent',  // Percentage discount
  10.0,       // 10%
  'Summer sale', // Reason
);
```

**Calculation:**
- Cart subtotal: 1000 NOK (100000 øre)
- Discount: 1000 NOK × 10% = 100 NOK (10000 øre)

### Apply 50 NOK Fixed Discount

```dart
await applyCartDiscount(
  'Verdi',    // Fixed amount
  5000.0,      // 50 NOK in øre
  'Customer loyalty discount',
);
```

**Calculation:**
- Cart subtotal: 1000 NOK (100000 øre)
- Discount: 50 NOK (5000 øre)

### Remove Cart Discount

```dart
await applyCartDiscount(
  'Ingen',     // Remove discount
  0.0,         // Ignored
  null,        // Ignored
);
```

## Implementation in FlutterFlow

### Step 1: Create Custom Action

1. Go to **Custom Code** → **Custom Actions**
2. Create new action: `applyCartDiscount`
3. Add parameters:
   - `discountType` (String) - Required
   - `discountValue` (double) - Required
   - `discountReason` (String?) - Optional
4. Set return type: `Future`
5. Paste the code from `docs/flutterflow/custom-actions/apply_cart_discount.dart`

### Step 2: Usage in Popup

**Example: Cart Discount Popup**

```dart
// In your cart discount popup widget
// Assuming you have:
// - discountType (String): "Ingen", "Prosent", or "Verdi"
// - discountValue (double): The discount value
// - discountReason (String?): Optional reason

// When user clicks "Apply Discount" button
onPressed: () async {
  await applyCartDiscount(
    discountType,
    discountValue,
    discountReason,
  );
  
  // Close popup or navigate back
  Navigator.pop(context);
}
```

### Step 3: Display Current Cart Discount

```dart
// Check if cart has discounts
if (FFAppState().cart.cartDiscounts.isNotEmpty) {
  final discount = FFAppState().cart.cartDiscounts.first;
  
  // Display discount amount
  Text('Cart Discount: ${discount.cartDiscountAmount / 100.0} NOK');
  
  // Display discount reason if available
  if (discount.cartDiscountReason != null) {
    Text('Reason: ${discount.cartDiscountReason}');
  }
}
```

## Discount Calculation Flow

```
1. User adds items to cart
   └─> cartTotalLinePrice calculated

2. User applies item-level discounts
   └─> cartTotalItemDiscounts calculated
   └─> Cart subtotal = cartTotalLinePrice - cartTotalItemDiscounts

3. User applies cart-level discount
   └─> Discount calculated from cart subtotal
   └─> cartTotalCartDiscounts updated
   └─> cartTotalDiscount = cartTotalItemDiscounts + cartTotalCartDiscounts

4. Final totals calculated
   └─> cartSubtotalExcludingTax = cartTotalLinePrice - cartTotalDiscount - cartTotalTax
   └─> cartTotalCartPrice = cartTotalLinePrice - cartTotalDiscount + cartTipAmount
```

## Important Notes

1. **Single Cart Discount**: The current implementation replaces all existing cart discounts with a single new discount. If you need to support multiple cart discounts, modify the logic to append instead of replace.

2. **Discount Order**: Cart discounts are applied after item discounts, so they're calculated from the remaining subtotal.

3. **Automatic Capping**: Discounts are automatically capped at the cart subtotal to prevent negative totals.

4. **Totals Recalculation**: The function automatically calls `updateCartTotals()` after applying/removing the discount.

5. **Discount ID**: A new discount ID is generated using `DateTime.now().millisecondsSinceEpoch.toString()`.

## Testing

### Test Percentage Discount

1. Add items totaling 100 NOK (10000 øre)
2. Apply 10% cart discount
3. Expected: Cart discount = 10 NOK (1000 øre)
4. Verify: `cartTotalCartDiscounts = 1000`

### Test Fixed Amount Discount

1. Add items totaling 100 NOK (10000 øre)
2. Apply 50 NOK (5000 øre) cart discount
3. Expected: Cart discount = 50 NOK (5000 øre)
4. Verify: `cartTotalCartDiscounts = 5000`

### Test Discount Capping

1. Add items totaling 100 NOK (10000 øre)
2. Apply 150 NOK (15000 øre) cart discount
3. Expected: Cart discount = 100 NOK (10000 øre) - capped at subtotal
4. Verify: `cartTotalCartDiscounts = 10000`

### Test Remove Discount

1. Apply a cart discount
2. Apply "Ingen" discount type
3. Expected: `cartDiscounts` list is empty
4. Verify: `cartTotalCartDiscounts = 0`

## Related Functions

- `applyItemDiscount()` - Apply discounts to individual cart items
- `updateCartTotals()` - Recalculate all cart totals after discount changes

