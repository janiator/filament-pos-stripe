# How to Use processOrderRefund - Parameter Guide

## Quick Answer

**Most common usage** (auto-detects from app state):
```dart
await processOrderRefund(
  context,
  purchase,
  apiBaseUrl,
  authToken,
  null,  // posDeviceId - will auto-detect if original session is closed
  null,  // posSessionId - will auto-detect if original session is closed
);
```

**Explicit usage** (recommended for clarity):
```dart
await processOrderRefund(
  context,
  purchase,
  apiBaseUrl,
  authToken,
  FFAppState().activePosDevice.id,  // Current POS device ID
  null,                              // posSessionId (optional)
);
```

## When Do You Need to Provide These Values?

### ✅ **You DON'T need to provide them if:**
- The original purchase was made in the **current open session** (same session you're in now)
- The action will automatically use the original session

### ⚠️ **You MUST provide them if:**
- The original purchase was made in a **closed session** (e.g., yesterday's order)
- You want to ensure the refund is tracked in a specific current session
- The app state doesn't have `activePosDevice` or `currentPosSession` set

## How to Get the Values

### Option 1: From FlutterFlow App State (Recommended)

The action automatically tries to get these from app state, but you can also pass them explicitly:

```dart
// Get POS Device ID (preferred - auto-detects current session)
final posDeviceId = FFAppState().activePosDevice.id;

// OR get POS Session ID directly (alternative)
final posSessionId = FFAppState().currentPosSession.id;
```

### Option 2: From Your Current Cart/Session

If you're tracking the current session in your cart:

```dart
final currentCart = FFAppState().currentCart;
final posSessionId = currentCart.posSessionId;  // If stored as String, convert to int
```

### Option 3: From API Response

If you just opened/retrieved a session:

```dart
// After opening a session via API
final sessionResponse = await getCurrentPosSession(deviceId);
final posSessionId = sessionResponse['id'] as int;
```

## Complete Examples

### Example 1: Refund from Current Session (Simplest)
```dart
// Original purchase is from current session - no need to specify
final result = await processOrderRefund(
  context,
  purchase,
  apiBaseUrl,
  authToken,
  null,  // posDeviceId - not needed
  null,  // posSessionId - not needed
);
```

### Example 2: Refund from Closed Session (Auto-detect)
```dart
// Original session is closed - action will auto-detect current session
final result = await processOrderRefund(
  context,
  purchase,
  apiBaseUrl,
  authToken,
  null,  // Will try FFAppState().activePosDevice.id
  null,  // Will try FFAppState().currentPosSession.id if device not available
);
```

### Example 3: Refund from Closed Session (Explicit)
```dart
// Explicitly provide current device/session
final result = await processOrderRefund(
  context,
  purchase,
  apiBaseUrl,
  authToken,
  FFAppState().activePosDevice.id,  // Explicit current device
  null,                              // Session will be auto-detected from device
);
```

### Example 4: Using Specific Session ID
```dart
// If you have a specific session ID you want to use
final result = await processOrderRefund(
  context,
  purchase,
  apiBaseUrl,
  authToken,
  null,                              // No device ID
  FFAppState().currentPosSession.id, // Explicit session ID
);
```

## Priority Order

The action uses this priority:

1. **Parameters you pass** (`posDeviceId` or `posSessionId`)
2. **App State - Device** (`FFAppState().activePosDevice.id`)
3. **App State - Session** (`FFAppState().currentPosSession.id`)
4. **Error** - If original session is closed and none of the above are available

## What Happens If You Don't Provide Them?

### Scenario A: Original Session is Open
✅ **Works fine** - Uses original session (no compliance issue)

### Scenario B: Original Session is Closed
❌ **Will fail** with error:
```
"Original POS session is closed. Please provide posDeviceId or posSessionId to use the current open session for the refund."
```

**Solution:** Provide one of:
- `posDeviceId: FFAppState().activePosDevice.id`
- `posSessionId: FFAppState().currentPosSession.id`

## Best Practice

**Recommended approach:**
```dart
// Always pass current device ID for clarity and compliance
final result = await processOrderRefund(
  context,
  purchase,
  apiBaseUrl,
  authToken,
  FFAppState().activePosDevice.id,  // Explicit - ensures compliance
  null,
);
```

**Why?**
- ✅ Clear intent - you're explicitly using current session
- ✅ Compliance - ensures refund goes to current session if original is closed
- ✅ No surprises - won't fail if app state structure changes
- ✅ Works for both open and closed original sessions

## Troubleshooting

### Error: "Original POS session is closed..."
**Cause:** Original session is closed and no current session info provided

**Fix:**
```dart
// Add posDeviceId parameter
posDeviceId: FFAppState().activePosDevice.id,
```

### Error: "No open POS session found..."
**Cause:** The device ID you provided doesn't have an open session

**Fix:**
1. Check if a session is open: `FFAppState().currentPosSession.id`
2. Or open a new session first
3. Or use `posSessionId` instead of `posDeviceId`

### App State Not Available
**Cause:** `FFAppState().activePosDevice` or `FFAppState().currentPosSession` not set

**Fix:**
1. Ensure POS device is registered/logged in
2. Ensure a session is opened
3. Or pass values explicitly from your own state management

## Summary

| Situation | posDeviceId | posSessionId | Result |
|-----------|-------------|-------------|---------|
| Original session open | `null` | `null` | ✅ Uses original session |
| Original session closed | `null` | `null` | ❌ Error (needs current session) |
| Original session closed | `FFAppState().activePosDevice.id` | `null` | ✅ Uses current device's session |
| Original session closed | `null` | `FFAppState().currentPosSession.id` | ✅ Uses specified session |
| Any situation | Explicit value | `null` | ✅ Uses your specified device/session |

**Recommendation:** Always pass `posDeviceId: FFAppState().activePosDevice.id` for clarity and compliance.
