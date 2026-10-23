import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'session.dart';

class ApiException implements Exception {
  ApiException(this.message, [this.code = 0]);
  final String message;
  final int code;
  @override
  String toString() => message;
}

class Api {
  static Uri _uri(String path, [Map<String, String>? q]) =>
      Uri.parse('${Session.I.baseUrl}/api/$path').replace(queryParameters: q);

  static Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-App-Platform': 'Android',
        'X-App-Version': '2.0.0',
        'X-App-Signature': 'TechGuruji-Secure-App-2026',
        'X-Requested-With': 'com.techguruji.smartschool',
        'User-Agent': 'TechGurujiSmartSchool/2.0 (Android; Flutter)',
        if (Session.I.token != null) 'Authorization': 'Bearer ${Session.I.token}',
      };

  static Future<Map<String, dynamic>> get(String path, [Map<String, String>? q]) =>
      _send(() => http.get(_uri(path, q), headers: _headers));

  static Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) =>
      _send(() => http.post(_uri(path), headers: _headers, body: jsonEncode(body)));

  static Future<Map<String, dynamic>> _send(Future<http.Response> Function() fn) async {
    http.Response r;
    try {
      r = await fn().timeout(const Duration(seconds: 45));
    } on SocketException {
      throw ApiException('इंटरनेट कनेक्शन नाही');
    } on TimeoutException {
      throw ApiException('सर्व्हर प्रतिसाद देत नाही');
    } on http.ClientException {
      throw ApiException('इंटरनेट कनेक्शन नाही');
    }
    Map<String, dynamic> j;
    try {
      j = jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException('सर्व्हरकडून अवैध प्रतिसाद (${r.statusCode})', r.statusCode);
    }
    if (r.statusCode >= 400 || j['success'] != true) {
      throw ApiException((j['message'] ?? 'त्रुटी').toString(), r.statusCode);
    }
    return j;
  }
}
