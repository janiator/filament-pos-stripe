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
import 'dart:convert';
import 'package:http/http.dart' as http;
// End custom code

import '/custom_code/pcm_internal_library.dart';

class ProductsCategoriesManager extends StatelessWidget {
  const ProductsCategoriesManager({
    super.key,
    this.width,
    this.height,
    required this.apiBaseUrl,
    required this.authToken,
    required this.storeSlug,
  });

  final double? width;
  final double? height;
  final String apiBaseUrl;
  final String authToken;
  final String storeSlug;

  @override
  Widget build(BuildContext context) {
    return PcmInternalLibrary(
      width: width,
      height: height,
      apiBaseUrl: apiBaseUrl,
      authToken: authToken,
      storeSlug: storeSlug,
    );
  }
}
