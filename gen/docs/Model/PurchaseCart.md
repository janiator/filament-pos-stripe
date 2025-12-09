# # PurchaseCart

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**items** | [**\OpenAPI\Client\Model\PurchaseCartItemsInner[]**](PurchaseCartItemsInner.md) |  |
**discounts** | [**\OpenAPI\Client\Model\PurchaseCartDiscountsInner[]**](PurchaseCartDiscountsInner.md) |  | [optional]
**tip_amount** | **int** | Tip amount in minor units | [optional] [default to 0]
**customer_id** | **string** | Customer ID (Stripe customer ID) | [optional]
**customer_name** | **string** |  | [optional]
**subtotal** | **int** | Subtotal in minor units (before discounts and tax) | [optional]
**total_discounts** | **int** | Total discount amount in minor units | [optional]
**total_tax** | **int** | Total tax amount in minor units | [optional]
**total** | **int** | Total amount in minor units |
**currency** | **string** | Currency code (ISO 3-letter) | [optional] [default to 'nok']

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
