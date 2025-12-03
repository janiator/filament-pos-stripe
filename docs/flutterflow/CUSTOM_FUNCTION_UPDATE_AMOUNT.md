# Custom Function: updateAmountWithDigit

## Overview

This custom function handles numeric input for amounts or percentages, with support for decimal points and value limits.

## Function Signature

```dart
String? updateAmountWithDigit(
  String currentAmount,
  String newDigit,
  bool usePercent,
  int? itemPriceCents,
)
```

## Parameters

- `currentAmount` (String) - The current amount/percentage string
- `newDigit` (String) - The new digit to add (0-9 or '.')
- `usePercent` (bool) - If true, limits value to 100 (for percentages)
- `itemPriceCents` (int?) - **Optional** item price in øre. When `null`, no discount limit is applied. When provided and `usePercent = false`, prevents discount from exceeding item price

## Behavior

### General Rules

1. **Accepts only digits (0-9) and decimal point (.)**
2. **Prevents leading zeros** (e.g., "00" becomes "0")
3. **Limits decimal places to 2** (e.g., "10.99" max)
4. **Prevents multiple decimal points**

### When `usePercent = true`

- **Limits value to 100.00**
- If adding a digit would exceed 100, the digit is ignored
- Examples:
  - Current: "99", adding "5" → stays "99" (would be 995 > 100)
  - Current: "10", adding "0" → "100" (exactly 100)
  - Current: "100", adding any digit → stays "100" (already at max)

### When `usePercent = false`

- **Discount limit**: If `itemPriceCents` is provided, discount cannot exceed item price
- **No limit if `itemPriceCents` is null**: For general amount input without price constraint
- Examples:
  - Current: "50", itemPrice: 10000 øre (100 NOK), adding "5" → "50" (stays, would be 500 NOK > 100 NOK)
  - Current: "10", itemPrice: 10000 øre (100 NOK), adding "0" → "100" (exactly 100 NOK, valid)
  - Current: "100", itemPrice: 10000 øre (100 NOK), adding any digit → "100" (stays at max)
  - Current: "1000", itemPrice: null, adding "5" → "10005" (no limit when itemPrice is null)

## Usage Examples

### Percentage Input (usePercent = true)

```dart
// User typing percentage discount
String? result = updateAmountWithDigit('10', '5', true);
// Result: '105' → but exceeds 100, so returns '10'

String? result = updateAmountWithDigit('99', '9', true);
// Result: '99' (stays at 99, can't exceed 100)

String? result = updateAmountWithDigit('10', '0', true);
// Result: '100' (exactly 100, valid)
```

### Amount Input (usePercent = false)

```dart
// User typing discount amount in kroner
// Item price: 10000 øre (100 NOK)
String? result = updateAmountWithDigit('50', '5', false, 10000);
// Result: '50' (stays, would be 505 NOK > 100 NOK)

String? result = updateAmountWithDigit('10', '0', false, 10000);
// Result: '100' (exactly 100 NOK, valid)

String? result = updateAmountWithDigit('50', '.', false, 10000);
// Result: '50.' (allows decimal)

// No item price constraint
String? result = updateAmountWithDigit('1000', '5', false, null);
// Result: '10005' (no limit when itemPrice is null)
```

## Implementation in FlutterFlow

### Step 1: Create Custom Function

1. Go to **Custom Code** → **Custom Functions**
2. Create new function: `updateAmountWithDigit`
3. Add parameters:
   - `currentAmount` (String) - Required
   - `newDigit` (String) - Required
   - `usePercent` (bool) - Required
4. Set return type: `String?`
5. Paste the code from `docs/flutterflow/custom-functions/update_amount_with_digit.dart`

### Step 2: Usage in Text Field

**For percentage input:**
```dart
// In TextField's onChanged or similar
onChanged: (String value) {
  // value is the new digit entered
  final updated = updateAmountWithDigit(
    currentPercentageValue,
    value,
    true, // usePercent = true
    null, // itemPriceCents not needed for percentage
  );
  setState(() {
    currentPercentageValue = updated ?? '';
  });
}
```

**For amount input (with item price constraint):**
```dart
// In TextField's onChanged
// itemPriceCents: price of the item in øre (e.g., 10000 for 100 NOK)
onChanged: (String value) {
  final updated = updateAmountWithDigit(
    currentAmountValue,
    value,
    false, // usePercent = false
    itemPriceCents, // e.g., 10000 for 100 NOK item
  );
  setState(() {
    currentAmountValue = updated ?? '';
  });
}
```

**For amount input (without constraint):**
```dart
// In TextField's onChanged
onChanged: (String value) {
  final updated = updateAmountWithDigit(
    currentAmountValue,
    value,
    false, // usePercent = false
    null, // No price constraint
  );
  setState(() {
    currentAmountValue = updated ?? '';
  });
}
```

## Edge Cases Handled

1. **Empty string** → Defaults to "0"
2. **Invalid characters** → Ignored (returns current value)
3. **Multiple decimal points** → Only first one accepted
4. **Leading zeros** → Prevented (except "0" itself)
5. **Decimal precision** → Limited to 2 decimal places
6. **Percentage limit** → Capped at 100.00 when `usePercent = true`
7. **Discount exceeds item price** → Prevented when `itemPriceCents` is provided and `usePercent = false`
8. **Null itemPriceCents** → No discount limit applied (for general amount input)

## Testing

### Test Percentage Mode (usePercent = true)

1. Start with "0", add "1" → "1" ✓
2. Start with "99", add "5" → "99" (capped at 100) ✓
3. Start with "10", add "0" → "100" ✓
4. Start with "100", add any digit → "100" (stays at max) ✓
5. Start with "50", add "." → "50." ✓
6. Start with "50.", add "5" → "50.5" ✓
7. Start with "50.5", add "5" → "50.55" ✓
8. Start with "50.55", add "5" → "50.55" (max 2 decimals) ✓

### Test Amount Mode (usePercent = false)

**With itemPriceCents = 10000 (100 NOK):**
1. Start with "0", add "1" → "1" ✓
2. Start with "50", add "5" → "50" (stays, 505 NOK > 100 NOK) ✓
3. Start with "10", add "0" → "100" (exactly 100 NOK) ✓
4. Start with "100", add any digit → "100" (stays at max) ✓
5. Start with "50", add "." → "50." ✓
6. Start with "50.", add "5" → "50.5" ✓
7. Start with "99.9", add "9" → "99.9" (stays, 999.9 NOK > 100 NOK) ✓

**With itemPriceCents = null (no constraint):**
1. Start with "0", add "1" → "1" ✓
2. Start with "1000", add "5" → "10005" (no limit) ✓
3. Start with "50", add "." → "50." ✓
4. Start with "50.", add "5" → "50.5" ✓

## Notes

- The function returns `String?` (nullable) for safety
- Empty input defaults to "0"
- Invalid characters are silently ignored
- Percentage mode enforces 100.00 maximum
- Amount mode enforces item price limit when `itemPriceCents` is provided
- When `itemPriceCents` is `null`, amount mode has no upper limit
- Input is in **kroner** (decimal), but validation compares to **øre** (itemPriceCents)
- Example: Input "50.00" kroner = 5000 øre, compared to `itemPriceCents` (e.g., 10000 øre)

