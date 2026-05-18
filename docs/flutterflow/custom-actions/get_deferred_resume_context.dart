// FlutterFlow Custom Action: Read deferred “resume on POS” context from device prefs.
//
// Written by prepareParkedDeferredPurchase (orders → POS). Use on the **pos** page
// (e.g. On Page Load) to drive a banner Text and Visibility for payment UI.
//
// Returns:
// - active (bool): true when a deferred resume session is stored
// - resumeChargeId (int): purchase/charge id for completeDeferredPayment
// - orderLabel (String): receipt number or #id from prepare
// - bannerText (String): short label for UI, e.g. "Ordre 1-D-000001"
// - hideDeferredPaymentMethod (bool): when true, hide the "deferred" settlement option
//   (use cash/card to complete via completeDeferredPayment; backend rejects `deferred` on complete-payment).

import 'package:shared_preferences/shared_preferences.dart';

const String kPositivDeferredResumeChargeIdKey =
    'positiv_deferred_resume_charge_id';
const String kPositivDeferredResumeOrderLabelKey =
    'positiv_deferred_resume_order_label';
const String kPositivDeferredResumeOrderNoteKey =
    'positiv_deferred_resume_order_note';

String _deferredResumeBannerText({
  required String orderLabel,
  required int chargeId,
  required String note,
}) {
  final ref = orderLabel.trim().isNotEmpty ? orderLabel.trim() : '#$chargeId';
  final base = 'Ordre $ref';
  final trimmedNote = note.trim();
  if (trimmedNote.isEmpty) {
    return base;
  }

  return '$base · Notat: $trimmedNote';
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

Future<dynamic> getDeferredResumeContext() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final id = prefs.getInt(kPositivDeferredResumeChargeIdKey) ?? 0;
    final label = (prefs.getString(kPositivDeferredResumeOrderLabelKey) ?? '')
        .trim();
    var note = (prefs.getString(kPositivDeferredResumeOrderNoteKey) ?? '').trim();
    if (note.isEmpty) {
      try {
        final fromCart = FFAppState().cart.cartNote.trim();
        if (fromCart.isNotEmpty) {
          note = fromCart;
        }
      } catch (_) {}
    }
    // Charge id alone is enough to complete payment; label can be empty if prefs
    // were written by an older client — still treat session as active for UI.
    final active = id > 0;
    final bannerText = active
        ? _deferredResumeBannerText(
            orderLabel: label,
            chargeId: id,
            note: note,
          )
        : '';

    mirrorDeferredResumeBannerToAppStateIfPresent(
      active: active,
      bannerText: bannerText,
    );

    if (active && note.isNotEmpty) {
      try {
        if (FFAppState().cart.cartNote.trim().isEmpty) {
          FFAppState().update(() {
            FFAppState().updateCartStruct((c) => c..cartNote = note);
          });
        }
      } catch (_) {}
    }

    return {
      'success': true,
      'active': active,
      'resumeChargeId': id,
      'orderLabel': label,
      'bannerText': bannerText,
      'hideDeferredPaymentMethod': active,
    };
  } catch (e) {
    mirrorDeferredResumeBannerToAppStateIfPresent(
      active: false,
      bannerText: '',
    );
    return {
      'success': false,
      'active': false,
      'resumeChargeId': 0,
      'orderLabel': '',
      'bannerText': '',
      'hideDeferredPaymentMethod': false,
      'message': e.toString(),
    };
  }
}
