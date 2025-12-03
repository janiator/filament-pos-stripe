# FlutterFlow POS Purchase Custom Action Implementation Guide

This guide provides step-by-step instructions for implementing the `completePosPurchase` custom action in FlutterFlow and integrating it into your purchase flow.

## Overview

The `completePosPurchase` custom action:
- ✅ Converts FlutterFlow cart data to API format
- ✅ Handles single and split payments
- ✅ Supports cash and Stripe payments
- ✅ Returns structured response for success/error handling
- ✅ Validates cart and session data

---

## Step 1: Create the Custom Action in FlutterFlow

### 1.1 Add Custom Action

1. Go to **Actions** → **Custom Actions**
2. Click **+ Add Action**
3. Name: `completePosPurchase`

### 1.2 Configure Parameters

Add these parameters in order:

| Parameter Name | Type | Required | Default Value | Description |
|----------------|------|----------|---------------|-------------|
| `posSessionId` | `Integer` | ✅ Yes | - | Current POS session ID |
| `paymentMethodCode` | `String` | ✅ Yes | - | Payment method code (e.g., "cash", "card_present") |
| `apiBaseUrl` | `String` | ✅ Yes | - | API base URL (e.g., "https://pos-stripe.share.visivo.no") |
| `authToken` | `String` | ✅ Yes | - | Authentication token (Bearer token) |
| `paymentIntentId` | `String` | ❌ No | `null` | Stripe payment intent ID (for card payments) |
| `additionalMetadataJson` | `String` | ❌ No | `null` | Additional metadata as JSON string (cashier name, device ID, etc.) |
| `isSplitPayment` | `Boolean` | ❌ No | `false` | Whether this is a split payment |
| `splitPaymentsJson` | `String` | ❌ No | `null` | Payment splits as JSON string (if split payment) |

**Important:** FlutterFlow doesn't support `Map` or `List<Map>` as parameter types, so we use JSON strings instead.

### 1.3 Configure Return Type

1. Set **Return Type**: `dynamic` (not `Map` or `JSON`)
2. FlutterFlow will recognize this and allow you to access the returned map fields

### 1.4 Add Custom Code

1. Click **Backend** tab
2. Paste the code from `docs/flutterflow/custom-actions/complete_pos_purchase.dart`
3. **Important:** Update the code to match your exact data structure:
   - Check how `ProductStruct` stores price (may need conversion to øre)
   - Verify `CartDataStruct` field names match your implementation
   - Adjust total field names (`totalCartPrice`, `subtotalCartPrice`, etc.)

### 1.5 Add Dependencies

Ensure these packages are in your `pubspec.yaml`:

```yaml
dependencies:
  http: ^1.1.0
```

---

## Step 2: Update Cart Structure Mapping

### 2.1 Verify Cart Totals

The action expects these fields on `ShoppingCartStruct`:
- `subtotalCartPrice` (Integer, in øre)
- `totalDiscountCartPrice` (Integer, in øre)
- `totalTaxCartPrice` (Integer, in øre)
- `totalCartPrice` (Integer, in øre)
- `tipAmount` (Integer, in øre)

**If your cart uses different field names**, update the action code accordingly.

### 2.2 Verify Product Price Format

The action assumes prices need conversion to øre. Update this line if your prices are already in øre:

```dart
// Current (if price is in NOK):
final unitPrice = (cartItem.product!.price ?? 0) * 100;

// If price is already in øre:
final unitPrice = cartItem.product!.price ?? 0;
```

### 2.3 Add Cart-Level Discounts (Optional)

If your cart supports cart-level discounts, uncomment and update this section:

```dart
// Build discounts array
final List<Map<String, dynamic>> cartDiscounts = [];
if (cart.cartDiscountAmount != null && cart.cartDiscountAmount! > 0) {
  cartDiscounts.add({
    'type': 'fixed', // or 'prosent'
    'amount': cart.cartDiscountAmount!,
    'percentage': cart.cartDiscountPercentage, // if type is 'prosent'
    'reason': cart.cartDiscountReason ?? 'Cart discount',
  });
}
```

---

## Step 3: Configure API Base URL and Auth Token

### 3.1 Configure API Base URL

You can either:
- **Option A:** Store in App Constants (recommended)
  1. Go to **App Settings** → **App Constants**
  2. Add constant: `apiBaseUrl` (String)
  3. Set value to your API base URL (e.g., `https://pos-stripe.share.visivo.no`)
  4. Use in action: `FFAppConstants().apiBaseUrl`

- **Option B:** Pass as parameter (current implementation)
  - Pass the API URL directly as `apiBaseUrl` parameter

### 3.2 Configure Auth Token

You can either:
- **Option A:** Store in App State (recommended)
  1. Go to **App State** → **App State Variables**
  2. Add variable: `authToken` (String)
  3. Set this when user logs in
  4. Use in action: `FFAppState().authToken`

- **Option B:** Pass as parameter (current implementation)
  - Pass the auth token directly as `authToken` parameter

### 3.3 Create JSON Encoding Helper (Optional but Recommended)

Since FlutterFlow doesn't have a built-in `jsonEncode()` function in the UI, you may want to create a helper custom action:

1. **Create Custom Action:** `encodeJson`
2. **Parameters:**
   - `data` (JSON) - The data to encode
3. **Return Type:** `String`
4. **Code:**
   ```dart
   import 'dart:convert';
   
   String encodeJson(dynamic data) {
     return jsonEncode(data);
   }
   ```

Then use it in your action flows: `encodeJson({"key": "value"})`

---

## Step 4: Implement Single Payment Flow

### 4.1 Cash Payment

1. **Create Button Action:**
   - Button: "Complete Cash Payment"
   - On Tap → **Custom Action** → `completePosPurchase`

2. **Configure Parameters:**
   ```
   posSessionId: FFAppState().currentPosSession.id
   paymentMethodCode: "cash"
   apiBaseUrl: FFAppConstants().apiBaseUrl  // or hardcode: "https://pos-stripe.share.visivo.no"
   authToken: FFAppState().authToken  // or get from your auth system
   paymentIntentId: null
   additionalMetadataJson: jsonEncode({
     "cashier_name": FFAppState().currentUser.name,
     "device_id": FFAppState().currentDevice.id
   })
   isSplitPayment: false
   splitPaymentsJson: null
   ```
   
   **Note:** Use `jsonEncode()` to convert maps to JSON strings. You may need to create a helper action for this, or use FlutterFlow's built-in JSON functions.

3. **Handle Response:**
   - Add **Action Flow** after custom action
   - Store result in a variable: `purchaseResult` (type: `dynamic`)
   - Check `purchaseResult['success']` (use conditional action)
   - If true:
     - Show success message
     - Access receipt: `purchaseResult['data']['receipt']['receipt_number']`
     - Clear cart: `clearCart()` action
     - Navigate to home/success page
   - If false:
     - Show error: `purchaseResult['message']`
     - Display validation errors if present: `purchaseResult['errors']`

**Example Action Flow:**
```
[completePosPurchase] 
  ↓
[If result['success'] == true]
  ↓ Yes
  [Show Success Dialog]
  [Clear Cart]
  [Navigate to Home]
  ↓ No
  [Show Error Dialog: result['message']]
```

### 4.2 Stripe Terminal Payment

1. **Step 1: Create Payment Intent**
   - Use existing Stripe Terminal integration
   - Create payment intent with amount: `FFAppState().cart.totalCartPrice`

2. **Step 2: Collect Payment**
   - Use Stripe Terminal SDK to collect and confirm payment
   - Store `paymentIntentId` in a local variable

3. **Step 3: Complete Purchase**
   - **Custom Action** → `completePosPurchase`
   - **Parameters:**
     ```
     posSessionId: FFAppState().currentPosSession.id
     paymentMethodCode: "card_present"
     apiBaseUrl: FFAppConstants().apiBaseUrl
     authToken: FFAppState().authToken
     paymentIntentId: <payment_intent_id_from_step_2>
     additionalMetadataJson: jsonEncode({
       "cashier_name": FFAppState().currentUser.name
     })
     isSplitPayment: false
     splitPaymentsJson: null
     ```

4. **Handle Response:** Same as cash payment

---

## Step 5: Implement Split Payment Flow

### 5.1 Create Split Payment UI

1. **Create Page:** "Split Payment"
2. **Add Components:**
   - Display cart total
   - List of payment splits (payment method + amount)
   - "Add Payment" button
   - "Complete Purchase" button

3. **Store Split Payments:**
   - Create app state variable: `splitPayments` (List<JSON>)
   - Each item: `{"payment_method_code": "cash", "amount": 5000, "metadata": {}}`

### 5.2 Process Stripe Payments First

For each split payment with `payment_method_code == "card_present"`:

1. Create payment intent for that amount
2. Collect payment using Stripe Terminal SDK
3. Add `payment_intent_id` to split payment metadata:
   ```dart
   splitPayment['metadata'] = {
     'payment_intent_id': paymentIntentId
   };
   ```

### 5.3 Complete Split Purchase

1. **Validate Split Amounts:**
   ```dart
   // Custom action or inline code
   int totalPaid = 0;
   for (var split in splitPayments) {
     totalPaid += split['amount'];
   }
   
   if (totalPaid != FFAppState().cart.totalCartPrice) {
     // Show error: "Payment amounts must equal cart total"
     return;
   }
   ```

2. **Call Purchase Action:**
   - **Custom Action** → `completePosPurchase`
   - **Parameters:**
     ```
     posSessionId: FFAppState().currentPosSession.id
     paymentMethodCode: ""  // Not used for split payments
     apiBaseUrl: FFAppConstants().apiBaseUrl
     authToken: FFAppState().authToken
     paymentIntentId: null
     additionalMetadataJson: jsonEncode({
       "cashier_name": FFAppState().currentUser.name
     })
     isSplitPayment: true
     splitPaymentsJson: jsonEncode(FFAppState().splitPayments)
     ```
   
   **Note:** `splitPayments` should be a list of maps like:
   ```dart
   [
     {"payment_method_code": "cash", "amount": 5000, "metadata": {}},
     {"payment_method_code": "card_present", "amount": 5000, "metadata": {"payment_intent_id": "pi_xxx"}}
   ]
   ```

3. **Handle Response:** Same as single payment

---

## Step 6: Response Handling

### 6.1 Success Response Structure

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
      "payment_method": "cash",
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
      "transaction_code": "11001"
    }
  },
  "message": "Purchase completed successfully"
}
```

### 6.2 Error Response Structure

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "cart.total": ["The cart total must be at least 1."]
  },
  "statusCode": 422
}
```

### 6.3 Create Response Handler Actions

**Success Handler:**
1. Create custom action: `handlePurchaseSuccess`
2. Parameters: `result` (JSON)
3. Actions:
   - Show success dialog with receipt number
   - Clear cart
   - Navigate to home

**Error Handler:**
1. Create custom action: `handlePurchaseError`
2. Parameters: `result` (JSON)
3. Actions:
   - Show error dialog with message
   - Display validation errors if present
   - Keep cart intact

---

## Step 7: Testing Checklist

- [ ] **Cash Payment:**
  - [ ] Cart with items completes successfully
  - [ ] Receipt number is displayed
  - [ ] Cart is cleared after success
  - [ ] Cash drawer opens (verify physically)

- [ ] **Stripe Terminal Payment:**
  - [ ] Payment intent is created
  - [ ] Payment is collected via Terminal SDK
  - [ ] Purchase completes successfully
  - [ ] Receipt is generated

- [ ] **Split Payment:**
  - [ ] Multiple payment methods can be added
  - [ ] Amount validation works (sum must equal cart total)
  - [ ] Stripe payments are processed first
  - [ ] Purchase completes with all payments
  - [ ] Single receipt is generated

- [ ] **Error Handling:**
  - [ ] Empty cart shows error
  - [ ] Invalid session ID shows error
  - [ ] Validation errors display correctly
  - [ ] Network errors are handled gracefully

- [ ] **Edge Cases:**
  - [ ] Cart with discounts
  - [ ] Cart with tip
  - [ ] Cart with customer
  - [ ] Large cart (many items)

---

## Step 8: Troubleshooting

### Issue: "Cart is empty" error
**Solution:** Ensure `FFAppState().cart.cartData` has items before calling action.

### Issue: "Invalid POS session ID"
**Solution:** Verify `FFAppState().currentPosSession.id` is set and valid.

### Issue: Price conversion error
**Solution:** Check how prices are stored in `ProductStruct` and update conversion logic.

### Issue: "Authentication token is missing"
**Solution:** Ensure `FFAppState().authToken` is set after login.

### Issue: API URL incorrect
**Solution:** Verify `FFAppConstants().apiBaseUrl` is set correctly.

### Issue: Response parsing error
**Solution:** Check API response format matches expected structure. Add error logging.

---

## Step 9: Optional Enhancements

### 9.1 Add Loading State

1. Create app state variable: `isProcessingPurchase` (Boolean)
2. Set to `true` before calling action
3. Set to `false` after response
4. Show loading indicator while `true`

### 9.2 Add Retry Logic

For network errors, add retry mechanism:
```dart
int retryCount = 0;
while (retryCount < 3) {
  final result = await completePosPurchase(...);
  if (result['success'] == true) break;
  retryCount++;
  await Future.delayed(Duration(seconds: 1));
}
```

### 9.3 Add Receipt Preview

After successful purchase, show receipt preview before navigating:
- Display receipt number
- Show purchase summary
- Option to print receipt again

---

## Summary

The `completePosPurchase` custom action provides a complete solution for processing POS purchases in FlutterFlow. It:

✅ Handles cart data conversion automatically
✅ Supports single and split payments
✅ Returns structured responses for easy handling
✅ Validates input data
✅ Handles errors gracefully

Follow the steps above to integrate it into your purchase flow, and customize the code to match your exact data structures.

