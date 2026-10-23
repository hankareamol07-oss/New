import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// On-device store. Server rows are mirrored as JSON per table, so the app keeps
/// working offline whatever columns the school's server tables have.
class LocalDb {
  LocalDb._(this.db);
  static LocalDb? _i;
  static LocalDb get I => _i!;
  final Database db;

  static Future<LocalDb> init() async {
    if (_i != null) return _i!;
    final path = p.join(await getDatabasesPath(), 'techguruji.db');
    final db = await openDatabase(path, version: 1, onCreate: (d, _) async {
      await d.execute('CREATE TABLE rows (tbl TEXT NOT NULL, id INTEGER NOT NULL, data TEXT NOT NULL, updated_at TEXT, PRIMARY KEY (tbl, id))');
      await d.execute('CREATE TABLE outbox (seq INTEGER PRIMARY KEY AUTOINCREMENT, tbl TEXT NOT NULL, op TEXT NOT NULL, row_id INTEGER NOT NULL, base_updated_at TEXT, data TEXT, created_at TEXT)');
      await d.execute('CREATE TABLE meta (k TEXT PRIMARY KEY, v TEXT)');
      await d.execute('CREATE TABLE cache (k TEXT PRIMARY KEY, v TEXT, at TEXT)');
    });
    _i = LocalDb._(db);
    return _i!;
  }

  Future<String?> meta(String k) async {
    final r = await db.query('meta', where: 'k = ?', whereArgs: [k]);
    return r.isEmpty ? null : r.first['v'] as String?;
  }

  Future<void> setMeta(String k, String v) =>
      db.insert('meta', {'k': k, 'v': v}, conflictAlgorithm: ConflictAlgorithm.replace);

  Future<void> putCache(String k, Map<String, dynamic> v) => db.insert(
      'cache', {'k': k, 'v': jsonEncode(v), 'at': DateTime.now().toIso8601String()},
      conflictAlgorithm: ConflictAlgorithm.replace);

  Future<Map<String, dynamic>?> getCache(String k) async {
    final r = await db.query('cache', where: 'k = ?', whereArgs: [k]);
    return r.isEmpty ? null : jsonDecode(r.first['v'] as String) as Map<String, dynamic>;
  }

  Future<void> putServerRows(String tbl, List rows, {List<int>? keepIds, bool replaceAll = false}) async {
    await db.transaction((tx) async {
      final pending = (await tx.query('outbox', columns: ['row_id'], where: 'tbl = ?', whereArgs: [tbl]))
          .map((e) => e['row_id'] as int)
          .toSet();
      if (replaceAll) {
        await tx.delete('rows', where: 'tbl = ? AND id > 0', whereArgs: [tbl]);
      } else if (keepIds != null) {
        final keep = keepIds.toSet();
        final local = await tx.query('rows', columns: ['id'], where: 'tbl = ? AND id > 0', whereArgs: [tbl]);
        for (final r in local) {
          final id = r['id'] as int;
          if (!keep.contains(id) && !pending.contains(id)) {
            await tx.delete('rows', where: 'tbl = ? AND id = ?', whereArgs: [tbl, id]);
          }
        }
      }
      final b = tx.batch();
      for (final raw in rows) {
        final row = Map<String, dynamic>.from(raw as Map);
        final id = int.tryParse('${row['id'] ?? ''}');
        if (id == null || pending.contains(id)) continue;
        b.insert('rows', {'tbl': tbl, 'id': id, 'data': jsonEncode(row), 'updated_at': row['updated_at']?.toString()},
            conflictAlgorithm: ConflictAlgorithm.replace);
      }
      await b.commit(noResult: true);
    });
  }

  Future<List<Map<String, dynamic>>> rows(String tbl) async {
    final r = await db.query('rows', where: 'tbl = ?', whereArgs: [tbl], orderBy: 'id');
    return r.map((e) => {...jsonDecode(e['data'] as String) as Map<String, dynamic>, 'id': e['id']}).toList();
  }

  Future<int> count(String tbl) async =>
      Sqflite.firstIntValue(await db.rawQuery('SELECT COUNT(*) FROM rows WHERE tbl = ?', [tbl])) ?? 0;

  Future<int> pendingCount() async => Sqflite.firstIntValue(await db.rawQuery('SELECT COUNT(*) FROM outbox')) ?? 0;

  /// Save a row locally and queue it for the next sync. New rows get negative ids.
  Future<int> saveRow(String tbl, Map<String, dynamic> row) async {
    return db.transaction((tx) async {
      var id = int.tryParse('${row['id'] ?? ''}') ?? 0;
      String? base;
      if (id == 0) {
        final min = Sqflite.firstIntValue(await tx.rawQuery('SELECT MIN(id) FROM rows WHERE tbl = ?', [tbl])) ?? 0;
        id = (min < 0 ? min : 0) - 1;
      } else {
        final cur = await tx.query('rows', where: 'tbl = ? AND id = ?', whereArgs: [tbl, id]);
        if (cur.isNotEmpty) base = cur.first['updated_at'] as String?;
      }
      final data = {...row, 'id': id};
      await tx.insert('rows', {'tbl': tbl, 'id': id, 'data': jsonEncode(data), 'updated_at': base},
          conflictAlgorithm: ConflictAlgorithm.replace);
      await tx.delete('outbox', where: 'tbl = ? AND row_id = ? AND op = ?', whereArgs: [tbl, id, 'upsert']);
      await tx.insert('outbox', {
        'tbl': tbl,
        'op': 'upsert',
        'row_id': id,
        'base_updated_at': base,
        'data': jsonEncode(data),
        'created_at': DateTime.now().toIso8601String(),
      });
      return id;
    });
  }

  Future<List<Map<String, dynamic>>> outbox() => db.query('outbox', orderBy: 'seq');

  Future<void> markPushed(int seq, String tbl, int localId, int serverId, String? updatedAt) async {
    await db.transaction((tx) async {
      await tx.delete('outbox', where: 'seq = ?', whereArgs: [seq]);
      final cur = await tx.query('rows', where: 'tbl = ? AND id = ?', whereArgs: [tbl, localId]);
      if (cur.isEmpty) return;
      final data = {...jsonDecode(cur.first['data'] as String) as Map<String, dynamic>, 'id': serverId};
      if (updatedAt != null && data.containsKey('updated_at')) data['updated_at'] = updatedAt;
      await tx.delete('rows', where: 'tbl = ? AND id = ?', whereArgs: [tbl, localId]);
      await tx.insert('rows', {'tbl': tbl, 'id': serverId, 'data': jsonEncode(data), 'updated_at': updatedAt},
          conflictAlgorithm: ConflictAlgorithm.replace);
    });
  }

  Future<void> dropOutbox(int seq) => db.delete('outbox', where: 'seq = ?', whereArgs: [seq]);

  Future<void> wipe() async {
    await db.delete('rows');
    await db.delete('outbox');
    await db.delete('meta');
    await db.delete('cache');
  }
}
