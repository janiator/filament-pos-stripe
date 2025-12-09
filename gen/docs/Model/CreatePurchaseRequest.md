# # CreatePurchaseRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**pos_session_id** | **int** | ID of the active POS session |
**payment_method_code** | **string** | Code of the payment method (e.g., \&quot;cash\&quot;, \&quot;card\&quot;, \&quot;card_present\&quot;) |
**cart** | [**\OpenAPI\Client\Model\PurchaseCart**](PurchaseCart.md) |  |
**metadata** | [**\OpenAPI\Client\Model\PurchaseMetadata**](PurchaseMetadata.md) |  | [optional]
**payments** | [**\OpenAPI\Client\Model\PurchaseSplitPaymentRequestPaymentsInner[]**](PurchaseSplitPaymentRequestPaymentsInner.md) | Array of payment methods and amounts |

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
