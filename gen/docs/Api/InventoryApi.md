# OpenAPI\Client\InventoryApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**adjustInventory()**](InventoryApi.md#adjustInventory) | **POST** /variants/{variant}/inventory/adjust | Adjust inventory |
| [**bulkUpdateInventory()**](InventoryApi.md#bulkUpdateInventory) | **POST** /inventory/bulk-update | Bulk update inventory |
| [**getProductInventory()**](InventoryApi.md#getProductInventory) | **GET** /products/{product}/inventory | Get product inventory |
| [**setInventory()**](InventoryApi.md#setInventory) | **POST** /variants/{variant}/inventory/set | Set inventory quantity |
| [**updateVariantInventory()**](InventoryApi.md#updateVariantInventory) | **PUT** /variants/{variant}/inventory | Update variant inventory |


## `adjustInventory()`

```php
adjustInventory($variant, $adjust_inventory_request): \OpenAPI\Client\Model\AdjustInventory200Response
```

Adjust inventory

Add or subtract from inventory quantity

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\InventoryApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$variant = 56; // int
$adjust_inventory_request = new \OpenAPI\Client\Model\AdjustInventoryRequest(); // \OpenAPI\Client\Model\AdjustInventoryRequest

try {
    $result = $apiInstance->adjustInventory($variant, $adjust_inventory_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling InventoryApi->adjustInventory: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **variant** | **int**|  | |
| **adjust_inventory_request** | [**\OpenAPI\Client\Model\AdjustInventoryRequest**](../Model/AdjustInventoryRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\AdjustInventory200Response**](../Model/AdjustInventory200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `bulkUpdateInventory()`

```php
bulkUpdateInventory($bulk_update_inventory_request): \OpenAPI\Client\Model\BulkUpdateInventory200Response
```

Bulk update inventory

Update inventory for multiple variants at once

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\InventoryApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$bulk_update_inventory_request = new \OpenAPI\Client\Model\BulkUpdateInventoryRequest(); // \OpenAPI\Client\Model\BulkUpdateInventoryRequest

try {
    $result = $apiInstance->bulkUpdateInventory($bulk_update_inventory_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling InventoryApi->bulkUpdateInventory: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **bulk_update_inventory_request** | [**\OpenAPI\Client\Model\BulkUpdateInventoryRequest**](../Model/BulkUpdateInventoryRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\BulkUpdateInventory200Response**](../Model/BulkUpdateInventory200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getProductInventory()`

```php
getProductInventory($product): \OpenAPI\Client\Model\GetProductInventory200Response
```

Get product inventory

Get inventory information for a product and all its variants

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\InventoryApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$product = 56; // int

try {
    $result = $apiInstance->getProductInventory($product);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling InventoryApi->getProductInventory: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **product** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\GetProductInventory200Response**](../Model/GetProductInventory200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `setInventory()`

```php
setInventory($variant, $set_inventory_request): \OpenAPI\Client\Model\SetInventory200Response
```

Set inventory quantity

Set inventory quantity to a specific value

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\InventoryApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$variant = 56; // int
$set_inventory_request = new \OpenAPI\Client\Model\SetInventoryRequest(); // \OpenAPI\Client\Model\SetInventoryRequest

try {
    $result = $apiInstance->setInventory($variant, $set_inventory_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling InventoryApi->setInventory: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **variant** | **int**|  | |
| **set_inventory_request** | [**\OpenAPI\Client\Model\SetInventoryRequest**](../Model/SetInventoryRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\SetInventory200Response**](../Model/SetInventory200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updateVariantInventory()`

```php
updateVariantInventory($variant, $update_variant_inventory_request): \OpenAPI\Client\Model\UpdateVariantInventory200Response
```

Update variant inventory

Update inventory settings for a product variant

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\InventoryApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$variant = 56; // int
$update_variant_inventory_request = new \OpenAPI\Client\Model\UpdateVariantInventoryRequest(); // \OpenAPI\Client\Model\UpdateVariantInventoryRequest

try {
    $result = $apiInstance->updateVariantInventory($variant, $update_variant_inventory_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling InventoryApi->updateVariantInventory: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **variant** | **int**|  | |
| **update_variant_inventory_request** | [**\OpenAPI\Client\Model\UpdateVariantInventoryRequest**](../Model/UpdateVariantInventoryRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\UpdateVariantInventory200Response**](../Model/UpdateVariantInventory200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
