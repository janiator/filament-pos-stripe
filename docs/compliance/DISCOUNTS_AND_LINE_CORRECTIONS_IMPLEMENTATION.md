# Manual Discounts and Line Corrections Implementation

## Overview

This document describes the implementation of manual discount tracking and line corrections in X and Z reports, as required by Skatteetaten FAQ requirements.

**Reference:** [Skatteetaten FAQ - Spørsmål og svar om nye kassasystemer](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/kassasystem/sporsmal-og-svar-om-nye-kassasystemer/)

---

## 1. Manual Discounts Tracking

### Requirement (#10 from FAQ)

**Question:** "Når kreves rabatter spesifisert i X- og Z-rapport?"

**Answer:** "Det er kun rabatt som gis på kassapunktet (prisen korrigeres manuelt i kassen) som skal spesifiseres i X- og Z rapport. Rabatter som blir korrigert automatisk i kassasystemet, f.eks. kampanjer som "ta 3 betal for 2" skal dermed ikke spesifiseres."

### Implementation

**Logic:**
- Only discounts with `discountReason` (manual discounts) are counted
- Automatic discounts (campaigns, coupons) don't have `discountReason` and are excluded
- Tracks both item-level and cart-level manual discounts

**Data Structure:**
```php
'manual_discounts' => [
    'count' => 5,        // Number of manual discounts applied
    'amount' => 50000,   // Total discount amount in øre
]
```

**Display:**
- Shown in X and Z reports (modal and PDF views)
- Displays count and total amount
- Only appears if manual discounts exist

---

## 2. Line Corrections Tracking

### Requirement (#9 from FAQ)

**Question:** "Skal både økning og reduksjon av antall enheter vises som linjekorreksjon i X- og Z-rapport?"

**Answer:** "Kun reduksjon av antall skal vises som linjekorreksjon."

### Implementation

**Database:**
- New table: `pos_line_corrections`
- Fields:
  - `correction_type` (default: 'feilslag')
  - `quantity_reduction` (only reductions count)
  - `amount_reduction` (only reductions count)
  - `reason` (optional)
  - `original_item_data` (snapshot)
  - `corrected_item_data` (snapshot)

**API Endpoints:**
- `GET /api/pos-line-corrections` - List corrections for a session
- `POST /api/pos-line-corrections` - Create a new correction
- `GET /api/pos-line-corrections/{id}` - Get specific correction

**Data Structure:**
```php
'line_corrections' => [
    'total_count' => 3,                    // Total number of corrections
    'total_amount_reduction' => 12000,     // Total amount reduced in øre
    'by_type' => [
        'feilslag' => [
            'type' => 'feilslag',
            'count' => 3,
            'total_quantity_reduction' => 4,
            'total_amount_reduction' => 12000,
        ],
    ],
]
```

**Display:**
- Shown in X and Z reports (modal and PDF views)
- Displays total count and total reduction amount
- Shows breakdown by correction type
- Only appears if line corrections exist

---

## 3. Complete Report Data in Event Logging

### Requirement (#11 from FAQ)

**Question:** "Dersom det blir generert en X-rapport, skal X-rapporten da i sin helhet logges i elektronisk journal?"

**Answer:** "Ja, hele rapporten skal vises i elektronisk journal, ikke bare tidspunktet for generering."

### Implementation

**Event Data Structure:**
```php
PosEvent::create([
    'event_code' => PosEvent::EVENT_X_REPORT, // or EVENT_Z_REPORT
    'event_data' => [
        'report_type' => 'X-Report', // or 'Z-Report'
        'session_number' => '000001',
        'report_data' => $report, // Complete report data
    ],
]);
```

**Applied To:**
- ✅ Filament X-report action (PosSessionsTable)
- ✅ Filament Z-report action (PosSessionsTable)
- ✅ Filament X-report action (PosReports page)
- ✅ Filament Z-report action (PosReports page)
- ✅ API X-report endpoint (PosSessionsController)
- ✅ API Z-report endpoint (PosSessionsController)
- ✅ PDF X-report download (ReportController)
- ✅ PDF Z-report download (ReportController)

---

## 4. Files Modified

### Models
- `app/Models/PosSession.php` - Added `lineCorrections()` relationship
- `app/Models/PosLineCorrection.php` - New model for line corrections

### Migrations
- `database/migrations/2025_12_10_220316_create_pos_line_corrections_table.php` - New table

### Controllers
- `app/Http/Controllers/Api/PosLineCorrectionsController.php` - New API controller
- `app/Http/Controllers/Api/PosSessionsController.php` - Updated to use shared report methods and include complete data in events
- `app/Http/Controllers/ReportController.php` - Updated to include complete data in events

### Report Generation
- `app/Filament/Resources/PosSessions/Tables/PosSessionsTable.php`:
  - Added `calculateManualDiscounts()` method
  - Added `calculateLineCorrections()` method
  - Updated `generateXReport()` to include manual discounts and line corrections
  - Updated `generateZReport()` to include manual discounts and line corrections
  - Updated event logging to include complete report data

- `app/Filament/Resources/PosReports/Pages/PosReports.php`:
  - Added `calculateManualDiscounts()` method
  - Added `calculateLineCorrections()` method
  - Updated `generateXReport()` to include manual discounts and line corrections
  - Updated `generateZReport()` to include manual discounts and line corrections
  - Updated event logging to include complete report data

### Views
- `resources/views/filament/resources/pos-reports/modals/x-report.blade.php` - Added manual discounts and line corrections sections
- `resources/views/filament/resources/pos-reports/modals/z-report.blade.php` - Added manual discounts and line corrections sections
- `resources/views/reports/x-report-pdf.blade.php` - Added manual discounts and line corrections sections
- `resources/views/reports/z-report-pdf.blade.php` - Added manual discounts and line corrections sections

### Routes
- `routes/api.php` - Added line corrections endpoints

### Filament Resources
- `app/Filament/Resources/PosSessions/Pages/EmbedPosSessions.php` - New embed page
- `app/Filament/Resources/PosSessions/PosSessionResource.php` - Added embed route

### Documentation
- `docs/compliance/SKATTEETATEN_FAQ_ADDITIONAL_REQUIREMENTS.md` - FAQ requirements documentation
- `docs/flutterflow/EMBED_POS_SESSIONS_LIST.md` - API documentation for embedding POS sessions

---

## 5. API Usage Examples

### Create Line Correction

```http
POST /api/pos-line-corrections
Authorization: Bearer {token}
Content-Type: application/json

{
  "pos_session_id": 123,
  "correction_type": "feilslag",
  "quantity_reduction": 2,
  "amount_reduction": 6000,
  "reason": "Feilslag - registrert feil vare",
  "original_item_data": {
    "product_id": 456,
    "quantity": 5,
    "unit_price": 3000
  },
  "corrected_item_data": {
    "product_id": 456,
    "quantity": 3,
    "unit_price": 3000
  }
}
```

### List Line Corrections

```http
GET /api/pos-line-corrections?pos_session_id=123
Authorization: Bearer {token}
```

### Get Current POS Session

```http
GET /api/pos-sessions/current?pos_device_id=1
Authorization: Bearer {token}
```

### List POS Sessions

```http
GET /api/pos-sessions?status=open&per_page=10
Authorization: Bearer {token}
```

---

## 6. Compliance Status

✅ **Manual Discounts** - Fully implemented per FAQ requirement #10
- Only manual discounts (with `discountReason`) are tracked
- Automatic discounts are excluded
- Displayed in X and Z reports

✅ **Line Corrections** - Fully implemented per FAQ requirement #9
- Only reductions count as line corrections
- API endpoints available for tracking
- Displayed in X and Z reports with type breakdown

✅ **Complete Report Data in Events** - Fully implemented per FAQ requirement #11
- Complete report data included in `event_data` for all report generation methods
- Ensures electronic journal compliance

✅ **POS Sessions Embed** - Documentation complete
- API endpoints documented
- Embed route available at `/app/store/{tenant}/pos-sessions/embed`
- FlutterFlow integration examples provided

---

## References

- [Skatteetaten FAQ](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/kassasystem/sporsmal-og-svar-om-nye-kassasystemer/)
- [Kassasystemforskriften FOR-2015-12-18-1616](https://lovdata.no/dokument/SF/forskrift/2015-12-18-1616)

