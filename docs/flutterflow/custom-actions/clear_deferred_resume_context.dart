// FlutterFlow Custom Action: Clear deferred “resume on POS” prefs.
//
// Call after successful completeDeferredPayment / completePosPurchase, or from a
// “Avbryt / ny salg” control so stale banners do not linger.

import 'package:shared_preferences/shared_preferences.dart';

const String kPositivDeferredResumeChargeIdKey =
    'positiv_deferred_resume_charge_id';
const String kPositivDeferredResumeOrderLabelKey =
    'positiv_deferred_resume_order_label';

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

Future<dynamic> clearDeferredResumeContext() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(kPositivDeferredResumeChargeIdKey);
    await prefs.remove(kPositivDeferredResumeOrderLabelKey);
    mirrorDeferredResumeBannerToAppStateIfPresent(
      active: false,
      bannerText: '',
    );
    return {'success': true};
  } catch (e) {
    return {'success': false, 'message': e.toString()};
  }
}
