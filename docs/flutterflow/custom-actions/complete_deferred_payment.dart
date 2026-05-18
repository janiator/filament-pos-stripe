// FlutterFlow Custom Action: Complete Deferred Payment Purchase
//
// This action completes payment for a purchase that was created with deferred payment
// (e.g., dry cleaning, payment on pickup).
//
// It calls the /api/purchases/{id}/complete-payment endpoint to:
// 1. Process payment based on the provided payment method
// 2. Update charge status from 'pending' to 'succeeded'
// 3. Generate a sales receipt (replacing the delivery receipt)
// 4. Update POS session totals
// 5. Open cash drawer (for cash payments)
// 6. Automatically print receipt (if configured)
//
// Supports:
// - Cash payments (no payment intent required)
// - Stripe card payments (requires payment_intent_id)
// - Other Stripe payment methods (requires payment_intent_id)
// - Optional final cart JSON (same shape as completePosPurchase cart) for parked / edited deferred orders
// - Choosing deferred / pay_later again with no payment_intent_id: revises the pending charge via
//   POST /api/purchases/{id}/revise-deferred (checkoutFlow often calls this action directly, not completePosPurchase)
//
// Compliance: Automatically uses current active POS device/session from app state
// to ensure proper audit trail and session tracking.
//
// IMPORTANT: FlutterFlow requires Future<dynamic> as return type

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

int? _parsePositiveInt(dynamic value) {
  if (value == null) {
    return null;
  }
  if (value is int) {
    return value > 0 ? value : null;
  }
  if (value is num) {
    final i = value.toInt();

    return i > 0 ? i : null;
  }

  final parsed = int.tryParse(value.toString());

  return parsed != null && parsed > 0 ? parsed : null;
}

Future<void> _resetPosCartAfterDeferredCompletion() async {
  try {
    await clearCart(true);

    return;
  } catch (_) {}

  try {
    final cart = FFAppState().cart;

    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        cartId: cart.cartId,
        cartPosSessionId: cart.cartPosSessionId,
        cartItems: <CartItemsStruct>[],
        cartDiscounts: <CartDiscountsStruct>[],
        cartTipAmount: 0,
        cartCustomerId: null,
        cartCustomerName: '',
        cartCreatedAt: cart.cartCreatedAt,
        cartUpdatedAt: getCurrentTimestamp.toString(),
        cartMetadata: CartMetadataStruct(),
        cartNote: '',
      );
      FFAppState().lastMeranoBookingResultJson = '';
      FFAppState().meranoSeatmapOrderJson = '';
    });

    await updateCartTotals();
  } catch (_) {}
}

void _bumpListRefreshCacheKey() {
  try {
    FFAppState().update(() {
      FFAppState().cacheRefreshKey = DateTime.now().microsecondsSinceEpoch
          .toString();
    });
  } catch (_) {}
}

void _applyEstimatedPickupDateToMetadata(
  Map<String, dynamic> metadata,
  DateTime? estimatedPickupDate,
) {
  if (estimatedPickupDate != null) {
    metadata['estimated_pickup_date'] = estimatedPickupDate.toIso8601String();

    return;
  }

  if (!metadata.containsKey('estimated_pickup_date')) {
    return;
  }

  final metadataDate = metadata['estimated_pickup_date'];
  if (metadataDate == null || metadataDate.toString().trim().isEmpty) {
    return;
  }

  try {
    final dateTime = DateTime.parse(metadataDate.toString());
    metadata['estimated_pickup_date'] = dateTime.toIso8601String();
  } catch (_) {}
}

int? _parseReceiptIdFromApiResponse(Map<String, dynamic> responseData) {
  final topLevel = _parsePositiveInt(responseData['receipt_id']);
  if (topLevel != null) {
    return topLevel;
  }

  final data = responseData['data'];
  if (data is Map<String, dynamic>) {
    final receipt = data['receipt'];
    if (receipt is Map<String, dynamic>) {
      return _parsePositiveInt(receipt['id']);
    }
  }

  return null;
}

/// Same rules as [receiptPrintAfterPosPurchase]: deferred checkout DSL does not
/// chain the normal post-[completePosPurchase] receipt actions, so we print here.
Future<void> _tryClientPrintDeferredSalesReceipt({
  required String apiBaseUrl,
  required String authToken,
  required int receiptId,
}) async {
  if (receiptId <= 0) {
    return;
  }
  try {
    final device = FFAppState().activePosDevice;
    String? eposUrl;
    for (final p in device.receiptPrinters) {
      if (p.id == device.defaultPrinterId && p.eposUrl.trim().isNotEmpty) {
        eposUrl = p.eposUrl.trim();
        break;
      }
    }

    final allowAutoPrint = device.hasAutoPrintReceipt()
        ? device.autoPrintReceipt
        : true;
    final shouldPrint =
        allowAutoPrint &&
        (FFAppState().receiptPrinter.isActive ||
            (eposUrl != null && eposUrl.isNotEmpty));
    if (!shouldPrint || eposUrl == null || eposUrl.isEmpty) {
      return;
    }

    final base = apiBaseUrl.endsWith('/')
        ? apiBaseUrl.substring(0, apiBaseUrl.length - 1)
        : apiBaseUrl;
    final xmlRes = await http.get(
      Uri.parse('$base/api/receipts/$receiptId/xml'),
      headers: {
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/xml, text/xml, application/json, */*',
      },
    );
    if (xmlRes.statusCode < 200 || xmlRes.statusCode >= 300) {
      return;
    }
    final xmlBody = xmlRes.body;
    if (xmlBody.isEmpty) {
      return;
    }

    final printRes = await http.post(
      Uri.parse(eposUrl),
      headers: {'Content-Type': 'text/xml; charset=utf-8'},
      body: xmlBody,
    );
    if (printRes.statusCode < 200 || printRes.statusCode >= 300) {
      return;
    }

    await http.post(
      Uri.parse('$base/api/receipts/$receiptId/mark-printed'),
      headers: {
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
    );
  } catch (_) {}
}

Future<Map<String, dynamic>?> _cartFromJsonOrCurrent(String? cartJson) async {
  final value = cartJson?.trim() ?? '';
  if (value.isNotEmpty) {
    try {
      final decoded = jsonDecode(value);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }

      throw FormatException('cartJson must decode to a JSON object');
    } catch (e) {
      throw FormatException('Invalid cartJson: ${e.toString()}');
    }
  }

  try {
    final serialized = await serializeCartForCompleteDeferred();
    if (serialized is Map && serialized['success'] == true) {
      final serializedCartJson = serialized['cartJson']?.toString().trim();
      if (serializedCartJson != null && serializedCartJson.isNotEmpty) {
        final decoded = jsonDecode(serializedCartJson);
        if (decoded is Map<String, dynamic>) {
          return decoded;
        }
      }
    }
  } catch (_) {}

  return null;
}

Future<dynamic> completeDeferredPayment(
  int chargeId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  String? paymentIntentId,
  String? additionalMetadataJson,
  String? cartJson,
  DateTime? estimatedPickupDate,
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

    // Validate charge ID
    if (chargeId <= 0) {
      return {'success': false, 'message': 'Invalid purchase/charge ID'};
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

    if (paymentMethodCode.isEmpty) {
      return {'success': false, 'message': 'Payment method code is required'};
    }

    Map<String, dynamic>? cart;
    try {
      cart = await _cartFromJsonOrCurrent(cartJson);
    } on FormatException catch (e) {
      return {'success': false, 'message': e.message};
    }

    // Build metadata object
    final metadata = <String, dynamic>{...?additionalMetadata};

    // Add payment intent ID if provided (required for Stripe payments)
    if (paymentIntentId != null && paymentIntentId.isNotEmpty) {
      metadata['payment_intent_id'] = paymentIntentId;
    }

    // Get current POS device/session from app state for compliance
    // This ensures the payment is completed on the current active session
    // Priority: pos_device_id (auto-detects current session) > pos_session_id > original session
    int? posDeviceId;
    int? posSessionId;

    try {
      final appState = FFAppState();

      // Try to get device ID from active POS device (preferred for compliance)
      // This will auto-detect the current active session for that device
      try {
        final deviceId = appState.activePosDevice.id;
        if (deviceId != null && deviceId > 0) {
          posDeviceId = deviceId;
        }
      } catch (e) {
        // Device ID not available, continue
      }

      // Try to get session ID from current POS session (fallback if device ID not available)
      if (posDeviceId == null) {
        try {
          final sessionId = appState.currentPosSession.id;
          if (sessionId != null && sessionId > 0) {
            posSessionId = sessionId;
          }
        } catch (e) {
          // Session ID not available, continue
        }
      }
    } catch (e) {
      // If app state is not available, continue without device/session ID
      // The backend will fall back to the original session where the deferred payment was created
      print('Warning: Could not get POS device/session from app state: $e');
    }

    // Staff chose "deferred / pay later" again on a pending pickup order: revise lines
    // (POST …/revise-deferred). Do not call complete-payment with payment_method deferred
    // (API rejects it). This path runs when checkoutFlow calls completeDeferredPayment directly.
    final codeLower = paymentMethodCode.trim().toLowerCase();
    final piTrim = (paymentIntentId ?? '').trim();
    final isDeferredAgain = codeLower == 'deferred' ||
        codeLower == 'pay_later' ||
        (additionalMetadata['deferred_payment'] == true);
    if (isDeferredAgain && piTrim.isEmpty) {
      if (cart == null) {
        return {
          'success': false,
          'message':
              'Cart is required to revise a deferred order. Add items or reload the order.',
        };
      }

      final reviseMetadata = Map<String, dynamic>.from(additionalMetadata);
      reviseMetadata.remove('payment_intent_id');
      _applyEstimatedPickupDateToMetadata(reviseMetadata, estimatedPickupDate);

      final base = apiBaseUrl.endsWith('/')
          ? apiBaseUrl.substring(0, apiBaseUrl.length - 1)
          : apiBaseUrl;

      final reviseBody = <String, dynamic>{
        'cart': cart,
        if (reviseMetadata.isNotEmpty) 'metadata': reviseMetadata,
        if (posDeviceId != null) 'pos_device_id': posDeviceId,
        if (posDeviceId == null && posSessionId != null)
          'pos_session_id': posSessionId,
      };

      final reviseResponse = await http.post(
        Uri.parse('$base/api/purchases/$chargeId/revise-deferred'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $authToken',
          'Accept': 'application/json',
        },
        body: jsonEncode(reviseBody),
      );

      final reviseData =
          jsonDecode(reviseResponse.body) as Map<String, dynamic>;

      if (reviseResponse.statusCode >= 200 &&
          reviseResponse.statusCode < 300 &&
          reviseData['success'] != false) {
        try {
          _bumpListRefreshCacheKey();
        } catch (_) {}

        final receiptId = _parseReceiptIdFromApiResponse(reviseData) ?? 0;
        try {
          await _tryClientPrintDeferredSalesReceipt(
            apiBaseUrl: apiBaseUrl,
            authToken: authToken,
            receiptId: receiptId,
          );
        } catch (_) {}

        return {
          'success': reviseData['success'] ?? true,
          'data': reviseData['data'],
          'message': reviseData['message'],
          'deferredResumeRevisedViaReviseDeferred': true,
          'resumeChargeId': chargeId,
          'receiptId': receiptId,
          'deliveryReceiptId': receiptId,
        };
      }

      return {
        'success': false,
        'message': reviseData['message']?.toString() ??
            'Deferred revision failed',
        'errors': reviseData['errors'],
        'statusCode': reviseResponse.statusCode,
      };
    }

    // Build request body
    final requestBody = <String, dynamic>{
      'payment_method_code': paymentMethodCode,
      // Add pos_device_id if available (recommended for compliance - auto-detects current active session)
      if (posDeviceId != null) 'pos_device_id': posDeviceId,
      // OR add pos_session_id if device ID not available but session ID is
      if (posDeviceId == null && posSessionId != null)
        'pos_session_id': posSessionId,
      if (metadata.isNotEmpty) 'metadata': metadata,
      if (cart != null) 'cart': cart,
    };

    // Make API request
    final response = await http.post(
      Uri.parse('$apiBaseUrl/api/purchases/$chargeId/complete-payment'),
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
      final data = responseData['data'];
      int? receiptId;
      String? receiptNumber;
      int? completedChargeId;
      String? chargeStatus;
      if (data is Map<String, dynamic>) {
        final receipt = data['receipt'];
        if (receipt is Map<String, dynamic>) {
          receiptId = _parsePositiveInt(receipt['id']);
          final rn = receipt['receipt_number'];
          if (rn != null) {
            receiptNumber = rn.toString();
          }
        }
        final chargeMap = data['charge'];
        if (chargeMap is Map<String, dynamic>) {
          completedChargeId = _parsePositiveInt(chargeMap['id']);
          final st = chargeMap['status'];
          if (st != null) {
            chargeStatus = st.toString();
          }
        }
      }
      receiptId ??= _parsePositiveInt(responseData['receipt_id']);

      final ok = responseData['success'] != false;
      if (ok) {
        try {
          await _clearPositivDeferredResumePrefs();
          mirrorDeferredResumeBannerToAppStateIfPresent(
            active: false,
            bannerText: '',
          );
          await _resetPosCartAfterDeferredCompletion();
        } catch (_) {}
        final rid = receiptId ?? 0;
        try {
          await _tryClientPrintDeferredSalesReceipt(
            apiBaseUrl: apiBaseUrl,
            authToken: authToken,
            receiptId: rid,
          );
        } catch (_) {}
        try {
          _bumpListRefreshCacheKey();
        } catch (_) {}
      }

      // Success
      // The response will have:
      // - charge.status = "succeeded" (changed from "pending")
      // - charge.paid = true
      // - charge.paid_at = timestamp
      // - receipt.receipt_type = "sales" (replaced delivery receipt)
      // - receipt.receipt_number format: "{store_id}-S-{number}" (S = Sales)
      return {
        'success': ok,
        'data': responseData['data'],
        'message':
            responseData['message'] ??
            (ok
                ? 'Payment completed successfully'
                : 'Payment completion failed'),
        'statusCode': response.statusCode,
        'receiptId': receiptId,
        'salesReceiptId': receiptId,
        'receiptNumber': receiptNumber,
        'completedChargeId': completedChargeId,
        'chargeStatus': chargeStatus,
      };
    } else {
      // Error
      return {
        'success': false,
        'message': responseData['message'] ?? 'Payment completion failed',
        'errors': responseData['errors'],
        'statusCode': response.statusCode,
      };
    }
  } catch (e) {
    // Handle exceptions
    return {
      'success': false,
      'message': 'Error completing deferred payment: ${e.toString()}',
      'error': e.toString(),
      'statusCode': 0, // 0 indicates exception occurred before HTTP request
    };
  }
}
