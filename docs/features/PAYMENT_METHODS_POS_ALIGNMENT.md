# Payment Methods POS Alignment

## Overview

Payment methods have been optimized for POS usability by:
1. Adding `pos_suitable` flag to distinguish POS vs online-only methods
2. Improving naming and configuration for clarity
3. Filtering API responses to show only POS-suitable methods by default

## Changes Made

### 1. Added `pos_suitable` Field

**Migration:** `2025_12_02_092508_add_pos_suitable_to_payment_methods_table`

- Added `pos_suitable` boolean field (default: `true`)
- Added index on `['store_id', 'pos_suitable', 'enabled']` for efficient filtering

**Purpose:**
- Mark payment methods as suitable for physical POS or online-only
- Allows filtering to show only relevant methods in POS interface

### 2. Updated Payment Method Configuration

#### Improved Defaults

| Name | Code | Provider | Provider Method | POS Suitable | Notes |
|------|------|----------|----------------|--------------|-------|
| Kontant | `cash` | `cash` | - | ✅ Yes | Cash payment |
| Kort | `card_present` | `stripe` | `card_present` | ✅ Yes | Terminal card payment (includes Apple Pay, Google Pay) |
| Kort (Online) | `card` | `stripe` | `card` | ❌ No | Online card payment only |
| Gavekort | `gift_token` | `other` | - | ✅ Yes | Gift card |
| Tilgodelapp | `credit_note` | `other` | - | ✅ Yes | Credit note |

#### Key Improvements

1. **"Kort" renamed and simplified:**
   - Main card payment is now just "Kort" (was "Kort (Terminal)")
   - Simpler name for POS users
   - Uses `card_present` provider method

2. **"Kort (Online)" separated:**
   - Online card payment is now explicitly marked as online-only
   - `pos_suitable = false` so it won't appear in POS by default
   - Still available for online/API use cases

3. **"Mobil" removed:**
   - Mobile wallets (Apple Pay, Google Pay) are handled through "Kort" payment method
   - Terminal automatically supports mobile wallets when customer taps phone/watch
   - No separate mobile payment method needed

### 3. API Endpoint Updates

**GET `/api/purchases/payment-methods`**

Now filters by `pos_suitable` by default:

```bash
# Get only POS-suitable methods (default)
GET /api/purchases/payment-methods

# Get all methods (including online-only)
GET /api/purchases/payment-methods?pos_only=false
```

**Response (POS-suitable only):**
```json
{
  "data": [
    {
      "id": 5,
      "name": "Kontant",
      "code": "cash",
      "pos_suitable": true,
      ...
    },
    {
      "id": 6,
      "name": "Kort",
      "code": "card_present",
      "provider_method": "card_present",
      "pos_suitable": true,
      ...
    },
    {
      "id": 7,
      "name": "Gavekort",
      "code": "gift_token",
      "pos_suitable": true,
      ...
    },
    {
      "id": 8,
      "name": "Tilgodelapp",
      "code": "credit_note",
      "pos_suitable": true,
      ...
    }
  ]
}
```

### 4. Model Updates

**Added to `PaymentMethod` model:**
- `pos_suitable` field in fillable
- `pos_suitable` boolean cast
- `scopePosSuitable()` query scope

**Usage:**
```php
// Get only POS-suitable methods
PaymentMethod::posSuitable()->enabled()->get();

// Get all methods (including online)
PaymentMethod::enabled()->get();
```

### 5. Filament Admin Updates

**Form:**
- Added `pos_suitable` toggle field
- Helper text explains when to disable (for online-only methods)

**Table:**
- Added `pos_suitable` toggle column
- Added filter for POS suitable vs online only

## Migration Guide

### For Existing Stores

Existing payment methods have been updated:
- `cash` → `pos_suitable = true`
- `card_present` → `pos_suitable = true`
- `card` (online) → `pos_suitable = false`
- `mobile` → `pos_suitable = true`, `provider_method = 'link'`
- `gift_token` → `pos_suitable = true`
- `credit_note` → `pos_suitable = true`

### For New Stores

The seeder now:
- Creates payment methods with correct `pos_suitable` values
- Updates existing methods if they already exist (preserves user customizations)

## Frontend Integration

### Recommended Approach

1. **Default POS View:**
   ```dart
   // Get POS-suitable methods only
   final response = await getPaymentMethodsCall.call();
   // Only shows: Cash, Card, Mobile, Gift Card, Credit Note
   ```

2. **Admin/Configuration View:**
   ```dart
   // Get all methods including online-only
   final response = await getPaymentMethodsCall.call(posOnly: false);
   // Shows all methods including "Kort (Online)"
   ```

### UI Recommendations

1. **Payment Method Selection:**
   - Show only POS-suitable methods in checkout
   - Group by type if needed (Cash, Card, Other)
   - Use clear icons/labels

2. **Method Display:**
   - "Kort" for terminal card payments (includes Apple Pay, Google Pay)
   - "Kontant" for cash
   - "Gavekort" for gift cards
   - "Tilgodelapp" for credit notes

3. **Mobile Wallet Support:**
   - Apple Pay and Google Pay work automatically through "Kort" payment method
   - Terminal detects mobile wallets when customer taps phone/watch
   - No separate payment method needed

## Benefits

1. **Cleaner POS Interface:**
   - Only shows relevant payment methods
   - No confusion with online-only options

2. **Better Organization:**
   - Clear distinction between POS and online methods
   - Easier to manage and configure

3. **Flexibility:**
   - Can still access all methods via API parameter
   - Supports both POS and online use cases

4. **Accurate Configuration:**
   - Mobile payment uses correct Stripe Link provider
   - Proper SAF-T codes maintained

## Testing

After migration, verify:

- [ ] POS endpoint returns only POS-suitable methods by default
- [ ] All methods returned with `pos_only=false`
- [ ] Existing payment methods have correct `pos_suitable` values
- [ ] Filament admin shows `pos_suitable` toggle
- [ ] Seeder updates existing methods correctly

