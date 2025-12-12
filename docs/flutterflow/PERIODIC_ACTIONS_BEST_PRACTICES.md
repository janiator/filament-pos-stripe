# Periodic Actions Best Practices

## Problem

When starting periodic actions (like heartbeat updates) on a loading screen or any page widget, the actions continue running even after the widget is disposed. This causes the error:

> "Looking up a deactivated widget's ancestor is unsafe"

This happens because:
1. Periodic actions are started in `initState()` or `onPageLoad`
2. User navigates away, disposing the widget
3. Periodic action fires and tries to update app state
4. Flutter tries to rebuild widgets using a disposed context

## Solution: Use a Singleton Service

Instead of starting periodic actions on a page, use a **singleton service** that:
- Doesn't depend on widget context
- Can be started once and runs independently
- Can be safely called from anywhere
- Automatically handles cleanup

## Implementation

### Step 1: Add the Service

1. In FlutterFlow, go to **Custom Code** → **Custom Actions**
2. Create a new custom action named `startPeriodicHeartbeat`
3. Copy the code from `periodic_heartbeat_service.dart`
4. Also create `stopPeriodicHeartbeat` and `isHeartbeatRunning` actions

### Step 2: Start the Service (Recommended Locations)

#### Option A: After Successful Login (Recommended)

1. **After Login Success Action:**
   - Add a **Custom Action** step
   - Select `startPeriodicHeartbeat`
   - Set parameters:
     - `apiBaseUrl`: Your API base URL
     - `authToken`: Token from login response
     - `deviceId`: Device ID from `registerPosDevice` response
     - `intervalMinutes`: 5 (or your preferred interval)
     - `deviceStatus`: "active" (optional)
     - `deviceMetadataJson`: Optional metadata

2. **Check if Already Running (Optional):**
   - Before starting, call `isHeartbeatRunning` to check
   - Only start if not already running

#### Option B: On App Initialization (If Available)

If FlutterFlow supports app-level initialization:
- Start the service there instead of on a page

#### Option C: On Main/Home Page (Once)

1. Add a **Conditional Action** on your main/home page
2. Check if heartbeat is running: `isHeartbeatRunning()`
3. If not running, start it: `startPeriodicHeartbeat()`
4. This ensures it only starts once

### Step 3: Stop the Service (When Needed)

#### On Logout:
1. Add `stopPeriodicHeartbeat()` action before logout
2. This ensures clean shutdown

#### On App Close (If Available):
- Call `stopPeriodicHeartbeat()` in app lifecycle callback

### Step 4: Update Token When It Changes

If the auth token changes (e.g., token refresh):

1. Stop the existing heartbeat: `stopPeriodicHeartbeat()`
2. Start with new token: `startPeriodicHeartbeat()` with new token

Or modify the service to accept token updates (see Advanced section).

## Example Flow

```
App Start
  ↓
Login
  ↓
Register POS Device (registerPosDevice)
  ↓
Store deviceId in app state
  ↓
Start Periodic Heartbeat (startPeriodicHeartbeat) ← ONCE HERE
  ↓
[Service runs independently every 5 minutes]
  ↓
User navigates anywhere in app
  ↓
[Service continues running - no context needed]
  ↓
Logout
  ↓
Stop Periodic Heartbeat (stopPeriodicHeartbeat)
```

## Key Benefits

1. **No Context Dependency**: Service doesn't use `BuildContext`, so it's safe even after widgets are disposed
2. **Single Instance**: Singleton pattern ensures only one timer runs
3. **Automatic Cleanup**: Can be stopped when needed
4. **Resilient**: Errors in heartbeat updates don't crash the app
5. **Flexible**: Can be started/stopped from anywhere

## Advanced: Token Refresh Support

If you need to update the token without stopping/starting:

```dart
// In the service, add a method to update token
void updateToken(String newAuthToken) {
  // Store new token and use it in next heartbeat
  _currentAuthToken = newAuthToken;
}
```

Then call this when token refreshes instead of stopping/starting.

## Testing

1. Start the app and login
2. Verify heartbeat starts (check logs or API)
3. Navigate to different pages
4. Verify heartbeat continues (no errors in console)
5. Logout
6. Verify heartbeat stops

## Troubleshooting

### Heartbeat Not Starting
- Check that `deviceId` is valid
- Verify `apiBaseUrl` and `authToken` are correct
- Check FlutterFlow action logs

### Still Getting Context Errors
- Make sure you're using the service, not starting timers on pages
- Check that no other code is trying to use context in periodic actions

### Multiple Timers Running
- The singleton should prevent this, but check if you're calling `startPeriodicHeartbeat` multiple times
- Use `isHeartbeatRunning()` to check before starting

## Migration from Page-Based Approach

If you currently have periodic actions on a loading screen:

1. **Remove** the periodic action from the loading screen
2. **Add** `startPeriodicHeartbeat` after login (or on main page with check)
3. **Add** `stopPeriodicHeartbeat` on logout
4. **Test** that it works without context errors

