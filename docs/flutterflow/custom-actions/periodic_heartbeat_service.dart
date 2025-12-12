// FlutterFlow Custom Action: Periodic Heartbeat Service
//
// This service manages periodic heartbeat updates independently of widget lifecycle.
// It uses a singleton pattern to ensure only one instance runs at a time.
//
// IMPORTANT: This service does NOT use BuildContext, so it's safe to use
// from anywhere in the app, including after widgets are disposed.

import 'dart:async';
import 'package:http/http.dart' as http;
import 'dart:convert';

class PeriodicHeartbeatService {
  static PeriodicHeartbeatService? _instance;
  Timer? _heartbeatTimer;
  bool _isRunning = false;

  // Private constructor for singleton
  PeriodicHeartbeatService._();

  // Get singleton instance
  static PeriodicHeartbeatService get instance {
    _instance ??= PeriodicHeartbeatService._();
    return _instance!;
  }

  /// Start periodic heartbeat updates
  /// 
  /// Parameters:
  /// - apiBaseUrl: Your API base URL
  /// - authToken: Current authentication token
  /// - deviceId: Device ID to update
  /// - intervalMinutes: How often to update (default: 5 minutes)
  /// - deviceStatus: Optional device status (default: "active")
  /// - deviceMetadataJson: Optional metadata as JSON string
  void startHeartbeat({
    required String apiBaseUrl,
    required String authToken,
    required String deviceId,
    int intervalMinutes = 5,
    String? deviceStatus,
    String? deviceMetadataJson,
  }) {
    // Stop existing timer if running
    stopHeartbeat();

    _isRunning = true;

    // Create periodic timer
    _heartbeatTimer = Timer.periodic(
      Duration(minutes: intervalMinutes),
      (timer) {
        if (!_isRunning) {
          timer.cancel();
          return;
        }

        // Call heartbeat update (fire and forget - no context needed)
        _updateHeartbeat(
          apiBaseUrl: apiBaseUrl,
          authToken: authToken,
          deviceId: deviceId,
          deviceStatus: deviceStatus,
          deviceMetadataJson: deviceMetadataJson,
        );
      },
    );

    // Also update immediately on start
    _updateHeartbeat(
      apiBaseUrl: apiBaseUrl,
      authToken: authToken,
      deviceId: deviceId,
      deviceStatus: deviceStatus,
      deviceMetadataJson: deviceMetadataJson,
    );
  }

  /// Stop periodic heartbeat updates
  void stopHeartbeat() {
    _isRunning = false;
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
  }

  /// Check if heartbeat service is running
  bool get isRunning => _isRunning;

  /// Internal method to update heartbeat (no context needed)
  Future<void> _updateHeartbeat({
    required String apiBaseUrl,
    required String authToken,
    required String deviceId,
    String? deviceStatus,
    String? deviceMetadataJson,
  }) async {
    try {
      // Parse deviceMetadata from JSON string (or use empty map if null/empty)
      Map deviceMetadata = {};
      if (deviceMetadataJson != null && deviceMetadataJson.isNotEmpty) {
        try {
          deviceMetadata = json.decode(deviceMetadataJson) as Map;
        } catch (e) {
          deviceMetadata = {};
        }
      }

      final Map requestBody = {};
      if (deviceStatus != null && deviceStatus.isNotEmpty) {
        requestBody['device_status'] = deviceStatus;
      }
      if (deviceMetadata.isNotEmpty) {
        requestBody['device_metadata'] = deviceMetadata;
      }

      final response = await http.post(
        Uri.parse('$apiBaseUrl/pos-devices/$deviceId/heartbeat'),
        headers: {
          'Authorization': 'Bearer $authToken',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode(requestBody),
      ).timeout(
        const Duration(seconds: 10),
        onTimeout: () {
          throw TimeoutException('Heartbeat update timed out');
        },
      );

      // Log success/failure (but don't use context)
      if (response.statusCode >= 200 && response.statusCode < 300) {
        // Success - heartbeat updated
        // You could update app state here if needed, but without using context
        // FFAppState().update(() { ... }) is safe to use without context
      } else {
        // Log error but don't throw - periodic action should be resilient
        final errorData = json.decode(response.body);
        print('Heartbeat update failed: ${errorData['message'] ?? response.statusCode}');
      }
    } catch (e) {
      // Log error but don't throw - periodic action should be resilient
      print('Heartbeat update error: $e');
    }
  }

  /// Dispose resources (call when app closes)
  void dispose() {
    stopHeartbeat();
    _instance = null;
  }
}

// FlutterFlow Custom Action wrapper
// This is the function you'll call from FlutterFlow
Future<dynamic> startPeriodicHeartbeat({
  required String apiBaseUrl,
  required String authToken,
  required String deviceId,
  int intervalMinutes = 5,
  String? deviceStatus,
  String? deviceMetadataJson,
}) async {
  PeriodicHeartbeatService.instance.startHeartbeat(
    apiBaseUrl: apiBaseUrl,
    authToken: authToken,
    deviceId: deviceId,
    intervalMinutes: intervalMinutes,
    deviceStatus: deviceStatus,
    deviceMetadataJson: deviceMetadataJson,
  );

  return {
    'success': true,
    'message': 'Periodic heartbeat started',
  };
}

// FlutterFlow Custom Action to stop heartbeat
Future<dynamic> stopPeriodicHeartbeat() async {
  PeriodicHeartbeatService.instance.stopHeartbeat();

  return {
    'success': true,
    'message': 'Periodic heartbeat stopped',
  };
}

// FlutterFlow Custom Action to check if running
Future<dynamic> isHeartbeatRunning() async {
  return {
    'success': true,
    'isRunning': PeriodicHeartbeatService.instance.isRunning,
  };
}

