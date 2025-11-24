// FlutterFlow Custom Action: Update Device Heartbeat
// 
// Returns Future<dynamic> (FlutterFlow doesn't support return type configuration)
// Nullable parameters (String?) make them optional in FlutterFlow
// Access result using JSON paths: result['success'], result['error']
//
// Dependencies: http

import 'package:http/http.dart' as http;
import 'dart:convert';

Future<dynamic> updateDeviceHeartbeat(
  String apiBaseUrl,
  String authToken,
  String deviceId,
  String? deviceStatus,
  String? deviceMetadataJson,
) async {
  final Map result = {
    'success': false,
    'error': '',
  };

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
    );
    
    if (response.statusCode >= 200 && response.statusCode < 300) {
      result['success'] = true;
      return result;
    } else {
      final errorData = json.decode(response.body);
      result['error'] = errorData['message']?.toString() ?? 'Failed: ${response.statusCode}';
      return result;
    }
  } catch (e) {
    result['error'] = e.toString();
    return result;
  }
}
