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

// FlutterFlow Custom Action: Complete Deferred Payment Purchase
// 
// This action completes payment for a purchase that was created with deferred payment
// (e.g., dry cleaning, payment on pickup).
//
// It calls the /api/purchases/{id}/complete-payment endpoint to:
// 1. Process payment based on the provided payment method
// 2. Update charge status from 'pending' to 'succeeded'
// 3. Generate a sales receipt (replacing the delivery receipt)
// 4. Update POS session totals
// 5. Open cash drawer (for cash payments)
// 6. Automatically print receipt (if configured)
//
// Supports:
// - Cash payments (no payment intent required)
// - Stripe card payments (requires payment_intent_id)
// - Other Stripe payment methods (requires payment_intent_id)
//
// Compliance: Automatically uses current active POS device/session from app state
// to ensure proper audit trail and session tracking.
//
// IMPORTANT: FlutterFlow requires Future<dynamic> as return type

import 'dart:convert';
import 'package:http/http.dart' as http;

Future<dynamic> completeDeferredPayment(
  int chargeId,
  String paymentMethodCode,
  String apiBaseUrl,
  String authToken,
  String? paymentIntentId,
  String? additionalMetadataJson,
) async {
  try {
    // Parse additional metadata from JSON string
    Map<String, dynamic> additionalMetadata = {};
    if (additionalMetadataJson != null && additionalMetadataJson.isNotEmpty) {
      try {
        additionalMetadata =
            jsonDecode(additionalMetadataJson) as Map<String, dynamic>;
      } catch (e) {
        // If JSON parsing fails, use empty map
        additionalMetadata = {};
      }
    }

    // Validate charge ID
    if (chargeId <= 0) {
      return {
        'success': false,
        'message': 'Invalid purchase/charge ID',
      };
    }
    
    // Validate API URL and auth token
    if (apiBaseUrl.isEmpty) {
      return {
        'success': false,
        'message': 'API base URL is missing',
      };
    }
    
    if (authToken.isEmpty) {
      return {
        'success': false,
        'message': 'Authentication token is missing. Please log in.',
      };
    }
    
    if (paymentMethodCode.isEmpty) {
      return {
        'success': false,
        'message': 'Payment method code is required',
      };
    }

    // Build metadata object
    final metadata = <String, dynamic>{
      ...?additionalMetadata,
    };
    
    // Add payment intent ID if provided (required for Stripe payments)
    if (paymentIntentId != null && paymentIntentId.isNotEmpty) {
      metadata['payment_intent_id'] = paymentIntentId;
    }
    
    // Get current POS device/session from app state for compliance
    // This ensures the payment is completed on the current active session
    // Priority: pos_device_id (auto-detects current session) > pos_session_id > original session
    int? posDeviceId;
    int? posSessionId;
    
    try {
      final appState = FFAppState();
      
      // Try to get device ID from active POS device (preferred for compliance)
      // This will auto-detect the current active session for that device
      try {
        final deviceId = appState.activePosDevice.id;
        if (deviceId != null && deviceId > 0) {
          posDeviceId = deviceId;
        }
      } catch (e) {
        // Device ID not available, continue
      }
      
      // Try to get session ID from current POS session (fallback if device ID not available)
      if (posDeviceId == null) {
        try {
          final sessionId = appState.currentPosSession.id;
          if (sessionId != null && sessionId > 0) {
            posSessionId = sessionId;
          }
        } catch (e) {
          // Session ID not available, continue
        }
      }
    } catch (e) {
      // If app state is not available, continue without device/session ID
      // The backend will fall back to the original session where the deferred payment was created
      print('Warning: Could not get POS device/session from app state: $e');
    }
    
    // Build request body
    final requestBody = <String, dynamic>{
      'payment_method_code': paymentMethodCode,
      // Add pos_device_id if available (recommended for compliance - auto-detects current active session)
      if (posDeviceId != null) 'pos_device_id': posDeviceId,
      // OR add pos_session_id if device ID not available but session ID is
      if (posDeviceId == null && posSessionId != null) 'pos_session_id': posSessionId,
      if (metadata.isNotEmpty) 'metadata': metadata,
    };
    
    // Make API request
    final response = await http.post(
      Uri.parse('$apiBaseUrl/api/purchases/$chargeId/complete-payment'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
      body: jsonEncode(requestBody),
    );
    
    // Parse response
    final responseData = jsonDecode(response.body) as Map<String, dynamic>;
    
    // Check HTTP status code
    if (response.statusCode >= 200 && response.statusCode < 300) {
      // Success
      // The response will have:
      // - charge.status = "succeeded" (changed from "pending")
      // - charge.paid = true
      // - charge.paid_at = timestamp
      // - receipt.receipt_type = "sales" (replaced delivery receipt)
      // - receipt.receipt_number format: "{store_id}-S-{number}" (S = Sales)
      return {
        'success': responseData['success'] ?? true,
        'data': responseData['data'],
        'message': responseData['message'] ?? 'Payment completed successfully',
        'statusCode': response.statusCode,
      };
    } else {
      // Error
      return {
        'success': false,
        'message': responseData['message'] ?? 'Payment completion failed',
        'errors': responseData['errors'],
        'statusCode': response.statusCode,
      };
    }
  } catch (e) {
    // Handle exceptions
    return {
      'success': false,
      'message': 'Error completing deferred payment: ${e.toString()}',
      'error': e.toString(),
      'statusCode': 0, // 0 indicates exception occurred before HTTP request
    };
  }
}
