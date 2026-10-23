<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/auth.php';
$user = require_role(['admin', 'principal', 'clerk', 'teacher']);
$sid = (int) $user['school_id'];
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
$stmt = db()->prepare('SELECT s.first_name,s.surname,s.gr_no,COUNT(a.id) total,SUM(a.status="P") present,SUM(a.status="A") absent FROM students s LEFT JOIN daily_attendance a ON a.student_id=s.id AND a.school_id=s.school_id AND a.date BETWEEN ? AND ? WHERE s.school_id=? GROUP BY s.id ORDER BY s.gr_no');
$stmt->execute([$from, $to, $sid]);
print_start('उपस्थिती अहवाल');
?>
<h1>उपस्थिती अहवाल</h1><p>कालावधी: <?= e($from) ?> ते <?= e($to) ?></p>
<table><thead><tr><th>GR</th><th>विद्यार्थी</th><th>एकूण</th><th>उपस्थित</th><th>अनुपस्थित</th></tr></thead><tbody><?php foreach ($stmt as $r): ?><tr><td><?= e((string) $r['gr_no']) ?></td><td><?= e(trim($r['first_name'] . ' ' . $r['surname'])) ?></td><td><?= e((string) $r['total']) ?></td><td><?= e((string) $r['present']) ?></td><td><?= e((string) $r['absent']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php print_signature_row(['वर्गशिक्षक', 'मुख्याध्यापक']); print_end(); ?>
