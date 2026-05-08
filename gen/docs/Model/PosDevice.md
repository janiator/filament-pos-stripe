# # PosDevice

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**device_identifier** | **string** | Device identifier from the device (Android may not be unique). Device identity per store is device_name. | [optional]
**device_name** | **string** | Human-readable name, unique per store; used for registration matching. | [optional]
**platform** | **string** |  | [optional]
**device_info** | [**\OpenAPIClient\Model\PosDeviceDeviceInfo**](PosDeviceDeviceInfo.md) |  | [optional]
**system_info** | [**\OpenAPIClient\Model\PosDeviceSystemInfo**](PosDeviceSystemInfo.md) |  | [optional]
**identifiers** | [**\OpenAPIClient\Model\PosDeviceIdentifiers**](PosDeviceIdentifiers.md) |  | [optional]
**device_status** | **string** |  | [optional]
**last_seen_at** | **\DateTime** |  | [optional]
**device_metadata** | **array<string,mixed>** | Additional device metadata | [optional]
**cash_drawer_enabled** | **bool** | When false, only non-cash transactions are allowed on this device (cash drawer disabled) | [optional] [default to true]
**has_integrated_drawer** | **bool** | When true, this device has a built-in cash drawer (e.g. Stripe Terminal S700) | [optional] [default to false]
**booking_enabled** | **bool** | When true, booking actions may be enabled for this device if the store has the Merano Booking add-on active | [optional] [default to false]
**inventory_enabled** | **bool** | When true, the store has the Inventory add-on active; the app may show stock levels and must send variant_id on cart lines for tracked variants | [optional] [default to false]
**auto_print_receipt** | **bool** | When true, receipts are auto-printed after purchase; when false, printing is optional in the frontend | [optional] [default to true]
**available_actions** | **string[]** | Action keys the frontend should use for capability gating on the active device | [optional]
**terminal_location_id** | **int** | ID of the single terminal location assigned to this device (each POS device has at most one) | [optional]
**terminal_locations_count** | **int** | Number of terminal locations (0 or 1; each device has at most one) | [optional]
**terminal_locations** | [**\OpenAPIClient\Model\PosDeviceTerminalLocationsInner[]**](PosDeviceTerminalLocationsInner.md) | Terminal location(s) assigned to this device (at most one) | [optional]
**receipt_printers_count** | **int** | Number of receipt printers attached to this device | [optional]
**default_printer_id** | **int** | ID of the default receipt printer for this POS device (set via Filament admin) | [optional]
**last_connected** | [**\OpenAPIClient\Model\PosDeviceLastConnected**](PosDeviceLastConnected.md) |  | [optional]
**receipt_printers** | [**\OpenAPIClient\Model\PosDeviceReceiptPrintersInner[]**](PosDeviceReceiptPrintersInner.md) | List of receipt printers attached to this POS device | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
