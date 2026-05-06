// Import the centralized service
import '/custom_code/actions/pos_periodic_actions_service.dart';

// ============================================================================
// FlutterFlow Custom Action
// ============================================================================

/// Stop all periodic POS actions
/// 
/// Call this on logout or when app closes
Future<dynamic> stopPosPeriodicActions() async {
  posPeriodicActionsServiceInstance.stopAllPeriodicActions();

  return {
    'success': true,
    'message': 'Periodic POS actions stopped',
  };
}
