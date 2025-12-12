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
// End custom code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

class ProductDeclarationWebView extends StatefulWidget {
  const ProductDeclarationWebView({
    super.key,
    this.width,
    this.height,
    required this.apiBaseUrl,
    required this.authToken,
  });

  final double? width;
  final double? height;
  final String apiBaseUrl;
  final String authToken;

  @override
  State<ProductDeclarationWebView> createState() =>
      _ProductDeclarationWebViewState();
}

class _ProductDeclarationWebViewState extends State<ProductDeclarationWebView> {
  late final WebViewController _controller;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _initializeWebView();
  }

  void _initializeWebView() {
    // Get base URL and token from widget parameters
    final baseUrl = widget.apiBaseUrl;
    final token = widget.authToken;

    if (baseUrl.isEmpty) {
      setState(() {
        _errorMessage = 'API base URL is not configured';
        _isLoading = false;
      });
      return;
    }

    if (token.isEmpty) {
      setState(() {
        _errorMessage = 'Authentication token is missing';
        _isLoading = false;
      });
      return;
    }

    final url = '$baseUrl/api/product-declaration/display';

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
                _errorMessage = 'Failed to load content: ${error.description}';
              });
            }
          },
          onHttpError: (HttpResponseError error) {
            if (mounted) {
              setState(() {
                _isLoading = false;
                if (error.response?.statusCode == 401) {
                  _errorMessage = 'Authentication failed. Please log in again.';
                } else if (error.response?.statusCode == 404) {
                  _errorMessage = 'Product declaration not found.';
                } else {
                  _errorMessage = 'Error loading content (${error.response?.statusCode})';
                }
              });
            }
          },
        ),
      )
      ..loadRequest(
        Uri.parse(url),
        headers: {
          'Authorization': 'Bearer $token',
          'Content-Type': 'application/json',
          'Accept': 'text/html',
        },
      );
  }

  void _reload() {
    _initializeWebView();
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

