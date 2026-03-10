# Merano booking via POSitiv — plan (add-on, disabled by default)

This document is the implementation plan for Merano ticket booking in the POSitiv Flutter app. It incorporates: **Merano as an add-on (disabled by default)**, Filament Blueprint–aligned specs where applicable, and **risks/issues to be aware of before building** from a new branch off `dev`.

---

## 0. Merano booking as add-on (disabled by default)

- **Addon type:** Add a new enum case `MeranoBooking = 'merano_booking'` to [app/Enums/AddonType.php](app/Enums/AddonType.php), with `label()` and `description()` (e.g. "Merano Booking" / "Sell event tickets from POS via Merano; create and confirm bookings after payment.").
- **Default state:** The feature is **off** until a store has an active addon of type MeranoBooking. No addon row = disabled. Admins enable by creating an Addon (or toggling existing) with `type = merano_booking`, `is_active = true` in Filament (Addons resource or AddonsPage).
- **Gating:** All Merano proxy endpoints and device-level booking capability are gated on `Addon::storeHasActiveAddon($store->id, AddonType::MeranoBooking)`. When the addon is inactive, proxy returns 503 (or 404) with a clear message; `available_actions` on the device must **not** include `'booking'` even if `booking_enabled` is true.
- **available_actions:** Include `'booking'` only when **both** the store has the Merano addon active **and** the device has `booking_enabled === true`. In PosDevicesController when building `available_actions`, add `'booking'` only if `Addon::storeHasActiveAddon($device->store_id, AddonType::MeranoBooking) && $device->booking_enabled`.
- **AddonsPage:** In [app/Filament/Pages/AddonsPage.php](app/Filament/Pages/AddonsPage.php) `getPrimaryActionForType()`, add `AddonType::MeranoBooking => null` (no dedicated resource; Merano config lives in Store edit). Optionally add a card/description for Merano that tells the user to configure base URL and token in Store settings when the addon is active.

---

## 1. POSitiv backend: per-device booking and available actions

### 1.1 Database and model

- **Migration:** Add `booking_enabled` to `pos_devices` (boolean, default `false`).
- **Model:** Add `booking_enabled` to `PosDevice` fillable and casts (boolean).

### 1.2 API: device response and validation

- **PosDevicesController:** In the device payload (e.g. the array that includes `cash_drawer_enabled`):
  - Add `'booking_enabled' => (bool) $device->booking_enabled`.
  - Add `'available_actions'`: array of strings. Build as: start with `cash_drawer` if `$device->cash_drawer_enabled`; add `booking` only if `Addon::storeHasActiveAddon($device->store_id, AddonType::MeranoBooking) && $device->booking_enabled`. Example: `array_values(array_filter(['cash_drawer' => $device->cash_drawer_enabled, 'booking' => Addon::storeHasActiveAddon($device->store_id, AddonType::MeranoBooking) && $device->booking_enabled]))` — but use string keys and filter so you get `['cash_drawer', 'booking']` or subset.
- **Validation:** In register and update, accept `booking_enabled` (nullable boolean); default to `false` on create if omitted.

### 1.3 Filament (Blueprint-aligned)

- **PosDeviceForm** [app/Filament/Resources/PosDevices/Schemas/PosDeviceForm.php](app/Filament/Resources/PosDevices/Schemas/PosDeviceForm.php):
  - **Component:** `Filament\Forms\Components\Toggle`
  - **Docs:** https://filamentphp.com/docs/4.x/forms/toggle
  - **Validation:** not required (boolean)
  - **Config:** `Toggle::make('booking_enabled')->label('Booking enabled')->default(false)->helperText('When on, this device can sell event tickets via Merano (requires Merano add-on for the store).')`
  - Place in the same Section as `cash_drawer_enabled` (Device Information).
- **PosDeviceInfolist** [app/Filament/Resources/PosDevices/Schemas/PosDeviceInfolist.php](app/Filament/Resources/PosDevices/Schemas/PosDeviceInfolist.php):
  - **Component:** `Filament\Infolists\Components\TextEntry`
  - **Config:** `TextEntry::make('booking_enabled')->label('Booking enabled')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')` or use `->badge()` with boolean colors.

### 1.4 API spec and docs

- **api-spec.yaml:** In `PosDevice` schema add `booking_enabled` (boolean, default false) and `available_actions` (array of strings, read-only). In register/update request bodies add `booking_enabled` (optional boolean).
- **Docs:** Note in [docs/implementation/POS_DEVICE_ARCHITECTURE.md](docs/implementation/POS_DEVICE_ARCHITECTURE.md); Flutter handoff in `docs/flutterflow/` that the app shows booking only when `'booking' in available_actions`.

---

## 2. POSitiv backend: Merano proxy and store config

### 2.1 Store-level Merano config (only when addon active)

- **Migration:** Add to `stores`: `merano_base_url` (string, nullable), `merano_pos_api_token` (text, nullable). Store token encrypted using Laravel `Illuminate\Database\Eloquent\Casts\Encrypted` cast.
- **Store model:** Add to fillable (or guarded exclude); cast `merano_pos_api_token` to `Encrypted`.
- **Filament Store form** [app/Filament/Resources/Stores/Schemas/StoreForm.php](app/Filament/Resources/Stores/Schemas/StoreForm.php):
  - Add a **Section** "Merano integration" with:
    - **Component:** `Filament\Forms\Components\TextInput` for `merano_base_url`. **Validation:** nullable, url when present. **Config:** `->url()->placeholder('https://merano.example.com')->helperText('Merano API base URL (no trailing slash). Only used when Merano Booking add-on is active.')`
    - **Component:** `Filament\Forms\Components\TextInput` for `merano_pos_api_token`. **Validation:** nullable. **Config:** `->password()->revealable()->dehydrated(true)->helperText('POS API token from Merano (POS_API_TOKEN). Stored encrypted.')`
  - **Section visibility:** `->visible(fn ($record) => $record && \App\Models\Addon::storeHasActiveAddon($record->id, \App\Enums\AddonType::MeranoBooking))`
  - **Imports:** Add `Addon` and `AddonType` in the schema class where used.

### 2.2 Merano proxy routes and controller

- **Routes:** Under Sanctum-protected API (same prefix as existing POS API, e.g. with tenant), add group `merano/v1/`: GET events, POST events/{event}/availability, POST bookings, POST bookings/{booking}/release, POST bookings/{booking}/confirm-pos-payment.
- **Controller:**
  - Resolve store via `getTenantStore($request)`.
  - **Addon check:** If `!Addon::storeHasActiveAddon($store->id, AddonType::MeranoBooking)`, return 503 with body `{ "message": "Merano booking is not enabled for this store." }`.
  - Load Merano base URL and token; if either missing, return 503 "Merano is not configured for this store."
  - **Device check:** For requests that accept or imply a device (e.g. body/query `pos_device_id`), verify device belongs to store and has `booking_enabled`; else 403 "Booking is not enabled for this device."
  - Forward to Merano with `Authorization: Bearer {token}` and `X-POS-API-Token: {token}`; return Merano response status and body.

### 2.3 Seatmap URL for WebView

- POSitiv now exposes a wrapper route at `GET /booking/seatmap` that resolves the tenant store, checks Merano addon/config, optionally validates `posDeviceId`, and redirects to `{store.merano_base_url}/booking/seatmap` with the original query string.
- The wrapper appends `posToken` (store Merano POS token) when missing so Merano can require auth for the seatmap endpoint in POS flows.
- Required query parameters from FlutterFlow: `tenant`, `provider`, `action`; optional: `storageKey`, `posDeviceId`.
- Use this route as the default WebView URL from POSitiv mobile clients.

---

## 3. FlutterFlow: adapt Merano drafts

- Device struct: include `booking_enabled` and `available_actions`; show Booking action only when `'booking' in available_actions`.
- Seatmap WebView: adapt Merano draft; URL from POSitiv or Merano as per 2.3.
- Add ticket to cart: call POSitiv `POST .../merano/v1/bookings`, then existing addItemToCart (custom price, description, metadata `merano_booking_id`, `merano_booking_number`); release via POSitiv release endpoint.
- Confirm after payment: call POSitiv confirm-pos-payment for each cart item with `merano_booking_id` after successful POS purchase.
- Docs: MERANO_BOOKING_FLOW.md in docs/flutterflow/ with full flow; adapted Dart in docs/flutterflow/custom-actions/ and custom-widgets/.

---

## 4. Commands (Blueprint)

- No new Filament resource scaffold. New migration: `php artisan make:migration add_booking_enabled_to_pos_devices_table --no-interaction`; `php artisan make:migration add_merano_config_to_stores_table --no-interaction`. New controller: `php artisan make:controller Api/MeranoProxyController --no-interaction` (or equivalent).

---

## 5. Authorization

- Merano proxy: use same tenant auth as other API (user must have access to store). No new policy; device check is business logic (403 when device does not have booking_enabled or store does not have addon).
- Store form: Merano section is visible only when addon is active; any user who can edit the store can edit Merano config (no separate permission).

---

## 6. Tests

- **Addon:** AddonType::MeranoBooking exists; storeHasActiveAddon returns false when no addon or is_active false.
- **PosDevice:** Migration and model; API returns booking_enabled and available_actions; available_actions contains 'booking' only when store has Merano addon and device.booking_enabled; register/update accept booking_enabled.
- **Merano proxy:** 503 when addon inactive; 503 when addon active but URL/token missing; 403 when device provided and booking_enabled false; 200/201 and body passthrough when OK (mock HTTP client to Merano).
- **Store:** Merano fields stored and encrypted token; Filament section visibility when addon active (feature test or unit).

---

## 7. Risks and issues before building (from dev)

1. **Branch and migrations**
   - Create a new branch from `dev`; do **not** run `migrate:fresh` or `migrate:refresh` (project rules: tests use a separate DB; migrate:fresh can wipe main DB if env is wrong). Run new migrations only; ensure test suite uses `RefreshDatabase` or equivalent on the test DB.

2. **Addon default and deactivation**
   - New stores have **no** Merano addon row, so feature is off. When an admin deactivates the addon (`is_active = false`), proxy returns 503 and `available_actions` no longer includes `booking`. Merano config (URL, token) and device `booking_enabled` remain in DB; re-activating the addon restores behaviour without re-entry. Consider whether to clear or hide token in Store form when addon is inactive (current plan: section hidden, so token not editable when off).

3. **Token storage**
   - Use Laravel’s `Encrypted` cast for `merano_pos_api_token` so it is encrypted at rest. Key rotation: if APP_KEY changes, tokens must be re-entered (document in Store form helperText if needed).

4. **Filament Store form and tenant**
   - StoreResource is not tenant-scoped (Store is the tenant). The Merano section visibility uses `$record->id` (the store being edited). Ensure `$record` is the Store model in EditStore context. AddonsPage is tenant-aware (current store from Filament::getTenant()); Merano addon card should appear in the list of addon types for that store.

5. **API route prefix and tenant**
   - Merano routes must use the same tenant resolution as other POSitiv API routes (e.g. `X-Tenant` header or route prefix `{tenant}`). Ensure the proxy is registered under the same middleware group as other authenticated API routes so `getTenantStore($request)` works.

6. **available_actions and POS addon**
   - POS devices exist only for stores that have the POS addon. Merano addon is independent. A store can have POS addon but not Merano; then `available_actions` never includes `booking`. A store can have both; then per-device `booking_enabled` controls booking. No conflict.

7. **Seatmap URL and CORS**
   - If the Flutter WebView loads a Merano URL directly, Merano must write to `merano_pos_seatmap_order` (localStorage). Cross-origin: WebView may load Merano in the same WebView; localStorage is origin-bound. If the seatmap is on a different origin than the app, the WebView’s localStorage is that of the loaded page (Merano), so the contract (Merano writes the key) is correct. If POSitiv serves a wrapper page that embeds Merano in an iframe, the iframe’s localStorage is Merano’s origin; the parent would need to communicate with the iframe or poll; document this clearly. Defer seatmap wrapper to follow-up if Merano does not expose a direct seatmap URL.

8. **Blueprint checklist**
   - Filament: use full namespaces for Toggle, TextEntry, Section, TextInput; include validation and config; no `->reactive()` (use `->live()` if needed). Store form Section visibility uses a closure; ensure Get/Set imports if reactive fields are added later.

9. **Duplicate addon type**
   - Addon table has unique `['store_id', 'type']`. Each store can have at most one addon per type. One MeranoBooking addon per store is correct.

10. **Error responses**
    - Proxy: 503 for addon inactive or config missing; 403 for device not allowed; forward Merano 4xx/5xx and body for Merano validation/errors so the app can show Merano messages.

---

## 9. Ticket printing from POS

Implement the two ticket print flows in POSitiv’s receipt/template system so the POS app can print **free tickets** and **booking tickets** (paid event tickets) as Epson ePOS XML, using the same template lookup (DB then file) as existing receipts.

### 9.1 Template types and structure

- **New receipt template types:** Add `freeticket` and `ticket` (booking ticket) to the system.
  - **config/receipts.php:** In `types`, add entries for `freeticket` and `ticket` (e.g. prefix `FT` / `TKT`, labels "Gratisbillett" / "Billett").
  - **ReceiptTemplate:** These types use the same model and `template_type`; no schema change. Filament ReceiptTemplate form/table: allow selecting `freeticket` and `ticket` (add to the template type Select options).
  - **Default template files:** Add under `resources/receipt-templates/epson/` (create or port from the existing PHP templates; POSitiv does not ship with these XML bodies — only the structure and marker/placeholder contract below):
    - `freeticket_template.xml` — Epson ePOS XML with the same **structure** as the PHP `print_freeticket.php` expects: a single loop block delimited by `<!-- FREETICKET-START -->` and `<!-- FREETICKET-END -->`; inside the loop, placeholders `<code>`, `<place>`, `<date>`, `<printerid>`, `<maxTickets>`, `<appliesTo>`, and optional conditional blocks: `<!-- DISCOUNTLINE-START -->...<discount>...<!-- DISCOUNTLINE-END -->`, `<!-- EXPIRESAT-START -->...<date>...<!-- EXPIRESAT-END -->`, `<!-- MAXTICKETS-START -->...<maxTickets>...<!-- MAXTICKETS-END -->`, `<!-- APPLIESTO-START -->...<appliesTo>...<!-- APPLIESTO-END -->`. (Do not change placeholder names; they must match the PHP template.)
    - `ticket_template.xml` — Epson ePOS XML with the same **structure** as the PHP `print_ticket.php` expects: a loop block `<!-- START LOOP -->` … `<!-- END LOOP -->`; inside each ticket, placeholders `<heading>`, `<category>`, `<section>`, `<row>`, `<seat>`, `<orderNumber>`, `<dateTime>`, `<place>`, `<entrance>`, `<printerid>`, `<ticketPrice>`. Conditional blocks: `<!-- TRIBUNE-START -->...<!-- TRIBUNE-END -->` (shown when category is Tribune or Sidetribune), `<!-- LOSJE-START -->...<!-- LOSJE-END -->` (shown when category is Losje or VIP Losje). (Exact behaviour: for "Losje" / "VIP Losje" remove TRIBUNE block; for "Tribune" / "Sidetribune" remove LOSJE block.)
- **SeedReceiptTemplates:** Extend the `$templates` array with `'freeticket' => 'freeticket_template.xml'` and `'ticket' => 'ticket_template.xml'` so `receipt-templates:seed` can load them.

### 9.2 Rendering logic (no Receipt model)

- Existing receipts are driven by a `Receipt` model and Mustache; ticket printing is driven by **request parameters** and **loop + placeholder + conditional-block** logic (as in the provided PHP).
- **Service:** Add `App\Services\TicketPrintService` (or extend `ReceiptTemplateService` with ticket-specific methods). Responsibilities:
  1. **Resolve template:** For a given `store_id` and template type (`freeticket` or `ticket`), get XML content via `ReceiptTemplate::getTemplate($storeId, $type)`; if null, load from file `resources/receipt-templates/epson/{freeticket_template.xml|ticket_template.xml}`.
  2. **Free ticket:** Method `renderFreeTicket(int $storeId, array $params): string`. `$params`: `printer_id`, `date`, `place`, `code`, `amount` (int), `discount` (optional), `applies_to` (optional), `max_tickets` (optional int). Logic: extract the block between `<!-- FREETICKET-START -->` and `<!-- FREETICKET-END -->`; repeat it `amount` times; in each copy replace `<code>`, `<place>`, `<date>`, `<printerid>`, `<maxTickets>`, `<appliesTo>` with the provided values; if `discount` is non-empty, keep the `<!-- DISCOUNTLINE-START -->...<!-- DISCOUNTLINE-END -->` block and replace `<discount>` inside it, otherwise remove the block; if `date` is non-empty and not "Alle", keep EXPIRESAT block and replace `<date>` inside it, otherwise remove it; if `max_tickets` > 0, keep MAXTICKETS block and replace `<maxTickets>`, otherwise remove it; if `applies_to` is non-empty, keep APPLIESTO block and replace `<appliesTo>`, otherwise remove it. Concatenate header (before loop) + repeated blocks + footer (after loop); replace any remaining `<printerid>` in the full output; return XML string.
  3. **Booking ticket:** Method `renderBookingTicket(int $storeId, array $params): string`. `$params`: `printer_id`, `order_number`, `date` (dateTime string), `place`, `heading`, `amount_paid` (number, for splitting per ticket), `tickets` (array of arrays; each has `category`, `section`, `row`, `seat`, `entrance`, and optionally `ticket_price`; if `ticket_price` missing, use `amount_paid / count(tickets)`). Logic: extract block between `<!-- START LOOP -->` and `<!-- END LOOP -->`; for each ticket, clone the block, replace all placeholders (`<heading>`, `<category>`, `<section>`, `<row>`, `<seat>`, `<orderNumber>`, `<dateTime>`, `<place>`, `<entrance>`, `<printerid>`, `<ticketPrice>`); if `category` is "Losje" or "VIP Losje", remove the `<!-- TRIBUNE-START -->...<!-- TRIBUNE-END -->` block and strip LOSJE markers; if "Tribune" or "Sidetribune", remove `<!-- LOSJE-START -->...<!-- LOSJE-END -->` and strip TRIBUNE markers; append to loop output. Return header + loop output + footer; replace `<printerid>` in full output; return XML string.
- **No Seats.io in POSitiv:** The PHP `print_ticket.php` calls Seats.io for seat object infos. In POSitiv, **the client (Flutter) must send the full `tickets` array** (category, section, row, seat, entrance, ticket_price) — e.g. from Merano/booking response or a Merano endpoint that returns seat details. POSitiv does not call Seats.io or Merano for seat data; it only renders the template with the provided data.

### 9.3 API endpoints

- **Auth and tenant:** Both endpoints use the same Sanctum + tenant resolution as other POSitiv API (e.g. `getTenantStore($request)`). Optionally gate on store having Merano addon for **booking ticket** only; **free ticket** can be allowed without Merano (or also gated; product decision).
- **POST /api/receipts/print-freeticket**  
  - **Request (JSON or form):** `printer_id` (required), `date`, `place`, `code` (required), `amount` (required, int, >= 1), `discount`, `applies_to`, `max_tickets` (int).  
  - **Behaviour:** Validate required fields; resolve store; load template; call `TicketPrintService::renderFreeTicket($store->id, $validated)`; return response with `Content-Type: text/xml; charset=UTF-8` and body = XML string. On validation/template error return 422/404 with error message.
- **POST /api/receipts/print-ticket**  
  - **Request (JSON):** `printer_id` (required), `order_number` (required), `date`, `place`, `heading`, `amount_paid` (number), `tickets` (required, array of objects: `category`, `section`, `row`, `seat`, `entrance`, optional `ticket_price`).  
  - **Behaviour:** Validate; resolve store; optionally require Merano addon for this store (403 if not); call `TicketPrintService::renderBookingTicket($store->id, $validated)`; return XML response. On empty `tickets` or template error return 422/404.

The POS app will call these endpoints with the chosen receipt printer ID and receive Epson ePOS XML to send to the printer (same pattern as existing receipt print flow if the app fetches XML from the API).

### 9.4 Filament and docs

- **ReceiptTemplateResource / ReceiptTemplateForm:** Add `freeticket` and `ticket` to the template type Select options so stores can create/edit/store-specific ticket templates.
- **Docs:** Update [docs/features/RECEIPT_TEMPLATE_SYSTEM.md](docs/features/RECEIPT_TEMPLATE_SYSTEM.md) (or add a new doc) to describe the two ticket template types, their placeholders and conditional blocks, and that booking-ticket data (category, section, row, seat, entrance) must be supplied by the client (e.g. from Merano booking flow). Document the two API endpoints and request shapes for FlutterFlow.

### 9.5 Summary for ticket printing

| Item | Detail |
|------|--------|
| **Template types** | `freeticket`, `ticket` in config and ReceiptTemplate; default files `freeticket_template.xml`, `ticket_template.xml` with same structure as provided PHP (loop markers + placeholders + conditional blocks). |
| **Service** | `TicketPrintService::renderFreeTicket()`, `renderBookingTicket()`; template from DB or file; loop + str_replace + regex for conditionals; return Epson ePOS XML. |
| **API** | `POST /api/receipts/print-freeticket`, `POST /api/receipts/print-ticket`; auth + tenant; response body = XML. |
| **Client data** | Booking ticket: client sends full `tickets` array (no Seats.io/Merano call from POSitiv). |
| **Seed** | SeedReceiptTemplates includes freeticket and ticket. |

---

## 10. Summary

| Area | Deliverables |
|------|---------------|
| **Addon** | AddonType::MeranoBooking; AddonsPage primary action null; gating in proxy and available_actions. |
| **POSitiv backend** | Migration booking_enabled (pos_devices); migration merano_base_url + merano_pos_api_token (stores, encrypted token); PosDevice model + API (booking_enabled, available_actions); Store model + form Section (Merano, visible when addon active); Merano proxy controller + routes; device and addon checks; api-spec and docs. |
| **FlutterFlow (docs)** | Adapted WebView, add-ticket, confirm-after-payment, release; flow doc; device struct and available_actions usage. |
| **Ticket printing** | Template types freeticket + ticket; TicketPrintService (free + booking); POST print-freeticket + print-ticket; default XML templates (PHP-compatible structure); ReceiptTemplate options + seed; docs. |
