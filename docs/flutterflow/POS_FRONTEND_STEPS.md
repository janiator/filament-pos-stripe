# POS Frontend Implementation Steps

## Quick Start Guide

Follow these steps to integrate the complete purchase flow in your FlutterFlow POS app.

## Step 1: Get Payment Methods

### Create API Call
1. **Action Name:** `getPaymentMethods`
2. **Method:** GET
3. **URL:** `/api/purchases/payment-methods`
4. **Headers:** Include authentication token
5. **Query Parameters:** 
   - `pos_only` (optional): `true` (default) to only get POS-suitable methods

### Display Payment Methods
1. Create a **Custom Widget** or use **List View** to display payment methods
2. For each payment method, create a button with:
   - **Background Color:** Use `background_color` from API (format: `#AARRGGBB`)
   - **Text/Icon Color:** Use `icon_color` from API (format: `#RRGGBB`)
   - **Label:** Use `name` from API
3. Store selected payment method in app state

**Example Color Conversion:**
```dart
// Convert #AARRGGBB to Flutter Color
Color backgroundColor = Color(int.parse(
  paymentMethod['background_color'].replaceFirst('#', '0xFF')
));

// Convert #RRGGBB to Flutter Color  
Color iconColor = Color(int.parse(
  paymentMethod['icon_color'].replaceFirst('#', '0xFF')
));
```

## Step 2: Single Payment Flow

### For Cash Payments

1. **Create API Call:** `createPurchase`
   - **Method:** POST
   - **URL:** `/api/purchases`
   - **Body:**
   ```json
   {
     "pos_session_id": <current_session_id>,
     "payment_method_code": "cash",
     "cart": {
       "items": [
         {
           "product_id": <product_id>,
           "quantity": <quantity>,
           "unit_price": <price_in_øre>,
           "discount_amount": <discount_in_øre>,
           "tax_rate": 0.25,
           "tax_inclusive": true
         }
       ],
       "total": <total_in_øre>,
       "currency": "nok",
       "subtotal": <subtotal_in_øre>,
       "total_discounts": <discounts_in_øre>,
       "total_tax": <tax_in_øre>
     },
     "metadata": {
       "cashier_name": "<user_name>",
       "device_id": "<device_id>"
     }
   }
   ```

2. **Handle Response:**
   - Check `success` field
   - If true: Show success message, clear cart, navigate to home
   - If false: Show error message from `message` field

### For Stripe Terminal Payments

1. **Step 1: Create Payment Intent**
   - Use existing Stripe Terminal integration
   - Create payment intent with amount from cart

2. **Step 2: Collect Payment**
   - Use Stripe Terminal SDK to collect payment
   - Confirm payment intent

3. **Step 3: Complete Purchase**
   - Use same `createPurchase` API call
   - Add `payment_intent_id` to metadata:
   ```json
   {
     "metadata": {
       "payment_intent_id": "<payment_intent_id>",
       "cashier_name": "<user_name>"
     }
   }
   ```

## Step 3: Split Payment Flow

### UI Requirements

1. **Create Split Payment Screen:**
   - Display cart total
   - Allow user to add multiple payment methods
   - For each payment method:
     - Show payment method selector
     - Show amount input field
     - Show running total
   - Validate that sum equals cart total

2. **Payment Split Data Structure:**
   ```dart
   class PaymentSplit {
     String paymentMethodCode;
     int amount; // in øre
     String? paymentIntentId; // for Stripe payments
   }
   ```

### Implementation Steps

1. **Collect Payment Splits:**
   ```dart
   List<PaymentSplit> splits = [];
   
   // User adds payment methods and amounts
   splits.add(PaymentSplit(
     paymentMethodCode: 'cash',
     amount: 5000, // 50.00 NOK
   ));
   
   splits.add(PaymentSplit(
     paymentMethodCode: 'card_present',
     amount: 5000,
   ));
   ```

2. **Validate Split Amounts:**
   ```dart
   int totalPaid = splits.fold(0, (sum, split) => sum + split.amount);
   if (totalPaid != cart.total) {
     showError('Payment amounts must equal cart total');
     return;
   }
   ```

3. **Process Stripe Payments First:**
   ```dart
   for (var split in splits) {
     if (split.paymentMethodCode == 'card_present') {
       // Create payment intent for this amount
       final piResponse = await createTerminalPaymentIntent(
         amount: split.amount,
         currency: 'nok',
       );
       
       // Collect payment
       await collectPayment(piResponse.clientSecret);
       
       // Confirm payment
       await confirmPayment(piResponse.clientSecret);
       
       // Store payment intent ID
       split.paymentIntentId = piResponse.id;
     }
   }
   ```

4. **Create Purchase with Split Payments:**
   ```dart
   final payments = splits.map((split) => {
     'payment_method_code': split.paymentMethodCode,
     'amount': split.amount,
     'metadata': {
       if (split.paymentIntentId != null)
         'payment_intent_id': split.paymentIntentId,
     },
   }).toList();
   
   final response = await createPurchaseCall.call(
     posSessionId: currentSession.id,
     payments: payments, // Use 'payments' instead of 'payment_method_code'
     cart: cart.toJson(),
     metadata: {
       'cashier_name': currentUser.name,
     },
   );
   ```

5. **Handle Response:**
   - Check `success` field
   - If split payment, response contains `charges` array instead of single `charge`
   - Single receipt is generated for all payments
   - Receipt shows breakdown of each payment method

## Step 4: Error Handling

### Validation Errors

```dart
if (!response.jsonBody['success']) {
  final message = response.jsonBody['message'];
  final errors = response.jsonBody['errors'] ?? {};
  
  // Show main error message
  showErrorDialog(message);
  
  // Show field-specific errors
  for (var error in errors.entries) {
    showError('${error.key}: ${error.value}');
  }
}
```

### Common Error Scenarios

1. **Payment amounts don't match:**
   - Error: "Payment amounts (X) must equal cart total (Y)"
   - Solution: Validate before API call

2. **Payment method not found:**
   - Error: "Payment method not found: <code>"
   - Solution: Refresh payment methods list

3. **Payment intent required:**
   - Error: "Payment intent ID is required for Stripe payments"
   - Solution: Ensure payment intent is created and confirmed

4. **POS session not open:**
   - Error: "POS session is not open"
   - Solution: Open a new POS session

## Step 5: Success Handling

### After Successful Purchase

1. **Show Success Message:**
   ```dart
   showSuccessDialog('Purchase completed!');
   ```

2. **Display Receipt Number:**
   ```dart
   final receiptNumber = response.jsonBody['data']['receipt']['receipt_number'];
   showReceiptNumber(receiptNumber);
   ```

3. **Clear Cart:**
   ```dart
   clearCart();
   ```

4. **Navigate:**
   - Navigate to home screen
   - Or start new sale

### Receipt Information

- Receipt is automatically generated
- Receipt is automatically printed (if configured)
- Receipt number is in response: `data.receipt.receipt_number`
- For split payments, receipt shows all payment methods

## Step 6: Cart Data Structure

Ensure your cart matches this structure:

```dart
{
  'items': [
    {
      'product_id': 123,           // Required
      'variant_id': 456,           // Optional
      'quantity': 2,               // Required, min: 1
      'unit_price': 5000,          // Required, in øre
      'discount_amount': 500,      // Optional, in øre
      'tax_rate': 0.25,            // Optional, default: 0.25
      'tax_inclusive': true,        // Optional, default: true
    }
  ],
  'discounts': [                   // Optional
    {
      'type': 'prosent',           // 'prosent', 'verdi', or 'ingen'
      'amount': 1000,              // in øre
      'percentage': 10,             // if type is 'prosent'
      'reason': 'Kundeklubb',      // Optional
    }
  ],
  'tip_amount': 0,                 // Optional, in øre
  'customer_id': 'cus_xxx',        // Optional
  'customer_name': 'John Doe',     // Optional
  'subtotal': 9000,                // Required, in øre
  'total_discounts': 1000,         // Optional, in øre
  'total_tax': 2000,               // Optional, in øre
  'total': 10000,                  // Required, in øre
  'currency': 'nok',               // Optional, default: 'nok'
}
```

## Important Notes

### Amounts
- **All amounts are in øre (minor units)**
- 100 øre = 1 NOK
- Always send as integers
- Example: 50.00 NOK = 5000 øre

### Payment Methods
- Use `code` field for `payment_method_code`
- Available codes: `cash`, `card_present`, `card`, `gift_token`, `credit_note`
- Check `enabled` and `pos_suitable` flags before showing

### Stripe Payments
- Payment intent must be created and confirmed BEFORE purchase API call
- Include `payment_intent_id` in metadata
- For split payments, create separate payment intent for each Stripe payment

### Automatic Features
- ✅ Receipt generation (automatic)
- ✅ Receipt printing (automatic, if configured)
- ✅ Cash drawer opening (automatic for cash payments)
- ✅ POS event logging (automatic)
- ✅ POS session totals update (automatic)

### Testing Checklist

- [ ] Payment methods load correctly
- [ ] Payment method colors display correctly
- [ ] Single cash payment works
- [ ] Single card payment works
- [ ] Split payment (cash + card) works
- [ ] Payment amount validation works
- [ ] Error messages display correctly
- [ ] Success message displays correctly
- [ ] Cart clears after purchase
- [ ] Receipt number displays correctly

## Example Complete Flow

```dart
// 1. Get payment methods
final paymentMethodsResponse = await getPaymentMethodsCall.call();
final paymentMethods = paymentMethodsResponse.jsonBody['data'];

// 2. User selects payment method(s)
final selectedMethod = paymentMethods.firstWhere((m) => m['code'] == 'cash');

// 3. For Stripe, create payment intent first
String? paymentIntentId;
if (selectedMethod['provider'] == 'stripe') {
  final piResponse = await createTerminalPaymentIntentCall.call(
    amount: cart.total,
    currency: 'nok',
  );
  paymentIntentId = piResponse.jsonBody['id'];
  
  // Collect and confirm payment
  await collectAndConfirmPayment(piResponse.jsonBody['client_secret']);
}

// 4. Complete purchase
final purchaseResponse = await createPurchaseCall.call(
  posSessionId: currentSession.id,
  paymentMethodCode: selectedMethod['code'],
  cart: {
    'items': cart.items.map((item) => {
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
    if (paymentIntentId != null) 'payment_intent_id': paymentIntentId,
    'cashier_name': currentUser.name,
    'device_id': currentDevice.id,
  },
);

// 5. Handle result
if (purchaseResponse.jsonBody['success']) {
  final receipt = purchaseResponse.jsonBody['data']['receipt'];
  showSuccessDialog('Purchase completed! Receipt: ${receipt['receipt_number']}');
  clearCart();
  navigateToHome();
} else {
  showErrorDialog(purchaseResponse.jsonBody['message']);
}
```

## Support

For detailed API documentation, see:
- `docs/flutterflow/POS_PURCHASE_INTEGRATION.md`
- API specification: `api-spec.yaml`

For backend implementation details, see:
- `docs/features/PURCHASE_FLOW.md`
- `docs/features/PURCHASE_NEXT_STEPS.md`


