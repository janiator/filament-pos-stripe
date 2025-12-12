# Printer Detection Widget - Quick Start Guide

## Quick Setup

1. **Copy Widget Code**
   - Copy `docs/flutterflow/custom-widgets/printer_detection_manager.dart`
   - Paste into FlutterFlow Custom Widget editor

2. **Add Dependencies**
   ```yaml
   dependencies:
     http: ^1.1.0
   ```

3. **Add Widget to Page**
   ```dart
   PrinterDetectionManager(
     apiBaseUrl: 'YOUR_API_BASE_URL',
     authToken: FFAppState().authToken,
   )
   ```

## API Endpoints Reference

The widget uses these endpoints (already implemented):

- `GET /api/receipt-printers` - List printers
- `POST /api/receipt-printers` - Create printer
- `PUT /api/receipt-printers/{id}` - Update printer
- `GET /api/pos-devices` - List POS devices

All endpoints require `Authorization: Bearer {token}` header.

## Request/Response Examples

### Create Printer

**Request:**
```json
POST /api/receipt-printers
{
  "name": "Main Receipt Printer",
  "printer_type": "epson",
  "paper_width": "80",
  "connection_type": "network",
  "ip_address": "192.168.1.100",
  "port": 9100,
  "device_id": "local_printer",
  "use_https": false,
  "pos_device_id": 1
}
```

**Response:**
```json
{
  "message": "Receipt printer created successfully",
  "printer": {
    "id": 1,
    "name": "Main Receipt Printer",
    "ip_address": "192.168.1.100",
    "port": 9100,
    "printer_type": "epson",
    "pos_device": {
      "id": 1,
      "device_name": "Kasse 1"
    }
  }
}
```

### Update Printer

**Request:**
```json
PUT /api/receipt-printers/1
{
  "name": "Updated Printer Name",
  "ip_address": "192.168.1.101",
  "pos_device_id": 2
}
```

## Common Use Cases

### 1. Register Printer for Current POS Device

```dart
PrinterDetectionManager(
  apiBaseUrl: FFAppState().apiBaseUrl,
  authToken: FFAppState().authToken,
  currentPosDeviceId: FFAppState().currentPosDeviceId,
)
```

### 2. Standalone Printer Management

```dart
PrinterDetectionManager(
  apiBaseUrl: FFAppState().apiBaseUrl,
  authToken: FFAppState().authToken,
)
```

## Troubleshooting

**Network scan not working?**
- Use manual entry instead
- Check device is on same network
- Verify printer IP address

**Registration fails?**
- Check IP address is correct
- Verify port (default: 9100)
- Ensure printer is powered on
- Check API authentication token

**Printer not showing?**
- Pull down to refresh
- Check store assignment
- Verify API permissions

