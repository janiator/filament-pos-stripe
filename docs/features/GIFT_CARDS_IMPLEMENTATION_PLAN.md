# Gift Cards Implementation Plan

## Overview

This document outlines the complete implementation plan for gift card functionality in the POS system. Gift cards allow customers to purchase prepaid cards that can be redeemed for future purchases.

## Requirements

### Functional Requirements

1. **Gift Card Purchase**
   - Customers can purchase gift cards at the POS
   - Gift cards can be purchased for any amount (with minimum/maximum limits)
   - Gift cards are activated immediately upon purchase
   - Receipt is generated for gift card purchase

2. **Gift Card Redemption**
   - Gift cards can be used as a payment method during checkout
   - Partial redemption allowed (remaining balance tracked)
   - Multiple gift cards can be used in split payment scenarios
   - Balance validation before redemption

3. **Gift Card Management**
   - View all gift cards (purchased and redeemed)
   - Search by card number/code
   - View transaction history per gift card
   - Manual balance adjustments (with audit trail)
   - Void/refund gift cards

4. **Gift Card Lifecycle**
   - Active: Available for use
   - Redeemed: Fully used (balance = 0)
   - Expired: Past expiration date (if applicable)
   - Voided: Manually voided by admin
   - Refunded: Original purchase refunded

### Compliance Requirements

1. **Kassasystemforskriften Compliance**
   - All gift card transactions must be logged in POS events
   - Receipts must be generated for gift card purchases
   - Receipts must be generated for gift card redemptions
   - Audit trail for all gift card operations

2. **SAF-T Compliance**
   - Gift card purchases: Transaction code 11001 (Sale)
   - Gift card redemptions: Payment code 12003 (Gift Card)
   - Proper article group codes for gift card products

### Technical Requirements

1. **Security**
   - Unique gift card codes (non-guessable)
   - Secure code generation
   - Balance validation with race condition protection
   - Transaction-level locking for balance updates

2. **Performance**
   - Fast lookup by card code
   - Indexed database queries
   - Efficient balance checking

3. **Integration**
   - FlutterFlow frontend support
   - Filament admin interface
   - API endpoints for all operations

---

## Database Schema

### 1. Gift Cards Table

```php
Schema::create('gift_cards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->string('code', 32)->unique(); // Unique gift card code
    $table->string('pin', 8)->nullable(); // Optional PIN for security
    $table->integer('initial_amount'); // Initial amount in øre
    $table->integer('balance'); // Current balance in øre
    $table->integer('amount_redeemed')->default(0); // Total redeemed in øre
    $table->string('currency', 3)->default('nok');
    $table->enum('status', ['active', 'redeemed', 'expired', 'voided', 'refunded'])->default('active');
    $table->dateTime('purchased_at'); // When gift card was purchased
    $table->dateTime('expires_at')->nullable(); // Optional expiration date
    $table->dateTime('last_used_at')->nullable(); // Last redemption date
    $table->foreignId('purchase_charge_id')->nullable()->constrained('connected_charges')->onDelete('set null'); // Original purchase charge
    $table->foreignId('purchased_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // User who sold the gift card
    $table->foreignId('customer_id')->nullable()->constrained('connected_customers')->onDelete('set null'); // Optional: Customer who purchased
    $table->text('notes')->nullable(); // Admin notes
    $table->json('metadata')->nullable(); // Additional metadata
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['store_id', 'status']);
    $table->index(['code']);
    $table->index(['purchased_at']);
    $table->index(['expires_at']);
});
```

### 2. Gift Card Transactions Table

```php
Schema::create('gift_card_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('gift_card_id')->constrained()->onDelete('cascade');
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['purchase', 'redemption', 'refund', 'adjustment', 'void']);
    $table->integer('amount'); // Transaction amount in øre (positive for purchase, negative for redemption)
    $table->integer('balance_before'); // Balance before transaction
    $table->integer('balance_after'); // Balance after transaction
    $table->foreignId('charge_id')->nullable()->constrained('connected_charges')->onDelete('set null'); // Related charge
    $table->foreignId('pos_session_id')->nullable()->constrained()->onDelete('set null');
    $table->foreignId('pos_event_id')->nullable()->constrained()->onDelete('set null');
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // User who performed the transaction
    $table->text('notes')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    // Indexes
    $table->index(['gift_card_id', 'created_at']);
    $table->index(['store_id', 'type', 'created_at']);
    $table->index(['charge_id']);
});
```

---

## Models

### 1. GiftCard Model

**Location:** `app/Models/GiftCard.php`

**Key Features:**
- Relationships: store, purchaseCharge, purchasedByUser, customer, transactions
- Scopes: active, redeemed, expired, voided
- Methods:
  - `isValid()` - Check if card can be used
  - `canRedeem(int $amount)` - Check if amount can be redeemed
  - `redeem(int $amount, ConnectedCharge $charge, PosSession $session)` - Redeem amount
  - `refund()` - Refund the gift card
  - `void()` - Void the gift card
  - `generateCode()` - Generate unique code
  - `getFormattedBalance()` - Format balance for display

### 2. GiftCardTransaction Model

**Location:** `app/Models/GiftCardTransaction.php`

**Key Features:**
- Relationships: giftCard, store, charge, posSession, posEvent, user
- Scopes: purchases, redemptions, refunds, adjustments
- Methods:
  - `getFormattedAmount()` - Format amount for display

---

## Services

### 1. GiftCardService

**Location:** `app/Services/GiftCardService.php`

**Responsibilities:**
- Generate unique gift card codes
- Purchase gift cards (create card + charge)
- Redeem gift cards (with transaction locking)
- Refund gift cards
- Void gift cards
- Balance adjustments
- Validation logic

**Key Methods:**
```php
public function purchaseGiftCard(
    PosSession $posSession,
    PaymentMethod $paymentMethod,
    int $amount,
    array $options = []
): GiftCard

public function redeemGiftCard(
    string $code,
    int $amount,
    ConnectedCharge $charge,
    PosSession $posSession
): GiftCardTransaction

public function validateGiftCard(string $code, int $amount): bool

public function getGiftCardByCode(string $code): ?GiftCard

public function refundGiftCard(GiftCard $giftCard, string $reason): GiftCardTransaction

public function voidGiftCard(GiftCard $giftCard, string $reason): void

public function adjustBalance(GiftCard $giftCard, int $amount, string $reason): GiftCardTransaction
```

### 2. Update PurchaseService

**Location:** `app/Services/PurchaseService.php`

**Changes:**
- Add gift card redemption support in `processOtherPayment()`
- Handle gift card as payment method in split payments
- Support gift card + other payment methods

---

## API Endpoints

### 1. Gift Card Purchase

**POST** `/api/gift-cards/purchase`

**Request:**
```json
{
  "pos_session_id": 123,
  "payment_method_code": "cash",
  "amount": 50000,  // 500.00 NOK in øre
  "currency": "nok",
  "expires_at": "2026-12-31T23:59:59Z",  // Optional
  "customer_id": 456,  // Optional
  "notes": "Birthday gift",  // Optional
  "metadata": {}  // Optional
}
```

**Response:**
```json
{
  "gift_card": {
    "id": 789,
    "code": "GC-ABC123XYZ456",
    "pin": "1234",  // If PIN enabled
    "initial_amount": 50000,
    "balance": 50000,
    "currency": "nok",
    "status": "active",
    "purchased_at": "2025-12-16T10:30:00Z",
    "expires_at": "2026-12-31T23:59:59Z"
  },
  "charge": {
    "id": 101,
    "amount": 50000,
    "status": "paid"
  },
  "receipt": {
    "id": 202,
    "receipt_number": "R-001234"
  }
}
```

### 2. Gift Card Lookup

**GET** `/api/gift-cards/{code}`

**Response:**
```json
{
  "id": 789,
  "code": "GC-ABC123XYZ456",
  "balance": 50000,
  "currency": "nok",
  "status": "active",
  "purchased_at": "2025-12-16T10:30:00Z",
  "expires_at": "2026-12-31T23:59:59Z",
  "can_redeem": true,
  "formatted_balance": "500.00 NOK"
}
```

### 3. Gift Card Validation

**POST** `/api/gift-cards/validate`

**Request:**
```json
{
  "code": "GC-ABC123XYZ456",
  "pin": "1234",  // Optional, if PIN enabled
  "amount": 25000  // Amount to redeem
}
```

**Response:**
```json
{
  "valid": true,
  "gift_card": {
    "id": 789,
    "code": "GC-ABC123XYZ456",
    "balance": 50000,
    "can_redeem_amount": true
  }
}
```

### 4. Gift Card Transactions

**GET** `/api/gift-cards/{id}/transactions`

**Query Parameters:**
- `type` - Filter by type (purchase, redemption, refund, adjustment, void)
- `limit` - Number of results (default: 50)
- `offset` - Pagination offset

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "purchase",
      "amount": 50000,
      "balance_before": 0,
      "balance_after": 50000,
      "created_at": "2025-12-16T10:30:00Z",
      "charge": {
        "id": 101,
        "receipt_number": "R-001234"
      }
    },
    {
      "id": 2,
      "type": "redemption",
      "amount": -25000,
      "balance_before": 50000,
      "balance_after": 25000,
      "created_at": "2025-12-20T14:15:00Z",
      "charge": {
        "id": 102,
        "receipt_number": "R-001235"
      }
    }
  ],
  "total": 2
}
```

### 5. Gift Card List (Admin)

**GET** `/api/gift-cards`

**Query Parameters:**
- `status` - Filter by status
- `store_id` - Filter by store
- `search` - Search by code
- `date_from` - Filter by purchase date
- `date_to` - Filter by purchase date

**Response:**
```json
{
  "data": [
    {
      "id": 789,
      "code": "GC-ABC123XYZ456",
      "initial_amount": 50000,
      "balance": 25000,
      "status": "active",
      "purchased_at": "2025-12-16T10:30:00Z",
      "last_used_at": "2025-12-20T14:15:00Z"
    }
  ],
  "total": 1
}
```

### 6. Gift Card Refund

**POST** `/api/gift-cards/{id}/refund`

**Request:**
```json
{
  "reason": "Customer requested refund",
  "refund_payment_method_code": "cash"  // How to refund (cash, card, etc.)
}
```

### 7. Gift Card Void

**POST** `/api/gift-cards/{id}/void`

**Request:**
```json
{
  "reason": "Lost or stolen"
}
```

### 8. Gift Card Balance Adjustment

**POST** `/api/gift-cards/{id}/adjust-balance`

**Request:**
```json
{
  "amount": 10000,  // Positive to add, negative to subtract
  "reason": "Correction for error"
}
```

---

## Purchase Flow Integration

### Update Purchase API

The existing purchase endpoint should support gift card redemption:

**POST** `/api/purchases`

**Request (with gift card):**
```json
{
  "pos_session_id": 123,
  "payments": [
    {
      "payment_method_code": "gift_token",
      "amount": 25000,
      "metadata": {
        "gift_card_code": "GC-ABC123XYZ456",
        "gift_card_pin": "1234"  // Optional
      }
    },
    {
      "payment_method_code": "cash",
      "amount": 10000
    }
  ],
  "cart": {
    "items": [...],
    "total": 35000
  }
}
```

**Changes to PurchaseService:**
- Detect `gift_token` payment method
- Validate gift card code and balance
- Redeem gift card amount
- Create gift card transaction
- Log POS event for gift card redemption

---

## Filament Admin Interface

### 1. GiftCardResource

**Location:** `app/Filament/Resources/GiftCards/`

**Features:**
- List view with filters (status, store, date range, search)
- View page showing:
  - Gift card details
  - Transaction history
  - Related charges/receipts
- Actions:
  - Refund gift card
  - Void gift card
  - Adjust balance
  - View transactions
- Bulk actions:
  - Export to CSV
  - Bulk void (with confirmation)

**Table Columns:**
- Code
- Store
- Initial Amount
- Balance
- Status
- Purchased At
- Last Used At
- Expires At
- Actions

**Form Fields (for manual creation):**
- Store
- Code (auto-generated, can override)
- Initial Amount
- Currency
- Expires At (optional)
- Customer (optional)
- Notes

### 2. GiftCardTransactionResource

**Location:** `app/Filament/Resources/GiftCardTransactions/`

**Features:**
- List view with filters
- View page showing transaction details
- Read-only (transactions are immutable)

---

## FlutterFlow Integration

### 1. Gift Card Purchase Flow

**Custom Action:** `purchase_gift_card.dart`

```dart
Future<Map<String, dynamic>> purchaseGiftCard({
  required int posSessionId,
  required String paymentMethodCode,
  required int amount,
  String? expiresAt,
  int? customerId,
  String? notes,
}) async {
  // Call POST /api/gift-cards/purchase
  // Return gift card details + receipt
}
```

**UI Flow:**
1. Cashier selects "Purchase Gift Card"
2. Enter amount
3. Select payment method
4. Optional: Set expiration date
5. Optional: Link to customer
6. Process payment
7. Display gift card code + print receipt

### 2. Gift Card Redemption Flow

**Custom Action:** `validate_gift_card.dart`

```dart
Future<Map<String, dynamic>> validateGiftCard({
  required String code,
  String? pin,
  required int amount,
}) async {
  // Call POST /api/gift-cards/validate
  // Return validation result + balance
}
```

**Custom Action:** `redeem_gift_card.dart`

```dart
Future<Map<String, dynamic>> redeemGiftCard({
  required String code,
  String? pin,
  required int amount,
  required int chargeId,
  required int posSessionId,
}) async {
  // Call gift card redemption (handled in purchase flow)
  // Return transaction details
}
```

**UI Flow:**
1. Customer presents gift card
2. Cashier scans/enters code
3. Optional: Enter PIN
4. System validates and shows balance
5. Customer uses full or partial balance
6. If partial, remaining balance shown
7. Complete purchase with gift card + other payment methods if needed

### 3. Gift Card Lookup Widget

**Custom Widget:** `gift_card_lookup.dart`

- Input field for gift card code
- PIN input (if enabled)
- Display balance
- Display status
- Display expiration date
- Show transaction history

### 4. Update Cart Data Structure

Add gift card support to cart:

```dart
class CartPayment {
  final String paymentMethodCode;
  final int amount;
  final Map<String, dynamic>? metadata;  // For gift_card_code, etc.
}
```

---

## POS Event Logging

### Event Codes

- **13023** - Gift card purchased
- **13024** - Gift card redeemed
- **13025** - Gift card refunded
- **13026** - Gift card voided
- **13027** - Gift card balance adjusted

### Implementation

Add to `PosEvent` logging in:
- `GiftCardService::purchaseGiftCard()` - Log 13023
- `GiftCardService::redeemGiftCard()` - Log 13024
- `GiftCardService::refundGiftCard()` - Log 13025
- `GiftCardService::voidGiftCard()` - Log 13026
- `GiftCardService::adjustBalance()` - Log 13027

---

## Receipt Generation

### 1. Gift Card Purchase Receipt

**Type:** Sales receipt
**Content:**
- Gift card code
- Initial amount
- Expiration date (if set)
- Purchase date
- Payment method used

### 2. Gift Card Redemption Receipt

**Type:** Sales receipt (regular purchase receipt)
**Content:**
- Gift card code used
- Amount redeemed
- Remaining balance (if partial)
- Regular purchase items

### 3. Gift Card Refund Receipt

**Type:** Return receipt
**Content:**
- Original gift card code
- Refund amount
- Refund reason
- Refund payment method

---

## SAF-T Compliance

### Transaction Codes

- **11001** - Gift card purchase (Sale)
- **11002** - Gift card redemption (Sale with gift card payment)

### Payment Codes

- **12003** - Gift card payment (already exists in system)

### Article Group Codes

- Gift cards should use appropriate article group code (e.g., "99" for services/gift cards)

### Implementation

Update `SafTCodeMapper` to handle gift card transactions:
- Gift card purchases: Transaction code 11001
- Gift card redemptions: Payment code 12003

---

## Security Considerations

### 1. Code Generation

- Use cryptographically secure random generator
- Format: `GC-{12 alphanumeric characters}` (e.g., `GC-ABC123XYZ456`)
- Ensure uniqueness (database constraint)
- Avoid easily guessable patterns

### 2. PIN (Optional)

- 4-8 digit PIN
- Stored as hashed value (bcrypt)
- Optional feature (configurable per store)

### 3. Balance Updates

- Use database transactions with row-level locking
- Prevent race conditions in concurrent redemptions
- Use `SELECT ... FOR UPDATE` when checking/updating balance

### 4. Validation

- Validate gift card exists and is active
- Check expiration date
- Verify balance is sufficient
- Validate PIN if enabled
- Check store ownership

---

## Testing Requirements

### Unit Tests

1. GiftCard model tests
   - Code generation uniqueness
   - Balance calculations
   - Status transitions
   - Validation methods

2. GiftCardService tests
   - Purchase flow
   - Redemption flow (with locking)
   - Refund flow
   - Void flow
   - Balance adjustments
   - Error handling

### Integration Tests

1. API endpoint tests
   - Purchase gift card
   - Validate gift card
   - Redeem gift card
   - List gift cards
   - Transaction history

2. Purchase flow integration
   - Gift card as single payment
   - Gift card in split payment
   - Partial redemption
   - Multiple gift cards

### Feature Tests

1. End-to-end purchase flow
2. End-to-end redemption flow
3. Admin operations (refund, void, adjust)

---

## Migration Strategy

### Phase 1: Core Infrastructure
1. Create database migrations
2. Create models (GiftCard, GiftCardTransaction)
3. Create GiftCardService
4. Basic validation and lookup

### Phase 2: Purchase Flow
1. Gift card purchase API endpoint
2. Receipt generation for purchases
3. POS event logging
4. Filament admin interface (basic)

### Phase 3: Redemption Flow
1. Update PurchaseService for gift card redemption
2. Gift card validation endpoint
3. Integration with purchase API
4. Receipt generation for redemptions

### Phase 4: Admin Features
1. Complete Filament admin interface
2. Refund functionality
3. Void functionality
4. Balance adjustments
5. Transaction history

### Phase 5: FlutterFlow Integration
1. Gift card purchase UI
2. Gift card redemption UI
3. Gift card lookup widget
4. Update cart structure

### Phase 6: Polish & Testing
1. Comprehensive testing
2. Documentation updates
3. Performance optimization
4. Security audit

---

## Configuration

### Store-Level Settings

Add to `stores` table or settings:
- `gift_card_enabled` - Enable/disable gift cards
- `gift_card_min_amount` - Minimum purchase amount (default: 100 NOK)
- `gift_card_max_amount` - Maximum purchase amount (default: 10000 NOK)
- `gift_card_pin_required` - Require PIN for redemption
- `gift_card_expiration_days` - Default expiration period (null = no expiration)
- `gift_card_code_prefix` - Custom prefix (default: "GC-")

---

## Documentation Updates

1. **API Documentation** (`api-spec.yaml`)
   - Add all gift card endpoints
   - Update purchase endpoint to include gift card support

2. **FlutterFlow Documentation**
   - Gift card purchase guide
   - Gift card redemption guide
   - Custom actions documentation

3. **Admin Documentation**
   - Gift card management guide
   - Refund/void procedures

---

## Future Enhancements (Out of Scope)

1. **Gift Card Products**
   - Pre-defined gift card amounts (e.g., 100 NOK, 500 NOK)
   - Physical gift card printing
   - Gift card templates

2. **Customer Features**
   - Customer gift card balance view
   - Gift card purchase history
   - Gift card gifting to other customers

3. **Marketing**
   - Gift card promotions
   - Bonus amounts (buy 500, get 550)
   - Expiration reminders

4. **Reporting**
   - Gift card sales reports
   - Unused gift card reports
   - Redemption analytics

---

## Summary

This implementation plan covers:

✅ **Database Schema** - Gift cards and transactions tables
✅ **Models** - GiftCard and GiftCardTransaction with relationships
✅ **Services** - GiftCardService for all business logic
✅ **API Endpoints** - Complete REST API for gift card operations
✅ **Purchase Integration** - Gift card redemption in purchase flow
✅ **Filament Admin** - Complete admin interface
✅ **FlutterFlow Integration** - Frontend support
✅ **Compliance** - POS events, receipts, SAF-T codes
✅ **Security** - Code generation, PIN support, transaction locking
✅ **Testing** - Comprehensive test coverage

The implementation follows existing patterns in the codebase and maintains compliance with Norwegian regulations.
