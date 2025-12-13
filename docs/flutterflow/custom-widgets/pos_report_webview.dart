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
import 'package:webview_flutter/webview_flutter.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/gestures.dart';
import 'package:http/http.dart' as http;
import 'dart:io';
import 'dart:ui';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:cross_file/cross_file.dart';
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
  final String reportType;

  @override
  State<PosReportWebView> createState() => _PosReportWebViewState();
}

class _PosReportWebViewState extends State<PosReportWebView> {
  late WebViewController _controller;
  bool _isLoading = true;
  String? _errorMessage;
  bool _isDownloading = false;
  final GlobalKey _downloadButtonKey = GlobalKey();

  @override
  void initState() {
    super.initState();
    _controller = WebViewController();
    _initializeWebView();
  }

  void _initializeWebView() {
    final baseUrl = widget.apiBaseUrl;
    final token = widget.authToken;
    final storeSlug = widget.storeSlug;
    final sessionId = widget.sessionId;
    final reportType = widget.reportType.toLowerCase();

    if (baseUrl.isEmpty || token.isEmpty || storeSlug.isEmpty || sessionId <= 0) {
      setState(() {
        _errorMessage = 'Invalid configuration';
        _isLoading = false;
      });
      return;
    }

    if (reportType != 'x' && reportType != 'z') {
      setState(() {
        _errorMessage = 'Report type must be "x" or "z"';
        _isLoading = false;
      });
      return;
    }

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
            // Hide the download button in the webview
            _controller.runJavaScript('''
              (function() {
                setTimeout(function() {
                  // Find download buttons by href containing report.pdf
                  var links = document.querySelectorAll('a[href*="report.pdf"]');
                  links.forEach(function(link) {
                    var parent = link.parentElement;
                    if (parent && (parent.style.textAlign === 'right' || parent.textContent.includes('Last ned PDF'))) {
                      parent.style.display = 'none';
                    } else {
                      link.style.display = 'none';
                    }
                  });
                  // Also hide any divs containing "Last ned PDF" text
                  var allDivs = document.querySelectorAll('div');
                  allDivs.forEach(function(div) {
                    if (div.textContent && div.textContent.includes('Last ned PDF') && div.style.textAlign === 'right') {
                      div.style.display = 'none';
                    }
                  });
                }, 100);
              })();
            ''');
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
                final statusCode = error.response?.statusCode ?? 0;
                if (statusCode == 401) {
                  _errorMessage = 'Authentication failed. Please log in again.';
                } else if (statusCode == 403) {
                  _errorMessage = 'You do not have access to this report.';
                } else if (statusCode == 404) {
                  _errorMessage = 'Report or session not found.';
                } else {
                  _errorMessage = 'Error loading report ($statusCode)';
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
      final reportType = widget.reportType.toLowerCase();
      final pdfUrl = Uri.parse('${widget.apiBaseUrl}/api/pos-sessions/${widget.sessionId}/$reportType-report/pdf').replace(queryParameters: {'store': widget.storeSlug});

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Downloading PDF...'),
            duration: Duration(seconds: 1),
          ),
        );
      }

      final pdfResponse = await http.get(pdfUrl, headers: {
        'Authorization': 'Bearer ${widget.authToken}',
        'Accept': 'application/pdf',
      }).timeout(const Duration(seconds: 30));

      if (pdfResponse.statusCode == 200) {
        final directory = await getApplicationDocumentsDirectory();
        final filename = '${widget.reportType.toUpperCase()}-Rapport-${widget.sessionId}-${DateTime.now().toIso8601String().split('T')[0]}.pdf';
        final file = File('${directory.path}/$filename');
        await file.writeAsBytes(pdfResponse.bodyBytes);

        if (await file.exists()) {
          final xFile = XFile(file.path, mimeType: 'application/pdf');
          
          // Get sharePositionOrigin for iPad support
          Rect? sharePositionOrigin;
          if (_downloadButtonKey.currentContext != null) {
            final box = _downloadButtonKey.currentContext!.findRenderObject() as RenderBox?;
            if (box != null) {
              sharePositionOrigin = box.localToGlobal(Offset.zero) & box.size;
            }
          }

          await SharePlus.instance.share(
            ShareParams(
              files: [xFile],
              text: 'POS Report PDF',
              sharePositionOrigin: sharePositionOrigin,
            ),
          );
          
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('PDF ready: $filename'),
                backgroundColor: Colors.green,
                duration: const Duration(seconds: 2),
              ),
            );
          }
        } else {
          throw Exception('File was not saved correctly');
        }
      } else if (pdfResponse.statusCode == 401) {
        throw Exception('Authentication failed. Please log in again.');
      } else if (pdfResponse.statusCode == 403) {
        throw Exception('You do not have access to this report.');
      } else if (pdfResponse.statusCode == 404) {
        throw Exception('Report or session not found.');
      } else {
        throw Exception('Failed to download PDF: ${pdfResponse.statusCode} ${pdfResponse.reasonPhrase}');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: ${e.toString()}'),
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
                Positioned(
                  top: 8,
                  right: 8,
                  child: Material(
                    key: _downloadButtonKey,
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
                                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
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
