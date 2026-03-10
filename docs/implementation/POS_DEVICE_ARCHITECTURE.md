# POS Device Architecture

## Overview

POS devices are now separated from Stripe Terminal locations to support multiple payment terminal vendors in the future.

## Data Model

### PosDevice (Vendor-Agnostic)
- Represents a physical POS device (iPad, Android tablet, etc.)
- Tracks device information from `device_info_plus`
- Used for compliance (Kassasystemforskriften)
- Belongs to a Store
- **cash_drawer_enabled** (boolean, default true): When false, only non-cash transactions are allowed on this device; cash-drawer open/close are disabled and cash payment is rejected with 422.
- **booking_enabled** (boolean, default false): When true, the device may use Merano booking actions, but only if the store also has the `MeranoBooking` add-on active.
- **available_actions** (API field): Array of action keys that the frontend should trust for UI gating, currently `cash_drawer` and `booking`.

### TerminalLocation (Stripe-Specific)
- Represents a Stripe Terminal location
- Can optionally be linked to a PosDevice via `pos_device_id`
- **Each POS device has at most one terminal location** (enforced by unique constraint)
- Used for Stripe Terminal API operations
- Belongs to a Store

### Store default terminal location
- Stores can set a **default terminal location** (`default_terminal_location_id`)
- When a new POS device is registered (API or otherwise), that location is assigned to the new device if set
- Configurable in Filament: Store → Terminal Locations relation manager → "Set as default" on the desired location

### TerminalReader (Stripe-Specific)
- Represents a Stripe Terminal reader device
- Belongs to a TerminalLocation
- Used for Stripe Terminal API operations

## Relationships

```
Store
  ├── PosDevice (POS Device 1 - "Kasse 1")
  │   └── TerminalLocation (Stripe Terminal Location)
  │       └── TerminalReader (Payment Terminal)
  ├── PosDevice (POS Device 2 - "Kasse 2")
  │   └── TerminalLocation (Stripe Terminal Location)
  │       └── TerminalReader (Payment Terminal)
  └── PosDevice (POS Device 3 - "Mobil POS")
      └── (No Stripe Terminal - can use other vendors)
```

## API Endpoints

### POS Devices (Vendor-Agnostic)
- `GET /api/pos-devices` - List all POS devices for current store
- `POST /api/pos-devices` - Register new POS device
- `GET /api/pos-devices/{id}` - Get specific POS device
- `PUT/PATCH /api/pos-devices/{id}` - Update POS device
- `POST /api/pos-devices/{id}/heartbeat` - Update device heartbeat

`booking` is only included in `available_actions` when:
- the store has the `MeranoBooking` add-on active, and
- the device has `booking_enabled = true`.

### Terminal Locations (Stripe-Specific)
- `GET /api/terminals/locations` - List Stripe Terminal locations
- `GET /api/terminals/readers` - List Stripe Terminal readers

## Device Information Fields

All fields match what's available from `device_info_plus` package:

### iOS (iPad)
- `device_identifier`: `identifierForVendor`
- `device_name`: `name`
- `device_model`: `model`
- `machine_identifier`: `utsname.machine`
- `system_name`: `systemName`
- `system_version`: `systemVersion`
- `vendor_identifier`: `identifierForVendor`

### Android
- `device_identifier`: `androidId`
- `device_name`: `device`
- `device_model`: `model`
- `device_brand`: `brand`
- `device_manufacturer`: `manufacturer`
- `device_product`: `product`
- `device_hardware`: `hardware`
- `system_version`: `version.release`
- `android_id`: `id`
- `serial_number`: `serialNumber`

## FlutterFlow Integration

See `FLUTTERFLOW_INTEGRATION.md` for detailed integration guide.

Custom actions provided:
- `registerPosDevice` - Register/update POS device
- `updateDeviceHeartbeat` - Update device heartbeat
- `getDeviceInfo` - Get device info (testing)

## Future Extensibility

This architecture allows for:
- Adding other payment terminal vendors (e.g., Adyen, Square)
- Each vendor can have their own location/reader models
- POS devices remain vendor-agnostic
- Compliance tracking is independent of payment vendor

## Migration Notes

- Existing TerminalLocations remain unchanged
- New `pos_devices` table created
- `terminal_locations` table has optional `pos_device_id` foreign key (unique when set: one location per device)
- Stores have optional `default_terminal_location_id`; new POS devices receive this location on registration when set
- Can gradually link existing TerminalLocations to PosDevices

