import 'package:flutter/material.dart';

class TG {
  static const g900 = Color(0xFF0B3D2E);
  static const g800 = Color(0xFF0F4F3B);
  static const g700 = Color(0xFF12634A);
  static const g600 = Color(0xFF167A5B);
  static const g500 = Color(0xFF1D9370);
  static const g50 = Color(0xFFEFF8F4);
  static const amber = Color(0xFFE9A23B);
  static const ink = Color(0xFF14231D);
  static const muted = Color(0xFF687A73);
  static const line = Color(0xFFE3EAE6);
  static const bg = Color(0xFFF5F7F6);

  // Dark-surface palette
  static const dBg = Color(0xFF0C1410);
  static const dCard = Color(0xFF17211C);
  static const dBar = Color(0xFF101B16);
  static const dLine = Color(0xFF2A3A32);
  static const dInk = Color(0xFFE8F0EC);
  static const dMuted = Color(0xFF9DB4AA);
  static const dTint = Color(0xFF1D3A2E);

  /// Set by the app root whenever the effective brightness changes.
  static bool darkMode = false;

  /// Brightness-adaptive colours — use these on theme surfaces
  /// (scaffold, cards) instead of the raw light constants.
  static Color get inkA => darkMode ? dInk : ink;
  static Color get mutedA => darkMode ? dMuted : muted;
  static Color get lineA => darkMode ? dLine : line;
  static Color get g50A => darkMode ? dTint : g50;

  static const _transitions = PageTransitionsTheme(builders: {
    TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
    TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
  });

  static ThemeData get theme {
    final cs = ColorScheme.fromSeed(seedColor: g600, primary: g600, secondary: amber, surface: Colors.white);
    final border = OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: line));
    return ThemeData(
      useMaterial3: true,
      colorScheme: cs,
      fontFamily: 'Mukta',
      scaffoldBackgroundColor: bg,
      pageTransitionsTheme: _transitions,
      appBarTheme: const AppBarTheme(backgroundColor: Colors.white, foregroundColor: ink, elevation: 0, scrolledUnderElevation: 1,
          titleTextStyle: TextStyle(fontFamily: 'Mukta', fontSize: 20, fontWeight: FontWeight.w700, color: ink)),
      cardTheme: CardThemeData(color: Colors.white, elevation: 0, margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: line))),
      inputDecorationTheme: InputDecorationTheme(filled: true, fillColor: Colors.white, border: border, enabledBorder: border,
          focusedBorder: border.copyWith(borderSide: const BorderSide(color: g600, width: 1.6))),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(minimumSize: const Size(48, 50),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontFamily: 'Mukta', fontSize: 16, fontWeight: FontWeight.w700))),
      navigationBarTheme: NavigationBarThemeData(backgroundColor: Colors.white, indicatorColor: g50,
          labelTextStyle: WidgetStateProperty.all(const TextStyle(fontFamily: 'Mukta', fontSize: 12, fontWeight: FontWeight.w600))),
    );
  }

  static ThemeData get darkTheme {
    final cs = ColorScheme.fromSeed(seedColor: g600, brightness: Brightness.dark, primary: g500, secondary: amber, surface: dCard);
    final border = OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: dLine));
    return ThemeData(
      useMaterial3: true,
      colorScheme: cs,
      fontFamily: 'Mukta',
      scaffoldBackgroundColor: dBg,
      pageTransitionsTheme: _transitions,
      appBarTheme: const AppBarTheme(backgroundColor: dBar, foregroundColor: dInk, elevation: 0, scrolledUnderElevation: 1,
          titleTextStyle: TextStyle(fontFamily: 'Mukta', fontSize: 20, fontWeight: FontWeight.w700, color: dInk)),
      cardTheme: CardThemeData(color: dCard, elevation: 0, margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: dLine))),
      dialogTheme: const DialogThemeData(backgroundColor: dCard),
      bottomSheetTheme: const BottomSheetThemeData(backgroundColor: dCard),
      dividerTheme: const DividerThemeData(color: dLine),
      inputDecorationTheme: InputDecorationTheme(filled: true, fillColor: const Color(0xFF131E19), border: border, enabledBorder: border,
          focusedBorder: border.copyWith(borderSide: const BorderSide(color: g500, width: 1.6))),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(minimumSize: const Size(48, 50),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontFamily: 'Mukta', fontSize: 16, fontWeight: FontWeight.w700))),
      navigationBarTheme: NavigationBarThemeData(backgroundColor: dBar, indicatorColor: dTint,
          labelTextStyle: WidgetStateProperty.all(const TextStyle(fontFamily: 'Mukta', fontSize: 12, fontWeight: FontWeight.w600, color: dInk))),
      navigationRailTheme: const NavigationRailThemeData(backgroundColor: dBar),
    );
  }
}
