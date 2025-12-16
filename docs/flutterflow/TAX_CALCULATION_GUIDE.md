# Tax Calculation Guide - Per Product Tax Rates

## Overview

The cart totals calculation now supports per-product tax rates. Each product can have a different tax rate based on its VAT percent or tax code.

## How It Works

1. **VAT Percent Storage**: Products now have a `vat_percent` field that can be set directly (e.g., 25.00 for 25%)
2. **Tax Code Storage**: When adding items to cart, the product's `taxCode` or `article_group_code` is stored in `cartItemArticleGroupCode`
3. **Tax Rate Lookup Priority**:
   - **First**: Use `vat_percent` from product (if available)
   - **Second**: Map tax code/article group code to percentage using `getTaxPercentageFromCode()`
   - **Third**: Default to 25% VAT
4. **Per-Item Tax Calculation**: Tax is calculated for each item based on its tax rate
5. **Total Tax**: Sum of all item taxes

## Setting VAT Percent on Products

### Via API
When creating or updating a product, you can set `vat_percent` directly:
```json
{
  "name": "Product Name",
  "vat_percent": 25.00,
  "article_group_code": "04003"
}
```

### Auto-Calculation
If `article_group_code` is set, the system will automatically set `vat_percent` based on the code:
- `04003` (Varesalg) → 25.00%
- `04006` (Mat) → 15.00%
- `04004` (Salg av behandlingstjenester) → 25.00%
- etc.

You can override the auto-calculated value by explicitly setting `vat_percent`.

## Tax Code Mapping

The `getTaxPercentageFromCode()` function currently supports:

| Tax Code | Description | Tax Rate |
|----------|-------------|----------|
| `txcd_99999999` or `standard` or `1` | Standard VAT (Norway) | 25% |
| `txcd_99999998` or `reduced` or `food` | Reduced rate (food) | 15% |
| `txcd_99999997` or `lower` or `service` | Lower rate (services) | 10% |
| `txcd_99999996` or `zero` or `exempt` or `0` | Zero rate / Exempt | 0% |
| (default) | Unknown code | 25% (default) |

## Using VAT Percent in Cart Items

When adding products to cart, store the `vat_percent` value in your cart item structure:

```dart
// When adding product to cart
final cartItem = CartItemStruct(
  // ... other fields
  cartItemVatPercent: product.vatPercent, // Store VAT percent from product
  cartItemArticleGroupCode: product.articleGroupCode, // Store tax code as fallback
);
```

Then in `updateCartTotals()`, use:
```dart
final taxPercentage = getTaxPercentageFromProduct(
  item.cartItemVatPercent, // Use VAT percent if available
  item.cartItemArticleGroupCode // Fallback to tax code mapping
);
```

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
   - Get tax percentage using `getTaxPercentageFromProduct(vatPercent, taxCode)`
     - Priority: `vatPercent` → `taxCode` mapping → default 25%
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

1. **VAT percent is preferred** - Store `vat_percent` from product in cart items when available
2. **Tax codes are stored** in `cartItemArticleGroupCode` as fallback when adding items to cart
3. **Default tax rate** is 25% if neither VAT percent nor recognized code is found
4. **Tax is calculated per item** before cart-level discounts
5. **Cart-level discounts** don't affect individual item tax calculations
6. **Auto-calculation** - Setting `article_group_code` on a product automatically sets `vat_percent`
7. **Manual override** - You can manually set `vat_percent` to override auto-calculated values

## Future Enhancements

Consider:
- Storing tax percentage directly in cart items (if available from API)
- Supporting tax-inclusive pricing
- Tax rate lookup from API/database
- Multiple tax rates per item (e.g., VAT + excise tax)

