# StripeTerminalSingleton and "Søk på nytt"

The connector passes a **token provider** to `ensureInit()` that **fetches a new token from the API every time the SDK requests one** (for init and for connect), so "the connection token has already been redeemed" is avoided. The connector also calls **`StripeTerminalSingleton.instance.resetForRescan()`** before re-running init when the user taps "Søk på nytt". If your singleton does not implement that method, the SDK may keep using the previous (already-used) connection token and discovery can hang or never complete.

## Recommended: add `resetForRescan()` to your singleton

Ensure your `StripeTerminalSingleton` (in `custom_code/stripe_terminal_singleton.dart` or similar):

1. Does **not** cache "already inited" in a way that prevents re-calling the token provider. When `ensureInit(Future<String> Function() tokenProvider)` is called after a rescan, the SDK must receive the **new** token (e.g. by calling `tokenProvider()` again and passing the result to Stripe’s `setConnectionToken` or equivalent).
2. Exposes **`void resetForRescan()`** and in that method clears any state that would cause the next `ensureInit()` to skip re-applying the token. For example, set an internal `_inited = false` or clear the cached token so the next `ensureInit()` runs the full init again and passes the new token to the Stripe Terminal SDK.

### Example (conceptual)

```dart
// In your StripeTerminalSingleton class:

bool _inited = false;

Future<void> ensureInit(Future<String> Function() tokenProvider) async {
  // Always call the provider and set the token - never cache the token (single-use).
  // After app restart or rescan, the connector clears app state and passes a provider that fetches fresh.
  final token = await tokenProvider();
  if (token.isEmpty) throw Exception('Connection token is empty');
  await Terminal.instance.setConnectionToken(token); // or whatever the SDK uses
  _inited = true;
}

/// Call this before "Søk på nytt" so the next ensureInit() re-applies the token.
void resetForRescan() {
  _inited = false;
}
```

Important: **Call the token provider every time** the SDK needs a token (e.g. on every `ensureInit` and whenever the SDK requests a new token). Do not cache and reuse the same token—Stripe tokens are single-use, so reusing causes "already been redeemed". If your SDK API differs (e.g. you pass a callback that the SDK calls when it needs a token), ensure that callback always invokes your token provider and passes the result to the SDK.
