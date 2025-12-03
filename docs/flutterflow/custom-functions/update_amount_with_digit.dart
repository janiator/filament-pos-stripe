// FlutterFlow Custom Function: Update Amount With Digit
// This function handles numeric input for amounts or percentages

import 'dart:convert';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:timeago/timeago.dart' as timeago;
import '/flutter_flow/custom_functions.dart';
import '/flutter_flow/lat_lng.dart';
import '/flutter_flow/place.dart';
import '/flutter_flow/uploaded_file.dart';
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/auth/custom_auth/auth_util.dart';

String? updateAmountWithDigit(
  String currentAmount,
  String newDigit,
  bool usePercent,
  int? itemPriceCents, // Nullable: if null, no discount limit is applied
) {
  /// MODIFY CODE ONLY BELOW THIS LINE
  
  String s = (currentAmount).trim();
  String d = (newDigit).trim();
  if (s.isEmpty) s = '0';

  // Only accept a single character: 0-9 or '.'
  if (!RegExp(r'^[0-9.]$').hasMatch(d)) {
    return s;
  }

  // Handle decimal point
  if (d == '.') {
    if (s.contains('.')) {
      // already has a decimal point -> ignore
      return s;
    }
    // add first decimal point
    return s + '.';
  }

  // From here: d is a digit ('0'..'9')
  final hasDot = s.contains('.');

  if (!hasDot) {
    // Integer part only
    // Avoid leading zeros like '00' -> keep '0' or replace with non-zero
    if (s == '0') {
      return d == '0' ? '0' : d;
    }
    
    // Build new value
    final newValue = s + d;
    
    // Validation checks
    if (usePercent) {
      // Percentage mode: check if value exceeds 100
      final numValue = double.tryParse(newValue) ?? 0;
      if (numValue > 100) {
        // Value exceeds 100, return current value (don't add digit)
        return s;
      }
    } else {
      // Amount mode: check if discount exceeds item price (only if itemPriceCents is provided)
      // If itemPriceCents is null, no limit is applied
      if (itemPriceCents != null && itemPriceCents > 0) {
        // Convert newValue (kroner) to øre
        final newValueInOre = (double.tryParse(newValue) ?? 0) * 100;
        if (newValueInOre > itemPriceCents) {
          // Discount exceeds item price, return current value (don't add digit)
          return s;
        }
      }
      // If itemPriceCents is null, allow any amount (no limit)
    }
    
    return newValue;
  } else {
    // Has decimals: limit to 2 decimal digits
    final dotIdx = s.indexOf('.');
    final decimals = s.length - dotIdx - 1;
    if (decimals >= 2) {
      // already at max precision
      return s;
    }
    
    // Build new value with decimal
    final newValue = s + d;
    
    // Validation checks
    if (usePercent) {
      // Percentage mode: check if value exceeds 100
      final numValue = double.tryParse(newValue) ?? 0;
      if (numValue > 100) {
        // Value exceeds 100, return current value (don't add digit)
        return s;
      }
    } else {
      // Amount mode: check if discount exceeds item price (only if itemPriceCents is provided)
      // If itemPriceCents is null, no limit is applied
      if (itemPriceCents != null && itemPriceCents > 0) {
        // Convert newValue (kroner) to øre
        final newValueInOre = (double.tryParse(newValue) ?? 0) * 100;
        if (newValueInOre > itemPriceCents) {
          // Discount exceeds item price, return current value (don't add digit)
          return s;
        }
      }
      // If itemPriceCents is null, allow any amount (no limit)
    }
    
    return newValue;
  }

  /// MODIFY CODE ONLY ABOVE THIS LINE
}

