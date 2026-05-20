library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

import 'flutterflow_custom_action_api_body.dart';
import 'flutterflow_source_paths.dart';

/// Background terminal reconnect on loadingPage + offline recovery monitor.
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final repoRoot = _repoRoot();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          _upsertConnectOrShowSelector(project, repoRoot);
          _updateTerminalWidget(project, repoRoot);
          _updatePeriodicAction(project, repoRoot, 'startPosPeriodicActions');
          _updatePeriodicAction(project, repoRoot, 'stopPosPeriodicActions');
          _updatePeriodicAction(project, repoRoot, 'updatePosPeriodicActionsToken');
          _updatePeriodicAction(project, repoRoot, 'createAndProcessTerminalPayment');
          _replaceTerminalSelectorCalls(project);
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
          'feat(pos): background terminal reconnect + offline recovery',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

String _repoRoot() {
  final scriptPath = File(Platform.script.toFilePath()).resolveSymbolicLinksSync();
  return File(File(scriptPath).parent.path).parent.parent.path;
}

void _upsertConnectOrShowSelector(FFProject project, String repoRoot) {
  final sourceFile = File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/connect_stripe_terminal_or_show_selector.dart',
  );
  if (!sourceFile.existsSync()) {
    throw StateError('Missing source file: ${sourceFile.path}');
  }

  final code = customActionCodeForFlutterFlowApi(sourceFile.readAsStringSync());
  final modal = findCustomAction(project, name: 'stripeTerminalSelectorModal');
  if (modal == null) {
    throw StateError('stripeTerminalSelectorModal not found in project.');
  }

  final args = modal.arguments
      .map((a) => a.deepCopy())
      .toList(growable: false);

  final existing = findCustomAction(project, name: 'connectStripeTerminalOrShowSelector');
  if (existing == null) {
    addCustomAction(
      project,
      name: 'connectStripeTerminalOrShowSelector',
      code: code,
      includeContext: true,
      description:
          'Try last-used terminal in background; show selector modal only on failure.',
      arguments: args,
    );
  } else {
    updateCustomAction(
      project,
      name: 'connectStripeTerminalOrShowSelector',
      code: code,
      description:
          'Try last-used terminal in background; show selector modal only on failure.',
    );
  }
}

void _updateTerminalWidget(FFProject project, String repoRoot) {
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

void _updatePeriodicAction(FFProject project, String repoRoot, String name) {
  final fileName = _actionFileName(name);
  final sourceFile = File('${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/$fileName');
  if (!sourceFile.existsSync()) {
    throw StateError('Missing action source: ${sourceFile.path}');
  }

  if (findCustomAction(project, name: name) == null) {
    throw StateError('Custom action $name not found in project.');
  }

  updateCustomAction(
    project,
    name: name,
    code: customActionCodeForFlutterFlowApi(sourceFile.readAsStringSync()),
  );
}

String _actionFileName(String actionName) {
  return switch (actionName) {
    'startPosPeriodicActions' => 'start_pos_periodic_actions.dart',
    'stopPosPeriodicActions' => 'stop_pos_periodic_actions.dart',
    'updatePosPeriodicActionsToken' => 'update_pos_periodic_actions_token.dart',
    'createAndProcessTerminalPayment' => 'create_and_process_terminal_payment.dart',
    _ => throw ArgumentError('Unknown action: $actionName'),
  };
}

void _replaceTerminalSelectorCalls(FFProject project) {
  final modal = findCustomAction(project, name: 'stripeTerminalSelectorModal');
  final connect = findCustomAction(project, name: 'connectStripeTerminalOrShowSelector');
  if (modal == null || connect == null) {
    throw StateError('Terminal custom actions missing after upsert.');
  }

  var replaced = 0;

  final loadingPage = findPage(project, name: 'loadingPage');
  if (loadingPage != null) {
    final trigger = findTrigger(loadingPage.node, FFActionTriggerType.ON_INIT_STATE);
    if (trigger != null) {
      replaced += _replaceInActionNode(
        trigger.rootAction,
        from: modal.identifier,
        to: connect.identifier,
      );
    }
  }

  final block = findActionBlock(project, name: 'stripeTerminalPickerConditional');
  if (block != null) {
    replaced += _replaceInActionNode(
      block.actions.rootAction,
      from: modal.identifier,
      to: connect.identifier,
    );
  }

  if (replaced == 0) {
    stderr.writeln(
      'Warning: no stripeTerminalSelectorModal calls replaced — '
      'update loadingPage / stripeTerminalPickerConditional manually.',
    );
  }
}

int _replaceInActionNode(
  FFActionNode node,
  {required FFIdentifier from,
  required FFIdentifier to,
}) {
  var count = 0;

  void walk(FFActionNode current) {
    if (current.hasAction() && current.action.hasCustomAction()) {
      final call = current.action.customAction;
      if (call.customActionIdentifier.name == from.name) {
        call.customActionIdentifier = to.clone();
        count++;
      }
    }

    if (current.hasFollowUpAction()) {
      walk(current.followUpAction);
    }

    if (current.hasConditionActions()) {
      final cond = current.conditionActions;
      for (final branch in cond.trueActions) {
        if (branch.hasTrueAction()) {
          walk(branch.trueAction);
        }
      }
      if (cond.hasFalseAction()) {
        walk(cond.falseAction);
      }
    }

    if (current.hasLoopAction() && current.loopAction.hasAction()) {
      walk(current.loopAction.action);
    }
  }

  walk(node);
  return count;
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
