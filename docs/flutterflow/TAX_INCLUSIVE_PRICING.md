# Tax-Inclusive Pricing Calculation

## Overview

Prices in the system are **tax-inclusive**, meaning the displayed price already includes tax. This requires a different calculation approach than tax-exclusive pricing.

## Tax Calculation Formula

When prices include tax, we need to **extract** the tax from the price rather than add it.

### Formula

For a tax-inclusive price:
```
Tax Amount = Price including tax × (Tax rate / (1 + Tax rate))
Base Price (excluding tax) = Price including tax - Tax Amount
```

### Examples

**Example 1: 25% VAT**
- Price including tax: 100 NOK
- Tax rate: 25% (0.25)
- Tax amount: 100 × (0.25 / 1.25) = 100 × 0.2 = 20 NOK
- Base price: 100 - 20 = 80 NOK

**Example 2: 15% Reduced Rate**
- Price including tax: 100 NOK
- Tax rate: 15% (0.15)
- Tax amount: 100 × (0.15 / 1.15) = 100 × 0.1304... ≈ 13 NOK
- Base price: 100 - 13 = 87 NOK

**Example 3: 0% Tax**
- Price including tax: 100 NOK
- Tax rate: 0%
- Tax amount: 0 NOK
- Base price: 100 NOK

## Implementation in `updateCartTotals`

The function now:

1. **Calculates line price** (tax-inclusive): `unitPrice × quantity`
2. **Applies discounts**: `linePrice - discount`
3. **Extracts tax** from tax-inclusive price:
   ```dart
   final itemTax = taxPercentage > 0
       ? (itemSubtotalIncludingTax * (taxPercentage / (1 + taxPercentage))).round()
       : 0;
   ```
4. **Calculates subtotal excluding tax**: `totalLinePrice - totalDiscount - totalTax`
5. **Calculates total**: `totalLinePrice - totalDiscount + tip`

## Cart Totals Breakdown

| Field | Calculation | Description |
|-------|-------------|-------------|
| `cartTotalLinePrice` | Sum of (unitPrice × quantity) | Total line items (tax-inclusive) |
| `cartTotalItemDiscounts` | Sum of (itemDiscount × quantity) | Item-level discounts |
| `cartTotalCartDiscounts` | Sum of cart discounts | Cart-level discounts |
| `cartTotalDiscount` | Item discounts + Cart discounts | Total discounts |
| `cartTotalTax` | Sum of extracted tax per item | Total tax extracted from prices |
| `cartSubtotalExcludingTax` | Line price - Discounts - Tax | Base price excluding tax |
| `cartTotalCartPrice` | Line price - Discounts + Tip | Final total (tax already included) |

## Important Notes

1. **Prices are tax-inclusive**: The `cartItemUnitPrice` already includes tax
2. **Tax is extracted, not added**: We calculate how much tax is in the price
3. **Discounts apply to tax-inclusive price**: Discounts reduce the total price (including tax)
4. **Tip is added after**: Tip is added to the final total
5. **Per-product tax rates**: Each item can have a different tax rate

## Verification

To verify the calculation is correct:

1. **Item with 25% tax**:
   - Price: 100 NOK (tax-inclusive)
   - Tax: 100 × (0.25 / 1.25) = 20 NOK
   - Base: 100 - 20 = 80 NOK ✓

2. **Item with 15% tax**:
   - Price: 100 NOK (tax-inclusive)
   - Tax: 100 × (0.15 / 1.15) ≈ 13.04 NOK
   - Base: 100 - 13.04 ≈ 86.96 NOK ✓

3. **Item with 0% tax**:
   - Price: 100 NOK
   - Tax: 0 NOK
   - Base: 100 NOK ✓

## Display in UI

When displaying prices:

```dart
// Total (already includes tax)
formatNumber(cart.cartTotalCartPrice / 100.0, ...)

// Subtotal excluding tax
formatNumber(cart.cartSubtotalExcludingTax / 100.0, ...)

// Tax amount
formatNumber(cart.cartTotalTax / 100.0, ...)
```

## Comparison: Tax-Inclusive vs Tax-Exclusive

### Tax-Exclusive (Old - Incorrect)
```
Base Price: 80 NOK
Tax (25%): 80 × 0.25 = 20 NOK
Total: 80 + 20 = 100 NOK
```

### Tax-Inclusive (Current - Correct)
```
Price including tax: 100 NOK
Tax (25%): 100 × (0.25 / 1.25) = 20 NOK
Base Price: 100 - 20 = 80 NOK
Total: 100 NOK (tax already included)
```

