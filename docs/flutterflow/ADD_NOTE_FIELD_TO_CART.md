# Adding Note Field to ShoppingCartStruct in FlutterFlow UI

## Step-by-Step Instructions

### Step 1: Navigate to Custom Data Types

1. In FlutterFlow, go to the left sidebar
2. Click on **Custom Data Types** (or **Data Types** → **Custom Data Types**)
3. You should see a list of all your custom data types

### Step 2: Open ShoppingCartStruct

1. Find **ShoppingCartStruct** in the list
2. Click on it to open the struct editor

### Step 3: Add the Note Field

1. Look for a **"+ Add Field"** button (usually at the top or bottom of the fields list)
2. Click **"+ Add Field"** (or similar button like **"Add"** or **"New Field"**)
3. A form will appear to configure the new field

### Step 4: Configure the Field

Fill in the field configuration:

| Setting | Value |
|---------|-------|
| **Field Name** | `note` |
| **Type** | **String** (or **Text**) |
| **Nullable** | ✅ **Yes** (check this box) |
| **Default Value** | Leave empty (or `null`) |
| **Required** | ❌ **No** (uncheck if checked) |

**Important Notes:**
- FlutterFlow will automatically prefix the field name with `cart` since it's in `ShoppingCartStruct`, so the actual field name in code will be `cartNote`
- Make sure **Nullable** is set to **Yes** since notes are optional
- The field name should be just `note` (not `cartNote`) - FlutterFlow adds the prefix automatically

### Step 5: Save the Field

1. Click **Save** or **Add** (or similar button)
2. The field should now appear in your fields list
3. FlutterFlow will automatically regenerate the struct code

### Step 6: Verify the Field

After adding the field, you can verify it was added correctly:

1. The field should appear in the fields list as `note` (or `cartNote`)
2. In your code, you should be able to access it as `cart.cartNote`
3. The field will be available in all ShoppingCartStruct instances

---

## Field Configuration Summary

| Property | Value |
|----------|-------|
| **Field Name** | `note` |
| **Display Name** | `Note` (optional, for UI display) |
| **Type** | String |
| **Nullable** | ✅ Yes |
| **Required** | ❌ No |
| **Default Value** | `null` (empty) |
| **Description** | Optional note/comment for the purchase |

---

## Usage After Adding

Once the field is added, you can use it in your FlutterFlow app:

### Setting a Note

```dart
// In a custom action or page action
FFAppState().update(() {
  FFAppState().cart = ShoppingCartStruct(
    // ... other fields ...
    cartNote: 'Customer requested delivery by 3 PM',
  );
});
```

### Reading a Note

```dart
final cart = FFAppState().cart;
final note = cart.cartNote; // Returns String? (nullable)

if (cart.hasCartNote()) {
  // Display the note
  Text(cart.cartNote)
}
```

### Including Note in API Request

When building the cart data for the purchase API, include the note:

```dart
final cartData = {
  'items': cartItems,
  'discounts': cartDiscounts,
  'tip_amount': cart.cartTipAmount,
  'customer_id': cart.cartCustomerId,
  'customer_name': cart.cartCustomerName,
  'note': cart.cartNote, // Add this line
  'subtotal': subtotal,
  'total_discounts': totalDiscounts,
  'total_tax': totalTax,
  'total': total,
  'currency': 'nok',
};
```

---

## Troubleshooting

### Field Not Appearing in Code

- Make sure you saved the field in FlutterFlow
- Try refreshing the FlutterFlow editor
- Check that the field name is exactly `note` (lowercase)

### Field Name is Wrong

- FlutterFlow automatically prefixes with `cart`, so `note` becomes `cartNote`
- If you see `cartCartNote`, you may have accidentally named it `cartNote` instead of `note`
- Delete the field and recreate it with the correct name

### Field is Required When It Shouldn't Be

- Make sure **Nullable** is checked (Yes)
- Make sure **Required** is unchecked (No)
- The field should allow `null` values

---

## Next Steps

After adding the field:

1. ✅ Update any custom actions that create `ShoppingCartStruct` to include `cartNote: null` or the actual note value
2. ✅ Update the purchase API call to include `'note': cart.cartNote ?? null` in the cart data
3. ✅ Add UI elements (text field, text area) to allow users to enter notes
4. ✅ Display the note in cart summary or receipt views

---

## Related Documentation

- See `docs/flutterflow/custom-actions/ShoppingCartStruct_with_note.dart` for the complete struct code reference
- See `docs/flutterflow/ADD_TOTALS_FIELDS_TO_CART.md` for similar field addition instructions
- See `docs/flutterflow/POS_PURCHASE_INTEGRATION.md` for API integration details

