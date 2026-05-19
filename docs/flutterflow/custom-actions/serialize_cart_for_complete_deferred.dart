// FlutterFlow Custom Action: Serialize FFAppState().cart for completeDeferredPayment.cartJson
//
// Keep the payload shape in sync with prepare_parked_deferred_purchase.dart
// (_buildCartPayloadFromShoppingCart).
//
// Call immediately before completeDeferredPayment on the POS “deferred resume” path
// so staff edits to the cart are reflected in complete-payment.

import 'dart:convert';

double getTaxPercentageFromCode(String? taxCode) {
  if (taxCode == null || taxCode.isEmpty) {
    return 0.25;
  }

  switch (taxCode.toLowerCase()) {
    case 'txcd_99999999':
    case 'standard':
    case '1':
      return 0.25;
    case 'txcd_99999998':
    case 'reduced':
    case 'food':
      return 0.15;
    case 'txcd_99999997':
    case 'lower':
    case 'service':
      return 0.10;
    case 'txcd_99999996':
    case 'zero':
    case 'exempt':
    case '0':
      return 0.0;
    default:
      return 0.25;
  }
}

double normalizeTaxRateDecimal(double? rate, {double defaultRate = 0.25}) {
  if (rate == null) {
    return defaultRate;
  }
  if (rate == 0) {
    return 0.0;
  }
  if (rate > 1) {
    return rate / 100.0;
  }

  return rate;
}

double resolveCartItemTaxRate(CartItemsStruct item) {
  final stored = item.cartItemTaxPercent;
  if (stored != null) {
    return normalizeTaxRateDecimal(stored);
  }

  final code = item.cartItemArticleGroupCode.trim();
  if (code.isNotEmpty) {
    return getTaxPercentageFromCode(code);
  }

  return 0.25;
}

Future<dynamic> serializeCartForCompleteDeferred() async {
  try {
    final cart = FFAppState().cart;
    if (cart.cartItems.isEmpty) {
      return {
        'success': false,
        'cartJson': '',
        'message': 'Cart is empty',
      };
    }
    final payload = _buildCartPayloadFromShoppingCart(cart);
    return {
      'success': true,
      'cartJson': jsonEncode(payload),
    };
  } catch (e) {
    return {
      'success': false,
      'cartJson': '',
      'message': e.toString(),
    };
  }
}

Map<String, dynamic> _buildCartPayloadFromShoppingCart(ShoppingCartStruct cart) {
  final cartItems = <Map<String, dynamic>>[];
  for (final cartItem in cart.cartItems) {
    final productId = int.tryParse(cartItem.cartItemProductId) ?? 0;
    final variantId = cartItem.cartItemVariantId != null && cartItem.cartItemVariantId!.isNotEmpty
        ? int.tryParse(cartItem.cartItemVariantId!)
        : null;
    final unitPrice = cartItem.cartItemUnitPrice;
    final discountAmount = cartItem.cartItemDiscountAmount ?? 0;

    cartItems.add({
      'product_id': productId,
      'variant_id': variantId,
      'quantity': cartItem.cartItemQuantity,
      'unit_price': unitPrice,
      'discount_amount': discountAmount,
      'tax_rate': resolveCartItemTaxRate(cartItem),
      'tax_inclusive': true,
    });
  }

  final cartDiscounts = <Map<String, dynamic>>[];
  for (final discount in cart.cartDiscounts) {
    final discountType =
        discount.cartDiscountType.isNotEmpty ? discount.cartDiscountType : 'fixed';
    final discountMap = <String, dynamic>{
      'type': discountType,
      'amount': discount.cartDiscountAmount,
    };
    if (discountType == 'prosent' && discount.cartDiscountPercentage > 0) {
      discountMap['percentage'] = discount.cartDiscountPercentage;
    }
    if (discount.cartDiscountReason.isNotEmpty) {
      discountMap['reason'] = discount.cartDiscountReason;
    } else if (discount.cartDiscountDescription.isNotEmpty) {
      discountMap['reason'] = discount.cartDiscountDescription;
    }
    if (discount.cartDiscountCouponId.isNotEmpty) {
      discountMap['coupon_id'] = discount.cartDiscountCouponId;
    }
    if (discount.cartDiscountCouponCode.isNotEmpty) {
      discountMap['coupon_code'] = discount.cartDiscountCouponCode;
    }
    cartDiscounts.add(discountMap);
  }

  final subtotal = cart.cartSubtotalExcludingTax;
  final totalDiscounts = cart.cartTotalDiscount;
  final totalTax = cart.cartTotalTax;
  final total = cart.cartTotalCartPrice;
  final tipAmount = cart.cartTipAmount;

  final rawCustomerId = cart.cartCustomerId;
  final customerIdForApi = rawCustomerId != null && rawCustomerId > 0 ? rawCustomerId : null;

  return {
    'items': cartItems,
    'discounts': cartDiscounts,
    'tip_amount': tipAmount,
    'customer_id': customerIdForApi,
    'customer_name': cart.cartCustomerName.isNotEmpty ? cart.cartCustomerName : null,
    'note': cart.cartNote.isNotEmpty ? cart.cartNote : null,
    'subtotal': subtotal,
    'total_discounts': totalDiscounts,
    'total_tax': totalTax,
    'total': total,
    'currency': 'nok',
  };
}
