// ignore_for_file: curly_braces_in_flow_control_structures
import 'package:flutter/material.dart';
import '../core/remote.dart';
import '../theme.dart';
import 'common.dart';
import 'widgets.dart';
import 'hpc_fs.dart';
import 'students.dart';

const _hApi = 'hpc_native.php';
Color _stageClr(String? k) { if (k=='foundational') return const Color(0xFFD97706); if (k=='preparatory') return const Color(0xFF0284C7); if (k=='middle') return const Color(0xFF10B981); return TG.g600; }
String _stageLbl(String? k) { if (k=='foundational') return 'पायाभूत (इ.१–२)'; if (k=='preparatory') return 'पूर्वतयारी (इ.३–५)'; if (k=='middle') return 'पूर्व-माध्यमिक (इ.६–८)'; return k??''; }
Color _sClr(String? s) { if (s=='completed') return const Color(0xFF059669); if (s=='draft') return const Color(0xFFD97706); return const Color(0xFF9CA3AF); }
String _sLbl(String? s) { if (s=='completed') return '✓ पूर्ण'; if (s=='draft') return '⏳ अपूर्ण'; return '○ सुरू नाही'; }

void _showClassPrintOptions(BuildContext context, int std) {
  if (std <= 2) {
    Remote.printReport(
      context,
      'cards/print_hpc_19.php',
      {'grade': 'इयत्ता $std', 'std': '$std'},
      name: 'HPC इ.$std (सर्व)',
    );
    return;
  }
  showModalBottomSheet(
    context: context,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (ctx) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              child: Text(
                'इयत्ता $std - HPC PDF प्रकार निवडा',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ),
            const Divider(),
            ListTile(
              leading: const CircleAvatar(
                backgroundColor: Color(0xFFE0F2FE),
                child: Icon(Icons.file_copy_outlined, color: Color(0xFF0284C7)),
              ),
              title: const Text('संक्षिप्त HPC (Compact PDF)', style: TextStyle(fontWeight: FontWeight.bold)),
              subtitle: const Text('१ पान प्रति विषय संक्षिप्त मांडणी (प्रिंटिंगसाठी सोपे व पानांची बचत)'),
              onTap: () {
                Navigator.pop(ctx);
                Remote.printReport(
                  context,
                  'cards/print_hpc_stage.php',
                  {'grade': 'इयत्ता $std', 'std': '$std', 'mode': 'compact'},
                  name: 'HPC इ.$std संक्षिप्त',
                );
              },
            ),
            ListTile(
              leading: const CircleAvatar(
                backgroundColor: Color(0xFFFEF3C7),
                child: Icon(Icons.picture_as_pdf_outlined, color: Color(0xFFD97706)),
              ),
              title: const Text('पूर्ण अधिकृत HPC (Full SCERT PDF)', style: TextStyle(fontWeight: FontWeight.bold)),
              subtitle: const Text('SCERT नुसार सर्व पानांचे सविस्तर समग्र प्रगती पत्रक'),
              onTap: () {
                Navigator.pop(ctx);
                Remote.printReport(
                  context,
                  'cards/print_hpc_stage.php',
                  {'grade': 'इयत्ता $std', 'std': '$std'},
                  name: 'HPC इ.$std पूर्ण',
                );
              },
            ),
          ],
        ),
      ),
    ),
  );
}

void _showStagePrintOptions(BuildContext context, dynamic cardId, String name) {
  showModalBottomSheet(
    context: context,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (ctx) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              child: Text(
                '$name - PDF प्रकार निवडा',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ),
            const Divider(),
            ListTile(
              leading: const CircleAvatar(
                backgroundColor: Color(0xFFE0F2FE),
                child: Icon(Icons.file_copy_outlined, color: Color(0xFF0284C7)),
              ),
              title: const Text('संक्षिप्त HPC (Compact PDF)', style: TextStyle(fontWeight: FontWeight.bold)),
              subtitle: const Text('१ पान प्रति विषय संक्षिप्त मांडणी (प्रिंटिंगसाठी सोपे व पानांची बचत)'),
              onTap: () {
                Navigator.pop(ctx);
                Remote.printReport(
                  context,
                  'cards/print_hpc_stage.php',
                  {'id': cardId, 'mode': 'compact'},
                  name: '$name (संक्षिप्त)',
                );
              },
            ),
            ListTile(
              leading: const CircleAvatar(
                backgroundColor: Color(0xFFFEF3C7),
                child: Icon(Icons.picture_as_pdf_outlined, color: Color(0xFFD97706)),
              ),
              title: const Text('पूर्ण अधिकृत HPC (Full SCERT PDF)', style: TextStyle(fontWeight: FontWeight.bold)),
              subtitle: const Text('SCERT नुसार सर्व पानांचे सविस्तर समग्र प्रगती पत्रक'),
              onTap: () {
                Navigator.pop(ctx);
                Remote.printReport(
                  context,
                  'cards/print_hpc_stage.php',
                  {'id': cardId},
                  name: '$name (पूर्ण)',
                );
              },
            ),
          ],
        ),
      ),
    ),
  );
}

// ═══════════════════════════ HPC MAIN SCREEN ═══════════════════════════════
class HpcScreen extends StatefulWidget {
  const HpcScreen({super.key});
  @override State<HpcScreen> createState() => _HpcScreenState();
}
class _HpcScreenState extends State<HpcScreen> {
  int std = 0; String statusFilter = ''; String searchQ = ''; String division = '';
  final _sc = TextEditingController();
  @override void dispose() { _sc.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('समग्र प्रगती पत्रक (HPC)'),
      actions: [if (std > 0) IconButton(icon: const Icon(Icons.print_outlined), tooltip: 'सर्व PDF',
        onPressed: () => _showClassPrintOptions(context, std))]),
    body: ApiLoader(path: _hApi, query: const {'action': 'dashboard'}, builder: (c, d, _) {
      final stages = (d['stages'] as List? ?? []).cast<Map>();
      final stds = [for (final s in (d['standards'] as List? ?? []).cast<Map>()) (asInt(s['std']), '${s['label']}')];
      if (std == 0 && stds.isNotEmpty) std = stds.first.$1;
      final stats = (d['stats'] as Map?) ?? {};
      return Column(children: [
        if (stages.isNotEmpty) SizedBox(height: 54, child: ListView(scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          children: [for (final st in stages) Padding(padding: const EdgeInsets.only(right: 8), child: ActionChip(
            avatar: Icon(Icons.school_outlined, size: 16, color: st['allowed']==false ? Colors.grey : _stageClr('${st['key']}')),
            label: Text(_stageLbl('${st['key']}')),
            backgroundColor: (st['allowed']==false ? Colors.grey : _stageClr('${st['key']}')).withValues(alpha: .12),
            labelStyle: TextStyle(color: st['allowed']==false ? Colors.grey : _stageClr('${st['key']}'), fontWeight: FontWeight.w700, fontSize: 12),
            side: BorderSide(color: (st['allowed']==false ? Colors.grey : _stageClr('${st['key']}')).withValues(alpha: .35)),
            onPressed: () { final ss = st['stds'] as List? ?? []; if (ss.isNotEmpty) setState(() { std = ss.first as int; division = ''; }); }))])),
        Padding(padding: const EdgeInsets.fromLTRB(12,4,12,0), child: Row(children: [
          Expanded(flex:3, child: Pick<int>(label:'इयत्ता', value:std, items:stds,
            onChanged: (v) => setState(() { std=v??std; division=''; searchQ=''; _sc.clear(); }))),
          const SizedBox(width:6),
          Expanded(flex:2, child: Pick<String>(label:'तुकडी', value:division,
            items: const [('','सर्व'),('अ','अ'),('ब','ब'),('क','क'),('ड','ड')],
            onChanged: (v) => setState(() => division=v??''))),
          const SizedBox(width:6),
          Expanded(flex:3, child: Pick<String>(label:'स्थिती', value:statusFilter,
            items: const [('','सर्व'),('completed','✓ पूर्ण'),('draft','⏳ अपूर्ण'),('pending','○ नाही')],
            onChanged: (v) => setState(() => statusFilter=v??''))),
        ])),
        Padding(padding: const EdgeInsets.fromLTRB(12,8,12,4), child: TextField(controller: _sc,
          decoration: InputDecoration(hintText:'विद्यार्थी शोधा…', prefixIcon: const Icon(Icons.search), isDense:true,
            suffixText:'पूर्ण ${stats['completed']??0} · अपूर्ण ${stats['draft']??0}',
            suffixStyle: const TextStyle(color:TG.muted, fontSize:11)),
          onChanged: (v) => setState(() => searchQ=v.trim()))),
        Expanded(child: ApiLoader(path: _hApi,
          query: {'action':'students','std':'$std','status':statusFilter,'search':searchQ, if (division.isNotEmpty) 'division': division},
          builder: (c2,sd,reload) {
            if (sd['allowed']==false) return Center(child: Padding(padding: const EdgeInsets.all(28),
              child: Column(mainAxisSize:MainAxisSize.min, children: [
                const Icon(Icons.lock_outline, size:60, color:TG.amber), const SizedBox(height:16),
                Text('${sd['access_message']??'हे वर्ग पाहण्यासाठी सशुल्क योजना आवश्यक आहे.'}', textAlign:TextAlign.center),
              ])));
            final students = (sd['students'] as List? ?? []).cast<Map>();
            if (students.isEmpty) return const EmptyState('या इयत्तेत विद्यार्थी नाहीत.', icon:Icons.groups_outlined);
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(12, 4, 12, 80),
              itemCount: students.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (ctx, i) {
                final s = students[i];
                final clr = _sClr('${s['status']}');
                final p1 = asInt(s['progress_sem1']);
                final p2 = asInt(s['progress_sem2']);
                final h1 = double.tryParse('${s['height_sem1']}') ?? 0;
                final w1 = double.tryParse('${s['weight_sem1']}') ?? 0;
                return Card(
                  elevation: 0.8,
                  margin: EdgeInsets.zero,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                    side: BorderSide(
                      color: clr == const Color(0xFF059669)
                          ? const Color(0xFF86EFAC)
                          : (clr == const Color(0xFFD97706) ? const Color(0xFFFDE68A) : Colors.grey.shade300),
                      width: 1.2,
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            InkWell(
                              borderRadius: BorderRadius.circular(24),
                              onTap: () async {
                                final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: Map<String, dynamic>.from(s))));
                                if (ok == true) reload();
                              },
                              child: CircleAvatar(
                                radius: 22,
                                backgroundColor: clr.withValues(alpha: .15),
                                child: Text(
                                  '${s['roll_no']?.toString().isNotEmpty == true ? s['roll_no'] : (s['name_mr'] ?? s['name'] ?? '?').toString().characters.first}',
                                  style: TextStyle(color: clr, fontWeight: FontWeight.w800, fontSize: 13),
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  InkWell(
                                    onTap: () async {
                                      final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: Map<String, dynamic>.from(s))));
                                      if (ok == true) reload();
                                    },
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Flexible(
                                          child: Text(
                                            '${s['name_mr'] ?? s['name']}',
                                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                        const SizedBox(width: 4),
                                        const Icon(Icons.edit_outlined, size: 14, color: Color(0xFF2563A8)),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 2,
                                    children: [
                                      Text('रोल: ${s['roll_no'] ?? '-'}', style: const TextStyle(fontSize: 12, color: TG.muted)),
                                      Text('तुकडी: ${s['section'] ?? 'अ'}', style: const TextStyle(fontSize: 12, color: TG.muted)),
                                      if (s['gr_no']?.toString().isNotEmpty == true)
                                        Text('GR: ${s['gr_no']}', style: const TextStyle(fontSize: 12, color: TG.muted)),
                                      if (std <= 2 && (p1 + p2) > 0)
                                        Text('स.१: $p1% · स.२: $p2%', style: const TextStyle(fontSize: 11.5, color: Color(0xFF0F766E), fontWeight: FontWeight.w600)),
                                      if (h1 > 0 || w1 > 0)
                                        Text(
                                          '${h1 > 0 ? '${s['height_sem1']}cm' : ''}${h1 > 0 && w1 > 0 ? ' · ' : ''}${w1 > 0 ? '${s['weight_sem1']}kg' : ''}',
                                          style: const TextStyle(fontSize: 11.5, color: Color(0xFF059669), fontWeight: FontWeight.w600),
                                        ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: clr.withValues(alpha: .12),
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(color: clr.withValues(alpha: .35)),
                              ),
                              child: Text(
                                _sLbl('${s['status']}'),
                                style: TextStyle(color: clr, fontWeight: FontWeight.w700, fontSize: 11),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        const Divider(height: 1),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: FilledButton.tonalIcon(
                                style: FilledButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                                icon: Icon(std <= 2 ? Icons.edit_note : Icons.assignment_turned_in_outlined, size: 18),
                                label: Text(
                                  std <= 2
                                      ? (asInt(s['card_id']) > 0 ? 'HPC नोंद / संपादन' : 'HPC कार्ड तयार करा')
                                      : (asInt(s['card_id']) > 0 ? 'विषय मूल्यमापन (संपादन)' : 'विषय मूल्यमापन'),
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5),
                                  overflow: TextOverflow.ellipsis,
                                ),
                                onPressed: () async {
                                  await Navigator.push(
                                    ctx,
                                    MaterialPageRoute(
                                      builder: (_) => std <= 2
                                          ? HpcFsFormScreen(student: s)
                                          : HpcStageScreen(student: s, std: std),
                                    ),
                                  );
                                  reload();
                                },
                              ),
                            ),
                            const SizedBox(width: 8),
                            OutlinedButton.icon(
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              icon: const Icon(Icons.person_pin_outlined, size: 17),
                              label: const Text('विद्यार्थी संपादन', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                              onPressed: () async {
                                final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: Map<String, dynamic>.from(s))));
                                if (ok == true) reload();
                              },
                            ),
                            if (asInt(s['card_id']) > 0) ...[
                              const SizedBox(width: 6),
                              IconButton.filledTonal(
                                icon: const Icon(Icons.print_outlined, size: 18, color: Color(0xFF059669)),
                                tooltip: 'PDF प्रिंट',
                                onPressed: () {
                                  final name = '${s['name_mr'] ?? s['name']} HPC';
                                  if (std <= 2) {
                                    Remote.printReport(
                                      context,
                                      'cards/print_hpc_19.php',
                                      {'id': s['card_id']},
                                      name: name,
                                    );
                                  } else {
                                    _showStagePrintOptions(context, s['card_id'], name);
                                  }
                                },
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        )),
      ]);
    }),
  );
}


// ═══════════════════════════ FOUNDATIONAL WIZARD (इ.१–२) ════════════════════
// 3-step wizard: Step1=रुब्रिक कोड, Step2=अभिप्राय, Step3=कामगिरी+attendance+save
class HpcFoundationalScreen extends StatefulWidget {
  const HpcFoundationalScreen({super.key, required this.student});
  final Map student;
  @override State<HpcFoundationalScreen> createState() => _HpcFoundationalState();
}
class _HpcFoundationalState extends State<HpcFoundationalScreen> with SingleTickerProviderStateMixin {
  late final _tab = TabController(length:4, vsync:this);
  int sem = 1; int domainIdx = 0;

  @override void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final name = '${widget.student['name_mr']??widget.student['name']}';
    return Scaffold(
      appBar: AppBar(
        title: Text(name, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            icon: const Icon(Icons.manage_accounts_outlined),
            tooltip: 'विद्यार्थी संपादन (माहिती, उंची, वजन)',
            onPressed: () async {
              final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: Map<String, dynamic>.from(widget.student))));
              if (!context.mounted) return;
              if (ok == true) {
                toast(context, 'विद्यार्थी माहिती अद्ययावत झाली');
              }
            },
          ),
        ],
        bottom: TabBar(controller: _tab, isScrollable: true, tabs: const [
          Tab(icon: Icon(Icons.edit_note), text: 'Step 1: रुब्रिक'),
          Tab(icon: Icon(Icons.comment_outlined), text: 'Step 2: अभिप्राय'),
          Tab(icon: Icon(Icons.emoji_events_outlined), text: 'Step 3: कामगिरी'),
          Tab(icon: Icon(Icons.flag_outlined), text: 'Step 4: जतन करा'),
        ]),
      ),
      body: ApiLoader(
        path: _hApi,
        query: {'action': 'foundational_get', 'student_id': '${widget.student['id']}'},
        builder: (c, d, reload) {
          final card = (d['card'] as Map?) ?? {};
          final domains = (d['domains'] as List? ?? []).cast<Map>();
          final levels = (d['levels'] as List? ?? []).cast<Map>();
          final att = (d['attendance'] as List? ?? []).cast<Map>();
          if (domains.isEmpty) return const Center(child: Text('विकास क्षेत्रे लोड होत नाहीत'));
          if (domainIdx >= domains.length) domainIdx = 0;
          return Column(children: [
            // Status bar with student edit option
            Container(
              color: _sClr('${card['status']}').withValues(alpha: .1),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
              child: Row(children: [
                Icon(
                  _sClr('${card['status']}') == const Color(0xFF059669) ? Icons.check_circle : Icons.hourglass_top,
                  color: _sClr('${card['status']}'),
                  size: 18,
                ),
                const SizedBox(width: 8),
                Expanded(child: Text(_sLbl('${card['status']}'), style: const TextStyle(fontWeight: FontWeight.w700))),
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
                    }
                  },
                ),
                if (asInt(card['id']) > 0) ...[
                  const SizedBox(width: 6),
                  IconButton(
                    icon: const Icon(Icons.picture_as_pdf_outlined, color: Color(0xFFB3261E)),
                    tooltip: 'PDF प्रिंट',
                    onPressed: () => Remote.printReport(c, 'cards/print_hpc_19.php', {'id': card['id'], 'format': 'official19'}, name: name),
                  ),
                ],
              ]),
            ),
            Expanded(child: TabBarView(controller:_tab, children: [
              _FoundStep1(domains:domains, levels:levels, sem:sem, domainIdx:domainIdx, student:widget.student,
                onSemChange:(v) => setState(()=>sem=v), onDomainChange:(v) => setState(()=>domainIdx=v), onSaved:reload),
              _FoundStep2(domains:domains, sem:sem, student:widget.student,
                onSemChange:(v) => setState(()=>sem=v), onSaved:reload),
              _FoundKamgiri(domains:domains, student:widget.student, onSaved:reload),
              _FoundStep3(student:widget.student, card:card, att:att, onSaved:reload),
            ])),
          ]);
        },
      ),
    );
  }
}


// Step 1: रुब्रिक कोड ─────────────────────────────────────────────────────────
class _FoundStep1 extends StatelessWidget {
  const _FoundStep1({required this.domains, required this.levels, required this.sem, required this.domainIdx,
    required this.student, required this.onSemChange, required this.onDomainChange, required this.onSaved});
  final List<Map> domains, levels; final int sem, domainIdx;
  final Map student; final ValueChanged<int> onSemChange, onDomainChange; final VoidCallback onSaved;

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(12), children: [
    Container(padding: const EdgeInsets.all(10), margin: const EdgeInsets.only(bottom:10),
      decoration: BoxDecoration(color: const Color(0xFFEFF6FF), borderRadius: BorderRadius.circular(10)),
      child: const Text('प्रत्येक विकास क्षेत्रासाठी सत्र 1 आणि सत्र 2 साठी एक-एक क्षमता (C-code) निवडा. निवड आपोआप जतन होते.',
        style: TextStyle(fontSize:13))),
    for (final dom in domains) Card(margin: const EdgeInsets.only(bottom:10), child: Padding(padding: const EdgeInsets.all(12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('${dom['id']}. ${dom['name_mr']}', style: const TextStyle(fontWeight:FontWeight.w800, fontSize:15))),
          Text('${(dom['competencies'] as List? ?? []).length} क्षमता', style: const TextStyle(fontSize:12, color:TG.muted)),
        ]),
        const SizedBox(height:10),
        for (final s in [1, 2]) _CodePick(dom: dom, sem: s, student: student, onSaved: onSaved),
      ]))),
    const SizedBox(height:80),
  ]);
}

class _CodePick extends StatefulWidget {
  const _CodePick({required this.dom, required this.sem, required this.student, required this.onSaved});
  final Map dom, student; final int sem; final VoidCallback onSaved;
  @override State<_CodePick> createState() => _CodePickState();
}
class _CodePickState extends State<_CodePick> {
  late String _cur = _saved; bool _saving = false;
  String get _saved {
    final sems = (widget.dom['semesters'] as List? ?? []).cast<Map>();
    final r = sems.firstWhere((x) => asInt(x['semester']) == widget.sem, orElse: () => {});
    return '${r['code'] ?? ''}';
  }
  @override void didUpdateWidget(_CodePick o) { super.didUpdateWidget(o); if (!_saving) _cur = _saved; }

  Future<void> _save(String code) async {
    final old = _cur;
    setState(() { _saving = true; _cur = code; });
    try {
      await Remote.save(_hApi, {'action':'save_code', 'student_id':widget.student['id'],
        'domain_id':asInt(widget.dom['id']), 'semester':widget.sem, 'capacity_code':code});
      if (mounted) toast(context, 'जतन झाले');
      widget.onSaved();
    } catch (e) { if (mounted) { toast(context, '$e', error:true); setState(() => _cur = old); } }
    finally { if (mounted) setState(() => _saving = false); }
  }

  @override
  Widget build(BuildContext context) {
    final comps = (widget.dom['competencies'] as List? ?? []).cast<Map>();
    final txt = {for (final c in comps) '${c['code']}': '${c['text']}'};
    final clr = widget.sem == 1 ? const Color(0xFF2563A8) : const Color(0xFFB45309);
    return Container(margin: const EdgeInsets.only(bottom:10), padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(border: Border.all(color: clr.withValues(alpha:.35)), borderRadius: BorderRadius.circular(10), color: clr.withValues(alpha:.04)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Text('${widget.sem == 1 ? '📘' : '📙'} सत्र ${widget.sem}', style: TextStyle(fontWeight:FontWeight.w700, color: clr)),
          const Spacer(),
          if (_saving) const SizedBox(width:16, height:16, child: CircularProgressIndicator(strokeWidth:2))
          else Text(_cur.isNotEmpty ? '✓ जतन' : '⏳ निवडा', style: TextStyle(fontSize:12, color: _cur.isNotEmpty ? const Color(0xFF059669) : TG.muted)),
        ]),
        const SizedBox(height:8),
        DropdownButtonFormField<String>(
          isExpanded: true,
          value: txt.containsKey(_cur) ? _cur : null,
          hint: const Text('-- निवडा --'),
          decoration: const InputDecoration(border: OutlineInputBorder(), isDense: true),
          items: [for (final c in comps) DropdownMenuItem(value: '${c['code']}',
            child: Text('${c['code']} : ${c['text']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize:13)))],
          selectedItemBuilder: (_) => [for (final c in comps) Text('${c['code']} : ${c['text']}', maxLines: 1, overflow: TextOverflow.ellipsis)],
          onChanged: _saving ? null : (v) { if (v != null && v != _cur) _save(v); }),
        if (txt.containsKey(_cur)) Padding(padding: const EdgeInsets.only(top:6),
          child: Text('$_cur : ${txt[_cur]}', style: const TextStyle(fontSize:12, color: TG.muted, height: 1.4))),
      ]));
  }
}


// Step 2: अभिप्राय ────────────────────────────────────────────────────────────
class _FoundStep2 extends StatefulWidget {
  const _FoundStep2({required this.domains, required this.sem, required this.student, required this.onSemChange, required this.onSaved});
  final List<Map> domains; final int sem; final Map student;
  final ValueChanged<int> onSemChange; final VoidCallback onSaved;
  @override State<_FoundStep2> createState() => _FoundStep2State();
}
class _FoundStep2State extends State<_FoundStep2> {
  final Set<String> _saving = {};
  final Map<String, Set<int>> _sel = {};

  @override void initState() { super.initState(); _loadSaved(); }
  @override void didUpdateWidget(_FoundStep2 o) { super.didUpdateWidget(o); if (_saving.isEmpty) _loadSaved(); }

  List<Map> _sems(Map dom) => (dom['semesters'] as List? ?? []).cast<Map>();
  void _loadSaved() {
    for (final dom in widget.domains) {
      for (final r in _sems(dom)) {
        _sel['${dom['id']}-${r['semester']}'] = {for (final e in (r['abhipray_selected'] as List? ?? [])) asInt(e)};
      }
    }
  }

  Future<void> _save(int did, int sem) async {
    final k = '$did-$sem';
    setState(() => _saving.add(k));
    try {
      await Remote.save(_hApi, {'action':'save_abhipray', 'student_id':widget.student['id'],
        'domain_id':did, 'semester':sem, 'ids':(_sel[k] ?? {}).toList()});
      if (mounted) toast(context, 'सत्र $sem अभिप्राय जतन झाला');
      widget.onSaved();
    } catch (e) { if (mounted) toast(context, '$e', error:true); }
    finally { if (mounted) setState(() => _saving.remove(k)); }
  }

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(12), children: [
    for (final dom in widget.domains) Card(margin: const EdgeInsets.only(bottom:10), child: Padding(padding: const EdgeInsets.all(12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('${dom['id']}. ${dom['name_mr']}', style: const TextStyle(fontWeight:FontWeight.w800, fontSize:15)),
        const SizedBox(height:8),
        for (final r in _sems(dom)) Builder(builder: (_) {
          final sem = asInt(r['semester']); final k = '${dom['id']}-$sem';
          final opts = (r['abhipray_options'] as List? ?? []).cast<Map>();
          final sel = _sel.putIfAbsent(k, () => {});
          final clr = sem == 1 ? const Color(0xFF2563A8) : const Color(0xFFB45309);
          return Container(margin: const EdgeInsets.only(bottom:10), padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(border: Border.all(color: clr.withValues(alpha:.35)), borderRadius: BorderRadius.circular(10)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${sem == 1 ? '📘' : '📙'} सत्र $sem${'${r['code'] ?? ''}'.isEmpty ? '' : ' — ${r['code']}'}${sel.isEmpty ? '' : '  (${sel.length} निवडले)'}',
                style: TextStyle(fontWeight:FontWeight.w700, color: clr)),
              if ('${r['code_text'] ?? ''}'.isNotEmpty) Text('${r['code_text']}', style: const TextStyle(fontSize:12, color:TG.muted)),
              if ('${r['code'] ?? ''}'.isEmpty) const Padding(padding: EdgeInsets.only(top:6),
                child: Text('आधी पायरी 1 मध्ये या सत्राची क्षमता (C-code) निवडा.', style: TextStyle(color: Color(0xFFB3261E), fontSize:13)))
              else if (opts.isEmpty) const Padding(padding: EdgeInsets.only(top:6), child: Text('या क्षमतेसाठी अभिप्राय उपलब्ध नाहीत.', style: TextStyle(fontSize:13)))
              else ...[
                for (var i = 0; i < opts.length; i++) CheckboxListTile(dense: true, contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  value: sel.contains(asInt(opts[i]['id'])),
                  title: Text('${i + 1}. ${opts[i]['text']}', style: const TextStyle(fontSize:13)),
                  onChanged: (v) => setState(() => v == true ? sel.add(asInt(opts[i]['id'])) : sel.remove(asInt(opts[i]['id'])))),
                SizedBox(width: double.infinity, child: FilledButton.icon(
                  onPressed: _saving.contains(k) ? null : () => _save(asInt(dom['id']), sem),
                  icon: const Icon(Icons.save, size:18), label: Text('सत्र $sem अभिप्राय जतन करा'))),
              ],
            ]));
        }),
      ]))),
    const SizedBox(height:80),
  ]);
}


// Step 3: कामगिरी + attendance + card-level fields + save ────────────────────
class _FoundStep3 extends StatefulWidget {
  const _FoundStep3({required this.student, required this.card, required this.att, required this.onSaved});
  final Map student, card; final List<Map> att; final VoidCallback onSaved;
  @override State<_FoundStep3> createState() => _FoundStep3State();
}
class _FoundStep3State extends State<_FoundStep3> {
  late final TextEditingController _feedback;
  late final TextEditingController _aware;
  late final TextEditingController _sens;
  late final TextEditingController _creat;
  late final List<Map<String,dynamic>> _att;
  bool _saving = false;
  late String _status;

  @override
  void initState() {
    super.initState();
    _feedback = TextEditingController(text:'${widget.card['final_annual_feedback']??''}');
    _aware = TextEditingController(text:'${widget.card['summary_awareness']??''}');
    _sens = TextEditingController(text:'${widget.card['summary_sensitivity']??''}');
    _creat = TextEditingController(text:'${widget.card['summary_creativity']??''}');
    _att = [
      for (final a in widget.att) {
        ...Map<String, dynamic>.from(a),
        'working_days': (asInt(a['working_days']) > 0)
            ? asInt(a['working_days'])
            : (defaultWorkingDays[asInt(a['month'])] ?? 0),
      }
    ];
    _status = '${widget.card['status']??'draft'}';
  }

  static const defaultWorkingDays = {
    6: 14, 7: 25, 8: 24, 9: 23, 10: 21, 11: 15, 12: 24, 1: 24, 2: 23, 3: 24, 4: 22, 5: 0
  };

  void _autoFillWorkingDays() {
    setState(() {
      for (var i = 0; i < _att.length; i++) {
        final m = asInt(_att[i]['month']);
        final defW = defaultWorkingDays[m] ?? 0;
        _att[i]['working_days'] = defW;
      }
    });
    toast(context, 'शासकीय प्रमाण कामाचे दिवस भरले');
  }

  @override void dispose() { _feedback.dispose(); _aware.dispose(); _sens.dispose(); _creat.dispose(); super.dispose(); }

  Future<void> _save(String status) async {
    setState(()=>_saving=true);
    try {
      await Remote.save(_hApi, {
        'action':'foundational_card_save', 'student_id':widget.student['id'],
        'status':status, 'final_annual_feedback':_feedback.text.trim(),
        'summary_awareness':_aware.text.trim(), 'summary_sensitivity':_sens.text.trim(),
        'summary_creativity':_creat.text.trim(),
        'attendance':[for (final a in _att) {'month':a['month'],'working_days':a['working_days']??0,'days_present':a['days_present']??0}],
      });
      setState(()=>_status=status);
      if (mounted) { toast(context, status=='completed' ? '✓ HPC कार्ड पूर्ण!' : 'मसुदा जतन झाला'); widget.onSaved(); }
    } catch(e) { if (mounted) toast(context,'$e',error:true); }
    finally { if (mounted) setState(()=>_saving=false); }
  }

  static const _annualRemarks = [
    '🌟 [अत्युत्तम] विद्यार्थी सर्व सहा विकास क्षेत्रांमध्ये अत्युत्तम प्रगती दर्शवतो. शारीरिक विकास, भाषा कौशल्य, गणितीय समज, सामाजिक वर्तन आणि अध्ययन सवयी या सर्व बाबतींत उत्कृष्ट कामगिरी. वर्गातील सर्व उपक्रमांमध्ये अग्रेसर.',
    '🌟 [अत्युत्तम] विद्यार्थी हुशार, मेहनती आणि सर्जनशील आहे. नेतृत्व गुण, सहकारी भावना आणि आत्मविश्वास यांचे उत्कृष्ट दर्शन. स्वतःहून नवीन गोष्टी शिकण्याची उत्कट इच्छा. खऱ्या अर्थाने आदर्श विद्यार्थी.',
    '⭐ [उत्तम] विद्यार्थी शैक्षणिक वर्षात सर्व क्षेत्रांमध्ये उत्तम प्रगती दर्शवतो. वर्गातील उपक्रमांमध्ये सक्रिय सहभाग. गृहपाठ वेळेवर पूर्ण करतो. पुढील वर्षी अशीच प्रगती कायम ठेवावी.',
    '⭐ [उत्तम] विद्यार्थी अभ्यासात नियमित आहे आणि शिक्षकांच्या सूचनांचे पालन करतो. शारीरिक खेळ, भाषा संवाद, गणितीय कृती आणि कला उपक्रमांमध्ये चांगला सहभाग दिसतो.',
    '🌱 [मध्यम] विद्यार्थी जिज्ञासू आहे आणि नवीन गोष्टी शिकण्यास उत्सुक आहे. वाचन आणि लेखन कौशल्यात चांगली प्रगती दिसते. अधिक एकाग्रता आणि चिकाटीसाठी प्रोत्साहन देणे आवश्यक.',
    '🌱 [मध्यम] विद्यार्थ्याने या शैक्षणिक वर्षात लक्षणीय प्रगती केली आहे. मित्रांशी चांगले संबंध निर्माण केले आहेत. भाषा कौशल्य आणि गणितीय समजात सुधारणा दिसते.',
    '🎯 [प्रगती आवश्यक] विद्यार्थी प्रगतीच्या मार्गावर आहे. वाचन आणि लेखनात अधिक सराव हवा. गणितीय संकल्पनांसाठी पालकांनी घरी अभ्यासासाठी मदत करणे गरजेचे.',
    '🎯 [विशेष मदत] विद्यार्थ्याला मूलभूत कौशल्ये (वाचन, लेखन, गणन) विकसित करण्यासाठी अतिरिक्त सराव हवा. पालकांनी घरी दररोज ३० मिनिटे अभ्यासासाठी वेळ द्यावा.',
  ];

  static const _awareRemarks = [
    'विषयाची अत्यंत प्रगल्भ आणि सखोल जाणीव असून स्वतःहून नवीन संकल्पना मांडतो. वर्गात शिकवलेले घटक चटकन समजतात.',
    'शिकवलेल्या घटकांबद्दलची समज स्पष्ट आणि पक्की आहे. विचारलेल्या प्रश्नांची योग्य उत्तरे देण्यात चांगला हातखंडा आहे.',
    'पायाभूत संकल्पना स्पष्ट आहेत आणि दैनंदिन अभ्यासात सातत्य ठेवतो.',
    'प्राथमिक संकल्पनांची समज निर्माण होत आहे, शिक्षकांनी मार्गदर्शन केल्यावर कृती पूर्ण करतो.',
  ];

  static const _sensRemarks = [
    'गटात काम करताना समजूतदारपणा दाखवतो. मित्र-मैत्रिणींना मदत करण्याची वृत्ती खूप छान आहे.',
    'सर्वांशी मिळून-मिसळून राहतो. शाळेतील नियम व शिस्त पाळण्याचा प्रामाणिक प्रयत्न करतो.',
    'इतरांच्या भावना समजतात आणि मित्रांसोबत खेळताना सहकार्याची भावना दाखवतो.',
    'संवाद साधताना हळूहळू आत्मविश्वास वाढत आहे, अधिक सहभागासाठी प्रोत्साहन आवश्यक आहे.',
  ];

  static const _creatRemarks = [
    'चित्रकला, मातीकाम आणि हस्तकला यात खूप चांगली कलाकुसर दाखवतो. रंगसंगतीची निवड कल्पक आहे.',
    'साहित्याचा वापर काळजीपूर्वक करून चांगल्या व नीटनेटक्या रचना तयार करतो.',
    'कागदकाम आणि साध्या चित्रकलेत हात चांगला बसला आहे. दिलेले काम वेळेत पूर्ण करतो.',
    'हस्तकला आणि विविध कृतींमध्ये आनंदाने सहभाग घेतो, कल्पना विस्तारासाठी थोडे मार्गदर्शन आवश्यक आहे.',
  ];

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.fromLTRB(12,12,12,100), children:[
    Card(color: _status=='completed' ? const Color(0xFFECFDF5) : const Color(0xFFFFF7ED), child: ListTile(
      leading: Icon(_status=='completed' ? Icons.check_circle : Icons.hourglass_top,
        color: _status=='completed' ? const Color(0xFF059669) : const Color(0xFFD97706)),
      title: Text(_status=='completed' ? '✓ HPC पूर्ण' : '⏳ मसुदा', style: const TextStyle(fontWeight:FontWeight.w700)),
      subtitle: Text(asInt(widget.card['id'])>0 ? 'कार्ड #${widget.card['id']}' : 'नवीन कार्ड'),
      trailing: asInt(widget.card['id'])>0 ? IconButton(
        icon: const Icon(Icons.picture_as_pdf_outlined, color:Color(0xFFB3261E)),
        onPressed: () => Remote.printReport(context,'cards/print_hpc_19.php',
          {'id':widget.card['id'],'format':'official19'},
          name:'${widget.student['name_mr']??widget.student['name']}')) : null,
    )),
    Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        const SectionTitle('शिक्षकाचे अभिप्राय'),
        FilledButton.tonalIcon(
          style: FilledButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
            minimumSize: const Size(0, 32),
          ),
          onPressed: () {
            setState(() {
              if (_feedback.text.trim().isEmpty) _feedback.text = _annualRemarks[0];
              if (_aware.text.trim().isEmpty) _aware.text = _awareRemarks[0];
              if (_sens.text.trim().isEmpty) _sens.text = _sensRemarks[0];
              if (_creat.text.trim().isEmpty) _creat.text = _creatRemarks[0];
            });
            toast(context, '⚡ अभिप्राय स्वयंचलित भरले (Auto Filled)');
          },
          icon: const Icon(Icons.flash_on, size: 14, color: Color(0xFF167A5B)),
          label: const Text('ऑटो-फिल अभिप्राय', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold)),
        ),
      ],
    ),
    Row(children: [
      const Expanded(child: Text('वार्षिक शेरा (अंतिम सर्वकष अभिप्राय)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5))),
      SheetPick(label: 'वार्षिक शेरा निवडा', hint: '— तयार शेरे —', options: _annualRemarks, onPicked: (v) => setState(() => _feedback.text = v)),
    ]),
    const SizedBox(height: 4),
    TextField(controller:_feedback, maxLines:3, decoration: const InputDecoration(border:OutlineInputBorder(), isDense: true)),
    const SizedBox(height:10),
    Row(children: [
      const Expanded(child: Text('जाणीव–संवेदना (Awareness)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5))),
      SheetPick(label: 'जाणीव शेरा निवडा', hint: '— तयार शेरे —', options: _awareRemarks, onPicked: (v) => setState(() => _aware.text = v)),
    ]),
    const SizedBox(height: 4),
    TextField(controller:_aware, maxLines:2, decoration: const InputDecoration(border:OutlineInputBorder(), isDense: true)),
    const SizedBox(height:10),
    Row(children: [
      const Expanded(child: Text('संवेदनशीलता (Sensitivity)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5))),
      SheetPick(label: 'संवेदनशीलता शेरा निवडा', hint: '— तयार शेरे —', options: _sensRemarks, onPicked: (v) => setState(() => _sens.text = v)),
    ]),
    const SizedBox(height: 4),
    TextField(controller:_sens, maxLines:2, decoration: const InputDecoration(border:OutlineInputBorder(), isDense: true)),
    const SizedBox(height:10),
    Row(children: [
      const Expanded(child: Text('सर्जनशीलता–कार्यशीलता (Creativity)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5))),
      SheetPick(label: 'सर्जनशीलता शेरा निवडा', hint: '— तयार शेरे —', options: _creatRemarks, onPicked: (v) => setState(() => _creat.text = v)),
    ]),
    const SizedBox(height: 4),
    TextField(controller:_creat, maxLines:2, decoration: const InputDecoration(border:OutlineInputBorder(), isDense: true)),

    if (_att.isNotEmpty) ...[
      Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const SectionTitle('मासिक उपस्थिती'),
          TextButton.icon(
            style: TextButton.styleFrom(visualDensity: VisualDensity.compact, padding: const EdgeInsets.symmetric(horizontal: 8)),
            icon: const Icon(Icons.auto_mode, size: 15),
            label: const Text('ऑटो दिवस भरा', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
            onPressed: _autoFillWorkingDays,
          ),
        ],
      ),
      Table(border:TableBorder.all(color:TG.line,width:.8),
        columnWidths: const {0:FlexColumnWidth(2.2),1:FlexColumnWidth(1.4),2:FlexColumnWidth(1.4)},
        children:[
          const TableRow(decoration: BoxDecoration(color:Color(0xFFEFF8F4)), children:[
            Padding(padding:EdgeInsets.all(8), child:Text('महिना',style:TextStyle(fontWeight:FontWeight.w700,fontSize:13))),
            Padding(padding:EdgeInsets.all(8), child:Text('कामाचे',style:TextStyle(fontWeight:FontWeight.w700,fontSize:13))),
            Padding(padding:EdgeInsets.all(8), child:Text('उपस्थित',style:TextStyle(fontWeight:FontWeight.w700,fontSize:13))),
          ]),
          for (var i=0;i<_att.length;i++) TableRow(children:[
            Padding(padding: const EdgeInsets.symmetric(horizontal:8,vertical:10),
              child:Text('${_att[i]['label']??''}',style: const TextStyle(fontSize:13))),
            Padding(padding: const EdgeInsets.all(4), child:TextFormField(
              key: ValueKey('w_${_att[i]['month']}_${_att[i]['working_days']}'),
              initialValue:'${_att[i]['working_days']==0?'':_att[i]['working_days']}',
              keyboardType:TextInputType.number, textAlign:TextAlign.center,
              style: const TextStyle(fontSize:13),
              decoration: const InputDecoration(isDense:true,contentPadding:EdgeInsets.symmetric(horizontal:4,vertical:8)),
              onChanged:(v) => _att[i]['working_days']=int.tryParse(v)??0)),
            Padding(padding: const EdgeInsets.all(4), child:TextFormField(
              key: ValueKey('p_${_att[i]['month']}_${_att[i]['days_present']}'),
              initialValue:'${_att[i]['days_present']==0?'':_att[i]['days_present']}',
              keyboardType:TextInputType.number, textAlign:TextAlign.center,
              style: const TextStyle(fontSize:13),
              decoration: const InputDecoration(isDense:true,contentPadding:EdgeInsets.symmetric(horizontal:4,vertical:8)),
              onChanged:(v) => _att[i]['days_present']=int.tryParse(v)??0)),
          ]),
        ]),
    ],
    const SizedBox(height:20),
    Row(children:[
      Expanded(child: OutlinedButton.icon(onPressed:_saving?null:()=>_save('draft'),
        icon: const Icon(Icons.save_outlined), label: const Text('मसुदा जतन'))),
      const SizedBox(width:12),
      Expanded(child: FilledButton.icon(
        style: FilledButton.styleFrom(backgroundColor: const Color(0xFF059669)),
        onPressed:_saving?null:()=>_save('completed'),
        icon:_saving ? const SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white))
            : const Icon(Icons.check_circle_outline),
        label: const Text('पूर्ण जतन करा'))),
    ]),
  ]);
}


// Step 3: कामगिरी (hpc/step3_kamgiri.php) ─────────────────────────────────────
class _FoundKamgiri extends StatefulWidget {
  const _FoundKamgiri({required this.domains, required this.student, required this.onSaved});
  final List<Map> domains; final Map student; final VoidCallback onSaved;
  @override State<_FoundKamgiri> createState() => _FoundKamgiriState();
}
class _FoundKamgiriState extends State<_FoundKamgiri> {
  int _di = 0; bool _saving = false;
  final _sel = <int, Set<int>>{}, _imp = <int, Set<int>>{};
  Set<int> _init(Map d, String k) => {for (final x in (d[k] as List? ?? []).cast<Map>()) if (x['selected']==true || asInt(x['selected'])==1) asInt(x['id'])};

  Future<void> _save(Map d) async {
    final id = asInt(d['id']);
    setState(()=>_saving=true);
    try {
      final ok = await Remote.save(_hApi, {'action':'save_kamgiri','student_id':widget.student['id'],'domain_id':id,
        'strengths':[..._sel[id]!], 'improvements':[..._imp[id]!]});
      if (mounted) toast(context, ok ? '✓ जतन झाले' : 'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.');
      widget.onSaved();
    } catch(e) { if (mounted) toast(context,'$e',error:true); }
    finally { if (mounted) setState(()=>_saving=false); }
  }

  Widget _list(String title, Color clr, List<Map> opts, Set<int> sel) => Card(child: Padding(
    padding: const EdgeInsets.fromLTRB(4,10,4,6),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Padding(padding: const EdgeInsets.symmetric(horizontal:10), child: Text('$title (${sel.length})', style: TextStyle(fontWeight:FontWeight.w700, color:clr))),
      for (final o in opts) CheckboxListTile(dense:true, controlAffinity:ListTileControlAffinity.leading,
        value: sel.contains(asInt(o['id'])), title: Text('${o['text']}', style: const TextStyle(height:1.35)),
        onChanged:(v)=>setState(()=> v==true ? sel.add(asInt(o['id'])) : sel.remove(asInt(o['id'])))),
    ])));

  @override
  Widget build(BuildContext context) {
    if (_di >= widget.domains.length) _di = 0;
    final d = widget.domains[_di]; final id = asInt(d['id']);
    _sel.putIfAbsent(id, ()=>_init(d,'strengths')); _imp.putIfAbsent(id, ()=>_init(d,'improvements'));
    final so = (d['strengths'] as List? ?? []).cast<Map>(), io = (d['improvements'] as List? ?? []).cast<Map>();
    return Column(children: [
      SizedBox(height:48, child: ListView(scrollDirection:Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal:10,vertical:6), children: [
        for (var i=0;i<widget.domains.length;i++) Padding(padding: const EdgeInsets.only(right:6),
          child: ChoiceChip(label: Text('${widget.domains[i]['name_mr']}'), selected: i==_di, onSelected:(_)=>setState(()=>_di=i))),
      ])),
      Expanded(child: ListView(padding: const EdgeInsets.fromLTRB(12,4,12,12), children: [
        const Text('प्रत्येक विकास क्षेत्रासाठी विद्यार्थ्याची सामर्थ्ये आणि सुधारणा क्षेत्रे निवडा. हे संपूर्ण शैक्षणिक वर्षासाठी एकदाच भरायचे आहे (वर्षाच्या सारांश पानावर छापले जाते).',
          style: TextStyle(fontSize:12.5, color:Colors.black54, height:1.4)),
        const SizedBox(height:8),
        if (so.isEmpty && io.isEmpty) const Padding(padding: EdgeInsets.all(16), child: Text('कामगिरी बँक उपलब्ध नाही.')),
        if (so.isNotEmpty) _list('💪 सामर्थ्य (Strength)', const Color(0xFF059669), so, _sel[id]!),
        if (io.isNotEmpty) _list('📈 सुधारणा (Improvement)', const Color(0xFFD97706), io, _imp[id]!),
      ])),
      SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: SizedBox(width: double.infinity,
        child: FilledButton.icon(icon: const Icon(Icons.save), label: Text('जतन करा — ${d['name_mr']}'),
          onPressed: _saving ? null : ()=>_save(d))))),
    ]);
  }
}


// ═══════════════════════════ STAGE WIZARD (इ.३–८) ═══════════════════════════
class HpcStageScreen extends StatefulWidget {
  const HpcStageScreen({super.key, required this.student, required this.std});
  final Map student; final int std;
  @override State<HpcStageScreen> createState() => _HpcStageScreenState();
}
class _HpcStageScreenState extends State<HpcStageScreen> {
  int _term = 1;

  @override
  Widget build(BuildContext context) {
    final name = '${widget.student['name_mr']??widget.student['name']}';
    final isMid = widget.std >= 6;
    return Scaffold(
      appBar: AppBar(title:Text(name, overflow:TextOverflow.ellipsis),
        actions:[
          Padding(padding: const EdgeInsets.only(right:4), child:ChoiceChip(label: const Text('सत्र १'), selected:_term==1, onSelected:(_)=>setState(()=>_term=1))),
          Padding(padding: const EdgeInsets.only(right:4), child:ChoiceChip(label: const Text('सत्र २'), selected:_term==2, onSelected:(_)=>setState(()=>_term=2))),
          IconButton(
            icon: const Icon(Icons.manage_accounts_outlined),
            tooltip: 'विद्यार्थी संपादन (माहिती, उंची, वजन)',
            onPressed: () async {
              final ok = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: Map<String, dynamic>.from(widget.student))));
              if (!context.mounted) return;
              if (ok == true) {
                toast(context, 'विद्यार्थी माहिती अद्ययावत झाली');
              }
            },
          ),
        ]),
      body: ApiLoader(path:_hApi,
        query: {'action':'stage_get','student_id':'${widget.student['id']}'},
        builder: (c,d,reload) {
          if (d['error'] != null || d['allowed']==false) return Center(child:Padding(padding: const EdgeInsets.all(28),
            child:Column(mainAxisSize:MainAxisSize.min, children:[
              const Icon(Icons.lock_outline,size:60,color:TG.amber), const SizedBox(height:16),
              Text('${d['access_message']??d['error']??'या स्तरासाठी सशुल्क योजना आवश्यक आहे.'}', textAlign:TextAlign.center),
            ])));
          final card = (d['card'] as Map?) ?? {};
          final subjects = (d['subjects'] as List? ?? []).cast<Map>();
          if (subjects.isEmpty) return const EmptyState('विषय सापडले नाहीत.', icon:Icons.book_outlined);
          return Column(children:[
            Container(color:_sClr('${card['status']}').withValues(alpha:.1),
              padding: const EdgeInsets.symmetric(horizontal:14,vertical:8),
              child:Row(children:[
                Icon(_sClr('${card['status']}')==const Color(0xFF059669)?Icons.check_circle:Icons.hourglass_top,
                  color:_sClr('${card['status']}'), size:18),
                const SizedBox(width:8),
                Expanded(child:Text(_sLbl('${card['status']}'),style: const TextStyle(fontWeight:FontWeight.w700))),
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
                    }
                  },
                ),
                if (asInt(card['id'])>0) IconButton(
                  icon: const Icon(Icons.picture_as_pdf_outlined, color:Color(0xFFB3261E)),
                  tooltip: 'PDF प्रिंट',
                  onPressed: () => _showStagePrintOptions(c, card['id'], name)),
                FilledButton(
                  style:FilledButton.styleFrom(backgroundColor: const Color(0xFF059669), padding: const EdgeInsets.symmetric(horizontal:14,vertical:8)),
                  onPressed:()=>_saveCard(c,card,reload),
                  child: const Text('पूर्ण ✓')),
              ]),
            ),
            Expanded(child:ListView(padding: const EdgeInsets.all(12), children:[
              Builder(builder: (_) {
                final pa = (d['parta'] as Map?) ?? {};
                final filled = [for (final k in ['intro_json','parent_json','parent_teacher_json']) (pa[k] is Map && (pa[k] as Map).isNotEmpty)];
                final n = filled.where((e) => e).length;
                return Card(color: const Color(0xFFFDF2F8), child: ListTile(
                  leading: const CircleAvatar(backgroundColor: Color(0xFFDB2777), child: Text('अ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800))),
                  title: const Text('भाग अ — ओळख व पालक माहिती', style: TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text(n == 3 ? '✓ तिन्ही विभाग भरले' : '$n / ३ विभाग भरले · ${isMid ? 'माझ्याबद्दल थोडेसे' : 'मी व माझा परिसर'}, पालक सहयोग, शिक्षक सूचना', style: const TextStyle(fontSize: 12)),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () async {
                    await Navigator.push(c, MaterialPageRoute(builder: (_) => HpcPartAScreen(student: widget.student, isMid: isMid, parta: pa)));
                    reload();
                  }));
              }),
              const Padding(padding: EdgeInsets.fromLTRB(4, 12, 4, 6), child: Text('भाग ब — विषयनिहाय नोंद', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
              for (final sub in subjects)
                _StageSubCard(key:ValueKey('${sub['id']}_$_term'), subject:sub, term:_term, student:widget.student, isMid:isMid, meta:d, onSaved:reload),
              const SizedBox(height:80),
            ])),
          ]);
        },
      ),
    );
  }

  Future<void> _saveCard(BuildContext ctx, Map card, VoidCallback reload) async {
    try {
      await Remote.save(_hApi,{'action':'stage_save','stage_action':'status','student_id':widget.student['id'],'status':'completed'});
      if (ctx.mounted) toast(ctx,'✓ HPC पूर्ण झाले!');
      reload();
    } catch(e) { if (ctx.mounted) toast(ctx,'$e',error:true); }
  }
}


// Stage Subject Card ────────────────────────────────────────────────────────
class _StageSubCard extends StatefulWidget {
  const _StageSubCard({super.key, required this.subject, required this.term, required this.student, required this.isMid, this.meta=const {}, required this.onSaved});
  final Map subject, student, meta; final int term; final bool isMid; final VoidCallback onSaved;
  @override State<_StageSubCard> createState() => _StageSubCardState();
}
class _StageSubCardState extends State<_StageSubCard> {
  static const _teacherF=[('activity_mr','उपक्रम / कृती'),('assessment_questions_mr','मूल्यमापनाचे प्रश्न'),('teacher_notes_mr','शिक्षकाच्या नोंदी'),('challenges_mr','अडचणी / आव्हाने'),('support_mr','आवश्यक आधार')];
  static const _selfF=[('liked_most_mr','मला सर्वात जास्त आवडले'),('need_practice_mr','मला अधिक सराव हवा'),('need_help_mr','मला मदत हवी'),('my_learning_mr','मी काय शिकलो/शिकले')];
  static const _peerF=[('peer_need_practice_mr','मित्राला अधिक सराव हवा'),('peer_need_help_mr','मित्राला मदत हवी')];
  static const _endF=[('teacher_help_mr','शिक्षक कशी मदत करतील'),('teacher_observation_mr','शिक्षकाचे निरीक्षण')];
  static const _selfOpts=[('','—'),('yes','😊 हो'),('some','😐 काही प्रमाणात'),('no','☹ नाही')];
  bool _exp=false, _saving=false, _savingSum=false;
  final Map<String,TextEditingController> _t={};
  final Map<String,String> _lv={}, _sumLv={};
  final Map<String,TextEditingController> _rt={};
  String _src='', _actSrc='';
  final Map<String,String> _self={}, _peer={};
  final Map<String,TextEditingController> _spc={}, _ppc={};
  List<String> _goals=[], _comps=[], _topics=[], _appr=[], _str=[], _hur=[];
  final _strOther=TextEditingController(), _hurOther=TextEditingController(), _peerName=TextEditingController();
  final _sumRemark=TextEditingController(), _sumCredit=TextEditingController();

  List<Map> get _abil => ((widget.meta['abilities'] as List?)?.cast<Map>()) ?? const [
    {'key':'awareness','name_mr':'जाणीवजागृती'},{'key':'sensitivity','name_mr':'संवेदनशीलता'},{'key':'creativity','name_mr':'सर्जनशीलता'}];
  List<Map> get _levels => ((widget.meta['levels'] as List?)?.cast<Map>()) ?? const [
    {'key':'prarambhik','name_mr':'प्रारंभिक'},{'key':'praveen','name_mr':'प्रवीण'},{'key':'pragat','name_mr':'प्रगत'}];
  List<Map> _stmts(String k) => (((widget.meta['statements'] as Map?)?[k] as List?)?.cast<Map>()) ?? const [];
  List<Map> get _classmates => ((widget.meta['classmates'] as List?)?.cast<Map>()) ?? const [];
  TextEditingController _c(String k) => _t.putIfAbsent(k, ()=>TextEditingController());

  List<String> _getPhrases(String key) {
    final p = _mp(widget.subject['phrases']);
    final l = p[key];
    if (l is List) return [for (final x in l) '$x'.trim()].where((x) => x.isNotEmpty).toList();
    return <String>[];
  }

  List<String> _getRemarkPhrases() {
    final p = _mp(widget.subject['phrases']);
    final res = <String>[];
    final pragat = (p['remark_pragat'] as List? ?? []);
    for (final x in pragat) { final s = '$x'.trim(); if (s.isNotEmpty) res.add('🌟 [प्रगत] $s'); }
    final praveen = (p['remark_praveen'] as List? ?? []);
    for (final x in praveen) { final s = '$x'.trim(); if (s.isNotEmpty) res.add('⭐ [प्रवीण] $s'); }
    final prarambhik = (p['remark_prarambhik'] as List? ?? []);
    for (final x in prarambhik) { final s = '$x'.trim(); if (s.isNotEmpty) res.add('🌱 [प्रारंभिक] $s'); }
    final general = (p['remark'] as List? ?? []);
    for (final x in general) { final s = '$x'.trim(); if (s.isNotEmpty) res.add(s); }
    return res;
  }

  @override void initState() { super.initState(); _load(); }
  @override void dispose() {
    for (final c in [..._t.values, ..._rt.values, ..._spc.values, ..._ppc.values, _strOther, _hurOther, _peerName, _sumRemark, _sumCredit]) { c.dispose(); }
    super.dispose();
  }

  List<String> _ls(dynamic v) => v is List ? [for (final e in v) '$e'] : <String>[];
  Map _mp(dynamic v) => v is Map ? v : const {};

  void _load() {
    final terms=(widget.subject['terms'] as List?)?.cast<Map>()??[];
    final td=terms.firstWhere((t)=>asInt(t['term'])==widget.term,orElse:()=>{});
    for (final f in [..._teacherF, ..._selfF, ..._peerF, ..._endF]) { _c(f.$1).text='${td[f.$1]??''}'; }
    _peerName.text='${td['peer_name']??''}';
    final rjAll=_mp(td['rubric_json']);
    _src='';
    _actSrc='';
    for (final a in _abil) { final k='${a['key']}'; final rj=_mp(rjAll[k]);
      _lv[k]='${td['level_$k']??''}'.isNotEmpty?'${td['level_$k']}':'${rj['level']??''}';
      _rt.putIfAbsent(k, ()=>TextEditingController()).text='${rj['text']??''}';
      if (_src.isEmpty && '${rj['src']??''}'.isNotEmpty) _src='${rj['src']}';
      _spc.putIfAbsent(k, ()=>TextEditingController()).text='${_mp(td['self_progress_count'])[k]??''}';
      _ppc.putIfAbsent(k, ()=>TextEditingController()).text='${_mp(td['peer_progress_count'])[k]??''}'; }
    _goals=_ls(td['goal_codes']); _comps=_ls(td['comp_codes']); _topics=_ls(td['topic_keys']); _appr=_ls(td['activity_approach']);
    _self..clear()..addAll({for (final e in _mp(td['self_reflection']).entries) '${e.key}':'${e.value}'});
    _peer..clear()..addAll({for (final e in _mp(td['peer_reflection']).entries) '${e.key}':'${e.value}'});
    final st=_ls(td['strengths']), hu=_ls(td['hurdles']);
    _str=st.where((x)=>!x.startsWith('other:')).toList(); _strOther.text=st.firstWhere((x)=>x.startsWith('other:'),orElse:()=>'other:').substring(6);
    _hur=hu.where((x)=>!x.startsWith('other:')).toList(); _hurOther.text=hu.firstWhere((x)=>x.startsWith('other:'),orElse:()=>'other:').substring(6);
    final sm=_mp(widget.subject['summary']);
    for (final a in _abil) { _sumLv['${a['key']}']='${sm['level_${a['key']}']??''}'; }
    _sumRemark.text='${sm['remark_mr']??''}';
    _sumCredit.text=sm['credit_points_earned']==null?'':'${sm['credit_points_earned']}';
    if (mounted) setState((){});
  }

  void _toggleTopic(String topicKey, bool selected) {
    final topics = (widget.subject['topics'] as List?)?.cast<Map>() ?? [];
    final t = topics.firstWhere((x) => '${x['key']}' == topicKey, orElse: () => {});
    final comps = (widget.subject['comps'] as List?)?.cast<Map>() ?? [];
    final compCodes = (t['comp_codes'] as List? ?? []).map((e) => '$e').toList();
    setState(() {
      if (selected) {
        if (!_topics.contains(topicKey)) _topics.add(topicKey);
        for (final c in compCodes) {
          if (!_comps.contains(c)) _comps.add(c);
          final cc = comps.firstWhere((x) => '${x['code']}' == c, orElse: () => {});
          final goal = '${cc['goal'] ?? ''}';
          if (goal.isNotEmpty && !_goals.contains(goal)) _goals.add(goal);
        }
      } else {
        _topics.remove(topicKey);
      }
    });
  }

  String? _lvFromCount(String t){ final n=int.tryParse(t.trim()); if(n==null) return null; return n<=2?'prarambhik':(n<=4?'praveen':'pragat'); }

  Future<void> _save() async {
    final spc={for (final e in _spc.entries) if (e.value.text.trim().isNotEmpty) e.key:asInt(e.value.text)};
    final ppc={for (final e in _ppc.entries) if (e.value.text.trim().isNotEmpty) e.key:asInt(e.value.text)};
    if ([...spc.values, ...ppc.values].any((v)=>v<0||v>6)) { toast(context,'प्रगती संख्या 0–6 असावी',error:true); return; }
    setState(()=>_saving=true);
    try {
      final rubric={for (final a in _abil) '${a['key']}':{'level':_lv['${a['key']}']??'','text':_rt['${a['key']}']?.text.trim()??'',if (_src.isNotEmpty) 'src':_src}};
      final wheel={for (final a in _abil) '${a['key']}':(){
        final k='${a['key']}'; final t=(_lv[k]??'').isEmpty?'pragat':_lv[k]!;
        return {'teacher':t,'student':_lvFromCount(_spc[k]!.text)??t,'peer':_lvFromCount(_ppc[k]!.text)??t};
      }()};
      await Remote.save(_hApi,{
        'action':'stage_save','stage_action':'subject','student_id':widget.student['id'],
        'subject_key':widget.subject['subject_key'],'term':widget.term,
        'goal_codes':_goals,'comp_codes':_comps,'topic_keys':_topics,'activity_approach':_appr,
        for (final a in _abil) 'level_${a['key']}':_lv['${a['key']}']??'',
        'rubric_json':rubric,
        for (final f in [..._teacherF, ..._selfF, ..._endF]) f.$1:_c(f.$1).text.trim(),
        'self_reflection':{for (final e in _self.entries) if (e.value.isNotEmpty) e.key:e.value},
        if (widget.isMid) 'self_progress_count':spc,
        if (widget.isMid) ...{
          'peer_name':_peerName.text.trim(),
          'peer_reflection':{for (final e in _peer.entries) if (e.value.isNotEmpty) e.key:e.value},
          'peer_progress_count':ppc,
          for (final f in _peerF) f.$1:_c(f.$1).text.trim(),
        },
        'strengths':[..._str, if (_strOther.text.trim().isNotEmpty) 'other:${_strOther.text.trim()}'],
        'hurdles':[..._hur, if (_hurOther.text.trim().isNotEmpty) 'other:${_hurOther.text.trim()}'],
        'wheel_json':wheel,
      });
      if(mounted){toast(context,'${widget.subject['name_mr']} — सत्र ${widget.term} जतन झाले');widget.onSaved();}
    }catch(e){if(mounted)toast(context,'$e',error:true);}
    finally{if(mounted)setState(()=>_saving=false);}
  }

  Future<void> _saveSummary() async {
    setState(()=>_savingSum=true);
    try {
      await Remote.save(_hApi,{
        'action':'stage_save','stage_action':'summary','student_id':widget.student['id'],
        'subject_key':widget.subject['subject_key'],
        for (final a in _abil) 'level_${a['key']}':_sumLv['${a['key']}']??'',
        'remark_mr':_sumRemark.text.trim(),'credit_points_earned':_sumCredit.text.trim(),
      });
      if(mounted){toast(context,'सारांश जतन झाला');widget.onSaved();}
    }catch(e){if(mounted)toast(context,'$e',error:true);}
    finally{if(mounted)setState(()=>_savingSum=false);}
  }

  Widget _h(String t) => Padding(padding: const EdgeInsets.only(top:14,bottom:6),
    child:Text(t,style: const TextStyle(fontWeight:FontWeight.w800,fontSize:14,color:TG.g800)));
  Widget _tf(TextEditingController c,String l,{int lines=2}) => Padding(padding: const EdgeInsets.only(bottom:8),
    child:TextField(controller:c,maxLines:lines,minLines:1,decoration:InputDecoration(labelText:l,border: const OutlineInputBorder(),isDense:true)));

  Widget _phraseField({
    required TextEditingController controller,
    required String label,
    required List<String> phrases,
    int lines = 2,
    String? hint,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: TG.ink))),
            if (phrases.isNotEmpty)
              InkWell(
                borderRadius: BorderRadius.circular(6),
                onTap: () async {
                  final picked = await showModalBottomSheet<String>(
                    context: context,
                    isScrollControlled: true,
                    showDragHandle: true,
                    builder: (ctx) => DraggableScrollableSheet(
                      expand: false,
                      initialChildSize: 0.65,
                      maxChildSize: 0.92,
                      builder: (ctx, sc) => Column(
                        children: [
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                            child: Row(
                              children: [
                                const Icon(Icons.auto_awesome, color: Color(0xFFBE185D), size: 18),
                                const SizedBox(width: 8),
                                Expanded(child: Text('$label — बँक निवडा', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
                              ],
                            ),
                          ),
                          const Divider(height: 1),
                          Expanded(
                            child: ListView.separated(
                              controller: sc,
                              itemCount: phrases.length,
                              separatorBuilder: (_, __) => const Divider(height: 1),
                              itemBuilder: (_, i) {
                                final text = phrases[i];
                                return ListTile(
                                  leading: CircleAvatar(
                                    radius: 12,
                                    backgroundColor: const Color(0xFFFCE7F3),
                                    child: Text('${i + 1}', style: const TextStyle(fontSize: 10, color: Color(0xFF9D174D), fontWeight: FontWeight.bold)),
                                  ),
                                  title: Text(text, style: const TextStyle(fontSize: 13.5, height: 1.35)),
                                  onTap: () => Navigator.pop(ctx, text),
                                );
                              },
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                  if (picked != null) {
                    String clean = picked;
                    if (clean.startsWith('🌟 [प्रगत] ')) clean = clean.substring('🌟 [प्रगत] '.length);
                    else if (clean.startsWith('⭐ [प्रवीण] ')) clean = clean.substring('⭐ [प्रवीण] '.length);
                    else if (clean.startsWith('🌱 [प्रारंभिक] ')) clean = clean.substring('🌱 [प्रारंभिक] '.length);
                    setState(() {
                      if (controller.text.trim().isEmpty) {
                        controller.text = clean;
                      } else {
                        controller.text = '${controller.text.trim()}\n$clean';
                      }
                    });
                    if (mounted) toast(context, '✓ जोडले: $label');
                  }
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFDF2F8),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: const Color(0xFFF472B6)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.arrow_drop_down_circle_outlined, size: 14, color: Color(0xFF9D174D)),
                      const SizedBox(width: 4),
                      Text('बँक (${phrases.length})', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF9D174D))),
                    ],
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 4),
        TextField(
          controller: controller,
          maxLines: lines,
          minLines: 1,
          decoration: InputDecoration(
            hintText: hint ?? '$label लिहा किंवा वरील बँकेतून निवडा…',
            border: const OutlineInputBorder(),
            isDense: true,
          ),
        ),
      ],
    ),
  );

  Widget _chips(List<(String,String)> opts,List<String> sel,ValueChanged<List<String>> on,{bool showText=false}) => Wrap(spacing:6,runSpacing:6,children:[
    for (final o in opts) FilterChip(label:Text(showText?o.$2:o.$1,style: const TextStyle(fontSize:12)),tooltip:o.$2,selected:sel.contains(o.$1),selectedColor:TG.g50,
      onSelected:(v){final l=List<String>.from(sel);v?l.add(o.$1):l.remove(o.$1);on(l);})]);
  Widget _lvPick(String label,String cur,ValueChanged<String> on) => DropdownButtonFormField<String>(
    isExpanded:true,
    decoration:InputDecoration(labelText:label,border: const OutlineInputBorder(),isDense:true,contentPadding: const EdgeInsets.symmetric(horizontal:8,vertical:8)),
    value:_levels.any((l)=>'${l['key']}'==cur)?cur:null,
    items:[for (final l in _levels) DropdownMenuItem(value:'${l['key']}',child:Text('${l['name_mr']}',style: const TextStyle(fontSize:12)))],
    onChanged:(v)=>on(v??''));
  Widget _counts(Map<String,TextEditingController> m,String title) => Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Text(title,style: const TextStyle(fontSize:12,color:TG.muted)),const SizedBox(height:6),
    Row(children:[for (final a in _abil) Expanded(child:Padding(padding: const EdgeInsets.only(right:6),child:TextField(controller:m['${a['key']}'],
      keyboardType:TextInputType.number,textAlign:TextAlign.center,
      decoration:InputDecoration(labelText:'${a['name_mr']}',border: const OutlineInputBorder(),isDense:true))))]),
    const SizedBox(height:8)]);
  Widget _reflect(List<Map> st,Map<String,String> m) => Column(children:[
    for (var n=0;n<st.length;n++) Padding(padding: const EdgeInsets.only(bottom:6),child:Row(children:[
      Expanded(child:Text('${st[n]['text']}',style: const TextStyle(fontSize:13))),
      SizedBox(width:150,child:Pick<String>(dense:true,label:'',value:m['${n+1}']??'',items:_selfOpts,onChanged:(v)=>setState(()=>m['${n+1}']=v??''))),
    ]))]);

  Widget _wheelCard() {
    String lvName(String? k) {
      if (k == 'pragat') return 'प्रगत';
      if (k == 'praveen') return 'प्रवीण';
      if (k == 'prarambhik') return 'प्रारंभिक';
      return '—';
    }
    Color lvColor(String? k) {
      if (k == 'pragat') return const Color(0xFF059669);
      if (k == 'praveen') return const Color(0xFF0284C7);
      if (k == 'prarambhik') return const Color(0xFFD97706);
      return TG.muted;
    }

    return Container(
      margin: const EdgeInsets.only(top: 14, bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF5F7),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFFBCFE8), width: 1.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.pie_chart, color: Color(0xFFBE185D), size: 18),
                  SizedBox(width: 6),
                  Text(
                    'विद्यार्थी प्रगतीचक्र (Evaluation Wheel)',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFFBE185D)),
                  ),
                ],
              ),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFFBE185D),
                  side: const BorderSide(color: Color(0xFFF472B6)),
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                icon: const Icon(Icons.bolt, size: 14),
                label: const Text('ऑटो-फिल चक्र', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                onPressed: () {
                  setState(() {
                    for (final a in _abil) {
                      final k = '${a['key']}';
                      if ((_lv[k] ?? '').isEmpty) _lv[k] = 'pragat';
                      if (_spc[k]?.text.trim().isEmpty ?? true) _spc[k]?.text = '6';
                      if (_ppc[k]?.text.trim().isEmpty ?? true) _ppc[k]?.text = '6';
                      if (_rt[k]?.text.trim().isEmpty ?? true) {
                        final curVal = _lv[k] ?? 'pragat';
                        final stList = _stmts(k);
                        final match = stList.firstWhere(
                          (s) => '${s['level']}' == curVal,
                          orElse: () => stList.isNotEmpty ? stList.first : {},
                        );
                        if (match['statement_mr'] != null) {
                          _rt[k]?.text = '${match['statement_mr']}';
                        }
                      }
                    }
                    for (final f in _selfF) {
                      if ((_self[f.$1] ?? '').isEmpty) _self[f.$1] = 'yes';
                    }
                    for (final f in _peerF) {
                      if ((_peer[f.$1] ?? '').isEmpty) _peer[f.$1] = 'yes';
                    }
                  });
                  toast(context, '✓ प्रगतीचक्र स्तर व मूल्यमापन स्वयंचलित भरले');
                },
              ),
            ],
          ),
          const SizedBox(height: 8),
          Table(
            border: TableBorder.all(color: const Color(0xFFFBCFE8), width: 1),
            children: [
              const TableRow(
                decoration: BoxDecoration(color: Color(0xFFFCE7F3)),
                children: [
                  Padding(padding: EdgeInsets.all(6), child: Text('क्षमता', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 11))),
                  Padding(padding: EdgeInsets.all(6), child: Text('शिक्षक', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 11))),
                  Padding(padding: EdgeInsets.all(6), child: Text('विद्यार्थी', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 11))),
                  Padding(padding: EdgeInsets.all(6), child: Text('सहाध्यायी', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 11))),
                ],
              ),
              for (final a in _abil) () {
                final k = '${a['key']}';
                final tLv = _lv[k];
                final sLv = _lvFromCount(_spc[k]?.text ?? '');
                final pLv = _lvFromCount(_ppc[k]?.text ?? '');
                return TableRow(
                  decoration: const BoxDecoration(color: Colors.white),
                  children: [
                    Padding(padding: const EdgeInsets.all(6), child: Text('${a['name_mr']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 11))),
                    Padding(padding: const EdgeInsets.all(6), child: Text(lvName(tLv), textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: lvColor(tLv)))),
                    Padding(padding: const EdgeInsets.all(6), child: Text(lvName(sLv ?? tLv), textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: lvColor(sLv ?? tLv)))),
                    Padding(padding: const EdgeInsets.all(6), child: Text(lvName(pLv ?? tLv), textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: lvColor(pLv ?? tLv)))),
                  ],
                );
              }(),
            ],
          ),
          const SizedBox(height: 6),
          const Text(
            'रुब्रिक, आत्मचिंतन व सहाध्यायी गुणांकनानुसार प्रगतीचक्र स्वयंचलित भरले जाते.',
            style: TextStyle(fontSize: 10.5, color: TG.muted),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final goals=(widget.subject['goals'] as List?)?.cast<Map>()??[];
    final comps=((widget.subject['comps'] as List?)?.cast<Map>()??[]).where((c)=>_goals.isEmpty||_goals.contains('${c['goal']}')).toList();
    final topics=(widget.subject['topics'] as List?)?.cast<Map>()??[];
    final rb=_mp(widget.subject['rubric_bank']);
    final acts=_mp(widget.subject['activities']);
    final hasData=_lv.values.any((v)=>v.isNotEmpty);
    String lvName(String k)=>'${_levels.firstWhere((l)=>'${l['key']}'==k,orElse:()=>{'name_mr':'-'})['name_mr']}';
    final credits=((_mp(widget.meta['credits'])['subjects'] as List?)?.cast<Map>()??[]).where((c)=>'${c['subject_key']}'=='${widget.subject['subject_key']}');
    final appr=_stmts('approach'), strs=_stmts('strength').where((x)=>'${x['text']}'.trim()!='इतर').toList(), hurs=_stmts('hurdle').where((x)=>'${x['text']}'.trim()!='इतर').toList();
    final btn=_saving?const SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white)):const Icon(Icons.save,size:18);
    return Card(margin: const EdgeInsets.only(bottom:10),child:Column(children:[
      ListTile(
        leading:CircleAvatar(backgroundColor:TG.g50,child:Text('${widget.subject['name_mr']}'.characters.first,style: const TextStyle(color:TG.g800,fontWeight:FontWeight.w800))),
        title:Text('${widget.subject['name_mr']}',style: const TextStyle(fontWeight:FontWeight.w800)),
        subtitle:hasData?Text([for (final a in _abil) lvName(_lv['${a['key']}']??'')].join(' / '),style: const TextStyle(fontSize:12,color:TG.muted)):null,
        trailing:Icon(_exp?Icons.expand_less:Icons.expand_more),
        onTap:()=>setState(()=>_exp=!_exp)),
      if(_exp) Padding(padding: const EdgeInsets.fromLTRB(14,0,14,14),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
        const Divider(),
        if(goals.isNotEmpty)...[_h('अभ्यासक्रमाची ध्येये'),_chips([for (final g in goals) ('${g['code']}','${g['text']}')],_goals,(l)=>setState(()=>_goals=l))],
        if(comps.isNotEmpty)...[_h('क्षमता'),_chips([for (final c in comps) ('${c['code']}','${c['text']}')],_comps,(l)=>setState(()=>_comps=l))],
        if(topics.isNotEmpty)...[
          _h('पाठ / घटक (2026-27 तचन नियोजन)'),
          const Text('घटक निवडल्यास संबंधित क्षमता आपोआप निवडल्या जातील', style: TextStyle(fontSize: 11.5, color: TG.muted)),
          const SizedBox(height: 6),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final t in topics)
              FilterChip(
                label: Text('${t['topic']}', style: const TextStyle(fontSize: 11.5)),
                selected: _topics.contains('${t['key']}'),
                selectedColor: const Color(0xFFFCE7F3),
                onSelected: (v) => _toggleTopic('${t['key']}', v),
              ),
          ]),
        ],
        if(appr.isNotEmpty)...[_h('उपक्रमाचा दृष्टिकोन'),_chips([for (final x in appr) ('${x['value']??x['text']}','${x['text']}')],_appr,(l)=>setState(()=>_appr=l),showText:true)],
        
        // Activity & Assessment Questions Bank
        if (acts.isNotEmpty) Container(
          margin: const EdgeInsets.symmetric(vertical: 8),
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: const Color(0xFFFDF2F8),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFFBCFE8)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Row(
                children: [
                  Icon(Icons.lightbulb_outline, color: Color(0xFFBE185D), size: 18),
                  SizedBox(width: 6),
                  Text('💡 कृती व प्रश्न बँक', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFF9D174D))),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      isExpanded: true,
                      value: acts.containsKey(_actSrc) ? _actSrc : null,
                      hint: const Text('— क्षमता निवडा (कृती व प्रश्न बँक) —', style: TextStyle(fontSize: 12)),
                      decoration: const InputDecoration(border: OutlineInputBorder(), isDense: true, contentPadding: EdgeInsets.symmetric(horizontal: 10, vertical: 8)),
                      items: [
                        for (final c in ((widget.subject['comps'] as List?)?.cast<Map>() ?? []).where((c) => acts.containsKey('${c['code']}')))
                          DropdownMenuItem(
                            value: '${c['code']}',
                            child: Text(
                              '${c['code']} : ${acts['${c['code']}']?['title'] ?? c['text']}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 12),
                            ),
                          ),
                      ],
                      onChanged: (v) => setState(() => _actSrc = v ?? ''),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton.tonal(
                    onPressed: _actSrc.isEmpty ? null : () {
                      final act = _mp(acts[_actSrc]);
                      if (act.isNotEmpty) {
                        setState(() {
                          if (act['activity_mr'] != null && '${act['activity_mr']}'.trim().isNotEmpty) {
                            _c('activity_mr').text = '${act['activity_mr']}'.trim();
                          }
                          final q = act['assessment_questions'];
                          if (q is List && q.isNotEmpty) {
                            _c('assessment_questions_mr').text = q.map((e) => '$e'.trim()).join('\n');
                          }
                          if (act['approach'] != null && '${act['approach']}'.trim().isNotEmpty) {
                            final app = '${act['approach']}'.trim();
                            if (!_appr.contains(app)) _appr.add(app);
                          }
                        });
                        toast(context, '✓ कृती व प्रश्न भरले');
                      }
                    },
                    child: const Text('भरा'),
                  ),
                ],
              ),
            ],
          ),
        ),

        _phraseField(controller: _c('activity_mr'), label: 'उपक्रम / कृती', phrases: const [], lines: 2, hint: 'कृती / उपक्रम लिहा किंवा वरील बँकेतून भरा…'),
        _phraseField(controller: _c('assessment_questions_mr'), label: 'मूल्यांकन प्रश्न', phrases: const [], lines: 2, hint: 'मूल्यांकन प्रश्न प्रति ओळ एक…'),

        _h('मूल्यांकन निकष (रुब्रिक) — कामगिरी स्तर'),
        if (rb.isNotEmpty) Padding(padding: const EdgeInsets.only(bottom:8), child: Row(children:[
          Expanded(child: DropdownButtonFormField<String>(isExpanded:true,
            value: rb.containsKey(_src) ? _src : null,
            hint: const Text('✨ सूचना — क्षमता निवडा', style: TextStyle(fontSize:12)),
            decoration: const InputDecoration(border: OutlineInputBorder(), isDense:true),
            items: [for (final c in ((widget.subject['comps'] as List?)?.cast<Map>() ?? []).where((c) => rb.containsKey('${c['code']}') && (_comps.isEmpty || _comps.contains('${c['code']}'))))
              DropdownMenuItem(value:'${c['code']}', child: Text('${c['code']} : ${c['text']}', maxLines:1, overflow:TextOverflow.ellipsis, style: const TextStyle(fontSize:12)))],
            onChanged: (v) => setState(() => _src = v ?? ''))),
          const SizedBox(width:6),
          OutlinedButton(onPressed: _src.isEmpty ? null : () => setState(() {
            for (final a in _abil) { final k='${a['key']}'; final lv=(_lv[k]??'').isEmpty?'pragat':_lv[k]!;
              _lv[k]=lv; final t=_mp(_mp(rb[_src])[k])[lv]; if (t!=null) _rt[k]!.text='$t'; }
          }), child: const Text('भरा')),
        ])),
        for (final a in _abil) Container(margin: const EdgeInsets.only(bottom:8), padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: const Color(0xFFFDEEF6).withValues(alpha:.5), borderRadius: BorderRadius.circular(8)),
          child: Column(crossAxisAlignment:CrossAxisAlignment.start, children:[
            _lvPick('${a['name_mr']} — स्तर', _lv['${a['key']}']??'', (v) => setState(() {
              final k='${a['key']}'; _lv[k]=v;
              final t=_src.isEmpty?null:_mp(_mp(rb[_src])[k])[v];
              if (t!=null && _rt[k]!.text.trim().isEmpty) _rt[k]!.text='$t';
            })),
            const SizedBox(height:6),
            TextField(controller:_rt['${a['key']}'], maxLines:3, minLines:1,
              decoration: InputDecoration(labelText:'${a['name_mr']} — निरीक्षण / निकष मजकूर', border: const OutlineInputBorder(), isDense:true)),
          ])),

        Row(children: [
          Expanded(child: _phraseField(controller: _c('challenges_mr'), label: 'आव्हाने', phrases: _getPhrases('challenges'), lines: 2)),
          const SizedBox(width: 8),
          Expanded(child: _phraseField(controller: _c('support_mr'), label: 'मात / मदत', phrases: _getPhrases('support'), lines: 2)),
        ]),
        _phraseField(controller: _c('teacher_notes_mr'), label: 'शिक्षकांच्या निरीक्षणात्मक नोंदी (शिक्षक अभिप्राय)', phrases: _getPhrases('teacher_notes'), lines: 2),

        _h('विद्यार्थ्याचे आत्मचिंतन'),
        _reflect(_stmts('self'),_self),
        if(widget.isMid) _counts(_spc,'माझ्या प्रगतीचा आराखडा — गोल केलेल्या विधानांची संख्या (0–6)'),
        Row(children: [
          Expanded(child: _phraseField(controller: _c('liked_most_mr'), label: 'सर्वात आवडले', phrases: _getPhrases('liked_most'), lines: 1)),
          const SizedBox(width: 6),
          Expanded(child: _phraseField(controller: _c('need_practice_mr'), label: 'अधिक सराव', phrases: _getPhrases('need_practice'), lines: 1)),
          const SizedBox(width: 6),
          Expanded(child: _phraseField(controller: _c('need_help_mr'), label: 'मदत हवी', phrases: _getPhrases('need_help'), lines: 1)),
        ]),
        _phraseField(controller: _c('my_learning_mr'), label: 'माझे शिकणे', phrases: _getPhrases('my_learning'), lines: 2),

        if(widget.isMid)...[
          _h('सहाध्यायी अभिप्राय (Peer Assessment)'),
          if (_classmates.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: SheetPick(
                label: 'सहाध्यायी निवडा',
                hint: '— वर्गातील सहाध्यायी निवडा —',
                options: [for (final cm in _classmates) '${cm['roll_no'] != null && '${cm['roll_no']}'.isNotEmpty ? '(${cm['roll_no']}) ' : ''}${cm['name']}'],
                onPicked: (v) => setState(() => _peerName.text = v),
              ),
            ),
          _tf(_peerName,'सहाध्यायीचे नाव (किंवा थेट नाव लिहा)',lines:1),
          _reflect(_stmts('peer'),_peer),
          _counts(_ppc,'सहाध्यायी प्रगतीचा आराखडा — विधानांची संख्या (0–6)'),
          Row(children: [
            Expanded(child: _phraseField(controller: _c('peer_need_practice_mr'), label: 'अधिक सराव', phrases: _getPhrases('peer_need_practice'), lines: 1)),
            const SizedBox(width: 8),
            Expanded(child: _phraseField(controller: _c('peer_need_help_mr'), label: 'मदत हवी', phrases: _getPhrases('peer_need_help'), lines: 1)),
          ]),
        ],
        if(strs.isNotEmpty)...[_h('बलस्थाने'),_chips([for (final x in strs) ('${x['value']??x['text']}','${x['text']}')],_str,(l)=>setState(()=>_str=l),showText:true),const SizedBox(height:6),_tf(_strOther,'इतर',lines:1)],
        if(hurs.isNotEmpty)...[_h('अडथळे'),_chips([for (final x in hurs) ('${x['value']??x['text']}','${x['text']}')],_hur,(l)=>setState(()=>_hur=l),showText:true),const SizedBox(height:6),_tf(_hurOther,'इतर',lines:1)],
        const SizedBox(height:6),
        _phraseField(controller: _c('teacher_help_mr'), label: 'विद्यार्थ्याच्या प्रगतीत वाढ होण्यासाठी मी मदत करू शकतो का?', phrases: _getPhrases('teacher_help'), lines: 2),
        _phraseField(controller: _c('teacher_observation_mr'), label: 'शिक्षकांचे निरीक्षण आणि शिफारशी', phrases: _getPhrases('teacher_observation'), lines: 2),

        if(widget.isMid) _wheelCard(),

        const SizedBox(height:10),
        SizedBox(width:double.infinity,child:FilledButton.icon(onPressed:_saving?null:_save,icon:btn,label:Text('सत्र ${widget.term} जतन करा'))),
        _h('वार्षिक सारांश (भाग – क)'),
        Row(children:[for (final a in _abil) Expanded(child:Padding(padding: const EdgeInsets.only(right:6),
          child:_lvPick('${a['name_mr']}',_sumLv['${a['key']}']??'',(v)=>setState(()=>_sumLv['${a['key']}']=v))))]),
        const SizedBox(height:8),
        _phraseField(controller: _sumRemark, label: 'विषय शेरा', phrases: _getRemarkPhrases(), lines: 2, hint: 'विषय शेरा लिहा किंवा वरील बँकेतून निवडा…'),
        TextField(controller:_sumCredit,keyboardType: const TextInputType.numberWithOptions(decimal:true),
          decoration:InputDecoration(labelText:'मिळवलेले श्रेयांक गुण',helperText:credits.isEmpty?null:'कमाल ${credits.first['credit_points']}',border: const OutlineInputBorder(),isDense:true)),
        const SizedBox(height:8),
        SizedBox(width:double.infinity,child:OutlinedButton.icon(onPressed:_savingSum?null:_saveSummary,icon: const Icon(Icons.summarize_outlined,size:18),label: const Text('सारांश जतन करा'))),
      ])),
    ]));
  }
}


// ═══════════════════════════ HPC भाग अ (इ.३–८) ════════════════════════════
class HpcPartAScreen extends StatefulWidget {
  const HpcPartAScreen({super.key, required this.student, required this.isMid, required this.parta});
  final Map student, parta; final bool isMid;
  @override State<HpcPartAScreen> createState() => _HpcPartAState();
}
class _HpcPartAState extends State<HpcPartAScreen> {
  static const _midF = [('likes','मला हे करायला आवडते'),('good_at','मी यात चांगला/चांगली आहे'),('want_learn','मला हे शिकायचे आहे'),('role_model','माझा आदर्श'),
    ('aspiration','मोठे होऊन मला व्हायचे आहे'),('hobbies','माझे छंद'),('need_help','मला यात मदत लागते'),('fav_book_sport','माझे आवडते पुस्तक / खेळ')];
  static const _prepF = [('age','माझे वय'),('birthday','माझा वाढदिवस'),('address','माझ्या घराचा पत्ता'),('family','माझ्या कुटुंबातील व्यक्ती'),
    ('favourites','माझा आवडता रंग / खाऊ / प्राणी'),('friends','माझे मित्र-मैत्रिणी'),('aspiration','मोठे होऊन मला व्हायचे आहे'),('likes','मला हे करायला आवडते')];
  static const _ints = ['चित्रकला','संगीत','नृत्य','नाट्य','खेळ','वाचन','लेखन','विज्ञान प्रयोग','गणित कोडी','संगणक','हस्तकला','बागकाम','समाजसेवा','भाषा','प्रवास','इतर'];
  static const _res = ['पुस्तके / गोष्टींची पुस्तके','वर्तमानपत्र / मासिके','खेळणी / खेळ','मोबाईल / टॅब','इंटरनेट','संगणक','ग्रंथालय सुविधा','शांत अभ्यासाची जागा','दूरचित्रवाणी','वाद्य / कला साहित्य','इतर','यांपैकी काही नाही'];
  static const _pq = [('p1','मी माझ्या पाल्याला दररोज अभ्यासात/गृहपाठात मदत करतो/करते.'),('p2','मी माझ्या पाल्याशी शाळेतील दिवसाबद्दल नियमित बोलतो/बोलते.'),
    ('p3','माझा पाल्य घरी वाचन/ चित्र काढणे/ खेळ यांसाठी वेळ देतो/देते.'),('p4','मी शाळेच्या पालक सभांना उपस्थित राहतो/राहते.'),('p5','माझा पाल्य घरातील कामांत नियमित मदत करतो/करते.')];
  static const _pOpen = [('strengths','माझ्या पाल्याचे सामर्थ्य / आवडी'),('improve','ज्यात सुधारणा हवी असे वाटते'),('expect','शाळेकडून अपेक्षा')];
  final _c = <String, TextEditingController>{};
  final Set<String> _iSel = {}, _rSel = {};
  final Map<String, String> _pv = {};
  bool _saving = false;

  Map _m(String k) => (widget.parta[k] is Map) ? widget.parta[k] as Map : const {};
  TextEditingController _t(String k, String v) => _c.putIfAbsent(k, () => TextEditingController(text: v));

  @override void initState() {
    super.initState();
    final i = _m('intro_json'), p = _m('parent_json'), pt = _m('parent_teacher_json');
    for (final f in (widget.isMid ? _midF : _prepF)) { _t('i.${f.$1}', '${i[f.$1] ?? ''}'); }
    _iSel.addAll([for (final x in (i['interests'] as List? ?? [])) '$x']);
    _rSel.addAll([for (final x in (p['resources'] as List? ?? [])) '$x']);
    for (final q in _pq) { _pv[q.$1] = '${p[q.$1] ?? ''}'; }
    for (final f in _pOpen) { _t('p.${f.$1}', '${p[f.$1] ?? ''}'); }
    _t('pt.term1', '${pt['term1'] ?? ''}'); _t('pt.term2', '${pt['term2'] ?? ''}');
  }
  @override void dispose() { for (final c in _c.values) { c.dispose(); } super.dispose(); }

  Future<void> _save() async {
    setState(() => _saving = true);
    String v(String k) => _c[k]!.text.trim();
    final intro = {for (final f in (widget.isMid ? _midF : _prepF)) f.$1: v('i.${f.$1}'), 'interests': _ints.where(_iSel.contains).toList()};
    final parent = {'resources': _res.where(_rSel.contains).toList(), for (final q in _pq) q.$1: _pv[q.$1] ?? '', for (final f in _pOpen) f.$1: v('p.${f.$1}')};
    try {
      final ok = await Remote.save(_hApi, {'action': 'stage_save', 'stage_action': 'parta', 'student_id': widget.student['id'],
        'intro_json': intro, 'parent_json': parent, 'parent_teacher_json': {'term1': v('pt.term1'), 'term2': v('pt.term2')}});
      if (!mounted) return;
      toast(context, ok ? 'भाग अ जतन झाला' : 'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.');
      Navigator.pop(context);
    } catch (e) { if (mounted) toast(context, '$e', error: true); }
    finally { if (mounted) setState(() => _saving = false); }
  }

  static const _suggestions = <String, List<String>>{
    'likes': [
      'चित्र काढणे व रंगवणे', 'गोष्टींची पुस्तके वाचणे', 'क्रिकेट व खो-खो खेळणे', 'सायकल चालवणे',
      'गाणी म्हणणे व ऐकणे', 'नृत्य करणे', 'नवीन गोष्टी शिकणे', 'मित्रांसोबत खेळणे'
    ],
    'good_at': [
      'धावण्यात व खेळात', 'चित्रकलेत व हस्तकलेत', 'सुंदर हस्ताक्षरात', 'गोष्ट सांगण्यात व संवादात',
      'गणित व पाढे सोडवण्यात', 'कविता पाठांतरात', 'स्वच्छतेत व वक्तशीरपणात'
    ],
    'want_learn': [
      'संगणक व टॅब चालवणे', 'इंग्रजी अस्खलित बोलणे', 'पोहायला शिकणे', 'सायकल चालवणे',
      'वाद्य वाजवणे (हार्मोनियम/तबला)', 'नवीन भाषा शिकणे', 'विज्ञान प्रयोग करणे'
    ],
    'role_model': [
      'आई-वडील', 'माझे शिक्षक', 'छत्रपती शिवाजी महाराज', 'डॉ. बाबासाहेब आंबेडकर',
      'डॉ. ए.पी.जे. अब्दुल कलाम', 'सावित्रीबाई फुले', 'सचिन तेंडुलकर'
    ],
    'aspiration': [
      'शिक्षक', 'डॉक्टर', 'इंजिनिअर', 'पोलीस अधिकारी', 'सैनिक (देशसेवा)', 'वैज्ञानिक',
      'पायलट', 'खेळाडू', 'कलाकार', 'गायक', 'शेतकरी'
    ],
    'hobbies': [
      'वाचन', 'चित्रकला', 'खेळ (क्रिकेट, खो-खो)', 'बागकाम', 'संगीत व गायन', 'हस्तकला', 'सायकलिंग'
    ],
    'fav_book_sport': [
      'श्यामची आई / क्रिकेट', 'इसपनीती / खो-खो', 'गोष्टींची पुस्तके / धावणे', 'चांदोबा / कबड्डी', 'पंचतंत्र / बॅडमिंटन'
    ],
    'favourites': [
      'लाल रंग, पुरणपोळी, ससा', 'निळा रंग, भात-भाजी, कुत्रा', 'पिवळा रंग, गुलाबजाम, मांजर', 'हिरवा रंग, पोहे, पोपट'
    ],
    'friends': [
      'आरव, समर्थ, ओम', 'सान्वी, आर्या, स्वरा', 'वेदांत, अथर्व, रुद्र'
    ],
    'strengths': [
      'माझा पाल्य अभ्यासात नियमित व आज्ञाधारक आहे.',
      'खेळ व कलागुणांत विशेष रुची आणि कौशल्य आहे.',
      'घरातील छोट्या कामांत आनंदाने मदत करतो/करते.',
      'मित्रांशी मिळून-मिसळून राहतो/राहते व सहकार्य करतो/करते.'
    ],
    'improve': [
      'वाचनाचा व लेखनाचा नियमित सराव वाढवावा.',
      'गणिताच्या मूलभूत क्रिया व पाढे अधिक पक्के करावेत.',
      'हस्ताक्षरात सुधारणा होण्यासाठी मार्गदर्शन करावे.',
      'अभ्यासात एकाग्रता व सातत्य वाढावे.'
    ],
    'expect': [
      'शिक्षकांचे प्रेमळ मार्गदर्शन व प्रोत्साहन नियमित मिळावे.',
      'शाळेत खेळ व कलागुणांना अधिक वाव मिळावा.',
      'पालक-शिक्षक संवाद नियमित राहावा.',
      'पाल्याचा सर्वांगीण विकास व्हावा हीच अपेक्षा.'
    ],
    'term1': [
      'दररोज किमान ३० मिनिटे वाचनाचा सराव घ्यावा.',
      'गणिताचे पाढे व संख्याज्ञानाचा नियमित सराव घ्यावा.',
      'पालकांनी दररोज गृहपाठावर लक्ष ठेवून कौतुक करावे.',
      'हस्ताक्षर सुधारण्यासाठी रोज एक पान सुलेखन करावे.'
    ],
    'term2': [
      'शैक्षणिक वर्षात खूप छान प्रगती केली आहे, अशीच प्रगती पुढे चालू ठेवावी.',
      'उन्हाळी सुट्टीत अवांतर वाचन व कलागुणांचा सराव चालू ठेवावा.',
      'पुढील इयत्तेसाठी आत्मविश्वास व नियमित सराव कायम ठेवावा.'
    ],
  };

  Widget _h(String t, [String? sub]) => Padding(padding: const EdgeInsets.fromLTRB(2, 18, 2, 6), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Text(t, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFFBE185D))),
    if (sub != null) Text(sub, style: const TextStyle(fontSize: 12, color: TG.muted)),
  ]));
  Widget _f(String k, String lbl, {int lines = 1}) {
    final bare = k.contains('.') ? k.split('.').last : k;
    final sugg = _suggestions[bare];
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Expanded(child: Text(lbl, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5))),
            if (sugg != null && sugg.isNotEmpty)
              SheetPick(
                label: '$lbl निवडा',
                hint: '— सुचवलेले पर्याय —',
                options: sugg,
                onPicked: (v) {
                  final cur = _c[k]!.text.trim();
                  setState(() => _c[k]!.text = cur.isEmpty ? v : '$cur, $v');
                },
              ),
          ]),
          const SizedBox(height: 4),
          TextField(controller: _c[k], maxLines: lines, minLines: 1, decoration: const InputDecoration(border: OutlineInputBorder(), isDense: true)),
        ],
      ),
    );
  }
  Widget _chips(List<String> opts, Set<String> sel) => Wrap(spacing: 6, runSpacing: 4, children: [
    for (final x in opts) FilterChip(label: Text(x, style: const TextStyle(fontSize: 12.5)), selected: sel.contains(x),
      onSelected: (b) => setState(() => b ? sel.add(x) : sel.remove(x)))]);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('भाग अ — ${widget.student['name_mr'] ?? widget.student['name']}', overflow: TextOverflow.ellipsis)),
    bottomNavigationBar: SafeArea(child: Padding(padding: const EdgeInsets.all(12), child: FilledButton.icon(
      onPressed: _saving ? null : _save,
      icon: _saving ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.save_outlined),
      label: const Text('भाग अ जतन करा')))),
    body: ListView(padding: const EdgeInsets.fromLTRB(14, 0, 14, 24), children: [
      _h('अ (2) ${widget.isMid ? 'माझ्याबद्दल थोडेसे' : 'मी व माझा परिसर'}'),
      for (final f in (widget.isMid ? _midF : _prepF)) _f('i.${f.$1}', f.$2, lines: f.$1 == 'address' || f.$1 == 'family' ? 2 : 1),
      _h('माझी आवड आहे', 'एकापेक्षा जास्त पर्याय निवडू शकता'),
      _chips(_ints, _iSel),
      _h('अ (3) पालक-शिक्षक सहयोग नोंदपत्रक', 'घरी उपलब्ध असलेली संसाधने (✓) निवडा'),
      _chips(_res, _rSel),
      _h('पाल्याच्या शिक्षणात पालकांचा सहभाग'),
      for (final q in _pq) Card(margin: const EdgeInsets.only(bottom: 8), child: Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(q.$2, style: const TextStyle(fontSize: 13.5)),
        const SizedBox(height: 6),
        SegmentedButton<String>(showSelectedIcon: false, emptySelectionAllowed: true,
          segments: const [ButtonSegment(value: 'yes', label: Text('😊 हो')), ButtonSegment(value: 'some', label: Text('😐 काही')), ButtonSegment(value: 'no', label: Text('☹ नाही'))],
          selected: {if ((_pv[q.$1] ?? '').isNotEmpty) _pv[q.$1]!},
          onSelectionChanged: (v) => setState(() => _pv[q.$1] = v.isEmpty ? '' : v.first)),
      ]))),
      _h('पालकांचे मत'),
      for (final f in _pOpen) _f('p.${f.$1}', f.$2, lines: 3),
      _h('शिक्षकांनी पालकांना दिलेल्या सूचना / पुढील पावले'),
      _f('pt.term1', 'सत्र १', lines: 3),
      _f('pt.term2', 'सत्र २', lines: 3),
    ]),
  );
}
