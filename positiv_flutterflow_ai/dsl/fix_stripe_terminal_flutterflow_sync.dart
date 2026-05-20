library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

import 'flutterflow_custom_action_api_body.dart';
import 'flutterflow_source_paths.dart';

/// Re-sync Stripe Terminal widget + modal from repo docs (body-only, no Supabase).
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final repoRoot = _repoRoot();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          _updateStripeTerminalWidget(project, repoRoot);
          _updateStripeTerminalModal(project, repoRoot);
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
          'fix(pos): stripe terminal widget/modal sync (no supabase, location-first modal)',
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

void _updateStripeTerminalWidget(FFProject project, String repoRoot) {
  final sourceFile = File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/widgets/stripe_internet_terminal_reader_picker_and_connector.dart',
  );
  if (!sourceFile.existsSync()) {
    throw StateError('Missing widget source: ${sourceFile.path}');
  }

  updateCustomWidget(
    project,
    name: 'StripeInternetTerminalReaderPickerAndConnector',
    code: customWidgetCodeForFlutterFlowApi(sourceFile.readAsStringSync()),
  );
}

void _updateStripeTerminalModal(FFProject project, String repoRoot) {
  final sourceFile = File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/stripe_terminal_selector_modal.dart',
  );
  if (!sourceFile.existsSync()) {
    throw StateError('Missing modal source: ${sourceFile.path}');
  }

  if (findCustomAction(project, name: 'stripeTerminalSelectorModal') == null) {
    throw StateError('stripeTerminalSelectorModal not found in project.');
  }

  updateCustomAction(
    project,
    name: 'stripeTerminalSelectorModal',
    code: customActionCodeForFlutterFlowApi(sourceFile.readAsStringSync()),
    description:
        'Location-first terminal selector modal with auto-connect to last reader.',
  );
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
