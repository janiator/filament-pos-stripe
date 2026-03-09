# Cash Drawer Disabled Per Device (Non-Cash Only)

This document describes backend support and frontend integration for POS devices that have the cash drawer disabled (non-cash transactions only).

## Backend support

- Each POS device has a **cash_drawer_enabled** (boolean) setting. Default is `true`.
- When **cash_drawer_enabled** is `false` for a device:
  - **Purchase and complete-deferred:** Cash as payment method is **rejected** with HTTP **422** and a clear message (e.g. "Cash payments are not allowed on this device (cash drawer disabled).").
  - **Cash-drawer open/close:** `POST /pos-devices/{id}/cash-drawer/open` and `POST /pos-devices/{id}/cash-drawer/close` return HTTP **403** with message "Cash drawer is disabled for this device."
  - Non-cash payments, session cash withdrawal/deposit, and all other flows work as before.

## Device struct

The struct used for **activePosDevice** (e.g. DevicesStruct) must include **cash_drawer_enabled** (bool). It is filled from:

- `GET /pos-devices` (list)
- `GET /pos-devices/{id}` (show)
- `POST /pos-devices` (register) response
- `PUT/PATCH /pos-devices/{id}` (update) response

Ensure the FlutterFlow/API schema adds this field so the app can branch on it.

## Where to read the flag

- From **FFAppState().activePosDevice** once the struct has **cash_drawer_enabled**.
- Or from the response of `GET /pos-devices` or `GET /pos-devices/{id}`.

## Payment methods

- **Option A:** Call `GET /purchases/payment-methods?pos_device_id={id}` when the active device is known. For devices with cash drawer disabled, cash is omitted from the response. Use the returned list as the only payment methods to show.
- **Option B:** Keep loading payment methods as today and **filter in UI:** when `activePosDevice.cash_drawer_enabled == false`, hide methods with `provider == 'cash'` (and optionally `code == 'cash'`). Implement at least Option B so the cash option is hidden on drawer-disabled devices.

## Purchase flows

- Existing error dialogs already show `$.message` on 422. The backend returns a user-friendly message; no change strictly required.
- Optionally detect the message (e.g. "Cash is not allowed on this device") to show a friendlier title or icon.

## Drawer calls (important)

When **cash_drawer_enabled == false**:

1. **Do not** call **ReceiptPrinterGroup.openDrawerCall** (printer SOAP open). Today `checkout_flow_widget` calls it unconditionally on cash "Fullfør handel"; the app should skip it when the device has no drawer (and ideally only call printer open after a successful purchase).
2. **Do not** call the backend cash-drawer open/report (**reportNullinnstalCall** / `POST pos-devices/{id}/cash-drawer/open`), e.g. in `pos_periodic_actions_service`. The backend returns 403 if called.

**References:** checkout_flow_widget (cash button), deferred_payment_checkout (after success), orders_widget, pos_periodic_actions_service.

## Reference

- [FlutterFlow Purchase Action Implementation](FLUTTERFLOW_PURCHASE_ACTION_IMPLEMENTATION.md) — where to plug in the device check and payment flow.
