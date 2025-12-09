# # OpenPosSessionRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**pos_device_id** | **int** | POS device ID |
**opening_balance** | **int** | Opening cash balance in øre | [optional]
**opening_notes** | **string** | Free-text notes for session opening (e.g., shift information, initial observations, cashier notes). Human-readable text field for cashiers/managers to document session start. | [optional]
**opening_data** | **array<string,mixed>** | Additional opening metadata (device info, app version, location, etc.). Flexible object for storing any additional session opening data. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
