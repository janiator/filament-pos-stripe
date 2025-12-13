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
import 'package:flutter/foundation.dart';
import 'dart:convert';
import 'dart:async';
import 'dart:io';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:multicast_dns/multicast_dns.dart';
// End custom code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

class PrinterDetectionManager extends StatefulWidget {
  const PrinterDetectionManager({
    super.key,
    this.width,
    this.height,
    required this.apiBaseUrl,
    required this.authToken,
    this.currentPosDeviceId,
  });

  final double? width;
  final double? height;
  final String apiBaseUrl;
  final String authToken;
  final int? currentPosDeviceId;

  @override
  State<PrinterDetectionManager> createState() =>
      _PrinterDetectionManagerState();
}

class _PrinterDetectionManagerState extends State<PrinterDetectionManager> {
  List<Map<String, dynamic>> _detectedPrinters = [];
  List<Map<String, dynamic>> _existingPrinters = [];
  List<Map<String, dynamic>> _posDevices = [];
  bool _isScanning = false;
  bool _isLoading = false;
  String? _errorMessage;
  String? _successMessage;
  Map<String, dynamic>? _selectedPrinter;
  bool _showPrinterForm = false;
  bool _isRegistering = false;

  // Form controllers
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _ipAddressController = TextEditingController();
  final TextEditingController _portController = TextEditingController(text: '9100');
  final TextEditingController _deviceIdController = TextEditingController(text: 'local_printer');
  String _selectedPrinterType = 'epson';
  String _selectedPaperWidth = '80';
  bool _useHttps = false;
  bool _setAsDefault = true; // Default to true for new printers

  @override
  void initState() {
    super.initState();
    _loadExistingPrinters();
    _loadPosDevices();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _ipAddressController.dispose();
    _portController.dispose();
    _deviceIdController.dispose();
    super.dispose();
  }

  Future<void> _loadExistingPrinters() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/receipt-printers');

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
        final printers = (data['printers'] as List<dynamic>?)
                ?.map((p) => p as Map<String, dynamic>)
                .toList() ??
            [];

        if (mounted) {
          setState(() {
            _existingPrinters = printers;
            _isLoading = false;
          });
        }
      } else {
        throw Exception('Failed to load printers: ${response.statusCode}');
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Error loading printers: ${e.toString()}';
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _loadPosDevices() async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/pos-devices');

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
        final devices = (data['devices'] as List<dynamic>?)
                ?.map((d) => d as Map<String, dynamic>)
                .toList() ??
            [];

        if (mounted) {
          setState(() {
            _posDevices = devices;
          });
        }
      }
    } catch (e) {
      // Silently fail - POS devices are optional
      if (mounted) {
        debugPrint('Error loading POS devices: $e');
      }
    }
  }

  Future<void> _scanNetwork() async {
    setState(() {
      _isScanning = true;
      _detectedPrinters = [];
      _errorMessage = null;
    });

    try {
      // Check if running on web (network scanning not supported)
      // ignore: avoid_web_libraries_in_flutter
      if (kIsWeb) {
        throw Exception('Network scanning is not available on web. Please use manual entry.');
      }
      
      final detected = <Map<String, dynamic>>[];
      
      // Method 1: Use Bonjour/mDNS discovery (like Epson TM Utility app)
      // This is the most reliable method - printers broadcast their presence
      debugPrint('Starting Bonjour/mDNS discovery...');
      final bonjourPrinters = await _discoverPrintersViaBonjour();
      detected.addAll(bonjourPrinters);
      
      if (mounted && detected.isNotEmpty) {
        setState(() {
          _detectedPrinters = List.from(detected);
        });
      }
      
      // Method 2: Fallback to IP scanning if Bonjour didn't find anything
      // or if user wants to scan for printers not using Bonjour
      if (detected.isEmpty) {
        debugPrint('Bonjour found no printers, falling back to IP scan...');
        
        final localIp = await _getLocalIpAddress();
        if (localIp != null) {
          final parts = localIp.split('.');
          if (parts.length == 4) {
            final networkPrefix = '${parts[0]}.${parts[1]}.${parts[2]}';
            final futures = <Future<Map<String, dynamic>?>>[];
            
            // Scan a smaller range (1-50) for faster results
            for (int i = 1; i <= 50; i++) {
              if (!mounted) break;
              
              final ip = '$networkPrefix.$i';
              futures.add(_checkEpsonPrinter(ip));
              
              // Process in batches of 10
              if (futures.length >= 10 || i == 50) {
                final results = await Future.wait(futures);
                for (final result in results) {
                  if (result != null) {
                    detected.add(result);
                  }
                }
                futures.clear();
                
                if (mounted && detected.isNotEmpty) {
                  setState(() {
                    _detectedPrinters = List.from(detected);
                  });
                }
              }
            }
          }
        }
      }

      if (mounted) {
        setState(() {
          _detectedPrinters = detected;
          _isScanning = false;
        });
      }
      
      // Show message if no printers found
      if (mounted && detected.isEmpty) {
        setState(() {
          _errorMessage = 'No printers found. Try manual entry or ensure printers are on the same network.';
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Scan error: ${e.toString()}';
          _isScanning = false;
        });
      }
    }
  }

  Future<String?> _getLocalIpAddress() async {
    try {
      // Method 1: Use NetworkInterface.list() - most reliable method
      // This directly queries the system's network interfaces
      try {
        final interfaces = await NetworkInterface.list(
          includeLinkLocal: false,
          includeLoopback: false,
        );
        
        // Prefer WiFi/Ethernet interfaces over others
        for (final interface in interfaces) {
          // Skip loopback and link-local interfaces
          if (interface.name.toLowerCase().contains('loopback') ||
              interface.name.toLowerCase().contains('lo')) {
            continue;
          }
          
          for (final addr in interface.addresses) {
            if (addr.type == InternetAddressType.IPv4 && _isPrivateIp(addr.address)) {
              debugPrint('Found local IP via NetworkInterface: ${addr.address} (interface: ${interface.name})');
              return addr.address;
            }
          }
        }
        
        // If no preferred interface found, try any interface with private IP
        for (final interface in interfaces) {
          for (final addr in interface.addresses) {
            if (addr.type == InternetAddressType.IPv4 && _isPrivateIp(addr.address)) {
              debugPrint('Found local IP via NetworkInterface (fallback): ${addr.address} (interface: ${interface.name})');
              return addr.address;
            }
          }
        }
      } catch (e) {
        debugPrint('NetworkInterface method failed: $e');
      }
      
      // Method 2: Try UDP socket binding as fallback
      try {
        final socket = await RawDatagramSocket.bind(InternetAddress.anyIPv4, 0);
        final address = socket.address;
        socket.close();
        
        if (address != null && address.address != '0.0.0.0') {
          final ip = address.address;
          if (_isPrivateIp(ip)) {
            debugPrint('Found local IP via UDP socket: $ip');
            return ip;
          }
        }
      } catch (e) {
        debugPrint('UDP socket method failed: $e');
      }
      
      debugPrint('Could not determine local IP address');
      return null;
    } catch (e) {
      debugPrint('Error getting local IP: $e');
      return null;
    }
  }
  
  /// Check if an IP address is in a private network range
  bool _isPrivateIp(String ip) {
    final parts = ip.split('.');
    if (parts.length != 4) return false;
    
    final first = int.tryParse(parts[0]) ?? 0;
    final second = int.tryParse(parts[1]) ?? 0;
    
    // Private IP ranges:
    // 10.0.0.0 - 10.255.255.255
    // 172.16.0.0 - 172.31.255.255
    // 192.168.0.0 - 192.168.255.255
    if (first == 10) return true;
    if (first == 172 && second >= 16 && second <= 31) return true;
    if (first == 192 && second == 168) return true;
    
    return false;
  }

  /// Check if an IP address hosts an Epson printer via ePOS-Print API
  Future<Map<String, dynamic>?> _checkEpsonPrinter(String ip) async {
    try {
      // First, try to verify it's actually an Epson printer via ePOS-Print API
      // We need a valid ePOS-Print response, not just any HTTP response
      final printerInfo = await _getEpsonPrinterInfo(ip, false);
      if (printerInfo != null) {
        return printerInfo;
      }
      
      // Try HTTPS if HTTP failed
      final printerInfoHttps = await _getEpsonPrinterInfo(ip, true);
      if (printerInfoHttps != null) {
        return printerInfoHttps;
      }
      
      // Fallback: Check web interface (some printers return HTML instead of XML)
      // Epson printers have a web configuration page that contains model info
      final webInfo = await _checkEpsonWebInterface(ip);
      if (webInfo != null) {
        return webInfo;
      }
      
      // Fallback: Check raw printing port (9100) for generic network printer
      // Only label as generic, not Epson
      try {
        final socket = await Socket.connect(ip, 9100, timeout: const Duration(milliseconds: 800));
        socket.destroy();
        return {
          'ip_address': ip,
          'name': 'Network Printer at $ip',
          'port': 9100,
          'printer_type': 'generic',
          'detected': true,
        };
      } catch (e) {
        // Port not open
      }
    } catch (e) {
      // Ignore errors during scanning
    }
    
    return null;
  }
  
  /// Get detailed information about an Epson printer
  /// Uses proper ePOS-Print API request (similar to epson_epos package approach)
  /// Sends a status request to get reliable printer information
  Future<Map<String, dynamic>?> _getEpsonPrinterInfo(String ip, bool useHttps) async {
    try {
      final protocol = useHttps ? 'https' : 'http';
      final url = Uri.parse('$protocol://$ip/cgi-bin/epos/service.cgi?devid=local_printer&timeout=5000');
      
      // Use proper ePOS-Print API request (like the package does)
      // Send a status request to get printer information - more reliable than GET
      final statusXml = '''<?xml version="1.0" encoding="UTF-8"?>
<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">
  <request>
    <status />
  </request>
</epos-print>''';
      
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/xml'},
        body: statusXml,
      ).timeout(const Duration(milliseconds: 2000));
      
      debugPrint('Checking $ip ($protocol) - Status: ${response.statusCode}');
      
      // Epson printers respond with <ePOSResponse> containing status information
      if (response.statusCode >= 200 && response.statusCode < 500) {
        final body = response.body;
        final bodyLower = body.toLowerCase();
        
        // Debug: Show response preview to understand what we're getting
        debugPrint('$ip - Response body (first 500 chars): ${body.length > 500 ? body.substring(0, 500) : body}');
        debugPrint('$ip - Response headers: ${response.headers}');
        
        // CRITICAL CHECK: Must have <ePOSResponse> - this is unique to Epson printers
        final hasEposResponse = bodyLower.contains('<eposresponse') || 
                               bodyLower.contains('eposresponse>');
        final hasEposNamespace = bodyLower.contains('http://www.epson-pos.com/schemas');
        
        // Check for Epson in server header (very reliable)
        final serverHeader = response.headers['server']?.toLowerCase() ?? '';
        final hasEpsonHeader = serverHeader.contains('epson');
        
        // Check if it's an HTML page (printer web interface) - Epson printers have web config
        final isHtmlPage = bodyLower.contains('<html') || bodyLower.contains('<!doctype');
        final hasEpsonInHtml = isHtmlPage && (bodyLower.contains('epson') || 
                                             bodyLower.contains('tm-m30') ||
                                             bodyLower.contains('tm30') ||
                                             bodyLower.contains('tm-'));
        
        // Check for Epson in title or other HTML elements
        final hasEpsonTitle = bodyLower.contains('<title') && bodyLower.contains('epson');
        
        debugPrint('$ip - Checks: ePOSResponse=$hasEposResponse, namespace=$hasEposNamespace, header=$hasEpsonHeader, html=$isHtmlPage, epsonInHtml=$hasEpsonInHtml, epsonTitle=$hasEpsonTitle');
        
        // Accept if it has ePOS response/namespace OR Epson server header OR Epson in HTML page
        // This handles printers that return HTML web interface instead of XML
        if (hasEposResponse || hasEposNamespace || hasEpsonHeader || hasEpsonInHtml || hasEpsonTitle) {
          String printerName = 'Epson Printer';
          String? model;
          
          // Extract model from response (package likely does this too)
          // Try <model> tag first (most reliable)
          final modelMatch = RegExp(r'<model[^>]*>([^<]+)</model>', caseSensitive: false).firstMatch(body);
          if (modelMatch != null) {
            model = modelMatch.group(1)?.trim();
            if (model != null && model.isNotEmpty) {
              printerName = 'Epson $model';
            }
          }
          
          // Also check for model in other XML tags
          if (model == null || model.isEmpty) {
            final deviceMatch = RegExp(r'<device[^>]*model="([^"]+)"', caseSensitive: false).firstMatch(body);
            if (deviceMatch != null) {
              model = deviceMatch.group(1)?.trim();
              printerName = 'Epson $model';
            }
          }
          
          // Fallback: Pattern matching for TM models (check in HTML too)
          if (model == null || model.isEmpty) {
            // Check for TM-m30III-H specifically (the user's model)
            if (bodyLower.contains('tm-m30iii-h') || bodyLower.contains('tm-m30-iii-h')) {
              model = 'TM-m30III-H';
              printerName = 'Epson TM-m30III-H';
            } else if (bodyLower.contains('m30') || bodyLower.contains('tm-m30') || bodyLower.contains('tm30')) {
              model = 'TM-m30III';
              printerName = 'Epson TM-m30III';
            } else if (bodyLower.contains('tm-')) {
              final tmMatch = RegExp(r'tm-?([a-z0-9-]+)', caseSensitive: false).firstMatch(bodyLower);
              if (tmMatch != null) {
                model = 'TM-${tmMatch.group(1)?.toUpperCase() ?? ""}';
                printerName = 'Epson $model';
              }
            }
          }
          
          // Always use "local_printer" as device_id (standard for ePOS-Print)
          debugPrint('$ip - ✓ CONFIRMED Epson: $printerName');
          return {
            'ip_address': ip,
            'name': printerName,
            'port': useHttps ? 443 : 80,
            'printer_type': 'epson',
            'printer_model': model ?? 'Epson ePOS',
            'use_https': useHttps,
            'device_id': 'local_printer', // Always use local_printer for ePOS-Print
            'detected': true,
          };
        } else {
          debugPrint('$ip - ✗ Not Epson (no ePOSResponse or namespace)');
        }
      }
    } catch (e) {
      // Ignore errors - not an Epson printer or not reachable
      debugPrint('$ip - ✗ Error: $e');
    }
    
    return null;
  }
  
  /// Discover Epson printers using Bonjour/mDNS (like Epson TM Utility app)
  /// Uses multicast_dns package for reliable mDNS discovery
  /// This is the most reliable method - printers broadcast their presence via mDNS
  Future<List<Map<String, dynamic>>> _discoverPrintersViaBonjour() async {
    final discovered = <Map<String, dynamic>>[];
    final seenIps = <String>{}; // Track IPs to avoid duplicates
    
    try {
      debugPrint('Starting mDNS/Bonjour discovery...');
      
      final mdns = MDnsClient();
      await mdns.start();
      
      // Look for printer services (Epson printers use various service types)
      final serviceTypes = [
        '_printer._tcp.local',
        '_http._tcp.local',
        '_ipps._tcp.local',
      ];
      
      for (final serviceType in serviceTypes) {
        if (!mounted) break;
        
        try {
          debugPrint('Discovering $serviceType...');
          
          // Query for services
          await for (final PtrResourceRecord ptr in mdns.lookup<PtrResourceRecord>(
            ResourceRecordQuery.serverPointer(serviceType),
          ).timeout(const Duration(seconds: 3))) {
            if (!mounted) break;
            
            try {
              // Resolve the service
              await for (final SrvResourceRecord srv in mdns.lookup<SrvResourceRecord>(
                ResourceRecordQuery.service(ptr.domainName),
              ).timeout(const Duration(seconds: 2))) {
                if (!mounted) break;
                
                // Get the IP address
                await for (final IPAddressResourceRecord ip in mdns.lookup<IPAddressResourceRecord>(
                  ResourceRecordQuery.addressIPv4(srv.target),
                ).timeout(const Duration(seconds: 2))) {
                  if (!mounted) break;
                  
                  final ipAddress = ip.address.address;
                  
                  // Skip if we've already seen this IP (deduplicate)
                  if (seenIps.contains(ipAddress)) {
                    debugPrint('Skipping duplicate IP: $ipAddress');
                    continue;
                  }
                  
                  final serviceName = ptr.domainName.replaceAll('.$serviceType', '');
                  
                  debugPrint('Found mDNS service: $serviceName at $ipAddress');
                  
                  // Check if it's an Epson printer by name
                  final nameLower = serviceName.toLowerCase();
                  final isEpson = nameLower.contains('epson') || nameLower.contains('tm-');
                  
                  if (isEpson) {
                    // Mark this IP as seen
                    seenIps.add(ipAddress);
                    
                    String printerName = serviceName;
                    String? model;
                    
                    // Extract model from service name (e.g., "EPSON TM-m30III-H")
                    if (nameLower.contains('tm-')) {
                      final modelMatch = RegExp(r'tm-?([a-z0-9-]+)', caseSensitive: false)
                          .firstMatch(nameLower);
                      if (modelMatch != null) {
                        model = 'TM-${modelMatch.group(1)?.toUpperCase() ?? ""}';
                        printerName = 'Epson $model';
                      }
                    }
                    
                    // Verify it's actually an Epson printer via ePOS-Print API
                    final printerInfo = await _getEpsonPrinterInfo(ipAddress, false);
                    if (printerInfo != null) {
                      // Use mDNS name if it's more descriptive
                      if (printerName.isNotEmpty && printerName != 'Epson Printer') {
                        printerInfo['name'] = printerName;
                      }
                      if (model != null && model.isNotEmpty) {
                        printerInfo['printer_model'] = model;
                      }
                      printerInfo['discovered_via'] = 'bonjour';
                      printerInfo['bonjour_name'] = serviceName; // Store Bonjour name for unique identification
                      discovered.add(printerInfo);
                      debugPrint('✓ Discovered Epson printer via Bonjour: ${printerInfo['name']} at $ipAddress (Bonjour: $serviceName)');
                    } else {
                      // Even if ePOS-Print doesn't respond, if mDNS says it's Epson, trust it
                      // This handles cases where ePOS-Print service might be disabled
                      discovered.add({
                        'ip_address': ipAddress,
                        'name': printerName,
                        'port': srv.port ?? 80,
                        'printer_type': 'epson',
                        'printer_model': model ?? 'Epson ePOS',
                        'use_https': false,
                        'device_id': 'local_printer', // Always use local_printer
                        'discovered_via': 'bonjour',
                        'bonjour_name': serviceName, // Store Bonjour name for unique identification
                        'detected': true,
                      });
                      debugPrint('✓ Discovered Epson printer via Bonjour (no ePOS response): $printerName at $ipAddress (Bonjour: $serviceName)');
                    }
                  }
                }
              }
            } catch (e) {
              debugPrint('Error resolving service ${ptr.domainName}: $e');
            }
          }
        } catch (e) {
          debugPrint('Error discovering $serviceType: $e');
        }
      }
      
      // Stop mDNS client (stop() returns void, not a Future)
      try {
        mdns.stop();
      } catch (e) {
        debugPrint('Error stopping mDNS client: $e');
      }
    } catch (e) {
      debugPrint('Bonjour/mDNS discovery error: $e');
      // Fallback to IP scanning if Bonjour fails
    }
    
    return discovered;
  }
  
  /// Check Epson printer web interface (fallback if ePOS-Print API doesn't respond)
  /// Some printers return HTML web interface instead of XML
  Future<Map<String, dynamic>?> _checkEpsonWebInterface(String ip) async {
    try {
      // Try HTTP first
      final url = Uri.parse('http://$ip/');
      final response = await http.get(url).timeout(const Duration(milliseconds: 2000));
      
      if (response.statusCode == 200) {
        final body = response.body;
        final bodyLower = body.toLowerCase();
        final headers = response.headers;
        
        // Check for Epson indicators in web interface
        final serverHeader = headers['server']?.toLowerCase() ?? '';
        final hasEpsonHeader = serverHeader.contains('epson');
        final hasEpsonInBody = bodyLower.contains('epson') && 
                              (bodyLower.contains('tm-') || bodyLower.contains('printer'));
        final hasEpsonTitle = bodyLower.contains('<title') && bodyLower.contains('epson');
        
        debugPrint('$ip - Web interface check: header=$hasEpsonHeader, body=$hasEpsonInBody, title=$hasEpsonTitle');
        
        if (hasEpsonHeader || hasEpsonInBody || hasEpsonTitle) {
          String printerName = 'Epson Printer';
          String? model;
          
          // Try to extract model from HTML
          // Look for TM-m30III-H or other models in the HTML
          if (bodyLower.contains('tm-m30iii-h') || bodyLower.contains('tm-m30-iii-h')) {
            model = 'TM-m30III-H';
            printerName = 'Epson TM-m30III-H';
          } else if (bodyLower.contains('m30') || bodyLower.contains('tm-m30') || bodyLower.contains('tm30')) {
            model = 'TM-m30III';
            printerName = 'Epson TM-m30III';
          } else if (bodyLower.contains('tm-')) {
            final tmMatch = RegExp(r'tm-?([a-z0-9-]+)', caseSensitive: false).firstMatch(bodyLower);
            if (tmMatch != null) {
              model = 'TM-${tmMatch.group(1)?.toUpperCase() ?? ""}';
              printerName = 'Epson $model';
            }
          }
          
          debugPrint('$ip - ✓ CONFIRMED Epson via web interface: $printerName');
          return {
            'ip_address': ip,
            'name': printerName,
            'port': 80,
            'printer_type': 'epson',
            'printer_model': model ?? 'Epson ePOS',
            'use_https': false,
            'device_id': 'local_printer', // Default for web interface
            'detected': true,
          };
        }
      }
    } catch (e) {
      debugPrint('$ip - Web interface check error: $e');
    }
    
    return null;
  }

  /// Find existing printer by Bonjour name (from metadata) or by name + model combination
  Map<String, dynamic>? _findExistingPrinter(Map<String, dynamic> detectedPrinter) {
    final bonjourName = detectedPrinter['bonjour_name'] as String?;
    final printerName = detectedPrinter['name'] as String? ?? '';
    final printerModel = detectedPrinter['printer_model'] as String?;
    final ipAddress = detectedPrinter['ip_address'] as String? ?? '';
    
    // First, try to find by Bonjour name (stored in printer_metadata)
    if (bonjourName != null && bonjourName.isNotEmpty) {
      for (final existing in _existingPrinters) {
        final metadata = existing['printer_metadata'] as Map<String, dynamic>?;
        final existingBonjourName = metadata?['bonjour_name'] as String?;
        if (existingBonjourName != null && 
            existingBonjourName.toLowerCase() == bonjourName.toLowerCase()) {
          debugPrint('Found existing printer by Bonjour name: $bonjourName');
          return existing;
        }
      }
    }
    
    // Fallback: Try to find by name + model combination (if Bonjour name not available)
    if (printerName.isNotEmpty && printerModel != null && printerModel.isNotEmpty) {
      for (final existing in _existingPrinters) {
        final existingName = existing['name'] as String? ?? '';
        final existingModel = existing['printer_model'] as String?;
        if (existingName.toLowerCase() == printerName.toLowerCase() &&
            existingModel != null &&
            existingModel.toLowerCase() == printerModel.toLowerCase()) {
          debugPrint('Found existing printer by name + model: $printerName ($printerModel)');
          return existing;
        }
      }
    }
    
    // Last resort: Check by IP (in case IP hasn't changed)
    if (ipAddress.isNotEmpty) {
      for (final existing in _existingPrinters) {
        final existingIp = existing['ip_address'] as String? ?? '';
        if (existingIp.toLowerCase() == ipAddress.toLowerCase()) {
          debugPrint('Found existing printer by IP: $ipAddress');
          return existing;
        }
      }
    }
    
    return null;
  }

  void _selectDetectedPrinter(Map<String, dynamic> printer) async {
    // Check if printer already exists by Bonjour name or name + model
    final existingPrinter = _findExistingPrinter(printer);
    
    if (existingPrinter != null && existingPrinter['id'] != null) {
      // Printer already exists - check if IP has changed
      final existingIp = existingPrinter['ip_address'] as String? ?? '';
      final newIp = printer['ip_address'] as String? ?? '';
      
      if (existingIp.toLowerCase() != newIp.toLowerCase() && newIp.isNotEmpty) {
        // IP has changed - update the printer's IP address
        debugPrint('IP changed for printer ${existingPrinter['id']}: $existingIp -> $newIp');
        await _updatePrinterIp(existingPrinter['id'] as int, newIp);
      }
      
      // Show form to allow user to decide if they want to set as default
      setState(() {
        _selectedPrinter = existingPrinter;
        _showPrinterForm = true;
      });
      _editExistingPrinter(existingPrinter);
      return;
    }
    
    // New printer - show form to register
    setState(() {
      _selectedPrinter = printer;
      _ipAddressController.text = printer['ip_address'] as String? ?? '';
      _portController.text = (printer['port'] as int? ?? 9100).toString();
      _deviceIdController.text = 'local_printer'; // Always use local_printer
      _nameController.text = printer['name'] as String? ?? 'Printer ${printer['ip_address']}';
      _selectedPrinterType = printer['printer_type'] as String? ?? 'epson';
      _selectedPaperWidth = printer['paper_width'] as String? ?? '80';
      _useHttps = printer['use_https'] as bool? ?? false;
      _setAsDefault = true; // Default to true for new printers
      _showPrinterForm = true;
    });
  }

  void _editExistingPrinter(Map<String, dynamic> printer) {
    // Check if this printer is the default for the current POS device
    final printerId = printer['id'] as int?;
    bool isDefaultForCurrentPos = false;
    
    if (widget.currentPosDeviceId != null && printerId != null) {
      // We'll check this after loading POS devices, but for now assume false
      // The actual check will happen when we have the POS device data
      isDefaultForCurrentPos = false; // Will be updated when we check
    }
    
    setState(() {
      _selectedPrinter = printer;
      _ipAddressController.text = printer['ip_address'] as String? ?? '';
      _portController.text = (printer['port'] as int? ?? 9100).toString();
      _deviceIdController.text = 'local_printer'; // Always use local_printer
      _nameController.text = printer['name'] as String? ?? '';
      _selectedPrinterType = printer['printer_type'] as String? ?? 'epson';
      _selectedPaperWidth = printer['paper_width'] as String? ?? '80';
      _useHttps = printer['use_https'] as bool? ?? false;
      _setAsDefault = isDefaultForCurrentPos; // Will be updated if we can check
      _showPrinterForm = true;
    });
    
    // Check if this printer is the default for current POS device
    if (widget.currentPosDeviceId != null && printerId != null) {
      _checkIfPrinterIsDefault(printerId);
    }
  }
  
  /// Check if a printer is the default for the current POS device
  Future<void> _checkIfPrinterIsDefault(int printerId) async {
    if (widget.currentPosDeviceId == null) return;
    
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/pos-devices/${widget.currentPosDeviceId}');
      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken}',
          'Accept': 'application/json',
        },
      );
      
      if (response.statusCode == 200) {
        final responseData = jsonDecode(response.body) as Map<String, dynamic>;
        final device = responseData['device'] as Map<String, dynamic>?;
        final defaultPrinterId = device?['default_printer_id'] as int?;
        
        if (mounted) {
          setState(() {
            _setAsDefault = defaultPrinterId == printerId;
          });
        }
      }
    } catch (e) {
      debugPrint('Error checking if printer is default: $e');
    }
  }

  Future<void> _registerPrinter() async {
    if (_nameController.text.isEmpty || _ipAddressController.text.isEmpty) {
      setState(() {
        _errorMessage = 'Please fill in all required fields';
      });
      return;
    }

    setState(() {
      _isRegistering = true;
      _errorMessage = null;
      _successMessage = null;
    });

    try {
      final ipAddress = _ipAddressController.text.trim();
      final isUpdate = _selectedPrinter?['id'] != null;
      final printerId = _selectedPrinter?['id'];
      
      // Get Bonjour name from selected printer if available (from detection)
      final bonjourName = _selectedPrinter?['bonjour_name'] as String?;
      
      // Build printer metadata with Bonjour name for unique identification
      final printerMetadata = <String, dynamic>{};
      if (bonjourName != null && bonjourName.isNotEmpty) {
        printerMetadata['bonjour_name'] = bonjourName;
      }
      
      final uri = isUpdate && printerId != null
          ? Uri.parse('${widget.apiBaseUrl}/api/receipt-printers/$printerId')
          : Uri.parse('${widget.apiBaseUrl}/api/receipt-printers');

      final printerData = {
        'name': _nameController.text,
        'printer_type': _selectedPrinterType,
        'paper_width': _selectedPaperWidth,
        'connection_type': 'network',
        'ip_address': ipAddress,
        'port': int.tryParse(_portController.text) ?? 9100,
        'device_id': 'local_printer', // Always use local_printer
        'use_https': _useHttps,
        'timeout': 60000,
        'is_active': true,
        'monitor_status': false,
        'drawer_open_level': 'low',
        'use_job_id': false,
        'printer_metadata': printerMetadata.isNotEmpty ? printerMetadata : null,
        // Always assign to current POS device if available
        if (widget.currentPosDeviceId != null) 'pos_device_id': widget.currentPosDeviceId,
      };

      final response = isUpdate && printerId != null
          ? await http.put(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(printerData),
            )
          : await http.post(
              uri,
              headers: {
                'Authorization': 'Bearer ${widget.authToken}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: jsonEncode(printerData),
            );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final responseData = jsonDecode(response.body) as Map<String, dynamic>;
        final savedPrinter = responseData['printer'] as Map<String, dynamic>?;
        final printerId = savedPrinter?['id'] as int?;
        
        debugPrint('Printer ${isUpdate ? 'updated' : 'registered'} successfully. Printer ID: $printerId');
        debugPrint('Current POS device ID: ${widget.currentPosDeviceId}');
        debugPrint('Set as default: $_setAsDefault');
        
        // Set as default printer for current POS device if switch is enabled
        if (_setAsDefault && widget.currentPosDeviceId != null && printerId != null) {
          debugPrint('Setting printer $printerId as default for POS device ${widget.currentPosDeviceId}...');
          await _updatePosDeviceDefaultPrinter(widget.currentPosDeviceId!, printerId);
        } else if (!_setAsDefault && widget.currentPosDeviceId != null && printerId != null) {
          // If switch is off and this printer is currently the default, clear it
          debugPrint('Not setting as default. Checking if we need to clear existing default...');
          await _checkAndClearDefaultPrinter(widget.currentPosDeviceId!, printerId);
        } else {
          if (widget.currentPosDeviceId == null) {
            debugPrint('⚠ No current POS device ID provided - cannot set default printer');
          }
          if (printerId == null) {
            debugPrint('⚠ No printer ID returned - cannot set default printer');
          }
        }
        
        if (mounted) {
          setState(() {
            _showPrinterForm = false;
            _selectedPrinter = null;
            _successMessage = isUpdate ? 'Printer updated successfully' : 'Printer registered successfully';
            _isRegistering = false;
          });
          
          // Clear form
          _nameController.clear();
          _ipAddressController.clear();
          _portController.text = '9100';
          _deviceIdController.text = 'local_printer'; // Always local_printer
          
          // Reload printers
          await _loadExistingPrinters();
          
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(_successMessage!),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        final errorData = jsonDecode(response.body) as Map<String, dynamic>?;
        throw Exception(errorData?['message'] ??
            'Failed to ${isUpdate ? 'update' : 'register'} printer: ${response.statusCode}');
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Error: ${e.toString()}';
          _isRegistering = false;
        });
      }
    }
  }
  
  /// Update printer's IP address (when IP has changed but printer is the same)
  Future<void> _updatePrinterIp(int printerId, String newIp) async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/receipt-printers/$printerId');
      
      final response = await http.put(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken}',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'ip_address': newIp,
        }),
      );
      
      if (response.statusCode == 200) {
        debugPrint('✓ Updated printer $printerId IP address to $newIp');
        // Reload printers to get updated data
        await _loadExistingPrinters();
      } else {
        debugPrint('⚠ Failed to update printer IP: ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('⚠ Error updating printer IP: $e');
      // Don't throw - this is a secondary operation
    }
  }

  /// Check if printer is currently default and clear it if switch is off
  Future<void> _checkAndClearDefaultPrinter(int posDeviceId, int printerId) async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/pos-devices/$posDeviceId');
      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken}',
          'Accept': 'application/json',
        },
      );
      
      if (response.statusCode == 200) {
        final responseData = jsonDecode(response.body) as Map<String, dynamic>?;
        final device = responseData?['device'] as Map<String, dynamic>?;
        final currentDefaultPrinterId = device?['default_printer_id'] as int?;
        
        // If this printer is currently the default, clear it
        if (currentDefaultPrinterId == printerId) {
          debugPrint('Clearing default printer for POS device $posDeviceId...');
          await _updatePosDeviceDefaultPrinter(posDeviceId, null);
        }
      }
    } catch (e) {
      debugPrint('⚠ Error checking default printer: $e');
    }
  }

  /// Update POS device's default printer
  Future<void> _updatePosDeviceDefaultPrinter(int posDeviceId, int? printerId) async {
    try {
      final uri = Uri.parse('${widget.apiBaseUrl}/api/pos-devices/$posDeviceId');
      
      debugPrint('Updating POS device $posDeviceId default printer to $printerId...');
      
      final response = await http.put(
        uri,
        headers: {
          'Authorization': 'Bearer ${widget.authToken}',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          if (printerId != null) 'default_printer_id': printerId else 'default_printer_id': null,
        }),
      );
      
      if (response.statusCode == 200) {
        final responseData = jsonDecode(response.body) as Map<String, dynamic>?;
        final device = responseData?['device'] as Map<String, dynamic>?;
        final updatedDefaultPrinterId = device?['default_printer_id'] as int?;
        
        if (printerId != null) {
          debugPrint('✓ Updated POS device $posDeviceId default printer to $printerId');
          debugPrint('  Response default_printer_id: $updatedDefaultPrinterId');
          
          if (updatedDefaultPrinterId != printerId) {
            debugPrint('⚠ WARNING: Default printer ID mismatch! Expected $printerId, got $updatedDefaultPrinterId');
          }
        } else {
          debugPrint('✓ Cleared default printer for POS device $posDeviceId');
          debugPrint('  Response default_printer_id: $updatedDefaultPrinterId');
        }
        
        // Update activePosDevice app state with the new printer settings
        if (device != null) {
          try {
            // Ensure device_metadata is a string (DevicesStruct expects String)
            final deviceMap = Map<String, dynamic>.from(device);
            if (deviceMap['device_metadata'] != null && deviceMap['device_metadata'] is! String) {
              deviceMap['device_metadata'] = jsonEncode(deviceMap['device_metadata']);
            }
            
            // Build DevicesStruct from the updated device data
            final devicesStruct = DevicesStruct.fromMap(deviceMap);
            
            // Update global app state
            FFAppState().update(() {
              FFAppState().activePosDevice = devicesStruct;
            });
            
            debugPrint('✓ Updated activePosDevice app state with new default printer settings');
          } catch (e) {
            debugPrint('⚠ Error updating activePosDevice app state: $e');
            // Don't throw - this is a secondary operation
          }
        }
      } else {
        final errorBody = response.body;
        debugPrint('⚠ Failed to update POS device default printer: ${response.statusCode}');
        debugPrint('  Response body: $errorBody');
        
        // Show error to user
        if (mounted) {
          final errorData = jsonDecode(errorBody) as Map<String, dynamic>?;
          final errorMessage = errorData?['message'] ?? 'Failed to set default printer';
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Warning: $errorMessage'),
              backgroundColor: Colors.orange,
              duration: const Duration(seconds: 4),
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('⚠ Error updating POS device default printer: $e');
      // Show error to user
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Warning: Failed to set default printer: ${e.toString()}'),
            backgroundColor: Colors.orange,
            duration: const Duration(seconds: 4),
          ),
        );
      }
    }
  }

  void _addManualPrinter() {
    setState(() {
      _selectedPrinter = null;
      _nameController.clear();
      _ipAddressController.clear();
      _portController.text = '9100';
      _deviceIdController.text = 'local_printer'; // Always local_printer
      _selectedPrinterType = 'epson';
      _selectedPaperWidth = '80';
      _useHttps = false;
      _setAsDefault = true; // Default to true for new printers
      _showPrinterForm = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading && _existingPrinters.isEmpty) {
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
      child: _showPrinterForm ? _buildPrinterForm() : _buildMainView(),
    );
  }

  Widget _buildMainView() {
    return Column(
      children: [
        // Header with scan button
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
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Printer Management',
                    style: FlutterFlowTheme.of(context).headlineSmall,
                  ),
                  FFButtonWidget(
                    onPressed: _addManualPrinter,
                    text: 'Add Manual',
                    icon: const Icon(Icons.add, size: 20),
                    options: FFButtonOptions(
                      height: 40,
                      padding: const EdgeInsetsDirectional.fromSTEB(12, 0, 12, 0),
                      iconPadding: const EdgeInsetsDirectional.fromSTEB(0, 0, 8, 0),
                      color: FlutterFlowTheme.of(context).primary,
                      textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                            fontFamily: 'Inter',
                            color: Colors.white,
                            letterSpacing: 0.0,
                          ),
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              FFButtonWidget(
                onPressed: (kIsWeb || _isScanning) ? null : _scanNetwork,
                text: kIsWeb 
                    ? 'Network Scan Not Available (Web)'
                    : (_isScanning ? 'Scanning Network...' : 'Scan for Printers'),
                icon: Icon(
                  kIsWeb 
                      ? Icons.info_outline 
                      : (_isScanning ? Icons.hourglass_empty : Icons.search), 
                  size: 20
                ),
                options: FFButtonOptions(
                  width: double.infinity,
                  height: 48,
                  padding: const EdgeInsetsDirectional.fromSTEB(0, 0, 0, 0),
                  iconPadding: const EdgeInsetsDirectional.fromSTEB(0, 0, 8, 0),
                  color: kIsWeb 
                      ? FlutterFlowTheme.of(context).alternate
                      : FlutterFlowTheme.of(context).secondary,
                  textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                        fontFamily: 'Inter',
                        color: Colors.white,
                        letterSpacing: 0.0,
                      ),
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ],
          ),
        ),

        // Error/Success messages
        if (_errorMessage != null)
          Container(
            padding: const EdgeInsets.all(12),
            color: FlutterFlowTheme.of(context).error,
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
                  icon: const Icon(Icons.close, color: Colors.white, size: 20),
                  onPressed: () {
                    setState(() {
                      _errorMessage = null;
                    });
                  },
                ),
              ],
            ),
          ),

        if (_successMessage != null)
          Container(
            padding: const EdgeInsets.all(12),
            color: Colors.green,
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _successMessage!,
                    style: FlutterFlowTheme.of(context).bodyMedium.override(
                          fontFamily: 'Inter',
                          color: Colors.white,
                          letterSpacing: 0.0,
                        ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close, color: Colors.white, size: 20),
                  onPressed: () {
                    setState(() {
                      _successMessage = null;
                    });
                  },
                ),
              ],
            ),
          ),

        // Scanning indicator
        if (_isScanning)
          Container(
            padding: const EdgeInsets.all(16),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CircularProgressIndicator(),
                SizedBox(width: 16),
                Text('Scanning network for printers...'),
              ],
            ),
          ),

        // Detected printers section
        if (_detectedPrinters.isNotEmpty) ...[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: FlutterFlowTheme.of(context).primaryBackground,
              border: Border(
                bottom: BorderSide(
                  color: FlutterFlowTheme.of(context).alternate,
                ),
              ),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.wifi_find,
                  color: FlutterFlowTheme.of(context).primary,
                ),
                const SizedBox(width: 8),
                Text(
                  'Detected Printers (${_detectedPrinters.length})',
                  style: FlutterFlowTheme.of(context).titleMedium,
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: _detectedPrinters.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final printer = _detectedPrinters[index];
                return InkWell(
                  onTap: () => _selectDetectedPrinter(printer),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: FlutterFlowTheme.of(context).secondaryBackground,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: FlutterFlowTheme.of(context).alternate,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.print,
                          color: FlutterFlowTheme.of(context).primary,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                printer['name'] as String? ?? 'Unknown Printer',
                                style: FlutterFlowTheme.of(context).bodyMedium.override(
                                      fontFamily: 'Inter',
                                      fontWeight: FontWeight.w600,
                                      letterSpacing: 0.0,
                                    ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${printer['ip_address']}:${printer['port']}',
                                style: FlutterFlowTheme.of(context).bodySmall.override(
                                      fontFamily: 'Inter',
                                      letterSpacing: 0.0,
                                    ),
                              ),
                            ],
                          ),
                        ),
                        Icon(
                          Icons.arrow_forward_ios,
                          size: 16,
                          color: FlutterFlowTheme.of(context).secondaryText,
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],

        // Existing printers section
        if (_detectedPrinters.isEmpty) ...[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: FlutterFlowTheme.of(context).primaryBackground,
              border: Border(
                bottom: BorderSide(
                  color: FlutterFlowTheme.of(context).alternate,
                ),
              ),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.print,
                  color: FlutterFlowTheme.of(context).primary,
                ),
                const SizedBox(width: 8),
                Text(
                  'Registered Printers (${_existingPrinters.length})',
                  style: FlutterFlowTheme.of(context).titleMedium,
                ),
              ],
            ),
          ),
          Expanded(
            child: _existingPrinters.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.print_disabled,
                          size: 64,
                          color: FlutterFlowTheme.of(context).secondaryText,
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'No printers registered',
                          style: FlutterFlowTheme.of(context).titleMedium,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Scan for printers or add manually',
                          style: FlutterFlowTheme.of(context).bodyMedium,
                        ),
                      ],
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: _loadExistingPrinters,
                    child: ListView.separated(
                      padding: const EdgeInsets.all(12),
                      itemCount: _existingPrinters.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        final printer = _existingPrinters[index];
                        final posDevice = printer['pos_device'] as Map<String, dynamic>?;
                        final isActive = printer['is_active'] as bool? ?? false;

                        return InkWell(
                          onTap: () => _editExistingPrinter(printer),
                          child: Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: FlutterFlowTheme.of(context).secondaryBackground,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: isActive
                                    ? FlutterFlowTheme.of(context).primary
                                    : FlutterFlowTheme.of(context).alternate,
                                width: isActive ? 2 : 1,
                              ),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Icon(
                                      Icons.print,
                                      color: isActive
                                          ? FlutterFlowTheme.of(context).primary
                                          : FlutterFlowTheme.of(context).secondaryText,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            printer['name'] as String? ?? 'Unknown',
                                            style: FlutterFlowTheme.of(context).bodyMedium.override(
                                                  fontFamily: 'Inter',
                                                  fontWeight: FontWeight.w600,
                                                  letterSpacing: 0.0,
                                                ),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            '${printer['ip_address']}:${printer['port']}',
                                            style: FlutterFlowTheme.of(context).bodySmall.override(
                                                  fontFamily: 'Inter',
                                                  letterSpacing: 0.0,
                                                ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: isActive ? Colors.green : Colors.grey,
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: Text(
                                        isActive ? 'Active' : 'Inactive',
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Icon(
                                      Icons.chevron_right,
                                      color: FlutterFlowTheme.of(context).secondaryText,
                                    ),
                                  ],
                                ),
                                if (posDevice != null) ...[
                                  const SizedBox(height: 8),
                                  Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: FlutterFlowTheme.of(context).primaryBackground,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Row(
                                      children: [
                                        Icon(
                                          Icons.devices,
                                          size: 16,
                                          color: FlutterFlowTheme.of(context).secondaryText,
                                        ),
                                        const SizedBox(width: 8),
                                        Text(
                                          'POS: ${posDevice['device_name'] ?? 'Unknown'}',
                                          style: FlutterFlowTheme.of(context).bodySmall.override(
                                                fontFamily: 'Inter',
                                                letterSpacing: 0.0,
                                              ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
          ),
        ],
      ],
    );
  }

  Widget _buildPrinterForm() {
    // Debug: Log current POS device ID
    debugPrint('_buildPrinterForm: currentPosDeviceId = ${widget.currentPosDeviceId}');
    
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                _selectedPrinter?['id'] != null ? 'Edit Printer' : 'Register Printer',
                style: FlutterFlowTheme.of(context).headlineSmall,
              ),
              IconButton(
                icon: const Icon(Icons.close),
                onPressed: () {
                  setState(() {
                    _showPrinterForm = false;
                    _selectedPrinter = null;
                    _errorMessage = null;
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Name field
          TextField(
            controller: _nameController,
            decoration: const InputDecoration(
              labelText: 'Printer Name *',
              hintText: 'e.g., Main Receipt Printer',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),

          // IP Address field
          TextField(
            controller: _ipAddressController,
            decoration: const InputDecoration(
              labelText: 'IP Address *',
              hintText: '192.168.1.100',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 16),

          // Port field
          TextField(
            controller: _portController,
            decoration: const InputDecoration(
              labelText: 'Port',
              hintText: '9100',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 16),

          // Device ID field
          TextField(
            controller: _deviceIdController,
            decoration: const InputDecoration(
              labelText: 'Device ID',
              hintText: 'local_printer',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),

          // Printer Type dropdown
          DropdownButtonFormField<String>(
            value: _selectedPrinterType,
            decoration: const InputDecoration(
              labelText: 'Printer Type',
              border: OutlineInputBorder(),
            ),
            items: const [
              DropdownMenuItem(value: 'epson', child: Text('Epson')),
              DropdownMenuItem(value: 'star', child: Text('Star')),
              DropdownMenuItem(value: 'generic', child: Text('Generic')),
            ],
            onChanged: (value) {
              setState(() {
                _selectedPrinterType = value ?? 'epson';
              });
            },
          ),
          const SizedBox(height: 16),

          // Paper Width dropdown
          DropdownButtonFormField<String>(
            value: _selectedPaperWidth,
            decoration: const InputDecoration(
              labelText: 'Paper Width',
              border: OutlineInputBorder(),
            ),
            items: const [
              DropdownMenuItem(value: '80', child: Text('80mm')),
              DropdownMenuItem(value: '58', child: Text('58mm')),
            ],
            onChanged: (value) {
              setState(() {
                _selectedPaperWidth = value ?? '80';
              });
            },
          ),

          // Use HTTPS switch
          SwitchListTile(
            title: const Text('Use HTTPS'),
            subtitle: const Text('Enable if printer supports HTTPS'),
            value: _useHttps,
            onChanged: (value) {
              setState(() {
                _useHttps = value;
              });
            },
          ),
          const SizedBox(height: 16),
          
          // Set as default printer switch
          SwitchListTile(
            title: const Text('Set as default for this POS'),
            subtitle: widget.currentPosDeviceId != null
                ? const Text('Make this printer the default receipt printer for the current POS device')
                : const Text('No current POS device - cannot set as default'),
            value: _setAsDefault,
            onChanged: widget.currentPosDeviceId != null
                ? (value) {
                    setState(() {
                      _setAsDefault = value;
                    });
                  }
                : null, // Setting onChanged to null disables the switch
          ),
          const SizedBox(height: 24),

          // Register/Update button
          FFButtonWidget(
            onPressed: _isRegistering ? null : _registerPrinter,
            text: _isRegistering
                ? 'Registering...'
                : (_selectedPrinter?['id'] != null ? 'Update Printer' : 'Register Printer'),
            icon: _isRegistering
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                : const Icon(Icons.save, size: 20),
            options: FFButtonOptions(
              width: double.infinity,
              height: 48,
              padding: const EdgeInsetsDirectional.fromSTEB(0, 0, 0, 0),
              iconPadding: const EdgeInsetsDirectional.fromSTEB(0, 0, 8, 0),
              color: FlutterFlowTheme.of(context).primary,
              textStyle: FlutterFlowTheme.of(context).titleSmall.override(
                    fontFamily: 'Inter',
                    color: Colors.white,
                    letterSpacing: 0.0,
                  ),
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        ],
      ),
    );
  }
}

