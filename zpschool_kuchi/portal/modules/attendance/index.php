<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/app_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../../includes/cce_functions.php';

$user = require_role(['admin', 'principal', 'clerk', 'teacher']);
$sid = (int) $user['school_id'];
$pdo = getMainDB();
$date = posted_date('date');

$classes = school_classes($sid);
$classIds = array_map('intval', array_column($classes, 'id'));

$classId = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
if ($classId === 0 && !empty($classes)) {
    $classId = (int)$classes[0]['id'];
}
if (!empty($classes) && !in_array($classId, $classIds, true)) {
    $classId = (int)$classes[0]['id'];
}

// Find standard number for this class
$currentClassStd = 0;
foreach ($classes as $c) {
    if ((int)$c['id'] === $classId) {
        $currentClassStd = (int)$c['std'];
        break;
    }
}

// Handle Attendance Save POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null) && isset($_POST['status'])) {
    $s_stmt = $pdo->prepare('SELECT id FROM students WHERE school_id = ? AND (class_id = ? OR std = ?) AND (is_active = 1 OR status = "active")');
    $s_stmt->execute([$sid, $classId, $currentClassStd]);
    $stRows = $s_stmt->fetchAll(PDO::FETCH_ASSOC);

    $p = $pdo->prepare('REPLACE INTO daily_attendance (school_id, student_id, date, status) VALUES (?, ?, ?, ?)');
    foreach ($stRows as $s) {
        $stVal = (string)($_POST['status'][$s['id']] ?? 'P');
        $p->execute([$sid, $s['id'], $date, $stVal]);
    }
    flash_set('success', 'दैनिक उपस्थिती यशस्वीरित्या जतन झाली.');
}

$students = [];
if ($classId > 0) {
    $s = $pdo->prepare('
        SELECT s.*, COALESCE(a.status, "P") as att_status 
        FROM students s 
        LEFT JOIN daily_attendance a ON a.student_id = s.id AND a.school_id = s.school_id AND a.date = ? 
        WHERE s.school_id = ? AND (s.class_id = ? OR s.std = ?) AND (s.is_active = 1 OR s.status = "active" OR s.status IS NULL) 
        ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.name_mr ASC
    ');
    $s->execute([$date, $sid, $classId, $currentClassStd]);
    $students = $s->fetchAll(PDO::FETCH_ASSOC);
}

layout_start('दैनिक उपस्थिती नोंद (Daily Attendance)', 'attendance');
?>

<div class="mb-6 bg-gradient-to-r from-[#1E3A8A] to-[#2563EB] text-white p-6 rounded-3xl shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="inline-block bg-white/20 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-2">हजेरी व्यवस्थापन</span>
            <h1 class="text-2xl sm:text-3xl font-black">दैनिक उपस्थिती नोंदवही</h1>
            <p class="text-blue-100 text-sm mt-1">तारीख: <?= e(date('d/m/Y', strtotime($date))) ?> | विद्यार्थी संख्या: <?= count($students) ?></p>
        </div>
        <div class="text-right">
            <span class="text-xs text-blue-200">आजची तारीख</span>
            <div class="text-2xl font-black"><?= date('d M Y') ?></div>
        </div>
    </div>
</div>

<form method="get" class="bg-white border rounded-2xl p-4 mb-5 flex flex-wrap items-center gap-3 shadow-sm">
    <label class="font-bold text-sm text-slate-700">तारीख:</label>
    <input type="date" name="date" value="<?= e($date) ?>" class="border rounded-xl px-3 py-2 text-sm">
    
    <label class="font-bold text-sm text-slate-700 ml-2">वर्ग:</label>
    <select name="class_id" class="border rounded-xl px-3 py-2 text-sm font-semibold">
        <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    
    <button class="bg-[#1E3A8A] text-white rounded-xl px-5 py-2 font-bold text-sm hover:bg-[#1E40AF]">यादी दाखवा</button>
</form>

<?php if ($classId && !empty($students)): ?>
    <form method="post" class="bg-white border rounded-2xl p-6 shadow-sm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        
        <div class="table-wrap overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b bg-slate-50 text-slate-600 text-sm">
                        <th class="p-3">रोल नं.</th>
                        <th class="p-3">विद्यार्थ्याचे नाव (Student Name)</th>
                        <th class="p-3">PEN / GR</th>
                        <th class="p-3">उपस्थिती स्थिती (Status)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($students as $s): 
                        $stName = !empty($s['name_mr']) ? $s['name_mr'] : (!empty($s['name']) ? $s['name'] : trim(($s['first_name'] ?? '') . ' ' . ($s['surname'] ?? '')));
                        $curStat = $s['att_status'] ?? 'P';
                    ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 font-bold text-slate-700"><?= e((string)$s['roll_no']) ?></td>
                            <td class="p-3">
                                <div class="font-bold text-slate-900"><?= e($stName) ?></div>
                                <?php if (!empty($s['name']) && $s['name'] !== $stName): ?>
                                    <div class="text-xs text-slate-400 font-medium"><?= e($s['name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-xs text-slate-500"><?= e((string)($s['student_pen'] ?? $s['gr_no'] ?? '-')) ?></td>
                            <td class="p-3">
                                <select name="status[<?= $s['id'] ?>]" class="border rounded-lg px-3 py-1 font-bold text-sm <?= $curStat === 'P' ? 'text-emerald-700 bg-emerald-50' : ($curStat === 'A' ? 'text-rose-700 bg-rose-50' : 'text-amber-700 bg-amber-50') ?>">
                                    <option value="P" <?= $curStat === 'P' ? 'selected' : '' ?>>हजर (P)</option>
                                    <option value="A" <?= $curStat === 'A' ? 'selected' : '' ?>>गैरहजर (A)</option>
                                    <option value="L" <?= $curStat === 'L' ? 'selected' : '' ?>>रजा (L)</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button class="bg-[#1E3A8A] text-white rounded-xl px-6 py-3 font-black text-base shadow hover:bg-[#1E40AF]">
                <i class="bi bi-check2-circle me-1"></i> उपस्थिती जतन करा (Save Attendance)
            </button>
        </div>
    </form>
<?php elseif ($classId): ?>
    <div class="bg-white border rounded-2xl p-8 text-center text-slate-500 shadow-sm">
        या वर्गासाठी कोणतेही सक्रिय विद्यार्थी आढळले नाहीत.
    </div>
<?php endif; ?>

<?php layout_end(); ?>
