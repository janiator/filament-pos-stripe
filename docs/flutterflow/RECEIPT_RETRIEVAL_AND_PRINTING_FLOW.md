# Receipt Retrieval and Printing Flow

This document explains the complete flow for retrieving and printing receipts after a purchase is completed.

## Overview

When a purchase is completed, a receipt is automatically generated on the backend. The receipt can be:
1. **Retrieved** via API endpoints
2. **Displayed** in the FlutterFlow app
3. **Printed** automatically (if configured) or manually

---

## 1. Receipt Generation (Automatic)

### During Purchase Completion

When `completePosPurchase` is called successfully, the backend automatically:

1. **Processes the payment** (creates `ConnectedCharge`)
2. **Generates the receipt** via `ReceiptGenerationService::generateSalesReceipt()`
3. **Creates receipt record** in the database with:
   - Receipt number (sequential per store)
   - Receipt type (`sales`)
   - Receipt data (items, totals, taxes, etc.)
   - XML template for printing
4. **Auto-prints receipt** (if `pos.auto_print_receipts` config is `true`)
5. **Returns receipt info** in the purchase response

### Purchase Response Structure

```json
{
  "success": true,
  "data": {
    "charge": {
      "id": 123,
      "stripe_charge_id": "ch_xxx",
      "amount": 10000,
      "currency": "nok",
      "status": "succeeded",
      "payment_method": "card_present",
      "paid_at": "2025-12-02T10:30:00Z"
    },
    "receipt": {
      "id": 456,
      "receipt_number": "1-S-000001",
      "receipt_type": "sales"
    },
    "pos_event": {
      "id": 789,
      "event_code": "13012",
      "transaction_code": "11002"
    }
  }
}
```

**Key Fields:**
- `receipt.id` - Use this to retrieve full receipt details
- `receipt.receipt_number` - Display this to the user
- `receipt.receipt_type` - Usually `"sales"` for purchases

---

## 2. Retrieving Receipt Details

### Option 1: Get Receipt by ID (Recommended)

**Endpoint:** `GET /api/receipts/{id}`

**Example:**
```dart
final receiptId = purchaseResult['data']['receipt']['id'];
final receiptResponse = await http.get(
  Uri.parse('$apiBaseUrl/api/receipts/$receiptId'),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
);

final receiptData = jsonDecode(receiptResponse.body)['receipt'];
```

**Response Structure:**
```json
{
  "receipt": {
    "id": 456,
    "receipt_number": "1-S-000001",
    "receipt_type": "sales",
    "store_id": 1,
    "pos_session_id": 123,
    "pos_session": {
      "id": 123,
      "session_number": "S-2025-12-02-001"
    },
    "charge_id": 123,
    "charge": {
      "id": 123,
      "stripe_charge_id": "ch_xxx",
      "amount": 10000
    },
    "user_id": 1,
    "user": {
      "id": 1,
      "name": "John Doe"
    },
    "receipt_data": {
      "receipt_number": "1-S-000001",
      "store": {
        "name": "My Store",
        "address": "...",
        "org_number": "..."
      },
      "items": [
        {
          "name": "Product Name",
          "quantity": 2,
          "unit_price": 5000,
          "total": 10000
        }
      ],
      "subtotal": 8000,
      "total_tax": 2000,
      "total": 10000,
      "payment_method": "card_present",
      "date": "2025-12-02T10:30:00Z"
    },
    "printed": true,
    "printed_at": "2025-12-02T10:30:00Z",
    "reprint_count": 0,
    "created_at": "2025-12-02T10:30:00Z"
  }
}
```

### Option 2: List Receipts (with filters)

**Endpoint:** `GET /api/receipts`

**Query Parameters:**
- `receipt_type` - Filter by type (`sales`, `return`, etc.)
- `pos_session_id` - Filter by POS session
- `printed` - Filter by printed status (`true`/`false`)
- `from_date` - Filter from date (YYYY-MM-DD)
- `to_date` - Filter to date (YYYY-MM-DD)
- `per_page` - Results per page (default: 20)

**Example:**
```dart
final receiptsResponse = await http.get(
  Uri.parse('$apiBaseUrl/api/receipts?pos_session_id=$sessionId&per_page=10'),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
);

final receiptsData = jsonDecode(receiptsResponse.body);
final receipts = receiptsData['receipts'] as List;
```

---

## 3. Retrieving Receipt XML for Printing

### Get Receipt XML

**Endpoint:** `GET /api/receipts/{id}/xml`

**Response:** XML content (Content-Type: `application/xml`)

**Example:**
```dart
final xmlResponse = await http.get(
  Uri.parse('$apiBaseUrl/api/receipts/$receiptId/xml'),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/xml',
  },
);

final receiptXml = xmlResponse.body;
// Use this XML to send to printer or display
```

**Use Cases:**
- Send XML directly to printer
- Display formatted receipt in app
- Save receipt as file
- Email receipt to customer

---

## 4. Printing Receipts

### Automatic Printing

Receipts are automatically printed when:
1. Purchase is completed successfully
2. `pos.auto_print_receipts` config is `true` (default)
3. POS device has a printer configured

**No action required** - happens automatically on backend.

### Manual Printing

If automatic printing fails or is disabled, you can:

#### Option 1: Mark as Printed (if printed externally)

**Endpoint:** `POST /api/receipts/{id}/mark-printed`

```dart
await http.post(
  Uri.parse('$apiBaseUrl/api/receipts/$receiptId/mark-printed'),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
);
```

#### Option 2: Reprint Receipt

**Endpoint:** `POST /api/receipts/{id}/reprint`

This increments the `reprint_count` and can trigger reprinting logic.

```dart
await http.post(
  Uri.parse('$apiBaseUrl/api/receipts/$receiptId/reprint'),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
);
```

#### Option 3: Print from FlutterFlow

1. **Get receipt XML:**
   ```dart
   final xmlResponse = await http.get(
     Uri.parse('$apiBaseUrl/api/receipts/$receiptId/xml'),
     headers: {
       'Authorization': 'Bearer $authToken',
     },
   );
   final receiptXml = xmlResponse.body;
   ```

2. **Send to printer:**
   - Use a Flutter printing package (e.g., `printing`, `esc_pos_utils`)
   - Or send XML to printer via network
   - Or use device's native printing capabilities

---

## 5. FlutterFlow Implementation Example

### Complete Purchase Flow with Receipt Display

```dart
// Step 1: Complete purchase
final purchaseResult = await completePosPurchase(
  posSessionId: currentSessionId,
  paymentMethodCode: 'card_present',
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: paymentIntentId,
  additionalMetadataJson: null,
  isSplitPayment: false,
  splitPaymentsJson: null,
);

if (purchaseResult['success'] == true) {
  final receiptId = purchaseResult['data']['receipt']['id'];
  final receiptNumber = purchaseResult['data']['receipt']['receipt_number'];
  
  // Step 2: Retrieve full receipt details
  final receiptResponse = await http.get(
    Uri.parse('$apiBaseUrl/api/receipts/$receiptId'),
    headers: {
      'Authorization': 'Bearer $authToken',
      'Accept': 'application/json',
    },
  );
  
  if (receiptResponse.statusCode == 200) {
    final receiptData = jsonDecode(receiptResponse.body)['receipt'];
    
    // Step 3: Display receipt in UI
    // - Show receipt number
    // - Show items, totals, payment method
    // - Option to print/reprint
    
    // Step 4: Optionally get XML for printing
    final xmlResponse = await http.get(
      Uri.parse('$apiBaseUrl/api/receipts/$receiptId/xml'),
      headers: {
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/xml',
      },
    );
    
    final receiptXml = xmlResponse.body;
    // Use receiptXml for printing or display
  }
}
```

### Receipt Display Widget

```dart
// Create a receipt display widget
Widget buildReceiptDisplay(Map<String, dynamic> receiptData) {
  final receipt = receiptData['receipt_data'];
  
  return Column(
    children: [
      Text('Receipt: ${receiptData['receipt_number']}'),
      Text('Store: ${receipt['store']['name']}'),
      Text('Date: ${receipt['date']}'),
      
      // Items
      ...receipt['items'].map<Widget>((item) => 
        ListTile(
          title: Text(item['name']),
          trailing: Text('${item['total'] / 100} NOK'),
        )
      ),
      
      // Totals
      Divider(),
      Text('Subtotal: ${receipt['subtotal'] / 100} NOK'),
      Text('Tax: ${receipt['total_tax'] / 100} NOK'),
      Text('Total: ${receipt['total'] / 100} NOK'),
      Text('Payment: ${receipt['payment_method']}'),
    ],
  );
}
```

---

## 6. Receipt Types

### Sales Receipt (`sales`)
- Normal purchase receipt
- Generated automatically on purchase
- Contains all transaction details

### Return Receipt (`return`)
- Generated when processing refunds/returns
- Links to original receipt
- Negative amounts

### Copy Receipt (`copy`)
- Copy of an existing receipt
- Same data as original
- Different receipt number

### Other Types
- `steb` - STEB receipt
- `provisional` - Provisional receipt
- `training` - Training receipt
- `delivery` - Delivery receipt

---

## 7. Receipt Data Structure

### Receipt Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | Integer | Receipt ID |
| `receipt_number` | String | Human-readable receipt number |
| `receipt_type` | String | Type of receipt (`sales`, `return`, etc.) |
| `store_id` | Integer | Store ID |
| `pos_session_id` | Integer | POS session ID |
| `charge_id` | Integer | Related charge ID |
| `user_id` | Integer | User who created receipt |
| `receipt_data` | Object | Full receipt data (items, totals, etc.) |
| `printed` | Boolean | Whether receipt has been printed |
| `printed_at` | DateTime | When receipt was printed |
| `reprint_count` | Integer | Number of times reprinted |
| `created_at` | DateTime | When receipt was created |

### Receipt Data Object

The `receipt_data` field contains:

```json
{
  "receipt_number": "1-S-000001",
  "store": {
    "name": "Store Name",
    "address": "Store Address",
    "org_number": "123456789"
  },
  "items": [
    {
      "name": "Product Name",
      "quantity": 2,
      "unit_price": 5000,
      "discount_amount": 0,
      "total": 10000,
      "tax_rate": 0.25
    }
  ],
  "discounts": [
    {
      "type": "fixed",
      "amount": 1000,
      "reason": "Customer discount"
    }
  ],
  "subtotal": 8000,
  "total_discounts": 1000,
  "total_tax": 2000,
  "total": 10000,
  "tip_amount": 0,
  "payment_method": "card_present",
  "customer_id": "cus_xxx",
  "customer_name": "John Doe",
  "date": "2025-12-02T10:30:00Z",
  "cashier_name": "Jane Smith"
}
```

---

## 8. Error Handling

### Receipt Not Found

```json
{
  "message": "Receipt not found"
}
```
**Status:** 404

### Unauthorized Access

```json
{
  "message": "Receipt does not belong to this store"
}
```
**Status:** 403

### Invalid Receipt ID

```json
{
  "message": "Invalid receipt ID"
}
```
**Status:** 422

---

## 9. Best Practices

### 1. Store Receipt ID After Purchase

```dart
// After successful purchase
final receiptId = purchaseResult['data']['receipt']['id'];
FFAppState().update(() {
  FFAppState().lastReceiptId = receiptId;
});
```

### 2. Display Receipt Immediately

Show receipt details right after purchase completion for better UX.

### 3. Allow Reprint

Provide a "Reprint Receipt" button that:
- Calls `POST /api/receipts/{id}/reprint`
- Retrieves XML and sends to printer
- Updates UI to show reprint count

### 4. Cache Receipt Data

Cache receipt data locally to display even if network is unavailable.

### 5. Handle Print Failures

If automatic printing fails:
- Show notification to user
- Provide manual print option
- Log error for debugging

---

## 10. API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/receipts` | List receipts (with filters) |
| `GET` | `/api/receipts/{id}` | Get receipt details |
| `GET` | `/api/receipts/{id}/xml` | Get receipt XML for printing |
| `POST` | `/api/receipts/generate` | Generate receipt for charge |
| `POST` | `/api/receipts/{id}/mark-printed` | Mark receipt as printed |
| `POST` | `/api/receipts/{id}/reprint` | Reprint receipt |

---

## Related Documentation

- [Purchase Flow](./POS_PURCHASE_INTEGRATION.md)
- [Receipt Template System](../features/RECEIPT_TEMPLATE_SYSTEM.md)
- [Purchase Implementation Guide](./FLUTTERFLOW_PURCHASE_ACTION_IMPLEMENTATION.md)



