# FlutterFlow Custom Widget: Printer Detection Manager

This widget allows users to detect network printers, register them with IP addresses, and assign them to POS devices in the Filament backend.

## Features

- **Network Printer Scanning**: Automatically scans the local network for printers
- **Manual Printer Entry**: Add printers manually by entering IP address
- **Printer Registration**: Register new printers or update existing ones
- **POS Device Assignment**: Assign printers to specific POS devices
- **Printer Management**: View and edit all registered printers

## Setup Instructions

### Step 1: Add Custom Widget in FlutterFlow

1. In FlutterFlow, go to **Custom Code** → **Custom Widgets**
2. Click **+ Add Widget**
3. Name: `PrinterDetectionManager`
4. **Parameters** (add these in FlutterFlow UI):
   - `apiBaseUrl` - Type: `String` - Required
   - `authToken` - Type: `String` - Required
   - `currentPosDeviceId` - Type: `int` - Optional (pre-selects current POS device)
   - `width` - Type: `double` - Optional
   - `height` - Type: `double` - Optional

5. **Widget Code**: Copy the code from `docs/flutterflow/custom-widgets/printer_detection_manager.dart` and paste it in FlutterFlow

### Step 2: Add Required Dependencies

The widget uses the following packages. Make sure they are added to your `pubspec.yaml`:

```yaml
dependencies:
  http: ^1.1.0
  multicast_dns: ^0.3.2+2
```

**Platform Permissions**:

For **iOS** (in `ios/Runner/Info.plist`), add these keys for mDNS/Bonjour discovery:
```xml
<key>NSLocalNetworkUsageDescription</key>
<string>Required to discover local network printers</string>
<key>NSBonjourServices</key>
<array>
    <string>_http._tcp</string>
    <string>_printer._tcp</string>
    <string>_ipps._tcp</string>
</array>
```

For **Android**, no special permissions are needed beyond the default INTERNET permission.

### Step 3: Using the Widget

1. **Add Widget to Page**:
   - Drag the `PrinterDetectionManager` widget onto your page
   - Set the required parameters:
     - `apiBaseUrl`: Your API base URL (e.g., `https://api.example.com`)
     - `authToken`: The user's authentication token (from `FFAppState().authToken` or similar)

2. **Optional Parameters**:
   - `currentPosDeviceId`: If you want to pre-select the current POS device, pass the device ID

## Usage Example

### Basic Usage

```dart
PrinterDetectionManager(
  apiBaseUrl: 'https://api.example.com',
  authToken: FFAppState().authToken,
)
```

### With Current POS Device Pre-selection

```dart
PrinterDetectionManager(
  apiBaseUrl: 'https://api.example.com',
  authToken: FFAppState().authToken,
  currentPosDeviceId: FFAppState().currentPosDeviceId,
)
```

**Note**: The widget automatically refreshes the printer list after registration. If you need to trigger additional actions, you can use FlutterFlow's action system or state management to listen for changes.

## Widget Features

### Network Scanning

The widget can scan the local network for printers by:
1. Detecting the local IP address
2. Scanning common printer ports (9100, 515, 631) on the local network
3. Displaying detected printers for selection

**Note**: Network scanning may take some time and may not detect all printers. For best results, use manual entry if you know the printer's IP address.

### Manual Printer Entry

Users can manually add printers by:
1. Clicking "Add Manual" button
2. Entering printer details:
   - Name
   - IP Address
   - Port (default: 9100)
   - Device ID (default: local_printer)
   - Printer Type (Epson, Star, Generic)
   - Paper Width (80mm or 58mm)
   - POS Device Assignment (optional)

### Printer Management

- **View Registered Printers**: See all printers registered for the current store
- **Edit Printers**: Tap on any printer to edit its settings
- **POS Assignment**: Assign or change POS device assignment
- **Status Indicators**: See which printers are active/inactive

## API Endpoints Used

The widget uses the following API endpoints:

- `GET /api/receipt-printers` - List all printers
- `POST /api/receipt-printers` - Register new printer
- `PUT /api/receipt-printers/{id}` - Update printer
- `GET /api/pos-devices` - List POS devices for assignment

All endpoints require authentication via Bearer token.

## Printer Configuration

### Supported Printer Types

- **Epson**: Epson ePOS-Print compatible printers
- **Star**: Star Micronics printers
- **Generic**: Generic network printers

### Connection Settings

- **IP Address**: Required for network printers
- **Port**: Default 9100 (raw printing port)
- **Device ID**: Default "local_printer" (for Epson ePOS-Print)
- **HTTPS**: Enable if printer supports HTTPS

### Paper Width Options

- **80mm**: Standard receipt width
- **58mm**: Narrow receipt width

## POS Device Assignment

Printers can be assigned to specific POS devices:

1. When registering/editing a printer, select a POS device from the dropdown
2. The printer will be linked to that POS device
3. The POS device can then use this printer as its default printer

## Platform Compatibility

### Network Scanning

- **iOS/Android**: Full support for network scanning
- **Web**: Network scanning is not available (use manual entry instead)

The widget automatically detects the platform and disables network scanning on web. Manual printer entry works on all platforms.

## Troubleshooting

### Network Scanning Not Finding Printers

- Ensure the device is on the same network as the printer
- Check if the printer's firewall is blocking connections
- Try manual entry with the known IP address
- Verify printer is powered on and connected to network
- **Note**: Network scanning may not work on all networks or with all printer models

### Registration Fails

- Verify IP address is correct and printer is reachable
- Check that the port is correct (default 9100)
- Ensure authentication token is valid
- Check API base URL is correct

### Printer Not Showing in List

- Refresh the printer list by pulling down
- Check that the printer was registered to the correct store
- Verify API permissions

## Integration with Filament

The widget integrates with the Filament backend:

- Printers are stored in the `receipt_printers` table
- POS assignments are stored via `pos_device_id` foreign key
- All printers are scoped to the current store (via authentication)

## Security Notes

- All API calls require authentication
- Printers are scoped to the authenticated user's store
- POS device assignments are validated to ensure they belong to the same store

