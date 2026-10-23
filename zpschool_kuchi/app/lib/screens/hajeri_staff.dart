import 'package:flutter/material.dart';

import '../core/remote.dart';
import 'common.dart';
import 'widgets.dart';

const _api = 'hajeri_native.php';
const _st = [('P', 'उपस्थित', Color(0xFF059669)), ('A', 'गैरहजर', Color(0xFFB3261E)), ('L', 'रजा', Color(0xFFB45309)), ('H', 'सुट्टी', Color(0xFF2563A8))];

class StaffAttendanceScreen extends StatefulWidget {
  const StaffAttendanceScreen({super.key});
  @override State<StaffAttendanceScreen> createState() => _StaffAttendanceState();
}
class _StaffAttendanceState extends State<StaffAttendanceScreen> {
  String date = DateTime.now().toIso8601String().substring(0, 10);
  final Map<int, String> _s = {};
  bool saving = false;

  Future<void> _save(List<Map> t, VoidCallback reload) async {
    setState(() => saving = true);
    try {
      await Remote.save(_api, {'action': 'staff_save', 'date': date, 'rows': [for (final x in t) {'teacher_id': x['id'], 'status': _s[asInt(x['id'])] ?? '${x['status'] ?? 'P'}'}]});
      if (mounted) toast(context, 'कर्मचारी हजेरी जतन झाली');
      reload();
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => saving = false); }
  }

  Future<void> _monthPrint() async {
    final m = date.substring(0, 7);
    try {
      final d = await Remote.get(_api, {'action': 'staff_month', 'month': m});
      final n = asInt(d['days_in_month'], 30);
      final t = ((d['teachers'] as List?) ?? const []).cast<Map>();
      String esc(Object? v) => '${v ?? ''}'.replaceAll('&', '&amp;').replaceAll('<', '&lt;');
      final b = StringBuffer('<html><head><meta charset="utf-8"><style>body{font-family:sans-serif;font-size:10px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #444;padding:2px;text-align:center}td.n{text-align:left}</style></head><body>')
        ..write('<h3 style="text-align:center">कर्मचारी मासिक हजेरी — $m</h3><table><tr><th>क्र.</th><th>नाव</th>');
      for (var i = 1; i <= n; i++) { b.write('<th>$i</th>'); }
      b.write('<th>उप.</th><th>गैर.</th><th>रजा</th></tr>');
      for (var k = 0; k < t.length; k++) {
        final days = (t[k]['days'] as Map?) ?? const {};
        b.write('<tr><td>${k + 1}</td><td class="n">${esc(t[k]['name_mr'] ?? t[k]['name'])}</td>');
        for (var i = 1; i <= n; i++) { final key = '$m-${i.toString().padLeft(2, '0')}'; b.write('<td>${esc(days[key] ?? days['$i'] ?? '')}</td>'); }
        b.write('<td>${t[k]['present'] ?? 0}</td><td>${t[k]['absent'] ?? 0}</td><td>${t[k]['leave'] ?? 0}</td></tr>');
      }
      b.write('</table><p>P = उपस्थित, A = गैरहजर, L = रजा, H = सुट्टी</p><br><p style="text-align:right">मुख्याध्यापक</p></body></html>');
      if (mounted) await Remote.printHtml(context, b.toString(), name: 'कर्मचारी हजेरी $m', landscape: true);
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('कर्मचारी हजेरी'), actions: [IconButton(icon: const Icon(Icons.print_outlined), tooltip: 'मासिक अहवाल', onPressed: _monthPrint)]),
    body: ApiLoader(key: ValueKey(date), path: _api, query: {'action': 'staff', 'date': date}, builder: (c, d, reload) {
      final t = ((d['teachers'] as List?) ?? const []).cast<Map>();
      return Column(children: [
        Padding(padding: const EdgeInsets.fromLTRB(12, 10, 12, 4), child: Row(children: [
          Expanded(child: OutlinedButton.icon(icon: const Icon(Icons.event), label: Text(date), onPressed: () async {
            final p = await showDatePicker(context: context, initialDate: DateTime.parse(date), firstDate: DateTime(2020), lastDate: DateTime.now());
            if (p != null) setState(() { date = p.toIso8601String().substring(0, 10); _s.clear(); });
          })),
          const SizedBox(width: 8),
          TextButton(onPressed: () => setState(() { for (final x in t) { _s[asInt(x['id'])] = 'P'; } }), child: const Text('सर्व उपस्थित')),
        ])),
        Expanded(child: t.isEmpty ? const EmptyState('कर्मचारी नोंदलेले नाहीत', icon: Icons.badge_outlined) : ListView(padding: const EdgeInsets.fromLTRB(12, 4, 12, 90), children: [
          for (final x in t) Card(child: Padding(padding: const EdgeInsets.fromLTRB(12, 8, 8, 8), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text('${x['name_mr'] ?? x['name']}', style: const TextStyle(fontWeight: FontWeight.w700))),
              if (x['marked'] != true && !_s.containsKey(asInt(x['id']))) const Text('नोंद नाही', style: TextStyle(fontSize: 11, color: Colors.black45)),
            ]),
            const SizedBox(height: 6),
            Wrap(spacing: 6, children: [for (final s in _st) ChoiceChip(
              label: Text(s.$2), selectedColor: s.$3.withValues(alpha: .18),
              labelStyle: TextStyle(color: s.$3, fontWeight: FontWeight.w700),
              selected: (_s[asInt(x['id'])] ?? '${x['status'] ?? 'P'}') == s.$1,
              onSelected: (_) => setState(() => _s[asInt(x['id'])] = s.$1))]),
          ]))),
        ])),
        SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: SizedBox(width: double.infinity, child: FilledButton.icon(
          onPressed: saving || t.isEmpty ? null : () => _save(t, reload), icon: const Icon(Icons.save_outlined), label: const Text('हजेरी जतन करा'))))),
      ]);
    }),
  );
}
