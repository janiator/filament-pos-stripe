# PR #29 (dev → main): API-based POS impact for existing stores

This document summarizes changes that may affect the API-based POS (e.g. FlutterFlow app) for existing stores.

## PR link

**https://github.com/janiator/filament-pos-stripe/pull/29**

---

## Potential breaking / behavior changes

### 1. **Terminal connection token response shape** — verify Flutter app

- **Before (main):** `POST /api/stores/{store}/terminal/connection-token` returned the **full Stripe connection token object** (e.g. `json($connectionToken)` — typically includes `object`, `secret`, and possibly other Stripe fields).
- **After (dev):** Returns only `{ "secret": "...", "location": "tml_xxx" }`.

**Impact:** If the Flutter app only uses `secret` for the Terminal SDK, this is **safe**. If it reads or forwards any other key (e.g. `object`), or expects the raw Stripe shape, update the app to use only `secret` and optionally `location`. The API spec already documents this shape.

---

### 2. **POS session open: device conflict across stores** — new 409 case

- **Before:** Opening a session failed with 409 only when the **same store** already had an open session on that device.
- **After:** Opening a session also returns **409** when the device has an open session on **another store**, with message: *"You need to close other open POS sessions on the current device before opening a new session."*

**Impact:** Existing apps that show a generic “device already has open session” message will still work. If the app parses the **message** and shows it to the user, the new text is appropriate. No change required unless the app had store-specific handling; then it should treat both 409 cases the same (prompt to close the other session).

---

## Additive / backward-compatible changes

These only add fields or optional behavior; existing clients can ignore them.

| Area | Change | Notes |
|------|--------|--------|
| **POS devices** | `device_identifier` is unique **per store** (not globally). Same device can be registered on multiple stores. | Registration validation relaxed; existing single-store usage unchanged. |
| **POS devices** | New optional fields: `last_connected_terminal_location_id`, `last_connected_terminal_reader_id` (update), and response `last_connected` (object). | Additive; optional for auto-reconnect. |
| **POS sessions** | `session_number` is unique **per store** (not globally). | DB constraint change only; API contract unchanged. |
| **Products** | Product list/detail now includes `article_group_code`. | Additive. |
| **Terminal locations** | Optional query `device_identifier`; response may include `last_connected`. | Additive. |
| **Terminal readers** | Each reader now includes `serial_number`. | Additive. |
| **Stores** | New `address` field (DB + model). | Additive; no existing API response changes required. |
| **Receipts** | Copy receipt keeps original receipt’s order date instead of print time. | Behavior change for copy content only; endpoints and status codes unchanged. |

---

## New API surface

- **`POST /api/terminals/readers/register-from-code`** — Register a reader from registration code; optional for stores that don’t use reader re-registration.

---

## Migrations (existing stores)

- **pos_devices:** `device_identifier` unique per store; new columns `last_connected_terminal_location_id`, `last_connected_terminal_reader_id` (nullable).
- **pos_sessions:** `session_number` unique per store.
- **terminal_readers:** `serial_number` (nullable).
- **stores:** `address` (nullable).
- **connected_charges:** `event_ticket_processed` (default false).
- New tables: `event_tickets`, `addons`, `notifications`, etc. (no impact on existing POS API usage.)

All of these are additive or constraint relaxations; no breaking schema change for existing API-based POS flows.

---

## Recommendation

1. **Confirm Flutter/API client** only uses `secret` (and optionally `location`) from the connection-token response; if it uses the full object, update to the new shape before or at release.
2. **Handle 409 on session open** with the new message for “other store” conflict; treat like existing “device already has session” (e.g. show message and require closing the other session).
3. Run **smoke tests** for: login, store/device context, open/close session, connection token, terminal discovery, and one purchase flow.
