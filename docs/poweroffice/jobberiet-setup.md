# Jobberiet PowerOffice Go setup

Operational checklist for accountant (Anne Berit) and store admin after POS PowerOffice integration is connected. Ledger account numbers are configured in **Filament → PowerOffice → Accounting**, not in this document.

## PowerOffice Go (accountant)

Create or verify these GL accounts in PowerOffice Go:

| Account | Purpose | Momskode in PowerOffice Go |
|---------|---------|----------------------------|
| 3000 | Neverstua sales | 3 (25% VAT) |
| 3020 | Vaskeri sales | 3 |
| 3022 | Uteavdelingen sales | 3 |
| 3023 | Stuttreist / commission revenue | 3 |
| 5910 | Kantine sales | **0** (0% VAT) |
| 2700 | Output VAT | — |
| 3001 | Tips | — |
| 1920 | Cash | — |
| 1921 | Card / Vipps clearing | — |
| 7720 | Vipps fee expense | — |

Also confirm:

- **Department 20** exists; it is applied to **every voucher line** (turnover, payments, fees), matching manual bookkeeping.
- Vipps fee account **7720** exists for application-fee debits.
- The integration has **ReverseVoucher_Full** privilege in PowerOffice Go — required for the re-sync action (reverse old voucher + post corrected one).

Momskode is **not** configured from POS; the API sends `VatId` on **every** voucher line — from each GL account's vat code when set, otherwise PowerOffice VAT code **0** (ingen mva). Vendor reskontro lines use code 0 so VAT is only reported on the store's own turnover + commission revenue.

## Account check & bulk create

On **Filament → PowerOffice**, the **PowerOffice accounts** section has **Check accounts in PowerOffice**: it verifies every saved account number (mappings, ledger routing, vendor commission accounts via `GET /GeneralLedgerAccounts`; vendor leverandørnr / reskontro numbers via `GET /Suppliers`). Missing numbers can be bulk-created with **Create missing in PowerOffice** — GL accounts are created with a name + vat code (`POST /GeneralLedgerAccounts`, requires `GeneralLedgerAccount_Full`); vendor reskontro rows are created as suppliers with `Number = leverandørnr` (`POST /Suppliers`, requires `Supplier_Full`). The leverandørnr must be the intended supplier account number (e.g. 40001) and must fall inside the client's supplier sub-ledger number series in PowerOffice Go.

## Voucher shape (matches the accountant's manual booking)

- Hybrid mode uses the Z-report **Salg per leverandør** table (`sales_by_vendor`) for vendor reskontro and provision (commission) amounts — the same figures as on the PDF.
- Store-owned turnover (no-vendor bucket) is split across **article group** accounts (3020, 3000, …) from product lines; only scaled when product subtotals drift from the Z-report store bucket.
- Sales are credited **gross (incl. VAT)** to sales/reskontro accounts. PowerOffice splits out the VAT from each line's vat code — **no explicit VAT line** is posted to 2700/2701. Vendor reskontro lines carry no vat code, so VAT is only reported on the store's own turnover + commission revenue, exactly like the manual voucher.
- Commission vendors: vendor share → reskontro (`amount − provision` from Z-report), provision → commission account (3023). Amounts come from the Z-report, not a second product-line recalculation.
- Payment debits are gross per method (cash / card). To mirror the accountant exactly (bank debited directly, no interim/fee/payout lines), set `payment_debits.card` to the **bank account** (e.g. 1920) and leave the **payment fee** and **payout** account pairs empty in PowerOffice settings. Configure fee/payout pairs only if you want the Stripe settlement modelled through an interim account instead.

## Re-sync (corrected Z-report)

The **Sync PowerOffice** action on a POS session is safe to re-run. If a voucher was already posted, Filament asks for confirmation, then POSTs `/Vouchers/Reverse/{id}` (PowerOffice creates a reversal voucher and frees the `ExternalImportReference`) and posts a fresh voucher from the current Z-report snapshot. Automatic sync on session close never reverses.

## POS admin (Filament)

1. **Article group codes** — Create store codes (format `04xxx`) for: Neverstua, Vaskeri, Uteavdelingen, Stuttreist (non-commission fallback), Kantine.
2. **PowerOffice → Accounting** — Mapping basis: **Product collection** (hybrid mode).
   - Map each **article group code** → sales account (primary routing).
   - Map each **product collection** → same accounts (fallback).
   - Set department **20**, default sales **3000**, commission fallback **3023**, Vipps fee debit **7720**.
   - Shared: VAT **2700**, tips **3001**, cash **1920**, card **1921**.
3. **Products** — Assign **article group code** on each product (primary). Collection is optional backup.
4. **Kantine products** — Use **0% VAT** in POS; map Kantine article group / collection to **5910**.
5. **Stuttreist vendor** — On the vendor record: `commission_percent = 10`, supplier reskontro `4xxxx`, commission revenue **3023** (or leave blank to use global commission account). Commission products post 90/10 via vendor fields, not varegruppe 3023 for the full amount.

## Income routing order (POS → PowerOffice)

For each Z-report product line:

1. Vendor with **commission % > 0** → 90% reskontro + 10% commission account.
2. Else **article group mapping** → sales account.
3. Else **product collection mapping** → sales account.
4. Else **default sales account** (e.g. 3000).

Do **not** set mapping basis to **Vendor** for Jobberiet-style setups; that posts all turnover to reskontro and skips varegruppe accounts.

## Anne Berit confirmation

After deploy and Filament config:

1. Confirm five article group codes exist in POS admin.
2. Confirm PowerOffice mappings match the table above.
3. Assign products to article group codes in POS (Anne Berit can complete product assignment once codes exist).

Optional: use the **setup status** table on PowerOffice settings to see mapped accounts and product counts per article group code.
