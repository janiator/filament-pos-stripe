# FlutterFlow Implementation Guide: POS Shopping Cart

## Step-by-Step Guide

This guide walks you through implementing the POS shopping cart data structure in FlutterFlow.

---

## Prerequisites

- FlutterFlow project set up
- Backend API endpoints ready
- Authentication configured
- POS session management working

---

## Step 1: Create Custom Data Types

### 1.1 Create CartItem Type

1. Go to **Data Types** → **Custom Data Types**
2. Click **+ Add Custom Data Type**
3. Name: `CartItem`
4. Add the following fields:

| Field Name | Type | Required | Default Value |
|-----------|------|----------|---------------|
| `id` | String | ✅ Yes | - |
| `productId` | String | ✅ Yes | - |
| `variantId` | String | ❌ No | - |
| `productName` | String | ✅ Yes | - |
| `productImageUrl` | String | ❌ No | - |
| `unitPrice` | Integer | ✅ Yes | 0 |
| `quantity` | Integer | ✅ Yes | 1 |
| `originalPrice` | Integer | ❌ No | - |
| `discountAmount` | Integer | ❌ No | - |
| `discountReason` | String | ❌ No | - |
| `articleGroupCode` | String | ❌ No | - |
| `productCode` | String | ❌ No | - |
| `metadata` | JSON | ❌ No | - |

### 1.2 Create CartDiscount Type

1. Click **+ Add Custom Data Type**
2. Name: `CartDiscount`
3. Add the following fields:

| Field Name | Type | Required | Default Value |
|-----------|------|----------|---------------|
| `id` | String | ✅ Yes | - |
| `type` | String | ✅ Yes | - |
| `couponId` | String | ❌ No | - |
| `couponCode` | String | ❌ No | - |
| `description` | String | ✅ Yes | - |
| `amount` | Integer | ✅ Yes | 0 |
| `percentage` | Double | ❌ No | - |
| `reason` | String | ❌ No | - |
| `requiresApproval` | Boolean | ✅ Yes | false |

### 1.3 Create ShoppingCart Type

1. Click **+ Add Custom Data Type**
2. Name: `ShoppingCart`
3. Add the following fields:

| Field Name | Type | Required | Default Value |
|-----------|------|----------|---------------|
| `id` | String | ✅ Yes | - |
| `posSessionId` | String | ❌ No | - |
| `items` | List<CartItem> | ✅ Yes | [] |
| `discounts` | List<CartDiscount> | ✅ Yes | [] |
| `tipAmount` | Integer | ❌ No | - |
| `customerId` | String | ❌ No | - |
| `customerName` | String | ❌ No | - |
| `createdAt` | DateTime | ✅ Yes | - |
| `updatedAt` | DateTime | ✅ Yes | - |
| `metadata` | JSON | ❌ No | - |

---

## Step 2: Create App State Variables

### 2.1 Create Cart App State

1. Go to **App State** → **App State Variables**
2. Click **+ Add App State Variable**
3. Configure:
   - **Name**: `cart`
   - **Type**: `ShoppingCart`
   - **Initial Value**: Create a new ShoppingCart with:
     - `id`: Generate UUID (use `generateUUID()` action)
     - `items`: Empty list `[]`
     - `discounts`: Empty list `[]`
     - `createdAt`: Current date/time
     - `updatedAt`: Current date/time

### 2.2 Create Computed Properties (Optional but Recommended)

Create these as **Computed Properties** in App State:

1. **cartSubtotal**
   - Type: `Integer`
   - Formula: Sum of all `cart.items[].subtotal` (you'll need a custom action for this)

2. **cartTaxAmount**
   - Type: `Integer`
   - Formula: `cartSubtotal * 0.25` (25% VAT)

3. **cartTotal**
   - Type: `Integer`
   - Formula: `cartSubtotal + cartTaxAmount + (cart.tipAmount ?? 0)`

4. **cartItemCount**
   - Type: `Integer`
   - Formula: Sum of all `cart.items[].quantity`

---

## Step 3: Create Custom Actions

### 3.1 Action: Add Item to Cart

1. Go to **Actions** → **Custom Actions**
2. Click **+ Add Action**
3. Name: `addItemToCart`
4. **Parameters**:
   - `product` (Product type) - Required
   - `quantity` (Integer) - Default: 1
   - `variant` (ProductVariant) - Optional

5. **Action Code** (Backend):
```dart
// Get current cart from app state
final currentCart = FFAppState().cart;

// Create cart item
final cartItem = CartItemStruct(
  id: generateUUID(),
  productId: product.id,
  variantId: variant?.id,
  productName: product.name,
  productImageUrl: product.imageUrl,
  unitPrice: variant?.price ?? product.price,
  quantity: quantity,
  articleGroupCode: product.articleGroupCode,
  productCode: product.productCode,
);

// Check if item already exists
final existingIndex = currentCart.items.indexWhere(
  (item) => item.productId == product.id && 
            item.variantId == (variant?.id ?? ''),
);

if (existingIndex >= 0) {
  // Update quantity of existing item
  final existingItem = currentCart.items[existingIndex];
  final updatedItem = CartItemStruct(
    id: existingItem.id,
    productId: existingItem.productId,
    variantId: existingItem.variantId,
    productName: existingItem.productName,
    productImageUrl: existingItem.productImageUrl,
    unitPrice: existingItem.unitPrice,
    quantity: existingItem.quantity + quantity,
    originalPrice: existingItem.originalPrice,
    discountAmount: existingItem.discountAmount,
    discountReason: existingItem.discountReason,
    articleGroupCode: existingItem.articleGroupCode,
    productCode: existingItem.productCode,
    metadata: existingItem.metadata,
  );
  
  final updatedItems = List<CartItemStruct>.from(currentCart.items);
  updatedItems[existingIndex] = updatedItem;
  
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      id: currentCart.id,
      posSessionId: currentCart.posSessionId,
      items: updatedItems,
      discounts: currentCart.discounts,
      tipAmount: currentCart.tipAmount,
      customerId: currentCart.customerId,
      customerName: currentCart.customerName,
      createdAt: currentCart.createdAt,
      updatedAt: getCurrentTimestamp,
      metadata: currentCart.metadata,
    );
  });
} else {
  // Add new item
  final updatedItems = [...currentCart.items, cartItem];
  
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      id: currentCart.id,
      posSessionId: currentCart.posSessionId,
      items: updatedItems,
      discounts: currentCart.discounts,
      tipAmount: currentCart.tipAmount,
      customerId: currentCart.customerId,
      customerName: currentCart.customerName,
      createdAt: currentCart.createdAt,
      updatedAt: getCurrentTimestamp,
      metadata: currentCart.metadata,
    );
  });
}
```

### 3.2 Action: Remove Item from Cart

1. Name: `removeItemFromCart`
2. **Parameters**:
   - `itemId` (String) - Required

3. **Action Code**:
```dart
final currentCart = FFAppState().cart;

FFAppState().update(() {
  FFAppState().cart = ShoppingCartStruct(
    id: currentCart.id,
    posSessionId: currentCart.posSessionId,
    items: currentCart.items.where((item) => item.id != itemId).toList(),
    discounts: currentCart.discounts,
    tipAmount: currentCart.tipAmount,
    customerId: currentCart.customerId,
    customerName: currentCart.customerName,
    createdAt: currentCart.createdAt,
    updatedAt: getCurrentTimestamp,
    metadata: currentCart.metadata,
  );
});
```

### 3.3 Action: Update Item Quantity

1. Name: `updateItemQuantity`
2. **Parameters**:
   - `itemId` (String) - Required
   - `quantity` (Integer) - Required

3. **Action Code**:
```dart
final currentCart = FFAppState().cart;

if (quantity <= 0) {
  // Remove item if quantity is 0 or less
  removeItemFromCart(itemId);
  return;
}

final updatedItems = currentCart.items.map((item) {
  if (item.id == itemId) {
    return CartItemStruct(
      id: item.id,
      productId: item.productId,
      variantId: item.variantId,
      productName: item.productName,
      productImageUrl: item.productImageUrl,
      unitPrice: item.unitPrice,
      quantity: quantity,
      originalPrice: item.originalPrice,
      discountAmount: item.discountAmount,
      discountReason: item.discountReason,
      articleGroupCode: item.articleGroupCode,
      productCode: item.productCode,
      metadata: item.metadata,
    );
  }
  return item;
}).toList();

FFAppState().update(() {
  FFAppState().cart = ShoppingCartStruct(
    id: currentCart.id,
    posSessionId: currentCart.posSessionId,
    items: updatedItems,
    discounts: currentCart.discounts,
    tipAmount: currentCart.tipAmount,
    customerId: currentCart.customerId,
    customerName: currentCart.customerName,
    createdAt: currentCart.createdAt,
    updatedAt: getCurrentTimestamp,
    metadata: currentCart.metadata,
  );
});
```

### 3.4 Action: Apply Item Discount

1. Name: `applyItemDiscount`
2. **Parameters**:
   - `itemId` (String) - Required
   - `discountAmount` (Integer) - Required
   - `reason` (String) - Optional

3. **Action Code**:
```dart
final currentCart = FFAppState().cart;

final updatedItems = currentCart.items.map((item) {
  if (item.id == itemId) {
    return CartItemStruct(
      id: item.id,
      productId: item.productId,
      variantId: item.variantId,
      productName: item.productName,
      productImageUrl: item.productImageUrl,
      unitPrice: item.unitPrice,
      quantity: item.quantity,
      originalPrice: item.originalPrice ?? item.unitPrice,
      discountAmount: discountAmount,
      discountReason: reason,
      articleGroupCode: item.articleGroupCode,
      productCode: item.productCode,
      metadata: item.metadata,
    );
  }
  return item;
}).toList();

FFAppState().update(() {
  FFAppState().cart = ShoppingCartStruct(
    id: currentCart.id,
    posSessionId: currentCart.posSessionId,
    items: updatedItems,
    discounts: currentCart.discounts,
    tipAmount: currentCart.tipAmount,
    customerId: currentCart.customerId,
    customerName: currentCart.customerName,
    createdAt: currentCart.createdAt,
    updatedAt: getCurrentTimestamp,
    metadata: currentCart.metadata,
  );
});
```

### 3.5 Action: Add Cart Discount (Coupon)

1. Name: `addCartDiscount`
2. **Parameters**:
   - `coupon` (Coupon type) - Required

3. **Action Code**:
```dart
final currentCart = FFAppState().cart;

final discount = CartDiscountStruct(
  id: generateUUID(),
  type: 'coupon',
  couponId: coupon.id,
  couponCode: coupon.code,
  description: coupon.name,
  amount: coupon.discountAmount,
  percentage: coupon.discountPercentage,
  requiresApproval: false,
);

FFAppState().update(() {
  FFAppState().cart = ShoppingCartStruct(
    id: currentCart.id,
    posSessionId: currentCart.posSessionId,
    items: currentCart.items,
    discounts: [...currentCart.discounts, discount],
    tipAmount: currentCart.tipAmount,
    customerId: currentCart.customerId,
    customerName: currentCart.customerName,
    createdAt: currentCart.createdAt,
    updatedAt: getCurrentTimestamp,
    metadata: currentCart.metadata,
  );
});
```

### 3.6 Action: Set Tip Amount

1. Name: `setCartTip`
2. **Parameters**:
   - `amount` (Integer) - Required

3. **Action Code**:
```dart
final currentCart = FFAppState().cart;

FFAppState().update(() {
  FFAppState().cart = ShoppingCartStruct(
    id: currentCart.id,
    posSessionId: currentCart.posSessionId,
    items: currentCart.items,
    discounts: currentCart.discounts,
    tipAmount: amount,
    customerId: currentCart.customerId,
    customerName: currentCart.customerName,
    createdAt: currentCart.createdAt,
    updatedAt: getCurrentTimestamp,
    metadata: currentCart.metadata,
  );
});
```

### 3.7 Action: Clear Cart

1. Name: `clearCart`
2. **Action Code**:
```dart
FFAppState().update(() {
  FFAppState().cart = ShoppingCartStruct(
    id: generateUUID(),
    posSessionId: FFAppState().cart.posSessionId,
    items: [],
    discounts: [],
    tipAmount: null,
    customerId: null,
    customerName: null,
    createdAt: getCurrentTimestamp,
    updatedAt: getCurrentTimestamp,
    metadata: null,
  );
});
```

### 3.8 Action: Calculate Cart Totals

1. Name: `calculateCartTotals`
2. **Returns**: `Map<String, dynamic>`
3. **Action Code**:
```dart
final cart = FFAppState().cart;

// Calculate items subtotal
int itemsSubtotal = 0;
int itemsTotalDiscount = 0;

for (var item in cart.items) {
  final itemSubtotal = item.unitPrice * item.quantity;
  final itemDiscount = (item.discountAmount ?? 0) * item.quantity;
  itemsSubtotal += itemSubtotal;
  itemsTotalDiscount += itemDiscount;
}

// Calculate cart discounts
int cartDiscountsTotal = 0;
for (var discount in cart.discounts) {
  cartDiscountsTotal += discount.amount;
}

// Calculate subtotal
final subtotal = itemsSubtotal - itemsTotalDiscount - cartDiscountsTotal;

// Calculate tax (25% VAT)
final taxAmount = (subtotal * 0.25).round();

// Calculate total
final total = subtotal + taxAmount + (cart.tipAmount ?? 0);

return {
  'itemsSubtotal': itemsSubtotal,
  'itemsTotalDiscount': itemsTotalDiscount,
  'cartDiscountsTotal': cartDiscountsTotal,
  'subtotal': subtotal,
  'taxAmount': taxAmount,
  'total': total,
  'itemCount': cart.items.fold(0, (sum, item) => sum + item.quantity),
};
```

---

## Step 4: Create API Call Actions

### 4.1 Action: Checkout (Create Charge)

1. Name: `checkoutCart`
2. **Parameters**:
   - `paymentMethod` (String) - Required ('card', 'cash', 'mobile')
   - `paymentIntentId` (String) - Optional (for Stripe Terminal)

3. **API Call Configuration**:
   - **Method**: POST
   - **URL**: `{{apiBaseUrl}}/api/charges/create`
   - **Headers**:
     - `Authorization`: `Bearer {{authToken}}`
     - `Content-Type`: `application/json`
   - **Body** (JSON):
```json
{
  "pos_session_id": "{{FFAppState().cart.posSessionId}}",
  "customer_id": "{{FFAppState().cart.customerId}}",
  "items": [
    {{#each FFAppState().cart.items}}
    {
      "product_id": "{{this.productId}}",
      "variant_id": "{{this.variantId}}",
      "quantity": {{this.quantity}},
      "unit_price": {{this.unitPrice}},
      "discount_amount": {{this.discountAmount ?? 0}},
      "discount_reason": "{{this.discountReason ?? ''}}",
      "article_group_code": "{{this.articleGroupCode ?? ''}}",
      "product_code": "{{this.productCode ?? ''}}"
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ],
  "discounts": [
    {{#each FFAppState().cart.discounts}}
    {
      "id": "{{this.id}}",
      "type": "{{this.type}}",
      "coupon_id": "{{this.couponId ?? ''}}",
      "coupon_code": "{{this.couponCode ?? ''}}",
      "description": "{{this.description}}",
      "amount": {{this.amount}}
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ],
  "subtotal": {{calculateCartTotals()['subtotal']}},
  "tax_amount": {{calculateCartTotals()['taxAmount']}},
  "tip_amount": {{FFAppState().cart.tipAmount ?? 0}},
  "total": {{calculateCartTotals()['total']}},
  "currency": "nok",
  "payment_method": "{{paymentMethod}}",
  "payment_intent_id": "{{paymentIntentId ?? ''}}"
}
```

4. **Success Action**:
   - Clear cart: `clearCart()`
   - Show success message
   - Navigate to receipt/success page

5. **Error Action**:
   - Show error message
   - Keep cart intact

---

## Step 5: Create UI Components

### 5.1 Cart Item Widget

Create a reusable widget for displaying cart items:

**Components**:
- Image (product image)
- Text (product name)
- Text (price per unit)
- Text (quantity)
- Text (item total)
- Button (remove)
- Button (edit quantity)

**Actions**:
- On remove button tap: `removeItemFromCart(item.id)`
- On quantity change: `updateItemQuantity(item.id, newQuantity)`

### 5.2 Cart Summary Widget

Display cart totals:

**Components**:
- Text: "Subtotal: {{formatCurrency(calculateCartTotals()['subtotal'])}}"
- Text: "Tax (25%): {{formatCurrency(calculateCartTotals()['taxAmount'])}}"
- Text: "Tip: {{formatCurrency(FFAppState().cart.tipAmount ?? 0)}}"
- Divider
- Text (Large): "Total: {{formatCurrency(calculateCartTotals()['total'])}}"

### 5.3 Cart Page Layout

1. **App Bar**: "Shopping Cart" + Item count badge
2. **Body**:
   - ListView of cart items (using Cart Item Widget)
   - Cart Summary Widget
   - Discount section (if discounts applied)
   - Tip input field
   - Customer selector (if applicable)
3. **Bottom Bar**:
   - "Clear Cart" button
   - "Checkout" button

---

## Step 6: Implement Product Selection

### 6.1 Product List Page

1. Display products in grid/list
2. On product tap:
   - Show product detail modal/page
   - Allow quantity selection
   - If product has variants, show variant selector
   - "Add to Cart" button → calls `addItemToCart(product, quantity, variant)`

### 6.2 Quick Add to Cart

For simple products:
- Long press or swipe → Quick add with default quantity (1)

---

## Step 7: Implement Discounts

### 7.1 Apply Coupon

1. Add "Apply Coupon" button/text field
2. User enters coupon code
3. API call to validate coupon: `GET /api/coupons/validate?code={{code}}`
4. On success: `addCartDiscount(coupon)`
5. Display applied discount in cart

### 7.2 Manual Discount

1. Long press on cart item → "Apply Discount" option
2. Show discount input dialog
3. Enter discount amount and reason
4. Call `applyItemDiscount(itemId, amount, reason)`

---

## Step 8: Implement Tips

### 8.1 Tip Input

1. Add tip section in cart
2. Options:
   - Quick buttons: 0%, 10%, 15%, 20%
   - Custom amount input
3. On selection: `setCartTip(amount)`

---

## Step 9: Implement Checkout Flow

### 9.1 Checkout Button

1. Validate cart has items
2. Validate POS session is open
3. Show payment method selector
4. If Stripe Terminal:
   - Create Payment Intent
   - Process payment
   - Get payment intent ID
5. Call `checkoutCart(paymentMethod, paymentIntentId)`

### 9.2 Payment Processing

1. **Cash Payment**:
   - Select "Cash"
   - Show cash amount input
   - Calculate change
   - Call `checkoutCart('cash')`

2. **Card Payment (Stripe Terminal)**:
   - Select "Card"
   - Create Payment Intent via API
   - Process payment on terminal
   - Get payment intent ID
   - Call `checkoutCart('card', paymentIntentId)`

3. **Mobile Payment**:
   - Select "Mobile" (Vipps, etc.)
   - Process mobile payment
   - Call `checkoutCart('mobile')`

---

## Step 10: Handle Cart Persistence (Optional)

### 10.1 Save Cart to Local Storage

1. Create action: `saveCartToStorage`
2. Use SharedPreferences or Hive
3. Save cart JSON on every update

### 10.2 Load Cart from Storage

1. On app start, check for saved cart
2. If found, restore cart state
3. Validate cart items are still available

---

## Step 11: Testing Checklist

- [ ] Add item to cart
- [ ] Remove item from cart
- [ ] Update item quantity
- [ ] Apply item discount
- [ ] Apply coupon discount
- [ ] Set tip amount
- [ ] Calculate totals correctly
- [ ] Checkout with cash
- [ ] Checkout with card (Stripe Terminal)
- [ ] Checkout with mobile payment
- [ ] Clear cart
- [ ] Cart persistence (if implemented)
- [ ] Handle empty cart
- [ ] Handle API errors
- [ ] Validate POS session

---

## Step 12: Format Currency Helper

Create a helper action to format amounts:

1. Name: `formatCurrency`
2. **Parameters**:
   - `amount` (Integer) - Amount in øre
3. **Action Code**:
```dart
final amountInNok = amount / 100.0;
return '${amountInNok.toStringAsFixed(2)} kr';
```

---

## Step 13: Generate UUID Helper

Create a helper action to generate UUIDs:

1. Name: `generateUUID`
2. **Action Code**:
```dart
import 'package:uuid/uuid.dart';

final uuid = Uuid();
return uuid.v4();
```

**Note**: You'll need to add the `uuid` package to your `pubspec.yaml`:
```yaml
dependencies:
  uuid: ^4.0.0
```

---

## Common Issues & Solutions

### Issue: Cart not updating
**Solution**: Make sure to use `FFAppState().update()` when modifying cart

### Issue: Totals not calculating correctly
**Solution**: Use the `calculateCartTotals()` action and verify formulas

### Issue: Items duplicating
**Solution**: Check the `addItemToCart` logic for existing item detection

### Issue: API call failing
**Solution**: Verify JSON structure matches backend expectations

---

## Best Practices

1. **Always validate** cart state before checkout
2. **Show loading indicators** during API calls
3. **Handle errors gracefully** with user-friendly messages
4. **Update cart timestamp** on every modification
5. **Use computed properties** for frequently accessed values
6. **Test edge cases**: empty cart, large quantities, etc.

---

## Next Steps

1. Implement receipt display after checkout
2. Add cart history (saved carts)
3. Implement cart sharing between devices (if needed)
4. Add inventory validation before checkout
5. Implement cart analytics

---

## Summary

This guide provides a complete implementation of the POS shopping cart in FlutterFlow. The cart is managed entirely in the frontend, with the backend only receiving the final transaction data on checkout. This approach provides:

- ✅ Fast, responsive UI
- ✅ Offline capability
- ✅ Simple backend integration
- ✅ Complete audit trail (via backend)
- ✅ POS compliance (Kassasystemforskriften)

The cart structure is flexible and can be extended with additional features as needed.

