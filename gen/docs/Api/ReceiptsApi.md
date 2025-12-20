# OpenAPI\Client\ReceiptsApi

Receipt generation and management

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**generateReceipt()**](ReceiptsApi.md#generateReceipt) | **POST** /receipts/generate | Generate receipt |
| [**getReceipt()**](ReceiptsApi.md#getReceipt) | **GET** /receipts/{id} | Get receipt |
| [**getReceiptXml()**](ReceiptsApi.md#getReceiptXml) | **GET** /receipts/{id}/xml | Get receipt XML |
| [**listReceipts()**](ReceiptsApi.md#listReceipts) | **GET** /receipts | List receipts |
| [**markReceiptPrinted()**](ReceiptsApi.md#markReceiptPrinted) | **POST** /receipts/{id}/mark-printed | Mark receipt as printed |
| [**reprintReceipt()**](ReceiptsApi.md#reprintReceipt) | **POST** /receipts/{id}/reprint | Reprint receipt |


## `generateReceipt()`

```php
generateReceipt($generate_receipt_request): \OpenAPI\Client\Model\GenerateReceipt201Response
```

Generate receipt

Generate a receipt for a charge

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$generate_receipt_request = new \OpenAPI\Client\Model\GenerateReceiptRequest(); // \OpenAPI\Client\Model\GenerateReceiptRequest

try {
    $result = $apiInstance->generateReceipt($generate_receipt_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->generateReceipt: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **generate_receipt_request** | [**\OpenAPI\Client\Model\GenerateReceiptRequest**](../Model/GenerateReceiptRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\GenerateReceipt201Response**](../Model/GenerateReceipt201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getReceipt()`

```php
getReceipt($id): \OpenAPI\Client\Model\GetReceipt200Response
```

Get receipt

Get a specific receipt by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getReceipt($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->getReceipt: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\GetReceipt200Response**](../Model/GetReceipt200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getReceiptXml()`

```php
getReceiptXml($id): string
```

Get receipt XML

Get receipt in XML format (for printing)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getReceiptXml($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->getReceiptXml: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

**string**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/xml`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listReceipts()`

```php
listReceipts($receipt_type, $pos_session_id, $charge_id, $printed, $from_date, $to_date, $per_page): \OpenAPI\Client\Model\ListReceipts200Response
```

List receipts

Get paginated list of receipts for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$receipt_type = 'receipt_type_example'; // string | Filter by receipt type
$pos_session_id = 56; // int | Filter by POS session ID
$charge_id = 56; // int | Filter by charge ID (purchase ID) to get all receipts for a single purchase
$printed = True; // bool | Filter by printed status
$from_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter from date (YYYY-MM-DD)
$to_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter to date (YYYY-MM-DD)
$per_page = 20; // int | Number of items per page

try {
    $result = $apiInstance->listReceipts($receipt_type, $pos_session_id, $charge_id, $printed, $from_date, $to_date, $per_page);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->listReceipts: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **receipt_type** | **string**| Filter by receipt type | [optional] |
| **pos_session_id** | **int**| Filter by POS session ID | [optional] |
| **charge_id** | **int**| Filter by charge ID (purchase ID) to get all receipts for a single purchase | [optional] |
| **printed** | **bool**| Filter by printed status | [optional] |
| **from_date** | **\DateTime**| Filter from date (YYYY-MM-DD) | [optional] |
| **to_date** | **\DateTime**| Filter to date (YYYY-MM-DD) | [optional] |
| **per_page** | **int**| Number of items per page | [optional] [default to 20] |

### Return type

[**\OpenAPI\Client\Model\ListReceipts200Response**](../Model/ListReceipts200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `markReceiptPrinted()`

```php
markReceiptPrinted($id): \OpenAPI\Client\Model\MarkReceiptPrinted200Response
```

Mark receipt as printed

Mark a receipt as printed

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->markReceiptPrinted($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->markReceiptPrinted: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\MarkReceiptPrinted200Response**](../Model/MarkReceiptPrinted200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `reprintReceipt()`

```php
reprintReceipt($id): \OpenAPI\Client\Model\ReprintReceipt200Response
```

Reprint receipt

Reprint a receipt (increments reprint count)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->reprintReceipt($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->reprintReceipt: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\ReprintReceipt200Response**](../Model/ReprintReceipt200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
