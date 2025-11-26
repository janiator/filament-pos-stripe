# SAF-T Cash Register Implementation Plan

## Executive Summary

This document outlines the complete implementation plan for SAF-T Cash Register compliance, focusing on PredefinedBasicID-13 event codes and integration with the POS frontend.

## Current State Analysis

### ✅ What We Have
- POS Session management (`PosSession` model)
- Transaction tracking (`ConnectedCharge` model)
- Daily closing reports (`PosSessionClosing` model)
- Basic SAF-T XML generation
- User authentication system
- POS device tracking
- Electronic journal foundation (PosEvent model created)

### ❌ What We're Missing (Critical for Legal Compliance)
- **Receipt generation system** - **CRITICAL** (Required by § 2-8)
- **X-report generation** - **CRITICAL** (Required by § 2-8-2)
- **Z-report generation** - **CRITICAL** (Required by § 2-8-3)
- **Cash drawer nullinnslag tracking** - **CRITICAL** (Required by § 2-2)
- Event logging system implementation - **CRITICAL**
- Application lifecycle tracking (13001, 13002)
- Cash drawer event tracking (13005, 13006)
- Report event tracking (13008, 13009)
- Transaction event linking (13012, 13013)
- Payment method event tracking (13016-13019)
- SAF-T integration with events
- Norwegian language support - **CRITICAL** (Required by § 2-4)

**Legal Reference:** [Forskrift om krav til kassasystem (FOR-2015-12-18-1616)](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)

## Implementation Phases

### Phase 1: Core Event Infrastructure (Week 1)

**Priority: CRITICAL**

#### 1.1 Database & Models
- [x] Create `PosEvent` model
- [x] Create migration with all required fields
- [ ] Add relationships to existing models
- [ ] Create event code constants

#### 1.2 API Endpoints
- [ ] `POST /api/pos-events` - Log event
- [ ] `GET /api/pos-events` - List events (with filters)
- [ ] `GET /api/pos-events/{id}` - Get event details

#### 1.3 Auto-Logging
- [ ] Observer for `PosSession::created` → log 13020
- [ ] Observer for `PosSession::updated` (status=closed) → log 13021
- [ ] Observer for `ConnectedCharge::created` → log 13012
- [ ] Observer for refunds → log 13013

**Deliverables:**
- PosEvent model with full functionality
- Basic event logging API
- Automatic event logging for sessions and transactions

---

### Phase 2: Application & User Events (Week 1-2)

**Priority: HIGH**

#### 2.1 Application Lifecycle
- [ ] `POST /api/pos-devices/{id}/start` - Log 13001
- [ ] `POST /api/pos-devices/{id}/shutdown` - Log 13002
- [ ] Frontend integration: Call on app start/close

#### 2.2 User Authentication
- [ ] Update login endpoint to log 13003
- [ ] Update logout endpoint to log 13004
- [ ] Link events to active POS session

**Deliverables:**
- Application lifecycle tracking
- User authentication event logging

---

### Phase 3: Cash Drawer & Reports (Week 2)

**Priority: MEDIUM**

#### 3.1 Cash Drawer Events
- [ ] `POST /api/pos-devices/{id}/cash-drawer/open` - Log 13005
- [ ] `POST /api/pos-devices/{id}/cash-drawer/close` - Log 13006
- [ ] Require active session for drawer operations

#### 3.2 Report Generation
- [ ] `POST /api/pos-sessions/{id}/x-report` - Log 13008, return summary
- [ ] `POST /api/pos-sessions/{id}/z-report` - Log 13009, close session
- [ ] Generate report data with event logging

**Deliverables:**
- Cash drawer event tracking
- X and Z report generation with event logging

---

### Phase 4: Payment Method Events (Week 2-3)

**Priority: MEDIUM**

#### 4.1 Payment Event Logging
- [ ] Update charge creation to log payment method:
  - Cash → 13016
  - Card → 13017
  - Mobile → 13018
  - Other → 13019
- [ ] Link payment events to transaction events

**Deliverables:**
- Complete payment method tracking
- Payment events linked to transactions

---

### Phase 5: SAF-T Integration (Week 3)

**Priority: HIGH**

#### 5.1 Update SAF-T Generator
- [ ] Include `PosEvent` records in SAF-T XML
- [ ] Map events to SAF-T event structure
- [ ] Ensure chronological ordering
- [ ] Validate event completeness

#### 5.2 Event Validation
- [ ] Check for required events in sessions
- [ ] Generate warnings for missing events
- [ ] Compliance validation before SAF-T export

**Deliverables:**
- SAF-T XML includes all events
- Event validation and compliance checking

---

### Phase 6: Advanced Features (Week 4+)

**Priority: LOW**

#### 6.1 Void & Correction
- [ ] `POST /api/charges/{id}/void` - Log 13014
- [ ] `POST /api/charges/{id}/correction` - Log 13015

#### 6.2 Optional Events
- [ ] Price override (13022)
- [ ] Discount applied (13023)
- [ ] Tax exemption (13024)
- [ ] Receipt reprint (13025)

**Deliverables:**
- Complete event coverage
- Advanced transaction management

---

## Frontend Integration Requirements

### Application Start Flow
```javascript
// 1. App initializes
async function onAppStart(deviceId) {
  // 2. Log application start
  await api.post(`/pos-devices/${deviceId}/start`);
  
  // 3. Check for open session
  const session = await api.get('/pos-sessions/current', {
    params: { pos_device_id: deviceId }
  });
  
  // 4. If no session, prompt user to open one
  if (!session.data.session) {
    // Show "Open Session" prompt
  }
}
```

### User Login Flow
```javascript
async function login(email, password, deviceId) {
  // 1. Authenticate user
  const auth = await api.post('/auth/login', { email, password });
  
  // 2. Backend automatically logs 13003 event
  // (handled in AuthController)
  
  // 3. Get or create session
  const session = await getOrCreateSession(deviceId);
  
  return { auth, session };
}
```

### Transaction Flow
```javascript
async function createSale(items, paymentMethod, sessionId) {
  // 1. Create charge
  const charge = await api.post('/charges', {
    amount: calculateTotal(items),
    payment_method: paymentMethod,
    pos_session_id: sessionId,
    // ... other data
  });
  
  // 2. Backend automatically logs:
  //    - 13012 (Sales receipt)
  //    - 13016-13019 (Payment method)
  
  // 3. Return charge with receipt
  return charge;
}
```

### Cash Drawer Flow
```javascript
async function openCashDrawer(deviceId, sessionId) {
  // 1. Log drawer open event
  await api.post(`/pos-devices/${deviceId}/cash-drawer/open`, {
    pos_session_id: sessionId
  });
  
  // 2. Backend logs 13005 event
  
  // 3. Trigger physical drawer (hardware integration)
  // (handled by POS hardware SDK)
}
```

### Session Close Flow
```javascript
async function closeSession(sessionId, actualCash) {
  // 1. Generate Z report
  const zReport = await api.post(`/pos-sessions/${sessionId}/z-report`, {
    actual_cash: actualCash
  });
  
  // 2. Backend automatically:
  //    - Logs 13009 (Z report)
  //    - Logs 13021 (Session closed)
  //    - Closes session
  
  return zReport;
}
```

## API Endpoint Specifications

### Event Logging

**POST** `/api/pos-events`
```json
{
  "event_code": "13012",
  "event_type": "transaction",
  "pos_session_id": 123,
  "related_charge_id": 456,
  "description": "Sales receipt #789",
  "event_data": {
    "receipt_number": "789",
    "items_count": 3
  },
  "occurred_at": "2025-11-26T10:30:00Z"
}
```

**Response:**
```json
{
  "event": {
    "id": 1,
    "event_code": "13012",
    "event_type": "transaction",
    "description": "Sales receipt #789",
    "occurred_at": "2025-11-26T10:30:00Z"
  }
}
```

### Application Lifecycle

**POST** `/api/pos-devices/{id}/start`
```json
{
  "event_data": {
    "app_version": "1.0.0",
    "device_info": {...}
  }
}
```

**POST** `/api/pos-devices/{id}/shutdown`
```json
{
  "event_data": {
    "reason": "user_action" | "system_error" | "timeout"
  }
}
```

### Cash Drawer

**POST** `/api/pos-devices/{id}/cash-drawer/open`
```json
{
  "pos_session_id": 123,
  "reason": "sale" | "manual" | "count"
}
```

**POST** `/api/pos-devices/{id}/cash-drawer/close`
```json
{
  "pos_session_id": 123
}
```

### Reports

**POST** `/api/pos-sessions/{id}/x-report`
```json
{
  "include_details": true
}
```

**Response:**
```json
{
  "report": {
    "session_id": 123,
    "session_number": "000001",
    "opened_at": "2025-11-26T08:00:00Z",
    "transactions_count": 45,
    "total_amount": 150000,
    "cash_amount": 50000,
    "card_amount": 100000,
    "summary": {...}
  },
  "event": {
    "event_code": "13008",
    "occurred_at": "2025-11-26T14:00:00Z"
  }
}
```

**POST** `/api/pos-sessions/{id}/z-report`
```json
{
  "actual_cash": 52000,
  "closing_notes": "End of shift"
}
```

**Response:**
```json
{
  "session": {
    "id": 123,
    "status": "closed",
    "closed_at": "2025-11-26T17:00:00Z",
    "cash_difference": 2000
  },
  "report": {
    "total_transactions": 45,
    "total_amount": 150000,
    "summary": {...}
  },
  "events": [
    {
      "event_code": "13009",
      "description": "Z report"
    },
    {
      "event_code": "13021",
      "description": "Session closed"
    }
  ]
}
```

## Data Flow Diagrams

### Event Creation Flow
```
Frontend Action
    ↓
API Endpoint
    ↓
Controller
    ↓
Event Logger (Service/Action)
    ↓
PosEvent::create()
    ↓
Observer/Listener (if auto-logging)
    ↓
Database
```

### SAF-T Generation Flow
```
Request SAF-T Export
    ↓
Get Date Range
    ↓
Load Sessions
    ↓
Load Events (for each session)
    ↓
Load Charges (for each session)
    ↓
Generate XML
    ↓
Include Events in XML
    ↓
Return/Download
```

## Testing Strategy

### Unit Tests
- [ ] PosEvent model relationships
- [ ] Event code constants
- [ ] Event scopes and queries

### Integration Tests
- [ ] Event logging API endpoints
- [ ] Auto-logging observers
- [ ] SAF-T generation with events

### E2E Tests
- [ ] Complete transaction flow with events
- [ ] Session open/close with events
- [ ] SAF-T export validation

## Compliance Checklist

- [ ] All PredefinedBasicID-13 codes mapped
- [ ] Events cannot be deleted (immutable)
- [ ] Events include timestamps
- [ ] Events include user information
- [ ] Events linked to sessions
- [ ] Events included in SAF-T export
- [ ] Event validation before export
- [ ] Complete audit trail

## Risk Mitigation

### Missing Events
**Risk:** Required events not logged
**Mitigation:** 
- Automatic logging where possible
- Validation before SAF-T export
- Frontend integration requirements

### Performance
**Risk:** Too many events slow down system
**Mitigation:**
- Indexed database queries
- Batch event creation where possible
- Async event logging for non-critical events

### Data Integrity
**Risk:** Events not linked correctly
**Mitigation:**
- Foreign key constraints
- Validation in models
- Transaction rollback on errors

## Timeline

- **Week 1:** Phase 1 & 2 (Core infrastructure + App/User events)
- **Week 2:** Phase 3 & 4 (Drawer/Reports + Payment events)
- **Week 3:** Phase 5 (SAF-T integration)
- **Week 4+:** Phase 6 (Advanced features)

## Success Criteria

1. ✅ All required PredefinedBasicID-13 events are logged
2. ✅ Events are automatically logged where possible
3. ✅ SAF-T export includes all events
4. ✅ Frontend can easily integrate event logging
5. ✅ System passes compliance validation
6. ✅ Performance is acceptable (<100ms for event logging)

