# OpenAPIClient\ProductsApi

Product management for POS

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getProduct()**](ProductsApi.md#getProduct) | **GET** /products/{id} | Get product |
| [**listProducts()**](ProductsApi.md#listProducts) | **GET** /products | List products |
| [**serveProductImage()**](ProductsApi.md#serveProductImage) | **GET** /products/{product}/images/{media} | Serve product image |


## `getProduct()`

```php
getProduct($id): \OpenAPIClient\Model\GetMeranoTicketProduct200Response
```

Get product

Get a specific product with variants and inventory

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ProductsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getProduct($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ProductsApi->getProduct: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\GetMeranoTicketProduct200Response**](../Model/GetMeranoTicketProduct200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listProducts()`

```php
listProducts($include_inactive, $search, $type, $collection_id, $collection_slug, $per_page): \OpenAPIClient\Model\ListProducts200Response
```

List products

Get paginated list of products. By default only active products are returned (for POS). For admin screens (e.g. Products Categories Manager) pass include_inactive=1 to list all products including inactive.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ProductsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$include_inactive = false; // bool | If 1 or true, include inactive products. Use for admin/product manager screens. Default is to return only active products.
$search = 'search_example'; // string | Freetext search term that searches across multiple product fields: - Product name, description - Product codes (product_code, article_group_code) - Stripe product ID - Variant SKU and barcode - Variant names and option values - Stripe product/price IDs for variants
$type = 'type_example'; // string | Filter by product type
$collection_id = 1; // int | Filter by collection ID. Use 0 to get uncategorized products (products with no collection)
$collection_slug = summer-collection; // string | Filter by collection slug/handle
$per_page = 50; // int | Number of items per page (max 100)

try {
    $result = $apiInstance->listProducts($include_inactive, $search, $type, $collection_id, $collection_slug, $per_page);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ProductsApi->listProducts: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **include_inactive** | **bool**| If 1 or true, include inactive products. Use for admin/product manager screens. Default is to return only active products. | [optional] [default to false] |
| **search** | **string**| Freetext search term that searches across multiple product fields: - Product name, description - Product codes (product_code, article_group_code) - Stripe product ID - Variant SKU and barcode - Variant names and option values - Stripe product/price IDs for variants | [optional] |
| **type** | **string**| Filter by product type | [optional] |
| **collection_id** | **int**| Filter by collection ID. Use 0 to get uncategorized products (products with no collection) | [optional] |
| **collection_slug** | **string**| Filter by collection slug/handle | [optional] |
| **per_page** | **int**| Number of items per page (max 100) | [optional] [default to 50] |

### Return type

[**\OpenAPIClient\Model\ListProducts200Response**](../Model/ListProducts200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `serveProductImage()`

```php
serveProductImage($product, $media): \SplFileObject
```

Serve product image

Serve product image with signed URL (public endpoint, secured by signature)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OpenAPIClient\Api\ProductsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$product = 56; // int
$media = 'media_example'; // string

try {
    $result = $apiInstance->serveProductImage($product, $media);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ProductsApi->serveProductImage: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **product** | **int**|  | |
| **media** | **string**|  | |

### Return type

**\SplFileObject**

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `image/*`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
