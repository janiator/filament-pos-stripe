# # RegisterPosDeviceRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**device_identifier** | **string** | Device identifier from the device (iOS: identifierForVendor, Android: may not be unique). Stored for reference; matching uses device_name. |
**device_name** | **string** | Human-readable device name, unique per store (e.g. POS 4, POS 6). Used as the device identity for registration matching. |
**platform** | **string** | Device platform |
**device_model** | **string** | Device model | [optional]
**device_brand** | **string** | Device brand (Android only) | [optional]
**device_manufacturer** | **string** | Device manufacturer (Android only) | [optional]
**device_product** | **string** | Device product (Android only) | [optional]
**device_hardware** | **string** | Device hardware (Android only) | [optional]
**machine_identifier** | **string** | Machine identifier (iOS: utsname.machine) | [optional]
**system_name** | **string** | System name (iOS: systemName) | [optional]
**system_version** | **string** | System version | [optional]
**vendor_identifier** | **string** | Vendor identifier (iOS: identifierForVendor) | [optional]
**android_id** | **string** | Android ID (Android only) | [optional]
**serial_number** | **string** | Serial number (if available) | [optional]
**device_metadata** | **array<string,mixed>** | Additional device metadata (battery, storage, etc.) | [optional]
**cash_drawer_enabled** | **bool** | When false, only non-cash transactions are allowed on this device. Defaults to true if omitted. | [optional]
**has_integrated_drawer** | **bool** | When true, this device has a built-in cash drawer (e.g. Stripe Terminal S700). Defaults to false if omitted. | [optional]
**booking_enabled** | **bool** | When true, booking actions may be enabled for this device. Defaults to false if omitted. | [optional]
**auto_print_receipt** | **bool** | When true, receipts are auto-printed after purchase. Defaults to true if omitted. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
