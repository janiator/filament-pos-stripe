import '/auth/custom_auth/auth_util.dart';
import '/backend/api_requests/api_calls.dart';
import 'package:collection/collection.dart';

/// Client-side receipt fetch + print after a POS purchase completes.
///
/// Mirrors checkout `receiptPrint` logic: treat omitted API `auto_print_receipt`
/// as enabled (`hasAutoPrintReceipt`), and allow printing when either legacy
/// `receiptPrinter.isActive` is true or the device default printer has a
/// non-empty `eposUrl`.
///
/// [manualPrint] bypasses the auto-print preference (still requires a printer target).
///
/// Function signature (FlutterFlow Custom Action):
/// Future<dynamic> receiptPrintAfterPosPurchase(
///   BuildContext context,
///   dynamic purchaseCompletedJson,
///   bool? manualPrint,
/// ) async
Future<dynamic> receiptPrintAfterPosPurchase(
  BuildContext context, // ignore: unused_parameter
  dynamic purchaseCompletedJson,
  bool? manualPrint,
) async {
  final device = FFAppState().activePosDevice;
  final defaultPrinter = device.receiptPrinters
      .where((e) => e.id == device.defaultPrinterId)
      .toList()
      .firstOrNull;
  final hasDefaultPrinterTarget = defaultPrinter != null &&
      defaultPrinter.eposUrl.trim().isNotEmpty;

  final allowAutoPrint =
      device.hasAutoPrintReceipt() ? device.autoPrintReceipt : true;
  final shouldPrint = (manualPrint == true || allowAutoPrint) &&
      (FFAppState().receiptPrinter.isActive || hasDefaultPrinterTarget);

  if (!shouldPrint) {
    return null;
  }

  final receiptId = getJsonField(
    purchaseCompletedJson,
    r'''$.data.receipt.id''',
  );
  final getReceiptResultCopy =
      await FilamentPOSPurchaseGroup.getReceiptCall.call(
    receiptId: receiptId,
    authToken: currentAuthenticationToken,
  );

  if (!(getReceiptResultCopy.succeeded)) {
    return null;
  }

  final apiResult3k4Copy = await ReceiptPrinterGroup.printReceiptCall.call(
    body: getReceiptResultCopy.bodyText,
    eposUrl: FFAppState()
        .activePosDevice
        .receiptPrinters
        .where((e) => e.id == FFAppState().activePosDevice.defaultPrinterId)
        .toList()
        .firstOrNull
        ?.eposUrl,
  );

  if (!(apiResult3k4Copy.succeeded)) {
    return null;
  }

  await FilamentPOSPurchaseGroup.markReceiptPrintedCall.call(
    receiptId: receiptId,
    apiHostname: FFDevEnvironmentValues().apiHost,
    authToken: currentAuthenticationToken,
  );

  return null;
}
