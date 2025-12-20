# # Purchase

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**id** | **int** |  | [optional]
**stripe_charge_id** | **string** |  | [optional]
**amount** | **int** | Amount in øre | [optional]
**amount_refunded** | **int** | Amount refunded in øre | [optional]
**currency** | **string** |  | [optional]
**status** | **string** |  | [optional]
**payment_method** | **string** |  | [optional]
**description** | **string** |  | [optional]
**failure_code** | **string** |  | [optional]
**failure_message** | **string** |  | [optional]
**captured** | **bool** |  | [optional]
**refunded** | **bool** |  | [optional]
**paid** | **bool** |  | [optional]
**paid_at** | **\DateTime** |  | [optional]
**charge_type** | **string** |  | [optional]
**application_fee_amount** | **int** | Application fee in øre | [optional]
**tip_amount** | **int** | Tip amount in øre | [optional]
**transaction_code** | **string** | SAF-T transaction code (PredefinedBasicID-11) | [optional]
**payment_code** | **string** | SAF-T payment code (PredefinedBasicID-12) | [optional]
**article_group_code** | **string** | SAF-T article group code (PredefinedBasicID-04) | [optional]
**purchase_metadata** | **array<string,mixed>** | Additional metadata (cashier_name, device_id, payment_intent_id, etc.) | [optional]
**outcome** | **array<string,mixed>** | Payment outcome (for Stripe payments) | [optional]
**created_at** | **\DateTime** |  | [optional]
**updated_at** | **\DateTime** |  | [optional]
**purchase_session** | [**\OpenAPI\Client\Model\PurchasePurchaseSession**](PurchasePurchaseSession.md) |  | [optional]
**purchase_store** | [**\OpenAPI\Client\Model\PurchasePurchaseStore**](PurchasePurchaseStore.md) |  | [optional]
**purchase_receipt** | [**\OpenAPI\Client\Model\PurchasePurchaseReceipt**](PurchasePurchaseReceipt.md) |  | [optional]
**purchase_customer** | [**\OpenAPI\Client\Model\PurchasePurchaseCustomer**](PurchasePurchaseCustomer.md) |  | [optional]
**purchase_items** | [**\OpenAPI\Client\Model\PurchasePurchaseItemsInner[]**](PurchasePurchaseItemsInner.md) | Array of purchased items/products with enriched product information | [optional]
**purchase_discounts** | [**\OpenAPI\Client\Model\PurchasePurchaseDiscountsInner[]**](PurchasePurchaseDiscountsInner.md) | Array of cart-level discounts | [optional]
**purchase_subtotal** | **int** | Subtotal in øre (before discounts and tax) | [optional]
**purchase_total_discounts** | **int** | Total discount amount in øre | [optional]
**purchase_total_tax** | **int** | Total tax amount in øre | [optional]
**purchase_tip_amount** | **int** | Tip amount in øre | [optional]
**purchase_note** | **string** | Optional note/comment for the purchase | [optional]
**purchase_payments** | [**\OpenAPI\Client\Model\PurchasePurchasePaymentsInner[]**](PurchasePurchasePaymentsInner.md) | List of payments connected to this purchase (usually one payment per purchase) | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
