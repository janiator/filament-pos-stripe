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
import 'dart:async';

// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

// ============================================================================
// POS Periodic Actions Service (Singleton) - Included for self-containment
// ============================================================================

class PosPeriodicActionsServiceImpl {
  static PosPeriodicActionsServiceImpl? _instance;
  
  bool _isRunning = false;
  
  PosPeriodicActionsServiceImpl._();

  static PosPeriodicActionsServiceImpl get instance {
    _instance ??= PosPeriodicActionsServiceImpl._();
    return _instance!;
  }

  bool get isRunning => _isRunning;
}

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
    'isRunning': PosPeriodicActionsServiceImpl.instance.isRunning,
  };
}
