# SAF-T Cash Register Generation

## Overview

This system generates SAF-T (Standard Audit File for Tax) Cash Register files in compliance with Norwegian tax authority requirements. The implementation follows the [Norwegian SAF-T Cash Register Schema v1.00](https://github.com/skatteetaten/saf-t/blob/master/Norwegian_SAF-T_Cash_Register_Schema_v_1.00.xsd).

## API Endpoints

### Generate SAF-T File

**POST** `/api/saf-t/generate`

Generate and store a SAF-T file for a date range.

**Request Body:**
```json
{
  "from_date": "2025-11-01",
  "to_date": "2025-11-30"
}
```

**Response:**
```json
{
  "message": "SAF-T file generated successfully",
  "filename": "SAF-T_store-slug_2025-11-01_2025-11-30.xml",
  "download_url": "https://.../api/saf-t/download/SAF-T_store-slug_2025-11-01_2025-11-30.xml",
  "size": 12345,
  "from_date": "2025-11-01",
  "to_date": "2025-11-30"
}
```

### Get SAF-T Content

**GET** `/api/saf-t/content?from_date=2025-11-01&to_date=2025-11-30`

Get SAF-T XML content directly (returns XML file).

**Query Parameters:**
- `from_date` (required): Start date (YYYY-MM-DD)
- `to_date` (required): End date (YYYY-MM-DD)

**Response:** XML file with `Content-Type: application/xml`

### Download SAF-T File

**GET** `/api/saf-t/download/{filename}`

Download a previously generated SAF-T file.

**Response:** XML file download

## SAF-T Structure

The generated SAF-T file includes:

### Header
- Audit file version (1.00)
- Country code (NO)
- Creation date and time
- Software information
- Company information
- Tax accounting basis (Cash basis)
- Currency (NOK)

### MasterData
- Cash register information
- Device information

### GeneralLedgerEntries
- Journal entries for each POS session
- Transaction lines for each charge
- Debit/Credit entries
- Tax information

## Data Mapping

### Payment Methods to Account IDs
- **Cash**: Account 1920
- **Card**: Account 1921
- **Other**: Account 1922

### Revenue Account
- **Revenue**: Account 3000

### Tax Information
- Default tax code: `1` (Standard VAT rate)
- Default tax percentage: `25.00%` (Norwegian standard VAT)
- Tax amount calculated: 20% of net (25% of total)

## Requirements

### Store Configuration
The store should have:
- `name`: Company name
- `metadata['organization_number']`: Organization number (org.nr.)

### Session Data
- All sessions must be closed (`status = 'closed'`)
- Sessions must have charges linked (`pos_session_id`)
- Charges must have `status = 'succeeded'`

## Usage Example

```php
// Generate SAF-T for a date range
$generator = new \App\Actions\SafT\GenerateSafTCashRegister();
$xml = $generator($store, '2025-11-01', '2025-11-30');

// Save to file
file_put_contents('saf-t-export.xml', $xml);
```

## FlutterFlow Integration

```dart
// Generate SAF-T file
final response = await api.call(
  'saf-t/generate',
  method: 'POST',
  body: {
    'from_date': '2025-11-01',
    'to_date': '2025-11-30',
  }
);

final downloadUrl = response['download_url'];

// Download file
final fileResponse = await http.get(Uri.parse(downloadUrl));
await File('saf-t-export.xml').writeAsBytes(fileResponse.bodyBytes);
```

## Compliance Notes

✅ **Session Tracking**: All transactions linked to POS sessions
✅ **Double-Entry Bookkeeping**: Debit and credit entries for each transaction
✅ **Tax Information**: VAT calculation and tax codes included
✅ **Audit Trail**: Complete transaction history with timestamps
✅ **Schema Compliance**: Follows Norwegian SAF-T Cash Register Schema v1.00

## Future Enhancements

1. **Product-Level Tax**: Use actual tax rates from products
2. **Digital Signatures**: Add digital signature validation
3. **Validation**: Validate XML against XSD schema
4. **Scheduled Generation**: Automatic monthly SAF-T generation
5. **Filament Admin**: Admin interface for SAF-T file management

