# OpenAPI\Client\CollectionsApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**getCollection()**](CollectionsApi.md#getCollection) | **GET** /collections/{id} | Get collection |
| [**listCollections()**](CollectionsApi.md#listCollections) | **GET** /collections | List collections |


## `getCollection()`

```php
getCollection($id): \OpenAPI\Client\Model\GetCollection200Response
```

Get collection

Get a specific collection with its products

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\CollectionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getCollection($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling CollectionsApi->getCollection: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\GetCollection200Response**](../Model/GetCollection200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listCollections()`

```php
listCollections($search, $active, $per_page): \OpenAPI\Client\Model\ListCollections200Response
```

List collections

Get paginated list of collections

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\CollectionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$search = 'search_example'; // string | Search term (name or description)
$active = True; // bool | Filter by active status (defaults to true)
$per_page = 50; // int | Number of items per page (max 100)

try {
    $result = $apiInstance->listCollections($search, $active, $per_page);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling CollectionsApi->listCollections: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **search** | **string**| Search term (name or description) | [optional] |
| **active** | **bool**| Filter by active status (defaults to true) | [optional] |
| **per_page** | **int**| Number of items per page (max 100) | [optional] [default to 50] |

### Return type

[**\OpenAPI\Client\Model\ListCollections200Response**](../Model/ListCollections200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
