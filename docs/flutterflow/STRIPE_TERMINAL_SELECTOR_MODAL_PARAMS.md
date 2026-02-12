# Stripe Terminal Selector Modal – FlutterFlow parameters

## "Failed to process parameters" when saving

FlutterFlow custom actions require the **parameters in your code to match the parameters defined in the Custom Action in FlutterFlow** (name, order, and nullability). Mismatches cause: *"Failed to process parameters. Are you sure you want to save?"*

## Current action signature (9 parameters)

The action uses **positional parameters only** (no named parameters), in this order:

1. `context` – BuildContext (required)
2. `width` – double, nullable
3. `height` – double, nullable
4. `apiBaseUrl` – String, nullable
5. `authToken` – String, nullable
6. `storeSlug` – String, nullable
7. `deviceIdentifier` – String, nullable
8. `posDeviceId` – int, nullable
9. `autoconnect` – bool, nullable (defaults to true when null)
10. `autoCloseOnConnect` – bool, nullable (defaults to true when null; set to false to keep the modal open after connecting)

**In FlutterFlow:** Open the Custom Action for `stripeTerminalSelectorModal` and add parameters in exactly this order and type. Mark all except `context` as optional/nullable if your UI doesn’t always pass them.

## If you still get "Failed to process parameters"

Some FlutterFlow setups only allow a few parameters. You can use a **minimal 3-parameter** version:

- **Parameters:** `BuildContext context`, `double? width`, `double? height` only.
- **Behavior:** The modal then uses **App State** for API and device options. Before calling the action, set in App State (if your app has these):  
  `stripeApiBaseUrl`, `stripeAuthToken`, `stripeStoreSlug`, `stripeDeviceIdentifier`, `posDeviceId`, and a flag for autoconnect.  
  In the modal widget, read these in `initState` when `apiBaseUrl`/`authToken`/`storeSlug` are null.

To switch to the minimal version: in `stripe_terminal_selector_modal.dart`, change the action to:

```dart
Future<dynamic> stripeTerminalSelectorModal(
  BuildContext context,
  double? width,
  double? height,
) async {
  // Read from App State: FFAppState().stripeApiBaseUrl, etc.
  final apiBaseUrl = FFAppState().stripeApiBaseUrl;
  final authToken = FFAppState().stripeAuthToken;
  final storeSlug = FFAppState().storeSlug;  // or your app state field
  final deviceIdentifier = FFAppState().deviceIdentifier;
  final posDeviceId = FFAppState().posDeviceId;
  final autoconnect = true;

  try {
    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: EdgeInsets.zero,
        child: StripeTerminalSelectorModal(
          width: width,
          height: height,
          apiBaseUrl: apiBaseUrl,
          authToken: authToken,
          storeSlug: storeSlug,
          deviceIdentifier: deviceIdentifier,
          posDeviceId: posDeviceId,
          autoconnect: autoconnect,
        ),
      ),
    );
    return {'success': true};
  } catch (e) {
    return {'success': false, 'message': 'Failed to show terminal selector modal: $e'};
  }
}
```

Ensure your App State has the corresponding variables; create them in FlutterFlow if they don’t exist.
