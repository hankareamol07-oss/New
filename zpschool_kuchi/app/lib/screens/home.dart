import 'package:flutter/material.dart';

import '../core/session.dart';
import '../core/sync.dart';
import '../core/theme_controller.dart';
import '../theme.dart';
import 'ai_study_bot.dart';
import 'cce.dart';
import 'dashboard.dart';
import 'hpc.dart';
import 'more.dart';
import 'student_dashboard.dart';
import 'tachan.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _i = 0;
  late final List<Widget> _pages;

  @override
  void initState() {
    super.initState();
    _pages = [
      DashboardScreen(onOpenTab: (i) => setState(() => _i = i)),
      const CceScreen(),
      const HpcScreen(),
      const TachanScreen(),
      const MoreScreen(),
    ];
    SyncService.I.addListener(_onSync);
  }

  @override
  void dispose() {
    SyncService.I.removeListener(_onSync);
    super.dispose();
  }

  void _onSync() => setState(() {});

  @override
  Widget build(BuildContext context) {
    if (Session.I.role == 'student') {
      return const StudentDashboardScreen();
    }

    final wide = MediaQuery.sizeOf(context).width >= 840;
    final pending = SyncService.I.pending;

    final dests = [
      (Icons.space_dashboard_outlined, Icons.space_dashboard, 'डॅशबोर्ड', pending > 0),
      (Icons.assignment_turned_in_outlined, Icons.assignment_turned_in, 'CCE', false),
      (Icons.insert_chart_outlined, Icons.insert_chart, 'HPC', false),
      (Icons.edit_note_outlined, Icons.edit_note, 'टाचण', false),
      (Icons.apps_outlined, Icons.apps, 'अधिक', false),
    ];

    final body = IndexedStack(index: _i, children: _pages);
    if (wide) {
      return Scaffold(
        body: Row(children: [
          NavigationRail(
            selectedIndex: _i,
            onDestinationSelected: (v) => setState(() => _i = v),
            labelType: NavigationRailLabelType.all,
            destinations: [
              for (final d in dests)
                NavigationRailDestination(
                  icon: d.$4 ? Badge(smallSize: 8, child: Icon(d.$1)) : Icon(d.$1),
                  selectedIcon: d.$4 ? Badge(smallSize: 8, child: Icon(d.$2)) : Icon(d.$2),
                  label: Text(d.$3),
                ),
            ],
          ),
          const VerticalDivider(width: 1),
          Expanded(child: body),
        ]),
      );
    }
    return Scaffold(
      appBar: AppBar(
        leading: Padding(
          padding: const EdgeInsets.only(left: 12),
          child: Center(
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .18), blurRadius: 5, offset: const Offset(0, 2))],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.asset('assets/images/logo.png', fit: BoxFit.cover),
              ),
            ),
          ),
        ),
        leadingWidth: 52,
        title: Text(
          Session.I.school['name_mr'] ?? Session.I.school['name'] ?? 'जि. प. शाळा नं. २ कुची',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
        ),
        backgroundColor: TG.g800,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: Icon(Theme.of(context).brightness == Brightness.dark
                ? Icons.light_mode_outlined
                : Icons.dark_mode_outlined),
            tooltip: 'डार्क / लाईट मोड',
            onPressed: () => ThemeController.set(
                Theme.of(context).brightness == Brightness.dark
                    ? ThemeMode.light
                    : ThemeMode.dark),
          ),
          IconButton(
            icon: const Icon(Icons.smart_toy),
            tooltip: 'AI अभ्यास मित्र',
            onPressed: () => openAiStudyBot(context),
          ),
        ],
      ),
      body: body,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _i,
        onDestinationSelected: (v) => setState(() => _i = v),
        destinations: [
          for (final d in dests)
            NavigationDestination(
              icon: d.$4 ? Badge(smallSize: 8, child: Icon(d.$1)) : Icon(d.$1),
              selectedIcon: d.$4 ? Badge(smallSize: 8, child: Icon(d.$2)) : Icon(d.$2),
              label: d.$3,
            ),
        ],
      ),
    );
  }
}
