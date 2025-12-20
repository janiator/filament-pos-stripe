# # Product

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**stripe_product_id** | **string** |  | [optional]
**name** | **string** |  | [optional]
**description** | **string** |  | [optional]
**type** | **string** |  | [optional]
**active** | **bool** |  | [optional]
**shippable** | **bool** |  | [optional]
**url** | **string** |  | [optional]
**images** | **string[]** | Array of image URLs (signed URLs for security) | [optional]
**no_price_in_pos** | **bool** | If true, this product has no preset price and requires custom price input on POS. | [optional] [default to false]
**product_price** | [**\OpenAPI\Client\Model\ProductProductPrice**](ProductProductPrice.md) |  | [optional]
**prices** | [**\OpenAPI\Client\Model\ProductPricesInner[]**](ProductPricesInner.md) |  | [optional]
**variants** | [**\OpenAPI\Client\Model\ProductVariantsInner[]**](ProductVariantsInner.md) |  | [optional]
**variants_count** | **int** |  | [optional]
**product_inventory** | [**\OpenAPI\Client\Model\ProductProductInventory**](ProductProductInventory.md) |  | [optional]
**tax_code** | **string** |  | [optional]
**unit_label** | **string** |  | [optional]
**statement_descriptor** | **string** |  | [optional]
**collections** | [**\OpenAPI\Client\Model\ProductCollectionsInner[]**](ProductCollectionsInner.md) | Collections this product belongs to | [optional]
**package_dimensions** | **object** |  | [optional]
**product_meta** | **array<string,mixed>** |  | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
