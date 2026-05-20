library;

import 'dart:io';

import 'package:flutterflow_ai/src/helpers/tree_helpers.dart';
import 'package:flutterflow_ai/src/internal_sdk.dart';

/// Fixes deferredPaymentCheckout component state after parked-order checkout:
/// - Stripe spinner amount reads current FFAppState.cart total, not stale purchase.amount.
/// - Stripe callback advances the component to step 3 after success.
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          final wc = findComponent(project, name: 'deferredPaymentCheckout');
          if (wc == null) {
            throw StateError('Component deferredPaymentCheckout not found.');
          }

          final checkoutFlow = findComponent(project, name: 'checkoutFlow');
          if (checkoutFlow == null) {
            throw StateError('Component checkoutFlow not found.');
          }
          final cartTotalVariable = _findCheckoutCartTotalVariable(
            checkoutFlow,
          );
          if (cartTotalVariable == null) {
            throw StateError('Could not find existing cart total variable.');
          }
          var patchedAmount = false;
          var patchedStep = false;

          for (final node in [wc.node, ...flattenTree(wc.node)]) {
            if (node.hasParameterValues()) {
              for (final pass in node.parameterValues.parameterPasses.values) {
                if (pass.paramIdentifier.name == 'amount') {
                  pass.clearValue();
                  pass.variable =
                      cartTotalVariable.deepCopy()
                        ..nodeKeyRef = FFNodeKeyReference(key: wc.node.key);
                  patchedAmount = true;
                }
              }
            }

            for (final triggerActions in node.triggerActions) {
              final trigger = triggerActions.trigger;
              if (trigger.triggerType == FFActionTriggerType.CALLBACK &&
                  trigger.parameterIdentifier.name ==
                      'paymentFinishedCallback') {
                patchedStep =
                    _appendCurrentStepThree(
                      project,
                      triggerActions.rootAction,
                    ) ||
                    patchedStep;
              }
            }
          }

          if (!patchedAmount) {
            throw StateError(
              'Did not find CheckoutStatus amount binding to patch.',
            );
          }
          if (!patchedStep) {
            throw StateError(
              'Did not find Stripe paymentFinishedCallback step transition to patch.',
            );
          }

          project.widgetClasses[wc.node.key] = wc;
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
          'fix(pos): repair deferred stripe checkout state',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

FFVariable? _findCheckoutCartTotalVariable(FFWidgetClass checkoutFlow) {
  for (final node in [checkoutFlow.node, ...flattenTree(checkoutFlow.node)]) {
    if (!node.hasParameterValues()) {
      continue;
    }

    for (final pass in node.parameterValues.parameterPasses.values) {
      if (pass.paramIdentifier.name == 'amount' && pass.hasVariable()) {
        final encoded = pass.variable.writeToJson();
        if (encoded.contains('cartTotalCartPrice')) {
          return pass.variable;
        }
      }
    }
  }

  return null;
}

bool _appendCurrentStepThree(FFProject project, FFActionNode node) {
  var patched = false;
  if (node.hasConditionActions()) {
    for (final branch in node.conditionActions.trueActions) {
      if (!branch.hasTrueAction()) {
        continue;
      }

      final trueAction = branch.trueAction;
      if (!trueAction.hasFollowUpAction()) {
        trueAction.followUpAction = FFActionNode(
          key: generateActionKey(),
          action: Actions.updatePageState(
            project,
            widgetClassName: 'deferredPaymentCheckout',
            updates: const [StateFieldUpdate.set('currentStep', '3')],
          ),
        );
        patched = true;
      } else {
        patched =
            _appendCurrentStepThree(project, trueAction.followUpAction) ||
            patched;
      }
    }
    if (node.conditionActions.hasFalseAction()) {
      patched =
          _appendCurrentStepThree(project, node.conditionActions.falseAction) ||
          patched;
    }
  }

  if (node.hasFollowUpAction()) {
    patched = _appendCurrentStepThree(project, node.followUpAction) || patched;
  }

  return patched;
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
Fix deferredPaymentCheckout amount and step transition.

Usage:
  dart run dsl/fix_deferred_payment_checkout_state.dart [options]

Options:
  --api-key <key>           FlutterFlow API key. Defaults to FLUTTERFLOW_AI_API_KEY or FF_API_KEY.
  --base-url <url>          Override the FlutterFlow API base URL.
  --project-id <id>         Default: pointofsale-xrlz5i or FLUTTERFLOW_POSITIV_PROJECT_ID.
  --commit-message <text>   Commit message for the push.
  --dry-run                 Validate without pushing.
  --help, -h                Show this help.
''');
}
