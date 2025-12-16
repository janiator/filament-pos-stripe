# OpenAPI\Client\TerminalsApi

Terminal locations and readers management (Stripe-specific)

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createTerminalConnectionToken()**](TerminalsApi.md#createTerminalConnectionToken) | **POST** /stores/{store}/terminal/connection-token | Create terminal connection token |
| [**createTerminalPaymentIntent()**](TerminalsApi.md#createTerminalPaymentIntent) | **POST** /stores/{store}/terminal/payment-intents | Create payment intent |
| [**listTerminalLocations()**](TerminalsApi.md#listTerminalLocations) | **GET** /terminals/locations | List terminal locations |
| [**listTerminalReaders()**](TerminalsApi.md#listTerminalReaders) | **GET** /terminals/readers | List terminal readers |


## `createTerminalConnectionToken()`

```php
createTerminalConnectionToken($store, $create_terminal_connection_token_request): \OpenAPI\Client\Model\CreateTerminalConnectionToken200Response
```

Create terminal connection token

Create a connection token for Stripe Terminal

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 1; // string | Store ID or slug
$create_terminal_connection_token_request = new \OpenAPI\Client\Model\CreateTerminalConnectionTokenRequest(); // \OpenAPI\Client\Model\CreateTerminalConnectionTokenRequest

try {
    $result = $apiInstance->createTerminalConnectionToken($store, $create_terminal_connection_token_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TerminalsApi->createTerminalConnectionToken: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **store** | **string**| Store ID or slug | |
| **create_terminal_connection_token_request** | [**\OpenAPI\Client\Model\CreateTerminalConnectionTokenRequest**](../Model/CreateTerminalConnectionTokenRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\CreateTerminalConnectionToken200Response**](../Model/CreateTerminalConnectionToken200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createTerminalPaymentIntent()`

```php
createTerminalPaymentIntent($store, $create_terminal_payment_intent_request): \OpenAPI\Client\Model\CreateTerminalPaymentIntent201Response
```

Create payment intent

Create a payment intent for Stripe Terminal

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 1; // string | Store ID or slug
$create_terminal_payment_intent_request = new \OpenAPI\Client\Model\CreateTerminalPaymentIntentRequest(); // \OpenAPI\Client\Model\CreateTerminalPaymentIntentRequest

try {
    $result = $apiInstance->createTerminalPaymentIntent($store, $create_terminal_payment_intent_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TerminalsApi->createTerminalPaymentIntent: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **store** | **string**| Store ID or slug | |
| **create_terminal_payment_intent_request** | [**\OpenAPI\Client\Model\CreateTerminalPaymentIntentRequest**](../Model/CreateTerminalPaymentIntentRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\CreateTerminalPaymentIntent201Response**](../Model/CreateTerminalPaymentIntent201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listTerminalLocations()`

```php
listTerminalLocations(): \OpenAPI\Client\Model\ListTerminalLocations200Response
```

List terminal locations

Get all terminal locations for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->listTerminalLocations();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TerminalsApi->listTerminalLocations: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPI\Client\Model\ListTerminalLocations200Response**](../Model/ListTerminalLocations200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listTerminalReaders()`

```php
listTerminalReaders(): \OpenAPI\Client\Model\ListTerminalReaders200Response
```

List terminal readers

Get all terminal readers for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->listTerminalReaders();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TerminalsApi->listTerminalReaders: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPI\Client\Model\ListTerminalReaders200Response**](../Model/ListTerminalReaders200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
