# Flutter Custom Action: ffconnectreader with Re-registration

**Suggested implementation for FlutterFlow.** Apply this logic in your custom action that connects Bluetooth readers (Chipper 2X BT, WisePad 3, Stripe M2). When connection fails because the reader is registered to a different location or store, prompt for a registration code and call the backend to register the reader to the current location, then retry connection.

## Backend API

- **Endpoint:** `POST {apiBaseUrl}/terminals/readers/register-from-code`
- **Auth:** Bearer token (e.g. from `FFAppState().authToken` or your auth state)
- **Headers:** `X-Tenant: {storeSlug}` if using multi-tenant
- **Body (JSON):**
  - `registration_code` (string, required) – from the reader (admin settings → generate pairing code)
  - `terminal_location_id` (int, optional) OR `stripe_location_id` (string, optional) – target location
  - `label` (string, optional)

The API registers the reader in Stripe for the current store/location, removes it from any previous store, and returns the reader. You can then retry `connectReader` with the same reader and `locationId`.

## Flow

1. Call `connectReader(selectedReader, BluetoothConnectionConfiguration(locationId: locationId, ...))`.
2. On exception (e.g. `TerminalException` or platform equivalent):
   - If the error message suggests location/store mismatch (e.g. contains "location", "registered", "different"):
     - Show a dialog: "This reader is registered to a different location. Generate a registration code on the reader (admin settings), then enter it below to register the reader here."
     - Provide a text field for the registration code.
     - On confirm, call `POST .../terminals/readers/register-from-code` with `registration_code` and either `terminal_location_id` or `stripe_location_id` (same value you use as `locationId` if it is the Stripe location ID).
     - On success, retry `connectReader` with the same reader and `locationId`.
     - On API error, show the error to the user.
3. Return the reader’s serial number (or relevant id) on success.

## Function signature (FlutterFlow)

- **context** – BuildContext (auto-provided by FlutterFlow) for showing the registration-code dialog.
- **readerId** – Serial number from discovery (match `Reader.serialNumber`).
- **locationId** – Stripe location ID (e.g. `tml_xxx`); also sent as `stripe_location_id` to the API.
- **apiBaseUrl** – Backend base URL (e.g. from app config).
- **authToken** – Bearer token (e.g. from `FFAppState().authToken` or auth state).
- **storeSlug** – Current store slug for `X-Tenant` header.

Return type: `Future<String>` – reader serial number on success; throws on failure or cancel.

## Complete custom action code

Copy the code below into your FlutterFlow custom action. Keep the automatic imports block at the top; add the extra imports and the rest of the script.

```dart
// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/custom_code/actions/index.dart';
import '/flutter_flow/custom_functions.dart';
import 'package:flutter/material.dart';
// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:mek_stripe_terminal/mek_stripe_terminal.dart';

class MyMobileReaderDelegate extends MobileReaderDelegate {
  @override
  void onReportAvailableUpdate(ReaderSoftwareUpdate update) {}

  @override
  void onStartInstallingUpdate(
      ReaderSoftwareUpdate update, Cancellable cancelUpdate) {}

  @override
  void onReportReaderSoftwareUpdateProgress(double progress) {}

  @override
  void onFinishInstallingUpdate(
      ReaderSoftwareUpdate? update, TerminalException? exception) {}

  @override
  void onRequestReaderDisplayMessage(ReaderDisplayMessage message) {}

  @override
  void onRequestReaderInput(List<ReaderInputOption> options) {}

  @override
  void onReportReaderEvent(ReaderEvent event) {}

  @override
  void onReaderReconnectStarted(
      Reader reader, Cancellable cancelReconnect, DisconnectReason reason) {}

  @override
  void onReaderReconnectFailed(Reader reader) {}

  @override
  void onReaderReconnectSucceeded(Reader reader) {}

  @override
  void onDisconnect(DisconnectReason reason) {}

  @override
  void onBatteryLevelUpdate(
      double batteryLevel, BatteryStatus? batteryStatus, bool isCharging) {}

  @override
  void onReportLowBatteryWarning() {}
}

Future<Map<String, dynamic>?> _registerReaderFromCode({
  required String apiBaseUrl,
  required String authToken,
  required String storeSlug,
  required String registrationCode,
  required String locationId,
  String? label,
}) async {
  final uri = Uri.parse('$apiBaseUrl/terminals/readers/register-from-code');
  final body = <String, dynamic>{
    'registration_code': registrationCode,
    'stripe_location_id': locationId,
  };
  if (label != null && label.isNotEmpty) body['label'] = label;
  final response = await http.post(
    uri,
    headers: <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': 'Bearer $authToken',
      if (storeSlug.isNotEmpty) 'X-Tenant': storeSlug,
    },
    body: jsonEncode(body),
  );
  if (response.statusCode == 201) {
    return jsonDecode(response.body) as Map<String, dynamic>;
  }
  return null;
}

Future<String> ffconnectreader(
  BuildContext context,
  String readerId,
  String locationId,
  String apiBaseUrl,
  String authToken,
  String storeSlug,
) async {
  List<Reader> readers = await Terminal.instance
      .discoverReaders(BluetoothDiscoveryConfiguration(isSimulated: false))
      .first;

  Reader selectedReader = readers.firstWhere(
    (r) => r.serialNumber == readerId,
    orElse: () => throw Exception('Reader not found'),
  );

  final config = BluetoothConnectionConfiguration(
    locationId: locationId,
    readerDelegate: MyMobileReaderDelegate(),
  );

  try {
    await Terminal.instance.connectReader(selectedReader, configuration: config);
    return selectedReader.serialNumber;
  } catch (e) {
    final message = e.toString().toLowerCase();
    final isLocationMismatch = message.contains('location') ||
        message.contains('registered') ||
        message.contains('different');

    if (!isLocationMismatch) rethrow;

    final registrationCode = await showDialog<String>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        final controller = TextEditingController();
        return AlertDialog(
          title: const Text('Reader at different location'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'This reader is registered to a different location. '
                  'Generate a registration code on the reader (admin settings), '
                  'then enter it below to register the reader here.',
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: controller,
                  decoration: const InputDecoration(
                    labelText: 'Registration code',
                    hintText: 'Enter code from reader',
                    border: OutlineInputBorder(),
                  ),
                  autofocus: true,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
              child: const Text('Register and connect'),
            ),
          ],
        );
      },
    );

    if (registrationCode == null || registrationCode.isEmpty) {
      throw Exception('Registration cancelled');
    }

    final result = await _registerReaderFromCode(
      apiBaseUrl: apiBaseUrl,
      authToken: authToken,
      storeSlug: storeSlug,
      registrationCode: registrationCode,
      locationId: locationId,
    );

    if (result == null) {
      throw Exception(
        'Failed to register reader. Check the registration code and try again.',
      );
    }

    await Terminal.instance.connectReader(selectedReader, configuration: config);
    return selectedReader.serialNumber;
  }
}
```

## Notes

- **context:** FlutterFlow usually provides `context` when the action is run from a widget (e.g. button press). Use it as the first parameter so the registration dialog can be shown.
- **locationId:** Pass Stripe’s `stripe_location_id` (e.g. `tml_xxx`). The API accepts it as `stripe_location_id` in the body.
- **Reader discovery:** Still use `discoverReaders(BluetoothDiscoveryConfiguration(...))` and match by `serialNumber == readerId`.
- Per project rules, do not edit files under FlutterFlow-managed directories; paste this logic into your custom action in the FlutterFlow editor.
