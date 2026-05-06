# # CreateTerminalConnectionToken200Response

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**secret** | **string** | Stripe connection token secret for the Terminal SDK | [optional]
**location** | **string** | Stripe location ID (tml_xxx) used for this token; client can set app state for discovery | [optional]
**location_id** | **int** | Internal terminal location ID used for this token (e.g. for saving last_connected) | [optional]
**preferred_reader_id** | **string** | When pos_device_id was used and the device has a last-connected reader, Stripe reader ID for auto-connect | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
