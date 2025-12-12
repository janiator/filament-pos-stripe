# Loading Page Refactoring Guide

## Problem

The current loading page has multiple nested `InstantTimer.periodic` calls that:
1. Continue running after navigation, causing context errors
2. Are deeply nested and hard to manage
3. Depend on widget context which gets disposed
4. Can't be easily stopped or restarted

## Solution

Extract all periodic actions into a singleton service (`POSPeriodicActionsService`) that:
- Doesn't depend on widget context
- Can be started once and runs independently
- Can be safely stopped/restarted
- Manages all timers in one place

## Refactored Loading Page

### Before (Current Code)

```dart
// In loading page initState
_model.cacheRefreshTimer = InstantTimer.periodic(
  duration: Duration(milliseconds: 600000),
  callback: (timer) async {
    // ... nested timers ...
    _model.instantTimer = InstantTimer.periodic(
      duration: Duration(milliseconds: 60000),
      callback: (timer) async {
        // ... more nested timers ...
      },
    );
  },
);
```

### After (Refactored)

```dart
// In loading page initState - simplified
SchedulerBinding.instance.addPostFrameCallback((_) async {
  // Initial setup actions
  _model.pOSregisterOutput = await actions.registerPosDevice(...);
  _model.availablePaymentMethods = await FilamentAPIGroup.getPaymentMethodsCall.call(...);
  // ... other setup actions ...
  
  // Navigate to main page
  context.goNamed(PosWidget.routeName);
  
  // Start periodic actions service (no context needed, runs independently)
  await actions.startPosPeriodicActions(
    FFDevEnvironmentValues().apiHost,
    currentAuthenticationToken!,
    getJsonField(_model.pOSregisterOutput, r'''$.deviceId''').toString(),
    currentUserData?.currentStore?.slug ?? '',
  );
});
```

## Step-by-Step Migration

### Step 1: Add the Service

1. In FlutterFlow, go to **Custom Code** → **Custom Actions**
2. Create a new custom action named `startPosPeriodicActions`
3. Copy the code from `pos_periodic_actions_service.dart`
4. Also create:
   - `stopPosPeriodicActions`
   - `updatePosPeriodicActionsToken`
   - `isPosPeriodicActionsRunning`

### Step 2: Update Loading Page

1. **Remove all nested `InstantTimer.periodic` calls** from the loading page
2. **After navigation** (after `context.goNamed(PosWidget.routeName)`), add:
   ```dart
   await actions.startPosPeriodicActions(
     FFDevEnvironmentValues().apiHost,
     currentAuthenticationToken!,
     getJsonField(_model.pOSregisterOutput, r'''$.deviceId''').toString(),
     currentUserData?.currentStore?.slug ?? '',
   );
   ```

### Step 3: Update Logout Flow

1. **Before logout**, add:
   ```dart
   await actions.stopPosPeriodicActions();
   ```
2. This ensures clean shutdown of all timers

### Step 4: Handle Token Refresh (Optional)

If your app refreshes tokens:
1. When token refreshes, call:
   ```dart
   await actions.updatePosPeriodicActionsToken(newAuthToken);
   ```

## Complete Refactored Loading Page Structure

```dart
@override
void initState() {
  super.initState();
  _model = createModel(context, () => LoadingPageModel());

  SchedulerBinding.instance.addPostFrameCallback((_) async {
    // 1. Register POS device
    _model.pOSregisterOutput = await actions.registerPosDevice(
      FFDevEnvironmentValues().apiHost,
      currentAuthenticationToken!,
      '',
      '',
    );

    // 2. Get payment methods
    _model.availablePaymentMethods =
        await FilamentAPIGroup.getPaymentMethodsCall.call(
      authToken: currentAuthenticationToken,
    );

    FFAppState().availablePaymentMethods =
        FilamentAPIGroup.getPaymentMethodsCall
            .paymentMethods(
              (_model.availablePaymentMethods?.jsonBody ?? ''),
            )!
            .toList()
            .cast<PosPaymentMethodStruct>();

    safeSetState(() {});

    // 3. Get connection token
    _model.getConnectionToken =
        await POSStripeConnectAPIGroup.createTerminalConnectionTokenCall.call(
      store: currentUserData?.currentStore?.slug,
      bearerAuth: currentAuthenticationToken,
    );

    if ((_model.getConnectionToken?.succeeded ?? true)) {
      FFAppState().stripeLocationId =
          POSStripeConnectAPIGroup.createTerminalConnectionTokenCall.location(
        (_model.getConnectionToken?.jsonBody ?? ''),
      )!;

      FFAppState().stripeConnectionToken =
          POSStripeConnectAPIGroup.createTerminalConnectionTokenCall.secret(
        (_model.getConnectionToken?.jsonBody ?? ''),
      )!;

      safeSetState(() {});
    }

    // 4. Terminal picker
    await action_blocks.stripeTerminalPickerConditional(context);
    safeSetState(() {});

    // 5. Set current POS session
    await action_blocks.setCurrentPosSession(context);
    safeSetState(() {});

    // 6. Navigate to main page
    context.goNamed(PosWidget.routeName);

    // 7. Start periodic actions service (runs independently, no context needed)
    await actions.startPosPeriodicActions(
      FFDevEnvironmentValues().apiHost,
      currentAuthenticationToken!,
      getJsonField(
        _model.pOSregisterOutput,
        r'''$.deviceId''',
      ).toString(),
      currentUserData?.currentStore?.slug ?? '',
    );
  });
}
```

## What the Service Handles

The `POSPeriodicActionsService` manages:

1. **Cache Refresh** (every 10 minutes)
   - Updates `FFAppState().cacheRefreshKey`

2. **Device Heartbeat** (every 1 minute)
   - Calls `updateDeviceHeartbeat` to keep device online

3. **Drawer Status Check** (every 4 seconds)
   - Checks printer drawer status
   - Updates POS lock status
   - Reports nullinnstall when drawer opens unexpectedly

## Benefits

1. ✅ **No Context Errors**: Service doesn't use `BuildContext`
2. ✅ **Clean Code**: No nested timers, easy to read
3. ✅ **Manageable**: All timers in one place
4. ✅ **Stoppable**: Can be stopped on logout
5. ✅ **Resilient**: Errors don't crash the app
6. ✅ **Testable**: Service can be tested independently

## Testing

1. Start app and login
2. Verify periodic actions start (check logs)
3. Navigate around the app
4. Verify no context errors in console
5. Verify drawer checking works
6. Verify heartbeat continues
7. Logout
8. Verify all timers stop

## Troubleshooting

### Actions Not Starting
- Check that `deviceId` is valid
- Verify all parameters are passed correctly
- Check FlutterFlow action logs

### Still Getting Context Errors
- Make sure you removed all `InstantTimer.periodic` from loading page
- Verify service is started AFTER navigation
- Check that no other code uses context in periodic callbacks

### Timers Not Stopping
- Verify `stopPosPeriodicActions()` is called on logout
- Check that service singleton is working correctly

