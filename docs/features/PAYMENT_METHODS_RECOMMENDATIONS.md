# Payment Methods: Recommendations for Clarity

## Summary

You have two different payment method resources that serve different purposes:

1. **`PaymentMethod`** - Store configuration for accepted payment types
2. **`ConnectedPaymentMethod`** - Customer's saved payment instruments from Stripe

**They should NOT be combined** - they work together but serve different purposes.

## Changes Made

### 1. Updated Filament Navigation Labels

**PaymentMethod Resource:**
- Navigation Label: "Payment Method Types"
- Description: "Configure which payment method types are accepted at the POS"
- Group: "Settings"

**ConnectedPaymentMethod Resource:**
- Navigation Label: "Customer Payment Methods"
- Description: "View customer saved payment instruments synced from Stripe"
- Group: "Payments"

This makes it clear that:
- Payment Method Types = Store configuration
- Customer Payment Methods = Customer data

### 2. Created Documentation

- `docs/features/PAYMENT_METHODS_EXPLAINED.md` - Detailed explanation of both concepts
- This document - Recommendations

## How They Work Together

### Purchase Flow

1. **Store Admin** configures accepted payment types in `PaymentMethod`:
   - Cash
   - Card (Terminal)
   - Card (Online)
   - Mobile Pay

2. **POS System** shows these options to cashier

3. **Customer** selects payment type (e.g., "Card")

4. **If customer has saved cards**, system shows their `ConnectedPaymentMethod` records:
   - "Visa •••• 4242"
   - "Mastercard •••• 5678"

5. **System processes payment** using the selected `ConnectedPaymentMethod`'s Stripe ID

## Optional Enhancements

### 1. Add Relationship Helper

You could add a helper method to link them:

```php
// In PaymentMethod model
public function getCustomerPaymentMethodsForStore(Store $store): Collection
{
    if ($this->provider !== 'stripe') {
        return collect();
    }
    
    return ConnectedPaymentMethod::where('stripe_account_id', $store->stripe_account_id)
        ->where('type', $this->provider_method ?? 'card')
        ->get();
}
```

### 2. Update API Response

When returning payment methods to frontend, structure it clearly:

```json
{
  "payment_method_types": [
    {
      "id": 1,
      "code": "card",
      "name": "Kort",
      "enabled": true,
      "provider": "stripe"
    }
  ],
  "customer_saved_methods": [
    {
      "id": 123,
      "stripe_payment_method_id": "pm_xxx",
      "card_display": "Visa •••• 4242",
      "is_default": true
    }
  ]
}
```

### 3. Add Helper Methods

```php
// In PaymentMethod model
public function allowsSavedInstruments(): bool
{
    return $this->provider === 'stripe' && 
           in_array($this->provider_method, ['card', 'card_present']);
}

// In ConnectedPaymentMethod model
public function matchesPaymentMethodType(PaymentMethod $paymentMethod): bool
{
    if ($paymentMethod->provider !== 'stripe') {
        return false;
    }
    
    $expectedType = $paymentMethod->provider_method ?? 'card';
    return $this->type === $expectedType;
}
```

## Current State

✅ **Both models are correctly implemented**
✅ **Navigation labels updated for clarity**
✅ **Documentation created**
✅ **They work together in purchase flow**

## No Action Required

The system is working correctly. The only issue was naming confusion, which has been addressed by:
1. Updating navigation labels
2. Adding descriptions
3. Creating documentation

You can continue using both models as they are - they serve different purposes and work together seamlessly.

