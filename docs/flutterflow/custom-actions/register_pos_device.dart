// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/custom_code/actions/index.dart'; // Imports other custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'package:device_info_plus/device_info_plus.dart';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:async';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

Future<dynamic> registerPosDevice(
  String apiBaseUrl,
  String authToken,
  String? deviceName,
  String? deviceMetadataJson,
) async {
  Future<String> getOrCreateLocalInstallId() async {
    const storageKey = 'pos_device_install_id_v1';
    final prefs = await SharedPreferences.getInstance();
    final existing = (prefs.getString(storageKey) ?? '').trim();
    if (existing.isNotEmpty) {
      return existing;
    }

    final newId = const Uuid().v4();
    await prefs.setString(storageKey, newId);

    return newId;
  }

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
    final localInstallId = await getOrCreateLocalInstallId();

    String platform = 'unknown';
    String deviceIdentifier = '';
    String deviceNameValue = '';
    Map deviceData = {};

    if (Platform.isIOS) {
      platform = 'ios';
      final IosDeviceInfo iosInfo = await deviceInfo.iosInfo;

      // Get identifier - may be null on simulator
      deviceIdentifier = iosInfo.identifierForVendor ?? '';

      // Fallback when iOS vendor id is unavailable.
      if (deviceIdentifier.isEmpty) {
        if (!iosInfo.isPhysicalDevice) {
          final machine = iosInfo.utsname.machine ?? 'unknown';
          final name = iosInfo.name ?? 'iOS Simulator';
          deviceIdentifier = 'ios-simulator-${machine}-${name}';
        } else {
          deviceIdentifier = 'ios-local-$localInstallId';
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
        'local_install_id': localInstallId,
        'is_physical_device': iosInfo.isPhysicalDevice,
      };
    } else if (Platform.isAndroid) {
      platform = 'android';
      final AndroidDeviceInfo androidInfo = await deviceInfo.androidInfo;

      final androidData = androidInfo.data;
      final androidIdFromData =
          (androidData['androidId'] ?? androidData['android_id'] ?? '')
              .toString()
              .trim();
      final serialFromData =
          (androidData['serialNumber'] ?? androidData['serial_number'] ?? '')
              .toString()
              .trim();
      final buildId = (androidInfo.id).toString().trim();

      // Identifier: prefer androidId/serial; avoid buildId (same on all same-OS devices).
      if (androidIdFromData.isNotEmpty &&
          androidIdFromData.toLowerCase() != 'unknown') {
        deviceIdentifier = 'android-id-$androidIdFromData';
      } else if (serialFromData.isNotEmpty &&
          serialFromData.toLowerCase() != 'unknown') {
        deviceIdentifier = 'android-serial-$serialFromData';
      } else {
        deviceIdentifier = 'android-local-$localInstallId';
      }

      if (deviceIdentifier.toLowerCase() == 'unknown') {
        deviceIdentifier = '';
      }

      // Emulator fallback - create a stable identifier
      if (!androidInfo.isPhysicalDevice) {
        if (deviceIdentifier.isEmpty || deviceIdentifier == 'unknown') {
          final model = androidInfo.model ?? 'unknown';
          final device = androidInfo.device ?? 'unknown';
          deviceIdentifier = 'android-emulator-${model}-${device}';
        }
      }

      if (deviceIdentifier.isEmpty) {
        deviceIdentifier = 'android-local-$localInstallId';
      }

      // Device name: use custom if provided; otherwise UNIQUE per install so each
      // tablet gets its own record (androidInfo.device/name are same on identical models).
      if (deviceName != null && deviceName.isNotEmpty) {
        deviceNameValue = deviceName;
      } else {
        final shortId = localInstallId.length >= 8
            ? localInstallId.substring(0, 8)
            : localInstallId;
        deviceNameValue = 'Android-$shortId';
      }

      deviceData = {
        'device_model': androidInfo.model ?? '',
        'device_brand': androidInfo.brand ?? '',
        'device_manufacturer': androidInfo.manufacturer ?? '',
        'device_product': androidInfo.product ?? '',
        'device_hardware': androidInfo.hardware ?? '',
        'system_version': androidInfo.version.release ?? '',
        'android_id': androidIdFromData,
        'build_id': buildId,
        'serial_number': serialFromData,
        'local_install_id': localInstallId,
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
      'device_name':
          deviceNameValue.isEmpty ? 'Unknown Device' : deviceNameValue,
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

    if (!baseUrl.startsWith('http://') && !baseUrl.startsWith('https://')) {
      result['error'] = 'Invalid API URL: Must start with http:// or https://';
      return result;
    }

    if (baseUrl.endsWith('/')) {
      baseUrl = baseUrl.substring(0, baseUrl.length - 1);
    }

    if (!baseUrl.endsWith('/api') && !baseUrl.contains('/api/')) {
      baseUrl = '$baseUrl/api';
    } else if (baseUrl.endsWith('/api/')) {
      baseUrl = baseUrl.substring(0, baseUrl.length - 5) + '/api';
    }

    // Single idempotent register endpoint: server keys by device_name so each
    // tablet gets its own record (no GET/match by device_identifier).
    final registerUrl = '$baseUrl/pos-devices/register';

    try {
      final uri = Uri.parse(registerUrl);
      if (!uri.hasScheme || !uri.hasAuthority) {
        result['error'] = 'Invalid URL format: $registerUrl';
        return result;
      }
    } catch (e) {
      result['error'] = 'Invalid URL format: $registerUrl. ${e.toString()}';
      return result;
    }

    http.Response response;
    try {
      response = await http
          .post(
            Uri.parse(registerUrl),
            headers: headers,
            body: json.encode(requestBody),
          )
          .timeout(
            const Duration(seconds: 30),
            onTimeout: () {
              throw Exception('Request timeout: Could not register device');
            },
          );
    } on SocketException catch (e) {
      result['error'] =
          'Network error: Unable to connect to server. Please check your internet connection and API URL. ${e.message}';
      return result;
    } on HttpException catch (e) {
      result['error'] = 'HTTP error: ${e.message}';
      return result;
    } on FormatException catch (e) {
      result['error'] = 'Invalid URL format: $registerUrl. ${e.message}';
      return result;
    } catch (e) {
      result['error'] = 'Connection failed: ${e.toString()}';
      return result;
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      try {
        final responseData = json.decode(response.body);
        final dynamic rawDevice = responseData['device'] ?? responseData;
        final isNewDevice = responseData['is_new_device'] == true;

        final Map<String, dynamic> deviceMap =
            Map<String, dynamic>.from(rawDevice as Map);

        if (deviceMap['device_metadata'] != null &&
            deviceMap['device_metadata'] is! String) {
          deviceMap['device_metadata'] =
              json.encode(deviceMap['device_metadata']);
        }

        final devicesStruct = DevicesStruct.fromMap(deviceMap);

        FFAppState().update(() {
          FFAppState().activePosDevice = devicesStruct;
        });

        result['success'] = true;
        result['deviceId'] = deviceMap['id']?.toString() ?? '';
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
        result['error'] = errorData['message']?.toString() ??
            'Failed: ${response.statusCode}';
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