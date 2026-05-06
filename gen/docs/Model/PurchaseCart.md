# # PurchaseCart

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**items** | [**\OpenAPIClient\Model\PurchaseCartItemsInner[]**](PurchaseCartItemsInner.md) |  |
**discounts** | [**\OpenAPIClient\Model\PurchaseCartDiscountsInner[]**](PurchaseCartDiscountsInner.md) |  | [optional]
**tip_amount** | **int** | Tip amount in minor units | [optional] [default to 0]
**customer_id** | **int** | Customer database ID (integer) | [optional]
**customer_name** | **string** |  | [optional]
**subtotal** | **int** | Subtotal in minor units (before discounts and tax) | [optional]
**total_discounts** | **int** | Total discount amount in minor units | [optional]
**total_tax** | **int** | Total tax amount in minor units | [optional]
**total** | **int** | Total amount in minor units (0 allowed for e.g. freeticket orders) |
**currency** | **string** | Currency code (ISO 3-letter) | [optional] [default to 'nok']
**note** | **string** | Optional whole-order note (same value as &#x60;purchase_note&#x60; on the purchase response). Printed on sales and delivery (ordrebekreftelse) receipts under the heading \&quot;Notat\&quot; when non-empty. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
