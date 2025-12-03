# How to Use addItemToCart Action in FlutterFlow

## Function Signature

```dart
Future addItemToCart(
  ProductStruct? product,
  VariantsStruct? variants,
  int? quantity,
) async
```

## How to Call the Action in FlutterFlow UI

### Step 1: Add Action to Button/Widget

1. Select the button or widget where you want to add the item to cart
2. Go to **Actions** tab
3. Click **+ Add Action**
4. Select **Custom Action**
5. Choose `addItemToCart` from the list

### Step 2: Configure Parameters

FlutterFlow will show you three parameter fields:

#### Parameter 1: `product` (ProductStruct?)
- **Type**: Product (your ProductStruct data type)
- **Required**: Yes (but nullable)
- **How to set**:
  - If you have a product variable: Select the variable
  - If product is in a list item: Use `listItem.product` or `productItem`
  - Example: `productGridItem` or `selectedProduct`

#### Parameter 2: `variants` (VariantsStruct?)
- **Type**: Variants (your VariantsStruct data type)
- **Required**: No (optional)
- **How to set**:
  - **If product has variants and user selected one**:
    - Use the selected variant variable: `selectedVariant`
    - Or from product variants list: `product.variants[index]`
    - Or from a variant picker: `variantPickerValue`
  - **If product has no variants or user didn't select**:
    - Leave it empty/null
    - Or set to `null`

#### Parameter 3: `quantity` (int?)
- **Type**: Integer
- **Required**: No (optional, defaults to 1)
- **How to set**:
  - **Fixed quantity**: Enter a number like `1`, `2`, etc.
  - **From variable**: Use a quantity variable like `selectedQuantity`
  - **From input field**: Use `quantityTextFieldValue` or similar
  - **Leave empty**: Will default to 1

## Example Use Cases

### Example 1: Simple Product (No Variants)

**Scenario**: User clicks "Add to Cart" button for a product without variants

**Action Configuration**:
```
addItemToCart(
  product: productGridItem,  // The product from grid
  variants: null,            // No variant
  quantity: 1                // Default quantity
)
```

**In FlutterFlow UI**:
- `product`: Select your product variable (e.g., `productGridItem`)
- `variants`: Leave empty or set to `null`
- `quantity`: Enter `1` or leave empty

### Example 2: Product with Selected Variant

**Scenario**: User selects a variant (e.g., "Size: Large") and clicks "Add to Cart"

**Action Configuration**:
```
addItemToCart(
  product: selectedProduct,      // The product
  variants: selectedVariant,     // The selected variant
  quantity: quantityPickerValue  // From quantity picker
)
```

**In FlutterFlow UI**:
- `product`: Select `selectedProduct` variable
- `variants`: Select `selectedVariant` variable (from variant picker/selector)
- `quantity`: Select `quantityPickerValue` or enter a number

### Example 3: From Product List with Variant Selection

**Scenario**: User is viewing a product detail page with variant options

**Action Configuration**:
```
addItemToCart(
  product: productDetailPageProduct,
  variants: variantSelectorValue,  // From dropdown/picker
  quantity: quantityInputValue     // From text field
)
```

**In FlutterFlow UI**:
- `product`: Select the product from your page state
- `variants`: Select the value from your variant selector widget
- `quantity`: Select the value from your quantity input field

### Example 4: Quick Add (No Variant Selection UI)

**Scenario**: Quick add button that uses default variant or first variant

**Action Configuration**:
```
addItemToCart(
  product: productItem,
  variants: productItem.variants.isNotEmpty 
    ? productItem.variants[0]  // First variant if available
    : null,                     // No variant if empty
  quantity: 1
)
```

**In FlutterFlow UI**:
- `product`: Select product variable
- `variants`: Use a conditional expression:
  - If `productItem.variants.length > 0`: `productItem.variants[0]`
  - Else: `null`
- `quantity`: Enter `1`

## Common Patterns

### Pattern 1: From Product Grid Item

When clicking a product in a grid:

```dart
// In FlutterFlow, configure:
product: gridItemProduct
variants: null  // or gridItemSelectedVariant if you have variant selection
quantity: 1
```

### Pattern 2: From Product Detail Page

When user selects variant and quantity, then clicks "Add to Cart":

```dart
// In FlutterFlow, configure:
product: productDetailPageProduct
variants: variantDropdownValue  // From your variant dropdown
quantity: quantityTextFieldValue  // From your quantity input
```

### Pattern 3: Bulk Add

When adding multiple items at once:

```dart
// In FlutterFlow, configure:
product: selectedProduct
variants: selectedVariant
quantity: bulkQuantityPickerValue  // User-selected quantity
```

## Important Notes

1. **Variant Parameter is Optional**: 
   - If your product doesn't have variants, always pass `null`
   - If variants exist but user didn't select, you can pass `null` or the first variant

2. **Quantity Defaults to 1**:
   - If you don't specify quantity, it defaults to 1
   - You can pass `null` and it will use 1

3. **Product is Required**:
   - The product parameter should always have a value
   - The function will return early if product is null

4. **Variant Matching**:
   - The current implementation checks for existing items by product ID
   - If you need variant-specific cart items, you'll need to update the matching logic in the action

## Troubleshooting

### Variant Not Being Passed

**Problem**: Variant parameter is always null

**Solution**: 
- Make sure your variant selector widget is properly bound to a variable
- Check that the variant variable is set before calling the action
- Verify the variant selector's `onChanged` callback updates the variable

### Quantity Not Working

**Problem**: Quantity always adds 1

**Solution**:
- Make sure your quantity input/selector is bound to a variable
- Pass that variable to the `quantity` parameter
- Check that the variable is a number (int), not a string

### Product Not Found

**Problem**: Action doesn't work, product is null

**Solution**:
- Verify the product variable is set before calling the action
- Check that you're selecting the correct variable in FlutterFlow
- Make sure the product data structure matches `ProductStruct`

