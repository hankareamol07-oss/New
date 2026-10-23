// ignore_for_file: curly_braces_in_flow_control_structures
import 'package:flutter/material.dart';
import '../core/remote.dart';
import '../theme.dart';
import 'cce_setup.dart';
import 'common.dart';
import 'widgets.dart';

const _api = 'cce_native.php';

// ═══════════════════════ CCE MAIN SCREEN ════════════════════════════════════
class CceScreen extends StatefulWidget {
  const CceScreen({super.key});
  @override State<CceScreen> createState() => _CceState();
}
class _CceState extends State<CceScreen> {
  int std=0, sem=1;
  String division='';

  static String _n(int n) => n.toString().split('').map((d) => '०१२३४५६७८९'[int.parse(d)]).join();

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('सातत्यपूर्ण मूल्यमापन')),
    body: ApiLoader(path:_api, query: const {'action':'class_scope'}, builder:(c,d,_){
      final stds=[for(final s in (d['standards'] as List?) ??[]) (asInt(s['std']),'${s['label']}')];
      if(stds.isEmpty) return const Center(child:Text('वर्ग उपलब्ध नाहीत.'));
      if(!stds.any((e)=>e.$1==std)) std=stds.first.$1;
      final stdLbl = stds.firstWhere((e)=>e.$1==std).$2;
      final classList = (d['classes'] as List?)?.cast<Map>() ?? [];
      final availableDivs = {
        for (final cl in classList)
          if (asInt(cl['std']) == std && '${cl['section'] ?? ''}'.trim().isNotEmpty)
            '${cl['section']}'.trim()
      };
      final divItems = [
        ('', 'सर्व तुकड्या'),
        if (availableDivs.isNotEmpty)
          for (final sec in availableDivs) (sec, 'तुकडी $sec')
        else ...const [('अ', 'तुकडी अ'), ('ब', 'तुकडी ब'), ('क', 'तुकडी क'), ('ड', 'तुकडी ड')],
      ];
      final semLbl = sem == 1 ? 'प्रथम सत्र' : 'द्वितीय सत्र';
      Widget page(String t, Widget child) => Scaffold(
        appBar: AppBar(title: Text(t), bottom: PreferredSize(preferredSize: const Size.fromHeight(22),
          child: Padding(padding: const EdgeInsets.only(bottom: 6), child: Text('$stdLbl${division.isEmpty ? '' : ' · तुकडी $division'} · $semLbl',
            style: const TextStyle(color: Colors.white70, fontSize: 12))))),
        body: child);
      final groups = <(String, List<(String, String, IconData, Widget Function())>)>[
        ('वर्ग सेटअप (वर्षातून एकदा)', [
          ('विषय', 'विषय यादी, क्रम, आकारिक/संकलित लागू', Icons.menu_book_outlined, () => CceSubjectsScreen(std: std)),
          ('भारांश', 'आकारिक ८ व संकलित ३ साधनांचे कमाल गुण', Icons.balance_outlined, () => CceMarksConfigScreen(std: std)),
          ('शिक्षक नेमणूक', 'वर्गशिक्षक व विषयनिहाय शिक्षक', Icons.person_pin_outlined, () => CceTeachersScreen(std: std, division: division)),
        ]),
        ('सत्रातील नोंदी', [
          ('उपस्थिती', 'महिनानिहाय कामाचे व उपस्थित दिवस', Icons.event_available_outlined, () => CceAttendanceScreen(std: std, division: division)),
          ('गुण नोंद', 'आकारिक (FA) व संकलित (SA) गुण, श्रेणी', Icons.edit_note, () => page('गुण नोंद', _MarksTab(std:std,sem:sem,division:division))),
          ('सहशालेय', 'आरोग्य-शा.शि., कार्यानुभव, कला, वृत्ती', Icons.palette_outlined, () => page('सहशालेय क्षेत्र', _CoschoTab(std:std,sem:sem,division:division))),
          ('वर्णनात्मक नोंदी', 'विषयनिहाय विशेष प्रगती व सुधारणा', Icons.comment_outlined, () => page('वर्णनात्मक नोंदी', _NotesTab(std:std,sem:sem,division:division))),
          ('निष्पत्ती', 'अध्ययन निष्पत्ती नोंद', Icons.flag_outlined, () => page('अध्ययन निष्पत्ती', _OutcomesTab(std:std,sem:sem,division:division))),
          ('इतर माहिती', 'उंची, वजन, जात, धर्म, सरल ID, PEN, निकाल', Icons.badge_outlined, () => page('विद्यार्थी इतर माहिती', _ExtraTab(std:std,sem:sem,division:division))),
        ]),
        ('निकाल व अहवाल', [
          ('सेटिंग्ज', 'मुख्याध्यापक, निकाल तारीख, शाळा माहिती', Icons.settings_outlined, () => const CceSettingsScreen()),
          ('अहवाल व प्रिंट', 'नोंदवही, प्रगती पुस्तक, निकाल, श्रेणी तक्ते', Icons.picture_as_pdf_outlined, () => page('अहवाल व प्रिंट', _ReportsTab(std:std,sem:sem,division:division))),
        ]),
      ];
      var no = 0;
      return ListView(padding: const EdgeInsets.only(bottom: 24), children:[
        Container(color: const Color(0xFFF0F9FF), padding: const EdgeInsets.symmetric(horizontal:14,vertical:6),
          child:Row(children:[
            _ph('टप्पा १','आकारिक\nFA 1–8', const Color(0xFF0284C7)),
            const Icon(Icons.arrow_right_alt,size:18,color:Colors.grey),
            _ph('टप्पा २','संकलित\nSA 1–3', const Color(0xFF7C3AED)),
            const Icon(Icons.arrow_right_alt,size:18,color:Colors.grey),
            _ph('टप्पा ३','वार्षिक\nनिकाल', const Color(0xFF059669)),
          ])),
        Padding(padding: const EdgeInsets.fromLTRB(12,10,12,4), child:Row(children:[
          Expanded(flex: 3, child:Pick<int>(label:'इयत्ता',value:std,items:stds,onChanged:(v)=>setState((){ std=v??std; division=''; }))),
          const SizedBox(width:6),
          Expanded(flex: 3, child:Pick<String>(label:'तुकडी',value:division,items:divItems,onChanged:(v)=>setState(()=>division=v??''))),
          const SizedBox(width:6),
          Expanded(flex: 3, child:Pick<int>(label:'सत्र',value:sem,
            items: const [(1,'प्रथम सत्र'),(2,'द्वितीय सत्र')],
            onChanged:(v)=>setState(()=>sem=v??1))),
        ])),
        for (final g in groups) ...[
          Padding(padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
            child: Text(g.$1, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF1E293B)))),
          for (final st in g.$2) Builder(builder: (_) { no++; final n = no; return Card(
            margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: ListTile(
              leading: CircleAvatar(radius: 17, backgroundColor: const Color(0xFFE0F2FE),
                child: Text(_n(n), style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0369A1)))),
              title: Text(st.$1, style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text(st.$2, style: const TextStyle(fontSize: 12)),
              trailing: Icon(st.$3, color: const Color(0xFF0284C7)),
              onTap: () => Navigator.push(c, MaterialPageRoute(builder: (_) => st.$4())),
            )); }),
        ],
      ]);
    }),
  );

  Widget _ph(String phase,String label,Color color)=>Expanded(child:Container(
    margin: const EdgeInsets.symmetric(horizontal:3),
    padding: const EdgeInsets.symmetric(vertical:4,horizontal:6),
    decoration:BoxDecoration(color:color.withValues(alpha:.1),borderRadius:BorderRadius.circular(8),border:Border.all(color:color.withValues(alpha:.3))),
    child:Column(children:[
      Text(phase,style:TextStyle(fontSize:10,fontWeight:FontWeight.w700,color:color)),
      Text(label,style:TextStyle(fontSize:9,color:color.withValues(alpha:.8)),textAlign:TextAlign.center),
    ])));
}

Future<void> _saved(BuildContext c,Future<bool> f) async {
  try { final ok=await f; if(c.mounted) toast(c,ok?'जतन झाले':'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.'); }
  catch(e){ if(c.mounted) toast(c,'$e',error:true); }
}

// ═══════ Tab 1: गुण नोंद ═════════════════════════════════════════════════════
class _MarksTab extends StatefulWidget {
  const _MarksTab({required this.std, required this.sem, this.division=''});
  final int std,sem; final String division;
  @override State<_MarksTab> createState() => _MarksTabState();
}
class _MarksTabState extends State<_MarksTab> {
  int subjectId=0; int idx=0; bool vertical=true;

  List<Widget> _vertical(List<Map> students, Map<int,Map> marks, Map subject, Map labels, List<Map> scale, VoidCallback reload){
    if(idx>=students.length) idx=students.length-1;
    final s=students[idx]; final sid=asInt(s['id']);
    final filled=students.where((x)=>marks.containsKey(asInt(x['id']))).length;
    return [
      Padding(padding: const EdgeInsets.fromLTRB(12,0,12,4), child: Row(children:[
        IconButton.filledTonal(tooltip:'मागील', onPressed: idx>0?()=>setState(()=>idx--):null, icon: const Icon(Icons.chevron_left)),
        const SizedBox(width:6),
        Expanded(child: Pick<int>(label:'विद्यार्थी निवडा (${idx+1} / ${students.length})', value: idx,
          items:[for(var i=0;i<students.length;i++)(i,'${students[i]['roll_no']??i+1}. ${students[i]['name_mr']??students[i]['name']}${marks.containsKey(asInt(students[i]['id']))?'  ✓':''}')],
          onChanged:(v)=>setState(()=>idx=v??idx))),
        const SizedBox(width:6),
        IconButton.filledTonal(tooltip:'पुढील', onPressed: idx+1<students.length?()=>setState(()=>idx++):null, icon: const Icon(Icons.chevron_right)),
      ])),
      Padding(padding: const EdgeInsets.symmetric(horizontal:12), child: LinearProgressIndicator(value: filled/students.length, minHeight:5, borderRadius: BorderRadius.circular(4))),
      Padding(padding: const EdgeInsets.fromLTRB(12,2,12,0), child: Align(alignment: Alignment.centerLeft, child: Text('गुण भरलेले विद्यार्थी: $filled / ${students.length}', style: const TextStyle(fontSize:12,color:TG.muted)))),
      Expanded(child:_MarksSheet(key:ValueKey('m$sid-$subjectId-${marks[sid]?['grand_total']}'), std:widget.std, sem:widget.sem, subject:subject, student:s, row:marks[sid]??{},
        labels:labels, scale:scale, division:widget.division, hasNext: idx+1<students.length, inline:true,
        onDone:(next){ toast(context,'✓ ${s['name_mr']??s['name']} चे गुण जतन झाले'); setState((){ if(next && idx+1<students.length) idx++; }); reload(); })),
    ];
  }

  @override
  Widget build(BuildContext context) => ApiLoader(path:_api,
    query:{'action':'marks_get','std':'${widget.std}','semester':'${widget.sem}','subject_id':'$subjectId', if (widget.division.isNotEmpty) 'division': widget.division},
    builder:(c,d,reload){
      final subjects=(d['subjects'] as List?) ?.cast<Map>()??[];
      if(subjects.isEmpty) return Center(child:Column(mainAxisSize:MainAxisSize.min,children:[
        const Text('विषय नाहीत.'), const SizedBox(height:8),
        FilledButton(onPressed:()=>Remote.save(_api,{'action':'subjects_seed','std':widget.std}).then((_)=>reload()),child: const Text('डिफॉल्ट विषय तयार करा')),
      ]));
      if(!subjects.any((s)=>asInt(s['id'])==subjectId)){
        WidgetsBinding.instance.addPostFrameCallback((_)=>setState(()=>subjectId=asInt(subjects.first['id'])));
        return const Center(child:CircularProgressIndicator());
      }
      final subject=subjects.firstWhere((s)=>asInt(s['id'])==subjectId);
      final labels=(d['technique_labels'] as Map?) ??{};
      final scale=(d['grade_scale'] as List?)?.cast<Map>()??const <Map>[];
      final rawMarks=d['marks'];
      final marks=<int,Map>{
        if(rawMarks is Map) for(final e in rawMarks.entries) asInt(e.key):(e.value as Map)
        else if(rawMarks is List) for(final m in (rawMarks).cast<Map>()) asInt(m['student_id']):m,
      };
      final students=(d['students'] as List?) ?.cast<Map>()??[];
      return Column(children:[
        Padding(padding: const EdgeInsets.fromLTRB(12,8,12,4),
          child:Pick<int>(label:'विषय',value:subjectId,
            items:[for(final s in subjects) (asInt(s['id']),'${s['name_mr']}  (आकारिक ${s['fe_max']} + संकलित ${s['se_max']})')],
            onChanged:(v)=>setState(()=>subjectId=v??subjectId))),
        Container(margin: const EdgeInsets.fromLTRB(12,0,12,8),
          padding: const EdgeInsets.symmetric(horizontal:12,vertical:6),
          decoration:BoxDecoration(color: const Color(0xFFEFF8F4),borderRadius:BorderRadius.circular(8)),
          child:Row(mainAxisAlignment:MainAxisAlignment.spaceAround,children:[
            Text('FA: ${subject['fe_max']}',style: const TextStyle(color:Color(0xFF0284C7),fontWeight:FontWeight.w700,fontSize:13)),
            Container(width:1,height:16,color:TG.line),
            Text('SA: ${subject['se_max']}',style: const TextStyle(color:Color(0xFF7C3AED),fontWeight:FontWeight.w700,fontSize:13)),
            Container(width:1,height:16,color:TG.line),
            Text('एकूण: ${asInt(subject['fe_max'])+asInt(subject['se_max'])}',style: const TextStyle(color:TG.g800,fontWeight:FontWeight.w700,fontSize:13)),
          ])),
        Padding(padding: const EdgeInsets.fromLTRB(12,0,12,6), child: SegmentedButton<bool>(
          segments: const [ButtonSegment(value:true,icon:Icon(Icons.phone_android),label:Text('उभी नोंद (मोबाईल)')),ButtonSegment(value:false,icon:Icon(Icons.list_alt),label:Text('सर्व विद्यार्थी'))],
          selected:{vertical}, onSelectionChanged:(v)=>setState(()=>vertical=v.first))),
        if(vertical && students.isNotEmpty) ..._vertical(students, marks, subject, labels, scale, reload),
        if(!vertical || students.isEmpty) Expanded(child:students.isEmpty?const Center(child:Text('विद्यार्थी नाहीत'))
          :ListView.builder(itemCount:students.length,itemBuilder:(c,i){
            final s=students[i]; final m=marks[asInt(s['id'])];
            return ListTile(
              leading:CircleAvatar(backgroundColor:m==null?TG.line:const Color(0xFFECFDF5),
                child:Text('${s['roll_no']??i+1}',style:TextStyle(color:m==null?TG.muted:TG.g800,fontWeight:FontWeight.w700,fontSize:12))),
              title:Text('${s['name_mr']??s['name']}'),
              subtitle:Text(m==null?'गुण भरलेले नाहीत':'FA:${m['fe_total']??'-'} SA:${m['se_total']??'-'} एकूण:${m['grand_total']??'-'}'),
              trailing:m==null?const Icon(Icons.add_circle_outline,color:TG.g600)
                :Container(padding: const EdgeInsets.symmetric(horizontal:10,vertical:4),
                  decoration:BoxDecoration(color:TG.g50,borderRadius:BorderRadius.circular(12),border:Border.all(color:TG.g600.withValues(alpha:.3))),
                  child:Text('${m['grade']??'-'}',style: const TextStyle(fontWeight:FontWeight.w800,color:TG.g800))),
              onTap:()=>setState((){idx=i;vertical=true;}));
          })),
      ]);
    });
}


// Marks entry bottom sheet ────────────────────────────────────────────────────
class _MarksSheet extends StatefulWidget {
  const _MarksSheet({required this.std,required this.sem,required this.subject,required this.student,required this.row,required this.labels,this.scale=const [],this.hasNext=false,this.division='',this.inline=false,this.onDone,super.key});
  final int std,sem; final Map subject,student,row,labels; final String division; final List<Map> scale; final bool hasNext, inline; final ValueChanged<bool>? onDone;
  @override State<_MarksSheet> createState() => _MarksSheetState();
}
class _MarksSheetState extends State<_MarksSheet> {
  final Map<String,TextEditingController> _c={};
  bool _saving=false;

  @override void initState(){
    super.initState();
    for(final k in ['fe1','fe2','fe3','fe4','fe5','fe6','fe7','fe8','se1','se2','se3']){
      final v=widget.row[k]; _c[k]=TextEditingController(text:v==null?'':v==0?'':'$v');
    }
  }
  @override void dispose(){ for(final c in _c.values) c.dispose(); super.dispose(); }

  Future<void> _save([bool next=false]) async {
    double feT=0,seT=0;
    for(final e in _c.entries){
      final v=double.tryParse(e.value.text.trim()); if(v==null) continue;
      final raw=widget.subject['${e.key}_max'];
      if(v<0 || (v*2)%1!=0){ toast(context,'${_lbl(e.key.substring(0,2),asInt(e.key.substring(2)))}: गुण 0.5 च्या पटीत भरा',error:true); return; }
      if(raw!=null && v>asInt(raw)){ toast(context,'${_lbl(e.key.substring(0,2),asInt(e.key.substring(2)))}: कमाल ${asInt(raw)} गुण',error:true); return; }
      if(e.key.startsWith('fe')) feT+=v; else seT+=v;
    }
    if(feT>_mx('fe_max')){ toast(context,'आकारिक एकूण कमाल ${_mx('fe_max')} पेक्षा जास्त',error:true); return; }
    if(seT>_mx('se_max')){ toast(context,'संकलित एकूण कमाल ${_mx('se_max')} पेक्षा जास्त',error:true); return; }
    setState(()=>_saving=true);
    try{
      final data=<String,dynamic>{};
      for(final e in _c.entries){ final v=double.tryParse(e.value.text.trim()); if(v!=null) data[e.key]=v; }
      await Remote.save(_api,{'action':'marks_save','std':widget.std,'semester':widget.sem,
        if (widget.division.isNotEmpty) 'division': widget.division,
        'subject_id':asInt(widget.subject['id']),'student_id':asInt(widget.student['id']),...data});
      if(!mounted) return;
      if(widget.onDone!=null) { widget.onDone!(next); } else { Navigator.pop(context,next?'next':'saved'); }
    }catch(e){ if(mounted) toast(context,'$e',error:true); }
    finally{ if(mounted) setState(()=>_saving=false); }
  }

  bool get _hasTech {
    for(var i=1;i<=8;i++){ if(widget.subject['fe${i}_max']!=null) return true; }
    for(var i=1;i<=3;i++){ if(widget.subject['se${i}_max']!=null) return true; }
    return false;
  }
  String _lbl(String g,int i){
    final m=widget.labels[g];
    final v=m is Map ? (m['$i']??m[i]) : (m is List && i<m.length ? m[i] : null);
    return v==null ? '${g=='fe'?'FA':'SA'}$i' : '$v';
  }
  Widget _fld(String k,String label,dynamic rawMax){
    if(_hasTech&&rawMax==null) return const SizedBox.shrink();
    return Padding(padding: const EdgeInsets.only(bottom:8), child: Row(children:[
      Expanded(child: Text(label, style: const TextStyle(fontSize:14, fontWeight: FontWeight.w600))),
      if(rawMax!=null) Container(margin: const EdgeInsets.only(right:8), padding: const EdgeInsets.symmetric(horizontal:8,vertical:3),
        decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(10)),
        child: Text('कमाल ${asInt(rawMax)}', style: const TextStyle(fontSize:11.5,color:TG.muted))),
      SizedBox(width:92, child: TextFormField(controller:_c[k], keyboardType: const TextInputType.numberWithOptions(decimal:true), textAlign:TextAlign.center,
        style: const TextStyle(fontSize:18,fontWeight:FontWeight.w700),
        decoration: const InputDecoration(hintText:'0', border: OutlineInputBorder(), isDense:true, contentPadding: EdgeInsets.symmetric(vertical:12)),
        onChanged:(_)=>setState((){}))),
    ]));
  }

  int _mx(String f)=>asInt(widget.subject[f]);

  @override
  Widget build(BuildContext context){
    const fe=['fe1','fe2','fe3','fe4','fe5','fe6','fe7','fe8'];
    const se=['se1','se2','se3'];
    double feT=0,seT=0;
    for(final k in fe){final v=double.tryParse(_c[k]?.text??'');if(v!=null)feT+=v;}
    for(final k in se){final v=double.tryParse(_c[k]?.text??'');if(v!=null)seT+=v;}
    final total=feT+seT;
    final feMax=_mx('fe_max'); final seMax=_mx('se_max');
    final pct=feMax+seMax>0?total/(feMax+seMax)*100:0.0;
    String gr='-';
    if(widget.scale.isNotEmpty){ for(final g in widget.scale){ if(pct>=(double.tryParse('${g['marks']}')??0)){ gr='${g['gradetext']}'; break; } } }
    else if(pct>=91)gr='A+'; else if(pct>=81)gr='A'; else if(pct>=71)gr='B+'; else if(pct>=61)gr='B'; else if(pct>=51)gr='C+'; else if(pct>=41)gr='C'; else gr='D';
    final score=Container(padding: const EdgeInsets.all(12),decoration:BoxDecoration(color: const Color(0xFFEFF8F4),borderRadius:BorderRadius.circular(10)),
      child:Row(mainAxisAlignment:MainAxisAlignment.spaceAround,children:[
        _tot('${feT.toStringAsFixed(feT%1==0?0:1)}/$feMax','आकारिक'),
        Container(width:1,height:32,color:TG.line),
        _tot('${seT.toStringAsFixed(seT%1==0?0:1)}/$seMax','संकलित'),
        Container(width:1,height:32,color:TG.line),
        _tot(total.toStringAsFixed(total%1==0?0:1),'एकूण'),
        Container(width:1,height:32,color:TG.line),
        _tot('${pct.toStringAsFixed(0)}%','टक्के'),
        Container(width:1,height:32,color:TG.line),
        _tot(gr,'श्रेणी',isGrade:true),
      ]));
    final head=<Widget>[
      Text('${widget.student['roll_no']!=null?'${widget.student['roll_no']}. ':''}${widget.student['name_mr']??widget.student['name']}',style: const TextStyle(fontWeight:FontWeight.w800,fontSize:17)),
      Text('${widget.subject['name_mr']} — सत्र ${widget.sem}',style: const TextStyle(color:TG.muted)),
      const SizedBox(height:10), score, const SizedBox(height:14),
      Text('आकारिक मूल्यमापन (कमाल ${_mx('fe_max')} गुण)',style: const TextStyle(fontWeight:FontWeight.w800,color:Color(0xFF0284C7))),
      const SizedBox(height:8),
      for(var i=1;i<=8;i++) _fld('fe$i',_lbl('fe',i),widget.subject['fe${i}_max']),
      const SizedBox(height:8),
      Text('संकलित मूल्यमापन (कमाल ${_mx('se_max')} गुण)',style: const TextStyle(fontWeight:FontWeight.w800,color:Color(0xFF7C3AED))),
      const SizedBox(height:8),
      for(var i=1;i<=3;i++) _fld('se$i',_lbl('se',i),widget.subject['se${i}_max']),
    ];
    const spin=SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white));
    final bar=Row(children:[
      if(widget.hasNext) ...[Expanded(child:OutlinedButton.icon(onPressed:_saving?null:()=>_save(), icon: const Icon(Icons.save_outlined), label: const Text('गुण जतन करा'))), const SizedBox(width:8)],
      Expanded(flex:widget.hasNext?2:1, child:FilledButton.icon(style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical:14)),
        onPressed:_saving?null:()=>_save(widget.hasNext), icon:_saving?spin:Icon(widget.hasNext?Icons.skip_next:Icons.save),
        label: Text(widget.hasNext?'जतन करा व पुढील विद्यार्थी':'गुण जतन करा'))),
    ]);
    if(widget.inline) return Column(children:[
      Expanded(child:ListView(padding: const EdgeInsets.fromLTRB(16,12,16,12), children:head)),
      Material(elevation:8, child: Padding(padding: const EdgeInsets.fromLTRB(12,8,12,10), child: bar)),
    ]);
    return Padding(padding:EdgeInsets.fromLTRB(16,20,16,MediaQuery.of(context).viewInsets.bottom+20),
      child:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,crossAxisAlignment:CrossAxisAlignment.start,children:[...head, const SizedBox(height:14), bar])));
  }
  Widget _tot(String v,String l,{bool isGrade=false})=>Column(children:[
    Text(v,style:TextStyle(fontWeight:FontWeight.w800,fontSize:isGrade?22:18,color:isGrade?const Color(0xFF059669):TG.ink)),
    Text(l,style: const TextStyle(fontSize:11,color:TG.muted)),
  ]);
}


class _StudentPicker extends StatelessWidget {
  const _StudentPicker({required this.students,required this.value,required this.onChanged});
  final List<Map> students; final int value; final ValueChanged<int> onChanged;
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.fromLTRB(12,8,12,0),
    child:Pick<int>(label:'विद्यार्थी',value:value,
      items:[for(final s in students)(asInt(s['id']),'${s['roll_no']??''} ${s['name_mr']??s['name']}')],
      onChanged:(v)=>onChanged(v??value)));
}

// ═══════ Tab 2: सहशालेय ══════════════════════════════════════════════════════
class _CoschoTab extends StatefulWidget {
  const _CoschoTab({required this.std,required this.sem,this.division=''});
  final int std,sem; final String division;
  @override State<_CoschoTab> createState() => _CoschoTabState();
}
class _CoschoTabState extends State<_CoschoTab> {
  final Map<String,Map<String,String>> _g={};
  String _loaded='';

  @override
  Widget build(BuildContext context) => ApiLoader(path:_api,
    query:{'action':'coscholastic_get','std':'${widget.std}','semester':'${widget.sem}', if (widget.division.isNotEmpty) 'division': widget.division},
    builder:(c,d,reload){
      var students=(d['students'] as List?) ?.cast<Map>()??[];
      if(widget.division.isNotEmpty) students=students.where((s)=>'${s['section']??''}'.trim()==widget.division).toList();
      if(students.isEmpty) return const EmptyState('विद्यार्थी नाहीत',icon:Icons.groups_outlined);
      final areas=(d['areas'] as List?) ?.cast<Map>()??[];
      final valid=[for(final v in (d['valid_grades'] as List?)??const ['A+','A','B','C','D']) '$v'];
      final key='${widget.std}/${widget.sem}/${widget.division}';
      if(_loaded!=key){
        _g.clear();
        final ex=d['grades'];
        if(ex is Map) ex.forEach((k,v){ if(v is Map) _g['$k']={for(final e in v.entries) '${e.key}':'${e.value??''}'}; });
        _loaded=key;
      }
      final items=<(String,String)>[('','—'),for(final v in valid)(v,v)];
      return Column(children:[
        Padding(padding: const EdgeInsets.fromLTRB(12,8,12,0),child:Wrap(spacing:6,runSpacing:6,children:[
          for(final a in areas) ActionChip(avatar: const Icon(Icons.done_all,size:16),label:Text('सर्वांना: ${a['label']}',style: const TextStyle(fontSize:12)),
            onPressed:()async{
              final g=await showDialog<String>(context:c,builder:(dc)=>SimpleDialog(title:Text('${a['label']}'),children:[
                for(final v in valid) SimpleDialogOption(onPressed:()=>Navigator.pop(dc,v),child:Text(v,style: const TextStyle(fontSize:16)))]));
              if(g!=null) setState((){ for(final s in students){ (_g['${asInt(s['id'])}']??={})['${a['key']}']=g; } });
            }),
        ])),
        Expanded(child:ListView.builder(padding: const EdgeInsets.all(12),itemCount:students.length,itemBuilder:(_,i){
          final s=students[i]; final sid='${asInt(s['id'])}';
          return Card(margin: const EdgeInsets.only(bottom:8),child:Padding(padding: const EdgeInsets.all(10),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
            Text('${s['roll_no']??i+1}. ${s['name_mr']??s['name']}',style: const TextStyle(fontWeight:FontWeight.w700)),
            const SizedBox(height:8),
            Wrap(spacing:8,runSpacing:8,children:[for(final a in areas) SizedBox(width:150,child:Pick<String>(dense:true,label:'${a['label']}',
              value:_g[sid]?['${a['key']}']??'',items:items,onChanged:(v)=>setState(()=>(_g[sid]??={})['${a['key']}']=v??'')))]),
          ])));
        })),
        SafeArea(child:Padding(padding: const EdgeInsets.all(12),child:SizedBox(width:double.infinity,
          child:FilledButton.icon(icon: const Icon(Icons.save),label: const Text('सहशालेय जतन करा'),
            onPressed:()=>_saved(c,Remote.save(_api,{
              'action':'coscholastic_save','std':widget.std,'semester':widget.sem,
              if (widget.division.isNotEmpty) 'division': widget.division,
              'cs':{for(final s in students) '${asInt(s['id'])}':{for(final a in areas) '${a['key']}':_g['${asInt(s['id'])}']?['${a['key']}']??''}},
            })))))),
      ]);
    });
}


// ═══════ Tab 3: वर्णनात्मक नोंदी ════════════════════════════════════════════
class _NotesTab extends StatefulWidget {
  const _NotesTab({required this.std, required this.sem, this.division = ''});
  final int std, sem;
  final String division;
  @override
  State<_NotesTab> createState() => _NotesTabState();
}

class _NotesTabState extends State<_NotesTab> {
  int studentId = 0, slot = 0;
  final Map<String, TextEditingController> _ctrl = {};
  bool _busy = false;

  @override
  void dispose() {
    for (final c in _ctrl.values) c.dispose();
    super.dispose();
  }

  void _autoFillCurrent(List<Map> areas, int sid, {bool overwrite = false}) {
    int filled = 0;
    for (final a in areas) {
      final sNo = asInt(a['slot']);
      final k = '$sid-$sNo';
      final ctrl = _ctrl[k];
      final def = (a['default'] ?? '').toString().trim();
      if (ctrl != null && def.isNotEmpty) {
        if (overwrite || ctrl.text.trim().isEmpty) {
          ctrl.text = def;
          filled++;
        }
      }
    }
    toast(context, filled > 0 ? '$filled विषयांच्या नोंदी स्वयंचलित भरल्या.' : 'सर्व नोंदी आधीच भरलेल्या आहेत.');
    setState(() {});
  }

  Future<void> _autoFillAll(BuildContext c, VoidCallback reload) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.bolt, color: Color(0xFF167A5B)),
            SizedBox(width: 8),
            Expanded(child: Text('सर्व विद्यार्थ्यांच्या नोंदी ऑटो-फिल?')),
          ],
        ),
        content: const Text(
          'या वर्गातील सर्व विद्यार्थ्यांच्या रिकाम्या विषयांच्या वर्णनात्मक नोंदी अधिकृत SCERT बँकेतून स्वयंचलित भरल्या जातील. लिंगानुसार (मुलगा/मुलगी) व्याकरण आपोआप बदलले जाईल.',
          style: TextStyle(fontSize: 13.5),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('रद्द करा')),
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFF167A5B)),
            onPressed: () => Navigator.pop(ctx, true),
            icon: const Icon(Icons.flash_on, size: 18),
            label: const Text('होय, ऑटो-फिल करा'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _busy = true);
    try {
      final res = await Remote.post(_api, {
        'action': 'notes_autofill_all',
        'std': widget.std,
        'semester': widget.sem,
        if (widget.division.isNotEmpty) 'division': widget.division,
        'overwrite': false,
      });
      final msg = res['message'] ?? 'नोंदी यशस्वीरीत्या भरल्या!';
      if (mounted) {
        toast(context, msg);
        _ctrl.clear();
        reload();
      }
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _saveCurrent(BuildContext c, int sid, List<Map> areas, {int? nextSid}) async {
    setState(() => _busy = true);
    try {
      final ok = await Remote.save(_api, {
        'action': 'notes_save',
        'std': widget.std,
        'semester': widget.sem,
        'student_id': sid,
        if (widget.division.isNotEmpty) 'division': widget.division,
        'note': {for (final a in areas) '${a['slot']}': _ctrl['$sid-${a['slot']}']?.text.trim() ?? ''},
      });
      if (mounted) {
        toast(context, ok ? 'नोंदी जतन केल्या.' : 'नोंदी ऑफलाइन जतन झाल्या.');
        if (nextSid != null) {
          setState(() => studentId = nextSid);
        }
      }
    } catch (e) {
      if (mounted) toast(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => ApiLoader(
        path: _api,
        query: {
          'action': 'notes_get',
          'std': '${widget.std}',
          'semester': '${widget.sem}',
          'student_id': '$studentId',
          if (widget.division.isNotEmpty) 'division': widget.division,
        },
        builder: (c, d, reload) {
          final students = (d['students'] as List?)?.cast<Map>() ?? [];
          if (students.isEmpty) return const EmptyState('विद्यार्थी नाहीत', icon: Icons.groups_outlined);
          final sid = asInt(d['student_id']);
          if (sid == 0 || studentId == 0) {
            WidgetsBinding.instance.addPostFrameCallback((_) => setState(() => studentId = sid == 0 ? asInt(students.first['id']) : sid));
            if (sid == 0) return const Center(child: CircularProgressIndicator());
          }
          final areas = (d['areas'] as List?)?.cast<Map>() ?? [];
          for (final a in areas) {
            final k = '$sid-${a['slot']}';
            if (!_ctrl.containsKey(k)) {
              final sv = '${a['saved'] ?? ''}';
              _ctrl[k] = TextEditingController(text: sv.trim().isEmpty ? '${a['default'] ?? ''}' : sv);
            }
          }

          final curIdx = students.indexWhere((s) => asInt(s['id']) == sid);
          final currStudent = curIdx >= 0 ? students[curIdx] : null;
          final prevStudent = curIdx > 0 ? students[curIdx - 1] : null;
          final nextStudent = (curIdx >= 0 && curIdx < students.length - 1) ? students[curIdx + 1] : null;
          final isGirl = d['is_girl'] == true;

          return Column(
            children: [
              _StudentPicker(students: students, value: sid, onChanged: (v) => setState(() => studentId = v)),

              // Student Info & Auto-fill Toolbar matching portal (cce/remarks_entry.php)
              Container(
                margin: const EdgeInsets.fromLTRB(12, 4, 12, 6),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: isGirl ? const Color(0xFFFFF1F2) : const Color(0xFFF0FDF4),
                  border: Border.all(color: isGirl ? const Color(0xFFFDA4AF) : const Color(0xFFBBF7D0)),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(isGirl ? '👧' : '👦', style: const TextStyle(fontSize: 22)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${currStudent?['name_mr'] ?? currStudent?['name'] ?? ''}',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                overflow: TextOverflow.ellipsis,
                              ),
                              Text(
                                'हजेरी क्र. ${currStudent?['roll_no'] ?? '-'} • इयत्ता ${widget.std} • ${isGirl ? 'मुलगी' : 'मुलगा'}',
                                style: const TextStyle(fontSize: 11.5, color: Colors.black54),
                              ),
                            ],
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: isGirl ? const Color(0xFFEC4899) : const Color(0xFF3B82F6),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            isGirl ? 'मुलगी' : 'मुलगा',
                            style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        FilledButton.tonalIcon(
                          onPressed: _busy ? null : () => _autoFillCurrent(areas, sid, overwrite: false),
                          icon: const Icon(Icons.flash_on, size: 15, color: Color(0xFF167A5B)),
                          label: const Text('रिकाम्या नोंदी ऑटो-फिल', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold)),
                          style: FilledButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
                            minimumSize: const Size(0, 32),
                          ),
                        ),
                        OutlinedButton.icon(
                          onPressed: _busy ? null : () => _autoFillCurrent(areas, sid, overwrite: true),
                          icon: const Icon(Icons.refresh, size: 15),
                          label: const Text('पुन्हा भरा', style: TextStyle(fontSize: 11.5)),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
                            minimumSize: const Size(0, 32),
                          ),
                        ),
                        FilledButton.icon(
                          onPressed: _busy ? null : () => _autoFillAll(c, reload),
                          icon: const Icon(Icons.auto_awesome, size: 15),
                          label: const Text('सर्व विद्यार्थी ऑटो-फिल', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold)),
                          style: FilledButton.styleFrom(
                            backgroundColor: const Color(0xFF167A5B),
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
                            minimumSize: const Size(0, 32),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              Padding(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 0),
                child: Pick<int>(
                  label: 'विषय / घटक',
                  value: areas.any((a) => asInt(a['slot']) == slot) ? slot : 0,
                  items: [
                    (0, '— सर्व निवडक विषय / घटक (${areas.length}) —'),
                    for (final a in areas) (asInt(a['slot']), '${a['slot']}. ${a['label']}'),
                  ],
                  onChanged: (v) => setState(() => slot = v ?? 0),
                ),
              ),

              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(12),
                  children: [
                    for (final a in areas.where((a) => slot == 0 || asInt(a['slot']) == slot))
                      Card(
                        margin: const EdgeInsets.only(bottom: 10),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${a['slot']}. ${a['label']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                              const SizedBox(height: 8),
                              if ((a['suggestions'] as List?)?.isNotEmpty == true)
                                DropdownButtonFormField<String>(
                                  isExpanded: true,
                                  value: null,
                                  hint: Text('-- तयार नोंद निवडा (${(a['suggestions'] as List).length} नोंदी उपलब्ध) --', style: const TextStyle(fontSize: 13)),
                                  decoration: const InputDecoration(
                                    labelText: 'तयार नोंदींमधून निवडा',
                                    prefixIcon: Icon(Icons.lightbulb_outline),
                                    border: OutlineInputBorder(),
                                    isDense: true,
                                  ),
                                  items: [
                                    for (final sg in (a['suggestions'] as List).cast<String>())
                                      DropdownMenuItem(
                                        value: sg,
                                        child: Text(sg, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13)),
                                      ),
                                  ],
                                  selectedItemBuilder: (_) => [
                                    for (final sg in (a['suggestions'] as List).cast<String>())
                                      Text(sg, maxLines: 1, overflow: TextOverflow.ellipsis),
                                  ],
                                  onChanged: (v) {
                                    if (v != null) setState(() => _ctrl['$sid-${a['slot']}']!.text = v);
                                  },
                                )
                              else
                                const Text('थेट नोंद खाली लिहा.', style: TextStyle(fontSize: 12, color: TG.muted)),
                              const SizedBox(height: 8),
                              TextField(
                                controller: _ctrl['$sid-${a['slot']}'],
                                maxLines: null,
                                decoration: const InputDecoration(
                                  labelText: 'वर्णनात्मक नोंद मजकूर',
                                  hintText: 'येथे वर्णनात्मक नोंद लिहा किंवा वरील यादीतून निवडा...',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    const SizedBox(height: 70),
                  ],
                ),
              ),

              // Bottom sticky navigation action bar
              SafeArea(
                child: Container(
                  padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.05),
                        offset: const Offset(0, -2),
                        blurRadius: 6,
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      if (prevStudent != null) ...[
                        OutlinedButton.icon(
                          onPressed: _busy ? null : () => setState(() => studentId = asInt(prevStudent['id'])),
                          icon: const Icon(Icons.chevron_left, size: 18),
                          label: Text('${prevStudent['roll_no']}', style: const TextStyle(fontSize: 12)),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 0),
                            minimumSize: const Size(0, 42),
                          ),
                        ),
                        const SizedBox(width: 8),
                      ],
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _busy ? null : () => _saveCurrent(c, sid, areas),
                          icon: const Icon(Icons.save, size: 18),
                          label: const Text('नोंदी जतन करा', style: TextStyle(fontWeight: FontWeight.bold)),
                          style: FilledButton.styleFrom(minimumSize: const Size(0, 42)),
                        ),
                      ),
                      if (nextStudent != null) ...[
                        const SizedBox(width: 8),
                        FilledButton.icon(
                          onPressed: _busy ? null : () => _saveCurrent(c, sid, areas, nextSid: asInt(nextStudent['id'])),
                          style: FilledButton.styleFrom(
                            backgroundColor: const Color(0xFF059669),
                            minimumSize: const Size(0, 42),
                            padding: const EdgeInsets.symmetric(horizontal: 10),
                          ),
                          icon: const Icon(Icons.arrow_forward, size: 18),
                          label: const Text('जतन व पुढील →', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5)),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      );
}

// ═══════ Tab 4: अध्ययन निष्पत्ती ════════════════════════════════════════════
class _OutcomesTab extends StatefulWidget {
  const _OutcomesTab({required this.std,required this.sem,this.division=''});
  final int std,sem; final String division;
  @override State<_OutcomesTab> createState() => _OutcomesTabState();
}
class _OutcomesTabState extends State<_OutcomesTab> {
  int studentId=0;
  String selectedSub='';
  final Map<int,int> _lv={};
  int _loadedFor=-1, _bulk=4;

  @override
  Widget build(BuildContext context) => ApiLoader(path:_api,
    query:{'action':'outcomes_get','std':'${widget.std}','semester':'${widget.sem}','student_id':'$studentId',
      if (selectedSub.isNotEmpty) 'sub': selectedSub,
      if (widget.division.isNotEmpty) 'division': widget.division},
    builder:(c,d,reload){
      final students=(d['students'] as List?) ?.cast<Map>()??[];
      if(students.isEmpty) return const EmptyState('विद्यार्थी नाहीत',icon:Icons.groups_outlined);
      final sid=asInt(d['student_id']);
      if(sid==0||studentId==0){WidgetsBinding.instance.addPostFrameCallback((_)=>setState(()=>studentId=sid==0?asInt(students.first['id']):sid));if(sid==0)return const Center(child:CircularProgressIndicator());}
      if(_loadedFor!=sid){_lv.clear();final sel=d['selected'];if(sel is Map)sel.forEach((k,v)=>_lv[asInt(k)]=asInt(v is Map?(v['level']??v['achieved']):v));else if(sel is List)for(final v in sel)if(v is Map)_lv[asInt(v['outcome_id']??v['id'])]=asInt(v['level']??v['achieved'],1);_loadedFor=sid;}
      final levels=[for(final e in ((d['levels'] as Map?) ??{}).entries)(asInt(e.key),'${e.value}')];
      final outcomes=(d['outcomes'] as List?) ?.cast<Map>()??[];
      final subs=[for(final x in (d['subjects'] as List?)??const []) '$x'];

      return Column(children:[
        _StudentPicker(students:students,value:sid,onChanged:(v)=>setState(()=>studentId=v)),
        if(subs.isNotEmpty)
          SizedBox(height:44,child:ListView(scrollDirection:Axis.horizontal,padding: const EdgeInsets.symmetric(horizontal:12,vertical:4),children:[
            Padding(padding: const EdgeInsets.only(right:6),child:ChoiceChip(label: const Text('सर्व विषय'),selected:selectedSub=='',onSelected:(_)=>setState(()=>selectedSub=''))),
            for(final s in subs)
              Padding(padding: const EdgeInsets.only(right:6),child:ChoiceChip(label:Text(s),selected:selectedSub==s,onSelected:(_)=>setState(()=>selectedSub=s))),
          ])),
        if(outcomes.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(12,8,12,0),child:Row(children:[
          Expanded(child:Pick<int>(label:'सर्वांना एकच स्तर',value:_bulk,items:levels,onChanged:(v)=>setState(()=>_bulk=v??4))),
          const SizedBox(width:8),
          OutlinedButton(onPressed:()=>setState((){for(final o in outcomes)_lv[asInt(o['id'])]=_bulk;}),child: const Text('सर्वांना लागू करा')),
        ])),
        Expanded(child:ListView.separated(padding: const EdgeInsets.all(12),itemCount:outcomes.length,
          separatorBuilder:(_,__)=> const Divider(),
          itemBuilder:(c,i){final o=outcomes[i];final id=asInt(o['id']);
            return Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
              Text('${i+1}. ${o['text']}',style: const TextStyle(height:1.4)),
              const SizedBox(height:6),
              Pick<int>(label:'स्तर',value:_lv[id]??0,items:[(0,'— निवडा —'),...levels],onChanged:(v)=>setState(()=>_lv[id]=v??0)),
            ]);})),
        SafeArea(child:Padding(padding: const EdgeInsets.all(12),child:SizedBox(width:double.infinity,
          child:FilledButton.icon(icon: const Icon(Icons.save),label: const Text('निष्पत्ती जतन करा'),
            onPressed:()=>_saved(c,Remote.save(_api,{
              'action':'outcomes_save','std':widget.std,'semester':widget.sem,'student_id':sid,
              if (widget.division.isNotEmpty) 'division': widget.division,
              'visible_ids':[for(final o in outcomes)asInt(o['id'])],
              'level':{for(final e in _lv.entries)if(e.value>0)'${e.key}':e.value},
            })))))),
      ]);
    });
}


// ═══════ Tab 5: अतिरिक्त माहिती ════════════════════════════════════════════
class _ExtraTab extends StatefulWidget {
  const _ExtraTab({required this.std,required this.sem,this.division=''});
  final int std,sem; final String division;
  @override State<_ExtraTab> createState() => _ExtraTabState();
}
class _ExtraTabState extends State<_ExtraTab> {
  int studentId=0;

  @override
  Widget build(BuildContext context) => ApiLoader(path:_api,
    query:{'action':'extra_get','std':'${widget.std}','semester':'${widget.sem}','student_id':'$studentId', if (widget.division.isNotEmpty) 'division': widget.division},
    builder:(c,d,reload){
      final students=(d['students'] as List?) ?.cast<Map>()??[];
      if(students.isEmpty) return const EmptyState('विद्यार्थी नाहीत',icon:Icons.groups_outlined);
      final sid=students.any((x)=>asInt(x['id'])==studentId)?studentId:asInt(students.first['id']);
      final s=students.firstWhere((x)=>asInt(x['id'])==sid,orElse:()=>{});
      final all=(d['extra'] as Map?)??{};
      final ex=Map<String,dynamic>.from((all['$sid'] as Map?)??{});
      return Column(children:[
        _StudentPicker(students:students,value:sid,onChanged:(v)=>setState(()=>studentId=v)),
        Expanded(child:SingleChildScrollView(padding: const EdgeInsets.all(12),child:_ExtraForm(key:ValueKey('ex$sid'),student:s,extra:ex,std:widget.std,sem:widget.sem,division:widget.division,onSaved:reload))),
      ]);
    });
}
class _ExtraForm extends StatefulWidget {
  const _ExtraForm({super.key,required this.student,required this.extra,required this.std,required this.sem,this.division='',required this.onSaved});
  final Map student,extra; final int std,sem; final String division; final VoidCallback onSaved;
  @override State<_ExtraForm> createState() => _ExtraFormState();
}
class _ExtraFormState extends State<_ExtraForm> {
  late final TextEditingController h1=TextEditingController(text:'${widget.extra['height_1']??''}');
  late final TextEditingController w1=TextEditingController(text:'${widget.extra['weight_1']??''}');
  late final TextEditingController h2=TextEditingController(text:'${widget.extra['height_2']??''}');
  late final TextEditingController w2=TextEditingController(text:'${widget.extra['weight_2']??''}');
  late final TextEditingController saral=TextEditingController(text:'${widget.extra['saral_id']??''}');
  late final TextEditingController pen=TextEditingController(text:'${widget.extra['pen']??''}');
  late final TextEditingController remark=TextEditingController(text:'${widget.extra['remark']??''}');
  late final TextEditingController cast=TextEditingController(text:'${widget.extra['cast_cat']??''}');
  late final TextEditingController religion=TextEditingController(text:'${widget.extra['religion']??''}');
  late final TextEditingController nextStd=TextEditingController(text:'${widget.extra['next_std']??''}');
  late String _res='${widget.extra['result_status']??''}';
  bool _saving=false;
  static const _resOpts=['उत्तीर्ण','अनुत्तीर्ण','प्रविष्ट','इतर'];
  @override void dispose(){ h1.dispose();w1.dispose();h2.dispose();w2.dispose();saral.dispose();pen.dispose();remark.dispose();cast.dispose();religion.dispose();nextStd.dispose();super.dispose(); }

  Future<void> _save() async {
    setState(()=>_saving=true);
    try {
      await Remote.save(_api,{'action':'extra_save','std':widget.std,'semester':widget.sem,'student_id':asInt(widget.student['id']),
        if (widget.division.isNotEmpty) 'division': widget.division,
        'ex':{'${asInt(widget.student['id'])}':{
          'height_1':h1.text.trim(),'weight_1':w1.text.trim(),'height_2':h2.text.trim(),'weight_2':w2.text.trim(),
          'cast_cat':cast.text.trim(),'religion':religion.text.trim(),
          'saral_id':saral.text.trim(),'pen':pen.text.trim(),'result_status':_res,'next_std':nextStd.text.trim(),'remark':remark.text.trim()}}});
      if(mounted){toast(context,'अतिरिक्त माहिती जतन झाली');widget.onSaved();}
    }catch(e){if(mounted)toast(context,'$e',error:true);}
    finally{if(mounted)setState(()=>_saving=false);}
  }

  static const _remarksList = [
    'अभ्यासात नियमित प्रगती आहे.',
    'वाचन व लेखनात उत्तम सुधारणा दिसून येते.',
    'खेळ व कलागुणांत विशेष प्रावीण्य आहे.',
    'नियमित उपस्थिती व आज्ञाधारक विद्यार्थी.',
    'गणितात अधिक सरावाची आवश्यकता आहे.',
    'इंग्रजी वाचनाचा सराव नियमित वाढवावा.',
    'हस्ताक्षरात सुधारणा आवश्यक आहे.',
    'सर्व विषयांत उत्तम श्रेणी मिळवली आहे.',
  ];

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Text('${widget.student['name_mr']??widget.student['name']}',style: const TextStyle(fontWeight:FontWeight.w800,fontSize:16)),
    const SectionTitle('उंची व वजन'),
    Row(children:[Expanded(child:TextField(controller:h1,keyboardType:TextInputType.number,decoration: const InputDecoration(labelText:'सत्र १ उंची (सेमी)',border:OutlineInputBorder(),isDense:true))),const SizedBox(width:8),Expanded(child:TextField(controller:w1,keyboardType:TextInputType.number,decoration: const InputDecoration(labelText:'सत्र १ वजन (किलो)',border:OutlineInputBorder(),isDense:true)))]),
    const SizedBox(height:8),
    Row(children:[Expanded(child:TextField(controller:h2,keyboardType:TextInputType.number,decoration: const InputDecoration(labelText:'सत्र २ उंची (सेमी)',border:OutlineInputBorder(),isDense:true))),const SizedBox(width:8),Expanded(child:TextField(controller:w2,keyboardType:TextInputType.number,decoration: const InputDecoration(labelText:'सत्र २ वजन (किलो)',border:OutlineInputBorder(),isDense:true)))]),
    const SectionTitle('जात व धर्म'),
    Row(children:[
      Expanded(child:DropdownButtonFormField<String>(
        value: const ['खुला / Open', 'SC', 'ST', 'VJ/DT', 'NT-B', 'NT-C', 'NT-D', 'OBC', 'SBC', 'EWS'].contains(cast.text) ? cast.text : null,
        decoration: const InputDecoration(labelText:'प्रवर्ग/जात',border:OutlineInputBorder(),isDense:true),
        hint: Text(cast.text.isNotEmpty ? cast.text : 'निवडा', overflow: TextOverflow.ellipsis),
        items: [for (final c in const ['खुला / Open', 'SC', 'ST', 'VJ/DT', 'NT-B', 'NT-C', 'NT-D', 'OBC', 'SBC', 'EWS']) DropdownMenuItem(value: c, child: Text(c))],
        onChanged: (v) => setState(() => cast.text = v ?? ''),
      )),
      const SizedBox(width:8),
      Expanded(child:DropdownButtonFormField<String>(
        value: const ['हिंदू', 'बौद्ध', 'मुस्लिम', 'जैन', 'ख्रिश्चन', 'शीख', 'लिंगायत', 'इतर'].contains(religion.text) ? religion.text : null,
        decoration: const InputDecoration(labelText:'धर्म',border:OutlineInputBorder(),isDense:true),
        hint: Text(religion.text.isNotEmpty ? religion.text : 'निवडा', overflow: TextOverflow.ellipsis),
        items: [for (final r in const ['हिंदू', 'बौद्ध', 'मुस्लिम', 'जैन', 'ख्रिश्चन', 'शीख', 'लिंगायत', 'इतर']) DropdownMenuItem(value: r, child: Text(r))],
        onChanged: (v) => setState(() => religion.text = v ?? ''),
      )),
    ]),
    const SectionTitle('ओळख क्रमांक'),
    Row(children:[Expanded(child:TextField(controller:saral,decoration: const InputDecoration(labelText:'SARAL ID',border:OutlineInputBorder(),isDense:true))),const SizedBox(width:8),Expanded(child:TextField(controller:pen,decoration: const InputDecoration(labelText:'PEN (UDISE)',border:OutlineInputBorder(),isDense:true)))]),
    const SectionTitle('निकाल व प्रगती शेरा'),
    Row(children:[
      Expanded(child:DropdownButtonFormField<String>(value:_resOpts.contains(_res)?_res:null,decoration: const InputDecoration(labelText:'निकाल स्थिती',border:OutlineInputBorder(),isDense:true),hint: const Text('निवडा'),
        items:[for(final r in _resOpts) DropdownMenuItem(value:r,child:Text(r))],onChanged:(v)=>setState(()=>_res=v??''))),
      const SizedBox(width:8),
      Expanded(child:TextField(controller:nextStd,decoration: const InputDecoration(labelText:'पुढील इयत्ता',border:OutlineInputBorder(),isDense:true))),
    ]),
    const SizedBox(height:10),
    DropdownButtonFormField<String>(
      isExpanded: true,
      value: null,
      hint: const Text('-- तयार शेरा निवडा --', style: TextStyle(fontSize: 13)),
      decoration: const InputDecoration(labelText: 'तयार शेऱ्यांमधून निवडा', prefixIcon: Icon(Icons.lightbulb_outline), border: OutlineInputBorder(), isDense: true),
      items: [for (final s in _remarksList) DropdownMenuItem(value: s, child: Text(s, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13)))],
      onChanged: (v) { if (v != null) setState(() => remark.text = v); },
    ),
    const SizedBox(height:8),
    TextField(controller:remark,decoration: const InputDecoration(labelText:'शेरा (Remarks)',border:OutlineInputBorder())),
    const SizedBox(height:16),
    SizedBox(width:double.infinity,child:FilledButton.icon(onPressed:_saving?null:_save,
      icon:_saving?const SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white)):const Icon(Icons.save),
      label: const Text('जतन करा'))),
    const SizedBox(height:40),
  ]);
}


// ═══════ Tab 6: अहवाल / PDF ══════════════════════════════════════════════════
class _ReportsTab extends StatelessWidget {
  const _ReportsTab({required this.std,required this.sem,this.division=''});
  final int std,sem; final String division;
  @override
  Widget build(BuildContext context) => ApiLoader(path:_api,
    query:{'action':'reports','std':'$std','semester':'$sem', if (division.isNotEmpty) 'division': division},
    builder:(c,d,_){
      final groups=(d['groups'] as List?) ?.cast<Map>()??[];
      if(groups.isEmpty) return ListView(padding: const EdgeInsets.all(12),children:[
        ReportTile(title:'प्रगती पुस्तक (A4)',page:'cce/pragati_pustak.php',params:{'std':std,'all':1,'size':'a4', if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
        ReportTile(title:'प्रगती पुस्तक (A5)',page:'cce/pragati_pustak.php',params:{'std':std,'all':1,'size':'a5', if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
        ReportTile(title:'नोंदवही',page:'cce/nondvahi.php',params:{'std':std,'semester':sem, if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
        ReportTile(title:'गुण–श्रेणी रजिस्टर',page:'cce/marks_grade_register.php',params:{'std':std,'semester':sem, if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
        ReportTile(title:'वार्षिक निकालपत्रक',page:'cce/annual.php',params:{'std':std, if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
        ReportTile(title:'प्रथम सत्र प्रगती पत्रक',page:'cce/progress_card_term1.php',params:{'std':std,'all':1, if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
        ReportTile(title:'वर्णनात्मक नोंदी अहवाल',page:'cce/narrative_report.php',params:{'std':std,'all':1, if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
      ]);
      return ListView(padding: const EdgeInsets.all(12),children:[
        for(final g in groups)...[
          Padding(padding: const EdgeInsets.fromLTRB(4,16,4,8),
            child:Text('${g['title']}',style: const TextStyle(fontWeight:FontWeight.w800,fontSize:16,color:TG.ink))),
          for(final r in (g['items'] as List?) ?.cast<Map>()??[])
            ReportTile(title:'${r['title']}',subtitle:'${r['subtitle']??''}',
              page:'${r['page']}',params:{...Map<String,dynamic>.from((r['params'] as Map?) ??{}), if (division.isNotEmpty) 'section': division, if (division.isNotEmpty) 'division': division, if (division.isNotEmpty) 'section': division}),
        ],
        const SizedBox(height:40),
      ]);
    });
}

