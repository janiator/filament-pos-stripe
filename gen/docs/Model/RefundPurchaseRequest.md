# # RefundPurchaseRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**amount** | **int** | Amount to refund in minor units (øre). If not provided, refunds the full remaining refundable amount. Must not exceed remaining refundable amount. | [optional]
**reason** | **string** | Optional reason for refund (for compliance tracking) | [optional]
**pos_device_id** | **int** | Optional: Current POS device ID (if you want to specify which device&#39;s session to use). If not provided and original session is closed, backend auto-detects current open session. For compliance: Refunds from closed sessions are tracked in the current open session. The original session totals remain unchanged per Kassasystemforskriften. | [optional]
**pos_session_id** | **int** | Optional: Current POS session ID (if you want to specify which session to use). Must be an open session. If not provided and original session is closed, backend auto-detects. | [optional]
**items** | [**\OpenAPI\Client\Model\RefundPurchaseRequestItemsInner[]**](RefundPurchaseRequestItemsInner.md) | Optional array of items being refunded (for item-level tracking) | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
