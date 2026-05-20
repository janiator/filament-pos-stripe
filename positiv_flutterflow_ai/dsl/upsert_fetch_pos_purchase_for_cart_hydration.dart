library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';

import 'flutterflow_custom_action_api_body.dart';
import 'flutterflow_source_paths.dart';

/// Adds or updates `fetchPosPurchaseForCartHydration` from generated_code export into
/// FlutterFlow POSitiv (`pointofsale-xrlz5i`).
///
/// Source: [repo]/generated_code/lib/custom_code/actions/fetch_pos_purchase_for_cart_hydration.dart
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final scriptDir = File(Platform.script.toFilePath()).parent;
  final workspaceRoot = scriptDir.parent;
  final fetchSource = File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/fetch_pos_purchase_for_cart_hydration.dart',
  );
  if (!fetchSource.existsSync()) {
    stderr.writeln('Missing source file: ${fetchSource.path}');
    exit(1);
  }
  final fetchCode = customActionCodeForFlutterFlowApi(fetchSource.readAsStringSync());

  final completeDeferredSource = File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/complete_deferred_payment.dart',
  );
  if (!completeDeferredSource.existsSync()) {
    stderr.writeln('Missing source file: ${completeDeferredSource.path}');
    exit(1);
  }
  final completeDeferredCode =
      customActionCodeForFlutterFlowApi(completeDeferredSource.readAsStringSync());

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          _syncCompleteDeferredPaymentCartJson(project, completeDeferredCode);

          final existing = findCustomAction(
            project,
            name: 'fetchPosPurchaseForCartHydration',
          );
          const description =
              'GET /api/purchases/{id} for parked deferred cart hydration; '
              'returns success, purchase, statusCode.';

          if (existing == null) {
            addCustomAction(
              project,
              name: 'fetchPosPurchaseForCartHydration',
              code: fetchCode,
              description: description,
              arguments: [
                FFParameter(
                  identifier: FFIdentifier(
                    name: 'purchaseId',
                    key: generateRandomAlphaNumericString(),
                  ),
                  dataType: FFDataTypeV2(
                    scalarType: FFBaseDataType.Integer,
                    nonNullable: true,
                  ),
                ),
                FFParameter(
                  identifier: FFIdentifier(
                    name: 'apiBaseUrl',
                    key: generateRandomAlphaNumericString(),
                  ),
                  dataType: FFDataTypeV2(
                    scalarType: FFBaseDataType.String,
                    nonNullable: true,
                  ),
                ),
                FFParameter(
                  identifier: FFIdentifier(
                    name: 'authToken',
                    key: generateRandomAlphaNumericString(),
                  ),
                  dataType: FFDataTypeV2(
                    scalarType: FFBaseDataType.String,
                    nonNullable: true,
                  ),
                ),
              ],
            );
          } else {
            updateCustomAction(
              project,
              name: 'fetchPosPurchaseForCartHydration',
              code: fetchCode,
              description: description,
            );
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
          'feat(pos): fetchPosPurchaseForCartHydration (deferred cart hydration)',
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
Add or update fetchPosPurchaseForCartHydration from generated_code export into FlutterFlow POSitiv.

Also syncs completeDeferredPayment from docs and appends a nullable cartJson parameter
to the custom action metadata when the Dart signature includes cartJson but FlutterFlow
does not yet (fixes project validation after optional cart support).

Usage (from positiv_flutterflow_ai/):
  dart run dsl/upsert_fetch_pos_purchase_for_cart_hydration.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FLUTTERFLOW_AI_API_KEY or FF_API_KEY.
  --project-id <id>         Default: pointofsale-xrlz5i or FLUTTERFLOW_POSITIV_PROJECT_ID.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}

/// Syncs Dart + adds nullable [cartJson] to custom action metadata when missing,
/// then binds empty string for [cartJson] on every call site (FlutterFlow requires
/// each argument to be specified once declared).
void _syncCompleteDeferredPaymentCartJson(FFProject project, String code) {
  final action = findCustomAction(project, name: 'completeDeferredPayment');
  if (action == null) {
    stderr.writeln(
      'Warning: completeDeferredPayment custom action not found; '
      'skipping cartJson sync.',
    );
    return;
  }

  final hasCartJson = action.arguments.any(
    (FFParameter p) => p.identifier.name == 'cartJson',
  );

  if (!hasCartJson) {
    final mergedArgs = List<FFParameter>.from(action.arguments)
      ..add(
        FFParameter(
          identifier: FFIdentifier(
            name: 'cartJson',
            key: generateRandomAlphaNumericString(),
          ),
          dataType: FFDataTypeV2(
            scalarType: FFBaseDataType.String,
            nonNullable: false,
          ),
        ),
      );
    updateCustomAction(
      project,
      name: 'completeDeferredPayment',
      code: code,
      arguments: mergedArgs,
    );
  } else {
    updateCustomAction(project, name: 'completeDeferredPayment', code: code);
  }

  final refreshed = findCustomAction(project, name: 'completeDeferredPayment');
  if (refreshed == null) {
    return;
  }

  String? cartJsonKey;
  for (final p in refreshed.arguments) {
    if (p.identifier.name == 'cartJson') {
      cartJsonKey = p.identifier.key;
      break;
    }
  }
  if (cartJsonKey == null) {
    return;
  }

  _patchCompleteDeferredPaymentCallSitesForCartJson(
    project,
    cartJsonKey: cartJsonKey,
    customActionId: refreshed.identifier,
  );
}

void _patchCompleteDeferredPaymentCallSitesForCartJson(
  FFProject project, {
  required String cartJsonKey,
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
    if (values.arguments.containsKey(cartJsonKey)) {
      continue;
    }

    values.arguments[cartJsonKey] = FFFunctionCallValues_FFArgument(
      value: FFValue(
        inputValue: FFParameterValue(serializedValue: ''),
      ),
    );
  }
}
