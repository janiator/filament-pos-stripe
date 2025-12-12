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
import 'dart:convert';
import 'package:http/http.dart' as http;
// End custom code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

class ZReportsListView extends StatefulWidget {
  const ZReportsListView({
    super.key,
    this.width,
    this.height,
    required this.apiBaseUrl,
    required this.authToken,
    required this.storeSlug,
    required this.posDeviceId,
  });

  final double? width;
  final double? height;
  final String apiBaseUrl;
  final String authToken;
  final String storeSlug;
  final int posDeviceId;

  @override
  State<ZReportsListView> createState() => _ZReportsListViewState();
}

class _ZReportsListViewState extends State<ZReportsListView> {
  List<Map<String, dynamic>> _sessions = [];
  bool _isLoading = true;
  String? _errorMessage;
  int? _selectedSessionId;

  @override
  void initState() {
    super.initState();
    _loadZReports();
  }

  Future<void> _loadZReports() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      // Validate inputs
      if (widget.apiBaseUrl.isEmpty) {
        throw Exception('API base URL is not configured');
      }

      if (widget.authToken.isEmpty) {
        throw Exception('Authentication token is missing');
      }

      if (widget.posDeviceId <= 0) {
        throw Exception('Invalid POS device ID');
      }

      // Build API URL with query parameters
      final uri = Uri.parse('${widget.apiBaseUrl}/api/pos-sessions').replace(
        queryParameters: {
          'status': 'closed',
          'pos_device_id': widget.posDeviceId.toString(),
          'per_page': '50', // Get up to 50 closed sessions
        },
      );

      // Make API request
      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken}',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        final sessions = (data['sessions'] as List<dynamic>?)
                ?.map((s) => s as Map<String, dynamic>)
                .toList() ??
            [];

        setState(() {
          _sessions = sessions;
          _isLoading = false;
        });
      } else if (response.statusCode == 401) {
        setState(() {
          _errorMessage = 'Authentication failed. Please log in again.';
          _isLoading = false;
        });
      } else if (response.statusCode == 403) {
        setState(() {
          _errorMessage = 'You do not have access to this device.';
          _isLoading = false;
        });
      } else {
        final errorData = jsonDecode(response.body) as Map<String, dynamic>?;
        setState(() {
          _errorMessage = errorData?['message'] ??
              'Failed to load Z reports (${response.statusCode})';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error loading Z reports: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  void _viewZReport(int sessionId) {
    setState(() {
      _selectedSessionId = sessionId;
    });

    // Show Z report in a modal
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      enableDrag: false, // Disable drag-to-dismiss so webview can handle scrolling
      isDismissible: true, // Still allow closing by tapping outside or back button
      builder: (context) => Container(
        height: MediaQuery.of(context).size.height * 0.9,
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          children: [
            // Header with close button
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                border: Border(
                  bottom: BorderSide(
                    color: FlutterFlowTheme.of(context).alternate,
                    width: 1,
                  ),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Z-rapport',
                    style: FlutterFlowTheme.of(context).headlineSmall,
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () {
                      Navigator.pop(context);
                      setState(() {
                        _selectedSessionId = null;
                      });
                    },
                  ),
                ],
              ),
            ),
            // WebView with Z report
            Expanded(
              child: PosReportWebView(
                apiBaseUrl: widget.apiBaseUrl,
                authToken: widget.authToken,
                storeSlug: widget.storeSlug,
                sessionId: sessionId,
                reportType: 'z',
                width: null,
                height: null,
              ),
            ),
          ],
        ),
      ),
    ).then((_) {
      setState(() {
        _selectedSessionId = null;
      });
    });
  }

  String _formatDate(String? dateString) {
    if (dateString == null) return 'N/A';
    try {
      final date = DateTime.parse(dateString);
      return '${date.day}.${date.month}.${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    } catch (e) {
      return dateString;
    }
  }

  String _formatAmount(int? amount) {
    if (amount == null) return '0,00 kr';
    return '${(amount / 100).toStringAsFixed(2).replaceAll('.', ',')} kr';
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return SizedBox(
        width: widget.width,
        height: widget.height,
        child: const Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    if (_errorMessage != null) {
      return SizedBox(
        width: widget.width,
        height: widget.height,
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.error_outline,
                size: 48,
                color: Colors.red,
              ),
              const SizedBox(height: 16),
              Text(
                _errorMessage ?? 'Unknown error',
                textAlign: TextAlign.center,
                style: FlutterFlowTheme.of(context).bodyMedium.override(
                      fontFamily: 'Readex Pro',
                      color: FlutterFlowTheme.of(context).error,
                    ),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _loadZReports,
                child: const Text('Prøv igjen'),
              ),
            ],
          ),
        ),
      );
    }

    if (_sessions.isEmpty) {
      return SizedBox(
        width: widget.width,
        height: widget.height,
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.description_outlined,
                size: 64,
                color: FlutterFlowTheme.of(context).secondaryText,
              ),
              const SizedBox(height: 16),
              Text(
                'Ingen Z-rapporter funnet',
                style: FlutterFlowTheme.of(context).titleMedium,
              ),
              const SizedBox(height: 8),
              Text(
                'Lukkede sesjoner vil vises her',
                style: FlutterFlowTheme.of(context).bodyMedium,
              ),
            ],
          ),
        ),
      );
    }

    return SizedBox(
      width: widget.width,
      height: widget.height,
      child: RefreshIndicator(
        onRefresh: _loadZReports,
        child: ListView.builder(
          itemCount: _sessions.length,
          itemBuilder: (context, index) {
            final session = _sessions[index];
            final sessionId = session['id'] as int;
            final sessionNumber = session['session_number'] as String? ?? 'N/A';
            final closedAt = session['closed_at'] as String?;
            final totalAmount = session['total_amount'] as int?;
            final transactionCount = session['transaction_count'] as int? ?? 0;

            return Card(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: FlutterFlowTheme.of(context).primary,
                  child: const Icon(
                    Icons.description,
                    color: Colors.white,
                  ),
                ),
                title: Text(
                  'Sesjon $sessionNumber',
                  style: FlutterFlowTheme.of(context).titleMedium,
                ),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 4),
                    Text(
                      'Lukket: ${_formatDate(closedAt)}',
                      style: FlutterFlowTheme.of(context).bodySmall,
                    ),
                    Text(
                      'Transaksjoner: $transactionCount',
                      style: FlutterFlowTheme.of(context).bodySmall,
                    ),
                  ],
                ),
                trailing: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      _formatAmount(totalAmount),
                      style: FlutterFlowTheme.of(context).titleMedium.override(
                            fontFamily: 'Readex Pro',
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    const Icon(Icons.chevron_right),
                  ],
                ),
                onTap: () => _viewZReport(sessionId),
              ),
            );
          },
        ),
      ),
    );
  }
}

