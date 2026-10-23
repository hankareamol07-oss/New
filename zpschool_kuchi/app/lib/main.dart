import 'package:flutter/material.dart';

import 'core/config.dart';
import 'core/connectivity.dart';
import 'core/local_db.dart';
import 'core/session.dart';
import 'core/sync.dart';
import 'core/theme_controller.dart';
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

class TechGurujiApp extends StatefulWidget {
  const TechGurujiApp({super.key});

  @override
  State<TechGurujiApp> createState() => _TechGurujiAppState();
}

class _TechGurujiAppState extends State<TechGurujiApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    ThemeController.mode.addListener(_onThemeChanged);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    ThemeController.mode.removeListener(_onThemeChanged);
    super.dispose();
  }

  void _onThemeChanged() => setState(() {});

  @override
  void didChangePlatformBrightness() => setState(() {});

  @override
  Widget build(BuildContext context) {
    final mode = ThemeController.mode.value;
    final platformDark =
        WidgetsBinding.instance.platformDispatcher.platformBrightness == Brightness.dark;
    TG.darkMode = mode == ThemeMode.dark || (mode == ThemeMode.system && platformDark);

    final s = Session.I;
    final Widget start = !s.privacyAccepted
        ? const PrivacyConsentScreen()
        : (s.loggedIn ? const HomeScreen() : const LoginScreen());
    return MaterialApp(
      navigatorKey: rootNavigatorKey,
      title: kAppName,
      debugShowCheckedModeBanner: false,
      theme: TG.theme,
      darkTheme: TG.darkTheme,
      themeMode: mode,
      home: start,
    );
  }
}
