// FlutterFlow Custom Action: Apply Cart Discount
// This action applies or removes a cart-level discount
// Supports: "Ingen" (remove), "Prosent" (percentage), "Verdi" (fixed amount in øre)

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

/// Apply or remove a cart-level discount
/// 
/// Parameters:
/// - discountType: "Ingen" (remove), "Prosent" (percentage), or "Verdi" (fixed amount)
/// - discountValue: 
///   - For "Prosent": Percentage 0-100 (e.g., 10 for 10%)
///   - For "Verdi": Amount in øre (e.g., 5000 for 50.00 NOK)
///   - For "Ingen": Ignored
/// - discountReason: Optional reason for the discount
/// 
/// After applying/removing discount, recalculates cart totals
Future applyCartDiscount(
  String discountType,
  double discountValue,
  String? discountReason,
) async {
  final cart = FFAppState().cart;
  
  // Calculate cart subtotal (after item discounts, before cart discounts)
  // Calculate from items to ensure accuracy
  int totalLinePrice = 0;
  int totalItemDiscounts = 0;
  
  for (var item in cart.cartItems) {
    // Line price = unit price * quantity
    // Round to int since prices are in øre (smallest currency unit)
    totalLinePrice += (item.cartItemUnitPrice * item.cartItemQuantity).round();
    
    // Item discount = discount amount * quantity
    // Round to int since amounts are in øre
    totalItemDiscounts += ((item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity).round();
  }
  
  // Cart subtotal = line price - item discounts
  final cartSubtotal = totalLinePrice - totalItemDiscounts;
  
  // Calculate discount amount based on type
  int? discountAmount;
  int? discountPercentage;
  String? finalDiscountReason;
  
  if (discountType.toLowerCase() == 'ingen') {
    // Remove all cart discounts
    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        cartId: cart.cartId,
        cartPosSessionId: cart.cartPosSessionId,
        cartItems: cart.cartItems,
        cartDiscounts: [], // Remove all cart discounts
        cartTipAmount: cart.cartTipAmount,
        cartCustomerId: cart.cartCustomerId,
        cartCustomerName: cart.cartCustomerName,
        cartCreatedAt: cart.cartCreatedAt,
        cartUpdatedAt: getCurrentTimestamp.toString(),
        cartMetadata: cart.cartMetadata,
        // Preserve existing totals temporarily (will be recalculated)
        cartTotalLinePrice: cart.cartTotalLinePrice,
        cartTotalItemDiscounts: cart.cartTotalItemDiscounts,
        cartTotalCartDiscounts: cart.cartTotalCartDiscounts,
        cartTotalDiscount: cart.cartTotalDiscount,
        cartSubtotalExcludingTax: cart.cartSubtotalExcludingTax,
        cartTotalTax: cart.cartTotalTax,
        cartTotalCartPrice: cart.cartTotalCartPrice,
      );
    });
    
    // Recalculate totals after removing discount
    await updateCartTotals();
    return;
  } else if (discountType.toLowerCase() == 'prosent') {
    // Percentage discount: calculate from cart subtotal
    // discountValue is percentage 0-100 (e.g., 10 for 10%)
    // discountAmount = (cartSubtotal * discountValue / 100).round()
    int calculatedDiscount = (cartSubtotal * discountValue / 100).round();
    
    // Ensure discount doesn't exceed cart subtotal
    discountAmount = calculatedDiscount > cartSubtotal 
        ? cartSubtotal 
        : calculatedDiscount;
    
    discountPercentage = discountValue.round();
    finalDiscountReason = discountReason;
  } else if (discountType.toLowerCase() == 'verdi') {
    // Fixed amount discount: discountValue is already in øre
    int discountInOre = discountValue.round();
    
    // Ensure discount doesn't exceed cart subtotal
    discountAmount = discountInOre > cartSubtotal 
        ? cartSubtotal 
        : discountInOre;
    
    discountPercentage = null;
    finalDiscountReason = discountReason;
  } else {
    // Invalid discount type, return early
    return;
  }
  
  // Create or update cart discount
  // For simplicity, we'll replace all existing cart discounts with a single new one
  // If you need to support multiple cart discounts, you can modify this logic
  final discountId = DateTime.now().millisecondsSinceEpoch.toString();
  
  final newDiscount = CartDiscountsStruct(
    cartDiscountId: discountId,
    cartDiscountType: discountType.toLowerCase(),
    cartDiscountCouponId: null,
    cartDiscountCouponCode: null,
    cartDiscountDescription: finalDiscountReason ?? 'Cart discount',
    cartDiscountAmount: discountAmount,
    cartDiscountPercentage: discountPercentage,
    cartDiscountReason: finalDiscountReason,
    cartDiscountRequiresApproval: false,
  );
  
  // Update cart with new discount (replace all existing cart discounts)
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      cartId: cart.cartId,
      cartPosSessionId: cart.cartPosSessionId,
      cartItems: cart.cartItems,
      cartDiscounts: [newDiscount], // Replace with single discount
      cartTipAmount: cart.cartTipAmount,
      cartCustomerId: cart.cartCustomerId,
      cartCustomerName: cart.cartCustomerName,
      cartCreatedAt: cart.cartCreatedAt,
      cartUpdatedAt: getCurrentTimestamp.toString(),
      cartMetadata: cart.cartMetadata,
      // Preserve existing totals temporarily (will be recalculated)
      cartTotalLinePrice: cart.cartTotalLinePrice,
      cartTotalItemDiscounts: cart.cartTotalItemDiscounts,
      cartTotalCartDiscounts: cart.cartTotalCartDiscounts,
      cartTotalDiscount: cart.cartTotalDiscount,
      cartSubtotalExcludingTax: cart.cartSubtotalExcludingTax,
      cartTotalTax: cart.cartTotalTax,
      cartTotalCartPrice: cart.cartTotalCartPrice,
    );
  });
  
  // Recalculate totals after applying discount
  await updateCartTotals();
}

