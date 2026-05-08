# OpenAPIClient\PowerOfficeApi

PowerOffice Go accounting integration (Z-report sync, onboarding). Server-side calls to PowerOffice Go API v2 use OAuth 2.0 client credentials (Bearer access token from the Token URL; see &#x60;POWEROFFICE_CLIENT_ID&#x60; + per-store client key + &#x60;POWEROFFICE_SUBSCRIPTION_KEY&#x60; in server env). Z-report ledger post defaults to &#x60;POST …/Vouchers/ManualJournals&#x60; (direct posting; Go UI “Direktepostere manuelle bilag”); use &#x60;…/JournalEntryVouchers/ManualJournals&#x60; via &#x60;POWEROFFICE_LEDGER_POST_PATH&#x60; for the journal-entry workflow. HTTP 403 often means wrong path vs privilege set, stale token after Go privilege changes, or missing voucher documentation privilege. Z-report payloads stored on closed POS sessions may include &#x60;by_payment_method_net&#x60; (per payment_method after refunds) for ledger routing; optional &#x60;stripe_fees_minor&#x60;, &#x60;payout_to_bank_minor&#x60;, and &#x60;gift_card_sales_minor&#x60; when present—positive values override backend defaults. The server merges fee and payout totals into &#x60;closing_data.z_report_data&#x60; when generating or serving a Z-report (from Stripe data synced in Filament) unless a positive override is already set. When those keys are absent or zero at ledger build time, the server may still fill fees and payouts from the same source (**Stripe gebyr og saldo** / **Stripe-utbetalinger**). Category-style splits use **product collection** IDs (not article groups).

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**powerofficeOnboardingCallback()**](PowerOfficeApi.md#powerofficeOnboardingCallback) | **POST** /poweroffice/onboarding/callback | PowerOffice onboarding server callback |
| [**powerofficeOnboardingInit()**](PowerOfficeApi.md#powerofficeOnboardingInit) | **POST** /poweroffice/onboarding/init | Start PowerOffice Go onboarding |
| [**powerofficeSyncRetry()**](PowerOfficeApi.md#powerofficeSyncRetry) | **POST** /poweroffice/sync/retry/{syncRun} | Retry a failed PowerOffice sync |
| [**powerofficeSyncZReport()**](PowerOfficeApi.md#powerofficeSyncZReport) | **POST** /poweroffice/sync/z-report/{posSession} | Queue Z-report sync to PowerOffice |


## `powerofficeOnboardingCallback()`

```php
powerofficeOnboardingCallback($poweroffice_onboarding_callback_request)
```

PowerOffice onboarding server callback

Optional server-to-server hook if PowerOffice posts back here after user approval. In v2, the browser is usually redirected to `POWEROFFICE_REDIRECT_URL` (or `/integrations/poweroffice/onboarding/redirect`) with `state` and `token` query parameters; that page finalizes onboarding via `POST …/Onboarding/Finalize`. Optional header `X-PowerOffice-Callback-Secret` when `POWEROFFICE_CALLBACK_SECRET` is set.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OpenAPIClient\Api\PowerOfficeApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$poweroffice_onboarding_callback_request = new \OpenAPIClient\Model\PowerofficeOnboardingCallbackRequest(); // \OpenAPIClient\Model\PowerofficeOnboardingCallbackRequest

try {
    $apiInstance->powerofficeOnboardingCallback($poweroffice_onboarding_callback_request);
} catch (Exception $e) {
    echo 'Exception when calling PowerOfficeApi->powerofficeOnboardingCallback: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **poweroffice_onboarding_callback_request** | [**\OpenAPIClient\Model\PowerofficeOnboardingCallbackRequest**](../Model/PowerofficeOnboardingCallbackRequest.md)|  | |

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `powerofficeOnboardingInit()`

```php
powerofficeOnboardingInit(): \OpenAPIClient\Model\PowerofficeOnboardingInit200Response
```

Start PowerOffice Go onboarding

Returns a URL where the store admin completes PowerOffice Go activation. Requires the **PowerOffice Go** add-on to be enabled for the store. The backend calls PowerOffice Go API v2 `POST …/Onboarding/Initiate` using `POWEROFFICE_CLIENT_ID` (Application Key) and `POWEROFFICE_SUBSCRIPTION_KEY` (Ocp-Apim-Subscription-Key); the response `TemporaryUrl` is returned as `onboarding_url`.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\PowerOfficeApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->powerofficeOnboardingInit();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PowerOfficeApi->powerofficeOnboardingInit: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**\OpenAPIClient\Model\PowerofficeOnboardingInit200Response**](../Model/PowerofficeOnboardingInit200Response.md)

### Authorization

[bearerAuth](../../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `powerofficeSyncRetry()`

```php
powerofficeSyncRetry($sync_run)
```

Retry a failed PowerOffice sync

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\PowerOfficeApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$sync_run = 56; // int

try {
    $apiInstance->powerofficeSyncRetry($sync_run);
} catch (Exception $e) {
    echo 'Exception when calling PowerOfficeApi->powerofficeSyncRetry: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **sync_run** | **int**|  | |

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

## `powerofficeSyncZReport()`

```php
powerofficeSyncZReport($pos_session): \OpenAPIClient\Model\PowerofficeSyncZReport200Response
```

Queue Z-report sync to PowerOffice

Queues a job to post the session Z-report snapshot to PowerOffice (manual / force sync).

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPIClient\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPIClient\Api\PowerOfficeApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$pos_session = 56; // int

try {
    $result = $apiInstance->powerofficeSyncZReport($pos_session);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling PowerOfficeApi->powerofficeSyncZReport: ', $e->getMessage(), PHP_EOL;
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
