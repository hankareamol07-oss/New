// Browser entry point used only for testing the app with Flutter web.
import 'package:sqflite/sqflite.dart';
import 'package:sqflite_common_ffi_web/sqflite_ffi_web.dart';

import 'main.dart' as app;

Future<void> main() async {
  databaseFactory = databaseFactoryFfiWeb;
  await app.main();
}
