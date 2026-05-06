// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart'; // Imports other custom widgets
import '/custom_code/actions/index.dart'; // Imports custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom widget code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'index.dart'; // Imports other custom widgets

import '/flutter_flow/flutter_flow_widgets.dart';
import 'package:flutter/foundation.dart';
import 'dart:convert';
import 'dart:async';
import 'dart:io';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:multicast_dns/multicast_dns.dart';
// End custom code

import '/custom_code/pdm_internal_library.dart';

class PrinterDetectionManager extends StatelessWidget {
  const PrinterDetectionManager({
    super.key,
    this.width,
    this.height,
    required this.apiBaseUrl,
    required this.authToken,
    this.currentPosDeviceId,
  });

  final double? width;
  final double? height;
  final String apiBaseUrl;
  final String authToken;
  final int? currentPosDeviceId;

  @override
  Widget build(BuildContext context) {
    return PdmInternalLibrary(
      width: width,
      height: height,
      apiBaseUrl: apiBaseUrl,
      authToken: authToken,
      currentPosDeviceId: currentPosDeviceId,
    );
  }
}
