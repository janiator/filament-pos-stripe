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

Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
  int? customPrice, // Optional custom price in øre (required if no_price_in_pos is true)
  String? description, // NEW: Optional description for diverse products or products without price
) async {
  if (product == null) return;

  final qty = quantity ?? 1;
  final currentCart = FFAppState().cart;
  final variantId = variants?.id ?? 0;
  final variantIdString = variantId != 0 ? variantId.toString() : '';

  // Check if custom price is required (no_price_in_pos is true)
  final requiresCustomPrice =
      variants?.noPriceInPos ?? product.noPriceInPos ?? false;

  if (requiresCustomPrice) {
    // If no_price_in_pos is true, customPrice must be provided
    if (customPrice == null || customPrice <= 0) {
      throw Exception(
          'Custom price is required for this product/variant. Please provide customPrice parameter.');
    }
    
    // Recommend description for diverse products (but don't require it)
    // This helps with compliance and clarity on receipts
    if (description == null || description.trim().isEmpty) {
      // Log a warning but don't throw - description is optional
      // You may want to show a UI hint to the user instead
    }
  }

  final unitPrice = (customPrice != null && customPrice > 0)
      ? customPrice
      : (variants?.variantPrice?.amount ?? product.productPrice?.amount ?? 0);

  // Validate that price is set
  if (unitPrice <= 0) {
    throw Exception('Price must be greater than 0');
  }

  final originalPrice = unitPrice;

  // Get product image: use variant image if available, otherwise first product image
  String productImageUrl = '';
  if (variants != null && variants.imageUrl.isNotEmpty) {
    productImageUrl = variants.imageUrl;
  } else if (product.images.isNotEmpty && product.images.first.isNotEmpty) {
    productImageUrl = product.images.first;
  }

  // Check if item already exists in cart (by product ID + variant ID)
  final existingIndex = currentCart.cartItems.indexWhere(
    (item) {
      // Check if product IDs match
      if (item.cartItemProductId != product.id.toString()) {
        return false;
      }
      // Check if variant IDs match (both empty string means no variant)
      return item.cartItemVariantId == variantIdString;
    },
  );

  if (existingIndex >= 0) {
    // Update existing item quantity
    final existingItem = currentCart.cartItems[existingIndex];
    
    // NOTE: If CartItemsStruct doesn't have cartItemDescription field,
    // you'll need to add it to the FlutterFlow schema first.
    // Alternatively, you can store it in cartItemMetadata as:
    // {'description': description}
    final updatedItem = CartItemsStruct(
      cartItemId: existingItem.cartItemId,
      cartItemProductId: existingItem.cartItemProductId,
      cartItemVariantId: existingItem.cartItemVariantId,
      cartItemProductName: existingItem.cartItemProductName,
      cartItemDescription: description ?? existingItem.cartItemDescription, // NEW: Preserve or update description
      cartItemProductImageUrl: existingItem.cartItemProductImageUrl,
      cartItemUnitPrice:
          unitPrice, // Use the calculated unitPrice (may be custom)
      cartItemQuantity: existingItem.cartItemQuantity + qty,
      cartItemOriginalPrice: originalPrice,
      cartItemDiscountAmount: existingItem.cartItemDiscountAmount,
      cartItemDiscountReason: existingItem.cartItemDiscountReason,
      cartItemArticleGroupCode: existingItem.cartItemArticleGroupCode,
      cartItemProductCode: existingItem.cartItemProductCode,
      cartItemMetadata: existingItem.cartItemMetadata,
    );

    final updatedItems = List<CartItemsStruct>.from(currentCart.cartItems);
    updatedItems[existingIndex] = updatedItem;

    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        cartId: currentCart.cartId,
        cartPosSessionId: currentCart.cartPosSessionId,
        cartItems: updatedItems,
        cartDiscounts: currentCart.cartDiscounts,
        cartTipAmount: currentCart.cartTipAmount,
        cartCustomerId: currentCart.cartCustomerId,
        cartCustomerName: currentCart.cartCustomerName,
        cartCreatedAt: currentCart.cartCreatedAt,
        cartUpdatedAt: getCurrentTimestamp.toString(),
        cartMetadata: currentCart.cartMetadata,
      );
    });
  } else {
    // Add new item to cart
    // Generate unique ID for cart item
    final cartItemId = DateTime.now().millisecondsSinceEpoch.toString();

    // NOTE: If CartItemsStruct doesn't have cartItemDescription field,
    // you'll need to add it to the FlutterFlow schema first.
    // Alternatively, you can store it in cartItemMetadata as:
    // {'description': description}
    final newCartItem = CartItemsStruct(
      cartItemId: cartItemId,
      cartItemProductId: product.id.toString(),
      cartItemVariantId: variantIdString,
      cartItemProductName: product.name,
      cartItemDescription: description, // NEW: Store custom description
      cartItemProductImageUrl: productImageUrl,
      cartItemUnitPrice:
          unitPrice, // Use the calculated unitPrice (may be custom)
      cartItemQuantity: qty,
      cartItemOriginalPrice: originalPrice,
      cartItemDiscountAmount: null,
      cartItemDiscountReason: null,
      cartItemArticleGroupCode: product.taxCode ?? '',
      cartItemProductCode: product.stripeProductId ?? '',
      cartItemMetadata: null,
    );

    final updatedItems = List<CartItemsStruct>.from(currentCart.cartItems);
    updatedItems.add(newCartItem);

    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        cartId: currentCart.cartId,
        cartPosSessionId: currentCart.cartPosSessionId,
        cartItems: updatedItems,
        cartDiscounts: currentCart.cartDiscounts,
        cartTipAmount: currentCart.cartTipAmount,
        cartCustomerId: currentCart.cartCustomerId,
        cartCustomerName: currentCart.cartCustomerName,
        cartCreatedAt: currentCart.cartCreatedAt,
        cartUpdatedAt: getCurrentTimestamp.toString(),
        cartMetadata: currentCart.cartMetadata,
      );
    });
  }

  // Update cart totals after adding/updating item
  await updateCartTotals();
}




