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
// This action creates a Stripe Terminal payment intent and processes the payment.
// It returns the payment intent ID which can be used with completePosPurchase.
//
// Function signature (update in FlutterFlow):
// Future<dynamic> createAndProcessTerminalPayment(
//   int amount,  // Amount in øre
//   String apiBaseUrl,
//   String authToken,
//   String storeSlug,
//   String? description,  // Optional payment description
// ) async

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:mek_stripe_terminal/mek_stripe_terminal.dart';
import '/custom_code/stripe_terminal_singleton.dart';

Future<dynamic> createAndProcessTerminalPayment(
  int amount,
  String apiBaseUrl,
  String authToken,
  String storeSlug,
  String? description,
) async {
  try {
    // Validate inputs
    if (amount <= 0) {
      return {
        'success': false,
        'message': 'Amount must be greater than zero',
      };
    }
    
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
    
    if (storeSlug.isEmpty) {
      return {
        'success': false,
        'message': 'Store slug is missing',
      };
    }
    
    // Step 1: Create payment intent
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
      final errorData = jsonDecode(paymentIntentResponse.body) as Map<String, dynamic>;
      return {
        'success': false,
        'message': errorData['message'] ?? 'Failed to create payment intent',
        'statusCode': paymentIntentResponse.statusCode,
      };
    }
    
    final paymentIntentData = jsonDecode(paymentIntentResponse.body) as Map<String, dynamic>;
    final clientSecret = paymentIntentData['client_secret'] as String?;
    final paymentIntentId = paymentIntentData['id'] as String?;
    
    if (clientSecret == null || clientSecret.isEmpty) {
      return {
        'success': false,
        'message': 'Payment intent created but client secret is missing',
      };
    }
    
    if (paymentIntentId == null || paymentIntentId.isEmpty) {
      return {
        'success': false,
        'message': 'Payment intent created but ID is missing',
      };
    }
    
    // Step 2: Process terminal payment (collect and confirm)
    try {
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Henter betaling…';
      });
      
      final pi = await Terminal.instance.retrievePaymentIntent(clientSecret);
      
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Venter på kort…';
      });
      
      // Start collection and store the CancelableFuture so we can cancel it
      final collectFuture = Terminal.instance.collectPaymentMethod(pi);
      StripeTerminalSingleton.instance.currentPaymentCollection = collectFuture;
      
      final collected = await collectFuture;
      
      // Collection is done; no longer cancellable
      StripeTerminalSingleton.instance.currentPaymentCollection = null;
      
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Behandler betaling…';
      });
      
      final confirmed = await Terminal.instance.confirmPaymentIntent(collected);
      
      if (confirmed.id == null || confirmed.id!.isEmpty) {
        return {
          'success': false,
          'message': 'Payment confirmed but payment intent ID is missing',
        };
      }
      
      // Verify payment intent status
      // Note: confirmed.status is a PaymentIntentStatus enum, not a string
      // The enum value is PaymentIntentStatus.succeeded when payment succeeds
      // Convert to string and check if it contains 'succeeded' (enum.toString() returns "PaymentIntentStatus.succeeded")
      final statusString = confirmed.status.toString().toLowerCase();
      if (!statusString.contains('succeeded')) {
        return {
          'success': false,
          'message': 'Payment intent status is not succeeded: ${confirmed.status}',
        };
      }
      
      FFAppState().update(() {
        FFAppState().stripeReaderStatus = 'Betaling vellykket';
      });
      
      // Return success with payment intent ID
      return {
        'success': true,
        'paymentIntentId': confirmed.id!,
        'message': 'Payment processed successfully',
      };
      
    } on TerminalException catch (e) {
      StripeTerminalSingleton.instance.currentPaymentCollection = null;
      
      FFAppState().update(() {
        if (e.code == TerminalExceptionCode.canceled) {
          FFAppState().stripeReaderStatus = 'Betaling avbrutt';
        } else {
          FFAppState().stripeReaderStatus = 'Betalingsfeil: ${e.message}';
        }
      });
      
      return {
        'success': false,
        'message': e.code == TerminalExceptionCode.canceled
            ? 'Payment was canceled'
            : 'Payment failed: ${e.message}',
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
        'message': 'Error processing terminal payment: ${e.toString()}',
        'error': e.toString(),
      };
    }
  } catch (e) {
    // Handle exceptions
    return {
      'success': false,
      'message': 'Error creating and processing terminal payment: ${e.toString()}',
      'error': e.toString(),
    };
  }
}

