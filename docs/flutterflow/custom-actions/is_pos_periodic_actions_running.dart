// Import the centralized service
import '/custom_code/actions/pos_periodic_actions_service.dart';

// ============================================================================
// FlutterFlow Custom Action
// ============================================================================

/// Check if periodic POS actions are currently running
/// 
/// Returns:
/// - success: true
/// - isRunning: boolean indicating if actions are running
Future<dynamic> isPosPeriodicActionsRunning() async {
  return {
    'success': true,
    'isRunning': posPeriodicActionsServiceInstance.isRunning,
  };
}
