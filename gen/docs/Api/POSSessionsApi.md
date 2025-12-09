# OpenAPI\Client\POSSessionsApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**closePosSession()**](POSSessionsApi.md#closePosSession) | **POST** /pos-sessions/{id}/close | Close POS session |
| [**createDailyClosing()**](POSSessionsApi.md#createDailyClosing) | **POST** /pos-sessions/daily-closing | Create daily closing report |
| [**generateXReport()**](POSSessionsApi.md#generateXReport) | **POST** /pos-sessions/{id}/x-report | Generate X-report |
| [**generateZReport()**](POSSessionsApi.md#generateZReport) | **POST** /pos-sessions/{id}/z-report | Generate Z-report |
| [**getCurrentPosSession()**](POSSessionsApi.md#getCurrentPosSession) | **GET** /pos-sessions/current | Get current open session |
| [**getPosSession()**](POSSessionsApi.md#getPosSession) | **GET** /pos-sessions/{id} | Get POS session |
| [**listPosSessions()**](POSSessionsApi.md#listPosSessions) | **GET** /pos-sessions | List POS sessions |
| [**openPosSession()**](POSSessionsApi.md#openPosSession) | **POST** /pos-sessions/open | Open POS session |


## `closePosSession()`

```php
closePosSession($id, $close_pos_session_request): \OpenAPI\Client\Model\ClosePosSession200Response
```

Close POS session

Close a POS session and automatically generate Z-report. Automatically logs events 13009 (Z-report) and 13021 (Session closed)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$close_pos_session_request = new \OpenAPI\Client\Model\ClosePosSessionRequest(); // \OpenAPI\Client\Model\ClosePosSessionRequest

try {
    $result = $apiInstance->closePosSession($id, $close_pos_session_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->closePosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **close_pos_session_request** | [**\OpenAPI\Client\Model\ClosePosSessionRequest**](../Model/ClosePosSessionRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\ClosePosSession200Response**](../Model/ClosePosSession200Response.md)

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
createDailyClosing($create_daily_closing_request): \OpenAPI\Client\Model\CreateDailyClosing201Response
```

Create daily closing report

Create a daily closing report for a specific date

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_daily_closing_request = new \OpenAPI\Client\Model\CreateDailyClosingRequest(); // \OpenAPI\Client\Model\CreateDailyClosingRequest

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
| **create_daily_closing_request** | [**\OpenAPI\Client\Model\CreateDailyClosingRequest**](../Model/CreateDailyClosingRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\CreateDailyClosing201Response**](../Model/CreateDailyClosing201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `generateXReport()`

```php
generateXReport($id): \OpenAPI\Client\Model\GenerateXReport200Response
```

Generate X-report

Generate X-report (daily sales report) for current session. Logs event 13008. Does NOT close session.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
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

[**\OpenAPI\Client\Model\GenerateXReport200Response**](../Model/GenerateXReport200Response.md)

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
generateZReport($id, $generate_z_report_request): \OpenAPI\Client\Model\GenerateZReport200Response
```

Generate Z-report

Generate Z-report (end-of-day report) and close session. Logs events 13009 and 13021.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$generate_z_report_request = new \OpenAPI\Client\Model\GenerateZReportRequest(); // \OpenAPI\Client\Model\GenerateZReportRequest

try {
    $result = $apiInstance->generateZReport($id, $generate_z_report_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->generateZReport: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **generate_z_report_request** | [**\OpenAPI\Client\Model\GenerateZReportRequest**](../Model/GenerateZReportRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\GenerateZReport200Response**](../Model/GenerateZReport200Response.md)

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
getCurrentPosSession($pos_device_id): \OpenAPI\Client\Model\PosSessionWithCharges
```

Get current open session

Get the current open session for a POS device

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$pos_device_id = 56; // int | POS device ID

try {
    $result = $apiInstance->getCurrentPosSession($pos_device_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->getCurrentPosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **pos_device_id** | **int**| POS device ID | |

### Return type

[**\OpenAPI\Client\Model\PosSessionWithCharges**](../Model/PosSessionWithCharges.md)

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
getPosSession($id): \OpenAPI\Client\Model\GetPosSession200Response
```

Get POS session

Get a specific POS session with all details including charges

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getPosSession($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->getPosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\GetPosSession200Response**](../Model/GetPosSession200Response.md)

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
listPosSessions($status, $date, $pos_device_id, $per_page): \OpenAPI\Client\Model\ListPosSessions200Response
```

List POS sessions

Get all POS sessions for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$status = 'status_example'; // string | Filter by status (open, closed, abandoned)
$date = new \DateTime("2013-10-20T19:20:30+01:00"); // \DateTime | Filter by opening date (YYYY-MM-DD)
$pos_device_id = 56; // int | Filter by POS device ID
$per_page = 20; // int | Number of items per page

try {
    $result = $apiInstance->listPosSessions($status, $date, $pos_device_id, $per_page);
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

### Return type

[**\OpenAPI\Client\Model\ListPosSessions200Response**](../Model/ListPosSessions200Response.md)

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
openPosSession($open_pos_session_request): \OpenAPI\Client\Model\OpenPosSession201Response
```

Open POS session

Open a new POS session. Automatically logs event 13020 (Session opened)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSSessionsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$open_pos_session_request = new \OpenAPI\Client\Model\OpenPosSessionRequest(); // \OpenAPI\Client\Model\OpenPosSessionRequest

try {
    $result = $apiInstance->openPosSession($open_pos_session_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSSessionsApi->openPosSession: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **open_pos_session_request** | [**\OpenAPI\Client\Model\OpenPosSessionRequest**](../Model/OpenPosSessionRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\OpenPosSession201Response**](../Model/OpenPosSession201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
