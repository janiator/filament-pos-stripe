# Multi-Day Sessions and Compliance

## Overview

This document addresses the compliance implications of allowing POS sessions to span multiple days and the implementation of date display in Z-reports.

## Norwegian Tax Administration Requirements

According to the Norwegian Tax Administration (Skatteetaten), **daily settlements (dagsoppgjør) are required** for each cash point. At the end of each day, a Z-report must be generated from each cash point, along with reports from each payment terminal detailing registered payments and withdrawals.

**Key Requirements:**
- Daily cash balance counting
- Daily comparison with Z-reports
- Explanation of any discrepancies
- Z-reports must be retained (printed or stored electronically)

**Reference:** [Skatteetaten - Daily Settlement Requirements](https://www.skatteetaten.no/en/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-15/M-15-10/M-15-10.6/)

## System Implementation

### Current Behavior

The system **allows** POS sessions to span multiple days. This is technically possible because:
- Sessions are opened with `opened_at` timestamp
- Sessions are closed with `closed_at` timestamp
- There is no hard-coded restriction preventing multi-day sessions

### Compliance Recommendation

**While multi-day sessions are technically possible, daily Z-reports are strongly recommended for compliance with Norwegian regulations.**

**Best Practice:**
1. **Close sessions daily** - Generate a Z-report at the end of each business day
2. **Open new session** - Start a fresh session for the next day
3. **Daily reconciliation** - Count cash daily and compare with Z-reports

### Implementation for Multi-Day Sessions

If a session does span multiple days (e.g., due to operational needs or system issues), the Z-report implementation has been updated to:

1. **Detect multi-day sessions** - Automatically detects when `opened_at` and `closed_at` are on different dates
2. **Display dates in transaction list** - When a session spans multiple days, the transaction list shows both date and time (e.g., "25.12.2025 14:30:15") instead of just time (e.g., "14:30:15")
3. **Update column header** - Changes from "Tid" (Time) to "Dato & Tid" (Date & Time) when needed

### Code Implementation

**Detection Logic:**
```php
$sessionStartDate = $session->opened_at->format('Y-m-d');
$sessionEndDate = $session->closed_at ? $session->closed_at->format('Y-m-d') : now()->format('Y-m-d');
$spansMultipleDays = $sessionStartDate !== $sessionEndDate;
```

**Transaction List Format:**
- **Single day:** `H:i:s` (e.g., "14:30:15")
- **Multiple days:** `d.m.Y H:i:s` (e.g., "25.12.2025 14:30:15")

### Views Updated

1. **Modal View** (`z-report.blade.php`) - Filament modal display
2. **PDF View** (`z-report-pdf.blade.php`) - PDF export
3. **Report Generation** (`PosSessionsTable.php` and `PosReports.php`) - Backend logic

## Compliance Status

✅ **COMPLIANT** - The system properly handles multi-day sessions by:
- Detecting when sessions span multiple days
- Displaying dates in transaction lists when needed
- Maintaining all required data for compliance

⚠️ **RECOMMENDATION** - For optimal compliance with daily settlement requirements:
- Close sessions daily
- Generate Z-reports at end of each business day
- Perform daily cash reconciliation

## References

- [Kassasystemforskriften FOR-2015-12-18-1616](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)
- [Skatteetaten - Daily Settlement Requirements](https://www.skatteetaten.no/en/rettskilder/type/handboker/merverdiavgiftshandboken/gjeldende/M-15/M-15-10/M-15-10.6/)



