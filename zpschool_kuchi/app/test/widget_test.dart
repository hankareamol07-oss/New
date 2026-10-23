import 'package:flutter_test/flutter_test.dart';
import 'package:techguruji_app/screens/students.dart';

void main() {
  test('studentName prefers Marathi name', () {
    expect(studentName({'name': 'Amol', 'name_mr': 'अमोल'}), 'अमोल');
    expect(studentName({'name': 'Amol', 'name_mr': ''}), 'Amol');
  });
}
