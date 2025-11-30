# SAF-T Cash Register Complete Code Mapping

## Overview

This document maps ALL SAF-T Cash Register code lists to POS system functionality, including BasicType categories and all PredefinedBasicID codes.

## BasicType Categories

| Code | Description (ENG) | Description (NOB) | Implementation Status |
|------|------------------|-------------------|----------------------|
| 01 | Cost codes | Kostnadssted | ⚠️ Optional - For accounting |
| 02 | Product codes | Produkt koder (PLU) | ✅ Exists - ConnectedProduct |
| 03 | Project codes | Prosjekt koder | ⚠️ Optional - For project tracking |
| 04 | Article Group codes | Varegruppekoder | ✅ Exists - Product categories |
| 05 | Ticketline codes | Varelinjekoder | ⚠️ Partial - Line items in charges |
| 06 | Logging codes | Logging i systemet | ✅ Exists - PosEvent system |
| 07 | Savings codes | Rabatter per enhet | ❌ Not implemented |
| 08 | Discount codes | Rabatt i pris | ❌ Not implemented |
| 09 | Quantity codes | Definisjon av antall | ⚠️ Partial - Product quantities |
| 10 | Raise codes | Tips beløp | ❌ Not implemented |
| 11 | Transaction codes | Transaksjonstyper | ⚠️ Partial - Charge types |
| 12 | Payment codes | Betalingsmåter | ✅ Exists - Payment methods |
| 13 | Event codes | Hendelser som ikke er salg | ✅ Exists - PosEvent |
| 14 | Service code | Systemleverandør service | ⚠️ Optional - System events |
| 15 | User codes | Brukeridenten | ✅ Exists - User model |
| 16 | Other | Øvrige | ✅ Exists - Generic codes |

---

## PredefinedBasicID-04: Article Group Codes (Varegruppekoder)

**Purpose:** Categorize products/services for tax and reporting purposes

| Code | Description (ENG) | Description (NOB) | Current Status | Implementation |
|------|------------------|-------------------|----------------|----------------|
| 04001 | Withdrawal of treatment services | Uttak av behandlingstjenester | ❌ | Add to product categories |
| 04002 | Withdrawal of goods used for treatment | Uttak av behandlingsvarer | ❌ | Add to product categories |
| 04003 | Sale of goods | Varesalg | ✅ | Default for products |
| 04004 | Sale of treatment services | Salg av behandlingstjenester | ❌ | Add to product categories |
| 04005 | Sale of haircut | Salg av hårklipp | ❌ | Add to product categories |
| 04006 | Food | Mat | ❌ | Add to product categories |
| 04007 | Beer | Øl | ❌ | Add to product categories |
| 04008 | Wine | Vin | ❌ | Add to product categories |
| 04009 | Liquor | Brennevin | ❌ | Add to product categories |
| 04010 | Alcopops/Cider | Rusbrus/Cider | ❌ | Add to product categories |
| 04011 | Soft drinks/Mineral water | Mineralvann (brus) | ❌ | Add to product categories |
| 04012 | Other drinks | Annen drikke | ❌ | Add to product categories |
| 04013 | Tobacco | Tobakk | ❌ | Add to product categories |
| 04014 | Other goods | Andre varer | ❌ | Add to product categories |
| 04015 | Entrance fee | Inngangspenger | ❌ | Add to product categories |
| 04016 | Free entrance | Inngangspenger fri adgang | ❌ | Add to product categories |
| 04017 | Cloakroom fee | Garderobeavgift | ❌ | Add to product categories |
| 04018 | Free cloakroom | Garderobeavgift fri | ❌ | Add to product categories |
| 04019 | Accommodation - full board | Helfullpensjon | ❌ | Add to product categories |
| 04020 | Accommodation - half board | Halvpensjon | ❌ | Add to product categories |
| 04021 | Accommodation - with breakfast | Overnatting med frokost | ❌ | Add to product categories |
| 04999 | Other | Øvrige | ✅ | Default fallback |

**Implementation:**
- Add `article_group_code` field to `ConnectedProduct` model
- Create product category mapping
- Use in SAF-T generation for tax categorization

---

## PredefinedBasicID-10: Raise Codes (Tips/Gratuity)

**Purpose:** Track tips and gratuity amounts

| Code | Description (ENG) | Description (NOB) | Current Status | Implementation |
|------|------------------|-------------------|----------------|----------------|
| 10001 | Amount from gratuity/tip | Beløp knyttet til drikkepenger | ❌ | Add tip tracking |
| 10999 | Other | Øvrige | ❌ | Generic fallback |

**Implementation:**
- Add `tip_amount` field to `ConnectedCharge` model
- Add tip input in POS frontend
- Log tip events separately
- Include in SAF-T generation

---

## PredefinedBasicID-11: Transaction Codes (Transaksjonstyper)

**Purpose:** Categorize transaction types for reporting

| Code | Description (ENG) | Description (NOB) | Current Status | Implementation |
|------|------------------|-------------------|----------------|----------------|
| 11001 | Cash sale | Kontantsalg | ⚠️ Partial | Map payment_method='cash' |
| 11002 | Credit sale | Kredittsalg | ⚠️ Partial | Map to credit transactions |
| 11003 | Purchase | Kjøp av varer | ❌ | Add purchase transactions |
| 11004 | Payment | Betaling | ⚠️ Partial | Map to charges |
| 11005 | Receiving payment | Innbetaling fra kunde | ⚠️ Partial | Map to charges |
| 11006 | Return payment | Utbetaling ved retur | ⚠️ Partial | Map to refunds |
| 11007 | Cash declaration | Inngående vekselbeholdning | ❌ | Add cash management |
| 11008 | Cash difference | Kassedifferanse | ✅ | Exists in PosSession |
| 11009 | Correction | Korrigere kvittering | ❌ | Add correction transactions |
| 11010 | Out Payment | Ansatte tar ut penger | ❌ | Add cash withdrawal |
| 11011 | In Payment | Ansatte setter inn penger | ❌ | Add cash deposit |
| 11012 | Trade-in, exchange | Kjøp fra kunde + salg | ❌ | Add trade-in transactions |
| 11013 | Return products | Vare i retur | ⚠️ Partial | Map to refunds |
| 11014 | Inventory, stock | Inventar, lager | ❌ | Add inventory transactions |
| 11015 | Cash and credit sale | Kontant- og kredittsalg | ❌ | Add split payment |
| 11016 | Cash sale and return | Kontantsalg og retur | ❌ | Add return on cash sale |
| 11017 | Credit sale and return | Kredittsalg og retur | ❌ | Add return on credit sale |
| 11999 | Other | Øvrige | ✅ | Default fallback |

**Implementation:**
- Add `transaction_code` field to `ConnectedCharge` model
- Auto-map based on payment method and transaction type
- Allow manual override for special cases
- Include in SAF-T generation

---

## PredefinedBasicID-12: Payment Codes (Betalingsmåter)

**Purpose:** Specify payment methods used

| Code | Description (ENG) | Description (NOB) | Current Status | Implementation |
|------|------------------|-------------------|----------------|----------------|
| 12001 | Cash | Kontant | ✅ | Map payment_method='cash' |
| 12002 | Debit card | Bankkort (debet) | ✅ | Map payment_method='card' |
| 12003 | Credit card | Kredittkort | ✅ | Map payment_method='card' |
| 12004 | Bank account | Bankkonto | ❌ | Add bank transfer |
| 12005 | Gift token | Gavekort | ❌ | Add gift card payment |
| 12006 | Customer card | Kundekonto | ❌ | Add customer account |
| 12007 | Loyalty, stamps | Lojalitetspoeng | ❌ | Add loyalty points |
| 12008 | Bottle deposit | Pant | ❌ | Add deposit handling |
| 12009 | Check | Sjekk | ❌ | Add check payment |
| 12010 | Credit note | Tilgodelapp | ❌ | Add credit note |
| 12011 | Mobile phone apps | Mobiltelefon løsninger | ✅ | Map payment_method='mobile' |
| 12999 | Other | Øvrige | ✅ | Default fallback |

**Implementation:**
- Map existing `payment_method` to SAF-T codes
- Add new payment methods as needed
- Include in SAF-T generation
- Link to payment events (13016-13019)

---

## PredefinedBasicID-13: Event Codes (Hendelser)

**Purpose:** Track all POS system events for audit trail

| Code | Description | Status | Implementation |
|------|-------------|--------|----------------|
| 13001 | POS application start | ❌ | Phase 2 |
| 13002 | POS application shut down | ❌ | Phase 2 |
| 13003 | Employee log in | ⚠️ | Phase 2 |
| 13004 | Employee log out | ⚠️ | Phase 2 |
| 13005 | Open cash drawer | ❌ | Phase 3 |
| 13006 | Close cash drawer | ❌ | Phase 3 |
| 13008 | X report | ⚠️ | Phase 3 |
| 13009 | Z report | ⚠️ | Phase 3 |
| 13012 | Sales receipt | ⚠️ | Phase 1 |
| 13013 | Return receipt | ⚠️ | Phase 1 |
| 13014 | Void transaction | ❌ | Phase 6 |
| 13015 | Correction receipt | ❌ | Phase 6 |
| 13016 | Cash payment | ⚠️ | Phase 4 |
| 13017 | Card payment | ⚠️ | Phase 4 |
| 13018 | Mobile payment | ⚠️ | Phase 4 |
| 13019 | Other payment | ⚠️ | Phase 4 |
| 13020 | Session opened | ✅ | Phase 1 |
| 13021 | Session closed | ✅ | Phase 1 |

---

## Database Schema Updates Required

### ConnectedProduct
```php
// Add fields
'article_group_code' => 'string', // PredefinedBasicID-04 code
'product_code' => 'string', // PLU code (BasicType-02)
```

### ConnectedCharge
```php
// Add fields
'transaction_code' => 'string', // PredefinedBasicID-11 code
'payment_code' => 'string', // PredefinedBasicID-12 code
'tip_amount' => 'integer', // Tips in cents
'article_group_code' => 'string', // From product (can be overridden)
```

### PosSession
```php
// Already has:
'cash_difference' => 'integer', // Maps to 11008
```

### New Model: ChargeLineItem
```php
// For tracking individual line items (BasicType-05)
'charge_id' => 'foreignId',
'product_id' => 'foreignId',
'quantity' => 'integer',
'unit_price' => 'integer',
'total_amount' => 'integer',
'article_group_code' => 'string', // From product
'discount_code' => 'string', // PredefinedBasicID-08 (future)
'savings_code' => 'string', // PredefinedBasicID-07 (future)
```

---

## Code Mapping Logic

### Payment Method → Payment Code (PredefinedBasicID-12)
```php
function mapPaymentMethodToCode(string $paymentMethod): string
{
    return match($paymentMethod) {
        'cash' => '12001',
        'card' => '12002', // or 12003 based on card type
        'mobile' => '12011',
        default => '12999',
    };
}
```

### Payment Method → Event Code (PredefinedBasicID-13)
```php
function mapPaymentMethodToEventCode(string $paymentMethod): string
{
    return match($paymentMethod) {
        'cash' => '13016',
        'card' => '13017',
        'mobile' => '13018',
        default => '13019',
    };
}
```

### Transaction Type → Transaction Code (PredefinedBasicID-11)
```php
function mapTransactionToCode(ConnectedCharge $charge): string
{
    if ($charge->refunded) {
        return '11006'; // Return payment
    }
    
    if ($charge->payment_method === 'cash') {
        return '11001'; // Cash sale
    }
    
    return '11002'; // Credit sale (default)
}
```

### Product → Article Group Code (PredefinedBasicID-04)
```php
function getArticleGroupCode(ConnectedProduct $product): string
{
    // Use product's article_group_code if set
    if ($product->article_group_code) {
        return $product->article_group_code;
    }
    
    // Default based on product type
    return match($product->type) {
        'service' => '04004', // Treatment services
        'good' => '04003', // Sale of goods
        default => '04999', // Other
    };
}
```

---

## SAF-T XML Structure Updates

### Include Article Group Codes
```xml
<Line>
    <RecordID>123</RecordID>
    <AccountID>3000</AccountID>
    <ArticleGroupCode>04003</ArticleGroupCode> <!-- NEW -->
    <SourceDocumentID>ch_xxx</SourceDocumentID>
    <Description>Sale of goods</Description>
    <DebitAmount>15000</DebitAmount>
    <CreditAmount>0</CreditAmount>
</Line>
```

### Include Transaction Codes
```xml
<Transaction>
    <TransactionID>456</TransactionID>
    <TransactionCode>11001</TransactionCode> <!-- NEW -->
    <TransactionDate>2025-11-26</TransactionDate>
    <!-- ... -->
</Transaction>
```

### Include Payment Codes
```xml
<Payment>
    <PaymentCode>12001</PaymentCode> <!-- NEW -->
    <PaymentMethod>Cash</PaymentMethod>
    <Amount>15000</Amount>
</Payment>
```

### Include Tips
```xml
<Line>
    <RecordID>789</RecordID>
    <AccountID>3001</AccountID>
    <RaiseCode>10001</RaiseCode> <!-- NEW -->
    <Description>Tip/Gratuity</Description>
    <DebitAmount>500</DebitAmount>
    <CreditAmount>0</CreditAmount>
</Line>
```

---

## Implementation Priority

### Phase 1: Core Codes (Week 1)
- ✅ PredefinedBasicID-13 (Event codes) - Already planned
- ✅ PredefinedBasicID-12 (Payment codes) - Map existing payment methods
- ✅ PredefinedBasicID-11 (Transaction codes) - Map transaction types

### Phase 2: Product Codes (Week 2)
- ⚠️ PredefinedBasicID-04 (Article Group codes) - Add to products
- ⚠️ BasicType-02 (Product codes/PLU) - Add PLU to products

### Phase 3: Advanced Features (Week 3+)
- ❌ PredefinedBasicID-10 (Raise codes/Tips) - Add tip tracking
- ❌ BasicType-05 (Ticketline codes) - Add line item tracking
- ❌ BasicType-07/08 (Discount codes) - Add discount system

---

## Frontend Integration Requirements

### Product Creation/Edit
```javascript
// Add article group code selection
{
  name: 'article_group_code',
  type: 'select',
  label: 'Article Group',
  options: [
    { value: '04003', label: 'Sale of goods' },
    { value: '04006', label: 'Food' },
    { value: '04007', label: 'Beer' },
    // ... all codes
  ]
}
```

### Transaction Creation
```javascript
// Auto-map codes based on payment method
const paymentCode = mapPaymentMethodToCode(paymentMethod);
const transactionCode = mapTransactionToCode(charge);
const eventCode = mapPaymentMethodToEventCode(paymentMethod);

// Include in charge creation
await api.post('/charges', {
  amount: total,
  payment_method: paymentMethod,
  payment_code: paymentCode, // NEW
  transaction_code: transactionCode, // NEW
  tip_amount: tipAmount, // NEW
  // ...
});
```

### Tip Input
```javascript
// Add tip input in POS frontend
{
  name: 'tip_amount',
  type: 'number',
  label: 'Tip (optional)',
  min: 0,
  step: 1
}
```

---

## Compliance Checklist

- [ ] All BasicType categories mapped
- [ ] PredefinedBasicID-04 codes available for products
- [ ] PredefinedBasicID-10 codes for tips
- [ ] PredefinedBasicID-11 codes for transactions
- [ ] PredefinedBasicID-12 codes for payments
- [ ] PredefinedBasicID-13 codes for events
- [ ] Codes included in SAF-T XML
- [ ] Codes cannot be modified after creation
- [ ] Default codes assigned automatically

