# OpenAPIClient\ReceiptsApi

Receipt generation and management

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**generateReceipt()**](ReceiptsApi.md#generateReceipt) | **POST** /receipts/generate | Generate receipt |
| [**getReceipt()**](ReceiptsApi.md#getReceipt) | **GET** /receipts/{id} | Get receipt |
| [**getReceiptXml()**](ReceiptsApi.md#getReceiptXml) | **GET** /receipts/{id}/xml | Get receipt XML |
| [**getTicketXmlByReference()**](ReceiptsApi.md#getTicketXmlByReference) | **GET** /receipts/ticket-xml | Get ticket XML by booking reference |
| [**listReceipts()**](ReceiptsApi.md#listReceipts) | **GET** /receipts | List receipts |
| [**markReceiptPrinted()**](ReceiptsApi.md#markReceiptPrinted) | **POST** /receipts/{id}/mark-printed | Mark receipt as printed |
| [**printBookingTicket()**](ReceiptsApi.md#printBookingTicket) | **POST** /receipts/print-ticket | Render booking ticket XML (full payload) |
| [**printFreeTicket()**](ReceiptsApi.md#printFreeTicket) | **POST** /receipts/print-freeticket | Render free ticket XML |
| [**reprintReceipt()**](ReceiptsApi.md#reprintReceipt) | **POST** /receipts/{id}/reprint | Reprint receipt |


## `generateReceipt()`

```php
generateReceipt($generate_receipt_request): \OpenAPIClient\Model\GenerateReceipt201Response
```

Generate receipt

Generate a receipt for a charge

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$generate_receipt_request = new \OpenAPIClient\Model\GenerateReceiptRequest(); // \OpenAPIClient\Model\GenerateReceiptRequest

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
| **generate_receipt_request** | [**\OpenAPIClient\Model\GenerateReceiptRequest**](../Model/GenerateReceiptRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\GenerateReceipt201Response**](../Model/GenerateReceipt201Response.md)

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
getReceipt($id): \OpenAPIClient\Model\GetReceipt200Response
```

Get receipt

Get a specific receipt by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
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

[**\OpenAPIClient\Model\GetReceipt200Response**](../Model/GetReceipt200Response.md)

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
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
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

## `getTicketXmlByReference()`

```php
getTicketXmlByReference($booking_reference): string
```

Get ticket XML by booking reference

Returns Epson ePOS ticket XML for a Merano booking by booking reference only. Backend looks up the booking via Merano. Use from a FlutterFlow API request (e.g. reprint from order view using purchase_ticket_reference).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$booking_reference = BK-123; // string | Merano booking number

try {
    $result = $apiInstance->getTicketXmlByReference($booking_reference);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->getTicketXmlByReference: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **booking_reference** | **string**| Merano booking number | |

### Return type

**string**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `text/xml`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listReceipts()`

```php
listReceipts($receipt_type, $pos_session_id, $charge_id, $printed, $from_date, $to_date, $per_page): \OpenAPIClient\Model\ListReceipts200Response
```

List receipts

Get paginated list of receipts for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
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

[**\OpenAPIClient\Model\ListReceipts200Response**](../Model/ListReceipts200Response.md)

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
markReceiptPrinted($id): \OpenAPIClient\Model\MarkReceiptPrinted200Response
```

Mark receipt as printed

Mark a receipt as printed

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
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

[**\OpenAPIClient\Model\MarkReceiptPrinted200Response**](../Model/MarkReceiptPrinted200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `printBookingTicket()`

```php
printBookingTicket($print_booking_ticket_request): string
```

Render booking ticket XML (full payload)

Renders Epson ePOS XML for Merano booking ticket printing. Client sends full ticket data (order_number, date, place, tickets, printer_id). Used by the POS custom action that has the booking result in memory.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$print_booking_ticket_request = new \OpenAPIClient\Model\PrintBookingTicketRequest(); // \OpenAPIClient\Model\PrintBookingTicketRequest

try {
    $result = $apiInstance->printBookingTicket($print_booking_ticket_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->printBookingTicket: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **print_booking_ticket_request** | [**\OpenAPIClient\Model\PrintBookingTicketRequest**](../Model/PrintBookingTicketRequest.md)|  | |

### Return type

**string**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `text/xml`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `printFreeTicket()`

```php
printFreeTicket($print_free_ticket_request): string
```

Render free ticket XML

Render Epson ePOS XML for free-ticket printing from the configured template.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$print_free_ticket_request = new \OpenAPIClient\Model\PrintFreeTicketRequest(); // \OpenAPIClient\Model\PrintFreeTicketRequest

try {
    $result = $apiInstance->printFreeTicket($print_free_ticket_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ReceiptsApi->printFreeTicket: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **print_free_ticket_request** | [**\OpenAPIClient\Model\PrintFreeTicketRequest**](../Model/PrintFreeTicketRequest.md)|  | |

### Return type

**string**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `text/xml`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `reprintReceipt()`

```php
reprintReceipt($id): \OpenAPIClient\Model\ReprintReceipt200Response
```

Reprint receipt

Reprint a receipt (increments reprint count)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\ReceiptsApi(
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

[**\OpenAPIClient\Model\ReprintReceipt200Response**](../Model/ReprintReceipt200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
