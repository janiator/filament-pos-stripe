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
  
  String? _authToken;
  
  PosPeriodicActionsServiceImpl._();

  static PosPeriodicActionsServiceImpl get instance {
    _instance ??= PosPeriodicActionsServiceImpl._();
    return _instance!;
  }

  void updateAuthToken(String newAuthToken) {
    _authToken = newAuthToken;
  }
}

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
  PosPeriodicActionsServiceImpl.instance.updateAuthToken(newAuthToken);

  return {
    'success': true,
    'message': 'Token updated',
  };
}
