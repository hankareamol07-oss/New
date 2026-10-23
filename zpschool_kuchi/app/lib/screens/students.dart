import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/local_db.dart';
import '../core/remote.dart';
import '../core/sync.dart';
import '../theme.dart';
import 'widgets.dart';

String studentName(Map s) {
  final mr = (s['name_mr'] ?? '').toString().trim();
  return mr.isNotEmpty ? mr : (s['name'] ?? '').toString();
}

class StudentsScreen extends StatefulWidget {
  const StudentsScreen({super.key});
  @override
  State<StudentsScreen> createState() => _StudentsScreenState();
}

class _StudentsScreenState extends State<StudentsScreen> {
  List<Map<String, dynamic>> _all = [];
  String _q = '', _grade = '';

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
    final r = await LocalDb.I.rows('students');
    if (mounted) setState(() => _all = r);
  }

  @override
  Widget build(BuildContext context) {
    final grades = {for (final s in _all) (s['grade'] ?? '').toString()}..remove('');
    final list = _all.where((s) {
      if (_grade.isNotEmpty && '${s['grade']}' != _grade) return false;
      if (_q.isEmpty) return true;
      final h = '${s['name']} ${s['name_mr']} ${s['roll_no']} ${s['gr_no']}'.toLowerCase();
      return h.contains(_q.toLowerCase());
    }).toList();
    return Scaffold(
      appBar: AppBar(
        title: Text('विद्यार्थी (${_all.length})'),
        actions: [
          FilledButton.tonalIcon(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFE8F5E9),
              foregroundColor: const Color(0xFF167A5B),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
              minimumSize: const Size(0, 34),
            ),
            onPressed: () async {
              final ok = await Navigator.push<bool>(
                context,
                MaterialPageRoute(builder: (_) => const UdiseImportScreen()),
              );
              if (ok == true) {
                await SyncService.I.refreshState();
                await _load();
              }
            },
            icon: const Icon(Icons.table_chart, size: 16),
            label: const Text('UDISE+ आयात', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(width: 8),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _edit({}),
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('नवीन'),
      ),
      body: Column(children: [
        // Prominent UDISE+ Excel Quick Import Banner matching portal
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFFFBEB),
              border: Border.all(color: const Color(0xFFFDE68A), width: 1.2),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: const BoxDecoration(color: Color(0xFFE8F5E9), shape: BoxShape.circle),
                  child: const Icon(Icons.table_chart, color: Color(0xFF167A5B), size: 24),
                ),
                const SizedBox(width: 10),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'UDISE+ Excel विद्यार्थी थेट आयात करा',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5, color: Color(0xFF92400E)),
                      ),
                      SizedBox(height: 2),
                      Text(
                        'SDMS Excel/CSV फाईल निवडून सर्व विद्यार्थ्यांची माहिती एका क्लिकवर मिळवा.',
                        style: TextStyle(fontSize: 11.5, color: Color(0xFFB45309)),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                FilledButton.icon(
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFF167A5B),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
                    minimumSize: const Size(0, 36),
                  ),
                  onPressed: () async {
                    final ok = await Navigator.push<bool>(
                      context,
                      MaterialPageRoute(builder: (_) => const UdiseImportScreen()),
                    );
                    if (ok == true) {
                      await SyncService.I.refreshState();
                      await _load();
                    }
                  },
                  icon: const Icon(Icons.cloud_upload, size: 16),
                  label: const Text('आयात करा', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
          child: TextField(
            onChanged: (v) => setState(() => _q = v),
            decoration: const InputDecoration(hintText: 'नाव, हजेरी क्र. किंवा GR शोधा', prefixIcon: Icon(Icons.search), isDense: true),
          ),
        ),
        SizedBox(
          height: 48,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12), children: [
            Padding(padding: const EdgeInsets.all(4), child: ChoiceChip(label: const Text('सर्व'), selected: _grade.isEmpty, onSelected: (_) => setState(() => _grade = ''))),
            for (final g in (grades.toList()..sort()))
              Padding(padding: const EdgeInsets.all(4), child: ChoiceChip(label: Text('इ. $g'), selected: _grade == g, onSelected: (_) => setState(() => _grade = g))),
          ]),
        ),
        Expanded(
          child: list.isEmpty
              ? const EmptyState('विद्यार्थी नाहीत. UDISE+ Excel आयात करा किंवा Sync दाबा.', icon: Icons.groups_outlined)
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 90),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (_, i) {
                    final s = list[i];
                    final local = (s['id'] as int) < 0;
                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(backgroundColor: TG.g50, foregroundColor: TG.g800, child: Text(studentName(s).characters.take(1).toString())),
                        title: Text(studentName(s), style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text('इ. ${s['grade'] ?? '-'} ${s['section'] ?? ''} • हजेरी क्र. ${s['roll_no'] ?? '-'}'),
                        trailing: local ? const Icon(Icons.cloud_upload_outlined, color: TG.amber) : const Icon(Icons.chevron_right),
                        onTap: () => _edit(s),
                      ),
                    );
                  },
                ),
        ),
      ]),
    );
  }

  Future<void> _edit(Map<String, dynamic> s) async {
    final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => StudentForm(student: s)));
    if (saved == true) {
      await SyncService.I.refreshState();
      await _load();
    }
  }
}

class StudentForm extends StatefulWidget {
  const StudentForm({super.key, required this.student});
  final Map<String, dynamic> student;
  @override
  State<StudentForm> createState() => _StudentFormState();
}

class _StudentFormState extends State<StudentForm> {
  final _form = GlobalKey<FormState>();
  // Same field groups and options as the portal's विद्यार्थी फॉर्म (modules/classes).
  static const _groups = <(String, List<(String, String, bool)>)>[
    ('मूलभूत माहिती', [
      ('name_mr', 'नाव (मराठी)', true), ('name', 'नाव (English)', false), ('grade', 'इयत्ता', true), ('section', 'तुकडी', false),
      ('roll_no', 'हजेरी क्रमांक', false), ('gr_no', 'जनरल रजिस्टर क्र.', false), ('registration_no', 'नोंदणी क्र.', false), ('gender', 'लिंग', false),
      ('date_of_birth', 'जन्मतारीख', false), ('dob_words', 'जन्मतारीख (अक्षरी)', false), ('age', 'वय', false), ('blood_group', 'रक्तगट', false),
    ]),
    ('शासकीय ओळख', [
      ('student_pen', 'Student PEN', false), ('saral_id', 'सरल ID', false), ('apaar_id', 'APAAR ID', false), ('aadhar_no', 'आधार क्रमांक', false), ('udid', 'UDID (दिव्यांग)', false),
    ]),
    ('जन्म व सामाजिक माहिती', [
      ('birth_place', 'जन्मस्थळ', false), ('birth_taluka', 'जन्म तालुका', false), ('birth_district', 'जन्म जिल्हा', false), ('birth_state', 'जन्म राज्य', false),
      ('nationality', 'राष्ट्रीयत्व', false), ('religion', 'धर्म', false), ('caste', 'जात', false), ('caste_category', 'प्रवर्ग', false),
      ('mother_tongue', 'मातृभाषा', false), ('medium_of_instruction', 'शिक्षणाचे माध्यम', false), ('area_type', 'क्षेत्र', false), ('address', 'पत्ता', false),
    ]),
    ('प्रवेश', [('admission_date', 'प्रवेश दिनांक', false), ('admission_class', 'प्रवेश इयत्ता', false)]),
    ('पालक माहिती', [
      ('mother_name', 'आईचे नाव', false), ('mother_education', 'आईचे शिक्षण', false), ('mother_occupation', 'आईचा व्यवसाय', false),
      ('father_name', 'वडिलांचे नाव', false), ('father_education', 'वडिलांचे शिक्षण', false), ('father_occupation', 'वडिलांचा व्यवसाय', false),
      ('guardian_name', 'पालकाचे नाव', false), ('guardian_relation', 'पालकाचे नाते', false), ('phone', 'पालक मोबाईल', false),
      ('num_siblings', 'भावंडांची संख्या', false), ('siblings_age', 'भावंडांचे वय', false),
    ]),
    ('आवडी-निवडी व मित्र (HPC Page 3)', [
      ('favourite_color', '🎨 आवडता रंग (Favourite Color)', false),
      ('favourite_food', '🍛 आवडते अन्न / खाद्य (Food)', false),
      ('favourite_flower', '🌺 आवडते फूल (Flower)', false),
      ('favourite_sport', '⚽ आवडता खेळ (Sport)', false),
      ('favourite_animal', '🐾 आवडता प्राणी (Animal)', false),
      ('favourite_subject', '📚 आवडता विषय (Subject)', false),
      ('aspiration', '🌟 मोठे होऊन काय व्हायचे आहे? (Aspiration / ध्येय)', false),
      ('best_friend1', '👫 जवळचा मित्र/मैत्रीण १', false),
      ('best_friend2', '👫 जवळचा मित्र/मैत्रीण २', false),
      ('best_friend3', '👫 जवळचा मित्र/मैत्रीण ३', false),
      ('height_sem1', 'उंची (सें.मी.) - सत्र १', false),
      ('weight_sem1', 'वजन (कि.ग्रॅ.) - सत्र १', false),
      ('height_sem2', 'उंची (सें.मी.) - सत्र २', false),
      ('weight_sem2', 'वजन (कि.ग्रॅ.) - सत्र २', false),
      ('other_interest', 'इतर आवड / छंद (नोंद)', false),
    ]),
  ];
  static const _opts = <String, List<String>>{
    'gender': ['मुलगा', 'मुलगी', 'इतर'],
    'blood_group': ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
    'caste_category': ['खुला / Open', 'SC', 'ST', 'VJ/DT', 'NT-B', 'NT-C', 'NT-D', 'OBC', 'SBC', 'EWS'],
    'religion': ['हिंदू', 'बौद्ध', 'मुस्लिम', 'जैन', 'ख्रिश्चन', 'शीख', 'लिंगायत', 'इतर'],
    'area_type': ['ग्रामीण', 'शहरी'],
    'mother_tongue': ['मराठी', 'इंग्रजी', 'उर्दू', 'हिंदी', 'कन्नड', 'गुजराती'],
    'medium_of_instruction': ['मराठी', 'इंग्रजी', 'उर्दू', 'हिंदी', 'कन्नड', 'गुजराती'],
    'grade': ['1', '2', '3', '4', '5', '6', '7', '8', 'बालवाटिका'],
    'admission_class': ['1', '2', '3', '4', '5', '6', '7', '8', 'बालवाटिका'],
    'section': ['अ', 'ब', 'क', 'ड', 'इ'],
    'mother_education': ['अशिक्षित', 'प्राथमिक (१ली ते ४थी)', 'माध्यमिक (५वी ते १०वी)', 'उच्च माध्यमिक (१२वी)', 'पदवीधर (Graduate)', 'पदव्युत्तर (Post Graduate)', 'इतर'],
    'mother_occupation': ['गृहिणी', 'शेती', 'मजुरी', 'नोकरी', 'व्यवसाय', 'इतर'],
    'father_education': ['अशिक्षित', 'प्राथमिक (१ली ते ४थी)', 'माध्यमिक (५वी ते १०वी)', 'उच्च माध्यमिक (१२वी)', 'पदवीधर (Graduate)', 'पदव्युत्तर (Post Graduate)', 'इतर'],
    'father_occupation': ['शेती', 'मजुरी', 'नोकरी', 'व्यवसाय', 'कामगार', 'इतर'],
    'favourite_color': ['लाल', 'निळा', 'हिरवा', 'पिवळा', 'गुलाबी', 'केशरी', 'जांभळा', 'पांढरा', 'काळा', 'आकाशी', 'तपकिरी', 'सोनेरी'],
    'favourite_food': ['भात', 'चपाती', 'पोळी-भाजी', 'इडली', 'डोसा', 'पिझ्झा', 'बिर्याणी', 'पुलाव', 'खिचडी', 'उपमा', 'पोहे', 'शिरा', 'वडापाव', 'मिसळ', 'पाव भाजी', 'आईस्क्रीम', 'चॉकलेट', 'फळे'],
    'favourite_flower': ['गुलाब', 'जाई', 'जुई', 'मोगरा', 'कमळ', 'सूर्यफूल', 'झेंडू', 'शेवंती', 'अबोली', 'पारिजात', 'चाफा', 'निशिगंध'],
    'favourite_sport': ['क्रिकेट', 'फुटबॉल', 'खो-खो', 'कबड्डी', 'लंगडी', 'बॅडमिंटन', 'धावणे', 'पोहणे', 'स्केटिंग', 'सायकलिंग', 'लपाछपी', 'विटीदांडू'],
    'favourite_animal': ['कुत्रा', 'मांजर', 'ससा', 'हत्ती', 'सिंह', 'वाघ', 'घोडा', 'गाय', 'पोपट', 'मोर', 'माकड', 'हरीण', 'कासव', 'मासा'],
    'favourite_subject': ['मराठी', 'गणित', 'इंग्रजी', 'परिसर अभ्यास', 'चित्रकला', 'संगीत', 'शारीरिक शिक्षण', 'हस्तकला'],
    'aspiration': ['शिक्षक', 'डॉक्टर', 'इंजिनिअर', 'पोलीस', 'सैनिक', 'वैज्ञानिक', 'पायलट', 'खेळाडू', 'कलाकार', 'गायक', 'डान्सर', 'शेतकरी', 'व्यापारी', 'नर्स', 'वकील'],
  };
  static const _interestsList = [
    'वाचन', 'सर्जनशील लेखन', 'नृत्य', 'गायन', 'वाद्य वाजवणे', 'बागकाम',
    'योग', 'चित्रकला', 'हस्तकला', 'खेळ', 'स्वयंपाक', 'घरकामात सहभाग'
  ];
  static const _num = {'grade', 'roll_no', 'phone', 'age', 'num_siblings', 'aadhar_no', 'admission_class', 'height_sem1', 'weight_sem1', 'height_sem2', 'weight_sem2'};
  static const _dates = {'date_of_birth', 'admission_date'};
  late final Map<String, TextEditingController> _c;
  late final Set<String> _selectedInterests;

  @override
  void initState() {
    super.initState();
    _c = {
      for (final g in _groups) for (final f in g.$2)
        f.$1: TextEditingController(text: '${widget.student[f.$1] ?? (f.$1 == 'date_of_birth' ? widget.student['dob'] ?? '' : f.$1 == 'grade' ? widget.student['std'] ?? '' : '')}'),
    };
    _selectedInterests = {
      for (final x in (widget.student['interests'] is List
          ? (widget.student['interests'] as List)
          : (widget.student['interests']?.toString().split(',') ?? [])))
        x.toString().trim()
    }..remove('');
  }

  @override
  void dispose() { for (final x in _c.values) { x.dispose(); } super.dispose(); }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) { toast(context, 'लाल रंगातील माहिती भरा', error: true); return; }
    final row = <String, dynamic>{
      ...widget.student,
      for (final e in _c.entries) e.key: e.value.text.trim(),
      'interests': _selectedInterests.toList(),
    };
    if ((row['name'] ?? '').toString().isEmpty) row['name'] = row['name_mr'];
    row['std'] = row['grade'];
    row['dob'] = row['date_of_birth'];
    row['is_active'] ??= 1;
    await LocalDb.I.saveRow('students', row);
    if (!mounted) return;
    toast(context, 'फोनवर जतन केले. Sync केल्यावर सर्व्हरवर जाईल.');
    Navigator.pop(context, true);
  }

  Future<String?> _askCustom(String label, String current) async {
    final tc = TextEditingController(text: current == '__other__' ? '' : current);
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('$label — इतर माहिती'),
        content: TextField(
          controller: tc,
          autofocus: true,
          decoration: InputDecoration(labelText: label, hintText: '$label टाइप करा...', border: const OutlineInputBorder()),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('रद्द करा')),
          FilledButton(onPressed: () => Navigator.pop(ctx, tc.text.trim()), child: const Text('नक्की करा')),
        ],
      ),
    );
  }

  Widget _field((String, String, bool) f) {
    final (k, label, req) = f;
    final c = _c[k]!;
    final opts = _opts[k];
    if (opts != null) {
      final isCustom = c.text.isNotEmpty && !opts.contains(c.text);
      final items = [...opts];
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DropdownButtonFormField<String>(
            value: opts.contains(c.text) ? c.text : (isCustom ? '__other__' : null),
            isExpanded: true,
            decoration: InputDecoration(
              labelText: label,
              border: const OutlineInputBorder(),
              isDense: true,
              suffixIcon: c.text.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      tooltip: 'साफ करा',
                      onPressed: () => setState(() => c.text = ''),
                    )
                  : null,
            ),
            hint: const Text('-- निवडा --'),
            items: [
              for (final o in items) DropdownMenuItem(value: o, child: Text(o)),
              const DropdownMenuItem(value: '__other__', child: Text('✏️ इतर (टाइप करा)...')),
            ],
            onChanged: (v) async {
              if (v == '__other__') {
                final custom = await _askCustom(label, c.text);
                if (custom != null && custom.isNotEmpty) {
                  setState(() => c.text = custom);
                }
              } else {
                setState(() => c.text = v ?? '');
              }
            },
            validator: (v) => req && c.text.trim().isEmpty ? 'आवश्यक' : null,
          ),
          if (isCustom)
            Padding(
              padding: const EdgeInsets.only(top: 4, left: 4),
              child: Text('निवडलेले: ${c.text}', style: const TextStyle(fontSize: 12, color: TG.g800, fontWeight: FontWeight.bold)),
            ),
        ],
      );
    }
    return TextFormField(
      controller: c, readOnly: _dates.contains(k),
      decoration: InputDecoration(labelText: label, border: const OutlineInputBorder(), isDense: true, suffixIcon: _dates.contains(k) ? const Icon(Icons.event, size: 20) : null),
      keyboardType: _num.contains(k) ? TextInputType.number : TextInputType.text,
      maxLines: k == 'address' ? 2 : 1, minLines: 1,
      onTap: !_dates.contains(k) ? null : () async {
        final d = await showDatePicker(context: context, initialDate: DateTime.tryParse(c.text) ?? DateTime(DateTime.now().year - 8), firstDate: DateTime(1990), lastDate: DateTime.now());
        if (d != null) setState(() => c.text = d.toIso8601String().substring(0, 10));
      },
      validator: (v) => req && (v ?? '').trim().isEmpty ? 'आवश्यक' : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.student.isEmpty ? 'नवीन विद्यार्थी' : 'विद्यार्थी माहिती')),
      bottomNavigationBar: SafeArea(child: Padding(padding: const EdgeInsets.all(12),
        child: FilledButton.icon(onPressed: _save, icon: const Icon(Icons.save_outlined), label: const Text('जतन करा')))),
      body: Form(
        key: _form,
        child: ListView(padding: const EdgeInsets.fromLTRB(12, 8, 12, 24), children: [
          for (var i = 0; i < _groups.length; i++) Card(child: ExpansionTile(
            initiallyExpanded: i == 0 || i == 5,
            title: Text('${i + 1}. ${_groups[i].$1}', style: const TextStyle(fontWeight: FontWeight.w800)),
            childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            children: [
              for (final f in _groups[i].$2) Padding(padding: const EdgeInsets.only(bottom: 10), child: _field(f)),
              if (i == 5) ...[
                const Divider(),
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(children: [
                    const Icon(Icons.star, color: Colors.amber, size: 18),
                    const SizedBox(width: 6),
                    Text('आवड व छंद (Interests — एक किंवा अधिक निवडा)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: TG.inkA)),
                  ]),
                ),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final interest in _interestsList)
                      FilterChip(
                        label: Text(interest, style: const TextStyle(fontSize: 12.5)),
                        selected: _selectedInterests.contains(interest),
                        selectedColor: const Color(0xFFD1FAE5),
                        checkmarkColor: const Color(0xFF047857),
                        onSelected: (sel) => setState(() => sel ? _selectedInterests.add(interest) : _selectedInterests.remove(interest)),
                      ),
                  ],
                ),
                const SizedBox(height: 6),
              ],
            ],
          )),
        ]),
      ),
    );
  }
}

/// UDISE+ SDMS Excel / CSV Student Import Screen
class UdiseImportScreen extends StatefulWidget {
  const UdiseImportScreen({super.key});
  @override
  State<UdiseImportScreen> createState() => _UdiseImportScreenState();
}

class _UdiseImportScreenState extends State<UdiseImportScreen> {
  bool _busy = false;
  String _status = '';
  List<Map<String, dynamic>> _parsed = [];
  final Set<int> _selected = {};
  final _textController = TextEditingController();

  Future<void> _pickAndParseFile() async {
    try {
      final res = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['xlsx', 'xls', 'csv'],
        withData: true,
      );
      if (res == null || res.files.isEmpty) return;
      final file = res.files.single;
      final bytes = file.bytes ?? (file.path != null ? await File(file.path!).readAsBytes() : null);
      if (bytes == null || bytes.isEmpty) {
        if (mounted) toast(context, 'फाईलमधून डेटा वाचता आला नाही', error: true);
        return;
      }

      setState(() {
        _busy = true;
        _status = 'UDISE+ फाईल तपासत आहे…';
      });

      final b64 = base64Encode(bytes);
      final response = await Remote.post('import_udise.php', {
        'action': 'parse',
        'file_base64': b64,
        'file_name': file.name,
      });

      final list = (response['students'] as List? ?? []).cast<Map<String, dynamic>>();
      setState(() {
        _parsed = list;
        _selected.clear();
        for (var i = 0; i < list.length; i++) {
          _selected.add(i);
        }
        _busy = false;
        _status = '${list.length} विद्यार्थी सापडले';
      });
      if (mounted) toast(context, '${list.length} विद्यार्थी यशस्वीरीत्या सापडले');
    } catch (e) {
      setState(() => _busy = false);
      if (mounted) toast(context, '$e', error: true);
    }
  }

  Future<void> _parsePastedText() async {
    final text = _textController.text.trim();
    if (text.isEmpty) {
      toast(context, 'कृपया UDISE+ चा डेटा पेस्ट करा', error: true);
      return;
    }

    setState(() {
      _busy = true;
      _status = 'डेटा प्रक्रिया करत आहे…';
    });

    try {
      final response = await Remote.post('import_udise.php', {
        'action': 'parse',
        'udise_text': text,
      });

      final list = (response['students'] as List? ?? []).cast<Map<String, dynamic>>();
      setState(() {
        _parsed = list;
        _selected.clear();
        for (var i = 0; i < list.length; i++) {
          _selected.add(i);
        }
        _busy = false;
        _status = '${list.length} विद्यार्थी सापडले';
      });
      if (mounted) toast(context, '${list.length} विद्यार्थी यशस्वीरीत्या सापडले');
    } catch (e) {
      setState(() => _busy = false);
      if (mounted) toast(context, '$e', error: true);
    }
  }

  Future<void> _saveImport() async {
    if (_selected.isEmpty) {
      toast(context, 'कृपया सेव्ह करण्यासाठी विद्यार्थी निवडा', error: true);
      return;
    }

    setState(() {
      _busy = true;
      _status = '${_selected.length} विद्यार्थी सेव्ह होत आहेत…';
    });

    try {
      final toSave = [
        for (final idx in _selected)
          {
            ..._parsed[idx],
            'selected': true,
          }
      ];

      final res = await Remote.post('import_udise.php', {
        'action': 'save',
        'students': toSave,
      });

      final msg = res['message'] ?? 'विद्यार्थी यशस्वीरीत्या आयात झाले!';
      if (mounted) {
        toast(context, msg);
        Navigator.pop(context, true);
      }
    } catch (e) {
      setState(() => _busy = false);
      if (mounted) toast(context, '$e', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('UDISE+ Excel विद्यार्थी आयात'),
      ),
      bottomNavigationBar: _parsed.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: FilledButton.icon(
                  onPressed: _busy ? null : _saveImport,
                  icon: const Icon(Icons.cloud_upload),
                  label: Text('${_selected.length} विद्यार्थी आयात करा (Save)'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                    backgroundColor: const Color(0xFF167A5B),
                  ),
                ),
              ),
            ),
      body: _busy
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const CircularProgressIndicator(),
                  const SizedBox(height: 16),
                  Text(_status, style: const TextStyle(fontWeight: FontWeight.w600)),
                ],
              ),
            )
          : _parsed.isEmpty
              ? ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Card(
                      elevation: 2,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            const Icon(Icons.table_chart_outlined, size: 48, color: Color(0xFF167A5B)),
                            const SizedBox(height: 12),
                            const Text(
                              'UDISE+ Excel / CSV फाईल निवडा',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'UDISE+ SDMS वरून डाऊनलोड केलेली "List of All Students" फाईल (.xlsx, .xls किंवा .csv) थेट निवडा. सर्व इंग्रजी नावांचे स्वयंचलित मराठी रुपांतर होईल.',
                              textAlign: TextAlign.center,
                              style: TextStyle(fontSize: 13, color: Colors.grey),
                            ),
                            const SizedBox(height: 16),
                            FilledButton.icon(
                              onPressed: _pickAndParseFile,
                              icon: const Icon(Icons.file_upload),
                              label: const Text('Excel / CSV फाईल निवडा'),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('किंवा UDISE+ डेटा कॉपी-पेस्ट करा:', style: TextStyle(fontWeight: FontWeight.bold)),
                            const SizedBox(height: 8),
                            TextField(
                              controller: _textController,
                              maxLines: 5,
                              decoration: const InputDecoration(
                                hintText: 'UDISE+ वरील टेबल मधील ओळी येथे पेस्ट करा…',
                                border: OutlineInputBorder(),
                              ),
                            ),
                            const SizedBox(height: 10),
                            Align(
                              alignment: Alignment.centerRight,
                              child: OutlinedButton.icon(
                                onPressed: _parsePastedText,
                                icon: const Icon(Icons.playlist_add_check),
                                label: const Text('पेस्ट केलेला डेटा वाचा'),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                )
              : Column(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      color: const Color(0xFFE8F5E9),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              'एकूण विद्यार्थी: ${_parsed.length} (निवडलेले: ${_selected.length})',
                              style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1B5E20)),
                            ),
                          ),
                          TextButton(
                            onPressed: () {
                              setState(() {
                                if (_selected.length == _parsed.length) {
                                  _selected.clear();
                                } else {
                                  _selected.clear();
                                  for (var i = 0; i < _parsed.length; i++) {
                                    _selected.add(i);
                                  }
                                }
                              });
                            },
                            child: Text(_selected.length == _parsed.length ? 'सर्व काढा' : 'सर्व निवडा'),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: ListView.separated(
                        itemCount: _parsed.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (c, i) {
                          final s = _parsed[i];
                          final isSel = _selected.contains(i);
                          final exists = s['is_existing'] == true;
                          return CheckboxListTile(
                            value: isSel,
                            onChanged: (v) => setState(() => v == true ? _selected.add(i) : _selected.remove(i)),
                            title: Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    '${s['name_mr']} (${s['name']})',
                                    style: const TextStyle(fontWeight: FontWeight.w700),
                                  ),
                                ),
                                if (exists)
                                  const Chip(
                                    label: Text('अस्तित्वात', style: TextStyle(fontSize: 10, color: Colors.blue)),
                                    padding: EdgeInsets.zero,
                                    visualDensity: VisualDensity.compact,
                                  ),
                              ],
                            ),
                            subtitle: Text(
                              'इयत्ता ${s['std']} ${s['section']} • हजेरी क्र. ${s['roll_no']} • ${s['gender']} • PEN: ${s['student_pen'] ?? '-'}',
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
    );
  }
}
