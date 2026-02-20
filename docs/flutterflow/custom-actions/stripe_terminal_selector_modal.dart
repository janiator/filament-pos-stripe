// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/flutter_flow/flutter_flow_widgets.dart';
import '/flutter_flow/flutter_flow_icon_button.dart';
import '/custom_code/actions/index.dart'; // Imports other custom actions
import '/custom_code/widgets/index.dart' as custom_widgets; // Imports other custom widgets
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom action code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:convert';
import 'dart:ui';
import 'package:flutter/scheduler.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

class StripeTerminalSelectorModal extends StatefulWidget {
  const StripeTerminalSelectorModal({
    Key? key,
    this.width,
    this.height,
    this.apiBaseUrl,
    this.authToken,
    this.storeSlug,
    this.deviceIdentifier,
    this.posDeviceId,
    this.autoconnect = true,
    this.autoCloseOnConnect = true,
  }) : super(key: key);

  final double? width;
  final double? height;
  final String? apiBaseUrl;
  final String? authToken;
  final String? storeSlug;
  final String? deviceIdentifier;
  final int? posDeviceId;
  final bool autoconnect;
  /// When true (default), the modal closes automatically after a reader is connected.
  final bool autoCloseOnConnect;

  @override
  State<StripeTerminalSelectorModal> createState() => _StripeTerminalSelectorModalState();
}

class _StripeTerminalSelectorModalState extends State<StripeTerminalSelectorModal> {
  bool _hasAutoClosed = false;
  bool _previousConnectionState = false;

  List<Map<String, dynamic>> _locations = <Map<String, dynamic>>[];
  Map<String, dynamic>? _selectedLocation;
  Map<String, dynamic>? _lastConnected;

  String? _connectionToken;
  String? _locationId;

  bool _locationLoading = false;
  bool _tokenLoading = false;
  String? _error;

  bool get _hasApiCreds {
    return (widget.apiBaseUrl?.trim().isNotEmpty ?? false) &&
        (widget.authToken?.trim().isNotEmpty ?? false) &&
        (widget.storeSlug?.trim().isNotEmpty ?? false);
  }

  String get _apiRoot {
    final b = widget.apiBaseUrl?.trim() ?? '';
    if (b.isEmpty) return b;
    final base = b.endsWith('/') ? b.substring(0, b.length - 1) : b;
    return base.endsWith('/api') ? base : '$base/api';
  }

  Future<void> _fetchLocations() async {
    if (!_hasApiCreds) return;
    setState(() {
      _locationLoading = true;
      _error = null;
      _locations = <Map<String, dynamic>>[];
      _selectedLocation = null;
      _lastConnected = null;
    });

    try {
      var uri = Uri.parse('$_apiRoot/terminals/locations');
      final deviceId = widget.deviceIdentifier?.trim();
      if (deviceId != null && deviceId.isNotEmpty) {
        uri = uri.replace(queryParameters: {'device_identifier': deviceId});
      }

      final resp = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken!.trim()}',
          'Accept': 'application/json',
          'X-Tenant': widget.storeSlug!.trim(),
        },
      );
      if (!mounted) return;
      if (resp.statusCode != 200) {
        setState(() {
          _locationLoading = false;
          _error = 'Kunne ikke hente terminalsteder';
        });
        return;
      }

      final data = jsonDecode(resp.body) as Map<String, dynamic>?;
      final list = data?['locations'] as List<dynamic>?;
      final locations = list == null
          ? <Map<String, dynamic>>[]
          : list.map((e) => Map<String, dynamic>.from(e as Map)).toList();

      final last = data?['last_connected'] as Map<String, dynamic>?;

      Map<String, dynamic>? selected;
      if (locations.length == 1) {
        selected = locations.first;
      } else if (last != null) {
        final lastLocId = last['location_id'];
        for (final loc in locations) {
          if (loc['id'] == lastLocId) {
            selected = loc;
            break;
          }
        }
      }

      setState(() {
        _locations = locations;
        _lastConnected = last != null ? Map<String, dynamic>.from(last) : null;
        _selectedLocation = selected;
        _locationLoading = false;
        _error = locations.isEmpty ? 'Ingen terminalsteder funnet' : null;
      });

      if (selected != null) {
        await _refreshTokenForSelectedLocation();
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _locationLoading = false;
        _error = 'Kunne ikke hente terminalsteder: $e';
      });
    }
  }

  Future<Map<String, String>?> _refreshTokenForSelectedLocation() async {
    if (!_hasApiCreds || _selectedLocation == null) return null;

    setState(() {
      _tokenLoading = true;
      _error = null;
    });

    try {
      final uri = Uri.parse('$_apiRoot/stores/${widget.storeSlug!.trim()}/terminal/connection-token');
      final locationInternalId = _selectedLocation!['id'];

      final resp = await http.post(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken!.trim()}',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({'location_id': locationInternalId}),
      );

      if (!mounted) return null;
      if (resp.statusCode != 200) {
        setState(() {
          _tokenLoading = false;
          _error = 'Kunne ikke hente tilkoblingstoken';
        });
        return null;
      }

      final data = jsonDecode(resp.body) as Map<String, dynamic>?;
      final token = (data?['secret']?.toString() ?? '').trim();
      final locationId = (data?['location']?.toString() ??
              (_selectedLocation?['stripe_location_id']?.toString() ?? ''))
          .trim();

      if (token.isEmpty || locationId.isEmpty) {
        setState(() {
          _tokenLoading = false;
          _error = 'Ugyldig tilkoblingstoken-respons';
        });
        return null;
      }

      FFAppState().update(() {
        FFAppState().stripeConnectionToken = token;
        FFAppState().stripeLocationId = locationId;
      });

      setState(() {
        _connectionToken = token;
        _locationId = locationId;
        _tokenLoading = false;
      });

      return {'token': token, 'locationId': locationId};
    } catch (e) {
      if (!mounted) return null;
      setState(() {
        _tokenLoading = false;
        _error = 'Kunne ikke hente tilkoblingstoken: $e';
      });
      return null;
    }
  }

  void _onSelectLocation(Map<String, dynamic> location) {
    setState(() {
      _selectedLocation = location;
      _connectionToken = null;
      _locationId = null;
    });
    _refreshTokenForSelectedLocation();
  }

  String? _preferredReaderIdForSelectedLocation() {
    if (_lastConnected == null || _selectedLocation == null) return null;
    final selectedLocId = _selectedLocation!['id'];
    if (_lastConnected!['location_id'] != selectedLocId) return null;
    return _lastConnected!['stripe_reader_id']?.toString();
  }

  @override
  void initState() {
    super.initState();
    _previousConnectionState = FFAppState().stripeReaderConnected;

    if (_hasApiCreds) {
      _fetchLocations();
    } else {
      _connectionToken = FFAppState().stripeConnectionToken;
      _locationId = FFAppState().stripeLocationId;
    }

    SchedulerBinding.instance.addPostFrameCallback((_) {
      if (widget.autoCloseOnConnect &&
          FFAppState().stripeReaderConnected &&
          mounted &&
          !_hasAutoClosed) {
        _hasAutoClosed = true;
        Navigator.pop(context);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    context.watch<FFAppState>();

    final currentConnectionState = FFAppState().stripeReaderConnected;
    if (widget.autoCloseOnConnect &&
        currentConnectionState &&
        !_previousConnectionState &&
        !_hasAutoClosed &&
        mounted) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && !_hasAutoClosed) {
          _hasAutoClosed = true;
          Navigator.pop(context);
        }
      });
    }
    _previousConnectionState = currentConnectionState;

    final token = (_connectionToken ?? FFAppState().stripeConnectionToken).trim();
    final locationId = (_locationId ?? FFAppState().stripeLocationId).trim();

    Widget body;
    if (_hasApiCreds && _locationLoading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null && _locations.isEmpty) {
      body = Center(child: Text(_error!, textAlign: TextAlign.center));
    } else if (_hasApiCreds && _locations.length > 1 && _selectedLocation == null) {
      body = Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Velg terminalsted', style: FlutterFlowTheme.of(context).titleMedium),
          const SizedBox(height: 12),
          ..._locations.map((loc) {
            final name = (loc['display_name']?.toString() ?? 'Sted');
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: OutlinedButton(onPressed: () => _onSelectLocation(loc), child: Text(name)),
            );
          }),
        ],
      );
    } else if (_tokenLoading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (token.isNotEmpty && locationId.isNotEmpty) {
      final selectedInternalId = _selectedLocation?['id'];
      int? selectedLocationInternalId;
      if (selectedInternalId is int) {
        selectedLocationInternalId = selectedInternalId;
      } else if (selectedInternalId != null) {
        selectedLocationInternalId = int.tryParse(selectedInternalId.toString());
      }

      body = custom_widgets.StripeInternetTerminalReaderPickerAndConnector(
        width: widget.width ?? MediaQuery.sizeOf(context).width,
        height: widget.height ?? 400,
        connectionToken: token,
        locationId: locationId,
        preferredReaderStripeId: _preferredReaderIdForSelectedLocation(),
        autoconnect: widget.autoconnect,
        posDeviceId: widget.posDeviceId,
        selectedLocationInternalId: selectedLocationInternalId,
        apiBaseUrl: widget.apiBaseUrl,
        authToken: widget.authToken,
        storeSlug: widget.storeSlug,
      );
    } else {
      body = const Center(
        child: Text(
          'Tilkoblingstoken mangler. Hent token før modalen åpnes, eller send med API-parametere.',
          textAlign: TextAlign.center,
        ),
      );
    }

    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(0, 44, 0, 0),
      child: Container(
        width: widget.width ?? 600,
        constraints: BoxConstraints(maxWidth: widget.width ?? 600),
        decoration: BoxDecoration(
          color: FlutterFlowTheme.of(context).secondaryBackground,
          boxShadow: const [
            BoxShadow(blurRadius: 4, color: Color(0x25090F13), offset: Offset(0, 2)),
          ],
          borderRadius: BorderRadius.circular(12),
        ),
        child: Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(16, 4, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsetsDirectional.fromSTEB(0, 12, 0, 0),
                child: Row(
                  children: [
                    Padding(
                      padding: const EdgeInsetsDirectional.only(end: 12),
                      child: FlutterFlowIconButton(
                        borderColor: Colors.transparent,
                        borderRadius: 30,
                        borderWidth: 1,
                        buttonSize: 44,
                        icon: Icon(Icons.close, color: FlutterFlowTheme.of(context).secondaryText, size: 24),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ),
                    Expanded(
                      child: Text(
                        'Velg terminal',
                        style: FlutterFlowTheme.of(context).headlineSmall.override(
                              font: GoogleFonts.interTight(
                                fontWeight: FlutterFlowTheme.of(context).headlineSmall.fontWeight,
                                fontStyle: FlutterFlowTheme.of(context).headlineSmall.fontStyle,
                              ),
                              letterSpacing: 0,
                            ),
                      ),
                    ),
                  ],
                ),
              ),
              Divider(height: 24, thickness: 2, color: FlutterFlowTheme.of(context).primaryBackground),
              SizedBox(
                width: widget.width ?? MediaQuery.sizeOf(context).width,
                height: widget.height ?? 400,
                child: body,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Show Stripe Terminal Selector Modal.
/// In FlutterFlow: add parameters in this order – width, height, apiBaseUrl, authToken, storeSlug, deviceIdentifier, posDeviceId, autoconnect, autoCloseOnConnect (all optional/nullable).
Future<dynamic> stripeTerminalSelectorModal(
  BuildContext context,
  double? width,
  double? height,
  String? apiBaseUrl,
  String? authToken,
  String? storeSlug,
  String? deviceIdentifier,
  int? posDeviceId,
  bool? autoconnect,
  bool? autoCloseOnConnect,
) async {
  try {
    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: EdgeInsets.zero,
        child: StripeTerminalSelectorModal(
          width: width,
          height: height,
          apiBaseUrl: apiBaseUrl,
          authToken: authToken,
          storeSlug: storeSlug,
          deviceIdentifier: deviceIdentifier,
          posDeviceId: posDeviceId,
          autoconnect: autoconnect ?? true,
          autoCloseOnConnect: autoCloseOnConnect ?? true,
        ),
      ),
    );

    return {'success': true};
  } catch (e) {
    return {'success': false, 'message': 'Failed to show terminal selector modal: $e'};
  }
}
