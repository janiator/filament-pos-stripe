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
      deviceIdentifier = iosInfo.identifierForVendor ?? '';
      // Use provided deviceName if given, otherwise use device's name from device_info_plus
      deviceNameValue = (deviceName != null && deviceName.isNotEmpty) ? deviceName : iosInfo.name;
      deviceData = {
        'device_model': iosInfo.model,
        'machine_identifier': iosInfo.utsname.machine,
        'system_name': iosInfo.systemName,
        'system_version': iosInfo.systemVersion,
        'vendor_identifier': iosInfo.identifierForVendor,
      };
    } else if (Platform.isAndroid) {
      platform = 'android';
      final AndroidDeviceInfo androidInfo = await deviceInfo.androidInfo;
      deviceIdentifier = androidInfo.id;
      // Use provided deviceName if given, otherwise use device's name from device_info_plus
      deviceNameValue = (deviceName != null && deviceName.isNotEmpty) ? deviceName : androidInfo.device;
      deviceData = {
        'device_model': androidInfo.model,
        'device_brand': androidInfo.brand,
        'device_manufacturer': androidInfo.manufacturer,
        'device_product': androidInfo.product,
        'device_hardware': androidInfo.hardware,
        'system_version': androidInfo.version.release,
        'android_id': androidInfo.id,
        // Note: serialNumber is not available in all versions of device_info_plus
        // 'serial_number': androidInfo.serialNumber, // Removed - not available
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
    
    final checkResponse = await http.get(
      Uri.parse('$apiBaseUrl/pos-devices'),
      headers: headers,
    );
    
    bool isNewDevice = true;
    String deviceId = '';
    
    if (checkResponse.statusCode == 200) {
      final devicesData = json.decode(checkResponse.body);
      final devices = devicesData['devices'] as List?;
      
      if (devices != null) {
        for (var device in devices) {
          if (device['device_identifier'] == deviceIdentifier) {
            isNewDevice = false;
            deviceId = device['id'].toString();
            break;
          }
        }
      }
    }
    
    http.Response response;
    
    if (isNewDevice) {
      response = await http.post(
        Uri.parse('$apiBaseUrl/pos-devices'),
        headers: headers,
        body: json.encode(requestBody),
      );
    } else {
      final Map updateBody = {
        'device_name': deviceNameValue,
        ...deviceData,
      };
      if (deviceMetadata.isNotEmpty) {
        updateBody['device_metadata'] = deviceMetadata;
      }
      
      response = await http.patch(
        Uri.parse('$apiBaseUrl/pos-devices/$deviceId'),
        headers: headers,
        body: json.encode(updateBody),
      );
    }
    
    if (response.statusCode >= 200 && response.statusCode < 300) {
      final responseData = json.decode(response.body);
      final device = responseData['device'] ?? responseData;
      
      result['success'] = true;
      result['deviceId'] = device['id']?.toString() ?? deviceId;
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
