library;

// ignore_for_file: deprecated_member_use

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/data_type_helpers.dart'
    show boolType, stringType;
import 'package:flutterflow_ai/src/helpers/ensure_helpers.dart';
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show findByKey, findDescendants;
import 'package:flutterflow_ai/src/helpers/variable_helpers.dart'
    show varFromAppState;
import 'package:flutterflow_ai/src/helpers/widget_class_param_helpers.dart'
    as wc_param;

/// Adds **FFAppState** fields `deferredResumeBannerText` / `deferredResumeBannerActive`,
/// rebinds the **pos** deferred banner **Text** (DSL key `6sy7nlgg`) from page state
/// to **App State**, and rewires **checkoutFlow**'s `deferredResumeBannerActive`
/// component parameter on **pos** / **posSession** from page state → app state.
///
/// Matches custom actions in `generated_code/lib/custom_code/actions/` that call
/// `mirrorDeferredResumeBannerToAppStateIfPresent`.
///
/// Idempotent. Re-inspect **pos** if FlutterFlow regens the banner Text key.
///
/// Run from `positiv_flutterflow_ai/`:
///   dart run dsl/wire_pos_deferred_resume_banner_app_state.dart
///
/// Then pull custom code so `app_state.dart` includes the new fields.

const _kPosPageName = 'pos';
const _kPosPagesHostingCheckout = <String>['pos', 'posSession'];
const _kBannerTextWidgetKey = '6sy7nlgg';
const _kCheckoutComponentName = 'checkoutFlow';
const _kCheckoutDeferredResumeParamName = 'deferredResumeBannerActive';

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
          'feat(pos): deferred resume banner → FFAppState + checkoutFlow param',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void _wire(FFProject project) {
  final textFieldId = ensureAppStateField(
    project,
    name: 'deferredResumeBannerText',
    type: stringType,
    description:
        'Parked deferred resume banner line (e.g. Ordre …). Synced by custom actions.',
    persisted: false,
  );
  final activeFieldId = ensureAppStateField(
    project,
    name: 'deferredResumeBannerActive',
    type: boolType,
    description:
        'True when parked deferred resume is active. Synced by custom actions.',
    persisted: false,
  );

  final pos = findPage(project, name: _kPosPageName);
  if (pos == null) {
    throw StateError('Page "$_kPosPageName" not found.');
  }

  final banner = findByKey(pos.node, _kBannerTextWidgetKey);
  if (banner == null) {
    stderr.writeln(
      '[wire_pos_deferred_resume_banner_app_state] Text widget '
      '$_kBannerTextWidgetKey not found on pos — re-run '
      'wire_pos_deferred_resume_banner.dart or update _kBannerTextWidgetKey '
      'from inspect.',
    );
  } else {
    if (banner.type != FFWidgetType.Text) {
      throw StateError(
        'Node $_kBannerTextWidgetKey is ${banner.type}, expected Text.',
      );
    }
    banner.props.text.textValue = FFStringValue(
      variable: varFromAppState(textFieldId),
    );
    final vis = banner.props.ensureVisibility();
    vis.visibleValue = FFBooleanValue(
      variable: varFromAppState(activeFieldId),
      mostRecentInputValue: true,
    );
    stderr.writeln(
      '[wire_pos_deferred_resume_banner_app_state] pos banner Text '
      '$_kBannerTextWidgetKey → App State (text + visibility).',
    );
  }

  final checkout = findComponent(project, name: _kCheckoutComponentName);
  if (checkout == null) {
    stderr.writeln(
      '[wire_pos_deferred_resume_banner_app_state] Component '
      '$_kCheckoutComponentName not found; skipped checkout param rewire.',
    );
    return;
  }

  final checkoutParamId = _checkoutDeferredResumeParamIdentifier(project);
  if (checkoutParamId == null) {
    stderr.writeln(
      '[wire_pos_deferred_resume_banner_app_state] checkoutFlow has no '
      '$_kCheckoutDeferredResumeParamName param; run '
      'wire_checkoutflow_deferred_pay_branch.dart first.',
    );
    return;
  }

  final passKey = checkoutParamId.key;
  final appStateVar = varFromAppState(activeFieldId);
  var wired = 0;
  for (final pageName in _kPosPagesHostingCheckout) {
    final page = findPage(project, name: pageName);
    if (page == null) {
      continue;
    }
    final scaffold = page.node;
    final instances = findDescendants(
      scaffold,
      (n) => _isCheckoutFlowInstanceOnPage(n, checkout),
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
          existing.whichValue() == FFParameterPass_Value.variable &&
          _variableIsAppStateBinding(
            existing.variable,
            fieldKey: activeFieldId.key,
          )) {
        continue;
      }

      pv.parameterPasses[passKey] = FFParameterPass(
        paramIdentifier: checkoutParamId.clone(),
        variable: appStateVar.clone(),
      );
      wired++;
      stderr.writeln(
        '[wire_pos_deferred_resume_banner_app_state] page "$pageName": '
        'checkoutFlow ${inst.key} ← App State.$_kCheckoutDeferredResumeParamName.',
      );
    }
  }

  if (wired == 0) {
    stderr.writeln(
      '[wire_pos_deferred_resume_banner_app_state] No checkoutFlow instances '
      'to rewire on ${_kPosPagesHostingCheckout.join(", ")}.',
    );
  }
}

FFIdentifier? _checkoutDeferredResumeParamIdentifier(FFProject project) {
  final existing = wc_param.listComponentParameters(
    project,
    componentName: _kCheckoutComponentName,
  );
  for (final p in existing) {
    if (p.name == _kCheckoutDeferredResumeParamName) {
      return FFIdentifier(name: p.name, key: p.key);
    }
  }
  return null;
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
Wire pos deferred resume banner to FFAppState + checkoutFlow param from app state.

Usage (from positiv_flutterflow_ai/):
  dart run dsl/wire_pos_deferred_resume_banner_app_state.dart [options]

Options:
  --api-key <key>           FlutterFlow AI API key.
  --project-id <id>         Default: pointofsale-xrlz5i.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
