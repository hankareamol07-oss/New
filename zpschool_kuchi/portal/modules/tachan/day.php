<?php
declare(strict_types=1);

/**
 * Smart School Hub — Daily Lesson Planner (दैनिक टाचण)
 * 1. शिक्षकाचे स्वतःचे तासिका वेळापत्रक (Teacher's Multi-Class Daily Plan)
 * 2. वर्गनिहाय किंवा जोडवर्ग टाचण (Class / Jodvarg Daily Plan)
 * Automatically integrates:
 * - Paripath Suvichar (data/paripath.sqlite)
 * - Academic Calendar Upakram / Dinvishesh
 * - Balbharati Official Content Bank 2026004
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db(); // SQLite tenant DB

$allTeachers = school_teachers($sid);
$teacherById = [];
foreach ($allTeachers as $t) {
    $teacherById[(int)$t['id']] = $t;
}

$teacherId = !empty($_REQUEST['teacher_id']) && isset($teacherById[(int)$_REQUEST['teacher_id']])
    ? (int)$_REQUEST['teacher_id']
    : (isset($teacherById[(int)$user['id']]) ? (int)$user['id'] : ((int)($allTeachers[0]['id'] ?? $user['id'])));

$classes = school_classes($sid);

if (!$classes) {
    flash_set('error', 'आधी वर्ग व तुकड्या तयार करा.');
    header('Location: ' . APP_URL . '/modules/classes/index.php');
    exit;
}

$byId = [];
foreach ($classes as $c) {
    $byId[(int)$c['id']] = $c;
}

$pref = tachan_prefs_get($pdo, $sid, $teacherId);
$teacherTT = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
$hasTeacherTT = !empty($teacherTT);

// Mode: Teacher (शिक्षकाचे वेळापत्रक) vs Class (वर्गनिहाय)
$mode = isset($_REQUEST['mode']) && in_array($_REQUEST['mode'], ['teacher', 'class'], true)
    ? (string)$_REQUEST['mode']
    : (($pref['teach_type'] ?? '') === 'single' && isset($_REQUEST['class_id']) ? 'class' : ($hasTeacherTT || ($pref['teach_type'] ?? '') === 'periodwise' ? 'teacher' : 'class'));

// वर्ग : GET > सेटिंग > पहिला वर्ग
$prefClass = $pref['class_id'] && isset($byId[$pref['class_id']]) ? $pref['class_id'] : (int)$classes[0]['id'];
$sel = isset($_REQUEST['class_id']) && isset($byId[(int)$_REQUEST['class_id']]) ? (int)$_REQUEST['class_id'] : $prefClass;
$class = $byId[$sel];

$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date']) ? (string)$_GET['date'] : date('Y-m-d');
$medium = isset($_GET['medium']) ? tachan_norm_medium((string)$_GET['medium']) : ($pref['medium'] ?: tachan_norm_medium((string)($class['medium'] ?? 'marathi')));

// जोडवर्ग (दुसरा वर्ग) — GET > सेटिंग (jod प्रकार असल्यास)
$prefJod = ($pref['teach_type'] === 'jod' && $pref['jod_class_id'] && isset($byId[$pref['jod_class_id']]) && $pref['jod_class_id'] !== $sel) ? $pref['jod_class_id'] : 0;
$jod = isset($_GET['jod']) ? ((isset($byId[(int)$_GET['jod']]) && (int)$_GET['jod'] !== $sel) ? (int)$_GET['jod'] : 0) : $prefJod;

// दिवसाची लांबी
$dayLen = isset($_GET['dl']) && in_array($_GET['dl'], ['full', 'half', 'upto'], true) ? (string)$_GET['dl'] : $pref['day_length'];
$upto = isset($_GET['upto']) ? max(1, min(9, (int)$_GET['upto'])) : $pref['upto_period'];
$lengths = ['full' => 'पूर्ण दिवस (सर्व तासिका)', 'half' => 'अर्धा दिवस (तासिका १–४)', 'upto' => 'तासिका ' . $upto . ' पर्यंत'];
$mediums = ['marathi' => 'मराठी माध्यम', 'semi_english' => 'सेमी-इंग्रजी'];

// POST: पुन्हा तयार करा (Regenerate)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && tachan_verify_csrf() && isset($_POST['regen'])) {
    $rm = tachan_norm_medium((string)($_POST['medium'] ?? $medium));
    if ($mode === 'teacher') {
        // Clear bank rows for teacher and recreate
        $pdo->prepare("DELETE FROM lesson_plans WHERE school_id=? AND teacher_id=? AND date=? AND src IN ('teacher_tt', 'bank')")->execute([$sid, $teacherId, $date]);
        syncTenantDeleteToMySQL('lesson_plans', ['school_id' => $sid, 'teacher_id' => $teacherId, 'date' => $date]);
        tachan_teacher_day_rows($pdo, $sid, $teacherId, $date, true, $rm);
        flash_set('success', 'शिक्षकाच्या वेळापत्रकानुसार आजचे टाचण अधिकृत बँकेतून पुन्हा तयार झाले.');
    } else {
        $t = strtotime($date);
        tachan_regenerate_month($pdo, $sid, $teacherId, $class, (int)date('Y', $t), (int)date('n', $t), $rm);
        if ($jod && isset($byId[$jod])) {
            tachan_regenerate_month($pdo, $sid, $teacherId, $byId[$jod], (int)date('Y', $t), (int)date('n', $t), tachan_norm_medium((string)($byId[$jod]['medium'] ?? 'marathi')));
        }
        flash_set('success', 'या महिन्याचे टाचण (' . $mediums[$rm] . ') यशस्वीरित्या पुन्हा तयार झाले.');
    }
    header('Location: ' . APP_URL . '/modules/tachan/day.php?mode=' . $mode . '&class_id=' . $sel . '&date=' . $date . '&medium=' . $rm . ($jod ? '&jod=' . $jod : '') . '&dl=' . $dayLen . '&upto=' . $upto);
    exit;
}

$dow = (int)date('N', strtotime($date));
$periods = planner_periods($dow === 6 ? 6 : 1);
$isSat = $dow === 6;

// सुट्टी तपासणी
$holq = $pdo->prepare('SELECT name FROM holidays WHERE school_id=? AND date=?');
$holq->execute([$sid, $date]);
$holName = $holq->fetchColumn();
$closed = ($dow === 7 || $holName !== false);

// परिपाठातून आजचा सुविचार आपोआप मिळवा
$suvichar = tachan_fetch_suvichar($date);

// शैक्षणिक कॅलेंडर व परिपाठातून आजचे उपक्रम व दिनविशेष आपोआप मिळवा
$upakram = tachan_fetch_upakram($pdo, $sid, $date);

// Data loading depending on mode
if ($mode === 'teacher') {
    $rows = $closed ? [] : tachan_teacher_day_rows($pdo, $sid, $teacherId, $date, true, $medium);
    $rows = tachan_apply_day_length($rows, $dayLen, $upto);
    $have = [];
    foreach ($rows as $r) {
        $have[(int)$r['period']] = $r;
    }
    $todaySlots = $teacherTT[$dow] ?? [];
} else {
    $tt = tachan_timetable($pdo, $sid, $class);
    $ttToday = $tt[$dow] ?? [];
    $rows = $closed ? [] : tachan_day_rows($pdo, $sid, $teacherId, $class, $date, true, $medium);
    $rows = tachan_apply_day_length($rows, $dayLen, $upto);
    $have = [];
    foreach ($rows as $r) {
        $have[(int)$r['period']] = $r;
    }

    $jodRows = [];
    if ($jod && !$closed && isset($byId[$jod])) {
        foreach (tachan_apply_day_length(tachan_day_rows($pdo, $sid, $teacherId, $byId[$jod], $date, true, tachan_norm_medium((string)($byId[$jod]['medium'] ?? 'marathi'))), $dayLen, $upto) as $r) {
            $jodRows[(int)$r['period']] = $r;
        }
    }
    $jodTT = ($jod && isset($byId[$jod])) ? (tachan_timetable($pdo, $sid, $byId[$jod])[$dow] ?? []) : [];
    $jodMed = ($jod && isset($byId[$jod])) ? tachan_norm_medium((string)($byId[$jod]['medium'] ?? 'marathi')) : 'marathi';
}

$prev = date('Y-m-d', strtotime($date . ' -1 day'));
$next = date('Y-m-d', strtotime($date . ' +1 day'));
$dnames = [1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार', 7 => 'रविवार'];
$q = fn(string $d) => 'mode=' . $mode . '&class_id=' . $sel . '&date=' . $d . '&medium=' . $medium . ($jod ? '&jod=' . $jod : '') . '&dl=' . $dayLen . '&upto=' . $upto;

$school = getSchool();
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));

$mn = [1 => 'जानेवारी', 2 => 'फेब्रुवारी', 3 => 'मार्च', 4 => 'एप्रिल', 5 => 'मे', 6 => 'जून', 7 => 'जुलै', 8 => 'ऑगस्ट', 9 => 'सप्टेंबर', 10 => 'ऑक्टोबर', 11 => 'नोव्हेंबर', 12 => 'डिसेंबर'];
$fmt = fn(string $d) => (int)date('j', strtotime($d)) . ' ' . $mn[(int)date('n', strtotime($d))] . ' ' . date('Y', strtotime($d));

// Fast Date switcher tabs with Marathi dates
$mn = [1 => 'जानेवारी', 2 => 'फेब्रुवारी', 3 => 'मार्च', 4 => 'एप्रिल', 5 => 'मे', 6 => 'जून', 7 => 'जुलै', 8 => 'ऑगस्ट', 9 => 'सप्टेंबर', 10 => 'ऑक्टोबर', 11 => 'नोव्हेंबर', 12 => 'डिसेंबर'];
$fmtShort = fn(string $d) => (int)date('j', strtotime($d)) . ' ' . $mn[(int)date('n', strtotime($d))] . ' ' . date('Y', strtotime($d));

$today = date('Y-m-d');
$tabs = [
    'काल' => date('Y-m-d', strtotime($today . ' -1 day')),
    'आज' => $today,
    'उद्या' => date('Y-m-d', strtotime($today . ' +1 day'))
];
$last = tachan_last_period($dayLen, $upto);

$currTeacherName = resolve_teacher_name($mode === 'class' ? $class : null, $sid, $user);

$ttPrintUrl = APP_URL . '/modules/tachan/timetable_print.php?mode=' . $mode . ($mode === 'class' ? '&class_id=' . $sel : '') . '&medium=' . urlencode($medium);
$pdfPrintBase = APP_URL . '/modules/tachan/day_print.php?' . $q($date);

tachan_layout_start('दैनिक टाचण डॅशबोर्ड', 'day');
?>

<!-- Top Header & Half-Day Selector -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div class="d-flex align-items-center gap-2">
        <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <span class="fs-4">📝</span> दैनिक टाचण डॅशबोर्ड
        </h4>
        <div class="btn-group btn-group-sm ms-2" role="group">
            <a href="<?= APP_URL ?>/modules/tachan/day.php?mode=teacher&date=<?= e($date) ?>&medium=<?= urlencode($medium) ?>" 
               class="btn <?= $mode === 'teacher' ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>"
               style="<?= $mode === 'teacher' ? 'background:#0284C7; border-color:#0284C7;' : '' ?>">
                <i class="bi bi-person-lines-fill me-1"></i> माझे वेळापत्रक
            </a>
            <a href="<?= APP_URL ?>/modules/tachan/day.php?mode=class&class_id=<?= $sel ?>&date=<?= e($date) ?>&medium=<?= urlencode($medium) ?>" 
               class="btn <?= $mode === 'class' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
                <i class="bi bi-mortarboard me-1"></i> वर्गनिहाय टाचण
            </a>
        </div>
        <?php if ($mode === 'class'): ?>
            <form method="get" class="d-inline-flex gap-1 align-items-center ms-1">
                <input type="hidden" name="mode" value="class">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="medium" value="<?= e($medium) ?>">
                <select name="class_id" onchange="this.form.submit()" class="form-select form-select-sm fw-bold rounded-3" style="width: auto;">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $sel === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($jod): ?>
                    <span class="badge bg-light text-dark border">+ <?= e($byId[$jod]['name'] ?? 'जोडवर्ग') ?></span>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <!-- अर्धा दिवस / तासिका Dropdown Filter -->
    <div class="d-flex align-items-center gap-2">
        <label for="uptoSel" class="text-secondary small fw-bold mb-0 text-nowrap">अर्धा दिवस :</label>
        <select id="uptoSel" class="form-select form-select-sm fw-bold border-secondary-subtle rounded-3" style="min-width: 140px;">
            <option value="" <?= empty($upto) || $dayLen === 'full' ? 'selected' : '' ?>>संपूर्ण दिवस</option>
            <?php for ($p = 1; $p <= 9; $p++): ?>
                <option value="<?= $p ?>" <?= ($dayLen === 'upto' || !empty($_GET['upto'])) && (int)$upto === $p ? 'selected' : '' ?>>
                    तासिका <?= $p ?> पर्यंत
                </option>
            <?php endfor; ?>
        </select>
    </div>
</div>

<!-- 3-Column Fast Date Nav Pills  -->
<div class="row g-2 mb-3">
    <?php foreach ($tabs as $lbl => $d): 
        $isActive = ($date === $d);
    ?>
    <div class="col-4">
        <a href="<?= APP_URL ?>/modules/tachan/day.php?<?= $q($d) ?>" 
           class="card h-100 text-center text-decoration-none p-2 rounded-3 border transition-all shadow-sm <?= $isActive ? 'text-white' : 'text-dark bg-white hover-bg-light' ?>"
           style="<?= $isActive ? 'background:#0284C7; border-color:#0284C7;' : 'border-color:#e2e8f0;' ?>">
            <div class="fw-bold fs-6"><?= $lbl ?></div>
            <div class="small <?= $isActive ? 'text-white-50' : 'text-muted' ?>" style="font-size: 11.5px;">
                (<?= e($fmtShort($d)) ?>)
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Secondary Date Picker Bar -->
<div class="card border-0 shadow-sm rounded-3 p-2 mb-3 bg-white">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-1">
            <span class="small text-muted fw-bold me-1">तारीख बदला:</span>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= APP_URL ?>/modules/tachan/day.php?<?= $q($prev) ?>" class="btn btn-outline-secondary" title="मागील दिवस">‹ मागील</a>
                <a href="<?= APP_URL ?>/modules/tachan/day.php?<?= $q($next) ?>" class="btn btn-outline-secondary" title="पुढील दिवस">पुढील ›</a>
            </div>
            <form method="get" class="d-inline ms-1">
                <input type="hidden" name="mode" value="<?= e($mode) ?>">
                <input type="hidden" name="class_id" value="<?= $sel ?>">
                <input type="hidden" name="medium" value="<?= e($medium) ?>">
                <input type="hidden" name="jod" value="<?= $jod ?>">
                <input type="hidden" name="dl" value="<?= e($dayLen) ?>">
                <input type="hidden" name="upto" value="<?= $upto ?>">
                <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()" class="form-control form-control-sm rounded-3 d-inline" style="width: 130px;">
            </form>
        </div>

        <div class="d-flex align-items-center gap-2">
            <form method="get" class="d-inline-flex gap-1 align-items-center">
                <input type="hidden" name="mode" value="<?= e($mode) ?>">
                <input type="hidden" name="class_id" value="<?= $sel ?>">
                <input type="hidden" name="jod" value="<?= $jod ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <select name="medium" onchange="this.form.submit()" class="form-select form-select-sm rounded-3">
                    <?php foreach ($mediums as $mv => $ml): ?>
                        <option value="<?= $mv ?>" <?= $medium === $mv ? 'selected' : '' ?>><?= e($ml) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
</div>

<!-- Action Buttons: PDF पहा, डाउनलोड, वेळापत्रक PDF -->
<div class="row g-2 mb-3">
    <div class="col-12 col-sm-4">
        <a id="pdfView" data-base="<?= e($pdfPrintBase) ?>" href="<?= e($pdfPrintBase) ?><?= !empty($upto) ? '&upto=' . $upto : '' ?>" target="_blank"
           class="btn btn-danger w-100 fw-bold py-2 shadow-sm rounded-pill d-flex align-items-center justify-content-center gap-2" style="background:#E11D48; border-color:#E11D48;">
            <i class="bi bi-file-earmark-pdf-fill fs-5"></i> 📄 PDF पहा
        </a>
    </div>
    <div class="col-12 col-sm-4">
        <a id="pdfDl" data-base="<?= e($pdfPrintBase) ?>&dl_pdf=1" href="<?= e($pdfPrintBase) ?>&dl_pdf=1<?= !empty($upto) ? '&upto=' . $upto : '' ?>" target="_blank"
           class="btn btn-success w-100 fw-bold py-2 shadow-sm rounded-pill d-flex align-items-center justify-content-center gap-2" style="background:#16A34A; border-color:#16A34A;">
            <i class="bi bi-download fs-5"></i> ⬇ डाउनलोड
        </a>
    </div>
    <div class="col-12 col-sm-4">
        <a href="<?= e($ttPrintUrl) ?>" target="_blank"
           class="btn btn-primary w-100 fw-bold py-2 shadow-sm rounded-pill d-flex align-items-center justify-content-center gap-2" style="background:#4F46E5; border-color:#4F46E5;">
            <i class="bi bi-calendar3 fs-5"></i> 🗓 वर्ग वेळापत्रक PDF
        </a>
    </div>
</div>

<script>
(function () {
    var sel = document.getElementById('uptoSel');
    if (!sel) return;
    sel.addEventListener('change', function () {
        var v = sel.value;
        ['pdfView', 'pdfDl'].forEach(function (id) {
            var a = document.getElementById(id);
            if (a) {
                a.href = a.dataset.base + (v ? '&upto=' + encodeURIComponent(v) : '');
            }
        });
        // Also reload page with upto parameter for on-screen filter
        var url = new URL(window.location.href);
        if (v) {
            url.searchParams.set('upto', v);
            url.searchParams.set('dl', 'upto');
        } else {
            url.searchParams.delete('upto');
            url.searchParams.set('dl', 'full');
        }
        window.location.href = url.toString();
    });
})();
</script>

<!-- Teacher & School Details Bar -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-3 bg-white">
    <div class="d-flex flex-wrap gap-4 align-items-center text-sm">
        <div><span class="text-muted">नाव:</span> <strong class="text-dark"><?= e($currTeacherName) ?></strong></div>
        <div><span class="text-muted">शाळा:</span> <strong class="text-dark"><?= e($schoolName) ?></strong></div>
        <div><span class="text-muted">तारीख:</span> <strong class="text-dark"><?= e($fmt($date)) ?></strong> (<?= e($dnames[$dow] ?? '') ?>)</div>
        <?php if ($mode === 'class'): ?>
            <div><span class="text-muted">वर्ग:</span> <strong class="text-primary"><?= e($class['name']) ?><?= $jod ? ' + ' . e($byId[$jod]['name']) : '' ?></strong></div>
        <?php else: ?>
            <div><span class="text-muted">वेळापत्रक:</span> <strong class="text-success">शिक्षकाचे तासिका वेळापत्रक</strong></div>
        <?php endif; ?>
    </div>
</div>

<!-- Daily Period Summary Table (Standard Signature Table) -->
<?php if (!$closed && (($mode === 'teacher' && !empty($todaySlots)) || ($mode === 'class' && !empty($ttToday)))): ?>
<div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size: 13.5px;">
            <thead>
                <tr class="text-white" style="background:#0284C7;">
                    <th class="text-center py-2 px-3" style="width: 70px;">तास</th>
                    <th class="py-2 px-3" style="width: 160px;">इयत्ता / तुकडी</th>
                    <th class="py-2 px-3">विषय</th>
                    <th class="text-center py-2 px-3" style="width: 140px;">टाचण उपलब्ध</th>
                    <th class="text-center py-2 px-3" style="width: 110px;">कृती</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php 
                for ($p = 1; $p <= $last; $p++):
                    if ($mode === 'teacher') {
                        $slot = $todaySlots[$p] ?? [];
                        $pSub = trim((string)($slot['subject'] ?? ''));
                        $pCls = trim((string)($slot['class_name'] ?? ''));
                        if ($pCls === '') {
                            $pCls = !empty($slot['std']) ? "इ. {$slot['std']} वी" : 'वर्ग';
                        }
                    } else {
                        $pSub = trim((string)($ttToday[$p] ?? ''));
                        $pCls = $class['name'];
                    }
                    $hasPlan = isset($have[$p]);
                ?>
                <tr class="hover-bg-light">
                    <td class="text-center fw-bold fs-6 py-2 px-3" style="color:#0369A1;">
                        <?= $p ?>
                    </td>
                    <td class="py-2 px-3 fw-semibold text-slate-700">
                        <?= e($pCls) ?>
                    </td>
                    <td class="py-2 px-3 fw-bold text-dark">
                        <?= $pSub !== '' ? e(tachan_display_subject($pSub, $medium)) : '<span class="text-muted fw-normal">रिक्त तासिका</span>' ?>
                    </td>
                    <td class="text-center py-2 px-3">
                        <?php if ($hasPlan): ?>
                            <span class="text-success fw-bold fs-5" title="टाचण उपलब्ध आहे">✔</span>
                        <?php elseif ($pSub === ''): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <span class="text-danger fw-bold fs-5" title="टाचण उपलब्ध नाही">❌</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center py-2 px-3">
                        <?php if ($hasPlan): ?>
                            <a href="<?= APP_URL ?>/modules/tachan/edit.php?id=<?= $have[$p]['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" style="font-size: 11.5px;">
                                <i class="bi bi-pencil"></i> संपादन
                            </a>
                        <?php elseif ($pSub !== ''): ?>
                            <a href="<?= APP_URL ?>/modules/tachan/add.php?class_id=<?= $sel ?>&period=<?= $p ?>&date=<?= e($date) ?>&class_name=<?= urlencode($pCls) ?>&subject=<?= urlencode($pSub) ?>" class="btn btn-sm btn-outline-warning py-0 px-2 rounded-pill" style="font-size: 11.5px;">
                                <i class="bi bi-plus"></i> नोंद करा
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Automatic Suvichar Banner from Paripath DB -->
<?php if (!empty($suvichar['text'])): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3" style="background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-left: 5px solid #F59E0B !important;">
        <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
            <i class="bi bi-chat-quote-fill fs-3 text-warning"></i>
            <div class="flex-grow-1">
                <span class="small fw-bold text-uppercase text-warning-emphasis">आजचा परिपाठ सुविचार :</span>
                <span class="fw-bold text-dark fs-6 ms-1">“<?= e($suvichar['text']) ?>”</span>
                <?php if (!empty($suvichar['author'])): ?>
                    <span class="small text-muted ms-1">— <?= e($suvichar['author']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Automatic Upakram & Dinvishesh Banner from Academic Calendar -->
<?php if (!empty($upakram)): ?>
    <div class="alert alert-info py-2 px-3 mb-3 shadow-sm rounded-4 d-flex align-items-center gap-2 border-0" style="background: #E0F2FE; color: #0369A1;">
        <i class="bi bi-bell-fill fs-5 text-primary"></i>
        <div>
            <strong class="text-primary-emphasis">आजचे उपक्रम / दिनविशेष :</strong>
            <span class="fw-semibold ms-1"><?= e(implode('  •  ', array_map('strval', $upakram))) ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Main Day Content View -->
<?php if ($dow === 7): ?>
    <div class="card tachan-card text-center p-5 mb-4 border-0 shadow-sm rounded-4">
        <i class="bi bi-cup-hot text-danger display-4 mb-2"></i>
        <h4 class="fw-bold text-danger">रविवार — साप्ताहिक सुट्टी</h4>
        <p class="text-muted mb-0">रविवारी शाळा बंद असते. इतर कामकाजाच्या दिवसाचे टाचण पाहण्यासाठी वरील तारीख निवडा.</p>
    </div>
<?php elseif ($holName !== false): ?>
    <div class="card tachan-card text-center p-5 mb-4 border-0 shadow-sm rounded-4">
        <i class="bi bi-sun-fill text-warning display-4 mb-2"></i>
        <h4 class="fw-bold text-dark">शाळा सुट्टी : <span class="text-primary"><?= e((string)$holName) ?></span></h4>
        <p class="text-muted mb-0">आज सुट्टी असल्यामुळे दैनिक टाचण लागू नाही.</p>
    </div>
<?php elseif ($mode === 'teacher' && empty($todaySlots)): ?>
    <div class="card tachan-card text-center p-5 mb-4 border-0 shadow-sm rounded-4">
        <i class="bi bi-calendar-x text-muted display-4 mb-2"></i>
        <h4 class="fw-bold text-dark">आजच्या वारासाठी (<?= e($dnames[$dow] ?? '') ?>) तुमचे वेळापत्रक नोंदवलेले नाही</h4>
        <p class="text-muted mb-3">शिक्षकाच्या वेळापत्रकात आजच्या दिवसासाठी तासिका व विषय भरलेले नाहीत किंवा साचा निवडलेला नाही.</p>
        <div>
            <a href="<?= APP_URL ?>/modules/tachan/timetable.php?mode=teacher" class="btn btn-primary fw-bold rounded-pill px-4" style="background:#167A5B; border-color:#167A5B;">
                <i class="bi bi-magic me-1"></i> माझे वेळापत्रक साचा निवडा / तयार करा →
            </a>
        </div>
    </div>
<?php elseif ($mode === 'class' && empty($ttToday)): ?>
    <div class="card tachan-card text-center p-5 mb-4 border-0 shadow-sm rounded-4">
        <i class="bi bi-calendar-x text-muted display-4 mb-2"></i>
        <h4 class="fw-bold text-dark">या वर्गासाठी आजच्या वाराचे वेळापत्रक तयार नाही</h4>
        <p class="text-muted mb-3">या वर्गाच्या चालू दिवसासाठी तासिका व विषय भरलेले नाहीत.</p>
        <div>
            <a href="<?= APP_URL ?>/modules/tachan/timetable.php?mode=class&class_id=<?= $sel ?>" class="btn btn-success fw-bold rounded-pill px-4">
                <i class="bi bi-clock-history me-1"></i> वर्ग वेळापत्रक तयार करा →
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Table of Periods -->
    <div class="card tachan-card overflow-hidden mb-4 border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                <i class="bi bi-list-check text-warning me-2"></i>
                <?= $mode === 'teacher' ? 'माझ्या तासिकानिहाय पाठ नोंदी' : 'वर्ग तासिकानिहाय पाठ नोंदी' ?>
            </h5>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-muted border px-3 py-2 rounded-pill">
                    तासिका १ ते <?= $last ?>
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                    <i class="bi bi-patch-check-fill me-1"></i> बालभारती अधिकृत २०२६-२७
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tachan-table">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 70px;">तासिका</th>
                        <th style="width: 140px;">इयत्ता / वर्ग</th>
                        <th style="width: 160px;">विषय</th>
                        <th>घटक / पाठ / उपक्रम</th>
                        <th class="text-center" style="width: 130px;">स्थिती / संपादन</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    for ($pn = 1; $pn <= $last; $pn++):
                        if ($mode === 'teacher') {
                            $slot = $todaySlots[$pn] ?? [];
                            $sub = trim((string)($slot['subject'] ?? ''));
                            $clsName = trim((string)($slot['class_name'] ?? ''));
                            $plan = $have[$pn] ?? null;
                            if ($clsName === '') {
                                $clsName = !empty($slot['std']) ? "इ. {$slot['std']} वी" : 'वर्ग';
                            }
                        } else {
                            $sub = trim((string)($ttToday[$pn] ?? ''));
                            $clsName = $class['name'];
                            $plan = $have[$pn] ?? null;
                        }
                    ?>
                        <tr>
                            <td class="text-center fw-bold fs-5 text-primary bg-light">
                                <?= $pn ?>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill fw-bold">
                                    <?= e($clsName) ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark"><?= $sub !== '' ? e(tachan_display_subject($sub, $medium)) : '—' ?></strong>
                            </td>
                            <td>
                                <?php if ($plan): ?>
                                    <div class="fw-bold text-dark mb-1"><?= e((string)($plan['topic'] ?? '')) ?></div>
                                    <?php if (!empty($plan['learning_outcome'])): ?>
                                        <div class="text-muted small text-truncate" style="max-width: 480px;">
                                            <i class="bi bi-check2 text-success"></i> निष्पत्ती: <?= e((string)$plan['learning_outcome']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($plan['activities'])): ?>
                                        <div class="text-secondary small text-truncate" style="max-width: 480px;">
                                            <i class="bi bi-journal-text text-info"></i> कृती: <?= e((string)$plan['activities']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($sub === ''): ?>
                                    <span class="text-muted small">तासिका रिक्त (रिकामा तास)</span>
                                <?php else: ?>
                                    <span class="text-warning small"><i class="bi bi-exclamation-triangle"></i> नोंद उपलब्ध नाही</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($plan): ?>
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-lg"></i> पूर्ण
                                        </span>
                                        <a href="<?= APP_URL ?>/modules/tachan/edit.php?id=<?= $plan['id'] ?>" class="btn btn-outline-secondary btn-sm p-1 rounded-circle" title="संपादन करा">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                <?php elseif ($sub === ''): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <a href="<?= APP_URL ?>/modules/tachan/add.php?class_id=<?= $sel ?>&period=<?= $pn ?>&date=<?= e($date) ?>&class_name=<?= urlencode($clsName) ?>&subject=<?= urlencode($sub) ?>" class="btn btn-outline-warning btn-sm rounded-pill">
                                        <i class="bi bi-plus"></i> नोंद करा
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Class Mode Jodvarg secondary row -->
                        <?php if ($mode === 'class' && $jod && isset($byId[$jod])): 
                            $js = trim((string)($jodTT[$pn] ?? ''));
                            $jplan = $jodRows[$pn] ?? null;
                        ?>
                            <tr class="table-info bg-opacity-25">
                                <td class="text-center text-muted small">—</td>
                                <td>
                                    <span class="badge bg-info-subtle text-info-emphasis border rounded-pill"><?= e($byId[$jod]['name']) ?></span>
                                </td>
                                <td>
                                    <strong><?= $js !== '' ? e(tachan_display_subject($js, $jodMed)) : '—' ?></strong>
                                </td>
                                <td>
                                    <?php if ($jplan): ?>
                                        <div class="fw-bold text-dark mb-1"><?= e((string)($jplan['topic'] ?? '')) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($jplan): ?>
                                        <a href="<?= APP_URL ?>/modules/tachan/edit.php?id=<?= $jplan['id'] ?>" class="btn btn-outline-secondary btn-sm p-1 rounded-circle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Actions / Regeneration Strip -->
<div class="card tachan-card mb-4 border-0 shadow-sm rounded-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h6 class="fw-bold text-dark mb-1">
                    <i class="bi bi-arrow-repeat text-warning me-1"></i> टाचण पुनर्निर्मिती (Regenerate from Bank)
                </h6>
                <p class="text-muted small mb-0">
                    वेळापत्रक किंवा विषय बदलल्यास अधिकृत अभ्यासक्रम बँकेतून आजचे किंवा चालू महिन्याचे टाचण पुन्हा भरा (स्वतः लिहिलेल्या नोंदी सुरक्षित राहतील).
                </p>
            </div>
            <div class="d-flex gap-2">
                <form method="post" onsubmit="return confirm('वेळापत्रकानुसार टाचण पुन्हा तयार करायचे? स्वतः लिहिलेल्या नोंदी सुरक्षित राहतील.');" class="d-inline">
                    <?= tachan_csrf_field() ?>
                    <input type="hidden" name="mode" value="<?= e($mode) ?>">
                    <input type="hidden" name="medium" value="<?= e($medium) ?>">
                    <input type="hidden" name="regen" value="1">
                    <button type="submit" class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3">
                        <i class="bi bi-arrow-clockwise me-1"></i> टाचण पुन्हा भरा
                    </button>
                </form>
                <a href="<?= APP_URL ?>/modules/tachan/timetable.php?mode=<?= $mode ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-calendar3-range me-1"></i> वेळापत्रक बदला
                </a>
            </div>
        </div>
    </div>
</div>

<?php
tachan_layout_end();
