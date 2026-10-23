import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../core/config.dart';
import '../core/session.dart';

class InAppWebScreen extends StatefulWidget {
  const InAppWebScreen({super.key, required this.url, this.title});
  final String url;
  final String? title;

  @override
  State<InAppWebScreen> createState() => _InAppWebScreenState();
}

class _InAppWebScreenState extends State<InAppWebScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  double _progress = 0;
  String _pageTitle = '';

  @override
  void initState() {
    super.initState();
    _pageTitle = widget.title ?? kAppName;
    final fullUrl = widget.url.startsWith('http')
        ? widget.url
        : '${Session.I.baseUrl}${widget.url.startsWith('/') ? '' : '/'}${widget.url}';

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (p) {
            if (mounted) setState(() => _progress = p / 100.0);
          },
          onPageStarted: (_) {
            if (mounted) setState(() => _loading = true);
          },
          onPageFinished: (url) async {
            if (mounted) setState(() => _loading = false);
            // Rewrite target="_blank" and window.open to keep all browsing inside the webview
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
            final t = await _controller.getTitle();
            if (t != null && t.isNotEmpty && widget.title == null && mounted) {
              setState(() => _pageTitle = t);
            }
          },
          onNavigationRequest: (req) => NavigationDecision.navigate,
        ),
      )
      ..loadRequest(Uri.parse(fullUrl));
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
          title: Text(
            _pageTitle,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          backgroundColor: const Color(0xFF1E3A8A),
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh),
              tooltip: 'रिफ्रेश',
              onPressed: () => _controller.reload(),
            ),
          ],
          bottom: _loading
              ? PreferredSize(
                  preferredSize: const Size.fromHeight(3),
                  child: LinearProgressIndicator(
                    value: _progress > 0 && _progress < 1 ? _progress : null,
                    backgroundColor: Colors.white24,
                    valueColor: const AlwaysStoppedAnimation<Color>(Colors.amber),
                  ),
                )
              : null,
        ),
        body: WebViewWidget(controller: _controller),
      ),
    );
  }
}
