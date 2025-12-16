# # RefundPurchase200ResponseDataCharge

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**stripe_charge_id** | **string** |  | [optional]
**amount** | **int** | Original purchase amount in minor units (øre) | [optional]
**amount_refunded** | **int** | Total amount refunded so far in minor units (øre) | [optional]
**currency** | **string** |  | [optional]
**status** | **string** | Purchase status (will be &#39;refunded&#39; if fully refunded) | [optional]
**refunded** | **bool** | Whether purchase is fully refunded | [optional]
**payment_method** | **string** |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
