# Payment Methods: Two Different Concepts Explained

## Overview

There are **two different** payment method resources in the system, each serving a distinct purpose:

1. **`PaymentMethod`** - Store configuration for accepted payment types
2. **`ConnectedPaymentMethod`** - Customer's saved payment instruments from Stripe

These are **NOT duplicates** - they serve completely different purposes and should **NOT be combined**.

## 1. PaymentMethod (Store Configuration)

**Purpose:** Defines which payment method **types** the store accepts at the POS.

**What it stores:**
- Payment method types (Cash, Card, Mobile Pay, etc.)
- Provider information (Stripe, Cash, Other)
- SAF-T compliance codes
- Store-level configuration

**Characteristics:**
- ✅ Store-level (one per store)
- ✅ Configuration/administrative model
- ✅ Defines what payment options are available
- ✅ Provider-agnostic (can support Stripe, Cash, or other providers)
- ✅ Used in POS checkout flow

**Example:**
```php
PaymentMethod {
  store_id: 1,
  name: "Kontant",
  code: "cash",
  provider: "cash",
  enabled: true,
  saf_t_payment_code: "12001"
}
```

**Use Case:**
- Store admin configures: "We accept Cash, Card, and Mobile Pay"
- POS system shows these options to cashiers
- When processing purchase, system checks if payment method is enabled

## 2. ConnectedPaymentMethod (Customer Payment Instruments)

**Purpose:** Stores actual payment instruments (saved credit cards, bank accounts) that customers have saved in Stripe.

**What it stores:**
- Stripe payment method IDs
- Card details (brand, last4, expiry)
- Customer association
- Billing information

**Characteristics:**
- ✅ Customer-level (many per customer)
- ✅ Synced from Stripe
- ✅ Represents actual payment instruments
- ✅ Stripe-specific
- ✅ Used when customer wants to pay with saved card

**Example:**
```php
ConnectedPaymentMethod {
  stripe_payment_method_id: "pm_1234",
  stripe_customer_id: "cus_5678",
  type: "card",
  card_brand: "visa",
  card_last4: "4242",
  card_exp_month: 12,
  card_exp_year: 2025,
  is_default: true
}
```

**Use Case:**
- Customer saves their credit card during checkout
- System syncs from Stripe and stores in `ConnectedPaymentMethod`
- When customer returns, they can select from saved cards
- Used to charge the customer's saved payment method

## How They Work Together

### Purchase Flow Example

1. **Customer selects payment method type:**
   ```php
   // Check PaymentMethod to see if "card" is enabled
   $paymentMethod = PaymentMethod::where('code', 'card')
       ->where('store_id', $storeId)
       ->where('enabled', true)
       ->first();
   ```

2. **If customer wants to use saved card:**
   ```php
   // Get customer's saved payment instruments
   $savedCards = ConnectedPaymentMethod::where('stripe_customer_id', $customerId)
       ->where('stripe_account_id', $store->stripe_account_id)
       ->get();
   ```

3. **Process payment:**
   ```php
   // Use the ConnectedPaymentMethod's stripe_payment_method_id
   // to charge the customer's saved card
   $stripe->paymentIntents->create([
       'payment_method' => $savedCard->stripe_payment_method_id,
       // ...
   ]);
   ```

## Key Differences

| Aspect | PaymentMethod | ConnectedPaymentMethod |
|--------|--------------|------------------------|
| **Level** | Store configuration | Customer data |
| **Purpose** | "What payment types do we accept?" | "What payment instruments do customers have?" |
| **Scope** | One per store per type | Many per customer |
| **Source** | Admin configuration | Synced from Stripe |
| **Provider** | Any (Stripe, Cash, Other) | Stripe only |
| **Example** | "We accept Cash" | "John's Visa •••• 4242" |
| **Used in** | POS checkout selection | Payment processing |

## Naming Confusion

The naming is confusing because both are called "Payment Method" but they represent different concepts:

- **PaymentMethod** = Payment method **type** (like "Card" or "Cash")
- **ConnectedPaymentMethod** = Payment method **instrument** (like "John's Visa card")

## Recommendations

### Option 1: Keep Both (Recommended)

Keep both models but clarify their purposes:

1. **Rename Filament resources for clarity:**
   - `PaymentMethodResource` → Keep as is (or rename to `StorePaymentMethodResource`)
   - `ConnectedPaymentMethodResource` → Rename to `CustomerPaymentMethodResource` or `SavedPaymentMethodResource`

2. **Update navigation labels:**
   - PaymentMethod: "Payment Method Types" or "Accepted Payment Methods"
   - ConnectedPaymentMethod: "Customer Payment Methods" or "Saved Payment Methods"

3. **Update documentation** to clearly explain the difference

### Option 2: Add Relationship (Optional Enhancement)

Add a relationship to link them when applicable:

```php
// In PaymentMethod model
public function connectedPaymentMethods()
{
    // For Stripe payment methods, get all customer payment instruments
    if ($this->provider === 'stripe') {
        return ConnectedPaymentMethod::where('stripe_account_id', $this->store->stripe_account_id)
            ->where('type', $this->provider_method ?? 'card');
    }
    return collect(); // Empty for non-Stripe methods
}
```

### Option 3: Update API Responses

When returning payment methods to frontend, include both:

```json
{
  "payment_method_types": [
    {
      "id": 1,
      "code": "card",
      "name": "Kort",
      "enabled": true
    }
  ],
  "saved_payment_methods": [
    {
      "id": 123,
      "stripe_payment_method_id": "pm_xxx",
      "card_display": "Visa •••• 4242",
      "is_default": true
    }
  ]
}
```

## Current Implementation Status

### PaymentMethod (Store Configuration)
- ✅ Model created
- ✅ Migration created
- ✅ Filament resource created
- ✅ Seeder created
- ✅ API endpoint created
- ✅ Used in purchase flow

### ConnectedPaymentMethod (Customer Instruments)
- ✅ Model exists (from Stripe Connect package)
- ✅ Migration exists
- ✅ Filament resource exists
- ✅ Synced from Stripe
- ✅ Used in charge creation
- ⚠️ Naming could be clearer

## Conclusion

**Do NOT combine these models.** They serve different purposes:

- **PaymentMethod** = Store configuration ("What do we accept?")
- **ConnectedPaymentMethod** = Customer data ("What do customers have saved?")

They work together in the purchase flow:
1. Check `PaymentMethod` to see if payment type is enabled
2. If customer wants saved card, use `ConnectedPaymentMethod` to get their saved instruments
3. Process payment using the saved instrument

The main issue is **naming confusion**, not functionality. Consider renaming the Filament resources and navigation labels to make the distinction clearer.

