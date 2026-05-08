# OpenAPIClient\MeranoApi

Merano booking proxy operations for POS devices

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**checkMeranoAvailability()**](MeranoApi.md#checkMeranoAvailability) | **POST** /merano/v1/events/{event}/availability | Check Merano seat availability |
| [**confirmMeranoPosPayment()**](MeranoApi.md#confirmMeranoPosPayment) | **POST** /merano/v1/bookings/{booking}/confirm-pos-payment | Confirm Merano POS payment |
| [**createMeranoBooking()**](MeranoApi.md#createMeranoBooking) | **POST** /merano/v1/bookings | Create Merano booking |
| [**getMeranoTicketProduct()**](MeranoApi.md#getMeranoTicketProduct) | **GET** /stores/current/merano-ticket-product | Get Merano ticket product |
| [**listMeranoEvents()**](MeranoApi.md#listMeranoEvents) | **GET** /merano/v1/events | List Merano events |
| [**releaseMeranoBooking()**](MeranoApi.md#releaseMeranoBooking) | **POST** /merano/v1/bookings/{booking}/release | Release Merano booking |


## `checkMeranoAvailability()`

```php
checkMeranoAvailability($event, $check_merano_availability_request): array<string,mixed>
```

Check Merano seat availability

Proxy Merano seat availability checks through POSitiv.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\MeranoApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$event = 'event_example'; // string
$check_merano_availability_request = new \OpenAPIClient\Model\CheckMeranoAvailabilityRequest(); // \OpenAPIClient\Model\CheckMeranoAvailabilityRequest

try {
    $result = $apiInstance->checkMeranoAvailability($event, $check_merano_availability_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling MeranoApi->checkMeranoAvailability: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **event** | **string**|  | |
| **check_merano_availability_request** | [**\OpenAPIClient\Model\CheckMeranoAvailabilityRequest**](../Model/CheckMeranoAvailabilityRequest.md)|  | |

### Return type

**array<string,mixed>**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `confirmMeranoPosPayment()`

```php
confirmMeranoPosPayment($booking, $confirm_merano_pos_payment_request): array<string,mixed>
```

Confirm Merano POS payment

Confirm a pending Merano booking after a successful POS payment.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\MeranoApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$booking = 'booking_example'; // string
$confirm_merano_pos_payment_request = new \OpenAPIClient\Model\ConfirmMeranoPosPaymentRequest(); // \OpenAPIClient\Model\ConfirmMeranoPosPaymentRequest

try {
    $result = $apiInstance->confirmMeranoPosPayment($booking, $confirm_merano_pos_payment_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling MeranoApi->confirmMeranoPosPayment: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **booking** | **string**|  | |
| **confirm_merano_pos_payment_request** | [**\OpenAPIClient\Model\ConfirmMeranoPosPaymentRequest**](../Model/ConfirmMeranoPosPaymentRequest.md)|  | |

### Return type

**array<string,mixed>**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createMeranoBooking()`

```php
createMeranoBooking($create_merano_booking_request): array<string,mixed>
```

Create Merano booking

Create a pending Merano booking through the POSitiv proxy.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\MeranoApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_merano_booking_request = new \OpenAPIClient\Model\CreateMeranoBookingRequest(); // \OpenAPIClient\Model\CreateMeranoBookingRequest

try {
    $result = $apiInstance->createMeranoBooking($create_merano_booking_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling MeranoApi->createMeranoBooking: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_merano_booking_request** | [**\OpenAPIClient\Model\CreateMeranoBookingRequest**](../Model/CreateMeranoBookingRequest.md)|  | |

### Return type

**array<string,mixed>**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
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


$apiInstance = new OpenAPIClient\Api\MeranoApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->getMeranoTicketProduct();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling MeranoApi->getMeranoTicketProduct: ', $e->getMessage(), PHP_EOL;
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

## `listMeranoEvents()`

```php
listMeranoEvents($pos_device_id): array<string,mixed>
```

List Merano events

Proxy the store's configured Merano event list through POSitiv.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\MeranoApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$pos_device_id = 56; // int | Optional POS device ID used to enforce per-device booking access.

try {
    $result = $apiInstance->listMeranoEvents($pos_device_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling MeranoApi->listMeranoEvents: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **pos_device_id** | **int**| Optional POS device ID used to enforce per-device booking access. | [optional] |

### Return type

**array<string,mixed>**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `releaseMeranoBooking()`

```php
releaseMeranoBooking($booking, $release_merano_booking_request): array<string,mixed>
```

Release Merano booking

Release a pending Merano booking through POSitiv.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\MeranoApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$booking = 'booking_example'; // string
$release_merano_booking_request = new \OpenAPIClient\Model\ReleaseMeranoBookingRequest(); // \OpenAPIClient\Model\ReleaseMeranoBookingRequest

try {
    $result = $apiInstance->releaseMeranoBooking($booking, $release_merano_booking_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling MeranoApi->releaseMeranoBooking: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **booking** | **string**|  | |
| **release_merano_booking_request** | [**\OpenAPIClient\Model\ReleaseMeranoBookingRequest**](../Model/ReleaseMeranoBookingRequest.md)|  | [optional] |

### Return type

**array<string,mixed>**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
