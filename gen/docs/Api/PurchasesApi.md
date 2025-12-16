# OpenAPI\Client\PurchasesApi

POS purchase processing with multiple payment methods

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**cancelPurchase()**](PurchasesApi.md#cancelPurchase) | **POST** /purchases/{id}/cancel | Cancel a pending purchase |
| [**completePurchasePayment()**](PurchasesApi.md#completePurchasePayment) | **POST** /purchases/{id}/complete-payment | Complete payment for deferred purchase |
| [**createPurchase()**](PurchasesApi.md#createPurchase) | **POST** /purchases | Complete purchase (single or split payment) |
| [**getPaymentMethods()**](PurchasesApi.md#getPaymentMethods) | **GET** /purchases/payment-methods | Get available payment methods |
| [**getPurchase()**](PurchasesApi.md#getPurchase) | **GET** /purchases/{id} | Get purchase |
| [**listPurchases()**](PurchasesApi.md#listPurchases) | **GET** /purchases | List purchases |
| [**refundPurchase()**](PurchasesApi.md#refundPurchase) | **POST** /purchases/{id}/refund | Refund a purchase |
| [**updatePurchaseCustomer()**](PurchasesApi.md#updatePurchaseCustomer) | **PUT** /purchases/{id}/customer | Register or update customer for purchase |
| [**updatePurchaseCustomerPatch()**](PurchasesApi.md#updatePurchaseCustomerPatch) | **PATCH** /purchases/{id}/customer | Register or update customer for purchase (PATCH) |


## `cancelPurchase()`

```php
cancelPurchase($id, $cancel_purchase_request): \OpenAPI\Client\Model\CancelPurchase200Response
```

Cancel a pending purchase

Cancels a purchase that is in pending status (e.g., deferred payments).  This endpoint: 1. Validates the purchase is pending and not paid 2. Cancels the Stripe payment intent if one exists (for deferred payments) 3. Changes purchase status to 'cancelled' 4. Logs a void transaction event (13014) 5. Updates purchase metadata with cancellation information  **Use cases:** - Cancel deferred payments that were never completed - Cancel pending orders that customers no longer want - Handle abandoned transactions  **Note:** This is different from a refund. Refunds are for completed purchases. Cancellation is for pending purchases that never completed payment.

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
$cancel_purchase_request = new \OpenAPI\Client\Model\CancelPurchaseRequest(); // \OpenAPI\Client\Model\CancelPurchaseRequest

try {
    $result = $apiInstance->cancelPurchase($id, $cancel_purchase_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->cancelPurchase: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**| Purchase ID | |
| **cancel_purchase_request** | [**\OpenAPI\Client\Model\CancelPurchaseRequest**](../Model/CancelPurchaseRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\CancelPurchase200Response**](../Model/CancelPurchase200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `completePurchasePayment()`

```php
completePurchasePayment($id, $complete_purchase_payment_request): \OpenAPI\Client\Model\CompletePurchasePayment200Response
```

Complete payment for deferred purchase

Complete payment for a purchase that was created with deferred payment (e.g., dry cleaning, payment on pickup).  This endpoint: 1. Validates the charge is pending 2. Processes payment based on the provided payment method 3. Updates charge status to succeeded 4. Generates a sales receipt (replacing the delivery receipt) 5. Logs POS events 6. Updates POS session totals 7. Opens cash drawer for cash payments 8. Automatically prints receipt (if configured)  For Stripe payments, the payment intent must already be created and confirmed. Include the payment_intent_id in the metadata.  **Compliance:** This complies with Kassasystemforskriften § 2-8-7 (Utleveringskvittering). Deferred payments generate delivery receipts initially, then sales receipts when payment is completed.

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
$id = 56; // int | Purchase (charge) ID
$complete_purchase_payment_request = new \OpenAPI\Client\Model\CompletePurchasePaymentRequest(); // \OpenAPI\Client\Model\CompletePurchasePaymentRequest

try {
    $result = $apiInstance->completePurchasePayment($id, $complete_purchase_payment_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->completePurchasePayment: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**| Purchase (charge) ID | |
| **complete_purchase_payment_request** | [**\OpenAPI\Client\Model\CompletePurchasePaymentRequest**](../Model/CompletePurchasePaymentRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\CompletePurchasePayment200Response**](../Model/CompletePurchasePayment200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createPurchase()`

```php
createPurchase($create_purchase_request): \OpenAPI\Client\Model\CreatePurchase201Response
```

Complete purchase (single or split payment)

Process a complete POS purchase with single or split payment methods.  **Single Payment:** Include `payment_method_code` in request body. **Split Payment:** Include `payments` array in request body. **Deferred Payment:** Set `metadata.deferred_payment` to `true` to create a purchase with payment on pickup/later. When using deferred payment, a delivery receipt (Utleveringskvittering) is generated per Kassasystemforskriften § 2-8-7. Payment can be completed later using POST /purchases/{id}/complete-payment.  This endpoint: 1. Validates the cart and payment method(s) 2. Processes payment(s) based on method (cash, Stripe, etc.) or creates pending charge for deferred payments 3. Creates ConnectedCharge record(s) with status 'pending' for deferred payments 4. Generates a receipt (sales receipt for paid, delivery receipt for deferred) 5. Logs POS events for kassasystemforskriften compliance 6. Updates POS session totals (only for paid charges) 7. Opens cash drawer for cash payments (not for deferred) 8. Automatically prints receipt (if configured)  For Stripe payments, the payment intent must already be created and confirmed. Include the payment_intent_id in the metadata.

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
listPurchases($pos_session_id, $status, $payment_method, $customer_id, $from_date, $to_date, $paid_from_date, $paid_to_date, $search, $per_page): \OpenAPI\Client\Model\ListPurchases200Response
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
$customer_id = 1; // int | Filter by customer database ID (integer)
$from_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter purchases created from this date (YYYY-MM-DD)
$to_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter purchases created until this date (YYYY-MM-DD)
$paid_from_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter purchases paid from this date (YYYY-MM-DD)
$paid_to_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter purchases paid until this date (YYYY-MM-DD)
$search = 'search_example'; // string | Freetext search term that searches across multiple purchase fields: - Purchase ID (exact match for numeric IDs) - Stripe charge ID - Description - Transaction code and payment code - Article group code - Customer name, email, phone, or Stripe customer ID - Receipt number - Purchase items (purchase_items) - searches in:   * Item names, product names, descriptions   * Product codes and article group codes   * SKUs and barcodes   * Item IDs (purchase_item_id)   * Product IDs and variant IDs (if search term is numeric)   * Product names via product relationships (from purchase_item_product_id or product_id)   * Variant SKUs, barcodes, and option values via variant relationships (from purchase_item_variant_id or variant_id)
$per_page = 20; // int | Number of items per page (max 100)

try {
    $result = $apiInstance->listPurchases($pos_session_id, $status, $payment_method, $customer_id, $from_date, $to_date, $paid_from_date, $paid_to_date, $search, $per_page);
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
| **customer_id** | **int**| Filter by customer database ID (integer) | [optional] |
| **from_date** | **\DateTime**| Filter purchases created from this date (YYYY-MM-DD) | [optional] |
| **to_date** | **\DateTime**| Filter purchases created until this date (YYYY-MM-DD) | [optional] |
| **paid_from_date** | **\DateTime**| Filter purchases paid from this date (YYYY-MM-DD) | [optional] |
| **paid_to_date** | **\DateTime**| Filter purchases paid until this date (YYYY-MM-DD) | [optional] |
| **search** | **string**| Freetext search term that searches across multiple purchase fields: - Purchase ID (exact match for numeric IDs) - Stripe charge ID - Description - Transaction code and payment code - Article group code - Customer name, email, phone, or Stripe customer ID - Receipt number - Purchase items (purchase_items) - searches in:   * Item names, product names, descriptions   * Product codes and article group codes   * SKUs and barcodes   * Item IDs (purchase_item_id)   * Product IDs and variant IDs (if search term is numeric)   * Product names via product relationships (from purchase_item_product_id or product_id)   * Variant SKUs, barcodes, and option values via variant relationships (from purchase_item_variant_id or variant_id) | [optional] |
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

## `refundPurchase()`

```php
refundPurchase($id, $refund_purchase_request): \OpenAPI\Client\Model\RefundPurchase200Response
```

Refund a purchase

Processes refunds for both Stripe and cash payments.  **For Stripe payments:** - Creates a refund in Stripe - Updates local charge record with refund information - Supports full and partial refunds  **For cash payments:** - Updates local records only (manual refund process) - Cash must be physically returned to customer  **Automatic processes:** 1. Validates purchase can be refunded (paid, not cancelled) 2. Processes Stripe refund if applicable 3. Updates charge status and refund amounts 4. Generates return receipt automatically (Kassasystemforskriften compliance) 5. Logs return receipt event (13013) 6. Updates POS session totals (decrements amounts) 7. Auto-prints receipt if configured  **Compliance:** - Return receipts are immutable and sequentially numbered - All refunds are logged in POS events for audit trail - Supports refund reasons for compliance tracking  **Use cases:** - Full refunds for returned items - Partial refunds for partial returns - Refunds with reasons for compliance tracking

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
$refund_purchase_request = new \OpenAPI\Client\Model\RefundPurchaseRequest(); // \OpenAPI\Client\Model\RefundPurchaseRequest

try {
    $result = $apiInstance->refundPurchase($id, $refund_purchase_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PurchasesApi->refundPurchase: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**| Purchase ID | |
| **refund_purchase_request** | [**\OpenAPI\Client\Model\RefundPurchaseRequest**](../Model/RefundPurchaseRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\RefundPurchase200Response**](../Model/RefundPurchase200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
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
