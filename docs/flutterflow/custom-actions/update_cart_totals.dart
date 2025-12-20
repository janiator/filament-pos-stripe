// FlutterFlow Custom Action: Update Cart Totals
// This action calculates all cart totals with per-product tax rates
// Tax is calculated based on each product's tax code

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

// Prevent concurrent updates
bool _isUpdatingTotals = false;

/// Get tax percentage from tax code
/// Maps tax codes to tax percentages
/// Returns default 25% if code is not recognized
double getTaxPercentageFromCode(String? taxCode) {
  if (taxCode == null || taxCode.isEmpty) {
    return 0.25; // Default 25% VAT in Norway
  }
  
  // Stripe tax codes and common tax code formats
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
/// This action calculates totals and stores them in the cart struct fields:
/// - cartTotalLinePrice
/// - cartTotalItemDiscounts
/// - cartTotalCartDiscounts
/// - cartTotalDiscount
/// - cartSubtotalExcludingTax
/// - cartTotalTax (calculated per item based on tax code)
/// - cartTotalCartPrice
/// 
/// Includes protection against concurrent updates to prevent rendering errors
Future updateCartTotals() async {
  // Prevent concurrent updates to avoid rendering errors
  if (_isUpdatingTotals) {
    return;
  }
  
  _isUpdatingTotals = true;
  
  try {
    final cart = FFAppState().cart;
  
  // Initialize totals
  int totalLinePrice = 0;
  int totalItemDiscounts = 0;
  int totalCartDiscounts = 0;
  int totalTax = 0; // Will be calculated per item
  
  // Calculate line items totals and tax per item
  // Note: Prices are tax-inclusive, so we need to extract tax from the price
  for (var item in cart.cartItems) {
    // Line price = unit price * quantity (this is tax-inclusive)
    // Round to int since prices are in øre (smallest currency unit)
    final linePrice = (item.cartItemUnitPrice * item.cartItemQuantity).round();
    totalLinePrice += linePrice;
    
    // Item discount = discount amount * quantity
    // Round to int since amounts are in øre
    final itemDiscount = ((item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity).round();
    totalItemDiscounts += itemDiscount;
    
    // Calculate item subtotal (after discount, still tax-inclusive)
    final itemSubtotalIncludingTax = linePrice - itemDiscount;
    
    // Get tax percentage from article group code (stored when adding to cart)
    // The cartItemArticleGroupCode contains the tax code from the product
    final taxPercentage = getTaxPercentageFromCode(item.cartItemArticleGroupCode);
    
    // Calculate tax for this item (extract tax from tax-inclusive price)
    // Formula: Tax = Price including tax × (Tax rate / (1 + Tax rate))
    // Example: If price is 100 and tax is 25%, tax = 100 × (0.25 / 1.25) = 20
    final itemTax = taxPercentage > 0
        ? (itemSubtotalIncludingTax * (taxPercentage / (1 + taxPercentage))).round()
        : 0;
    totalTax += itemTax;
  }
  
  // Calculate cart-level discounts
  for (var discount in cart.cartDiscounts) {
    totalCartDiscounts += discount.cartDiscountAmount;
  }
  
  // Calculate final totals
  final totalDiscount = totalItemDiscounts + totalCartDiscounts;
  
  // Subtotal excluding tax = total line price (tax-inclusive) - discounts - tax
  // Since prices include tax, we subtract the tax to get the base price
  final subtotalExcludingTax = totalLinePrice - totalDiscount - totalTax;
  
  // Total cart price = subtotal excluding tax + tax + tip
  // Or simply: total line price - discounts + tip (since line price already includes tax)
  final totalCartPrice = totalLinePrice - totalDiscount + (cart.cartTipAmount ?? 0);
  
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
  } finally {
    _isUpdatingTotals = false;
  }
}

