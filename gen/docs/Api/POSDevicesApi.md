# OpenAPI\Client\POSDevicesApi



All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**closeCashDrawer()**](POSDevicesApi.md#closeCashDrawer) | **POST** /pos-devices/{id}/cash-drawer/close | Close cash drawer |
| [**getPosDevice()**](POSDevicesApi.md#getPosDevice) | **GET** /pos-devices/{id} | Get POS device |
| [**listPosDevices()**](POSDevicesApi.md#listPosDevices) | **GET** /pos-devices | List POS devices |
| [**logApplicationShutdown()**](POSDevicesApi.md#logApplicationShutdown) | **POST** /pos-devices/{id}/shutdown | Log application shutdown |
| [**logApplicationStart()**](POSDevicesApi.md#logApplicationStart) | **POST** /pos-devices/{id}/start | Log application start |
| [**openCashDrawer()**](POSDevicesApi.md#openCashDrawer) | **POST** /pos-devices/{id}/cash-drawer/open | Open cash drawer |
| [**patchPosDevice()**](POSDevicesApi.md#patchPosDevice) | **PATCH** /pos-devices/{id} | Update POS device (partial) |
| [**registerPosDevice()**](POSDevicesApi.md#registerPosDevice) | **POST** /pos-devices | Register POS device |
| [**updateDeviceHeartbeat()**](POSDevicesApi.md#updateDeviceHeartbeat) | **POST** /pos-devices/{id}/heartbeat | Update device heartbeat |
| [**updatePosDevice()**](POSDevicesApi.md#updatePosDevice) | **PUT** /pos-devices/{id} | Update POS device |


## `closeCashDrawer()`

```php
closeCashDrawer($id, $close_cash_drawer_request): \OpenAPI\Client\Model\CloseCashDrawer200Response
```

Close cash drawer

Log cash drawer close event (13006) for audit trail compliance

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$close_cash_drawer_request = new \OpenAPI\Client\Model\CloseCashDrawerRequest(); // \OpenAPI\Client\Model\CloseCashDrawerRequest

try {
    $result = $apiInstance->closeCashDrawer($id, $close_cash_drawer_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->closeCashDrawer: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **close_cash_drawer_request** | [**\OpenAPI\Client\Model\CloseCashDrawerRequest**](../Model/CloseCashDrawerRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\CloseCashDrawer200Response**](../Model/CloseCashDrawer200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getPosDevice()`

```php
getPosDevice($id): \OpenAPI\Client\Model\GetPosDevice200Response
```

Get POS device

Get a specific POS device by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getPosDevice($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->getPosDevice: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\GetPosDevice200Response**](../Model/GetPosDevice200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listPosDevices()`

```php
listPosDevices(): \OpenAPI\Client\Model\ListPosDevices200Response
```

List POS devices

Get all POS devices for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->listPosDevices();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->listPosDevices: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPI\Client\Model\ListPosDevices200Response**](../Model/ListPosDevices200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `logApplicationShutdown()`

```php
logApplicationShutdown($id): \OpenAPI\Client\Model\LogApplicationShutdown200Response
```

Log application shutdown

Log POS application shutdown event (13002) for audit trail compliance

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->logApplicationShutdown($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->logApplicationShutdown: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\LogApplicationShutdown200Response**](../Model/LogApplicationShutdown200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `logApplicationStart()`

```php
logApplicationStart($id): \OpenAPI\Client\Model\LogApplicationStart200Response
```

Log application start

Log POS application start event (13001) for audit trail compliance

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->logApplicationStart($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->logApplicationStart: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\LogApplicationStart200Response**](../Model/LogApplicationStart200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `openCashDrawer()`

```php
openCashDrawer($id, $open_cash_drawer_request): \OpenAPI\Client\Model\OpenCashDrawer200Response
```

Open cash drawer

Log cash drawer open event (13005). Supports nullinnslag (drawer open without sale) - mandatory per Kassasystemforskriften § 2-2

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$open_cash_drawer_request = new \OpenAPI\Client\Model\OpenCashDrawerRequest(); // \OpenAPI\Client\Model\OpenCashDrawerRequest

try {
    $result = $apiInstance->openCashDrawer($id, $open_cash_drawer_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->openCashDrawer: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **open_cash_drawer_request** | [**\OpenAPI\Client\Model\OpenCashDrawerRequest**](../Model/OpenCashDrawerRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\OpenCashDrawer200Response**](../Model/OpenCashDrawer200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `patchPosDevice()`

```php
patchPosDevice($id, $patch_pos_device_request): \OpenAPI\Client\Model\UpdatePosDevice200Response
```

Update POS device (partial)

Partially update POS device information

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$patch_pos_device_request = new \OpenAPI\Client\Model\PatchPosDeviceRequest(); // \OpenAPI\Client\Model\PatchPosDeviceRequest

try {
    $result = $apiInstance->patchPosDevice($id, $patch_pos_device_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->patchPosDevice: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **patch_pos_device_request** | [**\OpenAPI\Client\Model\PatchPosDeviceRequest**](../Model/PatchPosDeviceRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\UpdatePosDevice200Response**](../Model/UpdatePosDevice200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `registerPosDevice()`

```php
registerPosDevice($register_pos_device_request): \OpenAPI\Client\Model\RegisterPosDevice201Response
```

Register POS device

Register a new POS device using device information from device_info_plus

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$register_pos_device_request = new \OpenAPI\Client\Model\RegisterPosDeviceRequest(); // \OpenAPI\Client\Model\RegisterPosDeviceRequest

try {
    $result = $apiInstance->registerPosDevice($register_pos_device_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->registerPosDevice: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **register_pos_device_request** | [**\OpenAPI\Client\Model\RegisterPosDeviceRequest**](../Model/RegisterPosDeviceRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\RegisterPosDevice201Response**](../Model/RegisterPosDevice201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updateDeviceHeartbeat()`

```php
updateDeviceHeartbeat($id, $update_device_heartbeat_request): \OpenAPI\Client\Model\UpdateDeviceHeartbeat200Response
```

Update device heartbeat

Update device last_seen_at timestamp and optionally status/metadata

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$update_device_heartbeat_request = new \OpenAPI\Client\Model\UpdateDeviceHeartbeatRequest(); // \OpenAPI\Client\Model\UpdateDeviceHeartbeatRequest

try {
    $result = $apiInstance->updateDeviceHeartbeat($id, $update_device_heartbeat_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->updateDeviceHeartbeat: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **update_device_heartbeat_request** | [**\OpenAPI\Client\Model\UpdateDeviceHeartbeatRequest**](../Model/UpdateDeviceHeartbeatRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\UpdateDeviceHeartbeat200Response**](../Model/UpdateDeviceHeartbeat200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `updatePosDevice()`

```php
updatePosDevice($id, $update_pos_device_request): \OpenAPI\Client\Model\UpdatePosDevice200Response
```

Update POS device

Update POS device information

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSDevicesApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int
$update_pos_device_request = new \OpenAPI\Client\Model\UpdatePosDeviceRequest(); // \OpenAPI\Client\Model\UpdatePosDeviceRequest

try {
    $result = $apiInstance->updatePosDevice($id, $update_pos_device_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSDevicesApi->updatePosDevice: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |
| **update_pos_device_request** | [**\OpenAPI\Client\Model\UpdatePosDeviceRequest**](../Model/UpdatePosDeviceRequest.md)|  | [optional] |

### Return type

[**\OpenAPI\Client\Model\UpdatePosDevice200Response**](../Model/UpdatePosDevice200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
