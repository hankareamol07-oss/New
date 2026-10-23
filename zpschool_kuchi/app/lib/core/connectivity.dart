import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

/// Wraps connectivity_plus. Screens listen to [isOnlineNotifier] to react
/// to network changes without rebuilding the whole widget tree.
class NetStatus extends ChangeNotifier {
  NetStatus._() {
    _init();
  }
  static final NetStatus I = NetStatus._();

  bool _online = true;
  bool get isOnline => _online;

  StreamSubscription? _sub;

  void _init() {
    _sub = Connectivity().onConnectivityChanged.listen((results) {
      final wasOnline = _online;
      _online = results.any((r) => r != ConnectivityResult.none);
      if (_online != wasOnline) notifyListeners();
    });
    // Check current state immediately
    Connectivity().checkConnectivity().then((results) {
      _online = results.any((r) => r != ConnectivityResult.none);
      notifyListeners();
    });
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }
}
