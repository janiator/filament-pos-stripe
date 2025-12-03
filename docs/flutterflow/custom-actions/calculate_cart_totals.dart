// FlutterFlow Custom Action: Calculate Cart Totals
// This action calculates all cart totals including subtotals, discounts, tax, and final total

// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import 'index.dart'; // Imports other custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

/// Calculate all cart totals
/// Returns a map with the following keys:
/// - totalLinePrice: Total of all line items (price * quantity) before discounts
/// - totalItemDiscounts: Total discounts applied to individual items
/// - totalCartDiscounts: Total discounts applied to the entire cart
/// - totalDiscount: Sum of all discounts (item + cart)
/// - subtotalExcludingTax: Total price excluding tax (line price - all discounts)
/// - totalTax: Total tax amount (25% VAT in Norway)
/// - totalCartPrice: Final total including tax and tip
Future<Map<String, int>> calculateCartTotals() async {
  final cart = FFAppState().cart;
  
  // Initialize totals
  int totalLinePrice = 0;
  int totalItemDiscounts = 0;
  int totalCartDiscounts = 0;
  
  // Calculate line items totals
  for (var item in cart.cartItems) {
    // Line price = unit price * quantity
    final linePrice = item.cartItemUnitPrice * item.cartItemQuantity;
    totalLinePrice += linePrice;
    
    // Item discount = discount amount * quantity
    final itemDiscount = (item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity;
    totalItemDiscounts += itemDiscount;
  }
  
  // Calculate cart-level discounts
  for (var discount in cart.cartDiscounts) {
    totalCartDiscounts += discount.cartDiscountAmount;
  }
  
  // Total discount = item discounts + cart discounts
  final totalDiscount = totalItemDiscounts + totalCartDiscounts;
  
  // Subtotal excluding tax = line price - all discounts
  final subtotalExcludingTax = totalLinePrice - totalDiscount;
  
  // Calculate tax (25% VAT in Norway)
  // Formula: tax = subtotal * 0.25 / 1.25 (if price includes tax)
  // Or: tax = subtotal * 0.25 (if price excludes tax)
  // Assuming prices exclude tax, we use: tax = subtotal * 0.25
  final totalTax = (subtotalExcludingTax * 0.25).round();
  
  // Total cart price = subtotal + tax + tip
  final totalCartPrice = subtotalExcludingTax + totalTax + (cart.cartTipAmount ?? 0);
  
  return {
    'totalLinePrice': totalLinePrice,
    'totalItemDiscounts': totalItemDiscounts,
    'totalCartDiscounts': totalCartDiscounts,
    'totalDiscount': totalDiscount,
    'subtotalExcludingTax': subtotalExcludingTax,
    'totalTax': totalTax,
    'totalCartPrice': totalCartPrice,
  };
}

