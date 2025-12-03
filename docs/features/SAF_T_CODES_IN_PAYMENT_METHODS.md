# SAF-T Codes in Payment Methods: Implementation Approach

## Question

Should SAF-T payment codes and event codes be:
1. **Select lists** (dropdown with predefined options)
2. **Hardcoded** (auto-filled based on payment method type, no user input)
3. **Text inputs** (free text entry)

## Answer: Hybrid Approach (Auto-fill + Select Override)

We use a **hybrid approach** that combines the best of both:

- ✅ **Auto-fills** based on payment method code using `SafTCodeMapper`
- ✅ **Select dropdowns** with all valid SAF-T codes for manual override
- ✅ **Validates** against the official SAF-T specification
- ✅ **Flexible** for edge cases while ensuring compliance

## Implementation

### Form Fields

Both SAF-T code fields are now **Select dropdowns** with:

1. **Auto-fill on code change:**
   - When user enters/changes the payment method `code` field
   - SAF-T codes are automatically filled using `SafTCodeMapper::mapPaymentMethodToCode()` and `mapPaymentMethodToEventCode()`

2. **Manual override available:**
   - User can select a different code from the dropdown if needed
   - All valid SAF-T codes from the specification are available

3. **Validation:**
   - Only valid codes from the specification can be selected
   - Prevents typos and invalid codes

### Code Example

```php
// In PaymentMethodForm.php

TextInput::make('code')
    ->live(onBlur: true)
    ->afterStateUpdated(function ($state, $set) {
        // Auto-fill SAF-T codes when code changes
        if ($state) {
            $paymentCode = SafTCodeMapper::mapPaymentMethodToCode($state);
            $eventCode = SafTCodeMapper::mapPaymentMethodToEventCode($state);
            $set('saf_t_payment_code', $paymentCode);
            $set('saf_t_event_code', $eventCode);
        }
    }),

Select::make('saf_t_payment_code')
    ->options(SafTCodeMapper::getPaymentCodes())
    ->searchable()
    ->helperText('Auto-filled based on payment method code, but can be overridden.'),

Select::make('saf_t_event_code')
    ->options([
        '13016' => '13016 - Cash payment',
        '13017' => '13017 - Card payment',
        '13018' => '13018 - Mobile payment',
        '13019' => '13019 - Other payment method',
    ])
    ->searchable()
```

### Backend Auto-fill

The `CreatePaymentMethod` page also auto-fills codes if not provided:

```php
// Auto-fill SAF-T codes based on payment method code if not provided
if (!empty($data['code']) && empty($data['saf_t_payment_code'])) {
    $data['saf_t_payment_code'] = SafTCodeMapper::mapPaymentMethodToCode($data['code']);
}

if (!empty($data['code']) && empty($data['saf_t_event_code'])) {
    $data['saf_t_event_code'] = SafTCodeMapper::mapPaymentMethodToEventCode($data['code']);
}
```

## Why This Approach?

### ✅ Advantages

1. **Compliance:** Only valid SAF-T codes can be selected
2. **Convenience:** Auto-fills for common cases (cash, card, mobile)
3. **Flexibility:** Manual override for edge cases
4. **Error Prevention:** No typos or invalid codes
5. **User-Friendly:** Shows descriptions (e.g., "12001 - Kontant")
6. **Maintainable:** Changes to mapping logic update all forms

### ❌ Why Not Hardcode Only?

- Too rigid - no flexibility for edge cases
- Can't handle custom payment methods
- Difficult to maintain if spec changes

### ❌ Why Not Text Input Only?

- Error-prone - users can enter invalid codes
- No validation against specification
- Typos can break SAF-T compliance
- No guidance on valid codes

### ❌ Why Not Select Only?

- Requires manual selection every time
- Slower workflow
- More clicks for common cases

## SAF-T Code Mapping

### Payment Codes (PredefinedBasicID-12)

The `SafTCodeMapper::getPaymentCodes()` method returns all valid payment codes:

```php
[
    '12001' => 'Kontant',
    '12002' => 'Bankkort (debet)',
    '12003' => 'Kredittkort',
    '12004' => 'Bankkonto',
    '12005' => 'Gavekort',
    '12006' => 'Kundekonto',
    '12007' => 'Lojalitetspoeng',
    '12008' => 'Pant',
    '12009' => 'Sjekk',
    '12010' => 'Tilgodelapp',
    '12011' => 'Mobiltelefon løsninger',
    '12999' => 'Øvrige',
]
```

### Event Codes (PredefinedBasicID-13)

For payment methods, only these event codes are relevant:

```php
[
    '13016' => 'Cash payment (Kontantbetaling)',
    '13017' => 'Card payment (Kortbetaling)',
    '13018' => 'Mobile payment (Mobilbetaling)',
    '13019' => 'Other payment method (Annen betalingsmåte)',
]
```

## Default Mapping

The `SafTCodeMapper` automatically maps payment method codes:

| Payment Method Code | Payment Code | Event Code |
|---------------------|--------------|------------|
| `cash` | `12001` | `13016` |
| `card` | `12002` | `13017` |
| `credit_card` | `12003` | `13017` |
| `mobile` | `12011` | `13018` |
| Other | `12999` | `13019` |

## Usage Example

1. **User creates payment method:**
   - Enters code: `cash`
   - SAF-T codes auto-fill: `12001` and `13016`
   - User can override if needed

2. **User creates custom payment method:**
   - Enters code: `gift_card`
   - SAF-T codes auto-fill: `12999` and `13019` (default)
   - User selects appropriate codes: `12005` (Gavekort) and `13019`

3. **User edits existing payment method:**
   - Changes code from `card` to `mobile`
   - SAF-T codes update automatically
   - User can keep or change the codes

## Conclusion

The hybrid approach (auto-fill + select override) provides:
- ✅ Compliance with SAF-T specification
- ✅ Convenience for common cases
- ✅ Flexibility for edge cases
- ✅ Error prevention
- ✅ Better user experience

This is the recommended approach for SAF-T code management in payment methods.

