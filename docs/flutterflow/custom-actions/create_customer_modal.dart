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
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

// Begin custom action code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

// FlutterFlow Custom Action: Create Customer Modal
//
// This action shows a modal dialog for creating a new customer.
// It handles the form UI and API calls to create customers.
//
// IMPORTANT: FlutterFlow requires Future<dynamic> as return type

Future<dynamic> createCustomerModal(
  BuildContext context,
  String apiBaseUrl,
  String authToken,
) async {
  try {
    // Validate inputs
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

    // For creating a new customer, we don't need a customer struct
    // Just create empty controllers
    final isEdit = false;
    
    // Controllers for form fields
    final nameController = TextEditingController();
    final emailController = TextEditingController();
    final phoneController = TextEditingController();
    final line1Controller = TextEditingController();
    final line2Controller = TextEditingController();
    final cityController = TextEditingController();
    final stateController = TextEditingController();
    final postalCodeController = TextEditingController();
    final countryController = TextEditingController(text: 'NO');

    // Show modal bottom sheet
    Map<String, dynamic>? result;
    try {
      result = await showModalBottomSheet<Map<String, dynamic>>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        isDismissible: true,
        enableDrag: true,
        useSafeArea: true,
        barrierColor: Colors.black54,
        builder: (context) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: _CustomerFormModal(
            isEdit: isEdit,
            nameController: nameController,
            emailController: emailController,
            phoneController: phoneController,
            line1Controller: line1Controller,
            line2Controller: line2Controller,
            cityController: cityController,
            stateController: stateController,
            postalCodeController: postalCodeController,
            countryController: countryController,
            apiBaseUrl: apiBaseUrl,
            authToken: authToken,
            customerId: null,
          ),
        ),
      );
    } catch (e) {
      // If showing the modal itself fails, return error
      return {
        'success': false,
        'message': 'Failed to show modal: ${e.toString()}',
        'error': e.toString(),
      };
    }

    return result ?? {
      'success': false,
      'message': 'Action cancelled',
    };
  } catch (e) {
    return {
      'success': false,
      'message': 'Error showing customer form: ${e.toString()}',
      'error': e.toString(),
    };
  }
}

class _CustomerFormModal extends StatefulWidget {
  final bool isEdit;
  final TextEditingController nameController;
  final TextEditingController emailController;
  final TextEditingController phoneController;
  final TextEditingController line1Controller;
  final TextEditingController line2Controller;
  final TextEditingController cityController;
  final TextEditingController stateController;
  final TextEditingController postalCodeController;
  final TextEditingController countryController;
  final String apiBaseUrl;
  final String authToken;
  final int? customerId;

  const _CustomerFormModal({
    required this.isEdit,
    required this.nameController,
    required this.emailController,
    required this.phoneController,
    required this.line1Controller,
    required this.line2Controller,
    required this.cityController,
    required this.stateController,
    required this.postalCodeController,
    required this.countryController,
    required this.apiBaseUrl,
    required this.authToken,
    this.customerId,
  });

  @override
  State<_CustomerFormModal> createState() => _CustomerFormModalState();
}

class _CustomerFormModalState extends State<_CustomerFormModal> {
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    // Ensure controllers are properly initialized
    widget.nameController;
    widget.emailController;
    widget.phoneController;
  }

  @override
  void dispose() {
    // Don't dispose controllers here - they're managed by the parent
    super.dispose();
  }

  Future<void> _saveCustomer() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      // Build customer data
      final customerData = <String, dynamic>{
        'name': widget.nameController.text.isEmpty ? null : widget.nameController.text,
        'email': widget.emailController.text.isEmpty ? null : widget.emailController.text,
        'phone': widget.phoneController.text.isEmpty ? null : widget.phoneController.text,
      };

      // Build address if any address fields are filled
      final addressData = <String, dynamic>{};
      if (widget.line1Controller.text.isNotEmpty) {
        addressData['line1'] = widget.line1Controller.text;
      }
      if (widget.line2Controller.text.isNotEmpty) {
        addressData['line2'] = widget.line2Controller.text;
      }
      if (widget.cityController.text.isNotEmpty) {
        addressData['city'] = widget.cityController.text;
      }
      if (widget.stateController.text.isNotEmpty) {
        addressData['state'] = widget.stateController.text;
      }
      if (widget.postalCodeController.text.isNotEmpty) {
        addressData['postal_code'] = widget.postalCodeController.text;
      }
      if (widget.countryController.text.isNotEmpty) {
        addressData['country'] = widget.countryController.text.toUpperCase();
      }

      if (addressData.isNotEmpty) {
        customerData['customer_address'] = addressData;
      }

      // Make API request
      final uri = widget.isEdit
          ? Uri.parse('${widget.apiBaseUrl}/api/customers/${widget.customerId}')
          : Uri.parse('${widget.apiBaseUrl}/api/customers');

      final response = widget.isEdit
          ? await http.put(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(customerData),
            )
          : await http.post(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(customerData),
            );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final responseData = jsonDecode(response.body) as Map<String, dynamic>;
        if (mounted) {
          // Return customer data in a format FlutterFlow can use
          Navigator.pop(context, {
            'success': true,
            'data': responseData,
            'customer': responseData, // Include customer object for state updates
            'message': widget.isEdit ? 'Kunde oppdatert' : 'Kunde opprettet',
          });
        }
      } else {
        final errorData = jsonDecode(response.body) as Map<String, dynamic>?;
        if (mounted) {
          setState(() {
            _errorMessage = errorData?['error'] ??
                errorData?['message'] ??
                'Failed to save customer: ${response.statusCode}';
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Error saving customer: ${e.toString()}';
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    try {
      final screenWidth = MediaQuery.of(context).size.width;
      final modalWidth = screenWidth * 0.6;
      
      return GestureDetector(
        onTap: () {
          // Prevent tap from propagating and closing the modal
          FocusScope.of(context).unfocus();
        },
        child: Material(
          color: Colors.transparent,
          child: Center(
            child: Container(
              width: modalWidth,
              constraints: const BoxConstraints(
                maxWidth: 600.0,
                minWidth: 300.0,
                minHeight: 200.0,
              ),
              decoration: BoxDecoration(
                color: FlutterFlowTheme.of(context).secondaryBackground,
                boxShadow: const [
                  BoxShadow(
                    blurRadius: 4.0,
                    color: Color(0x25090F13),
                    offset: Offset(0.0, 2.0),
                  ),
                ],
                borderRadius: BorderRadius.circular(12.0),
              ),
              child: GestureDetector(
                onTap: () {
                  // Prevent tap from closing modal when tapping inside
                },
                child: Padding(
                  padding: const EdgeInsetsDirectional.fromSTEB(16.0, 4.0, 16.0, 16.0),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                    // Header
                    Padding(
                      padding: const EdgeInsetsDirectional.fromSTEB(0.0, 12.0, 0.0, 0.0),
                      child: Row(
                        mainAxisSize: MainAxisSize.max,
                        children: [
                          Padding(
                            padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 12.0, 0.0),
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
                              onPressed: () => Navigator.pop(context),
                            ),
                          ),
                          Expanded(
                            child: Text(
                              widget.isEdit ? 'Rediger kunde' : 'Ny kunde',
                              style: FlutterFlowTheme.of(context).headlineSmall.override(
                                    fontFamily: 'Inter',
                                    letterSpacing: 0.0,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    // Divider
                    Divider(
                      height: 24.0,
                      thickness: 2.0,
                      color: FlutterFlowTheme.of(context).primaryBackground,
                    ),
                    // Form
                    Flexible(
                      child: SingleChildScrollView(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            // Error message
                            if (_errorMessage != null)
                              Container(
                                padding: const EdgeInsets.all(12.0),
                                margin: const EdgeInsets.only(bottom: 16.0),
                                decoration: BoxDecoration(
                                  color: FlutterFlowTheme.of(context).error,
                                  borderRadius: BorderRadius.circular(8.0),
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        _errorMessage!,
                                        style: FlutterFlowTheme.of(context).bodyMedium.override(
                                              fontFamily: 'Inter',
                                              color: Colors.white,
                                              letterSpacing: 0.0,
                                            ),
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.close, color: Colors.white, size: 20.0),
                                      onPressed: () {
                                        setState(() {
                                          _errorMessage = null;
                                        });
                                      },
                                    ),
                                  ],
                                ),
                              ),
                            // Name
                            TextFormField(
                              controller: widget.nameController,
                              decoration: InputDecoration(
                                labelText: 'Navn',
                                hintText: 'Kundens navn',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(8.0),
                                ),
                              ),
                              style: FlutterFlowTheme.of(context).bodyMedium.override(
                                    fontFamily: 'Inter',
                                    letterSpacing: 0.0,
                                  ),
                            ),
                            const SizedBox(height: 16.0),
                            // Email
                            TextFormField(
                              controller: widget.emailController,
                              decoration: InputDecoration(
                                labelText: 'E-post',
                                hintText: 'kunde@example.com',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(8.0),
                                ),
                              ),
                              keyboardType: TextInputType.emailAddress,
                              style: FlutterFlowTheme.of(context).bodyMedium.override(
                                    fontFamily: 'Inter',
                                    letterSpacing: 0.0,
                                  ),
                            ),
                            const SizedBox(height: 16.0),
                            // Phone
                            TextFormField(
                              controller: widget.phoneController,
                              decoration: InputDecoration(
                                labelText: 'Telefon',
                                hintText: '+47 123 45 678',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(8.0),
                                ),
                              ),
                              keyboardType: TextInputType.phone,
                              style: FlutterFlowTheme.of(context).bodyMedium.override(
                                    fontFamily: 'Inter',
                                    letterSpacing: 0.0,
                                  ),
                            ),
                            const SizedBox(height: 24.0),
                            // Address section
                            Text(
                              'Adresse',
                              style: FlutterFlowTheme.of(context).titleSmall.override(
                                    fontFamily: 'Inter',
                                    letterSpacing: 0.0,
                                  ),
                            ),
                            const SizedBox(height: 12.0),
                            // Address line 1
                            TextFormField(
                              controller: widget.line1Controller,
                              decoration: InputDecoration(
                                labelText: 'Adresselinje 1',
                                hintText: 'Gateadresse',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(8.0),
                                ),
                              ),
                              style: FlutterFlowTheme.of(context).bodyMedium.override(
                                    fontFamily: 'Inter',
                                    letterSpacing: 0.0,
                                  ),
                            ),
                            const SizedBox(height: 12.0),
                            // Address line 2
                            TextFormField(
                              controller: widget.line2Controller,
                              decoration: InputDecoration(
                                labelText: 'Adresselinje 2',
                                hintText: 'Leilighet, etasje, etc.',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(8.0),
                                ),
                              ),
                              style: FlutterFlowTheme.of(context).bodyMedium.override(
                                    fontFamily: 'Inter',
                                    letterSpacing: 0.0,
                                  ),
                            ),
                            const SizedBox(height: 12.0),
                            // City and State
                            Row(
                              children: [
                                Expanded(
                                  child: TextFormField(
                                    controller: widget.cityController,
                                    decoration: InputDecoration(
                                      labelText: 'Poststed',
                                      hintText: 'Oslo',
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(8.0),
                                      ),
                                    ),
                                    style: FlutterFlowTheme.of(context).bodyMedium.override(
                                          fontFamily: 'Inter',
                                          letterSpacing: 0.0,
                                        ),
                                  ),
                                ),
                                const SizedBox(width: 12.0),
                                Expanded(
                                  child: TextFormField(
                                    controller: widget.stateController,
                                    decoration: InputDecoration(
                                      labelText: 'Fylke',
                                      hintText: 'Oslo',
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(8.0),
                                      ),
                                    ),
                                    style: FlutterFlowTheme.of(context).bodyMedium.override(
                                          fontFamily: 'Inter',
                                          letterSpacing: 0.0,
                                        ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12.0),
                            // Postal code and Country
                            Row(
                              children: [
                                Expanded(
                                  flex: 2,
                                  child: TextFormField(
                                    controller: widget.postalCodeController,
                                    decoration: InputDecoration(
                                      labelText: 'Postnummer',
                                      hintText: '0001',
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(8.0),
                                      ),
                                    ),
                                    style: FlutterFlowTheme.of(context).bodyMedium.override(
                                          fontFamily: 'Inter',
                                          letterSpacing: 0.0,
                                        ),
                                  ),
                                ),
                                const SizedBox(width: 12.0),
                                Expanded(
                                  flex: 1,
                                  child: TextFormField(
                                    controller: widget.countryController,
                                    decoration: InputDecoration(
                                      labelText: 'Land',
                                      hintText: 'NO',
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(8.0),
                                      ),
                                    ),
                                    style: FlutterFlowTheme.of(context).bodyMedium.override(
                                          fontFamily: 'Inter',
                                          letterSpacing: 0.0,
                                        ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 32.0),
                            // Save button
                            FFButtonWidget(
                              onPressed: _isLoading ? null : _saveCustomer,
                              text: widget.isEdit ? 'Oppdater' : 'Opprett',
                              icon: _isLoading
                                  ? const SizedBox(
                                      width: 20.0,
                                      height: 20.0,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.0,
                                        valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                      ),
                                    )
                                  : null,
                              options: FFButtonOptions(
                                width: double.infinity,
                                height: 48.0,
                                padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
                                iconPadding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
                                color: FlutterFlowTheme.of(context).primary,
                                textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                                      fontFamily: 'Inter',
                                      color: Colors.white,
                                      letterSpacing: 0.0,
                                    ),
                                elevation: 0.0,
                                borderRadius: BorderRadius.circular(12.0),
                                disabledColor: FlutterFlowTheme.of(context).alternate,
                                disabledTextColor: FlutterFlowTheme.of(context).secondaryText,
                              ),
                            ),
                            const SizedBox(height: 16.0),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
    } catch (e) {
      // If there's an error, show a simple error widget
      return Container(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Error loading form',
              style: FlutterFlowTheme.of(context).headlineSmall,
            ),
            const SizedBox(height: 16.0),
            Text(
              e.toString(),
              style: FlutterFlowTheme.of(context).bodyMedium,
            ),
            const SizedBox(height: 16.0),
            FFButtonWidget(
              onPressed: () => Navigator.pop(context),
              text: 'Close',
              options: FFButtonOptions(
                width: double.infinity,
                height: 48.0,
                color: FlutterFlowTheme.of(context).primary,
                textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                      fontFamily: 'Inter',
                      color: Colors.white,
                      letterSpacing: 0.0,
                    ),
                borderRadius: BorderRadius.circular(12.0),
              ),
            ),
          ],
        ),
      );
    }
  }
}
