# POS Session Management (Kassasystemforskriften Compliance)

## Overview

This system implements POS session management to comply with Norwegian Kassasystemforskriften (Cash Register System Regulation). It tracks cash register sessions, links transactions to sessions, and generates daily closing reports.

## Data Models

### PosSession
Represents a cash register session (kassesesjon).

**Key Fields:**
- `session_number`: Sequential session number per store (e.g., "000001")
- `status`: `open` or `closed`
- `opened_at`: When the session was opened
- `closed_at`: When the session was closed
- `opening_balance`: Starting cash balance in cents
- `expected_cash`: Calculated expected cash from transactions
- `actual_cash`: Actual cash counted at closing
- `cash_difference`: Difference between expected and actual
- `pos_device_id`: The POS device this session belongs to
- `user_id`: The user/cashier who opened the session

**Relationships:**
- Belongs to `Store`
- Belongs to `PosDevice` (optional)
- Belongs to `User` (who opened it)
- Has many `ConnectedCharge` (transactions)

### PosSessionClosing
Represents a daily closing report (dagsavslutning).

**Key Fields:**
- `closing_date`: Date of the closing report
- `total_sessions`: Number of sessions closed
- `total_transactions`: Total number of transactions
- `total_amount`: Total amount in cents
- `total_cash`: Total cash payments
- `total_card`: Total card payments
- `total_refunds`: Total refunds
- `summary_data`: Detailed breakdown (by payment method, by session)
- `verified`: Whether closing has been verified
- `verified_by_user_id`: User who verified the closing

**Relationships:**
- Belongs to `Store`
- Belongs to `User` (who created it)
- Belongs to `User` (who verified it)

### ConnectedCharge
Now includes `pos_session_id` to link transactions to sessions.

#### When can a purchase (ConnectedCharge) have `pos_session_id = null`?

A charge may have `pos_session_id = null` in these cases:

1. **Stripe charge webhook** – `HandleChargeWebhook` creates or updates a charge from Stripe events (`charge.created`, `charge.succeeded`, etc.). It looks up by `stripe_payment_intent_id` first when present; when a row is found, it updates that row with webhook data and **preserves** `pos_session_id` and other POS fields (transaction_code, payment_code, tip_amount, article_group_code), so webhook and POS-created charges are merged without duplicates or lost session. When it **creates** a new record (no existing row by payment_intent_id or stripe_charge_id), the record has only Stripe fields and no `pos_session_id`. That can happen when the webhook runs before the POS flow saves the charge, or when the charge was created outside the POS (e.g. Payment Link, Stripe Dashboard).

2. **Sync from Stripe** – `SyncConnectedChargesFromStripe` creates new `ConnectedCharge` rows for charges that exist in Stripe but not in the app. New rows are created with Stripe data only; `pos_session_id` is not set. Updates preserve existing `pos_session_id`.

3. **CreateConnectedChargeOnStripe** – Creating a charge directly on Stripe via this action (e.g. from Filament or another flow that does not use `PurchaseService`) creates a local record without `pos_session_id`.

4. **API transaction create with no session** – If the client creates a transaction/purchase and the current session is missing or not passed, the charge can be stored with `pos_session_id => $session?->id` (null).

5. **Race between POS and webhook** – POS creates a charge with `pos_session_id` and `stripe_charge_id` still null. Stripe confirms the payment and sends a webhook. The webhook creates a second record with `stripe_charge_id` set and `pos_session_id` null. The app then has two records for the same Stripe charge; the one from the webhook has null session. (PurchaseService has logic to delete such duplicates when completing deferred payments.)

**POS-style observer events** – `ConnectedChargeObserver` creates PosEvent records for sales receipts (13012), payment method (13016–13019), and return receipts (13013) **only when** the charge has a non-null `pos_session_id`. Webhook-only (and other non-POS) charges do not create these events, so the POS event journal is not polluted and Z-report event summaries are not inflated when orphaned charges are later linked by regenerate.

To keep Z-reports complete, **before generating a Z-report** (on close or on demand) the app runs `RegenerateZReports::attachMissingDataToSession()`, which links orphaned charges, receipts, and events to the session **only when** `session_number` is present in charge metadata, receipt `receipt_data`, or event `event_data`. It never links by date range or device+date alone, so online/non-POS sales are not attached to a POS session and transactions are not assigned to the wrong device when multiple devices have overlapping sessions.

## API Endpoints

### Session Management

#### `GET /api/pos-sessions`
List all sessions for the current store.

**Query Parameters:**
- `status`: Filter by status (`open`, `closed`)
- `date`: Filter by opening date
- `pos_device_id`: Filter by device
- `per_page`: Pagination (default: 20)

**Response:**
```json
{
  "sessions": [
    {
      "id": 1,
      "session_number": "000001",
      "status": "open",
      "opened_at": "2025-11-26T10:00:00Z",
      "opening_balance": 0,
      "transaction_count": 5,
      "total_amount": 150000,
      "pos_device": {...},
      "user": {...}
    }
  ],
  "meta": {...}
}
```

#### `GET /api/pos-sessions/current`
Get the current open session for a device.

**Query Parameters:**
- `pos_device_id` (required): The POS device ID

**Response:**
```json
{
  "session": {
    "id": 1,
    "session_number": "000001",
    "status": "open",
    "charges": [...],
    ...
  }
}
```

#### `POST /api/pos-sessions/open`
Open a new POS session.

**Request Body:**
```json
{
  "pos_device_id": 1,
  "opening_balance": 0,
  "opening_notes": "Morning shift",
  "opening_data": {}
}
```

**Response:**
```json
{
  "message": "Session opened successfully",
  "session": {...}
}
```

#### `POST /api/pos-sessions/{id}/close`
Close a POS session.

**Request Body:**
```json
{
  "actual_cash": 50000,
  "closing_notes": "End of shift",
  "closing_data": {}
}
```

**Response:**
```json
{
  "message": "Session closed successfully",
  "session": {...}
}
```

#### `POST /api/pos-sessions/{id}/cash-withdrawal`
Record a cash withdrawal (staff taking money out of the drawer). Only allowed for open sessions.

**Request Body:**
```json
{
  "amount": 5000,
  "reason": "Bank deposit"
}
```
- `amount` (required): Amount in øre (integer, min 1).
- `reason` (optional): String, max 500 characters.

**Response:** `201 Created` with created event (id, event_code 13028, event_data, occurred_at).

#### `POST /api/pos-sessions/{id}/cash-deposit`
Record a cash deposit (staff putting money into the drawer). Only allowed for open sessions.

**Request Body:**
```json
{
  "amount": 3000,
  "reason": "Change refill"
}
```
- `amount` (required): Amount in øre (integer, min 1).
- `reason` (optional): String, max 500 characters.

**Response:** `201 Created` with created event (id, event_code 13029, event_data, occurred_at). Withdrawals and deposits appear in X- and Z-reports (count, type, amount) and affect expected cash.

#### `GET /api/pos-sessions/{id}`
Get a specific session with all details.

**Response:**
```json
{
  "session": {
    "id": 1,
    "session_number": "000001",
    "status": "closed",
    "charges": [...],
    "cash_withdrawals": { "count": 0, "total_amount": 0 },
    "cash_deposits": { "count": 0, "total_amount": 0 },
    ...
  }
}
```

### Daily Closing Reports

#### `POST /api/pos-sessions/daily-closing`
Create a daily closing report for a specific date.

**Request Body:**
```json
{
  "closing_date": "2025-11-26",
  "notes": "Daily closing notes"
}
```

**Response:**
```json
{
  "message": "Daily closing created successfully",
  "closing": {
    "id": 1,
    "closing_date": "2025-11-26",
    "total_sessions": 3,
    "total_transactions": 45,
    "total_amount": 500000,
    "total_cash": 200000,
    "total_card": 300000,
    "summary_data": {
      "by_payment_method": {...},
      "by_session": [...]
    }
  }
}
```

## Workflow

### Opening a Session
1. Cashier opens POS app on device
2. App calls `POST /api/pos-sessions/open` with device ID
3. System creates session with sequential session number
4. Session is linked to device and user
5. All subsequent transactions are linked to this session

### Processing Transactions
1. When a charge is created, it should be linked to the current open session
2. Use `pos_session_id` field in `ConnectedCharge`
3. System automatically calculates `expected_cash` from cash transactions

### Closing a Session
1. Cashier counts actual cash
2. App calls `POST /api/pos-sessions/{id}/close` with actual cash amount
3. System calculates difference between expected and actual
4. Session status changes to `closed`
5. All transactions remain linked to the session

### Daily Closing
1. At end of day, manager creates daily closing report
2. App calls `POST /api/pos-sessions/daily-closing` with date
3. System aggregates all closed sessions for that date
4. Generates summary with totals and breakdowns
5. Closing can be verified by authorized user

## Integration with FlutterFlow

**Reducing orphan charges:** To reduce the chance that charges stay with `pos_session_id = null`, the Flutter app should wait for a successful response from `POST /api/purchases` (or the relevant purchase endpoint) before clearing the cart or navigating away, and show an error with retry on failure (timeout, 5xx, network error). That way the backend can run the “find by payment_intent_id and set pos_session_id” path when the webhook has already created the charge.

### Opening Session
```dart
// When POS app starts
final response = await api.call(
  'pos-sessions/open',
  method: 'POST',
  body: {
    'pos_device_id': deviceId,
    'opening_balance': 0,
  }
);
final session = response['session'];
```

### Linking Transactions
When creating a charge, include the current session ID:
```dart
final charge = await createCharge({
  'amount': amount,
  'currency': 'nok',
  'pos_session_id': currentSessionId, // Link to session
});
```

### Closing Session
```dart
final response = await api.call(
  'pos-sessions/$sessionId/close',
  method: 'POST',
  body: {
    'actual_cash': actualCashAmount,
    'closing_notes': 'End of shift',
  }
);
```

## Compliance Features

✅ **Session Tracking**: Every transaction is linked to a session
✅ **User Authentication**: Sessions track which user opened them
✅ **Audit Trail**: All sessions are logged with timestamps
✅ **Daily Closing Reports**: Automatic aggregation of daily totals
✅ **Cash Reconciliation**: Tracks expected vs actual cash
✅ **Transaction History**: Complete history per session
✅ **Device Tracking**: Links sessions to specific POS devices

## Next Steps

1. **Receipt Generation**: Generate receipts with session number
2. **Session Verification**: Add verification workflow for sessions
3. **Reporting**: Add Filament admin interface for viewing sessions
4. **Automatic Session Linking**: Auto-link charges to current session

