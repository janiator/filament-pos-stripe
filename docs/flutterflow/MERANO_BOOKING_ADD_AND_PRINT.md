# Merano: Add seats to cart and print tickets

After the user selects seats in the seatmap and the modal returns a result (e.g. the JSON with `bookingCreated`, `booking`, `orderJson`, `seats`, `eventName`, `totalPriceOre`), use these actions to add the booking to the cart and optionally print tickets.

## 0. Ticket product (Filament + API)

**Filament:** In the backend (pos-stripe), open **Stores** → edit a store → **Merano Integration** section. Set **Merano ticket product** to the product that should be used as the cart line when adding Merano bookings. Only stores with the Merano Booking add-on and a connected Stripe account see this field.

**API:** The POS uses this automatically. When the user confirms a Merano booking in the seatmap modal, the modal calls:

- **Endpoint:** `GET /api/stores/current/merano-ticket-product`
- **Auth:** Bearer token; tenant from `X-Tenant` header or current store.
- **Response:** Same shape as `GET /api/products/{id}`: `{ "product": { ... } }`. 404 if no ticket product is configured.

The modal fetches the ticket product from this endpoint and adds the booking to the cart. No extra parameters or FlutterFlow configuration needed.

## 1. Add booking to cart

### Option A: Automatic (recommended)

**Custom action:** `showProviderActionModal` with optional **ticketProduct**.

- When you call `showProviderActionModal`, pass the **ticket product** (ProductStruct) as the last parameter. If `createBookingOnConfirm` is true and the user confirms, the modal will create the booking and **automatically** call `addMeranoBookingResultToCart` before returning. No separate “add to cart” step needed.
- **FlutterFlow:** In the Custom Action parameters for the Booking tile (or wherever you open the seatmap), add the 18th parameter `ticketProduct` and set it to your store’s Merano ticket product (e.g. from a page/global variable or product list). Leave it null if you prefer to add to cart manually (Option B).

### Option B: Manual add after modal returns

**Custom action:** `addMeranoBookingResultToCart`

- **When:** After `showProviderActionModal` returns with success and non-empty order/booking data, and you want to add to cart yourself (e.g. automatic add was skipped because no ticket product was configured).
- **Parameters:**
  - `bookingResultJson` (String) – JSON string of the result. Use `FFAppState().lastMeranoBookingResultJson` (modal saves it on success), or serialize the modal return to JSON.
  - `ticketProduct` (ProductStruct) – the POS “ticket” product used for Merano bookings.
  - `apiBaseUrl`, `authToken`, `posDeviceId`, `customerName`, `customerEmail`, `customerPhone`, `storeSlug` (same as other Merano/API calls).

**Behaviour:**

- If the result already has a created booking (`bookingCreated` + `booking`), that booking is added to the cart (no extra create-booking API call).
- If only `orderJson` is present, the action calls `createMeranoBooking` to create the booking, then adds the cart line(s).
- **Multiple lines by category/price:** When the seatmap order includes a `tickets` array (each with `category`, `seat`, `ticket_price`), the action adds one cart line per distinct (category, price): e.g. "Category A" × 2 @ 100 øre and "Category B" × 1 @ 150 øre. If `tickets` is missing, a single line is added with total quantity and averaged unit price.
- Cart line metadata for confirm-after-payment is stored in `CartItemMetadataStruct.notes` as JSON (contains `merano_booking_id`, `merano_booking_number`, etc.). All lines for the same booking share the same metadata so confirm is called once per booking with the summed amount.

**FlutterFlow steps (Option B only):**

1. On the action that opens the seatmap, after `showProviderActionModal` returns, check `result['success'] == true` and `result['cancelled'] != true` and that there is order/booking data.
2. Call `addMeranoBookingResultToCart` with `FFAppState().lastMeranoBookingResultJson`, your ticket product, and the same API/auth/customer/tenant params you use elsewhere.
3. No need to call `updateCartTotals` separately; the action calls `addItemToCart`, which triggers cart totals update.

## 2. Print tickets (Epson ePOS XML)

**Automatic after purchase:** When you use `completePosPurchase`, after each Merano booking is confirmed the action automatically fetches ticket XML from `POST /api/receipts/print-ticket` and sends it to the store’s default receipt printer (from `FFAppState().activePosDevice`). No extra FlutterFlow step needed for the normal “pay then print” flow.

**Custom action (reprint or manual):** `printMeranoBookingTickets`

- **When:** You need to print tickets outside the purchase flow (e.g. reprint, or print right after adding to cart before payment).
- **Parameters:**
  - `bookingReference` (String) – Merano booking number (e.g. `BK-14A9D4B9`). From cart metadata (`merano_booking_number`) or `FFAppState().lastMeranoBookingResultJson` → `booking.bookingNumber`.
  - `apiBaseUrl`, `authToken`, `storeSlug`, `eposUrl`.

**Behaviour:** Calls `GET /api/receipts/ticket-xml?booking_reference=<ref>`. The backend looks up the booking in Merano and returns ticket XML (template, heading, place, date, losje/sete, confirmation URL are all from Merano). The action then sends the XML to the printer via `ReceiptPrinterGroup.printReceiptCall`.

**FlutterFlow:** Update the Custom Action parameters to match: 1) `bookingReference` (String, required), 2) `apiBaseUrl`, 3) `authToken`, 4) `storeSlug` (optional), 5) `eposUrl`. Remove `bookingResultJson` and `printerId`.

## 3. Confirm booking after payment

**Automatic (recommended):** The custom action `completePosPurchase` already confirms Merano bookings after a successful purchase. It uses the charge identifier from the API response (`stripe_charge_id` for Stripe, or `transaction_code` / charge `id` for cash) so confirm is called for both Stripe and cash payments. It groups cart lines by `merano_booking_id`, sums the amount paid per booking, and calls `confirmMeranoBookingAfterPayment` once per booking. No extra FlutterFlow steps needed if you use `completePosPurchase`.

**Manual:** If you complete payment outside `completePosPurchase`, for each distinct `merano_booking_id` in the cart (from `item.cartItemMetadata.notes`), sum the line amounts for that booking and call `confirmMeranoBookingAfterPayment(bookingId, totalAmountPaidOre, posChargeId, apiBaseUrl, authToken, ...)`. See the comment block in `confirm_merano_booking_after_payment.dart` for parameters.

## 4. Order view: show “Print tickets” when purchase had tickets

When completing a purchase that included Merano ticket items, the POS sends ticket metadata so the order view can show a “Print tickets” action.

- **Purchase metadata (stored with the charge):**
  - `purchase_contains_tickets` (bool) – `true` when the cart contained at least one Merano booking.
  - `purchase_ticket_reference` (string) – Merano booking number(s), comma-separated if the purchase had multiple bookings. Matches the booking reference in Merano.

These are added automatically by the `completePosPurchase` custom action when the cart has items with Merano booking metadata. They are stored in the purchase/charge metadata and returned in the purchase API (e.g. GET purchase or charge) as `purchase_metadata.purchase_contains_tickets` and `purchase_metadata.purchase_ticket_reference`.

**FlutterFlow order view:** If `purchase_metadata?.purchase_contains_tickets == true`, show a “Print tickets” (or similar) action. Use **GET /api/receipts/ticket-xml?booking_reference=&lt;booking_number&gt;** (one request per booking; comma-separate multiple references from `purchase_ticket_reference`). That endpoint returns ticket XML only; use your own FlutterFlow API request. Then send the XML to the printer (e.g. via your print flow).

## 5. Clearing result and cart; releasing bookings

**clearPreviousResult (modal):** When opening the seatmap modal with `clearPreviousResult: true` (default), the app clears `meranoSeatmapOrderJson` and `lastMeranoBookingResultJson` before showing the dialog. That way the action output and app state are reset when opening the modal again.

**clearCart:** The custom action `clearCart` clears the cart and Merano-related app state (`lastMeranoBookingResultJson`, `meranoSeatmapOrderJson`). It supports:

- **afterSuccessfulPurchase** (bool, default false): When **true**, only the cart and Merano state are cleared (bookings were already confirmed by `completePosPurchase`). Use this when clearing after payment.
- When **false** (e.g. user cancels or clears cart): if **apiBaseUrl** and **authToken** are passed, the action releases any pending Merano bookings in the cart via the API, then clears the cart and state. In FlutterFlow, when calling `clearCart` from a “Cancel purchase” or “Clear cart” button, pass **apiBaseUrl** and **authToken** (and optionally **storeSlug**) so those bookings are released.

**Cart cleared after purchase:** `completePosPurchase` clears the cart (and Merano state) on success internally. You can remove any separate FlutterFlow callback that clears the cart after a successful purchase.

## API used

- **Create booking:** `POST /api/merano/v1/bookings` (via `createMeranoBooking` / `addMeranoBookingResultToCart` when no booking yet).
- **Release booking:** `POST /api/merano/v1/bookings/{id}/release` (via `clearCart(afterSuccessfulPurchase: false, ...)` when clearing/cancelling).
- **Ticket XML by reference:** `GET /api/receipts/ticket-xml?booking_reference=&lt;Merano_booking_number&gt;` – backend fetches booking from Merano and returns ticket XML. Used by `printMeranoBookingTickets` and for reprint from order view.
- **Ticket XML (full payload):** `POST /api/receipts/print-ticket` – body has `printer_id`, `order_number`, `date`, `place`, `tickets`; for custom flows that supply payload directly.
- **Confirm after payment:** `POST /api/merano/v1/bookings/{id}/confirm-pos-payment`.

All require Bearer token and, where applicable, `X-Tenant` (store slug).
