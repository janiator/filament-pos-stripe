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
  
  Timer? _cacheRefreshTimer;
  Timer? _heartbeatTimer;
  Timer? _drawerCheckTimer;
  bool _isRunning = false;
  
  PosPeriodicActionsServiceImpl._();

  static PosPeriodicActionsServiceImpl get instance {
    _instance ??= PosPeriodicActionsServiceImpl._();
    return _instance!;
  }

  void stopAllPeriodicActions() {
    _isRunning = false;
    _cacheRefreshTimer?.cancel();
    _cacheRefreshTimer = null;
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
    _drawerCheckTimer?.cancel();
    _drawerCheckTimer = null;
  }
}

// ============================================================================
// FlutterFlow Custom Action
// ============================================================================

/// Stop all periodic POS actions
/// 
/// Call this on logout or when app closes
Future<dynamic> stopPosPeriodicActions() async {
  PosPeriodicActionsServiceImpl.instance.stopAllPeriodicActions();

  return {
    'success': true,
    'message': 'Periodic POS actions stopped',
  };
}
