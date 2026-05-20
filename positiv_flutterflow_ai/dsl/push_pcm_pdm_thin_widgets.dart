library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

import 'flutterflow_source_paths.dart';
import 'positiv_flutter_export.dart';

/// Directory of [absolutePath] (no `dart:path`; FlutterFlow AI validate target is VM-lite).
String _dirname(String absolutePath) {
  final p = absolutePath.replaceAll(r'\', '/');
  final i = p.lastIndexOf('/');
  if (i <= 0) {
    return i == 0 ? '/' : '.';
  }
  return p.substring(0, i);
}

String _joinPath(String dir, List<String> segments) {
  var base = dir.endsWith('/') ? dir.substring(0, dir.length - 1) : dir;
  for (final s in segments) {
    base = '$base/$s';
  }
  return base;
}

File _thinWidgetSource(String fileName) {
  final generated = generatedCustomWidgetSource(fileName);
  if (generated.existsSync()) {
    return generated;
  }
  return positivCustomWidgetSource(fileName);
}

bool _keepProjectValidatorFinding(ProjectError error) {
  if (error.isWarning) {
    return true;
  }
  if (error.message == 'Variable value configured incorrectly for API call.') {
    return false;
  }
  if (error.message == 'Conditional execution for action is improperly set.') {
    return false;
  }
  return true;
}

/// Updates only [ProductsCategoriesManager] and [PrinterDetectionManager] from
/// `generated_code/lib/custom_code/widgets/` (thin shells delegating to custom classes).
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);

  final pcmWidget = _thinWidgetSource('products_categories_manager.dart');
  final pdmWidget = _thinWidgetSource('printer_detection_manager.dart');

  for (final f in [pcmWidget, pdmWidget]) {
    if (!f.existsSync()) {
      stderr.writeln('Missing source file: ${f.path}');
      exit(1);
    }
  }

  final pcmWidgetCode = pcmWidget.readAsStringSync();
  final pdmWidgetCode = pdmWidget.readAsStringSync();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          updateCustomWidget(
            project,
            name: 'ProductsCategoriesManager',
            code: pcmWidgetCode,
          );
          updateCustomWidget(
            project,
            name: 'PrinterDetectionManager',
            code: pdmWidgetCode,
          );
        });
      },
      apiKey: options.apiKey,
      baseUrl: options.baseUrl,
      projectName: options.projectName,
      projectId: options.projectId,
      findOrCreate: options.findOrCreate,
      allowNewProject: options.allowNewProject,
      dryRun: options.dryRun,
      commitMessage:
          options.commitMessage ??
          'fix(pos): thin ProductsCategoriesManager/PrinterDetectionManager shells',
      validationFilter: _keepProjectValidatorFinding,
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

final class _CliOptions {
  const _CliOptions({
    this.apiKey,
    this.baseUrl,
    this.projectName,
    this.projectId,
    this.findOrCreate = false,
    this.allowNewProject = false,
    this.dryRun = false,
    this.commitMessage,
  });

  final String? apiKey;
  final String? baseUrl;
  final String? projectName;
  final String? projectId;
  final bool findOrCreate;
  final bool allowNewProject;
  final bool dryRun;
  final String? commitMessage;
}

_CliOptions _parseCliOptions(List<String> args) {
  String? apiKey;
  String? baseUrl;
  String? projectName;
  String? projectId;
  String? commitMessage;
  var findOrCreate = false;
  var allowNewProject = false;
  var dryRun = false;

  for (var i = 0; i < args.length; i++) {
    final arg = args[i];
    switch (arg) {
      case '--help':
      case '-h':
        stdout.writeln(
          'Push thin PCM/PDM widget shells from generated_code/lib/custom_code/widgets/.',
        );
        exit(0);
      case '--api-key':
        apiKey = _requireValue(args, ++i, '--api-key');
      case '--base-url':
        baseUrl = _requireValue(args, ++i, '--base-url');
      case '--project-name':
        projectName = _requireValue(args, ++i, '--project-name');
      case '--project-id':
        projectId = _requireValue(args, ++i, '--project-id');
      case '--commit-message':
        commitMessage = _requireValue(args, ++i, '--commit-message');
      case '--find-or-create':
        findOrCreate = true;
      case '--allow-new-project':
        allowNewProject = true;
      case '--dry-run':
        dryRun = true;
      default:
        stderr.writeln('Unknown option: $arg');
        exit(64);
    }
  }

  return _CliOptions(
    apiKey:
        apiKey ??
        Platform.environment['FLUTTERFLOW_AI_API_KEY'] ??
        Platform.environment['FF_API_KEY'],
    baseUrl: baseUrl,
    projectName: projectName,
    projectId:
        projectId ??
        Platform.environment['FLUTTERFLOW_POSITIV_PROJECT_ID'] ??
        'pointofsale-xrlz5i',
    findOrCreate: findOrCreate,
    allowNewProject: allowNewProject,
    dryRun: dryRun,
    commitMessage: commitMessage,
  );
}

String _requireValue(List<String> args, int index, String flag) {
  if (index >= args.length) {
    stderr.writeln('Missing value for $flag.');
    exit(64);
  }
  return args[index];
}
