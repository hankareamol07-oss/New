<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/print_layout.php';
$user = require_role(['admin', 'principal', 'teacher']);
$sid = (int) $user['school_id'];
$pdo = db();
$classes = school_classes($sid);
$classId = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? ($classes[0]['id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null) && in_array($user['role'], ['admin', 'principal'], true)) {
    $stmt = $pdo->prepare('REPLACE INTO timetable(school_id,class_id,day,period,subject) VALUES(?,?,?,?,?)');
    $stmt->execute([$sid, $classId, (int) $_POST['day'], (int) $_POST['period'], trim((string) $_POST['subject'])]);
    flash_set('success', 'वेळापत्रक जतन झाले.');
    header("Location:/modules/classes/timetable.php?class_id=$classId");
    exit;
}
$stmt = $pdo->prepare('SELECT t.*,c.name class_name FROM timetable t JOIN classes c ON c.id=t.class_id AND c.school_id=t.school_id WHERE t.school_id=? AND t.class_id=? ORDER BY t.day,t.period');
$stmt->execute([$sid, $classId]);
$items = $stmt->fetchAll();
if (isset($_GET['print'])) {
    print_start('वर्गाचे वेळापत्रक');
    ?>
    <h1>वर्गाचे वेळापत्रक</h1><table><thead><tr><th>वर्ग</th><th>वार</th><th>तास</th><th>विषय</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><?= e($item['class_name']) ?></td><td><?= e((string) $item['day']) ?></td><td><?= e((string) $item['period']) ?></td><td><?= e($item['subject']) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php print_signature_row(['वर्गशिक्षक', 'मुख्याध्यापक']); print_end(); exit;
}
layout_start('वेळापत्रक', 'classes');
?>
<div class="flex justify-end mb-4"><a href="/modules/classes/timetable.php?class_id=<?= $classId ?>&print=1" class="bg-white border rounded-xl px-4 py-2 text-navy font-bold">प्रिंट</a></div><div class="grid lg:grid-cols-3 gap-6"><form method="post" class="bg-white border rounded-2xl p-6 space-y-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label class="font-semibold block">वर्ग<select name="class_id" class="w-full mt-2"><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label><label class="font-semibold block">वार (1-6)<input required type="number" min="1" max="6" name="day" class="w-full mt-2"></label><label class="font-semibold block">तास<input required type="number" min="1" max="8" name="period" class="w-full mt-2"></label><label class="font-semibold block">विषय<input required name="subject" class="w-full mt-2"></label><button class="bg-gold rounded-xl px-5 py-3 font-bold">जतन करा</button></form><div class="lg:col-span-2 bg-white border rounded-2xl p-6"><h2 class="text-xl font-bold text-navy mb-4">आठवड्याचे वेळापत्रक</h2><div class="grid sm:grid-cols-2 gap-3"><?php foreach ($items as $item): ?><div class="border rounded-xl p-4"><span class="text-sm text-slate-500">वार <?= $item['day'] ?> · तास <?= $item['period'] ?></span><strong class="block text-navy mt-1"><?= e($item['subject']) ?></strong></div><?php endforeach; ?></div></div></div>
<?php layout_end(); ?>
