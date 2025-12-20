# # CreateCustomerRequest

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**name** | **string** |  |
**email** | **string** | Customer email address (optional - some older customers may not have an email) | [optional]
**phone** | **string** | Customer phone number (per Stripe API spec) |
**profile_image_url** | **string** | URL to the customer profile image | [optional]
**customer_address** | [**\OpenAPI\Client\Model\CreateCustomerRequestCustomerAddress**](CreateCustomerRequestCustomerAddress.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
