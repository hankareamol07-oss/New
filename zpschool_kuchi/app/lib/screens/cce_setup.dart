// ignore_for_file: curly_braces_in_flow_control_structures
import 'package:flutter/material.dart';
import '../core/remote.dart';
import 'common.dart';
import 'widgets.dart';

const _api = 'cce_native.php';

Future<void> _run(BuildContext c, Future<bool> f, [VoidCallback? after]) async {
  try {
    final ok = await f;
    if (c.mounted) toast(c, ok ? 'जतन झाले' : 'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.');
    after?.call();
  } catch (e) {
    if (c.mounted) toast(c, '$e', error: true);
  }
}

String _mr(Object n) {
  const d = '०१२३४५६७८९';
  return '$n'.split('').map((ch) {
    final i = ch.codeUnitAt(0) - 48;
    return i >= 0 && i <= 9 ? d[i] : ch;
  }).join();
}

Map<String, String> _labels(dynamic v) {
  if (v is Map) return {for (final e in v.entries) '${e.key}': '${e.value}'};
  if (v is List) return {for (var i = 0; i < v.length; i++) if (v[i] != null) '$i': '${v[i]}'};
  return {};
}

/// Setup steps in the same order as the portal's CCE dashboard.
void openCceSetup(BuildContext context, int std, String division) {
  final steps = <(String, String, IconData, Widget Function())>[
    ('विषय', 'विषय, आकारिक व संकलित गुण, क्रम', Icons.menu_book_outlined, () => CceSubjectsScreen(std: std)),
    ('भारांश', 'आकारिक ८ व संकलित ३ साधनांचे गुण', Icons.balance_outlined, () => CceMarksConfigScreen(std: std)),
    ('शिक्षक नेमणूक', 'विषयनिहाय शिक्षक', Icons.person_pin_outlined, () => CceTeachersScreen(std: std, division: division)),
    ('उपस्थिती', 'महिनानिहाय उपस्थित दिवस', Icons.event_available_outlined, () => CceAttendanceScreen(std: std, division: division)),
    ('सेटिंग्ज', 'मुख्याध्यापक, निकाल तारीख, कामाचे दिवस', Icons.settings_outlined, () => const CceSettingsScreen()),
  ];
  showModalBottomSheet(
    context: context,
    showDragHandle: true,
    builder: (sc) => SafeArea(
      child: ListView(shrinkWrap: true, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
          child: Text('${stdLabel(std)} — सेटअप', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        ),
        for (var i = 0; i < steps.length; i++)
          ListTile(
            leading: CircleAvatar(radius: 16, child: Text(_mr(i + 1))),
            title: Text(steps[i].$1),
            subtitle: Text(steps[i].$2),
            trailing: Icon(steps[i].$3),
            onTap: () {
              Navigator.pop(sc);
              Navigator.push(context, MaterialPageRoute(builder: (_) => steps[i].$4()));
            },
          ),
      ]),
    ),
  );
}

class _NumBox extends StatelessWidget {
  const _NumBox({required this.ctl, this.label, this.width = 64, this.onChanged});
  final TextEditingController ctl;
  final String? label;
  final double width;
  final ValueChanged<String>? onChanged;
  @override
  Widget build(BuildContext context) => SizedBox(
        width: width,
        child: TextField(
          controller: ctl,
          keyboardType: TextInputType.number,
          textAlign: TextAlign.center,
          onChanged: onChanged,
          decoration: InputDecoration(
            labelText: label,
            isDense: true,
            border: const OutlineInputBorder(),
            contentPadding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
          ),
        ),
      );
}

Widget _saveBar(String label, VoidCallback? onPressed) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: SizedBox(
          width: double.infinity,
          child: FilledButton.icon(icon: const Icon(Icons.save), label: Text(label), onPressed: onPressed),
        ),
      ),
    );

Widget _sumChip(String t, int v, Color c) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: c, borderRadius: BorderRadius.circular(8)),
      child: Text('$t: ${_mr(v)}', style: const TextStyle(fontWeight: FontWeight.w700)),
    );

// ═══════ १. विषय (cce/subjects.php) ═════════════════════════════════════════
class CceSubjectsScreen extends StatefulWidget {
  const CceSubjectsScreen({super.key, required this.std});
  final int std;
  @override
  State<CceSubjectsScreen> createState() => _CceSubjectsState();
}

class _CceSubjectsState extends State<CceSubjectsScreen> {
  final _c = <String, TextEditingController>{};
  final _active = <int, bool>{};
  int _ver = 0;
  TextEditingController _t(int id, String k, dynamic v) => _c.putIfAbsent('$_ver/$id/$k', () => TextEditingController(text: v == null ? '' : '$v'));

  @override
  void dispose() {
    for (final c in _c.values) c.dispose();
    super.dispose();
  }

  void _reload(VoidCallback r) {
    setState(() => _ver++);
    r();
  }

  Future<void> _add(BuildContext c, VoidCallback reload) async {
    final name = TextEditingController(), short = TextEditingController();
    final fe = TextEditingController(text: '40'), se = TextEditingController(text: '60'), ord = TextEditingController(text: '99');
    final ok = await showDialog<bool>(
      context: c,
      builder: (d) => AlertDialog(
        title: const Text('नवीन विषय जोडा'),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(controller: name, decoration: const InputDecoration(labelText: 'विषयाचे नाव')),
            TextField(controller: short, decoration: const InputDecoration(labelText: 'लघुरूप')),
            const SizedBox(height: 10),
            Row(children: [
              Expanded(child: _NumBox(ctl: fe, label: 'आकारिक')),
              const SizedBox(width: 8),
              Expanded(child: _NumBox(ctl: se, label: 'संकलित')),
              const SizedBox(width: 8),
              Expanded(child: _NumBox(ctl: ord, label: 'क्रम')),
            ]),
          ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(d, false), child: const Text('रद्द करा')),
          FilledButton(onPressed: () => Navigator.pop(d, true), child: const Text('जोडा')),
        ],
      ),
    );
    if (ok != true || !c.mounted) return;
    if (name.text.trim().isEmpty) return toast(c, 'विषयाचे नाव आवश्यक', error: true);
    await _run(c, Remote.save(_api, {
      'action': 'subject_add', 'std': widget.std, 'name_mr': name.text.trim(), 'short_name': short.text.trim(),
      'fe_max': asInt(fe.text, 40), 'se_max': asInt(se.text, 60), 'sort_order': asInt(ord.text, 99),
    }), () => _reload(reload));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text('विषय — ${stdLabel(widget.std)}')),
        body: ApiLoader(
          path: _api,
          query: {'action': 'subjects_get', 'std': '${widget.std}'},
          builder: (c, d, reload) {
            final subs = (d['subjects'] as List?)?.cast<Map>() ?? [];
            final seed = OutlinedButton.icon(
              icon: const Icon(Icons.auto_awesome_outlined),
              label: const Text('डीफॉल्ट विषय जोडा'),
              onPressed: () => _run(c, Remote.save(_api, {'action': 'subjects_seed', 'std': widget.std}), () => _reload(reload)),
            );
            if (subs.isEmpty) {
              return Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                const EmptyState('या इयत्तेसाठी विषय नाहीत', icon: Icons.menu_book_outlined),
                seed,
                const SizedBox(height: 8),
                FilledButton.icon(icon: const Icon(Icons.add), label: const Text('विषय जोडा'), onPressed: () => _add(c, reload)),
              ]);
            }
            return Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                child: Row(children: [
                  Expanded(child: seed),
                  const SizedBox(width: 8),
                  Expanded(child: FilledButton.tonalIcon(icon: const Icon(Icons.add), label: const Text('जोडा'), onPressed: () => _add(c, reload))),
                ]),
              ),
              Expanded(
                child: ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: subs.length,
                  itemBuilder: (_, i) {
                    final s = subs[i];
                    final id = asInt(s['id']);
                    final on = _active[id] ?? asInt(s['is_active'], 1) == 1;
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: Column(children: [
                          Row(children: [
                            Expanded(child: TextField(controller: _t(id, 'name_mr', s['name_mr']), decoration: const InputDecoration(labelText: 'विषयाचे नाव', isDense: true))),
                            const SizedBox(width: 8),
                            SizedBox(width: 100, child: TextField(controller: _t(id, 'short_name', s['short_name']), decoration: const InputDecoration(labelText: 'लघुरूप', isDense: true))),
                          ]),
                          const SizedBox(height: 10),
                          Row(children: [
                            _NumBox(ctl: _t(id, 'fe_max', s['fe_max']), label: 'आकारिक गुण', width: 82),
                            const SizedBox(width: 6),
                            _NumBox(ctl: _t(id, 'se_max', s['se_max']), label: 'संकलित गुण', width: 82),
                            const SizedBox(width: 6),
                            _NumBox(ctl: _t(id, 'sort_order', s['sort_order']), label: 'क्रम', width: 52),
                            const Spacer(),
                            Column(children: [
                              const Text('सुरू', style: TextStyle(fontSize: 11)),
                              Switch(value: on, onChanged: (v) => setState(() => _active[id] = v)),
                            ]),
                            IconButton(
                              icon: const Icon(Icons.delete_outline, color: Colors.red),
                              onPressed: () async {
                                final yes = await showDialog<bool>(
                                  context: c,
                                  builder: (dc) => AlertDialog(
                                    title: const Text('विषय हटवायचा?'),
                                    content: Text('${s['name_mr']} हा विषय हटवला जाईल.'),
                                    actions: [
                                      TextButton(onPressed: () => Navigator.pop(dc, false), child: const Text('रद्द करा')),
                                      FilledButton(onPressed: () => Navigator.pop(dc, true), child: const Text('हटवा')),
                                    ],
                                  ),
                                );
                                if (yes == true && c.mounted) {
                                  await _run(c, Remote.save(_api, {'action': 'subject_delete', 'std': widget.std, 'id': id}), () => _reload(reload));
                                }
                              },
                            ),
                          ]),
                        ]),
                      ),
                    );
                  },
                ),
              ),
              _saveBar('जतन करा', () {
                final body = <String, dynamic>{};
                for (final s in subs) {
                  final id = asInt(s['id']);
                  body['$id'] = {
                    'name_mr': _t(id, 'name_mr', s['name_mr']).text.trim(),
                    'short_name': _t(id, 'short_name', s['short_name']).text.trim(),
                    'fe_max': asInt(_t(id, 'fe_max', s['fe_max']).text),
                    'se_max': asInt(_t(id, 'se_max', s['se_max']).text),
                    'sort_order': asInt(_t(id, 'sort_order', s['sort_order']).text, 99),
                    'is_active': (_active[id] ?? asInt(s['is_active'], 1) == 1) ? 1 : 0,
                  };
                }
                _run(c, Remote.save(_api, {'action': 'subjects_update', 'std': widget.std, 'subject': body}), () => _reload(reload));
              }),
            ]);
          },
        ),
      );
}

// ═══════ २. भारांश (cce/marks_config.php) ═══════════════════════════════════
class CceMarksConfigScreen extends StatefulWidget {
  const CceMarksConfigScreen({super.key, required this.std});
  final int std;
  @override
  State<CceMarksConfigScreen> createState() => _CceMarksConfigState();
}

class _CceMarksConfigState extends State<CceMarksConfigScreen> {
  final _c = <String, TextEditingController>{};
  TextEditingController _t(int id, String k, dynamic v) => _c.putIfAbsent('$id/$k', () => TextEditingController(text: v == null ? '' : '${asInt(v)}'));
  int _sum(int id, String p, int n, Map s) {
    var t = 0;
    for (var i = 1; i <= n; i++) t += asInt(_t(id, '$p$i', s['$p${i}_max']).text);
    return t;
  }

  @override
  void dispose() {
    for (final c in _c.values) c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text('भारांश — ${stdLabel(widget.std)}')),
        body: ApiLoader(
          path: _api,
          query: {'action': 'marks_config_get', 'std': '${widget.std}'},
          builder: (c, d, reload) {
            final subs = ((d['subjects'] as List?)?.cast<Map>() ?? []).where((s) => asInt(s['is_active'], 1) == 1).toList();
            final tl = d['technique_labels'] is Map ? d['technique_labels'] as Map : const {};
            final fe = _labels(tl['fe']), se = _labels(tl['se']);
            if (subs.isEmpty) return const EmptyState('आधी विषय जोडा', icon: Icons.menu_book_outlined);
            return Column(children: [
              Expanded(
                child: ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: subs.length,
                  itemBuilder: (_, i) {
                    final s = subs[i];
                    final id = asInt(s['id']);
                    final a = _sum(id, 'fe', 8, s), b = _sum(id, 'se', 3, s);
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${_mr(i + 1)}. ${s['name_mr']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                          const SizedBox(height: 8),
                          const Text('अ. आकारिक मूल्यमापन', style: TextStyle(color: Color(0xFFAD1457), fontWeight: FontWeight.w600)),
                          const SizedBox(height: 6),
                          Wrap(spacing: 6, runSpacing: 8, children: [
                            for (var k = 1; k <= 8; k++)
                              _NumBox(ctl: _t(id, 'fe$k', s['fe${k}_max']), label: fe['$k'] ?? 'साधन $k', width: 96, onChanged: (_) => setState(() {})),
                          ]),
                          const SizedBox(height: 10),
                          const Text('ब. संकलित मूल्यमापन', style: TextStyle(color: Color(0xFF1565C0), fontWeight: FontWeight.w600)),
                          const SizedBox(height: 6),
                          Wrap(spacing: 6, runSpacing: 8, children: [
                            for (var k = 1; k <= 3; k++)
                              _NumBox(ctl: _t(id, 'se$k', s['se${k}_max']), label: se['$k'] ?? 'साधन $k', width: 96, onChanged: (_) => setState(() {})),
                          ]),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 6, children: [
                            _sumChip('एकूण (अ)', a, const Color(0xFFF8BBD0)),
                            _sumChip('एकूण (ब)', b, const Color(0xFFBBDEFB)),
                            _sumChip('एकूण', a + b, const Color(0xFFE0E0E0)),
                          ]),
                        ]),
                      ),
                    );
                  },
                ),
              ),
              _saveBar('भारांश जतन करा', () {
                final body = <String, dynamic>{};
                for (final s in subs) {
                  final id = asInt(s['id']);
                  body['$id'] = {
                    for (var k = 1; k <= 8; k++) 'fe$k': _t(id, 'fe$k', s['fe${k}_max']).text.trim(),
                    for (var k = 1; k <= 3; k++) 'se$k': _t(id, 'se$k', s['se${k}_max']).text.trim(),
                  };
                }
                _run(c, Remote.save(_api, {'action': 'marks_config_save', 'std': widget.std, 'subject': body}));
              }),
            ]);
          },
        ),
      );
}

// ═══════ ३. शिक्षक नेमणूक (cce/teachers.php) ════════════════════════════════
class CceTeachersScreen extends StatefulWidget {
  const CceTeachersScreen({super.key, required this.std, this.division = ''});
  final int std;
  final String division;
  @override
  State<CceTeachersScreen> createState() => _CceTeachersState();
}

class _CceTeachersState extends State<CceTeachersScreen> {
  late String _div;
  final _sel = <int, int>{};
  String _loaded = '\u0000';

  @override
  void initState() {
    super.initState();
    _div = widget.division;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text('शिक्षक नेमणूक — ${stdLabel(widget.std)}')),
        body: ApiLoader(
          path: _api,
          query: {'action': 'teachers_get', 'std': '${widget.std}', 'division': _div},
          builder: (c, d, reload) {
            final teachers = (d['teachers'] as List?)?.cast<Map>() ?? [];
            final subs = ((d['subjects'] as List?)?.cast<Map>() ?? []).where((s) => asInt(s['is_active'], 1) == 1).toList();
            final divs = [for (final x in (d['divisions'] as List?) ?? []) '$x'];
            if (_loaded != _div) {
              _sel.clear();
              final m = d['mapping'];
              if (m is Map) m.forEach((k, v) => _sel[asInt(k)] = asInt(v));
              _loaded = _div;
            }
            final tItems = <(int, String)>[(0, '— निवडा —'), for (final t in teachers) (asInt(t['id']), '${t['name']}')];
            return Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 4),
                child: Pick<String>(
                  label: 'तुकडी',
                  value: _div,
                  items: [('', 'सर्व / तुकडी नाही'), for (final x in divs) if (x.isNotEmpty) (x, 'तुकडी $x')],
                  onChanged: (v) => setState(() => _div = v ?? ''),
                ),
              ),
              if (teachers.isEmpty) const Padding(padding: EdgeInsets.all(12), child: Text('शिक्षक यादी रिकामी आहे. आधी शिक्षक जोडा.')),
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.all(12),
                  itemCount: subs.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, i) {
                    final s = subs[i];
                    final id = asInt(s['id']);
                    return Pick<int>(label: '${_mr(i + 1)}. ${s['name_mr']}', value: _sel[id] ?? 0, items: tItems, onChanged: (v) => setState(() => _sel[id] = v ?? 0));
                  },
                ),
              ),
              _saveBar('जतन करा', () => _run(c, Remote.save(_api, {
                    'action': 'teachers_save', 'std': widget.std, 'division': _div,
                    'teacher': {for (final s in subs) '${asInt(s['id'])}': _sel[asInt(s['id'])] ?? 0},
                  }))),
            ]);
          },
        ),
      );
}

// ═══════ ४. उपस्थिती (cce/attendance.php) ═══════════════════════════════════
class CceAttendanceScreen extends StatefulWidget {
  const CceAttendanceScreen({super.key, required this.std, this.division = ''});
  final int std;
  final String division;
  @override
  State<CceAttendanceScreen> createState() => _CceAttendanceState();
}

class _CceAttendanceState extends State<CceAttendanceScreen> {
  int _month = 0;
  final _p = <String, TextEditingController>{};
  final _w = <int, TextEditingController>{};

  TextEditingController _pc(int sid, int m, Map s) => _p.putIfAbsent('$sid/$m', () {
        final a = s['attendance'];
        final r = a is Map ? a['$m'] : null;
        return TextEditingController(text: r is Map ? '${r['days_present'] ?? ''}' : '');
      });

  @override
  void dispose() {
    for (final c in [..._p.values, ..._w.values]) c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text('उपस्थिती — ${stdLabel(widget.std)}${widget.division.isEmpty ? '' : ' ${widget.division}'}')),
        body: ApiLoader(
          path: _api,
          query: {'action': 'attendance_get', 'std': '${widget.std}', if (widget.division.isNotEmpty) 'division': widget.division},
          builder: (c, d, reload) {
            final months = (d['months'] as List?)?.cast<Map>() ?? [];
            var students = (d['students'] as List?)?.cast<Map>() ?? [];
            if (widget.division.isNotEmpty) students = students.where((s) => '${s['section'] ?? ''}'.trim() == widget.division).toList();
            if (months.isEmpty) return const EmptyState('महिने उपलब्ध नाहीत');
            if (_month == 0 || !months.any((m) => asInt(m['month']) == _month)) _month = asInt(months.first['month']);
            for (final m in months) {
              _w.putIfAbsent(asInt(m['month']), () => TextEditingController(text: '${asInt(m['working_days'])}'));
            }
            final wd = _w[_month]!;
            void allMonths(int n) => setState(() {
                  for (final c in _w.values) c.text = '$n';
                });
            return Column(children: [
              SizedBox(
                height: 48,
                child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), children: [
                  for (final m in months)
                    Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: ChoiceChip(label: Text('${m['label']}'), selected: asInt(m['month']) == _month, onSelected: (_) => setState(() => _month = asInt(m['month']))),
                    ),
                ]),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 4),
                child: Row(children: [
                  const Text('कामाचे दिवस: '),
                  _NumBox(ctl: wd, width: 60, onChanged: (_) => setState(() {})),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Wrap(spacing: 4, children: [
                      for (final n in [22, 24, 25]) ActionChip(label: Text('सर्व महिने ${_mr(n)}'), onPressed: () => allMonths(n)),
                    ]),
                  ),
                ]),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    icon: const Icon(Icons.done_all),
                    label: const Text('सर्वांना पूर्ण उपस्थिती'),
                    onPressed: () => setState(() {
                      for (final s in students) _pc(asInt(s['id']), _month, s).text = wd.text;
                    }),
                  ),
                ),
              ),
              if (students.isEmpty) const Expanded(child: EmptyState('विद्यार्थी नाहीत', icon: Icons.groups_outlined)),
              if (students.isNotEmpty)
                Expanded(
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                    itemCount: students.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (_, i) {
                      final s = students[i];
                      return ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: CircleAvatar(radius: 14, child: Text('${s['roll_no'] ?? i + 1}', style: const TextStyle(fontSize: 11))),
                        title: Text(studentLabel(s)),
                        trailing: _NumBox(ctl: _pc(asInt(s['id']), _month, s), width: 64),
                      );
                    },
                  ),
                ),
              _saveBar('उपस्थिती जतन करा', () {
                final p = <String, Map<String, int>>{};
                for (final e in _p.entries) {
                  final t = e.value.text.trim();
                  if (t.isEmpty) continue;
                  final parts = e.key.split('/');
                  final m = asInt(parts[1]);
                  final v = asInt(t);
                  final max = asInt(_w[m]?.text);
                  if (max > 0 && v > max) {
                    toast(c, 'उपस्थित दिवस कामाच्या दिवसांपेक्षा जास्त आहेत', error: true);
                    return;
                  }
                  (p[parts[0]] ??= {})['$m'] = v;
                }
                _run(c, Remote.save(_api, {
                  'action': 'attendance_save', 'std': widget.std,
                  if (widget.division.isNotEmpty) 'division': widget.division,
                  'working': {for (final e in _w.entries) '${e.key}': asInt(e.value.text)},
                  'p': p,
                }));
              }),
            ]);
          },
        ),
      );
}

// ═══════ ५. सेटिंग्ज (cce/settings.php) ═════════════════════════════════════
class CceSettingsScreen extends StatefulWidget {
  const CceSettingsScreen({super.key});
  @override
  State<CceSettingsScreen> createState() => _CceSettingsState();
}

class _CceSettingsState extends State<CceSettingsScreen> {
  TextEditingController? _h1, _h2, _date, _notice;
  final _w = <int, TextEditingController>{};

  @override
  void dispose() {
    for (final c in [_h1, _h2, _date, _notice, ..._w.values]) c?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('शैक्षणिक वर्ष व स्वाक्षरी सेटिंग्ज')),
        body: ApiLoader(
          path: _api,
          query: const {'action': 'settings_get'},
          builder: (c, d, reload) {
            _h1 ??= TextEditingController(text: '${d['sem1_headmaster_name'] ?? ''}');
            _h2 ??= TextEditingController(text: '${d['sem2_headmaster_name'] ?? ''}');
            _date ??= TextEditingController(text: '${d['result_date'] ?? ''}');
            _notice ??= TextEditingController(text: '${d['next_year_notice'] ?? ''}');
            final months = (d['months'] as List?)?.cast<Map>() ?? [];
            for (final m in months) {
              _w.putIfAbsent(asInt(m['month']), () => TextEditingController(text: '${asInt(m['working_days'])}'));
            }
            return Column(children: [
              Expanded(
                child: ListView(padding: const EdgeInsets.all(14), children: [
                  if ('${d['academic_year'] ?? ''}'.isNotEmpty)
                    Padding(padding: const EdgeInsets.only(bottom: 10), child: Text('शैक्षणिक वर्ष: ${d['academic_year']}', style: const TextStyle(fontWeight: FontWeight.w600))),
                  TextField(controller: _h1, decoration: const InputDecoration(labelText: 'मुख्याध्यापक नाव (प्रथम सत्र):', hintText: 'उदा. श्री. अ. ब. पाटील', border: OutlineInputBorder())),
                  const SizedBox(height: 12),
                  TextField(controller: _h2, decoration: const InputDecoration(labelText: 'मुख्याध्यापक नाव (द्वितीय सत्र):', hintText: 'उदा. श्री. अ. ब. पाटील', border: OutlineInputBorder())),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _date,
                    readOnly: true,
                    decoration: const InputDecoration(labelText: 'वार्षिक निकाल जाहीर तारीख:', border: OutlineInputBorder(), suffixIcon: Icon(Icons.calendar_month)),
                    onTap: () async {
                      final now = DateTime.now();
                      final p = await showDatePicker(context: c, initialDate: DateTime.tryParse(_date!.text) ?? now, firstDate: DateTime(now.year - 3), lastDate: DateTime(now.year + 3));
                      if (p != null) setState(() => _date!.text = '${p.year}-${'${p.month}'.padLeft(2, '0')}-${'${p.day}'.padLeft(2, '0')}');
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(controller: _notice, maxLines: 3, decoration: const InputDecoration(labelText: 'पुढील शैक्षणिक वर्ष सुरू होण्याची सूचना / सुट्टी संपण्याची तारीख:', hintText: 'उदा. नवीन शैक्षणिक वर्ष दिनांक १५ जून पासून सुरू होईल.', border: OutlineInputBorder(), alignLabelWithHint: true)),
                  if (months.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    Row(children: [
                      const Expanded(child: Text('महिनानिहाय कामाचे दिवस', style: TextStyle(fontWeight: FontWeight.w700))),
                      for (final n in [22, 24, 25])
                        Padding(
                          padding: const EdgeInsets.only(left: 4),
                          child: ActionChip(label: Text('${_mr(n)} दिवस'), onPressed: () => setState(() { for (final w in _w.values) w.text = '$n'; })),
                        ),
                    ]),
                    const SizedBox(height: 8),
                    Wrap(spacing: 8, runSpacing: 10, children: [
                      for (final m in months) _NumBox(ctl: _w[asInt(m['month'])]!, label: '${m['label']}', width: 92),
                    ]),
                  ],
                ]),
              ),
              _saveBar('जतन करा', () => _run(c, Remote.save(_api, {
                    'action': 'settings_save',
                    'sem1_headmaster_name': _h1!.text.trim(),
                    'sem2_headmaster_name': _h2!.text.trim(),
                    'result_date': _date!.text.trim(),
                    'next_year_notice': _notice!.text.trim(),
                    'working': {for (final e in _w.entries) '${e.key}': asInt(e.value.text)},
                  }))),
            ]);
          },
        ),
      );
}
