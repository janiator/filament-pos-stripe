# OpenAPI\Client\CustomersApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createCustomer()**](CustomersApi.md#createCustomer) | **POST** /customers | Create customer |
| [**deleteCustomer()**](CustomersApi.md#deleteCustomer) | **DELETE** /customers/{id} | Delete customer |
| [**getCustomer()**](CustomersApi.md#getCustomer) | **GET** /customers/{id} | Get customer |
| [**listCustomers()**](CustomersApi.md#listCustomers) | **GET** /customers | List customers |
| [**updateCustomer()**](CustomersApi.md#updateCustomer) | **PUT** /customers/{id} | Update customer |


## `createCustomer()`

```php
createCustomer($create_customer_request, $x_tenant): \OpenAPI\Client\Model\Customer
```

Create customer

Create a new customer for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\CustomersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_customer_request = new \OpenAPI\Client\Model\CreateCustomerRequest(); // \OpenAPI\Client\Model\CreateCustomerRequest
$x_tenant = 'x_tenant_example'; // string | Store slug (optional, defaults to user's first store)

try {
    $result = $apiInstance->createCustomer($create_customer_request, $x_tenant);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling CustomersApi->createCustomer: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_customer_request** | [**\OpenAPI\Client\Model\CreateCustomerRequest**](../Model/CreateCustomerRequest.md)|  | |
| **x_tenant** | **string**| Store slug (optional, defaults to user&#39;s first store) | [optional] |

### Return type

[**\OpenAPI\Client\Model\Customer**](../Model/Customer.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `deleteCustomer()`

```php
deleteCustomer($id, $x_tenant)
```

Delete customer

Delete a customer

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\CustomersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 1; // int
$x_tenant = 'x_tenant_example'; // string | Store slug (optional, defaults to user's first store)

try {
    $apiInstance->deleteCustomer($id, $x_tenant);
} catch (Exception $e) {
    echo 'Exception when calling CustomersApi->deleteCustomer: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **x_tenant** | **string**| Store slug (optional, defaults to user&#39;s first store) | [optional] |

### Return type

void (empty response body)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getCustomer()`

```php
getCustomer($id, $x_tenant): \OpenAPI\Client\Model\Customer
```

Get customer

Get a specific customer by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\CustomersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 1; // int
$x_tenant = 'x_tenant_example'; // string | Store slug (optional, defaults to user's first store)

try {
    $result = $apiInstance->getCustomer($id, $x_tenant);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling CustomersApi->getCustomer: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **x_tenant** | **string**| Store slug (optional, defaults to user&#39;s first store) | [optional] |

### Return type

[**\OpenAPI\Client\Model\Customer**](../Model/Customer.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listCustomers()`

```php
listCustomers($per_page, $x_tenant): \OpenAPI\Client\Model\PaginatedCustomers
```

List customers

Get paginated list of customers for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\CustomersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$per_page = 15; // int | Number of items per page
$x_tenant = 'x_tenant_example'; // string | Store slug (optional, defaults to user's first store)

try {
    $result = $apiInstance->listCustomers($per_page, $x_tenant);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling CustomersApi->listCustomers: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **per_page** | **int**| Number of items per page | [optional] [default to 15] |
| **x_tenant** | **string**| Store slug (optional, defaults to user&#39;s first store) | [optional] |

### Return type

[**\OpenAPI\Client\Model\PaginatedCustomers**](../Model/PaginatedCustomers.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updateCustomer()`

```php
updateCustomer($id, $update_customer_request, $x_tenant): \OpenAPI\Client\Model\Customer
```

Update customer

Update a customer's information

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\CustomersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 1; // int
$update_customer_request = new \OpenAPI\Client\Model\UpdateCustomerRequest(); // \OpenAPI\Client\Model\UpdateCustomerRequest
$x_tenant = 'x_tenant_example'; // string | Store slug (optional, defaults to user's first store)

try {
    $result = $apiInstance->updateCustomer($id, $update_customer_request, $x_tenant);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling CustomersApi->updateCustomer: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **update_customer_request** | [**\OpenAPI\Client\Model\UpdateCustomerRequest**](../Model/UpdateCustomerRequest.md)|  | |
| **x_tenant** | **string**| Store slug (optional, defaults to user&#39;s first store) | [optional] |

### Return type

[**\OpenAPI\Client\Model\Customer**](../Model/Customer.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
