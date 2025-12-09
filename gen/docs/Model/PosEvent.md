# # PosEvent

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**store_id** | **int** |  | [optional]
**pos_device_id** | **int** |  | [optional]
**pos_device** | [**\OpenAPI\Client\Model\PosSessionSessionDevice**](PosSessionSessionDevice.md) |  | [optional]
**pos_session_id** | **int** |  | [optional]
**pos_session** | [**\OpenAPI\Client\Model\LogApplicationStart200ResponseCurrentSession**](LogApplicationStart200ResponseCurrentSession.md) |  | [optional]
**user_id** | **int** |  | [optional]
**user** | [**\OpenAPI\Client\Model\PosSessionSessionUser**](PosSessionSessionUser.md) |  | [optional]
**event_code** | **string** | SAF-T event code (PredefinedBasicID-13) | [optional]
**event_type** | **string** |  | [optional]
**description** | **string** |  | [optional]
**event_description** | **string** | Human-readable event description | [optional]
**related_charge_id** | **int** |  | [optional]
**event_data** | **array<string,mixed>** |  | [optional]
**occurred_at** | **\DateTime** |  | [optional]
**created_at** | **\DateTime** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
