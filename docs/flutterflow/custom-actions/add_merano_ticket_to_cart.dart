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
import '/custom_code/widgets/merano_seatmap_order_web_view.dart';

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

/// Creates a pending Merano booking through POSitiv and returns booking metadata
/// needed for the cart line.
///
/// Suggested flow in FlutterFlow:
/// 1. Hide the booking action unless `available_actions` contains `booking`.
/// 2. Open `MeranoSeatmapOrderWebView`.
/// 3. Collect customer data and call this action.
/// 4. Use the returned values with your existing `addItemToCart` action.
/// 5. Call `updateCartTotals`.
Future<dynamic> addMeranoTicketToCart(
  SeatmapOrder order,
  String apiBaseUrl,
  String authToken,
  int posDeviceId,
  String customerName,
  String customerEmail,
  String customerPhone,
  String? storeSlug,
) async {
  if (apiBaseUrl.trim().isEmpty) {
    throw Exception('API base URL is required.');
  }

  if (authToken.trim().isEmpty) {
    throw Exception('Authentication token is required.');
  }

  if (posDeviceId <= 0) {
    throw Exception('A valid POS device ID is required.');
  }

  final response = await http.post(
    Uri.parse('${apiBaseUrl.trim()}/api/merano/v1/bookings'),
    headers: _meranoHeaders(authToken, storeSlug: storeSlug),
    body: jsonEncode({
      'pos_device_id': posDeviceId,
      'event_id': order.eventId,
      'seats': order.seats,
      'name': customerName.trim(),
      'email': customerEmail.trim(),
      'phone': customerPhone.trim(),
      'payment_type': 'pos',
    }),
  );

  if (response.statusCode < 200 || response.statusCode >= 300) {
    throw Exception(
      'Failed to create Merano booking: ${response.statusCode} ${response.body}',
    );
  }

  final data = jsonDecode(response.body) as Map<String, dynamic>;
  final bookingId = data['booking_id'];
  final bookingNumber = data['booking_number'];

  if (bookingId == null || bookingNumber == null) {
    throw Exception('Booking response is missing booking_id or booking_number.');
  }

  final seatLabels = order.seats.join(', ');
  final description = '${order.eventName} - $seatLabels ($bookingNumber)';

  return {
    'bookingId': bookingId,
    'bookingNumber': bookingNumber,
    'description': description,
    'customPrice': order.totalPriceOre,
    'metadata': {
      'merano_booking_id': bookingId,
      'merano_booking_number': bookingNumber,
      'merano_event_id': order.eventId,
      'merano_event_name': order.eventName,
      'merano_seats': order.seats,
    },
  };
}

/// Release a pending Merano booking when the customer cancels or abandons.
Future<void> releaseMeranoTicketBooking(
  int bookingId,
  String apiBaseUrl,
  String authToken, {
  int? posDeviceId,
  int? posSessionId,
  String? storeSlug,
}) async {
  if (bookingId <= 0 ||
      apiBaseUrl.trim().isEmpty ||
      authToken.trim().isEmpty) {
    return;
  }

  await http.post(
    Uri.parse('${apiBaseUrl.trim()}/api/merano/v1/bookings/$bookingId/release'),
    headers: _meranoHeaders(authToken, storeSlug: storeSlug),
    body: jsonEncode({
      if (posDeviceId != null) 'pos_device_id': posDeviceId,
      if (posSessionId != null) 'pos_session_id': posSessionId,
    }),
  );
}

/*
Suggested follow-up action in FlutterFlow after calling addMeranoTicketToCart():

final booking = await addMeranoTicketToCart(...);

await addItemToCart(
  ticketProduct,
  ticketVariant,
  1,
  booking['customPrice'] as int,
  booking['description'] as String,
);

// If your cart action supports metadata directly, merge:
// booking['metadata']

await updateCartTotals();
*/
