import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../core/session.dart';

/// Marathi standard ordinal formatter:
/// १ ली, २ री, ३ री, ४ थी, ५ वी, ६ वी, ७ वी, ८ वी
String formatMarathiStd(dynamic std) {
  final n = int.tryParse('$std') ?? 0;
  switch (n) {
    case 1:
      return '१ ली';
    case 2:
      return '२ री';
    case 3:
      return '३ री';
    case 4:
      return '४ थी';
    case 5:
      return '५ वी';
    case 6:
      return '६ वी';
    case 7:
      return '७ वी';
    case 8:
      return '८ वी';
    default:
      return '$std वी';
  }
}

/// Opens AI अभ्यास मित्र in a full-screen or modal sheet
void openAiStudyBot(BuildContext context, {int? std, String? name, String? subject, bool? lockStd}) {
  final user = Session.I.user;
  final isStudentRole = Session.I.role == 'student';
  final effectiveStd = std ?? (user['std'] != null ? int.tryParse(user['std'].toString()) : null) ?? 6;
  final effectiveName = name ?? (user['name_mr'] ?? user['name'] ?? 'विद्यार्थी');
  final shouldLock = lockStd ?? isStudentRole;

  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => AiStudyBotScreen(
        std: effectiveStd,
        name: effectiveName.toString(),
        subject: subject,
        lockStd: shouldLock,
      ),
    ),
  );
}

class AiStudyBotScreen extends StatefulWidget {
  const AiStudyBotScreen({
    super.key,
    this.std = 6,
    this.name = 'विद्यार्थी',
    this.subject,
    this.lockStd = true,
  });

  final int std;
  final String name;
  final String? subject;
  final bool lockStd;

  @override
  State<AiStudyBotScreen> createState() => _AiStudyBotScreenState();
}

class _AiStudyBotScreenState extends State<AiStudyBotScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _error = false;

  @override
  void initState() {
    super.initState();
    _initWebView();
  }

  void _initWebView() {
    final baseUrl = Session.I.baseUrl;
    final uri = Uri.parse('$baseUrl/bot/index.php').replace(queryParameters: {
      'std': widget.std.toString(),
      'name': widget.name,
      if (widget.subject != null && widget.subject!.isNotEmpty) 'subject': widget.subject,
      'in_app': '1',
      if (widget.lockStd) 'lock_std': '1',
      if (Session.I.token != null) 'token': Session.I.token!,
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
          onPageFinished: (_) {
            if (mounted) setState(() => _loading = false);
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

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _controller.canGoBack()) {
          await _controller.goBack();
        } else if (context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.smart_toy, size: 20),
                SizedBox(width: 8),
                Text(
                  'AI अभ्यास मित्र',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
                ),
              ],
            ),
            Text(
              'इयत्ता ${formatMarathiStd(widget.std)} • सर्व विषय व सराव',
              style: const TextStyle(fontSize: 11, color: Color(0xFFCFE7DC)),
            ),
          ],
        ),
        backgroundColor: const Color(0xFF1E3A8A),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'रिफ्रेश करा',
            onPressed: () => _controller.reload(),
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
                  CircularProgressIndicator(color: Color(0xFF1E3A8A)),
                  SizedBox(height: 12),
                  Text('AI अभ्यास मित्र लोड होत आहे...', style: TextStyle(color: Colors.blueGrey)),
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
                    const Icon(Icons.cloud_off, size: 54, color: Colors.orange),
                    const SizedBox(height: 16),
                    const Text(
                      'AI अभ्यास मित्र शी संपर्क होऊ शकला नाही',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'कृपया आपले इंटरनेट कनेक्शन तपासा आणि पुन्हा प्रयत्न करा.',
                      style: TextStyle(color: Colors.blueGrey),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 20),
                    FilledButton.icon(
                      style: FilledButton.styleFrom(backgroundColor: const Color(0xFF1E3A8A)),
                      onPressed: () => _initWebView(),
                      icon: const Icon(Icons.refresh),
                      label: const Text('पुन्हा प्रयत्न करा'),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    ));
  }
}
