# Item Discount Management - Custom Actions

## Overview

This guide covers a single custom action for applying and removing discounts on individual cart items. The action supports:
- **"Ingen"** (None) - Remove discount
- **"Prosent"** (Percentage) - Percentage discount (e.g., 10% off)
- **"Verdi"** (Value) - Fixed amount discount in whole kroner (e.g., 50 NOK off)

---

## Apply Item Discount Action

### Action Details

- **Name**: `applyItemDiscount`
- **Parameters**:
  - `cartItemId` (String) - Required - The ID of the cart item
  - `discountType` (String) - Required - One of:
    - `"Ingen"` - Remove discount
    - `"Prosent"` - Percentage discount
    - `"Verdi"` - Fixed amount discount
  - `discountValue` (double) - Required - 
    - For "Prosent": Percentage 0-100 (e.g., 10 for 10%)
    - For "Verdi": Amount in øre (e.g., 5000 for 50.00 NOK)
    - For "Ingen": Ignored (can be 0)
  - `discountReason` (String) - Optional - Reason for discount (e.g., "Manager override")
- **Return Type**: `void` (no return value)

### Implementation

The action:
1. Finds the cart item by ID
2. Handles discount based on type:
   - **"Ingen"**: Removes discount (sets to null)
   - **"Prosent"**: Calculates `(unitPrice × discountValue / 100)`
   - **"Verdi"**: Converts kroner to øre `(discountValue × 100)`
3. Ensures discount doesn't exceed item price
4. Updates the item with discount (or removes it)
5. Calls `updateCartTotals()` to recalculate

### Usage in FlutterFlow

1. Go to **Custom Code** → **Actions**
2. Create new action: `applyItemDiscount`
3. Add parameters:
   - `cartItemId` (String) - Required
   - `discountType` (String) - Required
   - `discountValue` (double) - Required
   - `discountReason` (String) - Optional
4. Paste code from `docs/flutterflow/custom-actions/apply_item_discount.dart`

### Example Usage

#### Remove Discount ("Ingen")

```dart
await applyItemDiscount(
  cartItem.cartItemId,
  'Ingen',
  0.0, // Ignored for "Ingen"
  null, // Optional
);
```

#### Percentage Discount ("Prosent" - 10% off)

```dart
await applyItemDiscount(
  cartItem.cartItemId,
  'Prosent',
  10.0, // 10%
  'Kundelojalitetsrabatt',
);
```

#### Fixed Amount Discount ("Verdi" - 50 NOK off)

```dart
await applyItemDiscount(
  cartItem.cartItemId,
  'Verdi',
  5000.0, // 50.00 NOK in øre
  'Sjefsoverstyring',
);
```

#### In FlutterFlow UI

**For removing discount:**
1. Select button/widget
2. Add action: `applyItemDiscount`
3. Set parameters:
   - `cartItemId`: `cartItem.cartItemId`
   - `discountType`: `"Ingen"`
   - `discountValue`: `0.0` (ignored)
   - `discountReason`: Leave empty or null

**For percentage discount:**
1. Select button/widget
2. Add action: `applyItemDiscount`
3. Set parameters:
   - `cartItemId`: `cartItem.cartItemId`
   - `discountType`: `"Prosent"`
   - `discountValue`: `10.0` (or from input field)
   - `discountReason`: `"Rabatt"` (optional)

**For fixed amount discount:**
1. Select button/widget
2. Add action: `applyItemDiscount`
3. Set parameters:
   - `cartItemId`: `cartItem.cartItemId`
   - `discountType`: `"Verdi"`
   - `discountValue`: `5000.0` (50.00 NOK in øre, or from input field)
   - `discountReason`: `"Sjefsoverstyring"` (optional)

---

## Common UI Patterns

### Pattern 1: Discount Input Dialog

```dart
// Show dialog to enter discount
showDialog(
  context: context,
  builder: (context) => AlertDialog(
    title: Text('Legg til rabatt'),
    content: Column(
      children: [
        // Discount type selector
        DropdownButton<String>(
          value: discountType,
          items: ['Ingen', 'Prosent', 'Verdi'].map((type) => 
            DropdownMenuItem(value: type, child: Text(type))
          ).toList(),
          onChanged: (value) => setState(() => discountType = value),
        ),
        // Discount value input (only show if not "Ingen")
        if (discountType != 'Ingen')
          TextField(
            onChanged: (value) => discountValue = double.tryParse(value) ?? 0,
            decoration: InputDecoration(
              labelText: discountType == 'Prosent' ? 'Prosent' : 'Beløp (kr)',
            ),
          ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () async {
          await applyItemDiscount(
            cartItem.cartItemId,
            discountType,
            discountValue,
            'Manuell rabatt',
          );
          Navigator.pop(context);
        },
        child: Text('Bruk'),
      ),
    ],
  ),
);
```

### Pattern 2: Quick Percentage Discounts

```dart
// 10% off button
onPressed: () async {
  await applyItemDiscount(
    cartItem.cartItemId,
    'Prosent',
    10.0,
    '10% rabatt',
  );
}

// 20% off button
onPressed: () async {
  await applyItemDiscount(
    cartItem.cartItemId,
    'Prosent',
    20.0,
    '20% rabatt',
  );
}
```

### Pattern 3: Fixed Amount Discounts

```dart
// 50 NOK off
onPressed: () async {
  await applyItemDiscount(
    cartItem.cartItemId,
    'Verdi',
    5000.0, // 50.00 NOK in øre
    '50 kr rabatt',
  );
}

// 100 NOK off
onPressed: () async {
  await applyItemDiscount(
    cartItem.cartItemId,
    'Verdi',
    10000.0, // 100.00 NOK in øre
    '100 kr rabatt',
  );
}
```

### Pattern 4: Remove Discount Button

```dart
// Only show if item has discount
if (cartItem.cartItemDiscountAmount != null && cartItem.cartItemDiscountAmount! > 0)
  IconButton(
    icon: Icon(Icons.close),
    onPressed: () async {
      await applyItemDiscount(
        cartItem.cartItemId,
        'Ingen',
        0.0,
        null,
      );
    },
    tooltip: 'Fjern rabatt',
  ),
```

---

## Discount Calculation Details

### "Ingen" (Remove Discount)

**Action:**
- Sets `cartItemDiscountAmount` to `null`
- Sets `cartItemDiscountReason` to `null`

### "Prosent" (Percentage Discount)

**Formula:**
```
discountAmount = (unitPrice × discountValue / 100).round()
```

**Example:**
- Unit price: 1000 øre (10.00 NOK)
- Discount: 10%
- Discount amount: (1000 × 10 / 100) = 100 øre (1.00 NOK)

### "Verdi" (Fixed Amount Discount)

**Formula:**
```
discountAmount = discountValue (already in øre)
```

**Example:**
- Unit price: 1000 øre (10.00 NOK)
- Discount: 5000 øre (input)
- Discount amount: 5000 øre (50.00 NOK)

### Safety Checks

Both discount types ensure:
- Discount doesn't exceed item price
- Discount amount is never negative
- Item price remains valid after discount

---

## Displaying Discounts in UI

### Show Discount Amount

```dart
if (cartItem.cartItemDiscountAmount != null && cartItem.cartItemDiscountAmount! > 0)
  Text(
    'Discount: ${formatNumber(
      cartItem.cartItemDiscountAmount! / 100.0,
      formatType: FormatType.custom,
      format: '\'kr \'#.##\',-\'',
      locale: 'nb_no',
    )}',
    style: TextStyle(color: Colors.green),
  ),
```

### Show Discount Reason

```dart
if (cartItem.cartItemDiscountReason != null && cartItem.cartItemDiscountReason!.isNotEmpty)
  Text(
    cartItem.cartItemDiscountReason!,
    style: TextStyle(fontSize: 12, color: Colors.grey),
  ),
```

### Show Discounted Price

```dart
// Original price (strikethrough)
Text(
  formatNumber(
    cartItem.cartItemUnitPrice / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
  style: TextStyle(
    decoration: TextDecoration.lineThrough,
    color: Colors.grey,
  ),
),

// Discounted price
Text(
  formatNumber(
    (cartItem.cartItemUnitPrice - (cartItem.cartItemDiscountAmount ?? 0)) / 100.0,
    formatType: FormatType.custom,
    format: '\'kr \'#.##\',-\'',
    locale: 'nb_no',
  ),
  style: TextStyle(
    fontWeight: FontWeight.bold,
    color: Colors.green,
  ),
),
```

---

## Important Notes

1. **Discount Amount**: Stored in **øre** (smallest currency unit)
   - 1 NOK = 100 øre
   - When displaying, divide by 100
   - For "Verdi" type, input is already in øre (no conversion needed)

2. **Discount Type**: Must be exactly `"Ingen"`, `"Prosent"`, or `"Verdi"` (case-insensitive)

3. **Percentage Range**: Should be 0-100 (action will cap at item price)

4. **Automatic Recalculation**: Action calls `updateCartTotals()` automatically

5. **Discount Validation**: 
   - Discount cannot exceed item price
   - Discount amount is always positive
   - If discount exceeds price, it's capped at item price

6. **Multiple Discounts**: 
   - Only one discount per item (applying a new discount replaces the old one)
   - To stack discounts, calculate the combined discount first

7. **"Verdi" Input**: 
   - Input is already in øre (e.g., 5000 for 50.00 NOK)
   - No conversion needed

---

## Testing

### Test Percentage Discount

1. Add item with price 100 NOK (10000 øre)
2. Apply 10% discount
3. Verify:
   - `cartItemDiscountAmount` = 1000 øre (10 NOK)
   - Discounted price = 90 NOK
   - Cart totals updated correctly

### Test Fixed Amount Discount ("Verdi")

1. Add item with price 100 NOK (10000 øre)
2. Apply 20 NOK discount using `applyItemDiscount(cartItemId, "Verdi", 2000.0, null)` (2000 øre)
3. Verify:
   - `cartItemDiscountAmount` = 2000 øre
   - Discounted price = 80 NOK
   - Cart totals updated correctly

### Test Remove Discount

1. Apply discount to item
2. Call `applyItemDiscount(cartItemId, "Ingen", 0.0, null)`
3. Verify:
   - `cartItemDiscountAmount` = null
   - `cartItemDiscountReason` = null
   - Cart totals updated correctly

---

## Files

- `docs/flutterflow/custom-actions/apply_item_discount.dart` - Apply/remove discount action
- `docs/flutterflow/custom-actions/update_cart_totals.dart` - Recalculate totals (called automatically)

