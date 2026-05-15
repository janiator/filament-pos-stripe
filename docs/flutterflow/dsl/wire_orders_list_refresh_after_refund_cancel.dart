library;

import 'dart:io';

import 'package:flutterflow_ai/src/internal_sdk.dart';

/// After a successful refund (receipt/print chain) or order cancel on the
/// `orders` page, trigger **Rebuild containing page** so embedded `orderList`
/// backend queries run again and statuses update (POSitiv `pointofsale-xrlz5i`).
///
/// `refreshDatabaseRequest` is not used here: the purchases ListViews live on
/// the `orderList` component template and are not addressable from page-scoped
/// actions in the FlutterFlow graph.
///
/// Run from **positiv_flutterflow_ai** (copy this file to `dsl/` there), same as
/// other POSitiv DSL entrypoints.
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          _wireOrdersListRefresh(project);
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
          'fix(pos): refresh orders list after refund or cancel',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

FFActionNode _containingPageRebuildChain() {
  final rebuildAction = FFAction(
    key: generateActionKey(),
    rebuild: FFRebuildAction(
      updateType: FFRebuildAction_UpdateType.CONTAINING_PAGE,
    ),
  );
  return FFActionNode(
    key: generateActionKey(),
    action: rebuildAction,
  );
}

bool _startsWithContainingPageRebuild(FFActionNode? node) {
  if (node == null || !node.hasAction() || !node.action.hasRebuild()) {
    return false;
  }
  return node.action.rebuild.updateType ==
      FFRebuildAction_UpdateType.CONTAINING_PAGE;
}

/// Deepest node in the linear `followUpAction` chain starting at [head].
FFActionNode _deepestFollowUp(FFActionNode head) {
  var cur = head;
  while (cur.hasFollowUpAction()) {
    cur = cur.followUpAction;
  }
  return cur;
}

/// Last action after successful `processOrderRefund` (Get receipt → Print → …).
FFActionNode? _findRefundPostSuccessTail(FFActionNode refundButtonRoot) {
  if (!refundButtonRoot.hasAction() ||
      !refundButtonRoot.action.hasCustomAction() ||
      refundButtonRoot.action.customAction.customActionIdentifier.name !=
          'processOrderRefund') {
    return null;
  }
  if (!refundButtonRoot.hasFollowUpAction()) {
    return null;
  }
  final afterRefund = refundButtonRoot.followUpAction;
  if (!afterRefund.hasConditionActions()) {
    return null;
  }
  final successBranches = afterRefund.conditionActions.trueActions;
  if (successBranches.isEmpty || !successBranches.first.hasTrueAction()) {
    return null;
  }
  final inner = successBranches.first.trueAction;
  if (!inner.hasFollowUpAction()) {
    return null;
  }
  return _deepestFollowUp(inner.followUpAction);
}

/// `Button_o4l03uxp` opens an alert; the cancel API lives under the custom
/// dialog's `onConfirm` callback, not directly under the button root.
FFActionNode? _cancelOrderCallbackRoot(FFActionNode cancelButtonRoot) {
  if (!cancelButtonRoot.hasAction() ||
      !cancelButtonRoot.action.hasAlertDialog()) {
    return null;
  }
  final dialog = cancelButtonRoot.action.alertDialog;
  if (dialog.whichAlert() != FFAlertDialogAction_Alert.customDialog) {
    return null;
  }
  final custom = dialog.customDialog;
  if (!custom.hasPassedParameters()) {
    return null;
  }
  for (final pass in custom.passedParameters.parameterPasses.values) {
    if (!pass.hasParamIdentifier()) {
      continue;
    }
    if (pass.paramIdentifier.name != 'onConfirm') {
      continue;
    }
    if (!pass.hasAction()) {
      continue;
    }
    final trigger = pass.action;
    if (!trigger.hasRootAction()) {
      continue;
    }
    return trigger.rootAction;
  }
  return null;
}

/// Rebuild widget action after successful "Cancel order" API call.
FFActionNode? _findCancelOrderSuccessRebuild(FFActionNode cancelCallbackRoot) {
  FFActionNode? result;

  void visit(FFActionNode n) {
    if (result != null) {
      return;
    }
    if (n.hasAction() && n.action.hasDatabase()) {
      final db = n.action.database;
      if (db.hasApiCall()) {
        final name = db.apiCall.endpointIdentifier.name;
        if (name == 'Cancel order' && n.hasFollowUpAction()) {
          final fu = n.followUpAction;
          if (fu.hasConditionActions()) {
            final branches = fu.conditionActions.trueActions;
            if (branches.isNotEmpty && branches.first.hasTrueAction()) {
              final t = branches.first.trueAction;
              if (t.hasAction() && t.action.hasRebuild()) {
                result = t;
                return;
              }
            }
          }
        }
      }
    }
    if (n.hasFollowUpAction()) {
      visit(n.followUpAction);
    }
    if (n.hasConditionActions()) {
      final ca = n.conditionActions;
      for (final b in ca.trueActions) {
        if (b.hasTrueAction()) {
          visit(b.trueAction);
        }
      }
      if (ca.hasFalseAction()) {
        visit(ca.falseAction);
      }
    }
  }

  visit(cancelCallbackRoot);
  return result;
}

void _wireOrdersListRefresh(FFProject project) {
  final page = findPage(project, name: 'orders');
  if (page == null) {
    throw StateError('FlutterFlow page "orders" not found.');
  }

  final refundButton = findByKey(page.node, 'Button_9s0uhhto');
  if (refundButton == null || refundButton.triggerActions.isEmpty) {
    throw StateError('Button_9s0uhhto (refund) not found on orders page.');
  }
  final refundRoot = refundButton.triggerActions.first.rootAction;
  final refundTail = _findRefundPostSuccessTail(refundRoot);
  if (refundTail == null) {
    throw StateError(
      'Could not locate post-refund action tail (processOrderRefund → …); '
      'FlutterFlow tree may have changed.',
    );
  }
  if (!_startsWithContainingPageRebuild(refundTail.followUpAction)) {
    if (refundTail.hasFollowUpAction()) {
      throw StateError(
        'Refund post-success tail already has a follow-up; inspect manually.',
      );
    }
    refundTail.followUpAction = _containingPageRebuildChain();
  }

  final cancelButton = findByKey(page.node, 'Button_o4l03uxp');
  if (cancelButton == null || cancelButton.triggerActions.isEmpty) {
    throw StateError('Button_o4l03uxp (cancel) not found on orders page.');
  }
  final cancelRoot = cancelButton.triggerActions.first.rootAction;
  final cancelCallbackRoot = _cancelOrderCallbackRoot(cancelRoot);
  if (cancelCallbackRoot == null) {
    throw StateError(
      'Could not locate cancel dialog onConfirm callback root; FlutterFlow tree may have changed.',
    );
  }
  final cancelRebuild = _findCancelOrderSuccessRebuild(cancelCallbackRoot);
  if (cancelRebuild == null) {
    throw StateError(
      'Could not locate rebuild after Cancel order API; FlutterFlow tree may have changed.',
    );
  }
  if (!_startsWithContainingPageRebuild(cancelRebuild.followUpAction)) {
    if (cancelRebuild.hasFollowUpAction()) {
      throw StateError(
        'Cancel success rebuild already has a follow-up; inspect manually.',
      );
    }
    cancelRebuild.followUpAction = _containingPageRebuildChain();
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
Wire orders page: rebuild containing page after refund or cancel (refreshes lists).

Usage:
  dart run dsl/wire_orders_list_refresh_after_refund_cancel.dart [options]

Run from **positiv_flutterflow_ai** (copy from pos-stripe docs/flutterflow/dsl/).

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
