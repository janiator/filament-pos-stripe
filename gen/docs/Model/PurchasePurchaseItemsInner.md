# # PurchasePurchaseItemsInner

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**purchase_item_id** | **string** | Cart item ID (UUID from FlutterFlow) | [optional]
**purchase_item_product_id** | **string** | Product ID | [optional]
**purchase_item_variant_id** | **string** | Product variant ID (if applicable) | [optional]
**purchase_item_product_name** | **string** | Product name (includes variant name if applicable) | [optional]
**purchase_item_description** | **string** | Custom description for diverse products or products without price. This is used on receipts instead of product_name when provided. | [optional]
**purchase_item_product_image_url** | **string** | Product image URL (signed URL for security) | [optional]
**purchase_item_unit_price** | **int** | Price per unit in øre | [optional]
**purchase_item_quantity** | **float** | Quantity purchased (supports decimals for continuous units like meters, kilograms) | [optional]
**purchase_item_original_price** | **int** | Original price before discount in øre | [optional]
**purchase_item_discount_amount** | **int** | Discount amount per unit in øre | [optional]
**purchase_item_discount_reason** | **string** | Reason for discount | [optional]
**purchase_item_article_group_code** | **string** | SAF-T article group code (PredefinedBasicID-04) | [optional]
**purchase_item_product_code** | **string** | SAF-T product code (PLU - BasicType-02) | [optional]
**purchase_item_metadata** | **array<string,mixed>** | Additional item metadata | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
