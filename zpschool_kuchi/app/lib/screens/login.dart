import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/config.dart';
import '../core/session.dart';
import '../core/sync.dart';
import 'ai_study_bot.dart';
import 'home.dart';
import 'widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _mobileController = TextEditingController();
  final _passController = TextEditingController();
  String _selectedRole = 'teacher';
  bool _busy = false;
  bool _hidePassword = true;

  // Student Easy Login State
  int _selectedStd = 6;
  List<dynamic> _classStudents = [];
  Map<String, dynamic>? _selectedStudent;
  bool _loadingStudents = false;
  bool _easyStudentMode = true;

  @override
  void initState() {
    super.initState();
    _passController.text = '123456';
  }

  @override
  void dispose() {
    _mobileController.dispose();
    _passController.dispose();
    super.dispose();
  }

  Future<void> _loadClassStudents(int std) async {
    setState(() {
      _selectedStd = std;
      _loadingStudents = true;
      _selectedStudent = null;
    });
    try {
      final res = await Api.get('easy_login.php', {
        'action': 'get_class_students',
        'std': std.toString(),
      });
      if (mounted) {
        final rawList = (res['students'] as List<dynamic>?) ?? [];
        final seen = <int>{};
        final uniqueList = <dynamic>[];
        for (final item in rawList) {
          if (item is Map) {
            final id = int.tryParse('${item['id']}');
            if (id != null && !seen.contains(id)) {
              seen.add(id);
              uniqueList.add(item);
            }
          }
        }
        setState(() {
          _classStudents = uniqueList;
          _loadingStudents = false;
          if (uniqueList.isNotEmpty) {
            _selectedStudent = Map<String, dynamic>.from(uniqueList.first as Map);
            _mobileController.text = _selectedStudent?['username'] ?? _selectedStudent?['student_pen'] ?? '';
            _passController.text = '123456';
          }
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingStudents = false);
    }
  }

  void _loginSuccess(String name) {
    if (!mounted) return;
    toast(context, 'स्वागत आहे, $name!');
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const HomeScreen()),
    );
  }



  Future<void> _submit() async {
    final loginId = _mobileController.text.trim();
    final password = _passController.text;

    if (_selectedRole == 'student' && _easyStudentMode && _selectedStudent == null) {
      toast(context, 'कृपया विद्यार्थी निवडा', error: true);
      return;
    }

    if (password.isEmpty) {
      toast(context, 'कृपया पासवर्ड भरा (पहिली वेळ असल्यास: 123456)', error: true);
      return;
    }

    setState(() => _busy = true);
    try {
      if (_selectedRole == 'student' && _easyStudentMode && _selectedStudent != null) {
        final res = await Api.post('easy_login.php?action=easy_login', {
          'student_id': _selectedStudent!['id'],
          'pin': password,
        });
        final st = Map<String, dynamic>.from(res['student'] as Map);
        final token = st['token'] as String;
        final user = {
          'id': st['user_id'] ?? st['student_id'],
          'linked_id': st['student_id'],
          'name': st['name'],
          'name_mr': st['name_mr'] ?? st['name'],
          'std': st['std'],
          'role': 'student',
        };
        await Session.I.save(token, user, 'student');
        await Session.I.addSavedStudent({
          'student_id': st['student_id'],
          'name': st['name'],
          'name_mr': st['name_mr'] ?? st['name'],
          'std': st['std'],
          'token': token,
        });
        if (!mounted) return;
        toast(context, 'लॉगिन यशस्वी! स्वागत आहे, ${st['name_mr'] ?? st['name']}');
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const HomeScreen()),
        );
        return;
      }

      if (loginId.isEmpty) {
        toast(context, 'कृपया लॉगिन आयडी भरा', error: true);
        setState(() => _busy = false);
        return;
      }

      final res = await Api.post('auth/login.php', {
        'mobile': loginId,
        'password': password,
        'role': _selectedRole,
      });

      final data = Map<String, dynamic>.from(res['data'] as Map);
      final token = data['token'] as String;
      final user = Map<String, dynamic>.from(data['user'] as Map);
      final role = (user['role'] ?? _selectedRole).toString();

      await Session.I.save(token, user, role);
      if (role == 'student') {
        await Session.I.addSavedStudent({
          'student_id': user['linked_id'] ?? user['id'],
          'name': user['name'],
          'name_mr': user['name_mr'] ?? user['name'],
          'std': user['std'] ?? 6,
          'token': token,
        });
      }
      
      // Auto-sync offline cache if teacher
      if (role == 'teacher') {
        try {
          await SyncService.I.run();
        } catch (_) {}
      }

      if (!mounted) return;
      toast(context, 'लॉगिन यशस्वी! स्वागत आहे.');
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const HomeScreen()),
      );
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } catch (e) {
      if (mounted) toast(context, 'सर्व्हरशी संपर्क होऊ शकला नाही: $e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: ListView(
              shrinkWrap: true,
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              children: [
                // School Header Card
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF1E3A8A), Color(0xFF0F172A)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.12),
                        blurRadius: 16,
                        offset: const Offset(0, 6),
                      )
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(Icons.school, color: Color(0xFF1E3A8A), size: 28),
                          ),
                          const SizedBox(width: 14),
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  kAppName,
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: 18,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                Text(
                                  'UDISE: $kSchoolUdise | $kAcademicYear',
                                  style: TextStyle(color: Color(0xFFCFE7DC), fontSize: 13),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        'शाळा पोर्टल व समग्र प्रगती पत्रक',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const Text(
                        'NEP 2020 Single-School Dedicated App',
                        style: TextStyle(color: Color(0xFF93C5FD), fontSize: 13),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),

                // Role Selector Chips
                const Text(
                  'आपले पद निवडा (Select Role):',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF334155)),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: ChoiceChip(
                        label: const Text('शिक्षक'),
                        avatar: const Icon(Icons.person_pin, size: 18),
                        selected: _selectedRole == 'teacher',
                        onSelected: (v) => setState(() => _selectedRole = 'teacher'),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: ChoiceChip(
                        label: const Text('विद्यार्थी'),
                        avatar: const Icon(Icons.backpack, size: 18),
                        selected: _selectedRole == 'student',
                        onSelected: (v) {
                          setState(() => _selectedRole = 'student');
                          if (_classStudents.isEmpty) _loadClassStudents(_selectedStd);
                        },
                      ),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: ChoiceChip(
                        label: const Text('प्रशासक'),
                        avatar: const Icon(Icons.shield, size: 18),
                        selected: _selectedRole == 'admin',
                        onSelected: (v) => setState(() => _selectedRole = 'admin'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // Saved Accounts for Parents/Siblings
                if (_selectedRole == 'student' && Session.I.savedStudents.isNotEmpty) ...[
                  const Text(
                    'जतन केलेली पाल्य खाती (Saved Sibling Accounts):',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1E3A8A)),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: Session.I.savedStudents.map((s) {
                      final sName = s['name_mr'] ?? s['name'] ?? 'पाल्य';
                      final sStd = s['std'] ?? '-';
                      return ActionChip(
                        avatar: const Icon(Icons.account_circle, size: 18, color: Color(0xFF2563EB)),
                        label: Text('🎓 $sName (इ. ${formatMarathiStd(sStd)})'),
                        onPressed: () async {
                          final sToken = s['token'] ?? '';
                          if (sToken.isNotEmpty) {
                            await Session.I.save(sToken, {
                              'id': s['student_id'] ?? s['id'],
                              'linked_id': s['student_id'] ?? s['id'],
                              'name': sName,
                              'std': sStd,
                              'role': 'student',
                            }, 'student');
                            _loginSuccess(sName);
                          }
                        },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),
                ],

                // Student Class Selection & Dropdown
                if (_selectedRole == 'student' && _easyStudentMode) ...[
                  const Text(
                    'इयत्ता निवडा (Select Standard):',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF334155)),
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
                            label: Text(formatMarathiStd(stdNum)),
                            selected: isSel,
                            selectedColor: const Color(0xFF1E3A8A),
                            labelStyle: TextStyle(
                              color: isSel ? Colors.white : Colors.black87,
                              fontWeight: FontWeight.bold,
                            ),
                            onSelected: (_) => _loadClassStudents(stdNum),
                          ),
                        );
                      }),
                    ),
                  ),
                  const SizedBox(height: 16),

                  if (_loadingStudents)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 14),
                      child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                    )
                  else if (_classStudents.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 10),
                      child: Text('विद्यार्थी यादी उपलब्ध नाही', style: TextStyle(color: Colors.red)),
                    )
                  else
                    DropdownButtonFormField<int>(
                      value: _selectedStudent != null && _classStudents.any((x) => x['id'] == _selectedStudent!['id'])
                          ? _selectedStudent!['id'] as int
                          : null,
                      decoration: InputDecoration(
                        labelText: 'विद्यार्थी निवडा (Select Student)',
                        prefixIcon: const Icon(Icons.school_outlined),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      items: _classStudents.map((s) {
                        final sMap = Map<String, dynamic>.from(s as Map);
                        final name = sMap['name_mr'] ?? sMap['name'] ?? '';
                        final uid = sMap['username'] ?? 'kuchi${sMap['std']}${sMap['roll_no']}';
                        return DropdownMenuItem<int>(
                          value: sMap['id'] as int,
                          child: Text(
                            '$name ($uid)',
                            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                            overflow: TextOverflow.ellipsis,
                          ),
                        );
                      }).toList(),
                      onChanged: (sid) {
                        final found = _classStudents.firstWhere((x) => x['id'] == sid, orElse: () => null);
                        if (found != null) {
                          setState(() {
                            _selectedStudent = Map<String, dynamic>.from(found as Map);
                            _mobileController.text = _selectedStudent?['username'] ?? _selectedStudent?['student_pen'] ?? '';
                          });
                        }
                      },
                    ),
                  const SizedBox(height: 16),
                ] else ...[
                  // Standard Login ID TextField
                  TextField(
                    controller: _mobileController,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: _selectedRole == 'student'
                          ? 'Student UserID किंवा PEN'
                          : 'मोबाईल क्रमांक / Login ID',
                      hintText: _selectedRole == 'student' ? 'उदा. kuchi601 किंवा 21935574209' : 'उदा. 9960967574',
                      prefixIcon: const Icon(Icons.person_outline),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Password TextField
                TextField(
                  controller: _passController,
                  obscureText: _hidePassword,
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _submit(),
                  decoration: InputDecoration(
                    labelText: 'पासवर्ड (Password)',
                    helperText: _selectedRole == 'student' ? 'डीफॉल्ट पासवर्ड: 123456 (शिक्षकांकडून रीसेट करता येतो)' : null,
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      icon: Icon(_hidePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                      onPressed: () => setState(() => _hidePassword = !_hidePassword),
                    ),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 8),

                if (_selectedRole == 'student')
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () => setState(() => _easyStudentMode = !_easyStudentMode),
                      child: Text(
                        _easyStudentMode ? 'किंवा UserID / PEN ने मॅन्युअली लॉगिन करा' : 'सोप्या पद्धतीकडे परत जा (इयत्ता निवडा)',
                        style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                const SizedBox(height: 16),

                // Submit Button
                FilledButton(
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    backgroundColor: const Color(0xFF1E3A8A),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                        )
                      : const Text(
                          'लॉगिन करा (Sign In)',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                        ),
                ),
                const SizedBox(height: 24),

              ],
            ),
          ),
        ),
      ),
    );
  }
}
