// FlutterFlow Custom Action: Apply Item Discount
// This action applies or removes a discount to/from a cart item
// Supports: "Ingen" (remove), "Prosent" (percentage), "Verdi" (fixed amount in kroner)

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

/// Apply or remove a discount from a cart item
/// 
/// Parameters:
/// - cartItemId: The ID of the cart item
/// - discountType: "Ingen" (remove), "Prosent" (percentage), or "Verdi" (fixed amount)
/// - discountValue: 
///   - For "Prosent": Percentage 0-100 (e.g., 10 for 10%)
///   - For "Verdi": Amount in øre (e.g., 5000 for 50.00 NOK)
///   - For "Ingen": Ignored
/// - discountReason: Optional reason for the discount
/// 
/// After applying/removing discount, recalculates cart totals
Future applyItemDiscount(
  String cartItemId,
  String discountType,
  double discountValue,
  String? discountReason,
) async {
  final cart = FFAppState().cart;
  
  // Find the item index
  final itemIndex = cart.cartItems.indexWhere(
    (item) => item.cartItemId == cartItemId,
  );
  
  // If item not found, return early
  if (itemIndex < 0) {
    return;
  }
  
  final existingItem = cart.cartItems[itemIndex];
  
  // Calculate discount amount based on type
  int? discountAmount;
  String? finalDiscountReason;
  
  if (discountType.toLowerCase() == 'ingen') {
    // Remove discount
    discountAmount = null;
    finalDiscountReason = null;
  } else if (discountType.toLowerCase() == 'prosent') {
    // Percentage discount: calculate from unit price
    // discountValue is percentage 0-100 (e.g., 10 for 10%)
    // discountAmount = (unitPrice * discountValue / 100).round()
    int calculatedDiscount = (existingItem.cartItemUnitPrice * discountValue / 100).round();
    
    // Ensure discount doesn't exceed item price
    discountAmount = calculatedDiscount > existingItem.cartItemUnitPrice 
        ? existingItem.cartItemUnitPrice 
        : calculatedDiscount;
    
    finalDiscountReason = discountReason;
  } else if (discountType.toLowerCase() == 'verdi') {
    // Fixed amount discount: discountValue is already in øre
    int discountInOre = discountValue.round();
    
    // Ensure discount doesn't exceed item price
    discountAmount = discountInOre > existingItem.cartItemUnitPrice 
        ? existingItem.cartItemUnitPrice 
        : discountInOre;
    
    finalDiscountReason = discountReason;
  } else {
    // Invalid discount type, return early
    return;
  }
  
  // Update the item with discount (or remove discount if "Ingen")
  final updatedItem = CartItemsStruct(
    cartItemId: existingItem.cartItemId,
    cartItemProductId: existingItem.cartItemProductId,
    cartItemVariantId: existingItem.cartItemVariantId,
    cartItemProductName: existingItem.cartItemProductName,
    cartItemProductImageUrl: existingItem.cartItemProductImageUrl,
    cartItemUnitPrice: existingItem.cartItemUnitPrice,
    cartItemQuantity: existingItem.cartItemQuantity,
    cartItemOriginalPrice: existingItem.cartItemOriginalPrice,
    cartItemDiscountAmount: discountAmount,
    cartItemDiscountReason: finalDiscountReason,
    cartItemArticleGroupCode: existingItem.cartItemArticleGroupCode,
    cartItemProductCode: existingItem.cartItemProductCode,
    cartItemMetadata: existingItem.cartItemMetadata,
  );
  
  final updatedItems = List<CartItemsStruct>.from(cart.cartItems);
  updatedItems[itemIndex] = updatedItem;
  
  // Update cart
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      cartId: cart.cartId,
      cartPosSessionId: cart.cartPosSessionId,
      cartItems: updatedItems,
      cartDiscounts: cart.cartDiscounts,
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
  
  // Recalculate totals after applying/removing discount
  await updateCartTotals();
}
