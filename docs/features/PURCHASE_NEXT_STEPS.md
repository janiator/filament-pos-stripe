# Purchase Flow - Next Steps Implementation Guide

## Overview

This document outlines the remaining steps to complete the POS purchase flow implementation, including split payments, receipt printing, and proper logging.

## Current Status

### ✅ Already Implemented

1. **Payment Method Management**
   - PaymentMethod model with store-scoped configuration
   - Filament admin interface for managing payment methods
   - Default payment methods seeded (Cash, Card, Gift Card, Credit Note)
   - Color customization for payment method buttons

2. **Basic Purchase Flow**
   - `POST /api/purchases` endpoint
   - `PurchaseService` with support for:
     - Cash payments (immediate)
     - Stripe payments (via payment intent)
     - Other payment providers (placeholder)
   - Receipt generation via `ReceiptGenerationService`
   - POS event logging (13012 - Sales receipt, 13016-13019 - Payment events)

3. **SAF-T Compliance**
   - Transaction codes (11001, 11002)
   - Payment codes (12001, 12002, etc.)
   - Event codes (13012, 13016-13019)
   - Complete audit trail in `PosEvent` model

## Next Steps

### 1. Split Payment Support ⚠️ **HIGH PRIORITY**

**Problem:** Currently, the system only supports single payment method per purchase. Need to support split payments (e.g., part cash, part card).

**Implementation Plan:**

#### 1.1 Update API Request Schema

**New Request Format:**
```json
{
  "pos_session_id": 123,
  "payments": [
    {
      "payment_method_code": "cash",
      "amount": 5000,  // in øre
      "metadata": {
        "cashier_name": "John Doe"
      }
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
    "total": 10000
  },
  "metadata": {
    "device_id": "device_123"
  }
}
```

**Validation Rules:**
- Sum of all payment amounts must equal cart total
- At least one payment required
- Each payment must have valid payment method
- For Stripe payments, payment_intent_id required

#### 1.2 Update PurchaseService

**New Method:** `processSplitPurchase()`

```php
public function processSplitPurchase(
    PosSession $posSession,
    array $payments,  // Array of payment data
    array $cartData,
    array $metadata = []
): array {
    // Validate payment amounts sum to cart total
    $totalPaid = array_sum(array_column($payments, 'amount'));
    if ($totalPaid !== $cartData['total']) {
        throw new \Exception('Payment amounts do not match cart total');
    }

    // Process each payment
    $charges = [];
    foreach ($payments as $paymentData) {
        $paymentMethod = PaymentMethod::where('store_id', $posSession->store_id)
            ->where('code', $paymentData['payment_method_code'])
            ->firstOrFail();

        $charge = $this->processPayment(
            $posSession,
            $paymentMethod,
            $paymentData['amount'],
            $cartData['currency'] ?? 'nok',
            $cartData,
            array_merge($metadata, $paymentData['metadata'] ?? [])
        );

        $charges[] = $charge;
    }

    // Generate single receipt for all payments
    $receipt = $this->receiptService->generateSalesReceipt(
        $charges[0],  // Primary charge
        $posSession,
        $charges  // All charges for split payment info
    );

    // Log sales receipt event
    $posEvent = $this->logSalesReceiptEvent(
        $posSession,
        $charges[0],
        $receipt,
        $payments  // Multiple payment methods
    );

    return [
        'charges' => $charges,
        'receipt' => $receipt,
        'pos_event' => $posEvent,
    ];
}
```

**Refactor Existing Method:**
- Rename `processPurchase()` to `processSinglePurchase()`
- Create new `processPayment()` method (extracted from current logic)
- Update controller to handle both single and split payments

#### 1.3 Update Receipt Generation

**Update `ReceiptGenerationService::generateSalesReceipt()`:**
- Accept array of charges for split payments
- Include split payment breakdown in receipt data
- Show each payment method and amount on receipt

**Receipt Data Structure:**
```php
'receipt_data' => [
    'items' => [...],
    'totals' => [...],
    'payments' => [
        [
            'method' => 'cash',
            'amount' => 5000,
            'payment_code' => '12001',
        ],
        [
            'method' => 'card_present',
            'amount' => 5000,
            'payment_code' => '12002',
        ],
    ],
]
```

#### 1.4 Update POS Event Logging

**For Split Payments:**
- Log one sales receipt event (13012) for the entire purchase
- Log individual payment events (13016-13019) for each payment method
- Link all events to the same receipt

**Event Structure:**
```php
// Sales receipt event
PosEvent::create([
    'event_code' => '13012',
    'event_data' => [
        'receipt_id' => $receipt->id,
        'total_amount' => $cartTotal,
        'payment_count' => count($charges),
        'charges' => $charges->pluck('id')->toArray(),
    ],
]);

// Individual payment events
foreach ($charges as $charge) {
    $this->logPaymentEvent($posSession, $charge, $paymentMethod, $eventCode);
}
```

### 2. Cash Drawer Integration ⚠️ **MEDIUM PRIORITY**

**Problem:** Cash payments should trigger cash drawer opening.

**Implementation Plan:**

#### 2.1 Create Cash Drawer Service

**New Service:** `App\Services\CashDrawerService`

```php
class CashDrawerService
{
    public function openCashDrawer(PosSession $posSession, int $amount): void
    {
        // Get POS device
        $device = $posSession->posDevice;
        
        if (!$device) {
            Log::warning('No POS device found for cash drawer opening');
            return;
        }

        // Log cash drawer open event
        PosEvent::create([
            'store_id' => $posSession->store_id,
            'pos_session_id' => $posSession->id,
            'pos_device_id' => $device->id,
            'user_id' => $posSession->user_id,
            'event_code' => '13020', // Cash drawer open
            'event_type' => 'system',
            'description' => 'Cash drawer opened',
            'event_data' => [
                'amount' => $amount,
                'trigger' => 'cash_payment',
            ],
            'occurred_at' => now(),
        ]);

        // Send command to device (implementation depends on device type)
        // For Epson printers: ESC/POS command
        // For other devices: API call or webhook
        $this->sendOpenDrawerCommand($device, $amount);
    }

    protected function sendOpenDrawerCommand(PosDevice $device, int $amount): void
    {
        // Implementation depends on device type
        // Example for Epson printers:
        if ($device->device_type === 'epson_printer') {
            // Send ESC/POS command via network or USB
            // Command: ESC p 0 25 250 (opens drawer for 250ms)
        }
    }
}
```

#### 2.2 Integrate with PurchaseService

**Update `processCashPayment()`:**
```php
protected function processCashPayment(...): ConnectedCharge
{
    // ... existing charge creation ...

    // Open cash drawer
    $cashDrawerService = app(CashDrawerService::class);
    $cashDrawerService->openCashDrawer($posSession, $amount);

    return $charge;
}
```

### 3. Receipt Printing Integration ⚠️ **MEDIUM PRIORITY**

**Problem:** Receipts are generated but not automatically printed.

**Implementation Plan:**

#### 3.1 Create Receipt Print Service

**New Service:** `App\Services\ReceiptPrintService`

```php
class ReceiptPrintService
{
    protected ReceiptGenerationService $receiptService;

    public function __construct(ReceiptGenerationService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    public function printReceipt(Receipt $receipt, PosSession $posSession): bool
    {
        $device = $posSession->posDevice;
        
        if (!$device) {
            Log::warning('No POS device found for receipt printing');
            return false;
        }

        // Generate receipt XML/format
        $receiptData = $this->receiptService->generateReceiptData($receipt);
        
        // Send to printer
        $success = $this->sendToPrinter($device, $receiptData);
        
        if ($success) {
            // Mark receipt as printed
            $receipt->update([
                'printed' => true,
                'printed_at' => now(),
            ]);

            // Log print event
            PosEvent::create([
                'store_id' => $posSession->store_id,
                'pos_session_id' => $posSession->id,
                'pos_device_id' => $device->id,
                'user_id' => $posSession->user_id,
                'related_receipt_id' => $receipt->id,
                'event_code' => '13021', // Receipt printed
                'event_type' => 'system',
                'description' => 'Receipt printed',
                'event_data' => [
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                ],
                'occurred_at' => now(),
            ]);
        }

        return $success;
    }

    protected function sendToPrinter(PosDevice $device, array $receiptData): bool
    {
        // Implementation depends on printer type
        // For Epson: Send XML via network
        // For other printers: Use appropriate protocol
        return true;
    }
}
```

#### 3.2 Auto-Print After Purchase

**Update `PurchaseService::processPurchase()`:**
```php
// After receipt generation
$receipt = $this->receiptService->generateSalesReceipt($charge, $posSession);

// Auto-print receipt (if configured)
if (config('pos.auto_print_receipts', true)) {
    $printService = app(ReceiptPrintService::class);
    $printService->printReceipt($receipt, $posSession);
}
```

### 4. Enhanced Validation & Error Handling ⚠️ **MEDIUM PRIORITY**

**Improvements Needed:**

#### 4.1 Cart Validation

**Add validation for:**
- Cart total matches sum of item prices minus discounts plus tax
- All items belong to the store
- Quantities are positive
- Prices are non-negative
- Discounts don't exceed item prices

#### 4.2 Payment Validation

**Add validation for:**
- Payment method is enabled for store
- Payment amounts are positive
- For split payments: amounts sum to cart total
- For Stripe: payment intent exists and is succeeded
- For cash: amount matches cart total (if single payment)

#### 4.3 Better Error Messages

**Return structured errors:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "payments.0.amount": ["Payment amount must be positive"],
    "payments": ["Payment amounts must sum to cart total"]
  },
  "error_code": "VALIDATION_ERROR"
}
```

### 5. POS Session Updates ⚠️ **LOW PRIORITY**

**Update session totals after purchase:**

```php
// In PurchaseService, after successful purchase
$posSession->increment('transaction_count');
$posSession->increment('total_amount', $totalAmount);

// For cash payments, update expected cash
if ($paymentMethod->isCash()) {
    $posSession->increment('expected_cash', $amount);
}

$posSession->save();
```

### 6. Frontend Integration Guide ⚠️ **HIGH PRIORITY**

**Create Flutter/Dart integration examples:**

#### 6.1 Payment Method Selection

```dart
// Get available payment methods
final response = await getPaymentMethodsCall.call();
final paymentMethods = response.jsonBody['data'] as List;

// Display payment method buttons with colors
for (var method in paymentMethods) {
  PaymentMethodButton(
    name: method['name'],
    code: method['code'],
    backgroundColor: Color(int.parse(method['background_color'].replaceFirst('#', '0xFF'))),
    iconColor: Color(int.parse(method['icon_color'].replaceFirst('#', '0xFF'))),
    onTap: () => selectPaymentMethod(method),
  );
}
```

#### 6.2 Single Payment Flow

```dart
Future<void> completePurchase(String paymentMethodCode) async {
  // For Stripe payments, create payment intent first
  String? paymentIntentId;
  if (paymentMethodCode == 'card_present') {
    paymentIntentId = await createAndConfirmTerminalPayment();
  }

  // Create purchase
  final response = await createPurchaseCall.call(
    posSessionId: currentSession.id,
    paymentMethodCode: paymentMethodCode,
    cart: cart.toJson(),
    metadata: {
      if (paymentIntentId != null) 'payment_intent_id': paymentIntentId,
    },
  );

  if (response.jsonBody['success']) {
    // Show success, print receipt
    await printReceipt(response.jsonBody['data']['receipt']);
  }
}
```

#### 6.3 Split Payment Flow

```dart
Future<void> completeSplitPurchase(List<PaymentSplit> splits) async {
  // Validate splits sum to cart total
  final totalPaid = splits.fold(0, (sum, split) => sum + split.amount);
  if (totalPaid != cart.total) {
    throw Exception('Payment amounts must equal cart total');
  }

  // Process Stripe payments first
  for (var split in splits) {
    if (split.paymentMethodCode == 'card_present') {
      split.paymentIntentId = await createAndConfirmTerminalPayment(split.amount);
    }
  }

  // Create purchase with split payments
  final response = await createPurchaseCall.call(
    posSessionId: currentSession.id,
    payments: splits.map((s) => s.toJson()).toList(),
    cart: cart.toJson(),
  );

  if (response.jsonBody['success']) {
    // Show success, print receipt
    await printReceipt(response.jsonBody['data']['receipt']);
  }
}
```

## Implementation Priority

1. **HIGH PRIORITY:**
   - ✅ Split payment support
   - ✅ Frontend integration guide
   - ✅ Enhanced validation

2. **MEDIUM PRIORITY:**
   - Cash drawer integration
   - Receipt printing integration
   - Better error handling

3. **LOW PRIORITY:**
   - POS session auto-updates
   - Additional logging
   - Performance optimizations

## Testing Checklist

### Single Payment Tests
- [ ] Cash payment completes successfully
- [ ] Stripe Terminal payment completes successfully
- [ ] Stripe Card payment completes successfully
- [ ] Receipt is generated correctly
- [ ] POS events are logged correctly
- [ ] SAF-T codes are assigned correctly

### Split Payment Tests
- [ ] Cash + Card split payment completes
- [ ] Multiple card payments (if supported)
- [ ] Receipt shows split payment breakdown
- [ ] All payment events are logged
- [ ] Validation rejects invalid split amounts

### Error Handling Tests
- [ ] Invalid payment method rejected
- [ ] Disabled payment method rejected
- [ ] Payment amount mismatch rejected
- [ ] Missing payment intent for Stripe rejected
- [ ] Closed POS session rejected

### Integration Tests
- [ ] Cash drawer opens for cash payments
- [ ] Receipt prints automatically (if configured)
- [ ] POS session totals update correctly
- [ ] Multiple purchases in same session work

## API Endpoints Summary

### Existing Endpoints
- `GET /api/purchases/payment-methods` - Get available payment methods
- `POST /api/purchases` - Create purchase (single payment)

### New Endpoints Needed
- `POST /api/purchases/split` - Create purchase with split payments (or extend existing endpoint)
- `POST /api/receipts/{id}/print` - Manually print receipt
- `POST /api/receipts/{id}/reprint` - Reprint receipt

## Database Considerations

### No New Tables Required
- Split payments can be handled with multiple `ConnectedCharge` records
- All linked to same `Receipt` record
- Events logged separately for each payment

### Potential Enhancements
- Add `parent_charge_id` to `ConnectedCharge` for split payment grouping
- Add `split_payment_index` to track order of payments
- Add `is_split_payment` flag to `Receipt` model

## Security Considerations

1. **Payment Validation:**
   - Always validate payment amounts server-side
   - Never trust client-calculated totals
   - Verify payment intents are succeeded before creating charges

2. **Authorization:**
   - Verify user has access to POS session
   - Verify payment methods belong to store
   - Verify cart items belong to store

3. **Audit Trail:**
   - All payments logged in PosEvent
   - Receipts are immutable
   - Charges cannot be modified after creation

## Next Actions

1. **Immediate:**
   - Implement split payment support in `PurchaseService`
   - Update API endpoint to accept split payments
   - Update validation rules

2. **Short-term:**
   - Create cash drawer service
   - Create receipt print service
   - Update frontend integration

3. **Long-term:**
   - Add support for partial payments
   - Add support for payment plans
   - Add support for gift card redemption
   - Add support for loyalty point redemption


