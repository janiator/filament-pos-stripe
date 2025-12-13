# How to Add processOrderRefund Custom Action in FlutterFlow

## Step 1: Add the Custom Action File

1. **Open your FlutterFlow project** in the FlutterFlow editor

2. **Navigate to Custom Code**:
   - Click on **"Custom Code"** in the left sidebar
   - Or go to **Settings** → **Custom Code**

3. **Add Custom Action**:
   - Click the **"+"** button or **"Add Custom Action"**
   - Select **"Custom Action"** (not Custom Widget)

4. **Copy the Code**:
   - Open the file: `docs/flutterflow/custom-actions/process_order_refund.dart`
   - Copy **ALL** the code (from line 1 to the end)
   - Paste it into the FlutterFlow custom action editor

5. **Set the Action Name**:
   - FlutterFlow should auto-detect the function name: `processOrderRefund`
   - If not, make sure the function name is exactly: `processOrderRefund`

6. **Save the Action**:
   - Click **"Save"** or **"Apply"**

## Step 2: Configure Parameters in FlutterFlow

After adding the action, FlutterFlow should auto-detect the parameters. Verify they are set up correctly:

### Required Parameters:
1. **context** (BuildContext) - Auto-provided by FlutterFlow
2. **purchase** (PurchaseStruct) - The complete purchase/order object
3. **apiBaseUrl** (String) - Your API base URL
4. **authToken** (String) - Authentication token

### Optional Parameters:
5. **width** (double, nullable) - Modal width (default: 600.0 if null)
6. **height** (double, nullable) - Modal height (default: 700.0 if null)

**Note**: In FlutterFlow, you can mark `width` and `height` as optional/nullable. If you don't provide them, the defaults (600.0 and 700.0) will be used.

## Step 3: Add Refund Button to Your Orders Page

### Option A: On Order Detail Page

1. **Open your Order Detail Page** in FlutterFlow

2. **Add a Button**:
   - Drag a **Button** widget onto the page
   - Set the text to "Refunder" or "Return"
   - Position it where you want (e.g., in the app bar or at the bottom)

3. **Set Button Action**:
   - Select the button
   - Go to **Actions** tab
   - Click **"Add Action"**
   - Select **"Custom Action"**
   - Choose **"processOrderRefund"**

4. **Configure Parameters**:
   - **context**: Leave as default (auto-provided)
   - **purchase**: Use `purchase` (the entire purchase object from your page state)
   - **apiBaseUrl**: Use your app state variable (e.g., `FFAppState().apiBaseUrl`)
   - **authToken**: Use your app state variable (e.g., `FFAppState().authToken`)
   - **width**: Leave empty/null (optional, defaults to 600.0)
   - **height**: Leave empty/null (optional, defaults to 700.0)

5. **Handle the Result**:
   - After the action, add a conditional check
   - If `result['success'] == true`:
     - Show success message (SnackBar or Alert)
     - Refresh the purchase data
   - If `result['success'] == false`:
     - Show error message

### Option B: On Orders List Page

1. **Open your Orders List Page**

2. **Add Action to List Item**:
   - In your list item widget (e.g., ListTile, Container)
   - Add a button or icon button
   - Set the action to call `processOrderRefund`
   - Pass the purchase data from the list item

## Step 4: Update PurchaseItemStruct (If Needed)

The API now returns additional fields for refund status. You may need to update your `PurchaseItemStruct`:

1. **Go to Data Types** in FlutterFlow
2. **Find PurchaseItemStruct**
3. **Add these fields** (if they don't exist):
   - `purchase_item_quantity_refunded` (int, nullable)
   - `purchase_item_is_refunded` (bool)
   - `purchase_item_is_partially_refunded` (bool)

**Note**: If you're using auto-generated types from your API, you may need to:
- Regenerate your API types
- Or manually add these fields to match the API response

## Step 5: Update UI to Show Refund Status

### Visual Indicators for Refunded Items

1. **In your Order Items List**:

   - Add a conditional widget based on `item.purchaseItemIsRefunded`
   
   **Example Structure**:
   ```
   Container (for each item)
   ├─ If purchaseItemIsRefunded == true
   │  └─ Show item with strikethrough/grayed out
   ├─ Else If purchaseItemIsPartiallyRefunded == true
   │  └─ Show item with badge: "X av Y refundert"
   └─ Else
      └─ Show normal item
   ```

2. **Styling for Refunded Items**:
   - **Fully Refunded**: 
     - Text decoration: Strikethrough
     - Text color: Gray
     - Background: Light gray
   
   - **Partially Refunded**:
     - Badge or text showing: "2 av 3 refundert"
     - Text color: Orange or yellow
     - Optional: Different background color

### Example FlutterFlow Widget Structure:

```
Column (for each purchase item)
├─ Row
│  ├─ Text (product name)
│  │  └─ Style: If refunded, add strikethrough and gray color
│  └─ If partially refunded
│     └─ Text: "${item.purchaseItemQuantityRefunded} av ${item.purchaseItemQuantity} refundert"
└─ Text (price)
```

## Step 6: Test the Integration

1. **Test Full Refund**:
   - Select all items in the modal
   - Process refund
   - Verify items show as fully refunded

2. **Test Partial Refund**:
   - Select some items or partial quantities
   - Process refund
   - Verify items show correct refund status

3. **Test Error Handling**:
   - Try refunding an already fully refunded order
   - Verify error message appears

4. **Test Both Payment Methods**:
   - Test with cash payment
   - Test with Stripe/card payment

## Troubleshooting

### Issue: "Failed to process parameters"
**Solution**: Make sure all required parameters are provided and match the expected types.

### Issue: Modal doesn't appear
**Solution**: 
- Check that `context` is provided correctly
- Verify the modal code is complete (including the `RefundItemSelectionModal` widget)

### Issue: API returns error
**Solution**:
- Verify `apiBaseUrl` and `authToken` are correct
- Check that the purchase ID exists
- Ensure POS session is open (required for refunds)

### Issue: Refund status not showing
**Solution**:
- Verify `PurchaseItemStruct` has the refund status fields
- Refresh purchase data after refund
- Check API response includes the new fields

## Quick Reference: Action Call Example

```
Action: processOrderRefund
Parameters:
  - context: (auto)
  - purchase: purchase (entire purchase object)
  - apiBaseUrl: FFAppState().apiBaseUrl
  - authToken: FFAppState().authToken
  - width: (empty/null, optional)
  - height: (empty/null, optional)

Result Handling:
  - If result['success'] == true: Show success, refresh data
  - If result['success'] == false: Show error message
```

## Additional Notes

- The modal automatically handles item selection and refund calculation
- For Stripe payments, the refund is processed automatically via the API
- For cash payments, it's a record update (manual cash return)
- All refunds are logged in POS events for compliance
- Return receipts are automatically generated

