library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/action_block_helpers.dart'
    as action_blocks;

/// Ensures every `completePosPurchase` custom-action call includes the
/// `terminalPaymentResult` argument (constant NULL when no terminal payload).
///
/// FlutterFlow AI project validation fails with "terminalPaymentResult is not
/// specified" when the parameter was added to the custom action but older
/// widget/action-block chains never bound it.
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          final completePos = findCustomAction(
            project,
            name: 'completePosPurchase',
          );
          if (completePos == null) {
            throw StateError('completePosPurchase custom action not found.');
          }
          final terminalKey = _argKeyOrNull(completePos, 'terminalPaymentResult');
          if (terminalKey == null) {
            stderr.writeln(
              '[fix_complete_pos_purchase_terminal_bindings] '
              'completePosPurchase has no terminalPaymentResult parameter; skip.',
            );
            return;
          }

          var patched = 0;
          for (final wc in project.widgetClasses.values) {
            patched += _patchWidgetClassTriggers(
              wc.node,
              terminalArgKey: terminalKey,
            );
            for (final block in action_blocks.listActionBlocks(
              project,
              widgetClass: wc,
            )) {
              patched += _visitActionNode(
                block.actions.rootAction,
                terminalArgKey: terminalKey,
              );
            }
          }
          for (final block in action_blocks.listActionBlocks(project)) {
            patched += _visitActionNode(
              block.actions.rootAction,
              terminalArgKey: terminalKey,
            );
          }

          stdout.writeln(
            '[fix_complete_pos_purchase_terminal_bindings] '
            'Patched $patched completePosPurchase call(s).',
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
          'fix(pos): bind terminalPaymentResult on all completePosPurchase calls',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

String? _argKeyOrNull(FFCustomAction action, String name) {
  for (final p in action.arguments) {
    if (p.identifier.name == name) {
      return p.identifier.key;
    }
  }
  return null;
}

FFFunctionCallValues_FFArgument _nullTerminalArg() =>
    FFFunctionCallValues_FFArgument(
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

int _patchWidgetClassTriggers(FFNode root, {required String terminalArgKey}) {
  var n = 0;
  void walk(FFNode node) {
    for (final ta in node.triggerActions) {
      if (ta.hasRootAction()) {
        n += _visitActionNode(ta.rootAction, terminalArgKey: terminalArgKey);
      }
    }
    for (final c in node.children) {
      walk(c);
    }
  }

  walk(root);
  return n;
}

int _visitActionNode(
  FFActionNode node, {
  required String terminalArgKey,
}) {
  var n = _patchIfCompletePos(node, terminalArgKey: terminalArgKey);

  if (node.hasConditionActions()) {
    final cond = node.conditionActions;
    for (final t in cond.trueActions) {
      if (t.hasTrueAction()) {
        n += _visitActionNode(t.trueAction, terminalArgKey: terminalArgKey);
      }
    }
    if (cond.hasFalseAction()) {
      n += _visitActionNode(cond.falseAction, terminalArgKey: terminalArgKey);
    }
  }
  if (node.hasLoopAction() && node.loopAction.hasAction()) {
    n += _visitActionNode(node.loopAction.action, terminalArgKey: terminalArgKey);
  }
  if (node.hasParallelActions()) {
    for (final branch in node.parallelActions.actions) {
      n += _visitActionNode(branch, terminalArgKey: terminalArgKey);
    }
  }
  if (node.hasFollowUpAction()) {
    n += _visitActionNode(node.followUpAction, terminalArgKey: terminalArgKey);
  }
  return n;
}

int _patchIfCompletePos(FFActionNode node, {required String terminalArgKey}) {
  if (!node.hasAction()) {
    return 0;
  }
  final a = node.action;
  if (a.whichAction() != FFAction_Action.customAction) {
    return 0;
  }
  final call = a.customAction;
  if (call.customActionIdentifier.name != 'completePosPurchase') {
    return 0;
  }
  final args = call.argumentValues;
  if (args.arguments.containsKey(terminalArgKey)) {
    return 0;
  }
  args.arguments[terminalArgKey] = _nullTerminalArg();
  return 1;
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
Patch missing terminalPaymentResult on completePosPurchase calls.

Usage (from positiv_flutterflow_ai/):
  dart run dsl/fix_complete_pos_purchase_terminal_bindings.dart [options]

Options:
  --api-key <key>           FlutterFlow API key.
  --project-id <id>         Default: pointofsale-xrlz5i.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
