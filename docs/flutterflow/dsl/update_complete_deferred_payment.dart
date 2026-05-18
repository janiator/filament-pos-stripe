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
          _syncCompleteDeferredPaymentEstimatedPickupDate(project, code);
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

/// Adds nullable [estimatedPickupDate], syncs Dart, and binds NULL on call sites
/// that omit it (FlutterFlow requires every argument once declared).
void _syncCompleteDeferredPaymentEstimatedPickupDate(
  FFProject project,
  String code,
) {
  final action = findCustomAction(project, name: 'completeDeferredPayment');
  if (action == null) {
    stderr.writeln('Warning: completeDeferredPayment not found.');
    return;
  }

  const description =
      'Complete deferred pickup payment (cash/card/…) or revise lines when staff picks deferred again '
      '(POST …/revise-deferred when no payment_intent_id). Optional cartJson + estimatedPickupDate. '
      'Sends pos_device_id / pos_session_id from FFAppState. On success: client receipt print (sales or '
      'revised delivery), bumps cacheRefreshKey.';

  final hasPickup = action.arguments.any(
    (FFParameter p) => p.identifier.name == 'estimatedPickupDate',
  );

  if (!hasPickup) {
    final mergedArgs = List<FFParameter>.from(action.arguments)
      ..add(
        FFParameter(
          identifier: FFIdentifier(
            name: 'estimatedPickupDate',
            key: generateRandomAlphaNumericString(),
          ),
          dataType: FFDataTypeV2(
            scalarType: FFBaseDataType.DateTime,
            nonNullable: false,
          ),
          description:
              'Estimated pickup date for deferred revise (same as completePosPurchase).',
        ),
      );
    updateCustomAction(
      project,
      name: 'completeDeferredPayment',
      code: code,
      arguments: mergedArgs,
      description: description,
    );
  } else {
    updateCustomAction(
      project,
      name: 'completeDeferredPayment',
      code: code,
      description: description,
    );
  }

  final refreshed = findCustomAction(project, name: 'completeDeferredPayment');
  if (refreshed == null) {
    return;
  }

  String? pickupKey;
  for (final p in refreshed.arguments) {
    if (p.identifier.name == 'estimatedPickupDate') {
      pickupKey = p.identifier.key;
      break;
    }
  }
  if (pickupKey == null) {
    return;
  }

  _patchCompleteDeferredPaymentCallSitesForEstimatedPickupDate(
    project,
    pickupKey: pickupKey,
    customActionId: refreshed.identifier,
  );
}

void _patchCompleteDeferredPaymentCallSitesForEstimatedPickupDate(
  FFProject project, {
  required String pickupKey,
  required FFIdentifier customActionId,
}) {
  final actions = allProtosOfType<FFAction>(
    project,
    recurseOnNodes: true,
    recurseOnVariables: true,
  );

  for (final ffAction in actions) {
    if (ffAction.whichAction() != FFAction_Action.customAction) {
      continue;
    }
    final call = ffAction.customAction;
    final id = call.customActionIdentifier;
    final matches =
        id.name == customActionId.name && id.key == customActionId.key;
    if (!matches) {
      continue;
    }

    final values = call.ensureArgumentValues();
    if (values.arguments.containsKey(pickupKey)) {
      continue;
    }

    values.arguments[pickupKey] = FFFunctionCallValues_FFArgument(
      value: FFValue(
        variable: FFVariable(
          source: FFVariableSource.CONSTANTS,
          baseVariable: FFBaseVariable(
            constants: FFConstantsVariable(
              value: FFConstantsVariable_ConstantValue.NULL,
            ),
          ),
        ),
      ),
    );
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
