# # PurchaseCartItemsInner

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**product_id** | **int** |  |
**variant_id** | **int** |  | [optional]
**quantity** | **float** | Quantity of this item (supports decimals for continuous units like meters, kilograms) |
**unit_price** | **int** | Price per unit in minor units (øre) |
**description** | **string** | Custom description for diverse products or products without price. This will be used on receipts instead of the product name. Useful for items like \&quot;Various items\&quot;, \&quot;Custom service\&quot;, etc. | [optional]
**discount_amount** | **int** | Discount amount per unit in minor units | [optional] [default to 0]
**tax_rate** | **float** | Tax rate (e.g., 0.25 for 25%) | [optional] [default to 0]
**tax_inclusive** | **bool** | Whether price includes tax | [optional] [default to true]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
