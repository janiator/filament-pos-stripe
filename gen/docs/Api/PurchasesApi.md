# OpenAPI\Client\PurchasesApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createPurchase()**](PurchasesApi.md#createPurchase) | **POST** /purchases | Complete purchase (single or split payment) |
| [**getPaymentMethods()**](PurchasesApi.md#getPaymentMethods) | **GET** /purchases/payment-methods | Get available payment methods |
| [**getPurchase()**](PurchasesApi.md#getPurchase) | **GET** /purchases/{id} | Get purchase |
| [**listPurchases()**](PurchasesApi.md#listPurchases) | **GET** /purchases | List purchases |
| [**updatePurchaseCustomer()**](PurchasesApi.md#updatePurchaseCustomer) | **PUT** /purchases/{id}/customer | Register or update customer for purchase |
| [**updatePurchaseCustomerPatch()**](PurchasesApi.md#updatePurchaseCustomerPatch) | **PATCH** /purchases/{id}/customer | Register or update customer for purchase (PATCH) |


## `createPurchase()`

```php
createPurchase($create_purchase_request): \OpenAPI\Client\Model\CreatePurchase201Response
```

Complete purchase (single or split payment)

Process a complete POS purchase with single or split payment methods.  **Single Payment:** Include `payment_method_code` in request body. **Split Payment:** Include `payments` array in request body.  This endpoint: 1. Validates the cart and payment method(s) 2. Processes payment(s) based on method (cash, Stripe, etc.) 3. Creates ConnectedCharge record(s) 4. Generates a receipt (single receipt for split payments) 5. Logs POS events for kassasystemforskriften compliance 6. Updates POS session totals 7. Opens cash drawer for cash payments 8. Automatically prints receipt (if configured)  For Stripe payments, the payment intent must already be created and confirmed. Include the payment_intent_id in the metadata.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\PurchasesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_purchase_request = new \OpenAPI\Client\Model\CreatePurchaseRequest(); // \OpenAPI\Client\Model\CreatePurchaseRequest

try {
    $result = $apiInstance->createPurchase($create_purchase_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->createPurchase: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_purchase_request** | [**\OpenAPI\Client\Model\CreatePurchaseRequest**](../Model/CreatePurchaseRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\CreatePurchase201Response**](../Model/CreatePurchase201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getPaymentMethods()`

```php
getPaymentMethods(): \OpenAPI\Client\Model\GetPaymentMethods200Response
```

Get available payment methods

Get list of enabled payment methods for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\PurchasesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->getPaymentMethods();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->getPaymentMethods: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPI\Client\Model\GetPaymentMethods200Response**](../Model/GetPaymentMethods200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getPurchase()`

```php
getPurchase($id): \OpenAPI\Client\Model\GetPurchase200Response
```

Get purchase

Retrieve a single purchase by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\PurchasesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int | Purchase ID

try {
    $result = $apiInstance->getPurchase($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->getPurchase: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**| Purchase ID | |

### Return type

[**\OpenAPI\Client\Model\GetPurchase200Response**](../Model/GetPurchase200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listPurchases()`

```php
listPurchases($pos_session_id, $status, $payment_method, $from_date, $to_date, $paid_from_date, $paid_to_date, $per_page): \OpenAPI\Client\Model\ListPurchases200Response
```

List purchases

Retrieve a paginated list of purchases (charges) for the current store. Only purchases associated with POS sessions are returned.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\PurchasesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$pos_session_id = 56; // int | Filter by POS session ID
$status = 'status_example'; // string | Filter by charge status
$payment_method = cash; // string | Filter by payment method
$from_date = new \DateTime("2013-10-20T19:20:30+01:00"); // \DateTime | Filter purchases created from this date (YYYY-MM-DD)
$to_date = new \DateTime("2013-10-20T19:20:30+01:00"); // \DateTime | Filter purchases created until this date (YYYY-MM-DD)
$paid_from_date = new \DateTime("2013-10-20T19:20:30+01:00"); // \DateTime | Filter purchases paid from this date (YYYY-MM-DD)
$paid_to_date = new \DateTime("2013-10-20T19:20:30+01:00"); // \DateTime | Filter purchases paid until this date (YYYY-MM-DD)
$per_page = 20; // int | Number of items per page (max 100)

try {
    $result = $apiInstance->listPurchases($pos_session_id, $status, $payment_method, $from_date, $to_date, $paid_from_date, $paid_to_date, $per_page);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->listPurchases: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **pos_session_id** | **int**| Filter by POS session ID | [optional] |
| **status** | **string**| Filter by charge status | [optional] |
| **payment_method** | **string**| Filter by payment method | [optional] |
| **from_date** | **\DateTime**| Filter purchases created from this date (YYYY-MM-DD) | [optional] |
| **to_date** | **\DateTime**| Filter purchases created until this date (YYYY-MM-DD) | [optional] |
| **paid_from_date** | **\DateTime**| Filter purchases paid from this date (YYYY-MM-DD) | [optional] |
| **paid_to_date** | **\DateTime**| Filter purchases paid until this date (YYYY-MM-DD) | [optional] |
| **per_page** | **int**| Number of items per page (max 100) | [optional] [default to 20] |

### Return type

[**\OpenAPI\Client\Model\ListPurchases200Response**](../Model/ListPurchases200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updatePurchaseCustomer()`

```php
updatePurchaseCustomer($id, $update_purchase_customer_request): \OpenAPI\Client\Model\UpdatePurchaseCustomer200Response
```

Register or update customer for purchase

Register a customer to an existing purchase, or remove the customer by setting customer_id to null. This allows associating a customer with a purchase after it has been completed.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\PurchasesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int | Purchase ID
$update_purchase_customer_request = new \OpenAPI\Client\Model\UpdatePurchaseCustomerRequest(); // \OpenAPI\Client\Model\UpdatePurchaseCustomerRequest

try {
    $result = $apiInstance->updatePurchaseCustomer($id, $update_purchase_customer_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->updatePurchaseCustomer: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**| Purchase ID | |
| **update_purchase_customer_request** | [**\OpenAPI\Client\Model\UpdatePurchaseCustomerRequest**](../Model/UpdatePurchaseCustomerRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\UpdatePurchaseCustomer200Response**](../Model/UpdatePurchaseCustomer200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updatePurchaseCustomerPatch()`

```php
updatePurchaseCustomerPatch($id, $update_purchase_customer_request): \OpenAPI\Client\Model\UpdatePurchaseCustomer200Response
```

Register or update customer for purchase (PATCH)

Same as PUT /purchases/{id}/customer

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\PurchasesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int | Purchase ID
$update_purchase_customer_request = new \OpenAPI\Client\Model\UpdatePurchaseCustomerRequest(); // \OpenAPI\Client\Model\UpdatePurchaseCustomerRequest

try {
    $result = $apiInstance->updatePurchaseCustomerPatch($id, $update_purchase_customer_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->updatePurchaseCustomerPatch: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**| Purchase ID | |
| **update_purchase_customer_request** | [**\OpenAPI\Client\Model\UpdatePurchaseCustomerRequest**](../Model/UpdatePurchaseCustomerRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\UpdatePurchaseCustomer200Response**](../Model/UpdatePurchaseCustomer200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
