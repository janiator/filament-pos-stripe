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
**track_inventory** | **bool** | When true and the store has the Inventory add-on, variant quantities with a non-null inventory_quantity are enforced at checkout (deny policy). | [optional] [default to false]
**product_price** | [**\OpenAPIClient\Model\ProductProductPrice**](ProductProductPrice.md) |  | [optional]
**prices** | [**\OpenAPIClient\Model\ProductPricesInner[]**](ProductPricesInner.md) |  | [optional]
**variants** | [**\OpenAPIClient\Model\ProductVariantsInner[]**](ProductVariantsInner.md) |  | [optional]
**variants_count** | **int** |  | [optional]
**product_inventory** | [**\OpenAPIClient\Model\ProductProductInventory**](ProductProductInventory.md) |  | [optional]
**tax_code** | **string** |  | [optional]
**unit_label** | **string** |  | [optional]
**vendor_id** | **int** | Vendor ID for product edit form (Leverandør) | [optional]
**article_group_code** | **string** | Article group code for product edit form (Varegruppekode) | [optional]
**quantity_unit_id** | **int** | Quantity unit ID for product edit form (Enhet) | [optional]
**vendor** | [**\OpenAPIClient\Model\ProductVendor**](ProductVendor.md) |  | [optional]
**quantity_unit** | [**\OpenAPIClient\Model\ProductQuantityUnit**](ProductQuantityUnit.md) |  | [optional]
**statement_descriptor** | **string** |  | [optional]
**collections** | [**\OpenAPIClient\Model\ProductCollectionsInner[]**](ProductCollectionsInner.md) | Collections this product belongs to | [optional]
**package_dimensions** | **object** |  | [optional]
**product_meta** | **array<string,mixed>** |  | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
