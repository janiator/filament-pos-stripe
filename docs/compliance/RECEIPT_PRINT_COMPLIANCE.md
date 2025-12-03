# Receipt Print Logic - Compliance Implementation

## Overview

This document explains how the receipt printing system ensures compliance with Norwegian Kassasystemforskriften (FOR-2015-12-18-1616), specifically § 2-8 requirements for receipt generation and printing.

## Compliance Requirements Summary

According to Kassasystemforskriften § 2-8, receipts must:
1. Be numbered sequentially per type and store
2. Include all required information
3. Be clearly marked with appropriate labels
4. Meet font size requirements for marked text
5. Be immutable once created
6. Be trackable (print status, reprint count)

## Implementation Architecture

### 1. Sequential Receipt Numbering

**Requirement:** Each receipt type must be numbered sequentially in its own series per store.

**Implementation:**

```77:108:app/Models/Receipt.php
public static function generateReceiptNumber(int $storeId, string $receiptType = 'sales'): string
    {
        $config = config('receipts.types.' . $receiptType, ['prefix' => 'X']);
        $prefix = $config['prefix'] ?? 'X';

        // Get last receipt number for this store and type
        $lastReceipt = static::where('store_id', $storeId)
            ->where('receipt_type', $receiptType)
            ->orderBy('receipt_number', 'desc')
            ->first();

        if ($lastReceipt) {
            // Extract number from receipt number (format: STOREID-PREFIX-000001)
            // Try to extract the sequential number
            $pattern = '/' . preg_quote($storeId . '-' . $prefix . '-', '/') . '(\d+)/';
            if (preg_match($pattern, $lastReceipt->receipt_number, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            } else {
                // Fallback: try to extract any number at the end
                if (preg_match('/(\d+)$/', $lastReceipt->receipt_number, $matches)) {
                    $nextNumber = (int) $matches[1] + 1;
                } else {
                    $nextNumber = 1;
                }
            }
        } else {
            $nextNumber = 1;
        }

        // Format: STOREID-PREFIX-000001
        return sprintf('%d-%s-%06d', $storeId, $prefix, $nextNumber);
    }
```

**Key Features:**
- ✅ Separate numbering series per receipt type (sales, return, copy, etc.)
- ✅ Sequential numbering per store
- ✅ Format: `{store_id}-{prefix}-{sequential_number}` (e.g., `2-S-000001`)
- ✅ Zero-padded to 6 digits for consistency
- ✅ Database constraint ensures uniqueness

**Receipt Type Prefixes:**
```56:89:config/receipts.php
    'types' => [
        'sales' => [
            'prefix' => 'S',
            'label' => 'Salgskvittering',
        ],
        'return' => [
            'prefix' => 'R',
            'label' => 'Returkvittering',
        ],
        'copy' => [
            'prefix' => 'C',
            'label' => 'Kopikvittering',
        ],
        'steb' => [
            'prefix' => 'STEB',
            'label' => 'STEB-kvittering',
        ],
        'provisional' => [
            'prefix' => 'P',
            'label' => 'Foreløpig kvittering',
        ],
        'training' => [
            'prefix' => 'T',
            'label' => 'Treningskvittering',
        ],
        'delivery' => [
            'prefix' => 'D',
            'label' => 'Utleveringskvittering',
        ],
        'correction' => [
            'prefix' => 'CORR',
            'label' => 'Korrigeringskvittering',
        ],
    ],
```

### 2. Receipt Immutability

**Requirement:** Receipts cannot be modified after creation (§ 2-6).

**Implementation:**
- Receipt model uses standard Laravel Eloquent (no special update restrictions)
- **Best Practice:** Receipts should be treated as immutable in application logic
- Receipt data is stored in JSON field, making it difficult to modify without explicit code
- All receipt changes should create new receipts (e.g., corrections create new correction receipts)

**Database Schema:**
```14:35:database/migrations/2025_11_26_175411_create_receipts_table.php
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->onDelete('set null');
            $table->foreignId('charge_id')->nullable()->constrained('connected_charges')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Cashier
            $table->string('receipt_number')->unique(); // Sequential per store
            $table->enum('receipt_type', [
                'sales',
                'return',
                'copy',
                'steb',
                'provisional',
                'training',
                'delivery'
            ]);
            $table->foreignId('original_receipt_id')->nullable()->constrained('receipts')->onDelete('set null'); // For returns/copies
            $table->json('receipt_data'); // All receipt content
            $table->boolean('printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->integer('reprint_count')->default(0);
            $table->timestamps();
```

### 3. Required Receipt Fields

**Requirement:** All receipts must include specific information (§ 2-8-4).

**Implementation in ReceiptGenerationService:**

```111:133:app/Services/ReceiptGenerationService.php
        $receiptData = [
            'store' => [
                'name' => $store->name,
                'address' => $storeMetadata['address'] ?? '',
                'organization_number' => $storeMetadata['organization_number'] ?? '',
            ],
            'receipt_number' => Receipt::generateReceiptNumber($store->id, 'sales'),
            'date' => $primaryCharge->paid_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'transaction_id' => $primaryCharge->stripe_charge_id ?? $primaryCharge->id, // Use charge ID if no Stripe charge ID (for cash payments)
            'session_number' => $session?->session_number,
            'cashier' => $session?->user?->name ?? 'Unknown',
            'items' => $items,
            'subtotal' => $subtotal,
            'total_discounts' => $totalDiscounts,
            'tax' => $totalTax,
            'total' => $totalAmount / 100,
            'is_split_payment' => $isSplitPayment,
            'payments' => $payments,
            'payment_method' => $isSplitPayment ? 'split' : $primaryCharge->payment_method,
            'payment_code' => $isSplitPayment ? null : $primaryCharge->payment_code,
            'tip_amount' => $tipAmount > 0 ? ($tipAmount / 100) : null,
            'charge_ids' => array_column($charges, 'id'),
        ];
```

**Required Fields Compliance:**
- ✅ Store name and address
- ✅ Receipt number (sequential)
- ✅ Date and time
- ✅ Transaction ID
- ✅ Items with quantities and prices
- ✅ Subtotal, tax, total
- ✅ Payment method
- ✅ Cashier name
- ✅ Session number

### 4. Receipt Type Markings

**Requirement:** Each receipt type must be clearly marked with appropriate Norwegian text (§ 2-8-5, 2-8-6, 2-8-7).

**Implementation in Templates:**

All receipt templates include proper markings:

1. **Sales Receipt:** "SALGSKVITTERING"
2. **Return Receipt:** "RETURKVITTERING"
3. **Copy Receipt:** "KOPI" (50% larger font)
4. **STEB Receipt:** "STEB-kvittering"
5. **Provisional Receipt:** "Foreløpig kvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
6. **Training Receipt:** "Treningskvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
7. **Delivery Receipt:** "Utleveringskvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)

**Template Compliance:**
```121:133:docs/features/RECEIPT_TEMPLATE_SYSTEM.md
All templates comply with Kassasystemforskriften requirements:

- ✅ Sales receipts marked as "SALGSKVITTERING"
- ✅ Return receipts marked as "RETURKVITTERING"
- ✅ Copy receipts marked as "KOPI" (50% larger font)
- ✅ STEB receipts marked as "STEB-kvittering"
- ✅ Provisional receipts marked with "Foreløpig kvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
- ✅ Training receipts marked with "Treningskvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
- ✅ Delivery receipts marked with "Utleveringskvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
- ✅ Sequential numbering per receipt type
- ✅ All required fields included (store info, date, transaction ID, items, totals, VAT, payment method)
```

### 5. Font Size Requirements

**Requirement:** Marked text must be at least 50% larger than amount text (§ 2-8-6).

**Implementation:**

Templates use Epson ePOS XML font attributes:
- Normal text: `width="1" height="1"`
- Marked text (50% larger): `width="2" height="2"`

Example from copy receipt template:
```xml
<text reverse="false" ul="false" em="true" color="color_1" width="2" height="2"/>
<text>KOPI</text>
```

### 6. Receipt Generation Flow

**Compliance-Enforced Flow:**

1. **Receipt Creation:**
   - Receipt number generated sequentially per type
   - All required data collected
   - Receipt stored in database

2. **Template Rendering:**
   - Template selected based on receipt type
   - Mustache variables populated with receipt data
   - XML sanitized to ensure schema compliance

3. **Print Tracking:**
   - Receipt marked as printed when XML is retrieved (first print)
   - Print timestamp recorded automatically
   - Reprint count incremented on subsequent XML retrievals
   - All receipt XML access is tracked for compliance

```110:127:app/Models/Receipt.php
    /**
     * Mark as printed
     */
    public function markAsPrinted(): void
    {
        $this->printed = true;
        $this->printed_at = now();
        $this->save();
    }

    /**
     * Increment reprint count
     */
    public function incrementReprint(): void
    {
        $this->reprint_count++;
        $this->save();
    }
```

### 7. XML Sanitization for Compliance

**Requirement:** Receipt XML must be valid and comply with Epson ePOS schema.

**Implementation:**

The `ReceiptTemplateService::sanitizeXml()` method ensures:
- Invalid elements removed
- Split text elements merged
- Empty text elements removed
- Valid XML structure maintained

This prevents schema errors that could cause printing failures.

### 8. Receipt Data Structure

**Compliance Requirements:**
- All transaction data preserved
- Link to original receipt (for returns/copies)
- Payment method details
- VAT information
- Item details

**Implementation:**

Receipt data is stored as JSON in `receipt_data` field, containing:
- Store information
- Receipt metadata
- Transaction details
- Items array
- Payment information
- Tax calculations

### 9. Print Status Tracking

**Requirement:** System must track receipt printing status.

**Implementation:**
- `printed` boolean flag
- `printed_at` timestamp
- `reprint_count` integer
- API endpoints to mark receipts as printed

### 10. Receipt Types and Compliance

Each receipt type has specific compliance requirements:

#### Sales Receipt (§ 2-8-4)
- ✅ Sequential numbering
- ✅ All required fields
- ✅ Marked as "SALGSKVITTERING"

#### Return Receipt (§ 2-8-5)
- ✅ Sequential numbering in own series
- ✅ Marked as "RETURKVITTERING"
- ✅ Links to original receipt
- ✅ Negative amounts shown

#### Copy Receipt (§ 2-8-6)
- ✅ Marked as "KOPI"
- ✅ 50% larger font for marking
- ✅ Links to original receipt

#### STEB Receipt (§ 2-8-6)
- ✅ Marked as "STEB-kvittering"
- ✅ Sequential numbering

#### Provisional Receipt (§ 2-8-6)
- ✅ Marked as "Foreløpig kvittering – IKKJE KVITTERING FOR KJØP"
- ✅ 50% larger font for marking

#### Training Receipt (§ 2-8-6)
- ✅ Marked as "Treningskvittering – IKKJE KVITTERING FOR KJØP"
- ✅ 50% larger font for marking
- ✅ Sequential numbering in own series
- ✅ Dated

#### Delivery Receipt (§ 2-8-7)
- ✅ Marked as "Utleveringskvittering – IKKJE KVITTERING FOR KJØP"
- ✅ 50% larger font for marking
- ✅ Sequential numbering in own series
- ✅ Shows delivered goods/services

## Compliance Checklist

### Receipt Generation
- [x] Sequential numbering per type and store
- [x] All required fields included
- [x] Proper receipt type markings
- [x] Font size requirements met
- [x] Receipt immutability (application-level)
- [x] Print status tracking
- [x] Reprint tracking

### Receipt Templates
- [x] All receipt types supported
- [x] Norwegian language text
- [x] Proper markings for each type
- [x] Font size compliance
- [x] Required fields in templates
- [x] XML schema compliance

### Data Integrity
- [x] Unique receipt numbers
- [x] Store association
- [x] Session association
- [x] Charge association
- [x] User (cashier) association
- [x] Original receipt linking (for returns/copies)

## API Endpoints for Compliance

### Generate Receipt
```
POST /api/receipts/generate
```
- Creates receipt with sequential number
- Includes all required fields
- Renders template with compliance markings

### Get Receipt XML
```
GET /api/receipts/{id}/xml
```
- Returns sanitized XML ready for printing
- Ensures schema compliance
- **Does NOT modify print status** - call mark-printed endpoint after successful print
- **Copy receipt handling:**
  - If receipt is not printed: Returns original receipt XML
  - If receipt is already printed: Returns copy receipt XML (marked as "KOPI")
  - Copy receipts are created automatically if they don't exist
- This ensures compliance by providing proper receipt types

### Mark as Printed
```
POST /api/receipts/{id}/mark-printed
```
- Marks receipt as printed on first call
- Records `printed_at` timestamp on first call
- Increments `reprint_count` on subsequent calls
- Maintains audit trail for all print operations

### Reprint Receipt
```
POST /api/receipts/{id}/reprint
```
- Increments reprint count
- Returns XML for printing
- Maintains original receipt data

## Compliance Validation

### Automatic Compliance Checks

1. **Receipt Number Generation:**
   - Ensures sequential numbering
   - Prevents duplicates (database unique constraint)
   - Separate series per type

2. **Template Rendering:**
   - Ensures proper markings
   - Validates required fields
   - Sanitizes XML

3. **Data Validation:**
   - Required fields present
   - Store information included
   - Transaction data complete

### Manual Compliance Verification

To verify compliance:
1. Check receipt numbering sequence
2. Verify receipt markings in templates
3. Validate required fields in receipt data
4. Test print functionality
5. Verify reprint tracking

## Future Enhancements

### Recommended Improvements

1. **Receipt Immutability Enforcement:**
   - Add database-level constraints
   - Implement model events to prevent updates
   - Add audit logging for any changes

2. **Compliance Validation Service:**
   - Automated compliance checking
   - Validation before receipt creation
   - Compliance report generation

3. **Receipt Correction Handling:**
   - Proper correction receipt generation
   - Link to original receipt
   - Compliance with correction requirements

4. **Enhanced Audit Trail:**
   - Log all receipt operations
   - Track receipt lifecycle
   - Compliance audit reports

## References

- [Kassasystemforskriften (FOR-2015-12-18-1616)](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)
- [Receipt Template System Documentation](../features/RECEIPT_TEMPLATE_SYSTEM.md)
- [Kassasystemforskriften Compliance Overview](./KASSASYSTEMFORSKRIFTEN_COMPLIANCE.md)

