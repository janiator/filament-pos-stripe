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
      deviceIdentifier = iosInfo.identifierForVendor ?? '';
      if (deviceIdentifier.isEmpty) {
        if (!iosInfo.isPhysicalDevice) {
          final machine = iosInfo.utsname.machine ?? 'unknown';
          final name = iosInfo.name ?? 'iOS Simulator';
          deviceIdentifier = 'ios-simulator-${machine}-${name}';
        } else {
          deviceIdentifier = 'ios-local-$localInstallId';
        }
      }
      // Use provided deviceName if given, otherwise use device's name from device_info_plus
      deviceNameValue = (deviceName != null && deviceName.isNotEmpty) ? deviceName : iosInfo.name;
      deviceData = {
        'device_model': iosInfo.model,
        'machine_identifier': iosInfo.utsname.machine,
        'system_name': iosInfo.systemName,
        'system_version': iosInfo.systemVersion,
        'vendor_identifier': iosInfo.identifierForVendor,
        'local_install_id': localInstallId,
      };
    } else if (Platform.isAndroid) {
      platform = 'android';
      final AndroidDeviceInfo androidInfo = await deviceInfo.androidInfo;
      final androidData = androidInfo.data;
      final androidIdFromData =
          (androidData['androidId'] ?? androidData['android_id'] ?? '')
              .toString()
              .trim();
      final serialFromData = (androidData['serialNumber'] ??
              androidData['serial_number'] ??
              '')
          .toString()
          .trim();
      final buildId = (androidInfo.id).toString().trim();

      // Use provided deviceName if given, otherwise use device's name from device_info_plus
      deviceNameValue = (deviceName != null && deviceName.isNotEmpty) ? deviceName : androidInfo.device;
      final nameForId = deviceNameValue.toString().trim();

      // Use only truly per-device identifiers. Build.ID is the same for all devices
      // with the same OS build, so two identical tablets would register as one.
      if (androidIdFromData.isNotEmpty &&
          androidIdFromData.toLowerCase() != 'unknown') {
        deviceIdentifier = 'android-id-$androidIdFromData';
      } else if (serialFromData.isNotEmpty &&
          serialFromData.toLowerCase() != 'unknown') {
        deviceIdentifier = 'android-serial-$serialFromData';
      } else if (nameForId.isNotEmpty) {
        // Use device name first when unique (e.g. "POS 4" vs "POS 5") so devices register separately.
        final sanitized = nameForId.replaceAll(RegExp(r'[^a-zA-Z0-9\-_]'), '').toLowerCase();
        deviceIdentifier = sanitized.isNotEmpty
            ? 'android-name-$sanitized'
            : 'android-local-$localInstallId';
      } else {
        deviceIdentifier = 'android-local-$localInstallId';
      }
      if (deviceIdentifier.toLowerCase() == 'unknown') {
        deviceIdentifier = '';
      }
      if (deviceIdentifier.isEmpty) {
        deviceIdentifier = 'android-local-$localInstallId';
      }
      deviceData = {
        'device_model': androidInfo.model,
        'device_brand': androidInfo.brand,
        'device_manufacturer': androidInfo.manufacturer,
        'device_product': androidInfo.product,
        'device_hardware': androidInfo.hardware,
        'system_version': androidInfo.version.release,
        'android_id': androidIdFromData,
        'build_id': buildId,
        'serial_number': serialFromData,
        'local_install_id': localInstallId,
      };
    } else {
      result['error'] = 'Unsupported platform';
      return result;
    }
    
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
    
    // Add Host header if using IP address or localhost (for Herd/Valet compatibility)
    final headers = {
      'Authorization': 'Bearer $authToken',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    
    // If API URL contains IP address or localhost, add Host header
    if (apiBaseUrl.contains(RegExp(r'^\d+\.\d+\.\d+\.\d+')) || 
        apiBaseUrl.contains('localhost') || 
        apiBaseUrl.contains('127.0.0.1')) {
      headers['Host'] = 'pos-stripe.test';
    }
    
    // Use idempotent register endpoint: server keys by device_name so same name
    // updates one record, different names create separate records (fixes Android
    // tablets with same device_identifier but different names replacing each other).
    final response = await http.post(
      Uri.parse('$apiBaseUrl/pos-devices/register'),
      headers: headers,
      body: json.encode(requestBody),
    );

    if (response.statusCode >= 200 && response.statusCode < 300) {
      final responseData = json.decode(response.body);
      final device = responseData['device'] ?? responseData;
      final isNewDevice = responseData['is_new_device'] == true;

      result['success'] = true;
      result['deviceId'] = device['id']?.toString() ?? '';
      result['deviceIdentifier'] = deviceIdentifier;
      result['isNewDevice'] = isNewDevice;
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
