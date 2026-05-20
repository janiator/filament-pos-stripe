library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/action_block_helpers.dart'
    show findActionBlock, updateActionBlock;
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show addChild, findDescendants;
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart'
    show varFromPageParam;
import 'package:flutterflow_ai/src/helpers/widget_class_param_helpers.dart'
    as wc_param;

/// Wires the **orders** page **Betaling** button (`Button_svzfzpi5`) to the
/// parked-deferred flow: `prepareParkedDeferredPurchase` → success check →
/// **Navigate** to the **`pos`** page (cart already hydrated + prefs for banner).
///
/// Also configures **`deferredPaymentCheckout`** (used from POS): optional
/// **`parkedCartJson`**, **`completeDeferredPayment.cartJson`** bound to it, and
/// a **hidden** widget-tree reference so FlutterFlow R1 preflight passes.
///
/// Run from `positiv_flutterflow_ai/`:
///   dart run dsl/wire_orders_betaling_prepare_parked.dart --commit-message "…"
///
/// Prerequisites: `prepareParkedDeferredPurchase` exists (upsert script).
/// If FlutterFlow regenerates **node keys**, update the constants below
/// (re-inspect page `orders` → button "Betaling").

const _kBetalingButtonKey = 'Button_svzfzpi5';
const _kOrdersScaffoldKey = 'Scaffold_e2rlu24l';
/// Root scaffold of the **pos** page (inspect page `pos` if this changes).
const _kPosPageScaffoldKey = 'Scaffold_6umjp4qm';
const _kSelectedOrderStateKey = 'bha7f';
const _kPurchaseIdFieldKey = 'o2b9t';
const _kApiHostEnvKey = 'yfmygc';

const _kPrepareOutputName = 'prepareParkedForBetalingResult';

/// Stable [FFNode.name] for the hidden widget that references [parkedCartJson]
/// so FlutterFlow R1 (param must appear in widget tree) passes.
const _kParkedCartJsonSinkNodeName = 'parkedCartJsonParamSink';

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
          'feat(pos): orders Betaling → prepare + navigate pos (deferred resume)',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void _wire(FFProject project) {
  final prepare = findCustomAction(project, name: 'prepareParkedDeferredPurchase');
  if (prepare == null) {
    throw StateError(
      'Custom action prepareParkedDeferredPurchase not found. '
      'Run: dart run dsl/upsert_prepare_parked_deferred_purchase.dart',
    );
  }

  final purchaseIdArg = _argKey(prepare, 'purchaseId');
  final apiBaseArg = _argKey(prepare, 'apiBaseUrl');
  final authArg = _argKey(prepare, 'authToken');

  final parkedParamId = _ensureParkedCartJsonParameter(project);
  _ensureParkedCartJsonWidgetTreeSink(project, parkedParamId);
  _wireDeferredCheckoutCartJsonArgument(project, parkedParamId);

  final orders = findPage(project, name: 'orders');
  if (orders == null) {
    throw StateError('Page "orders" not found.');
  }
  final button = findByKey(orders.node, _kBetalingButtonKey);
  if (button == null) {
    throw StateError(
      'Button $_kBetalingButtonKey not found on orders page. '
      'Re-inspect and update _kBetalingButtonKey in this script.',
    );
  }
  if (button.triggerActions.isEmpty) {
    throw StateError('Betaling button has no trigger actions.');
  }

  final onTap = button.triggerActions.firstWhere(
    (t) => t.trigger.triggerType == FFActionTriggerType.ON_TAP,
    orElse: () => button.triggerActions.first,
  );

  if (_ordersBetalingAlreadyPrepareThenNavigateToPos(onTap)) {
    stderr.writeln(
      '[wire_orders_betaling_prepare_parked] Already wired. Skipping.',
    );
    return;
  }

  final prepNodeKey = generateRandomAlphaNumericString();
  final prepInnerActionKey = generateRandomAlphaNumericString();
  final condBlockKey = generateRandomAlphaNumericString();

  final navigateInnerKey = generateRandomAlphaNumericString();
  final navigateNode = FFActionNode(key: generateRandomAlphaNumericString())
    ..action = (FFAction()
      ..key = navigateInnerKey
      ..navigate = (FFNavigateAction()
        ..allowBack = true
        ..isNavigateBack = false
        ..pageNodeKeyRef = FFNodeKeyReference(key: _kPosPageScaffoldKey)
        ..passedParameters = (FFPassedParameters()
          ..widgetClassNodeKeyRef =
              FFNodeKeyReference(key: _kPosPageScaffoldKey))));

  final prepareArgs = FFFunctionCallValues()
    ..arguments.addAll({
      purchaseIdArg: FFFunctionCallValues_FFArgument(
        value: FFValue(variable: _selectedOrderIdVariable()),
      ),
      apiBaseArg: FFFunctionCallValues_FFArgument(
        value: FFValue(variable: _apiHostVariable()),
      ),
      authArg: FFFunctionCallValues_FFArgument(
        value: FFValue(variable: _authTokenVariable()),
      ),
    });

  final prepareAction = FFAction()
    ..key = prepInnerActionKey
    ..customAction = (FFCustomActionCall()
      ..customActionIdentifier = prepare.identifier.clone()
      ..argumentValues = prepareArgs)
    ..outputVariableName = _kPrepareOutputName;

  final prepareRoot = FFActionNode(key: prepNodeKey)..action = prepareAction;

  final falseNode = FFActionNode(key: generateRandomAlphaNumericString())
    ..action = (FFAction()
      ..alertDialog = (FFAlertDialogAction()
        ..informationalDialog = FFInformationalDialogAction(
          title: FFValue(
            inputValue: FFParameterValue(serializedValue: 'Feil'),
          ),
          message: FFValue(
            variable: _prepareOutputJsonPathVariable(
              prepInnerActionKey,
              r'$.message',
            ),
          ),
          dismissText: FFValue(
            inputValue: FFParameterValue(serializedValue: 'OK'),
          ),
        )));

  final conditionBlock = FFConditionActions(
    key: generateRandomAlphaNumericString(),
    falseAction: falseNode,
    trueActions: [
      FFConditionActions_FFTrueConditionAction(
        condition: FFActionCondition(
          variable: _prepareOutputJsonPathVariable(
            prepInnerActionKey,
            r'$.success',
          ),
        ),
        trueAction: navigateNode,
      ),
    ],
  );

  prepareRoot.followUpAction = FFActionNode(key: condBlockKey)
    ..conditionActions = conditionBlock;

  final wired = FFTriggerActions(
    rootAction: prepareRoot,
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
  );

  final idx = button.triggerActions.indexOf(onTap);
  if (idx == -1) {
    button.triggerActions.add(wired);
  } else {
    button.triggerActions[idx] = wired;
  }
}

String _argKey(FFCustomAction prepare, String name) {
  for (final a in prepare.arguments) {
    if (a.identifier.name == name) {
      return a.identifier.key;
    }
  }
  throw StateError('prepareParkedDeferredPurchase missing argument "$name".');
}

/// Inserts a **zero-height** [FFWidgetType.Container] (clip) with a [Text]
/// bound to [parkedCartJson].
/// FlutterFlow wiring rule R1 only scans the visual widget tree, not action
/// blocks, so this satisfies "parameter referenced in widget tree".
void _ensureParkedCartJsonWidgetTreeSink(
  FFProject project,
  FFIdentifier parkedParamIdentifier,
) {
  final wc = findComponent(project, name: 'deferredPaymentCheckout');
  if (wc == null) {
    throw StateError('Widget class deferredPaymentCheckout not found.');
  }
  final root = wc.node;
  final existing = findDescendants(
    root,
    (n) => n.name == _kParkedCartJsonSinkNodeName,
  );
  if (existing.isNotEmpty) {
    return;
  }

  final rootKey = root.key;
  if (rootKey.isEmpty) {
    throw StateError('deferredPaymentCheckout root node has empty key.');
  }

  final paramVar = varFromPageParam(parkedParamIdentifier)
    ..nodeKeyRef = FFNodeKeyReference(key: rootKey);

  final textChild = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.Text,
    name: 'parkedCartJsonBoundText',
    props: FFWidgetProperties(
      text: FFText(
        textValue: FFStringValue(variable: paramVar),
        themeStyle: FFText_ThemeStyle.BODY_SMALL,
        selectable: false,
      ),
    ),
  );

  final zeroHeight = FFDim(pixelsValue: FFDoubleValue(inputValue: 0));
  final sinkWrap = FFNode(
    key: generateRandomAlphaNumericString(),
    type: FFWidgetType.Container,
    name: _kParkedCartJsonSinkNodeName,
    props: FFWidgetProperties(
      container: FFContainer(
        dimensions: FFDimensions(height: zeroHeight),
        clipContent: true,
      ),
    ),
    children: [textChild],
  );

  addChild(root, sinkWrap);
}

/// Binds [completeDeferredPayment] **`cartJson`** to the component parameter
/// **`parkedCartJson`** so FlutterFlow preflight accepts the parameter.
void _wireDeferredCheckoutCartJsonArgument(
  FFProject project,
  FFIdentifier parkedParamIdentifier,
) {
  final wc = findComponent(project, name: 'deferredPaymentCheckout');
  if (wc == null) {
    throw StateError('Widget class deferredPaymentCheckout not found.');
  }
  final block = findActionBlock(
    project,
    name: 'completeDeferredPayment',
    widgetClass: wc,
  );
  if (block == null) {
    throw StateError(
      'Action block completeDeferredPayment not found on deferredPaymentCheckout.',
    );
  }
  final complete = findCustomAction(project, name: 'completeDeferredPayment');
  if (complete == null) {
    throw StateError(
      'Custom action completeDeferredPayment not found. '
      'Run: dart run dsl/update_complete_deferred_payment.dart',
    );
  }
  final cartArgKey = _argKey(complete, 'cartJson');

  final root = block.actions.rootAction;
  if (!root.hasAction() ||
      root.action.whichAction() != FFAction_Action.customAction) {
    throw StateError(
      'completeDeferredPayment block root must be a custom action call.',
    );
  }
  if (_cartJsonAlreadyUsesParkedParam(
        root,
        cartArgKey: cartArgKey,
        parkedParamKey: parkedParamIdentifier.key,
      )) {
    stderr.writeln(
      '[wire_orders_betaling_prepare_parked] '
      'completeDeferredPayment.cartJson already bound to parkedCartJson. Skipping.',
    );
    return;
  }

  final rootKey = wc.hasNode() ? wc.node.key : '';
  if (rootKey.isEmpty) {
    throw StateError('deferredPaymentCheckout has no root node key.');
  }

  final vCart = varFromPageParam(parkedParamIdentifier)
    ..nodeKeyRef = FFNodeKeyReference(key: rootKey);

  root.action.customAction.argumentValues.arguments[cartArgKey] =
      FFFunctionCallValues_FFArgument(value: FFValue(variable: vCart));

  updateActionBlock(
    project,
    name: 'completeDeferredPayment',
    widgetClass: wc,
    rootAction: root,
  );
}

bool _cartJsonAlreadyUsesParkedParam(
  FFActionNode root, {
  required String cartArgKey,
  required String parkedParamKey,
}) {
  if (!root.hasAction() ||
      root.action.whichAction() != FFAction_Action.customAction) {
    return false;
  }
  final arg =
      root.action.customAction.argumentValues.arguments[cartArgKey];
  if (arg == null || !arg.value.hasVariable()) {
    return false;
  }
  final variable = arg.value.variable;
  if (variable.source != FFVariableSource.WIDGET_CLASS_PARAMETER) {
    return false;
  }
  if (!variable.baseVariable.hasWidgetClass()) {
    return false;
  }
  return variable.baseVariable.widgetClass.paramIdentifier.key ==
      parkedParamKey;
}

FFIdentifier _ensureParkedCartJsonParameter(FFProject project) {
  final existing = wc_param.listComponentParameters(
    project,
    componentName: 'deferredPaymentCheckout',
  );
  for (final p in existing) {
    if (p.name == 'parkedCartJson') {
      return FFIdentifier(name: p.name, key: p.key);
    }
  }
  return wc_param.addComponentParameter(
    project,
    componentName: 'deferredPaymentCheckout',
    name: 'parkedCartJson',
    dataType: FFDataTypeV2(
      scalarType: FFBaseDataType.String,
      nonNullable: false,
    ),
    description:
        'JSON from prepareParkedDeferredPurchase.cartJson (orders Betaling).',
  );
}

FFVariable _selectedOrderIdVariable() {
  return FFVariable(
    source: FFVariableSource.LOCAL_STATE,
    baseVariable: FFBaseVariable(
      localState: FFLocalStateVariable(
        fieldIdentifier: FFIdentifier(
          name: 'selectedOrder',
          key: _kSelectedOrderStateKey,
        ),
        stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
      ),
    ),
    operations: [
      FFVariableOperation(
        accessDataStructField: FFAccessDataStructField(
          fieldIdentifier: FFIdentifier(name: 'id', key: _kPurchaseIdFieldKey),
        ),
      ),
    ],
    nodeKeyRef: FFNodeKeyReference(key: _kOrdersScaffoldKey),
  );
}

FFVariable _apiHostVariable() {
  return FFVariable(
    source: FFVariableSource.DEV_ENVIRONMENT,
    baseVariable: FFBaseVariable(
      environmentValue: FFEnvironmentValueVariable(
        identifier: FFIdentifier(name: 'apiHost', key: _kApiHostEnvKey),
      ),
    ),
  );
}

FFVariable _authTokenVariable() {
  return FFVariable(
    source: FFVariableSource.CUSTOM_AUTH_USER,
    baseVariable: FFBaseVariable(
      auth: FFAuthVariable(
        property: FFAuthVariable_AuthProperty.AUTH_TOKEN,
      ),
    ),
  );
}

FFVariable _prepareOutputJsonPathVariable(
  String prepareInnerActionKey,
  String jsonPath,
) {
  return FFVariable(
    source: FFVariableSource.ACTION_OUTPUTS,
    baseVariable: FFBaseVariable(
      actionOutput: FFActionOutputVariable(
        outputVariableIdentifier: FFIdentifier(name: _kPrepareOutputName),
        actionKeyRef: FFActionKeyReference(key: prepareInnerActionKey),
      ),
    ),
    operations: [
      FFVariableOperation(
        jsonPathOperation: FFJsonPathOperation(jsonPath: jsonPath),
      ),
    ],
    nodeKeyRef: FFNodeKeyReference(key: _kBetalingButtonKey),
  );
}

/// True when this button already runs **prepareParkedDeferredPurchase** then
/// navigates to **pos** (re-run safe).
bool _ordersBetalingAlreadyPrepareThenNavigateToPos(FFTriggerActions onTap) {
  if (!onTap.hasRootAction() || !onTap.rootAction.hasAction()) {
    return false;
  }
  final rootAction = onTap.rootAction.action;
  if (rootAction.whichAction() != FFAction_Action.customAction) {
    return false;
  }
  if (rootAction.customAction.customActionIdentifier.name !=
      'prepareParkedDeferredPurchase') {
    return false;
  }
  final follow = onTap.rootAction;
  if (!follow.hasFollowUpAction()) {
    return false;
  }
  final condNode = follow.followUpAction;
  if (!condNode.hasConditionActions()) {
    return false;
  }
  final ca = condNode.conditionActions;
  if (ca.trueActions.isEmpty) {
    return false;
  }
  final trueNode = ca.trueActions.first.trueAction;
  if (!trueNode.hasAction()) {
    return false;
  }
  final ta = trueNode.action;
  if (ta.whichAction() != FFAction_Action.navigate) {
    return false;
  }
  return ta.navigate.pageNodeKeyRef.key == _kPosPageScaffoldKey;
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
Wire orders page Betaling button to prepareParkedDeferredPurchase + deferredPaymentCheckout.

Usage (from positiv_flutterflow_ai/):
  dart run dsl/wire_orders_betaling_prepare_parked.dart [options]

Options:
  --api-key <key>           FlutterFlow AI API key (or FLUTTERFLOW_AI_API_KEY / FF_API_KEY).
  --project-id <id>         Default: pointofsale-xrlz5i
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
