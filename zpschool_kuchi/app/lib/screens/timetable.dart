import 'package:flutter/material.dart';

import '../theme.dart';
import '../core/remote.dart';
import 'common.dart';
import 'widgets.dart';

class TimetableScreen extends StatefulWidget {
  const TimetableScreen({super.key});
  @override
  State<TimetableScreen> createState() => _TimetableScreenState();
}

class _TimetableScreenState extends State<TimetableScreen> {
  String mode = 'class', medium = '';
  int classId = 0;
  final Map<String, String> _sub = {}, _cls = {};
  String _key = '';
  bool _saving = false;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('वेळापत्रक')),
        body: ApiLoader(
          path: 'tachan_native.php',
          query: {'action': 'timetable', 'mode': mode, if (classId > 0 && mode == 'class') 'class_id': '$classId', if (medium.isNotEmpty) 'medium': medium},
          builder: (c, d, reload) {
            final days = (d['days'] as List? ?? []).cast<Map>();
            final cls = (d['class'] as Map?) ?? {};
            final key = '$mode/${cls['id']}/${d['medium']}';
            if (key != _key) {
              _sub.clear();
              _cls.clear();
              for (final x in (d['cells'] as List? ?? []).cast<Map>()) {
                _sub['${x['day']}-${x['period']}'] = '${x['subject'] ?? ''}';
                if (x['class_info'] != null) _cls['${x['day']}-${x['period']}'] = '${x['class_info']}';
              }
              _key = key;
            }
            final subjects = <String>{for (final s in (d['subjects'] as List? ?? [])) '$s', ..._sub.values.where((v) => v.isNotEmpty)}.toList();
            final choices = (d['class_choices'] as List? ?? []).cast<Map>();
            final mediums = (d['mediums'] as Map?) ?? {};
            return Column(children: [
              Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 4), child: SegmentedButton<String>(
                segments: const [ButtonSegment(value: 'class', label: Text('वर्ग वेळापत्रक')), ButtonSegment(value: 'teacher', label: Text('माझे वेळापत्रक'))],
                selected: {mode},
                onSelectionChanged: (v) => setState(() => mode = v.first),
              )),
              Padding(padding: const EdgeInsets.fromLTRB(12, 4, 12, 4), child: Row(children: [
                if (mode == 'class') ...[
                  Expanded(child: Pick<int>(dense: true, label: 'वर्ग', value: asInt(cls['id']),
                      items: [for (final x in (d['classes'] as List? ?? []).cast<Map>()) (asInt(x['id']), '${x['label'] ?? x['name']} ${x['section'] ?? ''}'.trim())],
                      onChanged: (v) => setState(() => classId = v ?? classId))),
                  const SizedBox(width: 8),
                ],
                Expanded(child: Pick<String>(dense: true, label: 'माध्यम', value: '${d['medium'] ?? ''}', items: [for (final e in mediums.entries) ('${e.key}', '${e.value}')], onChanged: (v) => setState(() => medium = v ?? medium))),
              ])),
              if (d['is_template'] == true)
                Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Text('हा सुचवलेला साचा आहे — बदल करून जतन करा.', style: TextStyle(color: TG.mutedA, fontSize: 12))),
              Expanded(child: ListView(padding: const EdgeInsets.fromLTRB(12, 4, 12, 12), children: [
                for (final day in days)
                  Card(child: ExpansionTile(
                    title: Text('${day['name']}', style: const TextStyle(fontWeight: FontWeight.w800, color: TG.g800)),
                    subtitle: Text([for (final p in (day['periods'] as List? ?? []).cast<Map>()) _sub['${day['day']}-${p['period']}'] ?? ''].where((x) => x.isNotEmpty).join(', '),
                        maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
                    childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
                    children: [
                      for (final p in (day['periods'] as List? ?? []).cast<Map>())
                        Padding(padding: const EdgeInsets.only(bottom: 8), child: Row(children: [
                          SizedBox(width: 74, child: Text('तासिका ${p['period']}\n${p['time'] ?? ''}', style: const TextStyle(fontSize: 11))),
                          Expanded(child: Pick<String>(dense: true, label: 'विषय', value: _sub['${day['day']}-${p['period']}'] ?? '',
                              items: [('', '—'), for (final s in subjects) (s, s)],
                              onChanged: (v) => setState(() => _sub['${day['day']}-${p['period']}'] = v ?? ''))),
                          if (mode == 'teacher') ...[
                            const SizedBox(width: 6),
                            Expanded(child: Pick<String>(dense: true, label: 'वर्ग', value: _cls['${day['day']}-${p['period']}'] ?? '',
                                items: [('', '—'), for (final x in choices) ('${x['key']}', '${x['label']}')],
                                onChanged: (v) => setState(() => _cls['${day['day']}-${p['period']}'] = v ?? ''))),
                          ],
                        ])),
                    ],
                  )),
              ])),
              SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: Row(children: [
                Expanded(child: FilledButton.icon(
                  icon: _saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.save),
                  label: const Text('वेळापत्रक जतन करा'),
                  onPressed: _saving ? null : () async {
                    setState(() => _saving = true);
                    try {
                      final cells = [
                        for (final day in days)
                          for (final p in (day['periods'] as List? ?? []).cast<Map>())
                            {'day': asInt(day['day']), 'period': asInt(p['period']), 'subject': _sub['${day['day']}-${p['period']}'] ?? '',
                              if (mode == 'teacher') 'class_info': _cls['${day['day']}-${p['period']}'] ?? ''},
                      ];
                      await Remote.save('tachan_native.php', mode == 'class'
                          ? {'action': 'timetable_class_save', 'class_id': asInt(cls['id']), 'cells': cells}
                          : {'action': 'timetable_teacher_save', 'medium': '${d['medium'] ?? 'marathi'}', 'cells': cells});
                      if (c.mounted) toast(c, 'वेळापत्रक जतन झाले');
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
                      onPressed: () => Remote.printReport(c, '${d['print']['page']}', ((d['print']['params'] as Map?) ?? {}).cast<String, dynamic>(), name: 'वेळापत्रक')),
              ]))),
            ]);
          },
        ),
      );
}
