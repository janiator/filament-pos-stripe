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

- **Department 20** exists and is used for turnover (set in POS PowerOffice settings as `Department number (all turnover)`).
- Vipps fee account **7720** exists for application-fee debits.

Momskode is **not** sent from POS; the API uses `VatId` resolved from each GL account in PowerOffice.

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
