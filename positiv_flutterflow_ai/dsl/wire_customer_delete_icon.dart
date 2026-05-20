library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';

import 'flutterflow_source_paths.dart';
// ignore: implementation_imports
import 'package:flutterflow_ai/src/helpers/data_type_helpers.dart' show stringType;
// ignore: implementation_imports
import 'package:flutterflow_ai/src/helpers/key_gen.dart' show generateActionKey;

/// Enables the customers list trash icon, adds/updates `deleteConnectedCustomer`,
/// then on success refreshes both customer ListViews (no route replace — avoids
/// disposing sibling tabs' paging controllers) (`pointofsale-xrlz5i`).
///
/// Source: `generated_code/lib/custom_code/actions/delete_connected_customer.dart`
/// (repo root). Run from **positiv_flutterflow_ai** after copying this file to
/// `positiv_flutterflow_ai/dsl/`.
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final sourceFile = _repoCustomActionFile();
  if (!sourceFile.existsSync()) {
    stderr.writeln('Missing source file: ${sourceFile.path}');
    exit(1);
  }
  final code = sourceFile.readAsStringSync();

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          if (findCustomAction(project, name: 'deleteConnectedCustomer') == null) {
            addCustomAction(
              project,
              name: 'deleteConnectedCustomer',
              code: code,
              arguments: _deleteCustomerParameters(project),
              includeContext: true,
              description:
                  'Confirm, archive customer via DELETE /api/customers/{id}, snackbar feedback.',
            );
          } else {
            updateCustomAction(
              project,
              name: 'deleteConnectedCustomer',
              code: code,
              arguments: _deleteCustomerParameters(project),
              description:
                  'Confirm, archive customer via DELETE /api/customers/{id}, snackbar feedback.',
            );
          }
          _wireCustomersDeleteIcon(project);
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
          'feat(pos): wire customer delete (API DELETE + list refresh)',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

File _repoCustomActionFile() {
  final scriptPath = File(Platform.script.toFilePath()).resolveSymbolicLinksSync();
  final dslDir = File(scriptPath).parent.path;
  final repoRoot = File(dslDir).parent.parent.path;
  return File(
    '${positivWorkspaceRoot()}/generated_code/lib/custom_code/actions/delete_connected_customer.dart',
  );
}

FFParameter _parameterFromEditModal(
  FFProject project, {
  required String sourceName,
  required String name,
  required String key,
}) {
  final edit = findCustomAction(project, name: 'editCustomerModal');
  if (edit == null) {
    throw StateError(
      'Custom action editCustomerModal not found; cannot infer $sourceName type.',
    );
  }
  FFParameter? src;
  for (final a in edit.arguments) {
    if (a.identifier.name == sourceName) {
      src = a;
      break;
    }
  }
  if (src == null) {
    throw StateError('editCustomerModal has no "$sourceName" parameter.');
  }
  return FFParameter(
    identifier: FFIdentifier(name: name, key: key),
    dataType: src.dataType.deepCopy(),
  );
}

List<FFParameter> _deleteCustomerParameters(FFProject project) {
  FFIdentifier pid(String name, String key) => FFIdentifier(name: name, key: key);

  return [
    FFParameter(
      identifier: pid('apiBaseUrl', 'delcu_api'),
      dataType: stringType,
    ),
    _parameterFromEditModal(
      project,
      sourceName: 'authToken',
      name: 'authToken',
      key: 'delcu_tok',
    ),
    _parameterFromEditModal(
      project,
      sourceName: 'customer',
      name: 'customer',
      key: 'delcu_cust',
    ),
  ];
}

void _wireCustomersDeleteIcon(FFProject project) {
  final page = findPage(project, name: 'customers');
  if (page == null) {
    throw StateError('FlutterFlow page "customers" not found.');
  }

  final action = findCustomAction(project, name: 'deleteConnectedCustomer');
  if (action == null) {
    throw StateError('Custom action deleteConnectedCustomer not found after add.');
  }

  final keys = {for (final a in action.arguments) a.identifier.name: a.identifier.key};
  final kApi = keys['apiBaseUrl'];
  final kTok = keys['authToken'];
  final kCust = keys['customer'];
  if (kApi == null || kTok == null || kCust == null) {
    throw StateError('Unexpected deleteConnectedCustomer parameters: $keys');
  }

  final apiVar = FFVariable(
    source: FFVariableSource.DEV_ENVIRONMENT,
    baseVariable: FFBaseVariable(
      environmentValue: FFEnvironmentValueVariable(
        identifier: FFIdentifier(name: 'apiHost', key: 'yfmygc'),
      ),
    ),
  );

  final authVar = FFVariable(
    source: FFVariableSource.CUSTOM_AUTH_USER,
    baseVariable: FFBaseVariable(
      auth: FFAuthVariable(property: FFAuthVariable_AuthProperty.AUTH_TOKEN),
    ),
  );

  final customerVar = FFVariable(
    source: FFVariableSource.API_CALL,
    baseVariable: FFBaseVariable(apiCall: FFApiCallVariable()),
    operations: [
      FFVariableOperation(
        apiResponseField: FFApiResponseField(
          responseField: FFApiResponseField_ResponseField.DATA_STRUCT,
        ),
      ),
    ],
    nodeKeyRef: FFNodeKeyReference(key: 'Container_xi31kn6a'),
  );

  final argumentValues = FFFunctionCallValues()
    ..arguments.addAll({
      kApi: FFFunctionCallValues_FFArgument(
        value: FFValue(variable: apiVar),
      ),
      kTok: FFFunctionCallValues_FFArgument(
        value: FFValue(variable: authVar),
      ),
      kCust: FFFunctionCallValues_FFArgument(
        value: FFValue(variable: customerVar),
      ),
    });

  const iconKey = 'Icon_2fkzckc0';

  final deleteCall = FFAction(
    key: generateActionKey(),
    customAction: FFCustomActionCall(
      customActionIdentifier: action.identifier,
      argumentValues: argumentValues,
    ),
  );

  final refreshActiveList = FFAction(
    key: generateActionKey(),
    refreshDatabaseRequest: FFRefreshDatabaseRequest(
      waitForResult: true,
      databaseRequestNodeKeyRef: FFNodeKeyReference(key: 'ListView_suno49fu'),
    ),
  );

  final refreshArchivedList = FFAction(
    key: generateActionKey(),
    refreshDatabaseRequest: FFRefreshDatabaseRequest(
      waitForResult: true,
      databaseRequestNodeKeyRef: FFNodeKeyReference(key: 'ListView_glru9mbj'),
    ),
  );

  final chain = FFActionNode(
    key: generateActionKey(),
    action: deleteCall,
    followUpAction: FFActionNode(
      key: generateActionKey(),
      action: refreshActiveList,
      followUpAction: FFActionNode(
        key: generateActionKey(),
        action: refreshArchivedList,
      ),
    ),
  );

  final ok = updateByKey(page.node, iconKey, (icon) {
    final vis = icon.props.ensureVisibility().ensureVisibleValue();
    vis.clearValue();
    vis.inputValue = true;
    vis.mostRecentInputValue = true;

    icon.triggerActions.clear();
    icon.triggerActions.add(
      FFTriggerActions(
        trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
        rootAction: chain,
      ),
    );
  });

  if (!ok) {
    throw StateError('Icon_2fkzckc0 not found on customers page.');
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
Wire customers trash icon + deleteConnectedCustomer custom action (POSitiv).

Usage:
  dart run dsl/wire_customer_delete_icon.dart [options]

Run from **positiv_flutterflow_ai** (copy from positiv_flutterflow_ai/dsl/).

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

