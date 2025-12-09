# OpenAPI\Client\SAFTApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**downloadSafT()**](SAFTApi.md#downloadSafT) | **GET** /saf-t/download/{filename} | Download SAF-T file |
| [**generateSafT()**](SAFTApi.md#generateSafT) | **POST** /saf-t/generate | Generate SAF-T file |
| [**getSafTContent()**](SAFTApi.md#getSafTContent) | **GET** /saf-t/content | Get SAF-T XML content |


## `downloadSafT()`

```php
downloadSafT($filename): \SplFileObject
```

Download SAF-T file

Download a previously generated SAF-T file

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\SAFTApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$filename = SAF-T_store-slug_2025-11-01_2025-11-30.xml; // string

try {
    $result = $apiInstance->downloadSafT($filename);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SAFTApi->downloadSafT: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **filename** | **string**|  | |

### Return type

**\SplFileObject**

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/xml`, `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `generateSafT()`

```php
generateSafT($generate_saf_t_request): \OpenAPI\Client\Model\GenerateSafT201Response
```

Generate SAF-T file

Generate and store a SAF-T Cash Register file for a date range

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\SAFTApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$generate_saf_t_request = new \OpenAPI\Client\Model\GenerateSafTRequest(); // \OpenAPI\Client\Model\GenerateSafTRequest

try {
    $result = $apiInstance->generateSafT($generate_saf_t_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SAFTApi->generateSafT: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **generate_saf_t_request** | [**\OpenAPI\Client\Model\GenerateSafTRequest**](../Model/GenerateSafTRequest.md)|  | |

### Return type

[**\OpenAPI\Client\Model\GenerateSafT201Response**](../Model/GenerateSafT201Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `getSafTContent()`

```php
getSafTContent($from_date, $to_date): string
```

Get SAF-T XML content

Get SAF-T XML content directly (returns XML file)

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\SAFTApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$from_date = new \DateTime("2013-10-20T19:20:30+01:00"); // \DateTime | Start date (YYYY-MM-DD)
$to_date = new \DateTime("2013-10-20T19:20:30+01:00"); // \DateTime | End date (YYYY-MM-DD)

try {
    $result = $apiInstance->getSafTContent($from_date, $to_date);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling SAFTApi->getSafTContent: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **from_date** | **\DateTime**| Start date (YYYY-MM-DD) | |
| **to_date** | **\DateTime**| End date (YYYY-MM-DD) | |

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
