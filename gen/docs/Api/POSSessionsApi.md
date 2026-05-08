# OpenAPIClient\POSSessionsApi



All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**cashDeposit()**](POSSessionsApi.md#cashDeposit) | **POST** /pos-sessions/{id}/cash-deposit | Record cash deposit |
| [**cashWithdrawal()**](POSSessionsApi.md#cashWithdrawal) | **POST** /pos-sessions/{id}/cash-withdrawal | Record cash withdrawal |
| [**closePosSession()**](POSSessionsApi.md#closePosSession) | **POST** /pos-sessions/{id}/close | Close POS session |
| [**createDailyClosing()**](POSSessionsApi.md#createDailyClosing) | **POST** /pos-sessions/daily-closing | Create daily closing report |
| [**downloadXReportPdf()**](POSSessionsApi.md#downloadXReportPdf) | **GET** /pos-sessions/{id}/x-report/pdf | Download X-report as PDF |
| [**downloadZReportPdf()**](POSSessionsApi.md#downloadZReportPdf) | **GET** /pos-sessions/{id}/z-report/pdf | Download Z-report as PDF |
| [**generateXReport()**](POSSessionsApi.md#generateXReport) | **POST** /pos-sessions/{id}/x-report | Generate X-report |
| [**generateZReport()**](POSSessionsApi.md#generateZReport) | **POST** /pos-sessions/{id}/z-report | Generate Z-report |
| [**getCurrentPosSession()**](POSSessionsApi.md#getCurrentPosSession) | **GET** /pos-sessions/current | Get current open session |
| [**getPosSession()**](POSSessionsApi.md#getPosSession) | **GET** /pos-sessions/{id} | Get POS session |
| [**listPosSessions()**](POSSessionsApi.md#listPosSessions) | **GET** /pos-sessions | List POS sessions |
| [**openPosSession()**](POSSessionsApi.md#openPosSession) | **POST** /pos-sessions/open | Open POS session |


## `cashDeposit()`

```php
cashDeposit($id, $cash_deposit_request): \OpenAPIClient\Model\CashDeposit201Response
```

Record cash deposit

Log a cash deposit for an open session. Only allowed when session status is open.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$cash_deposit_request = new \OpenAPIClient\Model\CashDepositRequest(); // \OpenAPIClient\Model\CashDepositRequest

try {
    $result = $apiInstance->cashDeposit($id, $cash_deposit_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->cashDeposit: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **cash_deposit_request** | [**\OpenAPIClient\Model\CashDepositRequest**](../Model/CashDepositRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\CashDeposit201Response**](../Model/CashDeposit201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `cashWithdrawal()`

```php
cashWithdrawal($id, $cash_withdrawal_request): \OpenAPIClient\Model\CashWithdrawal201Response
```

Record cash withdrawal

Log a cash withdrawal for an open session. Only allowed when session status is open.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$cash_withdrawal_request = new \OpenAPIClient\Model\CashWithdrawalRequest(); // \OpenAPIClient\Model\CashWithdrawalRequest

try {
    $result = $apiInstance->cashWithdrawal($id, $cash_withdrawal_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->cashWithdrawal: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **cash_withdrawal_request** | [**\OpenAPIClient\Model\CashWithdrawalRequest**](../Model/CashWithdrawalRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\CashWithdrawal201Response**](../Model/CashWithdrawal201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `closePosSession()`

```php
closePosSession($id, $include_session_charges, $close_pos_session_request): \OpenAPIClient\Model\ClosePosSession200Response
```

Close POS session

Close a POS session and automatically generate Z-report. Automatically logs events 13009 (Z-report) and 13021 (Session closed)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$include_session_charges = false; // bool | When true, include full session_charges list in the returned session. Default false.
$close_pos_session_request = new \OpenAPIClient\Model\ClosePosSessionRequest(); // \OpenAPIClient\Model\ClosePosSessionRequest

try {
    $result = $apiInstance->closePosSession($id, $include_session_charges, $close_pos_session_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->closePosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **include_session_charges** | **bool**| When true, include full session_charges list in the returned session. Default false. | [optional] [default to false] |
| **close_pos_session_request** | [**\OpenAPIClient\Model\ClosePosSessionRequest**](../Model/ClosePosSessionRequest.md)|  | [optional] |

### Return type

[**\OpenAPIClient\Model\ClosePosSession200Response**](../Model/ClosePosSession200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `createDailyClosing()`

```php
createDailyClosing($create_daily_closing_request): \OpenAPIClient\Model\CreateDailyClosing201Response
```

Create daily closing report

Create a daily closing report for a specific date

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_daily_closing_request = new \OpenAPIClient\Model\CreateDailyClosingRequest(); // \OpenAPIClient\Model\CreateDailyClosingRequest

try {
    $result = $apiInstance->createDailyClosing($create_daily_closing_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->createDailyClosing: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_daily_closing_request** | [**\OpenAPIClient\Model\CreateDailyClosingRequest**](../Model/CreateDailyClosingRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\CreateDailyClosing201Response**](../Model/CreateDailyClosing201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `downloadXReportPdf()`

```php
downloadXReportPdf($id, $store): \SplFileObject
```

Download X-report as PDF

Generate and download the X-report for the given session as a PDF. Requires Bearer token. Store can be specified via query parameter `store` (slug) or header `X-Store-Slug`.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$store = 'store_example'; // string | Store slug (optional if user has current store or single store)

try {
    $result = $apiInstance->downloadXReportPdf($id, $store);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->downloadXReportPdf: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **store** | **string**| Store slug (optional if user has current store or single store) | [optional] |

### Return type

**\SplFileObject**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/pdf`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `downloadZReportPdf()`

```php
downloadZReportPdf($id, $store): \SplFileObject
```

Download Z-report as PDF

Generate and download the Z-report for the given session as a PDF. Requires Bearer token. Store can be specified via query parameter `store` (slug) or header `X-Store-Slug`.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$store = 'store_example'; // string | Store slug (optional if user has current store or single store)

try {
    $result = $apiInstance->downloadZReportPdf($id, $store);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->downloadZReportPdf: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **store** | **string**| Store slug (optional if user has current store or single store) | [optional] |

### Return type

**\SplFileObject**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/pdf`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `generateXReport()`

```php
generateXReport($id): \OpenAPIClient\Model\GenerateXReport200Response
```

Generate X-report

Generate X-report (daily sales report) for current session. Logs event 13008. Does NOT close session.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->generateXReport($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->generateXReport: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\GenerateXReport200Response**](../Model/GenerateXReport200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `generateZReport()`

```php
generateZReport($id, $include_session_charges, $generate_z_report_request): \OpenAPIClient\Model\GenerateZReport200Response
```

Generate Z-report

Generate Z-report (end-of-day report) and close session. Logs events 13009 and 13021.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$include_session_charges = false; // bool | When true, include full session_charges list in the returned session. Default false.
$generate_z_report_request = new \OpenAPIClient\Model\GenerateZReportRequest(); // \OpenAPIClient\Model\GenerateZReportRequest

try {
    $result = $apiInstance->generateZReport($id, $include_session_charges, $generate_z_report_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->generateZReport: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **include_session_charges** | **bool**| When true, include full session_charges list in the returned session. Default false. | [optional] [default to false] |
| **generate_z_report_request** | [**\OpenAPIClient\Model\GenerateZReportRequest**](../Model/GenerateZReportRequest.md)|  | [optional] |

### Return type

[**\OpenAPIClient\Model\GenerateZReport200Response**](../Model/GenerateZReport200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getCurrentPosSession()`

```php
getCurrentPosSession($pos_device_id, $include_session_charges): \OpenAPIClient\Model\PosSession
```

Get current open session

Get the current open session for a POS device. session_charges only present when include_session_charges=true.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$pos_device_id = 56; // int | POS device ID
$include_session_charges = false; // bool | When true, include full session_charges list. Default false to reduce payload size.

try {
    $result = $apiInstance->getCurrentPosSession($pos_device_id, $include_session_charges);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->getCurrentPosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **pos_device_id** | **int**| POS device ID | |
| **include_session_charges** | **bool**| When true, include full session_charges list. Default false to reduce payload size. | [optional] [default to false] |

### Return type

[**\OpenAPIClient\Model\PosSession**](../Model/PosSession.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getPosSession()`

```php
getPosSession($id, $include_session_charges): \OpenAPIClient\Model\GetPosSession200Response
```

Get POS session

Get a specific POS session. Use include_session_charges=true to include the full session_charges list (default false).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$include_session_charges = false; // bool | When true, include full session_charges list. Default false to reduce payload size.

try {
    $result = $apiInstance->getPosSession($id, $include_session_charges);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->getPosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **include_session_charges** | **bool**| When true, include full session_charges list. Default false to reduce payload size. | [optional] [default to false] |

### Return type

[**\OpenAPIClient\Model\GetPosSession200Response**](../Model/GetPosSession200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listPosSessions()`

```php
listPosSessions($status, $date, $pos_device_id, $per_page, $include_session_charges): \OpenAPIClient\Model\ListPosSessions200Response
```

List POS sessions

Get all POS sessions for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$status = 'status_example'; // string | Filter by status (open, closed, abandoned)
$date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter by opening date (YYYY-MM-DD)
$pos_device_id = 56; // int | Filter by POS device ID
$per_page = 20; // int | Number of items per page
$include_session_charges = false; // bool | When true, include full session_charges list in each session. Default false to reduce payload size.

try {
    $result = $apiInstance->listPosSessions($status, $date, $pos_device_id, $per_page, $include_session_charges);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->listPosSessions: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **status** | **string**| Filter by status (open, closed, abandoned) | [optional] |
| **date** | **\DateTime**| Filter by opening date (YYYY-MM-DD) | [optional] |
| **pos_device_id** | **int**| Filter by POS device ID | [optional] |
| **per_page** | **int**| Number of items per page | [optional] [default to 20] |
| **include_session_charges** | **bool**| When true, include full session_charges list in each session. Default false to reduce payload size. | [optional] [default to false] |

### Return type

[**\OpenAPIClient\Model\ListPosSessions200Response**](../Model/ListPosSessions200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `openPosSession()`

```php
openPosSession($open_pos_session_request, $include_session_charges): \OpenAPIClient\Model\OpenPosSession201Response
```

Open POS session

Open a new POS session. Automatically logs event 13020 (Session opened)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$open_pos_session_request = new \OpenAPIClient\Model\OpenPosSessionRequest(); // \OpenAPIClient\Model\OpenPosSessionRequest
$include_session_charges = false; // bool | When true, include full session_charges list in the returned session (e.g. on 409 conflict). Default false.

try {
    $result = $apiInstance->openPosSession($open_pos_session_request, $include_session_charges);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->openPosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **open_pos_session_request** | [**\OpenAPIClient\Model\OpenPosSessionRequest**](../Model/OpenPosSessionRequest.md)|  | |
| **include_session_charges** | **bool**| When true, include full session_charges list in the returned session (e.g. on 409 conflict). Default false. | [optional] [default to false] |

### Return type

[**\OpenAPIClient\Model\OpenPosSession201Response**](../Model/OpenPosSession201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
