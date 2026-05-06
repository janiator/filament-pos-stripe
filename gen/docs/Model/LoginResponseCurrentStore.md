# # LoginResponseCurrentStore

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**slug** | **string** |  | [optional]
**name** | **string** |  | [optional]
**email** | **string** |  | [optional]
**stripe_account_id** | **string** |  | [optional]
**commission_type** | **string** |  | [optional]
**commission_rate** | **int** |  | [optional]
**visible_article_group_codes** | [**\OpenAPIClient\Model\StoreVisibleArticleGroupCodesInner[]**](StoreVisibleArticleGroupCodesInner.md) | Article group codes that are visible in the POS (active and show_in_pos). Use for product edit dropdown and to decide whether to show article group code on products. | [optional]
**customers_enabled** | **bool** | Whether customer-related features are enabled in POS for this store (e.g. linking customers to purchases). When false, frontend should hide/disable customer features. | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
