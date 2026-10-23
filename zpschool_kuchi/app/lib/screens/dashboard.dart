import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api.dart';
import '../core/connectivity.dart';
import '../core/local_db.dart';
import '../core/session.dart';
import '../core/sync.dart';
import '../theme.dart';
import 'attendance.dart';
import 'exam_paper.dart';
import 'fee.dart';
import 'holidays.dart';
import 'mdm.dart';
import 'paripath.dart';
import 'quiz.dart';
import 'school.dart';
import 'students.dart';
import 'timetable.dart';
import 'widgets.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.onOpenTab});
  final ValueChanged<int> onOpenTab;
  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, int> _c = {};
  Map<String, dynamic> _serverStats = {};
  bool _statsLoading = false;
  List<Map<String, dynamic>> _birthdays = [];
  List<Map<String, dynamic>> _upcomingHolidays = [];
  int? _attPct;
  String _attMonthName = '';

  @override
  void initState() {
    super.initState();
    SyncService.I.addListener(_load);
    NetStatus.I.addListener(_onNetChange);
    _load();
    _loadServerStats();
  }

  @override
  void dispose() {
    SyncService.I.removeListener(_load);
    NetStatus.I.removeListener(_onNetChange);
    super.dispose();
  }

  void _onNetChange() {
    if (NetStatus.I.isOnline && mounted) _loadServerStats();
  }

  Future<void> _load() async {
    final m = <String, int>{};
    for (final t in ['students', 'classes', 'teachers', 'lesson_plans', 'cce_marks', 'holidays']) {
      m[t] = await LocalDb.I.count(t);
    }
    if (mounted) setState(() => _c = m);
    _loadHighlights();
  }

  /// Today's birthdays, this month's attendance % and upcoming holidays —
  /// computed from data already on the phone (hidden when no data).
  Future<void> _loadHighlights() async {
    try {
      final now = DateTime.now();
      final students = await LocalDb.I.rows('students');
      final bdays = <Map<String, dynamic>>[];
      for (final s in students) {
        final raw = '${s['dob'] ?? s['date_of_birth'] ?? ''}'.trim();
        if (raw.isEmpty) continue;
        final d = DateTime.tryParse(raw);
        if (d != null && d.month == now.month && d.day == now.day) bdays.add(s);
      }
      final holidays = await LocalDb.I.rows('holidays');
      final upcoming = <Map<String, dynamic>>[];
      for (final h in holidays) {
        final d = DateTime.tryParse('${h['date'] ?? ''}');
        if (d == null) continue;
        final day = DateTime(d.year, d.month, d.day);
        final today = DateTime(now.year, now.month, now.day);
        if (!day.isBefore(today)) upcoming.add({...h, '_d': day});
      }
      upcoming.sort((a, b) => (a['_d'] as DateTime).compareTo(b['_d'] as DateTime));
      final att = await LocalDb.I.rows('attendance');
      final yStart = now.month >= 6 ? now.year : now.year - 1;
      final yearStr = '$yStart-${yStart + 1}';
      var present = 0, working = 0;
      for (final r in att) {
        if ('${r['month']}' == '${now.month}' && '${r['academic_year']}' == yearStr) {
          present += int.tryParse('${r['days_present'] ?? 0}') ?? 0;
          working += int.tryParse('${r['working_days'] ?? 0}') ?? 0;
        }
      }
      const monthsMr = ['जानेवारी', 'फेब्रुवारी', 'मार्च', 'एप्रिल', 'मे', 'जून', 'जुलै', 'ऑगस्ट', 'सप्टेंबर', 'ऑक्टोबर', 'नोव्हेंबर', 'डिसेंबर'];
      if (mounted) {
        setState(() {
          _birthdays = bdays;
          _upcomingHolidays = upcoming.take(3).toList();
          _attPct = working > 0 ? (present * 100 / working).round() : null;
          _attMonthName = monthsMr[now.month - 1];
        });
      }
    } catch (_) {
      // Highlights are optional — never break the dashboard for them.
    }
  }

  Future<void> _loadServerStats() async {
    if (_statsLoading || !NetStatus.I.isOnline) return;
    setState(() => _statsLoading = true);
    try {
      final d = await Api.get('dashboard.php');
      await Session.I.updateSchool({
        'plan_name': d['plan_name'], 'is_subscribed': d['is_subscribed'],
        'suvichar': d['suvichar'], 'suvichar_source': d['suvichar_source'],
        'today_marathi': d['today_marathi'], 'panchang_var': d['panchang_var'],
        'hpc_count': d['hpc_count'], 'hpc_completed': d['hpc_completed'],
        'cce_evaluated_count': d['cce_evaluated_count'],
      });
      if (mounted) setState(() => _serverStats = d);
    } on ApiException catch (_) {}
    finally { if (mounted) setState(() => _statsLoading = false); }
  }

  Future<void> _sync() async {
    final r = await SyncService.I.run();
    if (!mounted) return;
    if (r == null) {
      toast(context, SyncService.I.error ?? 'Sync अयशस्वी', error: true);
    } else {
      toast(context, 'Sync पूर्ण: ${r.pushed} पाठवले, ${r.pulled} मिळाले${r.conflicts > 0 ? ', ${r.conflicts} संघर्ष' : ''}');
      await _loadServerStats();
    }
  }

  void _openModule(String key, String title, VoidCallback onAllowed) {
    final modAcc = (_serverStats['modules_access'] as Map?) ?? {};
    final acc = (modAcc[key] as Map?) ?? {};
    if (acc['allowed'] == false) {
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(children: [
            const Icon(Icons.lock_outline, color: Color(0xFFD97706)),
            const SizedBox(width: 8),
            Text(title),
          ]),
          content: Text(acc['message'] ?? 'हे मॉड्युल तुमच्या सध्याच्या योजनेमध्ये समाविष्ट नाही. कृपया हे मॉड्युल वापरण्यासाठी योजना अपग्रेड करा.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('समजले')),
          ],
        ),
      );
      return;
    }
    onAllowed();
  }

  String _teacherGreeting() {
    final u = Session.I.user;
    final name = (u['name_mr'] ?? u['name'] ?? '').toString().trim();
    return name.isNotEmpty ? 'नमस्कार, $name 🙏' : 'नमस्कार 🙏';
  }

  String _academicYear() {
    final s = Session.I.school;
    return (s['academic_year'] ?? s['year'] ?? '').toString().trim();
  }

  Widget _headerChip(IconData icon, String label, {bool amber = false}) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: amber ? TG.amber.withValues(alpha: .22) : Colors.white.withValues(alpha: .14),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: amber ? TG.amber.withValues(alpha: .55) : Colors.white.withValues(alpha: .25)),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 14, color: amber ? TG.amber : const Color(0xFFBFE3D3)),
          const SizedBox(width: 5),
          Text(label, style: TextStyle(color: amber ? const Color(0xFFFFE9BD) : const Color(0xFFE4F2EB), fontSize: 12, fontWeight: FontWeight.w600)),
        ]),
      );

  Widget _quickAction(IconData icon, String label, Color color, VoidCallback onTap) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 4),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 58, height: 58,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [color, color.withValues(alpha: .72)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                borderRadius: BorderRadius.circular(18),
                boxShadow: [BoxShadow(color: color.withValues(alpha: .35), blurRadius: 8, offset: const Offset(0, 4))],
              ),
              child: Icon(icon, color: Colors.white, size: 27),
            ),
            const SizedBox(height: 7),
            SizedBox(width: 66, child: Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: TG.inkA))),
          ]),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final s = Session.I.school;
    final schoolName = (s['name_mr'] as String?)?.isNotEmpty == true ? s['name_mr'] : (s['name'] ?? 'शाळा');
    final sync = SyncService.I;
    final w = MediaQuery.sizeOf(context).width;
    final cols = w >= 1000 ? 4 : (w >= 600 ? 3 : 2);
    final suvichar = (_serverStats['suvichar'] ?? s['suvichar'] ?? '').toString();
    final suvicharSource = (_serverStats['suvichar_source'] ?? s['suvichar_source'] ?? '').toString();
    final todayMarathi = (_serverStats['today_marathi'] ?? s['today_marathi'] ?? '').toString();
    final panchangVar = (_serverStats['panchang_var'] ?? s['panchang_var'] ?? '').toString();
    final hpcCount = int.tryParse('${_serverStats['hpc_count'] ?? s['hpc_count'] ?? 0}') ?? 0;
    final hpcCompleted = int.tryParse('${_serverStats['hpc_completed'] ?? s['hpc_completed'] ?? 0}') ?? 0;
    final cceCount = int.tryParse('${_serverStats['cce_evaluated_count'] ?? s['cce_evaluated_count'] ?? _c['cce_marks'] ?? 0}') ?? 0;
    final modAcc = (_serverStats['modules_access'] as Map?) ?? {};
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _sync,
          child: ListView(padding: const EdgeInsets.all(16), children: [
            ListenableBuilder(listenable: NetStatus.I, builder: (_, __) => NetStatus.I.isOnline ? const SizedBox.shrink() : Container(
              margin: const EdgeInsets.only(bottom: 10), padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(color: Colors.orange.shade100, borderRadius: BorderRadius.circular(10)),
              child: const Row(children: [Icon(Icons.wifi_off, color: Colors.orange, size: 20), SizedBox(width: 8), Expanded(child: Text('ऑफलाइन: शेवटची जतन केलेली माहिती', style: TextStyle(color: Colors.deepOrange, fontSize: 13)))]),
            )),
            AnimIn(child: Container(
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [TG.g900, TG.g700, TG.g500], begin: Alignment.topLeft, end: Alignment.bottomRight),
                borderRadius: BorderRadius.circular(22),
                boxShadow: [BoxShadow(color: TG.g600.withValues(alpha: .35), blurRadius: 16, offset: const Offset(0, 8))],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(22),
                child: Stack(children: [
                  Positioned(right: -28, top: -28, child: Container(width: 120, height: 120, decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: .07)))),
                  Positioned(right: 36, bottom: -44, child: Container(width: 96, height: 96, decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: .05)))),
                  Positioned(left: -20, bottom: -36, child: Container(width: 80, height: 80, decoration: BoxDecoration(shape: BoxShape.circle, color: TG.amber.withValues(alpha: .10)))),
                  Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Container(
                          width: 52, height: 52,
                          padding: const EdgeInsets.all(3),
                          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .22), blurRadius: 8, offset: const Offset(0, 3))]),
                          child: ClipRRect(borderRadius: BorderRadius.circular(11), child: Image.asset('assets/images/logo.png', fit: BoxFit.cover)),
                        ),
                        const SizedBox(width: 12),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(todayMarathi.isNotEmpty ? todayMarathi : DateFormat('d MMMM yyyy').format(DateTime.now()), style: const TextStyle(color: Color(0xFFBFE3D3), fontSize: 13, fontWeight: FontWeight.w600)),
                          if (panchangVar.isNotEmpty) Text(panchangVar, style: const TextStyle(color: Color(0xFF9CCFBA), fontSize: 12)),
                        ])),
                        if (_statsLoading) const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white54)),
                      ]),
                      const SizedBox(height: 14),
                      Text(_teacherGreeting(), style: const TextStyle(color: Color(0xFFCFE7DC), fontSize: 14)),
                      const SizedBox(height: 2),
                      Text(schoolName ?? 'शाळा', style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, height: 1.15)),
                      const SizedBox(height: 10),
                      Wrap(spacing: 8, runSpacing: 6, children: [
                        _headerChip(Icons.badge_outlined, 'UDISE ${s['udise_code'] ?? ''}'),
                        _headerChip(s['is_subscribed'] == true ? Icons.verified : Icons.workspace_premium_outlined, '${s['plan_name'] ?? 'मोफत योजना'}${s['is_subscribed'] == true ? ' ✓' : ''}', amber: true),
                        if (_academicYear().isNotEmpty) _headerChip(Icons.calendar_month_outlined, 'शैक्षणिक वर्ष ${_academicYear()}'),
                      ]),
                    ]),
                  ),
                ]),
              ),
            )),
            const SizedBox(height: 10),
            if (suvichar.isNotEmpty) AnimIn(delay: const Duration(milliseconds: 60), child: Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: const Color(0xFFFFFBEB), border: Border.all(color: const Color(0xFFE9A23B).withValues(alpha: .4)), borderRadius: BorderRadius.circular(14)),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.format_quote, color: TG.amber, size: 28), const SizedBox(width: 10),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(suvichar, style: const TextStyle(fontSize: 14.5, color: TG.ink, fontStyle: FontStyle.italic, height: 1.5)),
                  if (suvicharSource.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text('— $suvicharSource', style: const TextStyle(color: TG.muted, fontSize: 12.5))),
                ])),
              ]),
            )),
            const SectionTitle('द्रुत कार्ये'),
            AnimIn(delay: const Duration(milliseconds: 130), child: SizedBox(
              height: 108,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  _quickAction(Icons.how_to_reg_outlined, 'हजेरी', const Color(0xFF2563A8), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AttendanceScreen()))),
                  _quickAction(Icons.menu_book_outlined, 'परिपाठ', const Color(0xFF0F8A84), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ParipathScreen()))),
                  _quickAction(Icons.schedule_outlined, 'वेळापत्रक', const Color(0xFF7C3AED), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TimetableScreen()))),
                  _quickAction(Icons.event_available_outlined, 'सुट्ट्या', const Color(0xFFB83B5E), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const HolidaysScreen()))),
                  _quickAction(Icons.payments_outlined, 'फी नोंद', const Color(0xFFB7791F), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FeeScreen()))),
                  _quickAction(Icons.restaurant_outlined, 'पोषण आहार', const Color(0xFFD97706), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MdmScreen()))),
                  _quickAction(Icons.quiz_outlined, 'प्रश्नपत्रिका', const Color(0xFF2563A8), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ExamPaperScreen()))),
                  _quickAction(Icons.emoji_events_outlined, 'क्विझ खेळ', const Color(0xFFD97706), () => Navigator.push(context, MaterialPageRoute(builder: (_) => const QuizHomeScreen()))),
                  _quickAction(Icons.groups_outlined, 'विद्यार्थी', TG.g600, () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentsScreen()))),
                ],
              ),
            )),
            if (_birthdays.isNotEmpty || _attPct != null || _upcomingHolidays.isNotEmpty)
              AnimIn(
                delay: const Duration(milliseconds: 200),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const SectionTitle('आजचे विशेष'),
                  if (_birthdays.isNotEmpty)
                    Card(
                      margin: const EdgeInsets.only(bottom: 10),
                      child: Padding(padding: const EdgeInsets.all(14), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Container(width: 44, height: 44, decoration: BoxDecoration(color: const Color(0xFFFCE7F3), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.cake_outlined, color: Color(0xFFB83B5E))),
                        const SizedBox(width: 12),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          const Text('🎂 आजचे वाढदिवस', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                          const SizedBox(height: 3),
                          Text(
                            _birthdays.take(4).map((b) => '${b['name_mr'] ?? b['name'] ?? 'विद्यार्थी'} (इ. ${b['grade'] ?? b['std'] ?? '-'})').join(', ') + (_birthdays.length > 4 ? ' व +${_birthdays.length - 4} अधिक' : ''),
                            style: TextStyle(color: TG.mutedA, fontSize: 13),
                          ),
                        ])),
                      ])),
                    ),
                  if (_attPct != null)
                    Card(
                      margin: const EdgeInsets.only(bottom: 10),
                      child: Padding(padding: const EdgeInsets.all(14), child: Row(children: [
                        SizedBox(width: 54, height: 54, child: Stack(fit: StackFit.expand, children: [
                          CircularProgressIndicator(value: (_attPct ?? 0) / 100, strokeWidth: 6, backgroundColor: TG.lineA, color: TG.g600),
                          Center(child: Text('$_attPct%', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13))),
                        ])),
                        const SizedBox(width: 14),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('$_attMonthName महिन्याची हजेरी', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                          const SizedBox(height: 2),
                          Text('शाळेची सरासरी उपस्थिती', style: TextStyle(color: TG.mutedA, fontSize: 13)),
                        ])),
                        IconButton(icon: const Icon(Icons.how_to_reg_outlined, color: TG.g600), tooltip: 'हजेरी पहा', onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AttendanceScreen()))),
                      ])),
                    ),
                  if (_upcomingHolidays.isNotEmpty)
                    Card(
                      child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Row(children: [
                          Icon(Icons.event_available_outlined, color: TG.g600, size: 20),
                          SizedBox(width: 8),
                          Text('आगामी सुट्ट्या', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                        ]),
                        const SizedBox(height: 10),
                        for (final h in _upcomingHolidays)
                          Padding(padding: const EdgeInsets.only(bottom: 7), child: Row(children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                              decoration: BoxDecoration(color: TG.g50A, borderRadius: BorderRadius.circular(8)),
                              child: Text(DateFormat('d MMM').format(h['_d'] as DateTime), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5, color: TG.g600)),
                            ),
                            const SizedBox(width: 10),
                            Expanded(child: Text('${h['name'] ?? h['title'] ?? 'सुट्टी'}', style: const TextStyle(fontSize: 13.5), overflow: TextOverflow.ellipsis)),
                          ])),
                      ])),
                    ),
                ]),
              ),
            ListenableBuilder(listenable: sync, builder: (_, __) => Card(
              child: Padding(padding: const EdgeInsets.all(14), child: Row(children: [
                Icon(sync.pending > 0 ? Icons.cloud_upload_outlined : Icons.cloud_done_outlined, color: sync.pending > 0 ? TG.amber : TG.g600, size: 30),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(sync.pending > 0 ? '${sync.pending} बदल पाठवायचे' : 'सर्व बदल Sync झाले', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                  Text(sync.lastSync == null ? 'अजून Sync केलेले नाही' : 'शेवटचे: ${DateFormat('d MMM, h:mm a').format(DateTime.parse(sync.lastSync!))}', style: TextStyle(color: TG.mutedA, fontSize: 13)),
                ])),
                FilledButton.icon(onPressed: sync.busy ? null : _sync,
                  icon: sync.busy ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.sync, size: 18),
                  label: const Text('Sync')),
              ])),
            )),
            // Prominent UDISE+ Excel Quick Import Banner matching portal
            Container(
              margin: const EdgeInsets.only(top: 10, bottom: 6),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                border: Border.all(color: const Color(0xFFFDE68A), width: 1.2),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: const BoxDecoration(color: Color(0xFFE8F5E9), shape: BoxShape.circle),
                    child: const Icon(Icons.table_chart, color: Color(0xFF167A5B), size: 26),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'UDISE+ Excel विद्यार्थी थेट आयात करा',
                          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5, color: Color(0xFF92400E)),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'SDMS वरील Excel/CSV फाईल अपलोड करून सर्व वर्गांची माहिती ऑटो-मराठी मिळवा.',
                          style: TextStyle(fontSize: 12, color: Color(0xFFB45309)),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF167A5B),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const UdiseImportScreen()),
                    ),
                    icon: const Icon(Icons.cloud_upload, size: 18),
                    label: const Text('आयात करा', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  ),
                ],
              ),
            ),
            const SectionTitle('आकडेवारी'),
            GridView.count(crossAxisCount: cols, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.7, children: [
              _stat('विद्यार्थी', _c['students'], Icons.groups, TG.g600),
              _stat('वर्ग', _c['classes'], Icons.class_outlined, const Color(0xFF2563A8)),
              _stat('शिक्षक', _c['teachers'], Icons.badge_outlined, const Color(0xFFB7791F)),
              _stat('टाचण नोंदी', _c['lesson_plans'], Icons.edit_note, const Color(0xFF6B4FB8)),
              _stat('HPC पूर्ण', hpcCompleted, Icons.insert_chart_outlined, const Color(0xFF0F8A84)),
              _stat('CCE नोंदी', cceCount, Icons.assignment_turned_in, const Color(0xFFB83B5E)),
            ]),
            const SectionTitle('मुख्य मॉड्यूल'),
            for (final m in <(String, String, IconData, Color, String, VoidCallback)>[
              ('समग्र प्रगती पत्रक (HPC)', 'रुब्रिक → अभिप्राय → कामगिरी → स्तर फॉर्म → भाग अ → PDF', Icons.insert_chart_outlined, const Color(0xFF0F8A84), 'hpc',
                () => widget.onOpenTab(2)),
              ('सातत्यपूर्ण मूल्यमापन', 'विषय → भारांश → शिक्षक → उपस्थिती → गुण → नोंदी → निष्पत्ती → अहवाल', Icons.assignment_turned_in_outlined, const Color(0xFFB83B5E), 'cce',
                () => widget.onOpenTab(1)),
              ('टाचण', 'दैनिक, साप्ताहिक, वार्षिक नियोजन, वेळापत्रक, प्रिंट', Icons.edit_note, const Color(0xFF6B4FB8), 'tachan', () => widget.onOpenTab(3)),
              ('प्रश्नपत्रिका', 'नवीन पेपर, माझ्या प्रश्नपत्रिका, गृहपाठ, Quiz, स्पर्धा परीक्षा', Icons.quiz_outlined, const Color(0xFF2563A8), 'que',
                () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ExamPaperScreen()))),
            ]) Builder(builder: (ctx) {
              final acc = (modAcc[m.$5] as Map?) ?? {};
              final isTrial = acc['type'] == 'trial';
              final isLocked = acc['allowed'] == false;
              final isFree = acc['type'] == 'free';
              final badge = isTrial
                  ? 'चाचणी: ${acc['days_left'] ?? 0} दिवस'
                  : (isLocked ? '🔒 अपग्रेड' : (isFree ? 'मोफत' : (acc['type'] == 'combo' || acc['type'] == 'subscribed' ? 'सक्रिय' : null)));
              final badgeColor = isTrial
                  ? const Color(0xFFD97706)
                  : (isLocked ? const Color(0xFFDC2626) : const Color(0xFF059669));
              return Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () => _openModule(m.$5, m.$1, m.$6),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      children: [
                        Container(
                          width: 46,
                          height: 46,
                          decoration: BoxDecoration(color: m.$4.withValues(alpha: .12), borderRadius: BorderRadius.circular(12)),
                          child: Icon(m.$3, color: m.$4, size: 26),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Flexible(
                                    child: Text(m.$1, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15.5, color: TG.inkA), overflow: TextOverflow.ellipsis),
                                  ),
                                  if (badge != null) ...[
                                    const SizedBox(width: 8),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: badgeColor.withValues(alpha: .12),
                                        borderRadius: BorderRadius.circular(12),
                                        border: Border.all(color: badgeColor.withValues(alpha: .3)),
                                      ),
                                      child: Text(badge, style: TextStyle(color: badgeColor, fontWeight: FontWeight.w700, fontSize: 10.5)),
                                    ),
                                  ],
                                ],
                              ),
                              const SizedBox(height: 3),
                              Text(m.$2, style: TextStyle(color: TG.mutedA, fontSize: 12, height: 1.35)),
                            ],
                          ),
                        ),
                        Icon(isLocked ? Icons.lock_outline : Icons.chevron_right, color: isLocked ? TG.amber : m.$4),
                      ],
                    ),
                  ),
                ),
              );
            }),
            const SectionTitle('इतर कार्ये'),
            GridView.count(crossAxisCount: cols, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.2, children: [
              IconTile(icon: Icons.groups, label: 'विद्यार्थी', sub: 'यादी, नवीन नोंद', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentsScreen()))),
              IconTile(icon: Icons.table_chart, label: 'UDISE+ आयात', sub: 'Excel विद्यार्थी नोंद', color: const Color(0xFF167A5B), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const UdiseImportScreen()))),
              IconTile(icon: Icons.assignment_turned_in_outlined, label: 'CCE मूल्यमापन', sub: 'गुण नोंद', color: const Color(0xFFB83B5E), onTap: () => widget.onOpenTab(1)),
              IconTile(icon: Icons.edit_note, label: 'टाचण', sub: 'पाठ नियोजन', color: const Color(0xFF6B4FB8), onTap: () => widget.onOpenTab(3)),
              IconTile(icon: Icons.insert_chart_outlined, label: 'समग्र प्रगती पत्रक', sub: 'HPC व PDF', color: const Color(0xFF0F8A84), onTap: () => widget.onOpenTab(2)),
              IconTile(icon: Icons.restaurant_outlined, label: 'पोषण आहार', sub: 'MDM नोंद', color: const Color(0xFFB7791F), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MdmScreen()))),
              IconTile(icon: Icons.schedule_outlined, label: 'वेळापत्रक', sub: 'वर्गनिहाय', color: const Color(0xFF7C3AED), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TimetableScreen()))),
              IconTile(icon: Icons.badge_outlined, label: 'शिक्षक संपादन', sub: 'शिक्षक माहिती व संपादन', color: const Color(0xFF0F766E), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SchoolListScreen(teachers: true)))),
            ]),
            if (hpcCount > 0) ...[
              const SectionTitle('HPC प्रगती'),
              Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  const Icon(Icons.insert_chart_outlined, color: TG.g600), const SizedBox(width: 10),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('$hpcCompleted / $hpcCount पत्रके पूर्ण', style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    LinearProgressIndicator(value: hpcCount > 0 ? hpcCompleted / hpcCount : 0, backgroundColor: TG.lineA, color: TG.g600, borderRadius: BorderRadius.circular(6), minHeight: 8),
                  ])),
                ]),
                const SizedBox(height: 8),
                Text('${hpcCount - hpcCompleted} पत्रके अपूर्ण', style: TextStyle(color: TG.mutedA, fontSize: 13)),
              ]))),
            ],
            const SizedBox(height: 16),
          ]),
        ),
      ),
    );
  }

  Widget _stat(String l, int? v, IconData i, Color c) => Card(
    child: Padding(padding: const EdgeInsets.all(14), child: Row(children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
        Text(l, style: TextStyle(color: TG.mutedA, fontSize: 12.5), maxLines: 1, overflow: TextOverflow.ellipsis),
        TweenAnimationBuilder<int>(
          tween: IntTween(begin: 0, end: v ?? 0),
          duration: const Duration(milliseconds: 800),
          curve: Curves.easeOutCubic,
          builder: (_, val, __) => Text('$val', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: TG.inkA)),
        ),
      ])),
      Container(width: 40, height: 40, decoration: BoxDecoration(gradient: LinearGradient(colors: [c, c.withValues(alpha: .7)], begin: Alignment.topLeft, end: Alignment.bottomRight), borderRadius: BorderRadius.circular(12), boxShadow: [BoxShadow(color: c.withValues(alpha: .3), blurRadius: 6, offset: const Offset(0, 3))]), child: Icon(i, color: Colors.white, size: 21)),
    ])),
  );
}
