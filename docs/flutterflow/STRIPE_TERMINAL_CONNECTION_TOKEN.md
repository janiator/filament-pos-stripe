# Where the Stripe Terminal connection token comes from

**API base URL:** You can pass `apiBaseUrl` without the `/api` prefix (e.g. `https://your-domain.com`). The custom code appends `/api` when calling the backend, so requests go to `https://your-domain.com/api/terminals/locations`, etc. If you already pass a base that ends with `/api`, it is left as-is.

The message **"Tilkoblingstoken mangler"** is shown when the modal cannot show the terminal picker (e.g. API credentials are not passed, or no location is available). **No connection token is passed in** — the modal and connector work only with **apiBaseUrl**, **authToken**, and **storeSlug**. The connector **StripeInternetTerminalReaderPickerAndConnector** fetches a fresh connection token at the start of init (Stripe tokens are single-use), so "Søk på nytt" and reopening the modal always use a new token.

## Backend (Laravel)

The token is created by:

- **Endpoint:** `POST /api/stores/{store}/terminal/connection-token`
- **Controller:** [app/Http/Controllers/Stores/StoreTerminalConnectionTokenController.php](app/Http/Controllers/Stores/StoreTerminalConnectionTokenController.php)
- **Route:** `stores.terminal.connection-token` in [routes/api.php](routes/api.php)

The controller:

1. Finds the store by slug (`{store}` in the URL).
2. Resolves the terminal location (optional body `location_id`, or the store’s single location).
3. Calls `$storeModel->createConnectionToken(['location' => $location->stripe_location_id], true)` (Stripe Connect).
4. Returns JSON with `secret` (connection token) and `location` (Stripe location ID, e.g. `tml_xxx`) so the client can update app state.

So the **backend** creates the token; the **connector** calls this endpoint when it needs a token.

## Flutter / FlutterFlow app

**You do not pass a connection token** to the terminal modal or connector. Pass **apiBaseUrl**, **authToken**, and **storeSlug** to **stripeTerminalSelectorModal**; the modal fetches locations, and when the user (or auto) selects a location, it shows **StripeInternetTerminalReaderPickerAndConnector** with **locationId** only. The connector fetches a fresh token and may update `FFAppState().stripeConnectionToken` / `FFAppState().stripeLocationId` for use elsewhere in the app. The token provider passed to the SDK **fetches a new token every time** the SDK requests one (init and connect), and never returns a cached token so redeems stay valid. At the start of init the connector calls **resetForRescan()** on the singleton (if present) so the SDK always re-initializes with the new token instead of skipping with a stale "already inited" state.

## Location-first flow and auto-reconnect

When opening the terminal modal with **stripeTerminalSelectorModal**, pass `apiBaseUrl`, `authToken`, `storeSlug`, and optionally:

- **deviceIdentifier** — Your POS device identifier (from device registration). The app will call `GET /terminals/locations?device_identifier=...`. The API returns `last_connected` (location and reader) for this device so the modal can auto-select the last-used location and try to auto-connect to the last-used reader.
- **posDeviceId** — Your POS device id (from registration/GET pos-devices). After a reader is connected, the app will call `PATCH /pos-devices/{posDeviceId}` with `last_connected_terminal_location_id` and `last_connected_terminal_reader_id` so the next time you open the modal the same device can auto-reconnect.

Flow: 1) Modal fetches terminal locations (with `device_identifier` if provided). 2) If the store has one location, it is auto-selected; if multiple, the user picks one (last-used is pre-selected when `last_connected` is present). 3) The connector is shown (optionally with the selected location’s Stripe location ID as **locationId**). It passes a **token provider** to the Stripe Terminal singleton that **fetches a new token from the API every time the SDK requests one** (init and connect each use a token, so "already been redeemed" is avoided). 4) The connector uses **only the location from the token response** for Stripe SDK discovery. 5) After a successful connect, the app saves the last-connected terminal to the POS device when `posDeviceId` is provided.

## "Søk på nytt" (rescan) and StripeTerminalSingleton

When the user taps **Søk på nytt**, the connector disconnects the current reader, updates app state (`stripeReaderConnected = false`), then calls **`StripeTerminalSingleton.instance.resetForRescan()`** if that method exists, then fetches a new token and runs init + discovery again. Connection tokens are single-use, so the SDK must receive the new token on rescan. If your **StripeTerminalSingleton** caches "already inited" and does not re-apply the token provider when `ensureInit()` is called again, add a method **`void resetForRescan()`** that clears that cache so the next `ensureInit()` re-applies the connection token to the Stripe SDK. The connector calls it safely (no-op if the method is missing). A 45-second timeout applies to rescan so the spinner never runs forever; on timeout the user is asked to close and reopen the modal.

## Copying custom code into your project

When you copy the terminal modal or connector from `docs/flutterflow/custom-actions/` and `docs/flutterflow/custom-widgets/` into your Flutter app:

1. **No Supabase** — If your project does not use Supabase, remove or comment out the line `import '/backend/supabase/supabase.dart';` in both `stripe_terminal_selector_modal.dart` and `stripe_internet_terminal_reader_picker_and_connector.dart` (in the automatic FlutterFlow imports block at the top).
2. **Locations and readers from API** — The connector fetches everything from the API: it calls `GET /terminals/locations` to resolve internal location id (for token requests and saving last-connected) and to get readers for the current location. For auto-connect it calls `GET /terminals/locations?device_identifier=X` to get `last_connected.stripe_reader_id`. Pass **deviceIdentifier** and **autoconnect** to the widget instead of passing in location id or preferred reader.
3. **StripeTerminalSingleton** — For "Søk på nytt" to work without an infinite spinner, implement **`void resetForRescan()`** on your singleton so the next `ensureInit()` re-applies the connection token to the Stripe SDK (see section above).
