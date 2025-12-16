# # CompletePurchasePaymentRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**payment_method_code** | **string** | Code of the payment method (e.g., \&quot;cash\&quot;, \&quot;card\&quot;, \&quot;card_present\&quot;) |
**pos_session_id** | **int** | Optional POS session ID to use for completing the payment.  If not provided, the system will: 1. Use the current active session for the provided pos_device_id (if pos_device_id is provided) 2. Fall back to the original session from when the deferred payment was created This allows completing deferred payments on different devices/sessions (e.g., customer returns to a different register). The session must be open and belong to the same store as the charge. **Compliance:** For proper audit trail, it&#39;s recommended to provide pos_device_id to use the current active session. | [optional]
**pos_device_id** | **int** | Optional POS device ID. If provided and pos_session_id is not provided,  the system will automatically use the current active open session for this device. This ensures compliance by defaulting to the session the user is currently signed in to. **Compliance:** Recommended to provide this parameter to ensure proper tracking and audit trail. | [optional]
**metadata** | **array<string,mixed>** | Payment-specific metadata (e.g., payment_intent_id for Stripe) | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
