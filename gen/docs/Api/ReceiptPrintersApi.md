# OpenAPIClient\ReceiptPrintersApi



All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createReceiptPrinter()**](ReceiptPrintersApi.md#createReceiptPrinter) | **POST** /receipt-printers | Create receipt printer |
| [**deleteReceiptPrinter()**](ReceiptPrintersApi.md#deleteReceiptPrinter) | **DELETE** /receipt-printers/{id} | Delete receipt printer |
| [**getReceiptPrinter()**](ReceiptPrintersApi.md#getReceiptPrinter) | **GET** /receipt-printers/{id} | Get receipt printer |
| [**listReceiptPrinters()**](ReceiptPrintersApi.md#listReceiptPrinters) | **GET** /receipt-printers | List receipt printers |
| [**patchReceiptPrinter()**](ReceiptPrintersApi.md#patchReceiptPrinter) | **PATCH** /receipt-printers/{id} | Update receipt printer (partial) |
| [**testReceiptPrinterConnection()**](ReceiptPrintersApi.md#testReceiptPrinterConnection) | **POST** /receipt-printers/{id}/test-connection | Test printer connection |
| [**testReceiptPrinterPrint()**](ReceiptPrintersApi.md#testReceiptPrinterPrint) | **POST** /receipt-printers/{id}/test-print | Send test print |
| [**updateReceiptPrinter()**](ReceiptPrintersApi.md#updateReceiptPrinter) | **PUT** /receipt-printers/{id} | Update receipt printer |


## `createReceiptPrinter()`

```php
createReceiptPrinter($create_receipt_printer_request): \OpenAPIClient\Model\CreateReceiptPrinter201Response
```

Create receipt printer

Create a new receipt printer for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_receipt_printer_request = new \OpenAPIClient\Model\CreateReceiptPrinterRequest(); // \OpenAPIClient\Model\CreateReceiptPrinterRequest

try {
    $result = $apiInstance->createReceiptPrinter($create_receipt_printer_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->createReceiptPrinter: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_receipt_printer_request** | [**\OpenAPIClient\Model\CreateReceiptPrinterRequest**](../Model/CreateReceiptPrinterRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\CreateReceiptPrinter201Response**](../Model/CreateReceiptPrinter201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `deleteReceiptPrinter()`

```php
deleteReceiptPrinter($id): \OpenAPIClient\Model\DeleteReceiptPrinter200Response
```

Delete receipt printer

Delete a receipt printer

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->deleteReceiptPrinter($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->deleteReceiptPrinter: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\DeleteReceiptPrinter200Response**](../Model/DeleteReceiptPrinter200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getReceiptPrinter()`

```php
getReceiptPrinter($id): \OpenAPIClient\Model\GetReceiptPrinter200Response
```

Get receipt printer

Get a specific receipt printer by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getReceiptPrinter($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->getReceiptPrinter: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\GetReceiptPrinter200Response**](../Model/GetReceiptPrinter200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listReceiptPrinters()`

```php
listReceiptPrinters(): \OpenAPIClient\Model\ListReceiptPrinters200Response
```

List receipt printers

Get all receipt printers for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->listReceiptPrinters();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->listReceiptPrinters: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPIClient\Model\ListReceiptPrinters200Response**](../Model/ListReceiptPrinters200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `patchReceiptPrinter()`

```php
patchReceiptPrinter($id, $patch_receipt_printer_request): \OpenAPIClient\Model\UpdateReceiptPrinter200Response
```

Update receipt printer (partial)

Partially update a receipt printer

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$patch_receipt_printer_request = new \OpenAPIClient\Model\PatchReceiptPrinterRequest(); // \OpenAPIClient\Model\PatchReceiptPrinterRequest

try {
    $result = $apiInstance->patchReceiptPrinter($id, $patch_receipt_printer_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->patchReceiptPrinter: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **patch_receipt_printer_request** | [**\OpenAPIClient\Model\PatchReceiptPrinterRequest**](../Model/PatchReceiptPrinterRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\UpdateReceiptPrinter200Response**](../Model/UpdateReceiptPrinter200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `testReceiptPrinterConnection()`

```php
testReceiptPrinterConnection($id): \OpenAPIClient\Model\TestReceiptPrinterConnection200Response
```

Test printer connection

Test network connection to a receipt printer

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->testReceiptPrinterConnection($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->testReceiptPrinterConnection: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\TestReceiptPrinterConnection200Response**](../Model/TestReceiptPrinterConnection200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `testReceiptPrinterPrint()`

```php
testReceiptPrinterPrint($id): \OpenAPIClient\Model\TestReceiptPrinterPrint200Response
```

Send test print

Send a test print to a receipt printer

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->testReceiptPrinterPrint($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->testReceiptPrinterPrint: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\TestReceiptPrinterPrint200Response**](../Model/TestReceiptPrinterPrint200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updateReceiptPrinter()`

```php
updateReceiptPrinter($id, $update_receipt_printer_request): \OpenAPIClient\Model\UpdateReceiptPrinter200Response
```

Update receipt printer

Update a receipt printer

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptPrintersApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$update_receipt_printer_request = new \OpenAPIClient\Model\UpdateReceiptPrinterRequest(); // \OpenAPIClient\Model\UpdateReceiptPrinterRequest

try {
    $result = $apiInstance->updateReceiptPrinter($id, $update_receipt_printer_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptPrintersApi->updateReceiptPrinter: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **update_receipt_printer_request** | [**\OpenAPIClient\Model\UpdateReceiptPrinterRequest**](../Model/UpdateReceiptPrinterRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\UpdateReceiptPrinter200Response**](../Model/UpdateReceiptPrinter200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
