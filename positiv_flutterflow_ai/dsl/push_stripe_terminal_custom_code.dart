library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

import 'flutterflow_source_paths.dart';

/// Pushes shared Stripe Terminal custom-code files (custom classes) to POSitiv.
///
/// Sources: `generated_code/lib/custom_code/*.dart`
/// Exports as: `lib/custom_code/stripe_terminal_singleton.dart`,
///             `lib/custom_code/stripe_terminal_background_connect.dart`
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final repoRoot = _repoRoot();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          _upsertCustomClassFromRepo(
            project,
            repoRoot,
            'stripe_terminal_singleton.dart',
            'StripeTerminalSingleton',
          );
          _upsertCustomClassFromRepo(
            project,
            repoRoot,
            'stripe_terminal_background_connect.dart',
            'StripeTerminalBackgroundConnect',
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
          'feat(pos): push Stripe Terminal custom-code support files',
      validationFilter: _keepProjectValidatorFinding,
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

String _repoRoot() {
  final scriptPath = File(Platform.script.toFilePath()).resolveSymbolicLinksSync();
  final dslDir = File(scriptPath).parent.path;
  return File(dslDir).parent.parent.path;
}

void _upsertCustomClassFromRepo(
  FFProject project,
  String repoRoot,
  String fileName,
  String className,
) {
  final code = readGeneratedCustomClassBody(fileName);
  if (findCustomClass(project, name: className) == null) {
    addCustomClass(project, name: className, code: code);
  } else {
    updateCustomClass(project, name: className, code: code);
  }
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
  var findOrCreate = false;
  var allowNewProject = false;
  var dryRun = false;
  String? commitMessage;

  for (var i = 0; i < args.length; i++) {
    final arg = args[i];
    switch (arg) {
      case '--api-key':
        apiKey = args[++i];
      case '--base-url':
        baseUrl = args[++i];
      case '--project-name':
        projectName = args[++i];
      case '--project-id':
        projectId = args[++i];
      case '--find-or-create':
        findOrCreate = true;
      case '--allow-new-project':
        allowNewProject = true;
      case '--dry-run':
        dryRun = true;
      case '--commit-message':
        commitMessage = args[++i];
    }
  }

  return _CliOptions(
    apiKey: apiKey ?? Platform.environment['FLUTTERFLOW_AI_API_KEY'] ?? Platform.environment['FF_API_KEY'],
    baseUrl: baseUrl ?? Platform.environment['FLUTTERFLOW_AI_BASE_URL'],
    projectName: projectName ?? Platform.environment['FLUTTERFLOW_POSITIV_PROJECT_NAME'],
    projectId: projectId ?? Platform.environment['FLUTTERFLOW_POSITIV_PROJECT_ID'] ?? 'pointofsale-xrlz5i',
    findOrCreate: findOrCreate,
    allowNewProject: allowNewProject,
    dryRun: dryRun,
    commitMessage: commitMessage,
  );
}
