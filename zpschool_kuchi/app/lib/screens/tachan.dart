import 'package:flutter/material.dart';

import '../core/remote.dart';
import 'common.dart';
import 'holidays.dart';
import 'tachan_more.dart';
import 'timetable.dart';
import 'widgets.dart';

const _api = 'tachan_native.php';
String _d(DateTime d) => d.toIso8601String().substring(0, 10);

class TachanScreen extends StatefulWidget {
  const TachanScreen({super.key, this.initialDate});
  final String? initialDate;
  @override
  State<TachanScreen> createState() => _TachanScreenState();
}

class _TachanScreenState extends State<TachanScreen> {
  late String date;
  int classId = 0;
  int month = DateTime.now().month;
  Map? lastPrint;

  @override
  void initState() {
    super.initState();
    date = widget.initialDate ?? _d(DateTime.now());
  }

  @override
  void didUpdateWidget(covariant TachanScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialDate != null && widget.initialDate != oldWidget.initialDate) {
      date = widget.initialDate!;
    }
  }

  @override
  Widget build(BuildContext context) => DefaultTabController(
        length: 3,
        child: Scaffold(
          appBar: AppBar(
            title: const Text('टाचण'),
            actions: [
              PopupMenuButton<int>(
                icon: const Icon(Icons.more_vert),
                onSelected: (v) => Navigator.push(context, MaterialPageRoute(builder: (_) => [
                  const TachanWeeklyScreen(), const TachanAnnualScreen(), const TimetableScreen(), const HolidaysScreen(), const TachanPrefsScreen()][v])),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 0, child: ListTile(leading: Icon(Icons.view_week_outlined), title: Text('साप्ताहिक टाचण'))),
                  PopupMenuItem(value: 1, child: ListTile(leading: Icon(Icons.calendar_month_outlined), title: Text('वार्षिक नियोजन'))),
                  PopupMenuItem(value: 2, child: ListTile(leading: Icon(Icons.schedule_outlined), title: Text('वेळापत्रक'))),
                  PopupMenuItem(value: 3, child: ListTile(leading: Icon(Icons.event_available_outlined), title: Text('शालेय कामकाज कॅलेंडर'))),
                  PopupMenuItem(value: 4, child: ListTile(leading: Icon(Icons.tune), title: Text('टाचण सेटिंग्ज'))),
                ],
              ),
            ],
            bottom: const TabBar(tabs: [Tab(text: 'दैनिक टाचण'), Tab(text: 'नोंदी'), Tab(text: 'अहवाल / PDF')]),
          ),
          body: Column(children: [
            SizedBox(height: 52, child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.fromLTRB(10, 8, 10, 6), children: [
              for (final e in <(String, IconData, Widget Function())>[
                ('साप्ताहिक', Icons.view_week_outlined, () => const TachanWeeklyScreen()),
                ('वार्षिक नियोजन', Icons.calendar_month_outlined, () => const TachanAnnualScreen()),
                ('वेळापत्रक', Icons.schedule_outlined, () => const TimetableScreen()),
                ('शालेय कॅलेंडर', Icons.event_available_outlined, () => const HolidaysScreen()),
                ('सेटिंग्ज', Icons.tune, () => const TachanPrefsScreen()),
              ]) Padding(padding: const EdgeInsets.only(right: 8), child: ActionChip(
                avatar: Icon(e.$2, size: 17, color: const Color(0xFF6B4FB8)),
                label: Text(e.$1, style: const TextStyle(fontWeight: FontWeight.w700)),
                backgroundColor: const Color(0xFFF3EFFC),
                side: const BorderSide(color: Color(0xFFD8CCF5)),
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => e.$3())))),
            ])),
            Expanded(child: TabBarView(children: [_day(), _list(), _reports()])),
          ]),
        ),
      );

  Widget _classPick(List classes, [int current = 0]) {
    final items = [for (final c in classes.cast<Map>()) (asInt(c['id']), '${c['label'] ?? c['name']} ${c['section'] ?? ''}')];
    if (items.isEmpty) return const SizedBox.shrink();
    final want = classId > 0 ? classId : current;
    final v = items.any((e) => e.$1 == want) ? want : items.first.$1;
    return Pick<int>(label: 'वर्ग', value: v, items: items, onChanged: (x) => setState(() => classId = x ?? classId));
  }

  Widget _day() => ApiLoader(
        path: _api,
        query: {'action': 'day', 'date': date, if (classId > 0) 'class_id': '$classId', 'dl': 'full', 'upto': '8'},
        builder: (c, d, reload) {
          final acc = (d['access'] as Map?) ?? {};
          final isLocked = acc['allowed'] == false;
          final isTrial = acc['type'] == 'trial';

          if (isLocked) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Card(
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.lock_clock, size: 54, color: Color(0xFFE9A23B)),
                        const SizedBox(height: 12),
                        const Text('दैनिक टाचण वही (PRO)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
                        const SizedBox(height: 8),
                        Text(
                          acc['message'] ?? 'टाचण वहीसाठी सशुल्क योजना आवश्यक आहे.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 14),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          }

          final cls = (d['class'] as Map?) ?? {};
          final periods = (d['periods'] as List? ?? []).cast<Map>();
          final sv = (d['suvichar'] as Map?) ?? {};
          return ListView(padding: const EdgeInsets.all(12), children: [
            if (isTrial)
              Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFFBEB),
                  border: Border.all(color: const Color(0xFFFDE68A), width: 1.2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.timer_outlined, color: Color(0xFFD97706), size: 24),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('५-दिवसीय मोफत चाचणी (Free Trial) सुरू आहे!', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF92400E))),
                          Text(
                            'आपली चाचणी सुरू असून ${acc['days_left'] ?? 0} दिवस शिल्लक आहेत. अमर्यादित वापरासाठी कृपया योजना अपग्रेड करा.',
                            style: const TextStyle(fontSize: 11.5, color: Color(0xFFB45309)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            Row(children: [
              IconButton(onPressed: () => setState(() => date = '${d['prev']}'), icon: const Icon(Icons.chevron_left)),
              Expanded(
                child: InkWell(
                  onTap: () async {
                    final x = await showDatePicker(context: c, initialDate: DateTime.parse(date), firstDate: DateTime(2020), lastDate: DateTime(2035));
                    if (x != null) setState(() => date = _d(x));
                  },
                  child: Column(children: [
                    Text('${d['day_name'] ?? ''}', style: const TextStyle(color: Color(0xFF687A73))),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('${d['date_label'] ?? date}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                        if (date == _d(DateTime.now().add(const Duration(days: 1)))) ...[
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(color: const Color(0xFF6B4FB8), borderRadius: BorderRadius.circular(6)),
                            child: const Text('उद्या', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                          ),
                        ],
                      ],
                    ),
                  ]),
                ),
              ),
              IconButton(onPressed: () => setState(() => date = '${d['next']}'), icon: const Icon(Icons.chevron_right)),
            ]),
            Wrap(spacing: 8, alignment: WrapAlignment.center, children: [
              for (final t in (d['tabs'] as List? ?? []).cast<Map>())
                ChoiceChip(
                  label: Text('${t['label']}', style: TextStyle(fontWeight: t['date'] == date ? FontWeight.bold : FontWeight.normal)),
                  selected: t['date'] == date,
                  selectedColor: const Color(0xFFE8DEF8),
                  onSelected: (_) => setState(() => date = '${t['date']}'),
                ),
            ]),
            const SizedBox(height: 8),
            _classPick(d['classes'] as List? ?? [], asInt(d['class_id'])),
            const SizedBox(height: 8),
            if (d['closed'] == true)
              Card(color: const Color(0xFFFDE2E1), child: ListTile(leading: const Icon(Icons.beach_access), title: Text('सुट्टी: ${d['holiday_name'] ?? ''}'))),
            if ('${sv['text'] ?? ''}'.isNotEmpty)
              Card(
                color: const Color(0xFFEFF8F4),
                child: ListTile(leading: const Icon(Icons.format_quote), title: Text('${sv['text']}'), subtitle: Text('${sv['author'] ?? ''}')),
              ),
            if ((d['upakram'] as List? ?? []).isNotEmpty)
              Wrap(spacing: 6, runSpacing: 4, children: [for (final u in d['upakram'] as List) Chip(avatar: const Icon(Icons.event, size: 16), label: Text('$u'))]),
            if (d['paripath_time'] != null) ListTile(dense: true, leading: const Icon(Icons.wb_sunny_outlined), title: Text('परिपाठ: ${d['paripath_time']}')),
            for (final p in periods) _periodCard(c, p, cls, reload),
            const SizedBox(height: 10),
            if (d['print'] is Map)
              Wrap(spacing: 8, runSpacing: 8, children: [
                FilledButton.icon(
                  icon: const Icon(Icons.picture_as_pdf),
                  label: const Text('आजचे टाचण PDF / प्रिंट'),
                  onPressed: () {
                    final Map<String, dynamic> p = Map<String, dynamic>.from((d['print'] as Map?)?['params'] as Map? ?? {});
                    p['dl'] = 'full';
                    p['upto'] = 8;
                    if (classId > 0) p['class_id'] = '$classId';
                    p['date'] = date;
                    p['mode'] = '${d['mode'] ?? 'class'}';
                    Remote.printReport(c, 'modules/tachan/day_print.php', p, name: 'टाचण $date');
                  },
                ),
                OutlinedButton.icon(
                  icon: const Icon(Icons.calendar_month),
                  label: const Text('वेळापत्रक PDF'),
                  onPressed: () => Remote.printReport(c, 'modules/tachan/timetable_print.php', {'mode': '${d['mode'] ?? 'class'}', if (classId > 0) 'class_id': '$classId'}, name: 'वेळापत्रक'),
                ),
              ]),
          ]);
        },
      );

  Widget _periodCard(BuildContext c, Map p, Map cls, VoidCallback reload) {
    final plan = (p['plan'] as Map?) ?? {};
    final has = '${plan['topic'] ?? ''}'.isNotEmpty;
    return Card(
      child: ListTile(
        leading: CircleAvatar(child: Text('${p['period']}')),
        title: Text('${plan['subject'] ?? p['timetable_subject'] ?? 'विषय'} · ${p['time'] ?? ''}'),
        subtitle: Text(has ? '${plan['topic']}\n${plan['activities'] ?? ''}' : 'टाचण नाही — टॅप करून भरा', maxLines: 3, overflow: TextOverflow.ellipsis),
        isThreeLine: has,
        trailing: const Icon(Icons.edit_outlined),
        onTap: () async {
          final ok = await showModalBottomSheet<bool>(
            context: c,
            isScrollControlled: true,
            builder: (_) => PlanSheet(
              row: {
                'date': date,
                'period': p['period'],
                'class_id': plan['class_id'] ?? cls['id'],
                'class_name': plan['class_name'] ?? p['timetable_class'] ?? cls['name'],
                'subject': p['timetable_subject'],
                ...plan,
              },
              std: asInt(cls['std']),
            ),
          );
          if (ok == true) reload();
        },
      ),
    );
  }

  Widget _list() => ApiLoader(
        path: _api,
        query: {'action': 'list', 'month': '$month', 'year': '${DateTime.now().year}', if (classId > 0) 'class_id': '$classId'},
        builder: (c, d, reload) {
          final rows = (d['rows'] as List? ?? []).cast<Map>();
          return Column(children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: Pick<int>(
                label: 'महिना',
                value: month,
                items: [for (var m = 1; m <= 12; m++) (m, const ['जानेवारी', 'फेब्रुवारी', 'मार्च', 'एप्रिल', 'मे', 'जून', 'जुलै', 'ऑगस्ट', 'सप्टेंबर', 'ऑक्टोबर', 'नोव्हेंबर', 'डिसेंबर'][m - 1])],
                onChanged: (v) => setState(() => month = v ?? month),
              ),
            ),
            Expanded(
              child: rows.isEmpty
                  ? const Center(child: Text('या महिन्यात टाचण नोंदी नाहीत'))
                  : ListView.builder(
                      itemCount: rows.length,
                      itemBuilder: (_, i) {
                        final r = rows[i];
                        return ListTile(
                          leading: CircleAvatar(child: Text('${r['period'] ?? ''}')),
                          title: Text('${r['subject'] ?? ''} — ${r['topic'] ?? ''}'),
                          subtitle: Text('${r['date']} · ${r['class_name'] ?? ''}'),
                          onTap: () async {
                            final ok = await showModalBottomSheet<bool>(context: c, isScrollControlled: true, builder: (_) => PlanSheet(row: r, std: 0));
                            if (ok == true) reload();
                          },
                        );
                      },
                    ),
            ),
          ]);
        },
      );

  Widget _reports() => ApiLoader(
        path: _api,
        query: const {'action': 'dashboard'},
        builder: (c, d, reload) {
          final classes = (d['classes'] as List? ?? []).cast<Map>();
          final int curClass = classId > 0
              ? classId
              : (classes.isNotEmpty ? asInt(classes.first['id']) : 0);
          const months = [
            (1, 'जानेवारी'), (2, 'फेब्रुवारी'), (3, 'मार्च'), (4, 'एप्रिल'),
            (5, 'मे'), (6, 'जून'), (7, 'जुलै'), (8, 'ऑगस्ट'),
            (9, 'सप्टेंबर'), (10, 'ऑक्टोबर'), (11, 'नोव्हेंबर'), (12, 'डिसेंबर'),
          ];
          final curYear = DateTime.now().year;

          return ListView(
            padding: const EdgeInsets.all(12),
            children: [
              Card(
                color: const Color(0xFFF8FAFC),
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('अहवाल सेटिंग्ज व वर्ग निवड', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                      const SizedBox(height: 8),
                      _classPick(classes, curClass),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              icon: const Icon(Icons.calendar_today, size: 16),
                              label: Text('दिनांक: $date'),
                              onPressed: () async {
                                final x = await showDatePicker(
                                  context: c,
                                  initialDate: DateTime.tryParse(date) ?? DateTime.now(),
                                  firstDate: DateTime(2020),
                                  lastDate: DateTime(2035),
                                );
                                if (x != null) setState(() => date = _d(x));
                              },
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Pick<int>(
                              label: 'महिना',
                              value: month,
                              items: months,
                              onChanged: (v) => setState(() => month = v ?? month),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 4),
                child: Text('दैनिक व साप्ताहिक अहवाल', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1E293B))),
              ),
              ReportTile(
                title: 'दैनिक पाठ टाचण (A4 Single Page)',
                subtitle: 'निवडलेल्या दिनांकाचे ($date) तासिकानिहाय सविस्तर टाचण',
                page: 'modules/tachan/day_print.php',
                params: {
                  'mode': 'class',
                  if (curClass > 0) 'class_id': curClass,
                  'date': date,
                  'medium': 'marathi',
                  'dl': 'full',
                  'upto': 8,
                },
              ),
              ReportTile(
                title: 'साप्ताहिक टाचण (६ दिवस)',
                subtitle: '$date च्या आठवड्याचे (सोमवार ते शनिवार) ६ दिवसांचे एकत्रित नियोजन',
                page: 'modules/tachan/weekly_print.php',
                params: {
                  if (curClass > 0) 'class_id': curClass,
                  'date': date,
                  'medium': 'marathi',
                },
              ),
              ReportTile(
                title: 'महिन्याचे सर्व दैनिक टाचण (बहुपान)',
                subtitle: 'निवडलेल्या महिन्याचे सर्व दिवसांचे टाचण एकत्र प्रिंट',
                page: 'modules/tachan/day_print_bulk.php',
                params: {
                  'mode': 'class',
                  if (curClass > 0) 'class_id': curClass,
                  'month': month,
                  'year': curYear,
                  'medium': 'marathi',
                  'dl': 'full',
                  'upto': 8,
                },
              ),
              const SizedBox(height: 12),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 4),
                child: Text('नोंदवही, मासिक व वार्षिक अहवाल', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1E293B))),
              ),
              ReportTile(
                title: 'मासिक टाचण वही (नोंदवही)',
                subtitle: '${months[month - 1].$2} $curYear चा संपूर्ण महिना रजिस्टर (आडवे A4)',
                page: 'modules/tachan/monthly_print.php',
                params: {
                  if (curClass > 0) 'class_id': curClass,
                  'month': month,
                  'year': curYear,
                  'medium': 'marathi',
                },
              ),
              ReportTile(
                title: 'सलग दैनिक टाचण (बल्क प्रिंट)',
                subtitle: '${months[month - 1].$2} $curYear मधील सर्व कामाच्या दिवसांचे पाठ सलग A4 पानांवर',
                page: 'modules/tachan/day_print_bulk.php',
                params: {
                  if (curClass > 0) 'class_id': curClass,
                  'month': month,
                  'year': curYear,
                  'medium': 'marathi',
                  'dl': 'full',
                  'upto': 8,
                },
              ),
              ReportTile(
                title: 'वार्षिक नियोजन (संपूर्ण वर्ष)',
                subtitle: 'सर्व महिन्यांचे घटकनिहाय शासकीय वार्षिक नियोजन (All Year)',
                page: 'modules/tachan/annual_print.php',
                params: {
                  if (curClass > 0) 'class_id': curClass,
                  'month': 0,
                },
              ),
              const SizedBox(height: 12),
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 4),
                child: Text('वेळापत्रक अहवाल', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1E293B))),
              ),
              ReportTile(
                title: 'वर्ग वेळापत्रक',
                subtitle: 'निवडलेल्या वर्गाचे साप्ताहिक तासिका वेळापत्रक',
                page: 'modules/tachan/timetable_print.php',
                params: {
                  'mode': 'class',
                  if (curClass > 0) 'class_id': curClass,
                  'medium': 'marathi',
                },
              ),
              const ReportTile(
                title: 'शिक्षक वेळापत्रक',
                subtitle: 'माझे वैयक्तिक शिक्षक तासिका वेळापत्रक',
                page: 'modules/tachan/timetable_print.php',
                params: {
                  'mode': 'teacher',
                  'medium': 'marathi',
                },
              ),
              const SizedBox(height: 30),
            ],
          );
        },
      );
}

/// One period's tachan. Topic list comes from the portal's tachan bank and fills every field.
class PlanSheet extends StatefulWidget {
  const PlanSheet({super.key, required this.row, required this.std});
  final Map row;
  final int std;
  @override
  State<PlanSheet> createState() => _PlanSheetState();
}

class _PlanSheetState extends State<PlanSheet> {
  static const _f = [
    ('subject', 'विषय'), ('topic', 'पाठ / घटक'), ('objectives', 'उद्दिष्टे'), ('activities', 'अध्यापन कृती'), ('eval_tool', 'मूल्यमापन साधन'),
    ('materials', 'शैक्षणिक साहित्य'), ('learning_outcome', 'अध्ययन निष्पत्ती'), ('homework', 'गृहपाठ'), ('notes', 'शेरा'),
  ];
  late final Map<String, TextEditingController> c;
  bool busy = false;

  @override
  void initState() {
    super.initState();
    c = {for (final f in _f) f.$1: TextEditingController(text: '${widget.row[f.$1] ?? ''}')};
  }

  @override
  void dispose() {
    for (final ctrl in c.values) {
      ctrl.dispose();
    }
    super.dispose();
  }

  void _fill(Map b) => setState(() {
        c['topic']!.text = '${b['topic'] ?? ''}';
        c['activities']!.text = '${b['tl_activity'] ?? ''}';
        c['eval_tool']!.text = '${b['eval_tool'] ?? ''}';
        c['materials']!.text = '${b['materials'] ?? ''}';
        c['learning_outcome']!.text = '${b['learning_outcome'] ?? ''}';
        c['homework']!.text = '${b['homework'] ?? ''}';
        if (c['objectives']!.text.trim().isEmpty) c['objectives']!.text = '${b['learning_outcome'] ?? ''}';
      });

  static const _quickOpts = <String, List<String>>{
    'eval_tool': ['तोंडी काम', 'प्रात्यक्षिक', 'लेखी चाचणी', 'स्वाध्याय / प्रकल्प', 'निरीक्षण', 'गटकार्य', 'प्रश्नावली', 'मुक्त प्रश्न'],
    'materials': ['पाठ्यपुस्तक', 'चित्रे / चार्ट', 'शब्दकार्ड / अंककार्ड', 'FLN शैक्षणिक साहित्य', 'डिजिटल साधने / QR कोड', 'परिसरातील वस्तू / पाने-फुले', 'प्रयोग साहित्य'],
    'notes': ['सर्व विद्यार्थ्यांनी आनंदाने सहभाग घेतला.', 'अध्ययन निष्पत्ती साध्य झाली.', 'काही विद्यार्थ्यांना अधिक सरावाची गरज आहे.', 'उजळणी पाठ घेण्यात आला.'],
  };

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
        child: DraggableScrollableSheet(
          expand: false,
          initialChildSize: .9,
          builder: (_, sc) => ListView(controller: sc, padding: const EdgeInsets.all(16), children: [
            Text('तासिका ${widget.row['period'] ?? ''} · ${widget.row['date'] ?? ''}', style: Theme.of(context).textTheme.titleLarge),
            Text('${widget.row['class_name'] ?? ''}'),
            const SizedBox(height: 12),
            if (c['subject']!.text.trim().isEmpty) ...[
              const Text('त्वरित विषय निवडा:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF6B4FB8))),
              const SizedBox(height: 6),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  for (final sub in const ['मराठी', 'गणित', 'इंग्रजी', 'परिसर अभ्यास', 'हिंदी', 'सामान्य विज्ञान', 'इतिहास', 'भूगोल', 'खेळू,करू,शिकू'])
                    ActionChip(
                      avatar: const Icon(Icons.book_outlined, size: 14, color: Color(0xFF6B4FB8)),
                      label: Text(sub, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                      backgroundColor: const Color(0xFFF3EFFC),
                      side: const BorderSide(color: Color(0xFFD8CCF5)),
                      onPressed: () => setState(() => c['subject']!.text = sub),
                    ),
                ],
              ),
              const SizedBox(height: 12),
            ],
            if (c['subject']!.text.trim().isNotEmpty)
              SizedBox(
                height: 70,
                child: ApiLoader(
                  path: _api,
                  query: {'action': 'bank', 'std': '${widget.std > 0 ? widget.std : 4}', 'subject': c['subject']!.text.trim()},
                  builder: (_, d, __) {
                    final items = (d['items'] as List? ?? []).cast<Map>();
                    if (items.isEmpty) return const Text('या विषयासाठी टाचण बँकेत पाठ नाहीत');
                    return Pick<int>(
                      label: 'टाचण बँकेतून पाठ निवडा (सर्व आपोआप भरेल)',
                      value: -1,
                      items: [(-1, '— पाठ निवडा —'), for (var i = 0; i < items.length; i++) (i, _bankLabel(items[i]))],
                      onChanged: (v) => (v ?? -1) >= 0 ? _fill(items[v!]) : null,
                    );
                  },
                ),
              ),
            for (final f in _f) ...[
              if (_quickOpts.containsKey(f.$1)) ...[
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  value: null,
                  hint: Text('-- ${f.$2} निवडा --', style: const TextStyle(fontSize: 13)),
                  decoration: InputDecoration(labelText: 'तयार ${f.$2} निवडा', prefixIcon: const Icon(Icons.lightbulb_outline), border: const OutlineInputBorder(), isDense: true),
                  items: [for (final opt in _quickOpts[f.$1]!) DropdownMenuItem(value: opt, child: Text(opt, style: const TextStyle(fontSize: 13)))],
                  onChanged: (v) {
                    if (v != null) {
                      final cur = c[f.$1]!.text.trim();
                      setState(() => c[f.$1]!.text = cur.isEmpty ? v : '$cur, $v');
                    }
                  },
                ),
                const SizedBox(height: 6),
              ],
              TextField(controller: c[f.$1], maxLines: null, decoration: InputDecoration(labelText: f.$2, border: const OutlineInputBorder())),
              const SizedBox(height: 10),
            ],
            FilledButton.icon(
              icon: const Icon(Icons.save),
              label: const Text('टाचण जतन करा'),
              onPressed: busy
                  ? null
                  : () async {
                      setState(() => busy = true);
                      try {
                        final ok = await Remote.save(_api, {
                          'action': 'plan_save',
                          if (asInt(widget.row['id']) > 0) 'id': widget.row['id'],
                          'date': widget.row['date'],
                          'period': widget.row['period'],
                          'class_id': widget.row['class_id'] ?? 0,
                          'class_name': widget.row['class_name'] ?? '',
                          for (final e in c.entries) e.key: e.value.text.trim(),
                        });
                        if (!context.mounted) return;
                        toast(context, ok ? 'टाचण जतन झाले' : 'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.');
                        Navigator.pop(context, true);
                      } catch (e) {
                        if (context.mounted) toast(context, '$e', error: true);
                        setState(() => busy = false);
                      }
                    },
            ),
          ]),
        ),
      );
}

String _bankLabel(Map b) {
  final step = '${b['tl_activity'] ?? ''}'.split(RegExp(r'[:：]')).first.trim();
  final short = step.length > 40 ? '${step.substring(0, 40)}…' : step;
  return short.isEmpty ? '${b['topic']}' : '${b['topic']} — $short';
}
