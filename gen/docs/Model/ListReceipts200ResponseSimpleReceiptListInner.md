# # ListReceipts200ResponseSimpleReceiptListInner

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Receipt ID (only present when is_generated is true) | [optional]
**receipt_type** | **string** | Receipt type code | [optional]
**receipt_type_display_name** | **string** | Receipt type display name in Norwegian | [optional]
**created_at** | **\DateTime** | Date and time when receipt was created (Oslo timezone) | [optional]
**cashier_name** | **string** | Name of the cashier who created the receipt | [optional]
**reprint_count** | **int** | Number of times the receipt has been reprinted | [optional]
**can_be_printed** | **bool** | Whether the receipt can still be printed/reprinted according to Kassasystemforskriften § 2-8-4: - Original sales receipts: can only be printed once (no reprints) - Copy receipts: can only be printed once - STEB receipts: can be printed multiple times (exception for tax-free shops) - Other receipt types: can only be printed once | [optional]
**is_generated** | **bool** | Whether this receipt has been generated or is just available to generate. - true: Receipt exists in the database (has id) - false: Receipt type is available but not yet generated (no id) | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
