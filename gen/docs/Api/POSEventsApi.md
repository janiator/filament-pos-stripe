# OpenAPI\Client\POSEventsApi



All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**createPosEvent()**](POSEventsApi.md#createPosEvent) | **POST** /pos-events | Create POS event |
| [**getPosEvent()**](POSEventsApi.md#getPosEvent) | **GET** /pos-events/{id} | Get POS event |
| [**listPosEvents()**](POSEventsApi.md#listPosEvents) | **GET** /pos-events | List POS events |


## `createPosEvent()`

```php
createPosEvent($create_pos_event_request): \OpenAPI\Client\Model\CreatePosEvent201Response
```

Create POS event

Manually create a POS event (for events not auto-logged)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSEventsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$create_pos_event_request = new \OpenAPI\Client\Model\CreatePosEventRequest(); // \OpenAPI\Client\Model\CreatePosEventRequest

try {
    $result = $apiInstance->createPosEvent($create_pos_event_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSEventsApi->createPosEvent: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **create_pos_event_request** | [**\OpenAPI\Client\Model\CreatePosEventRequest**](../Model/CreatePosEventRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\CreatePosEvent201Response**](../Model/CreatePosEvent201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getPosEvent()`

```php
getPosEvent($id): \OpenAPI\Client\Model\GetPosEvent200Response
```

Get POS event

Get a specific POS event by ID

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSEventsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$id = 56; // int

try {
    $result = $apiInstance->getPosEvent($id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSEventsApi->getPosEvent: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **id** | **int**|  | |

### Return type

[**\OpenAPI\Client\Model\GetPosEvent200Response**](../Model/GetPosEvent200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `listPosEvents()`

```php
listPosEvents($event_code, $event_type, $pos_session_id, $from_date, $to_date, $per_page, $nullinnslag): \OpenAPI\Client\Model\ListPosEvents200Response
```

List POS events

Get paginated list of POS events (audit log) for the current store

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\POSEventsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$event_code = 'event_code_example'; // string | Filter by event code (e.g., 13012, 13020)
$event_type = 'event_type_example'; // string | Filter by event type
$pos_session_id = 56; // int | Filter by POS session ID
$from_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter from date (YYYY-MM-DD)
$to_date = new \DateTime('2013-10-20T19:20:30+01:00'); // \DateTime | Filter to date (YYYY-MM-DD)
$per_page = 50; // int | Number of items per page
$nullinnslag = True; // bool | Filter by nullinnslag (drawer open without sale). Only applies to cash drawer events (13005). Use true to get only nullinnslag events, false to exclude them.

try {
    $result = $apiInstance->listPosEvents($event_code, $event_type, $pos_session_id, $from_date, $to_date, $per_page, $nullinnslag);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling POSEventsApi->listPosEvents: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **event_code** | **string**| Filter by event code (e.g., 13012, 13020) | [optional] |
| **event_type** | **string**| Filter by event type | [optional] |
| **pos_session_id** | **int**| Filter by POS session ID | [optional] |
| **from_date** | **\DateTime**| Filter from date (YYYY-MM-DD) | [optional] |
| **to_date** | **\DateTime**| Filter to date (YYYY-MM-DD) | [optional] |
| **per_page** | **int**| Number of items per page | [optional] [default to 50] |
| **nullinnslag** | **bool**| Filter by nullinnslag (drawer open without sale). Only applies to cash drawer events (13005). Use true to get only nullinnslag events, false to exclude them. | [optional] |

### Return type

[**\OpenAPI\Client\Model\ListPosEvents200Response**](../Model/ListPosEvents200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
