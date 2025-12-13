// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/custom_code/widgets/index.dart'; // Imports other custom widgets
import '/custom_code/actions/index.dart'; // Imports custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom widget code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:async';
import 'dart:io';
import 'package:permission_handler/permission_handler.dart';
import 'package:mek_stripe_terminal/mek_stripe_terminal.dart';
import '/custom_code/stripe_terminal_singleton.dart';

// ────────────────────────────────────────────────────────────────
// DELEGATE MED APP-STATE-OPPDATERINGER (NORSK TEKST)
// ────────────────────────────────────────────────────────────────
class MyInternetReaderDelegate extends InternetReaderDelegate
    with ReaderDisconnectDelegate {
  void _setStatus(String status) {
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = status;
    });
  }

  @override
  void onReportAvailableUpdate(ReaderSoftwareUpdate update) {
    _setStatus('Leseroppdatering tilgjengelig');
  }

  @override
  void onStartInstallingUpdate(
      ReaderSoftwareUpdate update, Cancellable cancelUpdate) {
    _setStatus('Installerer oppdatering på terminalen…');
  }

  @override
  void onReportReaderSoftwareUpdateProgress(double progress) {
    _setStatus(
      'Oppdaterer terminal… ${(progress * 100).toStringAsFixed(0)} % fullført',
    );
  }

  @override
  void onFinishInstallingUpdate(
      ReaderSoftwareUpdate? update, TerminalException? exception) {
    if (exception != null) {
      _setStatus('Oppdatering av terminal mislyktes: ${exception.message}');
    } else {
      _setStatus('Oppdatering av terminal fullført');
    }
  }

  @override
  void onRequestReaderDisplayMessage(ReaderDisplayMessage message) {
    _setStatus('Melding fra terminal: ${message.name}');
  }

  @override
  void onRequestReaderInput(List<ReaderInputOption> options) {
    final opts = options.map((e) => e.name).join(', ');
    _setStatus('Venter på kort: $opts');
  }

  @override
  void onReportReaderEvent(ReaderEvent event) {
    _setStatus('Hendelse på terminal: ${event.name}');
  }

  @override
  void onDisconnect(DisconnectReason reason) {
    _setStatus('Frakoblet: ${reason.name}');
  }

  @override
  void onReaderReconnectStarted(
      Reader reader, Cancellable cancelReconnect, DisconnectReason reason) {
    _setStatus('Kobler til terminal på nytt…');
  }

  @override
  void onReaderReconnectFailed(Reader reader) {
    _setStatus('Gjenoppkobling til terminal mislyktes');
  }

  @override
  void onReaderReconnectSucceeded(Reader reader) {
    _setStatus(
      'Terminal tilkoblet på nytt: ${reader.label ?? reader.serialNumber}',
    );
  }
}

/// ──────────────────────────────────────────────────────────────── WIDGET
/// ────────────────────────────────────────────────────────────────
class StripeInternetTerminalReaderPickerAndConnector extends StatefulWidget {
  final String connectionToken;
  final String locationId;
  final double? width;
  final double? height;

  const StripeInternetTerminalReaderPickerAndConnector({
    Key? key,
    required this.connectionToken,
    required this.locationId,
    this.width,
    this.height,
  }) : super(key: key);

  @override
  _StripeInternetTerminalReaderPickerAndConnectorState createState() =>
      _StripeInternetTerminalReaderPickerAndConnectorState();
}

class _StripeInternetTerminalReaderPickerAndConnectorState
    extends State<StripeInternetTerminalReaderPickerAndConnector> {
  // Readers
  List<Reader> _internetReaders = [];

  // Discovery subscription
  StreamSubscription<List<Reader>>? _inetSub;

  // UI state
  bool _loading = true;
  String? _error;
  String? _connectionStatus;

  // ─────────────────────────────────────────────────────────────
  // HJELPERE FOR Å SYNKE MED APP STATE
  // ─────────────────────────────────────────────────────────────
  void _setConnectionStatus(String? status) {
    final connected = status != null && status.startsWith('Tilkoblet:');
    setState(() => _connectionStatus = status);
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = status ?? '';
      FFAppState().stripeReaderConnected = connected;
    });
  }

  void _setError(String message) {
    setState(() => _error = message);
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Feil: $message';
      FFAppState().stripeReaderConnected = false;
    });
  }

  void _setAppStatus(String message) {
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = message;
      // status-only, no change to stripeReaderConnected here
    });
  }

  // ─────────────────────────────────────────────────────────────
  // LIFECYCLE
  // ─────────────────────────────────────────────────────────────
  @override
  void initState() {
    super.initState();
    // Utsett initialisering til etter første build
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initTerminalAndDiscover();
    });
  }

  @override
  void dispose() {
    _inetSub?.cancel();
    super.dispose();
  }

  // ─────────────────────────────────────────────────────────────
  // DISCOVERY HELPERS
  // ─────────────────────────────────────────────────────────────
  Future<List<Reader>> _discoverOnce(InternetDiscoveryConfiguration cfg) async {
    final completer = Completer<List<Reader>>();

    _inetSub = Terminal.instance.discoverReaders(cfg).listen(
          completer.complete,
          onError: completer.completeError,
          cancelOnError: true,
        );

    final res = await completer.future;
    await _inetSub?.cancel();
    _inetSub = null;
    return res;
  }

  Future<void> _cancelDiscovery() async {
    await _inetSub?.cancel();
    _inetSub = null;
  }

  // ─────────────────────────────────────────────────────────────
  // INIT + DISCOVER
  // ─────────────────────────────────────────────────────────────
  Future<void> _initTerminalAndDiscover() async {
    setState(() {
      _loading = true;
      _error = null;
      _internetReaders = [];
      _connectionStatus = null;
    });

    _setAppStatus('Initialiserer Stripe Terminal…');

    // Tillatelser
    final List<Permission> permissions = [
      Permission.locationWhenInUse,
      Permission.bluetooth,
      if (Platform.isAndroid) ...[
        Permission.bluetoothScan,
        Permission.bluetoothConnect,
      ],
    ];

    await permissions.request();

    // Init SDK én gang
    try {
      await StripeTerminalSingleton.instance
          .ensureInit(() async => widget.connectionToken);
    } catch (e) {
      _setError('Kunne ikke initialisere Stripe Terminal: $e');
      setState(() => _loading = false);
      return;
    }

    // Allerede tilkoblet?
    final current = await Terminal.instance.getConnectedReader();
    if (current != null) {
      _setConnectionStatus(
        'Tilkoblet: ${current.label ?? current.serialNumber}',
      );
      setState(() => _loading = false);
      return;
    }

    await _discoverReaders();
  }

  Future<void> _discoverReaders() async {
    setState(() {
      _loading = true;
      _error = null;
      _internetReaders = [];
    });

    _setAppStatus('Søker etter terminaler…');

    try {
      _internetReaders = await _discoverOnce(
        InternetDiscoveryConfiguration(
          locationId: widget.locationId,
          isSimulated: false,
        ),
      );

      setState(() => _loading = false);

      if (_internetReaders.isEmpty) {
        _setAppStatus('Fant ingen terminaler');
      } else {
        _setAppStatus(
          'Fant ${_internetReaders.length} terminal(er)',
        );
      }
    } catch (e) {
      _setError('Søk etter terminaler mislyktes: $e');
      setState(() => _loading = false);
    }
  }

  // ─────────────────────────────────────────────────────────────
  // CONNECT
  // ─────────────────────────────────────────────────────────────
  Future<void> _connectToReader(Reader reader) async {
    await _cancelDiscovery();

    _setConnectionStatus('Kobler til terminal…');

    setState(() {
      _error = null;
      _loading = true;
    });

    try {
      final current = await Terminal.instance.getConnectedReader();
      if (current != null) {
        await Terminal.instance.disconnectReader();
        await Future.delayed(const Duration(seconds: 1));
      }

      await Terminal.instance.connectReader(
        reader,
        configuration: InternetConnectionConfiguration(
          readerDelegate: MyInternetReaderDelegate(),
        ),
      );

      if (!mounted) return;

      _setConnectionStatus(
        'Tilkoblet: ${reader.label ?? reader.serialNumber}',
      );
      
      setState(() => _loading = false);
    } catch (e) {
      if (!mounted) return;
      _setConnectionStatus(null);
      _setError('Tilkobling mislyktes: $e');
      setState(() => _loading = false);
    }
  }

  // ─────────────────────────────────────────────────────────────
  // RESCAN
  // ─────────────────────────────────────────────────────────────
  Future<void> _handleRescan() async {
    _setAppStatus('Søker etter terminaler på nytt…');
    await _cancelDiscovery();

    final connected = await Terminal.instance.getConnectedReader();
    if (connected != null) {
      await Terminal.instance.disconnectReader();
    }

    await _initTerminalAndDiscover();
  }

  // ─────────────────────────────────────────────────────────────
  // UI
  // ─────────────────────────────────────────────────────────────
  Widget _buildReaderList() {
    if (_internetReaders.isEmpty) return const SizedBox();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Internett-tilkoblede terminaler',
          style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey),
        ),
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: _internetReaders.length,
          separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (context, i) {
            final r = _internetReaders[i];
            return ListTile(
              title: Text(r.label ?? r.serialNumber ?? 'Ukjent terminal'),
              subtitle: Text(r.serialNumber ?? ''),
              onTap: () => _connectToReader(r),
            );
          },
        ),
        const SizedBox(height: 16),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());

    if (_error != null) return Center(child: Text('Feil: $_error'));

    if (_connectionStatus != null) {
      return Column(
        children: [
          Text(
            _connectionStatus!,
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          ElevatedButton(
            onPressed: _handleRescan,
            child: const Text('Søk på nytt'),
          ),
        ],
      );
    }

    if (_internetReaders.isEmpty) {
      return Column(
        children: [
          const Text('Fant ingen Stripe-terminaler.'),
          ElevatedButton(
            onPressed: _handleRescan,
            child: const Text('Søk på nytt'),
          ),
        ],
      );
    }

    return SingleChildScrollView(child: _buildReaderList());
  }
}
