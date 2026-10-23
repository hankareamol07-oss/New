<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/module_helpers.php';

$user = require_role(['admin','principal','clerk']);
$sid = (int) $user['school_id'];
$pdo = db();
$q = trim((string) ($_GET['q'] ?? ''));
$classId = (int) ($_GET['class_id'] ?? 0);
$category = trim((string) ($_GET['category'] ?? ''));
$sql = 'SELECT s.*,c.name class_name,c.std,c.section FROM students s LEFT JOIN classes c ON c.id=s.class_id AND c.school_id=s.school_id WHERE s.school_id=?';
$args = [$sid];
if ($q !== '') {
    $sql .= ' AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.surname LIKE ? OR s.father_name LIKE ? OR CAST(s.gr_no AS TEXT) LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($classId) {
    $sql .= ' AND s.class_id=?';
    $args[] = $classId;
}
if ($category !== '') {
    $sql .= ' AND s.caste_category=?';
    $args[] = $category;
}
$sql .= ' ORDER BY s.book_no,s.gr_no';
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$students = $stmt->fetchAll();
$classes = school_classes($sid);
$years = getMainDB()->prepare('SELECT * FROM academic_years WHERE school_id=? ORDER BY id DESC');
$years->execute([$sid]);
layout_start('जनरल रजिस्टर', 'gr');
?>
<div class="flex flex-wrap gap-3 mb-5"><a class="bg-gold text-navy rounded-xl px-4 py-3 font-bold" href="/modules/gr/students/new.php">+ विद्यार्थी नोंदणी</a><a class="bg-white border rounded-xl px-4 py-3 font-bold text-navy" href="/modules/gr/students/dakhal.php">दाखल</a><a class="bg-white border rounded-xl px-4 py-3 font-bold text-navy" href="/modules/gr/students/import.php">आयात</a><a class="bg-white border rounded-xl px-4 py-3 font-bold text-navy" href="/modules/gr/certificates/lc_index.php">दाखले</a></div>
<form class="bg-white border rounded-2xl p-4 mb-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-3"><input name="q" placeholder="नाव, GR क्र., वडिलांचे नाव..." value="<?= e($q) ?>"><select name="class_id"><option value="0">सर्व इयत्ता</option><?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>" <?= $classId === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach; ?></select><select name="category"><option value="">सर्व प्रवर्ग</option><?php foreach (caste_options() as $label => $value): ?><option value="<?= e($value) ?>" <?= $category === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="bg-teal text-white rounded-xl px-5">शोधा</button></form>
<div class="flex gap-2 flex-wrap mb-5"><?php foreach ($years as $academic): ?><a class="rounded-lg px-3 py-2 <?= (int) ($academic['is_current'] ?? 0) ? 'bg-teal text-white' : 'bg-white border' ?>" href="/modules/gr/index.php?set_year=<?= $academic['id'] ?>"><?= e($academic['label']) ?></a><?php endforeach; ?></div>
<div class="bg-white border rounded-2xl table-wrap"><table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-3">पुस्तक</th><th class="p-3">GR क्र.</th><th class="p-3">विद्यार्थ्याचे नाव</th><th class="p-3">वर्ग/तुकडी</th><th class="p-3">वडिलांचे नाव</th><th class="p-3">जन्मतारीख</th><th class="p-3">प्रवर्ग</th><th class="p-3">कृती</th></tr></thead><tbody><?php if (!$students): ?><tr><td colspan="8" class="p-10 text-center text-slate-500">अजून विद्यार्थी नोंदलेले नाहीत.</td></tr><?php endif; ?><?php foreach ($students as $student): ?><tr class="border-b"><td class="p-3"><?= e($student['book_no']) ?></td><td class="p-3"><?= e($student['gr_no']) ?></td><td class="p-3 font-semibold"><?= e(student_full_name($student)) ?></td><td class="p-3"><?= e($student['class_name'] ?? '—') ?></td><td class="p-3"><?= e($student['father_name']) ?></td><td class="p-3"><?= e($student['dob']) ?></td><td class="p-3"><?= e($student['caste_category']) ?></td><td class="p-3 flex gap-2"><a class="text-teal font-bold" href="/modules/gr/view.php?id=<?= $student['id'] ?>">पहा</a><a class="text-gold font-bold" href="/modules/gr/students/edit.php?id=<?= $student['id'] ?>">संपादन</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php layout_end(); ?>
