# OpenAPIClient\TerminalsApi

Terminal locations and readers management (Stripe-specific)

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createTerminalConnectionToken()**](TerminalsApi.md#createTerminalConnectionToken) | **POST** /stores/{store}/terminal/connection-token | Create terminal connection token |
| [**createTerminalPaymentIntent()**](TerminalsApi.md#createTerminalPaymentIntent) | **POST** /stores/{store}/terminal/payment-intents | Create payment intent |
| [**listTerminalLocations()**](TerminalsApi.md#listTerminalLocations) | **GET** /terminals/locations | List terminal locations |
| [**listTerminalReaders()**](TerminalsApi.md#listTerminalReaders) | **GET** /terminals/readers | List terminal readers |
| [**registerTerminalReaderFromCode()**](TerminalsApi.md#registerTerminalReaderFromCode) | **POST** /terminals/readers/register-from-code | Register terminal reader from registration code |


## `createTerminalConnectionToken()`

```php
createTerminalConnectionToken($store, $create_terminal_connection_token_request): \OpenAPIClient\Model\CreateTerminalConnectionToken200Response
```

Create terminal connection token

Create a connection token for Stripe Terminal. Location can be specified by location_id, or by pos_device_id (uses the terminal location assigned to that POS device). If neither is provided, uses the store default or single location.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 1; // string | Store ID or slug
$create_terminal_connection_token_request = new \OpenAPIClient\Model\CreateTerminalConnectionTokenRequest(); // \OpenAPIClient\Model\CreateTerminalConnectionTokenRequest

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
| **create_terminal_connection_token_request** | [**\OpenAPIClient\Model\CreateTerminalConnectionTokenRequest**](../Model/CreateTerminalConnectionTokenRequest.md)|  | [optional] |

### Return type

[**\OpenAPIClient\Model\CreateTerminalConnectionToken200Response**](../Model/CreateTerminalConnectionToken200Response.md)

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
createTerminalPaymentIntent($store, $create_terminal_payment_intent_request): \OpenAPIClient\Model\CreateTerminalPaymentIntent201Response
```

Create payment intent

Create a payment intent for Stripe Terminal

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$store = 1; // string | Store ID or slug
$create_terminal_payment_intent_request = new \OpenAPIClient\Model\CreateTerminalPaymentIntentRequest(); // \OpenAPIClient\Model\CreateTerminalPaymentIntentRequest

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
| **create_terminal_payment_intent_request** | [**\OpenAPIClient\Model\CreateTerminalPaymentIntentRequest**](../Model/CreateTerminalPaymentIntentRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\CreateTerminalPaymentIntent201Response**](../Model/CreateTerminalPaymentIntent201Response.md)

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
listTerminalLocations($device_identifier): \OpenAPIClient\Model\ListTerminalLocations200Response
```

List terminal locations

Get all terminal locations for the current store. Optional query `device_identifier` — when provided, if the POS device has a last-connected terminal, the response includes `last_connected` (location_id, stripe_location_id, reader_id, stripe_reader_id, etc.) for auto-reconnect on the app.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$device_identifier = 'device_identifier_example'; // string | POS device identifier; when set, response may include last_connected for this device

try {
    $result = $apiInstance->listTerminalLocations($device_identifier);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TerminalsApi->listTerminalLocations: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **device_identifier** | **string**| POS device identifier; when set, response may include last_connected for this device | [optional] |

### Return type

[**\OpenAPIClient\Model\ListTerminalLocations200Response**](../Model/ListTerminalLocations200Response.md)

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
listTerminalReaders(): \OpenAPIClient\Model\ListTerminalReaders200Response
```

List terminal readers

Get all terminal readers for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TerminalsApi(
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

[**\OpenAPIClient\Model\ListTerminalReaders200Response**](../Model/ListTerminalReaders200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `registerTerminalReaderFromCode()`

```php
registerTerminalReaderFromCode($register_terminal_reader_from_code_request, $x_tenant): \OpenAPIClient\Model\RegisterTerminalReaderFromCode201Response
```

Register terminal reader from registration code

Register a Bluetooth reader to the current store and location using a registration code from the reader. If the reader was previously registered to another store, it is removed from that store. Returns the created/updated reader.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TerminalsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$register_terminal_reader_from_code_request = new \OpenAPIClient\Model\RegisterTerminalReaderFromCodeRequest(); // \OpenAPIClient\Model\RegisterTerminalReaderFromCodeRequest
$x_tenant = 'x_tenant_example'; // string | Store slug (optional, defaults to user's current store)

try {
    $result = $apiInstance->registerTerminalReaderFromCode($register_terminal_reader_from_code_request, $x_tenant);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TerminalsApi->registerTerminalReaderFromCode: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **register_terminal_reader_from_code_request** | [**\OpenAPIClient\Model\RegisterTerminalReaderFromCodeRequest**](../Model/RegisterTerminalReaderFromCodeRequest.md)|  | |
| **x_tenant** | **string**| Store slug (optional, defaults to user&#39;s current store) | [optional] |

### Return type

[**\OpenAPIClient\Model\RegisterTerminalReaderFromCode201Response**](../Model/RegisterTerminalReaderFromCode201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
