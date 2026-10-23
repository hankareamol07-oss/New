import 'package:flutter/material.dart';

import '../core/local_db.dart';
import '../core/remote.dart';
import 'common.dart';
import 'widgets.dart';

/// शिक्षक व वर्ग — offline list from the synced school data.
class SchoolListScreen extends StatefulWidget {
  const SchoolListScreen({super.key, required this.teachers});
  final bool teachers;

  @override
  State<SchoolListScreen> createState() => _SchoolListScreenState();
}

class _SchoolListScreenState extends State<SchoolListScreen> {
  void _editTeacher(BuildContext context, Map? t) {
    final isNew = t == null;
    final nameMrCtrl = TextEditingController(text: '${t?['name_mr'] ?? ''}');
    final nameCtrl = TextEditingController(text: '${t?['name'] ?? ''}');
    final phoneCtrl = TextEditingController(text: '${t?['phone'] ?? ''}');
    final codeCtrl = TextEditingController(text: '${t?['teacher_code'] ?? ''}');
    final classAssignedCtrl = TextEditingController(text: '${t?['class_assigned'] ?? ''}');
    final sectionCtrl = TextEditingController(text: '${t?['section'] ?? ''}');
    String role = '${t?['role'] ?? 'सहाय्यक शिक्षक'}';
    if (role.isEmpty || role == 'teacher') role = 'सहाय्यक शिक्षक';

    const roles = [
      'मुख्याध्यापक',
      'उपमुख्याध्यापक',
      'पदवीधर शिक्षक',
      'सहाय्यक शिक्षक',
      'विशेष शिक्षक',
      'कला शिक्षक',
      'क्रीडा शिक्षक',
    ];

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlgState) => AlertDialog(
          title: Row(
            children: [
              Icon(isNew ? Icons.person_add_alt_1 : Icons.edit_note, color: const Color(0xFF0F766E)),
              const SizedBox(width: 8),
              Text(isNew ? 'नवीन शिक्षक नोंद' : 'शिक्षक माहिती संपादन', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: nameMrCtrl,
                  decoration: const InputDecoration(labelText: 'शिक्षकाचे नाव (मराठी) *', hintText: 'उदा. श्री. सचिन रमेश तेंडुलकर', isDense: true),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: nameCtrl,
                  decoration: const InputDecoration(labelText: 'Name (English)', hintText: 'e.g. Sachin Tendulkar', isDense: true),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: roles.contains(role) ? role : 'सहाय्यक शिक्षक',
                  decoration: const InputDecoration(labelText: 'पद / भूमिका', isDense: true),
                  items: [for (final r in roles) DropdownMenuItem(value: r, child: Text(r))],
                  onChanged: (v) => setDlgState(() => role = v ?? role),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: TextField(
                        controller: classAssignedCtrl,
                        decoration: const InputDecoration(labelText: 'वर्गवाटप', hintText: 'उदा. इयत्ता ४', isDense: true),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 2,
                      child: TextField(
                        controller: sectionCtrl,
                        decoration: const InputDecoration(labelText: 'तुकडी', hintText: 'उदा. अ', isDense: true),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: phoneCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'मोबाईल नंबर', hintText: '१० अंकी मोबाईल', isDense: true),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: codeCtrl,
                  decoration: const InputDecoration(labelText: 'शिक्षक कोड / शालार्थ ID', hintText: 'उदा. T-101', isDense: true),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('रद्द करा')),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0F766E)),
              onPressed: () async {
                final mr = nameMrCtrl.text.trim();
                final en = nameCtrl.text.trim();
                if (mr.isEmpty && en.isEmpty) {
                  toast(ctx, 'शिक्षकाचे नाव आवश्यक आहे!', error: true);
                  return;
                }
                final row = <String, dynamic>{
                  if (!isNew && t['id'] != null) 'id': asInt(t['id']),
                  'name_mr': mr.isNotEmpty ? mr : en,
                  'name': en.isNotEmpty ? en : mr,
                  'role': role,
                  'class_assigned': classAssignedCtrl.text.trim(),
                  'section': sectionCtrl.text.trim(),
                  'phone': phoneCtrl.text.trim(),
                  'teacher_code': codeCtrl.text.trim(),
                  'is_active': 1,
                  'updated_at': DateTime.now().toIso8601String(),
                };
                await LocalDb.I.saveRow('teachers', row);
                if (ctx.mounted) Navigator.pop(ctx);
                if (mounted) {
                  toast(this.context, isNew ? 'शिक्षक जोडले गेले!' : 'शिक्षक माहिती अद्ययावत झाली!');
                  setState(() {});
                }
              },
              child: const Text('जतन करा ✓'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: Text(widget.teachers ? 'शिक्षक व्यवस्थापन' : 'वर्ग'),
          actions: [
            if (widget.teachers)
              IconButton(
                icon: const Icon(Icons.person_add_alt_1),
                tooltip: 'नवीन शिक्षक जोडा',
                onPressed: () => _editTeacher(context, null),
              ),
          ],
        ),
        body: FutureBuilder(
          future: Future.wait([LocalDb.I.rows('teachers'), LocalDb.I.rows('classes'), LocalDb.I.rows('students')]),
          builder: (c, s) {
            if (!s.hasData) return const Center(child: CircularProgressIndicator());
            final t = s.data![0], cl = s.data![1], st = s.data![2];
            final tName = {for (final x in t) asInt(x['id']): '${x['name_mr'] ?? x['name']}'};
            if ((widget.teachers ? t : cl).isEmpty) {
              return Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text('माहिती उपलब्ध नाही.'),
                    const SizedBox(height: 12),
                    if (widget.teachers)
                      FilledButton.icon(
                        onPressed: () => _editTeacher(context, null),
                        icon: const Icon(Icons.add),
                        label: const Text('पहिला शिक्षक जोडा'),
                      ),
                  ],
                ),
              );
            }
            if (widget.teachers) {
              return ListView(padding: const EdgeInsets.all(12), children: [
                for (final x in t)
                  Card(
                    child: ListTile(
                      leading: CircleAvatar(
                        backgroundColor: const Color(0xFFCCFBF1),
                        foregroundColor: const Color(0xFF0F766E),
                        child: Text('${x['name_mr'] ?? x['name'] ?? '?'}'.characters.first),
                      ),
                      title: Text('${x['name_mr'] ?? x['name']}', style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text([
                        x['role'],
                        x['class_assigned'] == null || '${x['class_assigned']}'.isEmpty ? null : 'वर्ग: ${x['class_assigned']} ${x['section'] ?? ''}',
                        x['phone'] == null || '${x['phone']}'.isEmpty ? null : '📱 ${x['phone']}',
                      ].where((e) => '${e ?? ''}'.isNotEmpty).join(' · ')),
                      trailing: IconButton(
                        icon: const Icon(Icons.edit_outlined, color: Color(0xFF0F766E)),
                        tooltip: 'संपादित करा',
                        onPressed: () => _editTeacher(context, x),
                      ),
                      onTap: () => _editTeacher(context, x),
                    ),
                  ),
              ]);
            }
            return ListView(padding: const EdgeInsets.all(12), children: [
              for (final x in cl)
                Card(
                  child: ListTile(
                    leading: CircleAvatar(child: Text('${x['std']}')),
                    title: Text('${x['name'] ?? stdLabel(asInt(x['std']))} ${x['section'] ?? ''}'),
                    subtitle: Text('वर्गशिक्षक: ${tName[asInt(x['class_teacher_id'])] ?? '-'} · माध्यम: ${x['medium'] ?? '-'}'),
                    trailing: Text('${st.where((e) => asInt(e['class_id']) == asInt(x['id']) || ('${e['grade']}' == '${x['std']}' && '${e['section'] ?? ''}' == '${x['section'] ?? ''}')).length} विद्यार्थी'),
                  ),
                ),
            ]);
          },
        ),
      );
}

/// बोनाफाईड दाखला — same certificate layout as the portal, as PDF.
class BonafideScreen extends StatefulWidget {
  const BonafideScreen({super.key});
  @override
  State<BonafideScreen> createState() => _BonafideScreenState();
}

class _BonafideScreenState extends State<BonafideScreen> {
  String grade = '';
  int student = 0;
  String purpose = 'शैक्षणिक कामासाठी';
  String conduct = 'उत्तम';
  final outward = TextEditingController();
  DateTime issue = DateTime.now();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('बोनाफाईड दाखला')),
        body: FutureBuilder(
          future: LocalDb.I.rows('students'),
          builder: (c, s) {
            if (!s.hasData) return const Center(child: CircularProgressIndicator());
            final all = s.data!.where((e) => asInt(e['is_active'], 1) == 1).toList();
            if (all.isEmpty) return const Center(child: Text('विद्यार्थी नाहीत. डॅशबोर्डवर Sync करा.'));
            final grades = {for (final e in all) '${e['grade']}'}.toList()..sort((a, b) => asInt(a).compareTo(asInt(b)));
            final list = all.where((e) => grade.isEmpty || '${e['grade']}' == grade).toList();
            return ListView(padding: const EdgeInsets.all(12), children: [
              Pick<String>(label: 'इयत्ता', value: grade, items: [('', 'सर्व'), for (final g in grades) (g, 'इयत्ता $g')], onChanged: (v) => setState(() { grade = v ?? ''; student = 0; })),
              const SizedBox(height: 10),
              Pick<int>(label: 'विद्यार्थी', value: student, items: [for (final e in list) (asInt(e['id']), studentLabel(e))], onChanged: (v) => setState(() => student = v ?? 0)),
              const SizedBox(height: 10),
              Pick<String>(
                label: 'दाखल्याचा उद्देश',
                value: purpose,
                items: const [
                  ('शैक्षणिक कामासाठी', 'शैक्षणिक कामासाठी'), ('शिष्यवृत्तीसाठी', 'शिष्यवृत्तीसाठी'), ('बँक खाते उघडण्यासाठी', 'बँक खाते उघडण्यासाठी'),
                  ('आधार कार्डसाठी', 'आधार कार्डसाठी'), ('जात प्रमाणपत्रासाठी', 'जात प्रमाणपत्रासाठी'), ('प्रवास सवलतीसाठी', 'प्रवास सवलतीसाठी'), ('शासकीय योजनेसाठी', 'शासकीय योजनेसाठी'),
                ],
                onChanged: (v) => setState(() => purpose = v ?? purpose),
              ),
              const SizedBox(height: 10),
              Pick<String>(label: 'वर्तणूक', value: conduct, items: const [('उत्तम', 'उत्तम'), ('चांगली', 'चांगली'), ('समाधानकारक', 'समाधानकारक')], onChanged: (v) => setState(() => conduct = v ?? conduct)),
              const SizedBox(height: 10),
              TextField(controller: outward, decoration: const InputDecoration(labelText: 'जावक क्रमांक (ऐच्छिक)', border: OutlineInputBorder())),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.event),
                title: Text('दिनांक: ${issue.toIso8601String().substring(0, 10)}'),
                onTap: () async {
                  final x = await showDatePicker(context: c, initialDate: issue, firstDate: DateTime(2020), lastDate: DateTime(2035));
                  if (x != null) setState(() => issue = x);
                },
              ),
              const SizedBox(height: 8),
              FilledButton.icon(
                icon: const Icon(Icons.picture_as_pdf),
                label: const Text('दाखला PDF / प्रिंट'),
                onPressed: student == 0
                    ? null
                    : () => Remote.printReport(c, 'modules/classes/bonafide.php', {
                          'student_id': student,
                          'purpose_select': purpose,
                          'conduct': conduct,
                          'outward_no': outward.text.trim(),
                          'issue_date': issue.toIso8601String().substring(0, 10),
                        }, name: 'बोनाफाईड दाखला'),
              ),
              const SizedBox(height: 8),
              if (grade.isNotEmpty)
                OutlinedButton.icon(
                  icon: const Icon(Icons.groups),
                  label: Text('इयत्ता $grade चे सर्व दाखले एकत्र PDF'),
                  onPressed: () => Remote.printReport(c, 'modules/classes/bonafide.php',
                      {'std': grade, 'all': 1, 'purpose_select': purpose, 'conduct': conduct, 'issue_date': issue.toIso8601String().substring(0, 10)},
                      name: 'बोनाफाईड दाखले'),
                ),
            ]);
          },
        ),
      );
}
