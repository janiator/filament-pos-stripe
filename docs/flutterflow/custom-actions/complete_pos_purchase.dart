// FlutterFlow Custom Action: Complete POS Purchase
//
// This action handles the complete purchase flow for POS transactions.
// It converts the cart data from FlutterFlow app state to the API format
// and makes the purchase API request.
//
// Supports:
// - Single and split payments
// - Cash and Stripe payments
// - Whole-order note: FFAppState().cart.cartNote → API cart.note (printed on sales/delivery receipts when set)
// - Deferred payments (payment on pickup) - use payment_method_code: "deferred" or set metadata.deferred_payment: true
// - Estimated pickup date for deferred payments: [estimatedPickupDate] and/or additionalMetadataJson
//
// IMPORTANT: FlutterFlow requires Future<dynamic> as return type, not Map<String, dynamic>?
//
// Function signature (must match FlutterFlow — POSitiv uses terminalPaymentResult):
// Future<dynamic> completePosPurchase(
//   int posSessionId,
//   String paymentMethodCode,
//   String apiBaseUrl,
//   String authToken,
//   dynamic? terminalPaymentResult,  // From createAndProcessTerminalPayment; optional
//   String? additionalMetadataJson,
//   bool isSplitPayment,
//   String? splitPaymentsJson,
//   DateTime? estimatedPickupDate,
// ) async

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

const String kPositivDeferredResumeChargeIdKey =
    'positiv_deferred_resume_charge_id';
const String kPositivDeferredResumeOrderLabelKey =
    'positiv_deferred_resume_order_label';

Future<void> _clearPositivDeferredResumePrefs() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(kPositivDeferredResumeChargeIdKey);
    await prefs.remove(kPositivDeferredResumeOrderLabelKey);
  } catch (_) {}
}

void mirrorDeferredResumeBannerToAppStateIfPresent({
  required bool active,
  required String bannerText,
}) {
  try {
    final s = FFAppState();
    s.update(() {
      final d = s as dynamic;
      d.deferredResumeBannerActive = active;
      d.deferredResumeBannerText = bannerText;
    });
  } catch (_) {}
}

/// Reads terminal payment fields without relying on FlutterFlow-generated struct
/// types (names change when Data Types are edited in the Designer).
String? _terminalPaymentField(dynamic result, String field) {
  if (result == null) {
    return null;
  }
  if (result is String) {
    final value = result.trim();

    return field == 'paymentIntentId' && value.isNotEmpty ? value : null;
  }
  if (result is Map) {
    return result[field]?.toString();
  }
  try {
    switch (field) {
      case 'provider':
        return (result as dynamic).provider?.toString();
      case 'providerPaymentReference':
        return (result as dynamic).providerPaymentReference?.toString();
      case 'status':
        return (result as dynamic).status?.toString();
      case 'paymentIntentId':
        return (result as dynamic).paymentIntentId?.toString();
      default:
        return null;
    }
  } catch (_) {
    return null;
  }
}

Future<dynamic> completePosPurchase(
  int posSessionId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  dynamic? terminalPaymentResult,
  String? additionalMetadataJson,
  bool isSplitPayment,
  String? splitPaymentsJson,
  DateTime? estimatedPickupDate,
) async {
  try {
    // Parse additional metadata from JSON string
    Map<String, dynamic> additionalMetadata = {};
    if (additionalMetadataJson != null &&
        additionalMetadataJson.trim().isNotEmpty) {
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
        splitPaymentsJson.trim().isNotEmpty) {
      try {
        final List<dynamic> parsed =
            jsonDecode(splitPaymentsJson) as List<dynamic>;
        splitPayments = parsed
            .map((item) => item as Map<String, dynamic>)
            .toList();
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

    // Parked deferred resume (orders -> POS): choosing a **settlement** method runs
    // completeDeferredPayment. Choosing **deferred** again revises the pending charge
    // (POST /api/purchases/{id}/revise-deferred) instead of incorrectly calling complete-payment.
    try {
      final prefs = await SharedPreferences.getInstance();
      final resumeId = prefs.getInt(kPositivDeferredResumeChargeIdKey) ?? 0;
      final resumeLabel =
          (prefs.getString(kPositivDeferredResumeOrderLabelKey) ?? '').trim();
      if (resumeId > 0) {
        final isDeferredAgain = paymentMethodCode == 'deferred' ||
            paymentMethodCode == 'pay_later' ||
            (additionalMetadata['deferred_payment'] == true);

        if (isDeferredAgain) {
          Map<String, dynamic>? cartMap;
          try {
            final serialized = await serializeCartForCompleteDeferred();
            if (serialized is Map && serialized['success'] == true) {
              final raw = serialized['cartJson']?.toString().trim() ?? '';
              if (raw.isNotEmpty) {
                final decoded = jsonDecode(raw);
                if (decoded is Map<String, dynamic>) {
                  cartMap = decoded;
                }
              }
            }
          } catch (_) {}

          if (cartMap == null) {
            return {
              'success': false,
              'message':
                  'Could not build cart for deferred revision. Ensure the cart has items.',
            };
          }

          final reviseMetadata = Map<String, dynamic>.from(additionalMetadata);
          if (estimatedPickupDate != null) {
            reviseMetadata['estimated_pickup_date'] =
                estimatedPickupDate.toIso8601String();
          } else if (reviseMetadata.containsKey('estimated_pickup_date')) {
            final metadataDate = reviseMetadata['estimated_pickup_date'];
            if (metadataDate != null &&
                metadataDate.toString().trim().isNotEmpty) {
              try {
                final dateTime = DateTime.parse(metadataDate.toString());
                reviseMetadata['estimated_pickup_date'] =
                    dateTime.toIso8601String();
              } catch (_) {}
            }
          }

          int? posDeviceId;
          int? posSessionId;
          try {
            final appState = FFAppState();
            try {
              final deviceId = appState.activePosDevice.id;
              if (deviceId != null && deviceId > 0) {
                posDeviceId = deviceId;
              }
            } catch (_) {}
            if (posDeviceId == null) {
              try {
                final sessionId = appState.currentPosSession.id;
                if (sessionId != null && sessionId > 0) {
                  posSessionId = sessionId;
                }
              } catch (_) {}
            }
          } catch (_) {}

          final baseUrl = apiBaseUrl.endsWith('/')
              ? apiBaseUrl.substring(0, apiBaseUrl.length - 1)
              : apiBaseUrl;

          final reviseBody = <String, dynamic>{
            'cart': cartMap,
            if (reviseMetadata.isNotEmpty) 'metadata': reviseMetadata,
            if (posDeviceId != null) 'pos_device_id': posDeviceId,
            if (posDeviceId == null && posSessionId != null)
              'pos_session_id': posSessionId,
          };

          final response = await http.post(
            Uri.parse('$baseUrl/api/purchases/$resumeId/revise-deferred'),
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer $authToken',
              'Accept': 'application/json',
            },
            body: jsonEncode(reviseBody),
          );

          final responseData = jsonDecode(response.body) as Map<String, dynamic>;

          if (response.statusCode >= 200 && response.statusCode < 300) {
            if (responseData['success'] != false) {
              return {
                'success': responseData['success'] ?? true,
                'data': responseData['data'],
                'message': responseData['message'],
                'deferredResumeRevisedViaReviseDeferred': true,
                'resumeChargeId': resumeId,
                'orderLabel':
                    resumeLabel.isNotEmpty ? resumeLabel : '#$resumeId',
              };
            }
          }

          return {
            'success': false,
            'message': responseData['message']?.toString() ??
                'Deferred revision failed',
            'errors': responseData['errors'],
            'statusCode': response.statusCode,
          };
        }

        String? cartJson;
        try {
          final serialized = await serializeCartForCompleteDeferred();
          if (serialized is Map && serialized['success'] == true) {
            cartJson = serialized['cartJson']?.toString();
          }
        } catch (_) {}

        final result = await completeDeferredPayment(
          resumeId,
          paymentMethodCode,
          apiBaseUrl,
          authToken,
          _terminalPaymentField(terminalPaymentResult, 'paymentIntentId') ?? '',
          additionalMetadataJson,
          cartJson ?? '',
        );

        if (result is Map) {
          return {
            ...Map<String, dynamic>.from(result),
            'deferredResumeSettledViaCompletePosPurchase': true,
            'resumeChargeId': resumeId,
            'orderLabel': resumeLabel.isNotEmpty ? resumeLabel : '#$resumeId',
          };
        }

        return result;
      }
    } catch (_) {}

    // Validate POS session ID
    if (posSessionId <= 0) {
      return {'success': false, 'message': 'Invalid POS session ID'};
    }

    // Validate API URL and auth token
    if (apiBaseUrl.isEmpty) {
      return {'success': false, 'message': 'API base URL is missing'};
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
      final variantId =
          cartItem.cartItemVariantId != null &&
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
    final totalDiscounts = cart
        .cartTotalDiscount; // in øre (includes both item and cart discounts)
    final totalTax = cart.cartTotalTax; // in øre
    final total = cart.cartTotalCartPrice; // in øre
    final tipAmount = cart.cartTipAmount; // in øre

    // Get customer ID from cart
    // Note: customer_id should be the local database ID (integer), not the Stripe customer ID
    // The backend will resolve the local ID to the Stripe customer ID automatically
    // cartCustomerId is already an integer (local database ID)
    final dynamic customerIdForApi =
        cart.cartCustomerId != null && cart.cartCustomerId! > 0
        ? cart.cartCustomerId
        : null;

    // Build cart object
    final cartData = {
      'items': cartItems,
      'discounts': cartDiscounts,
      'tip_amount': tipAmount,
      'customer_id':
          customerIdForApi, // Local customer ID (integer) - backend will resolve to Stripe ID
      'customer_name': cart.cartCustomerName.isNotEmpty
          ? cart.cartCustomerName
          : null,
      'note': cart.cartNote.isNotEmpty ? cart.cartNote : null,
      'subtotal': subtotal,
      'total_discounts': totalDiscounts,
      'total_tax': totalTax,
      'total': total,
      'currency': 'nok',
    };

    // Check if this is a deferred payment
    final isDeferredPayment =
        paymentMethodCode == 'deferred' ||
        (additionalMetadata['deferred_payment'] == true);

    // Handle estimated pickup date from parameter or metadata
    // Priority: parameter > metadata
    if (isDeferredPayment) {
      if (estimatedPickupDate != null) {
        // Use parameter value, convert to ISO 8601 format
        additionalMetadata['estimated_pickup_date'] = estimatedPickupDate
            .toIso8601String();
      } else if (additionalMetadata.containsKey('estimated_pickup_date')) {
        // Use metadata value if parameter is null, ensure it's in ISO 8601 format
        final metadataDate = additionalMetadata['estimated_pickup_date'];
        if (metadataDate != null && metadataDate.toString().trim().isNotEmpty) {
          try {
            // Try to parse as DateTime first (handles various formats)
            final dateTime = DateTime.parse(metadataDate.toString());
            // Convert to ISO 8601 format (e.g., "2025-12-20T00:00:00Z")
            additionalMetadata['estimated_pickup_date'] = dateTime
                .toIso8601String();
          } catch (e) {
            // If parsing fails, use the string as-is (might already be in correct format)
            // Keep the original value
          }
        }
      }
    }

    // Treat empty terminal results (Map or legacy struct) as "no terminal result".
    final providerRaw = _terminalPaymentField(
      terminalPaymentResult,
      'provider',
    );
    final hasTerminalProvider = (providerRaw ?? '').trim().isNotEmpty;
    final paymentProvider = hasTerminalProvider ? providerRaw : null;
    final providerPaymentReference = hasTerminalProvider
        ? _terminalPaymentField(
            terminalPaymentResult,
            'providerPaymentReference',
          )
        : null;
    final providerPaymentStatus = hasTerminalProvider
        ? _terminalPaymentField(terminalPaymentResult, 'status')
        : null;
    final paymentIntentId = _terminalPaymentField(
      terminalPaymentResult,
      'paymentIntentId',
    );

    // Build request body
    Map<String, dynamic> requestBody;

    if (isSplitPayment && splitPayments != null && splitPayments.isNotEmpty) {
      // Split payment request
      final normalizedSplitPayments = splitPayments.map((payment) {
        final metadata = <String, dynamic>{
          ...(payment['metadata'] as Map<String, dynamic>? ?? {}),
        };

        if (paymentProvider != null && paymentProvider.isNotEmpty) {
          metadata['payment_provider'] = paymentProvider;
        }
        if (providerPaymentReference != null &&
            providerPaymentReference.isNotEmpty) {
          metadata['provider_payment_reference'] = providerPaymentReference;
          if (paymentProvider == 'verifone') {
            metadata['verifone_payment_reference'] = providerPaymentReference;
          }
        }
        if (providerPaymentStatus != null && providerPaymentStatus.isNotEmpty) {
          metadata['provider_payment_status'] = providerPaymentStatus;
        }

        return {...payment, 'metadata': metadata};
      }).toList();

      requestBody = {
        'pos_session_id': posSessionId,
        'payments': normalizedSplitPayments,
        'cart': cartData,
        'metadata': {...?additionalMetadata},
      };
    } else {
      // Single payment request
      final metadata = <String, dynamic>{...?additionalMetadata};

      if (paymentProvider != null && paymentProvider.isNotEmpty) {
        metadata['payment_provider'] = paymentProvider;
      }
      if (providerPaymentReference != null &&
          providerPaymentReference.isNotEmpty) {
        metadata['provider_payment_reference'] = providerPaymentReference;
        if (paymentProvider == 'verifone') {
          metadata['verifone_payment_reference'] = providerPaymentReference;
        }
      }
      if (providerPaymentStatus != null && providerPaymentStatus.isNotEmpty) {
        metadata['provider_payment_status'] = providerPaymentStatus;
      }

      // Add payment intent ID if provided (for Stripe payments, backwards-compatible)
      if (paymentIntentId != null && paymentIntentId.isNotEmpty) {
        metadata['payment_intent_id'] = paymentIntentId;
      }

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
      if (responseData['success'] != false) {
        await _clearPositivDeferredResumePrefs();
        mirrorDeferredResumeBannerToAppStateIfPresent(
          active: false,
          bannerText: '',
        );
      }
      // Success
      return {
        'success': responseData['success'] ?? true,
        'data': responseData['data'],
        'message': responseData['message'],
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
    };
  }
}
