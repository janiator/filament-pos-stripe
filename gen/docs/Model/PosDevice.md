# # PosDevice

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**device_identifier** | **string** | Unique device identifier (iOS: identifierForVendor, Android: androidId) | [optional]
**device_name** | **string** |  | [optional]
**platform** | **string** |  | [optional]
**device_info** | [**\OpenAPI\Client\Model\PosDeviceDeviceInfo**](PosDeviceDeviceInfo.md) |  | [optional]
**system_info** | [**\OpenAPI\Client\Model\PosDeviceSystemInfo**](PosDeviceSystemInfo.md) |  | [optional]
**identifiers** | [**\OpenAPI\Client\Model\PosDeviceIdentifiers**](PosDeviceIdentifiers.md) |  | [optional]
**device_status** | **string** |  | [optional]
**last_seen_at** | **\DateTime** |  | [optional]
**device_metadata** | **array<string,mixed>** | Additional device metadata | [optional]
**terminal_locations_count** | **int** |  | [optional]
**terminal_locations** | [**\OpenAPI\Client\Model\PosDeviceTerminalLocationsInner[]**](PosDeviceTerminalLocationsInner.md) |  | [optional]
**receipt_printers_count** | **int** | Number of receipt printers attached to this device | [optional]
**default_printer_id** | **int** | ID of the default receipt printer for this POS device (set via Filament admin) | [optional]
**receipt_printers** | [**\OpenAPI\Client\Model\PosDeviceReceiptPrintersInner[]**](PosDeviceReceiptPrintersInner.md) | List of receipt printers attached to this POS device | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
