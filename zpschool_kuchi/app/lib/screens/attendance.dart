import 'package:flutter/material.dart';

import '../core/local_db.dart';
import '../core/remote.dart';
import '../core/sync.dart';
import '../theme.dart';
import 'hajeri_staff.dart';
import 'students.dart';
import 'widgets.dart';

/// Monthly attendance (working days / days present per student), same as the portal.
class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});
  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  static const _months = ['जानेवारी', 'फेब्रुवारी', 'मार्च', 'एप्रिल', 'मे', 'जून', 'जुलै', 'ऑगस्ट', 'सप्टेंबर', 'ऑक्टोबर', 'नोव्हेंबर', 'डिसेंबर'];
  List<Map<String, dynamic>> _students = [], _att = [];
  String _grade = '';
  int _month = DateTime.now().month;
  final _work = TextEditingController(text: '22');
  final Map<int, TextEditingController> _present = {};

  String get _year {
    final n = DateTime.now();
    final y = n.month >= 6 ? n.year : n.year - 1;
    return '$y-${y + 1}';
  }

  @override
  void initState() {
    super.initState();
    SyncService.I.addListener(_load);
    _load();
  }

  @override
  void dispose() {
    SyncService.I.removeListener(_load);
    super.dispose();
  }

  Future<void> _load() async {
    final s = await LocalDb.I.rows('students');
    final a = await LocalDb.I.rows('attendance');
    if (!mounted) return;
    setState(() {
      _students = s;
      _att = a;
      if (_grade.isEmpty && s.isNotEmpty) _grade = '${s.first['grade']}';
    });
    _fill();
  }

  Map<String, dynamic>? _rec(int sid) {
    for (final r in _att) {
      if ('${r['student_id']}' == '$sid' && '${r['month']}' == '$_month' && '${r['academic_year']}' == _year) return r;
    }
    return null;
  }

  void _fill() {
    for (final s in _students) {
      final r = _rec(s['id'] as int);
      _present.putIfAbsent(s['id'] as int, TextEditingController.new).text = r == null ? '' : '${r['days_present']}';
      if (r != null) _work.text = '${r['working_days']}';
    }
    setState(() {});
  }

  Future<void> _save(List<Map<String, dynamic>> list) async {
    final wd = int.tryParse(_work.text) ?? 0;
    var n = 0;
    for (final s in list) {
      final sid = s['id'] as int;
      if (sid < 0) continue;
      final v = int.tryParse(_present[sid]?.text ?? '');
      if (v == null) continue;
      final cur = _rec(sid);
      await LocalDb.I.saveRow('attendance', {
        ...?cur,
        'student_id': sid, 'academic_year': _year, 'month': _month, 'working_days': wd, 'days_present': v.clamp(0, wd),
      });
      n++;
    }
    await SyncService.I.refreshState();
    await _load();
    if (mounted) toast(context, '$n विद्यार्थ्यांची हजेरी फोनवर जतन केली');
  }

  @override
  Widget build(BuildContext context) {
    final grades = ({for (final s in _students) '${s['grade']}'}..remove('')).toList()..sort();
    final list = _students.where((s) => '${s['grade']}' == _grade).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('मासिक हजेरी'), actions: [
        IconButton(icon: const Icon(Icons.badge_outlined), tooltip: 'कर्मचारी हजेरी',
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StaffAttendanceScreen()))),
        IconButton(icon: const Icon(Icons.print_outlined), tooltip: 'उपस्थिती अहवाल', onPressed: () {
          final n = DateTime.now(); final y = _month >= 6 ? int.parse(_year.substring(0, 4)) : int.parse(_year.substring(0, 4)) + 1;
          final last = DateTime(y, _month + 1, 0).day;
          final mm = _month.toString().padLeft(2, '0');
          Remote.printReport(context, 'modules/attendance/report.php', {'from': '$y-$mm-01', 'to': y == n.year && _month == n.month ? n.toIso8601String().substring(0, 10) : '$y-$mm-$last'}, name: 'उपस्थिती अहवाल');
        }),
      ]),
      body: _students.isEmpty
          ? const EmptyState('आधी डॅशबोर्डवर Sync करा.', icon: Icons.fact_check_outlined)
          : Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Row(children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      value: grades.contains(_grade) ? _grade : null,
                      decoration: const InputDecoration(labelText: 'इयत्ता', isDense: true),
                      items: [for (final g in grades) DropdownMenuItem(value: g, child: Text('इ. $g'))],
                      onChanged: (v) => setState(() => _grade = v ?? ''),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: DropdownButtonFormField<int>(
                      value: _month,
                      decoration: const InputDecoration(labelText: 'महिना', isDense: true),
                      items: [for (var i = 1; i <= 12; i++) DropdownMenuItem(value: i, child: Text(_months[i - 1]))],
                      onChanged: (v) { _month = v ?? _month; _fill(); },
                    ),
                  ),
                  const SizedBox(width: 10),
                  SizedBox(
                    width: 90,
                    child: TextField(controller: _work, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'कामाचे दिवस', isDense: true)),
                  ),
                ]),
              ),
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const Divider(height: 1, color: TG.line),
                  itemBuilder: (_, i) {
                    final s = list[i];
                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(studentName(s), style: const TextStyle(fontWeight: FontWeight.w600)),
                      subtitle: Text('हजेरी क्र. ${s['roll_no'] ?? '-'}'),
                      trailing: SizedBox(
                        width: 80,
                        child: TextField(
                          controller: _present.putIfAbsent(s['id'] as int, TextEditingController.new),
                          keyboardType: TextInputType.number,
                          textAlign: TextAlign.center,
                          decoration: const InputDecoration(hintText: 'उपस्थित', isDense: true),
                        ),
                      ),
                    );
                  },
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                  child: SizedBox(width: double.infinity, child: FilledButton.icon(onPressed: () => _save(list), icon: const Icon(Icons.save_outlined), label: const Text('हजेरी जतन करा'))),
                ),
              ),
            ]),
    );
  }
}
