// FlutterFlow Custom Action: Complete Deferred Payment
// 
// This action completes payment for a deferred purchase (payment on pickup).
// It updates the charge status and generates a sales receipt.
//
// IMPORTANT: FlutterFlow requires Future<dynamic> as return type
//
// Function signature (update in FlutterFlow):
// Future<dynamic> completeDeferredPayment(
//   int chargeId,
//   String paymentMethodCode,
//   String apiBaseUrl,
//   String authToken,
//   String? paymentIntentId,  // Optional, for Stripe payments
//   String? additionalMetadataJson,  // Optional
// ) async

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
    // Validate charge ID
    if (chargeId <= 0) {
      return {
        'success': false,
        'message': 'Invalid charge ID',
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
    
    // Parse additional metadata from JSON string
    Map<String, dynamic> metadata = {};
    if (additionalMetadataJson != null && additionalMetadataJson.isNotEmpty) {
      try {
        metadata = jsonDecode(additionalMetadataJson) as Map<String, dynamic>;
      } catch (e) {
        // If JSON parsing fails, use empty map
        metadata = {};
      }
    }
    
    // Add payment intent ID if provided (for Stripe payments)
    if (paymentIntentId != null && paymentIntentId.isNotEmpty) {
      metadata['payment_intent_id'] = paymentIntentId;
    }
    
    // Build request body
    final requestBody = <String, dynamic>{
      'payment_method_code': paymentMethodCode,
    };
    
    // Add metadata if not empty
    if (metadata.isNotEmpty) {
      requestBody['metadata'] = metadata;
    }
    
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
      return {
        'success': responseData['success'] ?? true,
        'data': responseData['data'],
        'message': responseData['message'] ?? 'Payment completed successfully',
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
      'message': 'Error completing payment: ${e.toString()}',
      'error': e.toString(),
    };
  }
}
