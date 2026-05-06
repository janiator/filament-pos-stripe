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

import 'package:shared_preferences/shared_preferences.dart';

const String kPositivDeferredResumeChargeIdKey =
    'positiv_deferred_resume_charge_id';
const String kPositivDeferredResumeOrderLabelKey =
    'positiv_deferred_resume_order_label';

Future<dynamic> getDeferredResumeContext() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final id = prefs.getInt(kPositivDeferredResumeChargeIdKey) ?? 0;
    final label = (prefs.getString(kPositivDeferredResumeOrderLabelKey) ?? '')
        .trim();
    final active = id > 0 && label.isNotEmpty;
    final bannerText = active ? 'Ordre $label' : '';

    return {
      'success': true,
      'active': active,
      'resumeChargeId': id,
      'orderLabel': label,
      'bannerText': bannerText,
    };
  } catch (e) {
    return {
      'success': false,
      'active': false,
      'resumeChargeId': 0,
      'orderLabel': '',
      'bannerText': '',
      'message': e.toString(),
    };
  }
}
