# POS Purchase Integration Guide for FlutterFlow

This guide provides step-by-step instructions for integrating the complete purchase flow in your FlutterFlow POS frontend.

## Overview

The purchase flow supports:
- ✅ Single payment method (cash, card, etc.)
- ✅ Split payments (multiple payment methods in one purchase)
- ✅ Automatic receipt generation
- ✅ Automatic receipt printing (if configured)
- ✅ Cash drawer opening for cash payments
- ✅ Complete SAF-T compliance logging

## API Endpoints

### 1. Get Payment Methods

**Endpoint:** `GET /api/purchases/payment-methods`

**Query Parameters:**
- `pos_only` (optional, default: `true`) - Only return POS-suitable payment methods

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Kontant",
      "code": "cash",
      "provider": "cash",
      "enabled": true,
      "pos_suitable": true,
      "sort_order": 0,
      "background_color": "#4DEE8B60",
      "icon_color": "#ee8b60",
      "saf_t_payment_code": "12001",
      "saf_t_event_code": "13016"
    },
    {
      "id": 2,
      "name": "Kort",
      "code": "card_present",
      "provider": "stripe",
      "provider_method": "card_present",
      "enabled": true,
      "pos_suitable": true,
      "sort_order": 1,
      "background_color": "#4C4B39EF",
      "icon_color": "#272b3d",
      "saf_t_payment_code": "12002",
      "saf_t_event_code": "13017"
    }
  ]
}
```

### 2. Create Purchase (Single Payment)

**Endpoint:** `POST /api/purchases`

**Request Body:**
```json
{
  "pos_session_id": 123,
  "payment_method_code": "cash",
  "cart": {
    "items": [
      {
        "product_id": 456,
        "variant_id": 789,
        "quantity": 2,
        "unit_price": 5000,
        "discount_amount": 500,
        "tax_rate": 0.25,
        "tax_inclusive": true
      }
    ],
    "discounts": [
      {
        "type": "prosent",
        "amount": 1000,
        "percentage": 10,
        "reason": "Kundeklubb"
      }
    ],
    "tip_amount": 0,
    "customer_id": "cus_xxx",
    "subtotal": 9000,
    "total_discounts": 1000,
    "total_tax": 2000,
    "total": 10000,
    "currency": "nok"
  },
  "metadata": {
    "cashier_name": "John Doe",
    "device_id": "device_123"
  }
}
```

**For Stripe Payments, add to metadata:**
```json
{
  "metadata": {
    "payment_intent_id": "pi_xxx"
  }
}
```

**Response:**
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
  }
}
```

### 3. Create Purchase (Split Payment)

**Endpoint:** `POST /api/purchases`

**Request Body:**
```json
{
  "pos_session_id": 123,
  "payments": [
    {
      "payment_method_code": "cash",
      "amount": 5000,
      "metadata": {}
    },
    {
      "payment_method_code": "card_present",
      "amount": 5000,
      "metadata": {
        "payment_intent_id": "pi_xxx"
      }
    }
  ],
  "cart": {
    "items": [...],
    "total": 10000,
    "currency": "nok"
  },
  "metadata": {
    "cashier_name": "John Doe"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "charges": [
      {
        "id": 123,
        "amount": 5000,
        "payment_method": "cash",
        "paid_at": "2025-12-02T10:30:00Z"
      },
      {
        "id": 124,
        "amount": 5000,
        "payment_method": "card_present",
        "paid_at": "2025-12-02T10:30:00Z"
      }
    ],
    "receipt": {
      "id": 456,
      "receipt_number": "1-S-000001",
      "receipt_type": "sales"
    },
    "pos_event": {
      "id": 789,
      "event_code": "13012"
    }
  }
}
```

## FlutterFlow Implementation Steps

### Step 1: Create Payment Method Selection UI

1. **Create a Custom Action:** `getPaymentMethods`
   - Use API Call: `GET /api/purchases/payment-methods`
   - Store response in app state or local variable

2. **Create Payment Method Button Widget:**
   - Use Container with custom colors from API response
   - Display payment method name
   - Use `background_color` and `icon_color` from API
   - Example:
   ```dart
   Container(
     decoration: BoxDecoration(
       color: Color(int.parse(paymentMethod['background_color'].replaceFirst('#', '0xFF'))),
       borderRadius: BorderRadius.circular(8),
     ),
     child: Text(
       paymentMethod['name'],
       style: TextStyle(
         color: Color(int.parse(paymentMethod['icon_color'].replaceFirst('#', '0xFF'))),
       ),
     ),
   )
   ```

### Step 2: Implement Single Payment Flow

1. **For Cash Payments:**
   ```dart
   Future<void> completeCashPurchase() async {
     final response = await createPurchaseCall.call(
       posSessionId: currentSession.id,
       paymentMethodCode: 'cash',
       cart: {
         'items': cartItems.map((item) => {
           'product_id': item.productId,
           'quantity': item.quantity,
           'unit_price': item.unitPrice,
           'discount_amount': item.discountAmount,
           'tax_rate': 0.25,
           'tax_inclusive': true,
         }).toList(),
         'total': cart.total,
         'currency': 'nok',
         'subtotal': cart.subtotal,
         'total_discounts': cart.totalDiscounts,
         'total_tax': cart.totalTax,
       },
       metadata: {
         'cashier_name': currentUser.name,
         'device_id': currentDevice.id,
       },
     );

     if (response.jsonBody['success']) {
       // Show success message
       // Navigate to success screen
       // Receipt is automatically printed
     }
   }
   ```

2. **For Stripe Terminal Payments:**
   ```dart
   Future<void> completeCardPurchase() async {
     // Step 1: Create payment intent
     final piResponse = await createTerminalPaymentIntentCall.call(
       amount: cart.total,
       currency: 'nok',
       // ... other parameters
     );

     if (!piResponse.success) {
       // Handle error
       return;
     }

     final paymentIntentId = piResponse.jsonBody['id'];
     final clientSecret = piResponse.jsonBody['client_secret'];

     // Step 2: Collect payment using Stripe Terminal SDK
     try {
       await StripeTerminal.instance.collectPaymentMethod(
         paymentIntent: clientSecret,
       );
       
       await StripeTerminal.instance.confirmPaymentIntent(
         paymentIntent: clientSecret,
       );
     } catch (e) {
       // Handle payment failure
       return;
     }

     // Step 3: Complete purchase
     final response = await createPurchaseCall.call(
       posSessionId: currentSession.id,
       paymentMethodCode: 'card_present',
       cart: cart.toJson(),
       metadata: {
         'payment_intent_id': paymentIntentId,
         'cashier_name': currentUser.name,
       },
     );

     if (response.jsonBody['success']) {
       // Show success
       // Receipt is automatically printed
     }
   }
   ```

### Step 3: Implement Split Payment Flow

1. **Create Split Payment UI:**
   - Allow user to select multiple payment methods
   - Allow user to specify amount for each payment
   - Validate that amounts sum to cart total

2. **Complete Split Purchase:**
   ```dart
   Future<void> completeSplitPurchase(List<PaymentSplit> splits) async {
     // Validate splits
     final totalPaid = splits.fold(0, (sum, split) => sum + split.amount);
     if (totalPaid != cart.total) {
       showError('Payment amounts must equal cart total');
       return;
     }

     // Process Stripe payments first
     for (var split in splits) {
       if (split.paymentMethodCode == 'card_present') {
         // Create and confirm payment intent for this amount
         final piResponse = await createTerminalPaymentIntentCall.call(
           amount: split.amount,
           currency: 'nok',
         );
         
         await StripeTerminal.instance.collectPaymentMethod(
           paymentIntent: piResponse.jsonBody['client_secret'],
         );
         
         await StripeTerminal.instance.confirmPaymentIntent(
           paymentIntent: piResponse.jsonBody['client_secret'],
         );
         
         split.paymentIntentId = piResponse.jsonBody['id'];
       }
     }

     // Build payments array
     final payments = splits.map((split) => {
       'payment_method_code': split.paymentMethodCode,
       'amount': split.amount,
       'metadata': {
         if (split.paymentIntentId != null)
           'payment_intent_id': split.paymentIntentId,
       },
     }).toList();

     // Create purchase
     final response = await createPurchaseCall.call(
       posSessionId: currentSession.id,
       payments: payments,
       cart: cart.toJson(),
       metadata: {
         'cashier_name': currentUser.name,
       },
     );

     if (response.jsonBody['success']) {
       // Show success
       // Single receipt is generated for all payments
       // Receipt is automatically printed
     }
   }
   ```

### Step 4: Handle Responses

1. **Success Handling:**
   ```dart
   if (response.jsonBody['success']) {
     final receipt = response.jsonBody['data']['receipt'];
     
     // Show success message
     showSuccessDialog('Purchase completed!');
     
     // Display receipt number
     showReceiptNumber(receipt['receipt_number']);
     
     // Clear cart
     clearCart();
     
     // Navigate to home or next sale
     navigateToHome();
   }
   ```

2. **Error Handling:**
   ```dart
   if (!response.jsonBody['success']) {
     final message = response.jsonBody['message'];
     final errors = response.jsonBody['errors'] ?? {};
     
     // Display error message
     showErrorDialog(message);
     
     // Display validation errors if any
     if (errors.isNotEmpty) {
       for (var error in errors.entries) {
         showError('${error.key}: ${error.value}');
       }
     }
   }
   ```

### Step 5: Cart Data Structure

Ensure your cart data matches this structure:

```dart
{
  'items': [
    {
      'product_id': 123,
      'variant_id': 456,  // Optional
      'quantity': 2,
      'unit_price': 5000,  // in øre
      'discount_amount': 500,  // in øre
      'tax_rate': 0.25,
      'tax_inclusive': true,
    }
  ],
  'discounts': [
    {
      'type': 'prosent',  // or 'fixed'
      'amount': 1000,  // in øre
      'percentage': 10,  // if type is 'prosent'
      'reason': 'Kundeklubb',
    }
  ],
  'tip_amount': 0,  // in øre
  'customer_id': 'cus_xxx',  // Optional
  'customer_name': 'John Doe',  // Optional
  'subtotal': 9000,  // in øre
  'total_discounts': 1000,  // in øre
  'total_tax': 2000,  // in øre
  'total': 10000,  // in øre
  'currency': 'nok',
}
```

## Payment Flow Diagrams

### Single Payment Flow

```
[Cart Ready]
    ↓
[Select Payment Method]
    ↓
[Is Stripe?] → Yes → [Create Payment Intent] → [Collect Payment] → [Confirm Payment]
    ↓ No
[Create Purchase API Call]
    ↓
[Success?] → Yes → [Show Success] → [Clear Cart]
    ↓ No
[Show Error]
```

### Split Payment Flow

```
[Cart Ready]
    ↓
[Select Multiple Payment Methods]
    ↓
[Enter Amounts for Each Payment]
    ↓
[Validate: Sum = Cart Total]
    ↓
[For Each Stripe Payment:]
    → [Create Payment Intent]
    → [Collect Payment]
    → [Confirm Payment]
    ↓
[Create Purchase API Call with Payments Array]
    ↓
[Success?] → Yes → [Show Success] → [Clear Cart]
    ↓ No
[Show Error]
```

## Important Notes

1. **Amounts are in øre (minor units):**
   - 100 øre = 1 NOK
   - Always send amounts as integers

2. **Payment Intent for Stripe:**
   - Must be created and confirmed BEFORE calling purchase API
   - Include `payment_intent_id` in metadata

3. **Cash Drawer:**
   - Automatically opens for cash payments
   - No action required from frontend

4. **Receipt Printing:**
   - Automatically printed after successful purchase (if configured)
   - No action required from frontend

5. **Split Payments:**
   - All payments must be processed before calling purchase API
   - Single receipt is generated for all payments
   - Receipt shows breakdown of each payment method

6. **Error Handling:**
   - Always check `success` field in response
   - Display user-friendly error messages
   - Handle validation errors from `errors` object

## Testing Checklist

- [ ] Single cash payment completes successfully
- [ ] Single card payment completes successfully
- [ ] Split payment (cash + card) completes successfully
- [ ] Payment amounts validation works
- [ ] Error messages display correctly
- [ ] Receipt is generated (check receipt_number in response)
- [ ] Cart is cleared after successful purchase
- [ ] Success message displays correctly
- [ ] Cash drawer opens for cash payments (verify physically)
- [ ] Receipt prints automatically (verify physically)

## Troubleshooting

### Payment Intent Not Found
- Ensure payment intent is created and confirmed before purchase API call
- Check that `payment_intent_id` is included in metadata

### Payment Amounts Don't Match
- Verify all amounts are in øre (multiply by 100)
- Check that split payment amounts sum to cart total exactly

### Receipt Not Printing
- Check POS device configuration
- Verify printer is connected and online
- Check `POS_AUTO_PRINT_RECEIPTS` environment variable

### Cash Drawer Not Opening
- Check POS device configuration
- Verify cash drawer is connected
- Check device IP address and port settings


