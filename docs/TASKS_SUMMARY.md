# POS Tasks Summary (Norway / Multi-task)

**Last updated:** From analysis and your latest inputs (coupon screenshots, S3 scope, Shopify CSVs, nested collections, Stripe report).

---

## 1. Får feil når jeg legger inn kupon (Coupon / Discount error) — **FIX APPLIED**

### What was wrong
- **Coupons:** INSERT into `coupons` did not set `stripe_account_id` (NOT NULL). Form had `store_id` but no logic to set `stripe_account_id` from the store.
- **Discounts:** Same for `discounts` — INSERT did not include `stripe_account_id` (NOT NULL), so creating a discount from Filament failed.

*(If your error mentioned `coupon_account_id` instead of `stripe_account_id`, your DB may have a different column name from a custom migration; the codebase uses `stripe_account_id`. If the error persists, we can add a migration to align the column name.)*

### What was done
- **CreateCoupon** and **CreateDiscount** now set `stripe_account_id` before create using `mutateFormDataBeforeCreate`: they read the selected `store_id`, load the Store, and set `stripe_account_id` from `Store::stripe_account_id`.
- Creating a Coupon or Discount from Filament (with a store that has a Stripe account ID) should now succeed.

### You
- Test: create a new Coupon and a new Discount in Filament; confirm no more NOT NULL errors.
- If you still see `coupon_account_id` in the error, tell me and we can add a migration to match your schema.

---

## 2. Move storage to AWS S3 (e.g. Sweden) — **SCOPE CONFIRMED**

### Your choice
- **Everything on S3** where possible.

### Scope to implement
- **Product assets (images):** Spatie Media Library + product/collection image serving → S3, with **public** (or signed) URLs for the app.
- **Shopify import:** CSV/JSON temp and imported images → S3 (configurable disk).
- **POS / compliance:**
  - **SAF-T XML** files (today: default disk `saf-t/`) → S3; 10-year retention (lifecycle or policy).
  - **POS “journals”:** Today journals are **PosEvent** rows in the DB (no separate files). If you want journal *exports* (e.g. PDF) stored for 10 years, we can add that and put those on S3.
  - **Z-reports:** Today generated on-the-fly; full data is in PosEvent `event_data`. If you want Z-report **PDF/artefacts** stored for 10 years, we store those on S3 with retention.
- **Other:** Any Filament/PosReports CSV/PDF exports → S3 if desired.

### Technical plan
- New branch, e.g. `feature/s3-storage`.
- Config: S3 disk(s) (e.g. `eu-north-1` Stockholm), optionally separate buckets/prefixes for products, SAF-T, reports.
- Use S3 for: default or dedicated disks for product media, Shopify import, SAF-T, and (if you confirm) report exports and journal exports.
- Public (or signed) URLs for product/collection images; 10-year retention for POS/SAF-T (and report/journal exports if added).

### You
- Confirm AWS region (e.g. `eu-north-1`) and whether you want Z-report PDFs and journal exports stored on S3 for 10 years. Then implementation can start.

---

## 3. Shopify import – variants not working properly

### Your input
- You provided two CSV files: `products_export_1.csv` and `products_export_1 (1).csv` (Shopify product export format).

### CSV structure (relevant for variants)
- Columns include: Handle, Title, Body, Vendor, **Option1 Name**, **Option1 Value**, Option2/3, **Variant SKU**, **Variant Barcode**, **Variant Price**, **Image Src**, **Variant Image**, Status.
- **Single-variant products:** One row per product, often `Option1 Name = Title`, `Option1 Value = Default Title`.
- **Multi-variant products:** Multiple rows per Handle with same Title/Handle, different Option1/Option2/3 values (e.g. Størrelse S/M/L, Farge Navy/Sort), each with its own Variant Price, SKU, Barcode, and optionally Variant Image.
- Minor difference between the two files: one has `Variant Fulfillment Service`; the other is almost the same (one barcode cell has a leading quote `'084984799754`).

### What needs to be fixed (implementation)
- Ensure the importer:
  - Groups rows by Handle (product), then creates one product and multiple **ProductVariant** rows with correct Option1/2/3 → variant name, price, SKU, barcode.
  - Maps **Variant Image** / **Image Src** per variant where present.
  - Creates/updates Stripe prices per variant and keeps variant ↔ Stripe price mapping correct.
- Your **uncommitted** Shopify import code (e.g. `ImportShopifyProductsChunkJob`, `ShopifyImageFetcher`, `ShopifyImportRun`, CSV chunking) should be reused; variant parsing and sync logic should be verified/fixed against these CSVs.

### You
- No further input required for CSV format. Implementation will use your CSVs as reference and your existing WIP branch.

---

## 4. Multi-level (nested) collections

### Current state
- **Collection** model is flat: no `parent_id`; products linked via `collection_product`.
- API and Filament treat collections as a single-level list.

### Plan
- **Migration:** Add `parent_id` (nullable FK to `collections.id`). Existing collections remain root-level (`parent_id = null`).
- **Model:** `parent()` and `children()` relations; scopes for root-level; ordering (e.g. `sort_order`, `name`).
- **Filament:** Nested/tree UI for managing hierarchy (Filament v4).
- **API:** Support for tree or filtered-by-parent (e.g. `?parent_id=`) and response with `children` where needed for FlutterFlow.

### Need from manager (if unsure)
- **Max depth** (e.g. 2 vs 3 levels)?
- **Same product in multiple branches** allowed or not?
- **API:** Only list roots and children, or full tree in one call?

If these are not fixed by product, implementation can use sensible defaults (e.g. unlimited depth, product in multiple collections allowed) and we can adjust after manager input.

---

## 5. Flytt midlertidig Stripe report løsning inn i POSitiv

### Current codebase
- **Uncommitted code** is focused on **Shopify import** (ShopifyImportTest, ImportShopifyProductsChunkJob, ShopifyImageFetcher, ShopifyImportRun, config, tests). There is **no** Stripe-specific “report” module or temporary report script in this repo.
- In-app reports are **POS-based:** X-report, Z-report, PosReports (Filament), SAF-T. No Stripe Reporting API or separate “Stripe report” solution is present.

### Interpretation
- The “midlertidig Stripe report løsning” is likely **outside** this repo (e.g. script, other repo, or manual process). To move it into POSitiv we need:
  - **Where it lives** (repo, path, or description).
  - **What it does** (e.g. daily revenue per connected account, payout report, tax report) and which Stripe APIs it uses.

### You / manager
- Locate the temporary Stripe report solution and share either the code or a clear description (output + Stripe APIs). Then we can implement it inside POSitiv (e.g. new Filament page or API + job) on a branch like `feature/stripe-report-positiv`.

---

## Git / branches (reminder)

- **One branch per task**, one PR per branch.
- You are on `feature/shopify-shopify-import-dev`, **~155 commits behind main**, with uncommitted Shopify import changes.
- Suggested flow:
  1. Stash or commit current Shopify WIP.
  2. Update from `main` and create task branches from `main`: e.g. `fix/coupon-error`, `feature/s3-storage`, `feature/shopify-import-variants`, `feature/nested-collections`, `feature/stripe-report-positiv`.
  3. **Coupon fix:** Can be committed on a new branch from main (e.g. `fix/coupon-error`) with only the CreateCoupon + CreateDiscount changes.
  4. **Shopify variants:** Reuse your Shopify branch (merge/cherry-pick) onto `feature/shopify-import-variants` and fix variant handling there.

---

## Summary table

| # | Task                         | Status / Next step |
|---|------------------------------|--------------------|
| 1 | Coupon/Discount error        | **Fixed** in code; you test create Coupon/Discount. |
| 2 | S3 storage (everything)      | Scope set; confirm region + Z-report/journal exports; then implement. |
| 3 | Shopify variants              | CSVs reviewed; fix variant parsing/sync in your import WIP. |
| 4 | Multi-level collections      | Plan ready; optional manager input on depth/API. |
| 5 | Stripe report into POSitiv   | Need location/description of “midlertidig” solution from you/manager. |
