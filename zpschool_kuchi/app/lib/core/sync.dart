import 'dart:convert';

import 'package:flutter/foundation.dart';

import 'api.dart';
import 'local_db.dart';
import 'remote.dart';

class SyncResult {
  SyncResult(this.pushed, this.pulled, this.conflicts, this.failed);
  final int pushed, pulled, conflicts, failed;
}

/// User-controlled two-way sync: push queued local edits, then pull server changes.
class SyncService extends ChangeNotifier {
  SyncService._();
  static final SyncService I = SyncService._();

  bool busy = false;
  String? lastSync;
  String? error;
  int pending = 0;

  static const tables = [
    'school_info', 'classes', 'teachers', 'students', 'attendance', 'holidays', 'calendar_events',
    'timetable', 'lesson_plans', 'cce_subjects', 'cce_marks', 'mdm_daily', 'mdm_bmi',
  ];

  Future<void> refreshState() async {
    lastSync = await LocalDb.I.meta('last_sync_at');
    pending = await LocalDb.I.pendingCount() + (await Remote.queue()).length;
    notifyListeners();
  }

  Future<SyncResult?> run() async {
    if (busy) return null;
    busy = true;
    error = null;
    notifyListeners();
    var pushed = 0, pulled = 0, conflicts = 0, failed = 0;
    try {
      await Remote.flushQueue();
      final box = await LocalDb.I.outbox();
      for (var i = 0; i < box.length; i += 200) {
        final chunk = box.sublist(i, i + 200 > box.length ? box.length : i + 200);
        final changes = chunk.map((o) {
          final row = Map<String, dynamic>.from(jsonDecode(o['data'] as String) as Map);
          final id = o['row_id'] as int;
          return {
            'table': o['tbl'],
            'op': o['op'],
            'id': id > 0 ? id : 0,
            'client_id': o['seq'],
            'base_updated_at': o['base_updated_at'],
            'row': row..remove('id'),
          };
        }).toList();
        final res = await Api.post('app_sync.php', {'action': 'push', 'changes': changes});
        for (final r in (res['results'] as List)) {
          final seq = r['client_id'] as int;
          final o = chunk.firstWhere((e) => e['seq'] == seq);
          if (r['ok'] == true) {
            await LocalDb.I.markPushed(seq, o['tbl'] as String, o['row_id'] as int, r['id'] as int, r['updated_at'] as String?);
            pushed++;
          } else if (r['conflict'] == true) {
            await LocalDb.I.dropOutbox(seq);
            conflicts++;
          } else {
            failed++;
          }
        }
      }

      final since = await LocalDb.I.meta('server_time');
      final q = {'action': 'pull', 'tables': tables.join(',')};
      if (since != null) q['since'] = since;
      final res = await Api.get('app_sync.php', q);
      final data = Map<String, dynamic>.from(res['data'] as Map);
      for (final e in data.entries) {
        final t = Map<String, dynamic>.from(e.value as Map);
        final rows = t['rows'] as List;
        final ids = (t['all_ids'] as List?)?.map((x) => x as int).toList();
        await LocalDb.I.putServerRows(e.key, rows, keepIds: ids, replaceAll: since == null || t['full'] == true);
        pulled += rows.length;
        var offset = rows.length;
        var more = t['has_more'] == true;
        while (more) {
          final page = await Api.get('app_sync.php', {...q, 'tables': e.key, 'offset': '$offset'});
          final pt = Map<String, dynamic>.from((page['data'] as Map)[e.key] as Map);
          final pr = pt['rows'] as List;
          await LocalDb.I.putServerRows(e.key, pr);
          pulled += pr.length;
          offset += pr.length;
          more = pt['has_more'] == true && pr.isNotEmpty;
        }
      }
      await LocalDb.I.setMeta('server_time', res['server_time'] as String);
      await LocalDb.I.setMeta('last_sync_at', DateTime.now().toIso8601String());
      return SyncResult(pushed, pulled, conflicts, failed);
    } catch (e) {
      error = e.toString();
      return null;
    } finally {
      busy = false;
      await refreshState();
    }
  }
}
