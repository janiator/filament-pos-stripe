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
import 'dart:convert';
import 'package:http/http.dart' as http;

/// ────────────────────────────────────────────────────────────────
/// REFUND ITEM SELECTION MODAL
/// Allows selecting which items from an order should be refunded
/// ────────────────────────────────────────────────────────────────
class RefundItemSelectionModal extends StatefulWidget {
  const RefundItemSelectionModal({
    Key? key,
    required this.purchaseItems,
    required this.purchaseAmount,
    required this.amountRefunded,
    required this.paymentMethod,
    this.width,
    this.height,
  }) : super(key: key);

  final List<PurchaseItemStruct> purchaseItems;
  final int purchaseAmount;
  final int amountRefunded;
  final String paymentMethod;
  final double? width;
  final double? height;

  @override
  _RefundItemSelectionModalState createState() =>
      _RefundItemSelectionModalState();
}

class _RefundItemSelectionModalState extends State<RefundItemSelectionModal> {
  final Map<String, int> _selectedItems = {}; // item_id -> quantity to refund
  final TextEditingController _reasonController = TextEditingController();
  bool _selectAll = false;
  bool _isProcessing = false;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  void _toggleSelectAll() {
    setState(() {
      _selectAll = !_selectAll;
      if (_selectAll) {
        // Select all items with their full quantities
        for (var item in widget.purchaseItems) {
          final itemId = item.purchaseItemId ?? '';
          if (itemId.isNotEmpty) {
            _selectedItems[itemId] = item.purchaseItemQuantity ?? 1;
          }
        }
      } else {
        _selectedItems.clear();
      }
    });
  }

  void _toggleItem(String itemId, int maxQuantity) {
    setState(() {
      if (_selectedItems.containsKey(itemId)) {
        _selectedItems.remove(itemId);
      } else {
        _selectedItems[itemId] = maxQuantity;
      }
      // Update select all state
      _selectAll = _selectedItems.length == widget.purchaseItems.length;
    });
  }

  void _updateItemQuantity(String itemId, int quantity) {
    setState(() {
      if (quantity > 0) {
        _selectedItems[itemId] = quantity;
      } else {
        _selectedItems.remove(itemId);
      }
    });
  }

  int _calculateRefundAmount() {
    int total = 0;
    for (var item in widget.purchaseItems) {
      final itemId = item.purchaseItemId ?? '';
      if (_selectedItems.containsKey(itemId)) {
        final quantityToRefund = _selectedItems[itemId] ?? 0;
        final unitPrice = item.purchaseItemUnitPrice ?? 0;
        final discountAmount = item.purchaseItemDiscountAmount ?? 0;
        // Calculate line total: (unit_price - discount) * quantity
        final lineTotal = (unitPrice - discountAmount) * quantityToRefund;
        total += lineTotal;
      }
    }
    return total;
  }

  int _getRemainingRefundable() {
    return widget.purchaseAmount - widget.amountRefunded;
  }

  Future<void> _processRefund() async {
    if (_selectedItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Velg minst én vare å refundere'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final refundAmount = _calculateRefundAmount();
    if (refundAmount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Refunderingsbeløpet må være større enn 0'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final remainingRefundable = _getRemainingRefundable();
    if (refundAmount > remainingRefundable) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
              'Refunderingsbeløpet ($refundAmount) overstiger gjenstående refunderbart beløp ($remainingRefundable)'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() {
      _isProcessing = true;
    });

    // Return the selected items and refund amount to the caller
    Navigator.pop(context, {
      'success': true,
      'refundAmount': refundAmount,
      'selectedItems': _selectedItems,
      'reason': _reasonController.text.trim(),
    });
  }

  @override
  Widget build(BuildContext context) {
    final refundAmount = _calculateRefundAmount();
    final remainingRefundable = _getRemainingRefundable();
    final refundAmountFormatted = (refundAmount / 100).toStringAsFixed(2);
    final remainingFormatted = (remainingRefundable / 100).toStringAsFixed(2);

    return Padding(
      padding: EdgeInsetsDirectional.fromSTEB(0.0, 44.0, 0.0, 0.0),
      child: Container(
        width: widget.width ?? 600.0,
        constraints: BoxConstraints(
          maxWidth: widget.width ?? 600.0,
          maxHeight: widget.height ?? 700.0,
        ),
        decoration: BoxDecoration(
          color: FlutterFlowTheme.of(context).secondaryBackground,
          boxShadow: [
            BoxShadow(
              blurRadius: 4.0,
              color: Color(0x25090F13),
              offset: Offset(0.0, 2.0),
            )
          ],
          borderRadius: BorderRadius.circular(12.0),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header
            Padding(
              padding: EdgeInsetsDirectional.fromSTEB(16.0, 16.0, 16.0, 0.0),
              child: Row(
                mainAxisSize: MainAxisSize.max,
                children: [
                  Padding(
                    padding: EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 12.0, 0.0),
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
                      onPressed: () {
                        Navigator.pop(context, {'success': false});
                      },
                    ),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Refunder ordre',
                          style: FlutterFlowTheme.of(context).headlineSmall,
                        ),
                        Text(
                          'Velg varer som skal refunderes',
                          style: FlutterFlowTheme.of(context).bodySmall,
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
            // Items list
            Expanded(
              child: SingleChildScrollView(
                padding: EdgeInsetsDirectional.fromSTEB(16.0, 0.0, 16.0, 16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Select all checkbox
                    Padding(
                      padding: EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 12.0),
                      child: Row(
                        children: [
                          Checkbox(
                            value: _selectAll,
                            onChanged: (value) => _toggleSelectAll(),
                          ),
                          Text(
                            'Velg alle',
                            style: FlutterFlowTheme.of(context).bodyLarge,
                          ),
                        ],
                      ),
                    ),
                    // Items
                    ...widget.purchaseItems.map((item) {
                      final itemId = item.purchaseItemId ?? '';
                      final isSelected = _selectedItems.containsKey(itemId);
                      final quantity = item.purchaseItemQuantity ?? 1;
                      final unitPrice = item.purchaseItemUnitPrice ?? 0;
                      final discountAmount = item.purchaseItemDiscountAmount ?? 0;
                      final selectedQuantity = _selectedItems[itemId] ?? 0;
                      final itemTotal = (unitPrice - discountAmount) * quantity;
                      final itemTotalFormatted = (itemTotal / 100).toStringAsFixed(2);

                      return Container(
                        margin: EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 12.0),
                        padding: EdgeInsetsDirectional.fromSTEB(12.0, 12.0, 12.0, 12.0),
                        decoration: BoxDecoration(
                          color: isSelected
                              ? FlutterFlowTheme.of(context).primaryBackground
                              : FlutterFlowTheme.of(context).secondaryBackground,
                          border: Border.all(
                            color: isSelected
                                ? FlutterFlowTheme.of(context).primary
                                : FlutterFlowTheme.of(context).alternate,
                            width: 1.0,
                          ),
                          borderRadius: BorderRadius.circular(8.0),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Checkbox(
                                  value: isSelected,
                                  onChanged: (value) => _toggleItem(itemId, quantity),
                                ),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        item.purchaseItemProductName ?? 'Ukjent vare',
                                        style: FlutterFlowTheme.of(context).bodyLarge,
                                      ),
                                      if (item.purchaseItemDescription != null &&
                                          item.purchaseItemDescription!.isNotEmpty)
                                        Text(
                                          item.purchaseItemDescription!,
                                          style: FlutterFlowTheme.of(context).bodySmall,
                                        ),
                                    ],
                                  ),
                                ),
                                Text(
                                  '$itemTotalFormatted kr',
                                  style: FlutterFlowTheme.of(context).bodyMedium,
                                ),
                              ],
                            ),
                            if (isSelected && quantity > 1)
                              Padding(
                                padding: EdgeInsetsDirectional.fromSTEB(40.0, 8.0, 0.0, 0.0),
                                child: Row(
                                  children: [
                                    Text(
                                      'Antall å refundere: ',
                                      style: FlutterFlowTheme.of(context).bodySmall,
                                    ),
                                    IconButton(
                                      icon: Icon(Icons.remove_circle_outline),
                                      onPressed: selectedQuantity > 1
                                          ? () => _updateItemQuantity(itemId, selectedQuantity - 1)
                                          : null,
                                    ),
                                    Text(
                                      '$selectedQuantity',
                                      style: FlutterFlowTheme.of(context).bodyMedium,
                                    ),
                                    IconButton(
                                      icon: Icon(Icons.add_circle_outline),
                                      onPressed: selectedQuantity < quantity
                                          ? () => _updateItemQuantity(itemId, selectedQuantity + 1)
                                          : null,
                                    ),
                                    Text(
                                      ' / $quantity',
                                      style: FlutterFlowTheme.of(context).bodySmall,
                                    ),
                                  ],
                                ),
                              ),
                          ],
                        ),
                      );
                    }).toList(),
                    // Reason field
                    Padding(
                      padding: EdgeInsetsDirectional.fromSTEB(0.0, 16.0, 0.0, 0.0),
                      child: TextFormField(
                        controller: _reasonController,
                        decoration: InputDecoration(
                          labelText: 'Årsak for refusjon (valgfritt)',
                          hintText: 'F.eks. "Kunde returnerte vare"',
                          border: OutlineInputBorder(),
                        ),
                        maxLines: 3,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            // Footer with totals and action button
            Container(
              padding: EdgeInsetsDirectional.fromSTEB(16.0, 16.0, 16.0, 16.0),
              decoration: BoxDecoration(
                color: FlutterFlowTheme.of(context).primaryBackground,
                border: Border(
                  top: BorderSide(
                    color: FlutterFlowTheme.of(context).alternate,
                    width: 1.0,
                  ),
                ),
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Refunderingsbeløp:',
                        style: FlutterFlowTheme.of(context).bodyLarge,
                      ),
                      Text(
                        '$refundAmountFormatted kr',
                        style: FlutterFlowTheme.of(context).headlineSmall,
                      ),
                    ],
                  ),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Gjenstående refunderbart:',
                        style: FlutterFlowTheme.of(context).bodySmall,
                      ),
                      Text(
                        '$remainingFormatted kr',
                        style: FlutterFlowTheme.of(context).bodySmall,
                      ),
                    ],
                  ),
                  Padding(
                    padding: EdgeInsetsDirectional.fromSTEB(0.0, 16.0, 0.0, 0.0),
                    child: FFButtonWidget(
                      onPressed: _isProcessing ? null : _processRefund,
                      text: widget.paymentMethod == 'cash'
                          ? 'Registrer refusjon'
                          : 'Prosesser refusjon',
                      icon: _isProcessing
                          ? const SizedBox(
                              width: 20.0,
                              height: 20.0,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.0,
                                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            )
                          : const Icon(Icons.undo),
                      options: FFButtonOptions(
                        width: double.infinity,
                        height: 50.0,
                        color: FlutterFlowTheme.of(context).primary,
                        textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                              fontFamily: 'Inter Tight',
                              color: Colors.white,
                            ),
                        elevation: 3.0,
                        borderSide: BorderSide(
                          color: Colors.transparent,
                          width: 1.0,
                        ),
                        borderRadius: BorderRadius.circular(8.0),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// ────────────────────────────────────────────────────────────────
/// FLUTTERFLOW CUSTOM ACTION: Process Order Refund
/// ────────────────────────────────────────────────────────────────

/// Process a refund for an order
/// 
/// Opens a modal to select which items from the order should be refunded,
/// then processes the refund using the appropriate payment method (cash or Stripe).
/// 
/// For compliance: If the original POS session is closed, the refund will be tracked
/// in the current open session. The original session totals remain unchanged.
/// The backend automatically detects the current open session if original is closed.
/// 
/// Parameters:
/// - context: BuildContext required for showing the dialog
/// - purchase: The purchase/order struct containing all purchase information
/// - apiBaseUrl: Base URL for the API
/// - authToken: Authentication token
/// - width: Optional width for the modal (defaults to 600.0 if null)
/// - height: Optional height for the modal (defaults to 700.0 if null)
/// 
/// Returns:
/// - success: true if refund was processed successfully
/// - data: Refund data including charge, receipt, and pos_event
/// - message: Success or error message
Future<dynamic> processOrderRefund(
  BuildContext context,
  PurchaseStruct purchase,
  String apiBaseUrl,
  String authToken,
  double? width,
  double? height,
) async {
  try {
    // Extract values from purchase struct
    final purchaseId = purchase.id ?? 0;
    final purchaseItems = purchase.purchaseItems ?? [];
    final purchaseAmount = purchase.amount ?? 0;
    final amountRefunded = purchase.amountRefunded ?? 0;
    final paymentMethod = purchase.paymentMethod ?? '';
    final originalSession = purchase.purchaseSession;
    final isOriginalSessionClosed = originalSession?.status == 'closed';

    // Validate inputs
    if (purchaseId <= 0) {
      return {
        'success': false,
        'message': 'Invalid purchase ID',
      };
    }

    if (purchaseItems.isEmpty) {
      return {
        'success': false,
        'message': 'Purchase has no items to refund',
      };
    }

    if (apiBaseUrl.isEmpty) {
      return {
        'success': false,
        'message': 'API base URL is missing',
      };
    }

    if (authToken.isEmpty) {
      return {
        'success': false,
        'message': 'Authentication token is missing. Please log in.',
      };
    }

    // Check if already fully refunded
    if (amountRefunded >= purchaseAmount) {
      return {
        'success': false,
        'message': 'Purchase is already fully refunded',
      };
    }


    // Show modal to select items
    // Use default values if width/height are null (FlutterFlow compatibility)
    final modalWidth = width ?? 600.0;
    final modalHeight = height ?? 700.0;
    
    final modalResult = await showDialog<Map<String, dynamic>>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: EdgeInsets.zero,
          child: RefundItemSelectionModal(
            purchaseItems: purchaseItems,
            purchaseAmount: purchaseAmount,
            amountRefunded: amountRefunded,
            paymentMethod: paymentMethod,
            width: modalWidth,
            height: modalHeight,
          ),
        );
      },
    );

    if (modalResult == null || !(modalResult['success'] ?? false)) {
      return {
        'success': false,
        'message': 'Refund cancelled by user',
      };
    }

    final refundAmount = modalResult['refundAmount'] as int;
    final reason = modalResult['reason'] as String?;

    // For Stripe payments, the refund is processed automatically via the API
    // For cash payments, it's just a record update (manual process)
    // Both use the same API endpoint

    // Build refunded items array for item-level tracking
    final refundedItemsList = <Map<String, dynamic>>[];
    final selectedItemsMap = modalResult['selectedItems'] as Map<String, int>;
    for (var entry in selectedItemsMap.entries) {
      refundedItemsList.add({
        'item_id': entry.key,
        'quantity': entry.value,
      });
    }

    // Make API request to process refund
    final requestBody = <String, dynamic>{
      'amount': refundAmount,
      'items': refundedItemsList,
    };

    if (reason != null && reason.isNotEmpty) {
      requestBody['reason'] = reason;
    }

    // Backend automatically detects current open session if original session is closed
    // No need to pass pos_device_id or pos_session_id - backend handles it for compliance

    final response = await http.post(
      Uri.parse('$apiBaseUrl/api/purchases/$purchaseId/refund'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $authToken',
        'Accept': 'application/json',
      },
      body: jsonEncode(requestBody),
    );

    final responseData = jsonDecode(response.body) as Map<String, dynamic>;

    // Check HTTP status code
    if (response.statusCode >= 200 && response.statusCode < 300) {
      // Success
      final data = responseData['data'] as Map<String, dynamic>? ?? {};
      final refundProcessedAutomatically = data['refund_processed_automatically'] ?? false;
      final requiresManualProcessing = data['requires_manual_processing'] ?? false;
      final manualProcessingMessage = data['manual_processing_message'] as String?;
      final receipt = data['receipt'] as Map<String, dynamic>?;
      
      return {
        'success': responseData['success'] ?? true,
        'data': data,
        'message': responseData['message'] ?? 'Refund processed successfully',
        'statusCode': response.statusCode,
        'refundAmount': refundAmount,
        'selectedItems': modalResult['selectedItems'],
        'refundProcessedAutomatically': refundProcessedAutomatically,
        'requiresManualProcessing': requiresManualProcessing,
        'manualProcessingMessage': manualProcessingMessage,
        'receipt': receipt,
        'receiptId': receipt?['id'],
        'receiptNumber': receipt?['receipt_number'],
      };
    } else {
      // Error
      return {
        'success': false,
        'message': responseData['message'] ?? 'Refund failed',
        'errors': responseData['errors'],
        'statusCode': response.statusCode,
      };
    }
  } catch (e) {
    // Handle exceptions
    return {
      'success': false,
      'message': 'Error processing refund: ${e.toString()}',
      'error': e.toString(),
      'statusCode': 0, // 0 indicates exception occurred before HTTP request
    };
  }
}

