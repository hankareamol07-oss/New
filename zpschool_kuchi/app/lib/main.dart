import 'package:flutter/material.dart';

import 'core/config.dart';
import 'core/connectivity.dart';
import 'core/local_db.dart';
import 'core/session.dart';
import 'core/sync.dart';
import 'screens/home.dart';
import 'screens/login.dart';
import 'screens/privacy_consent.dart';
import 'theme.dart';

final GlobalKey<NavigatorState> rootNavigatorKey = GlobalKey<NavigatorState>();

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Session.init();
  await LocalDb.init();
  await SyncService.I.refreshState();
  // Init connectivity listener (singleton)
  NetStatus.I;
  runApp(const TechGurujiApp());
}

class TechGurujiApp extends StatelessWidget {
  const TechGurujiApp({super.key});

  @override
  Widget build(BuildContext context) {
    final s = Session.I;
    final Widget start = !s.privacyAccepted
        ? const PrivacyConsentScreen()
        : (s.loggedIn ? const HomeScreen() : const LoginScreen());
    return MaterialApp(
      navigatorKey: rootNavigatorKey,
      title: kAppName,
      debugShowCheckedModeBanner: false,
      theme: TG.theme,
      home: start,
    );
  }
}
