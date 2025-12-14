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
