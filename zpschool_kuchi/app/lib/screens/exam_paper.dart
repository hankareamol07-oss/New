import 'package:flutter/material.dart';

import '../core/remote.dart';
import 'common.dart';
import 'exam_more.dart';
import 'widgets.dart';

const _api = 'exam_native.php';

class ExamPaperScreen extends StatelessWidget {
  const ExamPaperScreen({super.key});
  @override
  Widget build(BuildContext context) => DefaultTabController(
        length: 2,
        child: Scaffold(
          appBar: AppBar(
            title: const Text('प्रश्नपत्रिका'),
            bottom: const TabBar(tabs: [Tab(text: 'नवीन प्रश्नपत्रिका'), Tab(text: 'माझ्या प्रश्नपत्रिका')]),
          ),
          body: SafeArea(
            bottom: true,
            child: Column(children: [
              Padding(padding: const EdgeInsets.fromLTRB(10, 8, 10, 4), child: Row(children: [
                for (final e in <(String, IconData, Widget Function())>[
                  ('गृहपाठ व Quiz', Icons.menu_book_outlined, () => const HomeworkScreen()),
                  ('स्पर्धा परीक्षा', Icons.emoji_events_outlined, () => const CompetitiveScreen()),
                ]) Expanded(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 3), child: FilledButton.tonalIcon(
                  icon: Icon(e.$2, size: 18), label: Text(e.$1, style: const TextStyle(fontWeight: FontWeight.w700)),
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => e.$3()))))),
              ])),
              const Expanded(child: TabBarView(children: [_Create(), _Papers()])),
            ]),
          ),
        ),
      );
}

class _Create extends StatefulWidget {
  const _Create();
  @override
  State<_Create> createState() => _CreateState();
}

class _CreateState extends State<_Create> {
  int standard = 0, subject = 0, template = 0;
  final chapters = <int>{};
  final title = TextEditingController();
  final date = TextEditingController();
  final duration = TextEditingController();
  bool busy = false;

  Future<void> _generate() async {
    setState(() => busy = true);
    try {
      final r = await Remote.post(_api, {
        'action': 'generate',
        'standard_id': standard,
        'subject_id': subject,
        'template_id': template,
        'chapter_ids': chapters.toList(),
        'title': title.text.trim(),
        'exam_date': date.text.trim(),
        'duration': duration.text.trim(),
      });
      if (!mounted) return;
      toast(context, 'प्रश्नपत्रिका तयार झाली (${r['total_marks']} गुण)');
      await Remote.printReport(context, 'modules/exam_paper/paper_view.php', {'id': r['paper_id']}, name: 'प्रश्नपत्रिका');
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => ApiLoader(
        path: _api,
        query: const {'action': 'standards'},
        builder: (c, d, _) {
          final acc = (d['access'] as Map?) ?? {};
          final can = (d['can_create'] as Map?) ?? {};
          final isLocked = acc['allowed'] == false;
          final isTrial = acc['type'] == 'trial';
          final isLimitReached = can['allowed'] == false && can['is_limited'] == true;

          if (isLocked) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Card(
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.lock_clock, size: 54, color: Color(0xFFE9A23B)),
                        const SizedBox(height: 12),
                        const Text('प्रश्नपत्रिका जनरेटर (PRO)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
                        const SizedBox(height: 8),
                        Text(
                          acc['message'] ?? 'प्रश्नपत्रिका निर्मितीसाठी सशुल्क योजना आवश्यक आहे.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 14),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          }

          final stds = [
            for (final s in (d['standards'] as List? ?? []).cast<Map>())
              (asInt(s['standard_id']), 'इयत्ता ${s['name']} — ${s['medium']}')
          ];
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 20),
                  children: [
                    if (isTrial)
                      Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFFBEB),
                          border: Border.all(color: const Color(0xFFFDE68A), width: 1.2),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.timer_outlined, color: Color(0xFFD97706), size: 24),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('१०-दिवसीय मोफत चाचणी (Free Trial) सुरू आहे!', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF92400E))),
                                  Text(
                                    'आपली प्रश्नपत्रिका चाचणी सुरू असून ${acc['days_left'] ?? 0} दिवस शिल्लक आहेत.\nवापर: ${can['count'] ?? 0} / ${can['max'] ?? 5} प्रश्नपत्रिका तयार.',
                                    style: const TextStyle(fontSize: 11.5, color: Color(0xFFB45309)),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    if (isLimitReached)
                      Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEE2E2),
                          border: Border.all(color: const Color(0xFFFCA5A5), width: 1.2),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.error_outline, color: Color(0xFFDC2626), size: 24),
                            SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('मोफत प्रश्नपत्रिका मर्यादा पूर्ण झाली आहे (५/५)!', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF991B1B))),
                                  Text('१ वर्ष अमर्यादित प्रश्नपत्रिका निर्मितीसाठी कृपया ऑल-इन-वन (₹१,४९९) किंवा प्रश्नपत्रिका योजना निवडा.', style: TextStyle(fontSize: 11.5, color: Color(0xFFB91C1C))),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    Pick<int>(
                        label: '1. इयत्ता व माध्यम',
                        value: standard,
                        items: stds,
                        onChanged: (v) => setState(() {
                              standard = v ?? 0;
                              subject = template = 0;
                              chapters.clear();
                            })),
                    if (standard > 0) ...[
                      const SizedBox(height: 12),
                      SizedBox(
                        height: 64,
                        child: ApiLoader(
                          path: _api,
                          query: {'action': 'subjects', 'standard_id': '$standard'},
                          builder: (c, d, _) => Pick<int>(
                            label: '2. विषय',
                            value: subject,
                            items: [for (final s in (d['items'] as List? ?? []).cast<Map>()) (asInt(s['subject_id']), '${s['name']}')],
                            onChanged: (v) => setState(() {
                              subject = v ?? 0;
                              template = 0;
                              chapters.clear();
                            }),
                          ),
                        ),
                      ),
                    ],
                    if (subject > 0) ...[
                      const SizedBox(height: 12),
                      SizedBox(
                        height: 64,
                        child: ApiLoader(
                          path: _api,
                          query: {'action': 'templates', 'subject_id': '$subject'},
                          builder: (c, d, _) {
                            final t = (d['items'] as List? ?? []).cast<Map>();
                            if (t.isEmpty) return const Center(child: Text('या विषयासाठी प्रश्नपत्रिका नमुना उपलब्ध नाही'));
                            return Pick<int>(
                              label: '3. प्रश्नपत्रिका नमुना (गुण)',
                              value: template,
                              items: [for (final x in t) (asInt(x['template_id']), '${x['title']} (${x['total_marks']} गुण)')],
                              onChanged: (v) => setState(() => template = v ?? 0),
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 12),
                      const Text('4. धडे निवडा', style: TextStyle(fontWeight: FontWeight.w700)),
                      SizedBox(
                        height: 300,
                        child: ApiLoader(
                          path: _api,
                          query: {'action': 'chapters', 'subject_id': '$subject'},
                          builder: (c, d, _) {
                            final ch = (d['items'] as List? ?? []).cast<Map>();
                            final ids = ch.map((e) => asInt(e['chapter_id'])).toList();
                            return Card(
                              child: Column(children: [
                                CheckboxListTile(
                                  title: const Text('सर्व धडे'),
                                  value: ids.isNotEmpty && chapters.containsAll(ids),
                                  onChanged: (v) => setState(() => v == true ? chapters.addAll(ids) : chapters.clear()),
                                ),
                                const Divider(height: 1),
                                Expanded(
                                  child: ListView(children: [
                                    for (final x in ch)
                                      CheckboxListTile(
                                        dense: true,
                                        title: Text('${x['name']}'),
                                        subtitle: Text('${x['question_count'] ?? 0} प्रश्न'),
                                        value: chapters.contains(asInt(x['chapter_id'])),
                                        onChanged: (v) => setState(() =>
                                            v == true ? chapters.add(asInt(x['chapter_id'])) : chapters.remove(asInt(x['chapter_id']))),
                                      ),
                                  ]),
                                ),
                              ]),
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(controller: title, decoration: const InputDecoration(labelText: 'परीक्षेचे नाव (ऐच्छिक)', border: OutlineInputBorder())),
                      const SizedBox(height: 10),
                      Row(children: [
                        Expanded(
                          child: TextField(
                            controller: date,
                            readOnly: true,
                            decoration: const InputDecoration(labelText: 'दिनांक', border: OutlineInputBorder()),
                            onTap: () async {
                              final x = await showDatePicker(context: context, initialDate: DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2035));
                              if (x != null) date.text = x.toIso8601String().substring(0, 10);
                            },
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(child: TextField(controller: duration, decoration: const InputDecoration(labelText: 'वेळ (उदा. 1 तास)', border: OutlineInputBorder()))),
                      ]),
                      const SizedBox(height: 12),
                    ],
                  ],
                ),
              ),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.08),
                      offset: const Offset(0, -3),
                      blurRadius: 8,
                    ),
                  ],
                ),
                child: FilledButton.icon(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(50),
                    backgroundColor: const Color(0xFF167A5B),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  icon: busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.auto_awesome),
                  label: Text(
                    isLimitReached ? 'मर्यादा पूर्ण (अपग्रेड करा)' : 'प्रश्नपत्रिका तयार करा व PDF',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15.5),
                  ),
                  onPressed: busy || template == 0 || chapters.isEmpty || isLimitReached ? null : _generate,
                ),
              ),
            ],
          );
        },
      );
}

class _Papers extends StatelessWidget {
  const _Papers();
  @override
  Widget build(BuildContext context) => ApiLoader(
        path: _api,
        query: const {'action': 'papers'},
        builder: (c, d, _) {
          final p = (d['papers'] as List? ?? []).cast<Map>();
          if (p.isEmpty) return const Center(child: Text('अजून प्रश्नपत्रिका तयार केलेली नाही'));
          return ListView(padding: EdgeInsets.fromLTRB(12, 12, 12, 60 + MediaQuery.paddingOf(context).bottom), children: [
            for (final x in p)
              Card(
                child: ListTile(
                  title: Text('${x['title']}'),
                  subtitle: Text('इयत्ता ${x['standard_name'] ?? ''} · ${x['subject_name'] ?? ''} · ${x['total_marks']} गुण'),
                  trailing: PopupMenuButton<String>(
                    icon: const Icon(Icons.picture_as_pdf_outlined),
                    onSelected: (v) => Remote.printReport(
                        c, 'modules/exam_paper/paper_view.php', {'id': x['paper_id'], if (v == 'a') 'answers': 1, if (v == 'b') 'set': 'B'},
                        name: '${x['title']}'),
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: 'p', child: Text('प्रश्नपत्रिका PDF')),
                      PopupMenuItem(value: 'b', child: Text('संच B (प्रश्न क्रम बदलून)')),
                      PopupMenuItem(value: 'a', child: Text('उत्तरांसह PDF')),
                    ],
                  ),
                ),
              ),
          ]);
        },
      );
}


class _Bank extends StatefulWidget {
  const _Bank();
  @override
  State<_Bank> createState() => _BankState();
}

class _BankState extends State<_Bank> {
  int std = 0, sub = 0, book = 0, chapter = 0, page = 0;
  String search = '';

  @override
  Widget build(BuildContext context) => ApiLoader(
        path: _api,
        query: const {'action': 'book_tree'},
        builder: (c, d, _) {
          final tree = (d['items'] as List? ?? []).cast<Map>();
          if (tree.isEmpty) return const Center(child: Text('प्रश्नबँक उपलब्ध नाही'));
          final st = tree[std.clamp(0, tree.length - 1)];
          final subs = (st['subjects'] as List? ?? []).cast<Map>();
          final sb = subs.isEmpty ? <String, dynamic>{} : subs[sub.clamp(0, subs.length - 1)];
          final books = (sb['books'] as List? ?? []).cast<Map>();
          final bk = books.isEmpty ? <String, dynamic>{} : books[book.clamp(0, books.length - 1)];
          final chs = (bk['chapters'] as List? ?? []).cast<Map>();
          return Column(children: [
            Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 0), child: Row(children: [
              Expanded(child: Pick<int>(dense: true, label: 'इयत्ता', value: std, items: [for (var i = 0; i < tree.length; i++) (i, 'इयत्ता ${tree[i]['standard']}')],
                  onChanged: (v) => setState(() { std = v ?? 0; sub = 0; book = 0; chapter = 0; page = 0; }))),
              const SizedBox(width: 8),
              Expanded(child: Pick<int>(dense: true, label: 'विषय', value: sub, items: [for (var i = 0; i < subs.length; i++) (i, '${subs[i]['name']}')],
                  onChanged: (v) => setState(() { sub = v ?? 0; book = 0; chapter = 0; page = 0; }))),
            ])),
            Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 0), child: Pick<int>(dense: true, label: 'घटक / पाठ', value: chapter,
                items: [(0, 'सर्व पाठ'), for (final ch in chs) (asInt(ch['chapter_id']), '${ch['no']}. ${ch['title']}')],
                onChanged: (v) => setState(() { chapter = v ?? 0; page = 0; }))),
            Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 0), child: TextField(
              decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'प्रश्न शोधा', border: OutlineInputBorder(), isDense: true),
              onSubmitted: (v) => setState(() { search = v.trim(); page = 0; }),
            )),
            Expanded(child: FutureBuilder<Map<String, dynamic>>(
              key: ValueKey('$std/$sub/$book/$chapter/$page/$search'),
              future: Remote.post(_api, {
                'action': 'book_browse',
                'page': page,
                if (chapter > 0) 'chapter_ids': [chapter] else if (bk['book_id'] != null) 'book_id': asInt(bk['book_id']),
                if (search.isNotEmpty) 'search': search,
              }),
              builder: (c2, snap) {
                if (snap.hasError) return Center(child: Text('${snap.error}'));
                if (!snap.hasData) return const Center(child: CircularProgressIndicator());
                final r = snap.data!;
                final items = (r['items'] as List? ?? []).cast<Map>();
                final total = asInt(r['total']);
                final pages = (total / 20).ceil();
                return Column(children: [
                  Padding(padding: const EdgeInsets.all(8), child: Text('एकूण $total प्रश्न', style: const TextStyle(fontWeight: FontWeight.w700))),
                  Expanded(child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    itemCount: items.length,
                    itemBuilder: (_, i) {
                      final q = items[i];
                      final opts = q['options'] is List ? (q['options'] as List) : const [];
                      return Card(child: Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        if ('${q['instruction'] ?? ''}'.isNotEmpty) Text('${q['instruction']}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                        Text('${page * 20 + i + 1}. ${q['text'] ?? ''}'),
                        for (var j = 0; j < opts.length; j++) Text('   ${'abcdefgh'[j % 8]}) ${opts[j]}', style: const TextStyle(fontSize: 13)),
                        if ('${q['answer'] ?? ''}'.isNotEmpty) Text('उत्तर: ${q['answer']}', style: const TextStyle(fontSize: 12, color: Colors.green)),
                        Text('${q['chapter_title'] ?? ''} · ${q['qtype'] ?? ''}', style: const TextStyle(fontSize: 11, color: Colors.black54)),
                      ])));
                    },
                  )),
                  if (pages > 1) Padding(
                    padding: EdgeInsets.only(bottom: 16 + MediaQuery.paddingOf(context).bottom),
                    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      IconButton(onPressed: page > 0 ? () => setState(() => page--) : null, icon: const Icon(Icons.chevron_left)),
                      Text('${page + 1} / $pages'),
                      IconButton(onPressed: page + 1 < pages ? () => setState(() => page++) : null, icon: const Icon(Icons.chevron_right)),
                    ]),
                  ),
                ]);
              },
            )),
          ]);
        },
      );
}
