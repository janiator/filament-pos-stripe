# Order Refund Implementation Summary

## What Was Created

### 1. FlutterFlow Custom Action
**File**: `docs/flutterflow/custom-actions/process_order_refund.dart`

A complete custom action that:
- Opens a modal to select which items from an order should be refunded
- Calculates refund amounts based on selected items
- Handles both cash and Stripe refunds automatically
- Supports partial refunds (specific items or quantities)
- Includes optional refund reason field

### 2. API Enhancements

#### Refund Endpoint Enhancement
**File**: `app/Http/Controllers/Api/PurchasesController.php`

- Added support for item-level refund tracking via optional `items` array in refund request
- Validates item-level refund data

#### Refund Service Enhancement
**File**: `app/Services/PurchaseService.php`

- Enhanced `processRefund()` method to accept and track refunded items
- Stores item-level refund information in purchase metadata
- Maintains backward compatibility (items array is optional)

#### Purchase Items Response Enhancement
**File**: `app/Http/Controllers/Api/PurchasesController.php`

- Added refund status fields to purchase items:
  - `purchase_item_quantity_refunded` (int, nullable)
  - `purchase_item_is_refunded` (bool)
  - `purchase_item_is_partially_refunded` (bool)

### 3. Documentation
**File**: `docs/flutterflow/custom-actions/PROCESS_ORDER_REFUND.md`

Complete documentation including:
- Usage examples
- Parameter descriptions
- Return value structure
- Integration steps
- UI display examples

## What Needs to Be Done in FlutterFlow

### 1. Add Custom Action to FlutterFlow

1. Copy `process_order_refund.dart` to your FlutterFlow custom actions directory
2. Ensure all imports are available in your FlutterFlow project

### 2. Update PurchaseItemStruct

Add the following fields to your `PurchaseItemStruct` in FlutterFlow:

- `purchase_item_quantity_refunded` (int, nullable)
- `purchase_item_is_refunded` (bool)
- `purchase_item_is_partially_refunded` (bool)

**Note**: These fields are now returned by the API, so you may need to regenerate your API types or manually add them.

### 3. Add Refund Button to Orders Page

1. In your orders/purchases list or detail page
2. Add a button (e.g., "Refunder" or "Return")
3. Set the button action to call `processOrderRefund`
4. Pass the required parameters:
   - `purchaseId`: From the purchase object
   - `purchaseItems`: `purchase.purchaseItems`
   - `purchaseAmount`: `purchase.amount`
   - `amountRefunded`: `purchase.amountRefunded`
   - `paymentMethod`: `purchase.paymentMethod`
   - `apiBaseUrl`: From app state
   - `authToken`: From app state

### 4. Update UI to Show Refund Status

Update your order items display to visually indicate refunded items:

**Example for fully refunded items:**
```dart
if (item.purchaseItemIsRefunded == true) {
  // Show with strikethrough or grayed out
  // Use gray color, strikethrough text decoration
}
```

**Example for partially refunded items:**
```dart
if (item.purchaseItemIsPartiallyRefunded == true) {
  // Show refunded quantity
  // Display: "2 av 3 refundert" or similar
  // Use orange/yellow color to indicate partial status
}
```

### 5. Handle Refund Result

After calling the refund action:

1. Check if `result['success'] == true`
2. Show success message to user
3. Refresh the purchase data to show updated refund status
4. Handle errors appropriately

## API Changes Summary

### Request Format (Enhanced)

The refund endpoint now accepts an optional `items` array:

```json
POST /api/purchases/{id}/refund
{
  "amount": 5000,
  "reason": "Customer returned item",
  "items": [
    {
      "item_id": "item-uuid-1",
      "quantity": 2
    }
  ]
}
```

**Note**: The `items` array is optional. If not provided, the refund works as before (amount-based only).

### Response Format (Enhanced)

Purchase items now include refund status:

```json
{
  "purchase_items": [
    {
      "purchase_item_id": "item-uuid-1",
      "purchase_item_product_name": "Coffee",
      "purchase_item_quantity": 3,
      "purchase_item_quantity_refunded": 2,
      "purchase_item_is_refunded": false,
      "purchase_item_is_partially_refunded": true
    }
  ]
}
```

## Testing Checklist

- [ ] Test full order refund
- [ ] Test partial item refund (quantity < total)
- [ ] Test refunding multiple items
- [ ] Test cash payment refund
- [ ] Test Stripe payment refund
- [ ] Test refund with reason
- [ ] Test refund validation (already fully refunded, etc.)
- [ ] Verify refund status appears correctly in UI
- [ ] Verify purchase data refreshes after refund
- [ ] Test error handling (network errors, API errors)

## Backward Compatibility

All changes are backward compatible:

- The `items` array in refund requests is optional
- Existing refunds (without item tracking) continue to work
- Purchase items without refund status fields will have null/false values
- The API still accepts refund requests without the `items` array

## Next Steps

1. **Immediate**: Add the custom action to FlutterFlow and test basic refund flow
2. **Short-term**: Update UI to show refund status on order items
3. **Optional**: Add visual indicators (badges, colors) for refunded items
4. **Future Enhancement**: Consider adding refund history/details view

## Support

For issues or questions:
- Check the detailed documentation: `PROCESS_ORDER_REFUND.md`
- Review the API spec for refund endpoint details
- Check FlutterFlow custom action examples for similar patterns

