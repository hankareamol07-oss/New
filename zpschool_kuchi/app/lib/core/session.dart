import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'config.dart';

class Session {
  Session._(this._prefs);
  static Session? _i;
  static Session get I => _i!;
  final SharedPreferences _prefs;

  static Future<Session> init() async {
    _i ??= Session._(await SharedPreferences.getInstance());
    return _i!;
  }

  String get baseUrl {
    final v = _prefs.getString('base_url');
    if (v == null || v.trim().isEmpty || v.contains('localhost') || v.contains('127.0.0.1') || v.contains('10.0.2.2')) {
      return kDefaultBaseUrl;
    }
    return v.trim();
  }
  set baseUrl(String v) => _prefs.setString('base_url', v.replaceAll(RegExp(r'/+$'), ''));

  String? get token => _prefs.getString('token');
  bool get loggedIn => (token ?? '').isNotEmpty;

  String get role => _prefs.getString('role') ?? 'teacher';
  set role(String r) => _prefs.setString('role', r);

  Map<String, dynamic> get user =>
      jsonDecode(_prefs.getString('user') ?? '{}') as Map<String, dynamic>;

  Map<String, dynamic> get school =>
      jsonDecode(_prefs.getString('school') ?? '{"name": "$kAppName", "udise": "$kSchoolUdise"}') as Map<String, dynamic>;

  bool get privacyAccepted => _prefs.getBool('privacy_ok') ?? true;
  set privacyAccepted(bool v) => _prefs.setBool('privacy_ok', v);

  bool get autoSync => _prefs.getBool('auto_sync') ?? false;
  set autoSync(bool v) => _prefs.setBool('auto_sync', v);

  /// 'system' | 'light' | 'dark'
  String get themeModeName => _prefs.getString('theme_mode') ?? 'system';
  Future<void> setThemeModeName(String v) => _prefs.setString('theme_mode', v);

  Future<void> save(String token, Map<String, dynamic> user, String role, [Map<String, dynamic>? school]) async {
    await _prefs.setString('token', token);
    await _prefs.setString('user', jsonEncode(user));
    await _prefs.setString('role', role);
    if (school != null) {
      await _prefs.setString('school', jsonEncode(school));
    }
  }

  Future<void> updateSchool(Map<String, dynamic> patch) async {
    await _prefs.setString('school', jsonEncode({...school, ...patch}));
  }

  List<Map<String, dynamic>> get savedStudents {
    final raw = _prefs.getString('kuchi_saved_students');
    if (raw == null || raw.isEmpty) return [];
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> addSavedStudent(Map<String, dynamic> student) async {
    final list = savedStudents;
    final id = student['student_id'] ?? student['id'];
    list.removeWhere((x) => (x['student_id'] ?? x['id']) == id);
    list.add(student);
    await _prefs.setString('kuchi_saved_students', jsonEncode(list));
  }

  Future<void> removeSavedStudent(int studentId) async {
    final list = savedStudents;
    list.removeWhere((x) => (x['student_id'] ?? x['id']) == studentId);
    await _prefs.setString('kuchi_saved_students', jsonEncode(list));
  }

  Future<void> clear() async {
    await _prefs.remove('token');
    await _prefs.remove('user');
    await _prefs.remove('role');
    await _prefs.remove('school');
  }
}
