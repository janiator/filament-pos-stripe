# OpenAPIClient\TripletexApi

Tripletex accounting integration: posts **draft** ledger vouchers (&#x60;sendToLedger&#x60; follows &#x60;TRIPLETEX_SEND_TO_LEDGER&#x60;). Per-store **consumer** and **employee** tokens are stored encrypted; the server exchanges them for a short-lived session token before account lookup and voucher POST. Z-report vouchers carry sales/VAT/tips/payment clearing; optional settlement lines on the Z voucher are controlled by &#x60;z_report_include_settlement&#x60; (default off) to avoid double-posting when **payout vouchers** also move bank/clearing/fee. Payout vouchers debit bank, credit Stripe clearing, and optionally fee expense/clearing from mirrored balance transactions (&#x60;stripe_payout_id&#x60; on &#x60;store_stripe_balance_transactions&#x60;). See &#x60;docs/accounting/TRIPLETEX_INTEGRATION.md&#x60;.

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**tripletexPreviewPayout()**](TripletexApi.md#tripletexPreviewPayout) | **GET** /tripletex/preview/payout/{payout} | Preview Tripletex payout voucher lines |
| [**tripletexPreviewZReport()**](TripletexApi.md#tripletexPreviewZReport) | **GET** /tripletex/preview/z-report/{posSession} | Preview Tripletex Z-report voucher lines |
| [**tripletexSyncHistorical()**](TripletexApi.md#tripletexSyncHistorical) | **POST** /tripletex/sync/historical | Queue historical Tripletex sync jobs |
| [**tripletexSyncPayout()**](TripletexApi.md#tripletexSyncPayout) | **POST** /tripletex/sync/payout/{payout} | Queue Stripe payout voucher sync to Tripletex |
| [**tripletexSyncRetry()**](TripletexApi.md#tripletexSyncRetry) | **POST** /tripletex/sync/retry/{syncRun} | Retry a failed or skipped Tripletex sync run |
| [**tripletexSyncZReport()**](TripletexApi.md#tripletexSyncZReport) | **POST** /tripletex/sync/z-report/{posSession} | Queue Z-report sync to Tripletex |


## `tripletexPreviewPayout()`

```php
tripletexPreviewPayout($payout, $resolve_accounts)
```

Preview Tripletex payout voucher lines

Same response shape as Z-report preview (`lines`, `lines_display` with optional `posting_date`/`line_kind`, optional resolved `tripletex_voucher_payload` / `tripletex_postings_display` with optional `date` per posting). Payout must be `paid`. Read-only; does not require `sync_enabled`.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TripletexApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$payout = 56; // int
$resolve_accounts = false; // bool

try {
    $apiInstance->tripletexPreviewPayout($payout, $resolve_accounts);
} catch (Exception $e) {
    echo 'Exception when calling TripletexApi->tripletexPreviewPayout: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **payout** | **int**|  | |
| **resolve_accounts** | **bool**|  | [optional] [default to false] |

### Return type

void (empty response body)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `tripletexPreviewZReport()`

```php
tripletexPreviewZReport($pos_session, $resolve_accounts)
```

Preview Tripletex Z-report voucher lines

Returns a voucher preview without posting. On success: `lines` (minor units), `lines_display` (same rows with debit/credit as decimal major units; optional `posting_date` and `line_kind` when present on a line), `document_date`, `description`, `balanced`, and totals. Set `resolve_accounts=true` to call Tripletex (session + ledger/account lookups) and include `tripletex_voucher_payload` (exact draft JSON for POST /ledger/voucher), `tripletex_postings_display` (readable posting rows, each with optional `date`), or `resolve_error` on failure. Does not require `sync_enabled` on the integration (read-only).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TripletexApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$pos_session = 56; // int
$resolve_accounts = false; // bool

try {
    $apiInstance->tripletexPreviewZReport($pos_session, $resolve_accounts);
} catch (Exception $e) {
    echo 'Exception when calling TripletexApi->tripletexPreviewZReport: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **pos_session** | **int**|  | |
| **resolve_accounts** | **bool**|  | [optional] [default to false] |

### Return type

void (empty response body)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `tripletexSyncHistorical()`

```php
tripletexSyncHistorical($tripletex_sync_historical_request): \OpenAPIClient\Model\TripletexSyncHistorical200Response
```

Queue historical Tripletex sync jobs

Queues up to `limit` jobs for Z-reports or paid payouts in optional date windows. Requires `sync_enabled` on the integration (same as other sync routes).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TripletexApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$tripletex_sync_historical_request = new \OpenAPIClient\Model\TripletexSyncHistoricalRequest(); // \OpenAPIClient\Model\TripletexSyncHistoricalRequest

try {
    $result = $apiInstance->tripletexSyncHistorical($tripletex_sync_historical_request);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TripletexApi->tripletexSyncHistorical: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **tripletex_sync_historical_request** | [**\OpenAPIClient\Model\TripletexSyncHistoricalRequest**](../Model/TripletexSyncHistoricalRequest.md)|  | |

### Return type

[**\OpenAPIClient\Model\TripletexSyncHistorical200Response**](../Model/TripletexSyncHistorical200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `tripletexSyncPayout()`

```php
tripletexSyncPayout($payout): \OpenAPIClient\Model\TripletexSyncPayout200Response
```

Queue Stripe payout voucher sync to Tripletex

Queues a job to post a voucher for a **paid** mirrored Stripe payout (`store_stripe_payouts` row id).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TripletexApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$payout = 56; // int | Primary key of `store_stripe_payouts` for this store

try {
    $result = $apiInstance->tripletexSyncPayout($payout);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TripletexApi->tripletexSyncPayout: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **payout** | **int**| Primary key of &#x60;store_stripe_payouts&#x60; for this store | |

### Return type

[**\OpenAPIClient\Model\TripletexSyncPayout200Response**](../Model/TripletexSyncPayout200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `tripletexSyncRetry()`

```php
tripletexSyncRetry($sync_run): \OpenAPIClient\Model\TripletexSyncRetry200Response
```

Retry a failed or skipped Tripletex sync run

Re-queues Z-report or payout sync based on the run's `sync_type` (same as Filament **Retry** for failed or skipped rows).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TripletexApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$sync_run = 56; // int

try {
    $result = $apiInstance->tripletexSyncRetry($sync_run);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TripletexApi->tripletexSyncRetry: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **sync_run** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\TripletexSyncRetry200Response**](../Model/TripletexSyncRetry200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `tripletexSyncZReport()`

```php
tripletexSyncZReport($pos_session): \OpenAPIClient\Model\PowerofficeSyncZReport200Response
```

Queue Z-report sync to Tripletex

Queues a job to post the session Z-report snapshot to Tripletex (manual / force sync).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\TripletexApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$pos_session = 56; // int

try {
    $result = $apiInstance->tripletexSyncZReport($pos_session);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling TripletexApi->tripletexSyncZReport: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **pos_session** | **int**|  | |

### Return type

[**\OpenAPIClient\Model\PowerofficeSyncZReport200Response**](../Model/PowerofficeSyncZReport200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
