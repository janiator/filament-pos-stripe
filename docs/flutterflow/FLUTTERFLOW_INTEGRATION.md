# FlutterFlow Integration Guide

## POS Device Registration

This guide explains how to integrate POS device registration in your FlutterFlow app using the custom actions provided.

## Setup

### 1. Add Dependencies

In your FlutterFlow project, add these dependencies to `pubspec.yaml`:

```yaml
dependencies:
  device_info_plus: ^12.2.0
  http: ^1.1.0
```

### 2. Add Custom Actions

Copy the custom actions from `flutterflow_custom_actions/register_pos_device.dart` to your FlutterFlow project:

1. In FlutterFlow, go to **Custom Code** → **Custom Actions**
2. Create a new custom action named `registerPosDevice`
3. Paste the `registerPosDevice` function code
4. Create another custom action named `updateDeviceHeartbeat`
5. Paste the `updateDeviceHeartbeat` function code
6. (Optional) Create `getDeviceInfo` for testing

### 3. Configure Action Parameters

#### registerPosDevice Action:
- **apiBaseUrl** (String, required): Your API base URL
  - Production: `https://pos.visivo.no/api`
  - Local: `https://pos-stripe.test/api`
- **authToken** (String, required): Bearer token from login
- **deviceName** (String, optional): Custom device name
- **deviceMetadata** (Map<String, dynamic>, optional): Additional metadata

#### updateDeviceHeartbeat Action:
- **apiBaseUrl** (String, required): Your API base URL
- **authToken** (String, required): Bearer token
- **deviceId** (String, required): Device ID from registration
- **deviceStatus** (String, optional): active, inactive, maintenance, offline
- **deviceMetadata** (Map<String, dynamic>, optional): Battery, storage, etc.

## Usage in FlutterFlow

### Register Device on App Start

1. **After Login Success:**
   - Add a **Custom Action** step
   - Select `registerPosDevice`
   - Set parameters:
     - `apiBaseUrl`: Use your API base URL variable
     - `authToken`: Use the token from login response
     - `deviceName`: Optional, can be left empty
     - `deviceMetadata`: Optional, can be left empty

2. **Store Device ID:**
   - Save the `deviceId` from the response to a variable
   - Use this for heartbeat updates

### Update Device Heartbeat

1. **Periodic Heartbeat (e.g., every 5 minutes):**
   - Use a **Timer** or **Periodic Action**
   - Call `updateDeviceHeartbeat` action
   - Set parameters:
     - `apiBaseUrl`: Your API base URL
     - `authToken`: Current auth token
     - `deviceId`: Stored device ID
     - `deviceStatus`: "active" (or current status)
     - `deviceMetadata`: Optional metadata (battery level, etc.)

2. **On App Resume:**
   - Call `updateDeviceHeartbeat` when app comes to foreground
   - Update `last_seen_at` timestamp

### Example Flow

```
App Start
  ↓
Login
  ↓
Register POS Device (registerPosDevice)
  ↓
Store deviceId in app state
  ↓
[Periodic: Every 5 minutes]
  ↓
Update Heartbeat (updateDeviceHeartbeat)
  ↓
[On App Resume]
  ↓
Update Heartbeat (updateDeviceHeartbeat)
```

## Device Information Mapping

### iOS (iPad)
- **device_identifier**: `identifierForVendor`
- **device_name**: `name` (e.g., "Jan's iPad")
- **device_model**: `model` (e.g., "iPad")
- **machine_identifier**: `utsname.machine` (e.g., "iPad13,1")
- **system_name**: `systemName` (e.g., "iOS")
- **system_version**: `systemVersion` (e.g., "17.0")
- **vendor_identifier**: `identifierForVendor`

### Android
- **device_identifier**: `androidId`
- **device_name**: `device`
- **device_model**: `model`
- **device_brand**: `brand`
- **device_manufacturer**: `manufacturer`
- **device_product**: `product`
- **device_hardware**: `hardware`
- **system_version**: `version.release`
- **android_id**: `id`
- **serial_number**: `serialNumber` (may be "unknown")

## API Endpoints

### Register/Update Device
- **POST** `/api/pos-devices` - Register new device
- **PATCH** `/api/pos-devices/{id}` - Update existing device
- **GET** `/api/pos-devices` - List all devices for current store
- **GET** `/api/pos-devices/{id}` - Get specific device
- **POST** `/api/pos-devices/{id}/heartbeat` - Update heartbeat

### Response Format

```json
{
  "success": true,
  "deviceId": "1",
  "deviceIdentifier": "ABC123-DEF456-...",
  "isNewDevice": true,
  "device": {
    "id": 1,
    "device_identifier": "ABC123-DEF456-...",
    "device_name": "Jan's iPad",
    "platform": "ios",
    "device_info": {
      "model": "iPad",
      "machine_identifier": "iPad13,1",
      ...
    },
    "system_info": {
      "name": "iOS",
      "version": "17.0"
    },
    "device_status": "active",
    "last_seen_at": "2025-11-24T13:00:00Z",
    ...
  }
}
```

## Error Handling

The custom actions return:
```json
{
  "success": false,
  "error": "Error message here"
}
```

Always check the `success` field before using the response data.

## Best Practices

1. **Register on first launch** after successful login
2. **Update heartbeat periodically** (every 5 minutes when app is active)
3. **Update heartbeat on app resume** to mark device as online
4. **Store deviceId** in app state for subsequent calls
5. **Handle errors gracefully** - device registration failure shouldn't block app usage
6. **Include metadata** for battery level, storage, etc. if available

## Testing

Use the `getDeviceInfo()` helper function to test device information retrieval without registering:

```dart
final deviceInfo = await getDeviceInfo();
print(deviceInfo);
```

This will show you what information is available from the device.

