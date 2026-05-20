library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';

import 'flutterflow_custom_action_api_body.dart';
import 'flutterflow_source_paths.dart';

/// Pushes deferred-resume helper custom actions from generated_code export into
/// FlutterFlow POSitiv (`pointofsale-xrlz5i`):
/// - getDeferredResumeContext
/// - clearDeferredResumeContext
/// - serializeCartForCompleteDeferred
///
/// Source: [repo]/generated_code/lib/custom_code/actions/*.dart
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final scriptDir = File(Platform.script.toFilePath()).parent;
  final workspaceRoot = scriptDir.parent;
  final actionsDir = Directory('${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions');

  final specs = <_ActionSpec>[
    _ActionSpec(
      name: 'getDeferredResumeContext',
      file: File('${actionsDir.path}/get_deferred_resume_context.dart'),
      description:
          'Read SharedPreferences deferred resume context for pos banner / branching.',
    ),
    _ActionSpec(
      name: 'clearDeferredResumeContext',
      file: File('${actionsDir.path}/clear_deferred_resume_context.dart'),
      description: 'Clear deferred resume prefs (abandon or cleanup).',
    ),
    _ActionSpec(
      name: 'serializeCartForCompleteDeferred',
      file: File('${actionsDir.path}/serialize_cart_for_complete_deferred.dart'),
      description:
          'Serialize FFAppState().cart to cartJson for completeDeferredPayment.',
    ),
  ];

  for (final s in specs) {
    if (!s.file.existsSync()) {
      stderr.writeln('Missing source file: ${s.file.path}');
      exit(1);
    }
  }

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          for (final s in specs) {
            final code = customActionCodeForFlutterFlowApi(s.file.readAsStringSync());
            final existing = findCustomAction(project, name: s.name);
            if (existing == null) {
              addCustomAction(
                project,
                name: s.name,
                code: code,
                description: s.description,
                arguments: const [],
              );
            } else {
              updateCustomAction(
                project,
                name: s.name,
                code: code,
                description: s.description,
              );
            }
          }
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
          'feat(pos): deferred resume helpers (get/clear/serialize cart)',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

final class _ActionSpec {
  const _ActionSpec({
    required this.name,
    required this.file,
    required this.description,
  });

  final String name;
  final File file;
  final String description;
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
Push getDeferredResumeContext, clearDeferredResumeContext, serializeCartForCompleteDeferred.

Usage (from positiv_flutterflow_ai/):
  dart run dsl/upsert_deferred_resume_helpers.dart [options]

Options:
  --api-key <key>           FlutterFlow API key (or FLUTTERFLOW_AI_API_KEY / FF_API_KEY).
  --project-id <id>         Default: pointofsale-xrlz5i
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
