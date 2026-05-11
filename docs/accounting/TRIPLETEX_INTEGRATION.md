# Tripletex integration (POS backend)

Tripletex is an optional per-store add-on (`AddonType::Tripletex`). It posts **ledger vouchers** from POS data already mirrored in this application: **Z-report snapshots** on closed `pos_sessions`, and **Stripe payouts** in `store_stripe_payouts` with related `store_stripe_balance_transactions`.

## Flows

| Flow | Trigger | Idempotency key | Notes |
|------|---------|-----------------|-------|
| Z-report voucher | `PosEvent` Z-report (when auto-sync on) or manual/API/Filament | `tripletex:z-report:{store_id}:{pos_session_id}` | Sales/VAT/tips/payment clearing from `closing_data.z_report_data` (or generated snapshot). |
| Payout voucher | `StoreStripePayout` saved as `paid` (when auto-sync on) or manual/API/Filament | `tripletex:payout:{store_id}:{stripe_payout_id}` | Bank vs Stripe clearing; fees from balance tx rows with same `stripe_payout_id` (optionally split into **application fee** vs **Stripe processing fee** when mirror `fee_details` exist and `settings.ledger.application_fee.debit_account_no` is set). Optional **external / web ticket** paired lines when `settings.ledger.external_ticket_sales` is enabled (see below). |

## Sync runs: skipped vs failed

`tripletex_sync_runs.status` includes **`skipped`** when a voucher was intentionally not posted but the integration is present (for example: Z-report has nothing to post, automatic sync is off and the job was not forced, Tripletex sync disabled for the store, or a payout row is not `paid` yet). The **reason** is stored in `error_message` (same column as failure text) and logged at `info` as `Tripletex voucher sync skipped` with `tripletex_sync_run_id` and `reason`.

If there is no Tripletex integration row or the add-on is inactive, the code only **logs** the reason (no sync run row). **Retry** in Filament sync history is available for both **Failed** and **Skipped** rows.

## Avoiding double posting (fees / bank)

By default, **Z-report vouchers do not** include Stripe settlement lines (`z_report_include_settlement` is `false`). Use **payout vouchers** for bank / clearing / fee movements tied to Stripe payout timing.

If you enable settlement lines on the Z voucher **and** use payout vouchers, you can double-post the same economic movement—keep one source of truth per amount type.

## Credentials & environment

- Stored on `tripletex_integrations`: encrypted `consumer_token`, `employee_token`, `environment` (`test` / `prod`).
- Session: `PUT` to Tripletex session create path with query params (see `config/tripletex.php`).
- Base URLs: `TRIPLETEX_TEST_BASE_URL`, `TRIPLETEX_PROD_BASE_URL` (defaults in config).

## Account mapping

- **Account mappings** (`tripletex_account_mappings`): same basis types as PowerOffice (VAT, collection, vendor, payment method) for Z-report sales and payment fallbacks.
- **Ledger routing** in `settings.ledger` (same shape as PowerOffice): `payment_debits`, `payment_fee`, `payout`, `default_sales_account_no`, etc. Editable in Filament **Tripletex** settings.
- **Z-report calendar-day lines** — `ledger.z_report_split_lines_by_calendar_day` (boolean, default off). When on, succeeded `connected_charges` for the session are grouped by calendar day in `config('app.timezone')`; sales, VAT, tips, payment debits, and optional settlement lines are **allocated across days** so each ledger line carries `posting_date` (Tripletex per-posting `date`) while the voucher stays **one document per session**. If there are no usable charges, behaviour matches the toggle off (single `document_date`).
- **Tripletex VAT types on Z (optional)** — `ledger.tripletex_vat_type_sales` (map of basis key, e.g. `"25"`, to Tripletex VAT type id) and `ledger.tripletex_vat_type_output_vat` for the output VAT posting. Omitted keys leave postings without `vatType` in Tripletex JSON.
- **Application fee vs Stripe fee** — `ledger.application_fee.debit_account_no` plus existing `payment_fee` credit/debit pair. Application-fee amounts come from Stripe `fee_details` on charge balance transactions (and from separate `application_fee` balance transactions). `ledger.app_fee_supplier_id` is attached on application-fee **expense** postings in Tripletex when set.
- **External / web ticket sales on payout** — `ledger.external_ticket_sales`: `enabled`, `sales_account_no`, optional `clearing_account_no` (defaults to payout settlement credit account), optional `tripletex_vat_type_id`, `require_metadata_keys` (default `booking_id`), optional `description_regex`. A charge qualifies only if the linked `connected_charges` row has **`pos_session_id` null** and passes metadata/regex rules—avoid double-booking POS-attributed charges. Required metadata keys are satisfied using a **union** of `connected_charges.metadata` and `store_stripe_balance_transactions.source_metadata` (Stripe charge metadata on the payout mirror row), with the charge row winning on duplicate keys—so keys present only on one side (e.g. `booking_id` from the balance transaction sync) still match.

On the Filament **Tripletex** page, when a store has **no** mapping rows yet and **no** `ledger.payout` credit/debit accounts saved, the form is **prefilled** from `config/tripletex.php` key `default_form_state` (values taken from the legacy Merano-Tripletex-Sync `config.js` `ACCOUNT` map: e.g. kiosk sales `3001`, ticket lines `3200`/`3201`, clearing `1900`/`1901`/`1902`, bank `1920`, Stripe fee `7771`). The same prefill includes **external / web ticket** fields aligned with Merano `main.js` §7f: sales **`3200`** (`SALES_BILLETTER_FORHAND`), clearing **`1901`** (`CLEARING_STRIPE`), Tripletex VAT type id **`6`** (Merano `VAT_TYPE.MVA_NONE` — adjust if your Tripletex chart uses a different id). Adjust or clear fields before saving; once mappings or payout routing exist, prefill no longer applies.

Tripletex account numbers are resolved to Tripletex ledger account IDs per sync via `GET /ledger/account?number=…` before posting.

## Amounts (decimals)

Internal ledger payloads use **integer minor units** (e.g. NOK øre). `TripletexManualVoucherPayloadFactory` converts each line to Tripletex `amountGross` / `amountGrossCurrency` as a **major-unit float rounded to two decimals**: debits positive, credits negative. That matches the legacy Merano-Tripletex-Sync `voucherBuilder.js` convention (`signedAmt = l.credit ? -amt : amt` with `amt` from `toFixed(2)`), while avoiding float accumulation drift from the old script’s summed float `amount` values.

**Payout preview diagnostics** — Filament and API payout previews include `payout_external_ticket_sales` with counts (charges in the payout mirror, without `pos_session_id`, matched for external-ticket lines) and short notes when web ticket lines are absent, so operators can see whether the feature is off, everything is POS-attributed, metadata/regex failed, or `connected_charges` rows are missing.

## HTTP / env

- `TRIPLETEX_VOUCHER_POST_PATH` — default `/ledger/voucher`
- `TRIPLETEX_SEND_TO_LEDGER` — default `false` (draft vouchers)
- `TRIPLETEX_SESSION_CREATE_PATH` — default `/token/session/:create`
- `TRIPLETEX_HTTP_TIMEOUT`

## API (Bearer + tenant)

Same auth as other tenant APIs (see `api-spec.yaml` **Tripletex** tag):

- `POST /api/tripletex/sync/z-report/{posSession}`
- `POST /api/tripletex/sync/payout/{payout}` — `{payout}` is the **database id** of `store_stripe_payouts`
- `POST /api/tripletex/sync/retry/{syncRun}` — `tripletex_sync_runs.id`
- `GET /api/tripletex/preview/z-report/{posSession}?resolve_accounts=false` — JSON preview of ledger lines (no voucher posted). With `resolve_accounts=true`, the response may include `tripletex_voucher_payload` after Tripletex account lookup (or `resolve_error`).
- `GET /api/tripletex/preview/payout/{payout}?resolve_accounts=false` — same for a paid payout row.
- `POST /api/tripletex/sync/historical` — body `{ "type": "z_report"|"payout", "from": "Y-m-d"?, "to": "Y-m-d"?, "limit": 50, "only_missing": true }` queues up to `limit` jobs (`sync_enabled` must be on, same as other sync routes).

## Preview (no post)

`TripletexSyncPreviewService` builds the same internal ledger payload as a real sync (`lines`: account number, `debit_minor` / `credit_minor`, description, optional `posting_date`, `tripletex_vat_type_id`, `tripletex_supplier_id`, `line_kind` for payout payloads, document date, currency). `lines_display` echoes optional `posting_date` and `line_kind`. It does **not** create or update `tripletex_sync_runs` and does **not** call Tripletex unless `resolve_accounts=true` (then it runs session token + ledger account GET + voucher payload factory only—still no `POST` voucher).

`TripletexManualVoucherPayloadFactory` maps internal lines to Tripletex voucher JSON: per-line `date` from `posting_date` or `document_date`, optional `vatType` / `supplier`, voucher header `date` = minimum effective line date when any line sets `posting_date`.

Filament **Tripletex** page: voucher preview actions (latest Z / latest payout) and **Clear preview**; session and payout view pages also expose previews.

## Historical backfill

- **Filament** — **Queue historical Z-reports** / **Queue historical payouts**: optional date range, limit (1–500), and “only missing successful sync” toggle (default on). Dispatches the same queue jobs as manual sync (`force=true`).
- **CLI** — `php artisan tripletex:sync-historical {store_id} [--type=z-report|payout] [--from=Y-m-d] [--to=Y-m-d] [--limit=50] [--all]`. Requires Tripletex add-on on the store. `--all` queues even when a successful Tripletex run already exists (use with care).

## Admin (Filament)

- **Tripletex** — integration settings, test connection, queue latest Z sync, **previews**, **historical queue** actions.
- **Tripletex sync history** (hidden from sidebar; open via **Sync history** on the integration page) — list runs and **Retry** failed or skipped rows (see **Sync runs: skipped vs failed** above).
- **POS sessions** table and session view — Tripletex sync status columns (**TX Synced**, **TX Voucher**), row actions **Preview Tripletex voucher** (slide-over JSON) and **Sync Tripletex**, bulk **Sync selected to Tripletex**, and filter **Tripletex Synced** (alongside existing PowerOffice columns/actions).
- **Stripe payouts** table and payout view — **TX** status column, **TX recon** badge (read-only comparison of mirror vs last successful sync `request_payload`), row actions **Reconcile** (slide-over JSON report), **Preview Tripletex voucher**, and **Sync Tripletex** for paid payouts in the tenant store.

## Stripe mirror: `stripe_payout_id`

`store_stripe_balance_transactions.stripe_payout_id` is filled when syncing from Stripe (`SyncStoreStripeBalanceTransactionsFromStripe`) so payout fee totals can be aggregated per payout.

That sync lists balance transactions with **`expand[]=data.source`** so charge sources include metadata when available. Persisted columns (when present): **`fee_details`** (JSON from Stripe), **`source_metadata`** (charge `metadata`), **`stripe_payment_intent_id`** (string). These power fee split and external-ticket detection without extra Stripe calls at voucher build time.

## Payout reconciliation (Filament)

`TripletexPayoutReconciliationService` compares the **paid** payout amount and mirror fee totals to the **last successful** `tripletex_sync_runs` row for that payout (`sync_type` payout). It classifies amounts using `line_kind` on stored `request_payload.lines` (with a small fallback for older payloads that only had descriptions). Status: **`ok`**, **`warn`**, or **`fail`**; tolerance **1** minor unit. No API or scheduled job—**Reconcile** on the payouts table only.

## Related code

- Services: `app/Services/Tripletex/` (including `TripletexPayoutReconciliationService`)
- Jobs: `SyncTripletexZReportJob`, `SyncTripletexPayoutJob`
- Observer: `PosEventObserver`, `StoreStripePayoutObserver`
