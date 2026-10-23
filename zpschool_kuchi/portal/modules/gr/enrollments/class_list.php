<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/module_helpers.php';

$user = require_role(['admin','principal','clerk','teacher']);
$sid = (int) $user['school_id'];
$pdo = db();
$yearId = (int) ($_GET['year_id'] ?? current_year_id($sid));
$yearStmt = getMainDB()->prepare('SELECT * FROM academic_years WHERE id=? AND school_id=?');
$yearStmt->execute([$yearId, $sid]);
$year = $yearStmt->fetch() ?: current_academic_year($sid);
$yearId = (int) ($year['id'] ?? 0);
$classes = $pdo->prepare('SELECT c.* FROM classes c WHERE c.school_id=? ORDER BY c.std,c.section');
$classes->execute([$sid]);
$classes = class_teacher_names($classes->fetchAll(), $sid);
$enrolledStmt = getMainDB()->prepare('SELECT class_id,COUNT(*) n FROM student_enrollments WHERE academic_year_id=? AND status="enrolled" GROUP BY class_id');
$enrolledStmt->execute([$yearId]);
$enrolledCounts = array_column($enrolledStmt->fetchAll(PDO::FETCH_ASSOC), 'n', 'class_id');
foreach ($classes as &$classRow) {
    $classRow['enrolled_count'] = (int) ($enrolledCounts[$classRow['id']] ?? 0);
}
unset($classRow);
$years = getMainDB()->prepare('SELECT * FROM academic_years WHERE school_id=? ORDER BY id DESC');
$years->execute([$sid]);
layout_start('वर्ग यादी', 'gr');
?>
<div class="bg-white border rounded-2xl p-6"><div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-2xl font-bold text-navy">वर्ग यादी</h2><p class="text-slate-500 mt-1">प्रत्येक शैक्षणिक वर्षातील विद्यार्थी नोंदणी.</p></div><form><select name="year_id" onchange="this.form.submit()"><?php foreach ($years as $academic): ?><option value="<?= $academic['id'] ?>" <?= (int) $academic['id'] === $yearId ? 'selected' : '' ?>><?= e($academic['label']) ?></option><?php endforeach; ?></select></form></div><div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6"><?php foreach ($classes as $class): ?><a href="/modules/gr/enrollments/class_list.php?year_id=<?= $yearId ?>&class_id=<?= $class['id'] ?>" class="border rounded-xl p-5 hover:border-teal"><strong class="text-navy"><?= e($class['name']) ?></strong><p class="text-sm text-slate-500 mt-2"><?= e($class['enrolled_count']) ?> विद्यार्थी</p><p class="text-xs text-slate-500 mt-1"><?= e($class['teacher_name'] ?: 'वर्गशिक्षक नाही') ?></p></a><?php endforeach; ?></div></div>
<?php layout_end(); ?>
