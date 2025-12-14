// ignore_for_file: unnecessary_getters_setters

import '/backend/schema/util/schema_util.dart';

import '/backend/schema/enums/enums.dart';

import 'index.dart';

import '/flutter_flow/flutter_flow_util.dart';

class ShoppingCartStruct extends BaseStruct {
  ShoppingCartStruct({
    String? cartId,
    String? cartPosSessionId,
    List<CartItemsStruct>? cartItems,
    List<CartDiscountsStruct>? cartDiscounts,
    int? cartTipAmount,
    int? cartCustomerId,
    String? cartCustomerName,
    String? cartNote,
    String? cartCreatedAt,
    String? cartUpdatedAt,
    CartMetadataStruct? cartMetadata,

    /// Sum of all (unit price × quantity) before discounts
    int? cartTotalLinePrice,

    /// Sum of discounts on individual items
    int? cartTotalItemDiscounts,

    /// Sum of cart-level discounts
    int? cartTotalCartDiscounts,

    /// Combined total of all discounts
    int? cartTotalDiscount,

    /// Line price minus all discounts
    int? cartSubtotalExcludingTax,

    /// Tax amount (25% VAT)
    int? cartTotalTax,

    /// Final total including tax and tip
    int? cartTotalCartPrice,
  })  : _cartId = cartId,
        _cartPosSessionId = cartPosSessionId,
        _cartItems = cartItems,
        _cartDiscounts = cartDiscounts,
        _cartTipAmount = cartTipAmount,
        _cartCustomerId = cartCustomerId,
        _cartCustomerName = cartCustomerName,
        _cartNote = cartNote,
        _cartCreatedAt = cartCreatedAt,
        _cartUpdatedAt = cartUpdatedAt,
        _cartMetadata = cartMetadata,
        _cartTotalLinePrice = cartTotalLinePrice,
        _cartTotalItemDiscounts = cartTotalItemDiscounts,
        _cartTotalCartDiscounts = cartTotalCartDiscounts,
        _cartTotalDiscount = cartTotalDiscount,
        _cartSubtotalExcludingTax = cartSubtotalExcludingTax,
        _cartTotalTax = cartTotalTax,
        _cartTotalCartPrice = cartTotalCartPrice;

  // "cart_id" field.
  String? _cartId;
  String get cartId => _cartId ?? '';
  set cartId(String? val) => _cartId = val;

  bool hasCartId() => _cartId != null;

  // "cart_pos_session_id" field.
  String? _cartPosSessionId;
  String get cartPosSessionId => _cartPosSessionId ?? '';
  set cartPosSessionId(String? val) => _cartPosSessionId = val;

  bool hasCartPosSessionId() => _cartPosSessionId != null;

  // "cart_items" field.
  List<CartItemsStruct>? _cartItems;
  List<CartItemsStruct> get cartItems => _cartItems ?? const [];
  set cartItems(List<CartItemsStruct>? val) => _cartItems = val;

  void updateCartItems(Function(List<CartItemsStruct>) updateFn) {
    updateFn(_cartItems ??= []);
  }

  bool hasCartItems() => _cartItems != null;

  // "cart_discounts" field.
  List<CartDiscountsStruct>? _cartDiscounts;
  List<CartDiscountsStruct> get cartDiscounts => _cartDiscounts ?? const [];
  set cartDiscounts(List<CartDiscountsStruct>? val) => _cartDiscounts = val;

  void updateCartDiscounts(Function(List<CartDiscountsStruct>) updateFn) {
    updateFn(_cartDiscounts ??= []);
  }

  bool hasCartDiscounts() => _cartDiscounts != null;

  // "cart_tip_amount" field.
  int? _cartTipAmount;
  int get cartTipAmount => _cartTipAmount ?? 0;
  set cartTipAmount(int? val) => _cartTipAmount = val;

  void incrementCartTipAmount(int amount) =>
      cartTipAmount = cartTipAmount + amount;

  bool hasCartTipAmount() => _cartTipAmount != null;

  // "cart_customer_id" field.
  int? _cartCustomerId;
  int get cartCustomerId => _cartCustomerId ?? 0;
  set cartCustomerId(int? val) => _cartCustomerId = val;

  void incrementCartCustomerId(int amount) =>
      cartCustomerId = cartCustomerId + amount;

  bool hasCartCustomerId() => _cartCustomerId != null;

  // "cart_customer_name" field.
  String? _cartCustomerName;
  String get cartCustomerName => _cartCustomerName ?? '';
  set cartCustomerName(String? val) => _cartCustomerName = val;

  bool hasCartCustomerName() => _cartCustomerName != null;

  // "cart_note" field.
  String? _cartNote;
  String get cartNote => _cartNote ?? '';
  set cartNote(String? val) => _cartNote = val;

  bool hasCartNote() => _cartNote != null;

  // "cart_created_at" field.
  String? _cartCreatedAt;
  String get cartCreatedAt => _cartCreatedAt ?? '';
  set cartCreatedAt(String? val) => _cartCreatedAt = val;

  bool hasCartCreatedAt() => _cartCreatedAt != null;

  // "cart_updated_at" field.
  String? _cartUpdatedAt;
  String get cartUpdatedAt => _cartUpdatedAt ?? '';
  set cartUpdatedAt(String? val) => _cartUpdatedAt = val;

  bool hasCartUpdatedAt() => _cartUpdatedAt != null;

  // "cart_metadata" field.
  CartMetadataStruct? _cartMetadata;
  CartMetadataStruct get cartMetadata => _cartMetadata ?? CartMetadataStruct();
  set cartMetadata(CartMetadataStruct? val) => _cartMetadata = val;

  void updateCartMetadata(Function(CartMetadataStruct) updateFn) {
    updateFn(_cartMetadata ??= CartMetadataStruct());
  }

  bool hasCartMetadata() => _cartMetadata != null;

  // "cartTotalLinePrice" field.
  int? _cartTotalLinePrice;
  int get cartTotalLinePrice => _cartTotalLinePrice ?? 0;
  set cartTotalLinePrice(int? val) => _cartTotalLinePrice = val;

  void incrementCartTotalLinePrice(int amount) =>
      cartTotalLinePrice = cartTotalLinePrice + amount;

  bool hasCartTotalLinePrice() => _cartTotalLinePrice != null;

  // "cartTotalItemDiscounts" field.
  int? _cartTotalItemDiscounts;
  int get cartTotalItemDiscounts => _cartTotalItemDiscounts ?? 0;
  set cartTotalItemDiscounts(int? val) => _cartTotalItemDiscounts = val;

  void incrementCartTotalItemDiscounts(int amount) =>
      cartTotalItemDiscounts = cartTotalItemDiscounts + amount;

  bool hasCartTotalItemDiscounts() => _cartTotalItemDiscounts != null;

  // "cartTotalCartDiscounts" field.
  int? _cartTotalCartDiscounts;
  int get cartTotalCartDiscounts => _cartTotalCartDiscounts ?? 0;
  set cartTotalCartDiscounts(int? val) => _cartTotalCartDiscounts = val;

  void incrementCartTotalCartDiscounts(int amount) =>
      cartTotalCartDiscounts = cartTotalCartDiscounts + amount;

  bool hasCartTotalCartDiscounts() => _cartTotalCartDiscounts != null;

  // "cartTotalDiscount" field.
  int? _cartTotalDiscount;
  int get cartTotalDiscount => _cartTotalDiscount ?? 0;
  set cartTotalDiscount(int? val) => _cartTotalDiscount = val;

  void incrementCartTotalDiscount(int amount) =>
      cartTotalDiscount = cartTotalDiscount + amount;

  bool hasCartTotalDiscount() => _cartTotalDiscount != null;

  // "cartSubtotalExcludingTax" field.
  int? _cartSubtotalExcludingTax;
  int get cartSubtotalExcludingTax => _cartSubtotalExcludingTax ?? 0;
  set cartSubtotalExcludingTax(int? val) => _cartSubtotalExcludingTax = val;

  void incrementCartSubtotalExcludingTax(int amount) =>
      cartSubtotalExcludingTax = cartSubtotalExcludingTax + amount;

  bool hasCartSubtotalExcludingTax() => _cartSubtotalExcludingTax != null;

  // "cartTotalTax" field.
  int? _cartTotalTax;
  int get cartTotalTax => _cartTotalTax ?? 0;
  set cartTotalTax(int? val) => _cartTotalTax = val;

  void incrementCartTotalTax(int amount) =>
      cartTotalTax = cartTotalTax + amount;

  bool hasCartTotalTax() => _cartTotalTax != null;

  // "cartTotalCartPrice" field.
  int? _cartTotalCartPrice;
  int get cartTotalCartPrice => _cartTotalCartPrice ?? 0;
  set cartTotalCartPrice(int? val) => _cartTotalCartPrice = val;

  void incrementCartTotalCartPrice(int amount) =>
      cartTotalCartPrice = cartTotalCartPrice + amount;

  bool hasCartTotalCartPrice() => _cartTotalCartPrice != null;

  static ShoppingCartStruct fromMap(Map<String, dynamic> data) =>
      ShoppingCartStruct(
        cartId: data['cart_id'] as String?,
        cartPosSessionId: data['cart_pos_session_id'] as String?,
        cartItems: getStructList(
          data['cart_items'],
          CartItemsStruct.fromMap,
        ),
        cartDiscounts: getStructList(
          data['cart_discounts'],
          CartDiscountsStruct.fromMap,
        ),
        cartTipAmount: castToType<int>(data['cart_tip_amount']),
        cartCustomerId: castToType<int>(data['cart_customer_id']),
        cartCustomerName: data['cart_customer_name'] as String?,
        cartNote: data['cart_note'] as String?,
        cartCreatedAt: data['cart_created_at'] as String?,
        cartUpdatedAt: data['cart_updated_at'] as String?,
        cartMetadata: data['cart_metadata'] is CartMetadataStruct
            ? data['cart_metadata']
            : CartMetadataStruct.maybeFromMap(data['cart_metadata']),
        cartTotalLinePrice: castToType<int>(data['cartTotalLinePrice']),
        cartTotalItemDiscounts: castToType<int>(data['cartTotalItemDiscounts']),
        cartTotalCartDiscounts: castToType<int>(data['cartTotalCartDiscounts']),
        cartTotalDiscount: castToType<int>(data['cartTotalDiscount']),
        cartSubtotalExcludingTax:
            castToType<int>(data['cartSubtotalExcludingTax']),
        cartTotalTax: castToType<int>(data['cartTotalTax']),
        cartTotalCartPrice: castToType<int>(data['cartTotalCartPrice']),
      );

  static ShoppingCartStruct? maybeFromMap(dynamic data) => data is Map
      ? ShoppingCartStruct.fromMap(data.cast<String, dynamic>())
      : null;

  Map<String, dynamic> toMap() => {
        'cart_id': _cartId,
        'cart_pos_session_id': _cartPosSessionId,
        'cart_items': _cartItems?.map((e) => e.toMap()).toList(),
        'cart_discounts': _cartDiscounts?.map((e) => e.toMap()).toList(),
        'cart_tip_amount': _cartTipAmount,
        'cart_customer_id': _cartCustomerId,
        'cart_customer_name': _cartCustomerName,
        'cart_note': _cartNote,
        'cart_created_at': _cartCreatedAt,
        'cart_updated_at': _cartUpdatedAt,
        'cart_metadata': _cartMetadata?.toMap(),
        'cartTotalLinePrice': _cartTotalLinePrice,
        'cartTotalItemDiscounts': _cartTotalItemDiscounts,
        'cartTotalCartDiscounts': _cartTotalCartDiscounts,
        'cartTotalDiscount': _cartTotalDiscount,
        'cartSubtotalExcludingTax': _cartSubtotalExcludingTax,
        'cartTotalTax': _cartTotalTax,
        'cartTotalCartPrice': _cartTotalCartPrice,
      }.withoutNulls;

  @override
  Map<String, dynamic> toSerializableMap() => {
        'cart_id': serializeParam(
          _cartId,
          ParamType.String,
        ),
        'cart_pos_session_id': serializeParam(
          _cartPosSessionId,
          ParamType.String,
        ),
        'cart_items': serializeParam(
          _cartItems,
          ParamType.DataStruct,
          isList: true,
        ),
        'cart_discounts': serializeParam(
          _cartDiscounts,
          ParamType.DataStruct,
          isList: true,
        ),
        'cart_tip_amount': serializeParam(
          _cartTipAmount,
          ParamType.int,
        ),
        'cart_customer_id': serializeParam(
          _cartCustomerId,
          ParamType.int,
        ),
        'cart_customer_name': serializeParam(
          _cartCustomerName,
          ParamType.String,
        ),
        'cart_note': serializeParam(
          _cartNote,
          ParamType.String,
        ),
        'cart_created_at': serializeParam(
          _cartCreatedAt,
          ParamType.String,
        ),
        'cart_updated_at': serializeParam(
          _cartUpdatedAt,
          ParamType.String,
        ),
        'cart_metadata': serializeParam(
          _cartMetadata,
          ParamType.DataStruct,
        ),
        'cartTotalLinePrice': serializeParam(
          _cartTotalLinePrice,
          ParamType.int,
        ),
        'cartTotalItemDiscounts': serializeParam(
          _cartTotalItemDiscounts,
          ParamType.int,
        ),
        'cartTotalCartDiscounts': serializeParam(
          _cartTotalCartDiscounts,
          ParamType.int,
        ),
        'cartTotalDiscount': serializeParam(
          _cartTotalDiscount,
          ParamType.int,
        ),
        'cartSubtotalExcludingTax': serializeParam(
          _cartSubtotalExcludingTax,
          ParamType.int,
        ),
        'cartTotalTax': serializeParam(
          _cartTotalTax,
          ParamType.int,
        ),
        'cartTotalCartPrice': serializeParam(
          _cartTotalCartPrice,
          ParamType.int,
        ),
      }.withoutNulls;

  static ShoppingCartStruct fromSerializableMap(Map<String, dynamic> data) =>
      ShoppingCartStruct(
        cartId: deserializeParam(
          data['cart_id'],
          ParamType.String,
          false,
        ),
        cartPosSessionId: deserializeParam(
          data['cart_pos_session_id'],
          ParamType.String,
          false,
        ),
        cartItems: deserializeStructParam<CartItemsStruct>(
          data['cart_items'],
          ParamType.DataStruct,
          true,
          structBuilder: CartItemsStruct.fromSerializableMap,
        ),
        cartDiscounts: deserializeStructParam<CartDiscountsStruct>(
          data['cart_discounts'],
          ParamType.DataStruct,
          true,
          structBuilder: CartDiscountsStruct.fromSerializableMap,
        ),
        cartTipAmount: deserializeParam(
          data['cart_tip_amount'],
          ParamType.int,
          false,
        ),
        cartCustomerId: deserializeParam(
          data['cart_customer_id'],
          ParamType.int,
          false,
        ),
        cartCustomerName: deserializeParam(
          data['cart_customer_name'],
          ParamType.String,
          false,
        ),
        cartNote: deserializeParam(
          data['cart_note'],
          ParamType.String,
          false,
        ),
        cartCreatedAt: deserializeParam(
          data['cart_created_at'],
          ParamType.String,
          false,
        ),
        cartUpdatedAt: deserializeParam(
          data['cart_updated_at'],
          ParamType.String,
          false,
        ),
        cartMetadata: deserializeStructParam(
          data['cart_metadata'],
          ParamType.DataStruct,
          false,
          structBuilder: CartMetadataStruct.fromSerializableMap,
        ),
        cartTotalLinePrice: deserializeParam(
          data['cartTotalLinePrice'],
          ParamType.int,
          false,
        ),
        cartTotalItemDiscounts: deserializeParam(
          data['cartTotalItemDiscounts'],
          ParamType.int,
          false,
        ),
        cartTotalCartDiscounts: deserializeParam(
          data['cartTotalCartDiscounts'],
          ParamType.int,
          false,
        ),
        cartTotalDiscount: deserializeParam(
          data['cartTotalDiscount'],
          ParamType.int,
          false,
        ),
        cartSubtotalExcludingTax: deserializeParam(
          data['cartSubtotalExcludingTax'],
          ParamType.int,
          false,
        ),
        cartTotalTax: deserializeParam(
          data['cartTotalTax'],
          ParamType.int,
          false,
        ),
        cartTotalCartPrice: deserializeParam(
          data['cartTotalCartPrice'],
          ParamType.int,
          false,
        ),
      );

  @override
  String toString() => 'ShoppingCartStruct(${toMap()})';

  @override
  bool operator ==(Object other) {
    const listEquality = ListEquality();

    return other is ShoppingCartStruct &&
        cartId == other.cartId &&
        cartPosSessionId == other.cartPosSessionId &&
        listEquality.equals(cartItems, other.cartItems) &&
        listEquality.equals(cartDiscounts, other.cartDiscounts) &&
        cartTipAmount == other.cartTipAmount &&
        cartCustomerId == other.cartCustomerId &&
        cartCustomerName == other.cartCustomerName &&
        cartNote == other.cartNote &&
        cartCreatedAt == other.cartCreatedAt &&
        cartUpdatedAt == other.cartUpdatedAt &&
        cartMetadata == other.cartMetadata &&
        cartTotalLinePrice == other.cartTotalLinePrice &&
        cartTotalItemDiscounts == other.cartTotalItemDiscounts &&
        cartTotalCartDiscounts == other.cartTotalCartDiscounts &&
        cartTotalDiscount == other.cartTotalDiscount &&
        cartSubtotalExcludingTax == other.cartSubtotalExcludingTax &&
        cartTotalTax == other.cartTotalTax &&
        cartTotalCartPrice == other.cartTotalCartPrice;
  }

  @override
  int get hashCode => const ListEquality().hash([
        cartId,
        cartPosSessionId,
        cartItems,
        cartDiscounts,
        cartTipAmount,
        cartCustomerId,
        cartCustomerName,
        cartNote,
        cartCreatedAt,
        cartUpdatedAt,
        cartMetadata,
        cartTotalLinePrice,
        cartTotalItemDiscounts,
        cartTotalCartDiscounts,
        cartTotalDiscount,
        cartSubtotalExcludingTax,
        cartTotalTax,
        cartTotalCartPrice
      ]);
}

ShoppingCartStruct createShoppingCartStruct({
  String? cartId,
  String? cartPosSessionId,
  int? cartTipAmount,
  int? cartCustomerId,
  String? cartCustomerName,
  String? cartNote,
  String? cartCreatedAt,
  String? cartUpdatedAt,
  CartMetadataStruct? cartMetadata,
  int? cartTotalLinePrice,
  int? cartTotalItemDiscounts,
  int? cartTotalCartDiscounts,
  int? cartTotalDiscount,
  int? cartSubtotalExcludingTax,
  int? cartTotalTax,
  int? cartTotalCartPrice,
}) =>
    ShoppingCartStruct(
      cartId: cartId,
      cartPosSessionId: cartPosSessionId,
      cartTipAmount: cartTipAmount,
      cartCustomerId: cartCustomerId,
      cartCustomerName: cartCustomerName,
      cartNote: cartNote,
      cartCreatedAt: cartCreatedAt,
      cartUpdatedAt: cartUpdatedAt,
      cartMetadata: cartMetadata ?? CartMetadataStruct(),
      cartTotalLinePrice: cartTotalLinePrice,
      cartTotalItemDiscounts: cartTotalItemDiscounts,
      cartTotalCartDiscounts: cartTotalCartDiscounts,
      cartTotalDiscount: cartTotalDiscount,
      cartSubtotalExcludingTax: cartSubtotalExcludingTax,
      cartTotalTax: cartTotalTax,
      cartTotalCartPrice: cartTotalCartPrice,
    );

