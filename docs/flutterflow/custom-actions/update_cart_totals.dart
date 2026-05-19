import 'package:mek_stripe_terminal/mek_stripe_terminal.dart';

// Prevent concurrent updates
bool _isUpdatingTotals = false;

/// Maps tax codes / article group hints to decimal VAT rate (0–1).
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

/// Normalize API/product tax percent to decimal rate 0–1.
double normalizeTaxRateDecimal(double? rate, {double defaultRate = 0.25}) {
  if (rate == null) {
    return defaultRate;
  }
  if (rate <= 0) {
    return 0.0;
  }
  if (rate > 1) {
    return rate / 100.0;
  }

  return rate;
}

/// Resolve effective VAT decimal rate for a cart line (0–1).
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

/// Calculate and update all cart totals with per-product tax calculation.
Future updateCartTotals() async {
  if (_isUpdatingTotals) {
    return;
  }

  _isUpdatingTotals = true;

  try {
    final cart = FFAppState().cart;

    int totalLinePrice = 0;
    int totalItemDiscounts = 0;
    int totalCartDiscounts = 0;
    int totalTax = 0;

    for (var item in cart.cartItems) {
      final linePrice = (item.cartItemUnitPrice * item.cartItemQuantity).round();
      totalLinePrice += linePrice;

      final itemDiscount =
          ((item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity).round();
      totalItemDiscounts += itemDiscount;

      final itemSubtotalIncludingTax = linePrice - itemDiscount;
      final taxPercentage = resolveCartItemTaxRate(item);

      final itemTax = taxPercentage > 0
          ? (itemSubtotalIncludingTax * (taxPercentage / (1 + taxPercentage)))
              .round()
          : 0;
      totalTax += itemTax;
    }

    for (var discount in cart.cartDiscounts) {
      totalCartDiscounts += discount.cartDiscountAmount;
    }

    final totalDiscount = totalItemDiscounts + totalCartDiscounts;
    final subtotalExcludingTax = totalLinePrice - totalDiscount - totalTax;
    final totalCartPrice =
        totalLinePrice - totalDiscount + (cart.cartTipAmount ?? 0);

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
        cartNote: cart.cartNote,
        cartTotalLinePrice: totalLinePrice,
        cartTotalItemDiscounts: totalItemDiscounts,
        cartTotalCartDiscounts: totalCartDiscounts,
        cartTotalDiscount: totalDiscount,
        cartSubtotalExcludingTax: subtotalExcludingTax,
        cartTotalTax: totalTax,
        cartTotalCartPrice: totalCartPrice,
      );
    });

    if (!FFAppState().stripeReaderConnected) {
      return;
    }

    final updatedCart = FFAppState().cart;
    try {
      if (updatedCart.cartItems.isEmpty) {
        await Terminal.instance.clearReaderDisplay();
      } else {
        final lineItems = updatedCart.cartItems.map((item) {
          final unitPrice = item.cartItemUnitPrice ?? 0;
          final qty = item.cartItemQuantity.round();
          final discount =
              ((item.cartItemDiscountAmount ?? 0) * item.cartItemQuantity)
                  .round();
          final amount = (unitPrice * item.cartItemQuantity).round() - discount;
          String description = item.cartItemProductName.isNotEmpty
              ? item.cartItemProductName
              : (item.cartItemDescription.isNotEmpty
                  ? item.cartItemDescription
                  : 'Item');
          if (description.length > 50) {
            description = '${description.substring(0, 47)}...';
          }
          return CartLineItem(
            description: description,
            quantity: qty,
            amount: amount,
          );
        }).toList();
        final stripeCart = Cart(
          currency: 'nok',
          tax: updatedCart.cartTotalTax,
          total: updatedCart.cartTotalCartPrice,
          lineItems: lineItems,
        );
        await Terminal.instance.setReaderDisplay(stripeCart);
      }
    } catch (_) {}
  } finally {
    _isUpdatingTotals = false;
  }
}
