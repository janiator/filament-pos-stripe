# Deferred Payments (Payment on Pickup)

## Overview

Deferred payments allow you to create purchases that will be paid later, such as dry cleaning services where payment is collected when the customer picks up their items. This feature complies with Norwegian cash register regulations (Kassasystemforskriften § 2-8-7).

## Use Cases

- **Dry Cleaning:** Customer drops off items, pays when picking up
- **Repairs:** Service performed, payment on completion/pickup
- **Custom Orders:** Order placed, payment when ready for pickup
- **Layaway:** Items reserved, payment completed later

## How It Works

### 1. Creating a Deferred Purchase

When creating a purchase, set `metadata.deferred_payment` to `true`:

```json
{
  "pos_session_id": 1,
  "payment_method_code": "deferred",
  "cart": {
    "items": [
      {
        "product_id": 123,
        "quantity": 1,
        "unit_price": 5000
      }
    ],
    "total": 5000,
    "currency": "nok"
  },
  "metadata": {
    "deferred_payment": true,
    "deferred_reason": "Payment on pickup"
  }
}
```

**What happens:**
- A `ConnectedCharge` is created with `status: 'pending'` and `paid: false`
- A **delivery receipt** (Utleveringskvittering) is generated
- The receipt is marked "Utleveringskvittering – IKKJE KVITTERING FOR KJØP" per regulations
- POS session totals are **not** updated (payment not received yet)
- Cash drawer does **not** open

### 2. Completing Payment

When the customer returns to pay, use the complete payment endpoint:

```json
POST /api/purchases/{charge_id}/complete-payment
{
  "payment_method_code": "cash",
  "metadata": {
    "payment_intent_id": "pi_xxx"  // Only for Stripe payments
  }
}
```

**What happens:**
- Charge status is updated to `'succeeded'` and `paid: true`
- `paid_at` timestamp is set
- A **sales receipt** is generated (replacing the delivery receipt)
- POS session totals are updated
- Cash drawer opens (for cash payments)
- Receipt is automatically printed (if configured)

## Compliance with Kassasystemforskriften

### § 2-8-7 Delivery Receipt (Utleveringskvittering)

The system complies with Norwegian cash register regulations:

1. **Delivery Receipt Required:** For credit sales that will be invoiced/paid later, a delivery receipt must be issued
2. **Clear Marking:** Receipt must be marked "Utleveringskvittering – IKKJE KVITTERING FOR KJØP"
3. **Font Size:** Marked text must be at least 50% larger than amount text
4. **Sequential Numbering:** Delivery receipts are numbered sequentially in their own series
5. **Date Requirements:** Receipts must be dated

### Implementation Details

- **Receipt Type:** `delivery` (separate from `sales` receipts)
- **Receipt Numbering:** Format `{store_id}-D-{sequential_number}` (D = Delivery)
- **Event Logging:** Still logs as sales receipt event (13012) for audit trail
- **Transaction Status:** Charge remains `pending` until payment is completed

## Payment Methods

### Supported Payment Methods for Deferred Purchases

There are **two ways** to create deferred purchases:

#### Option 1: Use Dedicated Payment Method (Recommended)

Use the `deferred` payment method code. This payment method is automatically created for all stores via the seeder:

```json
{
  "payment_method_code": "deferred",
  "metadata": {
    "deferred_reason": "Payment on pickup"
  }
}
```

**Benefits:**
- Clear and explicit in the UI
- Payment method appears in POS as "Betaling ved henting"
- Easy to identify deferred purchases
- No need to remember metadata flags

#### Option 2: Use Metadata Flag with Any Payment Method

Set `metadata.deferred_payment: true` with any existing payment method:

```json
{
  "payment_method_code": "cash",  // or any other payment method
  "metadata": {
    "deferred_payment": true,
    "deferred_reason": "Payment on pickup"
  }
}
```

**When to use:**
- When you want to use a different payment method code for organizational purposes
- When integrating with systems that don't support the deferred payment method

**Note:** Both approaches work identically - they create pending charges and generate delivery receipts.

### Completing Payment

When completing payment, you can use:

- **Cash:** `payment_method_code: "cash"`
- **Stripe Card:** `payment_method_code: "card_present"` (requires `payment_intent_id` in metadata)
- **Other Stripe Methods:** Any Stripe payment method (requires `payment_intent_id`)

## API Examples

### Example 1: Create Deferred Purchase (Dry Cleaning)

```bash
POST /api/purchases
{
  "pos_session_id": 1,
  "payment_method_code": "deferred",
  "cart": {
    "items": [
      {
        "product_id": 456,
        "quantity": 1,
        "unit_price": 15000
      }
    ],
    "total": 15000,
    "currency": "nok"
  },
  "metadata": {
    "deferred_payment": true,
    "deferred_reason": "Dry cleaning - payment on pickup",
    "customer_name": "John Doe"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "charge": {
      "id": 789,
      "status": "pending",
      "amount": 15000,
      "paid": false,
      "paid_at": null
    },
    "receipt": {
      "id": 101,
      "receipt_number": "1-D-000001",
      "receipt_type": "delivery"
    }
  }
}
```

### Example 2: Complete Payment with Cash

```bash
POST /api/purchases/789/complete-payment
{
  "payment_method_code": "cash"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "charge": {
      "id": 789,
      "status": "succeeded",
      "amount": 15000,
      "paid": true,
      "paid_at": "2025-12-09T14:30:00+01:00"
    },
    "receipt": {
      "id": 102,
      "receipt_number": "1-S-000045",
      "receipt_type": "sales"
    }
  }
}
```

### Example 3: Complete Payment with Stripe Card

```bash
POST /api/purchases/789/complete-payment
{
  "payment_method_code": "card_present",
  "metadata": {
    "payment_intent_id": "pi_3ABC123xyz"
  }
}
```

## Frontend Integration (FlutterFlow)

### Creating Deferred Purchase

```dart
final response = await apiCall(
  'POST',
  '/api/purchases',
  {
    'pos_session_id': currentSessionId,
    'payment_method_code': 'deferred',
    'cart': {
      'items': cartItems,
      'total': totalAmount,
      'currency': 'nok'
    },
    'metadata': {
      'deferred_payment': true,
      'deferred_reason': 'Payment on pickup'
    }
  }
);
```

### Completing Payment

```dart
final response = await apiCall(
  'POST',
  '/api/purchases/$chargeId/complete-payment',
  {
    'payment_method_code': selectedPaymentMethod,
    'metadata': {
      if (isStripePayment) 'payment_intent_id': paymentIntentId
    }
  }
);
```

## Database Schema

### ConnectedCharge Fields

For deferred payments:
- `status`: `'pending'`
- `paid`: `false`
- `paid_at`: `null`
- `metadata.deferred_payment`: `true`
- `metadata.deferred_reason`: Reason string

After payment completion:
- `status`: `'succeeded'`
- `paid`: `true`
- `paid_at`: Timestamp
- Payment method fields updated

### Receipt Fields

**Delivery Receipt:**
- `receipt_type`: `'delivery'`
- `receipt_number`: `{store_id}-D-{number}`
- `receipt_data.deferred_reason`: Reason for deferral

**Sales Receipt (after completion):**
- `receipt_type`: `'sales'`
- `receipt_number`: `{store_id}-S-{number}`

## POS Session Totals

**Important:** Deferred payments are **not** included in POS session totals until payment is completed.

- X-reports: Only show paid charges
- Z-reports: Only show paid charges
- Session totals: Only include completed payments

This ensures accurate cash reconciliation.

## Best Practices

1. **Always Set Reason:** Include `deferred_reason` in metadata for clarity
2. **Customer Information:** Store customer name/ID for pickup verification
3. **Receipt Handling:** Give customer the delivery receipt, keep copy for records
4. **Payment Completion:** Complete payment when customer picks up items
5. **Audit Trail:** All transactions are logged in POS events for compliance

## Error Handling

### Common Errors

**Charge Already Paid:**
```json
{
  "success": false,
  "message": "Charge is not pending or already paid"
}
```

**Invalid Payment Method:**
```json
{
  "success": false,
  "message": "Payment method not found"
}
```

**Missing Payment Intent (Stripe):**
```json
{
  "success": false,
  "message": "Payment intent ID is required for Stripe payments"
}
```

## Compliance Checklist

- ✅ Delivery receipts generated for deferred payments
- ✅ Receipts marked "Utleveringskvittering – IKKJE KVITTERING FOR KJØP"
- ✅ Sequential numbering for delivery receipts
- ✅ Sales receipts generated when payment completed
- ✅ All transactions logged in POS events
- ✅ POS session totals exclude unpaid charges
- ✅ Audit trail maintained

## References

- [Kassasystemforskriften § 2-8-7](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616/%C2%A72-8-7)
- [Compliance Documentation](../compliance/KASSASYSTEMFORSKRIFTEN_COMPLIANCE.md)
- [Receipt Types](../compliance/RECEIPT_PRINT_COMPLIANCE.md)
