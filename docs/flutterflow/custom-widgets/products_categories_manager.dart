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
  int _selectedTab = 0; // 0 = Products, 1 = Categories
  List<Map<String, dynamic>> _products = [];
  List<Map<String, dynamic>> _categories = [];
  bool _isLoading = false;
  String? _errorMessage;
  String _searchQuery = '';
  int? _selectedCategoryId;
  Map<String, dynamic>? _editingProduct;
  Map<String, dynamic>? _editingCategory;
  bool _showProductForm = false;
  bool _showCategoryForm = false;
  List<int> _selectedCollectionIds = [];

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
        final errorData = jsonDecode(response.body) as Map<String, dynamic>?;
        throw Exception(errorData?['error'] ??
            'Failed to save product: ${response.statusCode}');
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error saving product: ${e.toString()}';
        _isLoading = false;
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
        final errorData = jsonDecode(response.body) as Map<String, dynamic>?;
        throw Exception(errorData?['error'] ??
            'Failed to save category: ${response.statusCode}');
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error saving category: ${e.toString()}';
        _isLoading = false;
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
        'price': null,
        'currency': 'nok',
      };
      _selectedCollectionIds = [];
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
                : _buildCategoriesTab(),
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
          // Category selection
          Container(
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
                      ..._categories
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
                      }),
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
          const SizedBox(height: 16),
          StatefulBuilder(
            builder: (context, setStateLocal) {
              return Column(
                children: [
                  SwitchListTile(
                    title: const Text('Aktiv'),
                    value: formActive,
                    onChanged: (value) {
                      setStateLocal(() {
                        formActive = value;
                      });
                    },
                  ),
                  SwitchListTile(
                    title: const Text('Kan sendes'),
                    value: formShippable,
                    onChanged: (value) {
                      setStateLocal(() {
                        formShippable = value;
                      });
                    },
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 24),
          ElevatedButton(
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
                      if (priceController.text.isNotEmpty)
                        'price': double.tryParse(priceController.text),
                      'currency': currencyController.text.toLowerCase(),
                      'collection_ids': _selectedCollectionIds,
                    };
                    _saveProduct(productData);
                  },
            child: _isLoading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(product['id'] != null ? 'Oppdater' : 'Opprett'),
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
                onChanged: (value) {
                  setStateLocal(() {
                    formActive = value;
                  });
                },
              );
            },
          ),
          const SizedBox(height: 24),
          ElevatedButton(
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
            child: _isLoading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(category['id'] != null ? 'Oppdater' : 'Opprett'),
          ),
        ],
      ),
    );
  }
}

