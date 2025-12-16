# Decimal Quantities Implementation

## Overview

This document describes the implementation of decimal quantity support for products sold by continuous units (e.g., meters, kilograms, liters). This allows selling quantities like 4.3 meters or 2.5 kilograms.

## Problem Statement

Currently, the system only supports integer quantities. For products sold by meter or similar continuous units, customers need to purchase decimal quantities (e.g., 4.3 meters of fabric).

## Solution Approach

### 1. **Change Quantity Type from Integer to Decimal**

The quantity field needs to be changed from `integer` to `number` (decimal/float) throughout the system:

- **API Specification**: Change `purchase_item_quantity` and cart item `quantity` from `integer` to `number`
- **Validation Rules**: Change from `integer|min:1` to `numeric|min:0.01`
- **FlutterFlow Cart**: Change `quantity` from `int` to `double`
- **Generated Models**: Will be automatically updated when API spec is regenerated

### 2. **Precision Considerations**

- **Recommended Precision**: 2-3 decimal places for most use cases
- **Example**: 4.30 meters, 2.567 kilograms
- **Storage**: Use `decimal` type in database if storing directly, or `float` in JSON metadata

### 3. **Calculation Changes**

All quantity-based calculations need to handle decimals:

```php
// Before (integer)
$subtotal = $unitPrice * $quantity; // Works for integers

// After (decimal)
$subtotal = (int) round($unitPrice * $quantity); // Convert to øre (integer)
```

### 4. **Validation Updates**

- **Minimum Quantity**: Change from `>= 1` to `> 0` (e.g., `min:0.01`)
- **Type Validation**: Change from `integer` to `numeric` or `decimal`

## Implementation Details

### ✅ API Specification Changes

**File**: `api-spec.yaml`

1. **Purchase Items** (line ~5255):
   - Changed `purchase_item_quantity` from `type: integer` to `type: number, format: float`
   - Updated description to mention support for continuous units
   - Changed minimum from `1` to `0.01`
   - Updated example from `2` to `4.3`

2. **Cart Items** (line ~6348):
   - Changed `quantity` from `type: integer` to `type: number, format: float`
   - Updated description to mention support for continuous units
   - Changed minimum from `1` to `0.01`
   - Updated example from `2` to `4.3`

### ✅ Validation Rules

**File**: `app/Http/Controllers/Api/PurchasesController.php`

- Updated validation in both `processSinglePayment()` and `processSplitPayment()` methods
- Changed from: `'cart.items.*.quantity' => ['required', 'integer', 'min:1']`
- Changed to: `'cart.items.*.quantity' => ['required', 'numeric', 'min:0.01']`

### ✅ Calculation Updates

**File**: `app/Http/Controllers/Api/PurchasesController.php`

- Updated quantity calculation to handle decimals:
  - Changed `$quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;` 
  - To: `$quantity = isset($item['quantity']) ? (float) $item['quantity'] : 1.0;`
- Updated line total calculations to round properly:
  - `$lineSubtotal = (int) round($unitPrice * $quantity);`
  - `$lineDiscount = (int) round($discountAmount * $quantity);`

### ✅ FlutterFlow Cart Structure

**File**: `docs/flutterflow/FLUTTERFLOW_CART_DATA_STRUCTURE.md`

- Changed `quantity` field from `int` to `double`
- Updated `copyWith` method parameter type
- Updated calculations to round properly:
  - `int get subtotal => (unitPrice * quantity).round();`
  - `int get totalDiscount => ((discountAmount ?? 0) * quantity).round();`
- Updated `updateItemQuantity` method to accept `double`
- Updated `itemCount` getter to round quantities
- Updated JSON parsing to handle numeric values: `(json['quantity'] as num?)?.toDouble() ?? 1.0`

### Generated Models

The generated models in `gen/lib/Model/` will be automatically updated when the API spec is regenerated using OpenAPI Generator. The quantity type will change from `int` to `float`.

## Benefits

1. **Flexibility**: Supports both discrete items (quantity: 2) and continuous units (quantity: 4.3)
2. **Real-world Use Cases**: Enables selling fabric by meter, produce by weight, liquids by volume
3. **Backward Compatible**: Integer quantities (2, 3, 4) still work perfectly
4. **Precision**: Supports common decimal precision needs (2-3 decimal places)

## Considerations

### 1. **Inventory Tracking**

For products with inventory tracking:
- **Discrete Items**: Continue using integer quantities (e.g., 5 units)
- **Continuous Units**: May need to track in smallest unit (e.g., centimeters instead of meters) or use decimal inventory

### 2. **Display Formatting**

When displaying quantities:
- **Discrete Items**: Show as integer (e.g., "2")
- **Continuous Units**: Show with appropriate decimals (e.g., "4.30 m" or "2.5 kg")

### 3. **Receipt Generation**

Receipts should format quantities appropriately:
- Integer quantities: "2 stk"
- Decimal quantities: "4.30 m" or "2.5 kg"

### 4. **SAF-T Compliance**

Ensure decimal quantities are properly handled in SAF-T file generation for Norwegian tax compliance.

## Testing

1. **Test Cases**:
   - Add product with quantity 4.3 meters
   - Verify calculation: 4.3 * unit_price = correct subtotal
   - Verify receipt shows "4.30 m"
   - Test with integer quantities (backward compatibility)
   - Test with very small quantities (0.01)
   - Test with large decimal quantities (100.567)

2. **Edge Cases**:
   - Quantity = 0.01 (minimum)
   - Quantity = 0.001 (should be rejected if min is 0.01)
   - Very large quantities with decimals
   - Rounding in calculations

## Migration Notes

- **No Database Migration Required**: Purchase items are stored in JSON metadata, not a separate table
- **Backward Compatibility**: Existing purchases with integer quantities will continue to work
- **API Versioning**: Consider if this is a breaking change (it's not, as integers are valid numbers)

## Related Features

- **QuantityUnit Model**: Already supports units like "Meter", "Kilogram" - this feature enables using them with decimal quantities
- **Product Variants**: Decimal quantities work with variants
- **Discounts**: Discount calculations work with decimal quantities

## Future Enhancements

1. **Product-level Configuration**: Allow products to specify if they support decimal quantities
2. **Precision Settings**: Allow stores to configure decimal precision per quantity unit
3. **Inventory with Decimals**: Support decimal inventory tracking for continuous units
