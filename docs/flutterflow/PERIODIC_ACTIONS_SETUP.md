# Periodic Actions Setup Guide

## Files Created

1. **`pos_periodic_actions_service.dart`** - Contains the `PosPeriodicActionsService` singleton class
2. **`start_pos_periodic_actions.dart`** - Action to start periodic actions
3. **`stop_pos_periodic_actions.dart`** - Action to stop periodic actions
4. **`update_pos_periodic_actions_token.dart`** - Action to update auth token
5. **`is_pos_periodic_actions_running.dart`** - Action to check if running

## Setup Steps in FlutterFlow

### Step 1: Add the Service Class (Required First)

1. Go to **Custom Code** → **Custom Actions**
2. Click **+ Add Action**
3. Name: `posPeriodicActionsService` (or any name - this won't be called directly)
4. Paste the entire contents of `pos_periodic_actions_service.dart`
5. **Important**: This file contains only the service class, no action function. FlutterFlow may show a warning, but that's OK - the class will be available to other actions.

### Step 2: Add Individual Actions

For each of the following, create a separate custom action:

#### Action 1: Start Periodic Actions
1. **Name**: `startPosPeriodicActions`
2. **File**: `start_pos_periodic_actions.dart`
3. **Parameters**:
   - `apiBaseUrl` (String, required)
   - `authToken` (String, required)
   - `deviceId` (String, required)
   - `storeSlug` (String, required)

#### Action 2: Stop Periodic Actions
1. **Name**: `stopPosPeriodicActions`
2. **File**: `stop_pos_periodic_actions.dart`
3. **Parameters**: None

#### Action 3: Update Token
1. **Name**: `updatePosPeriodicActionsToken`
2. **File**: `update_pos_periodic_actions_token.dart`
3. **Parameters**:
   - `newAuthToken` (String, required)

#### Action 4: Check if Running
1. **Name**: `isPosPeriodicActionsRunning`
2. **File**: `is_pos_periodic_actions_running.dart`
3. **Parameters**: None

## Usage

### In Loading Page (After Navigation)

```dart
// After context.goNamed(PosWidget.routeName);
await actions.startPosPeriodicActions(
  FFDevEnvironmentValues().apiHost,
  currentAuthenticationToken!,
  getJsonField(_model.pOSregisterOutput, r'''$.deviceId''').toString(),
  currentUserData?.currentStore?.slug ?? '',
);
```

### On Logout

```dart
await actions.stopPosPeriodicActions();
```

### On Token Refresh

```dart
await actions.updatePosPeriodicActionsToken(newAuthToken);
```

### Check if Running (Optional)

```dart
final result = await actions.isPosPeriodicActionsRunning();
if (result['isRunning'] == true) {
  // Actions are running
}
```

## Important Notes

1. **Add the service class first** - The other actions depend on it
2. **Service runs independently** - No context needed, safe after widgets are disposed
3. **Singleton pattern** - Only one instance runs at a time
4. **Automatic cleanup** - Call `stopPosPeriodicActions()` on logout

## Troubleshooting

### "Class not found" errors
- Make sure `pos_periodic_actions_service.dart` is added first
- FlutterFlow should make the class available via `/custom_code/actions/index.dart`

### Actions not starting
- Check that all parameters are provided correctly
- Verify `deviceId` is valid (from `registerPosDevice` response)

### Still getting context errors
- Make sure you removed all `InstantTimer.periodic` from loading page
- Verify service is started AFTER navigation, not before

