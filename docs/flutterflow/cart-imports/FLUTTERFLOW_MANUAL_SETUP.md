# FlutterFlow Manual Setup Guide

Since FlutterFlow doesn't support direct JSON import for custom data types, use this manual setup guide.

## Step-by-Step Manual Creation

### Step 1: Create CartItem Custom Data Type

1. Go to **Data Types** → **Custom Data Types**
2. Click **+ Add Custom Data Type**
3. Name: `CartItem`
4. Add each field one by one:

#### Field 1: id
- **Name**: `id`
- **Type**: `String`
- **Required**: ✅ Yes
- **Default Value**: (leave empty)

#### Field 2: productId
- **Name**: `productId`
- **Type**: `String`
- **Required**: ✅ Yes
- **Default Value**: (leave empty)

#### Field 3: variantId
- **Name**: `variantId`
- **Type**: `String`
- **Required**: ❌ No
- **Default Value**: (leave empty)

#### Field 4: productName
- **Name**: `productName`
- **Type**: `String`
- **Required**: ✅ Yes
- **Default Value**: (leave empty)

#### Field 5: productImageUrl
- **Name**: `productImageUrl`
- **Type**: `String`
- **Required**: ❌ No
- **Default Value**: (leave empty)

#### Field 6: unitPrice
- **Name**: `unitPrice`
- **Type**: `int`
- **Required**: ✅ Yes
- **Default Value**: `0`

#### Field 7: quantity
- **Name**: `quantity`
- **Type**: `int`
- **Required**: ✅ Yes
- **Default Value**: `1`

#### Field 8: originalPrice
- **Name**: `originalPrice`
- **Type**: `int`
- **Required**: ❌ No
- **Default Value**: (leave empty)

#### Field 9: discountAmount
- **Name**: `discountAmount`
- **Type**: `int`
- **Required**: ❌ No
- **Default Value**: (leave empty)

#### Field 10: discountReason
- **Name**: `discountReason`
- **Type**: `String`
- **Required**: ❌ No
- **Default Value**: (leave empty)

#### Field 11: articleGroupCode
- **Name**: `articleGroupCode`
- **Type**: `String`
- **Required**: ❌ No
- **Default Value**: (leave empty)

#### Field 12: productCode
- **Name**: `productCode`
- **Type**: `String`
- **Required**: ❌ No
- **Default Value**: (leave empty)

#### Field 13: metadata
- **Name**: `metadata`
- **Type**: `JSON`
- **Required**: ❌ No
- **Default Value**: (leave empty)

---

### Step 2: Create CartDiscount Custom Data Type

1. Click **+ Add Custom Data Type**
2. Name: `CartDiscount`
3. Add each field:

#### Field 1: id
- **Name**: `id`
- **Type**: `String`
- **Required**: ✅ Yes

#### Field 2: type
- **Name**: `type`
- **Type**: `String`
- **Required**: ✅ Yes

#### Field 3: couponId
- **Name**: `couponId`
- **Type**: `String`
- **Required**: ❌ No

#### Field 4: couponCode
- **Name**: `couponCode`
- **Type**: `String`
- **Required**: ❌ No

#### Field 5: description
- **Name**: `description`
- **Type**: `String`
- **Required**: ✅ Yes

#### Field 6: amount
- **Name**: `amount`
- **Type**: `int`
- **Required**: ✅ Yes
- **Default Value**: `0`

#### Field 7: percentage
- **Name**: `percentage`
- **Type**: `double`
- **Required**: ❌ No

#### Field 8: reason
- **Name**: `reason`
- **Type**: `String`
- **Required**: ❌ No

#### Field 9: requiresApproval
- **Name**: `requiresApproval`
- **Type**: `bool`
- **Required**: ✅ Yes
- **Default Value**: `false`

---

### Step 3: Create ShoppingCart Custom Data Type

**IMPORTANT**: Create this AFTER CartItem and CartDiscount are created.

1. Click **+ Add Custom Data Type**
2. Name: `ShoppingCart`
3. Add each field:

#### Field 1: id
- **Name**: `id`
- **Type**: `String`
- **Required**: ✅ Yes

#### Field 2: posSessionId
- **Name**: `posSessionId`
- **Type**: `String`
- **Required**: ❌ No

#### Field 3: items
- **Name**: `items`
- **Type**: `List<CartItem>` (select CartItem from dropdown)
- **Required**: ✅ Yes
- **Default Value**: `[]` (empty list)

#### Field 4: discounts
- **Name**: `discounts`
- **Type**: `List<CartDiscount>` (select CartDiscount from dropdown)
- **Required**: ✅ Yes
- **Default Value**: `[]` (empty list)

#### Field 5: tipAmount
- **Name**: `tipAmount`
- **Type**: `int`
- **Required**: ❌ No

#### Field 6: customerId
- **Name**: `customerId`
- **Type**: `String`
- **Required**: ❌ No

#### Field 7: customerName
- **Name**: `customerName`
- **Type**: `String`
- **Required**: ❌ No

#### Field 8: createdAt
- **Name**: `createdAt`
- **Type**: `DateTime`
- **Required**: ✅ Yes

#### Field 9: updatedAt
- **Name**: `updatedAt`
- **Type**: `DateTime`
- **Required**: ✅ Yes

#### Field 10: metadata
- **Name**: `metadata`
- **Type**: `JSON`
- **Required**: ❌ No

---

## Quick Reference Table

### CartItem (13 fields)

| # | Field Name | Type | Required | Default |
|---|-----------|------|----------|---------|
| 1 | id | String | ✅ | - |
| 2 | productId | String | ✅ | - |
| 3 | variantId | String | ❌ | - |
| 4 | productName | String | ✅ | - |
| 5 | productImageUrl | String | ❌ | - |
| 6 | unitPrice | int | ✅ | 0 |
| 7 | quantity | int | ✅ | 1 |
| 8 | originalPrice | int | ❌ | - |
| 9 | discountAmount | int | ❌ | - |
| 10 | discountReason | String | ❌ | - |
| 11 | articleGroupCode | String | ❌ | - |
| 12 | productCode | String | ❌ | - |
| 13 | metadata | JSON | ❌ | - |

### CartDiscount (9 fields)

| # | Field Name | Type | Required | Default |
|---|-----------|------|----------|---------|
| 1 | id | String | ✅ | - |
| 2 | type | String | ✅ | - |
| 3 | couponId | String | ❌ | - |
| 4 | couponCode | String | ❌ | - |
| 5 | description | String | ✅ | - |
| 6 | amount | int | ✅ | 0 |
| 7 | percentage | double | ❌ | - |
| 8 | reason | String | ❌ | - |
| 9 | requiresApproval | bool | ✅ | false |

### ShoppingCart (10 fields)

| # | Field Name | Type | Required | Default |
|---|-----------|------|----------|---------|
| 1 | id | String | ✅ | - |
| 2 | posSessionId | String | ❌ | - |
| 3 | items | List<CartItem> | ✅ | [] |
| 4 | discounts | List<CartDiscount> | ✅ | [] |
| 5 | tipAmount | int | ❌ | - |
| 6 | customerId | String | ❌ | - |
| 7 | customerName | String | ❌ | - |
| 8 | createdAt | DateTime | ✅ | - |
| 9 | updatedAt | DateTime | ✅ | - |
| 10 | metadata | JSON | ❌ | - |

---

## Tips for FlutterFlow

1. **List Types**: When creating `items` and `discounts` fields:
   - Select type as `List`
   - Then select the item type (`CartItem` or `CartDiscount`) from the dropdown

2. **Default Values**:
   - For lists: Use `[]` (empty array)
   - For integers: Use `0` or `1` as appropriate
   - For booleans: Use `false`
   - For nullable fields: Leave default empty

3. **Required Fields**:
   - Make sure to mark required fields correctly
   - Required fields must have values when creating instances

4. **Order Matters**:
   - Create `CartItem` first
   - Create `CartDiscount` second
   - Create `ShoppingCart` last (it depends on the first two)

---

## Verification Checklist

After creating all three types, verify:

- [ ] CartItem has 13 fields
- [ ] CartDiscount has 9 fields
- [ ] ShoppingCart has 10 fields
- [ ] ShoppingCart.items is type `List<CartItem>`
- [ ] ShoppingCart.discounts is type `List<CartDiscount>`
- [ ] All required fields are marked correctly
- [ ] Default values are set where specified

---

## Next Steps

After creating the data types:

1. ✅ Create App State Variable (see below)
2. ✅ Follow the implementation guide for actions
3. ✅ Build UI components

---

## Create App State Variable

1. Go to **App State** → **App State Variables**
2. Click **+ Add App State Variable**
3. Configure:
   - **Name**: `cart`
   - **Type**: `ShoppingCart` (select from dropdown)
   - **Initial Value**: Click **Create New**
     - Set `id`: (you'll set this in an action later)
     - Set `items`: `[]` (empty list)
     - Set `discounts`: `[]` (empty list)
     - Set `createdAt`: Current date/time
     - Set `updatedAt`: Current date/time
     - Leave other fields as null/empty

---

## Alternative: Copy-Paste Helper

If FlutterFlow supports copying fields, you can use this format for quick reference:

### CartItem Fields (Copy this list)
```
id (String, required)
productId (String, required)
variantId (String, optional)
productName (String, required)
productImageUrl (String, optional)
unitPrice (int, required, default: 0)
quantity (int, required, default: 1)
originalPrice (int, optional)
discountAmount (int, optional)
discountReason (String, optional)
articleGroupCode (String, optional)
productCode (String, optional)
metadata (JSON, optional)
```

### CartDiscount Fields
```
id (String, required)
type (String, required)
couponId (String, optional)
couponCode (String, optional)
description (String, required)
amount (int, required, default: 0)
percentage (double, optional)
reason (String, optional)
requiresApproval (bool, required, default: false)
```

### ShoppingCart Fields
```
id (String, required)
posSessionId (String, optional)
items (List<CartItem>, required, default: [])
discounts (List<CartDiscount>, required, default: [])
tipAmount (int, optional)
customerId (String, optional)
customerName (String, optional)
createdAt (DateTime, required)
updatedAt (DateTime, required)
metadata (JSON, optional)
```

---

This manual approach will work in FlutterFlow. The key is creating the types in the correct order and ensuring list types reference the correct custom data types.

