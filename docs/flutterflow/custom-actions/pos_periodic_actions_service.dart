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
import '/backend/api_requests/api_calls.dart';
import '/flutter_flow/random_data_util.dart' as random_data;

// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

// ============================================================================
// POS Periodic Actions Service (Singleton)
// ============================================================================

class PosPeriodicActionsServiceImpl {
  static PosPeriodicActionsServiceImpl? _instance;
  
  // Timers
  Timer? _cacheRefreshTimer;
  Timer? _heartbeatTimer;
  Timer? _drawerCheckTimer;
  
  // State
  bool _isRunning = false;
  String? _apiBaseUrl;
  String? _authToken;
  String? _deviceId;
  String? _storeSlug;
  
  // Private constructor for singleton
  PosPeriodicActionsServiceImpl._();

  // Get singleton instance
  static PosPeriodicActionsServiceImpl get instance {
    _instance ??= PosPeriodicActionsServiceImpl._();
    return _instance!;
  }

  /// Start all periodic actions
  void startAllPeriodicActions({
    required String apiBaseUrl,
    required String authToken,
    required String deviceId,
    required String storeSlug,
  }) {
    // Stop existing timers if running
    stopAllPeriodicActions();

    _isRunning = true;
    _apiBaseUrl = apiBaseUrl;
    _authToken = authToken;
    _deviceId = deviceId;
    _storeSlug = storeSlug;

    // Start cache refresh timer (every 10 minutes)
    _cacheRefreshTimer = Timer.periodic(
      const Duration(minutes: 10),
      (timer) {
        if (!_isRunning) {
          timer.cancel();
          return;
        }
        _refreshCache();
      },
    );

    // Start heartbeat timer (every 1 minute)
    _heartbeatTimer = Timer.periodic(
      const Duration(minutes: 1),
      (timer) {
        if (!_isRunning) {
          timer.cancel();
          return;
        }
        _updateHeartbeat();
      },
    );

    // Start drawer check timer (every 4 seconds)
    _drawerCheckTimer = Timer.periodic(
      const Duration(seconds: 4),
      (timer) {
        if (!_isRunning) {
          timer.cancel();
          return;
        }
        _checkDrawerStatus();
      },
    );

    // Run initial updates immediately
    _refreshCache();
    _updateHeartbeat();
    _checkDrawerStatus();
  }

  /// Stop all periodic actions
  void stopAllPeriodicActions() {
    _isRunning = false;
    _cacheRefreshTimer?.cancel();
    _cacheRefreshTimer = null;
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
    _drawerCheckTimer?.cancel();
    _drawerCheckTimer = null;
  }

  /// Update authentication token (useful when token refreshes)
  void updateAuthToken(String newAuthToken) {
    _authToken = newAuthToken;
  }

  /// Check if service is running
  bool get isRunning => _isRunning;

  /// Refresh cache key (every 10 minutes)
  void _refreshCache() {
    try {
      // Update cache refresh key - safe to call without context
      FFAppState().update(() {
        FFAppState().cacheRefreshKey = random_data.randomString(
          10,
          10,
          true,
          true,
          true,
        );
      });
    } catch (e) {
      print('Cache refresh error: $e');
    }
  }

  /// Update device heartbeat (every 1 minute)
  void _updateHeartbeat() async {
    if (_apiBaseUrl == null || _authToken == null || _deviceId == null) {
      return;
    }

    try {
      final result = await updateDeviceHeartbeat(
        _apiBaseUrl!,
        _authToken!,
        _deviceId!,
        '', // deviceStatus - empty to use default
        '', // deviceMetadataJson - empty
      );

      // Log result but don't throw errors
      if (result['success'] != true) {
        print('Heartbeat update failed: ${result['error']}');
      }
    } catch (e) {
      print('Heartbeat update error: $e');
    }
  }

  /// Check drawer status (every 4 seconds)
  void _checkDrawerStatus() async {
    if (_authToken == null) {
      return;
    }

    // Skip drawer check if default printer ID is 0 or unset
    final defaultPrinterId = FFAppState().activePosDevice.defaultPrinterId;
    if (defaultPrinterId == null || defaultPrinterId == 0) {
      return;
    }

    try {
      // Get printer status
      final printerStatusOutput = await ReceiptPrinterGroup.printerStatusCall.call(
        eposUrl: FFAppState()
            .activePosDevice
            .receiptPrinters
            .where((e) =>
                FFAppState().activePosDevice.defaultPrinterId == e.id)
            .toList()
            .firstOrNull
            ?.eposUrl,
      );

      if (printerStatusOutput?.bodyText == null) {
        return;
      }

      // Parse printer status
      final printerStatusParsed = await parsePrinterStatusAction(
        printerStatusOutput!.bodyText ?? '',
      );

      if (printerStatusParsed == null) {
        return;
      }

      // Check if drawer is open
      final isDrawerOpen = printerStatusParsed.contains('Drawer open');

      // Get current state before making changes
      final currentPosLocked = FFAppState().posLockStatus.posLocked;
      final currentPosLockedReason = FFAppState().posLockStatus.posLockedReason;
      final currentDrawerStillOpenCounter = FFAppState().drawerStillOpenCounter;
      final drawerShouldBeOpen = FFAppState().drawerShouldBeOpen;

      // Determine what the new state should be
      bool needsUpdate = false;
      bool newPosLocked = currentPosLocked;
      String newPosLockedReason = currentPosLockedReason;
      int newDrawerStillOpenCounter = currentDrawerStillOpenCounter;
      bool shouldReportNullinnstall = false;

      if (isDrawerOpen) {
        if (drawerShouldBeOpen) {
          // Drawer should be open and is open - unlock if locked
          if (currentPosLocked) {
            newPosLocked = false;
            newPosLockedReason = 'OK';
            newDrawerStillOpenCounter = 0;
            needsUpdate = true;
          } else if (currentDrawerStillOpenCounter != 0) {
            // Reset counter if drawer is properly open
            newDrawerStillOpenCounter = 0;
            needsUpdate = true;
          }
        } else {
          // Drawer should be closed but is open - lock POS
          if (!currentPosLocked || currentPosLockedReason != 'Lukk kassaskuffen for å fortsette') {
            newPosLocked = true;
            newPosLockedReason = 'Lukk kassaskuffen for å fortsette';
            needsUpdate = true;
          }

          // Report nullinnstall on first detection
          if (currentDrawerStillOpenCounter == 0) {
            newDrawerStillOpenCounter = 1;
            shouldReportNullinnstall = true;
            needsUpdate = true;
          } else {
            // Increment counter (only if it would actually change)
            final nextCounter = currentDrawerStillOpenCounter + 1;
            if (newDrawerStillOpenCounter != nextCounter) {
              newDrawerStillOpenCounter = nextCounter;
              needsUpdate = true;
            }
          }
        }
      } else {
        // Drawer is closed - unlock if locked
        if (currentPosLocked) {
          newPosLocked = false;
          newPosLockedReason = 'OK';
          newDrawerStillOpenCounter = 0;
          needsUpdate = true;
        } else if (currentDrawerStillOpenCounter != 0) {
          // Reset counter if drawer is closed
          newDrawerStillOpenCounter = 0;
          needsUpdate = true;
        }
      }

      // Only update app state if something actually changed
      if (needsUpdate) {
        FFAppState().update(() {
          FFAppState().posLockStatus = PosLockDataStruct(
            posLocked: newPosLocked,
            posLockedReason: newPosLockedReason,
          );
          FFAppState().drawerStillOpenCounter = newDrawerStillOpenCounter;
        });

        // Report nullinnstall outside of update block
        if (shouldReportNullinnstall) {
          _reportNullinnstall();
        }
      }
    } catch (e) {
      print('Drawer check error: $e');
    }
  }

  /// Report nullinnstall (cash drawer opened)
  void _reportNullinnstall() async {
    if (_apiBaseUrl == null || _authToken == null) {
      return;
    }

    try {
      await POSStripeConnectAPIGroup.reportNullinnstalCall.call(
        id: FFAppState().activePosDevice.id,
        authToken: _authToken!,
      );
    } catch (e) {
      print('Nullinnstall report error: $e');
    }
  }

  /// Dispose resources (call when app closes)
  void dispose() {
    stopAllPeriodicActions();
    _instance = null;
  }
}

// ============================================================================
// FlutterFlow Action Function (Required)
// ============================================================================

/// This function is required by FlutterFlow but should not be called directly.
/// Use the separate action files instead:
/// - startPosPeriodicActions
/// - stopPosPeriodicActions
/// - updatePosPeriodicActionsToken
/// - isPosPeriodicActionsRunning
Future<dynamic> posPeriodicActionsService() async {
  return {
    'success': false,
    'error': 'Use startPosPeriodicActions, stopPosPeriodicActions, etc. instead',
  };
}

// Public accessor for the service instance (used by other action files)
PosPeriodicActionsServiceImpl get posPeriodicActionsServiceInstance => PosPeriodicActionsServiceImpl.instance;
