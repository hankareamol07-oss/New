<?php
declare(strict_types=1);

/**
 * Smart School Hub — Old & Future Tachan (जुने व भावी दिवसनिहाय टाचण)
 * Standard backdate.php workflow:
 * - Day-wise (NOT a flat list of individual rows)
 * - Based on timetable (वेळापत्रकानुसार तासिका १ ते ८/९)
 * - Supports past days (जुने टाचण) and future days (भावी नियोजन)
 * - 1-Click Day-Wise Bulk PDF Print (प्रत्येक दिवस स्वतंत्र A4 पानात छपाई)
 * - 1-Click Auto-Fill from Official Curriculum Bank as per Timetable
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db();

$school = getSchool() ?: school_row($sid);
$isPaid = is_tachan_module_purchased($sid);

if (!$isPaid) {
    tachan_layout_start('जुने व पुढील टाचण (सशुल्क)', 'home');
    ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 text-center my-4 mx-auto" style="max-width: 620px;">
        <div class="display-4 text-danger mb-3">🔒</div>
        <h4 class="fw-bold text-dark mb-2">जुने टाचण व एकत्रित डाऊनलोड — स्वतंत्र मॉड्युल आवश्यक</h4>
        <div class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill fw-bold mb-3">
            <i class="bi bi-lock-fill me-1"></i> सशुल्क मॉड्युल (या सुविधेसाठी मोफत चाचणी लागू नाही)
        </div>
        <p class="text-muted small mb-4" style="line-height: 1.8;">
            जुने टाचण पाहणे, मागील व भावी दिवसांचे एकत्रित नियोजन (Bulk Auto-Fill) आणि <strong>दिवसनिहाय एकत्रित PDF छपाई (Bulk PDF Download)</strong> ही सुविधा फक्त <strong>स्वतंत्र दैनिक टाचण वही मॉड्युल (₹४९९)</strong> किंवा <strong>ऑल-इन-वन शाळा पॅक (₹१,४९९)</strong> खरेदी केलेल्या ग्राहकांसाठीच उपलब्ध आहे.<br>
            <strong>या विशेष सुविधेसाठी कोणतीही मोफत चाचणी (Trial) उपलब्ध नाही.</strong>
        </p>
        <div class="d-flex flex-column gap-2 mb-3">
            <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=24" class="btn btn-warning fw-bold text-dark rounded-pill py-2.5 shadow-sm" style="background:#F59E0B; border-color:#F59E0B;">
                <i class="bi bi-cart-check-fill me-1"></i> फक्त दैनिक टाचण वही मॉड्युल खरेदी करा (&#8377;४९९/वर्ष)
            </a>
            <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=4" class="btn btn-outline-primary fw-bold rounded-pill py-2.5">
                <i class="bi bi-rocket-takeoff-fill me-1"></i> संपूर्ण शाळा ऑल-इन-वन पॅक (&#8377;१,४९९/वर्ष)
            </a>
            <a href="<?= APP_URL ?>/modules/tachan/index.php" class="btn btn-link text-muted text-decoration-none small mt-2">
                <i class="bi bi-arrow-left me-1"></i> टाचण हबवर परत जा
            </a>
        </div>
    </div>
    <?php
    tachan_layout_end();
    exit;
}

$teachers = school_teachers($sid);
$teachersById = [];
foreach ($teachers as $t) {
    $teachersById[(int)$t['id']] = $t;
}

$classes = school_classes($sid);
if (!$classes) {
    flash_set('error', 'आधी वर्ग व्यवस्थापनातून वर्ग तयार करा.');
    header('Location: ' . APP_URL . '/modules/classes/index.php');
    exit;
}

$byId = [];
foreach ($classes as $c) {
    $byId[(int)$c['id']] = $c;
}

// Teacher resolution
$defaultTeacherId = isset($teachersById[(int)$user['id']]) 
    ? (int)$user['id'] 
    : ((int)($teachers[0]['id'] ?? $user['id']));

$teacherId = isset($_REQUEST['teacher_id']) && isset($teachersById[(int)$_REQUEST['teacher_id']])
    ? (int)$_REQUEST['teacher_id']
    : $defaultTeacherId;

$pref = tachan_prefs_get($pdo, $sid, $teacherId);
$teacherTT = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
$hasTeacherTT = !empty($teacherTT);

// Mode: Teacher Timetable (शिक्षक वेळापत्रक) vs Class (वर्गनिहाय)
$mode = isset($_REQUEST['mode']) && in_array($_REQUEST['mode'], ['teacher', 'class'], true)
    ? (string)$_REQUEST['mode']
    : (($pref['teach_type'] ?? '') === 'periodwise' || ($hasTeacherTT && !isset($_REQUEST['class_id'])) ? 'teacher' : 'class');

$prefClass = ($pref['class_id'] && isset($byId[$pref['class_id']])) ? $pref['class_id'] : (int)$classes[0]['id'];
$sel = isset($_REQUEST['class_id']) && isset($byId[(int)$_REQUEST['class_id']]) ? (int)$_REQUEST['class_id'] : $prefClass;
$class = $byId[$sel];

// Detect Class Teacher for selected class
$classTeacher = null;
if (!empty($class['class_teacher_id']) && isset($teachersById[(int)$class['class_teacher_id']])) {
    $classTeacher = $teachersById[(int)$class['class_teacher_id']];
} else {
    foreach ($teachers as $t) {
        if (!empty($t['class_assigned']) && ((string)$t['class_assigned'] === (string)($class['std'] ?? '') || (string)$t['class_assigned'] === (string)($class['name'] ?? ''))) {
            $classTeacher = $t;
            break;
        }
    }
}

// Detect Assigned Class for selected teacher
$teacherAssignedClass = null;
if ($teacherId > 0) {
    foreach ($classes as $c) {
        if (!empty($c['class_teacher_id']) && (int)$c['class_teacher_id'] === $teacherId) {
            $teacherAssignedClass = $c;
            break;
        }
    }
    if (!$teacherAssignedClass) {
        $tRow = $teachersById[$teacherId] ?? null;
        if ($tRow && !empty($tRow['class_assigned'])) {
            foreach ($classes as $c) {
                if ((string)$c['std'] === (string)$tRow['class_assigned'] || (string)$c['name'] === (string)$tRow['class_assigned']) {
                    $teacherAssignedClass = $c;
                    break;
                }
            }
        }
    }
}

$medium = isset($_REQUEST['medium']) 
    ? tachan_norm_medium((string)$_REQUEST['medium']) 
    : ($pref['medium'] ?: tachan_norm_medium((string)($class['medium'] ?? 'marathi')));

$dayLen = isset($_REQUEST['dl']) && in_array($_REQUEST['dl'], ['full', 'half', 'upto'], true) ? (string)$_REQUEST['dl'] : $pref['day_length'];
$upto = isset($_REQUEST['upto']) ? max(1, min(9, (int)$_REQUEST['upto'])) : $pref['upto_period'];

$today = date('Y-m-d');

// Quick presets
$preset = (string)($_GET['preset'] ?? '');
if ($preset === 'last_week') {
    $from = date('Y-m-d', strtotime('monday last week'));
    $to   = date('Y-m-d', strtotime('saturday last week'));
} elseif ($preset === 'this_week') {
    $from = date('Y-m-d', strtotime('monday this week'));
    $to   = date('Y-m-d', strtotime('saturday this week'));
} elseif ($preset === 'next_week') {
    $from = date('Y-m-d', strtotime('monday next week'));
    $to   = date('Y-m-d', strtotime('saturday next week'));
} elseif ($preset === 'this_month') {
    $from = date('Y-m-01');
    $to   = date('Y-m-t');
} elseif ($preset === 'last_month') {
    $from = date('Y-m-01', strtotime('first day of last month'));
    $to   = date('Y-m-t', strtotime('last day of last month'));
} else {
    // Default: Past 10 working days up to today
    $from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['from']) 
        ? (string)$_GET['from'] 
        : date('Y-m-d', strtotime('-14 day'));
    $to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['to']) 
        ? (string)$_GET['to'] 
        : $today;
}

// POST: 1-Click Auto-Fill for the range
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && tachan_verify_csrf() && isset($_POST['bulk_autogen'])) {
    $rStart = (string)($_POST['from'] ?? $from);
    $rEnd = (string)($_POST['to'] ?? $to);
    $genCount = 0;

    $postTeacherId = !empty($_POST['teacher_id']) && isset($teachersById[(int)$_POST['teacher_id']]) 
        ? (int)$_POST['teacher_id'] 
        : $teacherId;
    $postClassId = !empty($_POST['class_id']) && isset($byId[(int)$_POST['class_id']]) 
        ? (int)$_POST['class_id'] 
        : $sel;
    $postClass = $byId[$postClassId] ?? $class;

    $cur = strtotime($rStart);
    $endTs = strtotime($rEnd);
    $holq = $pdo->prepare('SELECT name FROM holidays WHERE school_id=? AND date=?');

    while ($cur <= $endTs) {
        $d = date('Y-m-d', $cur);
        $dow = (int)date('N', $cur);
        $holq->execute([$sid, $d]);
        $isHol = ($holq->fetchColumn() !== false);

        if ($dow !== 7 && !$isHol) {
            if ($mode === 'teacher') {
                $rows = tachan_teacher_day_rows($pdo, $sid, $postTeacherId, $d, true, $medium);
            } else {
                $rows = tachan_day_rows($pdo, $sid, $postTeacherId, $postClass, $d, true, $medium);
            }
            $genCount += count($rows);
        }
        $cur = strtotime('+1 day', $cur);
    }

    flash_set('success', "निवडलेल्या कालावधीतील ({$rStart} ते {$rEnd}) टाचण अधिकृत बँकेतून वेळापत्रकानुसार तयार झाले (एकूण {$genCount} तासिका नोंदी).");
    header('Location: ' . APP_URL . '/modules/tachan/backdate.php?' . http_build_query([
        'from' => $rStart,
        'to' => $rEnd,
        'mode' => $mode,
        'class_id' => $postClassId,
        'teacher_id' => $postTeacherId,
        'medium' => $medium,
        'dl' => $dayLen,
        'upto' => $upto,
    ]));
    exit;
}

// Generate calendar dates in range
$rangeDates = [];
$curTs = strtotime($from);
$endTs = strtotime($to);
while ($curTs <= $endTs) {
    $rangeDates[] = date('Y-m-d', $curTs);
    $curTs = strtotime('+1 day', $curTs);
}

// Fetch holidays for the range
$holMap = [];
if ($rangeDates) {
    $minD = min($rangeDates);
    $maxD = max($rangeDates);
    $hstmt = $pdo->prepare('SELECT date, name FROM holidays WHERE school_id=? AND date BETWEEN ? AND ?');
    $hstmt->execute([$sid, $minD, $maxD]);
    foreach ($hstmt->fetchAll(PDO::FETCH_ASSOC) as $hr) {
        $holMap[$hr['date']] = $hr['name'];
    }
}

// Fetch existing lesson plans count per day
$plansCountMap = [];
if ($rangeDates) {
    $minD = min($rangeDates);
    $maxD = max($rangeDates);
    if ($mode === 'teacher') {
        $pStmt = $pdo->prepare('SELECT date, COUNT(*) as c FROM lesson_plans WHERE school_id=? AND teacher_id=? AND date BETWEEN ? AND ? GROUP BY date');
        $pStmt->execute([$sid, $teacherId, $minD, $maxD]);
    } else {
        $pStmt = $pdo->prepare('SELECT date, COUNT(*) as c FROM lesson_plans WHERE school_id=? AND class_id=? AND date BETWEEN ? AND ? GROUP BY date');
        $pStmt->execute([$sid, $sel, $minD, $maxD]);
    }
    foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $plansCountMap[$pr['date']] = (int)$pr['c'];
    }
}

$dnames = [1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार', 7 => 'रविवार'];
$mediums = ['marathi' => 'मराठी माध्यम', 'semi_english' => 'सेमी-इंग्रजी'];
$currTeacherName = resolve_teacher_name($mode === 'class' ? $class : null, $sid, $user);

tachan_layout_start('जुने व पुढील दिवसनिहाय टाचण', 'backdate');
?>

<!-- Header & Quick Preset Bar -->
<div class="card border-0 shadow-sm rounded-4 mb-3 bg-white">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <span class="fs-4">🗓️</span> जुने व पुढील दिवसनिहाय टाचण
                </h4>
                <p class="text-muted small mb-0">
                    वेळापत्रकानुसार प्रत्येक दिवसाचे स्वतंत्र दैनिक टाचण, मागील नोंदी (जुने टाचण) व पुढील भावी नियोजन.
                </p>
            </div>
            
            <!-- Mode Switcher -->
            <div class="btn-group btn-group-sm" role="group">
                <a href="?preset=<?= e($preset) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&mode=class&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" 
                   class="btn <?= $mode === 'class' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-mortarboard me-1"></i> वर्गनिहाय टाचण
                </a>
                <a href="?preset=<?= e($preset) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&mode=teacher&teacher_id=<?= $teacherId ?>&class_id=<?= $sel ?>" 
                   class="btn <?= $mode === 'teacher' ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>"
                   style="<?= $mode === 'teacher' ? 'background:#0284C7; border-color:#0284C7;' : '' ?>">
                    <i class="bi bi-person-lines-fill me-1"></i> शिक्षक वेळापत्रक
                </a>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small fw-bold text-muted">द्रुत निवड :</span>
            <a href="?preset=last_week&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" class="btn btn-sm rounded-pill <?= $preset === 'last_week' ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">⏪ मागील आठवडा</a>
            <a href="?preset=this_week&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" class="btn btn-sm rounded-pill <?= $preset === 'this_week' ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?>">📅 चालू आठवडा</a>
            <a href="?preset=next_week&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" class="btn btn-sm rounded-pill <?= $preset === 'next_week' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">⏩ पुढील आठवडा (भावी नियोजन)</a>
            <a href="?preset=this_month&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" class="btn btn-sm rounded-pill <?= $preset === 'this_month' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">🗓️ चालू महिना</a>
            <a href="?preset=last_month&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" class="btn btn-sm rounded-pill <?= $preset === 'last_month' ? 'btn-secondary fw-bold' : 'btn-outline-secondary' ?>">🗓️ मागील महिना</a>
        </div>

        <!-- Custom Range Filter Form: Option to Select Class OR Teacher -->
        <form method="get" class="row g-2 align-items-end pt-3 border-top">
            <input type="hidden" name="mode" id="filterMode" value="<?= e($mode) ?>">

            <!-- Option 1: वर्ग निवडा (Class Selector) -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted fw-bold mb-1 d-flex justify-content-between align-items-center">
                    <span>🏫 वर्ग निवडा</span>
                    <?php if ($mode === 'class'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">सक्रिय वर्ग</span>
                    <?php endif; ?>
                </label>
                <select name="class_id" class="form-select form-select-sm rounded-3 <?= $mode === 'class' ? 'border-success' : '' ?>" onchange="document.getElementById('filterMode').value='class'; this.form.submit();">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $sel === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($classTeacher): ?>
                    <div class="mt-1" style="font-size: 11px;">
                        <span class="text-muted">वर्गशिक्षक:</span> <strong><?= e($classTeacher['name_mr'] ?: $classTeacher['name']) ?></strong>
                        <?php if ($mode === 'class'): ?>
                            <a href="?mode=teacher&teacher_id=<?= $classTeacher['id'] ?>&class_id=<?= $sel ?>&from=<?= e($from) ?>&to=<?= e($to) ?>" class="text-decoration-none ms-1 text-primary fw-semibold" title="या शिक्षकांचे सर्व वर्ग तासिका टाचण पहा">
                                (टाचण ↗)
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Option 2: किंवा शिक्षक निवडा (Teacher Selector) -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted fw-bold mb-1 d-flex justify-content-between align-items-center">
                    <span>👨‍🏫 शिक्षक निवडा</span>
                    <?php if ($mode === 'teacher'): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 10px;">सक्रिय शिक्षक</span>
                    <?php endif; ?>
                </label>
                <select name="teacher_id" class="form-select form-select-sm rounded-3 <?= $mode === 'teacher' ? 'border-primary' : '' ?>" onchange="document.getElementById('filterMode').value='teacher'; this.form.submit();">
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $teacherId === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name_mr'] ?: $t['name']) ?><?= !empty($t['class_assigned']) ? ' (इ.' . e($t['class_assigned']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($teacherAssignedClass): ?>
                    <div class="mt-1" style="font-size: 11px;">
                        <span class="text-muted">नियुक्त वर्ग:</span> <strong><?= e($teacherAssignedClass['name']) ?></strong>
                        <?php if ($mode === 'teacher'): ?>
                            <a href="?mode=class&class_id=<?= $teacherAssignedClass['id'] ?>&teacher_id=<?= $teacherId ?>&from=<?= e($from) ?>&to=<?= e($to) ?>" class="text-decoration-none ms-1 text-success fw-semibold" title="या वर्गाचे संपूर्ण टाचण पहा">
                                (टाचण ↗)
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label small text-muted fw-bold mb-1">पासून तारीख</label>
                <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm rounded-3">
            </div>

            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label small text-muted fw-bold mb-1">पर्यंत तारीख</label>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm rounded-3">
            </div>

            <div class="col-6 col-sm-3 col-md-1">
                <label class="form-label small text-muted fw-bold mb-1">अर्धा दिवस</label>
                <select name="upto" class="form-select form-select-sm rounded-3">
                    <option value="" <?= empty($upto) || $dayLen === 'full' ? 'selected' : '' ?>>संपूर्ण</option>
                    <?php for ($p = 1; $p <= 9; $p++): ?>
                        <option value="<?= $p ?>" <?= ($dayLen === 'upto' || !empty($_GET['upto'])) && (int)$upto === $p ? 'selected' : '' ?>>ता. <?= $p ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100 rounded-3 fw-bold" style="background:#0284C7; border-color:#0284C7;">
                    दाखवा
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions & Print Bar -->
<div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); border-left: 5px solid #0284C7 !important;">
    <div class="card-body p-3">
        <form id="bulkPrintForm" action="<?= APP_URL ?>/modules/tachan/day_print_bulk.php" method="post" target="_blank" class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <input type="hidden" name="mode" value="<?= e($mode) ?>">
            <input type="hidden" name="class_id" value="<?= $sel ?>">
            <input type="hidden" name="teacher_id" value="<?= $teacherId ?>">
            <input type="hidden" name="medium" value="<?= e($medium) ?>">
            <input type="hidden" name="upto" value="<?= $upto ?>">

            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selectAllCheck" checked onchange="toggleAllCheckboxes(this)">
                    <label class="form-check-label fw-bold text-dark small" for="selectAllCheck">
                        सर्व दिवस निवडा (<span id="chkCount">0</span> दिवस निवडले)
                    </label>
                </div>
                <span class="badge bg-white text-primary border px-2.5 py-1.5 small fw-semibold">
                    <?= e(marathi_date_words($from)) ?> ते <?= e(marathi_date_words($to)) ?>
                </span>
                <span class="badge bg-info-subtle text-info-emphasis border px-2 py-1 small">
                    <?= $mode === 'teacher' ? '👨‍🏫 ' . e($teachersById[$teacherId]['name_mr'] ?? $currTeacherName) : '🏫 ' . e($class['name']) ?>
                </span>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <!-- 1-Click Day-Wise Bulk PDF Print Button -->
                <button type="submit" class="btn btn-danger btn-sm fw-bold rounded-pill px-3 shadow-sm d-flex align-items-center gap-2" style="background:#E11D48; border-color:#E11D48;">
                    <i class="bi bi-printer-fill fs-6"></i>
                    <span>📄 दिवसनिहाय एकत्रित PDF छपाई (Bulk Day Print)</span>
                </button>

                <!-- 1-Click Auto-Fill for Range -->
                <button type="button" onclick="document.getElementById('autoFillForm').submit()" class="btn btn-success btn-sm fw-bold rounded-pill px-3 shadow-sm d-flex align-items-center gap-2" style="background:#16A34A; border-color:#16A34A;">
                    <i class="bi bi-magic fs-6"></i>
                    <span>⚡ वेळापत्रकानुसार टाचण भरा (Auto-Fill)</span>
                </button>
            </div>
        </form>

        <form id="autoFillForm" method="post" onsubmit="return confirm('निवडलेल्या सर्व कामकाजाच्या दिवसांचे टाचण वेळापत्रकानुसार अधिकृत बँकेतून आपोआप भरायचे का?')" class="d-none">
            <?= tachan_csrf_field() ?>
            <input type="hidden" name="bulk_autogen" value="1">
            <input type="hidden" name="from" value="<?= e($from) ?>">
            <input type="hidden" name="to" value="<?= e($to) ?>">
            <input type="hidden" name="mode" value="<?= e($mode) ?>">
            <input type="hidden" name="class_id" value="<?= $sel ?>">
            <input type="hidden" name="teacher_id" value="<?= $teacherId ?>">
            <input type="hidden" name="medium" value="<?= e($medium) ?>">
        </form>
    </div>
</div>

<!-- Day-Wise List: 1 distinct card per day -->
<?php if (empty($rangeDates)): ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
        या कालावधीत कोणतेही दिवस आढळले नाहीत.
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-3 mb-4">
        <?php foreach ($rangeDates as $idx => $d): 
            $dow = (int)date('N', strtotime($d));
            $isSunday = ($dow === 7);
            $holName = $holMap[$d] ?? null;
            $isWorking = (!$isSunday && $holName === null);
            $plansCount = $plansCountMap[$d] ?? 0;
            $isToday = ($d === $today);
            $isFuture = ($d > $today);

            // Fetch day's periods as per timetable
            $daySlots = [];
            if ($isWorking) {
                if ($mode === 'teacher') {
                    $daySlots = $teacherTT[$dow] ?? [];
                } else {
                    $tt = tachan_timetable($pdo, $sid, $class);
                    $daySlots = $tt[$dow] ?? [];
                }
            }
            $expectedCount = count(array_filter($daySlots, fn($s) => is_array($s) ? !empty($s['subject']) : !empty($s)));
            $isComplete = ($expectedCount > 0 && $plansCount >= $expectedCount);
        ?>
        <div class="card border-0 shadow-sm rounded-4 tachan-card overflow-hidden" 
             style="<?= $isToday ? 'border: 2px solid #0284C7 !important;' : '' ?>">
            
            <!-- Day Card Header -->
            <div class="card-header bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <?php if ($isWorking): ?>
                        <input class="form-check-input day-chk" type="checkbox" name="selected_dates[]" value="<?= e($d) ?>" form="bulkPrintForm" <?= $isPaid ? 'checked' : 'disabled' ?> onchange="updateChkCount()">
                    <?php else: ?>
                        <span style="width: 16px;"></span>
                    <?php endif; ?>

                    <span class="fs-5 fw-bold text-dark">
                        <?= e(marathi_date_words($d)) ?>
                    </span>
                    <span class="badge bg-light text-dark border px-2 py-1">
                        <?= e($dnames[$dow] ?? '') ?>
                    </span>

                    <?php if ($isToday): ?>
                        <span class="badge bg-primary px-2 py-1">आज</span>
                    <?php elseif ($isFuture): ?>
                        <span class="badge bg-info-subtle text-info-emphasis border px-2 py-1">भावी दिवस</span>
                    <?php endif; ?>

                    <?php if ($isSunday): ?>
                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">🏖️ रविवार साप्ताहिक सुट्टी</span>
                    <?php elseif ($holName !== null): ?>
                        <span class="badge bg-danger-subtle text-danger border px-2 py-1">🏖️ सुट्टी: <?= e($holName) ?></span>
                    <?php elseif ($isComplete): ?>
                        <span class="badge bg-success-subtle text-success border px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> टाचण पूर्ण (<?= $plansCount ?> तासिका)</span>
                    <?php elseif ($plansCount > 0): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border px-2 py-1"><i class="bi bi-clock me-1"></i> अंशतः भरले (<?= $plansCount ?> / <?= $expectedCount ?>)</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i> टाचण बाकी</span>
                    <?php endif; ?>
                </div>

                <!-- Single Day Actions -->
                <div class="d-flex align-items-center gap-1">
                    <?php if ($isWorking): ?>
                        <a href="<?= APP_URL ?>/modules/tachan/day_print.php?date=<?= e($d) ?>&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" target="_blank" class="btn btn-outline-danger btn-sm py-1 px-2.5 rounded-pill shadow-sm" title="या दिवसाचे १ पानी PDF पहा">
                            <i class="bi bi-file-earmark-pdf"></i> PDF पहा
                        </a>
                        <a href="<?= APP_URL ?>/modules/tachan/day_print.php?date=<?= e($d) ?>&dl_pdf=1&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" target="_blank" class="btn btn-outline-success btn-sm py-1 px-2.5 rounded-pill shadow-sm" title="डाउनलोड">
                            <i class="bi bi-download"></i> डाउनलोड
                        </a>
                        <a href="<?= APP_URL ?>/modules/tachan/day.php?date=<?= e($d) ?>&mode=<?= $mode ?>&class_id=<?= $sel ?>&teacher_id=<?= $teacherId ?>" class="btn btn-primary btn-sm py-1 px-3 rounded-pill fw-bold" style="background:#0284C7; border-color:#0284C7;" title="दिवस टाचण संपादन करा">
                            <i class="bi bi-pencil-square me-1"></i> टाचण उघडा
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Day Card Body: Period breakdown as per timetable -->
            <?php if ($isWorking): ?>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 60px;">तास</th>
                                    <th style="width: 140px;">इयत्ता</th>
                                    <th style="width: 160px;">विषय</th>
                                    <th>घटक / पाठांश (वेळापत्रकानुसार)</th>
                                    <th class="text-center" style="width: 130px;">स्थिती</th>
                                    <th class="text-center" style="width: 110px;">कृती</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Fetch plans for this day
                                $dayPlans = [];
                                if ($mode === 'teacher') {
                                    $dq = $pdo->prepare('SELECT id, period, class_name, subject, topic, learning_outcome FROM lesson_plans WHERE school_id=? AND teacher_id=? AND date=?');
                                    $dq->execute([$sid, $teacherId, $d]);
                                } else {
                                    $dq = $pdo->prepare('SELECT id, period, class_name, subject, topic, learning_outcome FROM lesson_plans WHERE school_id=? AND class_id=? AND date=?');
                                    $dq->execute([$sid, $sel, $d]);
                                }
                                foreach ($dq->fetchAll(PDO::FETCH_ASSOC) as $dp) {
                                    $dayPlans[(int)$dp['period']] = $dp;
                                }

                                for ($p = 1; $p <= 8; $p++):
                                    if ($mode === 'teacher') {
                                        $slot = $daySlots[$p] ?? [];
                                        $pSub = trim((string)($slot['subject'] ?? ''));
                                        $pCls = trim((string)($slot['class_name'] ?? ''));
                                        if ($pCls === '') {
                                            $pCls = !empty($slot['std']) ? "इ. {$slot['std']} वी" : 'वर्ग';
                                        }
                                    } else {
                                        $pSub = trim((string)($daySlots[$p] ?? ''));
                                        $pCls = $class['name'];
                                    }
                                    if ($pSub === '' && !isset($dayPlans[$p])) continue;
                                    $plan = $dayPlans[$p] ?? null;
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-primary">
                                        <?= $p ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($pCls) ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-dark"><?= $pSub !== '' ? e(tachan_display_subject($pSub, $medium)) : '—' ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($plan): ?>
                                            <span class="fw-semibold text-dark"><?= e((string)$plan['topic']) ?></span>
                                            <?php if (!empty($plan['learning_outcome'])): ?>
                                                <div class="text-muted small text-truncate" style="max-width: 450px;">
                                                    <i class="bi bi-bullseye text-primary"></i> <?= e((string)$plan['learning_outcome']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">वेळापत्रकानुसार नोंद बाकी</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($plan): ?>
                                            <span class="badge bg-success-subtle text-success border px-2 py-1"><i class="bi bi-check-lg"></i> पूर्ण</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border px-2 py-1"><i class="bi bi-clock"></i> बाकी</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($plan): ?>
                                            <a href="<?= APP_URL ?>/modules/tachan/edit.php?id=<?= $plan['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 11px;">
                                                <i class="bi bi-pencil"></i> संपादन
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= APP_URL ?>/modules/tachan/add.php?class_id=<?= $sel ?>&period=<?= $p ?>&date=<?= e($d) ?>&class_name=<?= urlencode($pCls) ?>&subject=<?= urlencode($pSub) ?>" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" style="font-size: 11px;">
                                                <i class="bi bi-plus"></i> भरा
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-body py-2 px-3 text-muted small bg-light">
                    <?= $isSunday ? 'रविवारी शाळा बंद असल्यामुळे दैनिक टाचण लागू नाही.' : 'शाळा सुट्टी असल्यामुळे दैनिक टाचण लागू नाही.' ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function toggleAllCheckboxes(master) {
    var cbs = document.querySelectorAll('.day-chk');
    cbs.forEach(function(cb) {
        cb.checked = master.checked;
    });
    updateChkCount();
}

function updateChkCount() {
    var checked = document.querySelectorAll('.day-chk:checked').length;
    var el = document.getElementById('chkCount');
    if (el) el.innerText = checked;
}
document.addEventListener('DOMContentLoaded', updateChkCount);
</script>

<?php
tachan_layout_end();
