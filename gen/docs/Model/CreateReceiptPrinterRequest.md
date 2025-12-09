# # CreateReceiptPrinterRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**name** | **string** |  |
**printer_type** | **string** |  |
**printer_model** | **string** |  | [optional]
**paper_width** | **string** |  | [optional] [default to '80']
**connection_type** | **string** |  |
**ip_address** | **string** |  | [optional]
**port** | **int** |  | [optional] [default to 9100]
**device_id** | **string** |  | [optional] [default to 'local_printer']
**use_https** | **bool** |  | [optional] [default to false]
**timeout** | **int** |  | [optional] [default to 60000]
**is_active** | **bool** |  | [optional] [default to true]
**monitor_status** | **bool** |  | [optional] [default to false]
**drawer_open_level** | **string** |  | [optional] [default to 'low']
**use_job_id** | **bool** |  | [optional] [default to false]
**pos_device_id** | **int** | Optional POS device to associate with this printer | [optional]
**printer_metadata** | **array<string,mixed>** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
