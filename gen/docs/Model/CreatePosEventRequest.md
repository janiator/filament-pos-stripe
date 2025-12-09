# # CreatePosEventRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**pos_device_id** | **int** | POS device ID | [optional]
**pos_session_id** | **int** | POS session ID | [optional]
**event_code** | **string** | SAF-T event code (PredefinedBasicID-13) |
**event_type** | **string** | Event type category |
**description** | **string** | Human-readable description | [optional]
**related_charge_id** | **int** | Related charge ID (for transaction events) | [optional]
**event_data** | **array<string,mixed>** | Additional event-specific data | [optional]
**occurred_at** | **\DateTime** | When event occurred (defaults to now) | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
