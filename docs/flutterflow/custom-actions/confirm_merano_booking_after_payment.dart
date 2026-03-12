// Automatic FlutterFlow imports

import '/backend/schema/structs/index.dart';

import '/backend/schema/enums/enums.dart';

import '/backend/supabase/supabase.dart';

import '/actions/actions.dart' as action_blocks;

import '/flutter_flow/flutter_flow_theme.dart';

import '/flutter_flow/flutter_flow_util.dart';

import '/custom_code/actions/index.dart';

import '/flutter_flow/custom_functions.dart';

import 'package:flutter/material.dart';

// Begin custom action code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'package:http/http.dart' as http;

Map<String, String> _meranoHeaders(
  String authToken, {
  String? storeSlug,
}) {
  return {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': 'Bearer $authToken',
    if (storeSlug != null && storeSlug.trim().isNotEmpty)
      'X-Tenant': storeSlug.trim(),
  };
}

/// Confirm one Merano booking after a successful POS payment.
///
/// Call this once per cart line that contains `merano_booking_id`.
Future<void> confirmMeranoBookingAfterPayment({
  required int bookingId,
  required int amountPaidOre,
  required String posChargeId,
  required String apiBaseUrl,
  required String authToken,
  int? posSessionId,
  int? posDeviceId,
  String currency = 'NOK',
  String? storeSlug,
}) async {
  if (bookingId <= 0) {
    throw Exception('A valid booking ID is required.');
  }

  if (amountPaidOre < 0) {
    throw Exception('amountPaidOre cannot be negative.');
  }
  // amountPaidOre may be 0 for freeticket/zero-total orders.

  final response = await http.post(
    Uri.parse(
      '${apiBaseUrl.trim()}/api/merano/v1/bookings/$bookingId/confirm-pos-payment',
    ),
    headers: _meranoHeaders(authToken, storeSlug: storeSlug),
    body: jsonEncode({
      'amount_paid_ore': amountPaidOre,
      'pos_charge_id': posChargeId,
      'currency': currency,
      if (posSessionId != null) 'pos_session_id': posSessionId,
      if (posDeviceId != null) 'pos_device_id': posDeviceId,
    }),
  );

  if (response.statusCode < 200 || response.statusCode >= 300) {
    throw Exception(
      'Failed to confirm Merano booking $bookingId: ${response.statusCode} ${response.body}',
    );
  }
}

/*
Suggested FlutterFlow usage after complete purchase:

Use the actual amount paid for each booking line (in øre). For freeticket orders this is 0.
cartItemDiscountAmount is per unit; total line discount = (cartItemDiscountAmount ?? 0) * quantity.

for (final item in FFAppState().cart.cartItems) {
  final metadata = item.cartItemMetadata;
  final bookingId = metadata?['merano_booking_id'];

  if (bookingId != null) {
    // Amount actually paid for this line in øre (after per-unit discount)
    final unitPrice = item.cartItemUnitPrice;
    final qty = item.cartItemQuantity;
    final discountPerUnit = item.cartItemDiscountAmount ?? 0;
    final amountPaidOre = ((unitPrice - discountPerUnit) * qty).round().clamp(0, (unitPrice * qty).round());
    await confirmMeranoBookingAfterPayment(
      bookingId: int.parse(bookingId.toString()),
      amountPaidOre: amountPaidOre,
      posChargeId: completedChargeId,
      posSessionId: currentPosSessionId,
      posDeviceId: currentPosDeviceId,
      apiBaseUrl: apiBaseUrl,
      authToken: authToken,
      storeSlug: currentStoreSlug,
    );
  }
}
*/
