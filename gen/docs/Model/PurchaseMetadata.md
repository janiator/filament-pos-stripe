# # PurchaseMetadata

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**payment_intent_id** | **string** | Stripe payment intent ID (required for Stripe payments) | [optional]
**deferred_payment** | **bool** | Set to true to create a deferred payment (payment on pickup/later). Generates a delivery receipt per Kassasystemforskriften § 2-8-7. | [optional]
**deferred_reason** | **string** | Reason for deferred payment (e.g., \&quot;Payment on pickup\&quot;, \&quot;Dry cleaning\&quot;) | [optional]
**cashier_name** | **string** |  | [optional]
**device_id** | **int** |  | [optional]
**description** | **string** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
