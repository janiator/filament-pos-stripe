# OpenAPI\Client\StoresApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**changeCurrentStore()**](StoresApi.md#changeCurrentStore) | **PUT** /stores/current | Change current store |
| [**getCurrentStore()**](StoresApi.md#getCurrentStore) | **GET** /stores/current | Get current store |
| [**getStore()**](StoresApi.md#getStore) | **GET** /stores/{slug} | Get store by slug |
| [**listStores()**](StoresApi.md#listStores) | **GET** /stores | List stores |
| [**patchCurrentStore()**](StoresApi.md#patchCurrentStore) | **PATCH** /stores/current | Change current store (partial) |


## `changeCurrentStore()`

```php
changeCurrentStore($change_current_store_request): \OpenAPI\Client\Model\ChangeCurrentStore200Response
```

Change current store

Change the authenticated user's current/default store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$change_current_store_request = new \OpenAPI\Client\Model\ChangeCurrentStoreRequest(); // \OpenAPI\Client\Model\ChangeCurrentStoreRequest

try {
    $result = $apiInstance->changeCurrentStore($change_current_store_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling StoresApi->changeCurrentStore: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **change_current_store_request** | [**\OpenAPI\Client\Model\ChangeCurrentStoreRequest**](../Model/ChangeCurrentStoreRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\ChangeCurrentStore200Response**](../Model/ChangeCurrentStore200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getCurrentStore()`

```php
getCurrentStore(): \OpenAPI\Client\Model\GetCurrentStore200Response
```

Get current store

Get the authenticated user's default store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->getCurrentStore();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling StoresApi->getCurrentStore: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPI\Client\Model\GetCurrentStore200Response**](../Model/GetCurrentStore200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getStore()`

```php
getStore($slug): \OpenAPI\Client\Model\GetCurrentStore200Response
```

Get store by slug

Get a specific store by its slug

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$slug = my-store; // string | Store slug

try {
    $result = $apiInstance->getStore($slug);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling StoresApi->getStore: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **slug** | **string**| Store slug | |

### Return type

[**\OpenAPI\Client\Model\GetCurrentStore200Response**](../Model/GetCurrentStore200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listStores()`

```php
listStores(): \OpenAPI\Client\Model\ListStores200Response
```

List stores

Get all stores accessible by the authenticated user

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->listStores();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling StoresApi->listStores: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPI\Client\Model\ListStores200Response**](../Model/ListStores200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `patchCurrentStore()`

```php
patchCurrentStore($change_current_store_request): \OpenAPI\Client\Model\ChangeCurrentStore200Response
```

Change current store (partial)

Change the authenticated user's current/default store (alternative to PUT)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$change_current_store_request = new \OpenAPI\Client\Model\ChangeCurrentStoreRequest(); // \OpenAPI\Client\Model\ChangeCurrentStoreRequest

try {
    $result = $apiInstance->patchCurrentStore($change_current_store_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling StoresApi->patchCurrentStore: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **change_current_store_request** | [**\OpenAPI\Client\Model\ChangeCurrentStoreRequest**](../Model/ChangeCurrentStoreRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\ChangeCurrentStore200Response**](../Model/ChangeCurrentStore200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
