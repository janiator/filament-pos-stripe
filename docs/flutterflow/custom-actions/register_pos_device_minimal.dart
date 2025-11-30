// MINIMAL TEST VERSION - Use this first to verify FlutterFlow accepts it
// If this works, then use the full version

import 'package:device_info_plus/device_info_plus.dart';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'dart:convert';

Future<Map> registerPosDevice(String apiBaseUrl, String authToken, String deviceName, Map deviceMetadata) async {
  return {
    'success': true,
    'deviceId': '123',
    'deviceIdentifier': 'test',
    'isNewDevice': true,
    'error': '',
  };
}

