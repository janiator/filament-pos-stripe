# # CreatePurchase422Response

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**success** | **bool** |  | [optional]
**message** | **string** |  | [optional]
**error** | **string** | Present for inventory shortfall responses | [optional]
**lines** | [**\OpenAPIClient\Model\CreatePurchase422ResponseLinesInner[]**](CreatePurchase422ResponseLinesInner.md) | Per-variant shortfall details when error is insufficient_stock | [optional]
**errors** | **object** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
