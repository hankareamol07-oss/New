// ignore_for_file: curly_braces_in_flow_control_structures
import 'package:flutter/material.dart';
import '../core/remote.dart';
import '../theme.dart';
import 'common.dart';
import 'hpc.dart';
import 'students.dart';
import 'widgets.dart';

const _api = 'hpc_native.php';
List<Map> _l(dynamic v) => (v as List? ?? const []).cast<Map>();
List<String> _s(dynamic v) => [for (final e in (v as List? ?? const [])) '$e'];

/// hpc/create.php — सविस्तर अधिकृत HPC फॉर्म (पायाभूत स्तर, इ. १–२).
class HpcFsFormScreen extends StatefulWidget {
  const HpcFsFormScreen({super.key, required this.student});
  final Map student;

  @override
  State<HpcFsFormScreen> createState() => _HpcFsFormScreenState();
}

class _HpcFsFormScreenState extends State<HpcFsFormScreen> {
  late Future<Map<String, dynamic>> _f;
  bool _fallbackToWizard = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _f = Remote.get(_api, {'action': 'fs_form_get', 'student_id': '${widget.student['id']}'});
  }

  @override
  Widget build(BuildContext context) {
    if (_fallbackToWizard) {
      return HpcFoundationalScreen(student: widget.student);
    }
    return FutureBuilder<Map<String, dynamic>>(
      future: _f,
      builder: (c, s) {
        if (s.connectionState != ConnectionState.done) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        if (s.hasError) {
          final err = '${s.error}';
          // Auto-fallback if the live server has not yet been updated with fs_form_get
          if (err.contains('fs_form_get') || err.contains('Unknown action')) {
            return HpcFoundationalScreen(student: widget.student);
          }
          return Scaffold(
            appBar: AppBar(title: Text('${widget.student['name_mr'] ?? widget.student['name']}')),
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.cloud_off, size: 48, color: Colors.grey),
                  const SizedBox(height: 12),
                  Text(err, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: () => setState(_load),
                    icon: const Icon(Icons.refresh),
                    label: const Text('पुन्हा प्रयत्न करा'),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () => setState(() => _fallbackToWizard = true),
                    icon: const Icon(Icons.view_carousel_outlined),
                    label: const Text('स्टेप-बाय-स्टेप विझार्ड वापरा'),
                  ),
                ]),
              ),
            ),
          );
        }
        final d = s.data!;
        return _FsForm(key: ValueKey('fs${d['card']?['id']}'), d: d, student: widget.student, reload: () => setState(_load));
      },
    );
  }
}

class _FsForm extends StatefulWidget {
  const _FsForm({super.key, required this.d, required this.student, required this.reload});
  final Map d, student; final VoidCallback reload;
  @override State<_FsForm> createState() => _FsFormState();
}

class _FsFormState extends State<_FsForm> {
  static const _tf = ['activity', 'assessment_questions', 'teacher_feedback', 'self_assessment', 'peer_assessment'];
  static const _sf = ['awareness', 'sensitivity', 'creativity', 'self_emoji', 'peer_emoji', 'parent_observation'];
  final _st = <String, Map<String, dynamic>>{};
  final _c = <String, TextEditingController>{};
  final _term = <int, int>{};
  final _sum = <String, String>{};
  bool _saving = false;

  Map get card => (widget.d['card'] as Map?) ?? {};
  List<Map> get domains => _l(widget.d['domains']);
  TextEditingController _t(String k, [String init = '']) => _c.putIfAbsent(k, () => TextEditingController(text: init));

  @override
  void initState() {
    super.initState();
    for (final dm in domains) {
      final did = asInt(dm['id']);
      for (final t in _l(dm['terms'])) {
        final k = '$did-${t['term']}';
        _st[k] = {
          'goals': _s(t['goals']).toSet(), 'competencies': _s(t['competencies']),
          'comp_activities': {for (final e in ((t['comp_activities'] as Map?) ?? {}).entries) '${e.key}': '${e.value}'},
          for (final f in _sf) f: '${t[f] ?? ''}',
        };
        for (final f in _tf) _t('$k-$f', '${t[f] ?? ''}');
      }
      _t('sum$did', '${dm['summary'] ?? ''}');
      _t('cr$did-1', '${dm['credit_t1'] ?? ''}'); _t('cr$did-2', '${dm['credit_t2'] ?? ''}');
    }
    for (final a in ['awareness', 'sensitivity', 'creativity']) _sum[a] = '${card['summary_$a'] ?? ''}';
    _t('teacher_code', '${card['teacher_code'] ?? ''}');
    _t('final', '${card['final_annual_feedback'] ?? ''}');
    for (final a in _l(widget.d['attendance'])) {
      final m = asInt(a['month']);
      final savedW = asInt(a['working_days']);
      final wVal = savedW > 0 ? savedW : (defaultWorkingDays[m] ?? 0);
      _t('w$m', wVal == 0 ? '' : '$wVal');
      _t('p$m', asInt(a['days_present']) == 0 ? '' : '${a['days_present']}');
    }
  }

  static const defaultWorkingDays = {
    6: 14, 7: 25, 8: 24, 9: 23, 10: 21, 11: 15, 12: 24, 1: 24, 2: 23, 3: 24, 4: 22, 5: 0
  };

  void _autoFillWorkingDays() {
    setState(() {
      for (final a in _l(widget.d['attendance'])) {
        final m = asInt(a['month']);
        final defW = defaultWorkingDays[m] ?? 0;
        _t('w$m').text = defW > 0 ? '$defW' : '';
      }
    });
    toast(context, 'शासकीय प्रमाण कामाचे दिवस भरले');
  }

  @override void dispose() { for (final c in _c.values) c.dispose(); super.dispose(); }

  void _append(TextEditingController c, String v) => setState(() => c.text = c.text.trim().isEmpty ? v : '${c.text.trim()}\n$v');

  Future<void> _save(String status) async {
    setState(() => _saving = true);
    try {
      final body = <String, dynamic>{
        'action': 'fs_form_save', 'student_id': widget.student['id'], 'status': status,
        'teacher_code': _t('teacher_code').text.trim(), 'final_annual_feedback': _t('final').text.trim(),
        for (final a in _sum.entries) 'summary_${a.key}': a.value,
        for (final dm in domains) 'summary_domain_${dm['id']}': _t('sum${dm['id']}').text.trim(),
        'domains': [for (final dm in domains) {
          'id': asInt(dm['id']),
          for (final t in [1, 2]) 't$t': () {
            final s = _st['${dm['id']}-$t'] ?? {};
            return {
              'goals': [...(s['goals'] as Set? ?? {})], 'competencies': [...(s['competencies'] as List? ?? [])],
              'comp_activities': s['comp_activities'] ?? {},
              for (final f in _sf) f: s[f] ?? '',
              for (final f in _tf) f: _t('${dm['id']}-$t-$f').text.trim(),
            };
          }(),
        }],
        'attendance': [for (final a in _l(widget.d['attendance'])) {'month': a['month'],
          'working_days': asInt(_t('w${a['month']}').text.trim()), 'days_present': asInt(_t('p${a['month']}').text.trim())}],
        'credits': {for (final dm in domains) '${dm['id']}': {'t1': double.tryParse(_t('cr${dm['id']}-1').text.trim()) ?? 0, 't2': double.tryParse(_t('cr${dm['id']}-2').text.trim()) ?? 0}},
      };
      final ok = await Remote.save(_api, body);
      if (mounted) toast(context, !ok ? 'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.' : status == 'completed' ? '✓ HPC कार्ड पूर्ण झाले!' : 'HPC कार्ड मसुदा जतन झाला!');
      widget.reload();
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => _saving = false); }
  }

  Widget _h(String t, {Color clr = TG.g800}) => Padding(padding: const EdgeInsets.only(top: 14, bottom: 6),
    child: Text(t, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: clr)));
  Widget _field(TextEditingController c, String l, {int lines = 3, String? hint}) => Padding(padding: const EdgeInsets.only(bottom: 8),
    child: TextField(controller: c, maxLines: lines, minLines: 1, decoration: InputDecoration(labelText: l, hintText: hint, border: const OutlineInputBorder(), isDense: true)));
  Widget _gap() => const SizedBox(height: 8);

  Widget _levelRow(Map ab, String cur, ValueChanged<String> on, {bool desc = true}) {
    final ds = ((widget.d['descriptors'] as Map?)?[ab['key']] as Map?) ?? {};
    const clr = {'nirjhar': Color(0xFF0369A1), 'parvat': Color(0xFF047857), 'akash': Color(0xFFB45309)};
    const bg = {'nirjhar': Color(0xFFE0F2FE), 'parvat': Color(0xFFECFDF5), 'akash': Color(0xFFFEF3C7)};
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text('${ab['name_mr']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
      const SizedBox(height: 4),
      for (final lv in _l(widget.d['levels'])) Builder(builder: (_) {
        final k = '${lv['key']}'; final sel = cur == k;
        return InkWell(onTap: () => on(sel ? '' : k), child: Container(margin: const EdgeInsets.only(bottom: 6), padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: sel ? bg[k] : Colors.white, borderRadius: BorderRadius.circular(8),
            border: Border.all(color: sel ? clr[k]! : const Color(0xFFE5E7EB), width: sel ? 2 : 1)),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(sel ? Icons.radio_button_checked : Icons.radio_button_off, size: 20, color: clr[k]),
            const SizedBox(width: 8),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${lv['name_mr']}', style: TextStyle(fontWeight: FontWeight.w800, color: clr[k], fontSize: 13)),
              if (desc && ds[k] != null) Text('${ds[k]}', style: const TextStyle(fontSize: 12, height: 1.35, color: Colors.black87)),
            ])),
          ])));
      }),
      _gap(),
    ]);
  }

  Widget _emoji(String title, List<Map> opts, String cur, ValueChanged<String> on) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF2563A8))),
    const SizedBox(height: 6),
    Wrap(spacing: 8, runSpacing: 8, children: [
      for (final o in opts) ChoiceChip(
        avatar: Text('${o['icon']}', style: const TextStyle(fontSize: 20)),
        label: Text('${o['value']}', style: const TextStyle(fontSize: 13)),
        selected: cur == '${o['value']}', selectedColor: const Color(0xFFFFF3C4),
        onSelected: (v) => on(v ? '${o['value']}' : '')),
    ]),
    _gap(),
  ]);

  Widget _termForm(Map dm, int t) {
    final did = asInt(dm['id']); final k = '$did-$t';
    final s = _st.putIfAbsent(k, () => {'goals': <String>{}, 'competencies': <String>[], 'comp_activities': <String, String>{}, for (final f in _sf) f: ''});
    final goals = s['goals'] as Set<String>; final comps = s['competencies'] as List<String>;
    final acts = s['comp_activities'] as Map<String, String>;
    final allComps = _l(dm['competencies']);
    final clr = t == 1 ? const Color(0xFF2563A8) : const Color(0xFF15803D);
    final abhComps = comps.isNotEmpty ? comps : [if (allComps.isNotEmpty) '${allComps.first['code']}'];
    final abh = [for (final c in allComps) if (abhComps.contains('${c['code']}')) ..._s(c['abhipray'])];
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _h('🎯 अभ्यासक्रमाची ध्येये (Curricular Goals) – सत्र $t', clr: clr),
      for (final g in _l(dm['goals'])) CheckboxListTile(dense: true, contentPadding: EdgeInsets.zero, controlAffinity: ListTileControlAffinity.leading,
        value: goals.contains('${g['code']}'), title: Text('${g['code']}: ${g['text']}', style: const TextStyle(fontSize: 13)),
        onChanged: (v) => setState(() => v == true ? goals.add('${g['code']}') : goals.remove('${g['code']}'))),
      _h('✅ क्षमता (Competencies) – सत्र $t  (${comps.length} निवडल्या)', clr: clr),
      for (final c in allComps) Builder(builder: (_) {
        final code = '${c['code']}'; final on = comps.contains(code);
        return Container(margin: const EdgeInsets.only(bottom: 4), decoration: BoxDecoration(color: on ? clr.withValues(alpha: .05) : null, borderRadius: BorderRadius.circular(8)),
          child: Column(children: [
            CheckboxListTile(dense: true, contentPadding: EdgeInsets.zero, controlAffinity: ListTileControlAffinity.leading, value: on,
              title: Text.rich(TextSpan(children: [TextSpan(text: '$code  ', style: TextStyle(fontWeight: FontWeight.w800, color: clr)), TextSpan(text: '${c['text']}')]), style: const TextStyle(fontSize: 13)),
              onChanged: (v) => setState(() { if (v == true) { if (!on) comps.add(code); } else { comps.remove(code); acts.remove(code); } })),
            if (on) Padding(padding: const EdgeInsets.fromLTRB(8, 0, 8, 8), child: Column(children: [
              SheetPick(label: '📋 मूल्यांकनासाठी कृती/उपक्रम निवडा ($code)', hint: '-- कृती/उपक्रम निवडा --', options: _s(c['activities']), value: acts[code] ?? '',
                onPicked: (v) => setState(() => acts[code] = v)),
              _gap(),
              SheetPick(label: '❓ या क्षमतेसाठी मूल्यमापन प्रश्न निवडा ($code)', hint: '-- प्रश्न निवडा (खाली जोडण्यासाठी) --', options: _s(c['questions']),
                onPicked: (v) => _append(_t('$k-assessment_questions'), v)),
            ])),
          ]));
      }),
      _h('✏️ मूल्यमापनासाठी केलेली कृती/उपक्रम (Activity)', clr: clr),
      _field(_t('$k-activity'), 'उपक्रमाचे वर्णन', hint: 'उपक्रमाचे वर्णन लिहा... (किंवा वरील कृती निवडा)'),
      _h('❓ मूल्यमापनाचे प्रश्न (Assessment Questions)', clr: clr),
      SheetPick(label: '📋 या क्षेत्रातील आदर्श प्रश्न निवडा', hint: '-- प्रश्न निवडा --', options: _s(dm['sample_questions']),
        onPicked: (v) => _append(_t('$k-assessment_questions'), v)),
      _gap(),
      _field(_t('$k-assessment_questions'), 'मूल्यमापनाचे प्रश्न', lines: 4),
      _h('📊 कामगिरी स्तर (रुब्रिक) – निर्झर / पर्वत / आकाश', clr: clr),
      for (final ab in _l(widget.d['abilities'])) _levelRow(ab, '${s[ab['key']] ?? ''}', (v) => setState(() => s['${ab['key']}'] = v)),
      _h('💬 शिक्षकांचा अभिप्राय (Teacher Feedback)', clr: clr),
      SheetPick(label: 'अभिप्राय निवडा (${abhComps.join(', ')})', hint: '-- अभिप्राय निवडा --', options: abh,
        onPicked: (v) => setState(() => _t('$k-teacher_feedback').text = v)),
      _gap(),
      _field(_t('$k-teacher_feedback'), 'शिक्षकांचा अभिप्राय'),
      _h('😊 स्वयंमूल्यांकन (Self Assessment)', clr: clr),
      _emoji('विद्यार्थ्याला कसे वाटले?', _l(widget.d['self_emoji']), '${s['self_emoji']}', (v) => setState(() => s['self_emoji'] = v)),
      _field(_t('$k-self_assessment'), 'स्वयंमूल्यांकन नोंद', lines: 2),
      _h('🤝 सहाध्यायी मूल्यांकन (Peer Assessment)', clr: clr),
      _emoji('मित्राने काय केले?', _l(widget.d['peer_emoji']), '${s['peer_emoji']}', (v) => setState(() => s['peer_emoji'] = v)),
      if (_l(widget.d['classmates']).isNotEmpty) ...[
        SheetPick(
          label: '🤝 सहाध्यायी निवडा (Peer Student)',
          hint: '-- वर्गातील सहाध्यायी निवडा --',
          options: [for (final cm in _l(widget.d['classmates'])) '${cm['roll_no'] != null && '${cm['roll_no']}'.isNotEmpty ? '(${cm['roll_no']}) ' : ''}${cm['name']}'],
          onPicked: (v) => _append(_t('$k-peer_assessment'), 'सहाध्यायी: $v'),
        ),
        _gap(),
      ],
      _field(_t('$k-peer_assessment'), 'सहाध्यायी मूल्यांकन नोंद', lines: 2),
      _h('🏠 पालक/काळजीवाहक निरीक्षण (Parent Observation)', clr: clr),
      SheetPick(label: 'पालक निरीक्षण', hint: '-- पालक निरीक्षण निवडा --', options: _s(dm['parent_options']), value: '${s['parent_observation']}',
        onPicked: (v) => setState(() => s['parent_observation'] = v)),
      const SizedBox(height: 12),
    ]);
  }

  Widget _card(String title, List<Widget> children, {Widget? trailing}) => Card(margin: const EdgeInsets.only(bottom: 12), child: Padding(padding: const EdgeInsets.all(12),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Expanded(child: Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15.5, color: TG.inkA))),
        if (trailing != null) trailing,
      ]),
      const Divider(), ...children])));

  Widget _num(TextEditingController c, {String? hint}) => TextField(controller: c, textAlign: TextAlign.center,
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    decoration: InputDecoration(isDense: true, hintText: hint, border: const OutlineInputBorder(), contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8)));

  @override
  Widget build(BuildContext context) {
    final name = '${widget.student['name_mr'] ?? widget.student['name']}';
    final school = (widget.d['school'] as Map?) ?? {};
    final done = '${card['status']}' == 'completed';
    return Scaffold(
      appBar: AppBar(title: Text(name, overflow: TextOverflow.ellipsis), actions: [
        IconButton(
          tooltip: 'विद्यार्थी संपादन (माहिती, उंची, वजन)',
          icon: const Icon(Icons.manage_accounts_outlined),
          onPressed: () async {
            final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: Map<String, dynamic>.from(widget.student))));
            if (!context.mounted) return;
            if (ok == true) {
              toast(context, 'विद्यार्थी माहिती अद्ययावत झाली');
              widget.reload();
            }
          },
        ),
        IconButton(tooltip: '३-स्टेप रुब्रिक wizard', icon: const Icon(Icons.auto_fix_high),
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => HpcFoundationalScreen(student: widget.student))).then((_) => widget.reload())),
        if (asInt(card['id']) > 0) IconButton(tooltip: 'PDF प्रिंट', icon: const Icon(Icons.picture_as_pdf_outlined),
          onPressed: () => Remote.printReport(context, 'cards/print_hpc_19.php', {'id': card['id'], 'format': 'official19'}, name: name)),
      ]),
      body: ListView(padding: const EdgeInsets.fromLTRB(10, 10, 10, 110), children: [
        Container(
          padding: const EdgeInsets.all(12),
          margin: const EdgeInsets.only(bottom: 10),
          decoration: BoxDecoration(color: done ? const Color(0xFFECFDF5) : const Color(0xFFFFF7ED), borderRadius: BorderRadius.circular(10)),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('समग्र प्रगतिपत्रक (HPC) – पायाभूत स्तर · ${widget.d['academic_year'] ?? ''} · ${done ? '✓ पूर्ण' : '⏳ मसुदा'}',
                      style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 2),
                    Text('इ. ${widget.student['grade'] ?? ''} ${widget.student['section'] ?? ''} · हजेरी क्र. ${widget.student['roll_no'] ?? '-'} · GR ${widget.student['gr_no'] ?? '-'}',
                      style: TextStyle(fontSize: 12, color: TG.mutedA)),
                  ],
                ),
              ),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  visualDensity: VisualDensity.compact,
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                ),
                icon: const Icon(Icons.edit_outlined, size: 14),
                label: const Text('विद्यार्थी संपादन', style: TextStyle(fontSize: 12)),
                onPressed: () async {
                  final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: Map<String, dynamic>.from(widget.student))));
                  if (!context.mounted) return;
                  if (ok == true) {
                    toast(context, 'विद्यार्थी माहिती अद्ययावत झाली');
                    widget.reload();
                  }
                },
              ),
            ],
          ),
        ),
        _h('भाग B - विकास क्षेत्र मूल्यमापन (६ क्षेत्रे)'),
        for (final dm in domains) Card(margin: const EdgeInsets.only(bottom: 10), child: ExpansionTile(
          shape: const Border(), maintainState: true,
          title: Text('${dm['icon']} क्षेत्र क्र. ${dm['id']}: ${dm['name_mr']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5)),
          subtitle: Text(() {
            final a = _st['${dm['id']}-1']?['competencies'] as List? ?? []; final b = _st['${dm['id']}-2']?['competencies'] as List? ?? [];
            return 'सत्र १: ${a.length} क्षमता · सत्र २: ${b.length} क्षमता';
          }(), style: TextStyle(fontSize: 12, color: TG.mutedA)),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
          children: [
            SegmentedButton<int>(segments: const [ButtonSegment(value: 1, label: Text('📘 सत्र १')), ButtonSegment(value: 2, label: Text('📗 सत्र २'))],
              selected: {_term[asInt(dm['id'])] ?? 1}, onSelectionChanged: (v) => setState(() => _term[asInt(dm['id'])] = v.first)),
            _termForm(dm, _term[asInt(dm['id'])] ?? 1),
          ])),
        _card('भाग C - शैक्षणिक वर्षाचा सारांश', [
          for (final ab in _l(widget.d['abilities'])) Padding(padding: const EdgeInsets.only(bottom: 8), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${ab['name_mr']}:', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
            Wrap(spacing: 8, children: [for (final lv in _l(widget.d['levels'])) ChoiceChip(label: Text('${lv['name_mr']}'),
              selected: _sum['${ab['key']}'] == '${lv['key']}', onSelected: (v) => setState(() => _sum['${ab['key']}'] = v ? '${lv['key']}' : ''))]),
          ])),
          _h('प्रत्येक विकास क्षेत्राचा कामगिरी सारांश'),
          for (final dm in domains) ...[
            Text('${dm['icon']} क्षेत्र ${dm['id']} : ${dm['name_mr']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
            const SizedBox(height: 6),
            SheetPick(label: 'कामगिरी विधान निवडा (सामर्थ्य / सुधारणा)', hint: '-- विधान निवडा --',
              options: [for (final x in _s(dm['strengths'])) '💪 $x', for (final x in _s(dm['improvements'])) '📈 $x'],
              onPicked: (v) => _append(_t('sum${dm['id']}'), v.substring(v.indexOf(' ') + 1))),
            _gap(),
            _field(_t('sum${dm['id']}'), '${dm['name_mr']} चा कामगिरी सारांश'),
          ],
          _h('शिक्षकांचा अंतिम वार्षिक अभिप्राय'),
          SheetPick(label: '📋 नमुना वार्षिक अभिप्राय निवडा', hint: '-- नमुना अभिप्राय निवडा --', options: _s(widget.d['annual_samples']),
            onPicked: (v) => setState(() => _t('final').text = v)),
          _gap(),
          _field(_t('final'), 'अंतिम वार्षिक अभिप्राय', lines: 4),
        ]),
        _card('भाग A(1) - शाळेची माहिती', [
          _info('शाळेचे नाव', '${school['name'] ?? ''}'), _info('शाळेचा पत्ता', '${school['address'] ?? ''}'),
          _info('UDISE कोड', '${school['udise'] ?? ''}'), _info('शैक्षणिक वर्ष', '${widget.d['academic_year'] ?? ''}'),
          _gap(), _field(_t('teacher_code'), 'शिक्षक कोड (Teacher Code)', lines: 1),
        ]),
        _card('भाग A(2) - मासिक उपस्थिती', [
          const Row(children: [Expanded(flex: 3, child: Text('महिना', style: TextStyle(fontWeight: FontWeight.w700))),
            Expanded(flex: 2, child: Text('कामाचे दिवस', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12))),
            SizedBox(width: 8), Expanded(flex: 2, child: Text('उपस्थित दिवस', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)))]),
          const SizedBox(height: 6),
          for (final a in _l(widget.d['attendance'])) Padding(padding: const EdgeInsets.only(bottom: 6), child: Row(children: [
            Expanded(flex: 3, child: Text('${a['label']}')),
            Expanded(flex: 2, child: _num(_t('w${a['month']}'))), const SizedBox(width: 8),
            Expanded(flex: 2, child: _num(_t('p${a['month']}'))),
          ])),
        ], trailing: TextButton.icon(
          style: TextButton.styleFrom(visualDensity: VisualDensity.compact, padding: const EdgeInsets.symmetric(horizontal: 8)),
          icon: const Icon(Icons.auto_mode, size: 15),
          label: const Text('ऑटो दिवस भरा', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
          onPressed: _autoFillWorkingDays,
        )),
        _card('भाग C - क्रेडिट फ्रेमवर्क (NCrF) · क्रेडिट 4.5 · NCF स्तर 0.2 · कमाल गुण 0.90', [
          for (final dm in domains) Padding(padding: const EdgeInsets.only(bottom: 6), child: Row(children: [
            Expanded(flex: 3, child: Text('${dm['id']}. ${dm['name_mr']}', style: const TextStyle(fontSize: 13))),
            Expanded(flex: 2, child: _num(_t('cr${dm['id']}-1'), hint: 'सत्र १')), const SizedBox(width: 8),
            Expanded(flex: 2, child: _num(_t('cr${dm['id']}-2'), hint: 'सत्र २')),
          ])),
        ]),
      ]),
      bottomNavigationBar: SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(12, 6, 12, 10), child: Row(children: [
        Expanded(child: OutlinedButton.icon(onPressed: _saving ? null : () => _save('draft'), icon: const Icon(Icons.save_outlined), label: const Text('मसुदा जतन करा'))),
        const SizedBox(width: 10),
        Expanded(child: FilledButton.icon(style: FilledButton.styleFrom(backgroundColor: const Color(0xFF059669)),
          onPressed: _saving ? null : () => _save('completed'),
          icon: _saving ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.check_circle_outline),
          label: const Text('पूर्ण जतन करा'))),
      ]))),
    );
  }

  Widget _info(String l, String v) => Padding(padding: const EdgeInsets.only(bottom: 4),
    child: Row(children: [SizedBox(width: 110, child: Text(l, style: TextStyle(color: TG.mutedA, fontSize: 13))), Expanded(child: Text(v.isEmpty ? '-' : v, style: const TextStyle(fontWeight: FontWeight.w600)))]));
}
