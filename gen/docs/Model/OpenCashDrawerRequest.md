# # OpenCashDrawerRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**pos_session_id** | **int** | POS session ID (auto-detected if not provided) | [optional]
**related_charge_id** | **int** | Charge ID if drawer opened with sale | [optional]
**nullinnslag** | **bool** | True if drawer opened without sale (mandatory to log per § 2-2) | [optional]
**reason** | **string** | Reason for nullinnslag (if applicable) | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
