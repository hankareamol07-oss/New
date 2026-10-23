import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api.dart';
import '../core/connectivity.dart';
import '../core/local_db.dart';
import '../core/session.dart';
import '../core/sync.dart';
import '../theme.dart';
import 'exam_paper.dart';
import 'mdm.dart';
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
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(gradient: const LinearGradient(colors: [TG.g900, TG.g600]), borderRadius: BorderRadius.circular(20)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.asset('assets/images/logo.png', width: 44, height: 44)),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(todayMarathi.isNotEmpty ? todayMarathi : DateFormat('d MMMM yyyy').format(DateTime.now()), style: const TextStyle(color: Color(0xFFBFE3D3), fontSize: 13)),
                    if (panchangVar.isNotEmpty) Text(panchangVar, style: const TextStyle(color: Color(0xFF9CCFBA), fontSize: 12)),
                  ])),
                  if (_statsLoading) const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white54)),
                ]),
                const SizedBox(height: 10),
                const Text('नमस्कार 🙏', style: TextStyle(color: Color(0xFFCFE7DC), fontSize: 14)),
                Text(schoolName ?? 'शाळा', style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
                Text('UDISE ${s['udise_code'] ?? ''} • ${s['plan_name'] ?? 'मोफत'}${s['is_subscribed'] == true ? ' ✓' : ''}', style: const TextStyle(color: Color(0xFFCFE7DC), fontSize: 13)),
              ]),
            ),
            const SizedBox(height: 10),
            if (suvichar.isNotEmpty) Container(
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
            ),
            ListenableBuilder(listenable: sync, builder: (_, __) => Card(
              child: Padding(padding: const EdgeInsets.all(14), child: Row(children: [
                Icon(sync.pending > 0 ? Icons.cloud_upload_outlined : Icons.cloud_done_outlined, color: sync.pending > 0 ? TG.amber : TG.g600, size: 30),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(sync.pending > 0 ? '${sync.pending} बदल पाठवायचे' : 'सर्व बदल Sync झाले', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                  Text(sync.lastSync == null ? 'अजून Sync केलेले नाही' : 'शेवटचे: ${DateFormat('d MMM, h:mm a').format(DateTime.parse(sync.lastSync!))}', style: const TextStyle(color: TG.muted, fontSize: 13)),
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
                                    child: Text(m.$1, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15.5, color: TG.ink), overflow: TextOverflow.ellipsis),
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
                              Text(m.$2, style: const TextStyle(color: TG.muted, fontSize: 12, height: 1.35)),
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
                    LinearProgressIndicator(value: hpcCount > 0 ? hpcCompleted / hpcCount : 0, backgroundColor: TG.line, color: TG.g600, borderRadius: BorderRadius.circular(6), minHeight: 8),
                  ])),
                ]),
                const SizedBox(height: 8),
                Text('${hpcCount - hpcCompleted} पत्रके अपूर्ण', style: const TextStyle(color: TG.muted, fontSize: 13)),
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
        Text(l, style: const TextStyle(color: TG.muted, fontSize: 12.5), maxLines: 1, overflow: TextOverflow.ellipsis),
        Text('${v ?? 0}', style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: TG.ink)),
      ])),
      Container(width: 36, height: 36, decoration: BoxDecoration(color: c.withValues(alpha: .1), borderRadius: BorderRadius.circular(10)), child: Icon(i, color: c, size: 20)),
    ])),
  );
}
