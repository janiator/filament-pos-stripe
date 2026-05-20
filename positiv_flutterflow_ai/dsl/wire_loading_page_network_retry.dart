library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

import 'flutterflow_custom_action_api_body.dart';
import 'flutterflow_source_paths.dart';

Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final repoRoot = _repoRoot();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          _upsertCustomActionFromRepo(
            project,
            repoRoot,
            'wait_for_network_connection.dart',
            'waitForNetworkConnection',
            'Blocks until API host (or fallback DNS) is reachable.',
          );
          _upsertEnforceMeProfileAuthLogout(project, repoRoot);
          if (!_isLoadingPageAlreadyWired(project)) {
            _wireLoadingPageNetworkRetry(project);
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
          'fix(pos): loadingPage retry until online; auth-only logout',
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

void _upsertCustomActionFromRepo(
  FFProject project,
  String repoRoot,
  String fileName,
  String actionName,
  String description, {
  List<FFParameter> arguments = const [],
  bool includeContext = false,
}) {
  final sourceFile = File('${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/$fileName');
  if (!sourceFile.existsSync()) {
    throw StateError('Missing source file: ${sourceFile.path}');
  }
  final code = customActionCodeForFlutterFlowApi(sourceFile.readAsStringSync());
  if (findCustomAction(project, name: actionName) == null) {
    addCustomAction(
      project,
      name: actionName,
      code: code,
      arguments: arguments,
      includeContext: includeContext,
      description: description,
    );
  } else {
    updateCustomAction(
      project,
      name: actionName,
      code: code,
      arguments: arguments,
      includeContext: includeContext,
      description: description,
    );
  }
}

void _upsertEnforceMeProfileAuthLogout(FFProject project, String repoRoot) {
  _upsertCustomActionFromRepo(
    project,
    repoRoot,
    'enforce_me_profile_auth_logout.dart',
    'enforceMeProfileAuthLogout',
    'Logout and go to login when Me Profile status is 401/403.',
    includeContext: true,
    arguments: [
      FFParameter(
        identifier: FFIdentifier(name: 'statusCode', key: 'eml_status'),
        dataType: FFDataTypeV2(scalarType: FFBaseDataType.Integer),
      ),
    ],
  );
}

bool _isLoadingPageAlreadyWired(FFProject project) {
  final page = findPage(project, name: 'loadingPage');
  if (page == null) {
    return false;
  }
  final trigger = findTrigger(page.node, FFActionTriggerType.ON_INIT_STATE);
  if (trigger == null) {
    return false;
  }
  final root = trigger.rootAction;
  if (!root.hasLoopAction() || !root.loopAction.hasAction()) {
    return false;
  }
  final first = root.loopAction.action;
  return first.hasAction() &&
      first.action.hasCustomAction() &&
      first.action.customAction.customActionIdentifier.name ==
          'waitForNetworkConnection';
}

void _wireLoadingPageNetworkRetry(FFProject project) {
  final page = findPage(project, name: 'loadingPage');
  if (page == null) {
    throw StateError('FlutterFlow page "loadingPage" not found.');
  }

  final trigger = findTrigger(page.node, FFActionTriggerType.ON_INIT_STATE);
  if (trigger == null) {
    throw StateError('loadingPage has no ON_INIT_STATE trigger actions.');
  }

  final root = trigger.rootAction;
  if (root.hasLoopAction()) {
    return;
  }

  final bootstrapNode = root.hasFollowUpAction() ? root.followUpAction : null;
  if (bootstrapNode == null) {
    throw StateError('Expected bootstrap followUp on loadingPage root.');
  }
  root.clearFollowUpAction();

  final meProfileNode = _findMeProfileActionNode(root);
  if (meProfileNode == null || !meProfileNode.hasFollowUpAction()) {
    throw StateError('Me Profile call or follow-up condition not found.');
  }

  final meProfileActionKey = meProfileNode.key;
  final meProfileCondNode = meProfileNode.followUpAction;
  if (!meProfileCondNode.hasConditionActions()) {
    throw StateError('Expected meProfile succeeded conditional.');
  }

  meProfileCondNode.conditionActions.falseAction = FFActionNode(
    key: generateActionKey(),
    action: Actions.wait(2000),
  );

  final enforceCall = _buildEnforceLogoutCall(project, meProfileActionKey);
  meProfileNode.followUpAction = FFActionNode(
    key: generateActionKey(),
    action: enforceCall,
    followUpAction: meProfileCondNode,
  );

  if (root.hasConditionActions()) {
    root.conditionActions.falseAction = _authFailureChain();
  }

  root.followUpAction = Actions.conditional(
    condition: _meProfileSucceededVar(meProfileActionKey),
    trueActions: _appendLoopBreak(bootstrapNode),
  );

  final waitAction = findCustomAction(project, name: 'waitForNetworkConnection');
  if (waitAction == null) {
    throw StateError('waitForNetworkConnection missing after upsert.');
  }

  trigger.rootAction = Actions.loop(
    condition: varFromConstant(FFConstantsVariable_ConstantValue.TRUE),
    body: FFActionNode(
      key: generateActionKey(),
      action: FFAction(
        key: generateActionKey(),
        customAction: FFCustomActionCall(
          customActionIdentifier: waitAction.identifier,
        ),
      ),
      followUpAction: root,
    ),
  );
}

FFAction _buildEnforceLogoutCall(FFProject project, String meProfileActionKey) {
  final actionDef = findCustomAction(project, name: 'enforceMeProfileAuthLogout');
  if (actionDef == null) {
    throw StateError('enforceMeProfileAuthLogout missing after upsert.');
  }

  final statusParamKey = actionDef.arguments
      .firstWhere((a) => a.identifier.name == 'statusCode')
      .identifier
      .key;

  return FFAction(
    key: generateActionKey(),
    customAction: FFCustomActionCall(
      customActionIdentifier: actionDef.identifier,
      argumentValues: FFFunctionCallValues()
        ..arguments.addAll({
          statusParamKey: FFFunctionCallValues_FFArgument(
            value: FFValue(
              variable: _meProfileStatusCodeVar(meProfileActionKey),
            ),
          ),
        }),
    ),
  );
}

FFActionNode? _findMeProfileActionNode(FFActionNode root) {
  FFActionNode? walk(FFActionNode node) {
    if (node.hasAction() &&
        node.action.hasDatabase() &&
        node.action.database.apiCall.endpointIdentifier.name == 'Me Profile') {
      return node;
    }

    if (node.hasConditionActions()) {
      final cond = node.conditionActions;
      for (final branch in cond.trueActions) {
        if (branch.hasTrueAction()) {
          final found = walk(branch.trueAction);
          if (found != null) {
            return found;
          }
        }
      }
      if (cond.hasFalseAction()) {
        final found = walk(cond.falseAction);
        if (found != null) {
          return found;
        }
      }
    }

    if (node.hasLoopAction() && node.loopAction.hasAction()) {
      final found = walk(node.loopAction.action);
      if (found != null) {
        return found;
      }
    }

    if (node.hasFollowUpAction()) {
      return walk(node.followUpAction);
    }

    return null;
  }

  return walk(root);
}

FFActionNode _authFailureChain() {
  return FFActionNode(
    key: generateActionKey(),
    action: Actions.logout(),
    followUpAction: Actions.loopBreakNode(),
  );
}

FFActionNode _appendLoopBreak(FFActionNode head) {
  var node = head;
  while (node.hasFollowUpAction()) {
    node = node.followUpAction;
  }
  node.followUpAction = Actions.loopBreakNode();
  return head;
}

FFVariable _meProfileStatusCodeVar(String actionKey) => FFVariable(
  source: FFVariableSource.ACTION_OUTPUTS,
  baseVariable: FFBaseVariable(
    actionOutput: FFActionOutputVariable(
      actionKeyRef: FFActionKeyReference(key: actionKey),
      outputVariableIdentifier: FFIdentifier(name: 'meprofile'),
    ),
  ),
  operations: [
    FFVariableOperation(
      apiResponseField: FFApiResponseField(
        responseField: FFApiResponseField_ResponseField.STATUS_CODE,
      ),
    ),
  ],
  nodeKeyRef: FFNodeKeyReference(key: 'Scaffold_58hncaks'),
);

FFVariable _meProfileSucceededVar(String actionKey) => FFVariable(
  source: FFVariableSource.ACTION_OUTPUTS,
  baseVariable: FFBaseVariable(
    actionOutput: FFActionOutputVariable(
      actionKeyRef: FFActionKeyReference(key: actionKey),
      outputVariableIdentifier: FFIdentifier(name: 'meprofile'),
    ),
  ),
  operations: [
    FFVariableOperation(
      apiResponseField: FFApiResponseField(
        responseField: FFApiResponseField_ResponseField.SUCCEEDED,
      ),
    ),
  ],
  nodeKeyRef: FFNodeKeyReference(key: 'Scaffold_58hncaks'),
);

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
    projectId: projectId ?? Platform.environment['FLUTTERFLOW_POSITIV_PROJECT_ID'],
    findOrCreate: findOrCreate,
    allowNewProject: allowNewProject,
    dryRun: dryRun,
    commitMessage: commitMessage,
  );
}

String _requireValue(List<String> args, int index, String flag) {
  if (index >= args.length) {
    stderr.writeln('Missing value for $flag');
    exit(64);
  }
  return args[index];
}

void _printUsage() {
  stderr.writeln('''
Usage: dart run dsl/wire_loading_page_network_retry.dart [options]
''');
}
