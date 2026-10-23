import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../core/remote.dart';
import '../core/session.dart';
import 'common.dart';
import 'widgets.dart';

const _api = 'tachan_native.php';

class HolidaysScreen extends StatefulWidget {
  const HolidaysScreen({super.key});
  @override
  State<HolidaysScreen> createState() => _HolidaysScreenState();
}

class _HolidaysScreenState extends State<HolidaysScreen> {
  DateTime month = DateTime(DateTime.now().year, DateTime.now().month);
  String get ym => '${month.year}-${month.month.toString().padLeft(2, '0')}';
  String? selected;

  Future<void> _add(BuildContext c, VoidCallback reload, {required bool holiday, String? date}) async {
    final name = TextEditingController();
    var d = date ?? '$ym-01';
    final ok = await showDialog<bool>(
      context: c,
      builder: (_) => StatefulBuilder(
        builder: (c2, set) => AlertDialog(
          title: Text(holiday ? 'नवीन सुट्टी' : 'नवीन कार्यक्रम / उपक्रम'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.event),
              title: Text(d),
              onTap: () async {
                final x = await showDatePicker(context: c2, initialDate: DateTime.parse(d), firstDate: DateTime(2020), lastDate: DateTime(2035));
                if (x != null) set(() => d = x.toIso8601String().substring(0, 10));
              },
            ),
            TextField(controller: name, autofocus: true, decoration: InputDecoration(labelText: holiday ? 'सुट्टीचे नाव' : 'कार्यक्रमाचे नाव', border: const OutlineInputBorder())),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c2, false), child: const Text('रद्द')),
            FilledButton(onPressed: () => Navigator.pop(c2, true), child: const Text('जोडा')),
          ],
        ),
      ),
    );
    if (ok != true || name.text.trim().isEmpty) return;
    try {
      final online = await Remote.save(_api, {
        'action': holiday ? 'holiday_add' : 'event_add',
        'date': d,
        holiday ? 'name' : 'title': name.text.trim(),
      });
      if (c.mounted) toast(c, online ? 'जोडले' : 'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.');
      reload();
    } catch (e) {
      if (c.mounted) toast(c, '$e', error: true);
    }
  }

  Future<void> _delete(BuildContext c, VoidCallback reload, bool holiday, dynamic id) async {
    final ok = await showDialog<bool>(
      context: c,
      builder: (c2) => AlertDialog(
        title: const Text('हटवायचे?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c2, false), child: const Text('नाही')),
          FilledButton(onPressed: () => Navigator.pop(c2, true), child: const Text('हो, हटवा')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await Remote.save(_api, {'action': holiday ? 'holiday_delete' : 'event_delete', 'id': id});
      reload();
    } catch (e) {
      if (c.mounted) toast(c, '$e', error: true);
    }
  }

  Future<void> _yearPdf(BuildContext c) async {
    final start = month.month >= 6 ? month.year : month.year - 1;
    final rows = <List<String>>[];
    try {
      for (var i = 0; i < 12; i++) {
        final m = DateTime(start, 6 + i);
        final d = await Remote.get(_api, {'action': 'holidays', 'ym': '${m.year}-${m.month.toString().padLeft(2, '0')}'});
        for (final h in (d['holidays'] as List? ?? []).cast<Map>()) {
          rows.add(['${h['date']}', '${h['name']}', h['kind'] == 'govt' ? 'शासकीय' : 'शाळा']);
        }
      }
      final font = await PdfGoogleFonts.muktaRegular();
      final bold = await PdfGoogleFonts.muktaBold();
      final school = '${Session.I.school['name_mr'] ?? Session.I.school['name'] ?? ''}';
      final doc = pw.Document(theme: pw.ThemeData.withFont(base: font, bold: bold));
      doc.addPage(pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        build: (_) => [
          pw.Center(child: pw.Text(school, style: pw.TextStyle(fontSize: 18, fontWeight: pw.FontWeight.bold))),
          pw.Center(child: pw.Text('वार्षिक सुट्टी यादी $start-${start + 1}', style: const pw.TextStyle(fontSize: 14))),
          pw.SizedBox(height: 12),
          pw.TableHelper.fromTextArray(
            headers: ['अ.क्र.', 'दिनांक', 'सुट्टी', 'प्रकार'],
            data: [for (var i = 0; i < rows.length; i++) ['${i + 1}', ...rows[i]]],
            headerStyle: pw.TextStyle(fontWeight: pw.FontWeight.bold, color: PdfColors.white),
            headerDecoration: const pw.BoxDecoration(color: PdfColor.fromInt(0xFF0F4F3B)),
            cellAlignment: pw.Alignment.centerLeft,
          ),
          pw.SizedBox(height: 8),
          pw.Text('एकूण सुट्ट्या: ${rows.length}'),
          pw.SizedBox(height: 40),
          pw.Align(alignment: pw.Alignment.centerRight, child: pw.Text('मुख्याध्यापक')),
        ],
      ));
      await Printing.layoutPdf(name: 'सुट्टी यादी', onLayout: (_) => doc.save());
    } catch (e) {
      if (c.mounted) toast(c, '$e', error: true);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('दिनदर्शिका व सुट्ट्या'), actions: [
          IconButton(tooltip: 'सुट्टी यादी PDF', icon: const Icon(Icons.picture_as_pdf_outlined), onPressed: () => _yearPdf(context)),
        ]),
        body: ApiLoader(
          path: _api,
          query: {'action': 'holidays', 'ym': ym},
          builder: (c, d, reload) {
            final hol = (d['holidays'] as List? ?? []).cast<Map>();
            final ev = (d['events'] as List? ?? []).cast<Map>();
            final days = asInt(d['days_in_month'], 30);
            final startDow = asInt(d['start_dow']);
            final names = (d['day_names'] as List? ?? ['र', 'सो', 'मं', 'बु', 'गु', 'शु', 'श']).map((e) => '$e').toList();
            String ds(int day) => '$ym-${day.toString().padLeft(2, '0')}';
            var sundays = 0;
            for (var i = 1; i <= days; i++) {
              if ((startDow + i - 1) % 7 == 0) sundays++;
            }
            final holDays = hol.map((h) => h['date']).toSet().where((x) => DateTime.parse('$x').weekday != DateTime.sunday).length;
            final canManage = d['can_manage'] != false;
            final dayItems = selected == null ? <Map>[] : [...hol.where((h) => h['date'] == selected), ...ev.where((e) => e['date'] == selected)];
            return ListView(padding: const EdgeInsets.all(12), children: [
              Row(children: [
                IconButton(onPressed: () => setState(() { month = DateTime(month.year, month.month - 1); selected = null; }), icon: const Icon(Icons.chevron_left)),
                Expanded(child: Text('${d['month_name'] ?? ''} ${d['year'] ?? month.year}', textAlign: TextAlign.center, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
                IconButton(onPressed: () => setState(() { month = DateTime(month.year, month.month + 1); selected = null; }), icon: const Icon(Icons.chevron_right)),
              ]),
              GridView.count(
                crossAxisCount: 7,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                childAspectRatio: 1,
                children: [
                  for (final n in names) Center(child: Text(n, style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF687A73)))),
                  for (var i = 0; i < startDow; i++) const SizedBox.shrink(),
                  for (var day = 1; day <= days; day++)
                    Builder(builder: (_) {
                      final isHol = hol.any((h) => h['date'] == ds(day));
                      final isEv = ev.any((e) => e['date'] == ds(day));
                      final sun = (startDow + day - 1) % 7 == 0;
                      final sel = selected == ds(day);
                      return InkWell(
                        borderRadius: BorderRadius.circular(10),
                        onTap: () => setState(() => selected = ds(day)),
                        child: Container(
                          margin: const EdgeInsets.all(2),
                          decoration: BoxDecoration(
                            color: isHol ? const Color(0xFFFDE2E1) : sun ? const Color(0xFFF2F4F3) : Colors.white,
                            border: Border.all(color: sel ? const Color(0xFF167A5B) : const Color(0xFFE3EAE6), width: sel ? 2 : 1),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                            Text('$day', style: TextStyle(fontWeight: FontWeight.w700, color: isHol || sun ? const Color(0xFFB3261E) : null)),
                            if (isEv) Container(width: 6, height: 6, decoration: const BoxDecoration(color: Color(0xFFE9A23B), shape: BoxShape.circle)),
                          ]),
                        ),
                      );
                    }),
                ],
              ),
              const SizedBox(height: 6),
              const Wrap(spacing: 12, children: [
                Chip(avatar: CircleAvatar(backgroundColor: Color(0xFFFDE2E1)), label: Text('सुट्टी')),
                Chip(avatar: CircleAvatar(backgroundColor: Color(0xFFE9A23B), radius: 5), label: Text('कार्यक्रम / दिनविशेष')),
              ]),
              Card(
                color: const Color(0xFFEFF8F4),
                child: ListTile(
                  leading: const Icon(Icons.work_outline, color: Color(0xFF167A5B)),
                  title: Text('कामाचे दिवस: ${days - sundays - holDays}'),
                  subtitle: Text('एकूण $days दिवस − रविवार $sundays − सुट्ट्या $holDays'),
                ),
              ),
              if (selected != null) ...[
                Padding(padding: const EdgeInsets.fromLTRB(4, 12, 4, 4), child: Text('$selected', style: const TextStyle(fontWeight: FontWeight.w800))),
                if (dayItems.isEmpty) const Padding(padding: EdgeInsets.all(8), child: Text('या दिवशी काही नोंद नाही')),
                if (canManage)
                  Wrap(spacing: 8, children: [
                    OutlinedButton.icon(onPressed: () => _add(c, reload, holiday: true, date: selected), icon: const Icon(Icons.beach_access), label: const Text('सुट्टी जोडा')),
                    OutlinedButton.icon(onPressed: () => _add(c, reload, holiday: false, date: selected), icon: const Icon(Icons.event), label: const Text('कार्यक्रम जोडा')),
                  ]),
              ],
              const Padding(padding: EdgeInsets.fromLTRB(4, 16, 4, 4), child: Text('या महिन्यातील सुट्ट्या', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16))),
              if (hol.isEmpty) const Padding(padding: EdgeInsets.all(8), child: Text('सुट्टी नाही')),
              for (final h in hol)
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.beach_access, color: Color(0xFFB3261E)),
                    title: Text('${h['name']}'),
                    subtitle: Text('${h['date']} · ${h['kind'] == 'govt' ? 'शासकीय' : 'शाळा'}'),
                    trailing: canManage ? IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _delete(c, reload, true, h['id'])) : null,
                  ),
                ),
              const Padding(padding: EdgeInsets.fromLTRB(4, 16, 4, 4), child: Text('कार्यक्रम / दिनविशेष', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16))),
              if (ev.isEmpty) const Padding(padding: EdgeInsets.all(8), child: Text('नोंद नाही')),
              for (final e in ev)
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.event, color: Color(0xFFE9A23B)),
                    title: Text('${e['title']}'),
                    subtitle: Text('${e['date']} · ${e['event_type'] ?? ''}'),
                    trailing: canManage ? IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _delete(c, reload, false, e['id'])) : null,
                  ),
                ),
              const SizedBox(height: 70),
            ]);
          },
        ),
        floatingActionButton: Builder(
          builder: (c) => FloatingActionButton.extended(
            onPressed: () => _add(c, () => setState(() {}), holiday: true),
            icon: const Icon(Icons.add),
            label: const Text('सुट्टी जोडा'),
          ),
        ),
      );
}
