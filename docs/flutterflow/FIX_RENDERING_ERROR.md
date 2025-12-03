# Fixing Flutter Rendering Error: semantics.parentDataDirty

## Problem

The error `'!semantics.parentDataDirty': is not true` occurs when Flutter tries to rebuild widgets while another rebuild is in progress. This commonly happens when:

1. Multiple state updates happen in quick succession
2. `FFAppState().update()` is called multiple times rapidly
3. Cart operations trigger multiple `updateCartTotals()` calls
4. Widget tree is being rebuilt while state is being updated

## Solution

### Option 1: Add Debouncing to `updateCartTotals`

Prevent multiple rapid calls by adding a debounce mechanism:

```dart
// Add at the top of the file (outside the function)
DateTime? _lastUpdateTime;
const _updateDebounceMs = 100; // 100ms debounce

Future updateCartTotals() async {
  final now = DateTime.now();
  
  // Debounce: only update if enough time has passed
  if (_lastUpdateTime != null && 
      now.difference(_lastUpdateTime!).inMilliseconds < _updateDebounceMs) {
    return; // Skip this update
  }
  
  _lastUpdateTime = now;
  
  // ... rest of the function
}
```

### Option 2: Use a Flag to Prevent Concurrent Updates

```dart
// Add at the top of the file
bool _isUpdating = false;

Future updateCartTotals() async {
  // Prevent concurrent updates
  if (_isUpdating) {
    return;
  }
  
  _isUpdating = true;
  
  try {
    final cart = FFAppState().cart;
    // ... calculation code ...
    
    // Update cart
    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        // ... cart fields ...
      );
    });
  } finally {
    _isUpdating = false;
  }
}
```

### Option 3: Batch State Updates (Recommended)

Instead of calling `updateCartTotals()` after every operation, batch the updates:

```dart
// In your cart actions, don't call updateCartTotals() immediately
// Instead, mark that totals need updating and update once at the end

Future addItemToCart(...) async {
  // ... add item logic ...
  
  // Don't call updateCartTotals() here
  // Instead, just update the cart items
  FFAppState().update(() {
    FFAppState().cart = ShoppingCartStruct(
      // ... update cart ...
    );
  });
  
  // Schedule totals update for next frame
  WidgetsBinding.instance.addPostFrameCallback((_) {
    updateCartTotals();
  });
}
```

### Option 4: Remove Redundant `updateCartTotals()` Calls

If you're calling `updateCartTotals()` multiple times in quick succession, remove redundant calls:

**Before:**
```dart
await addItemToCart(...);
await updateCartTotals(); // Called in addItemToCart
await updateItemQuantity(...);
await updateCartTotals(); // Called in updateItemQuantity
```

**After:**
```dart
await addItemToCart(...); // updateCartTotals() called inside
await updateItemQuantity(...); // updateCartTotals() called inside
// No need to call again
```

## Recommended Fix

The best approach is **Option 2** (prevent concurrent updates) combined with ensuring we're not calling `updateCartTotals()` too frequently.

### Updated `updateCartTotals` with Concurrency Protection

```dart
// Add at the top of the file, outside the function
bool _isUpdatingTotals = false;

Future updateCartTotals() async {
  // Prevent concurrent updates
  if (_isUpdatingTotals) {
    return;
  }
  
  _isUpdatingTotals = true;
  
  try {
    final cart = FFAppState().cart;
    
    // ... rest of calculation code ...
    
    // Use a single update call
    FFAppState().update(() {
      FFAppState().cart = ShoppingCartStruct(
        // ... all fields ...
      );
    });
  } finally {
    _isUpdatingTotals = false;
  }
}
```

## Additional Checks

1. **Ensure single `FFAppState().update()` call**: Don't nest multiple `update()` calls
2. **Await async operations**: Make sure all async operations are properly awaited
3. **Check for rapid button clicks**: Add debouncing to UI buttons that trigger cart updates
4. **Review widget rebuilds**: Check if widgets are rebuilding unnecessarily

## Testing

After applying the fix:

1. Test rapid cart operations (add/remove items quickly)
2. Test multiple discount applications
3. Test quantity updates
4. Monitor for the error - it should no longer appear

