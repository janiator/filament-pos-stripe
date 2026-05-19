library;

import 'dart:io';

import 'package:flutterflow_ai/flutterflow_ai.dart';

import 'flutterflow_custom_action_api_body.dart';

/// Push checkout customer + VAT fixes into FlutterFlow POSitiv (`pointofsale-xrlz5i`).
Future<void> main(List<String> args) async {
  final options = _parseCliOptions(args);
  final scriptDir = File(Platform.script.toFilePath()).parent;
  final repoRoot = scriptDir.parent.parent;
  final actionsDir = Directory('${repoRoot.path}/docs/flutterflow/custom-actions');

  final specs = <_ActionSpec>[
    _ActionSpec(
      name: 'updateCartTotals',
      file: File('${actionsDir.path}/update_cart_totals.dart'),
      description:
          'Recalculate cart totals with per-line VAT; preserve cartNote.',
    ),
    _ActionSpec(
      name: 'completePosPurchase',
      file: File('${actionsDir.path}/complete_pos_purchase.dart'),
      description:
          'Complete POS purchase; send cart customer_id; per-line tax_rate.',
    ),
    _ActionSpec(
      name: 'serializeCartForCompleteDeferred',
      file: File('${actionsDir.path}/serialize_cart_for_complete_deferred.dart'),
      description:
          'Serialize cart for deferred completion with correct tax_rate per line.',
    ),
    _ActionSpec(
      name: 'prepareParkedDeferredPurchase',
      file: File('${actionsDir.path}/prepare_parked_deferred_purchase.dart'),
      description:
          'Hydrate parked deferred cart and serialize with correct per-line VAT.',
    ),
  ];

  for (final s in specs) {
    if (!s.file.existsSync()) {
      stderr.writeln('Missing source file: ${s.file.path}');
      exit(1);
    }
  }

  try {
    await flutterFlowAI(
      (app) {
        app.raw((project) {
          for (final s in specs) {
            final code = customActionCodeForFlutterFlowApi(s.file.readAsStringSync());
            final existing = findCustomAction(project, name: s.name);
            if (existing == null) {
              throw StateError('Custom action ${s.name} not found in project.');
            }
            updateCustomAction(
              project,
              name: s.name,
              code: code,
              description: s.description,
            );
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
          'fix(pos): cart customer on terminal checkout; zero VAT rate',
    );
  } catch (error) {
    stderr.writeln('Error: ${formatFlutterFlowAIError(error)}');
    exit(1);
  }
}

final class _ActionSpec {
  const _ActionSpec({
    required this.name,
    required this.file,
    required this.description,
  });

  final String name;
  final File file;
  final String description;
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
Push checkout customer + VAT custom actions to FlutterFlow POSitiv.

Usage:
  dart run dsl/upsert_checkout_customer_vat.dart [options]
''');
}
