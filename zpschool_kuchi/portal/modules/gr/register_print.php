<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
$user = require_login();
$stmt = db()->prepare('SELECT s.*,c.name class_name,c.section FROM students s LEFT JOIN classes c ON c.id=s.class_id AND c.school_id=s.school_id WHERE s.id=? AND s.school_id=?');
$stmt->execute([(int) ($_GET['id'] ?? 0), (int) $user['school_id']]);
$student = $stmt->fetch();
if (!$student) { http_response_code(404); exit('सापडला नाही'); }
print_start('विद्यार्थी नोंदणी प्रमाणपत्र');
?>
<h1>विद्यार्थी नोंदणी प्रमाणपत्र</h1>
<table><tr><th>विद्यार्थ्याचे नाव</th><td><?= e(trim($student['first_name'] . ' ' . $student['father_name'] . ' ' . $student['surname'])) ?></td><th>GR क्रमांक</th><td><?= e((string) $student['gr_no']) ?></td></tr><tr><th>इयत्ता/तुकडी</th><td><?= e(($student['class_name'] ?? '') . ' / ' . ($student['section'] ?? '')) ?></td><th>जन्मतारीख अक्षरी</th><td><?= e(marathi_date_words((string) $student['dob'])) ?></td></tr><tr><th>पत्ता</th><td colspan="3"><?= e((string) $student['address']) ?></td></tr></table>
<?php print_signature_row(['वर्गशिक्षक', 'मुख्याध्यापक']); print_end(); ?>
