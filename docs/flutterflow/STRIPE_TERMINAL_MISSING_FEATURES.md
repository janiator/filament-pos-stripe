# Stripe Terminal: Features/Fixes Now Missing After Simplification

After reverting to the simple widget + modal and adding only minimal fixes (token refresh via modal, `onNeedNewToken`, app state update on disconnect), the following are **no longer implemented**. The docs in `STRIPE_TERMINAL_CONNECTION_TOKEN.md` and `STRIPE_TERMINAL_SINGLETON_RESCAN.md` still describe the old, richer flow and are **out of date** relative to the current code.

---

## 1. Token: “Already redeemed” when connecting (second use)

- **Previously:** The widget passed a **token provider** to `ensureInit()` that **fetched a new token from the API every time** the SDK asked (init + connect), so the “connection token has already been redeemed” error was avoided when connecting.
- **Now:** The widget uses a single `connectionToken` for both init and connect. If the SDK asks for a token again during connect, the singleton may return the same token → “already redeemed”.
- **Still needed if:** You see “already redeemed” when tapping a reader to connect (not on rescan). Fix: either the singleton always calls the token provider when the SDK asks, or the parent/modal supplies a fresh token for each “session” and the SDK is only inited once per token.

---

## 2. Location must match token (“Location parameter does not match the Connection Token”)

- **Previously:** The widget used **only the location returned in the token response** for `discoverReaders()`, so the location always matched the token.
- **Now:** Discovery uses `widget.locationId` (whatever the parent passed). If that ever differs from the location the backend used when creating the token, Stripe can throw the above error.
- **Still needed if:** You have multiple terminal locations or the parent might pass a different location than the one used for the token. Fix: parent must pass the **same** `locationId` that the backend returned with the token (or the backend returns `location` in the token response and the parent passes that as `locationId`).

---

## 3. Location-first flow and location picker in the modal

- **Previously:** Modal called `GET /terminals/locations` (optional `device_identifier`), showed a **location picker** when there were multiple locations, auto-selected when there was one location or `last_connected`, then requested a token for the **selected location** and showed the connector with that location.
- **Now:** Modal either uses app state token/location or fetches **one** token (no `location_id` in the request → backend uses default/single location). No location picker, no locations API.
- **Missing:** Location list from API, user choice when multiple locations, and token request tied to the chosen location.

---

## 4. Last-connected terminal and auto-reconnect

- **Previously:**  
  - `deviceIdentifier` → `GET /terminals/locations?device_identifier=...` returned `last_connected` (location + reader).  
  - Modal could pre-select that location and the connector could **auto-connect to the last-used reader**.  
  - `posDeviceId` → after connect, the app called `PATCH /pos-devices/{id}` with `last_connected_terminal_location_id` and `last_connected_terminal_reader_id`.
- **Now:** No `deviceIdentifier`, no `posDeviceId`, no `last_connected`, no auto-connect, no saving last-connected to the POS device.
- **Missing:** Same-device “reconnect to last terminal” and persistence of last-connected per device.

---

## 5. “Søk på nytt” without modal refresh (rescan hang / same token)

- **Previously:** Connector called **`resetForRescan()`** on the singleton before re-init so the SDK re-applied the token; connector could fetch a **new token** itself and run init again (or parent refreshed and passed new token).
- **Now:** If the modal passes **`onNeedNewToken`** (when it has apiBaseUrl/authToken/storeSlug), rescan triggers a token refresh and rebuild → new token and re-init. If **no** `onNeedNewToken` (e.g. token from app state only), rescan re-uses the same token → “already redeemed” or hang if the singleton doesn’t re-init.
- **Missing when not using API in modal:** Guaranteed fresh token and/or singleton reset on rescan, and no reliance on modal refresh.

---

## 6. Timeouts (spinner never stops)

- **Previously:** Init had a 20s timeout; full rescan had a 45s timeout; discovery had a 25s timeout so the spinner could not run forever.
- **Now:** No timeouts. If `ensureInit()` or `discoverReaders()` never completes, the spinner runs indefinitely.
- **Missing:** Timeouts (or equivalent) so the user gets an error and can retry (e.g. close and reopen) instead of an infinite spinner.

---

## 7. Optional parameters on the widget

- **Previously:** Widget had optional `apiBaseUrl`, `authToken`, `storeSlug`, `posDeviceId`, `deviceIdentifier`, `autoconnect` and could fetch token, resolve location, save last-connected, auto-connect to a preferred reader.
- **Now:** Widget only has `connectionToken`, `locationId`, `width`, `height`, and `onNeedNewToken`. All token/location/device logic is in the parent or app state.
- **Missing:** Any of the above behaviors inside the widget; they would need to be re-implemented in the modal or elsewhere.

---

## 8. Register reader from code / re-registration

- **Previously:** Widget had logic (and API call) to register a reader from a registration code and handle “reader at different location” (re-registration).
- **Now:** No registration-from-code or re-registration flow in the widget.
- **Missing:** In-widget registration and re-registration; would need to be done elsewhere if required.

---

## 9. Documentation

- **`STRIPE_TERMINAL_CONNECTION_TOKEN.md`** and **`STRIPE_TERMINAL_SINGLETON_RESCAN.md`** still describe the old flow (connector fetches token, location-first, token provider, resetForRescan, timeouts, deviceIdentifier, posDeviceId, etc.).
- **Gap:** They no longer match the current, simplified implementation and will confuse anyone reading them. They should be updated to describe the current modal + widget (app state or modal-fetched token, optional `onNeedNewToken`, no location-first, no singleton reset in code, no timeouts) or moved/archived.

---

## Summary

| Area | Status |
|------|--------|
| Token “already redeemed” on **connect** (second use) | Not fixed in widget; depends on singleton/parent |
| Location must match token | Parent must pass correct `locationId`; no “use token response only” in widget |
| Location-first + location picker | Removed from modal |
| Last-connected + auto-reconnect + save to POS device | Removed |
| Rescan with fresh token (when parent doesn’t pass API) | Only works if modal passes `onNeedNewToken` |
| Timeouts (init / rescan / discovery) | Removed; spinner can hang |
| Widget optional params (device, auto-connect, save) | Removed |
| Register from code / re-registration | Removed |
| Docs | Out of date vs current code |

You can re-add any of these incrementally (e.g. timeouts first, then location-from-token, then token provider for connect, then location-first modal, etc.) depending on what you need in production.
