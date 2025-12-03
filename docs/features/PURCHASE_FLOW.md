# POS Purchase Flow Documentation

## Overview

This document maps out the complete flow for completing a POS purchase using different payment methods. The system supports multiple payment providers including Stripe (card present, card not present), cash, and other payment services.

## Purchase Flow Diagram

```
[Cart Ready] 
    ↓
[Select Payment Method]
    ↓
[Validate Payment Method] → [Payment Method Not Available] → [Error]
    ↓
[Process Payment Based on Method]
    ├─→ [Cash] → [Record Cash Payment] → [Open Cash Drawer]
    ├─→ [Stripe Terminal] → [Create Payment Intent] → [Collect Payment] → [Capture Payment]
    ├─→ [Stripe Card] → [Create Payment Intent] → [Process Payment] → [Capture Payment]
    └─→ [Other] → [Custom Provider Logic]
    ↓
[Create ConnectedCharge]
    ↓
[Generate Receipt]
    ↓
[Log POS Event (13012 - Sales Receipt)]
    ↓
[Update POS Session Totals]
    ↓
[Return Purchase Result]
```

## Detailed Flow Steps

### 1. Cart Preparation

**Frontend State:**
- Cart contains items with quantities, prices, discounts, taxes
- Cart totals are calculated (subtotal, discounts, tax, total)
- Customer may be selected
- POS session is active

**Cart Structure:**
```dart
ShoppingCartStruct {
  cartId: String
  cartPosSessionId: String
  cartItems: List<CartItemsStruct>
  cartDiscounts: List<CartDiscountsStruct>
  cartTipAmount: int (in øre)
  cartCustomerId: String?
  cartCustomerName: String?
  cartTotalCartPrice: int (in øre)
  // ... other totals
}
```

### 2. Payment Method Selection

**API Endpoint:** `GET /api/stores/{store}/payment-methods`

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
      "sort_order": 0,
      "saf_t_payment_code": "12001",
      "saf_t_event_code": "13016"
    },
    {
      "id": 2,
      "name": "Kort",
      "code": "card",
      "provider": "stripe",
      "provider_method": "card_present",
      "enabled": true,
      "sort_order": 1,
      "saf_t_payment_code": "12002",
      "saf_t_event_code": "13017"
    }
  ]
}
```

**Frontend Action:**
- User selects payment method from available methods
- System validates method is enabled for current store
- System prepares payment method code for purchase request

### 3. Purchase Request

**API Endpoint:** `POST /api/purchases`

**Request Body:**
```json
{
  "pos_session_id": "123",
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
    "total": 10000
  },
  "metadata": {
    "cashier_name": "John Doe",
    "device_id": "device_123"
  }
}
```

### 4. Payment Processing

The system processes payment based on the selected method:

#### 4.1 Cash Payment

**Flow:**
1. Validate amount (must match cart total)
2. Create `ConnectedCharge` with:
   - `payment_method`: "cash"
   - `status`: "succeeded" (immediate)
   - `paid`: true
   - `paid_at`: now()
3. Log POS Event:
   - Event Code: `13016` (Cash payment)
   - Transaction Code: `11001` (Cash sale)
4. Open cash drawer (if configured)
5. Return success

**No external API calls required.**

#### 4.2 Stripe Terminal Payment (Card Present)

**Flow:**
1. Create Stripe Payment Intent:
   - Endpoint: `POST /api/stores/{store}/terminal/payment-intents`
   - Amount: cart total (in minor units)
   - Payment method types: `["card_present"]`
   - Capture method: `manual`
2. Frontend collects payment:
   - Use Stripe Terminal SDK
   - `retrievePaymentIntent(clientSecret)`
   - `collectPaymentMethod(paymentIntent)`
   - `confirmPaymentIntent(paymentIntent)`
3. Backend captures payment:
   - Webhook or direct capture call
   - Create `ConnectedCharge` from Payment Intent
4. Log POS Event:
   - Event Code: `13017` (Card payment)
   - Transaction Code: `11002` (Credit sale)
5. Return success

#### 4.3 Stripe Card Payment (Card Not Present)

**Flow:**
1. Create Stripe Payment Intent:
   - Endpoint: `POST /api/stores/{store}/payment-intents`
   - Amount: cart total
   - Payment method types: `["card"]`
   - Capture method: `automatic` or `manual`
2. Frontend processes payment:
   - Use Stripe.js or mobile SDK
   - Confirm payment with payment method
3. Backend handles webhook:
   - `payment_intent.succeeded` event
   - Create `ConnectedCharge`
4. Log POS Event:
   - Event Code: `13017` (Card payment)
   - Transaction Code: `11002` (Credit sale)
5. Return success

#### 4.4 Other Payment Providers

**Flow:**
1. Custom provider logic (to be implemented)
2. Create `ConnectedCharge` with provider-specific data
3. Log POS Event with appropriate codes
4. Return success

### 5. Charge Creation

**ConnectedCharge Fields:**
```php
[
  'stripe_account_id' => $store->stripe_account_id,
  'pos_session_id' => $posSession->id,
  'amount' => $cartTotal, // in minor units (øre)
  'currency' => 'nok',
  'status' => 'succeeded',
  'payment_method' => $paymentMethodCode,
  'payment_code' => $safTPaymentCode,
  'transaction_code' => $safTTransactionCode,
  'metadata' => [
    'items' => $cartItems,
    'discounts' => $cartDiscounts,
    'customer_id' => $customerId,
    'cashier_name' => $cashierName,
    'device_id' => $deviceId,
  ],
  'paid' => true,
  'paid_at' => now(),
]
```

### 6. Receipt Generation

**Automatic Process:**
1. `ReceiptGenerationService::generateSalesReceipt()` is called
2. Receipt number is generated (sequential per store)
3. Receipt data is stored in `receipts` table
4. XML template is rendered (for printing)
5. Receipt is linked to charge and POS session

**Receipt Types:**
- `sales`: Normal sales receipt
- `return`: Return/refund receipt
- `copy`: Copy of receipt
- `steb`: STEB receipt
- `provisional`: Provisional receipt
- `training`: Training receipt
- `delivery`: Delivery receipt

### 7. POS Event Logging

**Event Details:**
- **Event Code:** `13012` (Sales receipt)
- **Transaction Code:** Based on payment method
  - Cash: `11001` (Cash sale)
  - Card/Mobile: `11002` (Credit sale)
- **Payment Code:** From payment method configuration
- **Event Data:**
  - Charge ID
  - Receipt ID
  - Amount
  - Payment method
  - Customer (if applicable)

**Kassasystemforskriften Compliance:**
- All transactions are logged
- Events are immutable
- Complete audit trail maintained

### 8. POS Session Update

**Automatic Updates:**
- Session transaction count incremented
- Session total amount updated
- Expected cash updated (if cash payment)
- Session metadata updated with latest transaction

### 9. Response to Frontend

**Success Response:**
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
      "paid_at": "2025-12-01T10:30:00Z"
    },
    "receipt": {
      "id": 456,
      "receipt_number": "1-S-000001",
      "receipt_type": "sales",
      "receipt_data": { ... }
    },
    "pos_event": {
      "id": 789,
      "event_code": "13012",
      "transaction_code": "11001"
    }
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Payment method not available",
  "errors": {
    "payment_method": ["The selected payment method is not enabled for this store."]
  }
}
```

## Payment Method Configuration

### Default Payment Methods

When a store is created, default payment methods should be seeded:

1. **Cash (Kontant)**
   - Code: `cash`
   - Provider: `cash`
   - SAF-T Payment Code: `12001`
   - SAF-T Event Code: `13016`

2. **Card Present (Kort - Terminal)**
   - Code: `card_present`
   - Provider: `stripe`
   - Provider Method: `card_present`
   - SAF-T Payment Code: `12002`
   - SAF-T Event Code: `13017`

3. **Card Not Present (Kort - Online)**
   - Code: `card`
   - Provider: `stripe`
   - Provider Method: `card`
   - SAF-T Payment Code: `12002`
   - SAF-T Event Code: `13017`

4. **Mobile Payment (Mobil)**
   - Code: `mobile`
   - Provider: `stripe` or `other`
   - SAF-T Payment Code: `12011`
   - SAF-T Event Code: `13018`

## Error Handling

### Payment Method Errors

- **Method not enabled:** Return 422 with error message
- **Method not found:** Return 404
- **Method not available for store:** Return 403

### Payment Processing Errors

- **Stripe errors:** Return error from Stripe API
- **Cash amount mismatch:** Return 422 validation error
- **Session not found:** Return 404
- **Session not open:** Return 422

### Receipt Generation Errors

- **Store not found:** Log error, continue without receipt
- **Template not found:** Use default template
- **Generation failure:** Log error, return charge without receipt

## Security Considerations

1. **Authentication:** All endpoints require Sanctum authentication
2. **Authorization:** Users must belong to store or be admin
3. **Validation:** All input is validated
4. **Audit Trail:** All actions are logged
5. **Immutability:** Charges and events cannot be modified after creation

## Testing Checklist

- [ ] Cash payment completes successfully
- [ ] Stripe Terminal payment completes successfully
- [ ] Stripe Card payment completes successfully
- [ ] Receipt is generated for all payment types
- [ ] POS events are logged correctly
- [ ] SAF-T codes are assigned correctly
- [ ] Cash drawer opens for cash payments
- [ ] POS session totals are updated
- [ ] Error handling works for all error cases
- [ ] Payment method validation works
- [ ] Disabled payment methods are rejected

