# Deferred Payments Frontend Implementation Guide

This guide explains how to implement deferred payments (payment on pickup) in your FlutterFlow frontend application.

## Overview

Deferred payments allow you to:
- Create purchases that will be paid later (e.g., dry cleaning, repairs)
- Generate delivery receipts (Utleveringskvittering) per Norwegian regulations
- Complete payment later when customer picks up items
- Generate sales receipts when payment is completed

## Two Ways to Create Deferred Payments

### Option 1: Use "deferred" Payment Method (Recommended)

The simplest approach is to use the dedicated `deferred` payment method code:

```dart
// In your FlutterFlow action call
final result = await completePosPurchase(
  posSessionId: currentSessionId,
  paymentMethodCode: 'deferred',  // Use deferred payment method
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,  // Not needed for deferred
  additionalMetadataJson: jsonEncode({
    'deferred_reason': 'Payment on pickup',  // Optional reason
    'cashier_name': cashierName,
  }),
  isSplitPayment: false,
  splitPaymentsJson: null,
  customerId: customerId,  // Optional: customer database ID
);
```

**Benefits:**
- Clear and explicit - payment method appears as "Betaling ved henting" in POS
- No need to remember metadata flags
- Easy to identify deferred purchases in the UI

### Option 2: Use Metadata Flag with Any Payment Method

You can also use any payment method code and set `deferred_payment: true` in metadata:

```dart
final result = await completePosPurchase(
  posSessionId: currentSessionId,
  paymentMethodCode: 'cash',  // Or any other payment method
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,
  additionalMetadataJson: jsonEncode({
    'deferred_payment': true,  // Set this flag
    'deferred_reason': 'Dry cleaning - payment on pickup',
    'cashier_name': cashierName,
  }),
  isSplitPayment: false,
  splitPaymentsJson: null,
  customerId: customerId,
);
```

## Response Structure

When creating a deferred purchase, the response will look like:

```dart
{
  'success': true,
  'data': {
    'charge': {
      'id': 789,
      'status': 'pending',  // Note: pending, not succeeded
      'amount': 15000,
      'paid': false,  // Not paid yet
      'paid_at': null,
      'stripe_charge_id': null,
    },
    'receipt': {
      'id': 101,
      'receipt_number': '1-D-000001',  // D = Delivery receipt
      'receipt_type': 'delivery',  // Delivery receipt, not sales receipt
    },
    'pos_event': {
      'id': 789,
      'event_code': '13019',
    }
  }
}
```

**Key differences from regular purchases:**
- `charge.status` = `'pending'` (not `'succeeded'`)
- `charge.paid` = `false`
- `charge.paid_at` = `null`
- `receipt.receipt_type` = `'delivery'` (not `'sales'`)
- Receipt number format: `{store_id}-D-{number}` (D = Delivery)

## Completing Deferred Payments

When the customer returns to pay, you need to complete the payment using a separate endpoint.

### Step 1: Create Custom Action for Completing Payment

Create a new custom action in FlutterFlow called `completeDeferredPayment`.

**File Location:** `/docs/flutterflow/custom-actions/complete_deferred_payment.dart`

**Function signature:**
```dart
Future<dynamic> completeDeferredPayment(
  int chargeId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  String? paymentIntentId,  // Optional, for Stripe payments
  String? additionalMetadataJson,  // Optional
) async
```

**Custom code (copy from file):**
```dart
// FlutterFlow Custom Action: Complete Deferred Payment
// 
// This action completes payment for a deferred purchase (payment on pickup).
// It updates the charge status and generates a sales receipt.
//
// Function signature:
// Future<dynamic> completeDeferredPayment(
//   int chargeId,
//   String paymentMethodCode,
//   String apiBaseUrl,
//   String authToken,
//   String? paymentIntentId,  // Optional, for Stripe payments
//   String? additionalMetadataJson,  // Optional
// ) async

import 'dart:convert';
import 'package:http/http.dart' as http;

Future<dynamic> completeDeferredPayment(
  int chargeId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  String? paymentIntentId,
  String? additionalMetadataJson,
) async {
  try {
    // Parse additional metadata from JSON string
    Map<String, dynamic> metadata = {};
    if (additionalMetadataJson != null && additionalMetadataJson.isNotEmpty) {
      try {
        metadata = jsonDecode(additionalMetadataJson) as Map<String, dynamic>;
      } catch (e) {
        // If JSON parsing fails, use empty map
        metadata = {};
      }
    }
    
    // Add payment intent ID if provided (for Stripe payments)
    if (paymentIntentId != null && paymentIntentId.isNotEmpty) {
      metadata['payment_intent_id'] = paymentIntentId;
    }
    
    // Build request body
    final requestBody = {
      'payment_method_code': paymentMethodCode,
      if (metadata.isNotEmpty) 'metadata': metadata,
    };
    
    // Make API request
    final response = await http.post(
      Uri.parse('$apiBaseUrl/api/purchases/$chargeId/complete-payment'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
      body: jsonEncode(requestBody),
    );
    
    // Parse response
    final responseData = jsonDecode(response.body) as Map<String, dynamic>;
    
    // Check HTTP status code
    if (response.statusCode >= 200 && response.statusCode < 300) {
      // Success
      return {
        'success': responseData['success'] ?? true,
        'data': responseData['data'],
        'message': responseData['message'],
      };
    } else {
      // Error
      return {
        'success': false,
        'message': responseData['message'] ?? 'Payment completion failed',
        'errors': responseData['errors'],
        'statusCode': response.statusCode,
      };
    }
  } catch (e) {
    // Handle exceptions
    return {
      'success': false,
      'message': 'Error completing payment: ${e.toString()}',
      'error': e.toString(),
    };
  }
}
```

### Step 2: Configure Parameters in FlutterFlow

Add these parameters to the custom action:

| Parameter Name | Type | Required | Description |
|----------------|------|----------|-------------|
| `chargeId` | `Integer` | ✅ Yes | The purchase/charge ID to complete payment for |
| `paymentMethodCode` | `String` | ✅ Yes | Payment method code (e.g., "cash", "card_present") |
| `apiBaseUrl` | `String` | ✅ Yes | API base URL |
| `authToken` | `String` | ✅ Yes | Authentication token |
| `paymentIntentId` | `String` | ❌ No | Stripe payment intent ID (for Stripe payments) |
| `additionalMetadataJson` | `String` | ❌ No | Additional metadata as JSON string |

### Step 3: Use in Your FlutterFlow Flow

**Example: Complete payment with cash**

```dart
final result = await completeDeferredPayment(
  chargeId: pendingPurchaseId,  // ID from the deferred purchase
  paymentMethodCode: 'cash',
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,
  additionalMetadataJson: null,
);

if (result['success'] == true) {
  // Payment completed successfully
  final charge = result['data']['charge'];
  final receipt = result['data']['receipt'];
  
  // Show success message
  // receipt.receipt_type will be 'sales'
  // receipt.receipt_number will be in format: {store_id}-S-{number}
} else {
  // Handle error
  final errorMessage = result['message'];
  // Show error to user
}
```

**Example: Complete payment with Stripe card**

```dart
// First, create payment intent using Stripe Terminal SDK
final paymentIntent = await stripeTerminal.createPaymentIntent(...);

// Then complete the deferred payment
final result = await completeDeferredPayment(
  chargeId: pendingPurchaseId,
  paymentMethodCode: 'card_present',
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: paymentIntent.id,  // Required for Stripe payments
  additionalMetadataJson: null,
);
```

## Response Structure for Completed Payment

When completing a deferred payment, the response will look like:

```dart
{
  'success': true,
  'data': {
    'charge': {
      'id': 789,
      'status': 'succeeded',  // Now succeeded
      'amount': 15000,
      'paid': true,  // Now paid
      'paid_at': '2025-12-09T14:30:00+01:00',
      'payment_method': 'cash',
    },
    'receipt': {
      'id': 102,
      'receipt_number': '1-S-000045',  // S = Sales receipt
      'receipt_type': 'sales',  // Now sales receipt
    },
    'pos_event': {
      'id': 790,
      'event_code': '13012',  // Sales receipt event
    }
  }
}
```

## UI Flow Recommendations

### Creating Deferred Purchase

1. **Add "Deferred Payment" option** to your payment method selection screen
   - Display as "Betaling ved henting" or "Payment on pickup"
   - Use payment method code: `'deferred'`

2. **Optional: Add reason field**
   - Allow cashier to enter reason (e.g., "Dry cleaning", "Repairs")
   - Pass as `deferred_reason` in metadata

3. **Show delivery receipt**
   - Display the delivery receipt to customer
   - Print delivery receipt (if configured)
   - Note: This is NOT a sales receipt - it's marked "Utleveringskvittering"

### Completing Payment

1. **Find pending purchases**
   - Query purchases with `status: 'pending'` or `paid: false`
   - Filter by customer if needed
   - Display list of pending purchases

2. **Select purchase to complete**
   - Show purchase details (items, amount, date created)
   - Show delivery receipt number

3. **Select payment method**
   - Cash: Simple - just call complete payment
   - Stripe Card: Create payment intent first, then complete payment

4. **Show sales receipt**
   - Display the new sales receipt
   - Print sales receipt (if configured)
   - Note: This replaces the delivery receipt
   - The receipt ID is returned in `result['data']['receipt']['id']`
   - When retrieving the purchase later, `purchase.purchase_receipt` will show the sales receipt (not the delivery receipt)

## Example: Complete Flow

### 1. Create Deferred Purchase

```dart
// User selects "Payment on pickup" option
final result = await completePosPurchase(
  posSessionId: currentSessionId,
  paymentMethodCode: 'deferred',
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,
  additionalMetadataJson: jsonEncode({
    'deferred_reason': 'Dry cleaning',
    'customer_name': customerName,
  }),
  isSplitPayment: false,
  splitPaymentsJson: null,
  customerId: customerId,
);

if (result['success'] == true) {
  final chargeId = result['data']['charge']['id'];
  final receiptNumber = result['data']['receipt']['receipt_number'];
  
  // Store chargeId for later completion
  // Show delivery receipt to customer
  // Print delivery receipt
}
```

### 2. Complete Payment Later

```dart
// User selects pending purchase and payment method
final result = await completeDeferredPayment(
  chargeId: pendingChargeId,  // The purchase/charge ID from the deferred purchase
  paymentMethodCode: selectedPaymentMethod,  // 'cash' or 'card_present'
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: paymentIntentId,  // Required for Stripe payments, null for cash
  additionalMetadataJson: null,  // Optional additional metadata
);

if (result['success'] == true) {
  final charge = result['data']['charge'];
  final receipt = result['data']['receipt'];
  
  // Payment completed successfully
  // charge.status = "succeeded"
  // charge.paid = true
  // receipt.receipt_type = "sales" (replaced delivery receipt)
  
  // Show sales receipt to customer
  // Print sales receipt (if configured)
  // Update UI to remove from pending list
  // Cash drawer will open automatically for cash payments
} else {
  // Handle error
  final errorMessage = result['message'];
  // Show error to user
}
```

**Function Signature:**
```dart
Future<dynamic> completeDeferredPayment(
  int chargeId,                 // Required: Purchase/charge ID
  String paymentMethodCode,     // Required: 'cash', 'card_present', etc.
  String apiBaseUrl,            // Required: API base URL
  String authToken,             // Required: Authentication token
  String? paymentIntentId,      // Optional: Required for Stripe payments
  String? additionalMetadataJson, // Optional: Additional metadata as JSON string
)
```

**Parameters:**
- `chargeId`: The ID of the deferred purchase (charge ID) that was returned when creating the deferred purchase
- `paymentMethodCode`: The payment method code to use for completion (e.g., `'cash'`, `'card_present'`, `'card'`)
- `apiBaseUrl`: Your API base URL (e.g., `'https://api.example.com'`)
- `authToken`: Bearer token for authentication
- `paymentIntentId`: **Required for Stripe payments** - the payment intent ID from Stripe Terminal or card payment. Set to `null` for cash payments.
- `additionalMetadataJson`: Optional JSON string with additional metadata (e.g., `jsonEncode({'cashier_name': 'John Doe'})`)

**Response Structure:**
```dart
{
  'success': true,
  'data': {
    'charge': {
      'id': 123,
      'stripe_charge_id': 'ch_xxx',
      'amount': 11000,
      'currency': 'nok',
      'status': 'succeeded',  // Changed from 'pending'
      'payment_method': 'cash',
      'paid_at': '2025-12-09T14:30:00+01:00'
    },
    'receipt': {
      'id': 456,
      'receipt_number': '1-S-000001',  // Sales receipt (S = Sales)
      'receipt_type': 'sales'  // Replaced delivery receipt
    },
    'pos_event': {
      'id': 789,
      'event_code': '13012',
      'transaction_code': '1'
    }
  },
  'message': 'Payment completed successfully',
  'statusCode': 200
}
```

## Querying Pending Purchases

To show a list of pending purchases, use the purchases list endpoint with filters:

```dart
// GET /api/purchases?status=pending
final response = await http.get(
  Uri.parse('$apiBaseUrl/api/purchases?status=pending'),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
);

final data = jsonDecode(response.body);
final pendingPurchases = data['purchases'] as List;

// Filter by customer if needed
// Filter by date range if needed
```

## Error Handling

### Common Errors

**Charge Already Paid:**
```dart
{
  'success': false,
  'message': 'Charge is not pending or already paid'
}
```
**Solution:** Check charge status before attempting to complete payment.

**Invalid Payment Method:**
```dart
{
  'success': false,
  'message': 'Payment method not found'
}
```
**Solution:** Verify payment method code is correct and enabled.

**Missing Payment Intent (Stripe):**
```dart
{
  'success': false,
  'message': 'Payment intent ID is required for Stripe payments'
}
```
**Solution:** Create payment intent before completing payment.

## Best Practices

1. **Always store charge ID** when creating deferred purchase
2. **Show delivery receipt** to customer immediately
3. **Store customer information** for pickup verification
4. **Query pending purchases** regularly to show in UI
5. **Validate charge status** before completing payment
6. **Handle errors gracefully** with clear user messages
7. **Print receipts** at appropriate times (delivery receipt on creation, sales receipt on completion)

## Compliance Notes

- Delivery receipts are marked "Utleveringskvittering – IKKJE KVITTERING FOR KJØP"
- Delivery receipts have separate numbering (D-series)
- Sales receipts are generated when payment is completed
- All transactions are logged for audit trail
- POS session totals only include completed payments
