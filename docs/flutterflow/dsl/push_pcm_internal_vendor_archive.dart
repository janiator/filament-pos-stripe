library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

import 'positiv_flutter_export.dart';

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

File _customArtifactSource(String fileName) {
  var dir = _dirname(Platform.script.toFilePath());
  for (var i = 0; i < 14; i++) {
    final fromDocs = File(
      _joinPath(dir, ['docs', 'flutterflow', 'custom-widgets', fileName]),
    );
    if (fromDocs.existsSync()) {
      return fromDocs;
    }
    final parent = _dirname(dir);
    if (parent == dir) {
      break;
    }
    dir = parent;
  }

  if (fileName == 'products_categories_manager.dart') {
    return positivCustomWidgetSource(fileName);
  }
  return positivCustomCodeSource(fileName);
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

void _upsertCustomClass(FFProject project, String name, String code) {
  if (findCustomClass(project, name: name) == null) {
    addCustomClass(project, name: name, code: code);
  } else {
    updateCustomClass(project, name: name, code: code);
  }
}

/// Pushes vendor archive/delete UI in [PcmInternalLibrary] and the thin
/// [ProductsCategoriesManager] shell (POSitiv `pointofsale-xrlz5i`).
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);

  final pcmLib = _customArtifactSource('pcm_internal_library.dart');
  final pcmWidget = _customArtifactSource('products_categories_manager.dart');

  for (final f in [pcmLib, pcmWidget]) {
    if (!f.existsSync()) {
      stderr.writeln('Missing source file: ${f.path}');
      stderr.writeln(
        'Set POSITIV_FLUTTER_EXPORT_ROOT to your p_o_sitiv project root, or open the project in FlutterFlow desktop so the export exists.',
      );
      exit(1);
    }
  }

  final pcmLibCode = pcmLib.readAsStringSync();
  final pcmWidgetCode = pcmWidget.readAsStringSync();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          _upsertCustomClass(project, 'PcmInternalLibrary', pcmLibCode);
          updateCustomWidget(
            project,
            name: 'ProductsCategoriesManager',
            code: pcmWidgetCode,
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
          'feat(pos): vendor supplier ledger account + PCM',
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
        _printUsage();
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
        _printUsage();
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
    _printUsage();
    exit(64);
  }
  return args[index];
}

void _printUsage() {
  stdout.writeln('''
Push PcmInternalLibrary (vendor archive UI) + ProductsCategoriesManager shell.

Usage:
  dart run dsl/push_pcm_internal_vendor_archive.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FLUTTERFLOW_AI_API_KEY or FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-name <name>     Create a new project with this name.
  --project-id <id>         Push into an existing project (default: pointofsale-xrlz5i or FLUTTERFLOW_POSITIV_PROJECT_ID).
  --find-or-create          Retry by reusing a same-name project before creating.
  --allow-new-project       Bypass the workspace binding guard and create a different project.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Compile and validate without pushing.
  --help, -h                Show this help.
''');
}
