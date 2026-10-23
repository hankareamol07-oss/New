import 'package:flutter/material.dart';

import '../core/local_db.dart';
import '../core/remote.dart';
import '../theme.dart';
import 'common.dart';
import 'widgets.dart';

class ParipathScreen extends StatefulWidget {
  const ParipathScreen({super.key});
  @override
  State<ParipathScreen> createState() => _ParipathScreenState();
}

class _ParipathScreenState extends State<ParipathScreen> {
  DateTime date = DateTime.now();
  String dinFilter = 'all';
  String dinSearch = '';

  String get ds =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  bool get isToday {
    final now = DateTime.now();
    return date.year == now.year && date.month == now.month && date.day == now.day;
  }

  void _setDate(DateTime d) {
    setState(() {
      date = d;
      dinFilter = 'all';
      dinSearch = '';
    });
  }

  // Quick sync with paripath.in / SQLite
  Future<void> _sync(VoidCallback reload) async {
    try {
      final res = await Remote.get('paripath.php', {'action': 'sync', 'date': ds});
      // Cache for standard date query so immediate reload & offline loads get fresh copy
      await LocalDb.I.putCache('paripath.php?date=$ds', res);
      if (!mounted || !context.mounted) return;
      toast(context, res['message']?.toString() ?? 'परिपाठ सिंक झाला!');
      reload();
    } catch (e) {
      if (!mounted || !context.mounted) return;
      toast(context, 'सिंक त्रुटी: $e');
    }
  }

  // Open Blackboard Custom Notes Editor
  void _editNotes(Map notes, VoidCallback reload) {
    final wordCtrl = TextEditingController(text: notes['word_of_day'] ?? '');
    final meaningCtrl = TextEditingController(text: notes['word_meaning'] ?? '');
    final gkQCtrl = TextEditingController(text: notes['gk_question'] ?? '');
    final gkACtrl = TextEditingController(text: notes['gk_answer'] ?? '');
    final annCtrl = TextEditingController(text: notes['announcement'] ?? '');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.edit_note, color: TG.g600),
            SizedBox(width: 8),
            Text('शालेय फळा नोंद संपादन', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: wordCtrl,
                decoration: const InputDecoration(
                  labelText: 'आजचा शब्द (Word of the Day)',
                  hintText: 'उदा. परिश्रम (Hard Work)',
                  prefixIcon: Icon(Icons.spellcheck),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: meaningCtrl,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'शब्दाचा अर्थ / वाक्यात उपयोग',
                  hintText: 'शब्दाचा अर्थ किंवा विद्यार्थ्यांसाठी मार्गदर्शन',
                  prefixIcon: Icon(Icons.translate),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: gkQCtrl,
                decoration: const InputDecoration(
                  labelText: 'सामान्य ज्ञान प्रश्न (GK Question)',
                  hintText: 'उदा. भारताचे पहिले अंतराळवीर कोण होते?',
                  prefixIcon: Icon(Icons.quiz_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: gkACtrl,
                decoration: const InputDecoration(
                  labelText: 'सामान्य ज्ञान उत्तर',
                  hintText: 'उदा. विंग कमांडर राकेश शर्मा',
                  prefixIcon: Icon(Icons.check_circle_outline),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: annCtrl,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'शाळेची आजची महत्त्वाची सूचना / उपक्रम',
                  hintText: 'उदा. आज परिपाठानंतर वर्ग स्वच्छता करावी.',
                  prefixIcon: Icon(Icons.campaign_outlined),
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('रद्द करा')),
          FilledButton.icon(
            icon: const Icon(Icons.save_outlined),
            label: const Text('सेव्ह करा'),
            onPressed: () async {
              Navigator.pop(ctx);
              try {
                final res = await Remote.post('paripath.php', {
                  'action': 'save_notes',
                  'date': ds,
                  'word_of_day': wordCtrl.text.trim(),
                  'word_meaning': meaningCtrl.text.trim(),
                  'gk_question': gkQCtrl.text.trim(),
                  'gk_answer': gkACtrl.text.trim(),
                  'announcement': annCtrl.text.trim(),
                });
                if (!mounted || !context.mounted) return;
                toast(context, res['message']?.toString() ?? 'नोंदी सेव्ह झाल्या!');
                reload();
              } catch (e) {
                if (!mounted || !context.mounted) return;
                toast(context, 'त्रुटी: $e');
              }
            },
          ),
        ],
      ),
    );
  }

  // Digital Blackboard Dialog (TV / Projector Chalkboard mode)
  void _openDigitalBlackboard(Map d) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _DigitalBlackboard(data: d, ds: ds),
    );
  }

  // Full Prayers Collection Sheet
  void _openPrayersSheet(List prayers) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _PrayersSheet(prayers: prayers),
    );
  }

  // Display single routine step modal
  void _showRoutineDetail(int step, String title, String body, {String? sub}) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 16,
                  backgroundColor: TG.g600,
                  child: Text('$step', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 12),
                Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
                const Spacer(),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
              ],
            ),
            const Divider(),
            Expanded(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SelectableText(
                      body.trim(),
                      style: const TextStyle(fontSize: 16, height: 1.8, fontFamily: 'serif'),
                    ),
                    if (sub != null && sub.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFFBBF7D0)),
                        ),
                        child: Text(sub, style: const TextStyle(color: Color(0xFF166534), fontSize: 13, height: 1.5)),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('शालेय परिपाठ'),
        actions: [
          IconButton(
            icon: const Icon(Icons.picture_as_pdf_outlined),
            tooltip: 'परिपाठ प्रिंट',
            onPressed: () => Remote.printReport(
              context,
              'modules/paripath/print.php',
              {'date': ds},
              name: 'परिपाठ $ds',
            ),
          ),
          IconButton(
            icon: const Icon(Icons.calendar_month),
            tooltip: 'तारीख निवडा',
            onPressed: () async {
              final d = await showDatePicker(
                context: context,
                initialDate: date,
                firstDate: DateTime(2020),
                lastDate: DateTime(2035),
              );
              if (d != null) _setDate(d);
            },
          ),
        ],
      ),
      body: ApiLoader(
        path: 'paripath.php',
        query: {'date': ds},
        builder: (c, d, reload) {
          final p = (d['panchang'] as Map?) ?? {};
          final suvicharRaw = (d['suvichar_data'] as Map?) ?? {};
          final suvichar = {
            'text_mr': suvicharRaw['text_mr'] ?? d['suvichar'] ?? '',
            'author': suvicharRaw['author'] ?? d['suvichar_source'] ?? '',
            'meaning_mr': suvicharRaw['meaning_mr'] ?? d['suvichar_meaning'] ?? '',
          };
          final subhashitRaw = (d['subhashit_data'] as Map?) ?? {};
          final subhashit = {
            'text_sa': subhashitRaw['text_sa'] ?? d['subhashit_text'] ?? '',
            'meaning_mr': subhashitRaw['meaning_mr'] ?? d['subhashit_meaning'] ?? '',
          };
          final notes = (d['custom_notes'] as Map?) ?? {};
          var dinList = (d['dinvishesh_list'] as List? ?? []).cast<Map>();
          if (dinList.isEmpty && (d['dinvishesh'] ?? '').toString().trim().isNotEmpty) {
            final lines = (d['dinvishesh'] as String).split(RegExp(r'\n+'));
            dinList = [
              for (final line in lines)
                if (line.trim().isNotEmpty)
                  {
                    'title_mr': line.trim().replaceFirst(RegExp(r'^[•\-\*]\s*'), ''),
                    'short_info_mr': '',
                    'category': 'historical_event',
                  }
            ];
          }
          var prayers = (d['prayers'] as List? ?? []).cast<Map>();
          if (prayers.isEmpty) {
            final pt = d['prayer_text']?.toString().trim();
            prayers = [
              {
                'title_mr': 'सर्वधर्म प्रार्थना',
                'subtitle_mr': 'दैनिक प्रार्थना',
                'content': (pt != null && pt.isNotEmpty) ? pt : 'हे ईश्वरा, आम्हाला सद्बुद्धी दे, उत्तम ज्ञान दे, आमच्या मनाला शांती दे.\nसर्व प्राणिमात्रांवर तुझी कृपा असू दे. आमेन / ॐ शांती शांती शांती।।',
                'meaning': 'सद्मार्गावर चालण्याची व मनाला शांती देण्याची प्रार्थना.',
              },
              {
                'title_mr': 'राष्ट्रगीत',
                'subtitle_mr': 'रवींद्रनाथ टागोर',
                'content': d['national_anthem'] ?? "जन गण मन अधिनायक जय हे\nभारत भाग्य विधाता।\nपंजाब सिंधु गुजरात मराठा\nद्राविड उत्कल वंग।\nविंध्य हिमाचल यमुना गंगा\nउच्छल जलधि तरंग।\nतव शुभ नामे जागे, तव शुभ आशिष मांगे\nगाहे तव जय गाथा।\nजन गण मंगलदायक जय हे\nभारत भाग्य विधाता।\nजय हे, जय हे, जय हे\nजय जय जय जय हे।।",
                'meaning': 'भारताचे अधिकृत राष्ट्रगीत',
              },
              {
                'title_mr': 'प्रतिज्ञा',
                'subtitle_mr': 'पायदिमरी व्यंकट सुब्बाराव',
                'content': d['pledge'] ?? "भारत माझा देश आहे.\nसारे भारतीय माझे बांधव आहेत.\nमी माझ्या देशावर प्रेम करतो.\nत्याच्या समृद्ध आणि विविध परंपरांचा मला अभिमान आहे.\nत्यास योग्य असे बनण्याचा मी नेहमी प्रयत्न करेन.\nमाझ्या माता-पित्यांचा, गुरूजनांचा आणि थोरामोठ्यांचा मी आदर करेन.\nआणि प्रत्येकाशी नम्रतेने वागेन.\nमाझा देश आणि माझे देशबांधव यांच्याप्रती मी निष्ठावंत राहीन.\nत्यांच्या कल्याणात आणि समृद्धीत माझे सौख्य आहे.\n    जय हिंद!",
                'meaning': 'राष्ट्रीय एकात्मतेची प्रतिज्ञा',
              },
            ];
          }
          final schoolName = '${d['school_name'] ?? 'शालेय परिपाठ केंद्र'}';

          // Filter dinvishesh items
          final filteredDin = dinList.where((ev) {
            final cat = ev['category'] ?? 'historical_event';
            if (dinFilter != 'all' && cat != dinFilter) return false;
            if (dinSearch.trim().isNotEmpty) {
              final q = dinSearch.toLowerCase();
              final t = '${ev['title_mr'] ?? ''}'.toLowerCase();
              final s = '${ev['short_info_mr'] ?? ''}'.toLowerCase();
              final desc = '${ev['description_mr'] ?? ''}'.toLowerCase();
              final yr = '${ev['event_year'] ?? ''}';
              if (!t.contains(q) && !s.contains(q) && !desc.contains(q) && !yr.contains(q)) {
                return false;
              }
            }
            return true;
          }).toList();

          return ListView(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 40),
            children: [
              // Top Navigation: काल / आज / उद्या + सिंक + फळा + सर्व प्रार्थना
              _buildTopControls(reload, d, prayers),
              const SizedBox(height: 10),

              // 1. Hero Banner: Date, Day, Tithi & Suvichar Quick View
              _buildHeroBanner(d, p, suvichar, schoolName),
              const SizedBox(height: 12),

              // 2. आदर्श शालेय परिपाठ क्रम (८ टप्पे)
              _buildRoutineSequence(d, prayers),
              const SizedBox(height: 12),

              // 3. Detailed Panchang Card
              _buildPanchangCard(p, d['marathi_day']),
              const SizedBox(height: 12),

              // 4. Sanskrit Subhashit / Dnyaneshwari Ovi Card
              if ((subhashit['text_sa'] ?? '').toString().isNotEmpty) ...[
                _buildSubhashitCard(subhashit),
                const SizedBox(height: 12),
              ],

              // 5. Today's Suvichar Meaning & Details Card
              _buildSuvicharDetailsCard(suvichar, d),
              const SizedBox(height: 12),

              // 6. Blackboard Custom Notes / Word of Day & GK
              _buildBlackboardNotesCard(notes, reload),
              const SizedBox(height: 12),

              // 7. Dinvishesh Gallery Card
              _buildDinvisheshCard(dinList, filteredDin),
              const SizedBox(height: 12),

              // 8. Prayers & Anthem Quick Jump Card
              _buildPrayersQuickCard(prayers, d),
            ],
          );
        },
      ),
    );
  }

  // --- WIDGET BUILDERS ---

  Widget _buildTopControls(VoidCallback reload, Map d, List prayers) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        child: Row(
          children: [
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                visualDensity: VisualDensity.compact,
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              ),
              icon: const Icon(Icons.chevron_left, size: 16),
              label: const Text('काल', style: TextStyle(fontSize: 12)),
              onPressed: () => _setDate(date.subtract(const Duration(days: 1))),
            ),
            const SizedBox(width: 6),
            FilledButton.tonal(
              style: FilledButton.styleFrom(
                visualDensity: VisualDensity.compact,
                backgroundColor: isToday ? TG.g600 : null,
                foregroundColor: isToday ? Colors.white : null,
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              ),
              onPressed: () => _setDate(DateTime.now()),
              child: const Text('आज', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
            ),
            const SizedBox(width: 6),
            OutlinedButton(
              style: OutlinedButton.styleFrom(
                visualDensity: VisualDensity.compact,
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              ),
              onPressed: () => _setDate(date.add(const Duration(days: 1))),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('उद्या', style: TextStyle(fontSize: 12)),
                  Icon(Icons.chevron_right, size: 16),
                ],
              ),
            ),
            const Spacer(),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                visualDensity: VisualDensity.compact,
                foregroundColor: const Color(0xFF059669),
                side: const BorderSide(color: Color(0xFF10B981)),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              ),
              icon: const Icon(Icons.sync, size: 16),
              label: const Text('सिंक', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
              onPressed: () => _sync(reload),
            ),
            const SizedBox(width: 4),
            FilledButton.icon(
              style: FilledButton.styleFrom(
                visualDensity: VisualDensity.compact,
                backgroundColor: const Color(0xFF1E293B),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              ),
              icon: const Icon(Icons.tv, size: 15, color: Color(0xFFFBBF24)),
              label: const Text('फळा', style: TextStyle(fontSize: 12)),
              onPressed: () => _openDigitalBlackboard(d),
            ),
            const SizedBox(width: 2),
            IconButton(
              visualDensity: VisualDensity.compact,
              tooltip: 'सर्व प्रार्थना',
              icon: const Icon(Icons.music_note, size: 18, color: Color(0xFFDC2626)),
              onPressed: () => _openPrayersSheet(prayers),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeroBanner(Map d, Map p, Map suvichar, String schoolName) {
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [TG.g800, Color(0xFF0369A1), Color(0xFF0284C7)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0284C7).withValues(alpha: 0.3),
            blurRadius: 15,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Badges row
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFF59E0B),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  '${d['marathi_day'] ?? d['day_name'] ?? ''}',
                  style: const TextStyle(fontWeight: FontWeight.w800, color: Colors.black87, fontSize: 13),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  '${p['hindu_month'] ?? 'मास'} • ${p['paksha'] ?? 'पक्ष'}',
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFF10B981),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text('१००% मोफत परिपाठ',
                    style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Date & School Name
          Text(
            '${d['marathi_date'] ?? ds}',
            style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.location_on, size: 14, color: Color(0xFFFBBF24)),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  schoolName,
                  style: const TextStyle(color: Colors.white70, fontSize: 13),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Suvichar block
          if ((suvichar['text_mr'] ?? d['suvichar'] ?? '').toString().isNotEmpty)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.format_quote, size: 16, color: Color(0xFFFBBF24)),
                      SizedBox(width: 4),
                      Text('आजचा सुविचार',
                          style: TextStyle(color: Color(0xFFFBBF24), fontWeight: FontWeight.bold, fontSize: 12)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '"${suvichar['text_mr'] ?? d['suvichar']}"',
                    style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600, height: 1.4),
                  ),
                  if ((suvichar['author'] ?? d['suvichar_source'] ?? '').toString().isNotEmpty)
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        '— ${suvichar['author'] ?? d['suvichar_source']}',
                        style: const TextStyle(color: Colors.white70, fontSize: 12, fontStyle: FontStyle.italic),
                      ),
                    ),
                ],
              ),
            ),
          const SizedBox(height: 12),

          // Sunrise, Sunset & Rahu Kaal Pill Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Row(
                    children: [
                      const Icon(Icons.wb_sunny, color: Color(0xFFF59E0B), size: 16),
                      const SizedBox(width: 6),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('सूर्योदय', style: TextStyle(fontSize: 10, color: TG.mutedA)),
                          Text('${p['sunrise'] ?? '06:24 AM'}',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black87)),
                        ],
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: Row(
                    children: [
                      const Icon(Icons.nights_stay, color: Color(0xFFEF4444), size: 16),
                      const SizedBox(width: 6),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('सूर्यास्त', style: TextStyle(fontSize: 10, color: TG.mutedA)),
                          Text('${p['sunset'] ?? '06:33 PM'}',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black87)),
                        ],
                      ),
                    ],
                  ),
                ),
                if ((p['rahu_kaal'] ?? '').toString().isNotEmpty)
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('राहू काळ', style: TextStyle(fontSize: 10, color: Color(0xFFEF4444), fontWeight: FontWeight.bold)),
                        Text('${p['rahu_kaal']}',
                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFB91C1C))),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRoutineSequence(Map d, List prayers) {
    final steps = [
      {'n': 1, 'title': 'राष्ट्रगीत', 'clr': const Color(0xFFEF4444), 'k': 'national_anthem'},
      {'n': 2, 'title': 'प्रतिज्ञा', 'clr': const Color(0xFF2563EB), 'k': 'pledge'},
      {'n': 3, 'title': 'संविधान', 'clr': const Color(0xFF059669), 'k': 'preamble'},
      {'n': 4, 'title': 'सुविचार', 'clr': const Color(0xFFD97706), 'k': 'suvichar'},
      {'n': 5, 'title': 'पंचांग', 'clr': const Color(0xFF0284C7), 'k': 'panchang'},
      {'n': 6, 'title': 'दिनविशेष', 'clr': const Color(0xFF4B5563), 'k': 'dinvishesh'},
      {'n': 7, 'title': 'प्रार्थना', 'clr': const Color(0xFF7C3AED), 'k': 'prayer_text'},
      {'n': 8, 'title': 'पसायदान', 'clr': const Color(0xFFDC2626), 'k': 'pasaydan'},
    ];

    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.format_list_numbered, size: 18, color: TG.g600),
                const SizedBox(width: 8),
                const Text('आदर्श शालेय परिपाठ क्रम', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const Spacer(),
                Text('८ टप्पे', style: TextStyle(fontSize: 12, color: TG.mutedA, fontWeight: FontWeight.bold)),
              ],
            ),
            const SizedBox(height: 10),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final st in steps)
                    InkWell(
                      borderRadius: BorderRadius.circular(10),
                      onTap: () {
                        final k = st['k'] as String;
                        if (k == 'national_anthem') {
                          _showRoutineDetail(1, 'राष्ट्रगीत (जन गण मन)', '${d['national_anthem']}');
                        } else if (k == 'pledge') {
                          _showRoutineDetail(2, 'राष्ट्रीय प्रतिज्ञा', '${d['pledge']}');
                        } else if (k == 'preamble') {
                          final p = prayers.firstWhere((pr) => pr['category'] == 'preamble', orElse: () => {});
                          _showRoutineDetail(3, 'संविधान उद्देशिका', '${p['content'] ?? 'आम्ही भारताचे लोक...'}', sub: '${p['meaning'] ?? ''}');
                        } else if (k == 'pasaydan') {
                          final p = prayers.firstWhere((pr) => pr['category'] == 'pasaydan', orElse: () => {});
                          _showRoutineDetail(8, 'संत ज्ञानेश्वरांचे पसायदान', '${p['content'] ?? 'आता विश्वात्मकें देवें...'}', sub: '${p['meaning'] ?? ''}');
                        } else if (k == 'suvichar') {
                          _showRoutineDetail(4, 'आजचा सुविचार', '${d['suvichar']}', sub: '${d['suvichar_meaning'] ?? ''}');
                        } else if (k == 'prayer_text') {
                          _showRoutineDetail(7, 'शालेय प्रार्थना', '${d['prayer_text']}');
                        } else {
                          toast(context, '${st['title']} खालील भागात उपलब्ध आहे');
                        }
                      },
                      child: Container(
                        margin: const EdgeInsets.only(right: 8),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        decoration: BoxDecoration(
                          color: (st['clr'] as Color).withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: (st['clr'] as Color).withValues(alpha: 0.2)),
                        ),
                        child: Column(
                          children: [
                            CircleAvatar(
                              radius: 12,
                              backgroundColor: st['clr'] as Color,
                              child: Text('${st['n']}',
                                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                            ),
                            const SizedBox(height: 4),
                            Text('${st['title']}',
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black87)),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPanchangCard(Map p, dynamic marathiDay) {
    final pills = [
      {'label': 'तिथी', 'val': p['tithi'] ?? '-', 'clr': TG.g600},
      {'label': 'नक्षत्र', 'val': p['nakshatra'] ?? '-', 'clr': Colors.black87},
      {'label': 'वार', 'val': marathiDay ?? '-', 'clr': const Color(0xFF059669)},
      {'label': 'महिना व पक्ष', 'val': '${p['hindu_month'] ?? '-'} (${p['paksha'] ?? '-'})', 'clr': Colors.black87},
      {'label': 'योग', 'val': p['yoga'] ?? '-', 'clr': Colors.black87},
      {'label': 'करण', 'val': p['karana'] ?? '-', 'clr': Colors.black87},
    ];

    final festivals = (p['festivals_list'] as List? ?? []);

    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.calendar_today, size: 18, color: TG.g600),
                SizedBox(width: 8),
                Text('दैनिक पंचांग', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              ],
            ),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              childAspectRatio: 2.6,
              mainAxisSpacing: 8,
              crossAxisSpacing: 8,
              children: [
                for (final item in pills)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text('${item['label']}',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600)),
                        const SizedBox(height: 2),
                        Text(
                          '${item['val']}',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: item['clr'] as Color,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
              ],
            ),
            if (festivals.isNotEmpty || (p['festivals'] ?? '').toString().isNotEmpty) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFFDE68A)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.stars, color: Color(0xFFD97706), size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('आजचे सण / उत्सव / विशेष:',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF92400E))),
                          const SizedBox(height: 2),
                          Text(
                            festivals.isNotEmpty
                                ? festivals.map((f) => f is Map ? '${f['name_mr'] ?? ''}' : '$f').join(', ')
                                : '${p['festivals']}',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.black87),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildSubhashitCard(Map sub) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.menu_book, size: 18, color: Color(0xFFD97706)),
                const SizedBox(width: 8),
                const Text('संस्कृत सुभाषित / ज्ञानेश्वरी ओवी',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF3C7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    '${sub['granth'] ?? 'सुभाषित'}',
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFB45309)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Sanskrit Shloka Box
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFFEF3C7)),
              ),
              child: SelectableText(
                '${sub['text_sa'] ?? ''}'.trim(),
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 16,
                  height: 1.8,
                  fontWeight: FontWeight.bold,
                  fontFamily: 'serif',
                  color: Color(0xFF1E293B),
                ),
              ),
            ),
            const SizedBox(height: 10),

            // Meaning
            if ((sub['meaning_mr'] ?? '').toString().isNotEmpty) ...[
              const Text('मराठी भावार्थ:',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: TG.g600)),
              const SizedBox(height: 2),
              SelectableText(
                '${sub['meaning_mr']}',
                style: const TextStyle(fontSize: 14, height: 1.5, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
            ],

            // Explanation
            if ((sub['explanation_mr'] ?? '').toString().isNotEmpty) ...[
              Text('सविस्तर विवेचन:',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: TG.mutedA)),
              const SizedBox(height: 2),
              SelectableText(
                '${sub['explanation_mr']}',
                style: const TextStyle(fontSize: 13, height: 1.5, color: Colors.black87),
              ),
              const SizedBox(height: 8),
            ],

            // Moral / Usage
            if ((sub['usage_mr'] ?? '').toString().isNotEmpty)
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0FDF4),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFDCFCE7)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.lightbulb, size: 16, color: Color(0xFF16A34A)),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('विद्यार्थ्यांसाठी आचरण व बोध:',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF15803D))),
                          const SizedBox(height: 2),
                          Text('${sub['usage_mr']}', style: const TextStyle(fontSize: 12, color: Colors.black87)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildSuvicharDetailsCard(Map suvichar, Map d) {
    final textMr = '${suvichar['text_mr'] ?? d['suvichar'] ?? ''}';
    final textEn = '${suvichar['text_en'] ?? ''}';
    final meaning = '${suvichar['meaning_mr'] ?? d['suvichar_meaning'] ?? ''}';
    final exp = '${suvichar['explanation_mr'] ?? ''}';

    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.lightbulb_outline, size: 18, color: Color(0xFF0284C7)),
                SizedBox(width: 8),
                Text('सुविचार स्पष्टीकरण', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                Spacer(),
                Text('सार्थ सुविचार', style: TextStyle(fontSize: 11, color: Color(0xFF0284C7), fontWeight: FontWeight.bold)),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              '"$textMr"',
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, height: 1.4),
            ),
            if (textEn.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                '"$textEn"',
                style: TextStyle(fontSize: 13, fontStyle: FontStyle.italic, color: TG.mutedA),
              ),
            ],
            if (meaning.isNotEmpty) ...[
              const SizedBox(height: 8),
              const Text('अर्थ:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: TG.g600)),
              const SizedBox(height: 2),
              Text(meaning, style: const TextStyle(fontSize: 13, height: 1.5)),
            ],
            if (exp.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text('महत्त्व व बोध:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: TG.mutedA)),
              const SizedBox(height: 2),
              Text(exp, style: const TextStyle(fontSize: 13, height: 1.5, color: Colors.black87)),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildBlackboardNotesCard(Map notes, VoidCallback reload) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.draw_outlined, size: 18, color: Color(0xFF059669)),
                const SizedBox(width: 8),
                const Text('शालेय फळा नोंद व सामान्य ज्ञान',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const Spacer(),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    visualDensity: VisualDensity.compact,
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  ),
                  icon: const Icon(Icons.edit_outlined, size: 13),
                  label: const Text('संपादन', style: TextStyle(fontSize: 11)),
                  onPressed: () => _editNotes(notes, reload),
                ),
              ],
            ),
            const SizedBox(height: 10),

            // Word of Day
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(color: TG.g600, borderRadius: BorderRadius.circular(4)),
                        child: const Text('आजचा शब्द', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${notes['word_of_day'] ?? 'परिश्रम (Hard Work)'}',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${notes['word_meaning'] ?? 'कोणत्याही कार्यात यश मिळवण्यासाठी केलेले सततचे प्रयत्न.'}',
                    style: const TextStyle(fontSize: 12, color: Color(0xFF475569)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),

            // GK Question
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(4)),
                        child: const Text('सामान्य ज्ञान', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${notes['gk_question'] ?? 'भारताचे पहिले अंतराळवीर कोण होते?'}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'उत्तर: ${notes['gk_answer'] ?? 'विंग कमांडर राकेश शर्मा'}',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF059669)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),

            // Announcement
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFFEF3C7)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(color: const Color(0xFFD97706), borderRadius: BorderRadius.circular(4)),
                    child: const Text('शालेय सूचना / आजचा विचार',
                        style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    (notes['announcement'] ?? '').toString().isNotEmpty
                        ? '${notes['announcement']}'
                        : 'आज परिपाठानंतर सर्व विद्यार्थ्यांनी वर्ग स्वच्छता करावी.',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF78350F)),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDinvisheshCard(List fullList, List filtered) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.history_edu, size: 18, color: TG.g600),
                const SizedBox(width: 8),
                Text('आजचे दिनविशेष (${fullList.length})',
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const Spacer(),
                Text('${filtered.length} दर्शविले', style: TextStyle(fontSize: 11, color: TG.mutedA)),
              ],
            ),
            const SizedBox(height: 10),

            // Category filter chips
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _chip('all', 'सर्व (${fullList.length})'),
                  _chip('international_day', 'दिनविशेष'),
                  _chip('birth_anniversary', 'जयंती'),
                  _chip('death_anniversary', 'पुण्यतिथी'),
                  _chip('historical_event', 'घटना'),
                ],
              ),
            ),
            const SizedBox(height: 8),

            // Search bar if items > 3
            if (fullList.length > 3)
              TextField(
                decoration: InputDecoration(
                  hintText: 'दिनविशेष शोधा...',
                  prefixIcon: const Icon(Icons.search, size: 18),
                  isDense: true,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                ),
                onChanged: (v) => setState(() => dinSearch = v),
              ),
            const SizedBox(height: 10),

            if (filtered.isEmpty)
              Center(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text('या वर्गवारीत दिनविशेष उपलब्ध नाहीत.', style: TextStyle(color: TG.mutedA)),
                ),
              )
            else
              Column(
                children: [
                  for (final ev in filtered) _buildDinItem(ev),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _chip(String key, String label) {
    final sel = dinFilter == key;
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: ChoiceChip(
        label: Text(label, style: TextStyle(fontSize: 11, fontWeight: sel ? FontWeight.bold : FontWeight.normal)),
        selected: sel,
        onSelected: (_) => setState(() => dinFilter = key),
        visualDensity: VisualDensity.compact,
      ),
    );
  }

  Widget _buildDinItem(Map ev) {
    final cat = ev['category'] ?? 'historical_event';
    Color borderClr = const Color(0xFF0284C7);
    String catText = 'घटना';
    Color badgeBg = const Color(0xFFE0F2FE);
    Color badgeFg = const Color(0xFF0369A1);

    if (cat == 'international_day') {
      borderClr = const Color(0xFF10B981);
      catText = 'दिनविशेष';
      badgeBg = const Color(0xFFD1FAE5);
      badgeFg = const Color(0xFF065F46);
    } else if (cat == 'birth_anniversary') {
      borderClr = const Color(0xFFF59E0B);
      catText = 'जयंती';
      badgeBg = const Color(0xFFFEF3C7);
      badgeFg = const Color(0xFF92400E);
    } else if (cat == 'death_anniversary') {
      borderClr = const Color(0xFFEF4444);
      catText = 'पुण्यतिथी';
      badgeBg = const Color(0xFFFEE2E2);
      badgeFg = const Color(0xFF991B1B);
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: const BorderRadius.horizontal(right: Radius.circular(8)),
        border: Border(left: BorderSide(color: borderClr, width: 4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(color: badgeBg, borderRadius: BorderRadius.circular(4)),
                child: Text(catText, style: TextStyle(color: badgeFg, fontSize: 10, fontWeight: FontWeight.bold)),
              ),
              const Spacer(),
              if ((ev['event_year'] ?? '').toString().isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: const Color(0xFFCBD5E1)),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text('सन ${ev['event_year']}',
                      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF475569))),
                ),
            ],
          ),
          const SizedBox(height: 4),
          SelectableText(
            '${ev['title_mr'] ?? ''}',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, height: 1.3),
          ),
          if ((ev['short_info_mr'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 2),
            SelectableText(
              '${ev['short_info_mr']}',
              style: const TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.4),
            ),
          ] else if ((ev['description_mr'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 2),
            SelectableText(
              '${ev['description_mr']}',
              maxLines: 4,
              style: const TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.4),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildPrayersQuickCard(List prayers, Map d) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.music_note, size: 18, color: Color(0xFFDC2626)),
                const SizedBox(width: 8),
                const Text('प्रमुख शालेय प्रार्थना व गीते',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const Spacer(),
                TextButton(
                  onPressed: () => _openPrayersSheet(prayers),
                  child: const Text('सर्व पहा →', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                ),
              ],
            ),
            const Divider(),
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: const CircleAvatar(radius: 12, backgroundColor: Color(0xFFFEE2E2), child: Text('🇮🇳', style: TextStyle(fontSize: 12))),
              title: const Text('राष्ट्रगीत — जन-गण-मन', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              trailing: const Icon(Icons.chevron_right, size: 18),
              onTap: () => _showRoutineDetail(1, 'राष्ट्रगीत (जन गण मन)', '${d['national_anthem']}'),
            ),
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: const CircleAvatar(radius: 12, backgroundColor: Color(0xFFE0E7FF), child: Text('✋', style: TextStyle(fontSize: 12))),
              title: const Text('राष्ट्रीय प्रतिज्ञा (मराठी व इंग्रजी)', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              trailing: const Icon(Icons.chevron_right, size: 18),
              onTap: () => _showRoutineDetail(2, 'राष्ट्रीय प्रतिज्ञा', '${d['pledge']}'),
            ),
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: const CircleAvatar(radius: 12, backgroundColor: Color(0xFFDCFCE7), child: Text('📜', style: TextStyle(fontSize: 12))),
              title: const Text('भारताचे संविधान — उद्देशिका', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              trailing: const Icon(Icons.chevron_right, size: 18),
              onTap: () {
                final p = prayers.firstWhere((pr) => pr['category'] == 'preamble', orElse: () => {});
                _showRoutineDetail(3, 'संविधान उद्देशिका', '${p['content'] ?? 'आम्ही भारताचे लोक...'}', sub: '${p['meaning'] ?? ''}');
              },
            ),
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: const CircleAvatar(radius: 12, backgroundColor: Color(0xFFFEF3C7), child: Text('🌸', style: TextStyle(fontSize: 12))),
              title: const Text('संत ज्ञानेश्वरांचे पसायदान', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              trailing: const Icon(Icons.chevron_right, size: 18),
              onTap: () {
                final p = prayers.firstWhere((pr) => pr['category'] == 'pasaydan', orElse: () => {});
                _showRoutineDetail(8, 'संत ज्ञानेश्वरांचे पसायदान', '${p['content'] ?? 'आता विश्वात्मकें देवें...'}', sub: '${p['meaning'] ?? ''}');
              },
            ),
          ],
        ),
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Digital Blackboard View (TV / Projector Mode)
// -----------------------------------------------------------------------------
class _DigitalBlackboard extends StatefulWidget {
  const _DigitalBlackboard({required this.data, required this.ds});
  final Map data;
  final String ds;

  @override
  State<_DigitalBlackboard> createState() => _DigitalBlackboardState();
}

class _DigitalBlackboardState extends State<_DigitalBlackboard> {
  bool isGreen = true; // true = Green Chalkboard, false = Black Board

  @override
  Widget build(BuildContext context) {
    final d = widget.data;
    final p = (d['panchang'] as Map?) ?? {};
    final suvichar = (d['suvichar_data'] as Map?) ?? {};
    final notes = (d['custom_notes'] as Map?) ?? {};
    final dinList = (d['dinvishesh_list'] as List? ?? []).cast<Map>();
    final schoolName = '${d['school_name'] ?? 'महाराष्ट्र राज्य प्राथमिक/माध्यमिक शाळा'}';

    final bgClr = isGreen ? const Color(0xFF0F3827) : const Color(0xFF18181B);
    final boardInnerClr = isGreen ? const Color(0xFF164E35) : const Color(0xFF27272A);

    return Container(
      height: MediaQuery.of(context).size.height * 0.95,
      decoration: const BoxDecoration(
        color: Color(0xFF78350F), // Wooden frame border
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        boxShadow: [BoxShadow(color: Colors.black54, blurRadius: 20)],
      ),
      padding: const EdgeInsets.all(10),
      child: Container(
        decoration: BoxDecoration(
          color: bgClr,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFF92400E), width: 3),
        ),
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            // Chalkboard Header Controls
            Row(
              children: [
                const Icon(Icons.brush, color: Color(0xFFFDE68A), size: 16),
                const SizedBox(width: 6),
                const Text('डिजिटल परिपाठ फळा',
                    style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.bold)),
                const Spacer(),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    visualDensity: VisualDensity.compact,
                    foregroundColor: Colors.white,
                    side: const BorderSide(color: Colors.white30),
                  ),
                  onPressed: () => setState(() => isGreen = !isGreen),
                  child: Text(isGreen ? 'काळा फळा' : 'हिरवा फळा', style: const TextStyle(fontSize: 11)),
                ),
                const SizedBox(width: 6),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  icon: const Icon(Icons.close, color: Colors.white),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const Divider(color: Colors.white24),

            // Chalkboard Scrollable Content
            Expanded(
              child: SingleChildScrollView(
                child: Column(
                  children: [
                    // School Name & Date
                    Text(
                      schoolName,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Color(0xFFFBBF24), fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${d['marathi_date'] ?? widget.ds} (${d['marathi_day'] ?? ''})',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900),
                    ),
                    Text(
                      'तिथी: ${p['tithi'] ?? '-'} • ${p['hindu_month'] ?? ''} (${p['paksha'] ?? ''}) • सूर्योदय: ${p['sunrise'] ?? ''}',
                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                    const SizedBox(height: 14),

                    // Suvichar in Chalk style
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: boardInnerClr,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.white24),
                      ),
                      child: Column(
                        children: [
                          const Text('★ आजचा सुविचार ★',
                              style: TextStyle(color: Color(0xFFFDE68A), fontWeight: FontWeight.bold, fontSize: 12)),
                          const SizedBox(height: 6),
                          Text(
                            '"${suvichar['text_mr'] ?? d['suvichar'] ?? ''}"',
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold, height: 1.4),
                          ),
                          if ((suvichar['author'] ?? d['suvichar_source'] ?? '').toString().isNotEmpty)
                            Align(
                              alignment: Alignment.centerRight,
                              child: Text('— ${suvichar['author'] ?? d['suvichar_source']}',
                                  style: const TextStyle(color: Colors.white70, fontSize: 12, fontStyle: FontStyle.italic)),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Word of Day & GK Side-by-Side
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: boardInnerClr,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: Colors.white24),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('॥ आजचा शब्द ॥', style: TextStyle(color: Color(0xFF93C5FD), fontSize: 11, fontWeight: FontWeight.bold)),
                                const SizedBox(height: 4),
                                Text('${notes['word_of_day'] ?? 'परिश्रम (Hard Work)'}',
                                    style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                                Text('${notes['word_meaning'] ?? 'सतत केलेले प्रयत्न'}',
                                    style: const TextStyle(color: Colors.white70, fontSize: 11)),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: boardInnerClr,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: Colors.white24),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('॥ सामान्य ज्ञान ॥', style: TextStyle(color: Color(0xFF86EFAC), fontSize: 11, fontWeight: FontWeight.bold)),
                                const SizedBox(height: 4),
                                Text('प्र: ${notes['gk_question'] ?? 'भारताचे पहिले अंतराळवीर कोण?'}',
                                    style: const TextStyle(color: Colors.white, fontSize: 11)),
                                const SizedBox(height: 2),
                                Text('उ: ${notes['gk_answer'] ?? 'राकेश शर्मा'}',
                                    style: const TextStyle(color: Color(0xFF86EFAC), fontSize: 12, fontWeight: FontWeight.bold)),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Dinvishesh Highlights
                    if (dinList.isNotEmpty)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: boardInnerClr,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.white24),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('॥ आजचे महत्त्वाचे दिनविशेष ॥',
                                style: TextStyle(color: Color(0xFFFDE68A), fontSize: 11, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 6),
                            for (final ev in dinList.take(4))
                              Padding(
                                padding: const EdgeInsets.only(bottom: 4),
                                child: Text(
                                  '• ${ev['title_mr']} ${ev['event_year'] != null && '${ev['event_year']}'.isNotEmpty ? '(सन ${ev['event_year']})' : ''}',
                                  style: const TextStyle(color: Colors.white, fontSize: 12, height: 1.3),
                                ),
                              ),
                          ],
                        ),
                      ),
                    const SizedBox(height: 12),

                    // School Announcement
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: boardInnerClr,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFFDE68A)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('॥ आजची सूचना ॥',
                              style: TextStyle(color: Color(0xFFFDE68A), fontSize: 11, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 4),
                          Text(
                            (notes['announcement'] ?? '').toString().isNotEmpty
                                ? '${notes['announcement']}'
                                : 'आज परिपाठानंतर सर्व विद्यार्थ्यांनी वर्ग स्वच्छता करावी.',
                            style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Prayers Collection Sheet
// -----------------------------------------------------------------------------
class _PrayersSheet extends StatefulWidget {
  const _PrayersSheet({required this.prayers});
  final List prayers;

  @override
  State<_PrayersSheet> createState() => _PrayersSheetState();
}

class _PrayersSheetState extends State<_PrayersSheet> {
  String selectedCat = 'all';
  String q = '';

  @override
  Widget build(BuildContext context) {
    final filtered = widget.prayers.where((p) {
      if (selectedCat != 'all' && (p['category'] ?? '') != selectedCat) return false;
      if (q.trim().isNotEmpty) {
        final query = q.toLowerCase();
        final t = '${p['title_mr'] ?? ''}'.toLowerCase();
        final a = '${p['author'] ?? ''}'.toLowerCase();
        final c = '${p['content'] ?? ''}'.toLowerCase();
        if (!t.contains(query) && !a.contains(query) && !c.contains(query)) return false;
      }
      return true;
    }).toList();

    return Container(
      height: MediaQuery.of(context).size.height * 0.9,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.music_note, color: TG.g600),
              const SizedBox(width: 8),
              const Text('शालेय प्रार्थना व प्रतिज्ञा संग्रह',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
              const Spacer(),
              IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
            ],
          ),
          const SizedBox(height: 10),

          // Search bar
          TextField(
            decoration: InputDecoration(
              hintText: 'प्रार्थना किंवा गीत शोधा...',
              prefixIcon: const Icon(Icons.search, size: 18),
              isDense: true,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            ),
            onChanged: (v) => setState(() => q = v),
          ),
          const SizedBox(height: 10),

          // Category chips
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                _catChip('all', 'सर्व (${widget.prayers.length})'),
                _catChip('national', 'राष्ट्रगीत'),
                _catChip('pledge', 'प्रतिज्ञा'),
                _catChip('preamble', 'संविधान उद्देशिका'),
                _catChip('pasaydan', 'पसायदान'),
                _catChip('prayer', 'शालेय प्रार्थना'),
              ],
            ),
          ),
          const SizedBox(height: 10),

          Expanded(
            child: filtered.isEmpty
                ? Center(child: Text('कोणतीही प्रार्थना सापडली नाही.', style: TextStyle(color: TG.mutedA)))
                : ListView.builder(
                    itemCount: filtered.length,
                    itemBuilder: (ctx, i) {
                      final p = filtered[i];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      '${p['title_mr'] ?? ''}',
                                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                                    ),
                                  ),
                                  if ((p['category'] ?? '').toString().isNotEmpty)
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: TG.g600.withValues(alpha: 0.1),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        '${p['category']}',
                                        style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: TG.g600),
                                      ),
                                    ),
                                ],
                              ),
                              if ((p['author'] ?? '').toString().isNotEmpty) ...[
                                const SizedBox(height: 2),
                                Text('— ${p['author']}',
                                    style: TextStyle(fontSize: 12, color: TG.mutedA, fontStyle: FontStyle.italic)),
                              ],
                              const Divider(height: 16),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFDFBF7),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: const Color(0xFFF1ECE1)),
                                ),
                                child: SelectableText(
                                  '${p['content'] ?? ''}'.trim(),
                                  style: const TextStyle(fontSize: 15, height: 1.8, fontFamily: 'serif'),
                                ),
                              ),
                              if ((p['meaning'] ?? '').toString().isNotEmpty) ...[
                                const SizedBox(height: 8),
                                Text('भावार्थ: ${p['meaning']}',
                                    style: const TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.4)),
                              ],
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _catChip(String cat, String label) {
    final sel = selectedCat == cat;
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: ChoiceChip(
        label: Text(label, style: TextStyle(fontSize: 11, fontWeight: sel ? FontWeight.bold : FontWeight.normal)),
        selected: sel,
        onSelected: (_) => setState(() => selectedCat = cat),
        visualDensity: VisualDensity.compact,
      ),
    );
  }
}
