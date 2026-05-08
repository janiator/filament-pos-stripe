# # PaymentMethod

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**store_id** | **int** |  | [optional]
**name** | **string** | Display name | [optional]
**code** | **string** | Internal code | [optional]
**provider** | **string** |  | [optional]
**provider_method** | **string** | Provider-specific method (e.g., \&quot;card_present\&quot; for Stripe) | [optional]
**enabled** | **bool** |  | [optional]
**pos_suitable** | **bool** | Whether this payment method is suitable for physical POS | [optional]
**sort_order** | **int** |  | [optional]
**minimum_amount_ore** | **int** | Minimum payment amount in øre (e.g. 5000 for 50 kr). Null means no minimum. | [optional]
**pos_device_ids** | **int[]** | When non-empty, this payment method is only available on these POS device IDs. When empty, available on all devices. | [optional]
**background_color** | **string** | Accent background color for the payment method button (CSS hex format with alpha at end, e.g., | [optional]
**icon_color** | **string** | Color for the payment method icon (hex, e.g., | [optional]
**saf_t_payment_code** | **string** | SAF-T payment code (PredefinedBasicID-12) | [optional]
**saf_t_event_code** | **string** | SAF-T event code (PredefinedBasicID-13) | [optional]
**description** | **string** |  | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
