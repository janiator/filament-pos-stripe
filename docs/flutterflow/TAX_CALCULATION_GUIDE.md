# Tax Calculation Guide - Per Product Tax Rates

## Overview

The cart totals calculation now supports per-product tax rates. Each product can have a different tax rate based on its tax code.

## How It Works

1. **Tax Code Storage**: When adding items to cart, the product's `taxCode` is stored in `cartItemArticleGroupCode`
2. **Tax Rate Lookup**: The `getTaxPercentageFromCode()` function maps tax codes to tax percentages
3. **Per-Item Tax Calculation**: Tax is calculated for each item based on its tax rate
4. **Total Tax**: Sum of all item taxes

## Tax Code Mapping

The `getTaxPercentageFromCode()` function currently supports:

| Tax Code | Description | Tax Rate |
|----------|-------------|----------|
| `txcd_99999999` or `standard` or `1` | Standard VAT (Norway) | 25% |
| `txcd_99999998` or `reduced` or `food` | Reduced rate (food) | 15% |
| `txcd_99999997` or `lower` or `service` | Lower rate (services) | 10% |
| `txcd_99999996` or `zero` or `exempt` or `0` | Zero rate / Exempt | 0% |
| (default) | Unknown code | 25% (default) |

## Customizing Tax Codes

To add your own tax code mappings, edit the `getTaxPercentageFromCode()` function in `updateCartTotals`:

```dart
double getTaxPercentageFromCode(String? taxCode) {
  if (taxCode == null || taxCode.isEmpty) {
    return 0.25; // Default
  }
  
  switch (taxCode.toLowerCase()) {
    case 'your_custom_code_1':
      return 0.25; // 25%
    case 'your_custom_code_2':
      return 0.15; // 15%
    // Add more mappings as needed
    default:
      return 0.25; // Default
  }
}
```

## Tax Calculation Logic

### Current Implementation

1. **For each cart item**:
   - Calculate line price: `unitPrice × quantity`
   - Calculate item discount: `discountAmount × quantity`
   - Calculate item subtotal: `linePrice - itemDiscount`
   - Get tax percentage from `cartItemArticleGroupCode`
   - Calculate item tax: `itemSubtotal × taxPercentage`
   - Add to total tax

2. **Cart-level discounts** are applied after item-level calculations

3. **Final totals**:
   - `subtotalExcludingTax = totalLinePrice - totalDiscount`
   - `totalTax = sum of all item taxes`
   - `totalCartPrice = subtotalExcludingTax + totalTax + tip`

### Alternative: Tax After Cart Discounts

If you want to calculate tax **after** applying cart-level discounts:

```dart
// Calculate subtotal first
final subtotalExcludingTax = totalLinePrice - totalDiscount;

// Then calculate tax on the final subtotal
// But this loses per-product tax rates
final totalTax = (subtotalExcludingTax * 0.25).round();
```

**Note**: This approach loses per-product tax rate support. The current implementation calculates tax per item, which is more accurate.

## Stripe Tax Codes

If you're using Stripe tax codes, common ones include:

- `txcd_99999999` - Standard rate (varies by country)
- `txcd_99999998` - Reduced rate
- `txcd_99999997` - Lower rate
- `txcd_99999996` - Zero rate
- `txcd_00000000` - Exempt

You can look up Stripe tax codes in the [Stripe Tax Code Reference](https://stripe.com/docs/tax/tax-codes).

## SAF-T Article Group Codes

If you're using SAF-T article group codes, you might want to map them differently:

```dart
double getTaxPercentageFromCode(String? taxCode) {
  if (taxCode == null || taxCode.isEmpty) {
    return 0.25;
  }
  
  // SAF-T article group codes
  switch (taxCode) {
    case '04003': // Varesalg (Sale of goods) - usually 25%
      return 0.25;
    case '04006': // Mat (Food) - usually 15%
      return 0.15;
    case '04004': // Salg av behandlingstjenester (Treatment services) - varies
      return 0.25;
    // Add more mappings
    default:
      return 0.25;
  }
}
```

## Testing

To test different tax rates:

1. **Create products with different tax codes**:
   - Product A: `taxCode = "standard"` → 25%
   - Product B: `taxCode = "food"` → 15%
   - Product C: `taxCode = "zero"` → 0%

2. **Add to cart and verify**:
   ```dart
   final cart = FFAppState().cart;
   print('Total Tax: ${cart.cartTotalTax}');
   print('Subtotal: ${cart.cartSubtotalExcludingTax}');
   print('Total: ${cart.cartTotalCartPrice}');
   ```

3. **Verify calculations**:
   - Item with 25% tax: `itemSubtotal × 0.25`
   - Item with 15% tax: `itemSubtotal × 0.15`
   - Item with 0% tax: `itemSubtotal × 0.0`

## Important Notes

1. **Tax codes are stored** in `cartItemArticleGroupCode` when adding items to cart
2. **Default tax rate** is 25% if code is not recognized
3. **Tax is calculated per item** before cart-level discounts
4. **Cart-level discounts** don't affect individual item tax calculations
5. **Update the mapping** if you use custom tax codes

## Future Enhancements

Consider:
- Storing tax percentage directly in cart items (if available from API)
- Supporting tax-inclusive pricing
- Tax rate lookup from API/database
- Multiple tax rates per item (e.g., VAT + excise tax)

