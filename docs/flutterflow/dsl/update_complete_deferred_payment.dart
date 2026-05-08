library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';

import 'flutterflow_custom_action_api_body.dart';

const _kCompleteDeferredJsonReturnName = 'completeDeferredPaymentJson';

/// Syncs `completeDeferredPayment` custom action from pos-stripe repo docs into
/// FlutterFlow POSitiv (`pointofsale-xrlz5i`).
///
/// Source: [repo]/docs/flutterflow/custom-actions/complete_deferred_payment.dart
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final scriptDir = File(Platform.script.toFilePath()).parent;
  final repoRoot = scriptDir.parent.parent;
  final sourceFile = File(
    '${repoRoot.path}/docs/flutterflow/custom-actions/complete_deferred_payment.dart',
  );
  if (!sourceFile.existsSync()) {
    stderr.writeln('Missing source file: ${sourceFile.path}');
    exit(1);
  }
  final code = customActionCodeForFlutterFlowApi(sourceFile.readAsStringSync());

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          updateCustomAction(
            project,
            name: 'completeDeferredPayment',
            code: code,
            description:
                'Complete deferred (pickup) payment; optional cartJson for parked/edited orders. '
                'Sends pos_device_id / pos_session_id from FFAppState for compliance. '
                'On success: clears cart, client receipt print when configured, bumps cacheRefreshKey; '
                'returns JSON (receiptId, data, ...) for FlutterFlow bindings.',
          );
          _ensureCompleteDeferredPaymentDeclaresJsonReturn(project);
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
          'fix(pos): serialize edited deferred cart on completion',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

/// FlutterFlow R1 validates `$.receiptId` / `$.data.*` bindings only when the
/// action declares a JSON return.
void _ensureCompleteDeferredPaymentDeclaresJsonReturn(FFProject project) {
  final action = findCustomAction(project, name: 'completeDeferredPayment');
  if (action == null) {
    return;
  }
  if (action.hasReturnParameter()) {
    if (action.returnParameter.dataType.scalarType == FFBaseDataType.JSON) {
      return;
    }
  }

  updateCustomAction(
    project,
    name: 'completeDeferredPayment',
    returnParameter: FFParameter(
      identifier: FFIdentifier(
        name: _kCompleteDeferredJsonReturnName,
        key: generateRandomAlphaNumericString(),
      ),
      dataType: FFDataTypeV2(
        scalarType: FFBaseDataType.JSON,
        nonNullable: false,
      ),
      description:
          'JSON map from completeDeferredPayment (success, receiptId, data, ...).',
    ),
  );
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
Push completeDeferredPayment from pos-stripe docs into FlutterFlow POSitiv.

Usage (from positiv_flutterflow_ai/):
  dart run dsl/update_complete_deferred_payment.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FLUTTERFLOW_AI_API_KEY or FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-id <id>         Default: pointofsale-xrlz5i or FLUTTERFLOW_POSITIV_PROJECT_ID.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
