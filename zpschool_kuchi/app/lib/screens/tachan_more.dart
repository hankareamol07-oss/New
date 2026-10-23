import 'package:flutter/material.dart';

import '../theme.dart';
import '../core/remote.dart';
import 'common.dart';
import 'tachan.dart';
import 'widgets.dart';

const _api = 'tachan_native.php';

String _ymd(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

List<(int, String)> _classItems(List cls) =>
    [for (final c in cls.cast<Map>()) (asInt(c['id']), '${c['label'] ?? c['name']} ${c['section'] ?? ''}'.trim())];

// ─── साप्ताहिक टाचण ───────────────────────────────────────────────────────────
class TachanWeeklyScreen extends StatefulWidget {
  const TachanWeeklyScreen({super.key});
  @override
  State<TachanWeeklyScreen> createState() => _TachanWeeklyState();
}

class _TachanWeeklyState extends State<TachanWeeklyScreen> {
  String date = _ymd(DateTime.now());
  int classId = 0;
  String medium = '';

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('साप्ताहिक टाचण')),
        body: ApiLoader(
          path: _api,
          query: {'action': 'weekly', 'date': date, if (classId > 0) 'class_id': '$classId', if (medium.isNotEmpty) 'medium': medium},
          builder: (c, d, reload) {
            final cls = (d['class'] as Map?) ?? {};
            final days = (d['days'] as List? ?? []).cast<Map>();
            final mediums = (d['mediums'] as Map?) ?? {};
            return Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                child: Row(children: [
                  Expanded(child: Pick<int>(dense: true, label: 'वर्ग', value: asInt(cls['id']), items: _classItems(d['classes'] as List? ?? []), onChanged: (v) => setState(() => classId = v ?? classId))),
                  const SizedBox(width: 8),
                  Expanded(child: Pick<String>(dense: true, label: 'माध्यम', value: '${d['medium'] ?? ''}', items: [for (final e in mediums.entries) ('${e.key}', '${e.value}')], onChanged: (v) => setState(() => medium = v ?? medium))),
                ]),
              ),
              Row(children: [
                IconButton(onPressed: () => setState(() => date = '${d['prev']}'), icon: const Icon(Icons.chevron_left)),
                Expanded(child: TextButton(
                  onPressed: () async {
                    final x = await showDatePicker(context: c, initialDate: DateTime.tryParse(date) ?? DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2035));
                    if (x != null) setState(() => date = _ymd(x));
                  },
                  child: Text('आठवडा: ${days.isNotEmpty ? days.first['date_label'] : ''} – ${days.isNotEmpty ? days.last['date_label'] : ''}', style: const TextStyle(fontWeight: FontWeight.w700)))),
                IconButton(onPressed: () => setState(() => date = '${d['next']}'), icon: const Icon(Icons.chevron_right)),
              ]),
              Expanded(child: ListView(padding: const EdgeInsets.fromLTRB(12, 0, 12, 80), children: [
                for (final day in days) ...[
                  Padding(padding: const EdgeInsets.fromLTRB(4, 10, 4, 4),
                      child: Text('${day['day_name']} · ${day['date_label']}', style: const TextStyle(fontWeight: FontWeight.w800, color: TG.g800))),
                  if ('${day['holiday'] ?? ''}'.isNotEmpty)
                    Card(color: const Color(0xFFFDE2E1), child: ListTile(dense: true, leading: const Icon(Icons.beach_access), title: Text('सुट्टी: ${day['holiday']}')))
                  else
                    for (final r in (day['rows'] as List? ?? []).cast<Map>())
                      Card(child: ListTile(
                        dense: true,
                        leading: CircleAvatar(radius: 14, child: Text('${r['period']}', style: const TextStyle(fontSize: 12))),
                        title: Text('${r['subject'] ?? ''} — ${r['topic'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis),
                        subtitle: Text('${r['activities'] ?? ''}', maxLines: 2, overflow: TextOverflow.ellipsis),
                        trailing: const Icon(Icons.edit_outlined, size: 18),
                        onTap: () async {
                          final ok = await showModalBottomSheet<bool>(context: c, isScrollControlled: true,
                              builder: (_) => PlanSheet(row: {'date': day['date'], 'class_id': cls['id'], 'class_name': cls['name'], ...r}, std: asInt(cls['std'])));
                          if (ok == true) reload();
                        },
                      )),
                ],
              ])),
              if (d['print'] is Map)
                SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: SizedBox(width: double.infinity, child: FilledButton.icon(
                  icon: const Icon(Icons.picture_as_pdf),
                  label: const Text('साप्ताहिक टाचण PDF / प्रिंट'),
                  onPressed: () => Remote.printReport(c, '${d['print']['page']}', ((d['print']['params'] as Map?) ?? {}).cast<String, dynamic>(), name: 'साप्ताहिक टाचण'))))),
            ]);
          },
        ),
      );
}

// ─── वार्षिक नियोजन ───────────────────────────────────────────────────────────
class TachanAnnualScreen extends StatefulWidget {
  const TachanAnnualScreen({super.key});
  @override
  State<TachanAnnualScreen> createState() => _TachanAnnualState();
}

class _TachanAnnualState extends State<TachanAnnualScreen> {
  int classId = 0, month = 0;
  final Map<String, List<TextEditingController>> _w = {};
  String _key = '';
  bool _saving = false;

  @override
  void dispose() {
    for (final l in _w.values) {
      for (final c in l) {
        c.dispose();
      }
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('वार्षिक नियोजन')),
        body: ApiLoader(
          path: _api,
          query: {'action': 'annual', if (classId > 0) 'class_id': '$classId', if (month > 0) 'month': '$month'},
          builder: (c, d, reload) {
            final cls = (d['class'] as Map?) ?? {};
            final m = asInt(d['month']);
            final subjects = (d['subjects'] as List? ?? []).cast<Map>();
            final key = '${cls['id']}/$m/${subjects.length}';
            if (key != _key) {
              for (final l in _w.values) {
                for (final x in l) {
                  x.dispose();
                }
              }
              _w.clear();
              for (final s in subjects) {
                final mm = ((s['months'] as List? ?? []).cast<Map>()).firstWhere((x) => asInt(x['month']) == m, orElse: () => {});
                final wk = (mm['weeks'] as List? ?? []);
                _w['${s['subject']}'] = [for (var i = 0; i < 4; i++) TextEditingController(text: i < wk.length ? '${wk[i] ?? ''}' : '')];
              }
              _key = key;
            }
            return Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
                child: Row(children: [
                  Expanded(child: Pick<int>(dense: true, label: 'वर्ग', value: asInt(cls['id']), items: _classItems(d['classes'] as List? ?? []), onChanged: (v) => setState(() => classId = v ?? classId))),
                  const SizedBox(width: 8),
                  Expanded(child: Pick<int>(dense: true, label: 'महिना', value: m, items: [for (final x in (d['months'] as List? ?? []).cast<Map>()) (asInt(x['month']), '${x['name']}')], onChanged: (v) => setState(() => month = v ?? month))),
                ]),
              ),
              Expanded(child: ListView(padding: const EdgeInsets.fromLTRB(12, 4, 12, 12), children: [
                if (subjects.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Center(child: Text('या महिन्यासाठी विषय नाहीत. खाली विषय जोडा.'))),
                for (final s in subjects)
                  Card(child: Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${s['display'] ?? s['subject']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    for (var i = 0; i < 4; i++)
                      Padding(padding: const EdgeInsets.only(bottom: 6), child: TextField(controller: _w['${s['subject']}']![i], maxLines: null,
                          decoration: InputDecoration(labelText: 'आठवडा ${'१२३४'[i]}', border: const OutlineInputBorder(), isDense: true))),
                  ]))),
                OutlinedButton.icon(icon: const Icon(Icons.add), label: const Text('विषय जोडा'), onPressed: () => _addSubject(c, d, asInt(cls['id']), m, reload)),
              ])),
              SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: Row(children: [
                Expanded(child: FilledButton.icon(
                  icon: _saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.save),
                  label: const Text('नियोजन जतन करा'),
                  onPressed: _saving || subjects.isEmpty ? null : () async {
                    setState(() => _saving = true);
                    try {
                      await Remote.save(_api, {'action': 'annual_save_weeks', 'class_id': asInt(cls['id']), 'month': m,
                        'w': {for (final e in _w.entries) e.key: [for (final x in e.value) x.text.trim()]}});
                      if (c.mounted) toast(c, 'नियोजन जतन झाले');
                      _key = '';
                      reload();
                    } catch (e) {
                      if (c.mounted) toast(c, '$e', error: true);
                    } finally {
                      if (mounted) setState(() => _saving = false);
                    }
                  })),
                const SizedBox(width: 8),
                if (d['print'] is Map)
                  IconButton.filledTonal(icon: const Icon(Icons.picture_as_pdf), tooltip: 'PDF / प्रिंट',
                      onPressed: () => Remote.printReport(c, '${d['print']['page']}', ((d['print']['params'] as Map?) ?? {}).cast<String, dynamic>(), name: 'वार्षिक नियोजन')),
              ]))),
            ]);
          },
        ),
      );

  Future<void> _addSubject(BuildContext c, Map d, int cid, int m, VoidCallback reload) async {
    final sug = [for (final s in (d['suggested_subjects'] as List? ?? [])) '$s'];
    final ctl = TextEditingController();
    final sub = await showDialog<String>(context: c, builder: (dc) => AlertDialog(
      title: const Text('विषय जोडा'),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        TextField(controller: ctl, decoration: const InputDecoration(labelText: 'विषय', border: OutlineInputBorder())),
        const SizedBox(height: 8),
        Wrap(spacing: 6, runSpacing: 6, children: [for (final s in sug) ActionChip(label: Text(s), onPressed: () => ctl.text = s)]),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(dc), child: const Text('रद्द')),
        FilledButton(onPressed: () => Navigator.pop(dc, ctl.text.trim()), child: const Text('जोडा')),
      ],
    ));
    ctl.dispose();
    if (sub == null || sub.isEmpty) return;
    try {
      await Remote.save(_api, {'action': 'annual_add_subject', 'class_id': cid, 'month': m, 'subject': sub, 'mode': 'month'});
      _key = '';
      reload();
    } catch (e) {
      if (c.mounted) toast(c, '$e', error: true);
    }
  }
}

// ─── वर्ग पसंती (टाचण सेटिंग्ज) ───────────────────────────────────────────────
class TachanPrefsScreen extends StatefulWidget {
  const TachanPrefsScreen({super.key});
  @override
  State<TachanPrefsScreen> createState() => _TachanPrefsState();
}

class _TachanPrefsState extends State<TachanPrefsScreen> {
  Map<String, dynamic>? _p;
  bool _saving = false;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('टाचण सेटिंग्ज')),
        body: ApiLoader(
          path: _api,
          query: const {'action': 'prefs_get'},
          builder: (c, d, reload) {
            _p ??= Map<String, dynamic>.from((d['prefs'] as Map?) ?? {});
            final p = _p!;
            final classes = _classItems(d['classes'] as List? ?? []);
            final types = (d['types'] as List? ?? []).cast<Map>();
            final mediums = (d['mediums'] as Map?) ?? {};
            final lengths = (d['lengths'] as Map?) ?? {};
            final periods = (d['periods'] as List? ?? []).cast<Map>();
            return ListView(padding: const EdgeInsets.all(12), children: [
              const SectionTitle('शिकवण्याचा प्रकार'),
              for (final t in types)
                RadioListTile<String>(value: '${t['key']}', groupValue: '${p['teach_type']}', title: Text('${t['title']}'), subtitle: Text('${t['desc'] ?? ''}', style: const TextStyle(fontSize: 12)),
                    onChanged: (v) => setState(() => p['teach_type'] = v)),
              const SizedBox(height: 8),
              Pick<int>(label: 'मुख्य वर्ग', value: asInt(p['class_id']), items: classes, onChanged: (v) => setState(() => p['class_id'] = v)),
              if ('${p['teach_type']}' != 'single') ...[
                const SizedBox(height: 8),
                Pick<int>(label: 'जोड वर्ग', value: asInt(p['jod_class_id']), items: [(0, '—'), ...classes], onChanged: (v) => setState(() => p['jod_class_id'] = v)),
              ],
              const SizedBox(height: 8),
              Pick<String>(label: 'माध्यम', value: '${p['medium']}', items: [for (final e in mediums.entries) ('${e.key}', '${e.value}')], onChanged: (v) => setState(() => p['medium'] = v)),
              const SizedBox(height: 8),
              Pick<String>(label: 'दिवसाची लांबी', value: '${p['day_length']}', items: [for (final e in lengths.entries) ('${e.key}', '${e.value}')], onChanged: (v) => setState(() => p['day_length'] = v)),
              if ('${p['day_length']}' == 'upto') ...[
                const SizedBox(height: 8),
                Pick<int>(
                  label: 'तासिका क्रमांकापर्यंत',
                  value: asInt(p['upto_period']) >= 4 ? asInt(p['upto_period']) : 8,
                  items: [for (final x in periods) if (asInt(x['period']) >= 4) (asInt(x['period']), 'तासिका ${x['period']} (${x['time']})')],
                  onChanged: (v) => setState(() => p['upto_period'] = v ?? 8),
                ),
              ],
              const SizedBox(height: 16),
              FilledButton.icon(
                icon: const Icon(Icons.save),
                label: const Text('जतन करा'),
                onPressed: _saving ? null : () async {
                  setState(() => _saving = true);
                  try {
                    await Remote.save(_api, {'action': 'prefs_save', ...p});
                    if (c.mounted) toast(c, 'सेटिंग्ज जतन झाल्या');
                  } catch (e) {
                    if (c.mounted) toast(c, '$e', error: true);
                  } finally {
                    if (mounted) setState(() => _saving = false);
                  }
                }),
            ]);
          },
        ),
      );
}
