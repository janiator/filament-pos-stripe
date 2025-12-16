# Decimal Quantities - Compliance Analysis

## Overview

This document analyzes the compliance implications of supporting decimal quantities (e.g., 4.3 meters) for products sold by continuous units in the Norwegian POS system.

## SAF-T Compliance

### ✅ **SAF-T File Generation**

**Status**: **COMPLIANT**

The SAF-T (Standard Audit File for Tax) generation does **NOT** directly use purchase item quantities. Instead, it tracks transactions at the **charge level** with total amounts.

**Analysis**:
- SAF-T files track `DebitAmount` and `CreditAmount` at the charge/transaction level
- These amounts are in **øre** (integer values) - the smallest currency unit
- Purchase item quantities are stored in metadata and used for internal calculations
- The final charge amount (already in øre) is what gets exported to SAF-T

**Code Reference**: `app/Actions/SafT/GenerateSafTCashRegister.php`
- Line 147: `$this->addElement($xml, $line, 'DebitAmount', (string) $charge->amount);`
- The `$charge->amount` is already an integer in øre, calculated from `unit_price * quantity` (with proper rounding)

**Conclusion**: Decimal quantities are **fully compliant** because:
1. Quantities are used only for internal calculations
2. All monetary values in SAF-T are in øre (integers)
3. Calculations properly round to integers before SAF-T export
4. The rounding happens in `PurchasesController.php` (line 492-493)

### ✅ **Receipt Compliance (Kassasystemforskriften)**

**Status**: **COMPLIANT**

Norwegian cash register regulations (Kassasystemforskriften) require receipts to show:
- Product name
- Quantity
- Unit price
- Total amount

**Decimal Quantities**:
- ✅ Quantities can be displayed as decimals (e.g., "4.30 m")
- ✅ Unit prices remain in øre (integers)
- ✅ Total amounts are calculated and rounded to øre (integers)
- ✅ All monetary values on receipts are in NOK (or øre), which are integers

**Code Reference**: `app/Services/ReceiptGenerationService.php`
- Receipt generation uses quantities from metadata
- Quantities are displayed as-is (can be decimal)
- All price calculations result in integer øre values

### ✅ **Tax Compliance (VAT/MVA)**

**Status**: **COMPLIANT**

Norwegian VAT (MVA) calculations:
- VAT is calculated on the **total amount** (in øre)
- Total amounts are always integers (øre)
- Decimal quantities don't affect VAT compliance because:
  - `unit_price` (in øre) × `quantity` (decimal) = `subtotal` (rounded to integer øre)
  - VAT is calculated from the integer subtotal

**Example**:
```
Unit Price: 5000 øre (50.00 NOK)
Quantity: 4.3 meters
Subtotal: (5000 × 4.3).round() = 21500 øre (215.00 NOK)
VAT (25%): 21500 × 0.25 / 1.25 = 4300 øre (43.00 NOK)
Net: 17200 øre (172.00 NOK)
```

All values are integers in øre, ensuring tax compliance.

### ✅ **Accounting Compliance**

**Status**: **COMPLIANT**

Double-entry bookkeeping requirements:
- Debit and credit entries must balance
- All amounts must be in the smallest currency unit (øre = integers)
- Decimal quantities are used only for calculation, final amounts are integers

**Code Reference**: `app/Actions/SafT/GenerateSafTCashRegister.php`
- Lines 147-148: DebitAmount and CreditAmount are integers
- Line 183: CreditAmount is integer
- All calculations ensure integer results

## Implementation Compliance Checks

### ✅ **Data Storage**

- Purchase items are stored in JSON metadata (not a database table)
- Quantities stored as floats/decimals in JSON
- No database schema changes required
- **Compliant**: JSON supports decimal numbers natively

### ✅ **API Validation**

- Changed from `integer|min:1` to `numeric|min:0.01`
- Minimum quantity ensures positive values
- **Compliant**: Proper validation prevents invalid quantities

### ✅ **Calculations**

- All monetary calculations round to integers (øre)
- Line totals: `(int) round($unitPrice * $quantity)`
- Discounts: `(int) round($discountAmount * $quantity)`
- **Compliant**: Ensures all currency values are integers

### ✅ **Backward Compatibility**

- Integer quantities (1, 2, 3) still work perfectly
- Existing purchases remain valid
- **Compliant**: No breaking changes

## Potential Compliance Considerations

### ⚠️ **Inventory Tracking**

**Note**: If you track inventory for products sold by continuous units:
- Consider tracking in smallest unit (e.g., centimeters instead of meters)
- Or use decimal inventory quantities
- This is a business logic decision, not a compliance requirement

### ⚠️ **Display Formatting**

**Recommendation**: Format quantities appropriately:
- Discrete items: "2 stk" (integer)
- Continuous units: "4.30 m" or "2.5 kg" (decimal with appropriate precision)

This is for user experience, not compliance.

## Summary

### ✅ **FULLY COMPLIANT**

Decimal quantities are **fully compliant** with:
- ✅ SAF-T file generation (Norwegian tax compliance)
- ✅ Kassasystemforskriften (Norwegian cash register regulations)
- ✅ VAT/MVA tax calculations
- ✅ Double-entry bookkeeping requirements
- ✅ Receipt generation requirements

### Key Points

1. **Quantities are for calculation only** - Final amounts are always integers (øre)
2. **Proper rounding** - All calculations round to integers before storage/export
3. **No breaking changes** - Integer quantities still work
4. **Compliance maintained** - All monetary values remain integers as required

### Recommendations

1. ✅ **Use decimal quantities** for continuous units (meters, kilograms, liters)
2. ✅ **Display appropriately** - Show decimals for continuous units, integers for discrete items
3. ✅ **Test calculations** - Verify rounding works correctly in edge cases
4. ✅ **Document usage** - Ensure staff understand when to use decimal quantities

## Conclusion

**Decimal quantities are fully compliant** with Norwegian tax and regulatory requirements. The implementation properly handles:
- Decimal input quantities
- Integer output amounts (øre)
- Proper rounding in all calculations
- SAF-T file generation
- Receipt compliance
- Tax calculations

No compliance issues have been identified.
