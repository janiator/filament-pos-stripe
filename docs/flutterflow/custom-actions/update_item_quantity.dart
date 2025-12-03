// FlutterFlow Custom Action: Update Item Quantity
// This action updates the quantity of a cart item by its cartItemId

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

/// Update the quantity of a cart item
/// If quantity is 0 or less, the item is removed from cart
/// After update, recalculates cart totals
Future updateItemQuantity(String cartItemId, int quantity) async {
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
  
  // If quantity is 0 or less, remove the item
  if (quantity <= 0) {
    final updatedItems = List<CartItemsStruct>.from(cart.cartItems);
    updatedItems.removeAt(itemIndex);
    
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
    return;
  }
  
  // Update the item quantity
  final updatedItem = CartItemsStruct(
    cartItemId: existingItem.cartItemId,
    cartItemProductId: existingItem.cartItemProductId,
    cartItemVariantId: existingItem.cartItemVariantId,
    cartItemProductName: existingItem.cartItemProductName,
    cartItemProductImageUrl: existingItem.cartItemProductImageUrl,
    cartItemUnitPrice: existingItem.cartItemUnitPrice,
    cartItemQuantity: quantity,
    cartItemOriginalPrice: existingItem.cartItemOriginalPrice,
    cartItemDiscountAmount: existingItem.cartItemDiscountAmount,
    cartItemDiscountReason: existingItem.cartItemDiscountReason,
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
  
  // Recalculate totals after quantity update
  await updateCartTotals();
}

