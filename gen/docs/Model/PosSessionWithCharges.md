# # PosSessionWithCharges

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**store_id** | **int** |  | [optional]
**pos_device_id** | **int** |  | [optional]
**user_id** | **int** |  | [optional]
**session_number** | **string** | Zero-padded 6-digit session number | [optional]
**status** | **string** |  | [optional]
**opening_balance** | **int** | Opening cash balance in øre | [optional]
**expected_cash** | **int** | Expected cash amount in øre | [optional]
**actual_cash** | **int** | Actual cash count in øre | [optional]
**cash_difference** | **int** | Cash difference in øre | [optional]
**opening_notes** | **string** | Free-text notes for session opening (e.g., shift information, initial observations, cashier notes) | [optional]
**closing_notes** | **string** | Free-text notes for session closing (e.g., cash discrepancies, end-of-shift observations, issues encountered) | [optional]
**opening_data** | **array<string,mixed>** |  | [optional]
**closing_data** | **array<string,mixed>** |  | [optional]
**opened_at** | **\DateTime** |  | [optional]
**closed_at** | **\DateTime** |  | [optional]
**session_device** | [**\OpenAPI\Client\Model\PosSessionSessionDevice**](PosSessionSessionDevice.md) |  | [optional]
**session_user** | [**\OpenAPI\Client\Model\PosSessionSessionUser**](PosSessionSessionUser.md) |  | [optional]
**transaction_count** | **int** | Number of successful transactions | [optional]
**total_amount** | **int** | Total amount in øre | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]
**session_charges** | [**\OpenAPI\Client\Model\SessionCharge[]**](SessionCharge.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
