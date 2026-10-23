import 'package:flutter/material.dart';

import 'session.dart';

/// App-wide light/dark theme control, persisted in Session prefs.
class ThemeController {
  ThemeController._();

  static final ValueNotifier<ThemeMode> mode = ValueNotifier<ThemeMode>(_initial());

  static ThemeMode _initial() {
    switch (Session.I.themeModeName) {
      case 'dark':
        return ThemeMode.dark;
      case 'light':
        return ThemeMode.light;
      default:
        return ThemeMode.system;
    }
  }

  static Future<void> set(ThemeMode m) async {
    mode.value = m;
    await Session.I.setThemeModeName(m.name);
  }

  /// Whether dark colours are currently in effect for [context].
  static bool isDark(BuildContext context) =>
      mode.value == ThemeMode.dark ||
      (mode.value == ThemeMode.system &&
          MediaQuery.platformBrightnessOf(context) == Brightness.dark);

  /// One-tap light <-> dark toggle (stores an explicit choice).
  static Future<void> toggle(BuildContext context) =>
      set(isDark(context) ? ThemeMode.light : ThemeMode.dark);
}
