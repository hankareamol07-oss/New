import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart' show kIsWeb;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:pdf/pdf.dart';
import 'package:printing/printing.dart';

import 'api.dart';
import 'local_db.dart';
import 'session.dart';

/// Online-first API reads with an offline copy in the local cache table.
class Remote {
  static String _key(String path, Map<String, String> q) =>
      '$path?${(q.keys.toList()..sort()).map((k) => '$k=${q[k]}').join('&')}';

  /// Returns fresh data when online; falls back to the last saved copy when offline.
  static Future<Map<String, dynamic>> get(String path, Map<String, String> q) async {
    final k = _key(path, q);
    try {
      final j = await Api.get(path, q);
      await LocalDb.I.putCache(k, j);
      return j;
    } on ApiException catch (e) {
      final c = await LocalDb.I.getCache(k);
      if (c != null) return {...c, '_offline': true};
      throw ApiException(e.message, e.code);
    }
  }

  static Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) => Api.post(path, body);

  /// Saves now when online; when offline keeps the change on the phone and sends it on the next Sync.
  /// Returns true when saved on the server, false when queued.
  static Future<bool> save(String path, Map<String, dynamic> body) async {
    try {
      await Api.post(path, body);
      return true;
    } on ApiException catch (e) {
      if (e.code != 0) rethrow;
      final q = await queue();
      q.add({'path': path, 'body': body, 'at': DateTime.now().toIso8601String()});
      await LocalDb.I.setMeta('api_queue', jsonEncode(q));
      return false;
    }
  }

  static Future<List<Map<String, dynamic>>> queue() async {
    final raw = await LocalDb.I.meta('api_queue');
    if (raw == null || raw.isEmpty) return [];
    return (jsonDecode(raw) as List).cast<Map<String, dynamic>>();
  }

  /// Sends queued offline changes in order. Stops at the first network failure.
  static Future<int> flushQueue() async {
    final q = await queue();
    var sent = 0;
    while (q.isNotEmpty) {
      final item = q.first;
      try {
        await Api.post(item['path'] as String, (item['body'] as Map).cast<String, dynamic>());
      } on ApiException catch (e) {
        if (e.code == 0) break;
      }
      q.removeAt(0);
      sent++;
      await LocalDb.I.setMeta('api_queue', jsonEncode(q));
    }
    return sent;
  }

  /// Downloads the portal's own print page (same layout as the website) as HTML.
  static const _printChannel = MethodChannel('techguruji/print');

  static Future<String> reportHtml(String page, Map<String, dynamic> params) async {
    final rep = await fetchReport(page, params);
    return rep.html;
  }

  static Future<ReportResult> fetchReport(String page, Map<String, dynamic> params) async {
    final q = <String, String>{'page': page, for (final e in params.entries) e.key: '${e.value}'};
    final uri = Uri.parse('${Session.I.baseUrl}/api/report.php').replace(queryParameters: q);
    final r = await http.get(uri, headers: {
      'Accept': 'text/html,application/xhtml+xml,application/json',
      'X-App-Platform': 'Android',
      'X-App-Version': '2.0.0',
      'X-App-Signature': 'TechGuruji-Secure-App-2026',
      'User-Agent': 'TechGurujiSmartSchool/2.0 (Android; Flutter)',
      if (Session.I.token != null) 'Authorization': 'Bearer ${Session.I.token}',
    }).timeout(const Duration(seconds: 90));
    final body = utf8.decode(r.bodyBytes);
    if (r.statusCode >= 400 || body.trimLeft().startsWith('{')) {
      String msg = 'अहवाल तयार झाला नाही';
      try {
        msg = (jsonDecode(body)['message'] ?? msg).toString();
      } catch (_) {}
      throw ApiException(msg, r.statusCode);
    }

    String? headerOrientation = r.headers['x-print-orientation'];
    String? headerPaperSize = r.headers['x-print-paper-size'];

    bool isLandscape = false;
    if (headerOrientation != null) {
      isLandscape = headerOrientation.toLowerCase() == 'landscape';
    } else {
      final metaMatch = RegExp(r'''<meta\s+name=["']print-orientation["']\s+content=["']([^"']+)["']''', caseSensitive: false).firstMatch(body);
      if (metaMatch != null) {
        isLandscape = metaMatch.group(1)?.toLowerCase() == 'landscape';
      } else {
        isLandscape = RegExp(r'@page\s*\{[^}]*landscape', caseSensitive: false).hasMatch(body);
      }
    }

    String paperSize = 'A4';
    if (headerPaperSize != null && headerPaperSize.isNotEmpty) {
      paperSize = headerPaperSize.toUpperCase();
    } else {
      final metaSizeMatch = RegExp(r'''<meta\s+name=["']print-paper-size["']\s+content=["']([^"']+)["']''', caseSensitive: false).firstMatch(body);
      if (metaSizeMatch != null) {
        paperSize = (metaSizeMatch.group(1) ?? 'A4').toUpperCase();
      } else if (RegExp(r'@page\s*\{[^}]*size:\s*A5', caseSensitive: false).hasMatch(body) || (params['size']?.toString().toLowerCase() == 'a5')) {
        paperSize = 'A5';
      } else if (RegExp(r'@page\s*\{[^}]*size:\s*A3', caseSensitive: false).hasMatch(body)) {
        paperSize = 'A3';
      }
    }

    return ReportResult(html: body, isLandscape: isLandscape, paperSize: paperSize);
  }

  /// Opens the Android print / save-as-PDF / share sheet for a portal report.
  /// Print an HTML document built on the phone (works offline).
  static Future<void> printHtml(BuildContext context, String html,
      {String name = 'TechGuruji', bool landscape = false}) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      if (!kIsWeb && Platform.isAndroid) {
        await _printChannel.invokeMethod('printHtml', {
          'html': html,
          'baseUrl': Session.I.baseUrl,
          'name': name,
          'landscape': landscape,
          'paperSize': 'A4',
        });
      } else {
        final f = landscape ? PdfPageFormat.a4.landscape : PdfPageFormat.a4;
        await Printing.layoutPdf(
            name: name, format: f, onLayout: (fmt) => Printing.convertHtml(format: fmt, html: html));
      }
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red.shade700));
    }
  }

  static Future<void> printReport(BuildContext context, String page, Map<String, dynamic> params,
      {String name = 'TechGuruji'}) async {
    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(const SnackBar(content: Text('PDF तयार होत आहे…'), duration: Duration(seconds: 2)));
    try {
      final rep = await fetchReport(page, params);
      final html = rep.html
          .replaceFirst(RegExp('<head>', caseSensitive: false), '<head><script>window.print=function(){};</script>');
      if (!kIsWeb && Platform.isAndroid) {
        await _printChannel.invokeMethod('printHtml', {
          'html': html,
          'baseUrl': Session.I.baseUrl,
          'name': name,
          'landscape': rep.isLandscape,
          'paperSize': rep.paperSize,
        });
      } else {
        PdfPageFormat format = rep.isLandscape ? PdfPageFormat.a4.landscape : PdfPageFormat.a4;
        if (rep.paperSize == 'A5') {
          format = rep.isLandscape ? PdfPageFormat.a5.landscape : PdfPageFormat.a5;
        } else if (rep.paperSize == 'A3') {
          format = rep.isLandscape ? PdfPageFormat.a3.landscape : PdfPageFormat.a3;
        }
        await Printing.layoutPdf(
          name: name,
          format: format,
          onLayout: (fmt) => Printing.convertHtml(format: fmt, html: html, baseUrl: Session.I.baseUrl),
        );
      }
    } catch (e) {
      messenger.showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red.shade700));
    }
  }
}

class ReportResult {
  final String html;
  final bool isLandscape;
  final String paperSize;
  const ReportResult({required this.html, required this.isLandscape, required this.paperSize});
}
