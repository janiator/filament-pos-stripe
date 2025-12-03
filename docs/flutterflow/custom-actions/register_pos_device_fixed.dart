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

// FlutterFlow Custom Action: Register POS Device
// 
// IMPORTANT: FlutterFlow doesn't support Map parameters or return type configuration
// Solution: Return Future<dynamic> and pass metadata as JSON string
// Nullable parameters (String?) make them optional in FlutterFlow
//
// Dependencies: device_info_plus, http

import 'package:device_info_plus/device_info_plus.dart';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:async';

Future<dynamic> registerPosDevice(
  String apiBaseUrl,
  String authToken,
  String? deviceName,
  String? deviceMetadataJson,
) async {
  final Map result = {
    'success': false,
    'deviceId': '',
    'deviceIdentifier': '',
    'isNewDevice': false,
    'error': '',
    'device': null, // full device object from API
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

    final DeviceInfoPlugin deviceInfo = DeviceInfoPlugin();
    
    String platform = 'unknown';
    String deviceIdentifier = '';
    String deviceNameValue = '';
    Map deviceData = {};
    
    if (Platform.isIOS) {
      platform = 'ios';
      final IosDeviceInfo iosInfo = await deviceInfo.iosInfo;
      
      // Get identifier - may be null on simulator
      deviceIdentifier = iosInfo.identifierForVendor ?? '';
      
      // Simulator fallback - create a stable identifier
      if (!iosInfo.isPhysicalDevice) {
        if (deviceIdentifier.isEmpty) {
          // Create a stable identifier for simulator based on machine info
          final machine = iosInfo.utsname.machine ?? 'unknown';
          final name = iosInfo.name ?? 'iOS Simulator';
          deviceIdentifier = 'ios-simulator-${machine}-${name}';
        }
      }
      
      // Use provided deviceName if given, otherwise use device's name
      deviceNameValue = (deviceName != null && deviceName.isNotEmpty)
          ? deviceName
          : (iosInfo.name ?? 'iOS Device');
      
      deviceData = {
        'device_model': iosInfo.model ?? '',
        'machine_identifier': iosInfo.utsname.machine ?? '',
        'system_name': iosInfo.systemName ?? '',
        'system_version': iosInfo.systemVersion ?? '',
        'vendor_identifier': iosInfo.identifierForVendor ?? '',
        'is_physical_device': iosInfo.isPhysicalDevice,
      };
    } else if (Platform.isAndroid) {
      platform = 'android';
      final AndroidDeviceInfo androidInfo = await deviceInfo.androidInfo;
      
      deviceIdentifier = androidInfo.id;
      
      // Emulator fallback - create a stable identifier
      if (!androidInfo.isPhysicalDevice) {
        if (deviceIdentifier.isEmpty || deviceIdentifier == 'unknown') {
          // Create a stable identifier for emulator
          final model = androidInfo.model ?? 'unknown';
          final device = androidInfo.device ?? 'unknown';
          deviceIdentifier = 'android-emulator-${model}-${device}';
        }
      }
      
      // Use provided deviceName if given, otherwise use device's name
      deviceNameValue = (deviceName != null && deviceName.isNotEmpty)
          ? deviceName
          : (androidInfo.device ?? 'Android Device');
      
      deviceData = {
        'device_model': androidInfo.model ?? '',
        'device_brand': androidInfo.brand ?? '',
        'device_manufacturer': androidInfo.manufacturer ?? '',
        'device_product': androidInfo.product ?? '',
        'device_hardware': androidInfo.hardware ?? '',
        'system_version': androidInfo.version.release ?? '',
        'android_id': androidInfo.id,
        'is_physical_device': androidInfo.isPhysicalDevice,
      };
    } else {
      result['error'] = 'Unsupported platform';
      return result;
    }
    
    // Final guard – if we still don't have any identifier, bail
    if (deviceIdentifier.isEmpty) {
      result['error'] = 'Could not determine device identifier';
      return result;
    }
    
    final Map requestBody = {
      'device_identifier': deviceIdentifier,
      'device_name': deviceNameValue.isEmpty ? 'Unknown Device' : deviceNameValue,
      'platform': platform,
      ...deviceData,
    };
    
    if (deviceMetadata.isNotEmpty) {
      requestBody['device_metadata'] = deviceMetadata;
    }
    
    // Build headers
    final headers = {
      'Authorization': 'Bearer $authToken',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    
    // Normalize API base URL
    String baseUrl = apiBaseUrl.trim();
    
    // Validate URL format
    if (!baseUrl.startsWith('http://') && !baseUrl.startsWith('https://')) {
      result['error'] = 'Invalid API URL: Must start with http:// or https://';
      return result;
    }
    
    // Remove trailing slash
    if (baseUrl.endsWith('/')) {
      baseUrl = baseUrl.substring(0, baseUrl.length - 1);
    }
    
    // Ensure /api prefix is present
    // Check if /api is already in the URL (either as /api or /api/)
    if (!baseUrl.endsWith('/api') && !baseUrl.contains('/api/')) {
      baseUrl = '$baseUrl/api';
    } else if (baseUrl.endsWith('/api/')) {
      // Remove trailing /api/ and add /api
      baseUrl = baseUrl.substring(0, baseUrl.length - 5) + '/api';
    }
    
    // Construct full URL for checking existing devices
    final checkUrl = '$baseUrl/pos-devices';
    
    // Validate the constructed URL
    try {
      final uri = Uri.parse(checkUrl);
      if (!uri.hasScheme || !uri.hasAuthority) {
        result['error'] = 'Invalid URL format: $checkUrl';
        return result;
      }
    } catch (e) {
      result['error'] = 'Invalid URL format: $checkUrl. ${e.toString()}';
      return result;
    }
    
    // Check if device already exists with timeout
    http.Response checkResponse;
    try {
      checkResponse = await http.get(
        Uri.parse(checkUrl),
        headers: headers,
      ).timeout(
        const Duration(seconds: 30),
        onTimeout: () {
          throw Exception('Request timeout: Could not connect to API server');
        },
      );
    } on SocketException catch (e) {
      result['error'] = 'Network error: Unable to connect to server. Please check your internet connection and API URL. ${e.message}';
      return result;
    } on HttpException catch (e) {
      result['error'] = 'HTTP error: ${e.message}';
      return result;
    } on FormatException catch (e) {
      result['error'] = 'Invalid URL format: $checkUrl. ${e.message}';
      return result;
    } catch (e) {
      result['error'] = 'Connection failed: ${e.toString()}';
      return result;
    }
    
    bool isNewDevice = true;
    String deviceId = '';
    
    if (checkResponse.statusCode == 200) {
      try {
        final devicesData = json.decode(checkResponse.body);
        final devices = devicesData['devices'] as List?;
        
        if (devices != null) {
          for (var device in devices) {
            if (device['device_identifier'] == deviceIdentifier) {
              isNewDevice = false;
              deviceId = device['id']?.toString() ?? '';
              break;
            }
          }
        }
      } catch (e) {
        // If parsing fails, assume new device
        isNewDevice = true;
      }
    }
    
    http.Response response;
    
    try {
      if (isNewDevice) {
        // Register new device
        final registerUrl = '$baseUrl/pos-devices';
        response = await http.post(
          Uri.parse(registerUrl),
          headers: headers,
          body: json.encode(requestBody),
        ).timeout(
          const Duration(seconds: 30),
          onTimeout: () {
            throw Exception('Request timeout: Could not register device');
          },
        );
      } else {
        // Update existing device
        final Map updateBody = {
          'device_name': deviceNameValue,
          ...deviceData,
        };
        
        if (deviceMetadata.isNotEmpty) {
          updateBody['device_metadata'] = deviceMetadata;
        }
        
        // Use PATCH for update (more RESTful)
        final updateUrl = '$baseUrl/pos-devices/$deviceId';
        response = await http.patch(
          Uri.parse(updateUrl),
          headers: headers,
          body: json.encode(updateBody),
        ).timeout(
          const Duration(seconds: 30),
          onTimeout: () {
            throw Exception('Request timeout: Could not update device');
          },
        );
      }
    } on SocketException catch (e) {
      result['error'] = 'Network error: Unable to connect to server. Please check your internet connection and API URL. ${e.message}';
      return result;
    } on HttpException catch (e) {
      result['error'] = 'HTTP error: ${e.message}';
      return result;
    } on FormatException catch (e) {
      result['error'] = 'Invalid URL format. ${e.message}';
      return result;
    } catch (e) {
      result['error'] = 'Request failed: ${e.toString()}';
      return result;
    }
    
    if (response.statusCode >= 200 && response.statusCode < 300) {
      try {
        final responseData = json.decode(response.body);
        final dynamic rawDevice = responseData['device'] ?? responseData;
        
        // Ensure proper Map type
        final Map<String, dynamic> deviceMap = Map<String, dynamic>.from(rawDevice as Map);
        
        // device_metadata in API may be an array/object. DevicesStruct expects String.
        if (deviceMap['device_metadata'] != null && deviceMap['device_metadata'] is! String) {
          deviceMap['device_metadata'] = json.encode(deviceMap['device_metadata']);
        }
        
        // Build DevicesStruct from the API map
        final devicesStruct = DevicesStruct.fromMap(deviceMap);
        
        // Update global app state directly
        FFAppState().update(() {
          FFAppState().activePosDevice = devicesStruct;
        });
        
        result['success'] = true;
        result['deviceId'] = deviceMap['id']?.toString() ?? deviceId;
        result['deviceIdentifier'] = deviceIdentifier;
        result['isNewDevice'] = isNewDevice;
        result['device'] = deviceMap;
        
        return result;
      } catch (e) {
        result['error'] = 'Failed to parse response: ${e.toString()}';
        return result;
      }
    } else {
      try {
        final errorData = json.decode(response.body);
        result['error'] = errorData['message']?.toString() ?? 'Failed: ${response.statusCode}';
      } catch (e) {
        result['error'] = 'Failed: ${response.statusCode} - ${response.body}';
      }
      return result;
    }
  } catch (e) {
    result['error'] = e.toString();
    return result;
  }
}

