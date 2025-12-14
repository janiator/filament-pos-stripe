# Stripe Terminal Purchase Integration Guide

This guide explains how to integrate Stripe Terminal payments with the `completePosPurchase` action in FlutterFlow.

## Overview

There are two approaches to handle Stripe Terminal payments:

1. **All-in-one action**: `completeStripeTerminalPurchase` - Handles everything in one call
2. **Two-step approach**: Use `createAndProcessTerminalPayment` + `completePosPurchase` separately

## Option 1: All-in-One Action (Recommended for Simple Flows)

The `completeStripeTerminalPurchase` action handles the entire flow:
1. Creates a payment intent
2. Collects and confirms the payment via Stripe Terminal
3. Completes the purchase using `completePosPurchase`

### Setup

1. **Add Custom Action in FlutterFlow:**
   - Go to **Actions** → **Custom Actions**
   - Click **+ Add Action**
   - Name: `completeStripeTerminalPurchase`

2. **Configure Parameters:**

| Parameter Name | Type | Required | Default Value | Description |
|----------------|------|----------|---------------|-------------|
| `posSessionId` | `Integer` | ✅ Yes | - | Current POS session ID |
| `paymentMethodCode` | `String` | ✅ Yes | - | Payment method code (e.g., "card_present") |
| `apiBaseUrl` | `String` | ✅ Yes | - | API base URL |
| `authToken` | `String` | ✅ Yes | - | Authentication token (Bearer token) |
| `storeSlug` | `String` | ✅ Yes | - | Store slug for payment intent creation |
| `additionalMetadataJson` | `String` | ❌ No | `null` | Additional metadata as JSON string |

3. **Configure Return Type:**
   - Set **Return Type**: `dynamic`

4. **Add Custom Code:**
   - Click **Backend** tab
   - Paste the code from `docs/flutterflow/custom-actions/complete_stripe_terminal_purchase.dart`

5. **Add Dependencies:**
   Ensure these packages are in your `pubspec.yaml`:
   ```yaml
   dependencies:
     http: ^1.1.0
     mek_stripe_terminal: ^latest
   ```

### Usage Example

```dart
// In your FlutterFlow action flow
final result = await completeStripeTerminalPurchase(
  posSessionId: FFAppState().currentPosSessionId,
  paymentMethodCode: 'card_present',
  apiBaseUrl: 'https://your-api.com',
  authToken: FFAppState().authToken,
  storeSlug: FFAppState().currentStoreSlug,
  additionalMetadataJson: jsonEncode({
    'cashier_name': FFAppState().currentUser?.name,
    'device_id': FFAppState().currentDeviceId,
  }),
);

if (result['success'] == true) {
  // Show success message
  // Clear cart
  // Navigate to home
} else {
  // Show error message from result['message']
}
```

### Response Format

**Success:**
```dart
{
  'success': true,
  'data': {
    'charge': {...},
    'receipt': {...},
    'pos_event': {...}
  },
  'message': 'Purchase completed successfully'
}
```

**Error:**
```dart
{
  'success': false,
  'message': 'Error message here',
  'error': 'Error details',
  'statusCode': 400  // If applicable
}
```

---

## Option 2: Two-Step Approach (More Control)

Use `createAndProcessTerminalPayment` to handle the payment, then call `completePosPurchase` separately.

### Setup

1. **Add Custom Action: `createAndProcessTerminalPayment`**
   - Go to **Actions** → **Custom Actions**
   - Click **+ Add Action**
   - Name: `createAndProcessTerminalPayment`

2. **Configure Parameters:**

| Parameter Name | Type | Required | Default Value | Description |
|----------------|------|----------|---------------|-------------|
| `amount` | `Integer` | ✅ Yes | - | Amount in øre |
| `apiBaseUrl` | `String` | ✅ Yes | - | API base URL |
| `authToken` | `String` | ✅ Yes | - | Authentication token |
| `storeSlug` | `String` | ✅ Yes | - | Store slug |
| `description` | `String` | ❌ No | `null` | Payment description |

3. **Add Custom Code:**
   - Paste the code from `docs/flutterflow/custom-actions/create_and_process_terminal_payment.dart`

### Usage Example

```dart
// Step 1: Create and process terminal payment
final paymentResult = await createAndProcessTerminalPayment(
  amount: FFAppState().cart.cartTotalCartPrice,
  apiBaseUrl: 'https://your-api.com',
  authToken: FFAppState().authToken,
  storeSlug: FFAppState().currentStoreSlug,
  description: 'POS Purchase',
);

if (paymentResult['success'] != true) {
  // Handle payment error
  showError(paymentResult['message']);
  return;
}

final paymentIntentId = paymentResult['paymentIntentId'] as String;

// Step 2: Complete purchase with payment intent ID
final purchaseResult = await completePosPurchase(
  posSessionId: FFAppState().currentPosSessionId,
  paymentMethodCode: 'card_present',
  apiBaseUrl: 'https://your-api.com',
  authToken: FFAppState().authToken,
  paymentIntentId: paymentIntentId,
  additionalMetadataJson: jsonEncode({
    'cashier_name': FFAppState().currentUser?.name,
    'device_id': FFAppState().currentDeviceId,
  }),
  isSplitPayment: false,
  splitPaymentsJson: null,
);

if (purchaseResult['success'] == true) {
  // Show success message
  // Clear cart
  // Navigate to home
} else {
  // Show error message
  showError(purchaseResult['message']);
}
```

### Response Format

**Success:**
```dart
{
  'success': true,
  'paymentIntentId': 'pi_xxx',
  'message': 'Payment processed successfully'
}
```

**Error:**
```dart
{
  'success': false,
  'message': 'Error message here',
  'error': 'Error details',
  'errorCode': 'canceled'  // If applicable
}
```

---

## Stripe Reader Status Updates

Both actions update `FFAppState().stripeReaderStatus` during the payment process:

- `'Oppretter betaling…'` - Creating payment intent
- `'Henter betaling…'` - Retrieving payment intent
- `'Venter på kort…'` - Waiting for card
- `'Behandler betaling…'` - Processing payment
- `'Betaling vellykket'` - Payment successful
- `'Betaling avbrutt'` - Payment canceled
- `'Betalingsfeil: ...'` - Payment error

You can display this status in your UI to provide user feedback.

---

## Error Handling

### Payment Canceled

If the user cancels the payment:
```dart
{
  'success': false,
  'message': 'Payment was canceled',
  'errorCode': 'TerminalExceptionCode.canceled'
}
```

### Payment Failed

If the payment fails:
```dart
{
  'success': false,
  'message': 'Payment failed: [error message]',
  'error': '[full error details]'
}
```

### API Errors

If the payment intent creation fails:
```dart
{
  'success': false,
  'message': 'Failed to create payment intent',
  'statusCode': 400
}
```

---

## Split Payments with Stripe Terminal

For split payments that include Stripe Terminal:

```dart
// Process Stripe payment first
final paymentResult = await createAndProcessTerminalPayment(
  amount: stripeAmount,  // Amount for this split
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  storeSlug: storeSlug,
);

if (paymentResult['success'] != true) {
  // Handle error
  return;
}

final paymentIntentId = paymentResult['paymentIntentId'] as String;

// Build split payments array
final splitPayments = [
  {
    'payment_method_code': 'cash',
    'amount': cashAmount,
    'metadata': {},
  },
  {
    'payment_method_code': 'card_present',
    'amount': stripeAmount,
    'metadata': {
      'payment_intent_id': paymentIntentId,
    },
  },
];

// Complete purchase with split payments
final purchaseResult = await completePosPurchase(
  posSessionId: posSessionId,
  paymentMethodCode: '',  // Not used for split payments
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,  // Not used for split payments
  additionalMetadataJson: jsonEncode({}),
  isSplitPayment: true,
  splitPaymentsJson: jsonEncode(splitPayments),
);
```

---

## Comparison: Option 1 vs Option 2

| Feature | Option 1 (All-in-One) | Option 2 (Two-Step) |
|---------|----------------------|---------------------|
| **Simplicity** | ✅ Simpler, one call | ❌ More complex, two calls |
| **Control** | ❌ Less control over flow | ✅ More control |
| **Error Handling** | ✅ Handles all errors together | ✅ Can handle errors separately |
| **Split Payments** | ❌ Not directly supported | ✅ Better for split payments |
| **Reusability** | ❌ Less reusable | ✅ More reusable |

**Recommendation:**
- Use **Option 1** for simple single payment flows
- Use **Option 2** for split payments or when you need more control

---

## Troubleshooting

### Payment Intent Creation Fails

- Check that `storeSlug` is correct
- Verify the store has a Stripe account connected
- Ensure `authToken` is valid
- Check API base URL is correct

### Terminal Payment Fails

- Ensure Stripe Terminal is properly initialized
- Check that a reader is connected
- Verify the reader is in the correct location
- Check network connectivity

### Purchase Completion Fails

- Verify `paymentIntentId` is correct
- Check that payment intent status is `succeeded`
- Ensure cart data is valid
- Verify POS session is active

---

## Related Documentation

- [Complete POS Purchase Implementation](./FLUTTERFLOW_PURCHASE_ACTION_IMPLEMENTATION.md)
- [POS Purchase Integration](./POS_PURCHASE_INTEGRATION.md)
- [POS Frontend Steps](./POS_FRONTEND_STEPS.md)

