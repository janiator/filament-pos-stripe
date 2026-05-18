// FlutterFlow Custom Action: Clear Cart
//
// Resets FFAppState().cart (ShoppingCartStruct with cartItems / cartDiscounts),
// clears Merano UI state, clears parked-deferred "resume on POS" prefs so staff
// exit deferred-order mode when clearing the cart.
//
// Parameters (FlutterFlow — must match project custom action):
// - afterSuccessfulPurchase (bool): when false, releases pending Merano
//   bookings on cart lines if API base URL + token can be resolved from
//   FFDevEnvironmentValues and/or FFAppState (see [_resolveApiContextForMeranoRelease]).
//
// Function signature (do not add extra parameters in FlutterFlow):
// Future<dynamic> clearCart(bool afterSuccessfulPurchase) async

import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

const String kPositivDeferredResumeChargeIdKey =
    'positiv_deferred_resume_charge_id';
const String kPositivDeferredResumeOrderLabelKey =
    'positiv_deferred_resume_order_label';
const String kPositivDeferredResumeOrderNoteKey =
    'positiv_deferred_resume_order_note';

Future<void> _clearPositivDeferredResumePrefs() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(kPositivDeferredResumeChargeIdKey);
    await prefs.remove(kPositivDeferredResumeOrderLabelKey);
    await prefs.remove(kPositivDeferredResumeOrderNoteKey);
  } catch (_) {}
}

/// If **FlutterFlow App State** defines `deferredResumeBannerActive` (bool) and
/// `deferredResumeBannerText` (String), keep them aligned with prefs so the POS
/// “Ordre …” banner hides when the cart is cleared. Page / widget state alone
/// does not refresh when only SharedPreferences change.
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

/// Same API host / token / store slug sources as other POS custom actions, so
/// [clearCart] stays a single-parameter action in FlutterFlow.
({
  String apiBaseUrl,
  String authToken,
  String? storeSlug,
}) _resolveApiContextForMeranoRelease() {
  var base = '';
  var token = '';
  String? slug;

  // POSitiv does not ship FFAppConstants(); use dev env + optional app state.
  try {
    final h = FFDevEnvironmentValues().apiHost.trim();
    if (h.isNotEmpty) {
      base = h;
    }
  } catch (_) {}

  if (base.isEmpty) {
    try {
      final s = FFAppState() as dynamic;
      final v = s.apiBaseUrl?.toString().trim() ?? '';
      if (v.isNotEmpty) {
        base = v;
      }
    } catch (_) {}
  }

  try {
    final u = (FFAppState() as dynamic).userdata;
    token = u?.token?.toString().trim() ?? '';
    final store = u?.currentStore;
    final s = store?.slug?.toString().trim() ?? '';
    slug = s.isNotEmpty ? s : null;
  } catch (_) {}

  return (apiBaseUrl: base, authToken: token, storeSlug: slug);
}

Map<String, String> _meranoHeaders(
  String authToken, {
  String? storeSlug,
}) {
  return {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': 'Bearer ${authToken.trim()}',
    if (storeSlug != null && storeSlug.trim().isNotEmpty)
      'X-Tenant': storeSlug.trim(),
  };
}

Map<String, dynamic>? _metadataMap(dynamic raw) {
  if (raw == null) {
    return null;
  }
  try {
    if (raw is Map<String, dynamic>) {
      return raw;
    }
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
    }
    if (raw is String && raw.trim().isNotEmpty) {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    }
  } catch (_) {}
  return null;
}

int? _parseBookingId(dynamic value) {
  if (value == null) {
    return null;
  }
  if (value is int) {
    return value > 0 ? value : null;
  }
  final parsed = int.tryParse(value.toString());
  if (parsed == null || parsed <= 0) {
    return null;
  }
  return parsed;
}

int? _currentPosDeviceId() {
  try {
    final raw = FFAppState().activePosDevice.id;
    if (raw is int) {
      return raw > 0 ? raw : null;
    }
    final parsed = int.tryParse(raw.toString());
    return (parsed != null && parsed > 0) ? parsed : null;
  } catch (_) {
    return null;
  }
}

/// POS session id on cart may be `int` or `String` depending on FlutterFlow schema.
int? _parsedPosSessionIdForApi(dynamic sessionId) {
  if (sessionId == null) {
    return null;
  }
  if (sessionId is int) {
    return sessionId > 0 ? sessionId : null;
  }
  final parsed = int.tryParse(sessionId.toString().trim());
  if (parsed == null || parsed <= 0) {
    return null;
  }
  return parsed;
}

Future<void> _releaseMeranoBookingsInCart({
  required String apiBaseUrl,
  required String authToken,
  String? storeSlug,
}) async {
  final base = apiBaseUrl.trim();
  if (base.isEmpty || authToken.trim().isEmpty) {
    return;
  }

  final posDeviceId = _currentPosDeviceId();
  final posSessionId =
      _parsedPosSessionIdForApi(FFAppState().cart.cartPosSessionId);

  final seen = <int>{};
  for (final item in FFAppState().cart.cartItems) {
    final meta = _metadataMap(item.cartItemMetadata);
    if (meta == null) {
      continue;
    }
    final bookingId = _parseBookingId(meta['merano_booking_id']);
    if (bookingId == null || seen.contains(bookingId)) {
      continue;
    }
    seen.add(bookingId);

    try {
      await http.post(
        Uri.parse('$base/api/merano/v1/bookings/$bookingId/release'),
        headers: _meranoHeaders(authToken, storeSlug: storeSlug),
        body: jsonEncode({
          if (posDeviceId != null) 'pos_device_id': posDeviceId,
          if (posSessionId != null) 'pos_session_id': posSessionId,
        }),
      );
    } catch (_) {}
  }
}

Future<dynamic> clearCart(bool afterSuccessfulPurchase) async {
  try {
    final ctx = _resolveApiContextForMeranoRelease();
    if (!afterSuccessfulPurchase &&
        ctx.apiBaseUrl.isNotEmpty &&
        ctx.authToken.isNotEmpty) {
      await _releaseMeranoBookingsInCart(
        apiBaseUrl: ctx.apiBaseUrl,
        authToken: ctx.authToken,
        storeSlug: ctx.storeSlug,
      );
    }

    await _clearPositivDeferredResumePrefs();
    mirrorDeferredResumeBannerToAppStateIfPresent(
      active: false,
      bannerText: '',
    );

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

    return {
      'success': true,
      'deferredResumeActive': false,
      'deferredResumeBannerText': '',
      'resumeChargeId': 0,
    };
  } catch (e) {
    return {
      'success': false,
      'message': e.toString(),
    };
  }
}
