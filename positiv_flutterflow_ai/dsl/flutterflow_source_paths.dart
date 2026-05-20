library;

import 'dart:io';

import 'flutterflow_custom_action_api_body.dart';

/// POSitiv FlutterFlow AI workspace root (`positiv_flutterflow_ai/`).
String positivWorkspaceRoot() {
  final scriptPath = File(Platform.script.toFilePath()).resolveSymbolicLinksSync();
  return File(scriptPath).parent.parent.path;
}

File generatedCustomActionSource(String fileName) {
  return File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/$fileName',
  );
}

File generatedCustomWidgetSource(String fileName) {
  return File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/widgets/$fileName',
  );
}

/// Resolves a custom-class file (handles FlutterFlow export without `.dart`).
File generatedCustomClassSource(String fileName) {
  final base = fileName.endsWith('.dart')
      ? fileName.substring(0, fileName.length - 5)
      : fileName;
  final root = '${positivWorkspaceRoot()}/generated_code/lib/custom_code';
  final withExt = File('$root/$base.dart');
  if (withExt.existsSync()) {
    return withExt;
  }
  return File('$root/$base');
}

String readGeneratedCustomActionBody(String fileName) {
  final file = generatedCustomActionSource(fileName);
  if (!file.existsSync()) {
    throw StateError('Missing generated custom action: ${file.path}');
  }
  return customActionCodeForFlutterFlowApi(file.readAsStringSync());
}

String readGeneratedCustomWidgetBody(String fileName) {
  final file = generatedCustomWidgetSource(fileName);
  if (!file.existsSync()) {
    throw StateError('Missing generated custom widget: ${file.path}');
  }
  return customWidgetCodeForFlutterFlowApi(file.readAsStringSync());
}

String readGeneratedCustomClassBody(String fileName) {
  final file = generatedCustomClassSource(fileName);
  if (!file.existsSync()) {
    throw StateError('Missing generated custom class: ${file.path}');
  }
  return file.readAsStringSync().trim();
}
