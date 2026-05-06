import 'dart:io';

String _positivFlutterExportRoot() {
  final home = Platform.environment['HOME'];
  if (home == null || home.isEmpty) {
    throw StateError(
      'HOME is not set; set POSITIV_FLUTTER_EXPORT_ROOT to your p_o_sitiv export root.',
    );
  }
  return Platform.environment['POSITIV_FLUTTER_EXPORT_ROOT'] ??
      '$home/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv';
}

/// Resolves a file under the local FlutterFlow desktop export for POSitiv (`p_o_sitiv`).
///
/// Set [POSITIV_FLUTTER_EXPORT_ROOT] to the export project root if it is not under the
/// default macOS FlutterFlow app support path.
File positivCustomActionSource(String fileName) {
  return File(
    '${_positivFlutterExportRoot()}/lib/custom_code/actions/$fileName',
  );
}

/// Library or shared Dart under `lib/custom_code/` (not `actions/` or `widgets/`).
File positivCustomCodeSource(String fileName) {
  return File('${_positivFlutterExportRoot()}/lib/custom_code/$fileName');
}

File positivCustomWidgetSource(String fileName) {
  return File(
    '${_positivFlutterExportRoot()}/lib/custom_code/widgets/$fileName',
  );
}
