import 'package:flutter/material.dart';

import '../core/remote.dart';
import 'common.dart';
import 'widgets.dart';

class MdmScreen extends StatefulWidget {
  const MdmScreen({super.key});
  @override
  State<MdmScreen> createState() => _MdmScreenState();
}

class _MdmScreenState extends State<MdmScreen> {
  String _group = '1-5';
  DateTime date = DateTime.now();
  String get ds => '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  static const _reports = [
    ('prapatr_b', 'प्रपत्र ब — मासिक अहवाल'),
    ('tandul_monthly', 'दैनिक खर्च नोंदवही भाग १ (तांदूळ)'),
    ('nodvahi2', 'दैनिक खर्च नोंदवही भाग २ (धान्य)'),
    ('chav_nondavahi', 'चव रजिस्टर'),
    ('cumulative', 'संचयी अहवाल'),
    ('annual_summary', 'वार्षिक सारांश'),
    ('yearly_report', 'सर्वसमावेशक वार्षिक अहवाल'),
    ('annual_cert', 'वार्षिक उपयोगिता प्रमाणपत्र'),
  ];

  @override
  Widget build(BuildContext context) => DefaultTabController(
        length: 2,
        child: Scaffold(
          appBar: AppBar(
            title: const Text('पोषण आहार'),
            bottom: const TabBar(tabs: [Tab(text: 'रोजची नोंद'), Tab(text: 'अहवाल / PDF')]),
          ),
          body: TabBarView(children: [
            ApiLoader(
              path: 'mdm.php',
              query: {'date': ds},
              builder: (c, d, reload) {
                final menus = [for (final m in (d['menus'] as List? ?? []).cast<Map>()) (asInt(m['id']), '${m['name']}')];
                final raw = d['entries'];
                final entries = <String, Map>{};
                if (raw is Map) {
                  raw.forEach((k, v) { if (v is Map) entries['$k'] = v; });
                } else if (raw is List) {
                  for (final e in raw) { if (e is Map) entries['${e['class_group']}'] = e; }
                }
                final sum = (d['summary'] as Map?) ?? {};
                return ListView(padding: const EdgeInsets.all(12), children: [
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.calendar_today),
                      title: Text(ds),
                      subtitle: Text('लाभार्थी ${sum['total_beneficiaries'] ?? 0} · तांदूळ ${sum['total_rice_kg'] ?? 0} kg · डाळ ${sum['total_dal_kg'] ?? 0} kg'),
                      trailing: const Icon(Icons.edit_calendar),
                      onTap: () async {
                        final x = await showDatePicker(context: c, initialDate: date, firstDate: DateTime(2020), lastDate: DateTime.now());
                        if (x != null) setState(() => date = x);
                      },
                    ),
                  ),
                  for (final g in const [('1-5', 'group_1_5', 'इयत्ता १ ते ५'), ('6-8', 'group_6_8', 'इयत्ता ६ ते ८')])
                    _GroupCard(key: ValueKey('$ds-${g.$1}'), date: ds, groupKey: g.$2, title: g.$3, entry: entries[g.$1] ?? {}, menus: menus, onSaved: reload),
                ]);
              },
            ),
            ListView(padding: const EdgeInsets.all(12), children: [
              Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                ListTile(contentPadding: EdgeInsets.zero, leading: const Icon(Icons.calendar_month), title: Text('महिना: ${ds.substring(0, 7)}'),
                  subtitle: const Text('बदलण्यासाठी दाबा'), onTap: () async {
                    final x = await showDatePicker(context: context, initialDate: date, firstDate: DateTime(2020), lastDate: DateTime.now());
                    if (x != null) setState(() => date = x);
                  }),
                SegmentedButton<String>(segments: const [ButtonSegment(value: '1-5', label: Text('इयत्ता १ ते ५')), ButtonSegment(value: '6-8', label: Text('इयत्ता ६ ते ८'))],
                  selected: {_group}, onSelectionChanged: (v) => setState(() => _group = v.first)),
              ]))),
              for (final r in _reports)
                ReportTile(title: r.$2, page: 'modules/mdm/reports/${r.$1}.php', params: {
                  'month': date.month, 'year': date.year, 'ym': ds.substring(0, 7), 'class_group': _group,
                  'start_year': date.month >= 6 ? date.year : date.year - 1, 'up_to_month': date.month,
                }),
            ]),
          ]),
        ),
      );
}

class _GroupCard extends StatefulWidget {
  const _GroupCard({super.key, required this.date, required this.groupKey, required this.title, required this.entry, required this.menus, required this.onSaved});
  final String date, groupKey, title;
  final Map entry;
  final List<(int, String)> menus;
  final VoidCallback onSaved;
  @override
  State<_GroupCard> createState() => _GroupCardState();
}

class _GroupCardState extends State<_GroupCard> {
  late final TextEditingController ben;
  late final TextEditingController rem;
  late int menu;
  static bool _flag(dynamic v, bool def) => v is bool ? v : v == null ? def : asInt(v, def ? 1 : 0) == 1;
  late bool cooked;
  late bool holiday;

  @override
  void initState() {
    super.initState();
    ben = TextEditingController(text: '${widget.entry['beneficiaries'] ?? ''}');
    rem = TextEditingController(text: '${widget.entry['remarks'] ?? ''}');
    menu = asInt(widget.entry['menu_id']);
    cooked = widget.entry.isEmpty || _flag(widget.entry['cooked'], true);
    holiday = _flag(widget.entry['holiday'], false);
  }

  @override
  void dispose() {
    ben.dispose();
    rem.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const Spacer(),
              if (widget.entry.isNotEmpty) const Chip(label: Text('नोंद झाली'), avatar: Icon(Icons.check_circle, color: Colors.green)),
            ]),
            if (widget.entry.isNotEmpty) Text('तांदूळ ${widget.entry['rice_kg'] ?? 0} kg · डाळ ${widget.entry['dal_kg'] ?? 0} kg'),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('आज सुट्टी'), value: holiday, onChanged: (v) => setState(() => holiday = v)),
            if (!holiday) ...[
              SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('आहार शिजवला'), value: cooked, onChanged: (v) => setState(() => cooked = v)),
              Pick<int>(label: 'आजचा मेनू', value: menu, items: widget.menus, onChanged: (v) => setState(() => menu = v ?? 0)),
              const SizedBox(height: 10),
              TextField(controller: ben, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'लाभार्थी विद्यार्थी संख्या', border: OutlineInputBorder())),
            ],
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              isExpanded: true,
              value: null,
              hint: const Text('-- तयार शेरा निवडा --', style: TextStyle(fontSize: 13)),
              decoration: const InputDecoration(labelText: 'तयार शेऱ्यांमधून निवडा', prefixIcon: Icon(Icons.lightbulb_outline), border: OutlineInputBorder(), isDense: true),
              items: [
                for (final s in const [
                  'नियमित पोषण आहार वाटप',
                  'सर्व विद्यार्थ्यांना वेळेवर वाटप',
                  'आहार चवदार व दर्जेदार होता',
                  'विद्यार्थ्यांनी आनंदाने आहार घेतला',
                  'स्वच्छता नियमांचे पालन करून वाटप'
                ])
                  DropdownMenuItem(value: s, child: Text(s, style: const TextStyle(fontSize: 13))),
              ],
              onChanged: (v) { if (v != null) setState(() => rem.text = v); },
            ),
            const SizedBox(height: 8),
            TextField(controller: rem, decoration: const InputDecoration(labelText: 'शेरा (Remarks)', border: OutlineInputBorder())),
            const SizedBox(height: 10),
            FilledButton.icon(
              icon: const Icon(Icons.save),
              label: const Text('जतन करा'),
              onPressed: () async {
                final m = widget.menus.where((e) => e.$1 == menu).map((e) => e.$2).firstOrNull ?? '';
                try {
                  final ok = await Remote.save('mdm.php', {
                    'date': widget.date,
                    widget.groupKey: {
                      'beneficiaries': holiday ? 0 : int.tryParse(ben.text) ?? 0,
                      'cooked': !holiday && cooked ? 1 : 0,
                      'holiday': holiday ? 1 : 0,
                      'menu_id': menu == 0 ? null : menu,
                      'menu_name': m,
                      'remarks': rem.text.trim(),
                    },
                  });
                  if (!context.mounted) return;
                  toast(context, ok ? 'नोंद जतन झाली' : 'फोनवर जतन झाली. Sync केल्यावर पोर्टलवर जाईल.');
                  if (ok) widget.onSaved();
                } catch (e) {
                  if (context.mounted) toast(context, '$e', error: true);
                }
              },
            ),
          ]),
        ),
      );
}
