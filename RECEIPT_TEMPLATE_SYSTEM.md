# Receipt Template System Documentation

## Overview

The receipt system now supports Epson ePOS XML templates for TM-m30III printers. All receipt types are supported with proper legal compliance markings.

## Template Location

Templates are stored in: `resources/receipt-templates/epson/`

## Available Templates

1. **sales-receipt.xml** - Sales receipt (Salgskvittering)
2. **return-receipt.xml** - Return receipt (Returkvittering) with "RETURKVITTERING" marking
3. **copy-receipt.xml** - Copy receipt with "KOPI" marking (50% larger font)
4. **steb-receipt.xml** - STEB receipt with "STEB-kvittering" marking
5. **provisional-receipt.xml** - Provisional receipt with "Foreløpig kvittering – IKKJE KVITTERING FOR KJØP" marking
6. **training-receipt.xml** - Training receipt with "Treningskvittering – IKKJE KVITTERING FOR KJØP" marking
7. **delivery-receipt.xml** - Delivery receipt with "Utleveringskvittering – IKKJE KVITTERING FOR KJØP" marking

## Template Variables

All templates support the following Mustache variables:

### Store Information
- `{{store_name}}` - Store name
- `{{organization_number}}` - Organization number (from store metadata)
- `{{store_address}}` - Store address (from store metadata)

### Receipt Information
- `{{receipt_number}}` - Sequential receipt number
- `{{session_number}}` - POS session number
- `{{cashier_name}}` - Cashier/user name
- `{{transaction_id}}` - Stripe charge ID
- `{{date_time}}` - Date and time (format: dd.mm.YYYY HH:mm)

### Transaction Details
- `{{items}}` - Array of items (see Items section below)
- `{{total_amount}}` - Total amount (formatted with comma as decimal separator)
- `{{currency}}` - Currency code (e.g., NOK)
- `{{vat_rate}}` - VAT rate (e.g., "25")
- `{{vat_base}}` - VAT base amount
- `{{vat_amount}}` - VAT amount

### Payment Information
- `{{payment_method_display}}` - Payment method in Norwegian (Kontant, Kort, etc.)
- `{{terminal_number}}` - Terminal number (if available in charge metadata)
- `{{card_brand}}` - Card brand (Visa, Mastercard, etc.)
- `{{card_last4}}` - Last 4 digits of card
- `{{tip_amount}}` - Tip amount (if present)

### Return Receipt Specific
- `{{original_receipt_number}}` - Original receipt number (for returns/copies)

## Items Array

Each item in the `{{items}}` array should have:
- `name` - Product name
- `quantity` - Quantity
- `unit_price` - Unit price (formatted: "299,00")
- `line_total` - Line total (formatted: "299,00")

Example:
```json
{
  "items": [
    {
      "name": "Treboks liten",
      "quantity": 1,
      "unit_price": "299,00",
      "line_total": "299,00"
    }
  ]
}
```

## Usage

### Generate Receipt

```php
use App\Services\ReceiptGenerationService;

$receiptService = app(ReceiptGenerationService::class);
$receipt = $receiptService->generateSalesReceipt($charge, $session);
```

### Get XML for Printing

```php
use App\Services\ReceiptTemplateService;

$templateService = app(ReceiptTemplateService::class);
$xml = $templateService->renderReceipt($receipt);
```

### API Endpoints

1. **Generate Receipt**
   ```
   POST /api/receipts/generate
   {
     "charge_id": 123,
     "receipt_type": "sales",
     "pos_session_id": 456
   }
   ```

2. **Get Receipt XML**
   ```
   GET /api/receipts/{id}/xml
   ```
   Returns the Epson ePOS XML ready for printing.

3. **Get Receipt (includes XML)**
   ```
   GET /api/receipts/{id}
   ```
   Returns receipt data with XML in `receipt_data.xml`.

## Legal Compliance

All templates comply with Kassasystemforskriften requirements:

- ✅ Sales receipts marked as "SALGSKVITTERING"
- ✅ Return receipts marked as "RETURKVITTERING"
- ✅ Copy receipts marked as "KOPI" (50% larger font)
- ✅ STEB receipts marked as "STEB-kvittering"
- ✅ Provisional receipts marked with "Foreløpig kvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
- ✅ Training receipts marked with "Treningskvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
- ✅ Delivery receipts marked with "Utleveringskvittering – IKKJE KVITTERING FOR KJØP" (50% larger font)
- ✅ Sequential numbering per receipt type
- ✅ All required fields included (store info, date, transaction ID, items, totals, VAT, payment method)

## Font Size Requirements

According to § 2-8-6, marked text must be at least 50% larger than amount text. This is achieved using:
- `width="2" height="2"` for marked text
- `width="1" height="1"` for normal text

## Customization

### Adding New Templates

1. Create a new XML file in `resources/receipt-templates/epson/`
2. Use Mustache syntax for variables
3. Update `ReceiptTemplateService::getTemplateName()` to map receipt type to template
4. Follow Epson ePOS XML schema: http://www.epson-pos.com/schemas/2011/03/epos-print

### Modifying Existing Templates

Edit the XML files directly. Changes will be reflected immediately (no cache clearing needed).

### Store-Specific Templates

To support store-specific templates:
1. Create subdirectories: `resources/receipt-templates/epson/store-{id}/`
2. Modify `ReceiptTemplateService` to check for store-specific templates first

## Testing

To test receipt generation:

```php
$charge = ConnectedCharge::find(1);
$session = PosSession::find(1);

$receiptService = app(ReceiptGenerationService::class);
$receipt = $receiptService->generateSalesReceipt($charge, $session);

$templateService = app(ReceiptTemplateService::class);
$xml = $templateService->renderReceipt($receipt);

// Save to file for testing
file_put_contents('test-receipt.xml', $xml);
```

## Epson ePOS Integration

The generated XML can be sent directly to Epson ePOS printers via:
- ePOS-Print API
- ePOS-Print SDK
- Direct TCP/IP connection

Example using ePOS-Print API:
```javascript
// Frontend integration
const response = await fetch('/api/receipts/123/xml');
const xml = await response.text();

// Send to printer
eposPrint.send(xml);
```

## Configuration

Receipt configuration is stored in `config/receipts.php`:
- Printer type
- Template paths
- Receipt numbering format
- VAT rates
- Receipt type definitions

## Future Enhancements

- [ ] Support for other printer types (Star, Citizen, etc.)
- [ ] PDF receipt generation
- [ ] Email receipt sending
- [ ] Receipt preview in Filament
- [ ] Store-specific template customization
- [ ] Multi-language support

