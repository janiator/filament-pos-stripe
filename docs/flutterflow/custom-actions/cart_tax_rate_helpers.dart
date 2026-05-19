/// Shared VAT helpers for cart/checkout custom actions (body-only snippets).
/// Copy these functions into each action that builds API cart payloads or calculates tax.
/// Do not push this file as a standalone FlutterFlow action.

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
  if (rate == 0) {
    return 0.0;
  }
  if (rate > 1) {
    return rate / 100.0;
  }

  return rate;
}

/// Resolve effective VAT decimal rate for a cart line (0–1) for API `tax_rate`.
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
