library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';
import 'package:flutterflow_ai/src/helpers/key_gen.dart';
import 'package:flutterflow_ai/src/helpers/tree_helpers.dart'
    show
        findByKey,
        findDescendants,
        findParentByKey,
        insertBeforeKey,
        removeChild;

/// Shows [FFAppState().cart.cartNote] in **Checkout1Products** when non-empty.
///
/// The note must be a **sibling** of [Container_5tu16qac] (icon toolbar), not a
/// child of it — FF codegen only emitted the first child when the note lived
/// inside that container and hid the icon [Row_i5ksgwba].
///
/// Idempotent: stable node name [positivCartOrderNoteText].
///
/// Run from `positiv_flutterflow_ai/`:
///   dart run dsl/wire_checkout1_products_cart_order_note.dart --commit-message "…"
const _kComponentName = 'Checkout1Products';
const _kComponentRootKey = 'Container_48yqv5xo';
const _kIconToolbarContainerKey = 'Container_5tu16qac';
const _kNoteTextStableName = 'positivCartOrderNoteText';

/// App State `cart` field keys (re-inspect if FF regenerates).
const _kCartStateKey = 'y5lauzqk';
const _kCartNoteFieldKey = 'izszy';

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
          'fix(Checkout1Products): show cart note without breaking icon row',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

void _wire(FFProject project) {
  final wc = findComponent(project, name: _kComponentName);
  if (wc == null) {
    throw StateError('Component "$_kComponentName" not found.');
  }

  final root = findByKey(wc.node, _kComponentRootKey);
  if (root == null) {
    throw StateError(
      '$_kComponentName root $_kComponentRootKey not found; re-inspect component.',
    );
  }

  final toolbar = findByKey(root, _kIconToolbarContainerKey);
  if (toolbar == null) {
    throw StateError(
      '$_kIconToolbarContainerKey not found under $_kComponentRootKey; re-inspect.',
    );
  }

  _relocateMisplacedNote(root, toolbar);

  final existing = findDescendants(
    root,
    (n) => n.name == _kNoteTextStableName,
  );
  if (existing.isNotEmpty) {
    stderr.writeln(
      '[wire_checkout1_products_cart_order_note] $_kNoteTextStableName already placed; skip.',
    );
    return;
  }

  final cartNoteVar = _cartNoteVariable();
  final noteText = _buildOrderNoteTextNode(cartNoteVar);

  final inserted = insertBeforeKey(root, _kIconToolbarContainerKey, noteText);
  if (!inserted) {
    throw StateError(
      'Failed to insert $_kNoteTextStableName before $_kIconToolbarContainerKey.',
    );
  }

  stderr.writeln(
    '[wire_checkout1_products_cart_order_note] Inserted $_kNoteTextStableName '
    'before $_kIconToolbarContainerKey (icon row preserved).',
  );
}

/// Removes note nodes wrongly nested inside the icon toolbar container.
void _relocateMisplacedNote(FFNode root, FFNode toolbar) {
  final misplaced = findDescendants(
    toolbar,
    (n) => n.name == _kNoteTextStableName,
  );
  for (final note in misplaced) {
    final parentLink = findParentByKey(root, note.key);
    if (parentLink == null) {
      continue;
    }
    removeChild(parentLink.parent, parentLink.child);
    stderr.writeln(
      '[wire_checkout1_products_cart_order_note] Removed misplaced '
      '$_kNoteTextStableName from $_kIconToolbarContainerKey.',
    );
  }
}

FFVariable _cartNoteVariable() {
  return FFVariable(
    source: FFVariableSource.LOCAL_STATE,
    baseVariable: FFBaseVariable(
      localState: FFLocalStateVariable(
        fieldIdentifier: FFIdentifier(name: 'cart', key: _kCartStateKey),
        stateVariableType: FFStateVariableType.APP_STATE,
      ),
    ),
    operations: [
      FFVariableOperation(
        accessDataStructField: FFAccessDataStructField(
          fieldIdentifier: FFIdentifier(name: 'note', key: _kCartNoteFieldKey),
        ),
      ),
    ],
  );
}

FFNode _buildOrderNoteTextNode(FFVariable cartNoteVar) {
  final visibilityVar = FFVariable(
    source: FFVariableSource.FUNCTION_CALL,
    functionCall: FFFunctionCall(
      values: [FFValue(variable: cartNoteVar)],
      condition: FFCondition(
        relation: FFCondition_Relation.EXISTS_AND_NON_EMPTY,
      ),
    ),
  );

  final text = FFText(
    textValue: FFStringValue(
      variable: cartNoteVar,
      mostRecentInputValue: 'Notat',
    ),
    themeStyle: FFText_ThemeStyle.BODY_MEDIUM,
    colorValue: FFColorValue(
      inputValue: FFColor(
        themeColor: FFColor_ThemeColor.SECONDARY_TEXT,
      ),
    ),
    fontWeightValue: FFFontWeightValue(
      inputValue: FFFontWeight.w600,
    ),
    maxLinesValue: FFIntegerValue(inputValue: 4),
    overflowReplacement: FFText_OverflowReplacement.ELLIPSIS,
  );

  return FFNode(
    key: generateNodeKey(FFWidgetType.Text),
    type: FFWidgetType.Text,
    name: _kNoteTextStableName,
    props: FFWidgetProperties(
      text: text,
      padding: FFPadding(
        type: FFPadding_PaddingType.FF_PADDING_ONLY,
        leftValue: FFDoubleValue(inputValue: 4),
        rightValue: FFDoubleValue(inputValue: 4),
        topValue: FFDoubleValue(inputValue: 0),
        bottomValue: FFDoubleValue(inputValue: 4),
      ),
      visibility: FFVisibility(
        visibleValue: FFBooleanValue(
          variable: visibilityVar,
          mostRecentInputValue: true,
        ),
      ),
    ),
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
Wire Checkout1Products: visible cart order note above icon toolbar (not inside it).

Usage (from positiv_flutterflow_ai/):
  dart run dsl/wire_checkout1_products_cart_order_note.dart [options]
''');
}
