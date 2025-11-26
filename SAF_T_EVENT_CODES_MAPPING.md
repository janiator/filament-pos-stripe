# SAF-T Cash Register Event Codes Mapping (PredefinedBasicID-13)

## Overview

This document maps all relevant SAF-T Cash Register event codes (PredefinedBasicID-13) to POS system functionality and outlines the implementation plan.

## Event Code Categories

### Application Lifecycle Events

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13001 | POS application start | ❌ Not tracked | ✅ Required |
| 13002 | POS application shut down | ❌ Not tracked | ✅ Required |

### User/Authentication Events

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13003 | Employee log in | ⚠️ Partial (User auth exists) | ✅ Required - Link to POS sessions |
| 13004 | Employee log out | ⚠️ Partial (User auth exists) | ✅ Required - Link to POS sessions |

### Cash Drawer Events

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13005 | Open cash drawer | ❌ Not tracked | ✅ Required |
| 13006 | Close cash drawer | ❌ Not tracked | ✅ Required |

### Report Events

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13008 | X report (daily sales report) | ⚠️ Partial (Daily closing exists) | ✅ Required - Map to daily closing |
| 13009 | Z report (end-of-day report) | ⚠️ Partial (Daily closing exists) | ✅ Required - Map to daily closing |

### Transaction Events

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13012 | Sales receipt | ⚠️ Partial (Charges exist) | ✅ Required - Link event to charge |
| 13013 | Return receipt | ⚠️ Partial (Refunds exist) | ✅ Required - Link event to refund |
| 13014 | Void transaction | ❌ Not tracked | ✅ Required |
| 13015 | Correction receipt | ❌ Not tracked | ✅ Required |

### Payment Events

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13016 | Cash payment | ⚠️ Partial (Payment method tracked) | ✅ Required - Event logging |
| 13017 | Card payment | ⚠️ Partial (Payment method tracked) | ✅ Required - Event logging |
| 13018 | Mobile payment | ⚠️ Partial (Payment method tracked) | ✅ Required - Event logging |
| 13019 | Other payment method | ⚠️ Partial (Payment method tracked) | ✅ Required - Event logging |

### Session Events

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13020 | Session opened | ✅ Exists (PosSession) | ✅ Required - Map to event code |
| 13021 | Session closed | ✅ Exists (PosSession) | ✅ Required - Map to event code |

### Additional Events (May be required)

| Code | Description | Current Status | Implementation Required |
|------|-------------|----------------|-------------------------|
| 13022 | Price override | ❌ Not tracked | ⚠️ Optional |
| 13023 | Discount applied | ❌ Not tracked | ⚠️ Optional |
| 13024 | Tax exemption | ❌ Not tracked | ⚠️ Optional |
| 13025 | Receipt reprint | ❌ Not tracked | ⚠️ Optional |

## Data Model Requirements

### New Model: PosEvent

**Purpose**: Track all POS events for SAF-T compliance

**Fields**:
- `id` (primary key)
- `store_id` (foreign key)
- `pos_device_id` (foreign key, nullable)
- `pos_session_id` (foreign key, nullable)
- `user_id` (foreign key, nullable) - Employee who triggered event
- `event_code` (string) - PredefinedBasicID-13 code (e.g., "13012")
- `event_type` (enum) - Category: application, user, drawer, report, transaction, payment, session
- `description` (text, nullable) - Human-readable description
- `related_charge_id` (foreign key, nullable) - For transaction events
- `related_refund_id` (foreign key, nullable) - For refund events
- `event_data` (json, nullable) - Additional event-specific data
- `occurred_at` (timestamp) - When event occurred
- `created_at`, `updated_at` (timestamps)

**Indexes**:
- `store_id`, `occurred_at`
- `pos_session_id`, `event_code`
- `event_code`, `occurred_at`

## API Endpoints Required

### Event Logging

**POST** `/api/pos-events`
- Log a POS event
- Required: `event_code`, `event_type`
- Optional: `pos_session_id`, `related_charge_id`, `description`, `event_data`

**GET** `/api/pos-events`
- List events with filters
- Query params: `event_code`, `event_type`, `pos_session_id`, `from_date`, `to_date`

**GET** `/api/pos-events/{id}`
- Get specific event details

### Application Lifecycle

**POST** `/api/pos-devices/{id}/start`
- Log application start (13001)
- Auto-called when POS app initializes

**POST** `/api/pos-devices/{id}/shutdown`
- Log application shutdown (13002)
- Auto-called when POS app closes

### Cash Drawer

**POST** `/api/pos-devices/{id}/cash-drawer/open`
- Log cash drawer open (13005)
- Requires active session

**POST** `/api/pos-devices/{id}/cash-drawer/close`
- Log cash drawer close (13006)
- Requires active session

### Reports

**POST** `/api/pos-sessions/{id}/x-report`
- Generate X report (13008)
- Returns current session summary

**POST** `/api/pos-sessions/{id}/z-report`
- Generate Z report (13009)
- Closes session and generates final report

## Implementation Plan

### Phase 1: Core Event Tracking (Priority: High)

1. **Create PosEvent Model & Migration**
   - Model with all required fields
   - Relationships to Store, PosDevice, PosSession, User, ConnectedCharge
   - Indexes for performance

2. **Create PosEventsController**
   - `POST /api/pos-events` - Log events
   - `GET /api/pos-events` - List events
   - `GET /api/pos-events/{id}` - Get event

3. **Auto-log Session Events**
   - Update `PosSession::open()` to log 13020
   - Update `PosSession::close()` to log 13021

4. **Auto-log Transaction Events**
   - Create observer/listener for `ConnectedCharge::created` → log 13012
   - Create observer/listener for refunds → log 13013

### Phase 2: Application & User Events (Priority: High)

1. **Application Lifecycle**
   - Add `POST /api/pos-devices/{id}/start` endpoint
   - Add `POST /api/pos-devices/{id}/shutdown` endpoint
   - Frontend calls these on app start/close

2. **User Authentication Events**
   - Update login endpoint to log 13003
   - Update logout endpoint to log 13004
   - Link to current POS session if active

### Phase 3: Cash Drawer & Reports (Priority: Medium)

1. **Cash Drawer Events**
   - Add `POST /api/pos-devices/{id}/cash-drawer/open`
   - Add `POST /api/pos-devices/{id}/cash-drawer/close`
   - Frontend calls when drawer opens/closes

2. **Report Generation**
   - Add `POST /api/pos-sessions/{id}/x-report` (13008)
   - Add `POST /api/pos-sessions/{id}/z-report` (13009)
   - Z report should close session

### Phase 4: Payment Method Events (Priority: Medium)

1. **Payment Event Logging**
   - Update charge creation to log payment method event:
     - Cash → 13016
     - Card → 13017
     - Mobile → 13018
     - Other → 13019

### Phase 5: Advanced Events (Priority: Low)

1. **Void & Correction**
   - Add void transaction endpoint (13014)
   - Add correction receipt endpoint (13015)

2. **Optional Events**
   - Price override (13022)
   - Discount applied (13023)
   - Tax exemption (13024)
   - Receipt reprint (13025)

### Phase 6: SAF-T Integration (Priority: High)

1. **Update SAF-T Generator**
   - Include all PosEvent records in SAF-T XML
   - Map events to SAF-T event structure
   - Ensure chronological order

2. **Event Validation**
   - Validate required events are logged
   - Check for missing events in sessions
   - Generate warnings for compliance issues

## Frontend Integration Flow

### Application Start
```
1. POS app starts
2. Call POST /api/pos-devices/{id}/start (logs 13001)
3. Check for open session: GET /api/pos-sessions/current
4. If no session, prompt to open one
```

### User Login
```
1. User logs in via API
2. Backend logs 13003 event
3. If session exists, link event to session
4. Return session info to frontend
```

### Transaction Flow
```
1. User creates sale
2. Frontend calls charge creation endpoint
3. Backend creates charge AND logs 13012 event
4. Backend logs payment method event (13016-13019)
5. Return charge + event info to frontend
```

### Cash Drawer
```
1. User opens drawer (button/action)
2. Frontend calls POST /api/pos-devices/{id}/cash-drawer/open
3. Backend logs 13005 event
4. Physical drawer opens (hardware integration)
```

### Session Close
```
1. User closes session
2. Frontend calls POST /api/pos-sessions/{id}/close
3. Backend logs 13021 event
4. Generate Z report (13009)
5. Return closing summary
```

## Data Collection Strategy

### Automatic Events (Backend)
- Session open/close (13020, 13021)
- Transaction creation (13012)
- Refund creation (13013)
- Payment method (13016-13019)

### Manual Events (Frontend)
- Application start/shutdown (13001, 13002)
- User login/logout (13003, 13004)
- Cash drawer open/close (13005, 13006)
- Reports (13008, 13009)
- Void/correction (13014, 13015)

### Event Data Structure

```json
{
  "event_code": "13012",
  "event_type": "transaction",
  "pos_session_id": 123,
  "related_charge_id": 456,
  "description": "Sales receipt #789",
  "event_data": {
    "receipt_number": "789",
    "items_count": 3,
    "total_amount": 15000
  },
  "occurred_at": "2025-11-26T10:30:00Z"
}
```

## Compliance Checklist

- [ ] All required events are logged
- [ ] Events are linked to sessions
- [ ] Events include timestamps
- [ ] Events include user information
- [ ] Events are included in SAF-T export
- [ ] Event codes match PredefinedBasicID-13
- [ ] Events cannot be deleted (audit trail)
- [ ] Events are immutable once created

## Next Steps

1. Review and approve this mapping
2. Create PosEvent model and migration
3. Implement Phase 1 (Core Event Tracking)
4. Update SAF-T generator to include events
5. Create API endpoints for frontend integration
6. Document frontend integration requirements

