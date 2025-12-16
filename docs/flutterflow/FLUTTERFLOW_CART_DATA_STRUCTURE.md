# POS Shopping Cart Data Structure for FlutterFlow

## Overview

This document defines the recommended data structure for managing shopping cart state in FlutterFlow. The cart is managed entirely in the frontend and only sent to the backend when the transaction is finalized.

---

## Core Data Models

### 1. CartItem Model

```dart
class CartItem {
  final String id;                    // Unique cart item ID (UUID)
  final String productId;             // Product ID from backend
  final String? variantId;            // Product variant ID (if applicable)
  final String productName;           // Product name for display
  final String? productImageUrl;      // Product image URL
  final int unitPrice;                // Price per unit in øre (smallest currency unit)
  final double quantity;              // Quantity of this item (supports decimals for continuous units like meters, kilograms)
  final int? originalPrice;           // Original price before discount (if discounted)
  final int? discountAmount;          // Discount amount in øre (if applicable)
  final String? discountReason;       // Reason for discount (e.g., "Manager override")
  final String? articleGroupCode;      // SAF-T article group code
  final String? productCode;           // SAF-T product code (PLU)
  final Map<String, dynamic>? metadata; // Additional item metadata
  
  // Calculated properties
  int get subtotal => (unitPrice * quantity).round();
  int get totalDiscount => ((discountAmount ?? 0) * quantity).round();
  int get total => subtotal - totalDiscount;
  
  CartItem({
    required this.id,
    required this.productId,
    this.variantId,
    required this.productName,
    this.productImageUrl,
    required this.unitPrice,
    this.quantity = 1,
    this.originalPrice,
    this.discountAmount,
    this.discountReason,
    this.articleGroupCode,
    this.productCode,
    this.metadata,
  });
  
  CartItem copyWith({
    String? id,
    String? productId,
    String? variantId,
    String? productName,
    String? productImageUrl,
    int? unitPrice,
    double? quantity,
    int? originalPrice,
    int? discountAmount,
    String? discountReason,
    String? articleGroupCode,
    String? productCode,
    Map<String, dynamic>? metadata,
  }) {
    return CartItem(
      id: id ?? this.id,
      productId: productId ?? this.productId,
      variantId: variantId ?? this.variantId,
      productName: productName ?? this.productName,
      productImageUrl: productImageUrl ?? this.productImageUrl,
      unitPrice: unitPrice ?? this.unitPrice,
      quantity: quantity ?? this.quantity,
      originalPrice: originalPrice ?? this.originalPrice,
      discountAmount: discountAmount ?? this.discountAmount,
      discountReason: discountReason ?? this.discountReason,
      articleGroupCode: articleGroupCode ?? this.articleGroupCode,
      productCode: productCode ?? this.productCode,
      metadata: metadata ?? this.metadata,
    );
  }
  
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'product_id': productId,
      'variant_id': variantId,
      'product_name': productName,
      'product_image_url': productImageUrl,
      'unit_price': unitPrice,
      'quantity': quantity,
      'original_price': originalPrice,
      'discount_amount': discountAmount,
      'discount_reason': discountReason,
      'article_group_code': articleGroupCode,
      'product_code': productCode,
      'metadata': metadata,
    };
  }
  
  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      id: json['id'] as String,
      productId: json['product_id'] as String,
      variantId: json['variant_id'] as String?,
      productName: json['product_name'] as String,
      productImageUrl: json['product_image_url'] as String?,
      unitPrice: json['unit_price'] as int,
      quantity: (json['quantity'] as num?)?.toDouble() ?? 1.0,
      originalPrice: json['original_price'] as int?,
      discountAmount: json['discount_amount'] as int?,
      discountReason: json['discount_reason'] as String?,
      articleGroupCode: json['article_group_code'] as String?,
      productCode: json['product_code'] as String?,
      metadata: json['metadata'] as Map<String, dynamic>?,
    );
  }
}
```

### 2. CartDiscount Model

```dart
class CartDiscount {
  final String id;                    // Discount ID
  final String type;                  // 'coupon', 'manual', 'percentage', 'fixed'
  final String? couponId;             // Coupon ID (if from coupon)
  final String? couponCode;           // Coupon code (if applicable)
  final String description;           // Discount description
  final int amount;                   // Discount amount in øre
  final double? percentage;           // Percentage discount (if applicable)
  final String? reason;                // Reason for discount (if manual)
  final bool requiresApproval;        // Whether discount requires manager approval
  
  CartDiscount({
    required this.id,
    required this.type,
    this.couponId,
    this.couponCode,
    required this.description,
    required this.amount,
    this.percentage,
    this.reason,
    this.requiresApproval = false,
  });
  
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'coupon_id': couponId,
      'coupon_code': couponCode,
      'description': description,
      'amount': amount,
      'percentage': percentage,
      'reason': reason,
      'requires_approval': requiresApproval,
    };
  }
  
  factory CartDiscount.fromJson(Map<String, dynamic> json) {
    return CartDiscount(
      id: json['id'] as String,
      type: json['type'] as String,
      couponId: json['coupon_id'] as String?,
      couponCode: json['coupon_code'] as String?,
      description: json['description'] as String,
      amount: json['amount'] as int,
      percentage: json['percentage'] as double?,
      reason: json['reason'] as String?,
      requiresApproval: json['requires_approval'] as bool? ?? false,
    );
  }
}
```

### 3. ShoppingCart Model

```dart
class ShoppingCart {
  final String id;                    // Cart ID (UUID)
  final String? posSessionId;         // POS session ID (if session is open)
  final List<CartItem> items;         // Cart items
  final List<CartDiscount> discounts; // Applied discounts
  final int? tipAmount;               // Tip amount in øre
  final String? customerId;           // Customer ID (if customer selected)
  final String? customerName;         // Customer name for display
  final String? note;                 // Optional note/comment for the purchase
  final DateTime createdAt;           // Cart creation timestamp
  final DateTime updatedAt;           // Last update timestamp
  final Map<String, dynamic>? metadata; // Additional cart metadata
  
  // Calculated properties
  int get itemsSubtotal {
    return items.fold(0, (sum, item) => sum + item.subtotal);
  }
  
  int get itemsTotalDiscount {
    return items.fold(0, (sum, item) => sum + item.totalDiscount);
  }
  
  int get cartDiscountsTotal {
    return discounts.fold(0, (sum, discount) => sum + discount.amount);
  }
  
  int get subtotal {
    return itemsSubtotal - itemsTotalDiscount - cartDiscountsTotal;
  }
  
  int get taxAmount {
    // Calculate 25% VAT (Norwegian standard)
    return (subtotal * 0.25).round();
  }
  
  int get total {
    return subtotal + taxAmount + (tipAmount ?? 0);
  }
  
  int get itemCount {
    return items.fold(0, (sum, item) => sum + item.quantity.round());
  }
  
  bool get isEmpty => items.isEmpty;
  bool get hasItems => items.isNotEmpty;
  
  ShoppingCart({
    required this.id,
    this.posSessionId,
    List<CartItem>? items,
    List<CartDiscount>? discounts,
    this.tipAmount,
    this.customerId,
    this.customerName,
    this.note,
    DateTime? createdAt,
    DateTime? updatedAt,
    this.metadata,
  })  : items = items ?? [],
        discounts = discounts ?? [],
        createdAt = createdAt ?? DateTime.now(),
        updatedAt = updatedAt ?? DateTime.now();
  
  ShoppingCart copyWith({
    String? id,
    String? posSessionId,
    List<CartItem>? items,
    List<CartDiscount>? discounts,
    int? tipAmount,
    String? customerId,
    String? customerName,
    String? note,
    DateTime? createdAt,
    DateTime? updatedAt,
    Map<String, dynamic>? metadata,
  }) {
    return ShoppingCart(
      id: id ?? this.id,
      posSessionId: posSessionId ?? this.posSessionId,
      items: items ?? this.items,
      discounts: discounts ?? this.discounts,
      tipAmount: tipAmount ?? this.tipAmount,
      customerId: customerId ?? this.customerId,
      customerName: customerName ?? this.customerName,
      note: note ?? this.note,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? DateTime.now(),
      metadata: metadata ?? this.metadata,
    );
  }
  
  // Add item to cart
  ShoppingCart addItem(CartItem item) {
    // Check if item already exists (same product + variant)
    final existingIndex = items.indexWhere(
      (i) => i.productId == item.productId && i.variantId == item.variantId,
    );
    
    if (existingIndex >= 0) {
      // Update quantity of existing item
      final existingItem = items[existingIndex];
      final updatedItems = List<CartItem>.from(items);
      updatedItems[existingIndex] = existingItem.copyWith(
        quantity: existingItem.quantity + item.quantity,
      );
      return copyWith(items: updatedItems);
    } else {
      // Add new item
      return copyWith(items: [...items, item]);
    }
  }
  
  // Remove item from cart
  ShoppingCart removeItem(String itemId) {
    return copyWith(
      items: items.where((item) => item.id != itemId).toList(),
    );
  }
  
  // Update item quantity
  ShoppingCart updateItemQuantity(String itemId, double quantity) {
    if (quantity <= 0) {
      return removeItem(itemId);
    }
    
    final updatedItems = items.map((item) {
      if (item.id == itemId) {
        return item.copyWith(quantity: quantity);
      }
      return item;
    }).toList();
    
    return copyWith(items: updatedItems);
  }
  
  // Apply discount to item
  ShoppingCart applyItemDiscount(String itemId, int discountAmount, {String? reason}) {
    final updatedItems = items.map((item) {
      if (item.id == itemId) {
        return item.copyWith(
          discountAmount: discountAmount,
          discountReason: reason,
          originalPrice: item.originalPrice ?? item.unitPrice,
        );
      }
      return item;
    }).toList();
    
    return copyWith(items: updatedItems);
  }
  
  // Remove discount from item
  ShoppingCart removeItemDiscount(String itemId) {
    final updatedItems = items.map((item) {
      if (item.id == itemId) {
        return item.copyWith(
          discountAmount: null,
          discountReason: null,
          originalPrice: null,
        );
      }
      return item;
    }).toList();
    
    return copyWith(items: updatedItems);
  }
  
  // Add cart-level discount
  ShoppingCart addDiscount(CartDiscount discount) {
    return copyWith(
      discounts: [...discounts, discount],
    );
  }
  
  // Remove cart-level discount
  ShoppingCart removeDiscount(String discountId) {
    return copyWith(
      discounts: discounts.where((d) => d.id != discountId).toList(),
    );
  }
  
  // Set tip amount
  ShoppingCart setTip(int amount) {
    return copyWith(tipAmount: amount);
  }
  
  // Set customer
  ShoppingCart setCustomer(String customerId, String customerName) {
    return copyWith(
      customerId: customerId,
      customerName: customerName,
    );
  }
  
  // Clear cart
  ShoppingCart clear() {
    return ShoppingCart(
      id: id,
      posSessionId: posSessionId,
      items: [],
      discounts: [],
      tipAmount: null,
      customerId: customerId,
      customerName: customerName,
    );
  }
  
  // Convert to JSON for API request
  Map<String, dynamic> toCheckoutJson() {
    return {
      'pos_session_id': posSessionId,
      'customer_id': customerId,
      'customer_name': customerName,
      'note': note,
      'items': items.map((item) => {
        'product_id': item.productId,
        'variant_id': item.variantId,
        'quantity': item.quantity,
        'unit_price': item.unitPrice,
        'discount_amount': item.discountAmount,
        'discount_reason': item.discountReason,
        'article_group_code': item.articleGroupCode,
        'product_code': item.productCode,
      }).toList(),
      'discounts': discounts.map((discount) => discount.toJson()).toList(),
      'subtotal': subtotal,
      'tax_amount': taxAmount,
      'tip_amount': tipAmount ?? 0,
      'total': total,
      'currency': 'nok',
      'metadata': {
        ...?metadata,
        'item_count': itemCount,
        'cart_id': id,
      },
    };
  }
  
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'pos_session_id': posSessionId,
      'items': items.map((item) => item.toJson()).toList(),
      'discounts': discounts.map((discount) => discount.toJson()).toList(),
      'tip_amount': tipAmount,
      'customer_id': customerId,
      'customer_name': customerName,
      'note': note,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
      'metadata': metadata,
    };
  }
  
  factory ShoppingCart.fromJson(Map<String, dynamic> json) {
    return ShoppingCart(
      id: json['id'] as String,
      posSessionId: json['pos_session_id'] as String?,
      items: (json['items'] as List<dynamic>?)
          ?.map((item) => CartItem.fromJson(item as Map<String, dynamic>))
          .toList() ?? [],
      discounts: (json['discounts'] as List<dynamic>?)
          ?.map((discount) => CartDiscount.fromJson(discount as Map<String, dynamic>))
          .toList() ?? [],
      tipAmount: json['tip_amount'] as int?,
      customerId: json['customer_id'] as String?,
      customerName: json['customer_name'] as String?,
      note: json['note'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
      metadata: json['metadata'] as Map<String, dynamic>?,
    );
  }
}
```

---

## Usage Examples

### 1. Initialize Cart

```dart
final cart = ShoppingCart(
  id: Uuid().v4(),
  posSessionId: currentSessionId,
);
```

### 2. Add Product to Cart

```dart
final product = Product(
  id: 'prod_123',
  name: 'Coffee',
  price: 5000, // 50.00 NOK in øre
  articleGroupCode: '01',
  productCode: 'COFFEE001',
);

final cartItem = CartItem(
  id: Uuid().v4(),
  productId: product.id,
  productName: product.name,
  unitPrice: product.price,
  quantity: 1,
  articleGroupCode: product.articleGroupCode,
  productCode: product.productCode,
);

cart = cart.addItem(cartItem);
```

### 3. Update Quantity

```dart
cart = cart.updateItemQuantity(cartItemId, 3);
```

### 4. Apply Item Discount

```dart
cart = cart.applyItemDiscount(
  cartItemId,
  1000, // 10.00 NOK discount
  reason: 'Manager override',
);
```

### 5. Apply Coupon Discount

```dart
final coupon = Coupon(
  id: 'coupon_123',
  code: 'SUMMER2024',
  amount: 2000, // 20.00 NOK
);

final discount = CartDiscount(
  id: Uuid().v4(),
  type: 'coupon',
  couponId: coupon.id,
  couponCode: coupon.code,
  description: 'Summer Sale',
  amount: coupon.amount,
);

cart = cart.addDiscount(discount);
```

### 6. Add Tip

```dart
cart = cart.setTip(500); // 5.00 NOK tip
```

### 7. Set Customer

```dart
cart = cart.setCustomer(
  'cust_123',
  'John Doe',
);
```

### 8. Checkout (Send to Backend)

```dart
final checkoutData = cart.toCheckoutJson();

final response = await http.post(
  Uri.parse('$apiBaseUrl/api/charges/create'),
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer $token',
  },
  body: jsonEncode(checkoutData),
);
```

---

## FlutterFlow Implementation Notes

### State Management

1. **Use App State Variable**
   - Create an app state variable: `cart` (type: Custom Type - ShoppingCart)
   - This persists across pages

2. **Actions for Cart Operations**
   - `addItemToCart(product, quantity)`
   - `removeItemFromCart(itemId)`
   - `updateItemQuantity(itemId, quantity)`
   - `applyDiscount(discount)`
   - `setTip(amount)`
   - `clearCart()`

3. **Computed Properties**
   - `cart.subtotal` - Display subtotal
   - `cart.taxAmount` - Display tax
   - `cart.total` - Display total
   - `cart.itemCount` - Display item count badge

### Local Storage (Optional)

If you want to persist cart across app restarts:

```dart
// Save cart
await SharedPreferences.getInstance().then((prefs) {
  prefs.setString('cart', jsonEncode(cart.toJson()));
});

// Load cart
final cartJson = prefs.getString('cart');
if (cartJson != null) {
  cart = ShoppingCart.fromJson(jsonDecode(cartJson));
}
```

### Backend API Endpoint Expected Format

The backend expects this format when checkout is called:

```json
{
  "pos_session_id": 123,
  "customer_id": "cust_456",
  "items": [
    {
      "product_id": "prod_789",
      "variant_id": "var_101",
      "quantity": 2,
      "unit_price": 5000,
      "discount_amount": 1000,
      "discount_reason": "Manager override",
      "article_group_code": "01",
      "product_code": "COFFEE001"
    }
  ],
  "discounts": [
    {
      "id": "disc_123",
      "type": "coupon",
      "coupon_id": "coupon_456",
      "coupon_code": "SUMMER2024",
      "description": "Summer Sale",
      "amount": 2000
    }
  ],
  "subtotal": 8000,
  "tax_amount": 2000,
  "tip_amount": 500,
  "total": 10500,
  "currency": "nok",
  "metadata": {
    "item_count": 2,
    "cart_id": "cart_uuid"
  }
}
```

---

## Benefits of This Structure

1. **Type Safety** - Strong typing prevents errors
2. **Immutability** - Cart operations return new instances (easier state management)
3. **Calculated Properties** - Totals computed automatically
4. **Serialization** - Easy conversion to/from JSON
5. **Extensible** - Easy to add new features (loyalty points, etc.)
6. **POS-Ready** - Includes SAF-T codes, tips, discounts
7. **Backend Compatible** - `toCheckoutJson()` formats data for API

---

## Additional Considerations

### Price Override Tracking (Optional)

If you want to track price overrides for audit purposes (event 13022):

```dart
CartItem applyPriceOverride(int newPrice, String reason) {
  return copyWith(
    unitPrice: newPrice,
    originalPrice: unitPrice, // Store original
    metadata: {
      ...?metadata,
      'price_override': true,
      'override_reason': reason,
      'override_timestamp': DateTime.now().toIso8601String(),
    },
  );
}
```

### Inventory Validation

Before adding to cart, validate inventory:

```dart
Future<bool> validateInventory(String productId, String? variantId, int quantity) async {
  final response = await http.get(
    Uri.parse('$apiBaseUrl/api/products/$productId/inventory'),
  );
  
  final inventory = jsonDecode(response.body);
  final available = variantId != null
      ? inventory['variants'][variantId]['available']
      : inventory['available'];
  
  return available >= quantity;
}
```

---

## Summary

This data structure provides:
- ✅ Complete cart management in FlutterFlow
- ✅ Support for items, discounts, tips, customers
- ✅ Automatic calculation of totals
- ✅ Easy serialization for backend API
- ✅ POS-specific features (SAF-T codes, tips)
- ✅ Type-safe and maintainable
- ✅ Immutable operations (better state management)

The cart is entirely frontend-managed until checkout, when it's sent to the backend as a complete transaction.

