# Cart Item Management - Custom Actions

## Overview

This guide covers two custom actions for managing cart items:
1. **Remove Item from Cart** - Removes an item by its ID
2. **Update Item Quantity** - Changes the quantity of an item (or removes if quantity is 0)

Both actions automatically recalculate cart totals after making changes.

---

## 1. Remove Item from Cart

### Action Details

- **Name**: `removeItemFromCart`
- **Parameters**:
  - `cartItemId` (String) - Required - The ID of the cart item to remove
- **Return Type**: `void` (no return value)

### Implementation

```dart
Future removeItemFromCart(String cartItemId) async {
  // Finds item by ID and removes it
  // Then calls updateCartTotals() to recalculate
}
```

### Usage in FlutterFlow

1. Go to **Custom Code** → **Actions**
2. Create new action: `removeItemFromCart`
3. Add parameter:
   - Name: `cartItemId`
   - Type: `String`
   - Required: Yes
4. Paste code from `docs/flutterflow/custom-actions/remove_item_from_cart.dart`

### Example Usage

**In a button to remove an item:**

```dart
// When user clicks "Remove" button on a cart item
await removeItemFromCart(cartItem.cartItemId);
```

**In FlutterFlow UI:**
1. Select the "Remove" button
2. Add action: `removeItemFromCart`
3. Set parameter: `cartItemId` = `cartItem.cartItemId` (from your list item)

---

## 2. Update Item Quantity

### Action Details

- **Name**: `updateItemQuantity`
- **Parameters**:
  - `cartItemId` (String) - Required - The ID of the cart item
  - `quantity` (int) - Required - New quantity (if 0 or less, item is removed)
- **Return Type**: `void` (no return value)

### Implementation

```dart
Future updateItemQuantity(String cartItemId, int quantity) async {
  // Updates item quantity
  // If quantity <= 0, removes the item instead
  // Then calls updateCartTotals() to recalculate
}
```

### Usage in FlutterFlow

1. Go to **Custom Code** → **Actions**
2. Create new action: `updateItemQuantity`
3. Add parameters:
   - `cartItemId` (String) - Required
   - `quantity` (int) - Required
4. Paste code from `docs/flutterflow/custom-actions/update_item_quantity.dart`

### Example Usage

**Increase quantity by 1:**

```dart
await updateItemQuantity(
  cartItem.cartItemId,
  cartItem.cartItemQuantity + 1,
);
```

**Decrease quantity by 1:**

```dart
await updateItemQuantity(
  cartItem.cartItemId,
  cartItem.cartItemQuantity - 1,
);
```

**Set specific quantity:**

```dart
await updateItemQuantity(
  cartItem.cartItemId,
  5, // Set to 5
);
```

**Remove item (set quantity to 0):**

```dart
await updateItemQuantity(cartItem.cartItemId, 0);
// This will remove the item from cart
```

### Example UI Patterns

#### Pattern 1: Quantity Stepper

```dart
// Plus button
onPressed: () async {
  await updateItemQuantity(
    cartItem.cartItemId,
    cartItem.cartItemQuantity + 1,
  );
}

// Minus button
onPressed: () async {
  if (cartItem.cartItemQuantity > 1) {
    await updateItemQuantity(
      cartItem.cartItemId,
      cartItem.cartItemQuantity - 1,
    );
  }
}
```

#### Pattern 2: Quantity Input Field

```dart
// When user enters a new quantity
onChanged: (String value) async {
  final newQuantity = int.tryParse(value) ?? 1;
  if (newQuantity > 0) {
    await updateItemQuantity(
      cartItem.cartItemId,
      newQuantity,
    );
  }
}
```

#### Pattern 3: Remove Button

```dart
// Option 1: Use removeItemFromCart
onPressed: () async {
  await removeItemFromCart(cartItem.cartItemId);
}

// Option 2: Use updateItemQuantity with 0
onPressed: () async {
  await updateItemQuantity(cartItem.cartItemId, 0);
}
```

---

## Complete Cart Management Actions

Here's a summary of all cart management actions:

| Action | Purpose | Parameters |
|--------|---------|------------|
| `addItemToCart` | Add item to cart | `product`, `variants?`, `quantity?` |
| `removeItemFromCart` | Remove item from cart | `cartItemId` |
| `updateItemQuantity` | Change item quantity | `cartItemId`, `quantity` |
| `updateCartTotals` | Recalculate totals | (none) |

---

## Important Notes

1. **Automatic Total Recalculation**: Both `removeItemFromCart` and `updateItemQuantity` automatically call `updateCartTotals()` at the end, so you don't need to call it manually.

2. **Quantity Validation**: `updateItemQuantity` will remove the item if quantity is 0 or less.

3. **Item Not Found**: Both actions safely handle cases where the item ID doesn't exist (they just return early).

4. **Cart State Updates**: Both actions update `cartUpdatedAt` timestamp when modifying the cart.

5. **Preserving Totals**: The actions temporarily preserve existing totals, then recalculate them via `updateCartTotals()`.

---

## Testing

### Test Remove Item

1. Add items to cart
2. Call `removeItemFromCart` with a valid `cartItemId`
3. Verify:
   - Item is removed from `cart.cartItems`
   - `cart.cartTotalCartPrice` is updated
   - `cart.cartTotalLinePrice` is reduced
   - `cart.cartTotalTax` is recalculated

### Test Update Quantity

1. Add item with quantity 2
2. Call `updateItemQuantity(cartItemId, 5)`
3. Verify:
   - Item quantity is now 5
   - Totals are recalculated correctly
4. Call `updateItemQuantity(cartItemId, 0)`
5. Verify:
   - Item is removed from cart
   - Totals are updated

---

## Error Handling

Both actions include basic error handling:

- **Item not found**: Returns early without error (graceful failure)
- **Invalid quantity**: `updateItemQuantity` removes item if quantity <= 0
- **Empty cart**: Actions handle empty cart gracefully

For production, you might want to add:
- Toast notifications for user feedback
- Validation messages
- Error logging

---

## Files

- `docs/flutterflow/custom-actions/remove_item_from_cart.dart` - Remove item action
- `docs/flutterflow/custom-actions/update_item_quantity.dart` - Update quantity action
- `docs/flutterflow/custom-actions/update_cart_totals.dart` - Recalculate totals (called automatically)

