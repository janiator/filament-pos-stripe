library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

import 'positiv_flutter_export.dart';

/// Syncs `receiptPrintAfterPosPurchase` custom action and rewires the
/// `checkoutFlow` component action block `receiptPrint` to call it after the
/// existing receipt-id local state update (POSitiv `pointofsale-xrlz5i`).
///
/// Source: `p_o_sitiv/lib/custom_code/actions/receipt_print_after_pos_purchase.dart`
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final sourceFile = positivCustomActionSource(
    'receipt_print_after_pos_purchase.dart',
  );
  if (!sourceFile.existsSync()) {
    stderr.writeln('Missing source file: ${sourceFile.path}');
    stderr.writeln(
      'Set POSITIV_FLUTTER_EXPORT_ROOT to your p_o_sitiv project root, or open the project in FlutterFlow desktop so the export exists.',
    );
    exit(1);
  }
  final code = sourceFile.readAsStringSync();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          final wc = findComponent(project, name: 'checkoutFlow');
          if (wc == null) {
            throw StateError('Widget class checkoutFlow not found in project.');
          }
          final block = findActionBlock(
            project,
            name: 'receiptPrint',
            widgetClass: wc,
          );
          if (block == null) {
            throw StateError(
              'Action block receiptPrint not found on checkoutFlow.',
            );
          }
          final pPurchase = findActionBlockParam(
            project,
            blockName: 'receiptPrint',
            paramName: 'purchaseCompletedJson',
            widgetClass: wc,
          );
          final pManual = findActionBlockParam(
            project,
            blockName: 'receiptPrint',
            paramName: 'manualPrint',
            widgetClass: wc,
          );
          if (pPurchase == null) {
            throw StateError(
              'receiptPrint is missing purchaseCompletedJson parameter.',
            );
          }

          final existing = findCustomAction(
            project,
            name: 'receiptPrintAfterPosPurchase',
          );
          if (existing == null) {
            addCustomAction(
              project,
              name: 'receiptPrintAfterPosPurchase',
              code: code,
              includeContext: true,
              description:
                  'Fetch and print a POS receipt when a printer target exists; '
                  'honors hasAutoPrintReceipt and default printer eposUrl.',
              arguments: [
                FFParameter(
                  identifier: FFIdentifier(
                    name: 'purchaseCompletedJson',
                    key: generateRandomAlphaNumericString(),
                  ),
                  dataType: FFDataTypeV2(
                    scalarType: FFBaseDataType.JSON,
                    nonNullable: true,
                  ),
                ),
                FFParameter(
                  identifier: FFIdentifier(
                    name: 'manualPrint',
                    key: generateRandomAlphaNumericString(),
                  ),
                  dataType: FFDataTypeV2(
                    scalarType: FFBaseDataType.Boolean,
                    nonNullable: false,
                  ),
                ),
              ],
            );
          } else {
            updateCustomAction(
              project,
              name: 'receiptPrintAfterPosPurchase',
              code: code,
            );
          }

          final ca = findCustomAction(
            project,
            name: 'receiptPrintAfterPosPurchase',
          );
          if (ca == null) {
            throw StateError(
              'Custom action receiptPrintAfterPosPurchase not found after upsert.',
            );
          }
          final caPurchase = _paramByName(ca, 'purchaseCompletedJson');
          final caManual = _paramByName(ca, 'manualPrint');
          if (caPurchase == null || caManual == null) {
            throw StateError(
              'Custom action receiptPrintAfterPosPurchase must declare '
              'purchaseCompletedJson and manualPrint arguments. '
              'Remove the action in FlutterFlow Custom Code and re-run this script.',
            );
          }

          final oldRoot = block.actions.rootAction;
          if (!oldRoot.hasAction() || !oldRoot.action.hasLocalStateUpdate()) {
            throw StateError(
              'receiptPrint root must be a local state update (unexpected shape).',
            );
          }

          final rootClone = oldRoot.deepCopy()..clearFollowUpAction();

          final blockKey = block.identifier.key;
          final argValues = FFFunctionCallValues();

          final vPurchase = varFromActionBlockParam(pPurchase.identifier);
          vPurchase.actionComponentKeyRef = FFActionComponentKeyReference(
            key: blockKey,
          );
          argValues.arguments[caPurchase.identifier.key] =
              FFFunctionCallValues_FFArgument(
                value: FFValue(variable: vPurchase),
              );

          if (pManual != null) {
            final vManual = varFromActionBlockParam(pManual.identifier);
            vManual.actionComponentKeyRef = FFActionComponentKeyReference(
              key: blockKey,
            );
            argValues.arguments[caManual.identifier.key] =
                FFFunctionCallValues_FFArgument(
                  value: FFValue(variable: vManual),
                );
          } else {
            argValues.arguments[caManual.identifier.key] =
                FFFunctionCallValues_FFArgument(
                  value: FFValue(
                    variable: varFromConstant(
                      FFConstantsVariable_ConstantValue.NULL,
                    ),
                  ),
                );
          }

          final customCall = FFAction(
            key: generateActionKey(),
            customAction: FFCustomActionCall(
              customActionIdentifier: ca.identifier,
              argumentValues: argValues,
            ),
          );

          rootClone.followUpAction = FFActionNode(
            key: generateActionKey(),
            action: customCall,
          );

          updateActionBlock(
            project,
            name: 'receiptPrint',
            widgetClass: wc,
            rootAction: rootClone,
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
          'fix(pos): receipt print after purchase (auto-print + eposUrl)',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

FFParameter? _paramByName(FFCustomAction action, String name) {
  for (final p in action.arguments) {
    if (p.identifier.name == name) {
      return p;
    }
  }
  return null;
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
Sync receipt print custom action + checkoutFlow receiptPrint action block.

Usage:
  dart run dsl/sync_checkout_receipt_print.dart [options]

From the **positiv_flutterflow_ai** workspace (see .cursor/rules/multi-repo-workspace.mdc):
copy scripts from docs/flutterflow/dsl/ after \`flutterflow ai init\` (never name the workspace \`flutterflow_ai\` — pubspec name clash).

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FLUTTERFLOW_AI_API_KEY or FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-name <name>     Create a new project with this name.
  --project-id <id>         Push into an existing project by ID (default: pointofsale-xrlz5i or FLUTTERFLOW_POSITIV_PROJECT_ID).
  --find-or-create          Retry by reusing a same-name project before creating.
  --allow-new-project       Bypass the workspace binding guard and create a different project.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Compile and validate without pushing.
  --help, -h                Show this help.
''');
}
