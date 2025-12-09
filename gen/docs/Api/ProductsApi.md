# OpenAPI\Client\ProductsApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getProduct()**](ProductsApi.md#getProduct) | **GET** /products/{id} | Get product |
| [**listProducts()**](ProductsApi.md#listProducts) | **GET** /products | List products |
| [**serveProductImage()**](ProductsApi.md#serveProductImage) | **GET** /products/{product}/images/{media} | Serve product image |


## `getProduct()`

```php
getProduct($id): \OpenAPI\Client\Model\GetProduct200Response
```

Get product

Get a specific product with variants and inventory

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ProductsApi(
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

[**\OpenAPI\Client\Model\GetProduct200Response**](../Model/GetProduct200Response.md)

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
listProducts($search, $type, $collection_id, $per_page): \OpenAPI\Client\Model\ListProducts200Response
```

List products

Get paginated list of active products for POS

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ProductsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$search = 'search_example'; // string | Search term (name or description)
$type = 'type_example'; // string | Filter by product type
$collection_id = 56; // int | Filter by collection ID
$per_page = 50; // int | Number of items per page (max 100)

try {
    $result = $apiInstance->listProducts($search, $type, $collection_id, $per_page);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ProductsApi->listProducts: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **search** | **string**| Search term (name or description) | [optional] |
| **type** | **string**| Filter by product type | [optional] |
| **collection_id** | **int**| Filter by collection ID | [optional] |
| **per_page** | **int**| Number of items per page (max 100) | [optional] [default to 50] |

### Return type

[**\OpenAPI\Client\Model\ListProducts200Response**](../Model/ListProducts200Response.md)

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



$apiInstance = new OpenAPI\Client\Api\ProductsApi(
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
