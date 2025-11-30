# POS Audit Log Requirements - Kassasystemforskriften

## Overview

According to **§ 2-7 (Electronic Journal)** of Kassasystemforskriften, all transactions and system events must be logged in an electronic journal that is tamper-proof and exportable (SAF-T format).

**Legal Reference:** [Forskrift om krav til kassasystem (FOR-2015-12-18-1616)](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)

## Required Actions to Track

All actions must be logged using SAF-T event codes (PredefinedBasicID-13) in the `PosEvent` model.

---

## 1. Application Lifecycle Events

### ✅ **13001 - POS Application Start**
- **When:** When the POS application starts/initializes
- **Required by:** SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Frontend must call API endpoint when app starts
- **Data to log:**
  - `pos_device_id` - Which device started
  - `store_id` - Which store
  - `occurred_at` - Timestamp

### ✅ **13002 - POS Application Shutdown**
- **When:** When the POS application closes/shuts down
- **Required by:** SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Frontend must call API endpoint when app closes
- **Data to log:**
  - `pos_device_id` - Which device shut down
  - `store_id` - Which store
  - `occurred_at` - Timestamp

---

## 2. User/Authentication Events

### ✅ **13003 - Employee Login**
- **When:** When an employee logs into the POS system
- **Required by:** § 2-5 (User authentication), SAF-T compliance
- **Status:** ⚠️ **PARTIAL** - User auth exists but not linked to POS events
- **Implementation:** Must log when user authenticates for POS session
- **Data to log:**
  - `user_id` - Which employee logged in
  - `pos_device_id` - Which device
  - `pos_session_id` - Current session (if exists)
  - `store_id` - Which store
  - `occurred_at` - Timestamp

### ✅ **13004 - Employee Logout**
- **When:** When an employee logs out of the POS system
- **Required by:** § 2-5 (User authentication), SAF-T compliance
- **Status:** ⚠️ **PARTIAL** - User auth exists but not linked to POS events
- **Implementation:** Must log when user logs out
- **Data to log:**
  - `user_id` - Which employee logged out
  - `pos_device_id` - Which device
  - `pos_session_id` - Current session (if exists)
  - `store_id` - Which store
  - `occurred_at` - Timestamp

---

## 3. Cash Drawer Events

### ✅ **13005 - Open Cash Drawer**
- **When:** When the cash drawer is opened
- **Required by:** § 2-2 (Cash drawer integration), SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Must log every drawer open, distinguish between:
  - Drawer open WITH sale (normal operation)
  - Drawer open WITHOUT sale (nullinnslag) - **CRITICAL**
- **Data to log:**
  - `pos_device_id` - Which device
  - `pos_session_id` - Current session
  - `user_id` - Who opened drawer
  - `store_id` - Which store
  - `event_data` - Include `nullinnslag: true/false`
  - `related_charge_id` - If opened with sale
  - `occurred_at` - Timestamp

### ✅ **13006 - Close Cash Drawer**
- **When:** When the cash drawer is closed
- **Required by:** § 2-2 (Cash drawer integration), SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Must log every drawer close
- **Data to log:**
  - `pos_device_id` - Which device
  - `pos_session_id` - Current session
  - `user_id` - Who closed drawer
  - `store_id` - Which store
  - `occurred_at` - Timestamp

### ⚠️ **Cash Drawer Nullinnslag (Special Case)**
- **When:** When drawer is opened WITHOUT a sale transaction
- **Required by:** § 2-2 - **MANDATORY** - "Opening cash drawer without sale registration (nullinnslag) must be logged"
- **Status:** ❌ **NOT IMPLEMENTED** - **CRITICAL MISSING**
- **Implementation:** 
  - Must be logged as separate event or flagged in 13005
  - Cannot be bypassed
  - Must appear in reports
- **Data to log:**
  - Same as 13005 but with `nullinnslag: true`
  - Must include reason/description

---

## 4. Report Events

### ✅ **13008 - X Report (Daily Sales Report)**
- **When:** When X-report is generated (does NOT close session)
- **Required by:** § 2-8-2 (X-report requirement), SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Must log when X-report is generated
- **Data to log:**
  - `pos_session_id` - Which session
  - `user_id` - Who generated report
  - `store_id` - Which store
  - `event_data` - Report summary data
  - `occurred_at` - Timestamp

### ✅ **13009 - Z Report (End-of-Day Report)**
- **When:** When Z-report is generated (CLOSES session)
- **Required by:** § 2-8-3 (Z-report requirement), SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Must log when Z-report is generated (should also trigger 13021)
- **Data to log:**
  - `pos_session_id` - Which session (will be closed)
  - `user_id` - Who generated report
  - `store_id` - Which store
  - `event_data` - Complete report data including cash reconciliation
  - `occurred_at` - Timestamp

---

## 5. Transaction Events

### ✅ **13012 - Sales Receipt**
- **When:** When a sale transaction is completed
- **Required by:** § 2-8-4 (Sales receipt), § 2-7 (Electronic journal), SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `ConnectedChargeObserver`
- **Implementation:** Automatically logged when charge is created with `status = 'succeeded'`
- **Data logged:**
  - `related_charge_id` - The charge/transaction
  - `pos_session_id` - Current session
  - `user_id` - Employee who processed sale
  - `store_id` - Which store
  - `event_data` - Charge details (amount, currency, payment method)
  - `occurred_at` - Transaction timestamp

### ✅ **13013 - Return Receipt**
- **When:** When a refund/return transaction is completed
- **Required by:** § 2-8-5 (Return receipt), § 2-7 (Electronic journal), SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `ConnectedChargeObserver`
- **Implementation:** Automatically logged when charge is refunded
- **Data logged:**
  - `related_charge_id` - The original charge being refunded
  - `pos_session_id` - Current session
  - `user_id` - Employee who processed return
  - `store_id` - Which store
  - `event_data` - Refund details (refunded amount, original amount)
  - `occurred_at` - Refund timestamp

### ✅ **13014 - Void Transaction**
- **When:** When a transaction is voided (cancelled before completion)
- **Required by:** SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Must log when transaction is voided
- **Data to log:**
  - `related_charge_id` - The voided charge
  - `pos_session_id` - Current session
  - `user_id` - Who voided transaction
  - `store_id` - Which store
  - `event_data` - Void reason/details
  - `occurred_at` - Timestamp

### ✅ **13015 - Correction Receipt**
- **When:** When a correction receipt is issued (for errors)
- **Required by:** SAF-T compliance
- **Status:** ❌ **NOT IMPLEMENTED**
- **Implementation:** Must log when correction receipt is generated
- **Data to log:**
  - `related_charge_id` - Original charge being corrected (if applicable)
  - `pos_session_id` - Current session
  - `user_id` - Who issued correction
  - `store_id` - Which store
  - `event_data` - Correction details
  - `occurred_at` - Timestamp

---

## 6. Payment Method Events

These events track the payment method used for each transaction. They are logged alongside transaction events.

### ✅ **13016 - Cash Payment**
- **When:** When payment is made with cash
- **Required by:** SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `ConnectedChargeObserver`
- **Implementation:** Automatically logged when `payment_method = 'cash'`

### ✅ **13017 - Card Payment**
- **When:** When payment is made with card
- **Required by:** SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `ConnectedChargeObserver`
- **Implementation:** Automatically logged when `payment_method = 'card'`

### ✅ **13018 - Mobile Payment**
- **When:** When payment is made with mobile payment (e.g., Vipps, Apple Pay)
- **Required by:** SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `ConnectedChargeObserver`
- **Implementation:** Automatically logged when `payment_method = 'mobile'`

### ✅ **13019 - Other Payment Method**
- **When:** When payment is made with other/unknown payment method
- **Required by:** SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `ConnectedChargeObserver`
- **Implementation:** Automatically logged when payment method doesn't match above

---

## 7. Session Events

### ✅ **13020 - Session Opened**
- **When:** When a POS session is opened
- **Required by:** § 2-5 (Session management), SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `PosSessionObserver`
- **Implementation:** Automatically logged when `PosSession` is created
- **Data logged:**
  - `pos_session_id` - The session
  - `pos_device_id` - Which device
  - `user_id` - Employee who opened session
  - `store_id` - Which store
  - `event_data` - Session number, opening balance
  - `occurred_at` - Session open timestamp

### ✅ **13021 - Session Closed**
- **When:** When a POS session is closed
- **Required by:** § 2-5 (Session management), SAF-T compliance
- **Status:** ✅ **IMPLEMENTED** - Auto-logged via `PosSessionObserver`
- **Implementation:** Automatically logged when session status changes to 'closed'
- **Data logged:**
  - `pos_session_id` - The session
  - `pos_device_id` - Which device
  - `user_id` - Employee who closed session
  - `store_id` - Which store
  - `event_data` - Session number, cash reconciliation (expected, actual, difference)
  - `occurred_at` - Session close timestamp

---

## 8. Optional Events (Recommended but not strictly required)

### ⚠️ **13022 - Price Override**
- **When:** When a price is manually overridden
- **Status:** ❌ **NOT IMPLEMENTED** - Optional
- **Note:** Recommended for audit trail if price overrides are allowed

### ⚠️ **13023 - Discount Applied**
- **When:** When a discount is applied to a transaction
- **Status:** ❌ **NOT IMPLEMENTED** - Optional
- **Note:** Recommended for audit trail (you have Coupon/Discount models)

### ⚠️ **13024 - Tax Exemption**
- **When:** When tax exemption is applied
- **Status:** ❌ **NOT IMPLEMENTED** - Optional
- **Note:** Recommended if tax exemptions are used

### ⚠️ **13025 - Receipt Reprint**
- **When:** When a receipt is reprinted
- **Status:** ❌ **NOT IMPLEMENTED** - Optional
- **Note:** Recommended for audit trail (§ 2-8-6 mentions copy receipts)

---

## Summary: Implementation Status

### ✅ Fully Implemented (7 events)
- 13012 - Sales Receipt
- 13013 - Return Receipt
- 13016 - Cash Payment
- 13017 - Card Payment
- 13018 - Mobile Payment
- 13019 - Other Payment
- 13020 - Session Opened
- 13021 - Session Closed

### ⚠️ Partially Implemented (2 events)
- 13003 - Employee Login (auth exists, not linked to POS events)
- 13004 - Employee Logout (auth exists, not linked to POS events)

### ❌ Not Implemented - CRITICAL (8 events)
- 13001 - Application Start
- 13002 - Application Shutdown
- 13005 - Cash Drawer Open
- 13006 - Cash Drawer Close
- **Cash Drawer Nullinnslag** (special case of 13005) - **MANDATORY per § 2-2**
- 13008 - X Report
- 13009 - Z Report
- 13014 - Void Transaction
- 13015 - Correction Receipt

---

## Legal Requirements Summary

### § 2-2 (Cash Drawer)
- **MANDATORY:** Opening drawer without sale (nullinnslag) must be logged
- **MANDATORY:** All drawer operations must be tracked

### § 2-5 (Functions the System Must Have)
- **MANDATORY:** Complete transaction recording
- **MANDATORY:** Session management
- **MANDATORY:** User authentication

### § 2-7 (Electronic Journal)
- **MANDATORY:** All transactions must be logged
- **MANDATORY:** Journal must be tamper-proof
- **MANDATORY:** Journal must be exportable (SAF-T)

### § 2-8 (Reports and Receipts)
- **MANDATORY:** X-report must be available (§ 2-8-2)
- **MANDATORY:** Z-report must be available (§ 2-8-3)
- **MANDATORY:** Sales receipts must be generated (§ 2-8-4)
- **MANDATORY:** Return receipts must be generated (§ 2-8-5)

---

## Priority Implementation Order

### Phase 1: CRITICAL - Legal Compliance (Week 1)
1. **Cash Drawer Nullinnslag** (13005 with nullinnslag flag) - **MANDATORY per § 2-2**
2. **X-Report** (13008) - **MANDATORY per § 2-8-2**
3. **Z-Report** (13009) - **MANDATORY per § 2-8-3**
4. **Cash Drawer Open/Close** (13005, 13006) - **MANDATORY per § 2-2**

### Phase 2: High Priority (Week 2)
5. **Application Start/Shutdown** (13001, 13002) - SAF-T compliance
6. **Employee Login/Logout** (13003, 13004) - Link existing auth to POS events
7. **Void Transaction** (13014) - Transaction completeness
8. **Correction Receipt** (13015) - Error handling

### Phase 3: Optional (Week 3+)
9. Price Override (13022)
10. Discount Applied (13023)
11. Tax Exemption (13024)
12. Receipt Reprint (13025)

---

## Data Requirements for All Events

Every event must include:
- `store_id` - Which store (required)
- `event_code` - SAF-T event code (required)
- `event_type` - Category (application, user, drawer, report, transaction, payment, session)
- `occurred_at` - Timestamp (required)
- `user_id` - Employee who triggered event (when applicable)
- `pos_device_id` - Which POS device (when applicable)
- `pos_session_id` - Current session (when applicable)
- `related_charge_id` - Related transaction (for transaction events)
- `description` - Human-readable description
- `event_data` - Additional JSON data specific to event type

---

## Notes

1. **Immutability:** All events are immutable - they cannot be deleted or modified after creation (audit trail requirement)

2. **Completeness:** Every transaction must have corresponding events in the journal

3. **SAF-T Export:** All events must be included in SAF-T XML export

4. **Nullinnslag:** This is a special case that is legally required (§ 2-2) - drawer opens without sale must be explicitly logged

5. **Session Linking:** All events should be linked to a POS session when possible

6. **User Tracking:** All events should include the user/employee who triggered them

---

## References

- [Forskrift om krav til kassasystem (FOR-2015-12-18-1616)](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)
- [Kassasystemlova (LOV-2015-06-19-58)](https://lovdata.no/dokument/NL/lov/2015-06-19-58)
- SAF-T Cash Register Specification (PredefinedBasicID-13)

