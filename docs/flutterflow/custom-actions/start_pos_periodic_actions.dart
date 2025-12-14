// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/custom_code/actions/index.dart'; // Imports other custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';

// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

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
