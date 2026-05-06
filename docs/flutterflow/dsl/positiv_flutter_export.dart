import 'dart:io';

/// Resolves a file under the local FlutterFlow desktop export for POSitiv (`p_o_sitiv`).
///
/// Set [POSITIV_FLUTTER_EXPORT_ROOT] to the export project root if it is not under the
/// default macOS FlutterFlow app support path.
File positivCustomActionSource(String fileName) {
  final home = Platform.environment['HOME'];
  if (home == null || home.isEmpty) {
    throw StateError(
      'HOME is not set; set POSITIV_FLUTTER_EXPORT_ROOT to your p_o_sitiv export root.',
    );
  }
  final root =
      Platform.environment['POSITIV_FLUTTER_EXPORT_ROOT'] ??
      '$home/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv';
  return File('$root/lib/custom_code/actions/$fileName');
}

/// Resolves a file under `lib/custom_code/` in the desktop export (e.g. shared custom classes).
File positivCustomCodeSource(String fileName) {
  final home = Platform.environment['HOME'];
  if (home == null || home.isEmpty) {
    throw StateError(
      'HOME is not set; set POSITIV_FLUTTER_EXPORT_ROOT to your p_o_sitiv export root.',
    );
  }
  final root =
      Platform.environment['POSITIV_FLUTTER_EXPORT_ROOT'] ??
      '$home/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv';
  return File('$root/lib/custom_code/$fileName');
}

/// Resolves a custom widget Dart file under `lib/custom_code/widgets/` in the export.
File positivCustomWidgetSource(String fileName) {
  final home = Platform.environment['HOME'];
  if (home == null || home.isEmpty) {
    throw StateError(
      'HOME is not set; set POSITIV_FLUTTER_EXPORT_ROOT to your p_o_sitiv export root.',
    );
  }
  final root =
      Platform.environment['POSITIV_FLUTTER_EXPORT_ROOT'] ??
      '$home/Library/Application Support/io.flutterflow.prod.mac/p_o_sitiv';
  return File('$root/lib/custom_code/widgets/$fileName');
}
