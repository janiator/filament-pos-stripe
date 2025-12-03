// FlutterFlow Custom Action: Remove Item from Cart
// This action removes an item from the cart by its cartItemId

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

/// Remove an item from the cart by its cartItemId
/// After removal, recalculates cart totals
Future removeItemFromCart(String cartItemId) async {
  final cart = FFAppState().cart;
  
  // Find the item index
  final itemIndex = cart.cartItems.indexWhere(
    (item) => item.cartItemId == cartItemId,
  );
  
  // If item not found, return early
  if (itemIndex < 0) {
    return;
  }
  
  // Create a new list without the removed item
  final updatedItems = List<CartItemsStruct>.from(cart.cartItems);
  updatedItems.removeAt(itemIndex);
  
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
  
  // Recalculate totals after removal
  await updateCartTotals();
}

