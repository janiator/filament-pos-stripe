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
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';
import 'package:mek_stripe_terminal/mek_stripe_terminal.dart';
import '/custom_code/stripe_terminal_singleton.dart';

class MyInternetReaderDelegate extends InternetReaderDelegate with ReaderDisconnectDelegate {
  void _setStatus(String status) {
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = status;
    });
  }

  @override
  void onReportAvailableUpdate(ReaderSoftwareUpdate update) => _setStatus('Leseroppdatering tilgjengelig');

  @override
  void onStartInstallingUpdate(ReaderSoftwareUpdate update, Cancellable cancelUpdate) =>
      _setStatus('Installerer oppdatering på terminalen…');

  @override
  void onReportReaderSoftwareUpdateProgress(double progress) =>
      _setStatus('Oppdaterer terminal… ${(progress * 100).toStringAsFixed(0)} % fullført');

  @override
  void onRequestReaderDisplayMessage(ReaderDisplayMessage message) => _setStatus('Melding fra terminal: ${message.name}');

  @override
  void onRequestReaderInput(List<ReaderInputOption> options) =>
      _setStatus('Venter på kort: ${options.map((e) => e.name).join(', ')}');

  @override
  void onReportReaderEvent(ReaderEvent event) => _setStatus('Hendelse på terminal: ${event.name}');

  @override
  void onDisconnect(DisconnectReason reason) => _setStatus('Frakoblet: ${reason.name}');

  @override
  void onReaderReconnectStarted(Reader reader, Cancellable cancelReconnect, DisconnectReason reason) =>
      _setStatus('Kobler til terminal på nytt…');

  @override
  void onReaderReconnectFailed(Reader reader) => _setStatus('Gjenoppkobling til terminal mislyktes');

  @override
  void onReaderReconnectSucceeded(Reader reader) =>
      _setStatus('Terminal tilkoblet på nytt: ${reader.label ?? reader.serialNumber}');
}

class StripeInternetTerminalReaderPickerAndConnector extends StatefulWidget {
  final String connectionToken;
  final String locationId;
  final double? width;
  final double? height;

  /// Optional: used for auto-connect if found among discovered readers.
  final String? preferredReaderStripeId;
  final bool autoconnect;

  /// Optional: save last-connected terminal for this POS device.
  final int? posDeviceId;
  final int? selectedLocationInternalId;
  final String? apiBaseUrl;
  final String? authToken;
  final String? storeSlug;

  const StripeInternetTerminalReaderPickerAndConnector({
    Key? key,
    required this.connectionToken,
    required this.locationId,
    this.width,
    this.height,
    this.preferredReaderStripeId,
    this.autoconnect = true,
    this.posDeviceId,
    this.selectedLocationInternalId,
    this.apiBaseUrl,
    this.authToken,
    this.storeSlug,
  }) : super(key: key);

  @override
  State<StripeInternetTerminalReaderPickerAndConnector> createState() =>
      _StripeInternetTerminalReaderPickerAndConnectorState();
}

class _StripeInternetTerminalReaderPickerAndConnectorState
    extends State<StripeInternetTerminalReaderPickerAndConnector> {
  static const Duration _initTimeout = Duration(seconds: 20);
  static const Duration _discoverTimeout = Duration(seconds: 25);

  List<Reader> _internetReaders = [];
  StreamSubscription<List<Reader>>? _inetSub;

  bool _loading = true;
  String? _error;
  String? _connectionStatus;

  String? _activeToken;
  String? _activeLocationId;

  String get _apiRoot {
    final b = widget.apiBaseUrl?.trim() ?? '';
    if (b.isEmpty) return b;
    final base = b.endsWith('/') ? b.substring(0, b.length - 1) : b;
    return base.endsWith('/api') ? base : '$base/api';
  }

  bool get _hasApiCreds {
    final base = _apiRoot;
    return base.isNotEmpty &&
        (widget.authToken?.trim().isNotEmpty ?? false) &&
        (widget.storeSlug?.trim().isNotEmpty ?? false);
  }

  /// When apiBaseUrl, authToken, storeSlug and selectedLocationInternalId are set,
  /// fetch a fresh connection token from the API (FlutterFlow cannot pass callbacks).
  Future<Map<String, String>?> _fetchFreshTokenFromApi() async {
    if (!_hasApiCreds || widget.selectedLocationInternalId == null) return null;
    try {
      final uri = Uri.parse('$_apiRoot/stores/${widget.storeSlug!.trim()}/terminal/connection-token');
      final resp = await http.post(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken!.trim()}',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({'location_id': widget.selectedLocationInternalId}),
      );
      if (resp.statusCode != 200 || !mounted) return null;
      final data = jsonDecode(resp.body) as Map<String, dynamic>?;
      final token = (data?['secret']?.toString() ?? '').trim();
      final locationId = (data?['location']?.toString() ?? '').trim();
      if (token.isEmpty || locationId.isEmpty) return null;
      return {'token': token, 'locationId': locationId};
    } catch (_) {
      return null;
    }
  }

  void _setConnectionStatus(String? status) {
    final connected = status != null && status.startsWith('Tilkoblet:');
    if (mounted) setState(() => _connectionStatus = status);
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = status ?? '';
      FFAppState().stripeReaderConnected = connected;
    });
  }

  void _setError(String message) {
    if (mounted) setState(() => _error = message);
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = 'Feil: $message';
      FFAppState().stripeReaderConnected = false;
    });
  }

  void _setAppStatus(String message) {
    FFAppState().update(() {
      FFAppState().stripeReaderStatus = message;
    });
  }

  @override
  void initState() {
    super.initState();
    _activeToken = widget.connectionToken;
    _activeLocationId = widget.locationId;
    WidgetsBinding.instance.addPostFrameCallback((_) => _initTerminalAndDiscover(requestFreshToken: true));
  }

  @override
  void didUpdateWidget(covariant StripeInternetTerminalReaderPickerAndConnector oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.connectionToken != widget.connectionToken || oldWidget.locationId != widget.locationId) {
      _activeToken = widget.connectionToken;
      _activeLocationId = widget.locationId;
      _initTerminalAndDiscover(requestFreshToken: false);
    }
  }

  @override
  void dispose() {
    _inetSub?.cancel();
    super.dispose();
  }

  Future<bool> _refreshToken() async {
    final fresh = await _fetchFreshTokenFromApi();
    if (!mounted || fresh == null) return false;

    final token = fresh['token']?.trim() ?? '';
    final locationId = fresh['locationId']?.trim() ?? '';
    if (token.isEmpty || locationId.isEmpty) return false;

    if (mounted) {
      setState(() {
        _activeToken = token;
        _activeLocationId = locationId;
      });
    }
    return true;
  }

  Future<void> _cancelDiscovery() async {
    await _inetSub?.cancel();
    _inetSub = null;
  }

  Future<List<Reader>> _discoverOnce(InternetDiscoveryConfiguration cfg) async {
    final completer = Completer<List<Reader>>();

    _inetSub = Terminal.instance.discoverReaders(cfg).listen(
      completer.complete,
      onError: completer.completeError,
      cancelOnError: true,
    );

    final result = await completer.future.timeout(_discoverTimeout, onTimeout: () => <Reader>[]);
    await _inetSub?.cancel();
    _inetSub = null;
    return result;
  }

  Future<String> _tokenProvider() async {
    if (_hasApiCreds && widget.selectedLocationInternalId != null) {
      final ok = await _refreshToken();
      if (ok) return _activeToken ?? '';
    }
    return _activeToken ?? widget.connectionToken;
  }

  Future<void> _initTerminalAndDiscover({required bool requestFreshToken}) async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
      _internetReaders = [];
      _connectionStatus = null;
    });

    _setAppStatus('Initialiserer Stripe Terminal…');

    if (requestFreshToken && _hasApiCreds && widget.selectedLocationInternalId != null) {
      _setAppStatus('Henter nytt tilkoblingstoken…');
      final ok = await _refreshToken();
      if (!ok) {
        _setError('Kunne ikke hente nytt tilkoblingstoken');
        if (mounted) setState(() => _loading = false);
        return;
      }
    }

    final token = (_activeToken ?? widget.connectionToken).trim();
    final locationId = (_activeLocationId ?? widget.locationId).trim();
    if (token.isEmpty || locationId.isEmpty) {
      _setError('Mangler tilkoblingstoken eller terminalsted');
      if (mounted) setState(() => _loading = false);
      return;
    }

    final permissions = <Permission>[
      Permission.locationWhenInUse,
      Permission.bluetooth,
      if (Platform.isAndroid) ...[
        Permission.bluetoothScan,
        Permission.bluetoothConnect,
      ],
    ];
    await permissions.request();

    try {
      await StripeTerminalSingleton.instance.ensureInit(_tokenProvider).timeout(_initTimeout);
    } catch (e) {
      _setError('Kunne ikke initialisere Stripe Terminal: $e');
      if (mounted) setState(() => _loading = false);
      return;
    }

    final current = await Terminal.instance.getConnectedReader();
    if (current != null) {
      _setConnectionStatus('Tilkoblet: ${current.label ?? current.serialNumber}');
      if (mounted) setState(() => _loading = false);
      return;
    }

    await _discoverReaders();
  }

  Future<void> _discoverReaders() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
      _internetReaders = [];
    });

    final locationId = (_activeLocationId ?? widget.locationId).trim();
    if (locationId.isEmpty) {
      _setError('Mangler terminalsted (location)');
      if (mounted) setState(() => _loading = false);
      return;
    }

    _setAppStatus('Søker etter terminaler…');

    try {
      _internetReaders = await _discoverOnce(
        InternetDiscoveryConfiguration(locationId: locationId, isSimulated: false),
      );
      if (!mounted) return;
      setState(() => _loading = false);

      if (_internetReaders.isEmpty) {
        _setAppStatus('Fant ingen terminaler');
      } else {
        _setAppStatus('Fant ${_internetReaders.length} terminal(er)');

        if (widget.autoconnect && (widget.preferredReaderStripeId?.trim().isNotEmpty ?? false)) {
          final preferred = widget.preferredReaderStripeId!.trim();
          Reader? selected;
          for (final r in _internetReaders) {
            if (r.id == preferred) {
              selected = r;
              break;
            }
          }
          if (selected != null) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted) _connectToReader(selected!);
            });
          }
        }
      }
    } catch (e) {
      _setError('Søk etter terminaler mislyktes: $e');
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<int?> _fetchReaderInternalId(String stripeReaderId) async {
    if (!_hasApiCreds) return null;
    try {
      final uri = Uri.parse('$_apiRoot/terminals/locations');
      final resp = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken!.trim()}',
          'Accept': 'application/json',
          'X-Tenant': widget.storeSlug!.trim(),
        },
      );
      if (resp.statusCode != 200) return null;
      final data = jsonDecode(resp.body) as Map<String, dynamic>?;
      final locations = data?['locations'] as List<dynamic>?;
      if (locations == null) return null;

      for (final loc in locations) {
        final map = Map<String, dynamic>.from(loc as Map);
        final readers = map['readers'] as List<dynamic>?;
        if (readers == null) continue;
        for (final r in readers) {
          final rm = Map<String, dynamic>.from(r as Map);
          if ((rm['stripe_reader_id']?.toString() ?? '') == stripeReaderId) {
            final id = rm['id'];
            if (id is int) return id;
            if (id != null) return int.tryParse(id.toString());
          }
        }
      }
    } catch (_) {}
    return null;
  }

  Future<void> _saveLastConnectedTerminal(Reader reader) async {
    if (!_hasApiCreds || widget.posDeviceId == null || widget.selectedLocationInternalId == null) {
      return;
    }

    final readerStripeId = (reader.id ?? '').trim();
    final readerInternalId = readerStripeId.isNotEmpty ? await _fetchReaderInternalId(readerStripeId) : null;

    try {
      final uri = Uri.parse('$_apiRoot/pos-devices/${widget.posDeviceId}');
      final body = {
        'last_connected_terminal_location_id': widget.selectedLocationInternalId,
        if (readerInternalId != null) 'last_connected_terminal_reader_id': readerInternalId,
      };

      await http.patch(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken!.trim()}',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant': widget.storeSlug!.trim(),
        },
        body: jsonEncode(body),
      );
    } catch (_) {}
  }

  Future<void> _connectToReader(Reader reader) async {
    await _cancelDiscovery();

    _setConnectionStatus('Kobler til terminal…');
    if (mounted) {
      setState(() {
        _error = null;
        _loading = true;
      });
    }

    try {
      final current = await Terminal.instance.getConnectedReader();
      if (current != null) {
        await Terminal.instance.disconnectReader();
        await Future.delayed(const Duration(seconds: 1));
      }

      await Terminal.instance.connectReader(
        reader,
        configuration: InternetConnectionConfiguration(readerDelegate: MyInternetReaderDelegate()),
      );

      if (!mounted) return;

      _setConnectionStatus('Tilkoblet: ${reader.label ?? reader.serialNumber}');
      await _saveLastConnectedTerminal(reader);
      setState(() => _loading = false);
    } catch (e) {
      if (!mounted) return;
      _setConnectionStatus(null);
      _setError('Tilkobling mislyktes: $e');
      setState(() => _loading = false);
    }
  }

  Future<void> _handleRescan() async {
    _setAppStatus('Søker etter terminaler på nytt…');
    await _cancelDiscovery();

    final connected = await Terminal.instance.getConnectedReader();
    if (connected != null) {
      await Terminal.instance.disconnectReader();
      if (mounted) {
        FFAppState().update(() {
          FFAppState().stripeReaderConnected = false;
          FFAppState().stripeReaderStatus = 'Frakoblet';
        });
      }
    }

    await _initTerminalAndDiscover(requestFreshToken: true);
  }

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
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return Center(child: Text('Feil: $_error'));
    }

    if (_connectionStatus != null) {
      return Column(
        children: [
          Text(_connectionStatus!, style: const TextStyle(fontWeight: FontWeight.bold)),
          ElevatedButton(onPressed: _handleRescan, child: const Text('Søk på nytt')),
        ],
      );
    }

    if (_internetReaders.isEmpty) {
      return Column(
        children: [
          const Text('Fant ingen Stripe-terminaler.'),
          ElevatedButton(onPressed: _handleRescan, child: const Text('Søk på nytt')),
        ],
      );
    }

    return SingleChildScrollView(child: _buildReaderList());
  }
}
