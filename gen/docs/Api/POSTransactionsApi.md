# OpenAPIClient\POSTransactionsApi



All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createCorrectionReceipt()**](POSTransactionsApi.md#createCorrectionReceipt) | **POST** /pos-transactions/correction-receipt | Create correction receipt |
| [**voidTransaction()**](POSTransactionsApi.md#voidTransaction) | **POST** /pos-transactions/charges/{chargeId}/void | Void transaction |


## `createCorrectionReceipt()`

```php
createCorrectionReceipt($create_correction_receipt_request): \OpenAPIClient\Model\CreateCorrectionReceipt201Response
```

Create correction receipt

Create a correction receipt for errors. Logs event 13015.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSTransactionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_correction_receipt_request = new \OpenAPIClient\Model\CreateCorrectionReceiptRequest(); // \OpenAPIClient\Model\CreateCorrectionReceiptRequest

try {
    $result = $apiInstance->createCorrectionReceipt($create_correction_receipt_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSTransactionsApi->createCorrectionReceipt: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_correction_receipt_request** | [**\OpenAPIClient\Model\CreateCorrectionReceiptRequest**](../Model/CreateCorrectionReceiptRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\CreateCorrectionReceipt201Response**](../Model/CreateCorrectionReceipt201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `voidTransaction()`

```php
voidTransaction($charge_id, $void_transaction_request): \OpenAPIClient\Model\VoidTransaction200Response
```

Void transaction

Void a transaction (cancel before completion). Logs event 13014.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSTransactionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$charge_id = 56; // int
$void_transaction_request = new \OpenAPIClient\Model\VoidTransactionRequest(); // \OpenAPIClient\Model\VoidTransactionRequest

try {
    $result = $apiInstance->voidTransaction($charge_id, $void_transaction_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSTransactionsApi->voidTransaction: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **charge_id** | **int**|  | |
| **void_transaction_request** | [**\OpenAPIClient\Model\VoidTransactionRequest**](../Model/VoidTransactionRequest.md)|  | [optional] |

### Return type

[**\OpenAPIClient\Model\VoidTransaction200Response**](../Model/VoidTransaction200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
