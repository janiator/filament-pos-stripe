# # ProductVariantsInner

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**stripe_product_id** | **string** |  | [optional]
**stripe_price_id** | **string** |  | [optional]
**sku** | **string** |  | [optional]
**barcode** | **string** |  | [optional]
**variant_name** | **string** |  | [optional]
**variant_options** | [**\OpenAPI\Client\Model\ProductVariantsInnerVariantOptionsInner[]**](ProductVariantsInnerVariantOptionsInner.md) |  | [optional]
**variant_price** | [**\OpenAPI\Client\Model\ProductVariantsInnerVariantPrice**](ProductVariantsInnerVariantPrice.md) |  | [optional]
**price_amount** | **int** | Price in øre (flattened field for backward compatibility). Returns 0 for variants without preset prices (custom price input on POS). Frontend should check if price_amount &#x3D;&#x3D;&#x3D; 0 to enable custom price input. | [optional]
**price_amount_formatted** | **string** | Formatted price string (flattened field for backward compatibility). Returns \&quot;0.00\&quot; for variants without preset prices. Frontend should check if price_amount &#x3D;&#x3D;&#x3D; 0 to enable custom price input. | [optional]
**compare_at_price_amount** | **int** | Compare at price in øre (original price for discounts). | [optional]
**compare_at_price_amount_formatted** | **string** | Formatted compare at price string. | [optional]
**currency** | **string** |  | [optional]
**no_price_in_pos** | **bool** | If true, this variant has no preset price and requires custom price input on POS. | [optional] [default to false]
**variant_inventory** | [**\OpenAPI\Client\Model\UpdateVariantInventory200ResponseVariantVariantInventory**](UpdateVariantInventory200ResponseVariantVariantInventory.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
