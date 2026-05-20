library;

// Protobuf [clone] is still the practical way to duplicate nested FF messages.
// ignore_for_file: deprecated_member_use

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';
// ignore: implementation_imports
import 'package:flutterflow_ai/src/helpers/state_update.dart' show StateFieldUpdate;
import 'package:flutterflow_ai/src/helpers/data_schema_helpers.dart'
    show findAppStateField;
// ignore: implementation_imports
import 'package:flutterflow_ai/src/internal_sdk.dart' show Actions;
import 'package:flutterflow_ai/src/helpers/key_gen.dart';
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show findByKey, findDescendants, insertAfterKey;
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart'
    show varFromAppState, varFromPageParam, varFromPageState;
import 'package:flutterflow_ai/src/helpers/widget_class_param_helpers.dart'
    as wc_param;
import 'package:flutterflow_ai/src/helpers/action_block_helpers.dart'
    show findActionBlock, updateActionBlock;

/// Duplicates each **Fullfør handel** button in **`checkoutFlow`** (cash + two
/// card paths) and wires each duplicate to **`getDeferredResumeContext`** →
/// **`serializeCartForCompleteDeferred`** → **`completeDeferredPayment`**.
///
/// **Receipt / step 3:** after a successful `completeDeferredPayment`, each deferred
/// resume button sets **`currentStep = 3`** and **`receiptId`** from `$.receiptId` (for
/// the final checkout screen / manual reprint). Client delivery-receipt print and cart
/// clear still run **inside** `completeDeferredPayment` on success.
/// This script **wraps** `checkoutFlow` **`receiptPrint`** so it does not run while
/// **`deferredResumeBannerActive`** is true (avoids `/receipts/null/xml`).
///
/// **Designer (FlutterFlow UI):**
/// - **Visibility** is set by this script via component parameter
///   **`deferredResumeBannerActive`** (passed from **pos** widget state).
/// - Optionally re-bind **`completeDeferredPayment.cartJson`** from the
///   **serialize** action output (`$.cartJson`) if staff may **edit the cart**
///   before paying (R1 may require a hidden **Text** widget referencing the
///   same variable — see `wire_orders_betaling_prepare_parked.dart`).
///
/// Until **`cartJson`** is wired, the script passes an **empty string** (backend
/// keeps original deferred lines).
///
/// Each deferred tap chain starts with **`getDeferredResumeContext`** so
/// **`resumeChargeId`** is available for **`completeDeferredPayment`** without
/// relying on page-state bindings from inside the component.
///
/// Idempotent: stable **`name`** on deferred duplicates (`deferredResumePay_*`).
///
/// Run from `positiv_flutterflow_ai/`:
///   dart run dsl/wire_checkoutflow_deferred_pay_branch.dart --commit-message "…"
///
/// Prerequisites:
/// - `wire_pos_deferred_resume_banner.dart` (banner state + on-load get context).
/// - Custom actions: `serializeCartForCompleteDeferred`, `completeDeferredPayment`,
///   `completePosPurchase` (see repo `docs/flutterflow/` upsert scripts).
/// - If FlutterFlow regenerates **node keys**, update `_kCheckoutCompleteButtons`.

const _kCheckoutComponentName = 'checkoutFlow';
const _kCheckoutRootKey = 'Container_dgrfio4h';

/// Same name as **pos** page widget state (see `wire_pos_deferred_resume_banner.dart`).
const _kCheckoutDeferredResumeParamName = 'deferredResumeBannerActive';
/// Pages that embed **checkoutFlow** in POSitiv (extend if your project differs).
const _kPosPagesHostingCheckout = <String>['pos', 'posSession'];

/// FlutterFlow JSON-path bindings require the custom action to declare a JSON
/// return (same pattern as `getDeferredResumeContext` in
/// `wire_pos_deferred_resume_banner.dart`).
const _kSerializeReturnParamName = 'serializeDeferredCartJson';

/// Inspect cache keys for **Fullfør handel** buttons (re-inspect `checkoutFlow` if FF regens).
const _kCheckoutCompleteButtons = <({String key, String deferredNameSuffix})>[
  (key: 'Button_rs9pwjk1', deferredNameSuffix: 'cash'),
  (key: 'Button_w82o4mbk', deferredNameSuffix: 'card_a'),
  (key: 'Button_x0v33m29', deferredNameSuffix: 'card_b'),
];

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
          'feat(checkoutFlow): deferred resume pay branch + visibility',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void _wire(FFProject project) {
  final getCtx = findCustomAction(project, name: 'getDeferredResumeContext');
  final serialize = findCustomAction(project, name: 'serializeCartForCompleteDeferred');
  final completeDef = findCustomAction(project, name: 'completeDeferredPayment');
  final completePos = findCustomAction(project, name: 'completePosPurchase');
  if (getCtx == null) {
    throw StateError(
      'getDeferredResumeContext not found. '
      'Run: dart run dsl/upsert_deferred_resume_helpers.dart',
    );
  }
  if (serialize == null) {
    throw StateError(
      'serializeCartForCompleteDeferred not found. '
      'Run: dart run dsl/upsert_deferred_resume_helpers.dart',
    );
  }
  _ensureSerializeCartForCompleteDeferredDeclaresJsonReturn(project);
  if (completeDef == null) {
    throw StateError(
      'completeDeferredPayment not found. '
      'Run: dart run dsl/update_complete_deferred_payment.dart',
    );
  }
  if (completePos == null) {
    throw StateError('completePosPurchase not found.');
  }

  final checkout = findComponent(project, name: _kCheckoutComponentName);
  if (checkout == null) {
    throw StateError('Component "$_kCheckoutComponentName" not found.');
  }
  final checkoutRoot = findByKey(checkout.node, _kCheckoutRootKey);
  if (checkoutRoot == null) {
    throw StateError(
      'checkoutFlow root $_kCheckoutRootKey not found. Re-inspect and update _kCheckoutRootKey.',
    );
  }

  final deferredParamId = _ensureCheckoutDeferredResumeComponentParam(project);
  _wirePosCheckoutInstancesDeferredParam(
    project,
    checkoutWc: checkout,
    checkoutDeferredParamId: deferredParamId,
  );

  final calendarPickupArg = _checkoutCalendarPickupArg(
    checkout: checkout,
    completePos: completePos,
    checkoutRootKey: checkoutRoot.key,
  );
  if (calendarPickupArg == null) {
    stderr.writeln(
      '[wire_checkoutflow_deferred_pay_branch] Warning: could not resolve '
      'checkoutFlow calendar pickup binding; deferred revise may keep old hentedato.',
    );
  }

  for (final spec in _kCheckoutCompleteButtons) {
    _wireOneCheckoutButton(
      project: project,
      checkoutRoot: checkoutRoot,
      getDeferredAction: getCtx,
      serialize: serialize,
      completeDef: completeDef,
      completePos: completePos,
      calendarPickupArg: calendarPickupArg,
      sourceButtonKey: spec.key,
      deferredStableName: 'deferredResumePay_${spec.deferredNameSuffix}',
      deferredNameSuffix: spec.deferredNameSuffix,
      serializeOutputName: 'dslSerz_${spec.deferredNameSuffix}',
      tapContextOutputName: 'dslTapCtx_${spec.deferredNameSuffix}',
    );
  }

  _bindCheckoutButtonsVisibility(
    checkoutRoot: checkoutRoot,
    deferredResumeParamId: deferredParamId,
  );

  _gateReceiptPrintWhenDeferredResumeActive(
    project,
    checkout: checkout,
    deferredParamId: deferredParamId,
  );
}

/// While paying a parked deferred order, `receiptPrint` would still run with
/// `completePosPurchase` outputs (null receipt id). Skip the block when
/// [deferredResumeBannerActive] is true; printing uses `completeDeferredPayment` instead.
void _gateReceiptPrintWhenDeferredResumeActive(
  FFProject project, {
  required FFWidgetClass checkout,
  required FFIdentifier deferredParamId,
}) {
  final block = findActionBlock(
    project,
    name: 'receiptPrint',
    widgetClass: checkout,
  );
  if (block == null) {
    stderr.writeln(
      '[wire_checkoutflow_deferred_pay_branch] No action block receiptPrint on '
      'checkoutFlow; skip receipt gate.',
    );

    return;
  }

  final root = block.actions.rootAction;
  if (root.hasConditionActions()) {
    return;
  }

  final checkoutRoot = findByKey(checkout.node, _kCheckoutRootKey);
  if (checkoutRoot == null) {
    stderr.writeln(
      '[wire_checkoutflow_deferred_pay_branch] checkout root $_kCheckoutRootKey not '
      'found; skip receiptPrint gate.',
    );

    return;
  }
  final checkoutRootKey = checkoutRoot.key;

  final receiptTree = root.deepCopy();
  final noop = FFActionNode(key: generateActionKey())
    ..action = (FFAction()
      ..key = generateActionKey()
      ..waitAction = FFWaitAction(durationMillis: 1));

  final deferredVar = varFromPageParam(deferredParamId)
    ..nodeKeyRef = FFNodeKeyReference(key: checkoutRootKey);

  final wrapped = FFActionNode(key: generateActionKey())
    ..conditionActions = FFConditionActions(
      key: generateActionKey(),
      hasMultiConditions: false,
      falseAction: receiptTree,
      trueActions: [
        FFConditionActions_FFTrueConditionAction(
          condition: FFActionCondition(variable: deferredVar),
          trueAction: noop,
        ),
      ],
    );

  updateActionBlock(
    project,
    name: 'receiptPrint',
    widgetClass: checkout,
    rootAction: wrapped,
  );

  stderr.writeln(
    '[wire_checkoutflow_deferred_pay_branch] receiptPrint skipped when '
    '$_kCheckoutDeferredResumeParamName is true.',
  );
}

FFIdentifier _ensureCheckoutDeferredResumeComponentParam(FFProject project) {
  final existing = wc_param.listComponentParameters(
    project,
    componentName: _kCheckoutComponentName,
  );
  for (final p in existing) {
    if (p.name == _kCheckoutDeferredResumeParamName) {
      return FFIdentifier(name: p.name, key: p.key);
    }
  }

  return wc_param.addComponentParameter(
    project,
    componentName: _kCheckoutComponentName,
    name: _kCheckoutDeferredResumeParamName,
    dataType: FFDataTypeV2(
      scalarType: FFBaseDataType.Boolean,
      nonNullable: true,
    ),
    description:
        'When true, show deferred “Fullfør” buttons; mirrors pos '
        'deferredResumeBannerActive (wired on pos checkoutFlow instances).',
    defaultValue: 'false',
  );
}

void _wirePosCheckoutInstancesDeferredParam(
  FFProject project, {
  required FFWidgetClass checkoutWc,
  required FFIdentifier checkoutDeferredParamId,
}) {
  final passKey = checkoutDeferredParamId.key;

  final appStateActive = findAppStateField(
    project,
    name: _kCheckoutDeferredResumeParamName,
  );
  late final FFVariable stateVar;
  late final String passThroughLabel;

  if (appStateActive != null) {
    stateVar = varFromAppState(appStateActive.parameter.identifier);
    passThroughLabel =
        'FFAppState.$_kCheckoutDeferredResumeParamName (see wire_pos_deferred_resume_banner_app_state.dart)';
    stderr.writeln(
      '[wire_checkoutflow_deferred_pay_branch] checkoutFlow pass-through uses '
      '$passThroughLabel.',
    );
  } else {
    FFWidgetClass? posPageWithState;
    for (final name in _kPosPagesHostingCheckout) {
      final p = findPage(project, name: name);
      if (p == null) {
        continue;
      }
      if (_findPosDeferredResumeActiveFieldId(p) != null) {
        posPageWithState = p;
        break;
      }
    }

    if (posPageWithState == null) {
      stderr.writeln(
        '[wire_checkoutflow_deferred_pay_branch] No page in '
        '${_kPosPagesHostingCheckout.join(", ")} defines widget state '
        '${_kCheckoutDeferredResumeParamName}, and no matching App State field. '
        'Run wire_pos_deferred_resume_banner.dart on **pos**, or '
        'wire_pos_deferred_resume_banner_app_state.dart.',
      );
      return;
    }

    final activeFieldId = _findPosDeferredResumeActiveFieldId(posPageWithState)!;
    final stateSourceScaffold = posPageWithState.node;
    stateVar = varFromPageState(activeFieldId)
      ..nodeKeyRef = FFNodeKeyReference(key: stateSourceScaffold.key);
    passThroughLabel =
        '**pos** scaffold ${stateSourceScaffold.key} page state';
  }

  var totalWired = 0;
  for (final pageName in _kPosPagesHostingCheckout) {
    final page = findPage(project, name: pageName);
    if (page == null) {
      continue;
    }
    final scaffold = page.node;
    final instances = findDescendants(
      scaffold,
      (n) => _isCheckoutFlowInstanceOnPage(n, checkoutWc),
    );

    for (final inst in instances) {
      final pv = inst.hasParameterValues()
          ? inst.parameterValues
          : FFPassedParameters();
      if (!inst.hasParameterValues()) {
        inst.parameterValues = pv;
      }

      final existing = pv.parameterPasses[passKey];
      if (existing != null &&
          existing.whichValue() == FFParameterPass_Value.variable) {
        if (appStateActive != null) {
          if (_variableIsAppStateBinding(
            existing.variable,
            fieldKey: appStateActive.parameter.identifier.key,
          )) {
            continue;
          }
        } else {
          final posPageWithState = _posPageHavingDeferredWidgetState(project);
          if (posPageWithState != null) {
            final activeFieldId = _findPosDeferredResumeActiveFieldId(posPageWithState)!;
            final stateSourceScaffold = posPageWithState.node;
            if (_variableIsPageStateBinding(
              existing.variable,
              fieldKey: activeFieldId.key,
              scaffoldKey: stateSourceScaffold.key,
            )) {
              continue;
            }
          }
        }
      }

      pv.parameterPasses[passKey] = FFParameterPass(
        paramIdentifier: checkoutDeferredParamId.clone(),
        variable: stateVar.clone(),
      );
      totalWired++;
      stderr.writeln(
        '[wire_checkoutflow_deferred_pay_branch] page "$pageName": checkoutFlow '
        'instance ${inst.name} (${inst.key}) ← ${_kCheckoutDeferredResumeParamName} '
        'from $passThroughLabel.',
      );
    }
  }

  if (totalWired == 0) {
    stderr.writeln(
      '[wire_checkoutflow_deferred_pay_branch] No checkoutFlow instances on '
      '${_kPosPagesHostingCheckout.join(" or ")} (refs vs root ${checkoutWc.node.key}). '
      'Embed **checkoutFlow** on one of those pages, or pass '
      '${_kCheckoutDeferredResumeParamName} manually from **pos** widget state.',
    );
  }
}

FFWidgetClass? _posPageHavingDeferredWidgetState(FFProject project) {
  for (final name in _kPosPagesHostingCheckout) {
    final p = findPage(project, name: name);
    if (p == null) {
      continue;
    }
    if (_findPosDeferredResumeActiveFieldId(p) != null) {
      return p;
    }
  }
  return null;
}

bool _variableIsAppStateBinding(
  FFVariable variable, {
  required String fieldKey,
}) {
  if (variable.source != FFVariableSource.LOCAL_STATE) {
    return false;
  }
  if (!variable.baseVariable.hasLocalState()) {
    return false;
  }
  final ls = variable.baseVariable.localState;
  if (ls.stateVariableType != FFStateVariableType.APP_STATE) {
    return false;
  }
  return ls.fieldIdentifier.key == fieldKey;
}

bool _isCheckoutFlowInstanceOnPage(FFNode n, FFWidgetClass checkoutWc) {
  final defRootKey = checkoutWc.node.key;
  if (n.hasComponentClassKeyRef() && n.componentClassKeyRef.key == defRootKey) {
    return true;
  }
  if (n.hasLegacyFfWidgetClass() &&
      n.legacyFfWidgetClass == _kCheckoutComponentName) {
    return true;
  }
  if (!n.hasParameterValues()) {
    return false;
  }
  final pv = n.parameterValues;
  if (pv.hasWidgetClassNodeKeyRef() &&
      pv.widgetClassNodeKeyRef.key == defRootKey) {
    return true;
  }
  if (pv.hasCustomWidgetIdentifier() &&
      pv.customWidgetIdentifier.name == _kCheckoutComponentName) {
    return true;
  }
  if (pv.hasLegacyFfWidgetClass() &&
      pv.legacyFfWidgetClass == _kCheckoutComponentName) {
    return true;
  }
  return false;
}

FFIdentifier? _findPosDeferredResumeActiveFieldId(FFWidgetClass pos) {
  final model = pos.ensureClassModel();
  for (final sf in model.stateFields) {
    if (sf.parameter.identifier.name == _kCheckoutDeferredResumeParamName) {
      return sf.parameter.identifier;
    }
  }
  return null;
}

bool _variableIsPageStateBinding(
  FFVariable variable, {
  required String fieldKey,
  required String scaffoldKey,
}) {
  if (variable.source != FFVariableSource.LOCAL_STATE) {
    return false;
  }
  if (!variable.baseVariable.hasLocalState()) {
    return false;
  }
  final ls = variable.baseVariable.localState;
  if (ls.stateVariableType != FFStateVariableType.WIDGET_CLASS_STATE) {
    return false;
  }
  if (ls.fieldIdentifier.key != fieldKey) {
    return false;
  }
  if (!variable.hasNodeKeyRef()) {
    return false;
  }
  return variable.nodeKeyRef.key == scaffoldKey;
}

void _bindCheckoutButtonsVisibility({
  required FFNode checkoutRoot,
  required FFIdentifier deferredResumeParamId,
}) {
  final rootKey = checkoutRoot.key;
  for (final spec in _kCheckoutCompleteButtons) {
    final original = findByKey(checkoutRoot, spec.key);
    if (original == null) {
      continue;
    }
    _setButtonVisibilityFromCheckoutParam(
      original,
      paramId: deferredResumeParamId,
      checkoutRootKey: rootKey,
      visibleWhenDeferredResumeActive: false,
    );

    final deferredName = 'deferredResumePay_${spec.deferredNameSuffix}';
    final dups = findDescendants(
      checkoutRoot,
      (n) => n.name == deferredName,
    );
    if (dups.isEmpty) {
      continue;
    }
    _setButtonVisibilityFromCheckoutParam(
      dups.first,
      paramId: deferredResumeParamId,
      checkoutRootKey: rootKey,
      visibleWhenDeferredResumeActive: true,
    );
  }
}

void _setButtonVisibilityFromCheckoutParam(
  FFNode button, {
  required FFIdentifier paramId,
  required String checkoutRootKey,
  required bool visibleWhenDeferredResumeActive,
}) {
  if (button.type != FFWidgetType.Button) {
    return;
  }

  final paramVar = varFromPageParam(paramId)
    ..nodeKeyRef = FFNodeKeyReference(key: checkoutRootKey);
  if (!visibleWhenDeferredResumeActive) {
    paramVar.operations.add(
      FFVariableOperation(negate: FFNegateBoolean()),
    );
  }

  final vis = button.props.ensureVisibility();
  vis.visibleValue = FFBooleanValue(
    variable: paramVar,
    mostRecentInputValue: visibleWhenDeferredResumeActive,
  );
}

void _ensureSerializeCartForCompleteDeferredDeclaresJsonReturn(FFProject project) {
  final action = findCustomAction(project, name: 'serializeCartForCompleteDeferred');
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
    name: 'serializeCartForCompleteDeferred',
    returnParameter: FFParameter(
      identifier: FFIdentifier(
        name: _kSerializeReturnParamName,
        key: generateRandomAlphaNumericString(),
      ),
      dataType: FFDataTypeV2(
        scalarType: FFBaseDataType.JSON,
        nonNullable: false,
      ),
      description: 'JSON map from serializeCartForCompleteDeferred (success, cartJson, …).',
    ),
  );
}

String _deferredCompleteOutputVariableName(String deferredNameSuffix) {
  return switch (deferredNameSuffix) {
    'cash' => 'completeDeferredCashResult',
    'card_a' => 'completeDeferredDeferredResult',
    'card_b' => 'completeDeferredOtherResult',
    _ => 'completeDeferred${deferredNameSuffix}Result',
  };
}

void _wireOneCheckoutButton({
  required FFProject project,
  required FFNode checkoutRoot,
  required FFCustomAction getDeferredAction,
  required FFCustomAction serialize,
  required FFCustomAction completeDef,
  required FFCustomAction completePos,
  required FFFunctionCallValues_FFArgument? calendarPickupArg,
  required String sourceButtonKey,
  required String deferredStableName,
  required String deferredNameSuffix,
  required String serializeOutputName,
  required String tapContextOutputName,
}) {
  final completeOutputName = _deferredCompleteOutputVariableName(
    deferredNameSuffix,
  );
  final original = findByKey(checkoutRoot, sourceButtonKey);
  if (original == null) {
    stderr.writeln(
      '[wire_checkoutflow_deferred_pay_branch] Warning: button $sourceButtonKey '
      'not found under checkoutFlow (skipped).',
    );
    return;
  }

  final existingDup = findDescendants(
    checkoutRoot,
    (n) => n.name == deferredStableName,
  );
  if (existingDup.isNotEmpty) {
    final dup = existingDup.first;
    final origArgsRefresh = _completePosPurchaseArgumentValues(original);
    if (origArgsRefresh == null) {
      throw StateError(
        'Button $sourceButtonKey: expected ON_TAP root customAction completePosPurchase.',
      );
    }
    dup.triggerActions.clear();
    dup.triggerActions.add(
      _buildDeferredResumeOnTap(
        project: project,
        actionOutputHostKey: dup.key,
        getDeferredAction: getDeferredAction,
        serialize: serialize,
        completeDef: completeDef,
        completePos: completePos,
        completePosArgs: origArgsRefresh,
        calendarPickupArg: calendarPickupArg,
        completeOutputName: completeOutputName,
        serializeOutputName: serializeOutputName,
        tapContextOutputName: tapContextOutputName,
      ),
    );
    stderr.writeln(
      '[wire_checkoutflow_deferred_pay_branch] Deferred button "$deferredStableName" '
      'already exists; refreshed tap chain.',
    );
    return;
  }

  final dup = original.clone();
  dup.key = generateNodeKey(FFWidgetType.Button);
  dup.name = deferredStableName;
  dup.triggerActions.clear();

  final origArgs = _completePosPurchaseArgumentValues(original);
  if (origArgs == null) {
    throw StateError(
      'Button $sourceButtonKey: expected ON_TAP root customAction completePosPurchase.',
    );
  }

  dup.triggerActions.add(
    _buildDeferredResumeOnTap(
      project: project,
      actionOutputHostKey: dup.key,
      getDeferredAction: getDeferredAction,
      serialize: serialize,
      completeDef: completeDef,
      completePos: completePos,
      completePosArgs: origArgs,
      calendarPickupArg: calendarPickupArg,
      completeOutputName: completeOutputName,
      serializeOutputName: serializeOutputName,
      tapContextOutputName: tapContextOutputName,
    ),
  );

  if (!insertAfterKey(checkoutRoot, sourceButtonKey, dup)) {
    throw StateError('insertAfterKey failed for $sourceButtonKey.');
  }
}

FFActionNode _buildDeferredCompleteSuccessFollowUp({
  required FFProject project,
  required String actionOutputHostKey,
  required String completeInnerKey,
  required String completeOutputName,
}) {
  final checkoutUpdates = Actions.updatePageState(
    project,
    widgetClassName: _kCheckoutComponentName,
    updates: [
      const StateFieldUpdate.set('currentStep', '3'),
      StateFieldUpdate.setFromVariable(
        'receiptId',
        _actionOutputJsonPath(
          actionOutputHostKey: actionOutputHostKey,
          innerActionKey: completeInnerKey,
          outputName: completeOutputName,
          jsonPath: r'$.receiptId',
        ),
      ),
    ],
  );

  return FFActionNode(key: generateActionKey())
    ..conditionActions = FFConditionActions(
      key: generateActionKey(),
      hasMultiConditions: false,
      trueActions: [
        FFConditionActions_FFTrueConditionAction(
          condition: FFActionCondition(
            variable: _actionOutputJsonPath(
              actionOutputHostKey: actionOutputHostKey,
              innerActionKey: completeInnerKey,
              outputName: completeOutputName,
              jsonPath: r'$.success',
            ),
          ),
          trueAction: FFActionNode(key: generateActionKey())
            ..action = checkoutUpdates,
        ),
      ],
    );
}

FFTriggerActions _buildDeferredResumeOnTap({
  required FFProject project,
  required String actionOutputHostKey,
  required FFCustomAction getDeferredAction,
  required FFCustomAction serialize,
  required FFCustomAction completeDef,
  required FFCustomAction completePos,
  required FFFunctionCallValues completePosArgs,
  required FFFunctionCallValues_FFArgument? calendarPickupArg,
  required String completeOutputName,
  required String serializeOutputName,
  required String tapContextOutputName,
}) {
  final getDefInnerKey = generateActionKey();
  final getDefRootKey = generateActionKey();
  final serializeInnerKey = generateActionKey();
  final serializeRootKey = generateActionKey();
  final afterSerializeCondKey = generateActionKey();
  final serializeFailAlertKey = generateActionKey();
  final completeInnerKey = generateActionKey();
  final completeRootKey = generateActionKey();

  final getCtxAction = FFAction()
    ..key = getDefInnerKey
    ..customAction = (FFCustomActionCall()
      ..customActionIdentifier = getDeferredAction.identifier.clone()
      ..argumentValues = FFFunctionCallValues())
    ..outputVariableName = tapContextOutputName;

  final getCtxRoot = FFActionNode(key: getDefRootKey)..action = getCtxAction;

  final serializeAction = FFAction()
    ..key = serializeInnerKey
    ..customAction = (FFCustomActionCall()
      ..customActionIdentifier = serialize.identifier.clone()
      ..argumentValues = FFFunctionCallValues())
    ..outputVariableName = serializeOutputName;

  final serializeRoot = FFActionNode(key: serializeRootKey)..action = serializeAction;

  getCtxRoot.followUpAction = serializeRoot;

  final failAlert = FFActionNode(key: serializeFailAlertKey)
    ..action = (FFAction()
      ..key = generateActionKey()
      ..alertDialog = (FFAlertDialogAction()
        ..informationalDialog = FFInformationalDialogAction(
          title: FFValue(
            inputValue: FFParameterValue(serializedValue: 'Kunne ikke fullføre'),
          ),
          message: FFValue(
            inputValue: FFParameterValue(
              serializedValue:
                  'Handlekurven er tom eller serialisering feilet. Prøv igjen.',
            ),
          ),
          dismissText: FFValue(
            inputValue: FFParameterValue(serializedValue: 'OK'),
          ),
        )));

  final completeArgs = _completeDeferredArgumentValues(
    actionOutputHostKey: actionOutputHostKey,
    completeDef: completeDef,
    completePos: completePos,
    completePosArgs: completePosArgs,
    calendarPickupArg: calendarPickupArg,
    getDeferredInnerKey: getDefInnerKey,
    serializeInnerKey: serializeInnerKey,
    tapContextOutputName: tapContextOutputName,
    serializeOutputName: serializeOutputName,
  );

  final completeAction = FFAction()
    ..key = completeInnerKey
    ..customAction = (FFCustomActionCall()
      ..customActionIdentifier = completeDef.identifier.clone()
      ..argumentValues = completeArgs)
    ..outputVariableName = completeOutputName;

  final completeRoot = FFActionNode(key: completeRootKey)..action = completeAction;

  completeRoot.followUpAction = _buildDeferredCompleteSuccessFollowUp(
    project: project,
    actionOutputHostKey: actionOutputHostKey,
    completeInnerKey: completeInnerKey,
    completeOutputName: completeOutputName,
  );

  final afterSerializeCond = FFConditionActions(
    key: generateActionKey(),
    hasMultiConditions: false,
    falseAction: failAlert,
    trueActions: [
      FFConditionActions_FFTrueConditionAction(
        condition: FFActionCondition(
          variable: _actionOutputJsonPath(
            actionOutputHostKey: actionOutputHostKey,
            innerActionKey: serializeInnerKey,
            outputName: serializeOutputName,
            jsonPath: r'$.success',
          ),
        ),
        trueAction: completeRoot,
      ),
    ],
  );

  serializeRoot.followUpAction = FFActionNode(key: afterSerializeCondKey)
    ..conditionActions = afterSerializeCond;

  return FFTriggerActions(
    rootAction: getCtxRoot,
    trigger: FFActionTrigger(triggerType: FFActionTriggerType.ON_TAP),
  );
}

FFFunctionCallValues _completeDeferredArgumentValues({
  required String actionOutputHostKey,
  required FFCustomAction completeDef,
  required FFCustomAction completePos,
  required FFFunctionCallValues completePosArgs,
  required FFFunctionCallValues_FFArgument? calendarPickupArg,
  required String getDeferredInnerKey,
  required String serializeInnerKey,
  required String tapContextOutputName,
  required String serializeOutputName,
}) {
  final args = FFFunctionCallValues()..arguments.addAll({});

  String defArg(String name) => _argKey(completeDef, name);
  String posArg(String name) => _argKey(completePos, name);

  args.arguments[defArg('chargeId')] = FFFunctionCallValues_FFArgument(
    value: FFValue(
      variable: _actionOutputJsonPath(
        actionOutputHostKey: actionOutputHostKey,
        innerActionKey: getDeferredInnerKey,
        outputName: tapContextOutputName,
        jsonPath: r'$.resumeChargeId',
      ),
    ),
  );

  args.arguments[defArg('paymentMethodCode')] = _cloneArg(
    completePosArgs,
    posArg('paymentMethodCode'),
  );
  args.arguments[defArg('apiBaseUrl')] = _cloneArg(completePosArgs, posArg('apiBaseUrl'));
  args.arguments[defArg('authToken')] = _cloneArg(completePosArgs, posArg('authToken'));

  final defPi = defArg('paymentIntentId');
  final terminalKey = _tryArgKey(completePos, 'terminalPaymentResult');
  if (terminalKey != null && completePosArgs.arguments.containsKey(terminalKey)) {
    args.arguments[defPi] = _cloneArg(completePosArgs, terminalKey);
  } else {
    args.arguments[defPi] = FFFunctionCallValues_FFArgument(
      value: FFValue(
        variable: FFVariable(
          source: FFVariableSource.CONSTANTS,
          baseVariable: FFBaseVariable(
            constants: FFConstantsVariable(
              value: FFConstantsVariable_ConstantValue.EMPTY_STRING,
            ),
          ),
        ),
      ),
    );
  }

  final metaKey = posArg('additionalMetadataJson');
  if (completePosArgs.arguments.containsKey(metaKey)) {
    args.arguments[defArg('additionalMetadataJson')] = _cloneArg(
      completePosArgs,
      metaKey,
    );
  } else {
    args.arguments[defArg('additionalMetadataJson')] = FFFunctionCallValues_FFArgument(
      value: FFValue(
        variable: FFVariable(
          source: FFVariableSource.CONSTANTS,
          baseVariable: FFBaseVariable(
            constants: FFConstantsVariable(
              value: FFConstantsVariable_ConstantValue.EMPTY_STRING,
            ),
          ),
        ),
      ),
    );
  }

  final defPickupKey = _tryArgKey(completeDef, 'estimatedPickupDate');
  if (defPickupKey != null && calendarPickupArg != null) {
    args.arguments[defPickupKey] = calendarPickupArg.clone();
  }

  final cartKey = _tryArgKey(completeDef, 'cartJson');
  if (cartKey != null) {
    args.arguments[cartKey] = FFFunctionCallValues_FFArgument(
      value: FFValue(
        variable: _actionOutputJsonPath(
          actionOutputHostKey: actionOutputHostKey,
          innerActionKey: serializeInnerKey,
          outputName: serializeOutputName,
          jsonPath: r'$.cartJson',
        ),
      ),
    );
  }

  return args;
}

FFFunctionCallValues_FFArgument _cloneArg(FFFunctionCallValues src, String argKey) {
  final a = src.arguments[argKey];
  if (a == null) {
    throw StateError('completePosPurchase missing argument key $argKey');
  }
  return a.clone();
}

FFFunctionCallValues? _completePosPurchaseArgumentValues(FFNode button) {
  for (final t in button.triggerActions) {
    if (!t.hasTrigger() ||
        t.trigger.triggerType != FFActionTriggerType.ON_TAP) {
      continue;
    }
    if (!t.hasRootAction() || !t.rootAction.hasAction()) {
      continue;
    }
    final a = t.rootAction.action;
    if (a.whichAction() != FFAction_Action.customAction) {
      continue;
    }
    if (a.customAction.customActionIdentifier.name != 'completePosPurchase') {
      continue;
    }
    return a.customAction.argumentValues;
  }
  return null;
}

/// Same binding as deferred **completePosPurchase** (`calendarSelectedDay?.start`).
FFFunctionCallValues_FFArgument? _checkoutCalendarPickupArg({
  required FFWidgetClass checkout,
  required FFCustomAction completePos,
  required String checkoutRootKey,
}) {
  final fromExisting = _calendarPickupDateArgFromCheckout(checkout, completePos);
  if (fromExisting != null) {
    return fromExisting;
  }

  final fieldId = _findWidgetStateFieldId(checkout, 'calendarSelectedDay');
  if (fieldId == null) {
    return null;
  }

  final variable = FFVariable(
    source: FFVariableSource.LOCAL_STATE,
    baseVariable: FFBaseVariable(
      localState: FFLocalStateVariable(
        fieldIdentifier: fieldId,
        stateVariableType: FFStateVariableType.WIDGET_CLASS_STATE,
      ),
    ),
    operations: [
      FFVariableOperation(dateTimeRangeStart: FFDateTimeRangeStart()),
    ],
    nodeKeyRef: FFNodeKeyReference(key: checkoutRootKey),
  );

  return FFFunctionCallValues_FFArgument(value: FFValue(variable: variable));
}

FFFunctionCallValues_FFArgument? _calendarPickupDateArgFromCheckout(
  FFWidgetClass checkout,
  FFCustomAction completePos,
) {
  final pickupKey = _tryArgKey(completePos, 'estimatedPickupDate');
  if (pickupKey == null) {
    return null;
  }

  final actions = allProtosOfType<FFAction>(
    checkout,
    recurseOnNodes: true,
    recurseOnVariables: true,
  );

  for (final ffAction in actions) {
    if (ffAction.whichAction() != FFAction_Action.customAction) {
      continue;
    }
    final call = ffAction.customAction;
    if (call.customActionIdentifier.name != completePos.identifier.name) {
      continue;
    }

    final arg = call.argumentValues.arguments[pickupKey];
    if (arg == null || !arg.hasValue() || !arg.value.hasVariable()) {
      continue;
    }

    final variable = arg.value.variable;
    if (!variable.operations.any((op) => op.hasDateTimeRangeStart())) {
      continue;
    }

    if (variable.source == FFVariableSource.WIDGET_STATE ||
        variable.source == FFVariableSource.LOCAL_STATE) {
      return arg.clone();
    }
  }

  return null;
}

FFIdentifier? _findWidgetStateFieldId(FFWidgetClass checkout, String name) {
  final model = checkout.ensureClassModel();
  for (final sf in model.stateFields) {
    if (sf.parameter.identifier.name == name) {
      return sf.parameter.identifier;
    }
  }
  return null;
}

String _argKey(FFCustomAction action, String name) {
  for (final p in action.arguments) {
    if (p.identifier.name == name) {
      return p.identifier.key;
    }
  }
  throw StateError('Custom action has no argument "$name".');
}

String? _tryArgKey(FFCustomAction action, String name) {
  for (final p in action.arguments) {
    if (p.identifier.name == name) {
      return p.identifier.key;
    }
  }
  return null;
}

FFVariable _actionOutputJsonPath({
  required String actionOutputHostKey,
  required String innerActionKey,
  required String outputName,
  required String jsonPath,
}) {
  return FFVariable(
    source: FFVariableSource.ACTION_OUTPUTS,
    baseVariable: FFBaseVariable(
      actionOutput: FFActionOutputVariable(
        outputVariableIdentifier: FFIdentifier(name: outputName),
        actionKeyRef: FFActionKeyReference(key: innerActionKey),
      ),
    ),
    operations: [
      FFVariableOperation(
        jsonPathOperation: FFJsonPathOperation(jsonPath: jsonPath),
      ),
    ],
    nodeKeyRef: FFNodeKeyReference(key: actionOutputHostKey),
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
Wire checkoutFlow: duplicate Fullfør handel + deferred complete path + visibility.

Usage (from positiv_flutterflow_ai/):
  dart run dsl/wire_checkoutflow_deferred_pay_branch.dart [options]

Options:
  --api-key <key>           FlutterFlow AI API key (or FLUTTERFLOW_AI_API_KEY / FF_API_KEY).
  --project-id <id>         Default: pointofsale-xrlz5i
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
