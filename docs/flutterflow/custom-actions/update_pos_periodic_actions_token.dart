// Import the centralized service
import '/custom_code/actions/pos_periodic_actions_service.dart';

// ============================================================================
// FlutterFlow Custom Action
// ============================================================================

/// Update authentication token for periodic actions
/// 
/// Call this when token refreshes
/// 
/// Parameters:
/// - newAuthToken: The new authentication token
Future<dynamic> updatePosPeriodicActionsToken(
  String newAuthToken,
) async {
  posPeriodicActionsServiceInstance.updateAuthToken(newAuthToken);

  return {
    'success': true,
    'message': 'Token updated',
  };
}
