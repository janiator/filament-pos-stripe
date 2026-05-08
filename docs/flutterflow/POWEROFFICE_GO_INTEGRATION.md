# PowerOffice Go (FlutterFlow / POS app)

Optional per-store add-on. Backend stores encrypted credentials and account mappings. **Filament** uses a single **PowerOffice** page per store: an onboarding wizard (connect → sync type → account numbers) until setup is finished, then one settings screen with sync on/off, mapping basis, and account lines. Z-report sync runs when a session closes (if enabled) or via manual API calls.

## Prerequisites

- **Add-on**: PowerOffice Go must be enabled for the store (Filament **Add-ons**).
- **Auth**: Same Bearer token as other POS API calls (`Authorization: Bearer …`).
- **Store context**: Use the same `X-Store-Id` / current-store mechanism your app already uses for scoped endpoints (if applicable).

## Fixed URLs (whitelist with PowerOffice)

Register these with PowerOffice for **v2 onboarding** (whitelist the **browser redirect** URI; it receives `state` and `token` after the user approves). The optional API callback URL is only if PowerOffice server-to-server POST is used.

| Environment | Optional callback (API) | Browser redirect (required for v2 `RedirectUri`) |
|-------------|-------------------------|-----------------------------------------------------|
| Dev (`pos-stripe.share.visivo.no`) | `https://pos-stripe.share.visivo.no/api/poweroffice/onboarding/callback` | `https://pos-stripe.share.visivo.no/integrations/poweroffice/onboarding/redirect` |
| Prod (`positiv.visivo.no`) | `https://positiv.visivo.no/api/poweroffice/onboarding/callback` | `https://positiv.visivo.no/integrations/poweroffice/onboarding/redirect` |

## Endpoints

### `POST /api/poweroffice/onboarding/init`

Starts onboarding. Response:

```json
{ "onboarding_url": "https://..." }
```

Open `onboarding_url` in an in-app browser / WebView (store admin flow). After completion, PowerOffice calls the server callback; the integration status becomes **connected**. Store admins finish mapping in Filament’s **PowerOffice** wizard/settings (not a separate “account mappings” menu).

- **403** if the add-on is off or the user cannot access the store.
- If `POWEROFFICE_CLIENT_ID` (Application Key) or `POWEROFFICE_SUBSCRIPTION_KEY` is missing or PowerOffice returns an error, init returns **422** with a message (no URL). The backend calls PowerOffice v2 `POST …/Onboarding/Initiate` and returns the `TemporaryUrl` as `onboarding_url`.

### `POST /api/poweroffice/onboarding/callback`

**Server-to-server** (PowerOffice → backend). Not called from FlutterFlow. Optional header `X-PowerOffice-Callback-Secret` when `POWEROFFICE_CALLBACK_SECRET` is set.

### `POST /api/poweroffice/sync/z-report/{posSessionId}`

Queues a sync job for that POS session’s Z-report snapshot (manual / force). Session must be closed with Z-report data. Returns **403** if **PowerOffice sync** is turned off for the store (master switch in Filament).

### `POST /api/poweroffice/sync/retry/{syncRunId}`

Re-queues a failed sync run. Returns **403** if sync is turned off for the store.

## UX recommendations

1. **Settings screen**: “Connect PowerOffice” → `POST …/onboarding/init` → launch URL → poll or refresh integration status (e.g. next app open or pull-to-refresh hitting a future status endpoint, or rely on Filament for now).
2. **After Z-report**: No app action required if **PowerOffice sync** and **auto sync on Z-report** are enabled in Filament; otherwise expose “Post to accounting” calling `sync/z-report/{id}` for the closed session (will 403 if the master sync switch is off).
3. **Errors**: Mapping gaps return actionable messages in sync run records (see **Recent syncs** on the Filament PowerOffice page).

## Account mapping

Configured on the same Filament **PowerOffice** page (settings form). Exactly one **basis** per store: VAT, category, vendor, or payment method. Sync fails with a clear error if a required line is missing.

For **VAT rate** splitting, the UI uses one **sales/revenue account per standard rate** (0%, 15%, 25%) plus a **single shared block** for output VAT, cash, card/clearing, tips, fees, and rounding (Z-report-wide). Other bases still use a per-line repeater.

**Product collection** splitting uses each product’s **primary collection** (pivot `sort_order`); products with no collection use key `0` (“Uncategorized”). You can set a **default sales account** in Filament for new collections/vendors that do not yet have a row.

Z-reports now include **`by_payment_method_net`** (net amount per `payment_method` after refunds). PowerOffice sync uses it when present to debit **per-method accounts** configured under **Ledger routing** (cash, `card_present`, `card`, `vipps`, `mobile`, `gift_token`, default). Optional Z-report keys **`stripe_fees_minor`**, **`payout_to_bank_minor`**, and **`gift_card_sales_minor`** produce extra paired lines when accounts are configured (PSP-style settlement).

### Stripe fees and payouts (Filament)

Under **Betalinger**, **Stripe-utbetalinger** and **Stripe gebyr og saldo** list data synced from each store’s **connected Stripe account** (`payouts` and `balance_transactions`). Use **Synk fra Stripe** on those screens (or **Synkroniser alt**) to refresh.

When a **Z-report** is generated or loaded for a closed session, the server merges **`stripe_fees_minor`** and **`payout_to_bank_minor`** from the same Stripe-synced data (unless the snapshot already has a **positive** value for a key—POS override). Those values are stored on **`closing_data.z_report_data`** so the snapshot stays self-contained.

When building the PowerOffice ledger payload, if the Z-report snapshot does **not** set **`stripe_fees_minor`** / **`payout_to_bank_minor`** (or they are `0`), the backend still fills them from the database the same way:

- **Fees**: sum of **`fee`** on synced balance transactions of type **`charge`** whose **`stripe_charge_id`** matches **succeeded** charges on that POS session.
- **Payout**: sum of **`amount`** on synced payouts with **`status` = `paid`** whose **`arrival_date`** falls on the **same calendar day** as **`closed_at`** (app timezone) for that session.

If the Z-report includes a **positive** value for either key, that value wins (POS override).

## PowerOffice Go API v2 (posting)

Z-report sync posts a **manual journal** by default with **`POST {base}/Vouchers/ManualJournals`** ([direct voucher posting](https://developer.poweroffice.net/workflows/endpoints/voucher-workflows/voucher-posting)) — this matches the Go integration setting **“Direktepostere manuelle bilag”** (directly post manual vouchers).

If you only enabled **“Sende bilag til bilagsføring”** / the **[Journal Entry Voucher](https://developer.poweroffice.net/endpoints/voucher-workflows/journalentryvoucher)** workflow (vouchers land in the **journal entry view** for review before posting), set **`POWEROFFICE_LEDGER_POST_PATH=/JournalEntryVouchers/ManualJournals`** instead. That path needs **JournalEntryVouchers**-style API privileges on the token, not the direct-posting ones.

**After changing privileges in Go**, the cached OAuth access token may still carry the old claims for up to ~20 minutes. Run **`php artisan poweroffice:forget-token your-store-slug`** (or clear `access_token` / `token_expires_at` on the integration) so the next sync requests a **fresh** token, then **`php artisan poweroffice:diagnose your-store-slug`** to confirm the new privilege list.

The old placeholder **`/api/journalentries` is not a v2 endpoint** and returns **404 Resource not found**.

Before posting, the backend calls **`GET {base}/GeneralLedgerAccounts?accountNos=…`** to map your configured **account numbers** (e.g. `3000`, `1920`) to PowerOffice’s internal **`AccountId`** values. Any account number used on the Z-report that does not exist (or is inactive) in the client’s chart must be created in Go first, or sync will fail with a clear error listing missing numbers.

**Authentication (v2):** Ledger and voucher-documentation calls use **`Authorization: Bearer {access_token}`**, not the raw client key. The backend requests a token with **OAuth 2.0 client credentials**: **`POST`** to the demo/prod **OAuth Token** URL (see `.env.example`), with **`Authorization: Basic`** `base64(POWEROFFICE_CLIENT_ID:store_client_key)` (application key + per-store client key from onboarding), **`Ocp-Apim-Subscription-Key`**, and body **`grant_type=client_credentials`**. The access token is stored on the integration and refreshed before expiry.

Ensure **`POWEROFFICE_CLIENT_ID`** (application key) and **`POWEROFFICE_SUBSCRIPTION_KEY`** are set; the subscription key is sent as **`Ocp-Apim-Subscription-Key`** on token, ledger, GL list, and onboarding requests.

## Troubleshooting

- **HTTP 401 on ledger calls** — OAuth token issue: ensure **`POWEROFFICE_CLIENT_ID`** (application key) and per-store **client key** are correct; token requests need **`POWEROFFICE_SUBSCRIPTION_KEY`**.
- **“We connected in Go but still get HTTP 403 when posting”** — **Onboarding success ≠ voucher posting enabled.** Connecting gives you a **client key**; each **access token** also embeds **API privileges** granted to your **application key** by PowerOffice (see [Authorised access privileges](https://developer.poweroffice.net/documentation/authentication)). If those privileges do not include the manual-journal workflow you call, you get **403** (often with an empty body). Run **`php artisan poweroffice:diagnose your-store-slug`** — this calls [Client Integration Information](https://developer.poweroffice.net/endpoints/client-settings/client-integration-information) and lists **valid / invalid** privileges vs the client’s Go subscriptions. If the needed privilege is missing on the token, ask **go-api@poweroffice.no** (or your PowerOffice contact) to enable it for your integration’s agreed workflow. You can also try the other ledger path via **`POWEROFFICE_LEDGER_POST_PATH`** (`/JournalEntryVouchers/ManualJournals` vs `/Vouchers/ManualJournals`).
- **HTTP 403 on manual journal POST** (often empty body) — same as above: privilege mismatch for the path you use, or the Go client lacks a required module subscription (the diagnose command shows **invalid** privileges tied to subscriptions).

## Can we use another endpoint or ask for more privileges when connecting?

### Requesting privileges during onboarding

**No.** PowerOffice Go **onboarding** ([Initiate](https://developer.poweroffice.net/workflows/onboarding) / finalize) only ties your **application key** to a **Go client** and delivers a **client key**. It does **not** send a “scope” or privilege list that your app can extend per customer.

**API privileges** are attached to your **application key** by PowerOffice (what you agreed for your integration). They show up on the access token; use **`php artisan poweroffice:diagnose {slug}`** and PowerOffice’s [Client Integration Information](https://developer.poweroffice.net/endpoints/client-settings/client-integration-information) to see them. To add **manual journal / voucher posting** (or PDF documentation), you ask **go-api@poweroffice.no** (or your PowerOffice contact) to enable the right **v2 privileges** for your app—not something the POS can request in the connect UI.

### Using a different endpoint instead of manual journals

**Only if that operation is allowed on your token.** Each workflow ([voucher posting](https://developer.poweroffice.net/workflows/endpoints/voucher-workflows/voucher-posting), outgoing invoices, bank/cash journals, etc.) has its **own** endpoints **and** privilege names. Switching **`POWEROFFICE_LEDGER_POST_PATH`** between **`/JournalEntryVouchers/ManualJournals`** and **`/Vouchers/ManualJournals`** does not help if **neither** manual-journal privilege is on the token (same 403).

Examples:

| Idea | Reality |
|------|--------|
| **Outgoing invoice API** | Needs something like **`OutgoingInvoice_*`** on the token. Your demo client may already have **`OutgoingInvoice_Full`** — that could be a **different product** (post Z-totals as an invoice document) with **different payloads, VAT handling, and accounting**. That is **not** implemented in this codebase today; it would be a new integration path. |
| **Account transactions** | The [account transactions](https://developer.poweroffice.net/endpoints/reporting/account-transactions) workflow is oriented around **reading** transactions for reporting, not replacing **voucher posting** for POS day-end. |
| **Journal entry vouchers vs direct posting** | **[Journal Entry Voucher](https://developer.poweroffice.net/endpoints/voucher-workflows/journalentryvoucher)** (`/JournalEntryVouchers/…`) needs **JournalEntryVouchers** privileges; **[direct voucher posting](https://developer.poweroffice.net/workflows/endpoints/voucher-workflows/voucher-posting)** (`/Vouchers/ManualJournals`) needs the matching **direct posting** privileges. They are different privilege sets—confirm with `poweroffice:diagnose`. |

**Practical path for Z-reports as a journal:** keep the current manual-journal design and get **manual journal (+ voucher documentation)** privileges on your application key.

## OpenAPI

See `api-spec.yaml` → tag **PowerOffice** for request/response shapes.
