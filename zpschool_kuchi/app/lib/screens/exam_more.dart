import 'package:flutter/material.dart';

import '../core/remote.dart';
import 'common.dart';
import 'widgets.dart';

const _api = 'exam_native.php';
String _today() => DateTime.now().toIso8601String().substring(0, 10);
List<Map> _items(Map d) => ((d['items'] as List?) ?? const []).cast<Map>();

Future<String?> _pickDate(BuildContext c, String cur) async {
  final d = await showDatePicker(context: c, initialDate: DateTime.tryParse(cur) ?? DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2035));
  return d?.toIso8601String().substring(0, 10);
}

// ═══════════════════════════ दैनिक गृहपाठ ═══════════════════════════════════
class HomeworkScreen extends StatefulWidget {
  const HomeworkScreen({super.key});
  @override State<HomeworkScreen> createState() => _HomeworkScreenState();
}
class _HomeworkScreenState extends State<HomeworkScreen> {
  int std = 0; String date = ''; int _v = 0;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('दैनिक गृहपाठ व Quiz')),
    floatingActionButton: FloatingActionButton.extended(icon: const Icon(Icons.add), label: const Text('नवीन गृहपाठ'),
      onPressed: () async { await Navigator.push(context, MaterialPageRoute(builder: (_) => const HomeworkForm())); setState(() => _v++); }),
    body: Column(children: [
      Padding(padding: const EdgeInsets.fromLTRB(12, 10, 12, 4), child: Row(children: [
        Expanded(child: Pick<int>(label: 'इयत्ता', value: std, items: [(0, 'सर्व इयत्ता'), for (var i = 1; i <= 8; i++) (i, stdLabel(i))], onChanged: (v) => setState(() => std = v ?? 0))),
        const SizedBox(width: 8),
        Expanded(child: OutlinedButton.icon(icon: const Icon(Icons.event), label: Text(date.isEmpty ? 'सर्व तारखा' : date),
          onPressed: () async { final d = await _pickDate(context, date); setState(() => date = d ?? ''); })),
      ])),
      Expanded(child: ApiLoader(key: ValueKey('$std$date$_v'), path: _api,
        query: {'action': 'homework_list', if (std > 0) 'standard': '$std', if (date.isNotEmpty) 'date': date},
        builder: (c, d, reload) {
          final rows = _items(d);
          if (rows.isEmpty) return const EmptyState('अजून गृहपाठ नाही. "नवीन गृहपाठ" दाबा.', icon: Icons.menu_book_outlined);
          return ListView.builder(padding: const EdgeInsets.fromLTRB(12, 4, 12, 90), itemCount: rows.length, itemBuilder: (_, i) {
            final h = rows[i];
            final items = (h['items'] as List?) ?? const [];
            final hasQuiz = asInt(h['quiz_id']) > 0;
            return Card(child: Padding(padding: const EdgeInsets.fromLTRB(14, 12, 6, 6), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('${h['title'] ?? 'गृहपाठ'} · ${h['subject'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
                Text('${h['hw_date'] ?? ''}', style: const TextStyle(fontSize: 12, color: Colors.black54)),
              ]),
              const SizedBox(height: 3),
              Text('${(h['std_label'] ?? '').toString().isNotEmpty ? h['std_label'] : stdLabel(asInt(h['standard']))}${(h['division'] ?? '').toString().isNotEmpty ? ' (${h['division']})' : ''} · ${h['topic'] ?? ''}',
                style: const TextStyle(fontSize: 13)),
              Text('${items.length} प्रश्न${hasQuiz ? ' · Quiz कोड ${h['quiz_code']} · ${h['attempts'] ?? 0} विद्यार्थी${h['avg_pct'] != null ? ' · सरासरी ${h['avg_pct']}%' : ''}' : ''}',
                style: const TextStyle(fontSize: 12, color: Colors.black54)),
              Wrap(children: [
                TextButton.icon(icon: const Icon(Icons.picture_as_pdf_outlined, size: 18), label: const Text('गृहपाठ'),
                  onPressed: () => Remote.printReport(c, 'modules/exam_paper/homework_view.php', {'id': h['hw_id']}, name: 'गृहपाठ')),
                TextButton.icon(icon: const Icon(Icons.key_outlined, size: 18), label: const Text('उत्तरसूची'),
                  onPressed: () => Remote.printReport(c, 'modules/exam_paper/homework_key.php', {'id': h['hw_id']}, name: 'उत्तरसूची')),
                if (hasQuiz) TextButton.icon(icon: const Icon(Icons.leaderboard_outlined, size: 18), label: const Text('Quiz निकाल'),
                  onPressed: () => Remote.printReport(c, 'modules/exam_paper/quiz_results.php', {'id': h['quiz_id']}, name: 'Quiz निकाल')),
                IconButton(icon: const Icon(Icons.edit_outlined), tooltip: 'बदला', onPressed: () async {
                  await Navigator.push(c, MaterialPageRoute(builder: (_) => HomeworkForm(existing: h))); reload(); }),
                IconButton(icon: const Icon(Icons.delete_outline, color: Color(0xFFB3261E)), tooltip: 'काढा', onPressed: () async {
                  final ok = await showDialog<bool>(context: c, builder: (dc) => AlertDialog(title: const Text('गृहपाठ काढायचा?'),
                    content: const Text('यासोबतचा Quiz व त्याचे निकालही काढले जातील.'),
                    actions: [TextButton(onPressed: () => Navigator.pop(dc, false), child: const Text('नाही')), FilledButton(onPressed: () => Navigator.pop(dc, true), child: const Text('हो, काढा'))]));
                  if (ok != true) return;
                  try { await Remote.post(_api, {'action': 'delete_homework', 'hw_id': h['hw_id']}); reload(); }
                  catch (e) { if (c.mounted) toast(c, '$e', error: true); }
                }),
              ]),
            ])));
          });
        })),
    ]),
  );
}

class HomeworkForm extends StatefulWidget {
  const HomeworkForm({super.key, this.existing});
  final Map? existing;
  @override State<HomeworkForm> createState() => _HomeworkFormState();
}
class _HomeworkFormState extends State<HomeworkForm> {
  List<Map> tree = [];
  bool loading = true, saving = false, quizOn = false, showAns = true, autoBusy = false;
  int std = 0, chapter = 0, quizCount = 10;
  String subjKey = '', date = _today();
  final c = {for (final k in ['division', 'std_label', 'topic', 'teacher', 'title', 'note', 'quiz_title', 'quiz_time']) k: TextEditingController()};
  final items = <Map<String, dynamic>>[];
  final quiz = <Map<String, dynamic>>[];

  @override void initState() {
    super.initState();
    final e = widget.existing;
    if (e != null) {
      std = asInt(e['standard']); chapter = asInt(e['chapter_id']); date = '${e['hw_date'] ?? _today()}';
      for (final k in ['division', 'std_label', 'topic', 'teacher', 'title', 'note']) { c[k]!.text = '${e[k] ?? ''}'; }
      items.addAll([for (final i in (e['items'] as List? ?? const [])) Map<String, dynamic>.from(i as Map)]);
      quiz.addAll([for (final q in (e['quiz_questions'] as List? ?? const [])) Map<String, dynamic>.from(q as Map)]);
      quizOn = quiz.isNotEmpty;
      c['quiz_title']!.text = '${e['quiz_title'] ?? ''}'; c['quiz_time']!.text = '${e['time_limit'] ?? ''}';
      showAns = '${e['show_answers'] ?? 1}' != '0';
      subjKey = '${e['subject']}|${e['medium']}';
    } else { c['title']!.text = 'गृहपाठ'; }
    Remote.get(_api, {'action': 'book_tree'}).then((d) { if (mounted) setState(() { tree = _items(d); loading = false; _fix(); }); })
      .catchError((Object e) { if (mounted) { setState(() => loading = false); toast(context, '$e', error: true); } });
  }
  @override void dispose() { for (final x in c.values) { x.dispose(); } super.dispose(); }

  Map? get _std => tree.where((s) => asInt(s['standard']) == std).firstOrNull;
  List<Map> get _subjects => ((_std?['subjects'] as List?) ?? const []).cast<Map>();
  Map? get _subj => _subjects.where((s) => '${s['subject']}|${s['medium']}' == subjKey).firstOrNull;
  List<Map> get _chapters => [for (final b in ((_subj?['books'] as List?) ?? const []).cast<Map>()) ...((b['chapters'] as List?) ?? const []).cast<Map>()];
  Map? get _chap => _chapters.where((x) => asInt(x['chapter_id']) == chapter).firstOrNull;
  void _fix() {
    if (tree.isEmpty) return;
    if (_std == null) std = asInt(tree.first['standard']);
    if (_subj == null && _subjects.isNotEmpty) subjKey = '${_subjects.first['subject']}|${_subjects.first['medium']}';
    if (_chap == null && _chapters.isNotEmpty) chapter = asInt(_chapters.first['chapter_id']);
    if (c['std_label']!.text.isEmpty) c['std_label']!.text = stdLabel(std);
  }

  Future<void> _fromBook() async {
    final picked = await showModalBottomSheet<List<Map>>(context: context, isScrollControlled: true, showDragHandle: true,
      builder: (_) => _BookPicker(chapterId: chapter, used: {for (final i in items) asInt(i['bq_id'])}));
    if (picked == null) return;
    setState(() => items.addAll([for (final q in picked) {'bq_id': q['bq_id'], 'text': '${q['text'] ?? ''}', 'answer': '${q['answer'] ?? ''}',
      'page_image': q['page_image'], 'show_image': false, 'qtype': q['qtype']}]));
  }

  Future<void> _autoQuiz() async {
    final s = _subj; final ch = _chap;
    if (s == null) return;
    setState(() => autoBusy = true);
    try {
      final src = _items(await Remote.get(_api, {'action': 'quiz_sources', 'standard': '$std', 'subject': '${s['subject']}', 'medium': '${s['medium']}', 'chapter': '${ch?['title'] ?? c['topic']!.text}'}));
      final chs = src.isEmpty ? const <Map>[] : ((src.first['chapters'] as List?) ?? const []).cast<Map>().where((x) => asInt(x['mcq']) > 0).toList();
      if (chs.isEmpty) { if (mounted) toast(context, 'या धड्यासाठी MCQ प्रश्न सापडले नाहीत', error: true); return; }
      final pool = _items(await Remote.post(_api, {'action': 'quiz_mcq', 'chapter_ids': [chs.first['chapter_id']], 'count': quizCount,
        'exclude': [for (final q in quiz) if (q['question_id'] != null) q['question_id']]}));
      setState(() {
        quiz.addAll([for (final q in pool.take(quizCount)) Map<String, dynamic>.from(q)]);
        if (c['quiz_title']!.text.isEmpty) c['quiz_title']!.text = 'Quiz - ${c['topic']!.text.isEmpty ? (ch?['title'] ?? '') : c['topic']!.text}';
      });
      if (mounted) {
        toast(context, pool.isEmpty
            ? 'या धड्यात पर्याय (A/B/C/D) असलेले MCQ नाहीत. दुसरा धडा निवडा किंवा प्रश्न स्वतः लिहा.'
            : '${pool.length} MCQ जोडले (${src.first['subject_name']}: ${chs.first['name']})', error: pool.isEmpty);
      }
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => autoBusy = false); }
  }

  Future<void> _save() async {
    final s = _subj;
    if (s == null) { toast(context, 'इयत्ता व विषय निवडा', error: true); return; }
    final its = items.where((i) => '${i['text']}'.trim().isNotEmpty).toList();
    if (its.isEmpty && c['note']!.text.trim().isEmpty) { toast(context, 'किमान एक प्रश्न किंवा सूचना लिहा', error: true); return; }
    if (quizOn) {
      final bad = quiz.indexWhere((q) => '${q['text']}'.trim().isEmpty || (q['kind'] == 'mcq' ? ((q['options'] as List?) ?? []).where((o) => '$o'.trim().isNotEmpty).length < 2 : '${q['answer'] ?? ''}'.trim().isEmpty));
      if (bad >= 0) { toast(context, 'Quiz प्रश्न ${bad + 1} अपूर्ण आहे (प्रश्न व पर्याय/उत्तर लिहा)', error: true); return; }
    }
    setState(() => saving = true);
    try {
      final r = await Remote.post(_api, {
        'action': 'save_homework', 'hw_id': widget.existing?['hw_id'], 'quiz_id': widget.existing?['quiz_id'],
        'hw_date': date, 'standard': std, 'division': c['division']!.text.trim(), 'std_label': c['std_label']!.text.trim(),
        'subject': s['subject'], 'medium': s['medium'], 'chapter_id': chapter > 0 ? chapter : null, 'topic': c['topic']!.text.trim(),
        'teacher': c['teacher']!.text.trim(), 'title': c['title']!.text.trim(), 'note': c['note']!.text.trim(), 'items': its,
        'quiz': quizOn ? {'title': c['quiz_title']!.text.trim(), 'time_limit': asInt(c['quiz_time']!.text.trim()), 'show_answers': showAns, 'questions': quiz} : {'questions': []},
      });
      if (r['success'] == false || r['status'] == 'error') throw '${r['message'] ?? 'जतन झाले नाही'}';
      if (!mounted) return;
      toast(context, 'गृहपाठ जतन झाला');
      await Remote.printReport(context, 'modules/exam_paper/homework_view.php', {'id': r['hw_id']}, name: 'गृहपाठ');
      if (mounted) Navigator.pop(context);
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => saving = false); }
  }

  Widget _tf(String k, String l, {int lines = 1, TextInputType? kb}) => Padding(padding: const EdgeInsets.only(bottom: 10),
    child: TextField(controller: c[k], maxLines: lines, minLines: 1, keyboardType: kb, decoration: InputDecoration(labelText: l)));
  Widget _h(String t) => Padding(padding: const EdgeInsets.fromLTRB(2, 16, 2, 8), child: Text(t, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF2563A8))));

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.existing == null ? 'नवीन गृहपाठ' : 'गृहपाठ बदला')),
    bottomNavigationBar: SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: FilledButton.icon(onPressed: saving || loading ? null : _save,
      icon: saving ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.save_outlined),
      label: const Text('जतन करा व PDF')))),
    body: loading ? const Center(child: CircularProgressIndicator()) : ListView(padding: const EdgeInsets.fromLTRB(14, 6, 14, 24), children: [
      _h('१. वर्ग व धडा'),
      OutlinedButton.icon(icon: const Icon(Icons.event), label: Text('तारीख: $date'), onPressed: () async { final d = await _pickDate(context, date); if (d != null) setState(() => date = d); }),
      const SizedBox(height: 10),
      Row(children: [
        Expanded(child: Pick<int>(label: 'इयत्ता', value: std, items: [for (final s in tree) (asInt(s['standard']), stdLabel(asInt(s['standard'])))],
          onChanged: (v) => setState(() { std = v ?? std; subjKey = ''; chapter = 0; c['std_label']!.text = ''; _fix(); }))),
        const SizedBox(width: 8),
        SizedBox(width: 90, child: TextField(controller: c['division'], decoration: const InputDecoration(labelText: 'तुकडी'))),
      ]),
      const SizedBox(height: 10),
      Pick<String>(label: 'विषय', value: subjKey, items: [for (final s in _subjects) ('${s['subject']}|${s['medium']}', '${s['name']}')],
        onChanged: (v) => setState(() { subjKey = v ?? subjKey; chapter = 0; _fix(); })),
      const SizedBox(height: 10),
      Pick<int>(label: 'धडा', value: chapter, items: [for (final x in _chapters) (asInt(x['chapter_id']), '${x['no']}. ${x['title']}')],
        onChanged: (v) => setState(() { chapter = v ?? chapter; c['topic']!.text = '${_chap?['title'] ?? ''}'; })),
      const SizedBox(height: 10),
      _tf('topic', 'घटक / विषयभाग'), _tf('std_label', 'इयत्ता लेबल (PDF वर)'), _tf('teacher', 'शिक्षक'), _tf('title', 'शीर्षक'), _tf('note', 'सूचना', lines: 2),
      _h('२. गृहपाठ प्रश्न (${items.length})'),
      Wrap(spacing: 8, children: [
        FilledButton.tonalIcon(icon: const Icon(Icons.menu_book_outlined), label: const Text('पुस्तकातून निवडा'), onPressed: chapter > 0 ? _fromBook : null),
        OutlinedButton.icon(icon: const Icon(Icons.add), label: const Text('स्वतः लिहा'), onPressed: () => setState(() => items.add({'text': '', 'answer': ''}))),
      ]),
      for (var i = 0; i < items.length; i++) Card(key: ObjectKey(items[i]), child: Padding(padding: const EdgeInsets.fromLTRB(10, 4, 4, 8), child: Column(children: [
        Row(children: [
          CircleAvatar(radius: 12, child: Text('${i + 1}', style: const TextStyle(fontSize: 11))),
          const Spacer(),
          if (items[i]['page_image'] != null) Row(children: [const Text('चित्र', style: TextStyle(fontSize: 12)),
            Checkbox(value: items[i]['show_image'] == true, onChanged: (v) => setState(() => items[i]['show_image'] = v == true))]),
          IconButton(icon: const Icon(Icons.close, size: 18), onPressed: () => setState(() => items.removeAt(i))),
        ]),
        TextFormField(initialValue: '${items[i]['text'] ?? ''}', maxLines: null, decoration: const InputDecoration(labelText: 'प्रश्न'), onChanged: (v) => items[i]['text'] = v),
        const SizedBox(height: 6),
        TextFormField(initialValue: '${items[i]['answer'] ?? ''}', maxLines: null, decoration: const InputDecoration(labelText: 'उत्तर (उत्तरसूचीसाठी)'), onChanged: (v) => items[i]['answer'] = v),
      ]))),
      _h('३. ऑनलाईन Quiz'),
      SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('गृहपाठासोबत Quiz जोडा'), subtitle: const Text('विद्यार्थी कोड टाकून मोबाईलवर सोडवतील'),
        value: quizOn, onChanged: (v) => setState(() => quizOn = v)),
      if (quizOn) ...[
        _tf('quiz_title', 'Quiz शीर्षक'),
        Row(children: [
          Expanded(child: _tf('quiz_time', 'वेळ (मिनिटे, 0 = मर्यादा नाही)', kb: TextInputType.number)),
          const SizedBox(width: 8),
          SizedBox(width: 110, child: Pick<int>(label: 'प्रश्न', value: quizCount, items: const [(5, '5'), (10, '10'), (15, '15'), (20, '20')], onChanged: (v) => setState(() => quizCount = v ?? 10))),
        ]),
        CheckboxListTile(contentPadding: EdgeInsets.zero, title: const Text('सबमिट नंतर बरोबर उत्तरे दाखवा'), value: showAns, onChanged: (v) => setState(() => showAns = v == true)),
        Wrap(spacing: 8, children: [
          FilledButton.tonalIcon(icon: autoBusy ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.auto_awesome),
            label: const Text('आपोआप MCQ'), onPressed: autoBusy ? null : _autoQuiz),
          OutlinedButton.icon(icon: const Icon(Icons.add), label: const Text('MCQ लिहा'), onPressed: () => setState(() => quiz.add({'kind': 'mcq', 'text': '', 'options': ['', '', '', ''], 'answer': 0}))),
          OutlinedButton.icon(icon: const Icon(Icons.short_text), label: const Text('एका शब्दात'), onPressed: () => setState(() => quiz.add({'kind': 'text', 'text': '', 'answer': ''}))),
        ]),
        for (var i = 0; i < quiz.length; i++) _QuizEdit(key: ObjectKey(quiz[i]), n: i + 1, q: quiz[i], onDelete: () => setState(() => quiz.removeAt(i))),
      ],
    ]),
  );
}

class _QuizEdit extends StatefulWidget {
  const _QuizEdit({super.key, required this.n, required this.q, required this.onDelete});
  final int n; final Map<String, dynamic> q; final VoidCallback onDelete;
  @override State<_QuizEdit> createState() => _QuizEditState();
}
class _QuizEditState extends State<_QuizEdit> {
  @override
  Widget build(BuildContext context) {
    final q = widget.q; final mcq = q['kind'] == 'mcq';
    final opts = mcq ? (q['options'] = [for (final o in (q['options'] as List? ?? const [])) '$o']) as List : const [];
    return Card(child: Padding(padding: const EdgeInsets.fromLTRB(10, 4, 4, 8), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Text('Q${widget.n} · ${mcq ? 'MCQ' : 'एका शब्दात'}', style: const TextStyle(fontWeight: FontWeight.w700)),
        const Spacer(), IconButton(icon: const Icon(Icons.close, size: 18), onPressed: widget.onDelete),
      ]),
      TextFormField(initialValue: '${q['text'] ?? ''}', maxLines: null, decoration: const InputDecoration(labelText: 'प्रश्न'), onChanged: (v) => q['text'] = v),
      if (q['image_url'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text('🖼 चित्रासह प्रश्न', style: TextStyle(fontSize: 12, color: Colors.grey.shade700))),
      if (mcq) for (var j = 0; j < opts.length; j++) Row(children: [
        Radio<int>(value: j, groupValue: asInt(q['answer']), onChanged: (v) => setState(() => q['answer'] = v ?? 0)),
        Expanded(child: TextFormField(initialValue: '${opts[j]}', decoration: InputDecoration(isDense: true, hintText: 'पर्याय ${j + 1}'), onChanged: (v) => opts[j] = v)),
      ]) else Padding(padding: const EdgeInsets.only(top: 6), child: TextFormField(initialValue: '${q['answer'] ?? ''}', decoration: const InputDecoration(labelText: 'अपेक्षित उत्तर'), onChanged: (v) => q['answer'] = v)),
    ])));
  }
}

class _BookPicker extends StatefulWidget {
  const _BookPicker({required this.chapterId, required this.used});
  final int chapterId; final Set<int> used;
  @override State<_BookPicker> createState() => _BookPickerState();
}
class _BookPickerState extends State<_BookPicker> {
  final sel = <int, Map>{}; final search = TextEditingController();
  int page = 0, total = 0; bool busy = true; List<Map> rows = [];
  @override void initState() { super.initState(); _load(); }
  Future<void> _load() async {
    setState(() => busy = true);
    try {
      final d = await Remote.post(_api, {'action': 'book_browse', 'chapter_ids': [widget.chapterId], 'search': search.text.trim(), 'page': page});
      setState(() { rows = _items(d); total = asInt(d['total']); });
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => busy = false); }
  }
  @override
  Widget build(BuildContext context) => SizedBox(height: MediaQuery.of(context).size.height * .85, child: Column(children: [
    Padding(padding: const EdgeInsets.fromLTRB(12, 0, 12, 6), child: TextField(controller: search, textInputAction: TextInputAction.search,
      decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'स्वाध्याय प्रश्न शोधा', isDense: true),
      onSubmitted: (_) { page = 0; _load(); })),
    Expanded(child: busy ? const Center(child: CircularProgressIndicator()) : rows.isEmpty ? const EmptyState('प्रश्न सापडले नाहीत', icon: Icons.search_off)
      : ListView(children: [for (final q in rows) Builder(builder: (_) {
          final id = asInt(q['bq_id']); final used = widget.used.contains(id);
          return CheckboxListTile(value: used || sel.containsKey(id), onChanged: used ? null : (v) => setState(() => v == true ? sel[id] = q : sel.remove(id)),
            title: Text('${q['text'] ?? ''}', style: const TextStyle(fontSize: 13.5)),
            subtitle: Text('पान ${q['page'] ?? '-'} · ${q['qtype'] ?? ''}${(q['answer'] ?? '').toString().isNotEmpty ? ' · उत्तर: ${q['answer']}' : ''}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)));
        })])),
    SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(8, 4, 12, 8), child: Row(children: [
      IconButton(icon: const Icon(Icons.chevron_left), onPressed: page > 0 ? () { page--; _load(); } : null),
      Text('${page * 20 + 1}–${(page * 20 + rows.length)} / $total'),
      IconButton(icon: const Icon(Icons.chevron_right), onPressed: (page + 1) * 20 < total ? () { page++; _load(); } : null),
      const Spacer(),
      FilledButton(onPressed: sel.isEmpty ? null : () => Navigator.pop(context, sel.values.toList()), child: Text('${sel.length} जोडा')),
    ]))),
  ]));
}

// ═══════════════════════════ स्पर्धा परीक्षा पेपर ═════════════════════════════
class CompetitiveScreen extends StatefulWidget {
  const CompetitiveScreen({super.key});
  @override State<CompetitiveScreen> createState() => _CompetitiveScreenState();
}
class _CompetitiveScreenState extends State<CompetitiveScreen> {
  int _v = 0;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('स्पर्धा परीक्षा पेपर')),
    floatingActionButton: FloatingActionButton.extended(icon: const Icon(Icons.add), label: const Text('नवीन पेपर'),
      onPressed: () async { await Navigator.push(context, MaterialPageRoute(builder: (_) => const CompetitiveForm())); setState(() => _v++); }),
    body: ApiLoader(key: ValueKey(_v), path: _api, query: const {'action': 'competitive_list'}, builder: (c, d, reload) {
      final rows = _items(d);
      if (rows.isEmpty) return const EmptyState('अजून स्पर्धा परीक्षा पेपर नाही.', icon: Icons.emoji_events_outlined);
      return ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 90), children: [for (final p in rows) Card(child: Padding(padding: const EdgeInsets.fromLTRB(14, 12, 6, 4),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${p['title']}', style: const TextStyle(fontWeight: FontWeight.w800)),
          Text('${p['exam_name'] ?? ''} · ${p['total_marks'] ?? ''} गुण${p['exam_date'] != null ? ' · ${p['exam_date']}' : ''}', style: const TextStyle(fontSize: 12, color: Colors.black54)),
          Wrap(children: [
            TextButton.icon(icon: const Icon(Icons.picture_as_pdf_outlined, size: 18), label: const Text('पेपर'),
              onPressed: () => Remote.printReport(c, 'modules/exam_paper/paper_view.php', {'id': p['paper_id']}, name: '${p['title']}')),
            TextButton.icon(icon: const Icon(Icons.key_outlined, size: 18), label: const Text('उत्तरसूची'),
              onPressed: () => Remote.printReport(c, 'modules/exam_paper/paper_view.php', {'id': p['paper_id'], 'answers': 1}, name: 'उत्तरसूची')),
            TextButton.icon(icon: const Icon(Icons.grid_on_outlined, size: 18), label: const Text('OMR'),
              onPressed: () => Remote.printReport(c, 'modules/exam_paper/omr_view.php', {'id': p['paper_id']}, name: 'OMR')),
            TextButton.icon(icon: const Icon(Icons.phone_android, size: 18), label: const Text('ऑनलाईन Quiz'), onPressed: () => _quiz(c, p['paper_id'])),
          ]),
        ])))]);
    }),
  );
}

Future<void> _quiz(BuildContext c, dynamic paperId) async {
  try {
    final r = await Remote.post(_api, {'action': 'create_quiz_from_paper', 'paper_id': paperId});
    if (r['success'] == false) throw '${r['message'] ?? 'Quiz तयार झाला नाही'}';
    if (!c.mounted) return;
    await showDialog(context: c, builder: (dc) => AlertDialog(title: const Text('ऑनलाईन Quiz तयार'),
      content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('विद्यार्थी कोड:'), SelectableText('${r['code']}', style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, letterSpacing: 2)),
        if (r['quiz_url'] != null) SelectableText('${r['quiz_url']}', style: const TextStyle(fontSize: 12)),
      ]),
      actions: [TextButton(onPressed: () => Navigator.pop(dc), child: const Text('ठीक'))]));
  } catch (e) { if (c.mounted) toast(c, '$e', error: true); }
}

class CompetitiveForm extends StatefulWidget {
  const CompetitiveForm({super.key});
  @override State<CompetitiveForm> createState() => _CompetitiveFormState();
}
class _CompetitiveFormState extends State<CompetitiveForm> {
  Map<String, Map> patterns = {}; List<Map> tree = [];
  String pat = ''; bool loading = true, saving = false, filling = false;
  final secs = <Map<String, dynamic>>[];
  final c = {for (final k in ['title', 'exam_name', 'std_label', 'exam_date', 'duration', 'total_marks', 'instructions']) k: TextEditingController()};

  @override void initState() {
    super.initState();
    Future.wait([Remote.get(_api, {'action': 'exam_patterns'}), Remote.get(_api, {'action': 'source_tree'})]).then((r) {
      if (!mounted) return;
      setState(() {
        patterns = {for (final e in r[0].entries) if (e.value is Map) e.key: e.value as Map};
        tree = _items(r[1]); loading = false;
      });
    }).catchError((Object e) { if (mounted) { setState(() => loading = false); toast(context, '$e', error: true); } });
  }
  @override void dispose() { for (final x in c.values) { x.dispose(); } super.dispose(); }

  Map? _subj(dynamic stdId, dynamic subId) {
    for (final s in tree) { if ('${s['standard_id']}' == '$stdId') { for (final su in (s['subjects'] as List).cast<Map>()) { if ('${su['subject_id']}' == '$subId') return su; } } }
    return null;
  }
  (dynamic, dynamic) _guess(List hs, List hj) {
    final stds = tree.where((s) => hs.isEmpty || hs.any((h) => '${s['standard_id']}' == '$h' || '${s['name']}'.trim() == '$h' || '${s['name']}'.contains('$h'))).toList();
    for (final h in hj) { for (final st in stds) {
      final su = (st['subjects'] as List).cast<Map>().where((x) => '${x['name']}'.toLowerCase().contains('$h'.toLowerCase())).firstOrNull;
      if (su != null) return (st['standard_id'], su['subject_id']);
    } }
    final st = stds.isNotEmpty ? stds.first : (tree.isNotEmpty ? tree.first : null);
    final subs = (st?['subjects'] as List?) ?? const [];
    return (st?['standard_id'], subs.isNotEmpty ? (subs.first as Map)['subject_id'] : null);
  }
  void _applyPattern(String k) {
    final p = patterns[k]!;
    pat = k;
    c['title']!.text = '${p['name'] ?? ''}'; c['exam_name']!.text = '${p['group'] ?? ''}'; c['std_label']!.text = '${p['std_label'] ?? ''}';
    c['duration']!.text = '${p['duration'] ?? ''}'; c['total_marks']!.text = '${p['total_marks'] ?? ''}';
    secs..clear()..addAll([for (final s in ((p['sections'] as List?) ?? const []).cast<Map>()) () {
      final g = _guess((s['source_std'] as List?) ?? const [], (s['source_subject'] as List?) ?? const []);
      final su = _subj(g.$1, g.$2);
      return <String, dynamic>{'title': '${s['title'] ?? ''}', 'subtitle': '${s['subtitle'] ?? ''}', 'count': asInt(s['count'], 10), 'marks_per_q': p['marks_per_q'] ?? 1,
        'standard_id': g.$1, 'subject_id': g.$2, 'chapter_ids': [for (final ch in ((su?['chapters'] as List?) ?? const []).cast<Map>()) ch['chapter_id']], 'questions': <Map>[]};
    }()]);
  }
  List<int> get _used => [for (final s in secs) for (final q in (s['questions'] as List<Map>)) asInt(q['question_id'])];

  Future<void> _fill({bool reset = false}) async {
    setState(() => filling = true);
    try {
      for (final s in secs) {
        final qs = s['questions'] as List<Map>;
        if (reset) qs.clear();
        final need = asInt(s['count']) - qs.length;
        if (need <= 0 || (s['chapter_ids'] as List).isEmpty) continue;
        final got = _items(await Remote.post(_api, {'action': 'random', 'chapter_ids': s['chapter_ids'], 'type': 'mcq', 'qno': '', 'count': need, 'exclude': _used}));
        qs.addAll(got);
      }
      final short = secs.where((s) => (s['questions'] as List).length < asInt(s['count'])).length;
      if (mounted) toast(context, short == 0 ? 'सर्व विभागांत प्रश्न भरले' : '$short विभागांत पुरेसे प्रश्न नाहीत — स्रोत विषय बदला', error: short > 0);
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => filling = false); }
  }

  Future<void> _save({bool quiz = false}) async {
    if (secs.isEmpty || _used.isEmpty) { toast(context, 'आधी "प्रश्न भरा" दाबा', error: true); return; }
    setState(() => saving = true);
    try {
      final r = await Remote.post(_api, {'action': 'save_competitive', 'exam_name': c['exam_name']!.text, 'std_label': c['std_label']!.text, 'title': c['title']!.text,
        'exam_date': c['exam_date']!.text, 'duration': c['duration']!.text, 'total_marks': c['total_marks']!.text, 'instructions': c['instructions']!.text, 'format': 'standard',
        'sections': [for (final s in secs) {'title': s['title'], 'subtitle': s['subtitle'], 'count': s['count'], 'marks_per_q': s['marks_per_q'], 'chapter_ids': s['chapter_ids'],
          'question_ids': [for (final q in (s['questions'] as List<Map>)) q['question_id']]}]});
      if (r['success'] == false || r['status'] == 'error') throw '${r['message'] ?? 'जतन झाले नाही'}';
      if (!mounted) return;
      toast(context, 'पेपर जतन झाला');
      if (quiz) { await _quiz(context, r['paper_id']); }
      else { await Remote.printReport(context, 'modules/exam_paper/paper_view.php', {'id': r['paper_id']}, name: c['title']!.text); }
      if (mounted) Navigator.pop(context);
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => saving = false); }
  }

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<String>>{};
    for (final e in patterns.entries) { groups.putIfAbsent('${e.value['group'] ?? 'इतर'}', () => []).add(e.key); }
    return Scaffold(
      appBar: AppBar(title: const Text('नवीन स्पर्धा परीक्षा पेपर')),
      bottomNavigationBar: secs.isEmpty ? null : SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: Row(children: [
        Expanded(child: OutlinedButton.icon(onPressed: saving ? null : () => _save(quiz: true), icon: const Icon(Icons.phone_android), label: const Text('जतन + Quiz'))),
        const SizedBox(width: 8),
        Expanded(child: FilledButton.icon(onPressed: saving ? null : _save, icon: const Icon(Icons.save_outlined), label: const Text('जतन व PDF'))),
      ]))),
      body: loading ? const Center(child: CircularProgressIndicator()) : ListView(padding: const EdgeInsets.fromLTRB(14, 8, 14, 24), children: [
        const Text('१. परीक्षा नमुना निवडा', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF2563A8))),
        const SizedBox(height: 8),
        Pick<String>(label: 'नमुना', value: pat, items: [('', '— निवडा —'), for (final g in groups.entries) for (final k in g.value) (k, '${patterns[k]!['name']}')],
          onChanged: (v) => setState(() { if ((v ?? '').isNotEmpty) _applyPattern(v!); })),
        if (secs.isNotEmpty) ...[
          const SizedBox(height: 12),
          for (final e in [('title', 'शीर्षक'), ('exam_name', 'परीक्षा'), ('std_label', 'इयत्ता लेबल'), ('duration', 'वेळ'), ('total_marks', 'एकूण गुण'), ('instructions', 'सूचना')])
            Padding(padding: const EdgeInsets.only(bottom: 10), child: TextField(controller: c[e.$1], maxLines: e.$1 == 'instructions' ? 3 : 1, minLines: 1, decoration: InputDecoration(labelText: e.$2))),
          OutlinedButton.icon(icon: const Icon(Icons.event), label: Text(c['exam_date']!.text.isEmpty ? 'परीक्षा तारीख' : c['exam_date']!.text),
            onPressed: () async { final d = await _pickDate(context, c['exam_date']!.text); if (d != null) setState(() => c['exam_date']!.text = d); }),
          const SizedBox(height: 14),
          Row(children: [
            const Expanded(child: Text('२. विभाग व प्रश्न', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF2563A8)))),
            FilledButton.tonalIcon(onPressed: filling ? null : () => _fill(reset: true),
              icon: filling ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.auto_awesome), label: const Text('प्रश्न भरा')),
          ]),
          for (final s in secs) _SecCard(s: s, tree: tree, subjOf: _subj, onChanged: () => setState(() {})),
        ],
      ]),
    );
  }
}

class _SecCard extends StatelessWidget {
  const _SecCard({required this.s, required this.tree, required this.subjOf, required this.onChanged});
  final Map<String, dynamic> s; final List<Map> tree; final Map? Function(dynamic, dynamic) subjOf; final VoidCallback onChanged;
  @override
  Widget build(BuildContext context) {
    final qs = s['questions'] as List<Map>;
    final std = tree.where((t) => '${t['standard_id']}' == '${s['standard_id']}').firstOrNull;
    final ok = qs.length >= asInt(s['count']);
    return Card(child: ExpansionTile(
      title: Text('${s['title']} — ${s['subtitle']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
      subtitle: Text('${qs.length} / ${s['count']} प्रश्न · ${s['marks_per_q']} गुण प्रत्येकी', style: TextStyle(fontSize: 12, color: ok ? const Color(0xFF059669) : const Color(0xFFB45309))),
      childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
      children: [
        Row(children: [
          Expanded(child: Pick<String>(dense: true, label: 'स्रोत इयत्ता', value: '${s['standard_id']}', items: [for (final t in tree) ('${t['standard_id']}', '${t['name']} (${t['medium']})')],
            onChanged: (v) { final t = tree.firstWhere((x) => '${x['standard_id']}' == v); final su = ((t['subjects'] as List).first as Map);
              s['standard_id'] = t['standard_id']; s['subject_id'] = su['subject_id']; s['chapter_ids'] = [for (final ch in (su['chapters'] as List).cast<Map>()) ch['chapter_id']]; qs.clear(); onChanged(); })),
          const SizedBox(width: 6),
          Expanded(child: Pick<String>(dense: true, label: 'स्रोत विषय', value: '${s['subject_id']}', items: [for (final su in ((std?['subjects'] as List?) ?? const []).cast<Map>()) ('${su['subject_id']}', '${su['name']} (${su['mcq']})')],
            onChanged: (v) { final su = subjOf(s['standard_id'], v); s['subject_id'] = v; s['chapter_ids'] = [for (final ch in ((su?['chapters'] as List?) ?? const []).cast<Map>()) ch['chapter_id']]; qs.clear(); onChanged(); })),
        ]),
        for (var i = 0; i < qs.length; i++) ListTile(dense: true, contentPadding: EdgeInsets.zero,
          leading: Text('${i + 1}.'), title: Text('${qs[i]['markup'] ?? ''}'.replaceAll(RegExp(r'<[^>]*>'), ' ').trim(), maxLines: 3, overflow: TextOverflow.ellipsis),
          subtitle: Text('उत्तर: ${'${qs[i]['answer_markup'] ?? ''}'.replaceAll(RegExp(r'<[^>]*>'), ' ').trim()}', maxLines: 1, overflow: TextOverflow.ellipsis),
          trailing: IconButton(icon: const Icon(Icons.close, size: 18), onPressed: () { qs.removeAt(i); onChanged(); })),
      ],
    ));
  }
}
