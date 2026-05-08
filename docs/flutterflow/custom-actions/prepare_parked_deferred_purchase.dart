// FlutterFlow Custom Action: Prepare parked / edited deferred purchase (orders → POS cart)
//
// 1) GET /api/purchases/{id} (same payload as fetchPosPurchaseForCartHydration)
// 2) Rebuild FFAppState().cart from purchase_items + purchase_discounts + customer/note/tip
// 3) await updateCartTotals() so totals match the hydrated lines
// 4) Returns cartJson (JSON string) suitable for completeDeferredPayment.cartJson
//
// Use on the **orders** page **before** navigating to **pos** (recommended), or on **pos**
// after navigation — if you navigate first, **pos** On Page Load runs before prefs exist,
// so also chain **`getDeferredResumeContext`** after this action (or bind banner from the
// return map `deferredResumeBannerText` / `deferredResumeBannerActive` to App State / page state).
// Returns cartJson for APIs; sets resume prefs + banner mirror; merges deferred keys into
// `cartMetadata.notes` as JSON (CartMetadataStruct is source / deviceId / notes only).

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

const String kPositivDeferredResumeChargeIdKey =
    'positiv_deferred_resume_charge_id';
const String kPositivDeferredResumeOrderLabelKey =
    'positiv_deferred_resume_order_label';

void mirrorDeferredResumeBannerToAppStateIfPresent({
  required bool active,
  required String bannerText,
}) {
  try {
    final s = FFAppState();
    s.update(() {
      final d = s as dynamic;
      d.deferredResumeBannerActive = active;
      d.deferredResumeBannerText = bannerText;
    });
  } catch (_) {}
}

Future<dynamic> prepareParkedDeferredPurchase(
  int purchaseId,
  String apiBaseUrl,
  String authToken,
) async {
  try {
    if (purchaseId <= 0) {
      return {
        'success': false,
        'message': 'Invalid purchase ID',
      };
    }
    if (apiBaseUrl.isEmpty || authToken.isEmpty) {
      return {
        'success': false,
        'message': 'API base URL or auth token is missing',
      };
    }

    final base =
        apiBaseUrl.endsWith('/') ? apiBaseUrl.substring(0, apiBaseUrl.length - 1) : apiBaseUrl;
    final response = await http.get(
      Uri.parse('$base/api/purchases/$purchaseId'),
      headers: {
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
    );

    final responseData = jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode < 200 || response.statusCode >= 300) {
      return {
        'success': false,
        'message': responseData['message']?.toString() ?? 'Failed to load purchase',
        'statusCode': response.statusCode,
      };
    }

    final purchase = responseData['purchase'];
    if (purchase is! Map<String, dynamic>) {
      return {
        'success': false,
        'message': 'Invalid purchase payload',
        'statusCode': response.statusCode,
      };
    }

    final itemsRaw = purchase['purchase_items'];
    if (itemsRaw is! List || itemsRaw.isEmpty) {
      return {
        'success': false,
        'message': 'Purchase has no line items to hydrate',
        'statusCode': response.statusCode,
      };
    }

    final cartItems = <CartItemsStruct>[];
    for (final row in itemsRaw) {
      if (row is! Map) {
        continue;
      }
      final m = Map<String, dynamic>.from(row);
      final productId = m['purchase_item_product_id']?.toString() ?? '';
      final variantId = m['purchase_item_variant_id']?.toString() ?? '';
      final name = m['purchase_item_product_name']?.toString() ?? '';
      final imageUrl = m['purchase_item_product_image_url']?.toString() ?? '';
      final unitPrice = (m['purchase_item_unit_price'] as num?)?.round() ?? 0;
      final quantity = (m['purchase_item_quantity'] as num?)?.toDouble() ?? 1;
      final originalRaw = m['purchase_item_original_price'] as num?;
      final original = originalRaw?.round();
      final discRaw = m['purchase_item_discount_amount'] as num?;
      final disc = discRaw?.round();
      final discReason = m['purchase_item_discount_reason']?.toString();
      final article = m['purchase_item_article_group_code']?.toString() ?? '';
      final productCode = m['purchase_item_product_code']?.toString() ?? '';
      final lineId = m['purchase_item_id']?.toString() ??
          DateTime.now().microsecondsSinceEpoch.toString();

      cartItems.add(
        CartItemsStruct(
          cartItemId: lineId,
          cartItemProductId: productId,
          cartItemVariantId: variantId,
          cartItemProductName: name,
          cartItemProductImageUrl: imageUrl,
          cartItemUnitPrice: unitPrice,
          cartItemQuantity: quantity,
          cartItemOriginalPrice: original ?? unitPrice,
          cartItemDiscountAmount: disc,
          cartItemDiscountReason: discReason,
          cartItemArticleGroupCode: article,
          cartItemProductCode: productCode,
          cartItemMetadata: null,
        ),
      );
    }

    final discounts = <CartDiscountsStruct>[];
    final discountsRaw = purchase['purchase_discounts'];
    if (discountsRaw is List) {
      for (final row in discountsRaw) {
        if (row is! Map) {
          continue;
        }
        final d = Map<String, dynamic>.from(row);
        final type = (d['type'] ?? 'verdi').toString().toLowerCase();
        if (type == 'ingen') {
          continue;
        }
        final amount = (d['amount'] as num?)?.round() ?? 0;
        final pct = (d['percentage'] as num?)?.toDouble() ?? 0;
        final reason = d['reason']?.toString() ?? '';
        discounts.add(
          CartDiscountsStruct(
            cartDiscountId: '${DateTime.now().microsecondsSinceEpoch}_${discounts.length}',
            cartDiscountType: type,
            cartDiscountCouponId: '',
            cartDiscountCouponCode: '',
            cartDiscountDescription: reason.isNotEmpty ? reason : 'Cart discount',
            cartDiscountAmount: amount,
            cartDiscountPercentage: type == 'prosent' ? pct.round() : 0,
            cartDiscountReason: reason,
            cartDiscountRequiresApproval: false,
          ),
        );
      }
    }

    final customer = purchase['purchase_customer'];
    int? customerIdForCart;
    String customerName = '';
    if (customer is Map) {
      final cid = customer['id'];
      if (cid is int) {
        customerIdForCart = cid;
      } else if (cid != null) {
        customerIdForCart = int.tryParse(cid.toString());
      }
      final n = customer['name']?.toString();
      if (n != null && n.isNotEmpty) {
        customerName = n;
      }
    }

    final note = _resolvePurchaseOrderNote(purchase);
    final tipRaw = purchase['purchase_tip_amount'];
    final tip = tipRaw is num ? tipRaw.round() : int.tryParse(tipRaw?.toString() ?? '') ?? 0;

    final current = FFAppState().cart;

    final rawChargeId = purchase['id'];
    final chargeId = rawChargeId is int
        ? rawChargeId
        : int.tryParse(rawChargeId.toString()) ?? purchaseId;
    final receipt = purchase['purchase_receipt'];
    final String orderDisplayReference;
    if (receipt is Map) {
      final rn = receipt['receipt_number']?.toString().trim() ?? '';
      orderDisplayReference = rn.isNotEmpty ? rn : '#$chargeId';
    } else {
      orderDisplayReference = '#$chargeId';
    }

    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        cartId: current.cartId,
        cartPosSessionId: current.cartPosSessionId,
        cartItems: cartItems,
        cartDiscounts: discounts,
        cartTipAmount: tip,
        cartCustomerId: customerIdForCart,
        cartCustomerName: customerName,
        cartCreatedAt: current.cartCreatedAt,
        cartUpdatedAt: getCurrentTimestamp.toString(),
        cartMetadata: _mergedCartMetadata(
          current,
          chargeId,
          orderDisplayReference,
        ),
        // Whole-order note from API only (clears stale cart text when the order has no note).
        cartNote: note,
      );
    });

    await updateCartTotals();

    final hydrated = FFAppState().cart;
    final cartJson = jsonEncode(_buildCartPayloadFromShoppingCart(hydrated));

    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(kPositivDeferredResumeChargeIdKey, chargeId);
    await prefs.setString(kPositivDeferredResumeOrderLabelKey, orderDisplayReference);

    final bannerText = 'Ordre $orderDisplayReference';
    mirrorDeferredResumeBannerToAppStateIfPresent(
      active: true,
      bannerText: bannerText,
    );

    return {
      'success': true,
      'purchase': purchase,
      'chargeId': chargeId,
      'resumeDeferredChargeId': chargeId,
      'orderDisplayReference': orderDisplayReference,
      'deferredResumeBannerText': bannerText,
      'deferredResumeBannerActive': true,
      'purchaseOrderNote': note,
      'isParkedDeferredResume': true,
      'cartJson': cartJson,
      'message': 'Cart hydrated for deferred completion',
      'statusCode': response.statusCode,
    };
  } catch (e) {
    return {
      'success': false,
      'message': 'Error preparing parked deferred purchase: ${e.toString()}',
      'statusCode': 0,
    };
  }
}

/// Resolves whole-order note from GET /api/purchases/{id} payload (top-level
/// [purchase_note], [note], [purchase_metadata] string or map, common keys).
String _resolvePurchaseOrderNote(Map<String, dynamic> purchase) {
  for (final key in ['purchase_note', 'note']) {
    final v = purchase[key]?.toString().trim() ?? '';
    if (v.isNotEmpty) {
      return v;
    }
  }

  final desc = purchase['description']?.toString().trim() ?? '';
  if (desc.isNotEmpty) {
    return desc;
  }

  final metaMap = _purchaseMetadataAsMap(purchase['purchase_metadata']);
  if (metaMap != null) {
    for (final key in [
      'note',
      'cart_note',
      'order_note',
      'whole_order_note',
      'orderNote',
      'customer_note',
    ]) {
      final v = metaMap[key]?.toString().trim() ?? '';
      if (v.isNotEmpty) {
        return v;
      }
    }
  }

  return '';
}

Map<String, dynamic>? _purchaseMetadataAsMap(dynamic raw) {
  if (raw == null) {
    return null;
  }
  if (raw is Map<String, dynamic>) {
    return raw;
  }
  if (raw is Map) {
    return Map<String, dynamic>.from(raw);
  }
  if (raw is String && raw.trim().isNotEmpty) {
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
  }
  return null;
}

/// Merges deferred resume hints into [CartMetadataStruct.notes] as JSON for widgets
/// that read cart metadata; [source] / [deviceId] are preserved from [current].
CartMetadataStruct _mergedCartMetadata(
  ShoppingCartStruct current,
  int chargeId,
  String orderDisplayReference,
) {
  final m = current.cartMetadata;
  final merged = <String, dynamic>{};

  final notesTrim = m.notes.trim();
  if (notesTrim.isNotEmpty) {
    try {
      final decoded = jsonDecode(notesTrim);
      if (decoded is Map) {
        for (final e in Map<String, dynamic>.from(decoded).entries) {
          if (e.key == 'positiv_deferred_resume_charge_id' ||
              e.key == 'positiv_deferred_order_display') {
            continue;
          }
          merged[e.key] = e.value;
        }
      } else {
        merged['positiv_metadata_notes_legacy'] = m.notes;
      }
    } catch (_) {
      merged['positiv_metadata_notes_legacy'] = m.notes;
    }
  }

  merged['positiv_deferred_resume_charge_id'] = chargeId;
  merged['positiv_deferred_order_display'] = orderDisplayReference;

  return CartMetadataStruct(
    source: m.source.isNotEmpty ? m.source : 'positiv_deferred_resume',
    deviceId: m.deviceId,
    notes: jsonEncode(merged),
  );
}

/// Same cart object shape as [completePosPurchase] / complete-payment `cart`.
/// Keep in sync with [serialize_cart_for_complete_deferred.dart].
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
      'tax_rate': 0.25,
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
