# # PatchPosDeviceRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**device_name** | **string** |  | [optional]
**device_identifier** | **string** | Update the stored device identifier (e.g. when re-registering) | [optional]
**device_status** | **string** |  | [optional]
**device_metadata** | **array<string,mixed>** |  | [optional]
**cash_drawer_enabled** | **bool** | When false, only non-cash transactions are allowed on this device | [optional]
**has_integrated_drawer** | **bool** | When true, this device has a built-in cash drawer (e.g. Stripe Terminal S700) | [optional]
**booking_enabled** | **bool** | When true, booking actions may be enabled for this device | [optional]
**auto_print_receipt** | **bool** | When true, receipts are auto-printed after purchase | [optional]
**last_connected_terminal_location_id** | **int** |  | [optional]
**last_connected_terminal_reader_id** | **int** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
