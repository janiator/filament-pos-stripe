library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show addChild, findDescendants;

/// Adds **pos** page widget state, a **top banner** (visible when a deferred
/// resume session is active), and prepends **On Page Load** →
/// **`getDeferredResumeContext`** → **page state** updates for
/// **`deferredResumeBannerText`** / **`deferredResumeBannerActive`**.
///
/// Idempotent: safe to re-run after FlutterFlow edits (stable widget **name**
/// `deferredResumeBannerHost`, state field **names**, first **ON_INIT** chain
/// detection).
///
/// Run from `positiv_flutterflow_ai/`:
///   dart run dsl/wire_pos_deferred_resume_banner.dart --commit-message "…"
///
/// Prerequisites: `getDeferredResumeContext` (see `upsert_deferred_resume_helpers.dart`).
/// If FlutterFlow regenerates **node keys**, update `_kPosStackKey` / `_kPosScaffoldKey`
/// (re-inspect page **pos**).

const _kPosPageName = 'pos';
const _kPosScaffoldKey = 'Scaffold_6umjp4qm';
const _kPosStackKey = 'Stack_7r3u7h1a';

const _kBannerHostName = 'deferredResumeBannerHost';
const _kBannerTextStateName = 'deferredResumeBannerText';
const _kBannerActiveStateName = 'deferredResumeBannerActive';

/// Must match [FFAction.outputVariableName] and the custom action JSON return
/// id (FlutterFlow validates JSON-path bindings against a declared JSON return).
const _kDeferredResumeCtxOutput = 'deferredResumeContext';

Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  try {
    await flutterFlowAI(
      (app) {
        app.raw(_wire);
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
          'feat(pos): deferred resume banner + getDeferredResumeContext on load',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void _wire(FFProject project) {
  final getCtx = findCustomAction(project, name: 'getDeferredResumeContext');
  if (getCtx == null) {
    throw StateError(
      'Custom action getDeferredResumeContext not found. '
      'Run: dart run dsl/upsert_deferred_resume_helpers.dart',
    );
  }

  _ensureGetDeferredResumeContextDeclaresJsonReturn(project);

  final pos = findPage(project, name: _kPosPageName);
  if (pos == null) {
    throw StateError('Page "$_kPosPageName" not found.');
  }

  final scaffold = pos.node;
  if (scaffold.key != _kPosScaffoldKey) {
    stderr.writeln(
      '[wire_pos_deferred_resume_banner] Warning: pos root key is '
      '"${scaffold.key}" (expected $_kPosScaffoldKey). '
      'Variables still target the actual scaffold key.',
    );
  }

  final stack = findByKey(scaffold, _kPosStackKey);
  if (stack == null) {
    throw StateError(
      'Stack $_kPosStackKey not found under pos scaffold. Re-inspect and update '
      '_kPosStackKey.',
    );
  }

  final textFieldId = _ensurePageStateField(
    pos,
    name: _kBannerTextStateName,
    dataType: FFDataTypeV2(
      scalarType: FFBaseDataType.String,
      nonNullable: true,
    ),
    defaultSerialized: '',
    description: 'Banner line for parked deferred resume (DSL).',
  );
  final activeFieldId = _ensurePageStateField(
    pos,
    name: _kBannerActiveStateName,
    dataType: FFDataTypeV2(
      scalarType: FFBaseDataType.Boolean,
      nonNullable: true,
    ),
    defaultSerialized: 'false',
    description: 'True when deferred resume prefs are active (DSL).',
  );

  _ensureBannerHost(
    scaffoldKey: scaffold.key,
    stack: stack,
    textFieldId: textFieldId,
    activeFieldId: activeFieldId,
  );

  _ensureDeferredResumeOnInitTrigger(
    scaffold: scaffold,
    getDeferredAction: getCtx,
    textFieldId: textFieldId,
    activeFieldId: activeFieldId,
  );
}

/// FlutterFlow R1 rejects binding JSON-path values into page state unless the
/// custom action declares a **JSON** return type.
void _ensureGetDeferredResumeContextDeclaresJsonReturn(FFProject project) {
  final action = findCustomAction(project, name: 'getDeferredResumeContext');
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
    name: 'getDeferredResumeContext',
    returnParameter: FFParameter(
      identifier: FFIdentifier(
        name: _kDeferredResumeCtxOutput,
        key: generateRandomAlphaNumericString(),
      ),
      dataType: FFDataTypeV2(
        scalarType: FFBaseDataType.JSON,
        nonNullable: false,
      ),
      description:
          'JSON map from getDeferredResumeContext (active, bannerText, …).',
    ),
  );
}

FFIdentifier _ensurePageStateField(
  FFWidgetClass pos, {
  required String name,
  required FFDataTypeV2 dataType,
  required String defaultSerialized,
  required String description,
}) {
  final model = pos.ensureClassModel();
  for (final sf in model.stateFields) {
    if (sf.parameter.identifier.name == name) {
      return sf.parameter.identifier;
    }
  }

  final id = FFIdentifier(
    name: name,
    key: generateRandomAlphaNumericString(),
  );
  model.stateFields.add(
    FFWidgetClassStateField(
      parameter: FFParameter(
        identifier: id,
        dataType: dataType,
        defaultValue: FFParameterValue(serializedValue: defaultSerialized),
        description: description,
      ),
      serializedDefaultValue: [defaultSerialized],
    ),
  );
  return id;
}

void _ensureBannerHost({
  required String scaffoldKey,
  required FFNode stack,
  required FFIdentifier textFieldId,
  required FFIdentifier activeFieldId,
}) {
  final existing = findDescendants(
    stack,
    (n) => n.name == _kBannerHostName,
  );
  if (existing.isNotEmpty) {
    stderr.writeln(
      '[wire_pos_deferred_resume_banner] Banner host already present. Skipping widget insert.',
    );
    return;
  }

  final bannerTextVar = _pageWidgetStateStringVariable(
    field: textFieldId,
    scaffoldKey: scaffoldKey,
  );
  final bannerActiveVar = _pageWidgetStateBoolVariable(
    field: activeFieldId,
    scaffoldKey: scaffoldKey,
  );

  final text = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.Text,
    name: 'deferredResumeBannerText',
    props: FFWidgetProperties(
      padding: FFPadding(legacyVertical: 4, legacyHorizontal: 12),
      text: FFText(
        textValue: FFStringValue(variable: bannerTextVar),
        themeStyle: FFText_ThemeStyle.LABEL_LARGE,
        selectable: false,
        textAlignValue: FFTextAlignValue(
          inputValue: FFTextAlign.ALIGN_CENTER,
        ),
        colorValue: FFColorValue(
          inputValue: FFColor(themeColor: FFColor_ThemeColor.PRIMARY_TEXT),
        ),
      ),
    ),
  );

  final host = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.Container,
    name: _kBannerHostName,
    props: FFWidgetProperties(
      padding: FFPadding(legacyBottom: 6),
      container: FFContainer(
        dimensions: FFDimensions(
          width: FFDim(
            percentOfScreenSizeValue: FFDoubleValue(inputValue: 100),
          ),
        ),
        boxDecoration: FFBoxDecoration(
          colorValue: FFColorValue(
            inputValue: FFColor(themeColor: FFColor_ThemeColor.INFO),
          ),
        ),
      ),
      visibility: FFVisibility(
        visibleValue: FFBooleanValue(
          variable: bannerActiveVar,
          mostRecentInputValue: false,
        ),
      ),
    ),
    children: [text],
  );

  addChild(stack, host);
}

void _ensureDeferredResumeOnInitTrigger({
  required FFNode scaffold,
  required FFCustomAction getDeferredAction,
  required FFIdentifier textFieldId,
  required FFIdentifier activeFieldId,
}) {
  final triggers = scaffold.triggerActions;
  if (triggers.isNotEmpty && _onInitStartsWithGetDeferredResume(triggers.first)) {
    stderr.writeln(
      '[wire_pos_deferred_resume_banner] First page trigger already runs '
      'getDeferredResumeContext. Skipping on-load wiring.',
    );
    return;
  }

  final innerGetKey = generateRandomAlphaNumericString();
  final getRootKey = generateRandomAlphaNumericString();
  final updateNodeKey = generateRandomAlphaNumericString();
  final updateInnerKey = generateRandomAlphaNumericString();

  final getAction = FFAction()
    ..key = innerGetKey
    ..customAction = (FFCustomActionCall()
      // ignore: deprecated_member_use — same pattern as other DSL wires (identifier snapshot).
      ..customActionIdentifier = getDeferredAction.identifier.clone()
      ..argumentValues = FFFunctionCallValues())
    ..outputVariableName = _kDeferredResumeCtxOutput;

  final getRoot = FFActionNode(key: getRootKey)..action = getAction;

  final textBinding = FFValue(
    variable: _jsonPathFromCustomActionOutput(
      scaffoldKey: scaffold.key,
      innerActionKey: innerGetKey,
      jsonPath: r'$.bannerText',
    ),
  );
  final activeBinding = FFValue(
    variable: _jsonPathFromCustomActionOutput(
      scaffoldKey: scaffold.key,
      innerActionKey: innerGetKey,
      jsonPath: r'$.active',
    ),
  );

  final updateAction = FFAction()
    ..key = updateInnerKey
    ..localStateUpdate = FFLocalStateUpdate(
      updates: [
        FFLocalStateFieldUpdate(
          fieldIdentifier: textFieldId,
          setValue: textBinding,
        ),
        FFLocalStateFieldUpdate(
          fieldIdentifier: activeFieldId,
          setValue: activeBinding,
        ),
      ],
      updateType: FFLocalStateUpdate_UpdateType.WIDGET,
      stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
    );

  getRoot.followUpAction = FFActionNode(key: updateNodeKey)
    ..action = updateAction;

  final insertAt = triggers.indexWhere(
    (t) =>
        t.hasTrigger() &&
        t.trigger.triggerType == FFActionTriggerType.ON_INIT_STATE,
  );
  final wired = FFTriggerActions(
    rootAction: getRoot,
    trigger: FFActionTrigger(
      triggerType: FFActionTriggerType.ON_INIT_STATE,
    ),
  );

  if (insertAt == -1) {
    triggers.add(wired);
  } else {
    triggers.insert(insertAt, wired);
  }
}

bool _onInitStartsWithGetDeferredResume(FFTriggerActions t) {
  if (!t.hasTrigger() ||
      t.trigger.triggerType != FFActionTriggerType.ON_INIT_STATE) {
    return false;
  }
  if (!t.hasRootAction() || !t.rootAction.hasAction()) {
    return false;
  }
  final a = t.rootAction.action;
  if (a.whichAction() != FFAction_Action.customAction) {
    return false;
  }
  return a.customAction.customActionIdentifier.name ==
      'getDeferredResumeContext';
}

FFVariable _pageWidgetStateStringVariable({
  required FFIdentifier field,
  required String scaffoldKey,
}) {
  return FFVariable(
    source: FFVariableSource.LOCAL_STATE,
    baseVariable: FFBaseVariable(
      localState: FFLocalStateVariable(
        fieldIdentifier: field,
        stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
      ),
    ),
    nodeKeyRef: FFNodeKeyReference(key: scaffoldKey),
  );
}

FFVariable _pageWidgetStateBoolVariable({
  required FFIdentifier field,
  required String scaffoldKey,
}) {
  return FFVariable(
    source: FFVariableSource.LOCAL_STATE,
    baseVariable: FFBaseVariable(
      localState: FFLocalStateVariable(
        fieldIdentifier: field,
        stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
      ),
    ),
    nodeKeyRef: FFNodeKeyReference(key: scaffoldKey),
  );
}

FFVariable _jsonPathFromCustomActionOutput({
  required String scaffoldKey,
  required String innerActionKey,
  required String jsonPath,
}) {
  return FFVariable(
    source: FFVariableSource.ACTION_OUTPUTS,
    baseVariable: FFBaseVariable(
      actionOutput: FFActionOutputVariable(
        outputVariableIdentifier: FFIdentifier(name: _kDeferredResumeCtxOutput),
        actionKeyRef: FFActionKeyReference(key: innerActionKey),
      ),
    ),
    operations: [
      FFVariableOperation(
        jsonPathOperation: FFJsonPathOperation(jsonPath: jsonPath),
      ),
    ],
    nodeKeyRef: FFNodeKeyReference(key: scaffoldKey),
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
Wire pos page: deferred resume banner + On Page Load getDeferredResumeContext.

Usage (from positiv_flutterflow_ai/):
  dart run dsl/wire_pos_deferred_resume_banner.dart [options]

Options:
  --api-key <key>           FlutterFlow AI API key (or FLUTTERFLOW_AI_API_KEY / FF_API_KEY).
  --project-id <id>         Default: pointofsale-xrlz5i
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
