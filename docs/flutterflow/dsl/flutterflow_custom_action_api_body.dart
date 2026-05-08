library;

/// Returns the Dart string to pass to FlutterFlow AI `addCustomAction` /
/// `updateCustomAction` as `code`.
///
/// FlutterFlow prepends the block starting with `// Automatic FlutterFlow imports`
/// in the cloud project and on code export. If the payload already contains that
/// block, the editor ends up with duplicate imports and markers.
///
/// Canonical repo files under `docs/flutterflow/custom-actions/` are stored
/// body-only. Legacy files that still include the marker are trimmed at the
/// line `// DO NOT REMOVE OR MODIFY THE CODE ABOVE!` for compatibility.
String customActionCodeForFlutterFlowApi(String raw) {
  const marker = '// DO NOT REMOVE OR MODIFY THE CODE ABOVE!';
  final index = raw.indexOf(marker);
  if (index == -1) {
    return raw.trim();
  }

  return raw.substring(index + marker.length).trimLeft();
}
