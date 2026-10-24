import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/remote.dart';
import '../theme.dart';
import 'attendance.dart';
import 'cce.dart';
import 'hpc.dart';
import 'mdm.dart';
import 'quiz.dart';
import 'students.dart';
import 'tachan.dart';
import 'timetable.dart';
import 'widgets.dart';

String _formatStd(int num) {
  const map = {1: '१ ली', 2: '२ री', 3: '३ री', 4: '४ थी', 5: '५ वी', 6: '६ वी', 7: '७ वी', 8: '८ वी'};
  return map[num] ?? 'इ. $num';
}

class StudyAndTutorialsScreen extends StatefulWidget {
  const StudyAndTutorialsScreen({super.key, this.initialTab = 0});
  final int initialTab;

  @override
  State<StudyAndTutorialsScreen> createState() => _StudyAndTutorialsScreenState();
}

class _StudyAndTutorialsScreenState extends State<StudyAndTutorialsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  int _selectedStd = 6;
  final String _selectedSubject = 'सर्व';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this, initialIndex: widget.initialTab);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _openYouTube(String url) async {
    final uri = Uri.parse(url);
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      await launchUrl(uri, mode: LaunchMode.inAppWebView);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: TG.darkMode ? TG.dBg : const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('शैक्षणिक व्हिडिओ व शिक्षक सुविधा', style: TextStyle(fontWeight: FontWeight.w800)),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: TG.g600,
          indicatorWeight: 3,
          labelColor: TG.inkA,
          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14.5),
          tabs: const [
            Tab(icon: Icon(Icons.cast_for_education), text: 'शिक्षक सर्व सुविधा मार्गदर्शक'),
            Tab(icon: Icon(Icons.video_library), text: 'इयत्तानिहाय अभ्यास व्हिडिओ'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildTeacherFunctionsList(),
          _buildStudyVideosList(),
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════
  // TAB 1: ALL TEACHER FUNCTIONS & TUTORIALS
  // ══════════════════════════════════════════════════════════════════
  Widget _buildTeacherFunctionsList() {
    final functions = [
      {
        'title': 'हजेरी नोंदणी (Daily Attendance)',
        'sub': 'दररोज सकाळची पटसंख्या, हजेरी, गैरहजर विद्यार्थी व पालक SMS/WhatsApp अहवाल.',
        'icon': Icons.checklist_rtl_rounded,
        'color': const Color(0xFF10B981),
        'yt': 'https://www.youtube.com/results?search_query=ZP+school+attendance+app+tutorial',
        'actionLabel': 'हजेरी उघडा',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AttendanceScreen())),
      },
      {
        'title': 'CCE सातत्यपूर्ण मूल्यमापन',
        'sub': 'आकारिक व संकलित गुण नोंद, वर्णनात्मक नोंदी, श्रेणी तक्ते व अधिकृत निकालपत्रक.',
        'icon': Icons.assignment_turned_in_rounded,
        'color': const Color(0xFFB83B5E),
        'yt': 'https://www.youtube.com/results?search_query=Maharashtra+CCE+evaluation+portal+tutorial',
        'actionLabel': 'CCE उघडा',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CceScreen())),
      },
      {
        'title': 'समग्र प्रगती पत्रक (HPC SCERT)',
        'sub': 'रुब्रिक्स नोंद, स्वतःचे व समवयस्क मूल्यमापन, ३D प्रगती पत्रक व SCERT PDF छपाई.',
        'icon': Icons.insert_chart_rounded,
        'color': const Color(0xFF0F8A84),
        'yt': 'https://www.youtube.com/results?search_query=Holistic+progress+card+SCERT+Maharashtra',
        'actionLabel': 'HPC उघडा',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const HpcScreen())),
      },
      {
        'title': 'दैनंदिन टाचण व पाठ नियोजन (Tachan)',
        'sub': 'दैनिक टाचण नोंद, अध्ययन निष्पत्ती, वार्षिक नियोजन व आठवडा टाचण PDF.',
        'icon': Icons.edit_note_rounded,
        'color': const Color(0xFF6B4FB8),
        'yt': 'https://www.youtube.com/results?search_query=Daily+lesson+plan+tachan+ZP+school',
        'actionLabel': 'टाचण उघडा',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TachanScreen())),
      },
      {
        'title': 'शाळा वेळापत्रक (Timetable)',
        'sub': 'इयत्तानिहाय व शिक्षकनिहाय तासिका नियोजन व छापील वेळापत्रक तक्ता.',
        'icon': Icons.schedule_rounded,
        'color': const Color(0xFF7C3AED),
        'yt': 'https://www.youtube.com/results?search_query=ZP+school+timetable+management',
        'actionLabel': 'वेळापत्रक उघडा',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TimetableScreen())),
      },
      {
        'title': 'पोषण आहार (MDM Mid-Day Meal)',
        'sub': 'दैनिक लाभार्थी संख्या, मेनू, धान्य खर्च व शिल्लक साठा रजिस्टर अहवाल.',
        'icon': Icons.restaurant_rounded,
        'color': const Color(0xFFB7791F),
        'yt': 'https://www.youtube.com/results?search_query=MDM+portal+Maharashtra+tutorial',
        'actionLabel': 'MDM उघडा',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MdmScreen())),
      },
      {
        'title': 'विद्यार्थी आयडी व पासवर्ड PDF (Credentials)',
        'sub': 'सर्व १९० विद्यार्थ्यांची User ID, PEN व पासवर्ड (123456) यादी १-क्लिक PDF डाऊनलोड.',
        'icon': Icons.badge_rounded,
        'color': const Color(0xFF1E40AF),
        'yt': 'https://www.youtube.com/results?search_query=student+login+credentials+card+school',
        'actionLabel': 'PDF डाऊनलोड',
        'onTap': () => Remote.printReport(
          context,
          'modules/classes/student_credentials_pdf.php',
          {},
          name: 'विद्यार्थी आयडी व पासवर्ड यादी',
        ),
      },
      {
        'title': 'इयत्ता ४ थी व ७ वी शिष्यवृत्ती परीक्षा',
        'sub': 'पेपर १ (मराठी व गणित) आणि पेपर २ (इंग्रजी व बुद्धिमत्ता) सराव संच व निकाल.',
        'icon': Icons.military_tech_rounded,
        'color': const Color(0xFFEA580C),
        'yt': 'https://www.youtube.com/results?search_query=Maharashtra+scholarship+exam+std+4+7',
        'actionLabel': 'सराव क्विझ',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const QuizHomeScreen())),
      },
      {
        'title': 'विद्यार्थी माहिती व UDISE+ आयात',
        'sub': 'विद्यार्थी प्रोफाईल, आधार, PEN, जात संवर्ग व UDISE+ Excel थेट आयात.',
        'icon': Icons.groups_rounded,
        'color': const Color(0xFF047857),
        'yt': 'https://www.youtube.com/results?search_query=UDISE+plus+student+import+excel+tutorial',
        'actionLabel': 'विद्यार्थी यादी',
        'onTap': () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentsScreen())),
      },
    ];

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF0F172A), Color(0xFF1E3A8A)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 10, offset: const Offset(0, 4)),
            ],
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
                child: const Icon(Icons.school, color: Color(0xFF1E3A8A), size: 28),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'शिक्षकांसाठी ॲप सर्व सुविधा मार्गदर्शक',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'प्रत्येक सुविधेची सविस्तर माहिती, थेट वापर व व्हिडिओ ट्यूटोरियल्स.',
                      style: TextStyle(color: Color(0xFFCFE7DC), fontSize: 12),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        for (final fn in functions)
          PressableScale(
            onTap: fn['onTap'] as VoidCallback,
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: TG.darkMode ? TG.dCard : Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: TG.darkMode ? TG.dLine : const Color(0xFFE2E8F0),
                  width: 1.2,
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.04),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  )
                ],
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: (fn['color'] as Color).withOpacity(0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(fn['icon'] as IconData, color: fn['color'] as Color, size: 24),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          fn['title'] as String,
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            color: TG.inkA,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          fn['sub'] as String,
                          style: TextStyle(fontSize: 12.5, color: TG.mutedA, height: 1.35),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            FilledButton.tonal(
                              style: FilledButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 0),
                                minimumSize: const Size(0, 32),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              ),
                              onPressed: fn['onTap'] as VoidCallback,
                              child: Text(
                                fn['actionLabel'] as String,
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                              ),
                            ),
                            const SizedBox(width: 8),
                            OutlinedButton.icon(
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
                                minimumSize: const Size(0, 32),
                                side: const BorderSide(color: Color(0xFFEF4444), width: 1.2),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              ),
                              onPressed: () => _openYouTube(fn['yt'] as String),
                              icon: const Icon(Icons.play_circle_fill, size: 16, color: Color(0xFFEF4444)),
                              label: const Text(
                                'व्हिडिओ पहा',
                                style: TextStyle(color: Color(0xFFEF4444), fontSize: 12, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  // ══════════════════════════════════════════════════════════════════
  // TAB 2: CLASS-WISE STUDY YOUTUBE VIDEOS (STD 1 TO 8)
  // ══════════════════════════════════════════════════════════════════
  Widget _buildStudyVideosList() {
    final studyVideos = [
      // Standard 1
      {
        'std': 1,
        'subject': 'मराठी',
        'title': 'इयत्ता १ ली : मराठी मुळाक्षरे व बालगीते',
        'desc': 'स्वर, व्यंजने व सचित्र अक्षर ओळख बालवाडी व पहिलीसाठी.',
        'yt': 'https://www.youtube.com/results?search_query=Class+1+Marathi+alphabets+mulakshare+balbharti',
      },
      {
        'std': 1,
        'subject': 'गणित',
        'title': 'इयत्ता १ ली : अंक ओळख १ ते १० व लहान-मोठा',
        'desc': 'चित्रांच्या साहाय्याने अंकांचे ज्ञान व बेरीज संकल्पना.',
        'yt': 'https://www.youtube.com/results?search_query=Class+1+Marathi+medium+ganit+ank+olakh',
      },
      // Standard 2
      {
        'std': 2,
        'subject': 'मराठी',
        'title': 'इयत्ता २ री : वाचन पाठ व कविता',
        'desc': 'मराठी बालभारती इ. २ री सर्व कविता चालीवर गायन.',
        'yt': 'https://www.youtube.com/results?search_query=Class+2+Marathi+balbharti+kavita+chali',
      },
      {
        'std': 2,
        'subject': 'गणित',
        'title': 'इयत्ता २ री : हातच्याची बेरीज व वजाबाकी',
        'desc': 'स्थानिक किंमत, दशक-एकक व साध्या बेरजा.',
        'yt': 'https://www.youtube.com/results?search_query=Class+2+Maths+Marathi+medium+berij+vajabaki',
      },
      // Standard 3
      {
        'std': 3,
        'subject': 'गणित',
        'title': 'इयत्ता ३ री : पाढे तयार करणे व गुणाकार',
        'desc': '२ ते १० चे पाढे ट्रिक्स व २-अंकी गुणाकार.',
        'yt': 'https://www.youtube.com/results?search_query=Class+3+Maths+Marathi+gunakar+padhe',
      },
      {
        'std': 3,
        'subject': 'परिसर अभ्यास',
        'title': 'इयत्ता ३ री : आपला परिसर व पाण्याची गरज',
        'desc': 'परिसर अभ्यास भाग १ व २ सजीव-निर्जीव संकल्पना.',
        'yt': 'https://www.youtube.com/results?search_query=Class+3+Parisar+abhyas+Marathi+medium',
      },
      // Standard 4
      {
        'std': 4,
        'subject': 'शिष्यवृत्ती',
        'title': 'इयत्ता ४ थी : शिष्यवृत्ती परीक्षा सराव (पेपर १ व २)',
        'desc': 'मराठी, गणित, इंग्रजी व बुद्धिमत्ता चाचणी महत्वाचे प्रश्न व ट्रिक्स.',
        'yt': 'https://www.youtube.com/results?search_query=Scholarship+exam+std+4+Marathi+medium+tricks',
      },
      {
        'std': 4,
        'subject': 'इतिहास',
        'title': 'इयत्ता ४ थी : शिवछत्रपती (परिसर अभ्यास २)',
        'desc': 'छत्रपती शिवाजी महाराजांचा प्रेरणादायी इतिहास सर्व पाठ.',
        'yt': 'https://www.youtube.com/results?search_query=Class+4+Shivchatrapati+itahas+Marathi',
      },
      // Standard 5
      {
        'std': 5,
        'subject': 'इंग्रजी',
        'title': 'इयत्ता ५ वी : My English Book Five',
        'desc': 'कथा, कवितांचे अर्थ व सुलभ इंग्रजी व्याकरण.',
        'yt': 'https://www.youtube.com/results?search_query=Class+5+My+English+Book+Five+Marathi+explanation',
      },
      {
        'std': 5,
        'subject': 'गणित',
        'title': 'इयत्ता ५ वी : अपूर्णांक व दशांश अपूर्णांक',
        'desc': 'समच्छेद अपूर्णांक, बेरीज-वजाबाकी व व्यवहारी उदाहरणे.',
        'yt': 'https://www.youtube.com/results?search_query=Class+5+Maths+Apoornank+Marathi',
      },
      // Standard 6
      {
        'std': 6,
        'subject': 'विज्ञान',
        'title': 'इयत्ता ६ वी : सामान्य विज्ञान (सर्व पाठ)',
        'desc': 'नैसर्गिक संसाधने, सजीव सृष्टी, गती व गतीचे प्रकार ॲनिमेशनसह.',
        'yt': 'https://www.youtube.com/results?search_query=Class+6+Science+Marathi+medium+animation',
      },
      {
        'std': 6,
        'subject': 'गणित',
        'title': 'इयत्ता ६ वी : भूमितीतील मूलभूत संबोध व पूर्णांक संख्या',
        'desc': 'बिंदू, रेषा, कोन व पूर्णांक संख्यांची बेरीज-वजाबाकी.',
        'yt': 'https://www.youtube.com/results?search_query=Class+6+Maths+bhumiti+purnank+sankhya+Marathi',
      },
      // Standard 7
      {
        'std': 7,
        'subject': 'शिष्यवृत्ती',
        'title': 'इयत्ता ७ वी : शिष्यवृत्ती व स्पर्धा परीक्षा सराव',
        'desc': 'बुद्धिमत्ता चाचणी, गणितीय सूत्रे व इंग्रजी व्याकरण प्रश्नपत्रिका विश्लेषण.',
        'yt': 'https://www.youtube.com/results?search_query=Class+7+scholarship+exam+buddhimatta+ganit+Marathi',
      },
      {
        'std': 7,
        'subject': 'विज्ञान',
        'title': 'इयत्ता ७ वी : वनस्पतींची रचना, कार्य व भौतिक राशींचे मापन',
        'desc': 'सचित्र विज्ञान प्रयोग व संकल्पना स्पष्टीकरण.',
        'yt': 'https://www.youtube.com/results?search_query=Class+7+Science+Marathi+medium+lessons',
      },
      // Standard 8
      {
        'std': 8,
        'subject': 'गणित',
        'title': 'इयत्ता ८ वी : परिमेय व अपरिमेय संख्या, समांतर रेषा',
        'desc': 'सरावसंच सोडवून स्पष्टीकरणासह गणितीय मार्गदर्शन.',
        'yt': 'https://www.youtube.com/results?search_query=Class+8+Maths+Marathi+medium+saravsanch',
      },
      {
        'std': 8,
        'subject': 'विज्ञान',
        'title': 'इयत्ता ८ वी : सजीव सृष्टी व सूक्ष्मजीवांचे वर्गीकरण',
        'desc': 'आर. एच. व्हिटाकर यांची पंचसृष्टी पद्धती व सूक्ष्मजीव.',
        'yt': 'https://www.youtube.com/results?search_query=Class+8+Science+Marathi+medium+all+chapters',
      },
    ];

    final filteredVideos = studyVideos.where((v) {
      if ((v['std'] as int) != _selectedStd) return false;
      if (_selectedSubject != 'सर्व' && v['subject'] != _selectedSubject) return false;
      return true;
    }).toList();

    return Column(
      children: [
        // Standard Selector Chips
        Container(
          color: TG.darkMode ? TG.dBar : Colors.white,
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'इयत्ता निवडा (Select Standard):',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
              const SizedBox(height: 8),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: List.generate(8, (idx) {
                    final stdNum = idx + 1;
                    final isSel = _selectedStd == stdNum;
                    return Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: ChoiceChip(
                        label: Text(_formatStd(stdNum)),
                        selected: isSel,
                        selectedColor: const Color(0xFF1E3A8A),
                        labelStyle: TextStyle(
                          color: isSel ? Colors.white : Colors.black87,
                          fontWeight: FontWeight.bold,
                        ),
                        onSelected: (_) => setState(() => _selectedStd = stdNum),
                      ),
                    );
                  }),
                ),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: filteredVideos.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.video_library_outlined, size: 48, color: Colors.grey),
                      const SizedBox(height: 8),
                      Text('या इयत्तेचे व्हिडिओ लवकरच उपलब्ध होतील.', style: TextStyle(color: TG.mutedA)),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                  itemCount: filteredVideos.length,
                  itemBuilder: (ctx, idx) {
                    final v = filteredVideos[idx];
                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: BoxDecoration(
                        color: TG.darkMode ? TG.dCard : Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: TG.darkMode ? TG.dLine : const Color(0xFFE2E8F0),
                          width: 1.2,
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.04),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 48,
                              height: 48,
                              decoration: BoxDecoration(
                                color: const Color(0xFFEF4444).withOpacity(0.12),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(Icons.play_circle_fill, color: Color(0xFFEF4444), size: 30),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFEFF6FF),
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: Text(
                                          '${v['subject']}',
                                          style: const TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.bold,
                                            color: Color(0xFF1D4ED8),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    v['title'] as String,
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 14.5,
                                      color: TG.inkA,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    v['desc'] as String,
                                    style: TextStyle(fontSize: 12, color: TG.mutedA),
                                  ),
                                  const SizedBox(height: 10),
                                  FilledButton.icon(
                                    style: FilledButton.styleFrom(
                                      backgroundColor: const Color(0xFFDC2626),
                                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 0),
                                      minimumSize: const Size(0, 32),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    ),
                                    onPressed: () => _openYouTube(v['yt'] as String),
                                    icon: const Icon(Icons.smart_display, size: 16),
                                    label: const Text('YouTube वर पहा', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}
