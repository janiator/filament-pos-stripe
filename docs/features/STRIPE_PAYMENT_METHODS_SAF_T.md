# Stripe Payment Methods and SAF-T Code Mapping

## Problem

Stripe supports multiple payment method types (e.g., `card_present`, `card`, `us_bank_account`, `sepa_debit`), and each may require different SAF-T codes. The original implementation only considered the internal `code` field, not Stripe's actual payment method types.

## Solution

Updated the SAF-T code mapping to consider **both**:
1. **Internal `code`** (e.g., "card", "card_present", "bank_transfer")
2. **Stripe `provider_method`** (e.g., "card_present", "us_bank_account", "sepa_debit")

## Implementation

### Updated SafTCodeMapper

The `mapPaymentMethodToCode()` and `mapPaymentMethodToEventCode()` methods now accept both parameters:

```php
public static function mapPaymentMethodToCode(
    ?string $paymentMethodCode, 
    ?string $providerMethod = null
): string
```

### Mapping Logic

The mapper first checks `provider_method` for Stripe-specific types, then falls back to `code`:

#### Payment Codes (PredefinedBasicID-12)

| Provider Method | Payment Code | Description |
|----------------|--------------|-------------|
| `card_present` | `12002` | Bankkort (debet) - Terminal |
| `card` | `12002` | Bankkort (debet) - Online |
| `us_bank_account` | `12004` | Bankkonto |
| `sepa_debit` | `12004` | Bankkonto (SEPA) |
| `link` | `12011` | Mobiltelefon løsninger |
| Fallback | Based on `code` | Uses code-based mapping |

#### Event Codes (PredefinedBasicID-13)

| Provider Method | Event Code | Description |
|----------------|------------|-------------|
| `card_present` | `13017` | Card payment |
| `card` | `13017` | Card payment |
| `us_bank_account` | `13019` | Other payment method |
| `sepa_debit` | `13019` | Other payment method |
| `link` | `13018` | Mobile payment |
| Fallback | Based on `code` | Uses code-based mapping |

### Form Auto-Fill

The Filament form now auto-fills SAF-T codes when either field changes:

1. **When `code` changes:**
   - Uses both `code` and `provider_method` (if set) to determine SAF-T codes

2. **When `provider_method` changes:**
   - Recalculates SAF-T codes using both fields

### Example Scenarios

#### Scenario 1: Card Present (Terminal)
```php
PaymentMethod {
  code: "card_present",
  provider: "stripe",
  provider_method: "card_present"
}
```
- **Payment Code:** `12002` (Bankkort debet)
- **Event Code:** `13017` (Card payment)

#### Scenario 2: Card Online
```php
PaymentMethod {
  code: "card",
  provider: "stripe",
  provider_method: "card"
}
```
- **Payment Code:** `12002` (Bankkort debet)
- **Event Code:** `13017` (Card payment)

#### Scenario 3: Bank Account (US)
```php
PaymentMethod {
  code: "bank_transfer",
  provider: "stripe",
  provider_method: "us_bank_account"
}
```
- **Payment Code:** `12004` (Bankkonto)
- **Event Code:** `13019` (Other payment method)

#### Scenario 4: Bank Account (SEPA)
```php
PaymentMethod {
  code: "bank_transfer",
  provider: "stripe",
  provider_method: "sepa_debit"
}
```
- **Payment Code:** `12004` (Bankkonto)
- **Event Code:** `13019` (Other payment method)

#### Scenario 5: Stripe Link
```php
PaymentMethod {
  code: "mobile",
  provider: "stripe",
  provider_method: "link"
}
```
- **Payment Code:** `12011` (Mobiltelefon løsninger)
- **Event Code:** `13018` (Mobile payment)

## Backward Compatibility

The methods maintain backward compatibility:
- If `provider_method` is `null`, falls back to code-based mapping
- Existing payment methods without `provider_method` continue to work
- Default behavior unchanged for non-Stripe providers

## Usage in PurchaseService

When creating charges, the service now uses both fields:

```php
'payment_code' => $paymentMethod->saf_t_payment_code 
    ?? SafTCodeMapper::mapPaymentMethodToCode(
        $paymentMethod->code, 
        $paymentMethod->provider_method
    )
```

This ensures correct SAF-T codes are used even if not explicitly set in the payment method configuration.

## Future Stripe Payment Methods

To add support for new Stripe payment methods:

1. **Add to form options** in `PaymentMethodForm.php`:
   ```php
   'new_method' => 'New Method Name',
   ```

2. **Add to SafTCodeMapper** mapping:
   ```php
   'new_method' => '120XX', // Appropriate SAF-T code
   ```

3. **Update event code mapping** if needed:
   ```php
   'new_method' => '130XX', // Appropriate event code
   ```

## Testing

Test cases to verify:

- [ ] Card present payment uses correct codes
- [ ] Card online payment uses correct codes
- [ ] Bank account payments use correct codes
- [ ] SEPA debit uses correct codes
- [ ] Stripe Link uses correct codes
- [ ] Cash payments (no provider_method) still work
- [ ] Form auto-fills correctly when provider_method changes
- [ ] Form auto-fills correctly when code changes
- [ ] Manual override still works

## Summary

The system now properly handles Stripe's multiple payment method types by:
1. ✅ Checking `provider_method` first for Stripe-specific types
2. ✅ Falling back to `code`-based mapping for compatibility
3. ✅ Auto-filling SAF-T codes in the form based on both fields
4. ✅ Maintaining backward compatibility

This ensures accurate SAF-T compliance regardless of which Stripe payment method type is used.

