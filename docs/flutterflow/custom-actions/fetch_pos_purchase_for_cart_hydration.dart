// FlutterFlow Custom Action: Fetch POS purchase JSON (parked deferred / cart hydration)
//
// Calls GET /api/purchases/{id} and returns the parsed body so you can map
// `purchase.purchase_items`, discounts, and totals into your cart structs
// before calling completeDeferredPayment with cartJson.
//
// IMPORTANT: FlutterFlow requires Future<dynamic> as return type

import 'dart:convert';
import 'package:http/http.dart' as http;

Future<dynamic> fetchPosPurchaseForCartHydration(
  int purchaseId,
  String apiBaseUrl,
  String authToken,
) async {
  try {
    if (purchaseId <= 0) {
      return {
        'success': false,
        'message': 'Invalid purchase ID',
      };
    }
    if (apiBaseUrl.isEmpty || authToken.isEmpty) {
      return {
        'success': false,
        'message': 'API base URL or auth token is missing',
      };
    }

    final base = apiBaseUrl.endsWith('/') ? apiBaseUrl.substring(0, apiBaseUrl.length - 1) : apiBaseUrl;
    final response = await http.get(
      Uri.parse('$base/api/purchases/$purchaseId'),
      headers: {
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
    );

    final responseData = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return {
        'success': true,
        'purchase': responseData['purchase'],
        'statusCode': response.statusCode,
      };
    }

    return {
      'success': false,
      'message': responseData['message']?.toString() ?? 'Failed to load purchase',
      'statusCode': response.statusCode,
    };
  } catch (e) {
    return {
      'success': false,
      'message': 'Error loading purchase: ${e.toString()}',
      'statusCode': 0,
    };
  }
}
