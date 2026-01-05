// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/custom_code/actions/index.dart'; // Imports other custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';

// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

// FlutterFlow Custom Action: Complete POS Purchase
//
// This action handles the complete purchase flow for POS transactions.
// It converts the cart data from FlutterFlow app state to the API format
// and makes the purchase API request.
//
// Supports:
// - Single and split payments
// - Cash and Stripe payments
// - Deferred payments (payment on pickup) - use payment_method_code: "deferred" or set metadata.deferred_payment: true
//
// IMPORTANT: FlutterFlow requires Future<dynamic> as return type

import 'dart:convert';
import 'package:http/http.dart' as http;

Future<dynamic> completePosPurchase(
  int posSessionId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  String? paymentIntentId,
  String? additionalMetadataJson,
  bool isSplitPayment,
  String? splitPaymentsJson,
) async {
  try {
    // Parse additional metadata from JSON string
    Map<String, dynamic> additionalMetadata = {};
    if (additionalMetadataJson != null && additionalMetadataJson.isNotEmpty) {
      try {
        additionalMetadata =
            jsonDecode(additionalMetadataJson) as Map<String, dynamic>;
      } catch (e) {
        // If JSON parsing fails, use empty map
        additionalMetadata = {};
      }
    }

    // Parse split payments from JSON string
    List<Map<String, dynamic>>? splitPayments;
    if (isSplitPayment &&
        splitPaymentsJson != null &&
        splitPaymentsJson.isNotEmpty) {
      try {
        final List<dynamic> parsed =
            jsonDecode(splitPaymentsJson) as List<dynamic>;
        splitPayments =
            parsed.map((item) => item as Map<String, dynamic>).toList();
      } catch (e) {
        return {
          'success': false,
          'message': 'Invalid split payments JSON format',
        };
      }
    }

    // Get cart from app state
    final cart = FFAppState().cart;

    // Validate cart has items
    if (cart.cartItems.isEmpty) {
      return {
        'success': false,
        'message': 'Cart is empty. Cannot complete purchase.',
      };
    }

    // Validate POS session ID
    if (posSessionId <= 0) {
      return {
        'success': false,
        'message': 'Invalid POS session ID',
      };
    }

    // Validate API URL and auth token
    if (apiBaseUrl.isEmpty) {
      return {
        'success': false,
        'message': 'API base URL is missing',
      };
    }

    if (authToken.isEmpty) {
      return {
        'success': false,
        'message': 'Authentication token is missing. Please log in.',
      };
    }

    // Build cart items array from cartItems
    final List<Map<String, dynamic>> cartItems = [];
    for (var cartItem in cart.cartItems) {
      // Get product ID (assuming it's stored as string, convert to int)
      final productId = int.tryParse(cartItem.cartItemProductId) ?? 0;

      // Get variant ID if present
      final variantId = cartItem.cartItemVariantId != null &&
              cartItem.cartItemVariantId!.isNotEmpty
          ? int.tryParse(cartItem.cartItemVariantId!)
          : null;

      // Unit price is already in øre (based on CartItemsStruct structure)
      final unitPrice = cartItem.cartItemUnitPrice;

      // Discount amount in øre
      final discountAmount = cartItem.cartItemDiscountAmount ?? 0;

      cartItems.add({
        'product_id': productId,
        'variant_id': variantId,
        'quantity': cartItem.cartItemQuantity,
        'unit_price': unitPrice,
        'discount_amount': discountAmount,
        'tax_rate': 0.25, // Norwegian VAT rate
        'tax_inclusive': true,
      });
    }

    // Build discounts array from cartDiscounts
    final List<Map<String, dynamic>> cartDiscounts = [];
    for (var discount in cart.cartDiscounts) {
      final discountType = discount.cartDiscountType.isNotEmpty
          ? discount.cartDiscountType
          : 'fixed'; // Default to 'fixed' if empty

      final discountMap = <String, dynamic>{
        'type': discountType,
        'amount': discount.cartDiscountAmount,
      };

      // Add percentage if type is 'prosent' (percentage-based discount)
      if (discountType == 'prosent' && discount.cartDiscountPercentage > 0) {
        discountMap['percentage'] = discount.cartDiscountPercentage;
      }

      // Add reason if provided
      if (discount.cartDiscountReason.isNotEmpty) {
        discountMap['reason'] = discount.cartDiscountReason;
      } else if (discount.cartDiscountDescription.isNotEmpty) {
        discountMap['reason'] = discount.cartDiscountDescription;
      }

      // Add coupon info if present
      if (discount.cartDiscountCouponId.isNotEmpty) {
        discountMap['coupon_id'] = discount.cartDiscountCouponId;
      }

      if (discount.cartDiscountCouponCode.isNotEmpty) {
        discountMap['coupon_code'] = discount.cartDiscountCouponCode;
      }

      cartDiscounts.add(discountMap);
    }

    // Get totals from cart (already calculated)
    final subtotal = cart.cartSubtotalExcludingTax; // in øre
    final totalDiscounts =
        cart.cartTotalDiscount; // in øre (includes both item and cart discounts)
    final totalTax = cart.cartTotalTax; // in øre
    final total = cart.cartTotalCartPrice; // in øre
    final tipAmount = cart.cartTipAmount; // in øre

    // Build cart object
    final cartData = {
      'items': cartItems,
      'discounts': cartDiscounts,
      'tip_amount': tipAmount,
      'customer_id': cart.cartCustomerId,
      'customer_name':
          cart.cartCustomerName.isNotEmpty ? cart.cartCustomerName : null,
      'subtotal': subtotal,
      'total_discounts': totalDiscounts,
      'total_tax': totalTax,
      'total': total,
      'currency': 'nok',
    };

    // Build request body
    Map<String, dynamic> requestBody;

    if (isSplitPayment && splitPayments != null && splitPayments.isNotEmpty) {
      // Split payment request
      requestBody = {
        'pos_session_id': posSessionId,
        'payments': splitPayments,
        'cart': cartData,
        'metadata': {
          ...?additionalMetadata,
        },
      };
    } else {
      // Single payment request
      final metadata = <String, dynamic>{
        ...?additionalMetadata,
      };

      // Add payment intent ID if provided (for Stripe payments)
      if (paymentIntentId != null && paymentIntentId.isNotEmpty) {
        metadata['payment_intent_id'] = paymentIntentId;
      }

      // Note: For deferred payments, you can either:
      // 1. Use payment_method_code: "deferred" (recommended)
      // 2. Set metadata.deferred_payment: true with any payment method
      // If using option 2, include in additionalMetadataJson:
      // {"deferred_payment": true, "deferred_reason": "Payment on pickup"}

      requestBody = {
        'pos_session_id': posSessionId,
        'payment_method_code': paymentMethodCode,
        'cart': cartData,
        'metadata': metadata,
      };
    }

    // Make API request
    final response = await http.post(
      Uri.parse('$apiBaseUrl/api/purchases'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
      body: jsonEncode(requestBody),
    );

    // Parse response
    final responseData = jsonDecode(response.body) as Map<String, dynamic>;

    // Check HTTP status code
    if (response.statusCode >= 200 && response.statusCode < 300) {
      // Success
      // Note: For deferred payments, the response will have:
      // - charge.status = "pending" (not "succeeded")
      // - charge.paid = false
      // - receipt.receipt_type = "delivery" (not "sales")
      // - receipt.receipt_number format: "{store_id}-D-{number}" (D = Delivery)
      return {
        'success': responseData['success'] ?? true,
        'data': responseData['data'],
        'message': responseData['message'],
        'statusCode': response.statusCode,
      };
    } else {
      // Error
      return {
        'success': false,
        'message': responseData['message'] ?? 'Purchase failed',
        'errors': responseData['errors'],
        'statusCode': response.statusCode,
      };
    }
  } catch (e) {
    // Handle exceptions
    return {
      'success': false,
      'message': 'Error completing purchase: ${e.toString()}',
      'error': e.toString(),
      'statusCode': 0, // 0 indicates exception occurred before HTTP request
    };
  }
}




