// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/backend/supabase/supabase.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/flutter_flow/flutter_flow_widgets.dart';
import '/custom_code/widgets/index.dart'; // Imports other custom widgets
import '/custom_code/actions/index.dart'; // Imports custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
// End custom code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

class ProductsCategoriesManager extends StatefulWidget {
  const ProductsCategoriesManager({
    super.key,
    this.width,
    this.height,
    required this.apiBaseUrl,
    required this.authToken,
    required this.storeSlug,
  });

  final double? width;
  final double? height;
  final String apiBaseUrl;
  final String authToken;
  final String storeSlug;

  @override
  State<ProductsCategoriesManager> createState() =>
      _ProductsCategoriesManagerState();
}

class _ProductsCategoriesManagerState extends State<ProductsCategoriesManager> {
  int _selectedTab = 0; // 0 = Products, 1 = Categories, 2 = Vendors
  List<Map<String, dynamic>> _products = [];
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _vendors = [];
  bool _isLoading = false;
  String? _errorMessage;
  String _searchQuery = '';
  int? _selectedCategoryId;
  Map<String, dynamic>? _editingProduct;
  Map<String, dynamic>? _editingCategory;
  Map<String, dynamic>? _editingVendor;
  bool _showProductForm = false;
  bool _showCategoryForm = false;
  bool _showVendorForm = false;
  List<int> _selectedCollectionIds = [];
  int? _selectedVendorId;
  String? _selectedArticleGroupCode;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      await Future.wait([
        _loadProducts(),
        _loadCategories(),
        _loadVendors(),
      ]);
    } catch (e) {
      if (mounted) {
      setState(() {
        _errorMessage = 'Error loading data: ${e.toString()}';
        _isLoading = false;
      });
      }
    }
  }

  Future<void> _loadProducts() async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/products').replace(
        queryParameters: {
          'per_page': '100',
          if (_searchQuery.isNotEmpty) 'search': _searchQuery,
          if (_selectedCategoryId != null && _selectedCategoryId != 0)
            'collection_id': _selectedCategoryId.toString(),
          if (_selectedCategoryId == 0) 'collection_id': '0',
        },
      );

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
        final products = (data['product'] as List<dynamic>?)
                ?.map((p) => p as Map<String, dynamic>)
                .toList() ??
            [];

        if (mounted) {
        setState(() {
          _products = products;
          _isLoading = false;
        });
        }
      } else {
        throw Exception('Failed to load products: ${response.statusCode}');
      }
    } catch (e) {
      if (mounted) {
      setState(() {
        _errorMessage = 'Error loading products: ${e.toString()}';
        _isLoading = false;
      });
      }
    }
  }

  Future<void> _loadCategories() async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/collections').replace(
        queryParameters: {
          'per_page': '100',
        },
      );

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
        final categories = (data['collections'] as List<dynamic>?)
                ?.map((c) => c as Map<String, dynamic>)
                .toList() ??
            [];

        // Add "Uncategorized" option
        final uncategorizedCount = _products
            .where((p) =>
                (p['collections'] as List<dynamic>?)?.isEmpty ?? true)
            .length;
        if (uncategorizedCount > 0) {
          categories.insert(0, {
            'id': 0,
            'name': 'Ukategorisert',
            'products_count': uncategorizedCount,
          });
        }

        setState(() {
          _categories = categories;
        });
      } else {
        throw Exception('Failed to load categories: ${response.statusCode}');
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error loading categories: ${e.toString()}';
      });
    }
  }

  String _parseErrorMessage(String responseBody, int statusCode) {
    // Try to parse JSON error response
    if (responseBody.isNotEmpty) {
      try {
        final errorData = jsonDecode(responseBody) as Map<String, dynamic>?;
        if (errorData != null) {
          // Handle Laravel validation errors (422) - most common case
          if (errorData.containsKey('errors')) {
            final errors = errorData['errors'] as Map<String, dynamic>?;
            if (errors != null && errors.isNotEmpty) {
              final errorMessages = <String>[];
              errors.forEach((field, messages) {
                if (messages is List) {
                  for (var message in messages) {
                    // Format field name nicely (e.g., "name" -> "Navn")
                    final fieldName = _formatFieldName(field);
                    errorMessages.add('$fieldName: $message');
                  }
                } else {
                  final fieldName = _formatFieldName(field);
                  errorMessages.add('$fieldName: $messages');
                }
              });
              return errorMessages.join('\n');
            }
          }
          // Handle error message field
          if (errorData.containsKey('message')) {
            final message = errorData['message'];
            if (message is String && message.isNotEmpty) {
              return message;
            }
          }
          if (errorData.containsKey('error')) {
            final error = errorData['error'];
            if (error is String && error.isNotEmpty) {
              return error;
            }
          }
        }
      } catch (e) {
        // If JSON parsing fails, try to use the raw response if it's meaningful
        if (responseBody.length < 200) {
          return responseBody;
        }
      }
    }
    
    // Default error messages based on status code
    switch (statusCode) {
      case 422:
        return 'Valideringsfeil: Noen felt er ugyldige eller mangler. Sjekk at alle påkrevde felt er fylt ut korrekt.';
      case 400:
        return 'Ugyldig forespørsel. Sjekk at alle påkrevde felt er fylt ut.';
      case 401:
        return 'Du er ikke autorisert. Logg inn på nytt.';
      case 403:
        return 'Du har ikke tilgang til denne operasjonen.';
      case 404:
        return 'Ressursen ble ikke funnet.';
      case 500:
        return 'Serverfeil. Prøv igjen senere.';
      default:
        return 'Kunne ikke lagre. Statuskode: $statusCode';
    }
  }

  String _formatFieldName(String field) {
    // Convert field names to Norwegian labels
    final fieldMap = {
      'name': 'Navn',
      'description': 'Beskrivelse',
      'type': 'Type',
      'price': 'Pris',
      'currency': 'Valuta',
      'active': 'Aktiv',
      'shippable': 'Kan sendes',
      'no_price_in_pos': 'Ingen pris i kassa',
      'collection_ids': 'Kategorier',
      'vendor_id': 'Leverandør',
      'article_group_code': 'Varegruppekode',
    };
    return fieldMap[field] ?? field;
  }

  Future<void> _saveProduct(Map<String, dynamic> productData) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final isUpdate = productData['id'] != null;
      final uri = isUpdate
          ? Uri.parse(
              '${widget.apiBaseUrl}/api/products/${productData['id']}')
          : Uri.parse('${widget.apiBaseUrl}/api/products');

      final response = isUpdate
          ? await http.put(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(productData),
            )
          : await http.post(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(productData),
            );

      if (response.statusCode == 200 || response.statusCode == 201) {
        setState(() {
          _showProductForm = false;
          _editingProduct = null;
          _selectedCollectionIds = [];
          _selectedVendorId = null;
          _selectedArticleGroupCode = null;
        });
        await _loadData();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(isUpdate
                  ? 'Produkt oppdatert'
                  : 'Produkt opprettet'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        // Parse error message from response
        final errorMessage = _parseErrorMessage(response.body, response.statusCode);
        setState(() {
          _errorMessage = errorMessage;
          _isLoading = false;
          // Keep form state intact - don't reset _showProductForm or _editingProduct
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Feil ved lagring av produkt: ${e.toString()}';
        _isLoading = false;
        // Keep form state intact - don't reset _showProductForm or _editingProduct
      });
    }
  }

  Future<void> _saveCategory(Map<String, dynamic> categoryData) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final isUpdate = categoryData['id'] != null;
      final uri = isUpdate
          ? Uri.parse(
              '${widget.apiBaseUrl}/api/collections/${categoryData['id']}')
          : Uri.parse('${widget.apiBaseUrl}/api/collections');

      final response = isUpdate
          ? await http.put(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(categoryData),
            )
          : await http.post(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(categoryData),
            );

      if (response.statusCode == 200 || response.statusCode == 201) {
        setState(() {
          _showCategoryForm = false;
          _editingCategory = null;
        });
        await _loadData();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(isUpdate
                  ? 'Kategori oppdatert'
                  : 'Kategori opprettet'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        // Parse error message from response
        final errorMessage = _parseErrorMessage(response.body, response.statusCode);
        setState(() {
          _errorMessage = errorMessage;
          _isLoading = false;
          // Keep form state intact - don't reset _showCategoryForm or _editingCategory
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Feil ved lagring av kategori: ${e.toString()}';
        _isLoading = false;
        // Keep form state intact - don't reset _showCategoryForm or _editingCategory
      });
    }
  }

  void _editProduct(Map<String, dynamic> product) {
    setState(() {
      _editingProduct = Map<String, dynamic>.from(product);
      _showProductForm = true;
      // Extract current collections from product
      final currentCollections = product['collections'] as List<dynamic>? ?? [];
      _selectedCollectionIds = currentCollections
          .map((c) => (c as Map<String, dynamic>)['id'] as int?)
          .where((id) => id != null && id != 0)
          .cast<int>()
          .toList();
      // Extract vendor_id
      final vendor = product['vendor'] as Map<String, dynamic>?;
      _selectedVendorId = vendor?['id'] as int? ?? product['vendor_id'] as int?;
      // Extract article_group_code
      _selectedArticleGroupCode = product['article_group_code'] as String?;
    });
  }

  void _editCategory(Map<String, dynamic> category) {
    setState(() {
      _editingCategory = Map<String, dynamic>.from(category);
      _showCategoryForm = true;
    });
  }

  void _newProduct() {
    setState(() {
      _editingProduct = {
        'name': '',
        'description': '',
        'type': 'service',
        'active': true,
        'shippable': false,
        'no_price_in_pos': false,
        'price': null,
        'currency': 'nok',
      };
      _selectedCollectionIds = [];
      _selectedVendorId = null;
      _selectedArticleGroupCode = null;
      _showProductForm = true;
    });
  }

  void _newCategory() {
    setState(() {
      _editingCategory = {
        'name': '',
        'description': '',
        'active': true,
        'sort_order': 0,
      };
      _showCategoryForm = true;
    });
  }

  Future<void> _loadVendors() async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/vendors').replace(
        queryParameters: {
          'per_page': '100',
        },
      );

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
        final vendors = (data['vendors'] as List<dynamic>?)
                ?.map((v) => v as Map<String, dynamic>)
                .toList() ??
            (data['data'] as List<dynamic>?)
                ?.map((v) => v as Map<String, dynamic>)
                .toList() ??
            [];

        setState(() {
          _vendors = vendors;
        });
      } else {
        // If vendors endpoint doesn't exist, set empty list
        setState(() {
          _vendors = [];
        });
      }
    } catch (e) {
      // If vendors endpoint doesn't exist, set empty list
      setState(() {
        _vendors = [];
      });
    }
  }

  Future<void> _saveVendor(Map<String, dynamic> vendorData) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final isUpdate = vendorData['id'] != null;
      final uri = isUpdate
          ? Uri.parse('${widget.apiBaseUrl}/api/vendors/${vendorData['id']}')
          : Uri.parse('${widget.apiBaseUrl}/api/vendors');

      final response = isUpdate
          ? await http.put(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(vendorData),
            )
          : await http.post(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(vendorData),
            );

      if (response.statusCode == 200 || response.statusCode == 201) {
        setState(() {
          _showVendorForm = false;
          _editingVendor = null;
        });
        await _loadVendors();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(isUpdate
                  ? 'Leverandør oppdatert'
                  : 'Leverandør opprettet'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        final errorMessage = _parseErrorMessage(response.body, response.statusCode);
        setState(() {
          _errorMessage = errorMessage;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Feil ved lagring av leverandør: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  void _editVendor(Map<String, dynamic> vendor) {
    setState(() {
      _editingVendor = Map<String, dynamic>.from(vendor);
      _showVendorForm = true;
    });
  }

  void _newVendor() {
    setState(() {
      _editingVendor = {
        'name': '',
        'description': '',
        'contact_email': '',
        'contact_phone': '',
        'active': true,
      };
      _showVendorForm = true;
    });
  }

  String _formatPrice(int? amount) {
    if (amount == null) return 'Ingen pris';
    return '${(amount / 100).toStringAsFixed(2).replaceAll('.', ',')} kr';
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading && _products.isEmpty && _categories.isEmpty) {
      return SizedBox(
        width: widget.width,
        height: widget.height,
        child: const Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    return SizedBox(
      width: widget.width,
      height: widget.height,
      child: Column(
        children: [
          // Tab selector
          Container(
            decoration: BoxDecoration(
              color: FlutterFlowTheme.of(context).secondaryBackground,
              border: Border(
                bottom: BorderSide(
                  color: FlutterFlowTheme.of(context).alternate,
                ),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: () {
                      setState(() {
                        _selectedTab = 0;
                        _showProductForm = false;
                        _showCategoryForm = false;
                        _showVendorForm = false;
                      });
                    },
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        border: Border(
                          bottom: BorderSide(
                            color: _selectedTab == 0
                                ? FlutterFlowTheme.of(context).primary
                                : Colors.transparent,
                            width: 2,
                          ),
                        ),
                      ),
                      child: Text(
                        'Produkter',
                        textAlign: TextAlign.center,
                        style: FlutterFlowTheme.of(context).titleMedium.override(
                              fontFamily: 'Readex Pro',
                              color: _selectedTab == 0
                                  ? FlutterFlowTheme.of(context).primary
                                  : FlutterFlowTheme.of(context).secondaryText,
                            ),
                      ),
                    ),
                  ),
                ),
                Expanded(
                  child: InkWell(
                    onTap: () {
                      setState(() {
                        _selectedTab = 1;
                        _showProductForm = false;
                        _showCategoryForm = false;
                        _showVendorForm = false;
                      });
                    },
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        border: Border(
                          bottom: BorderSide(
                            color: _selectedTab == 1
                                ? FlutterFlowTheme.of(context).primary
                                : Colors.transparent,
                            width: 2,
                          ),
                        ),
                      ),
                      child: Text(
                        'Kategorier',
                        textAlign: TextAlign.center,
                        style: FlutterFlowTheme.of(context).titleMedium.override(
                              fontFamily: 'Readex Pro',
                              color: _selectedTab == 1
                                  ? FlutterFlowTheme.of(context).primary
                                  : FlutterFlowTheme.of(context).secondaryText,
                            ),
                      ),
                    ),
                  ),
                ),
                Expanded(
                  child: InkWell(
                    onTap: () {
                      setState(() {
                        _selectedTab = 2;
                        _showProductForm = false;
                        _showCategoryForm = false;
                        _showVendorForm = false;
                      });
                    },
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        border: Border(
                          bottom: BorderSide(
                            color: _selectedTab == 2
                                ? FlutterFlowTheme.of(context).primary
                                : Colors.transparent,
                            width: 2,
                          ),
                        ),
                      ),
                      child: Text(
                        'Leverandører',
                        textAlign: TextAlign.center,
                        style: FlutterFlowTheme.of(context).titleMedium.override(
                              fontFamily: 'Readex Pro',
                              color: _selectedTab == 2
                                  ? FlutterFlowTheme.of(context).primary
                                  : FlutterFlowTheme.of(context).secondaryText,
                            ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Error message
          if (_errorMessage != null)
            Container(
              padding: const EdgeInsets.all(16),
              color: FlutterFlowTheme.of(context).error,
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      _errorMessage!,
                      style: FlutterFlowTheme.of(context).bodyMedium.override(
                            fontFamily: 'Readex Pro',
                            color: Colors.white,
                          ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: () {
                      setState(() {
                        _errorMessage = null;
                      });
                    },
                  ),
                ],
              ),
            ),

          // Content
          Expanded(
            child: _selectedTab == 0
                ? _buildProductsTab()
                : _selectedTab == 1
                    ? _buildCategoriesTab()
                    : _buildVendorsTab(),
          ),
        ],
      ),
    );
  }

  Widget _buildProductsTab() {
    if (_showProductForm) {
      return _buildProductForm();
    }

    return Column(
      children: [
        // Search and filter bar
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: FlutterFlowTheme.of(context).secondaryBackground,
            border: Border(
              bottom: BorderSide(
                color: FlutterFlowTheme.of(context).alternate,
              ),
            ),
          ),
          child: Column(
            children: [
              TextField(
                decoration: InputDecoration(
                  hintText: 'Søk etter produkter...',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _searchQuery.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear),
                          onPressed: () {
                            setState(() {
                              _searchQuery = '';
                            });
                            _loadProducts();
                          },
                        )
                      : null,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                onChanged: (value) {
                  setState(() {
                    _searchQuery = value;
                  });
                },
                onSubmitted: (_) => _loadProducts(),
              ),
              const SizedBox(height: 12),
              // Category filter
              DropdownButtonFormField<int?>(
                value: _selectedCategoryId,
                decoration: InputDecoration(
                  labelText: 'Kategori',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('Alle kategorier'),
                  ),
                  ..._categories.map((category) {
                    return DropdownMenuItem<int?>(
                      value: category['id'] as int?,
                      child: Text(
                          '${category['name']} (${category['products_count'] ?? 0})'),
                    );
                  }),
                ],
                onChanged: (value) {
                  setState(() {
                    _selectedCategoryId = value;
                  });
                  _loadProducts();
                },
              ),
            ],
          ),
        ),

        // Products list
        Expanded(
          child: _products.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.inventory_2_outlined,
                        size: 64,
                        color: FlutterFlowTheme.of(context).secondaryText,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Ingen produkter funnet',
                        style: FlutterFlowTheme.of(context).titleMedium,
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadProducts,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(0, 12.0, 0, 12.0),
                    itemCount: _products.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12.0),
                    itemBuilder: (context, index) {
                      final product = _products[index];
                      final productPrice = product['product_price'] as Map<String, dynamic>?;
                      final priceAmount = productPrice?['amount'] as int?;
                      final images = product['images'] as List<dynamic>?;
                      final imageUrl = images?.isNotEmpty == true
                          ? images![0] as String?
                          : null;

                      return InkWell(
                        splashColor: Colors.transparent,
                        focusColor: Colors.transparent,
                        hoverColor: Colors.transparent,
                        highlightColor: Colors.transparent,
                        onTap: () => _editProduct(product),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          curve: Curves.easeInOut,
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: FlutterFlowTheme.of(context).secondaryBackground,
                            borderRadius: BorderRadius.circular(16.0),
                          ),
                          child: Padding(
                            padding: const EdgeInsetsDirectional.fromSTEB(12.0, 8.0, 12.0, 8.0),
                            child: Row(
                              mainAxisSize: MainAxisSize.max,
                              mainAxisAlignment: MainAxisAlignment.start,
                              children: [
                                // Icon container
                                Container(
                                  width: 32.0,
                                  height: 32.0,
                                  decoration: BoxDecoration(
                                    color: FlutterFlowTheme.of(context).primaryBackground,
                                    borderRadius: BorderRadius.circular(8.0),
                                    border: Border.all(
                                      color: FlutterFlowTheme.of(context).alternate,
                                    ),
                                  ),
                                  child: Align(
                                    alignment: const AlignmentDirectional(0.0, 0.0),
                                    child: imageUrl != null
                                        ? ClipRRect(
                                            borderRadius: BorderRadius.circular(8.0),
                                            child: Image.network(
                                              imageUrl,
                                              width: 32.0,
                                              height: 32.0,
                                              fit: BoxFit.cover,
                                              errorBuilder: (context, error, stackTrace) {
                                                return Icon(
                                                  Icons.inventory_2_outlined,
                                                  color: FlutterFlowTheme.of(context).secondaryText,
                                                  size: 24.0,
                                                );
                                              },
                                            ),
                                          )
                                        : Icon(
                                            Icons.inventory_2_outlined,
                                            color: FlutterFlowTheme.of(context).secondaryText,
                                            size: 24.0,
                                          ),
                                  ),
                                ),
                                // Content
                                Expanded(
                                  child: Padding(
                                    padding: const EdgeInsetsDirectional.fromSTEB(12.0, 0.0, 0.0, 0.0),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.max,
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          product['name'] as String? ?? 'Unnamed',
                                          style: FlutterFlowTheme.of(context).bodyMedium.override(
                                                fontFamily: 'Inter',
                                                fontSize: 16.0,
                                                letterSpacing: 0.0,
                                                fontWeight: FontWeight.w600,
                                              ),
                                        ),
                                        if (product['description'] != null) ...[
                                          const SizedBox(height: 4.0),
                                          Text(
                                            (product['description'] as String).length > 50
                                                ? '${(product['description'] as String).substring(0, 50)}...'
                                                : (product['description'] as String),
                                            style: FlutterFlowTheme.of(context).labelMedium.override(
                                                  fontFamily: 'Inter',
                                                  fontSize: 16.0,
                                                  letterSpacing: 0.0,
                                                ),
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ],
                                        // Show categories
                                        if ((product['collections'] as List<dynamic>?)?.isNotEmpty == true) ...[
                                          const SizedBox(height: 4.0),
                                          Wrap(
                                            spacing: 6.0,
                                            runSpacing: 3.0,
                                            children: (product['collections'] as List<dynamic>)
                                                .map((c) {
                                              final collectionName = (c as Map<String, dynamic>)['name'] as String?;
                                              if (collectionName == null) {
                                                return const SizedBox.shrink();
                                              }
                                              return Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 8.0, vertical: 4.0),
                                                decoration: BoxDecoration(
                                                  color: FlutterFlowTheme.of(context).primaryBackground,
                                                  borderRadius: BorderRadius.circular(8.0),
                                                  border: Border.all(
                                                    color: FlutterFlowTheme.of(context).alternate,
                                                  ),
                                                ),
                                                child: Text(
                                                  collectionName,
                                                  style: FlutterFlowTheme.of(context).labelSmall.override(
                                                        fontFamily: 'Inter',
                                                        fontSize: 12.0,
                                                      ),
                                                ),
                                              );
                                            }).toList(),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                ),
                                // Price and chevron
                                Expanded(
                                  child: Align(
                                    alignment: const AlignmentDirectional(1.0, 0.0),
                                    child: Padding(
                                      padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 20.0, 0.0),
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        crossAxisAlignment: CrossAxisAlignment.end,
                                        children: [
                                          Text(
                                            _formatPrice(priceAmount),
                                            style: FlutterFlowTheme.of(context).bodyMedium.override(
                                                  fontFamily: 'Inter',
                                                  fontSize: 16.0,
                                                  letterSpacing: 0.0,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                          ),
                                          const SizedBox(height: 4.0),
                                          Icon(
                                            Icons.chevron_right,
                                            color: FlutterFlowTheme.of(context).secondaryText,
                                            size: 24.0,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),

        // Floating action button
        if (!_isLoading)
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(16.0, 0.0, 16.0, 16.0),
            child: FFButtonWidget(
              onPressed: _newProduct,
              text: 'Nytt produkt',
              icon: const Icon(Icons.add, size: 20.0),
              options: FFButtonOptions(
                width: double.infinity,
                height: 48.0,
                padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
                iconPadding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
                color: FlutterFlowTheme.of(context).primary,
                textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                      fontFamily: 'Inter',
                      color: Colors.white,
                      letterSpacing: 0.0,
                    ),
                elevation: 0.0,
                borderRadius: BorderRadius.circular(12.0),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildCategoriesTab() {
    if (_showCategoryForm) {
      return _buildCategoryForm();
    }

    return Column(
      children: [
        // Categories list
        Expanded(
          child: _categories.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.category_outlined,
                        size: 64,
                        color: FlutterFlowTheme.of(context).secondaryText,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Ingen kategorier funnet',
                        style: FlutterFlowTheme.of(context).titleMedium,
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadCategories,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(0, 12.0, 0, 12.0),
                    itemCount: _categories.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12.0),
                    itemBuilder: (context, index) {
                      final category = _categories[index];
                      final categoryId = category['id'] as int?;
                      final isUncategorized = categoryId == 0;

                      return InkWell(
                        splashColor: Colors.transparent,
                        focusColor: Colors.transparent,
                        hoverColor: Colors.transparent,
                        highlightColor: Colors.transparent,
                        onTap: isUncategorized ? null : () => _editCategory(category),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          curve: Curves.easeInOut,
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: FlutterFlowTheme.of(context).secondaryBackground,
                            borderRadius: BorderRadius.circular(16.0),
                          ),
                          child: Padding(
                            padding: const EdgeInsetsDirectional.fromSTEB(12.0, 8.0, 12.0, 8.0),
                            child: Row(
                              mainAxisSize: MainAxisSize.max,
                              mainAxisAlignment: MainAxisAlignment.start,
                              children: [
                                // Icon container
                                Container(
                                  width: 32.0,
                                  height: 32.0,
                                  decoration: BoxDecoration(
                                    color: FlutterFlowTheme.of(context).primaryBackground,
                                    borderRadius: BorderRadius.circular(8.0),
                                    border: Border.all(
                                      color: FlutterFlowTheme.of(context).alternate,
                                    ),
                                  ),
                                  child: Align(
                                    alignment: const AlignmentDirectional(0.0, 0.0),
                                    child: Icon(
                                      Icons.category_outlined,
                                      color: FlutterFlowTheme.of(context).secondaryText,
                                      size: 24.0,
                                    ),
                                  ),
                                ),
                                // Content
                                Expanded(
                                  child: Padding(
                                    padding: const EdgeInsetsDirectional.fromSTEB(12.0, 0.0, 0.0, 0.0),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.max,
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          category['name'] as String? ?? 'Unnamed',
                                          style: FlutterFlowTheme.of(context).bodyMedium.override(
                                                fontFamily: 'Inter',
                                                fontSize: 16.0,
                                                letterSpacing: 0.0,
                                                fontWeight: FontWeight.w600,
                                              ),
                                        ),
                                        const SizedBox(height: 4.0),
                                        Text(
                                          '${category['products_count'] ?? 0} produkter',
                                          style: FlutterFlowTheme.of(context).labelMedium.override(
                                                fontFamily: 'Inter',
                                                fontSize: 16.0,
                                                letterSpacing: 0.0,
                                              ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                                // Chevron
                                if (!isUncategorized)
                                  Icon(
                                    Icons.chevron_right,
                                    color: FlutterFlowTheme.of(context).secondaryText,
                                    size: 24.0,
                                  ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),

        // Floating action button
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(16.0, 0.0, 16.0, 16.0),
          child: FFButtonWidget(
            onPressed: _newCategory,
            text: 'Ny kategori',
            icon: const Icon(Icons.add, size: 20.0),
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
              color: FlutterFlowTheme.of(context).primary,
              textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                    fontFamily: 'Inter',
                    color: Colors.white,
                    letterSpacing: 0.0,
                  ),
              elevation: 0.0,
              borderRadius: BorderRadius.circular(12.0),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildProductForm() {
    final product = _editingProduct ?? {};
    final nameController = TextEditingController(
        text: product['name'] as String? ?? '');
    final descriptionController = TextEditingController(
        text: product['description'] as String? ?? '');
    // Extract price from product_price if available
    final productPrice = product['product_price'] as Map<String, dynamic>?;
    final priceAmount = productPrice?['amount'] as int?;
    final priceController = TextEditingController(
        text: priceAmount != null
            ? (priceAmount / 100).toString()
            : (product['price'] != null
                ? (product['price'] as num).toString()
                : ''));
    final currencyController = TextEditingController(
        text: productPrice?['currency']?.toString().toLowerCase() ??
            product['currency'] as String? ??
            'nok');
    final typeController = TextEditingController(
        text: product['type'] as String? ?? 'service');
    
    // Use state variables that will be updated
    bool formActive = product['active'] as bool? ?? true;
    bool formShippable = product['shippable'] as bool? ?? false;
    bool formNoPriceInPos = product['no_price_in_pos'] as bool? ?? false;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                product['id'] != null ? 'Rediger produkt' : 'Nytt produkt',
                style: FlutterFlowTheme.of(context).headlineSmall,
              ),
              IconButton(
                icon: const Icon(Icons.close),
                onPressed: () {
                  setState(() {
                    _showProductForm = false;
                    _editingProduct = null;
                    _selectedCollectionIds = [];
                    _selectedVendorId = null;
                    _selectedArticleGroupCode = null;
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 16),
          // Grid layout: left side fields, right side categories
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Left side: Product fields
              Expanded(
                flex: 3,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextField(
                      controller: nameController,
                      decoration: const InputDecoration(
                        labelText: 'Navn *',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: descriptionController,
                      decoration: const InputDecoration(
                        labelText: 'Beskrivelse',
                        border: OutlineInputBorder(),
                      ),
                      maxLines: 3,
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      value: typeController.text,
                      decoration: const InputDecoration(
                        labelText: 'Type',
                        border: OutlineInputBorder(),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'service', child: Text('Tjeneste')),
                        DropdownMenuItem(value: 'good', child: Text('Vare')),
                      ],
                      onChanged: (value) {
                        typeController.text = value ?? 'service';
                      },
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: priceController,
                            decoration: const InputDecoration(
                              labelText: 'Pris',
                              border: OutlineInputBorder(),
                            ),
                            keyboardType: const TextInputType.numberWithOptions(
                                decimal: true),
                          ),
                        ),
                        const SizedBox(width: 16),
                        SizedBox(
                          width: 100,
                          child: TextField(
                            controller: currencyController,
                            decoration: const InputDecoration(
                              labelText: 'Valuta',
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    // Vendor selection
                    DropdownButtonFormField<int?>(
                      value: _selectedVendorId,
                      decoration: const InputDecoration(
                        labelText: 'Leverandør',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        const DropdownMenuItem<int?>(
                          value: null,
                          child: Text('Ingen leverandør'),
                        ),
                        ..._vendors.map((vendor) {
                          return DropdownMenuItem<int?>(
                            value: vendor['id'] as int?,
                            child: Text(vendor['name'] as String? ?? 'Unnamed'),
                          );
                        }),
                      ],
                      onChanged: (value) {
                        setState(() {
                          _selectedVendorId = value;
                        });
                      },
                    ),
                    const SizedBox(height: 16),
                    // Article group code selection
                    DropdownButtonFormField<String?>(
                      value: _selectedArticleGroupCode,
                      decoration: const InputDecoration(
                        labelText: 'Varegruppekode (SAF-T)',
                        border: OutlineInputBorder(),
                        helperText: 'PredefinedBasicID-04: Produktkategori for SAF-T rapportering',
                      ),
                      items: [
                        const DropdownMenuItem<String?>(
                          value: null,
                          child: Text('Ingen varegruppekode'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04001',
                          child: Text('04001 - Uttak av behandlingstjenester'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04002',
                          child: Text('04002 - Uttak av behandlingsvarer'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04003',
                          child: Text('04003 - Varesalg'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04004',
                          child: Text('04004 - Salg av behandlingstjenester'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04005',
                          child: Text('04005 - Salg av hårklipp'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04006',
                          child: Text('04006 - Mat'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04007',
                          child: Text('04007 - Øl'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04008',
                          child: Text('04008 - Vin'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04009',
                          child: Text('04009 - Brennevin'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04010',
                          child: Text('04010 - Rusbrus/Cider'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04011',
                          child: Text('04011 - Mineralvann (brus)'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04012',
                          child: Text('04012 - Annen drikke (te, kaffe etc)'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04013',
                          child: Text('04013 - Tobakk'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04014',
                          child: Text('04014 - Andre varer'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04015',
                          child: Text('04015 - Inngangspenger'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04016',
                          child: Text('04016 - Inngangspenger fri adgang'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04017',
                          child: Text('04017 - Garderobeavgift'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04018',
                          child: Text('04018 - Garderobeavgift fri garderobe'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04019',
                          child: Text('04019 - Helfullpensjon'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04020',
                          child: Text('04020 - Halvpensjon'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04021',
                          child: Text('04021 - Overnatting med frokost'),
                        ),
                        const DropdownMenuItem<String>(
                          value: '04999',
                          child: Text('04999 - Øvrige'),
                        ),
                      ],
                      onChanged: (value) {
                        setState(() {
                          _selectedArticleGroupCode = value;
                        });
                      },
                    ),
                    const SizedBox(height: 16),
                    StatefulBuilder(
                      builder: (context, setStateLocal) {
                        return Column(
                          children: [
                            SwitchListTile(
                              title: const Text('Aktiv'),
                              value: formActive,
                              activeColor: FlutterFlowTheme.of(context).primary,
                              onChanged: (value) {
                                setStateLocal(() {
                                  formActive = value;
                                });
                              },
                            ),
                            SwitchListTile(
                              title: const Text('Kan sendes'),
                              value: formShippable,
                              activeColor: FlutterFlowTheme.of(context).primary,
                              onChanged: (value) {
                                setStateLocal(() {
                                  formShippable = value;
                                });
                              },
                            ),
                            SwitchListTile(
                              title: const Text('Ingen pris i kassa'),
                              subtitle: const Text('Produktet vil ikke ha pris i kassasystemet'),
                              value: formNoPriceInPos,
                              activeColor: FlutterFlowTheme.of(context).primary,
                              onChanged: (value) {
                                setStateLocal(() {
                                  formNoPriceInPos = value;
                                });
                              },
                            ),
                          ],
                        );
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              // Right side: Categories
              Expanded(
                flex: 2,
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: FlutterFlowTheme.of(context).alternate,
                    ),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: StatefulBuilder(
                    builder: (context, setStateLocal) {
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'Kategorier',
                            style: FlutterFlowTheme.of(context).titleSmall,
                          ),
                          const SizedBox(height: 8),
                          if (_categories.isEmpty)
                            Text(
                              'Ingen kategorier tilgjengelig',
                              style: FlutterFlowTheme.of(context).bodySmall,
                            )
                          else
                            ConstrainedBox(
                              constraints: const BoxConstraints(
                                maxHeight: 400.0,
                              ),
                              child: SingleChildScrollView(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: _categories
                                      .where((c) => (c['id'] as int?) != 0)
                                      .map((category) {
                                    final categoryId = category['id'] as int?;
                                    if (categoryId == null) return const SizedBox.shrink();
                                    
                                    final isSelected =
                                        _selectedCollectionIds.contains(categoryId);
                                    
                                    return CheckboxListTile(
                                      title: Text(category['name'] as String? ?? 'Unnamed'),
                                      subtitle: Text(
                                        '${category['products_count'] ?? 0} produkter',
                                        style: FlutterFlowTheme.of(context).bodySmall,
                                      ),
                                      value: isSelected,
                                      activeColor: FlutterFlowTheme.of(context).primary,
                                      onChanged: (value) {
                                        setState(() {
                                          if (value == true) {
                                            if (!_selectedCollectionIds
                                                .contains(categoryId)) {
                                              _selectedCollectionIds.add(categoryId);
                                            }
                                          } else {
                                            _selectedCollectionIds.remove(categoryId);
                                          }
                                        });
                                      },
                                      dense: true,
                                      contentPadding: EdgeInsets.zero,
                                    );
                                  }).toList(),
                                ),
                              ),
                            ),
                          if (_selectedCollectionIds.isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: _selectedCollectionIds.map((id) {
                                final category = _categories.firstWhere(
                                  (c) => c['id'] == id,
                                  orElse: () => {'name': 'Unknown'},
                                );
                                return Chip(
                                  label: Text(category['name'] as String? ?? ''),
                                  onDeleted: () {
                                    setState(() {
                                      _selectedCollectionIds.remove(id);
                                    });
                                  },
                                  deleteIcon: const Icon(Icons.close, size: 18),
                                );
                              }).toList(),
                            ),
                          ],
                        ],
                      );
                    },
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          FFButtonWidget(
            onPressed: _isLoading
                ? null
                : () {
                    final productData = {
                      if (product['id'] != null) 'id': product['id'],
                      'name': nameController.text,
                      'description': descriptionController.text.isEmpty
                          ? null
                          : descriptionController.text,
                      'type': typeController.text,
                      'active': formActive,
                      'shippable': formShippable,
                      'no_price_in_pos': formNoPriceInPos,
                      if (priceController.text.isNotEmpty)
                        'price': double.tryParse(priceController.text),
                      'currency': currencyController.text.toLowerCase(),
                      'collection_ids': _selectedCollectionIds,
                      if (_selectedVendorId != null) 'vendor_id': _selectedVendorId,
                      if (_selectedArticleGroupCode != null) 'article_group_code': _selectedArticleGroupCode,
                    };
                    _saveProduct(productData);
                  },
            text: product['id'] != null ? 'Oppdater' : 'Opprett',
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
              color: FlutterFlowTheme.of(context).primary,
              textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                    fontFamily: 'Inter',
                    color: Colors.white,
                    letterSpacing: 0.0,
                  ),
              elevation: 0.0,
              borderRadius: BorderRadius.circular(12.0),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildVendorsTab() {
    if (_showVendorForm) {
      return _buildVendorForm();
    }

    return Column(
      children: [
        // Vendors list
        Expanded(
          child: _vendors.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.store_outlined,
                        size: 64,
                        color: FlutterFlowTheme.of(context).secondaryText,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Ingen leverandører funnet',
                        style: FlutterFlowTheme.of(context).titleMedium,
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadVendors,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(0, 12.0, 0, 12.0),
                    itemCount: _vendors.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12.0),
                    itemBuilder: (context, index) {
                      final vendor = _vendors[index];

                      return InkWell(
                        splashColor: Colors.transparent,
                        focusColor: Colors.transparent,
                        hoverColor: Colors.transparent,
                        highlightColor: Colors.transparent,
                        onTap: () => _editVendor(vendor),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          curve: Curves.easeInOut,
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: FlutterFlowTheme.of(context).secondaryBackground,
                            borderRadius: BorderRadius.circular(16.0),
                          ),
                          child: Padding(
                            padding: const EdgeInsetsDirectional.fromSTEB(12.0, 8.0, 12.0, 8.0),
                            child: Row(
                              mainAxisSize: MainAxisSize.max,
                              mainAxisAlignment: MainAxisAlignment.start,
                              children: [
                                // Icon container
                                Container(
                                  width: 32.0,
                                  height: 32.0,
                                  decoration: BoxDecoration(
                                    color: FlutterFlowTheme.of(context).primaryBackground,
                                    borderRadius: BorderRadius.circular(8.0),
                                    border: Border.all(
                                      color: FlutterFlowTheme.of(context).alternate,
                                    ),
                                  ),
                                  child: Align(
                                    alignment: const AlignmentDirectional(0.0, 0.0),
                                    child: Icon(
                                      Icons.store_outlined,
                                      color: FlutterFlowTheme.of(context).secondaryText,
                                      size: 24.0,
                                    ),
                                  ),
                                ),
                                // Content
                                Expanded(
                                  child: Padding(
                                    padding: const EdgeInsetsDirectional.fromSTEB(12.0, 0.0, 0.0, 0.0),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.max,
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          vendor['name'] as String? ?? 'Unnamed',
                                          style: FlutterFlowTheme.of(context).bodyMedium.override(
                                                fontFamily: 'Inter',
                                                fontSize: 16.0,
                                                letterSpacing: 0.0,
                                                fontWeight: FontWeight.w600,
                                              ),
                                        ),
                                        if (vendor['contact_email'] != null || vendor['contact_phone'] != null) ...[
                                          const SizedBox(height: 4.0),
                                          Text(
                                            [
                                              if (vendor['contact_email'] != null) vendor['contact_email'],
                                              if (vendor['contact_phone'] != null) vendor['contact_phone'],
                                            ].join(' • '),
                                            style: FlutterFlowTheme.of(context).labelMedium.override(
                                                  fontFamily: 'Inter',
                                                  fontSize: 16.0,
                                                  letterSpacing: 0.0,
                                                ),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                ),
                                // Chevron
                                Icon(
                                  Icons.chevron_right,
                                  color: FlutterFlowTheme.of(context).secondaryText,
                                  size: 24.0,
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),

        // Floating action button
        Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(16.0, 0.0, 16.0, 16.0),
          child: FFButtonWidget(
            onPressed: _newVendor,
            text: 'Ny leverandør',
            icon: const Icon(Icons.add, size: 20.0),
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
              color: FlutterFlowTheme.of(context).primary,
              textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                    fontFamily: 'Inter',
                    color: Colors.white,
                    letterSpacing: 0.0,
                  ),
              elevation: 0.0,
              borderRadius: BorderRadius.circular(12.0),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildVendorForm() {
    final vendor = _editingVendor ?? {};
    final nameController = TextEditingController(
        text: vendor['name'] as String? ?? '');
    final descriptionController = TextEditingController(
        text: vendor['description'] as String? ?? '');
    final contactEmailController = TextEditingController(
        text: vendor['contact_email'] as String? ?? '');
    final contactPhoneController = TextEditingController(
        text: vendor['contact_phone'] as String? ?? '');
    bool formActive = vendor['active'] as bool? ?? true;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                vendor['id'] != null ? 'Rediger leverandør' : 'Ny leverandør',
                style: FlutterFlowTheme.of(context).headlineSmall,
              ),
              IconButton(
                icon: const Icon(Icons.close),
                onPressed: () {
                  setState(() {
                    _showVendorForm = false;
                    _editingVendor = null;
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 16),
          TextField(
            controller: nameController,
            decoration: const InputDecoration(
              labelText: 'Navn *',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: descriptionController,
            decoration: const InputDecoration(
              labelText: 'Beskrivelse',
              border: OutlineInputBorder(),
            ),
            maxLines: 3,
          ),
          const SizedBox(height: 16),
          TextField(
            controller: contactEmailController,
            decoration: const InputDecoration(
              labelText: 'E-post',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.emailAddress,
          ),
          const SizedBox(height: 16),
          TextField(
            controller: contactPhoneController,
            decoration: const InputDecoration(
              labelText: 'Telefon',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.phone,
          ),
          const SizedBox(height: 16),
          StatefulBuilder(
            builder: (context, setStateLocal) {
              return SwitchListTile(
                title: const Text('Aktiv'),
                value: formActive,
                activeColor: FlutterFlowTheme.of(context).primary,
                onChanged: (value) {
                  setStateLocal(() {
                    formActive = value;
                  });
                },
              );
            },
          ),
          const SizedBox(height: 24),
          FFButtonWidget(
            onPressed: _isLoading
                ? null
                : () {
                    final vendorData = {
                      if (vendor['id'] != null) 'id': vendor['id'],
                      'name': nameController.text,
                      'description': descriptionController.text.isEmpty
                          ? null
                          : descriptionController.text,
                      'contact_email': contactEmailController.text.isEmpty
                          ? null
                          : contactEmailController.text,
                      'contact_phone': contactPhoneController.text.isEmpty
                          ? null
                          : contactPhoneController.text,
                      'active': formActive,
                    };
                    _saveVendor(vendorData);
                  },
            text: vendor['id'] != null ? 'Oppdater' : 'Opprett',
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
              color: FlutterFlowTheme.of(context).primary,
              textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                    fontFamily: 'Inter',
                    color: Colors.white,
                    letterSpacing: 0.0,
                  ),
              elevation: 0.0,
              borderRadius: BorderRadius.circular(12.0),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryForm() {
    final category = _editingCategory ?? {};
    final nameController = TextEditingController(
        text: category['name'] as String? ?? '');
    final descriptionController = TextEditingController(
        text: category['description'] as String? ?? '');
    bool formActive = category['active'] as bool? ?? true;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                category['id'] != null ? 'Rediger kategori' : 'Ny kategori',
                style: FlutterFlowTheme.of(context).headlineSmall,
              ),
              IconButton(
                icon: const Icon(Icons.close),
                onPressed: () {
                  setState(() {
                    _showCategoryForm = false;
                    _editingCategory = null;
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 16),
          TextField(
            controller: nameController,
            decoration: const InputDecoration(
              labelText: 'Navn *',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: descriptionController,
            decoration: const InputDecoration(
              labelText: 'Beskrivelse',
              border: OutlineInputBorder(),
            ),
            maxLines: 3,
          ),
          const SizedBox(height: 16),
          StatefulBuilder(
            builder: (context, setStateLocal) {
              return SwitchListTile(
                title: const Text('Aktiv'),
                value: formActive,
                activeColor: FlutterFlowTheme.of(context).primary,
                onChanged: (value) {
                  setStateLocal(() {
                    formActive = value;
                  });
                },
              );
            },
          ),
          const SizedBox(height: 24),
          FFButtonWidget(
            onPressed: _isLoading
                ? null
                : () {
                    final categoryData = {
                      if (category['id'] != null) 'id': category['id'],
                      'name': nameController.text,
                      'description': descriptionController.text.isEmpty
                          ? null
                          : descriptionController.text,
                      'active': formActive,
                    };
                    _saveCategory(categoryData);
                  },
            text: category['id'] != null ? 'Oppdater' : 'Opprett',
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
              color: FlutterFlowTheme.of(context).primary,
              textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                    fontFamily: 'Inter',
                    color: Colors.white,
                    letterSpacing: 0.0,
                  ),
              elevation: 0.0,
              borderRadius: BorderRadius.circular(12.0),
            ),
          ),
        ],
      ),
    );
  }
}

