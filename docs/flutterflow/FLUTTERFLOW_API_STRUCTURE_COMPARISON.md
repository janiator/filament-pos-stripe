# FlutterFlow API Structure Comparison Report

This document compares the FlutterFlow data structures with the Filament API response structures to identify any mismatches.

**Generated:** 2025-01-27

---

## Summary

This report compares all FlutterFlow structs in:
- `/Users/jan/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv/lib/backend/schema/structs`
- `/Users/jan/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv/lib/backend/api_requests`

With the corresponding Filament API responses in:
- `app/Http/Controllers/Api/`

---

## 1. Products Structure

### FlutterFlow: `ProductsStruct`
- `product` (List<ProductStruct>)
- `meta` (MetaStruct)

### FlutterFlow: `ProductStruct`
Fields:
- `id` (int)
- `stripe_product_id` (String)
- `name` (String)
- `description` (String)
- `type` (String)
- `active` (bool)
- `shippable` (bool)
- `url` (String)
- `images` (List<String>)
- `no_price_in_pos` (bool)
- `product_price` (ProductPriceStruct)
- `prices` (List<ProductPriceStruct>)
- `variants` (List<VariantsStruct>)
- `variants_count` (int)
- `product_inventory` (ProductInventoryStruct)
- `tax_code` (String)
- `unit_label` (String)
- `statement_descriptor` (String)
- `package_dimensions` (String)
- `product_meta` (ProductMetaStruct)
- `created_at` (String)
- `updated_at` (String)

### API: `ProductsController::formatProductResponse()`
**Status:** ✅ **MATCHES**

All fields match. The API returns:
- All product fields match exactly
- `product_price` structure matches
- `prices` array structure matches
- `variants` array structure matches
- `product_inventory` structure matches
- `collections` field is returned by API but not in FlutterFlow struct (acceptable - optional field)

---

## 2. Customers Structure

### FlutterFlow: `CustomersStruct`
Fields:
- `id` (int)
- `stripe_customer_id` (String)
- `stripe_account_id` (String)
- `name` (String)
- `email` (String)
- `phone` (String)
- `profile_image_url` (String)
- `customer_address` (CustomerAddressStruct)
- `created_at` (String)
- `updated_at` (String)

### API: `CustomersController`
**Status:** ✅ **MATCHES**

The API correctly:
- Returns `customer_address` (renamed from `address` in database)
- Hides internal fields (`model`, `model_id`, `model_uuid`)
- All fields match the FlutterFlow structure

---

## 3. Purchase Structure

### FlutterFlow: `PurchaseStruct`
Fields:
- `id` (int)
- `stripe_charge_id` (String)
- `amount` (int)
- `amount_refunded` (int)
- `currency` (String)
- `status` (String)
- `payment_method` (String)
- `description` (String)
- `failure_code` (String)
- `failure_message` (String)
- `captured` (bool)
- `refunded` (bool)
- `paid` (bool)
- `paid_at` (String)
- `charge_type` (String)
- `application_fee_amount` (int)
- `tip_amount` (int)
- `transaction_code` (String)
- `payment_code` (String)
- `article_group_code` (String)
- `purchase_metadata` (PurchaseMetadataStruct)
- `purchase_items` (List<PurchaseItemStruct>)
- `purchase_discounts` (List<PurchaseDiscountStruct>)
- `purchase_subtotal` (int)
- `purchase_total_discounts` (int)
- `purchase_note` (String)
- `purchase_tip_amount` (int)
- `purchase_total_tax` (int)
- `purchase_payments` (List<PurchasePaymentStruct>)
- `outcome` (PurchaseOutcomeStruct)
- `created_at` (String)
- `updated_at` (String)
- `purchase_session` (PurchaseSessionStruct)
- `purchase_store` (PurchaseStoreStruct)
- `purchase_receipt` (PurchaseReceiptStruct)
- `purchase_customer` (CustomersStruct)

### API: `PurchasesController::formatPurchaseResponse()`
**Status:** ⚠️ **ISSUE FOUND**

**Issue:** The API returns `purchase_item_description` in purchase items (line 620 in PurchasesController.php), but the FlutterFlow `PurchaseItemStruct` does not have this field.

**FlutterFlow PurchaseItemStruct fields:**
- `purchase_item_id`
- `purchase_item_product_id`
- `purchase_item_variant_id`
- `purchase_item_product_name`
- `purchase_item_product_image_url`
- `purchase_item_unit_price`
- `purchase_item_quantity`
- `purchase_item_original_price`
- `purchase_item_discount_amount`
- `purchase_item_discount_reason`
- `purchase_item_article_group_code`
- `purchase_item_product_code`
- `purchase_item_metadata`

**Missing in FlutterFlow:**
- `purchase_item_description` (String) - API returns this field for custom descriptions

**Recommendation:** Add `purchase_item_description` field to `PurchaseItemStruct` in FlutterFlow.

---

## 4. POS Session Structure

### FlutterFlow: `PosSessionStruct`
Fields:
- `id` (int)
- `session_number` (String)
- `status` (String)
- `opened_at` (String)
- `closed_at` (String)
- `opening_balance` (int)
- `expected_cash` (int)
- `actual_cash` (int)
- `cash_difference` (int)
- `opening_notes` (String)
- `closing_notes` (String)
- `session_device` (SessionDeviceStruct)
- `session_user` (SessionUserStruct)
- `transaction_count` (int)
- `total_amount` (int)
- `session_charges` (List<SessionChargesStruct>)

### API: `PosSessionsController::formatSessionResponse()`
**Status:** ✅ **MATCHES**

All fields match. The `session_charges` field is conditionally included when `includeCharges` is true.

---

## 5. Purchase Session Structure (Nested)

### FlutterFlow: `PurchaseSessionStruct`
Fields:
- `id` (int)
- `session_number` (String)
- `status` (String)
- `opened_at` (String)
- `closed_at` (String)

### API: `PurchasesController::formatPurchaseResponse()` - `purchase_session`
**Status:** ✅ **MATCHES**

The nested `purchase_session` object in purchase responses matches exactly.

---

## 6. Purchase Store Structure (Nested)

### FlutterFlow: `PurchaseStoreStruct`
Fields:
- `id` (int)
- `name` (String)
- `slug` (String)

### API: `PurchasesController::formatPurchaseResponse()` - `purchase_store`
**Status:** ✅ **MATCHES**

The nested `purchase_store` object matches exactly.

---

## 7. Store Structure

### FlutterFlow: `StoreStruct`
Fields:
- `id` (int)
- `name` (String)

### API: Store responses
**Status:** ⚠️ **POTENTIAL ISSUE**

The API may return additional fields like `slug` in some contexts (e.g., in `purchase_store`), but the basic `StoreStruct` only has `id` and `name`. This is acceptable if the FlutterFlow app doesn't need the `slug` field for basic store operations.

---

## 8. Session Device Structure (Nested)

### FlutterFlow: `SessionDeviceStruct`
Fields:
- `id` (int)
- `device_name` (String)

### API: `PosSessionsController::formatSessionResponse()` - `session_device`
**Status:** ✅ **MATCHES**

Matches exactly.

---

## 9. Session User Structure (Nested)

### FlutterFlow: `SessionUserStruct`
Fields:
- `id` (int)
- `name` (String)

### API: `PosSessionsController::formatSessionResponse()` - `session_user`
**Status:** ✅ **MATCHES**

Matches exactly.

---

## 10. Shopping Cart Structure

### FlutterFlow: `ShoppingCartStruct`
Fields:
- `cart_id` (String)
- `cart_pos_session_id` (String)
- `cart_items` (List<CartItemsStruct>)
- `cart_discounts` (List<CartDiscountsStruct>)
- `cart_tip_amount` (int)
- `cart_customer_id` (int)
- `cart_customer_name` (String)
- `cart_note` (String)
- `cart_created_at` (String)
- `cart_updated_at` (String)
- `cart_metadata` (CartMetadataStruct)
- `cartTotalLinePrice` (int)
- `cartTotalItemDiscounts` (int)
- `cartTotalCartDiscounts` (int)
- `cartTotalDiscount` (int)
- `cartSubtotalExcludingTax` (int)
- `cartTotalTax` (int)
- `cartTotalCartPrice` (int)

### API: Cart Endpoints
**Status:** ℹ️ **NOTE**

The shopping cart is managed client-side in FlutterFlow and sent to the API when finalizing purchases. The cart structure is used for request payloads, not API responses. The API accepts cart data in the purchase creation endpoint.

**Note:** The field naming convention differs slightly:
- FlutterFlow uses camelCase for calculated fields: `cartTotalLinePrice`, `cartTotalItemDiscounts`, etc.
- These are calculated client-side and sent in the purchase metadata

---

## 11. Cart Items Structure

### FlutterFlow: `CartItemsStruct`
Fields:
- `cart_item_id` (String)
- `cart_item_product_id` (String)
- `cart_item_variant_id` (String)
- `cart_item_product_name` (String)
- `cart_item_product_image_url` (String)
- `cart_item_unit_price` (int)
- `cart_item_quantity` (int)
- `cart_item_original_price` (int)
- `cart_item_discount_amount` (int)
- `cart_item_discount_reason` (String)
- `cart_item_article_group_code` (String)
- `cart_item_product_code` (String)
- `cart_item_metadata` (CartItemMetadataStruct)

### API: Cart Items in Purchase Metadata
**Status:** ✅ **MATCHES**

Cart items are sent as part of purchase creation and stored in metadata. The structure matches when items are converted to `purchase_items` in the purchase response.

---

## Issues Summary

### Critical Issues
None

### Minor Issues
1. **PurchaseItemStruct missing `purchase_item_description` field**
   - **Location:** FlutterFlow struct vs API response
   - **Impact:** Low - field is optional and used for custom item descriptions
   - **Recommendation:** Add `purchase_item_description` (String, nullable) to `PurchaseItemStruct`

### Notes
1. **StoreStruct** - Only includes `id` and `name`, but API may return `slug` in some contexts. This is acceptable if not needed.
2. **Shopping Cart** - Client-side structure, used for requests, not API responses. Structure is compatible.

---

## Recommendations

1. **Add missing field to PurchaseItemStruct:**
   ```dart
   // In PurchaseItemStruct
   String? purchase_item_description;
   ```

2. **Verify StoreStruct usage:**
   - If `slug` is needed in FlutterFlow, add it to `StoreStruct`
   - Currently only used in nested `purchase_store` context where it's available

3. **Consider adding collections to ProductStruct:**
   - API returns `collections` array but FlutterFlow struct doesn't include it
   - Add if collections are needed in the FlutterFlow app

---

## Verification Checklist

- [x] ProductsStruct matches API
- [x] ProductStruct matches API
- [x] CustomersStruct matches API
- [x] PurchaseStruct matches API (except purchase_item_description)
- [x] PosSessionStruct matches API
- [x] PurchaseSessionStruct matches API
- [x] PurchaseStoreStruct matches API
- [x] StoreStruct matches API (basic fields)
- [x] SessionDeviceStruct matches API
- [x] SessionUserStruct matches API
- [x] ShoppingCartStruct compatible with API requests
- [x] CartItemsStruct compatible with API requests

---

**Overall Status:** ✅ **Mostly Compatible** - One minor field missing in PurchaseItemStruct



