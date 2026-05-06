// Import the centralized service
import '/custom_code/actions/pos_periodic_actions_service.dart';

// ============================================================================
// FlutterFlow Custom Action
// ============================================================================

/// Start all periodic POS actions
/// 
/// Call this once after successful login and device registration
/// 
/// Parameters:
/// - apiBaseUrl: Your API base URL
/// - authToken: Current authentication token
/// - deviceId: Device ID from registration
/// - storeSlug: Current store slug
Future<dynamic> startPosPeriodicActions(
  String apiBaseUrl,
  String authToken,
  String deviceId,
  String storeSlug,
) async {
  posPeriodicActionsServiceInstance.startAllPeriodicActions(
    apiBaseUrl: apiBaseUrl,
    authToken: authToken,
    deviceId: deviceId,
    storeSlug: storeSlug,
  );

  return {
    'success': true,
    'message': 'Periodic POS actions started',
  };
}
