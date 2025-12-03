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

// FlutterFlow Custom Action: Complete Stripe Terminal Purchase
//
// This action handles the complete Stripe Terminal payment flow:
// 1. Creates a payment intent via API
// 2. Processes the terminal payment (collects and confirms)
// 3. Completes the purchase using completePosPurchase
//
// Function signature (update in FlutterFlow):
// Future<dynamic> completeStripeTerminalPurchase(
//   int posSessionId,
//   String paymentMethodCode,
//   String apiBaseUrl,
//   String authToken,
//   String storeSlug,  // Store slug for payment intent creation
//   String? additionalMetadataJson,  // Optional, pass as JSON string
// ) async

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:mek_stripe_terminal/mek_stripe_terminal.dart';
import '/custom_code/stripe_terminal_singleton.dart';

Future<dynamic> completeStripeTerminalPurchase(
  int posSessionId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  String storeSlug,
  String? additionalMetadataJson,
) async {
  try {
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
    
    if (storeSlug.isEmpty) {
      return {
        'success': false,
        'message': 'Store slug is missing',
      };
    }
    
    // Get cart total in øre
    final totalAmount = cart.cartTotalCartPrice;
    
    if (totalAmount <= 0) {
      return {
        'success': false,
        'message': 'Cart total must be greater than zero',
      };
    }
    
    // Step 1: Create payment intent
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Oppretter betaling…';
    });
    
    final paymentIntentResponse = await http.post(
      Uri.parse('$apiBaseUrl/api/stores/$storeSlug/terminal/payment-intents'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'amount': totalAmount,
        'currency': 'nok',
        'description': 'POS Purchase',
      }),
    );
    
    if (paymentIntentResponse.statusCode < 200 || 
        paymentIntentResponse.statusCode >= 300) {
      final errorData = jsonDecode(paymentIntentResponse.body) as Map<String, dynamic>;
      return {
        'success': false,
        'message': errorData['message'] ?? 'Failed to create payment intent',
        'statusCode': paymentIntentResponse.statusCode,
      };
    }
    
    final paymentIntentData = jsonDecode(paymentIntentResponse.body) as Map<String, dynamic>;
    final clientSecret = paymentIntentData['client_secret'] as String?;
    final paymentIntentId = paymentIntentData['id'] as String?;
    
    if (clientSecret == null || clientSecret.isEmpty) {
      return {
        'success': false,
        'message': 'Payment intent created but client secret is missing',
      };
    }
    
    if (paymentIntentId == null || paymentIntentId.isEmpty) {
      return {
        'success': false,
        'message': 'Payment intent created but ID is missing',
      };
    }
    
    // Step 2: Process terminal payment (collect and confirm)
    try {
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Henter betaling…';
      });
      
      final pi = await Terminal.instance.retrievePaymentIntent(clientSecret);
      
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Venter på kort…';
      });
      
      // Start collection and store the CancelableFuture so we can cancel it
      final collectFuture = Terminal.instance.collectPaymentMethod(pi);
      StripeTerminalSingleton.instance.currentPaymentCollection = collectFuture;
      
      final collected = await collectFuture;
      
      // Collection is done; no longer cancellable
      StripeTerminalSingleton.instance.currentPaymentCollection = null;
      
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Behandler betaling…';
      });
      
      final confirmed = await Terminal.instance.confirmPaymentIntent(collected);
      
      if (confirmed.id == null || confirmed.id!.isEmpty) {
        return {
          'success': false,
          'message': 'Payment confirmed but payment intent ID is missing',
        };
      }
      
      // Verify payment intent status
      // Note: confirmed.status is a PaymentIntentStatus enum, not a string
      // The enum value is PaymentIntentStatus.succeeded when payment succeeds
      // Convert to string and check if it contains 'succeeded' (enum.toString() returns "PaymentIntentStatus.succeeded")
      final statusString = confirmed.status.toString().toLowerCase();
      if (!statusString.contains('succeeded')) {
        return {
          'success': false,
          'message': 'Payment intent status is not succeeded: ${confirmed.status}',
        };
      }
      
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Betaling vellykket';
      });
      
      // Step 3: Complete purchase by calling the purchase API
      // Build metadata with payment intent ID
      final metadata = <String, dynamic>{
        'payment_intent_id': confirmed.id!,
      };
      
      // Add additional metadata if provided
      if (additionalMetadataJson != null && additionalMetadataJson.isNotEmpty) {
        try {
          final additionalMetadata = jsonDecode(additionalMetadataJson) as Map<String, dynamic>;
          metadata.addAll(additionalMetadata);
        } catch (e) {
          // If JSON parsing fails, continue without additional metadata
        }
      }
      
      // Build cart items array from cartItems
      final List<Map<String, dynamic>> cartItems = [];
      for (var cartItem in cart.cartItems) {
        final productId = int.tryParse(cartItem.cartItemProductId) ?? 0;
        final variantId = cartItem.cartItemVariantId != null && cartItem.cartItemVariantId!.isNotEmpty
            ? int.tryParse(cartItem.cartItemVariantId!)
            : null;
        final unitPrice = cartItem.cartItemUnitPrice;
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
            : 'fixed';
        
        final discountMap = <String, dynamic>{
          'type': discountType,
          'amount': discount.cartDiscountAmount,
        };
        
        if (discountType == 'prosent' && discount.cartDiscountPercentage > 0) {
          discountMap['percentage'] = discount.cartDiscountPercentage;
        }
        
        if (discount.cartDiscountReason.isNotEmpty) {
          discountMap['reason'] = discount.cartDiscountReason;
        } else if (discount.cartDiscountDescription.isNotEmpty) {
          discountMap['reason'] = discount.cartDiscountDescription;
        }
        
        if (discount.cartDiscountCouponId.isNotEmpty) {
          discountMap['coupon_id'] = discount.cartDiscountCouponId;
        }
        if (discount.cartDiscountCouponCode.isNotEmpty) {
          discountMap['coupon_code'] = discount.cartDiscountCouponCode;
        }
        
        cartDiscounts.add(discountMap);
      }
      
      // Build cart object
      final cartData = {
        'items': cartItems,
        'discounts': cartDiscounts,
        'tip_amount': cart.cartTipAmount,
        'customer_id': cart.cartCustomerId.isNotEmpty ? cart.cartCustomerId : null,
        'customer_name': cart.cartCustomerName.isNotEmpty ? cart.cartCustomerName : null,
        'subtotal': cart.cartSubtotalExcludingTax,
        'total_discounts': cart.cartTotalDiscount,
        'total_tax': cart.cartTotalTax,
        'total': cart.cartTotalCartPrice,
        'currency': 'nok',
      };
      
      // Build purchase request body
      final requestBody = {
        'pos_session_id': posSessionId,
        'payment_method_code': paymentMethodCode,
        'cart': cartData,
        'metadata': metadata,
      };
      
      // Make purchase API request
      final purchaseResponse = await http.post(
        Uri.parse('$apiBaseUrl/api/purchases'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $authToken',
          'Accept': 'application/json',
        },
        body: jsonEncode(requestBody),
      );
      
      // Parse purchase response
      final purchaseData = jsonDecode(purchaseResponse.body) as Map<String, dynamic>;
      
      // Check HTTP status code
      if (purchaseResponse.statusCode >= 200 && purchaseResponse.statusCode < 300) {
        // Success
        return {
          'success': purchaseData['success'] ?? true,
          'data': purchaseData['data'],
          'message': purchaseData['message'],
        };
      } else {
        // Error
        return {
          'success': false,
          'message': purchaseData['message'] ?? 'Purchase failed',
          'errors': purchaseData['errors'],
          'statusCode': purchaseResponse.statusCode,
        };
      }
      
    } on TerminalException catch (e) {
      StripeTerminalSingleton.instance.currentPaymentCollection = null;
      
      FFAppState().update(() {
        if (e.code == TerminalExceptionCode.canceled) {
          FFAppState().stripeReaderStatus = 'Betaling avbrutt';
        } else {
          FFAppState().stripeReaderStatus = 'Betalingsfeil: ${e.message}';
        }
      });
      
      return {
        'success': false,
        'message': e.code == TerminalExceptionCode.canceled
            ? 'Payment was canceled'
            : 'Payment failed: ${e.message}',
        'error': e.toString(),
        'errorCode': e.code.toString(),
      };
    } catch (e) {
      StripeTerminalSingleton.instance.currentPaymentCollection = null;
      
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Betalingsfeil: $e';
      });
      
      return {
        'success': false,
        'message': 'Error processing terminal payment: ${e.toString()}',
        'error': e.toString(),
      };
    }
  } catch (e) {
    // Handle exceptions
    return {
      'success': false,
      'message': 'Error completing Stripe Terminal purchase: ${e.toString()}',
      'error': e.toString(),
    };
  }
}

