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

import 'package:flutter/scheduler.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'dart:ui';

/// ────────────────────────────────────────────────────────────────
/// CUSTOM MODAL WIDGET FOR STRIPE TERMINAL SELECTION
/// Auto-closes when a reader is connected
/// ────────────────────────────────────────────────────────────────
class StripeTerminalSelectorModal extends StatefulWidget {
  const StripeTerminalSelectorModal({
    Key? key,
    this.width,
    this.height,
  }) : super(key: key);

  final double? width;
  final double? height;

  @override
  _StripeTerminalSelectorModalState createState() =>
      _StripeTerminalSelectorModalState();
}

class _StripeTerminalSelectorModalState
    extends State<StripeTerminalSelectorModal> {
  bool _hasAutoClosed = false;
  bool _previousConnectionState = false;

  @override
  void initState() {
    super.initState();
    // Initialize previous state
    _previousConnectionState = FFAppState().stripeReaderConnected;
    
    // Check if already connected on load
    SchedulerBinding.instance.addPostFrameCallback((_) {
      if (FFAppState().stripeReaderConnected && mounted && !_hasAutoClosed) {
        _hasAutoClosed = true;
        Navigator.pop(context);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    context.watch<FFAppState>();

    // Auto-close when reader connects (transition from false to true)
    final currentConnectionState = FFAppState().stripeReaderConnected;
    if (currentConnectionState && 
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

    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(0.0, 44.0, 0.0, 0.0),
      child: Container(
        width: widget.width ?? 600.0,
        constraints: BoxConstraints(
          maxWidth: widget.width ?? 600.0,
        ),
        decoration: BoxDecoration(
          color: FlutterFlowTheme.of(context).secondaryBackground,
          boxShadow: [
            BoxShadow(
              blurRadius: 4.0,
              color: Color(0x25090F13),
              offset: Offset(
                0.0,
                2.0,
              ),
            )
          ],
          borderRadius: BorderRadius.circular(12.0),
        ),
        child: Padding(
          padding: EdgeInsetsDirectional.fromSTEB(16.0, 4.0, 16.0, 16.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: EdgeInsetsDirectional.fromSTEB(0.0, 12.0, 0.0, 0.0),
                child: Row(
                  mainAxisSize: MainAxisSize.max,
                  children: [
                    Padding(
                      padding:
                          EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 12.0, 0.0),
                      child: FlutterFlowIconButton(
                        borderColor: Colors.transparent,
                        borderRadius: 30.0,
                        borderWidth: 1.0,
                        buttonSize: 44.0,
                        icon: Icon(
                          Icons.close,
                          color: FlutterFlowTheme.of(context).secondaryText,
                          size: 24.0,
                        ),
                        onPressed: () async {
                          Navigator.pop(context);
                        },
                      ),
                    ),
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.max,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Velg terminal',
                            style: FlutterFlowTheme.of(context)
                                .headlineSmall
                                .override(
                                  font: GoogleFonts.interTight(
                                    fontWeight: FlutterFlowTheme.of(context)
                                        .headlineSmall
                                        .fontWeight,
                                    fontStyle: FlutterFlowTheme.of(context)
                                        .headlineSmall
                                        .fontStyle,
                                  ),
                                  letterSpacing: 0.0,
                                  fontWeight: FlutterFlowTheme.of(context)
                                      .headlineSmall
                                      .fontWeight,
                                  fontStyle: FlutterFlowTheme.of(context)
                                      .headlineSmall
                                      .fontStyle,
                                ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Divider(
                height: 24.0,
                thickness: 2.0,
                color: FlutterFlowTheme.of(context).primaryBackground,
              ),
              Padding(
                padding: EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 16.0),
                child: Column(
                  mainAxisSize: MainAxisSize.max,
                  children: [
                    if (FFAppState().stripeConnectionToken != null &&
                        FFAppState().stripeConnectionToken != '')
                      Container(
                        width: widget.width ?? MediaQuery.sizeOf(context).width * 1.0,
                        height: widget.height ?? 400.0,
                        child: custom_widgets
                            .StripeInternetTerminalReaderPickerAndConnector(
                          width: widget.width ?? MediaQuery.sizeOf(context).width * 1.0,
                          height: widget.height ?? 400.0,
                          connectionToken: FFAppState().stripeConnectionToken,
                          locationId: FFAppState().stripeLocationId,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// ────────────────────────────────────────────────────────────────
/// FLUTTERFLOW CUSTOM ACTION
/// ────────────────────────────────────────────────────────────────

/// Show Stripe Terminal Selector Modal
/// 
/// Displays a modal dialog for selecting and connecting to a Stripe Terminal reader.
/// The modal automatically closes when a reader is successfully connected.
/// 
/// Parameters:
/// - context: BuildContext required for showing the dialog
/// - width: Optional width for the modal (defaults to 600.0)
/// - height: Optional height for the modal content (defaults to 400.0)
/// 
/// Returns:
/// - success: true if modal was shown successfully
Future<dynamic> stripeTerminalSelectorModal(
  BuildContext context,
  double? width,
  double? height,
) async {
  try {
    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: EdgeInsets.zero,
          child: StripeTerminalSelectorModal(
            width: width,
            height: height,
          ),
        );
      },
    );

    return {
      'success': true,
    };
  } catch (e) {
    return {
      'success': false,
      'message': 'Failed to show terminal selector modal: $e',
    };
  }
}
