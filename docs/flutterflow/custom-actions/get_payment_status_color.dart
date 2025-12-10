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

// Begin custom function code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

/// Returns appropriate color code for purchase/payment status
/// 
/// Works for both purchase statuses and payment statuses (from purchase_payments array).
/// Both use the same status values: succeeded, pending, failed, refunded, cancelled
/// 
/// Parameters:
/// - status: The purchase or payment status string (e.g., "succeeded", "pending", "failed", "refunded")
/// 
/// Returns:
/// - Color object appropriate for the status
/// 
/// Color mapping:
/// - succeeded: Green (success) - #4CAF50
/// - pending: Orange/Amber (warning) - #FF9800
/// - failed: Red (danger/error) - #F44336
/// - refunded: Purple (refund indicator) - #9C27B0
/// - cancelled: Blue Grey (neutral/cancelled) - #607D8B
/// - unknown/null: Gray - #9E9E9E
/// 
/// Usage in FlutterFlow:
/// For purchase status: getPaymentStatusColor(purchase.status)
/// For payment status: getPaymentStatusColor(payment.status)
/// Example: Container(color: getPaymentStatusColor(purchase.status))
Color getPaymentStatusColor(String? status) {
  if (status == null || status.isEmpty) {
    return const Color(0xFF9E9E9E); // Gray for unknown
  }

  // Convert to lowercase for case-insensitive matching
  final statusLower = status.toLowerCase();

  switch (statusLower) {
    case 'succeeded':
      return const Color(0xFF4CAF50); // Green - success
    case 'pending':
      return const Color(0xFFFF9800); // Orange/Amber - warning
    case 'failed':
      return const Color(0xFFF44336); // Red - danger/error
    case 'refunded':
      return const Color(0xFF9C27B0); // Purple - refund indicator (distinct from failed)
    case 'cancelled':
      return const Color(0xFF607D8B); // Blue Grey - neutral/cancelled (distinct from failed)
    default:
      return const Color(0xFF9E9E9E); // Gray for unknown status
  }
}

/// Returns color code as hex string for purchase/payment status
/// 
/// Works for both purchase statuses and payment statuses (from purchase_payments array).
/// Both use the same status values: succeeded, pending, failed, refunded, cancelled
/// 
/// Parameters:
/// - status: The purchase or payment status string
/// 
/// Returns:
/// - Hex color string (e.g., "#4CAF50")
/// 
/// Color mapping:
/// - succeeded: #4CAF50 (Green)
/// - pending: #FF9800 (Orange)
/// - failed: #F44336 (Red)
/// - refunded: #9C27B0 (Purple)
/// - cancelled: #607D8B (Blue Grey)
/// - unknown: #9E9E9E (Gray)
/// 
/// Usage in FlutterFlow:
/// For purchase status: getPaymentStatusColorHex(purchase.status)
/// For payment status: getPaymentStatusColorHex(payment.status)
/// Example: Text('Status', style: TextStyle(color: Color(int.parse(getPaymentStatusColorHex(purchase.status).replaceFirst('#', '0xFF')))))
String getPaymentStatusColorHex(String? status) {
  if (status == null || status.isEmpty) {
    return '#9E9E9E'; // Gray for unknown
  }

  // Convert to lowercase for case-insensitive matching
  final statusLower = status.toLowerCase();

  switch (statusLower) {
    case 'succeeded':
      return '#4CAF50'; // Green - success
    case 'pending':
      return '#FF9800'; // Orange/Amber - warning
    case 'failed':
      return '#F44336'; // Red - danger/error
    case 'refunded':
      return '#9C27B0'; // Purple - refund indicator (distinct from failed)
    case 'cancelled':
      return '#607D8B'; // Blue Grey - neutral/cancelled (distinct from failed)
    default:
      return '#9E9E9E'; // Gray for unknown status
  }
}
