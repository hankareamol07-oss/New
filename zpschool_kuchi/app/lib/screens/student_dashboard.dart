import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../core/session.dart';
import '../core/theme_controller.dart';
import '../theme.dart';
import 'ai_study_bot.dart';
import 'login.dart';
import 'quiz.dart';

class StudentDashboardScreen extends StatefulWidget {
  const StudentDashboardScreen({super.key});

  @override
  State<StudentDashboardScreen> createState() => _StudentDashboardScreenState();
}

class _StudentDashboardScreenState extends State<StudentDashboardScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _error = false;

  @override
  void initState() {
    super.initState();
    _initWebView();
  }

  void _initWebView() {
    final user = Session.I.user;
    final token = Session.I.token ?? '';
    final baseUrl = Session.I.baseUrl;

    final uri = Uri.parse('$baseUrl/index.php').replace(queryParameters: {
      'token': token,
      'in_app': '1',
      'role': 'student',
    });

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFFF8FAFC))
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) => setState(() {
            _loading = true;
            _error = false;
          }),
          onPageFinished: (url) {
            setState(() => _loading = false);
            _controller.runJavaScript("""
              try {
                document.querySelectorAll('a[target]').forEach(function(a) {
                  a.removeAttribute('target');
                });
                window.open = function(u) {
                  window.location.href = u;
                  return window;
                };
              } catch(e) {}
            """);
            // Save current student into saved accounts if not already saved
            if (user['id'] != null || user['linked_id'] != null) {
              Session.I.addSavedStudent({
                'id': user['linked_id'] ?? user['id'],
                'student_id': user['linked_id'] ?? user['id'],
                'name': user['name'] ?? 'विद्यार्थी',
                'name_mr': user['name_mr'] ?? user['name'] ?? 'विद्यार्थी',
                'std': user['std'] ?? 6,
                'token': token,
              });
            }
          },
          onNavigationRequest: (req) => NavigationDecision.navigate,
          onWebResourceError: (err) {
            if (err.isForMainFrame ?? true) {
              setState(() {
                _loading = false;
                _error = true;
              });
            }
          },
        ),
      )
      ..loadRequest(uri);
  }

  Future<void> _logout() async {
    await Session.I.clear();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  void _showSiblingSwitcher() {
    final saved = Session.I.savedStudents;
    final activeId = Session.I.user['linked_id'] ?? Session.I.user['id'];

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    '🔄 पाल्य बदला / दुसरे खाते',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: TG.g800),
                  ),
                  IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
                ],
              ),
              const Divider(),
              if (saved.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: Center(child: Text('इतर कोणतीही पाल्य खाती जतन केलेली नाहीत.')),
                )
              else
                ...saved.map((s) {
                  final sId = s['student_id'] ?? s['id'];
                  final isActive = (sId == activeId);
                  final name = s['name_mr'] ?? s['name'] ?? 'पाल्य';
                  final std = s['std'] ?? '-';

                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: isActive ? TG.g50A : Theme.of(ctx).cardColor,
                      border: Border.all(color: isActive ? TG.g600 : const Color(0xFFE2E8F0)),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: ListTile(
                      leading: CircleAvatar(
                        backgroundColor: isActive ? TG.g600 : const Color(0xFFE2E8F0),
                        child: Text(
                          '$std',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: isActive ? Colors.white : const Color(0xFF334155),
                          ),
                        ),
                      ),
                      title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text('इयत्ता: ${formatMarathiStd(std)}'),
                      trailing: isActive
                          ? const Chip(label: Text('सक्रिय', style: TextStyle(color: Colors.green, fontWeight: FontWeight.bold)))
                          : FilledButton.tonal(
                              onPressed: () async {
                                Navigator.pop(ctx);
                                final sToken = s['token'] ?? '';
                                if (sToken.isNotEmpty) {
                                  await Session.I.save(sToken, {
                                    'id': s['user_id'] ?? sId,
                                    'linked_id': sId,
                                    'name': name,
                                    'std': std,
                                    'role': 'student',
                                  }, 'student');
                                  setState(() {
                                    _initWebView();
                                  });
                                }
                              },
                              child: const Text('निवडा'),
                            ),
                    ),
                  );
                }),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(46),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: () {
                  Navigator.pop(ctx);
                  _logout();
                },
                icon: const Icon(Icons.person_add),
                label: const Text('नवीन पाल्य खाते लॉगिन करा'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = Session.I.user;
    final studentName = user['name_mr'] ?? user['name'] ?? 'विद्यार्थी';
    final studentStd = int.tryParse('${user['std'] ?? 6}') ?? 6;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _controller.canGoBack()) {
          await _controller.goBack();
        } else if (context.mounted) {
          final exit = await showDialog<bool>(
            context: context,
            builder: (c) => AlertDialog(
              title: const Text('ॲपमधून बाहेर पडायचे आहे का?'),
              content: const Text('तुम्हाला नक्की बाहेर पडायचे आहे का?'),
              actions: [
                TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('नाही')),
                FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('होय, बाहेर पडा')),
              ],
            ),
          );
          if (exit == true && context.mounted) {
            Navigator.of(context).pop();
          }
        }
      },
      child: Scaffold(
        appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              studentName,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            Text(
              'इयत्ता ${formatMarathiStd(studentStd)} • विद्यार्थी प्रगती कक्ष',
              style: const TextStyle(fontSize: 12, color: Color(0xFFCFE7DC)),
            ),
          ],
        ),
        backgroundColor: TG.g800,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'रिफ्रेश करा',
            onPressed: () => _controller.reload(),
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert),
            tooltip: 'मेनू',
            onSelected: (val) {
              if (val == 'ai_bot') {
                openAiStudyBot(context, std: studentStd, name: studentName, lockStd: true);
              } else if (val == 'theme') {
                ThemeController.toggle(context);
              } else if (val == 'quiz') {
                Navigator.push(context, MaterialPageRoute(builder: (_) => const QuizHomeScreen()));
              } else if (val == 'switch_child') {
                _showSiblingSwitcher();
              } else if (val == 'logout') {
                _logout();
              }
            },
            itemBuilder: (ctx) => [
              const PopupMenuItem(
                value: 'ai_bot',
                child: Row(
                  children: [
                    Icon(Icons.smart_toy, color: TG.g800),
                    SizedBox(width: 10),
                    Text(
                      'AI अभ्यास मित्र',
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'quiz',
                child: Row(
                  children: [
                    Icon(Icons.emoji_events_outlined, color: Color(0xFFD97706)),
                    SizedBox(width: 10),
                    Text('क्विझ खेळ'),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'theme',
                child: Row(
                  children: [
                    Icon(Icons.dark_mode_outlined, color: TG.g600),
                    SizedBox(width: 10),
                    Text('डार्क / लाईट मोड'),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'switch_child',
                child: Row(
                  children: [
                    Icon(Icons.people_alt_outlined, color: TG.g600),
                    SizedBox(width: 10),
                    Text('पाल्य बदला / दुसरे खाते'),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    Icon(Icons.logout, color: Colors.red),
                    SizedBox(width: 10),
                    Text('लॉगआउट', style: TextStyle(color: Colors.red)),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: Stack(
        children: [
          if (!_error)
            WebViewWidget(controller: _controller),
          if (_loading && !_error)
            const Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _PulseLogo(),
                  SizedBox(height: 16),
                  CircularProgressIndicator(color: TG.g600),
                  SizedBox(height: 12),
                  Text('विद्यार्थी कक्ष लोड होत आहे...', style: TextStyle(color: Colors.blueGrey)),
                ],
              ),
            ),
          if (_error)
            Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.wifi_off, size: 54, color: Colors.orange),
                    const SizedBox(height: 16),
                    const Text(
                      'सर्व्हरशी संपर्क होऊ शकला नाही',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'कृपया इंटरनेट कनेक्शन तपासा किंवा रिफ्रेश करा.',
                      style: TextStyle(color: Colors.blueGrey),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 20),
                    FilledButton.icon(
                      style: FilledButton.styleFrom(backgroundColor: TG.g800),
                      onPressed: () => _initWebView(),
                      icon: const Icon(Icons.refresh),
                      label: const Text('पुन्हा लोड करा'),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => openAiStudyBot(context, std: studentStd, name: studentName, lockStd: true),
        backgroundColor: TG.g800,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.smart_toy),
        label: const Text('AI अभ्यास मित्र', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
    ));
  }
}

/// Pulsing school logo shown while the student portal loads.
class _PulseLogo extends StatefulWidget {
  const _PulseLogo();

  @override
  State<_PulseLogo> createState() => _PulseLogoState();
}

class _PulseLogoState extends State<_PulseLogo> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 900))
        ..repeat(reverse: true);
  late final Animation<double> _scale =
      Tween<double>(begin: 0.92, end: 1.05).animate(CurvedAnimation(parent: _c, curve: Curves.easeInOut));

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ScaleTransition(
        scale: _scale,
        child: Container(
          width: 76,
          height: 76,
          padding: const EdgeInsets.all(5),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            boxShadow: [BoxShadow(color: TG.g600.withValues(alpha: .25), blurRadius: 14, offset: const Offset(0, 6))],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(15),
            child: Image.asset('assets/images/logo.png', fit: BoxFit.cover),
          ),
        ),
      );
}
