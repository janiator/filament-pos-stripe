# FlutterFlow Import Instructions

## How to Import Custom Data Types into FlutterFlow

### Step 1: Import CartItem

1. Open your FlutterFlow project
2. Go to **Data Types** → **Custom Data Types**
3. Click **+ Add Custom Data Type**
4. Click **Import from JSON** (or use the import option)
5. Copy and paste the contents of `CartItem.json`
6. Click **Import** or **Save**

### Step 2: Import CartDiscount

1. Click **+ Add Custom Data Type**
2. Click **Import from JSON**
3. Copy and paste the contents of `CartDiscount.json`
4. Click **Import** or **Save**

### Step 3: Import ShoppingCart

**Important**: Import this AFTER CartItem and CartDiscount, as it references them.

1. Click **+ Add Custom Data Type**
2. Click **Import from JSON**
3. Copy and paste the contents of `ShoppingCart.json`
4. Click **Import** or **Save**

### Step 4: Verify Import

After importing, verify that:
- ✅ All three data types appear in your Custom Data Types list
- ✅ ShoppingCart has `items` as `List<CartItem>`
- ✅ ShoppingCart has `discounts` as `List<CartDiscount>`
- ✅ All required fields are marked correctly

### Step 5: Create App State Variable

1. Go to **App State** → **App State Variables**
2. Click **+ Add App State Variable**
3. Configure:
   - **Name**: `cart`
   - **Type**: `ShoppingCart`
   - **Initial Value**: 
     - Click **Create New**
     - Set `id`: Use `generateUUID()` action (you'll need to create this)
     - Set `items`: Empty list `[]`
     - Set `discounts`: Empty list `[]`
     - Set `createdAt`: Current date/time
     - Set `updatedAt`: Current date/time
     - Leave other fields as null/empty

---

## Alternative: Manual Creation

If the JSON import doesn't work, you can manually create the data types using the field definitions below:

### CartItem Fields

| Field Name | Type | Required | Default |
|-----------|------|----------|---------|
| id | String | ✅ | - |
| productId | String | ✅ | - |
| variantId | String | ❌ | - |
| productName | String | ✅ | - |
| productImageUrl | String | ❌ | - |
| unitPrice | Integer | ✅ | 0 |
| quantity | Integer | ✅ | 1 |
| originalPrice | Integer | ❌ | - |
| discountAmount | Integer | ❌ | - |
| discountReason | String | ❌ | - |
| articleGroupCode | String | ❌ | - |
| productCode | String | ❌ | - |
| metadata | JSON | ❌ | - |

### CartDiscount Fields

| Field Name | Type | Required | Default |
|-----------|------|----------|---------|
| id | String | ✅ | - |
| type | String | ✅ | - |
| couponId | String | ❌ | - |
| couponCode | String | ❌ | - |
| description | String | ✅ | - |
| amount | Integer | ✅ | 0 |
| percentage | Double | ❌ | - |
| reason | String | ❌ | - |
| requiresApproval | Boolean | ✅ | false |

### ShoppingCart Fields

| Field Name | Type | Required | Default |
|-----------|------|----------|---------|
| id | String | ✅ | - |
| posSessionId | String | ❌ | - |
| items | List<CartItem> | ✅ | [] |
| discounts | List<CartDiscount> | ✅ | [] |
| tipAmount | Integer | ❌ | - |
| customerId | String | ❌ | - |
| customerName | String | ❌ | - |
| createdAt | DateTime | ✅ | - |
| updatedAt | DateTime | ✅ | - |
| metadata | JSON | ❌ | - |

---

## Troubleshooting

### Issue: "List<CartItem> not recognized"
**Solution**: Make sure CartItem is imported before ShoppingCart

### Issue: "Type not found"
**Solution**: Check that all custom data types are imported correctly

### Issue: "Default value error"
**Solution**: For list types, use empty array `[]` as default. For nullable fields, leave default as null.

### Issue: "JSON import not available"
**Solution**: Use the manual creation method with the field tables above

---

## Next Steps

After importing the data types:

1. ✅ Create the App State Variable (Step 5 above)
2. ✅ Follow the implementation guide: `FLUTTERFLOW_IMPLEMENTATION_GUIDE.md`
3. ✅ Create the custom actions for cart operations
4. ✅ Build the UI components

---

## Notes

- **Integer types**: FlutterFlow uses `int` for integers
- **List types**: Use `List<TypeName>` format
- **JSON type**: Use for flexible metadata storage
- **DateTime**: Use FlutterFlow's DateTime type
- **Required fields**: Must have values when creating instances
- **Default values**: Helpful for lists (use `[]`) and booleans (use `false`)

