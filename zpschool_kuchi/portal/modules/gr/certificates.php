<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/print_layout.php';

$user = require_role(['admin', 'principal', 'clerk']);
$sid = (int) $user['school_id'];
$id = (int) ($_GET['id'] ?? 0);
if ($id) {
    $stmt = db()->prepare('SELECT s.*,c.name class_name,c.section FROM students s LEFT JOIN classes c ON c.id=s.class_id AND c.school_id=s.school_id WHERE s.id=? AND s.school_id=?');
    $stmt->execute([$id, $sid]);
    $student = $stmt->fetch();
    if (!$student) { http_response_code(404); exit('विद्यार्थी सापडला नाही'); }
    $type = (string) ($_GET['type'] ?? 'bonafide');
    $titles = ['bonafide' => 'बोनाफाईड प्रमाणपत्र', 'leaving' => 'शाळा सोडल्याचा दाखला', 'birth' => 'जन्म दाखला'];
    print_start($titles[$type] ?? $titles['bonafide']);
    ?>
    <h1><?= e($titles[$type] ?? $titles['bonafide']) ?></h1>
    <p>याद्वारे प्रमाणित करण्यात येते की <strong><?= e(trim($student['first_name'] . ' ' . $student['father_name'] . ' ' . $student['surname'])) ?></strong> हा/ही <?= e($student['class_name'] ?? '') ?> मध्ये शिकत आहे/होता.</p>
    <table><tr><th>GR क्रमांक</th><td><?= e((string) $student['gr_no']) ?></td><th>हजेरी क्रमांक</th><td><?= e((string) $student['roll_no']) ?></td></tr><tr><th>इयत्ता/तुकडी</th><td><?= e(($student['class_name'] ?? '') . ' / ' . ($student['section'] ?? '')) ?></td><th>जन्मतारीख अक्षरी</th><td><?= e(marathi_date_words((string) $student['dob'])) ?></td></tr><tr><th>पत्ता</th><td colspan="3"><?= e((string) $student['address']) ?></td></tr></table>
    <?php print_signature_row(['मुख्याध्यापक', 'कार्यालयीन शिक्का']); print_end(); exit;
}
$students = school_students($sid);
layout_start('दाखले व प्रमाणपत्रे', 'gr');
?>
<div class="bg-white border rounded-2xl p-6"><p class="text-slate-500 mb-5">विद्यार्थी निवडा आणि बोनाफाईड/शाळा सोडल्याचा दाखला प्रिंट करा.</p><div class="grid sm:grid-cols-2 gap-3"><?php foreach ($students as $s): ?><a class="border rounded-xl p-4 hover:border-teal" href="/modules/gr/certificates.php?id=<?= $s['id'] ?>&type=leaving"><strong class="text-navy"><?= e(trim($s['first_name'] . ' ' . $s['surname'])) ?></strong><span class="block text-sm text-slate-500">GR <?= $s['gr_no'] ?> · प्रिंट →</span></a><?php endforeach; ?></div></div>
<?php layout_end(); ?>
