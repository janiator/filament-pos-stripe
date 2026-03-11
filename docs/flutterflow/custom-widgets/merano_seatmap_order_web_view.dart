// Automatic FlutterFlow imports

import '/backend/schema/structs/index.dart';

import '/backend/schema/enums/enums.dart';

import '/backend/supabase/supabase.dart';

import '/actions/actions.dart' as action_blocks;

import '/flutter_flow/flutter_flow_theme.dart';

import '/flutter_flow/flutter_flow_util.dart';

import '/custom_code/widgets/index.dart';

import '/custom_code/actions/index.dart';

import '/flutter_flow/custom_functions.dart';

import 'package:flutter/material.dart';

// Begin custom widget code

// DO NOT REMOVE OR MODIFY THE CODE ABOVE!

import 'dart:async';
import 'dart:convert';
import 'package:flutter/services.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// Reads `merano_pos_seatmap_order` from localStorage and emits the parsed order.
///
/// Use `url` for either:
/// - a POSitiv wrapper page, e.g. `/merano/seatmap?...`, or
/// - a public Merano seatmap URL that writes the same localStorage key.
class SeatmapOrder {
  SeatmapOrder({
    required this.eventId,
    required this.eventName,
    required this.seats,
    required this.totalPriceOre,
    required this.currency,
    this.name,
    this.email,
    this.phone,
  });

  final int eventId;
  final String eventName;
  final List<String> seats;
  final int totalPriceOre;
  final String currency;
  final String? name;
  final String? email;
  final String? phone;

  static SeatmapOrder? fromJson(Map<String, dynamic> map) {
    final eventId = map['eventId'];
    final eventName = map['eventName'];
    final seats = map['seats'];
    final totalPriceOre = map['totalPriceOre'];
    final currency = map['currency'];

    if (eventId == null ||
        eventName == null ||
        seats == null ||
        totalPriceOre == null ||
        currency == null) {
      return null;
    }

    final seatList = seats is List
        ? seats
            .map((e) => e?.toString() ?? '')
            .where((e) => e.isNotEmpty)
            .toList()
        : <String>[];

    if (seatList.isEmpty) {
      return null;
    }

    return SeatmapOrder(
      eventId: eventId is int ? eventId : int.tryParse(eventId.toString()) ?? 0,
      eventName: eventName.toString(),
      seats: seatList,
      totalPriceOre: totalPriceOre is int
          ? totalPriceOre
          : int.tryParse(totalPriceOre.toString()) ?? 0,
      currency: currency.toString(),
      name: map['name']?.toString(),
      email: map['email']?.toString(),
      phone: map['phone']?.toString(),
    );
  }
}

class MeranoSeatmapOrderWebView extends StatefulWidget {
  const MeranoSeatmapOrderWebView({
    super.key,
    this.width,
    this.height,
    required this.url,
    this.storageKey = 'merano_pos_seatmap_order',
    this.onOrderRead,
  });

  final double? width;
  final double? height;
  final String url;
  final String storageKey;
  final void Function(SeatmapOrder order)? onOrderRead;

  @override
  State<MeranoSeatmapOrderWebView> createState() =>
      _MeranoSeatmapOrderWebViewState();
}

class _MeranoSeatmapOrderWebViewState extends State<MeranoSeatmapOrderWebView> {
  late final WebViewController _controller;
  Timer? _readLoopTimer;
  static const Duration _interval = Duration(seconds: 2);
  bool _disposed = false;
  String? _lastSeatsKey;
  int? _lastTotalPriceOre;

  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (_) {
            _readLocalStorage();
            _startReadLoop();
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  void _startReadLoop() {
    if (_disposed) {
      return;
    }

    _readLoopTimer = Timer(Duration.zero, _readAndReschedule);
  }

  void _readAndReschedule() async {
    if (_disposed) {
      return;
    }

    await _readLocalStorage();

    if (_disposed) {
      return;
    }

    _readLoopTimer = Timer(_interval, _readAndReschedule);
  }

  @override
  void dispose() {
    _disposed = true;
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    _readLoopTimer?.cancel();
    super.dispose();
  }

  Future<void> _readLocalStorage() async {
    SeatmapOrder? order;

    try {
      final raw = await _controller.runJavaScriptReturningResult(
        "window.localStorage.getItem('${widget.storageKey}');",
      );

      Map<String, dynamic>? map;

      if (raw is Map) {
        map = Map<String, dynamic>.from(raw);
      } else if (raw is String && raw.isNotEmpty && raw != 'null') {
        dynamic decoded = jsonDecode(raw);

        while (decoded is String) {
          decoded = jsonDecode(decoded);
        }

        if (decoded is Map) {
          map = Map<String, dynamic>.from(decoded);
        }
      }

      if (map != null) {
        order = SeatmapOrder.fromJson(map);
      }
    } catch (error, stack) {
      debugPrint('MeranoSeatmapOrderWebView: failed to read localStorage: $error');
      debugPrint(stack.toString());
    }

    if (order == null) {
      return;
    }

    final seatsKey = order.seats.join(',');
    final changed =
        _lastSeatsKey != seatsKey || _lastTotalPriceOre != order.totalPriceOre;

    _lastSeatsKey = seatsKey;
    _lastTotalPriceOre = order.totalPriceOre;

    if (changed && !_disposed && mounted) {
      widget.onOrderRead?.call(order);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: widget.width,
      child: Column(
        children: [
          SizedBox(
            height: widget.height,
            child: WebViewWidget(controller: _controller),
          ),
        ],
      ),
    );
  }
}
