# # PurchasePurchasePaymentsInner

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** | Payment/charge ID | [optional]
**stripe_charge_id** | **string** | Stripe charge ID | [optional]
**stripe_payment_intent_id** | **string** | Stripe payment intent ID | [optional]
**amount** | **int** | Payment amount in øre | [optional]
**amount_refunded** | **int** | Amount refunded in øre | [optional]
**currency** | **string** | Currency code | [optional]
**status** | **string** | Payment status | [optional]
**method** | **string** | Payment method code | [optional]
**code** | **string** | SAF-T payment code | [optional]
**transaction_code** | **string** | SAF-T transaction code | [optional]
**captured** | **bool** | Whether payment is captured | [optional]
**refunded** | **bool** | Whether payment is refunded | [optional]
**paid** | **bool** | Whether payment is paid | [optional]
**paid_at** | **\DateTime** | Payment date/time in Oslo timezone | [optional]
**tip_amount** | **int** | Tip amount in øre | [optional]
**application_fee_amount** | **int** | Application fee amount in øre | [optional]
**description** | **string** | Payment description | [optional]
**failure_code** | **string** | Failure code if payment failed | [optional]
**failure_message** | **string** | Failure message if payment failed | [optional]
**created_at** | **\DateTime** | Payment creation date/time in Oslo timezone | [optional]
**payment_method_id** | **string** | Stripe payment method ID (from payment intent) | [optional]
**capture_method** | **string** | Capture method (automatic or manual) | [optional]
**confirmation_method** | **string** | Confirmation method | [optional]
**receipt_email** | **string** | Receipt email address | [optional]
**statement_descriptor** | **string** | Statement descriptor | [optional]
**succeeded_at** | **\DateTime** | Payment intent succeeded date/time in Oslo timezone | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
