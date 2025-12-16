// FlutterFlow Custom Action: Update Cart Totals (with per-product tax calculation)
// This version calculates tax based on each product's tax code/rate

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

/// Get tax percentage from tax code
/// Maps tax codes to tax percentages
/// Returns default 25% if code is not recognized
double getTaxPercentageFromCode(String? taxCode) {
  if (taxCode == null || taxCode.isEmpty) {
    return 0.25; // Default 25% VAT in Norway
  }
  
  // Stripe tax codes (common ones)
  // txcd_99999999 = Standard rate (25% in Norway)
  // txcd_99999998 = Reduced rate (15% in Norway - food)
  // txcd_99999997 = Lower rate (10% in Norway - some services)
  // txcd_99999996 = Zero rate (0%)
  
  // You can also use article group codes or custom tax codes
  // Map them to appropriate percentages
  
  // For now, default to 25% unless we have a specific mapping
  // TODO: Add your tax code mappings here based on your system
  switch (taxCode.toLowerCase()) {
    case 'txcd_99999999':
    case 'standard':
    case '1': // SAF-T standard rate code
      return 0.25; // 25% standard VAT
    case 'txcd_99999998':
    case 'reduced':
    case 'food':
      return 0.15; // 15% reduced rate (food)
    case 'txcd_99999997':
    case 'lower':
    case 'service':
      return 0.10; // 10% lower rate
    case 'txcd_99999996':
    case 'zero':
    case 'exempt':
    case '0':
      return 0.0; // 0% exempt
    default:
      // Default to 25% if code is not recognized
      return 0.25;
  }
}

/// Calculate and update all cart totals with per-product tax calculation
/// This action calculates totals and stores them in the cart struct fields
/// Tax is calculated per item based on each product's tax code
Future updateCartTotals() async {
  final cart = FFAppState().cart;
  
  // Initialize totals
  int totalLinePrice = 0;
  int totalItemDiscounts = 0;
  int totalCartDiscounts = 0;
  int totalTax = 0; // Will be calculated per item
  
  // Calculate line items totals and tax per item
  for (var item in cart.cartItems) {
    // Line price = unit price * quantity
    final linePrice = item.cartItemUnitPrice * item.cartItemQuantity;
    totalLinePrice += linePrice;
    
    // Item discount = discount amount * quantity
    final itemDiscount = (item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity;
    totalItemDiscounts += itemDiscount;
    
    // Calculate item subtotal (after discount)
    final itemSubtotal = linePrice - itemDiscount;
    
    // Get tax percentage from article group code (stored when adding to cart)
    // The cartItemArticleGroupCode contains the tax code from the product
    final taxPercentage = getTaxPercentageFromCode(item.cartItemArticleGroupCode);
    
    // Calculate tax for this item
    final itemTax = (itemSubtotal * taxPercentage).round();
    totalTax += itemTax;
  }
  
  // Calculate cart-level discounts
  for (var discount in cart.cartDiscounts) {
    totalCartDiscounts += discount.cartDiscountAmount;
  }
  
  // Calculate final totals
  final totalDiscount = totalItemDiscounts + totalCartDiscounts;
  final subtotalExcludingTax = totalLinePrice - totalDiscount;
  
  // Note: totalTax is already calculated per item above
  // If you want to apply cart discounts before tax, you might need to recalculate
  // For now, we calculate tax on item subtotals, then apply cart discounts
  
  final totalCartPrice = subtotalExcludingTax + totalTax + (cart.cartTipAmount ?? 0);
  
  // Update cart with calculated totals
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      cartId: cart.cartId,
      cartPosSessionId: cart.cartPosSessionId,
      cartItems: cart.cartItems,
      cartDiscounts: cart.cartDiscounts,
      cartTipAmount: cart.cartTipAmount,
      cartCustomerId: cart.cartCustomerId,
      cartCustomerName: cart.cartCustomerName,
      cartCreatedAt: cart.cartCreatedAt,
      cartUpdatedAt: getCurrentTimestamp.toString(),
      cartMetadata: cart.cartMetadata,
      // Store calculated totals
      cartTotalLinePrice: totalLinePrice,
      cartTotalItemDiscounts: totalItemDiscounts,
      cartTotalCartDiscounts: totalCartDiscounts,
      cartTotalDiscount: totalDiscount,
      cartSubtotalExcludingTax: subtotalExcludingTax,
      cartTotalTax: totalTax,
      cartTotalCartPrice: totalCartPrice,
    );
  });
}

