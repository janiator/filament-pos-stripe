# OpenAPIClient

API for managing Stripe Connect integration for POS systems.

This API provides endpoints for:
- User authentication and authorization
- Store management
- Customer management
- POS device registration and management
- POS session management (Kassasystemforskriften compliance)
- POS event logging (audit trail)
- POS transaction operations (void, correction)
- Receipt generation and management
- Receipt printer configuration and management
- Product and inventory management
- SAF-T file generation (Norwegian tax compliance)
- Terminal operations (connection tokens and payment intents)

All endpoints (except login and webhooks) require Bearer token authentication.
Requests are automatically scoped to the authenticated user's accessible stores.



## Installation & Usage

### Requirements

PHP 8.1 and later.

### Composer

To install the bindings via [Composer](https://getcomposer.org/), add the following to `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/GIT_USER_ID/GIT_REPO_ID.git"
    }
  ],
  "require": {
    "GIT_USER_ID/GIT_REPO_ID": "*@dev"
  }
}
```

Then run `composer install`

### Manual Installation

Download the files and include `autoload.php`:

```php
<?php
require_once('/path/to/OpenAPIClient/vendor/autoload.php');
```

## Getting Started

Please follow the [installation procedure](#installation--usage) and then run the following:

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



// Configure Bearer (JWT) authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\AuthenticationApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);

try {
    $result = $apiInstance->getCurrentUser();
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling AuthenticationApi->getCurrentUser: ', $e->getMessage(), PHP_EOL;
}

```

## API Endpoints

All URIs are relative to *https://pos.visivo.no/api*

Class | Method | HTTP request | Description
------------ | ------------- | ------------- | -------------
*AuthenticationApi* | [**getCurrentUser**](docs/Api/AuthenticationApi.md#getcurrentuser) | **GET** /auth/me | Get current user
*AuthenticationApi* | [**login**](docs/Api/AuthenticationApi.md#login) | **POST** /auth/login | Login user
*AuthenticationApi* | [**logout**](docs/Api/AuthenticationApi.md#logout) | **POST** /auth/logout | Logout user
*AuthenticationApi* | [**logoutAll**](docs/Api/AuthenticationApi.md#logoutall) | **POST** /auth/logout-all | Logout from all devices
*CollectionsApi* | [**getCollection**](docs/Api/CollectionsApi.md#getcollection) | **GET** /collections/{id} | Get collection
*CollectionsApi* | [**listCollections**](docs/Api/CollectionsApi.md#listcollections) | **GET** /collections | List collections
*CustomersApi* | [**createCustomer**](docs/Api/CustomersApi.md#createcustomer) | **POST** /customers | Create customer
*CustomersApi* | [**deleteCustomer**](docs/Api/CustomersApi.md#deletecustomer) | **DELETE** /customers/{id} | Delete customer
*CustomersApi* | [**getCustomer**](docs/Api/CustomersApi.md#getcustomer) | **GET** /customers/{id} | Get customer
*CustomersApi* | [**listCustomers**](docs/Api/CustomersApi.md#listcustomers) | **GET** /customers | List customers
*CustomersApi* | [**updateCustomer**](docs/Api/CustomersApi.md#updatecustomer) | **PUT** /customers/{id} | Update customer
*InventoryApi* | [**adjustInventory**](docs/Api/InventoryApi.md#adjustinventory) | **POST** /variants/{variant}/inventory/adjust | Adjust inventory
*InventoryApi* | [**bulkUpdateInventory**](docs/Api/InventoryApi.md#bulkupdateinventory) | **POST** /inventory/bulk-update | Bulk update inventory
*InventoryApi* | [**getProductInventory**](docs/Api/InventoryApi.md#getproductinventory) | **GET** /products/{product}/inventory | Get product inventory
*InventoryApi* | [**setInventory**](docs/Api/InventoryApi.md#setinventory) | **POST** /variants/{variant}/inventory/set | Set inventory quantity
*InventoryApi* | [**updateVariantInventory**](docs/Api/InventoryApi.md#updatevariantinventory) | **PUT** /variants/{variant}/inventory | Update variant inventory
*POSDevicesApi* | [**closeCashDrawer**](docs/Api/POSDevicesApi.md#closecashdrawer) | **POST** /pos-devices/{id}/cash-drawer/close | Close cash drawer
*POSDevicesApi* | [**getPosDevice**](docs/Api/POSDevicesApi.md#getposdevice) | **GET** /pos-devices/{id} | Get POS device
*POSDevicesApi* | [**listPosDevices**](docs/Api/POSDevicesApi.md#listposdevices) | **GET** /pos-devices | List POS devices
*POSDevicesApi* | [**logApplicationShutdown**](docs/Api/POSDevicesApi.md#logapplicationshutdown) | **POST** /pos-devices/{id}/shutdown | Log application shutdown
*POSDevicesApi* | [**logApplicationStart**](docs/Api/POSDevicesApi.md#logapplicationstart) | **POST** /pos-devices/{id}/start | Log application start
*POSDevicesApi* | [**openCashDrawer**](docs/Api/POSDevicesApi.md#opencashdrawer) | **POST** /pos-devices/{id}/cash-drawer/open | Open cash drawer
*POSDevicesApi* | [**patchPosDevice**](docs/Api/POSDevicesApi.md#patchposdevice) | **PATCH** /pos-devices/{id} | Update POS device (partial)
*POSDevicesApi* | [**registerPosDevice**](docs/Api/POSDevicesApi.md#registerposdevice) | **POST** /pos-devices | Register POS device
*POSDevicesApi* | [**updateDeviceHeartbeat**](docs/Api/POSDevicesApi.md#updatedeviceheartbeat) | **POST** /pos-devices/{id}/heartbeat | Update device heartbeat
*POSDevicesApi* | [**updatePosDevice**](docs/Api/POSDevicesApi.md#updateposdevice) | **PUT** /pos-devices/{id} | Update POS device
*POSEventsApi* | [**createPosEvent**](docs/Api/POSEventsApi.md#createposevent) | **POST** /pos-events | Create POS event
*POSEventsApi* | [**getPosEvent**](docs/Api/POSEventsApi.md#getposevent) | **GET** /pos-events/{id} | Get POS event
*POSEventsApi* | [**listPosEvents**](docs/Api/POSEventsApi.md#listposevents) | **GET** /pos-events | List POS events
*POSSessionsApi* | [**closePosSession**](docs/Api/POSSessionsApi.md#closepossession) | **POST** /pos-sessions/{id}/close | Close POS session
*POSSessionsApi* | [**createDailyClosing**](docs/Api/POSSessionsApi.md#createdailyclosing) | **POST** /pos-sessions/daily-closing | Create daily closing report
*POSSessionsApi* | [**generateXReport**](docs/Api/POSSessionsApi.md#generatexreport) | **POST** /pos-sessions/{id}/x-report | Generate X-report
*POSSessionsApi* | [**generateZReport**](docs/Api/POSSessionsApi.md#generatezreport) | **POST** /pos-sessions/{id}/z-report | Generate Z-report
*POSSessionsApi* | [**getCurrentPosSession**](docs/Api/POSSessionsApi.md#getcurrentpossession) | **GET** /pos-sessions/current | Get current open session
*POSSessionsApi* | [**getPosSession**](docs/Api/POSSessionsApi.md#getpossession) | **GET** /pos-sessions/{id} | Get POS session
*POSSessionsApi* | [**listPosSessions**](docs/Api/POSSessionsApi.md#listpossessions) | **GET** /pos-sessions | List POS sessions
*POSSessionsApi* | [**openPosSession**](docs/Api/POSSessionsApi.md#openpossession) | **POST** /pos-sessions/open | Open POS session
*POSTransactionsApi* | [**createCorrectionReceipt**](docs/Api/POSTransactionsApi.md#createcorrectionreceipt) | **POST** /pos-transactions/correction-receipt | Create correction receipt
*POSTransactionsApi* | [**voidTransaction**](docs/Api/POSTransactionsApi.md#voidtransaction) | **POST** /pos-transactions/charges/{chargeId}/void | Void transaction
*ProductsApi* | [**getProduct**](docs/Api/ProductsApi.md#getproduct) | **GET** /products/{id} | Get product
*ProductsApi* | [**listProducts**](docs/Api/ProductsApi.md#listproducts) | **GET** /products | List products
*ProductsApi* | [**serveProductImage**](docs/Api/ProductsApi.md#serveproductimage) | **GET** /products/{product}/images/{media} | Serve product image
*PurchasesApi* | [**cancelPurchase**](docs/Api/PurchasesApi.md#cancelpurchase) | **POST** /purchases/{id}/cancel | Cancel a pending purchase
*PurchasesApi* | [**completePurchasePayment**](docs/Api/PurchasesApi.md#completepurchasepayment) | **POST** /purchases/{id}/complete-payment | Complete payment for deferred purchase
*PurchasesApi* | [**createPurchase**](docs/Api/PurchasesApi.md#createpurchase) | **POST** /purchases | Complete purchase (single or split payment)
*PurchasesApi* | [**getPaymentMethods**](docs/Api/PurchasesApi.md#getpaymentmethods) | **GET** /purchases/payment-methods | Get available payment methods
*PurchasesApi* | [**getPurchase**](docs/Api/PurchasesApi.md#getpurchase) | **GET** /purchases/{id} | Get purchase
*PurchasesApi* | [**listPurchases**](docs/Api/PurchasesApi.md#listpurchases) | **GET** /purchases | List purchases
*PurchasesApi* | [**refundPurchase**](docs/Api/PurchasesApi.md#refundpurchase) | **POST** /purchases/{id}/refund | Refund a purchase
*PurchasesApi* | [**updatePurchaseCustomer**](docs/Api/PurchasesApi.md#updatepurchasecustomer) | **PUT** /purchases/{id}/customer | Register or update customer for purchase
*PurchasesApi* | [**updatePurchaseCustomerPatch**](docs/Api/PurchasesApi.md#updatepurchasecustomerpatch) | **PATCH** /purchases/{id}/customer | Register or update customer for purchase (PATCH)
*ReceiptPrintersApi* | [**createReceiptPrinter**](docs/Api/ReceiptPrintersApi.md#createreceiptprinter) | **POST** /receipt-printers | Create receipt printer
*ReceiptPrintersApi* | [**deleteReceiptPrinter**](docs/Api/ReceiptPrintersApi.md#deletereceiptprinter) | **DELETE** /receipt-printers/{id} | Delete receipt printer
*ReceiptPrintersApi* | [**getReceiptPrinter**](docs/Api/ReceiptPrintersApi.md#getreceiptprinter) | **GET** /receipt-printers/{id} | Get receipt printer
*ReceiptPrintersApi* | [**listReceiptPrinters**](docs/Api/ReceiptPrintersApi.md#listreceiptprinters) | **GET** /receipt-printers | List receipt printers
*ReceiptPrintersApi* | [**patchReceiptPrinter**](docs/Api/ReceiptPrintersApi.md#patchreceiptprinter) | **PATCH** /receipt-printers/{id} | Update receipt printer (partial)
*ReceiptPrintersApi* | [**testReceiptPrinterConnection**](docs/Api/ReceiptPrintersApi.md#testreceiptprinterconnection) | **POST** /receipt-printers/{id}/test-connection | Test printer connection
*ReceiptPrintersApi* | [**testReceiptPrinterPrint**](docs/Api/ReceiptPrintersApi.md#testreceiptprinterprint) | **POST** /receipt-printers/{id}/test-print | Send test print
*ReceiptPrintersApi* | [**updateReceiptPrinter**](docs/Api/ReceiptPrintersApi.md#updatereceiptprinter) | **PUT** /receipt-printers/{id} | Update receipt printer
*ReceiptsApi* | [**generateReceipt**](docs/Api/ReceiptsApi.md#generatereceipt) | **POST** /receipts/generate | Generate receipt
*ReceiptsApi* | [**getReceipt**](docs/Api/ReceiptsApi.md#getreceipt) | **GET** /receipts/{id} | Get receipt
*ReceiptsApi* | [**getReceiptXml**](docs/Api/ReceiptsApi.md#getreceiptxml) | **GET** /receipts/{id}/xml | Get receipt XML
*ReceiptsApi* | [**listReceipts**](docs/Api/ReceiptsApi.md#listreceipts) | **GET** /receipts | List receipts
*ReceiptsApi* | [**markReceiptPrinted**](docs/Api/ReceiptsApi.md#markreceiptprinted) | **POST** /receipts/{id}/mark-printed | Mark receipt as printed
*ReceiptsApi* | [**reprintReceipt**](docs/Api/ReceiptsApi.md#reprintreceipt) | **POST** /receipts/{id}/reprint | Reprint receipt
*SAFTApi* | [**downloadSafT**](docs/Api/SAFTApi.md#downloadsaft) | **GET** /saf-t/download/{filename} | Download SAF-T file
*SAFTApi* | [**generateSafT**](docs/Api/SAFTApi.md#generatesaft) | **POST** /saf-t/generate | Generate SAF-T file
*SAFTApi* | [**getSafTContent**](docs/Api/SAFTApi.md#getsaftcontent) | **GET** /saf-t/content | Get SAF-T XML content
*StoresApi* | [**changeCurrentStore**](docs/Api/StoresApi.md#changecurrentstore) | **PUT** /stores/current | Change current store
*StoresApi* | [**getCurrentStore**](docs/Api/StoresApi.md#getcurrentstore) | **GET** /stores/current | Get current store
*StoresApi* | [**getStore**](docs/Api/StoresApi.md#getstore) | **GET** /stores/{slug} | Get store by slug
*StoresApi* | [**listStores**](docs/Api/StoresApi.md#liststores) | **GET** /stores | List stores
*StoresApi* | [**patchCurrentStore**](docs/Api/StoresApi.md#patchcurrentstore) | **PATCH** /stores/current | Change current store (partial)
*TerminalsApi* | [**createTerminalConnectionToken**](docs/Api/TerminalsApi.md#createterminalconnectiontoken) | **POST** /stores/{store}/terminal/connection-token | Create terminal connection token
*TerminalsApi* | [**createTerminalPaymentIntent**](docs/Api/TerminalsApi.md#createterminalpaymentintent) | **POST** /stores/{store}/terminal/payment-intents | Create payment intent
*TerminalsApi* | [**listTerminalLocations**](docs/Api/TerminalsApi.md#listterminallocations) | **GET** /terminals/locations | List terminal locations
*TerminalsApi* | [**listTerminalReaders**](docs/Api/TerminalsApi.md#listterminalreaders) | **GET** /terminals/readers | List terminal readers
*WebhooksApi* | [**stripeConnectWebhook**](docs/Api/WebhooksApi.md#stripeconnectwebhook) | **POST** /stripe/connect/webhook | Stripe Connect webhook

## Models

- [AdjustInventory200Response](docs/Model/AdjustInventory200Response.md)
- [AdjustInventory200ResponseVariant](docs/Model/AdjustInventory200ResponseVariant.md)
- [AdjustInventoryRequest](docs/Model/AdjustInventoryRequest.md)
- [BulkUpdateInventory200Response](docs/Model/BulkUpdateInventory200Response.md)
- [BulkUpdateInventoryRequest](docs/Model/BulkUpdateInventoryRequest.md)
- [BulkUpdateInventoryRequestUpdatesInner](docs/Model/BulkUpdateInventoryRequestUpdatesInner.md)
- [CancelPurchase200Response](docs/Model/CancelPurchase200Response.md)
- [CancelPurchase422Response](docs/Model/CancelPurchase422Response.md)
- [CancelPurchaseRequest](docs/Model/CancelPurchaseRequest.md)
- [ChangeCurrentStore200Response](docs/Model/ChangeCurrentStore200Response.md)
- [ChangeCurrentStoreRequest](docs/Model/ChangeCurrentStoreRequest.md)
- [CloseCashDrawer200Response](docs/Model/CloseCashDrawer200Response.md)
- [CloseCashDrawerRequest](docs/Model/CloseCashDrawerRequest.md)
- [ClosePosSession200Response](docs/Model/ClosePosSession200Response.md)
- [ClosePosSessionRequest](docs/Model/ClosePosSessionRequest.md)
- [Collection](docs/Model/Collection.md)
- [CompletePurchasePayment200Response](docs/Model/CompletePurchasePayment200Response.md)
- [CompletePurchasePayment200ResponseData](docs/Model/CompletePurchasePayment200ResponseData.md)
- [CompletePurchasePayment200ResponseDataCharge](docs/Model/CompletePurchasePayment200ResponseDataCharge.md)
- [CompletePurchasePayment200ResponseDataPosEvent](docs/Model/CompletePurchasePayment200ResponseDataPosEvent.md)
- [CompletePurchasePayment422Response](docs/Model/CompletePurchasePayment422Response.md)
- [CompletePurchasePaymentRequest](docs/Model/CompletePurchasePaymentRequest.md)
- [CreateCorrectionReceipt201Response](docs/Model/CreateCorrectionReceipt201Response.md)
- [CreateCorrectionReceipt201ResponseEvent](docs/Model/CreateCorrectionReceipt201ResponseEvent.md)
- [CreateCorrectionReceipt201ResponseReceipt](docs/Model/CreateCorrectionReceipt201ResponseReceipt.md)
- [CreateCorrectionReceiptRequest](docs/Model/CreateCorrectionReceiptRequest.md)
- [CreateCustomerRequest](docs/Model/CreateCustomerRequest.md)
- [CreateCustomerRequestCustomerAddress](docs/Model/CreateCustomerRequestCustomerAddress.md)
- [CreateDailyClosing201Response](docs/Model/CreateDailyClosing201Response.md)
- [CreateDailyClosing409Response](docs/Model/CreateDailyClosing409Response.md)
- [CreateDailyClosingRequest](docs/Model/CreateDailyClosingRequest.md)
- [CreatePosEvent201Response](docs/Model/CreatePosEvent201Response.md)
- [CreatePosEventRequest](docs/Model/CreatePosEventRequest.md)
- [CreatePurchase201Response](docs/Model/CreatePurchase201Response.md)
- [CreatePurchase201ResponseData](docs/Model/CreatePurchase201ResponseData.md)
- [CreatePurchase201ResponseDataCharge](docs/Model/CreatePurchase201ResponseDataCharge.md)
- [CreatePurchase201ResponseDataPosEvent](docs/Model/CreatePurchase201ResponseDataPosEvent.md)
- [CreatePurchase201ResponseDataReceipt](docs/Model/CreatePurchase201ResponseDataReceipt.md)
- [CreatePurchase422Response](docs/Model/CreatePurchase422Response.md)
- [CreatePurchaseRequest](docs/Model/CreatePurchaseRequest.md)
- [CreateReceiptPrinter201Response](docs/Model/CreateReceiptPrinter201Response.md)
- [CreateReceiptPrinterRequest](docs/Model/CreateReceiptPrinterRequest.md)
- [CreateTerminalConnectionToken200Response](docs/Model/CreateTerminalConnectionToken200Response.md)
- [CreateTerminalConnectionTokenRequest](docs/Model/CreateTerminalConnectionTokenRequest.md)
- [CreateTerminalPaymentIntent201Response](docs/Model/CreateTerminalPaymentIntent201Response.md)
- [CreateTerminalPaymentIntentRequest](docs/Model/CreateTerminalPaymentIntentRequest.md)
- [Customer](docs/Model/Customer.md)
- [DeleteReceiptPrinter200Response](docs/Model/DeleteReceiptPrinter200Response.md)
- [ErrorResponse](docs/Model/ErrorResponse.md)
- [GenerateReceipt201Response](docs/Model/GenerateReceipt201Response.md)
- [GenerateReceiptRequest](docs/Model/GenerateReceiptRequest.md)
- [GenerateSafT201Response](docs/Model/GenerateSafT201Response.md)
- [GenerateSafTRequest](docs/Model/GenerateSafTRequest.md)
- [GenerateXReport200Response](docs/Model/GenerateXReport200Response.md)
- [GenerateZReport200Response](docs/Model/GenerateZReport200Response.md)
- [GenerateZReportRequest](docs/Model/GenerateZReportRequest.md)
- [GetCollection200Response](docs/Model/GetCollection200Response.md)
- [GetCollection200ResponseCollection](docs/Model/GetCollection200ResponseCollection.md)
- [GetCurrentStore200Response](docs/Model/GetCurrentStore200Response.md)
- [GetPaymentMethods200Response](docs/Model/GetPaymentMethods200Response.md)
- [GetPosDevice200Response](docs/Model/GetPosDevice200Response.md)
- [GetPosEvent200Response](docs/Model/GetPosEvent200Response.md)
- [GetPosSession200Response](docs/Model/GetPosSession200Response.md)
- [GetProduct200Response](docs/Model/GetProduct200Response.md)
- [GetProductInventory200Response](docs/Model/GetProductInventory200Response.md)
- [GetProductInventory200ResponseVariantsInner](docs/Model/GetProductInventory200ResponseVariantsInner.md)
- [GetProductInventory200ResponseVariantsInnerInventory](docs/Model/GetProductInventory200ResponseVariantsInnerInventory.md)
- [GetPurchase200Response](docs/Model/GetPurchase200Response.md)
- [GetReceipt200Response](docs/Model/GetReceipt200Response.md)
- [GetReceiptPrinter200Response](docs/Model/GetReceiptPrinter200Response.md)
- [ListCollections200Response](docs/Model/ListCollections200Response.md)
- [ListCustomers200Response](docs/Model/ListCustomers200Response.md)
- [ListPosDevices200Response](docs/Model/ListPosDevices200Response.md)
- [ListPosEvents200Response](docs/Model/ListPosEvents200Response.md)
- [ListPosSessions200Response](docs/Model/ListPosSessions200Response.md)
- [ListProducts200Response](docs/Model/ListProducts200Response.md)
- [ListPurchases200Response](docs/Model/ListPurchases200Response.md)
- [ListReceiptPrinters200Response](docs/Model/ListReceiptPrinters200Response.md)
- [ListReceipts200Response](docs/Model/ListReceipts200Response.md)
- [ListReceipts200ResponseSimpleReceiptListInner](docs/Model/ListReceipts200ResponseSimpleReceiptListInner.md)
- [ListStores200Response](docs/Model/ListStores200Response.md)
- [ListTerminalLocations200Response](docs/Model/ListTerminalLocations200Response.md)
- [ListTerminalReaders200Response](docs/Model/ListTerminalReaders200Response.md)
- [LogApplicationShutdown200Response](docs/Model/LogApplicationShutdown200Response.md)
- [LogApplicationStart200Response](docs/Model/LogApplicationStart200Response.md)
- [LogApplicationStart200ResponseCurrentSession](docs/Model/LogApplicationStart200ResponseCurrentSession.md)
- [LoginRequest](docs/Model/LoginRequest.md)
- [LoginResponse](docs/Model/LoginResponse.md)
- [LoginResponseCurrentStore](docs/Model/LoginResponseCurrentStore.md)
- [LoginResponseStoresInner](docs/Model/LoginResponseStoresInner.md)
- [Logout200Response](docs/Model/Logout200Response.md)
- [LogoutAll200Response](docs/Model/LogoutAll200Response.md)
- [MarkReceiptPrinted200Response](docs/Model/MarkReceiptPrinted200Response.md)
- [OpenCashDrawer200Response](docs/Model/OpenCashDrawer200Response.md)
- [OpenCashDrawer200ResponseEvent](docs/Model/OpenCashDrawer200ResponseEvent.md)
- [OpenCashDrawerRequest](docs/Model/OpenCashDrawerRequest.md)
- [OpenPosSession201Response](docs/Model/OpenPosSession201Response.md)
- [OpenPosSession409Response](docs/Model/OpenPosSession409Response.md)
- [OpenPosSessionRequest](docs/Model/OpenPosSessionRequest.md)
- [PaginationMeta](docs/Model/PaginationMeta.md)
- [PatchPosDeviceRequest](docs/Model/PatchPosDeviceRequest.md)
- [PatchReceiptPrinterRequest](docs/Model/PatchReceiptPrinterRequest.md)
- [PaymentMethod](docs/Model/PaymentMethod.md)
- [PosDevice](docs/Model/PosDevice.md)
- [PosDeviceDeviceInfo](docs/Model/PosDeviceDeviceInfo.md)
- [PosDeviceIdentifiers](docs/Model/PosDeviceIdentifiers.md)
- [PosDeviceReceiptPrintersInner](docs/Model/PosDeviceReceiptPrintersInner.md)
- [PosDeviceSystemInfo](docs/Model/PosDeviceSystemInfo.md)
- [PosDeviceTerminalLocationsInner](docs/Model/PosDeviceTerminalLocationsInner.md)
- [PosEvent](docs/Model/PosEvent.md)
- [PosSession](docs/Model/PosSession.md)
- [PosSessionClosing](docs/Model/PosSessionClosing.md)
- [PosSessionSessionDevice](docs/Model/PosSessionSessionDevice.md)
- [PosSessionSessionUser](docs/Model/PosSessionSessionUser.md)
- [PosSessionWithCharges](docs/Model/PosSessionWithCharges.md)
- [Product](docs/Model/Product.md)
- [ProductCollectionsInner](docs/Model/ProductCollectionsInner.md)
- [ProductPricesInner](docs/Model/ProductPricesInner.md)
- [ProductProductInventory](docs/Model/ProductProductInventory.md)
- [ProductProductPrice](docs/Model/ProductProductPrice.md)
- [ProductVariantsInner](docs/Model/ProductVariantsInner.md)
- [ProductVariantsInnerVariantOptionsInner](docs/Model/ProductVariantsInnerVariantOptionsInner.md)
- [ProductVariantsInnerVariantPrice](docs/Model/ProductVariantsInnerVariantPrice.md)
- [Purchase](docs/Model/Purchase.md)
- [PurchaseCart](docs/Model/PurchaseCart.md)
- [PurchaseCartDiscountsInner](docs/Model/PurchaseCartDiscountsInner.md)
- [PurchaseCartItemsInner](docs/Model/PurchaseCartItemsInner.md)
- [PurchaseMetadata](docs/Model/PurchaseMetadata.md)
- [PurchasePurchaseCustomer](docs/Model/PurchasePurchaseCustomer.md)
- [PurchasePurchaseDiscountsInner](docs/Model/PurchasePurchaseDiscountsInner.md)
- [PurchasePurchaseItemsInner](docs/Model/PurchasePurchaseItemsInner.md)
- [PurchasePurchasePaymentsInner](docs/Model/PurchasePurchasePaymentsInner.md)
- [PurchasePurchaseReceipt](docs/Model/PurchasePurchaseReceipt.md)
- [PurchasePurchaseSession](docs/Model/PurchasePurchaseSession.md)
- [PurchasePurchaseStore](docs/Model/PurchasePurchaseStore.md)
- [PurchaseSinglePaymentRequest](docs/Model/PurchaseSinglePaymentRequest.md)
- [PurchaseSplitPaymentRequest](docs/Model/PurchaseSplitPaymentRequest.md)
- [PurchaseSplitPaymentRequestPaymentsInner](docs/Model/PurchaseSplitPaymentRequestPaymentsInner.md)
- [Receipt](docs/Model/Receipt.md)
- [ReceiptCharge](docs/Model/ReceiptCharge.md)
- [ReceiptPrinter](docs/Model/ReceiptPrinter.md)
- [RefundPurchase200Response](docs/Model/RefundPurchase200Response.md)
- [RefundPurchase200ResponseData](docs/Model/RefundPurchase200ResponseData.md)
- [RefundPurchase200ResponseDataCharge](docs/Model/RefundPurchase200ResponseDataCharge.md)
- [RefundPurchase200ResponseDataPosEvent](docs/Model/RefundPurchase200ResponseDataPosEvent.md)
- [RefundPurchase200ResponseDataReceipt](docs/Model/RefundPurchase200ResponseDataReceipt.md)
- [RefundPurchase422Response](docs/Model/RefundPurchase422Response.md)
- [RefundPurchase500Response](docs/Model/RefundPurchase500Response.md)
- [RefundPurchaseRequest](docs/Model/RefundPurchaseRequest.md)
- [RefundPurchaseRequestItemsInner](docs/Model/RefundPurchaseRequestItemsInner.md)
- [RegisterPosDevice201Response](docs/Model/RegisterPosDevice201Response.md)
- [RegisterPosDeviceRequest](docs/Model/RegisterPosDeviceRequest.md)
- [ReprintReceipt200Response](docs/Model/ReprintReceipt200Response.md)
- [SessionCharge](docs/Model/SessionCharge.md)
- [SetInventory200Response](docs/Model/SetInventory200Response.md)
- [SetInventoryRequest](docs/Model/SetInventoryRequest.md)
- [Store](docs/Model/Store.md)
- [TerminalLocation](docs/Model/TerminalLocation.md)
- [TerminalLocationAddress](docs/Model/TerminalLocationAddress.md)
- [TerminalReader](docs/Model/TerminalReader.md)
- [TerminalReaderLocation](docs/Model/TerminalReaderLocation.md)
- [TestReceiptPrinterConnection200Response](docs/Model/TestReceiptPrinterConnection200Response.md)
- [TestReceiptPrinterPrint200Response](docs/Model/TestReceiptPrinterPrint200Response.md)
- [UpdateCustomerRequest](docs/Model/UpdateCustomerRequest.md)
- [UpdateDeviceHeartbeat200Response](docs/Model/UpdateDeviceHeartbeat200Response.md)
- [UpdateDeviceHeartbeatRequest](docs/Model/UpdateDeviceHeartbeatRequest.md)
- [UpdatePosDevice200Response](docs/Model/UpdatePosDevice200Response.md)
- [UpdatePosDeviceRequest](docs/Model/UpdatePosDeviceRequest.md)
- [UpdatePurchaseCustomer200Response](docs/Model/UpdatePurchaseCustomer200Response.md)
- [UpdatePurchaseCustomerRequest](docs/Model/UpdatePurchaseCustomerRequest.md)
- [UpdateReceiptPrinter200Response](docs/Model/UpdateReceiptPrinter200Response.md)
- [UpdateReceiptPrinterRequest](docs/Model/UpdateReceiptPrinterRequest.md)
- [UpdateVariantInventory200Response](docs/Model/UpdateVariantInventory200Response.md)
- [UpdateVariantInventory200ResponseVariant](docs/Model/UpdateVariantInventory200ResponseVariant.md)
- [UpdateVariantInventory200ResponseVariantVariantInventory](docs/Model/UpdateVariantInventory200ResponseVariantVariantInventory.md)
- [UpdateVariantInventoryRequest](docs/Model/UpdateVariantInventoryRequest.md)
- [User](docs/Model/User.md)
- [UserResponse](docs/Model/UserResponse.md)
- [ValidationErrorResponse](docs/Model/ValidationErrorResponse.md)
- [VoidTransaction200Response](docs/Model/VoidTransaction200Response.md)
- [VoidTransaction200ResponseCharge](docs/Model/VoidTransaction200ResponseCharge.md)
- [VoidTransactionRequest](docs/Model/VoidTransactionRequest.md)
- [XReport](docs/Model/XReport.md)
- [XReportStore](docs/Model/XReportStore.md)
- [ZReport](docs/Model/ZReport.md)

## Authorization

Authentication schemes defined for the API:
### bearerAuth

- **Type**: Bearer authentication (JWT)

## Tests

To run the tests, use:

```bash
composer install
vendor/bin/phpunit
```

## Author

support@visivo.no

## About this package

This PHP package is automatically generated by the [OpenAPI Generator](https://openapi-generator.tech) project:

- API version: `1.0.0`
    - Generator version: `7.17.0`
- Build package: `org.openapitools.codegen.languages.PhpClientCodegen`
