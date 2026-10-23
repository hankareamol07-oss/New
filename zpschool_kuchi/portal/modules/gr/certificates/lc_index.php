<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/module_helpers.php';
$user = require_role(['admin','principal','clerk']);
$stmt = db()->prepare('SELECT s.*,c.name class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id AND c.school_id=s.school_id WHERE s.school_id=? ORDER BY s.gr_no');
$stmt->execute([(int) $user['school_id']]);
layout_start('दाखले', 'gr');
?>
<div class="bg-white border rounded-2xl p-6"><h2 class="text-2xl font-bold text-navy">दाखले</h2><p class="text-slate-500 mt-1">शाळा सोडल्याचा दाखला तयार करा.</p><div class="table-wrap mt-6"><table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-3">GR क्र.</th><th class="p-3">विद्यार्थ्याचे नाव</th><th class="p-3">वर्ग</th><th class="p-3">कृती</th></tr></thead><tbody><?php foreach ($stmt as $student): ?><tr class="border-b"><td class="p-3"><?= e($student['gr_no']) ?></td><td class="p-3"><?= e(student_full_name($student)) ?></td><td class="p-3"><?= e($student['class_name'] ?? '') ?></td><td class="p-3"><a class="text-teal font-bold" href="/modules/gr/certificates.php?id=<?= $student['id'] ?>&type=leaving">LC उघडा</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php layout_end(); ?>
