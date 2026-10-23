<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';

$user = require_role(['admin','principal','teacher']);
$sid = (int) $user['school_id'];
$pdo = db();
$year = current_academic_year($sid);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $std = max(1, min(12, (int) ($_POST['std'] ?? 1)));
        $section = trim((string) ($_POST['section'] ?? ''));
        $medium = trim((string) ($_POST['medium'] ?? 'मराठी'));
        $stream = trim((string) ($_POST['stream'] ?? ''));
        $teacher = (int) ($_POST['class_teacher_id'] ?? 0);
        if ($section === '') {
            $errors[] = 'तुकडी आवश्यक आहे.';
        } else {
            $exists = $pdo->prepare('SELECT id FROM classes WHERE school_id=? AND std=? AND section=? AND COALESCE(academic_year_id,0)=COALESCE(?,0)');
            $exists->execute([$sid, $std, $section, $year['id'] ?? null]);
            if ($exists->fetchColumn()) {
                $errors[] = 'ही तुकडी आधीपासून तयार आहे.';
            } else {
                $pdo->prepare('INSERT INTO classes(school_id,std,name,section,class_teacher_id,medium,stream,academic_year_id) VALUES(?,?,?,?,?,?,?,?)')
                    ->execute([$sid, $std, class_label($std) . ' ' . $section, $section, $teacher ?: null, $medium, $stream, $year['id'] ?? null]);
                flash_set('success', 'तुकडी जतन झाली.');
                header('Location: /modules/varg/index.php');
                exit;
            }
        }
    } elseif ($action === 'promote') {
        $targetId = (int) ($_POST['target_year_id'] ?? 0);
        if (!$targetId) {
            $currentLabel = (string) ($year['label'] ?? date('Y') . '-' . (date('Y') + 1));
            preg_match('/(\d{4})/', $currentLabel, $match);
            $nextLabel = ((int) ($match[1] ?? date('Y')) + 1) . '-' . substr((string) ((int) ($match[1] ?? date('Y')) + 2), -2);
            $mainDb = getMainDB();
            $mainDb->prepare('INSERT INTO academic_years(school_id,label,is_current) VALUES(?,?,0)')->execute([$sid, $nextLabel]);
            $targetId = (int) $mainDb->lastInsertId();
        }
        try {
            $pdo->beginTransaction();
            $students = $pdo->prepare('SELECT s.*,c.std,c.section,c.medium class_medium,c.stream class_stream FROM students s LEFT JOIN classes c ON c.id=s.class_id AND c.school_id=s.school_id WHERE s.school_id=? AND s.status=? AND COALESCE(c.std,0) BETWEEN 1 AND 11 AND NOT EXISTS (SELECT 1 FROM student_enrollments e WHERE e.student_id=s.id AND e.academic_year_id=?)');
            $students->execute([$sid, 'active', $targetId]);
            $findClass = $pdo->prepare('SELECT id FROM classes WHERE school_id=? AND std=? AND section=? AND COALESCE(academic_year_id,0)=COALESCE(?,0) LIMIT 1');
            $newClass = $pdo->prepare('INSERT INTO classes(school_id,std,name,section,class_teacher_id,medium,stream,academic_year_id) VALUES(?,?,?,?,?,?,?,?)');
            $enroll = $pdo->prepare('INSERT IGNORE INTO student_enrollments(school_id,student_id,academic_year_id,class_id,status,created_at) VALUES(?,?,?,?,?,?)');
            $moved = 0;
            foreach ($students as $student) {
                $std = (int) $student['std'] + 1;
                $section = (string) ($student['section'] ?: 'अ');
                $findClass->execute([$sid, $std, $section, $targetId]);
                $classId = (int) $findClass->fetchColumn();
                if (!$classId) {
                    $newClass->execute([$sid, $std, class_label($std) . ' ' . $section, $section, null, $student['class_medium'] ?: 'मराठी', $student['class_stream'] ?: '', $targetId]);
                    $classId = (int) $pdo->lastInsertId();
                }
                $enroll->execute([$sid, $student['id'], $targetId, $classId, 'enrolled', date(DATE_ATOM)]);
                $pdo->prepare('UPDATE students SET class_id=? WHERE id=? AND school_id=?')->execute([$classId, $student['id'], $sid]);
                $moved++;
            }
            $pdo->commit();
            $success = $moved . ' विद्यार्थी वर्गोन्नतीसाठी नोंदवले.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'वर्गोन्नती अयशस्वी: ' . $e->getMessage();
        }
    }
}

$classes = $pdo->prepare('SELECT c.*,(SELECT COUNT(*) FROM students s WHERE s.school_id=c.school_id AND s.class_id=c.id AND s.status="active") student_count FROM classes c WHERE c.school_id=? ORDER BY c.std,c.section');
$classes->execute([$sid]);
$classes = class_teacher_names($classes->fetchAll(), $sid);
$teachers = getMainDB()->prepare('SELECT id,name FROM users WHERE school_id=? ORDER BY name');
$teachers->execute([$sid]);
$years = getMainDB()->prepare('SELECT * FROM academic_years WHERE school_id=? ORDER BY id DESC');
$years->execute([$sid]);
layout_start('वर्ग व्यवस्थापन', 'varg');
?>
<div class="grid xl:grid-cols-[360px_1fr] gap-6">
    <form method="post" class="bg-white border rounded-2xl p-6 h-fit">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
        <h2 class="text-xl font-bold text-navy mb-5">नवीन तुकडी तयार करा</h2>
        <?php if ($errors): ?><div class="mb-4 rounded-xl bg-red-50 text-red-700 p-3"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
        <label class="block text-sm font-semibold mb-3">इयत्ता<select name="std" class="w-full mt-2"><?php foreach (class_labels() as $number => $label): ?><option value="<?= $number ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="block text-sm font-semibold mb-3">तुकडी<input required name="section" class="w-full mt-2" placeholder="A, B, C..."></label>
        <label class="block text-sm font-semibold mb-3">माध्यम<select name="medium" class="w-full mt-2"><?php foreach (medium_options() as $label => $value): ?><option value="<?= e($label) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="block text-sm font-semibold mb-3">शाखा (उच्च माध्यमिक)<input name="stream" class="w-full mt-2" placeholder="Science / Arts / Commerce"></label>
        <label class="block text-sm font-semibold mb-5">वर्गशिक्षक<select name="class_teacher_id" class="w-full mt-2"><option value="0">-- निवडा --</option><?php foreach ($teachers as $teacher): ?><option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option><?php endforeach; ?></select></label>
        <button class="w-full bg-gold text-navy rounded-xl py-3 font-bold">जतन करा</button>
        <a href="/modules/varg/import.php" class="block text-center mt-4 text-teal font-bold">Excel / CSV आयात ›</a>
    </form>
    <div class="space-y-6">
        <?php if ($success): ?><div class="rounded-xl bg-green-50 text-green-700 p-4"><?= e($success) ?></div><?php endif; ?>
        <div class="bg-white border rounded-2xl p-6"><div class="flex items-center justify-between gap-4 mb-5"><div><h2 class="text-xl font-bold text-navy">तुकडी यादी</h2><p class="text-sm text-slate-500 mt-1">डावीकडून तुकडी निवडा किंवा नवीन तुकडी तयार करा.</p></div><span class="text-sm text-slate-500"><?= e($year['label'] ?? '') ?></span></div><div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4"><?php $has = false; foreach ($classes as $class): $has = true; ?><div class="border rounded-xl p-4"><div class="flex justify-between"><strong class="text-navy"><?= e($class['name']) ?></strong><span class="text-xs rounded-full bg-teal/10 text-teal px-2 py-1"><?= e($class['medium'] ?: 'मराठी') ?></span></div><p class="text-sm text-slate-500 mt-2"><?= e($class['student_count']) ?> विद्यार्थी · <?= e($class['teacher_name'] ?: 'वर्गशिक्षक नाही') ?></p><a href="/modules/classes/subjects.php?class_id=<?= $class['id'] ?>" class="text-teal text-sm font-bold inline-block mt-3">विषय पहा →</a></div><?php endforeach; if (!$has): ?><p class="text-slate-500 py-8 col-span-full text-center">अजून कोणतीही तुकडी तयार केलेली नाही.</p><?php endif; ?></div></div>
        <div class="bg-white border rounded-2xl p-6"><h2 class="text-xl font-bold text-navy">वर्गोन्नती</h2><p class="text-sm text-slate-600 mt-2">चालू वर्षातील सक्रिय विद्यार्थी पुढील शैक्षणिक वर्षात एक वर्ग पुढे जातील. आधीच नोंदलेले विद्यार्थी पुन्हा हलवले जाणार नाहीत. ही कृती थेट पूर्ववत करता येणार नाही.</p><form method="post" class="mt-5 flex flex-wrap gap-3" onsubmit="return confirm('चालू वर्षातील पात्र विद्यार्थ्यांची वर्गोन्नती करायची आहे का? ही कृती थेट पूर्ववत करता येणार नाही.')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="promote"><select name="target_year_id" class="min-w-48"><option value="0">नवीन शैक्षणिक वर्ष तयार करा</option><?php foreach ($years as $academic): ?><option value="<?= $academic['id'] ?>"><?= e($academic['label']) ?></option><?php endforeach; ?></select><button class="bg-teal text-white rounded-xl px-5 py-3 font-bold">वर्गोन्नती करा</button></form><p class="text-xs text-slate-500 mt-3">सध्या कोणीही विद्यार्थी वर्गोन्नतीसाठी पात्र नाही. असेल तरच नोंद केली जाईल.</p></div>
    </div>
</div>
<?php layout_end(); ?>
