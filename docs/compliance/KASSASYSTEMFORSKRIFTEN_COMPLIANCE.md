# Kassasystemforskriften Compliance Requirements

## Overview

This document maps the official Norwegian cash register regulation ([Forskrift om krav til kassasystem](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)) to our POS system implementation.

**Reference:** [FOR-2015-12-18-1616](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)

## Chapter 2: Requirements for Cash Register Systems

### § 2-1. System Description

**Requirement:** System must have a system description.

**Status:** ✅ Covered by documentation (POS_DEVICE_ARCHITECTURE.md, POS_SESSION_MANAGEMENT.md)

**Implementation:**
- Maintain system documentation
- Document all features and functions
- Keep documentation updated

---

### § 2-2. Cash Drawer (Kassaskuff)

**Requirements:**
- Cash drawer must be integrated with the system
- Opening cash drawer without sale registration (nullinnslag) must be logged
- Cash drawer operations must be tracked

**Status:** ⚠️ Partial - We track drawer events but need to ensure nullinnslag logging

**Implementation Required:**
- [x] Track cash drawer open/close events (13005, 13006)
- [ ] Log "nullinnslag" (drawer open without sale) as separate event
- [ ] Ensure drawer cannot be opened without logging
- [ ] Link drawer operations to POS sessions

**API Endpoints:**
- `POST /api/pos-devices/{id}/cash-drawer/open` - Already planned
- `POST /api/pos-devices/{id}/cash-drawer/close` - Already planned
- Need to distinguish between:
  - Drawer open with sale (normal operation)
  - Drawer open without sale (nullinnslag) - **REQUIRED**

---

### § 2-3. Printer (Skrivar)

**Requirements:**
- System must support receipt printing
- Receipts must meet format requirements (§ 2-8)

**Status:** ⚠️ Not yet implemented - Receipt generation needed

**Implementation Required:**
- [ ] Receipt generation functionality
- [ ] Receipt format compliance
- [ ] Receipt numbering (sequential)
- [ ] Receipt types:
  - Sales receipt (§ 2-8-4)
  - Return receipt (§ 2-8-5)
  - Copy receipt (§ 2-8-6)
  - STEB receipt (§ 2-8-6)
  - Provisional receipt (§ 2-8-6)
  - Training receipt (§ 2-8-6)
  - Delivery receipt (§ 2-8-7)

---

### § 2-4. Language Requirements

**Requirements:**
- System must support Norwegian language
- All user-facing text must be in Norwegian

**Status:** ⚠️ Partial - Need to ensure all text is in Norwegian

**Implementation Required:**
- [ ] Norwegian language support
- [ ] All error messages in Norwegian
- [ ] All receipts in Norwegian
- [ ] All reports in Norwegian

---

### § 2-5. Functions the System Must Have

**Requirements:**
- Complete transaction recording
- Receipt generation
- Report generation
- Electronic journal
- Session management
- User authentication

**Status:** ✅ Most functions exist, need to ensure completeness

**Implementation Checklist:**
- [x] Transaction recording (ConnectedCharge)
- [x] Session management (PosSession)
- [x] User authentication
- [x] Electronic journal (PosEvent)
- [ ] Receipt generation (see § 2-8)
- [ ] Report generation (X-report, Z-report)

---

### § 2-6. Functions the System Must NOT Have

**Requirements:**
- Cannot delete transactions
- Cannot modify completed transactions
- Cannot bypass security features
- Cannot disable logging

**Status:** ✅ Compliant - Transactions are immutable

**Implementation:**
- [x] Transactions cannot be deleted (soft delete only)
- [x] Transactions cannot be modified after creation
- [x] All events are logged (PosEvent)
- [x] Audit trail is maintained

---

### § 2-7. Electronic Journal (Elektronisk journal)

**Requirements:**
- All transactions must be logged in electronic journal
- Journal must be tamper-proof
- Journal must include all required information
- Journal must be exportable (SAF-T)

**Status:** ✅ Implemented via PosEvent system

**Implementation:**
- [x] All transactions logged (PosEvent)
- [x] Events are immutable (cannot be deleted)
- [x] Complete audit trail
- [x] SAF-T export functionality
- [ ] Ensure journal cannot be tampered with
- [ ] Validate journal completeness

---

### § 2-8. Reports and Receipts

#### § 2-8-1. General Requirements

**Requirements:**
- X-report, Z-report, sales receipt, etc. must be available
- All receipts must be numbered sequentially
- All receipts must be dated

**Status:** ⚠️ Partial - Reports planned, receipts not yet implemented

---

#### § 2-8-2. X-Report (X-rapport)

**Requirements:**
- Shows current session summary
- Does NOT close session
- Shows:
  - Number of transactions
  - Total amounts
  - Payment method breakdown
  - Cash amounts
  - Card amounts

**Status:** ⚠️ Planned but not implemented

**Implementation Required:**
- [ ] `POST /api/pos-sessions/{id}/x-report` endpoint
- [ ] Generate X-report data
- [ ] Log event 13008
- [ ] Return report data (JSON + printable format)
- [ ] Include all required information

**X-Report Content:**
```json
{
  "session_id": 123,
  "session_number": "000001",
  "opened_at": "2025-11-26T08:00:00Z",
  "report_generated_at": "2025-11-26T14:00:00Z",
  "transactions_count": 45,
  "total_amount": 150000,
  "cash_amount": 50000,
  "card_amount": 100000,
  "by_payment_method": {...},
  "cash_drawer_opens": 12,
  "nullinnslag_count": 2
}
```

---

#### § 2-8-3. Z-Report (Z-rapport)

**Requirements:**
- Shows final session summary
- CLOSES the session
- Shows:
  - All transaction details
  - Final cash count
  - Cash difference
  - Complete summary

**Status:** ⚠️ Planned but not implemented

**Implementation Required:**
- [ ] `POST /api/pos-sessions/{id}/z-report` endpoint
- [ ] Generate Z-report data
- [ ] Close session automatically
- [ ] Log events 13009 and 13021
- [ ] Return complete report
- [ ] Include all required information

**Z-Report Content:**
```json
{
  "session_id": 123,
  "session_number": "000001",
  "opened_at": "2025-11-26T08:00:00Z",
  "closed_at": "2025-11-26T17:00:00Z",
  "opening_balance": 0,
  "expected_cash": 50000,
  "actual_cash": 52000,
  "cash_difference": 2000,
  "transactions_count": 45,
  "total_amount": 150000,
  "cash_amount": 50000,
  "card_amount": 100000,
  "complete_transaction_list": [...],
  "events": [...]
}
```

---

#### § 2-8-4. Sales Receipt (Salskvittering)

**Requirements:**
- Must show:
  - Receipt number (sequential)
  - Date and time
  - Store information
  - Transaction details
  - Items sold
  - Prices
  - Total amount
  - Payment method
  - Transaction ID (if applicable)
- Must be clearly marked as sales receipt
- Must be numbered sequentially in own series

**Status:** ❌ Not implemented

**Implementation Required:**
- [ ] Receipt generation model
- [ ] Sequential receipt numbering per store
- [ ] Receipt format compliance
- [ ] Receipt printing support
- [ ] Receipt data structure

**Receipt Model:**
```php
class Receipt extends Model
{
    protected $fillable = [
        'store_id',
        'pos_session_id',
        'charge_id',
        'receipt_number', // Sequential per store
        'receipt_type', // 'sales', 'return', 'copy', etc.
        'receipt_data', // JSON with all receipt content
        'printed_at',
        'reprint_count',
    ];
}
```

**Receipt Content Requirements:**
- Store name and address
- Receipt number
- Date and time
- Transaction ID
- Items (name, quantity, price, total)
- Subtotal
- Tax information
- Total amount
- Payment method
- Cashier name
- Session number

---

#### § 2-8-5. Return Receipt (Returkvittering)

**Requirements:**
- Must be clearly marked "Returkvittering" at top
- Must include all information from § 2-8-4
- Must reference original receipt
- Must be numbered sequentially in own series

**Status:** ❌ Not implemented

**Implementation Required:**
- [ ] Return receipt generation
- [ ] Link to original receipt
- [ ] Mark clearly as "Returkvittering"
- [ ] Sequential numbering for returns
- [ ] Include all required information

---

#### § 2-8-6. Copy Receipt, STEB Receipt, Provisional Receipt, Training Receipt

**Requirements:**
- Copy receipt: Must be marked "KOPI"
- STEB receipt: Must be marked "STEB-kvittering"
- Provisional receipt: Must be marked "Foreløpig kvittering – IKKJE KVITTERING FOR KJØP"
- Training receipt: Must be marked "Treningskvittering – IKKJE KVITTERING FOR KJØP"
- Marked text must be at least 50% larger font than amount text
- Training receipts must be dated and numbered sequentially in own series

**Status:** ❌ Not implemented

**Implementation Required:**
- [ ] Receipt type field
- [ ] Special marking for each type
- [ ] Font size requirements
- [ ] Sequential numbering for training receipts
- [ ] Date requirements

---

#### § 2-8-7. Delivery Receipt (Utleveringskvittering)

**Requirements:**
- For credit sales that will be invoiced later
- Must be marked "Utleveringskvittering – IKKJE KVITTERING FOR KJØP"
- Marked text must be at least 50% larger font than amount text
- Must be dated and numbered sequentially in own series
- Must show what goods/services were delivered

**Status:** ❌ Not implemented

**Implementation Required:**
- [ ] Delivery receipt generation
- [ ] Mark clearly as delivery receipt
- [ ] Sequential numbering
- [ ] Link to credit sale/invoice

---

#### § 2-8-8. Payment Terminal Receipt

**Requirements:**
- Receipts from payment terminals not integrated with cash register
- Must be marked "IKKJE KVITTERING FOR KJØP"
- Marked text must be at least 50% larger font than amount text

**Status:** ⚠️ Partial - We use Stripe Terminal which is integrated

**Implementation:**
- [x] Stripe Terminal is integrated
- [ ] If using non-integrated terminals, mark receipts accordingly

---

## Compliance Checklist

### Core Requirements
- [x] Electronic journal (PosEvent system)
- [x] Transaction immutability
- [x] Session management
- [x] User authentication
- [x] SAF-T export
- [ ] Receipt generation (all types)
- [ ] X-report generation
- [ ] Z-report generation
- [ ] Cash drawer nullinnslag logging
- [ ] Norwegian language support

### Receipt Types
- [ ] Sales receipt (§ 2-8-4)
- [ ] Return receipt (§ 2-8-5)
- [ ] Copy receipt (§ 2-8-6)
- [ ] STEB receipt (§ 2-8-6)
- [ ] Provisional receipt (§ 2-8-6)
- [ ] Training receipt (§ 2-8-6)
- [ ] Delivery receipt (§ 2-8-7)

### Reports
- [ ] X-report (§ 2-8-2)
- [ ] Z-report (§ 2-8-3)

### Events
- [x] Session opened (13020)
- [x] Session closed (13021)
- [x] Sales receipt (13012)
- [x] Return receipt (13013)
- [ ] Cash drawer nullinnslag (new event code needed)

---

## Implementation Priority

### Phase 1: Critical Legal Requirements (Week 1-2)
1. **Receipt Generation System**
   - Receipt model and numbering
   - Sales receipt generation
   - Return receipt generation
   - Receipt format compliance

2. **X-Report and Z-Report**
   - X-report endpoint
   - Z-report endpoint
   - Report data generation
   - Event logging

3. **Cash Drawer Nullinnslag**
   - Track drawer opens without sale
   - Log as separate event
   - Include in reports

### Phase 2: Additional Receipt Types (Week 3)
1. Copy receipt
2. STEB receipt
3. Provisional receipt
4. Training receipt
5. Delivery receipt

### Phase 3: Language and Formatting (Week 4)
1. Norwegian language support
2. Receipt formatting
3. Font size requirements
4. Print support

---

## API Endpoints Required

### Receipts
- `POST /api/receipts/generate` - Generate receipt
- `GET /api/receipts/{id}` - Get receipt
- `POST /api/receipts/{id}/reprint` - Reprint receipt
- `GET /api/receipts` - List receipts

### Reports
- `POST /api/pos-sessions/{id}/x-report` - Generate X-report
- `POST /api/pos-sessions/{id}/z-report` - Generate Z-report

### Cash Drawer
- `POST /api/pos-devices/{id}/cash-drawer/open` - Open drawer (with/without sale)
- `POST /api/pos-devices/{id}/cash-drawer/nullinnslag` - Log nullinnslag

---

## Legal Compliance Notes

1. **Receipt Numbering:** Must be sequential per store, cannot skip numbers
2. **Receipt Immutability:** Receipts cannot be modified after creation
3. **Journal Completeness:** All transactions must be in electronic journal
4. **Report Accuracy:** X and Z reports must match actual transactions
5. **Language:** All user-facing text must be in Norwegian
6. **Formatting:** Receipts must meet specific format requirements

---

## References

- [Forskrift om krav til kassasystem (FOR-2015-12-18-1616)](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)
- [Kassasystemlova (LOV-2015-06-19-58)](https://lovdata.no/dokument/NL/lov/2015-06-19-58)

