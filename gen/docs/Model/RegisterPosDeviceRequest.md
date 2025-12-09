# # RegisterPosDeviceRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**device_identifier** | **string** | Unique device identifier (iOS: identifierForVendor, Android: androidId) |
**device_name** | **string** | Human-readable device name (iOS: name, Android: device) |
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

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
