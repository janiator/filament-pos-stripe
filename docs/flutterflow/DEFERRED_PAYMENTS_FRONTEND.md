# Deferred Payments Frontend Implementation Guide

This guide explains how to implement deferred payments (payment on pickup) in your FlutterFlow frontend application.

## Receipt auto-print (checkout)

The **checkout** action block should print delivery receipts the same way as paid sales. A common FlutterFlow pitfall is gating print on `FFAppState().receiptPrinter.isActive` only, while the actual `eposUrl` lives on `activePosDevice.defaultPrinterId` from the API—so printers work for Merano/tickets but POS receipt XML never prints. Prefer: allow print when the default device printer has a non-empty `eposUrl`, and treat `auto_print_receipt` as **on** when the API omits the field (your device struct may map omitted/null to `false`).

**Canonical implementation:** custom action `receiptPrintAfterPosPurchase` in `docs/flutterflow/custom-actions/receipt_print_after_pos_purchase.dart` (same file under the FlutterFlow export `lib/custom_code/actions/`). The FlutterFlow AI workspace can push it and rewire the `checkoutFlow` → `receiptPrint` action block via `dart run dsl/sync_checkout_receipt_print.dart` (sources live in `docs/flutterflow/dsl/`; copy into the workspace `dsl/` after `flutterflow ai init`—see `.cursor/rules/multi-repo-workspace.mdc`). Validate with FlutterFlow MCP `validate` / `run` as you do for other DSL scripts.

## FlutterFlow AI MCP (POSitiv)

In Cursor, the FlutterFlow MCP server for this app is **`user-flutterflow-positiv`** (the **POSitiv** / `p_o_sitiv` FlutterFlow project — not hi-members). Tools such as `inspect`, `validate`, and `run` need your FlutterFlow **project ID** and **FlutterFlow AI API key** on the MCP server (see `.env.example`: `FLUTTERFLOW_POSITIV_PROJECT_ID`, `FLUTTERFLOW_AI_API_KEY`). With those set, agents can push DSL changes to the remote project; otherwise copy custom actions from `docs/flutterflow/custom-actions/` into FlutterFlow manually.

## Overview

Deferred payments allow you to:
- Create purchases that will be paid later (e.g., dry cleaning, repairs)
- Generate delivery receipts (Utleveringskvittering) per Norwegian regulations
- Complete payment later when customer picks up items
- Generate sales receipts when payment is completed

## Two Ways to Create Deferred Payments

### Option 1: Use "deferred" Payment Method (Recommended)

The simplest approach is to use the dedicated `deferred` payment method code:

```dart
// In your FlutterFlow action call
final result = await completePosPurchase(
  posSessionId: currentSessionId,
  paymentMethodCode: 'deferred',  // Use deferred payment method
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,  // Not needed for deferred
  additionalMetadataJson: jsonEncode({
    'deferred_reason': 'Payment on pickup',  // Optional reason
    'cashier_name': cashierName,
  }),
  isSplitPayment: false,
  splitPaymentsJson: null,
  customerId: customerId,  // Optional: customer database ID
);
```

**Benefits:**
- Clear and explicit - payment method appears as "Betaling ved henting" in POS
- No need to remember metadata flags
- Easy to identify deferred purchases in the UI

### Option 2: Use Metadata Flag with Any Payment Method

You can also use any payment method code and set `deferred_payment: true` in metadata:

```dart
final result = await completePosPurchase(
  posSessionId: currentSessionId,
  paymentMethodCode: 'cash',  // Or any other payment method
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,
  additionalMetadataJson: jsonEncode({
    'deferred_payment': true,  // Set this flag
    'deferred_reason': 'Dry cleaning - payment on pickup',
    'cashier_name': cashierName,
  }),
  isSplitPayment: false,
  splitPaymentsJson: null,
  customerId: customerId,
);
```

## Response Structure

When creating a deferred purchase, the response will look like:

```dart
{
  'success': true,
  'data': {
    'charge': {
      'id': 789,
      'status': 'pending',  // Note: pending, not succeeded
      'amount': 15000,
      'paid': false,  // Not paid yet
      'paid_at': null,
      'stripe_charge_id': null,
    },
    'receipt': {
      'id': 101,
      'receipt_number': '1-D-000001',  // D = Delivery receipt
      'receipt_type': 'delivery',  // Delivery receipt, not sales receipt
    },
    'pos_event': {
      'id': 789,
      'event_code': '13019',
    }
  }
}
```

**Key differences from regular purchases:**
- `charge.status` = `'pending'` (not `'succeeded'`)
- `charge.paid` = `false`
- `charge.paid_at` = `null`
- `receipt.receipt_type` = `'delivery'` (not `'sales'`)
- Receipt number format: `{store_id}-D-{number}` (D = Delivery)

## Completing Deferred Payments

When the customer returns to pay, you need to complete the payment using a separate endpoint.

### Parked / edited order (optional)

To let staff **change line items or totals** before taking payment (same UX as a normal cart):

1. **Load lines for the cart:** `GET /api/purchases/{charge_id}` returns `purchase.purchase_items` (and discounts/metadata fields) so you can rebuild your in-app cart. Optional helpers: [`fetch_pos_purchase_for_cart_hydration.dart`](custom-actions/fetch_pos_purchase_for_cart_hydration.dart) (`{ success, purchase }` only), or **[`prepare_parked_deferred_purchase.dart`](custom-actions/prepare_parked_deferred_purchase.dart)** which also hydrates **`FFAppState().cart`**, runs **`updateCartTotals()`**, and returns **`cartJson`** for **`completeDeferredPayment`**. Push scripts: `dart run dsl/upsert_fetch_pos_purchase_for_cart_hydration.dart` and `dart run dsl/upsert_prepare_parked_deferred_purchase.dart` from [`positiv_flutterflow_ai/`](../../positiv_flutterflow_ai/) (the fetch upsert also refreshes `completeDeferredPayment` and wires empty `cartJson` on existing call sites so validation passes).
2. **Edit** in your POS cart UI.
3. **Stripe:** Create the PaymentIntent only after the cart is final; its amount must equal `cart.total` in minor units (øre).
4. **Complete:** call `POST /api/purchases/{charge_id}/complete-payment` with the same `cart` JSON shape you use for `POST /api/purchases` (items, `total`, `currency`, discounts, etc.), plus `payment_method_code` and `metadata` (e.g. `payment_intent_id`).

If you **omit** `cart`, the backend keeps the original deferred lines and amount (previous behavior).

### Step 1: Create Custom Action for Completing Payment

Create a new custom action in FlutterFlow called `completeDeferredPayment`.

**File Location:** `/docs/flutterflow/custom-actions/complete_deferred_payment.dart`

**Function signature:**
```dart
Future<dynamic> completeDeferredPayment(
  int chargeId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  String? paymentIntentId,  // Optional, for Stripe payments
  String? additionalMetadataJson,  // Optional
  String? cartJson,  // Optional: JSON string of final cart object (same shape as completePosPurchase)
) async
```

**Custom code:** Copy from [`docs/flutterflow/custom-actions/complete_deferred_payment.dart`](custom-actions/complete_deferred_payment.dart) (body-only; no automatic-imports block). In FlutterFlow, paste **only** into the editor region **below** FlutterFlow’s fixed header (`// DO NOT REMOVE OR MODIFY THE CODE ABOVE!`). Do not paste the automatic-imports block—FlutterFlow already provides it; duplicating it breaks the file.

### Step 2: Configure Parameters in FlutterFlow

Add these parameters to the custom action:

| Parameter Name | Type | Required | Description |
|----------------|------|----------|-------------|
| `chargeId` | `Integer` | ✅ Yes | The purchase/charge ID to complete payment for |
| `paymentMethodCode` | `String` | ✅ Yes | Payment method code (e.g., "cash", "card_present") |
| `apiBaseUrl` | `String` | ✅ Yes | API base URL |
| `authToken` | `String` | ✅ Yes | Authentication token |
| `paymentIntentId` | `String` | ❌ No | Stripe payment intent ID (for Stripe payments) |
| `additionalMetadataJson` | `String` | ❌ No | Additional metadata as JSON string |
| `cartJson` | `String` | ❌ No | Optional JSON string of the final `cart` object (same shape as `completePosPurchase`); omit for unchanged deferred lines |

### Step 3: Use in Your FlutterFlow Flow

**Example: Complete payment with cash**

```dart
final result = await completeDeferredPayment(
  chargeId: pendingPurchaseId,  // ID from the deferred purchase
  paymentMethodCode: 'cash',
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,
  additionalMetadataJson: null,
  cartJson: null,
);

if (result['success'] == true) {
  // Payment completed successfully
  final charge = result['data']['charge'];
  final receipt = result['data']['receipt'];
  
  // Show success message
  // receipt.receipt_type will be 'sales'
  // receipt.receipt_number will be in format: {store_id}-S-{number}
} else {
  // Handle error
  final errorMessage = result['message'];
  // Show error to user
}
```

**Example: Complete payment with Stripe card**

```dart
// First, create payment intent using Stripe Terminal SDK
final paymentIntent = await stripeTerminal.createPaymentIntent(...);

// Then complete the deferred payment
final result = await completeDeferredPayment(
  chargeId: pendingPurchaseId,
  paymentMethodCode: 'card_present',
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: paymentIntent.id,  // Required for Stripe payments
  additionalMetadataJson: null,
  cartJson: null,
);
```

## Response Structure for Completed Payment

When completing a deferred payment, the response will look like:

```dart
{
  'success': true,
  'data': {
    'charge': {
      'id': 789,
      'status': 'succeeded',  // Now succeeded
      'amount': 15000,
      'paid': true,  // Now paid
      'paid_at': '2025-12-09T14:30:00+01:00',
      'payment_method': 'cash',
    },
    'receipt': {
      'id': 102,
      'receipt_number': '1-S-000045',  // S = Sales receipt
      'receipt_type': 'sales',  // Now sales receipt
    },
    'pos_event': {
      'id': 790,
      'event_code': '13012',  // Sales receipt event
    }
  }
}
```

### Custom action (`completeDeferredPayment`) extras

The [`complete_deferred_payment.dart`](custom-actions/complete_deferred_payment.dart) action mirrors the API payload under **`data`** and, on success (`success` not false), also:

- Clears **`FFAppState().cart`** (empty lines, discounts, tip, customer, note, metadata; clears Merano booking JSON mirrors) and runs **`updateCartTotals()`**, matching post-sale **`clearCart(afterSuccessfulPurchase: true)`** behavior so the POS cart is empty without a separate **`clearCart`** call.
- Adds top-level fields so FlutterFlow can bind receipt URLs without fragile nested JSON paths:
  - **`receiptId`** and **`salesReceiptId`**: database id of the **sales** receipt from `data.receipt.id` (use for `GET /api/receipts/{id}/xml` or pass the whole map to **`receiptPrintAfterPosPurchase`**).
  - **`receiptNumber`**, **`completedChargeId`**, **`chargeStatus`**: when present in `data`.

**Receipt URL:** If you interpolate `…/api/receipts/{id}/xml`, bind **`id`** to **`receiptId`** / **`salesReceiptId`** from the **completion** result. Using a stale id from the **delivery** receipt (create-deferred / prepare step) produces the wrong document; omitting or mis-binding **`id`** yields `…/receipts/null/xml`.

**“Still waiting” on orders:** The backend sets the charge to **`succeeded`**. Refresh the purchases list (re-run the same query / reload the page) after a successful completion so the UI leaves the pending state.

## UI Flow Recommendations

### Creating Deferred Purchase

1. **Add "Deferred Payment" option** to your payment method selection screen
   - Display as "Betaling ved henting" or "Payment on pickup"
   - Use payment method code: `'deferred'`

2. **Optional: Add reason field**
   - Allow cashier to enter reason (e.g., "Dry cleaning", "Repairs")
   - Pass as `deferred_reason` in metadata

3. **Show delivery receipt**
   - Display the delivery receipt to customer
   - Print delivery receipt (if configured)
   - Note: This is NOT a sales receipt - it's marked "Utleveringskvittering"

### Completing Payment

1. **Find pending purchases**
   - Query purchases with `status: 'pending'` or `paid: false`
   - Filter by customer if needed
   - Display list of pending purchases

2. **Select purchase to complete**
   - Show purchase details (items, amount, date created)
   - Show delivery receipt number

3. **Select payment method**
   - Cash: Simple - just call complete payment
   - Stripe Card: Create payment intent first, then complete payment

4. **Show sales receipt**
   - Display the new sales receipt
   - Print sales receipt (if configured)
   - Note: This replaces the delivery receipt
   - The receipt ID is returned in `result['data']['receipt']['id']`
   - When retrieving the purchase later, `purchase.purchase_receipt` will show the sales receipt (not the delivery receipt)

## Example: Complete Flow

### 1. Create Deferred Purchase

```dart
// User selects "Payment on pickup" option
final result = await completePosPurchase(
  posSessionId: currentSessionId,
  paymentMethodCode: 'deferred',
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: null,
  additionalMetadataJson: jsonEncode({
    'deferred_reason': 'Dry cleaning',
    'customer_name': customerName,
  }),
  isSplitPayment: false,
  splitPaymentsJson: null,
  customerId: customerId,
);

if (result['success'] == true) {
  final chargeId = result['data']['charge']['id'];
  final receiptNumber = result['data']['receipt']['receipt_number'];
  
  // Store chargeId for later completion
  // Show delivery receipt to customer
  // Print delivery receipt
}
```

### 2. Complete Payment Later

```dart
// User selects pending purchase and payment method
final result = await completeDeferredPayment(
  chargeId: pendingChargeId,  // The purchase/charge ID from the deferred purchase
  paymentMethodCode: selectedPaymentMethod,  // 'cash' or 'card_present'
  apiBaseUrl: apiBaseUrl,
  authToken: authToken,
  paymentIntentId: paymentIntentId,  // Required for Stripe payments, null for cash
  additionalMetadataJson: null,  // Optional additional metadata
  cartJson: jsonEncode(finalCartMap),  // Optional: omit or null if cart unchanged
);

if (result['success'] == true) {
  final charge = result['data']['charge'];
  final receipt = result['data']['receipt'];
  
  // Payment completed successfully
  // charge.status = "succeeded"
  // charge.paid = true
  // receipt.receipt_type = "sales" (replaced delivery receipt)
  
  // Show sales receipt to customer
  // Print sales receipt (if configured)
  // Update UI to remove from pending list
  // Cash drawer will open automatically for cash payments
} else {
  // Handle error
  final errorMessage = result['message'];
  // Show error to user
}
```

**Function Signature:**
```dart
Future<dynamic> completeDeferredPayment(
  int chargeId,                 // Required: Purchase/charge ID
  String paymentMethodCode,     // Required: 'cash', 'card_present', etc.
  String apiBaseUrl,            // Required: API base URL
  String authToken,             // Required: Authentication token
  String? paymentIntentId,      // Optional: Required for Stripe payments
  String? additionalMetadataJson, // Optional: Additional metadata as JSON string
  String? cartJson,             // Optional: Final cart JSON string (parked / edited order)
) async
```

**Parameters:**
- `chargeId`: The ID of the deferred purchase (charge ID) that was returned when creating the deferred purchase
- `paymentMethodCode`: The payment method code to use for completion (e.g., `'cash'`, `'card_present'`, `'card'`)
- `apiBaseUrl`: Your API base URL (e.g., `'https://api.example.com'`)
- `authToken`: Bearer token for authentication
- `paymentIntentId`: **Required for Stripe payments** - the payment intent ID from Stripe Terminal or card payment. Set to `null` for cash payments.
- `additionalMetadataJson`: Optional JSON string with additional metadata (e.g., `jsonEncode({'cashier_name': 'John Doe'})`)
- `cartJson`: Optional `jsonEncode` of the final cart map (`items`, `total`, `currency`, …). For Stripe, `cart.total` must match the PaymentIntent amount.

**Response Structure:**
```dart
{
  'success': true,
  'data': {
    'charge': {
      'id': 123,
      'stripe_charge_id': 'ch_xxx',
      'amount': 11000,
      'currency': 'nok',
      'status': 'succeeded',  // Changed from 'pending'
      'payment_method': 'cash',
      'paid_at': '2025-12-09T14:30:00+01:00'
    },
    'receipt': {
      'id': 456,
      'receipt_number': '1-S-000001',  // Sales receipt (S = Sales)
      'receipt_type': 'sales'  // Replaced delivery receipt
    },
    'pos_event': {
      'id': 789,
      'event_code': '13012',
      'transaction_code': '1'
    }
  },
  'message': 'Payment completed successfully',
  'statusCode': 200
}
```

## Querying Pending Purchases

To show a list of pending purchases, use the purchases list endpoint with filters:

```dart
// GET /api/purchases?status=pending
final response = await http.get(
  Uri.parse('$apiBaseUrl/api/purchases?status=pending'),
  headers: {
    'Authorization': 'Bearer $authToken',
    'Accept': 'application/json',
  },
);

final data = jsonDecode(response.body);
final pendingPurchases = data['purchases'] as List;

// Filter by customer if needed
// Filter by date range if needed
```

## POSitiv (`pointofsale-xrlz5i`): orders page → parked deferred flow

The FlutterFlow project exposes the **orders** page, the **`pos`** page, the **`deferredPaymentCheckout`** component (optional small dialog), and **`completeDeferredPayment`**. Recommended UX: **hydrate the cart on orders, then navigate to `pos`**, show **which order** is being paid, and complete payment with **`checkoutFlow`** (normal POS) **or** a **conditional** branch that calls **`completeDeferredPayment`** when a deferred resume session is active.

### 1. Custom actions (repo + MCP)

| Action | Role |
|--------|------|
| [`prepare_parked_deferred_purchase.dart`](custom-actions/prepare_parked_deferred_purchase.dart) | `GET /api/purchases/{id}`, rebuilds **`FFAppState().cart`** (note from **`purchase_note`** / metadata keys; **`cartMetadata`** is **`CartMetadataStruct`**: deferred keys live in **`cartMetadata.notes`** as JSON, e.g. `positiv_deferred_resume_charge_id` / `positiv_deferred_order_display`), **`updateCartTotals()`**, prefs + banner mirror, returns **`cartJson`**, **`orderDisplayReference`**, **`deferredResumeBannerText`**, **`deferredResumeBannerActive`**, **`purchaseOrderNote`**, etc. |
| [`get_deferred_resume_context.dart`](custom-actions/get_deferred_resume_context.dart) | Reads prefs; returns **`active`**, **`resumeChargeId`**, **`orderLabel`**, **`bannerText`**; also syncs **FFAppState** mirror fields (see **§1a**) when present. |
| [`serialize_cart_for_complete_deferred.dart`](custom-actions/serialize_cart_for_complete_deferred.dart) | Builds **`cartJson`** from the **current** `FFAppState().cart` (after edits on **pos**) for **`completeDeferredPayment`**. |
| [`clear_deferred_resume_context.dart`](custom-actions/clear_deferred_resume_context.dart) | Clears resume prefs; syncs banner **FFAppState** mirror off (§1a). |
| [`clear_cart.dart`](custom-actions/clear_cart.dart) | **`clearCart`** clears cart, Merano JSON, resume prefs, **`cartMetadata`**, optional Merano **release**; syncs banner mirror off; returns **`deferredResumeBannerText`** / **`deferredResumeActive`** for optional “Update Page State”. MCP: `dsl/update_clear_cart.dart`. |
| [`fetch_pos_purchase_for_cart_hydration.dart`](custom-actions/fetch_pos_purchase_for_cart_hydration.dart) | Optional if you only need JSON without hydrating app-state cart. |

Push **`prepareParkedDeferredPurchase`**:

```bash
cd positiv_flutterflow_ai
dart run dsl/upsert_prepare_parked_deferred_purchase.dart --project-id pointofsale-xrlz5i
```

Sync **`completePosPurchase`** (repo `docs/flutterflow/custom-actions/complete_pos_purchase.dart`; blocks accidental new purchase when deferred resume prefs are set):

```bash
dart run dsl/update_complete_pos_purchase.dart --project-id pointofsale-xrlz5i
```

Push the **deferred resume helpers** (same as FlutterFlow Positiv MCP **`run`** on `dsl/upsert_deferred_resume_helpers.dart`):

```bash
dart run dsl/upsert_deferred_resume_helpers.dart --project-id pointofsale-xrlz5i
```

(Use FlutterFlow MCP **`validate`** / **`run`** with the same file if you prefer.)

### 1a. “Ordre …” banner + **FFAppState** (MCP / DSL — preferred)

**Why:** If the banner **Text** is bound only to **pos** page / widget state set on **On Page Load**, clearing SharedPreferences in **`clearCart`** does not rebuild that state — the old **`Ordre …`** string can stay on screen. Repo custom actions call **`mirrorDeferredResumeBannerToAppStateIfPresent`** for **`FFAppState.deferredResumeBannerText`** / **`deferredResumeBannerActive`**.

**Automated wiring (POSitiv):** from `positiv_flutterflow_ai/` run (or MCP **`validate` / `run`** on the same file):

```bash
dart run dsl/wire_pos_deferred_resume_banner_app_state.dart --project-id pointofsale-xrlz5i
```

That script (idempotent) **creates the two App State fields** if missing, rebinds the **pos** banner **Text** widget (DSL key `6sy7nlgg`) from page state → **App State**, and rewires **`checkoutFlow`**’s **`deferredResumeBannerActive`** parameter on **`pos`** / **`posSession`** to read **App State** instead of page state. Re-inspect **pos** if FlutterFlow regens the banner node key.

**Manual fallback:** add the same App State names in **App Settings → App State**, bind the banner and **`checkoutFlow`** param yourself, or chain **`getDeferredResumeContext`** after **`clearCart`** and **Update Page State**.

### 1b. One-shot DSL (recommended for POSitiv)

From `positiv_flutterflow_ai/`, run:

```bash
dart run dsl/wire_orders_betaling_prepare_parked.dart --project-id pointofsale-xrlz5i
```

That script (idempotent) adds **`parkedCartJson`** on **`deferredPaymentCheckout`** if missing, wires **`completeDeferredPayment.cartJson`** to that parameter, inserts a **zero-height clipped container** whose child **Text** is bound to **`parkedCartJson`** (FlutterFlow R1), and replaces the **orders** page **Betaling** button (`Button_svzfzpi5`) with **`prepareParkedDeferredPurchase`** → success check → **Navigate to `pos`**. Re-inspect **orders** / **`pos`** if FlutterFlow regenerates node keys (`Scaffold_6umjp4qm` for **pos** in the script).

**Action order (order label / banner):** **`prepareParkedDeferredPurchase` must finish before `pos` first reads prefs** — preferred: **Prepare → Navigate**. If your flow is **Navigate → Prepare** on **pos**, **`pos` On Page Load** may have run with empty prefs; fix by either (a) moving prepare to **orders** before navigation, or (b) after **Prepare** on **pos**, chain **`getDeferredResumeContext`** and **Update Page State** / **Set App State** from **`$.deferredResumeBannerText`** and **`$.deferredResumeBannerActive`** (the prepare return map includes these for binding).

**Pos banner + page load (recommended after the above):**

```bash
dart run dsl/wire_pos_deferred_resume_banner.dart --project-id pointofsale-xrlz5i
```

That script (idempotent) adds **pos** page state **`deferredResumeBannerText`** / **`deferredResumeBannerActive`**, prepends **On Page Load** → **`getDeferredResumeContext`** → updates those fields from **`$.bannerText`** / **`$.active`**, and appends an **INFO**-colored **Text** banner (`deferredResumeBannerHost`) to the main **Stack** with visibility bound to **`deferredResumeBannerActive`**.

**Checkout pay controls (normal vs deferred resume)** — DSL duplicate + visibility:

```bash
dart run dsl/wire_checkoutflow_deferred_pay_branch.dart --project-id pointofsale-xrlz5i
```

That script (idempotent) duplicates each **Fullfør handel** button in **`checkoutFlow`** and wires the duplicates to **`getDeferredResumeContext`** → **`serializeCartForCompleteDeferred`** → **`completeDeferredPayment`** (with **`chargeId`** from **`$.resumeChargeId`** on the tap-time context). It does **not** clone the normal post-`completePosPurchase` receipt action chain; **`completeDeferredPayment`** (repo) performs **client receipt print** (when a default printer `eposUrl` / auto-print rules apply) and bumps **`FFAppState().cacheRefreshKey`** after a successful API response. If FlutterFlow leaves **`cartJson`** blank on a checkout button, **`completeDeferredPayment`** now serializes the current **`FFAppState().cart`** immediately before `POST /complete-payment`, so staff edits to the resumed cart are still sent to the backend.

The same script also:

- Adds component parameter **`deferredResumeBannerActive`** (boolean, default `false`) on **`checkoutFlow`** if missing.
- Binds **visibility** on the original vs deferred **Fullfør** buttons to that parameter (normal buttons when the flag is false; deferred duplicates when true).
- When **`checkoutFlow`** is embedded on **`pos`** or **`posSession`**, wires that parameter from **pos** page widget state **`deferredResumeBannerActive`** (same field as the banner DSL), even if the embed lives on **`posSession`**, so the binding still reads **pos** scaffold state.

If your **`checkoutFlow`** instance sits on another page, copy the parameter pass-through in FlutterFlow (**Set from variable** → **Widget State** → **pos** scaffold → **`deferredResumeBannerActive`**) or extend `_kPosPagesHostingCheckout` in `dsl/wire_checkoutflow_deferred_pay_branch.dart`.

**Recommended:** use **1a** **`FFAppState.deferredResumeBannerActive`** / **`deferredResumeBannerText`** as the single source for the banner and **`checkoutFlow`** branching; repo actions keep them in sync with prefs.

### 2. **pos** page — banner and payment branching

1. **On Page Load** + banner: either run **`wire_pos_deferred_resume_banner.dart`** (above) or manually call **`getDeferredResumeContext`** and bind UI to **`bannerText`** / **`active`**.
2. **Payment / checkout**
   - **New sale:** unchanged — your existing **`checkoutFlow`** / **`completePosPurchase`** path (hidden while **`deferredResumeBannerActive`** when the checkout DSL above is applied). Successful **`completePosPurchase`** clears resume prefs (repo `complete_pos_purchase.dart`).
   - **Deferred resume:** run **`wire_checkoutflow_deferred_pay_branch.dart`** (above) or manually: **`getDeferredResumeContext`** (for **`resumeChargeId`**) → **`serializeCartForCompleteDeferred`** → **`completeDeferredPayment`** with **`cartJson`** from serialize, same **`apiBaseUrl`** / **`authToken`** / **`paymentMethodCode`** / terminal / metadata wiring as **`completePosPurchase`**. Hide the normal pay controls when a resume session is active so staff use **`POST …/complete-payment`** instead of **`POST /api/purchases`**.
   - **Safety net (repo):** if staff still hit **`completePosPurchase`** while resume prefs are set, the action returns **`success: false`**, **`blockedDeferredResume: true`**, **`resumeChargeId`**, **`orderLabel`** — branch in FlutterFlow (e.g. snackbar + open deferred pay) or rely on hiding the wrong button as above.
3. Optional **“Avbryt henting”**: call **`clearDeferredResumeContext`** and reset the cart if you need to abandon without paying — or rely on **`clearCart`** (repo [`clear_cart.dart`](custom-actions/clear_cart.dart)), which clears resume prefs when the cart is cleared.

### 3. `deferredPaymentCheckout` (small dialog) — optional

If you still open this component from **pos** or elsewhere, keep **`parkedCartJson`** wired as the DSL does: **`completeDeferredPayment.cartJson`** ← component param, plus the hidden **Text** reference for R1.

### 4. Stripe amount

If staff change the cart on **`pos`**, create or update the **PaymentIntent** only **after** cart totals are final so the amount matches **`cart.total`** (øre) sent in **`cartJson`** to **`completeDeferredPayment`**.

---

## Error Handling

### Common Errors

**Charge Already Paid:**
```dart
{
  'success': false,
  'message': 'Charge is not pending or already paid'
}
```
**Solution:** Check charge status before attempting to complete payment.

**Invalid Payment Method:**
```dart
{
  'success': false,
  'message': 'Payment method not found'
}
```
**Solution:** Verify payment method code is correct and enabled.

**Missing Payment Intent (Stripe):**
```dart
{
  'success': false,
  'message': 'Payment intent ID is required for Stripe payments'
}
```
**Solution:** Create payment intent before completing payment.

## Best Practices

1. **Always store charge ID** when creating deferred purchase
2. **Show delivery receipt** to customer immediately
3. **Store customer information** for pickup verification
4. **Query pending purchases** regularly to show in UI
5. **Validate charge status** before completing payment
6. **Handle errors gracefully** with clear user messages
7. **Print receipts** at appropriate times (delivery receipt on creation, sales receipt on completion)

## Compliance Notes

- Delivery receipts are marked "Utleveringskvittering – IKKJE KVITTERING FOR KJØP"
- Delivery receipts have separate numbering (D-series)
- Sales receipts are generated when payment is completed
- All transactions are logged for audit trail
- POS session totals only include completed payments
