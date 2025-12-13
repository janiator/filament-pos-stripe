# Process Order Refund - FlutterFlow Custom Action

## Overview

The `processOrderRefund` custom action provides a complete refund/return flow for orders. It opens a modal dialog where users can select which items from an order should be refunded, then processes the refund using the appropriate payment method (cash or Stripe).

## Features

- **Item Selection Modal**: Visual interface to select which items to refund
- **Partial Refunds**: Support for refunding specific items or quantities
- **Payment Method Handling**: Automatically handles both cash and Stripe refunds
- **Item-Level Tracking**: Tracks which specific items have been refunded
- **Refund Reason**: Optional reason field for compliance tracking
- **Visual Indicators**: Purchase items now include refund status fields

## Usage

### Basic Usage

```dart
final result = await processOrderRefund(
  context,
  purchase,                      // PurchaseStruct: The complete purchase/order object
  apiBaseUrl,                    // String: API base URL
  authToken,                     // String: Authentication token
);
```

### With Custom Modal Size

```dart
final result = await processOrderRefund(
  context,
  purchase,                      // PurchaseStruct: The complete purchase/order object
  apiBaseUrl,                    // String: API base URL
  authToken,                     // String: Authentication token
  700.0,                         // Optional: Modal width (nullable)
  800.0,                         // Optional: Modal height (nullable)
);
```

### Handling the Result

```dart
final result = await processOrderRefund(...);

if (result['success'] == true) {
  // Refund processed successfully
  final refundData = result['data'];
  final refundAmount = result['refundAmount'];
  final selectedItems = result['selectedItems'];
  
  // Refresh purchase data to show updated refund status
  // The purchase items will now have refund status fields:
  // - purchase_item_quantity_refunded
  // - purchase_item_is_refunded
  // - purchase_item_is_partially_refunded
} else {
  // Handle error
  final errorMessage = result['message'];
  // Show error to user
}
```

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `context` | `BuildContext` | Yes | BuildContext for showing the modal |
| `purchase` | `PurchaseStruct` | Yes | The complete purchase/order object containing all purchase information |
| `apiBaseUrl` | `String` | Yes | Base URL for the API (e.g., 'https://api.example.com') |
| `authToken` | `String` | Yes | Authentication token (Bearer token) |
| `width` | `double?` | No | Modal width (default: 600.0 if null) |
| `height` | `double?` | No | Modal height (default: 700.0 if null) |

## Return Value

The action returns a `Map<String, dynamic>` with the following structure:

### Success Response

```dart
{
  'success': true,
  'data': {
    'charge': {
      'id': 123,
      'amount_refunded': 5000,
      'status': 'succeeded',
      // ... other charge fields
    },
    'receipt': {
      'id': 456,
      'receipt_number': 'RET-2025-000123',
      'receipt_type': 'return',
    },
    'pos_event': {
      'id': 789,
      'event_code': '13013',
    },
  },
  'message': 'Refund processed successfully',
  'statusCode': 200,
  'refundAmount': 5000,  // Amount refunded in øre
  'selectedItems': {     // Map of item_id -> quantity refunded
    'item-uuid-1': 2,
    'item-uuid-2': 1,
  },
}
```

### Error Response

```dart
{
  'success': false,
  'message': 'Error message here',
  'errors': {...},  // Optional: validation errors
  'statusCode': 400, // HTTP status code, or 0 for exceptions
}
```

## Modal Features

### Item Selection

- **Select All**: Checkbox to select/deselect all items at once
- **Individual Selection**: Each item can be selected individually
- **Quantity Control**: For items with quantity > 1, users can select how many to refund
- **Visual Feedback**: Selected items are highlighted

### Refund Calculation

- Automatically calculates refund amount based on selected items
- Shows remaining refundable amount
- Validates that refund amount doesn't exceed remaining refundable amount

### Payment Method Handling

- **Cash Payments**: Records the refund (manual cash return process)
- **Stripe Payments**: Automatically processes refund via Stripe API

## Enhanced Purchase Item Fields

After a refund, purchase items now include the following fields for visual indication:

| Field | Type | Description |
|-------|------|-------------|
| `purchase_item_quantity_refunded` | `int?` | Quantity of this item that has been refunded (null if none) |
| `purchase_item_is_refunded` | `bool` | True if the entire item quantity has been refunded |
| `purchase_item_is_partially_refunded` | `bool` | True if some but not all of the item has been refunded |

### Example: Displaying Refund Status

```dart
// In your FlutterFlow UI
if (item.purchaseItemIsRefunded == true) {
  // Show as fully refunded (e.g., strikethrough, grayed out)
  return Container(
    decoration: BoxDecoration(
      color: Colors.grey[200],
    ),
    child: Text(
      item.purchaseItemProductName ?? '',
      style: TextStyle(
        decoration: TextDecoration.lineThrough,
        color: Colors.grey,
      ),
    ),
  );
} else if (item.purchaseItemIsPartiallyRefunded == true) {
  // Show as partially refunded
  return Container(
    child: Column(
      children: [
        Text(item.purchaseItemProductName ?? ''),
        Text(
          '${item.purchaseItemQuantityRefunded} av ${item.purchaseItemQuantity} refundert',
          style: TextStyle(color: Colors.orange),
        ),
      ],
    ),
  );
} else {
  // Normal item display
  return Text(item.purchaseItemProductName ?? '');
}
```

## API Changes

### Refund Endpoint Enhancement

The refund endpoint (`POST /api/purchases/{id}/refund`) now accepts an optional `items` array:

```json
{
  "amount": 5000,
  "reason": "Customer returned item",
  "items": [
    {
      "item_id": "item-uuid-1",
      "quantity": 2
    },
    {
      "item_id": "item-uuid-2",
      "quantity": 1
    }
  ]
}
```

### Purchase Items Response Enhancement

Purchase items in the API response now include refund status:

```json
{
  "purchase_items": [
    {
      "purchase_item_id": "item-uuid-1",
      "purchase_item_product_name": "Coffee",
      "purchase_item_quantity": 3,
      "purchase_item_quantity_refunded": 2,
      "purchase_item_is_refunded": false,
      "purchase_item_is_partially_refunded": true,
      // ... other fields
    }
  ]
}
```

## Integration Steps

### 1. Add the Custom Action

1. Copy the `process_order_refund.dart` file to your FlutterFlow custom actions directory
2. Import it in your FlutterFlow project

### 2. Update PurchaseItemStruct (if needed)

If your `PurchaseItemStruct` doesn't have the refund status fields, you may need to add them:

- `purchase_item_quantity_refunded` (int, nullable)
- `purchase_item_is_refunded` (bool)
- `purchase_item_is_partially_refunded` (bool)

### 3. Add Refund Button to Orders Page

In your orders/purchases page:

1. Add a button (e.g., "Refunder" or "Return")
2. Set the action to call `processOrderRefund`
3. Pass the required parameters from the purchase data

### 4. Update UI to Show Refund Status

Update your order items display to show refund status using the new fields:

- Fully refunded items: Show with strikethrough or grayed out
- Partially refunded items: Show refunded quantity
- Normal items: Display normally

## Error Handling

Common error scenarios:

1. **Purchase already fully refunded**: Check `amountRefunded >= purchaseAmount` before showing modal
2. **Invalid purchase ID**: Ensure purchase exists and belongs to the store
3. **POS session not open**: Refunds require an open POS session
4. **Stripe refund failure**: For Stripe payments, if the refund fails, the transaction is rolled back

## Compliance Notes

- All refunds are logged in POS events (event code 13013)
- Return receipts are automatically generated
- Refund reasons are stored for audit trail
- Item-level refund tracking supports compliance requirements

## Example: Complete Integration

```dart
// In your orders page
void _handleRefund(PurchaseStruct purchase) async {
  // Validate purchase can be refunded
  if (purchase.amountRefunded >= purchase.amount) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Ordren er allerede fullstendig refundert')),
    );
    return;
  }

  if (purchase.status != 'succeeded' || !purchase.paid) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Kan ikke refundere en ubetalt ordre')),
    );
    return;
  }

  // Call refund action
  final result = await processOrderRefund(
    context,
    purchase,
    FFAppState().apiBaseUrl,
    FFAppState().authToken,
  );

  if (result['success'] == true) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Refusjon prosessert'),
        backgroundColor: Colors.green,
      ),
    );
    // Refresh purchase data
    await _refreshPurchase(purchase.id);
  } else {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result['message'] ?? 'Refusjon feilet'),
        backgroundColor: Colors.red,
      ),
    );
  }
}
```

## Notes

- The refund modal automatically calculates refund amounts based on item prices and discounts
- For items with quantity > 1, users can select partial quantities to refund
- The action handles both full order refunds and partial item refunds
- Refunded items are tracked in purchase metadata for audit purposes
- The UI should refresh purchase data after a successful refund to show updated status
