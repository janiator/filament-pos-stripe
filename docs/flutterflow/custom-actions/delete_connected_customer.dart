// FlutterFlow Custom Action: deleteConnectedCustomer
//
// Confirms with the user, then DELETEs `/api/customers/{id}` on pos-stripe to
// archive the customer (same auth + api host wiring as editCustomerModal).

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

Future<dynamic> deleteConnectedCustomer(
  BuildContext context,
  String apiBaseUrl,
  String? authToken,
  CustomersStruct customer,
) async {
  if (apiBaseUrl.isEmpty || (authToken ?? '').isEmpty) {
    return {
      'success': false,
      'message': 'Mangler API eller innlogging',
    };
  }

  final customerId = customer.id;
  if (customerId == null || customerId <= 0) {
    return {
      'success': false,
      'message': 'Ugyldig kunde',
    };
  }

  final confirmed = await showDialog<bool>(
    context: context,
    barrierDismissible: true,
    builder: (dialogContext) => AlertDialog(
      title: const Text('Arkivere kunde?'),
      content: const Text(
        'Kunden fjernes fra kundelisten, men beholdes for historikk og rapporter.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(dialogContext, false),
          child: const Text('Avbryt'),
        ),
        TextButton(
          onPressed: () => Navigator.pop(dialogContext, true),
          style: TextButton.styleFrom(foregroundColor: Colors.red),
          child: const Text('Arkiver'),
        ),
      ],
    ),
  );

  if (confirmed != true) {
    return {'success': false, 'message': 'cancelled'};
  }

  final base = apiBaseUrl.endsWith('/')
      ? apiBaseUrl.substring(0, apiBaseUrl.length - 1)
      : apiBaseUrl;
  final uri = Uri.parse('$base/api/customers/$customerId');

  try {
    final response = await http.delete(
      uri,
      headers: {
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode >= 200 && response.statusCode < 300) {
      _bumpListRefreshCacheKey();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Kunden er arkivert')),
        );
      }
      return {'success': true};
    }

    var message = 'Kunne ikke arkivere (${response.statusCode})';
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map && decoded['message'] != null) {
        message = decoded['message'].toString();
      } else if (decoded is Map && decoded['error'] != null) {
        message = decoded['error'].toString();
      }
    } catch (_) {}

    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    }
    return {'success': false, 'message': message};
  } catch (e) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Nettverksfeil: $e')),
      );
    }
    return {'success': false, 'message': e.toString()};
  }
}

void _bumpListRefreshCacheKey() {
  try {
    FFAppState().update(() {
      FFAppState().cacheRefreshKey = DateTime.now().microsecondsSinceEpoch
          .toString();
    });
  } catch (_) {}
}
