# OpenAPI\Client\WebhooksApi

All URIs are relative to https://pos.visivo.no/api, except if the operation defines another base path.

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**stripeConnectWebhook()**](WebhooksApi.md#stripeConnectWebhook) | **POST** /stripe/connect/webhook | Stripe Connect webhook |


## `stripeConnectWebhook()`

```php
stripeConnectWebhook($body)
```

Stripe Connect webhook

Webhook endpoint for Stripe Connect events.  This endpoint receives webhooks from Stripe for connected account events. It handles events such as: - account.created, account.updated, account.deleted - customer.created, customer.updated, customer.deleted - charge.created, charge.updated, charge.refunded - subscription events - product and price events - payment method events - transfer events  **Note:** This endpoint does not require authentication as it uses Stripe signature verification.

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



$apiInstance = new OpenAPI\Client\Api\WebhooksApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$body = array('key' => new \stdClass); // object

try {
    $apiInstance->stripeConnectWebhook($body);
} catch (Exception $e) {
    echo 'Exception when calling WebhooksApi->stripeConnectWebhook: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **body** | **object**|  | |

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
