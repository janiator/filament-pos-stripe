# Troubleshooting: Purchase Item Description is Null

## Issue
The `purchase_item_description` field exists in the API response but the value is `null`.

## Root Cause Analysis

The description field flows through the system as follows:

1. **FlutterFlow Cart** → `cart.items[].description`
2. **API Request** → `cart.items[].description` in purchase creation
3. **PurchaseService** → Stores in metadata as `items[].description`
4. **API Response** → Returns as `purchase_item_description`

## Debugging Steps

### 1. Verify Description is in Cart Items

In FlutterFlow, when creating the purchase request, check that the description is included:

```dart
final cartItems = FFAppState().cart.cartItems.map((item) {
  return {
    'product_id': int.parse(item.cartItemProductId),
    'variant_id': item.cartItemVariantId.isNotEmpty 
        ? int.parse(item.cartItemVariantId) 
        : null,
    'quantity': item.cartItemQuantity,
    'unit_price': item.cartItemUnitPrice,
    'description': item.cartItemDescription, // ← Check this is not null
  };
}).toList();
```

**Check:**
- Is `cartItemDescription` field added to `CartItemsStruct`?
- Is description being set when adding items to cart?
- Is description included in the purchase request payload?

### 2. Verify API Request

Check the actual API request being sent. The request should look like:

```json
{
  "cart": {
    "items": [
      {
        "product_id": 123,
        "quantity": 1,
        "unit_price": 5000,
        "description": "Various items - customer selection"  // ← Should be here
      }
    ],
    "total": 5000
  }
}
```

**Debug in FlutterFlow:**
- Add logging before API call:
  ```dart
  print('Cart items: ${jsonEncode(cartItems)}');
  ```
- Check network tab in browser/dev tools to see actual request

### 3. Verify Backend Storage

The description should be stored in purchase metadata. Check the database:

```sql
SELECT 
  id,
  metadata->'items'->0->>'description' as item_description,
  metadata->'items'->0->>'product_name' as product_name
FROM connected_charges
WHERE id = <purchase_id>
ORDER BY created_at DESC
LIMIT 1;
```

**Expected:**
- `item_description` should contain the description if provided
- If null, it means description wasn't sent from FlutterFlow

### 4. Verify API Response

Check the purchase response:

```bash
curl -X GET "https://your-api.com/api/purchases/{id}" \
  -H "Authorization: Bearer {token}"
```

Look for:
```json
{
  "purchase_items": [
    {
      "purchase_item_description": "Various items - customer selection",  // ← Should be here
      "purchase_item_product_name": "Diverse Product"
    }
  ]
}
```

## Common Issues

### Issue 1: Description Field Not Added to CartItemsStruct

**Symptom:** `cartItemDescription` field doesn't exist in FlutterFlow

**Solution:**
1. Go to FlutterFlow → Data Types → CartItemsStruct
2. Add field:
   - Name: `cartItemDescription`
   - Type: `String`
   - Required: `false`
   - Default: `null`

### Issue 2: Description Not Included in Purchase Request

**Symptom:** Description exists in cart but not in API request

**Solution:**
Check your purchase creation code. Ensure description is included:

```dart
// ❌ Wrong - missing description
final cartItems = cart.cartItems.map((item) => {
  'product_id': int.parse(item.cartItemProductId),
  'quantity': item.cartItemQuantity,
  'unit_price': item.cartItemUnitPrice,
  // Missing description!
}).toList();

// ✅ Correct - includes description
final cartItems = cart.cartItems.map((item) => {
  'product_id': int.parse(item.cartItemProductId),
  'quantity': item.cartItemQuantity,
  'unit_price': item.cartItemUnitPrice,
  'description': item.cartItemDescription, // ← Include this
}).toList();
```

### Issue 3: Using Metadata Fallback

**Symptom:** Using metadata approach but description not extracted

**Solution:**
If using metadata fallback (not recommended), extract description:

```dart
final metadata = item.cartItemMetadata as Map<String, dynamic>?;
'description': metadata?['description'], // Extract from metadata
```

### Issue 4: Description is Empty String

**Symptom:** Description is sent as empty string `""` instead of `null`

**Solution:**
Backend handles this (converts empty string to null), but ensure FlutterFlow sends `null`:

```dart
'description': item.cartItemDescription?.isEmpty == true 
    ? null 
    : item.cartItemDescription,
```

## Verification Checklist

- [ ] `cartItemDescription` field exists in `CartItemsStruct`
- [ ] Description is set when adding items with custom price
- [ ] Description is included in cart items when building purchase request
- [ ] API request includes `cart.items[].description` field
- [ ] Backend stores description in purchase metadata
- [ ] API response includes `purchase_item_description` field

## Testing

### Test Case 1: Purchase with Description

1. Add item to cart with description:
   ```dart
   await addItemToCart(
     product: diverseProduct,
     variants: null,
     quantity: 1,
     customPrice: 5000,
     description: "Various items - customer selection",
   );
   ```

2. Create purchase and verify:
   - Check purchase metadata in database
   - Check API response
   - Check receipt shows description

### Test Case 2: Purchase without Description

1. Add item to cart without description:
   ```dart
   await addItemToCart(
     product: regularProduct,
     variants: variant,
     quantity: 1,
     customPrice: null,
     description: null, // No description
   );
   ```

2. Verify:
   - `purchase_item_description` is `null` (expected)
   - `purchase_item_product_name` shows product name
   - Receipt shows product name

## Still Having Issues?

1. **Check Logs:**
   - Laravel logs: `storage/logs/laravel.log`
   - Look for purchase creation entries
   - Check if description is in request payload

2. **Add Debugging:**
   ```php
   // In PurchaseService::enrichCartItemsWithProductSnapshots
   \Log::debug('Enriching item', [
       'item' => $item,
       'description' => $item['description'] ?? 'NOT SET',
   ]);
   ```

3. **Verify Database:**
   ```sql
   -- Check recent purchases
   SELECT 
     id,
     created_at,
     metadata->'items' as items
   FROM connected_charges
   WHERE pos_session_id IS NOT NULL
   ORDER BY created_at DESC
   LIMIT 5;
   ```

## Expected Behavior

- **With Description:** `purchase_item_description` contains the custom description
- **Without Description:** `purchase_item_description` is `null`, `purchase_item_product_name` is used
- **Receipt:** Shows description if provided, otherwise product name



