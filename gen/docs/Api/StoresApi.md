# OpenAPIClient\StoresApi

Store management operations

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**changeCurrentStore()**](StoresApi.md#changeCurrentStore) | **PUT** /stores/current | Change current store |
| [**getCurrentStore()**](StoresApi.md#getCurrentStore) | **GET** /stores/current | Get current store |
| [**getMeranoTicketProduct()**](StoresApi.md#getMeranoTicketProduct) | **GET** /stores/current/merano-ticket-product | Get Merano ticket product |
| [**getStore()**](StoresApi.md#getStore) | **GET** /stores/{slug} | Get store by slug |
| [**listStores()**](StoresApi.md#listStores) | **GET** /stores | List stores |
| [**patchCurrentStore()**](StoresApi.md#patchCurrentStore) | **PATCH** /stores/current | Change current store (partial) |


## `changeCurrentStore()`

```php
changeCurrentStore($change_current_store_request): \OpenAPIClient\Model\ChangeCurrentStore200Response
```

Change current store

Change the authenticated user's current/default store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$change_current_store_request = new \OpenAPIClient\Model\ChangeCurrentStoreRequest(); // \OpenAPIClient\Model\ChangeCurrentStoreRequest

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
| **change_current_store_request** | [**\OpenAPIClient\Model\ChangeCurrentStoreRequest**](../Model/ChangeCurrentStoreRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\ChangeCurrentStore200Response**](../Model/ChangeCurrentStore200Response.md)

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
getCurrentStore(): \OpenAPIClient\Model\GetCurrentStore200Response
```

Get current store

Get the authenticated user's default store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\StoresApi(
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

[**\OpenAPIClient\Model\GetCurrentStore200Response**](../Model/GetCurrentStore200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getMeranoTicketProduct()`

```php
getMeranoTicketProduct(): \OpenAPIClient\Model\GetMeranoTicketProduct200Response
```

Get Merano ticket product

Get the product configured in Filament as the Merano ticket product for the current store. Used by the POS when adding Merano bookings to the cart (e.g. showProviderActionModal with ticketProduct, or addMeranoBookingResultToCart). Returns the same product shape as GET /products/{id}. Tenant is determined by X-Tenant header or the authenticated user's current store.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->getMeranoTicketProduct();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling StoresApi->getMeranoTicketProduct: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

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

## `getStore()`

```php
getStore($slug): \OpenAPIClient\Model\GetCurrentStore200Response
```

Get store by slug

Get a specific store by its slug

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\StoresApi(
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

[**\OpenAPIClient\Model\GetCurrentStore200Response**](../Model/GetCurrentStore200Response.md)

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
listStores(): \OpenAPIClient\Model\ListStores200Response
```

List stores

Get all stores accessible by the authenticated user

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\StoresApi(
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

[**\OpenAPIClient\Model\ListStores200Response**](../Model/ListStores200Response.md)

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
patchCurrentStore($change_current_store_request): \OpenAPIClient\Model\ChangeCurrentStore200Response
```

Change current store (partial)

Change the authenticated user's current/default store (alternative to PUT)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\StoresApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$change_current_store_request = new \OpenAPIClient\Model\ChangeCurrentStoreRequest(); // \OpenAPIClient\Model\ChangeCurrentStoreRequest

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
| **change_current_store_request** | [**\OpenAPIClient\Model\ChangeCurrentStoreRequest**](../Model/ChangeCurrentStoreRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\ChangeCurrentStore200Response**](../Model/ChangeCurrentStore200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
