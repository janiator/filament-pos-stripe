# Cash Drawer Disabled Per Device — Handoff Report for Frontend Agent

**Feature:** Disable cash drawer for a POS device (non-cash transactions only)  
**Branch:** `feature/cash-drawer-disabled-per-device`  
**Status:** Backend and docs complete; frontend implementation pending.

---

## 1. Summary

The backend supports a per-device setting **cash_drawer_enabled** (boolean, default `true`). When set to `false` for a POS device:

- Only **non-cash** payment methods are allowed for purchases and for completing deferred payments on that device’s session.
- Cash payment attempts return **HTTP 422** with a clear, user-facing message.
- Cash-drawer **open** and **close** API calls for that device return **HTTP 403**; no drawer events (13005/13006) are created.
- Session-level cash withdrawal/deposit and all other flows (card, Vipps, etc.) are unchanged.

Implemented changes:

- **Database:** `pos_devices.cash_drawer_enabled` (boolean, default true).
- **API:** Device list/show, register, and update expose and accept `cash_drawer_enabled`. Purchase and complete-deferred enforce “no cash” and return 422; cash-drawer open/close return 403 when disabled.
- **Payment methods:** `GET /purchases/payment-methods?pos_device_id={id}` excludes cash when the device has cash drawer disabled.
- **Filament:** POS Device form has a “Cash drawer enabled” toggle; infolist shows the value.
- **Docs:** Compliance and architecture docs updated; [CASH_DRAWER_DISABLED_DEVICES.md](CASH_DRAWER_DISABLED_DEVICES.md) describes backend behaviour and frontend integration.

---

## 2. API contract

### 2.1 `cash_drawer_enabled` on POS device

| Endpoint | Request | Response |
|----------|---------|----------|
| `GET /api/pos-devices` | — | Each device object includes `cash_drawer_enabled` (boolean). |
| `GET /api/pos-devices/{id}` | — | Device object includes `cash_drawer_enabled` (boolean). |
| `POST /api/pos-devices` (register) | Optional body: `cash_drawer_enabled` (boolean). Omitted => `true`. | Created device includes `cash_drawer_enabled`. |
| `PUT /api/pos-devices/{id}` | Optional body: `cash_drawer_enabled` (boolean). | Updated device includes `cash_drawer_enabled`. |
| `PATCH /api/pos-devices/{id}` | Optional body: `cash_drawer_enabled` (boolean). | Updated device includes `cash_drawer_enabled`. |

- **Type:** boolean  
- **Default:** `true`  
- **When false:** Only non-cash transactions are allowed on this device; cash-drawer open/close are disabled.

### 2.2 Purchase and complete-deferred (cash rejected)

When the POS session’s device has `cash_drawer_enabled === false` and the user attempts a **cash** payment:

- **Endpoints:**  
  - `POST /api/purchases` (single or split payment with cash)  
  - `POST /api/purchases/{id}/complete-payment` (completing a deferred payment with cash)
- **Response:** **HTTP 422**
- **Body (example):**
  ```json
  {
    "success": false,
    "message": "Cash payments are not allowed on this device (cash drawer disabled)."
  }
  ```
- **Recommendation:** Show this `message` in the existing error dialog; optionally use a title like “Cash is not allowed on this device.”

### 2.3 Cash-drawer open/close (disabled device)

When the device has cash drawer disabled:

- **Endpoints:**  
  - `POST /api/pos-devices/{id}/cash-drawer/open`  
  - `POST /api/pos-devices/{id}/cash-drawer/close`
- **Response:** **HTTP 403**
- **Body (example):**
  ```json
  {
    "message": "Cash drawer is disabled for this device."
  }
  ```
- No 13005/13006 events are created for that device.

### 2.4 Payment methods filtered by device

- **Endpoint:** `GET /api/purchases/payment-methods`
- **Query (optional):** `pos_device_id` (integer) — POS device ID for the current device.
- **Behaviour:** If `pos_device_id` is present and that device has `cash_drawer_enabled === false`, the response **excludes** cash payment methods. Otherwise, behaviour is unchanged (all enabled, POS-suitable methods).
- **Use case:** Call this when the active device is known (e.g. after login/device registration) and use the returned list so the UI never shows cash for drawer-disabled devices (Option A in [CASH_DRAWER_DISABLED_DEVICES.md](CASH_DRAWER_DISABLED_DEVICES.md)).

---

## 3. Backend behaviour (short)

- **Rejected when device has cash drawer disabled:**  
  - Completing a purchase (single or split) with cash.  
  - Completing a deferred payment with cash.  
  - Opening or closing the cash drawer via API for that device.
- **Unchanged:**  
  - Non-cash payments (card, Vipps, etc.) on that device.  
  - Session cash withdrawal/deposit.  
  - Devices with `cash_drawer_enabled === true`.  
  - Filament: “Cash drawer enabled” can be toggled per device in the POS Device form.

---

## 4. Frontend checklist

Use this list together with [CASH_DRAWER_DISABLED_DEVICES.md](CASH_DRAWER_DISABLED_DEVICES.md).

1. **Device struct**  
   Add **cash_drawer_enabled** (bool) to the struct used for `activePosDevice` (e.g. DevicesStruct). Ensure it is filled from GET/register/update device responses.

2. **Payment methods**  
   - **Option A:** When the active device is known, call `GET /purchases/payment-methods?pos_device_id={id}` and use the returned list (cash omitted when drawer disabled).  
   - **Option B (minimum):** When `activePosDevice.cash_drawer_enabled === false`, filter `availablePaymentMethods` so methods with `provider == 'cash'` (and optionally `code == 'cash'`) are hidden.  
   Implement at least Option B so cash is not offered on drawer-disabled devices.

3. **Drawer calls**  
   When `cash_drawer_enabled === false`:  
   - Do **not** call **ReceiptPrinterGroup.openDrawerCall**.  
   - Do **not** call the backend cash-drawer open/report (e.g. reportNullinnstalCall).  
   Reference: checkout_flow_widget (cash button), deferred_payment_checkout (after success), orders_widget, pos_periodic_actions_service.

4. **422 handling**  
   Existing dialogs that show `$.message` on error will already show the backend message. Optionally handle the string “Cash payments are not allowed on this device (cash drawer disabled).” or “Cash is not allowed on this device” for a friendlier title.

5. **Reference**  
   See [FLUTTERFLOW_PURCHASE_ACTION_IMPLEMENTATION.md](FLUTTERFLOW_PURCHASE_ACTION_IMPLEMENTATION.md) for where to plug in the device check and payment flow.

---

## 5. Testing notes (API)

- Create a POS device with `cash_drawer_enabled: false` (via Filament or `PATCH /api/pos-devices/{id}`).
- Open a session on that device.  
  - `POST /api/purchases` with `payment_method_code: "cash"` → **422** and message above.  
  - `POST /api/purchases` with a non-cash method (e.g. Vipps) → **201**.  
  - `POST /api/pos-devices/{id}/cash-drawer/open` → **403**.  
  - `GET /api/purchases/payment-methods?pos_device_id={id}` → response `data` does not include a cash method.
- For deferred: create a pending charge, then `POST /api/purchases/{charge_id}/complete-payment` with `payment_method_code: "cash"` and the same session → **422**.

No Flutter test code is required in this report; the above is for manual or API-level verification.
