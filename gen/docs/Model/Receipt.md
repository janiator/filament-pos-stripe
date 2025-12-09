# # Receipt

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**store_id** | **int** |  | [optional]
**pos_session_id** | **int** |  | [optional]
**charge_id** | **int** |  | [optional]
**user_id** | **int** |  | [optional]
**receipt_number** | **string** |  | [optional]
**receipt_type** | **string** |  | [optional]
**receipt_data** | **array<string,mixed>** | Receipt data (store info, items, totals, etc.) | [optional]
**printed** | **bool** |  | [optional]
**printed_at** | **\DateTime** |  | [optional]
**reprint_count** | **int** |  | [optional]
**pos_session** | [**\OpenAPI\Client\Model\LogApplicationStart200ResponseCurrentSession**](LogApplicationStart200ResponseCurrentSession.md) |  | [optional]
**charge** | [**\OpenAPI\Client\Model\ReceiptCharge**](ReceiptCharge.md) |  | [optional]
**user** | [**\OpenAPI\Client\Model\PosSessionSessionUser**](PosSessionSessionUser.md) |  | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
