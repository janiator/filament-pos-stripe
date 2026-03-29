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

// FlutterFlow Custom Action: Create and Process Terminal Payment
//
// This action currently implements Stripe Terminal flow.
// For dual-provider support, keep Stripe behavior and add provider dispatch:
// - provider = "stripe"   -> existing payment-intent + SDK flow
// - provider = "verifone" -> call /api/verifone/stores/{store}/payments, then poll
//   /api/verifone/stores/{store}/payments/{serviceId}/status until final status.
//
// Return contract should be compatible with paymentFinishedCallback:
// {
//   success: bool,
//   provider: "stripe" | "verifone",
//   status: "pending" | "in_progress" | "succeeded" | "failed" | "canceled" | "unknown",
//   paymentIntentId: string?,               // Stripe only
//   providerPaymentReference: string?,      // Verifone reference
//   providerTransactionId: string?,         // Verifone transaction id
//   serviceId: string?,                     // Verifone tracking id
//   error: string?,
//   errorCode: string?,
//   statusCode: int?,
// }
//
// completePosPurchase should then receive provider metadata and include it under
// requestBody.metadata when calling POST /api/purchases.
//
// Function signature (update in FlutterFlow):
// Future<CreateTerminalPaymentIntentResponseStruct> createAndProcessTerminalPayment(
//   int amount,  // Amount in øre
//   String apiBaseUrl,
//   String authToken,
//   String storeSlug,
//   String? description,  // Optional payment description
//   String provider,       // "stripe" or "verifone"
//   String? verifoneTerminalPoiid,
//   int pollIntervalMs,
//   int maxPollSeconds,
// ) async

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:mek_stripe_terminal/mek_stripe_terminal.dart';
import '/custom_code/stripe_terminal_singleton.dart';

CreateTerminalPaymentIntentResponseStruct _normalizeTerminalPaymentResult(
  dynamic result, {
  required String defaultProvider,
}) {
  final source =
      result is Map<String, dynamic> ? result : <String, dynamic>{};
  final success = source['success'] == true;
  final provider = (source['provider'] ?? defaultProvider).toString();
  final status = (source['status'] ?? (success ? 'succeeded' : 'failed'))
      .toString()
      .toLowerCase();

  return CreateTerminalPaymentIntentResponseStruct.fromMap({
    'success': success,
    'provider': provider,
    'status': status,
    'message': source['message']?.toString() ?? '',
    'paymentIntentId': source['paymentIntentId']?.toString(),
    'providerPaymentReference': source['providerPaymentReference']?.toString(),
    'providerTransactionId': source['providerTransactionId']?.toString(),
    'serviceId': source['serviceId']?.toString(),
    'error': source['error']?.toString(),
    'errorCode': source['errorCode']?.toString(),
    'statusCode': source['statusCode'],
  });
}

Future<CreateTerminalPaymentIntentResponseStruct> createAndProcessTerminalPayment(
  int amount,
  String apiBaseUrl,
  String authToken,
  String storeSlug,
  String? description,
  String provider,
  String? verifoneTerminalPoiid, // POIID
  int pollIntervalMs,
  int maxPollSeconds,
) async {
  final providerValue = provider.trim().toLowerCase();

  try {
    if (amount <= 0) {
      return _normalizeTerminalPaymentResult(
        {
          'success': false,
          'status': 'failed',
          'message': 'Amount must be greater than zero',
        },
        defaultProvider: providerValue == 'verifone' ? 'verifone' : 'stripe',
      );
    }
    
    if (apiBaseUrl.isEmpty) {
      return _normalizeTerminalPaymentResult(
        {
          'success': false,
          'status': 'failed',
          'message': 'API base URL is missing',
        },
        defaultProvider: providerValue == 'verifone' ? 'verifone' : 'stripe',
      );
    }
    
    if (authToken.isEmpty) {
      return _normalizeTerminalPaymentResult(
        {
          'success': false,
          'status': 'failed',
          'message': 'Authentication token is missing. Please log in.',
        },
        defaultProvider: providerValue == 'verifone' ? 'verifone' : 'stripe',
      );
    }
    
    if (storeSlug.isEmpty) {
      return _normalizeTerminalPaymentResult(
        {
          'success': false,
          'status': 'failed',
          'message': 'Store slug is missing',
        },
        defaultProvider: providerValue == 'verifone' ? 'verifone' : 'stripe',
      );
    }

    if (providerValue == 'verifone') {
      if (verifoneTerminalPoiid == null ||
          verifoneTerminalPoiid.trim().isEmpty) {
        return _normalizeTerminalPaymentResult(
          {
            'success': false,
            'provider': 'verifone',
            'status': 'failed',
            'message': 'Verifone terminal POIID is required',
          },
          defaultProvider: 'verifone',
        );
      }

      final result = await _processVerifoneTerminalPayment(
        amount: amount,
        apiBaseUrl: apiBaseUrl,
        authToken: authToken,
        storeSlug: storeSlug,
        description: description,
        terminalPoiid: verifoneTerminalPoiid.trim(),
        pollIntervalMs: pollIntervalMs <= 0 ? 1500 : pollIntervalMs,
        maxPollSeconds: maxPollSeconds <= 0 ? 90 : maxPollSeconds,
      );

      return _normalizeTerminalPaymentResult(
        result,
        defaultProvider: 'verifone',
      );
    }

    final result = await _processStripeTerminalPayment(
      amount: amount,
      apiBaseUrl: apiBaseUrl,
      authToken: authToken,
      storeSlug: storeSlug,
      description: description,
    );

    return _normalizeTerminalPaymentResult(
      result,
      defaultProvider: 'stripe',
    );
  } catch (e) {
    final msg = e.toString();
    final isNoActiveReader = msg.toLowerCase().contains('no active reader');

    if (isNoActiveReader) {
      FFAppState().update(() {
        FFAppState().stripeReaderConnected = false;
        FFAppState().stripeReaderStatus = 'Terminal frakoblet';
      });
    }

    return _normalizeTerminalPaymentResult(
      {
        'success': false,
        'status': 'failed',
        'message': 'Error creating and processing terminal payment: $msg',
        'error': msg,
      },
      defaultProvider: providerValue == 'verifone' ? 'verifone' : 'stripe',
    );
  }
}

Future<dynamic> _processStripeTerminalPayment({
  required int amount,
  required String apiBaseUrl,
  required String authToken,
  required String storeSlug,
  required String? description,
}) async {
  FFAppState().update(() {
    FFAppState().stripeReaderStatus = 'Oppretter betaling…';
  });

  final paymentIntentResponse = await http.post(
    Uri.parse('$apiBaseUrl/api/stores/$storeSlug/terminal/payment-intents'),
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $authToken',
      'Accept': 'application/json',
    },
    body: jsonEncode({
      'amount': amount,
      'currency': 'nok',
      'description': description ?? 'POS Purchase',
    }),
  );

  if (paymentIntentResponse.statusCode < 200 ||
      paymentIntentResponse.statusCode >= 300) {
    final errorData =
        jsonDecode(paymentIntentResponse.body) as Map<String, dynamic>;
    return {
      'success': false,
      'provider': 'stripe',
      'status': 'failed',
      'message': errorData['message'] ?? 'Failed to create payment intent',
      'statusCode': paymentIntentResponse.statusCode,
    };
  }

  final paymentIntentData =
      jsonDecode(paymentIntentResponse.body) as Map<String, dynamic>;
  final clientSecret = paymentIntentData['client_secret'] as String?;
  final paymentIntentId = paymentIntentData['id'] as String?;

  if (clientSecret == null || clientSecret.isEmpty) {
    return {
      'success': false,
      'provider': 'stripe',
      'status': 'failed',
      'message': 'Payment intent created but client secret is missing',
    };
  }

  if (paymentIntentId == null || paymentIntentId.isEmpty) {
    return {
      'success': false,
      'provider': 'stripe',
      'status': 'failed',
      'message': 'Payment intent created but ID is missing',
    };
  }

  try {
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Henter betaling…';
    });

    final pi = await Terminal.instance.retrievePaymentIntent(clientSecret);

    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Venter på kort…';
    });

    final collectFuture = Terminal.instance.collectPaymentMethod(pi);
    StripeTerminalSingleton.instance.currentPaymentCollection = collectFuture;

    final collected = await collectFuture;
    StripeTerminalSingleton.instance.currentPaymentCollection = null;

    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Behandler betaling…';
    });

    final confirmed = await Terminal.instance.confirmPaymentIntent(collected);

    if (confirmed.id == null || confirmed.id!.isEmpty) {
      return {
        'success': false,
        'provider': 'stripe',
        'status': 'failed',
        'message': 'Payment confirmed but payment intent ID is missing',
      };
    }

    final statusString = confirmed.status.toString().toLowerCase();
    if (!statusString.contains('succeeded')) {
      return {
        'success': false,
        'provider': 'stripe',
        'status': 'failed',
        'message': 'Payment intent status is not succeeded: ${confirmed.status}',
      };
    }

    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Betaling vellykket';
    });

    return {
      'success': true,
      'provider': 'stripe',
      'status': 'succeeded',
      'paymentIntentId': confirmed.id!,
      'providerPaymentReference': confirmed.id!,
      'message': 'Payment processed successfully',
    };
  } on TerminalException catch (e) {
    StripeTerminalSingleton.instance.currentPaymentCollection = null;

    final isCanceled = e.code == TerminalExceptionCode.canceled;
    FFAppState().update(() {
      FFAppState().stripeReaderStatus =
          isCanceled ? 'Betaling avbrutt' : 'Betalingsfeil: ${e.message}';
    });

    return {
      'success': false,
      'provider': 'stripe',
      'status': isCanceled ? 'canceled' : 'failed',
      'message':
          isCanceled ? 'Payment was canceled' : 'Payment failed: ${e.message}',
      'error': e.toString(),
      'errorCode': e.code.toString(),
    };
  } catch (e) {
    StripeTerminalSingleton.instance.currentPaymentCollection = null;
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Betalingsfeil: $e';
    });

    return {
      'success': false,
      'provider': 'stripe',
      'status': 'failed',
      'message': 'Error processing terminal payment: $e',
      'error': e.toString(),
    };
  }
}

Future<dynamic> _processVerifoneTerminalPayment({
  required int amount,
  required String apiBaseUrl,
  required String authToken,
  required String storeSlug,
  required String? description,
  required String terminalPoiid,
  required int pollIntervalMs,
  required int maxPollSeconds,
}) async {
  FFAppState().update(() {
    FFAppState().stripeReaderStatus = 'Sender betaling...';
  });

  final startResponse = await http.post(
    Uri.parse('$apiBaseUrl/api/verifone/stores/$storeSlug/payments'),
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $authToken',
      'Accept': 'application/json',
    },
    body: jsonEncode({
      'terminal_poiid': terminalPoiid,
      'amount': amount,
      'currency': 'NOK',
      'description': description ?? 'POS Purchase',
    }),
  );

  if (startResponse.statusCode < 200 || startResponse.statusCode >= 300) {
    final errorData = jsonDecode(startResponse.body) as Map<String, dynamic>;
    return {
      'success': false,
      'provider': 'verifone',
      'status': 'failed',
      'message': errorData['message'] ?? 'Failed to start Verifone payment',
      'statusCode': startResponse.statusCode,
    };
  }

  final startData = jsonDecode(startResponse.body) as Map<String, dynamic>;
  final serviceId = startData['serviceId']?.toString();

  if (serviceId == null || serviceId.isEmpty) {
    return {
      'success': false,
      'provider': 'verifone',
      'status': 'failed',
      'message': 'Verifone payment started but serviceId is missing',
      'raw': startData,
    };
  }

  FFAppState().update(() {
    FFAppState().stripeReaderStatus = 'Venter på kort...';
  });

  final maxAttempts =
      ((maxPollSeconds * 1000) / pollIntervalMs).ceil().clamp(1, 200);

  for (var attempt = 0; attempt < maxAttempts; attempt++) {
    final statusResponse = await http.post(
      Uri.parse(
          '$apiBaseUrl/api/verifone/stores/$storeSlug/payments/$serviceId/status'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
      body: jsonEncode({}),
    );

    Map<String, dynamic> statusData = {};
    try {
      statusData = jsonDecode(statusResponse.body) as Map<String, dynamic>;
    } catch (_) {
      statusData = {};
    }

    if (statusResponse.statusCode < 200 || statusResponse.statusCode >= 300) {
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Betalingsfeil';
      });
      return {
        'success': false,
        'provider': 'verifone',
        'status': 'failed',
        'serviceId': serviceId,
        'message': statusData['message']?.toString() ??
            'Failed to poll Verifone payment status',
        'statusCode': statusResponse.statusCode,
      };
    }

    final status = (statusData['status'] ?? 'unknown').toString();

    if (status == 'succeeded') {
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Betaling vellykket';
      });
      return {
        'success': true,
        'provider': 'verifone',
        'status': 'succeeded',
        'serviceId': serviceId,
        'providerPaymentReference':
            statusData['providerPaymentReference']?.toString(),
        'providerTransactionId':
            statusData['providerTransactionId']?.toString(),
        'message': 'Verifone payment processed successfully',
      };
    }

    if (status == 'failed' || status == 'canceled') {
      FFAppState().update(() {
        FFAppState().stripeReaderStatus =
            status == 'canceled' ? 'Betaling avbrutt' : 'Betalingsfeil';
      });
      return {
        'success': false,
        'provider': 'verifone',
        'status': status,
        'serviceId': serviceId,
        'providerPaymentReference':
            statusData['providerPaymentReference']?.toString(),
        'providerTransactionId':
            statusData['providerTransactionId']?.toString(),
        'message': statusData['message']?.toString() ??
            'Verifone payment ended with status: $status',
      };
    }

    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Godkjenner...';
    });

    await Future.delayed(Duration(milliseconds: pollIntervalMs));
  }

  return {
    'success': false,
    'provider': 'verifone',
    'status': 'failed',
    'serviceId': serviceId,
    'message': 'Timed out while waiting for Verifone terminal status',
  };
}

