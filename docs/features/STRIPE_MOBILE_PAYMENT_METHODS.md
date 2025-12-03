# Stripe Mobile Payment Methods Explained

## Current Configuration Issue

The "Mobil" payment method is currently configured with `provider_method: 'link'`, which refers to **Stripe Link**. However, Stripe Link is **not** a mobile payment method - it's a one-click checkout solution.

## What is Stripe Link?

**Stripe Link** is:
- A one-click checkout solution
- Allows customers to save payment info and use it across merchants
- Works on both web and mobile
- **Not** a mobile-specific payment method
- Uses the `link` payment method type in Stripe

**Use Case:** Fast checkout for returning customers who have used Stripe Link before.

## What "Mobile" Payments Actually Mean for POS

For POS systems, "mobile" payments typically refer to:

### 1. Mobile Wallets (Apple Pay, Google Pay)
- **Stripe Type:** `card` (with wallet indicator)
- **How it works:** Customer taps phone/watch on terminal or uses wallet app
- **Provider Method:** `card` (same as regular card, but with wallet metadata)
- **SAF-T Code:** `12011` (Mobiltelefon løsninger) or `12002` (Bankkort) depending on interpretation

### 2. Tap to Pay on iPhone/Android
- **Stripe Type:** `card_present` 
- **How it works:** Merchant's phone becomes the terminal, customer taps card/phone
- **Provider Method:** `card_present`
- **SAF-T Code:** `12002` (Bankkort) or `12011` (Mobiltelefon løsninger)

### 3. MobilePay (Denmark/Finland)
- **Stripe Type:** `mobilepay`
- **How it works:** Customer uses MobilePay app to pay
- **Provider Method:** `mobilepay`
- **SAF-T Code:** `12011` (Mobiltelefon løsninger)

### 4. QR Code Payments
- Various providers (Vipps, Swish, etc.)
- Usually handled as separate payment methods

## Recommendation for POS

For a POS system, "Mobil" should likely refer to **mobile wallet payments** (Apple Pay, Google Pay), not Stripe Link.

### Option 1: Mobile Wallets (Recommended for POS)

```php
[
    'name' => 'Mobil',
    'code' => 'mobile',
    'provider' => 'stripe',
    'provider_method' => 'card', // Mobile wallets use 'card' type
    'enabled' => true,
    'pos_suitable' => true,
    'saf_t_payment_code' => '12011', // Mobiltelefon løsninger
    'saf_t_event_code' => '13018', // Mobile payment
    'description' => 'Mobilbetaling (Apple Pay, Google Pay)',
]
```

**Note:** In Stripe, mobile wallets (Apple Pay, Google Pay) are processed as `card` payment methods, but with additional metadata indicating the wallet type. The payment intent would include `payment_method_options.card.wallet` information.

### Option 2: Keep Stripe Link (If Used for Mobile Checkout)

If you're using Stripe Link for mobile checkout flows (not POS), keep current config but clarify:

```php
[
    'name' => 'Mobil (Link)',
    'code' => 'mobile_link',
    'provider' => 'stripe',
    'provider_method' => 'link',
    'pos_suitable' => false, // Link is for online/mobile checkout, not POS
    'description' => 'Stripe Link - Rask betaling for returnerende kunder',
]
```

### Option 3: Remove "Mobile" for POS

If you're only doing physical POS, you might not need a separate "mobile" payment method:
- Apple Pay/Google Pay can be handled through the terminal as `card_present`
- Tap to Pay uses `card_present`
- Mobile wallets are just another way to present a card

## How Stripe Handles Mobile Wallets

When a customer pays with Apple Pay or Google Pay:

1. **Payment Intent Creation:**
   ```php
   $paymentIntent = $stripe->paymentIntents->create([
       'amount' => 1000,
       'currency' => 'nok',
       'payment_method_types' => ['card'],
       // Mobile wallet is detected automatically
   ]);
   ```

2. **Payment Method Details:**
   ```php
   // The payment method will have:
   $paymentMethod->type; // 'card'
   $paymentMethod->card->wallet; // 'apple_pay' or 'google_pay'
   ```

3. **For POS Terminal:**
   - Mobile wallets work through Stripe Terminal
   - Customer taps phone/watch on terminal
   - Processed as `card_present` with wallet metadata

## Current Implementation Issue

The current "Mobil" payment method with `provider_method: 'link'` is **not suitable for POS** because:

1. **Stripe Link is for online checkout**, not POS
2. **Link requires customer to have used it before** (saved payment info)
3. **Not a payment method type** - it's a checkout optimization
4. **Doesn't work with Terminal** - Link is web/mobile app only

## Recommended Fix

### For POS Systems:

**Option A: Remove "Mobile" or make it mobile wallets**
```php
[
    'name' => 'Mobil (Apple Pay/Google Pay)',
    'code' => 'mobile_wallet',
    'provider' => 'stripe',
    'provider_method' => 'card', // Wallets use card type
    'pos_suitable' => true,
    'saf_t_payment_code' => '12011',
    'saf_t_event_code' => '13018',
]
```

**Option B: Use for Tap to Pay**
```php
[
    'name' => 'Mobil (Tap to Pay)',
    'code' => 'tap_to_pay',
    'provider' => 'stripe',
    'provider_method' => 'card_present', // Tap to Pay uses card_present
    'pos_suitable' => true,
    'saf_t_payment_code' => '12011',
    'saf_t_event_code' => '13018',
]
```

**Option C: Disable for POS**
- Set `pos_suitable = false`
- Keep for online/mobile app use cases only

## Decision Needed

You need to decide what "Mobil" means in your POS context:

1. **Mobile wallets** (Apple Pay, Google Pay) → Use `card` provider_method
2. **Tap to Pay** (iPhone/Android as terminal) → Use `card_present` provider_method  
3. **Stripe Link** (one-click checkout) → Not suitable for POS, set `pos_suitable = false`
4. **Remove entirely** → If mobile wallets are handled through regular card payment

## Implementation Note

If using mobile wallets, the actual wallet type (Apple Pay, Google Pay) is detected automatically by Stripe when the payment is processed. You don't need to specify it in the payment method configuration - it's metadata on the payment method object after payment.


