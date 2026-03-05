// Automatic FlutterFlow imports
import '/backend/schema/structs/index.dart';
import '/backend/schema/enums/enums.dart';
import '/actions/actions.dart' as action_blocks;
import '/flutter_flow/flutter_flow_theme.dart';
import '/flutter_flow/flutter_flow_util.dart';
import '/custom_code/widgets/index.dart'; // Imports other custom widgets
import '/custom_code/actions/index.dart'; // Imports custom actions
import '/flutter_flow/custom_functions.dart'; // Imports custom functions
import 'package:flutter/material.dart';
// Begin custom widget code
// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'index.dart'; // Imports other custom widgets

import 'index.dart'; // Imports other custom widgets

import 'index.dart'; // Imports other custom widgets
import '/flutter_flow/flutter_flow_widgets.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
// End custom code

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
  List<Map<String, dynamic>> _quantityUnits = [];
  List<Map<String, dynamic>> _visibleArticleGroupCodes = [];
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
  int? _selectedQuantityUnitId;

  // Product form controllers
  TextEditingController? _productNameController;
  TextEditingController? _productDescriptionController;
  TextEditingController? _productPriceController;
  TextEditingController? _productCurrencyController;
  TextEditingController? _productTypeController;

  // Product form state variables
  bool _formActive = true;
  bool _formShippable = false;
  bool _formNoPriceInPos = false;

  // Category form controllers and state
  TextEditingController? _categoryNameController;
  TextEditingController? _categoryDescriptionController;
  bool _formCategoryActive = true;

  // Vendor form controllers and state
  TextEditingController? _vendorNameController;
  TextEditingController? _vendorDescriptionController;
  TextEditingController? _vendorContactEmailController;
  TextEditingController? _vendorContactPhoneController;
  TextEditingController? _vendorCommissionPercentController;
  bool _formVendorActive = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _productNameController?.dispose();
    _productDescriptionController?.dispose();
    _productPriceController?.dispose();
    _productCurrencyController?.dispose();
    _productTypeController?.dispose();
    _categoryNameController?.dispose();
    _categoryDescriptionController?.dispose();
    _vendorNameController?.dispose();
    _vendorDescriptionController?.dispose();
    _vendorContactEmailController?.dispose();
    _vendorContactPhoneController?.dispose();
    _vendorCommissionPercentController?.dispose();
    super.dispose();
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
        _loadQuantityUnits(),
        _loadVisibleArticleGroupCodes(),
      ]);
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Error loading data: ${e.toString()}';
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _loadVisibleArticleGroupCodes() async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/stores/${widget.storeSlug}');
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
        final store = data['store'] as Map<String, dynamic>?;
        final codes = (store?['visible_article_group_codes'] as List<dynamic>?)
                ?.map((e) => Map<String, dynamic>.from(e as Map))
                .toList() ??
            [];
        if (mounted) {
          setState(() {
            _visibleArticleGroupCodes = codes;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _visibleArticleGroupCodes = [];
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _visibleArticleGroupCodes = [];
        });
      }
    }
  }

  Future<void> _loadProducts() async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/products').replace(
        queryParameters: {
          'per_page': '100',
          'include_inactive':
              '1', // Admin manager: show all products including inactive
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
            .where((p) => (p['collections'] as List<dynamic>?)?.isEmpty ?? true)
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
    if (responseBody.isNotEmpty) {
      try {
        final errorData = jsonDecode(responseBody) as Map<String, dynamic>?;
        if (errorData != null) {
          if (errorData.containsKey('errors')) {
            final errors = errorData['errors'] as Map<String, dynamic>?;
            if (errors != null && errors.isNotEmpty) {
              final errorMessages = <String>[];
              errors.forEach((field, messages) {
                if (messages is List) {
                  for (var message in messages) {
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
        if (responseBody.length < 200) {
          return responseBody;
        }
      }
    }

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
          ? Uri.parse('${widget.apiBaseUrl}/api/products/${productData['id']}')
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
        _productNameController?.dispose();
        _productDescriptionController?.dispose();
        _productPriceController?.dispose();
        _productCurrencyController?.dispose();
        _productTypeController?.dispose();

        setState(() {
          _showProductForm = false;
          _editingProduct = null;
          _selectedCollectionIds = [];
          _selectedVendorId = null;
          _selectedArticleGroupCode = null;
          _selectedQuantityUnitId = null;
          _productNameController = null;
          _productDescriptionController = null;
          _productPriceController = null;
          _productCurrencyController = null;
          _productTypeController = null;
        });
        await _loadData();
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content:
                  Text(isUpdate ? 'Produkt oppdatert' : 'Produkt opprettet'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        final errorMessage =
            _parseErrorMessage(response.body, response.statusCode);
        setState(() {
          _errorMessage = errorMessage;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Feil ved lagring av produkt: ${e.toString()}';
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

      final body = Map<String, dynamic>.from(categoryData);
      if (isUpdate) body.remove('id');

      final response = isUpdate
          ? await http.put(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(body),
            )
          : await http.post(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(body),
            );

      if (response.statusCode == 200 || response.statusCode == 201) {
        _categoryNameController?.dispose();
        _categoryDescriptionController?.dispose();
        setState(() {
          _showCategoryForm = false;
          _editingCategory = null;
          _categoryNameController = null;
          _categoryDescriptionController = null;
        });
        await _loadData();
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content:
                  Text(isUpdate ? 'Kategori oppdatert' : 'Kategori opprettet'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        final errorMessage =
            _parseErrorMessage(response.body, response.statusCode);
        setState(() {
          _errorMessage = errorMessage;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Feil ved lagring av kategori: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  void _editProduct(Map<String, dynamic> product) {
    _productNameController?.dispose();
    _productDescriptionController?.dispose();
    _productPriceController?.dispose();
    _productCurrencyController?.dispose();
    _productTypeController?.dispose();

    setState(() {
      _editingProduct = Map<String, dynamic>.from(product);
      _showProductForm = true;

      _productNameController =
          TextEditingController(text: product['name'] as String? ?? '');
      _productDescriptionController =
          TextEditingController(text: product['description'] as String? ?? '');

      final productPrice = product['product_price'] as Map<String, dynamic>?;
      final priceAmount = productPrice?['amount'] as int?;
      _productPriceController = TextEditingController(
          text: priceAmount != null
              ? (priceAmount / 100).toString()
              : (product['price'] != null
                  ? (product['price'] as num).toString()
                  : ''));

      _productCurrencyController = TextEditingController(
          text: productPrice?['currency']?.toString().toLowerCase() ??
              product['currency'] as String? ??
              'nok');

      _productTypeController =
          TextEditingController(text: product['type'] as String? ?? 'service');

      _formActive = product['active'] as bool? ?? true;
      _formShippable = product['shippable'] as bool? ?? false;
      _formNoPriceInPos = product['no_price_in_pos'] as bool? ?? false;

      final currentCollections = product['collections'] as List<dynamic>? ?? [];
      _selectedCollectionIds = currentCollections
          .map((c) => (c as Map<String, dynamic>)['id'] as int?)
          .where((id) => id != null && id != 0)
          .cast<int>()
          .toList();
      final vendor = product['vendor'] as Map<String, dynamic>?;
      _selectedVendorId =
          _parseInt(vendor?['id']) ?? _parseInt(product['vendor_id']);
      final rawArticleGroup = product['article_group_code'];
      _selectedArticleGroupCode = rawArticleGroup is String
          ? rawArticleGroup
          : (rawArticleGroup?.toString());
      final quantityUnit = product['quantity_unit'] as Map<String, dynamic>?;
      _selectedQuantityUnitId = _parseInt(quantityUnit?['id']) ??
          _parseInt(product['quantity_unit_id']);
    });
  }

  void _editCategory(Map<String, dynamic> category) {
    _categoryNameController?.dispose();
    _categoryDescriptionController?.dispose();
    setState(() {
      _editingCategory = Map<String, dynamic>.from(category);
      _categoryNameController =
          TextEditingController(text: category['name'] as String? ?? '');
      _categoryDescriptionController =
          TextEditingController(text: category['description'] as String? ?? '');
      _formCategoryActive = category['active'] as bool? ?? true;
      _showCategoryForm = true;
    });
  }

  void _newProduct() {
    _productNameController?.dispose();
    _productDescriptionController?.dispose();
    _productPriceController?.dispose();
    _productCurrencyController?.dispose();
    _productTypeController?.dispose();

    int? defaultQuantityUnitId;
    try {
      final pieceUnit = _quantityUnits.firstWhere(
        (unit) =>
            (unit['name'] as String? ?? '').toLowerCase() == 'piece' ||
            (unit['symbol'] as String? ?? '').toLowerCase() == 'stk',
        orElse: () => <String, dynamic>{},
      );
      if (pieceUnit.isNotEmpty) {
        defaultQuantityUnitId = pieceUnit['id'] as int?;
      }
    } catch (e) {
      if (_quantityUnits.isNotEmpty) {
        defaultQuantityUnitId = _quantityUnits.first['id'] as int?;
      }
    }

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

      _productNameController = TextEditingController(text: '');
      _productDescriptionController = TextEditingController(text: '');
      _productPriceController = TextEditingController(text: '');
      _productCurrencyController = TextEditingController(text: 'nok');
      _productTypeController = TextEditingController(text: 'service');

      _formActive = true;
      _formShippable = false;
      _formNoPriceInPos = false;

      _selectedCollectionIds = [];
      _selectedVendorId = null;
      _selectedArticleGroupCode = _visibleArticleGroupCodes.isNotEmpty
          ? (_visibleArticleGroupCodes.first['code'] as String?)
          : '04999';
      _selectedQuantityUnitId = defaultQuantityUnitId;
      _showProductForm = true;
    });
  }

  void _newCategory() {
    _categoryNameController?.dispose();
    _categoryDescriptionController?.dispose();
    setState(() {
      _editingCategory = {
        'name': '',
        'description': '',
        'active': true,
        'sort_order': 0,
      };
      _categoryNameController = TextEditingController(text: '');
      _categoryDescriptionController = TextEditingController(text: '');
      _formCategoryActive = true;
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
        final responseBody = jsonDecode(response.body);
        List<dynamic>? vendorsList;

        if (responseBody is Map<String, dynamic>) {
          final data = responseBody;
          if (data.containsKey('vendors')) {
            vendorsList = data['vendors'] as List<dynamic>?;
          } else if (data.containsKey('data')) {
            final dataValue = data['data'];
            if (dataValue is List) {
              vendorsList = dataValue;
            } else if (dataValue is Map) {
              final dataMap = dataValue as Map;
              if (dataMap.containsKey('data') && dataMap['data'] is List) {
                vendorsList = dataMap['data'] as List<dynamic>?;
              }
            }
          } else if (data.containsKey('vendor')) {
            vendorsList = data['vendor'] as List<dynamic>?;
          }
        } else if (responseBody is List) {
          vendorsList = responseBody;
        }

        final vendors =
            vendorsList?.map((v) => v as Map<String, dynamic>).toList() ?? [];

        if (mounted) {
          setState(() {
            _vendors = vendors;
          });
        }
      } else if (response.statusCode == 404) {
        if (mounted) {
          setState(() {
            _vendors = [];
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _vendors = [];
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _vendors = [];
        });
      }
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

      final body = Map<String, dynamic>.from(vendorData);
      if (isUpdate) body.remove('id');

      final response = isUpdate
          ? await http.put(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(body),
            )
          : await http.post(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(body),
            );

      if (response.statusCode == 200 || response.statusCode == 201) {
        _vendorNameController?.dispose();
        _vendorDescriptionController?.dispose();
        _vendorContactEmailController?.dispose();
        _vendorContactPhoneController?.dispose();
        _vendorCommissionPercentController?.dispose();
        setState(() {
          _showVendorForm = false;
          _editingVendor = null;
          _vendorNameController = null;
          _vendorDescriptionController = null;
          _vendorContactEmailController = null;
          _vendorContactPhoneController = null;
          _vendorCommissionPercentController = null;
        });
        await _loadVendors();
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                  isUpdate ? 'Leverandør oppdatert' : 'Leverandør opprettet'),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        final errorMessage =
            _parseErrorMessage(response.body, response.statusCode);
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
    _vendorNameController?.dispose();
    _vendorDescriptionController?.dispose();
    _vendorContactEmailController?.dispose();
    _vendorContactPhoneController?.dispose();
    _vendorCommissionPercentController?.dispose();
    setState(() {
      _editingVendor = Map<String, dynamic>.from(vendor);
      _vendorNameController =
          TextEditingController(text: vendor['name'] as String? ?? '');
      _vendorDescriptionController =
          TextEditingController(text: vendor['description'] as String? ?? '');
      _vendorContactEmailController =
          TextEditingController(text: vendor['contact_email'] as String? ?? '');
      _vendorContactPhoneController =
          TextEditingController(text: vendor['contact_phone'] as String? ?? '');
      _vendorCommissionPercentController = TextEditingController(
          text: _parseCommissionPercent(vendor['commission_percent'])
                  ?.toString() ??
              '');
      _formVendorActive = vendor['active'] as bool? ?? true;
      _showVendorForm = true;
    });
  }

  void _newVendor() {
    _vendorNameController?.dispose();
    _vendorDescriptionController?.dispose();
    _vendorContactEmailController?.dispose();
    _vendorContactPhoneController?.dispose();
    _vendorCommissionPercentController?.dispose();
    setState(() {
      _editingVendor = {
        'name': '',
        'description': '',
        'contact_email': '',
        'contact_phone': '',
        'active': true,
        'commission_percent': null,
      };
      _vendorNameController = TextEditingController(text: '');
      _vendorDescriptionController = TextEditingController(text: '');
      _vendorContactEmailController = TextEditingController(text: '');
      _vendorContactPhoneController = TextEditingController(text: '');
      _vendorCommissionPercentController = TextEditingController(text: '');
      _formVendorActive = true;
      _showVendorForm = true;
    });
  }

  Future<void> _loadQuantityUnits() async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/quantity-units').replace(
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
        final responseBody = jsonDecode(response.body);
        List<dynamic>? unitsList;

        if (responseBody is Map<String, dynamic>) {
          final data = responseBody;
          if (data.containsKey('quantity_units')) {
            unitsList = data['quantity_units'] as List<dynamic>?;
          } else if (data.containsKey('data')) {
            final dataValue = data['data'];
            if (dataValue is List) {
              unitsList = dataValue;
            } else if (dataValue is Map) {
              final dataMap = dataValue as Map;
              if (dataMap.containsKey('data') && dataMap['data'] is List) {
                unitsList = dataMap['data'] as List<dynamic>?;
              }
            }
          }
        } else if (responseBody is List) {
          unitsList = responseBody;
        }

        final unitsMapByNameSymbol = <String, Map<String, dynamic>>{};

        if (unitsList != null) {
          for (var unit in unitsList) {
            final unitMap = unit as Map<String, dynamic>;
            final id = unitMap['id'] as int?;
            final name = (unitMap['name'] as String? ?? '').toLowerCase();
            final symbol = (unitMap['symbol'] as String? ?? '').toLowerCase();
            final key = '$name|$symbol';

            if (id != null && !unitsMapByNameSymbol.containsKey(key)) {
              unitsMapByNameSymbol[key] = unitMap;
            } else if (id != null) {
              final existing = unitsMapByNameSymbol[key];
              final existingHasAccount =
                  existing?['stripe_account_id'] != null;
              final currentHasAccount = unitMap['stripe_account_id'] != null;
              if (currentHasAccount && !existingHasAccount) {
                unitsMapByNameSymbol[key] = unitMap;
              }
            }
          }
        }

        final units = unitsMapByNameSymbol.values.toList();

        if (mounted) {
          setState(() {
            _quantityUnits = units;
          });
        }
      } else if (response.statusCode == 404) {
        if (mounted) {
          setState(() {
            _quantityUnits = [];
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _quantityUnits = [];
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _quantityUnits = [];
        });
      }
    }
  }

  String _formatPrice(int? amount) {
    if (amount == null) return 'Ingen pris';
    return '${(amount / 100).toStringAsFixed(2).replaceAll('.', ',')} kr';
  }

  num? _parseCommissionPercent(dynamic value) {
    if (value == null) return null;
    if (value is num) return value;
    if (value is String) return num.tryParse(value);
    return null;
  }

  int? _parseInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
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
                _buildTab(0, 'Produkter'),
                _buildTab(1, 'Kategorier'),
                _buildTab(2, 'Leverandører'),
              ],
            ),
          ),
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

  Widget _buildTab(int index, String label) {
    return Expanded(
      child: InkWell(
        onTap: () {
          setState(() {
            _selectedTab = index;
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
                color: _selectedTab == index
                    ? FlutterFlowTheme.of(context).primary
                    : Colors.transparent,
                width: 2,
              ),
            ),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: FlutterFlowTheme.of(context).titleMedium.override(
                  fontFamily: 'Readex Pro',
                  color: _selectedTab == index
                      ? FlutterFlowTheme.of(context).primary
                      : FlutterFlowTheme.of(context).secondaryText,
                ),
          ),
        ),
      ),
    );
  }

  Widget _buildProductsTab() {
    if (_showProductForm) {
      return _buildProductForm();
    }

    return Column(
      children: [
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
                      final productPrice =
                          product['product_price'] as Map<String, dynamic>?;
                      final priceAmount = productPrice?['amount'] as int?;
                      final images = product['images'] as List<dynamic>?;
                      final imageUrl = images?.isNotEmpty == true
                          ? images![0] as String?
                          : null;

                      return InkWell(
                        onTap: () => _editProduct(product),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          curve: Curves.easeInOut,
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: FlutterFlowTheme.of(context)
                                .secondaryBackground,
                            borderRadius: BorderRadius.circular(16.0),
                          ),
                          child: Padding(
                            padding: const EdgeInsetsDirectional.fromSTEB(
                                12.0, 8.0, 12.0, 8.0),
                            child: Row(
                              children: [
                                Container(
                                  width: 32.0,
                                  height: 32.0,
                                  decoration: BoxDecoration(
                                    color: FlutterFlowTheme.of(context)
                                        .primaryBackground,
                                    borderRadius: BorderRadius.circular(8.0),
                                    border: Border.all(
                                      color: FlutterFlowTheme.of(context)
                                          .alternate,
                                    ),
                                  ),
                                  child: imageUrl != null
                                      ? ClipRRect(
                                          borderRadius:
                                              BorderRadius.circular(8.0),
                                          child: Image.network(
                                            imageUrl,
                                            width: 32.0,
                                            height: 32.0,
                                            fit: BoxFit.cover,
                                            errorBuilder:
                                                (context, error, stackTrace) {
                                              return Icon(
                                                Icons.inventory_2_outlined,
                                                color: FlutterFlowTheme.of(
                                                        context)
                                                    .secondaryText,
                                                size: 24.0,
                                              );
                                            },
                                          ),
                                        )
                                      : Icon(
                                          Icons.inventory_2_outlined,
                                          color: FlutterFlowTheme.of(context)
                                              .secondaryText,
                                          size: 24.0,
                                        ),
                                ),
                                Expanded(
                                  child: Padding(
                                    padding:
                                        const EdgeInsetsDirectional.fromSTEB(
                                            12.0, 0.0, 0.0, 0.0),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          product['name'] as String? ??
                                              'Unnamed',
                                          style: FlutterFlowTheme.of(context)
                                              .bodyMedium
                                              .override(
                                                fontFamily: 'Inter',
                                                fontSize: 16.0,
                                                fontWeight: FontWeight.w600,
                                              ),
                                        ),
                                        if (product['description'] != null) ...[
                                          const SizedBox(height: 4.0),
                                          Text(
                                            (product['description'] as String)
                                                        .length >
                                                    50
                                                ? '${(product['description'] as String).substring(0, 50)}...'
                                                : (product['description']
                                                    as String),
                                            style: FlutterFlowTheme.of(context)
                                                .labelMedium,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ],
                                        if ((product['collections']
                                                    as List<dynamic>?)
                                                ?.isNotEmpty ==
                                            true) ...[
                                          const SizedBox(height: 4.0),
                                          Wrap(
                                            spacing: 6.0,
                                            runSpacing: 3.0,
                                            children: (product['collections']
                                                    as List<dynamic>)
                                                .map((c) {
                                              final collectionName = (c as Map<
                                                      String,
                                                      dynamic>)['name']
                                                  as String?;
                                              if (collectionName == null) {
                                                return const SizedBox.shrink();
                                              }
                                              return Container(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                        horizontal: 8.0,
                                                        vertical: 4.0),
                                                decoration: BoxDecoration(
                                                  color: FlutterFlowTheme.of(
                                                          context)
                                                      .primaryBackground,
                                                  borderRadius:
                                                      BorderRadius.circular(8),
                                                  border: Border.all(
                                                    color: FlutterFlowTheme.of(
                                                            context)
                                                        .alternate,
                                                  ),
                                                ),
                                                child: Text(
                                                  collectionName,
                                                  style: FlutterFlowTheme.of(
                                                          context)
                                                      .labelSmall,
                                                ),
                                              );
                                            }).toList(),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                ),
                                Expanded(
                                  child: Align(
                                    alignment:
                                        const AlignmentDirectional(1.0, 0.0),
                                    child: Padding(
                                      padding:
                                          const EdgeInsetsDirectional.fromSTEB(
                                              0.0, 0.0, 20.0, 0.0),
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.end,
                                        children: [
                                          Text(
                                            _formatPrice(priceAmount),
                                            style: FlutterFlowTheme.of(context)
                                                .bodyMedium
                                                .override(
                                                  fontFamily: 'Inter',
                                                  fontSize: 16.0,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                          ),
                                          const SizedBox(height: 4.0),
                                          Icon(
                                            Icons.chevron_right,
                                            color: FlutterFlowTheme.of(context)
                                                .secondaryText,
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
        if (!_isLoading)
          Padding(
            padding:
                const EdgeInsetsDirectional.fromSTEB(16.0, 0.0, 16.0, 16.0),
            child: FFButtonWidget(
              onPressed: _newProduct,
              text: 'Nytt produkt',
              icon: const Icon(Icons.add, size: 20.0),
              options: FFButtonOptions(
                width: double.infinity,
                height: 48.0,
                padding:
                    const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
                iconPadding:
                    const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
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
                        onTap: isUncategorized
                            ? null
                            : () => _editCategory(category),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          curve: Curves.easeInOut,
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: FlutterFlowTheme.of(context)
                                .secondaryBackground,
                            borderRadius: BorderRadius.circular(16.0),
                          ),
                          child: Padding(
                            padding: const EdgeInsetsDirectional.fromSTEB(
                                12.0, 8.0, 12.0, 8.0),
                            child: Row(
                              children: [
                                Container(
                                  width: 32.0,
                                  height: 32.0,
                                  decoration: BoxDecoration(
                                    color: FlutterFlowTheme.of(context)
                                        .primaryBackground,
                                    borderRadius: BorderRadius.circular(8.0),
                                    border: Border.all(
                                      color: FlutterFlowTheme.of(context)
                                          .alternate,
                                    ),
                                  ),
                                  child: Icon(
                                    Icons.category_outlined,
                                    color: FlutterFlowTheme.of(context)
                                        .secondaryText,
                                    size: 24.0,
                                  ),
                                ),
                                Expanded(
                                  child: Padding(
                                    padding:
                                        const EdgeInsetsDirectional.fromSTEB(
                                            12.0, 0.0, 0.0, 0.0),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          category['name'] as String? ??
                                              'Unnamed',
                                          style: FlutterFlowTheme.of(context)
                                              .bodyMedium
                                              .override(
                                                fontFamily: 'Inter',
                                                fontSize: 16.0,
                                                fontWeight: FontWeight.w600,
                                              ),
                                        ),
                                        const SizedBox(height: 4.0),
                                        Text(
                                          '${category['products_count'] ?? 0} produkter',
                                          style: FlutterFlowTheme.of(context)
                                              .labelMedium,
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                                if (!isUncategorized)
                                  Icon(
                                    Icons.chevron_right,
                                    color: FlutterFlowTheme.of(context)
                                        .secondaryText,
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
              iconPadding:
                  const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
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
    final nameController = _productNameController ?? TextEditingController();
    final descriptionController =
        _productDescriptionController ?? TextEditingController();
    final priceController = _productPriceController ?? TextEditingController();
    final currencyController =
        _productCurrencyController ?? TextEditingController(text: 'nok');
    final typeController =
        _productTypeController ?? TextEditingController(text: 'service');

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
                  _productNameController?.dispose();
                  _productDescriptionController?.dispose();
                  _productPriceController?.dispose();
                  _productCurrencyController?.dispose();
                  _productTypeController?.dispose();
                  setState(() {
                    _showProductForm = false;
                    _editingProduct = null;
                    _selectedCollectionIds = [];
                    _selectedVendorId = null;
                    _selectedArticleGroupCode = null;
                    _selectedQuantityUnitId = null;
                    _productNameController = null;
                    _productDescriptionController = null;
                    _productPriceController = null;
                    _productCurrencyController = null;
                    _productTypeController = null;
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
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
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: DropdownButtonFormField<String>(
                              value: typeController.text,
                              decoration: const InputDecoration(
                                labelText: 'Type',
                                border: OutlineInputBorder(),
                              ),
                              items: const [
                                DropdownMenuItem(
                                    value: 'service', child: Text('Tjeneste')),
                                DropdownMenuItem(
                                    value: 'good', child: Text('Vare')),
                              ],
                              onChanged: (value) {
                                typeController.text = value ?? 'service';
                              },
                            ),
                          ),
                        ),
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(left: 8),
                            child: DropdownButtonFormField<int?>(
                              value: _selectedQuantityUnitId,
                              decoration: const InputDecoration(
                                labelText: 'Enhet',
                                helperText: 'Velg enhet for produktet',
                                border: OutlineInputBorder(),
                              ),
                              items: [
                                const DropdownMenuItem<int?>(
                                  value: null,
                                  child: Text('Ingen enhet'),
                                ),
                                ..._quantityUnits.map((unit) {
                                  return DropdownMenuItem<int?>(
                                    value: _parseInt(unit['id']),
                                    child: Text(unit['display_name']
                                            as String? ??
                                        '${unit['name']}${unit['symbol'] != null ? ' (${unit['symbol']})' : ''}'),
                                  );
                                }),
                              ],
                              onChanged: (value) {
                                setState(() {
                                  _selectedQuantityUnitId = value;
                                });
                              },
                            ),
                          ),
                        ),
                      ],
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
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: DropdownButtonFormField<int?>(
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
                                    value: _parseInt(vendor['id']),
                                    child: Text(
                                        vendor['name'] as String? ?? 'Unnamed'),
                                  );
                                }),
                              ],
                              onChanged: (value) {
                                setState(() {
                                  _selectedVendorId = value;
                                });
                              },
                            ),
                          ),
                        ),
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(left: 8),
                            child: DropdownButtonFormField<String?>(
                              value: _selectedArticleGroupCode,
                              decoration: const InputDecoration(
                                labelText: 'Varegruppekode (SAF-T)',
                                border: OutlineInputBorder(),
                                helperText:
                                    'PredefinedBasicID-04: Produktkategori for SAF-T rapportering',
                              ),
                              items: [
                                const DropdownMenuItem<String?>(
                                  value: null,
                                  child: Text('Ingen varegruppekode'),
                                ),
                                ..._visibleArticleGroupCodes.map((item) {
                                  final code = item['code'] as String?;
                                  final name = item['name'] as String? ?? code ?? '';
                                  return DropdownMenuItem<String?>(
                                    value: code,
                                    child: Text(code != null ? '$code - $name' : name),
                                  );
                                }),
                                if (_selectedArticleGroupCode != null &&
                                    !_visibleArticleGroupCodes.any(
                                        (e) => e['code'] == _selectedArticleGroupCode))
                                  DropdownMenuItem<String?>(
                                    value: _selectedArticleGroupCode,
                                    child: Text(
                                      '${_selectedArticleGroupCode!} (ikke synlig i POS)',
                                    ),
                                  ),
                              ],
                              onChanged: (value) {
                                setState(() {
                                  _selectedArticleGroupCode = value;
                                });
                              },
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    SwitchListTile(
                      title: const Text('Aktiv'),
                      value: _formActive,
                      activeColor: FlutterFlowTheme.of(context).primary,
                      onChanged: (value) {
                        setState(() {
                          _formActive = value;
                        });
                      },
                    ),
                    SwitchListTile(
                      title: const Text('Kan sendes'),
                      value: _formShippable,
                      activeColor: FlutterFlowTheme.of(context).primary,
                      onChanged: (value) {
                        setState(() {
                          _formShippable = value;
                        });
                      },
                    ),
                    SwitchListTile(
                      title: const Text('Ingen pris i kassa'),
                      subtitle: const Text(
                          'Produktet vil ikke ha pris i kassasystemet'),
                      value: _formNoPriceInPos,
                      activeColor: FlutterFlowTheme.of(context).primary,
                      onChanged: (value) {
                        setState(() {
                          _formNoPriceInPos = value;
                        });
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
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
                  child: Column(
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
                          constraints: const BoxConstraints(maxHeight: 400.0),
                          child: SingleChildScrollView(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: _categories
                                  .where((c) => (c['id'] as int?) != 0)
                                  .map((category) {
                                final categoryId = category['id'] as int?;
                                if (categoryId == null) {
                                  return const SizedBox.shrink();
                                }
                                final isSelected =
                                    _selectedCollectionIds.contains(categoryId);
                                return CheckboxListTile(
                                  title: Text(
                                      category['name'] as String? ?? 'Unnamed'),
                                  subtitle: Text(
                                    '${category['products_count'] ?? 0} produkter',
                                    style:
                                        FlutterFlowTheme.of(context).bodySmall,
                                  ),
                                  value: isSelected,
                                  activeColor:
                                      FlutterFlowTheme.of(context).primary,
                                  onChanged: (value) {
                                    setState(() {
                                      if (value == true) {
                                        if (!_selectedCollectionIds
                                            .contains(categoryId)) {
                                          _selectedCollectionIds
                                              .add(categoryId);
                                        }
                                      } else {
                                        _selectedCollectionIds
                                            .remove(categoryId);
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
                    String? unitLabel;
                    if (_selectedQuantityUnitId != null) {
                      final selectedUnit = _quantityUnits.firstWhere(
                        (u) => u['id'] == _selectedQuantityUnitId,
                        orElse: () => <String, dynamic>{},
                      );
                      unitLabel = selectedUnit['symbol'] as String?;
                    }
                    final productData = {
                      if (product['id'] != null) 'id': product['id'],
                      'name': nameController.text,
                      'description': descriptionController.text.isEmpty
                          ? null
                          : descriptionController.text,
                      'type': typeController.text,
                      'active': _formActive,
                      'shippable': _formShippable,
                      'no_price_in_pos': _formNoPriceInPos,
                      if (priceController.text.isNotEmpty)
                        'price': double.tryParse(priceController.text),
                      'currency': currencyController.text.toLowerCase(),
                      'collection_ids': _selectedCollectionIds,
                      if (_selectedVendorId != null)
                        'vendor_id': _selectedVendorId,
                      if (_selectedArticleGroupCode != null)
                        'article_group_code': _selectedArticleGroupCode,
                      if (_selectedQuantityUnitId != null)
                        'quantity_unit_id': _selectedQuantityUnitId,
                      if (unitLabel != null) 'unit_label': unitLabel,
                    };
                    _saveProduct(productData);
                  },
            text: product['id'] != null ? 'Oppdater' : 'Opprett',
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding:
                  const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
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
                        onTap: () => _editVendor(vendor),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          curve: Curves.easeInOut,
                          width: double.infinity,
                          decoration: BoxDecoration(
                            color: FlutterFlowTheme.of(context)
                                .secondaryBackground,
                            borderRadius: BorderRadius.circular(16.0),
                          ),
                          child: Padding(
                            padding: const EdgeInsetsDirectional.fromSTEB(
                                12.0, 8.0, 12.0, 8.0),
                            child: Row(
                              children: [
                                Container(
                                  width: 32.0,
                                  height: 32.0,
                                  decoration: BoxDecoration(
                                    color: FlutterFlowTheme.of(context)
                                        .primaryBackground,
                                    borderRadius: BorderRadius.circular(8.0),
                                    border: Border.all(
                                      color: FlutterFlowTheme.of(context)
                                          .alternate,
                                    ),
                                  ),
                                  child: Icon(
                                    Icons.store_outlined,
                                    color: FlutterFlowTheme.of(context)
                                        .secondaryText,
                                    size: 24.0,
                                  ),
                                ),
                                Expanded(
                                  child: Padding(
                                    padding:
                                        const EdgeInsetsDirectional.fromSTEB(
                                            12.0, 0.0, 0.0, 0.0),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          vendor['name'] as String? ??
                                              'Unnamed',
                                          style: FlutterFlowTheme.of(context)
                                              .bodyMedium
                                              .override(
                                                fontFamily: 'Inter',
                                                fontSize: 16.0,
                                                fontWeight: FontWeight.w600,
                                              ),
                                        ),
                                        if (vendor['contact_email'] != null ||
                                            vendor['contact_phone'] != null ||
                                            vendor['commission_percent'] !=
                                                null) ...[
                                          const SizedBox(height: 4.0),
                                          Text(
                                            [
                                              if (vendor['contact_email'] !=
                                                  null)
                                                vendor['contact_email'],
                                              if (vendor['contact_phone'] !=
                                                  null)
                                                vendor['contact_phone'],
                                              if (vendor['commission_percent'] !=
                                                  null)
                                                'Provision: ${_parseCommissionPercent(vendor['commission_percent'])?.toStringAsFixed(1) ?? '?'}%',
                                            ]
                                                .where((item) => item != null)
                                                .join(' • '),
                                            style: FlutterFlowTheme.of(context)
                                                .labelMedium,
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                ),
                                Icon(
                                  Icons.chevron_right,
                                  color: FlutterFlowTheme.of(context)
                                      .secondaryText,
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
              iconPadding:
                  const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
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
    final nameController = _vendorNameController!;
    final descriptionController = _vendorDescriptionController!;
    final contactEmailController = _vendorContactEmailController!;
    final contactPhoneController = _vendorContactPhoneController!;
    final commissionPercentController = _vendorCommissionPercentController!;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                vendor['id'] != null
                    ? 'Rediger leverandør'
                    : 'Ny leverandør',
                style: FlutterFlowTheme.of(context).headlineSmall,
              ),
              IconButton(
                icon: const Icon(Icons.close),
                onPressed: () {
                  _vendorNameController?.dispose();
                  _vendorDescriptionController?.dispose();
                  _vendorContactEmailController?.dispose();
                  _vendorContactPhoneController?.dispose();
                  _vendorCommissionPercentController?.dispose();
                  setState(() {
                    _showVendorForm = false;
                    _editingVendor = null;
                    _vendorNameController = null;
                    _vendorDescriptionController = null;
                    _vendorContactEmailController = null;
                    _vendorContactPhoneController = null;
                    _vendorCommissionPercentController = null;
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
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: TextField(
                    controller: contactEmailController,
                    decoration: const InputDecoration(
                      labelText: 'E-post',
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.emailAddress,
                  ),
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  child: TextField(
                    controller: contactPhoneController,
                    decoration: const InputDecoration(
                      labelText: 'Telefon',
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.phone,
                  ),
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(left: 6),
                  child: TextField(
                    controller: commissionPercentController,
                    decoration: const InputDecoration(
                      labelText: 'Provision (%)',
                      helperText: 'Provision i prosent (0-100)',
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: const TextInputType.numberWithOptions(
                        decimal: true),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          SwitchListTile(
            title: const Text('Aktiv'),
            value: _formVendorActive,
            activeColor: FlutterFlowTheme.of(context).primary,
            onChanged: (value) {
              setState(() {
                _formVendorActive = value;
              });
            },
          ),
          const SizedBox(height: 24),
          FFButtonWidget(
            onPressed: _isLoading
                ? null
                : () {
                    FocusScope.of(context).unfocus();
                    try {
                      final editingId = _editingVendor?['id'];
                      final name = _vendorNameController?.text.trim() ?? '';
                      final description =
                          _vendorDescriptionController?.text.trim() ?? '';
                      final contactEmail =
                          _vendorContactEmailController?.text.trim() ?? '';
                      final contactPhone =
                          _vendorContactPhoneController?.text.trim() ?? '';
                      final commissionText =
                          _vendorCommissionPercentController?.text.trim() ?? '';
                      final vendorData = <String, dynamic>{
                        'name': name,
                        'description':
                            description.isEmpty ? null : description,
                        'contact_email':
                            contactEmail.isEmpty ? '' : contactEmail,
                        'contact_phone':
                            contactPhone.isEmpty ? '' : contactPhone,
                        'active': _formVendorActive,
                      };
                      if (editingId != null) {
                        vendorData['id'] = editingId is int
                            ? editingId
                            : (editingId as num).toInt();
                      }
                      if (commissionText.isNotEmpty) {
                        vendorData['commission_percent'] =
                            double.tryParse(commissionText);
                      }
                      _saveVendor(vendorData);
                    } catch (e, st) {
                      if (mounted) {
                        setState(() {
                          _errorMessage = 'Feil ved lagring: $e';
                        });
                        debugPrintStack(stackTrace: st);
                      }
                    }
                  },
            text: _editingVendor?['id'] != null ? 'Oppdater' : 'Opprett',
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding:
                  const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
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
    final nameController = _categoryNameController!;
    final descriptionController = _categoryDescriptionController!;

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
                  _categoryNameController?.dispose();
                  _categoryDescriptionController?.dispose();
                  setState(() {
                    _showCategoryForm = false;
                    _editingCategory = null;
                    _categoryNameController = null;
                    _categoryDescriptionController = null;
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
          SwitchListTile(
            title: const Text('Aktiv'),
            value: _formCategoryActive,
            activeColor: FlutterFlowTheme.of(context).primary,
            onChanged: (value) {
              setState(() {
                _formCategoryActive = value;
              });
            },
          ),
          const SizedBox(height: 24),
          FFButtonWidget(
            onPressed: _isLoading
                ? null
                : () {
                    final editingId = _editingCategory?['id'];
                    final categoryData = <String, dynamic>{
                      'name': nameController.text,
                      'description': descriptionController.text.isEmpty
                          ? null
                          : descriptionController.text,
                      'active': _formCategoryActive,
                    };
                    if (editingId != null) {
                      categoryData['id'] = editingId is int
                          ? editingId
                          : (editingId as num).toInt();
                    }
                    _saveCategory(categoryData);
                  },
            text: _editingCategory?['id'] != null ? 'Oppdater' : 'Opprett',
            options: FFButtonOptions(
              width: double.infinity,
              height: 48.0,
              padding: const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 0.0, 0.0),
              iconPadding:
                  const EdgeInsetsDirectional.fromSTEB(0.0, 0.0, 8.0, 0.0),
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
