# OpenAPIClient\VerifoneApi

Verifone POS Cloud terminal operations

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**abortVerifoneTerminalRequest()**](VerifoneApi.md#abortVerifoneTerminalRequest) | **POST** /verifone/stores/{store}/terminals/{terminal}/abort | Abort active Verifone terminal request |
| [**createVerifonePayment()**](VerifoneApi.md#createVerifonePayment) | **POST** /verifone/stores/{store}/payments | Start Verifone terminal payment |
| [**listVerifoneTerminals()**](VerifoneApi.md#listVerifoneTerminals) | **GET** /verifone/stores/{store}/terminals | List Verifone terminals for store |
| [**pollVerifonePaymentStatus()**](VerifoneApi.md#pollVerifonePaymentStatus) | **POST** /verifone/stores/{store}/payments/{serviceId}/status | Poll Verifone transaction status |


## `abortVerifoneTerminalRequest()`

```php
abortVerifoneTerminalRequest($store, $terminal, $verifone_abort_request): \OpenAPIClient\Model\AbortVerifoneTerminalRequest200Response
```

Abort active Verifone terminal request

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\VerifoneApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 'store_example'; // string
$terminal = 56; // int
$verifone_abort_request = new \OpenAPIClient\Model\VerifoneAbortRequest(); // \OpenAPIClient\Model\VerifoneAbortRequest

try {
    $result = $apiInstance->abortVerifoneTerminalRequest($store, $terminal, $verifone_abort_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling VerifoneApi->abortVerifoneTerminalRequest: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **store** | **string**|  | |
| **terminal** | **int**|  | |
| **verifone_abort_request** | [**\OpenAPIClient\Model\VerifoneAbortRequest**](../Model/VerifoneAbortRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\AbortVerifoneTerminalRequest200Response**](../Model/AbortVerifoneTerminalRequest200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createVerifonePayment()`

```php
createVerifonePayment($store, $verifone_payment_create_request): \OpenAPIClient\Model\VerifonePaymentStartResponse
```

Start Verifone terminal payment

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\VerifoneApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 'store_example'; // string
$verifone_payment_create_request = new \OpenAPIClient\Model\VerifonePaymentCreateRequest(); // \OpenAPIClient\Model\VerifonePaymentCreateRequest

try {
    $result = $apiInstance->createVerifonePayment($store, $verifone_payment_create_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling VerifoneApi->createVerifonePayment: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **store** | **string**|  | |
| **verifone_payment_create_request** | [**\OpenAPIClient\Model\VerifonePaymentCreateRequest**](../Model/VerifonePaymentCreateRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\VerifonePaymentStartResponse**](../Model/VerifonePaymentStartResponse.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listVerifoneTerminals()`

```php
listVerifoneTerminals($store): \OpenAPIClient\Model\ListVerifoneTerminals200Response
```

List Verifone terminals for store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\VerifoneApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 'store_example'; // string

try {
    $result = $apiInstance->listVerifoneTerminals($store);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling VerifoneApi->listVerifoneTerminals: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **store** | **string**|  | |

### Return type

[**\OpenAPIClient\Model\ListVerifoneTerminals200Response**](../Model/ListVerifoneTerminals200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `pollVerifonePaymentStatus()`

```php
pollVerifonePaymentStatus($store, $service_id, $verifone_payment_status_request): \OpenAPIClient\Model\VerifonePaymentStatusResponse
```

Poll Verifone transaction status

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\VerifoneApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 'store_example'; // string
$service_id = 'service_id_example'; // string
$verifone_payment_status_request = new \OpenAPIClient\Model\VerifonePaymentStatusRequest(); // \OpenAPIClient\Model\VerifonePaymentStatusRequest

try {
    $result = $apiInstance->pollVerifonePaymentStatus($store, $service_id, $verifone_payment_status_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling VerifoneApi->pollVerifonePaymentStatus: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **store** | **string**|  | |
| **service_id** | **string**|  | |
| **verifone_payment_status_request** | [**\OpenAPIClient\Model\VerifonePaymentStatusRequest**](../Model/VerifonePaymentStatusRequest.md)|  | [optional] |

### Return type

[**\OpenAPIClient\Model\VerifonePaymentStatusResponse**](../Model/VerifonePaymentStatusResponse.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
