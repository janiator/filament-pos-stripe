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
import 'package:flutter/foundation.dart';
import 'package:flutter/gestures.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
// End custom code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

class PosReportWebView extends StatefulWidget {
  const PosReportWebView({
    super.key,
    this.width,
    this.height,
    required this.apiBaseUrl,
    required this.authToken,
    required this.storeSlug,
    required this.sessionId,
    required this.reportType,
  });

  final double? width;
  final double? height;
  final String apiBaseUrl;
  final String authToken;
  final String storeSlug;
  final int sessionId;
  final String reportType; // 'x' or 'z'

  @override
  State<PosReportWebView> createState() => _PosReportWebViewState();
}

class _PosReportWebViewState extends State<PosReportWebView> {
  late final WebViewController _controller;
  bool _isLoading = true;
  String? _errorMessage;
  bool _isDownloading = false;

  @override
  void initState() {
    super.initState();
    _initializeWebView();
  }

  void _initializeWebView() {
    // Get parameters from widget
    final baseUrl = widget.apiBaseUrl;
    final token = widget.authToken;
    final storeSlug = widget.storeSlug;
    final sessionId = widget.sessionId;
    final reportType = widget.reportType.toLowerCase();

    // Validate inputs
    if (baseUrl.isEmpty) {
      setState(() {
        _errorMessage = 'API base URL is not configured';
        _isLoading = false;
      });
      _controller = WebViewController();
      return;
    }

    if (token.isEmpty) {
      setState(() {
        _errorMessage = 'Authentication token is missing';
        _isLoading = false;
      });
      _controller = WebViewController();
      return;
    }

    if (storeSlug.isEmpty) {
      setState(() {
        _errorMessage = 'Store slug is not configured';
        _isLoading = false;
      });
      _controller = WebViewController();
      return;
    }

    if (sessionId <= 0) {
      setState(() {
        _errorMessage = 'Invalid session ID';
        _isLoading = false;
      });
      _controller = WebViewController();
      return;
    }

    if (reportType != 'x' && reportType != 'z') {
      setState(() {
        _errorMessage = 'Report type must be "x" or "z"';
        _isLoading = false;
      });
      _controller = WebViewController();
      return;
    }

    // Build Filament authentication URL with redirect to embed route
    // The /filament-auth/{token} route will authenticate and redirect to the embed page
    final redirectPath = 'pos-sessions/$sessionId/$reportType-report/embed';
    final url = '$baseUrl/filament-auth/$token?store=$storeSlug&redirect=$redirectPath';

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            if (mounted) {
              setState(() {
                _isLoading = true;
                _errorMessage = null;
              });
            }
          },
          onPageFinished: (String url) {
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
          },
          onWebResourceError: (WebResourceError error) {
            if (mounted) {
              setState(() {
                _isLoading = false;
                _errorMessage = 'Failed to load report: ${error.description}';
              });
            }
          },
          onHttpError: (HttpResponseError error) {
            if (mounted) {
              setState(() {
                _isLoading = false;
                if (error.response?.statusCode == 401) {
                  _errorMessage = 'Authentication failed. Please log in again.';
                } else if (error.response?.statusCode == 403) {
                  _errorMessage = 'You do not have access to this report.';
                } else if (error.response?.statusCode == 404) {
                  _errorMessage = 'Report or session not found.';
                } else {
                  _errorMessage = 'Error loading report (${error.response?.statusCode})';
                }
              });
            }
          },
        ),
      )
      ..loadRequest(Uri.parse(url));
  }

  void _reload() {
    _initializeWebView();
  }

  Future<void> _downloadPdf() async {
    if (_isDownloading) return;

    setState(() {
      _isDownloading = true;
    });

    try {
      // Build PDF download URL using API endpoint with Bearer token auth
      final reportType = widget.reportType.toLowerCase();
      final pdfUrl = Uri.parse(
        '${widget.apiBaseUrl}/api/pos-sessions/${widget.sessionId}/$reportType-report/pdf',
      ).replace(queryParameters: {
        'store': widget.storeSlug,
      });

      // Download the PDF with Bearer token authentication
      final pdfResponse = await http.get(
        pdfUrl,
        headers: {
          'Authorization': 'Bearer ${widget.authToken}',
          'Content-Type': 'application/json',
          'Accept': 'application/pdf',
        },
      );

      if (pdfResponse.statusCode == 200) {
        // Get the download directory
        Directory? downloadDir;

        if (Platform.isAndroid) {
          // Request storage permission for Android
          final status = await Permission.storage.request();
          if (!status.isGranted) {
            throw Exception('Storage permission denied');
          }
          // Use external storage directory for Android
          downloadDir = await getExternalStorageDirectory();
        } else if (Platform.isIOS) {
          // Use application documents directory for iOS
          downloadDir = await getApplicationDocumentsDirectory();
        } else {
          throw Exception('Unsupported platform');
        }

        if (downloadDir == null) {
          throw Exception('Could not access download directory');
        }

        // Generate filename from Content-Disposition header or use default
        String filename =
            '${widget.reportType.toUpperCase()}-Rapport-${widget.sessionId}-${DateTime.now().toIso8601String().split('T')[0]}.pdf';

        final contentDisposition = pdfResponse.headers['content-disposition'];
        if (contentDisposition != null) {
          final filenameMatch = RegExp(r'filename[^;=\n]*=(([\'"]).*?\2|[^;\n]*)')
              .firstMatch(contentDisposition);
          if (filenameMatch != null) {
            filename = filenameMatch
                    .group(1)
                    ?.replaceAll('"', '')
                    .replaceAll("'", '') ??
                filename;
          }
        }

        // Save the file
        final file = File('${downloadDir.path}/$filename');
        await file.writeAsBytes(pdfResponse.bodyBytes);

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('PDF downloaded: $filename'),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 3),
            ),
          );
        }
      } else if (pdfResponse.statusCode == 401) {
        throw Exception('Authentication failed. Please log in again.');
      } else if (pdfResponse.statusCode == 403) {
        throw Exception('You do not have access to this report.');
      } else if (pdfResponse.statusCode == 404) {
        throw Exception('Report or session not found.');
      } else {
        throw Exception(
            'Failed to download PDF: ${pdfResponse.statusCode} ${pdfResponse.reasonPhrase}');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error downloading PDF: ${e.toString()}'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 4),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isDownloading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: widget.width,
      height: widget.height,
      child: _errorMessage != null
          ? _buildErrorWidget()
          : Stack(
              children: [
                // Enable scrolling by allowing gestures
                WebViewWidget(
                  controller: _controller,
                  gestureRecognizers: {
                    Factory<VerticalDragGestureRecognizer>(
                      () => VerticalDragGestureRecognizer(),
                    ),
                    Factory<HorizontalDragGestureRecognizer>(
                      () => HorizontalDragGestureRecognizer(),
                    ),
                    Factory<ScaleGestureRecognizer>(
                      () => ScaleGestureRecognizer(),
                    ),
                  },
                ),
                // Download button overlay
                Positioned(
                  top: 8,
                  right: 8,
                  child: Material(
                    color: FlutterFlowTheme.of(context).primary,
                    borderRadius: BorderRadius.circular(8),
                    child: InkWell(
                      onTap: _isDownloading ? null : _downloadPdf,
                      borderRadius: BorderRadius.circular(8),
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        child: _isDownloading
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(
                                      Colors.white),
                                ),
                              )
                            : const Icon(
                                Icons.download,
                                color: Colors.white,
                                size: 20,
                              ),
                      ),
                    ),
                  ),
                ),
                if (_isLoading)
                  Container(
                    color: Colors.white,
                    child: const Center(
                      child: CircularProgressIndicator(),
                    ),
                  ),
              ],
            ),
    );
  }

  Widget _buildErrorWidget() {
    return Container(
      padding: const EdgeInsets.all(16.0),
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
              onPressed: _reload,
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
