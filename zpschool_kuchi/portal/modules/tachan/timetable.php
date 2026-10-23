<?php
declare(strict_types=1);

/**
 * Smart School Hub — Timetable Management (वेळापत्रक नियोजन)
 * 1. शिक्षकाचे स्वतःचे तासिका वेळापत्रक (Teacher's Multi-Class Timetable)
 * 2. वर्ग वेळापत्रक (School Class Timetable)
 * Uses tenant SQLite storage with dual-sync to MySQL.
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/planner_seed.php';
require_once __DIR__ . '/../lib/tachan_engine.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$teacherId = (int)$user['id'];
$pdo = db();
$classes = school_classes($sid);

if (!$classes) {
    flash_set('error', 'आधी वर्ग व्यवस्थापनातून वर्ग तयार करा.');
    header('Location: ' . APP_URL . '/modules/tachan/index.php');
    exit;
}

$prefs = tachan_prefs_get($pdo, $sid, $teacherId);
$mode = isset($_GET['mode']) && in_array($_GET['mode'], ['teacher', 'class'], true) 
    ? (string)$_GET['mode'] 
    : (($prefs['teach_type'] ?? '') === 'single' && !isset($_GET['mode']) ? 'class' : 'teacher');

$medium = isset($_GET['medium']) ? tachan_norm_medium((string)$_GET['medium']) : $prefs['medium'];
$days = planner_days();
$maxP = 9;
$disp = static fn(string $x): string => tachan_display_subject($x, $medium);

// Helper for standard labels
$stdLabels = [1 => 'इ. १ ली', 2 => 'इ. २ री', 3 => 'इ. ३ री', 4 => 'इ. ४ थी', 5 => 'इ. ५ वी', 6 => 'इ. ६ वी', 7 => 'इ. ७ वी', 8 => 'इ. ८ वी'];

// Standard class choices for teacher timetable
$classChoices = [
    '1|0|इ. १ ली' => 'इ. १ ली (पहिली)',
    '2|0|इ. २ री' => 'इ. २ री (दुसरी)',
    '3|0|इ. ३ री' => 'इ. ३ री (तिसरी)',
    '4|0|इ. ४ थी' => 'इ. ४ थी (चौथी)',
    '5|0|इ. ५ वी' => 'इ. ५ वी (पाचवी)',
    '6|0|इ. ६ वी' => 'इ. ६ वी (सहावी)',
    '7|0|इ. ७ वी' => 'इ. ७ वी (सातवी)',
    '8|0|इ. ८ वी' => 'इ. ८ वी (आठवी)',
    // Jodvarg (जोडवर्ग) options
    '1,2|0|जोडवर्ग १+२' => 'जोडवर्ग १+२ (पहिली व दुसरी)',
    '1,3|0|जोडवर्ग १+३' => 'जोडवर्ग १+३ (पहिली व तिसरी)',
    '1,4|0|जोडवर्ग १+४' => 'जोडवर्ग १+४ (पहिली व चौथी)',
    '2,3|0|जोडवर्ग २+३' => 'जोडवर्ग २+३ (दुसरी व तिसरी)',
    '2,4|0|जोडवर्ग २+४' => 'जोडवर्ग २+४ (दुसरी व चौथी)',
    '3,4|0|जोडवर्ग ३+४' => 'जोडवर्ग ३+४ (तिसरी व चौथी)',
    '3,5|0|जोडवर्ग ३+५' => 'जोडवर्ग ३+५ (तिसरी व पाचवी)',
    '4,5|0|जोडवर्ग ४+५' => 'जोडवर्ग ४+५ (चौथी व पाचवी)',
    '5,6|0|जोडवर्ग ५+६' => 'जोडवर्ग ५+६ (पाचवी व सहावी)',
    '6,7|0|जोडवर्ग ६+७' => 'जोडवर्ग ६+७ (सहावी व सातवी)',
    '6,8|0|जोडवर्ग ६+८' => 'जोडवर्ग ६+८ (सहावी व आठवी)',
    '7,8|0|जोडवर्ग ७+८' => 'जोडवर्ग ७+८ (सातवी व आठवी)',
];

// Add school registered classes to choices
foreach ($classes as $c) {
    $cstd = planner_class_std($c);
    $key = "{$cstd}|{$c['id']}|{$c['name']}";
    if (!isset($classChoices[$key])) {
        $classChoices[$key] = "शाळा वर्ग: {$c['name']} (" . class_label($cstd) . ")";
    }
}

// Subject options list
$commonSubjects = [
    'मराठी',
    'इंग्रजी',
    'Maths',
    'गणित',
    'Gen Science',
    'सामान्य विज्ञान',
    'सामाजिक शास्त्रे',
    'इतिहास',
    'भूगोल',
    'हिंदी',
    'परिसर अभ्यास',
    'खेळू,करू,शिकू',
    'शारीरिक शिक्षण',
    'कला शिक्षण',
    'कार्यानुभव',
    'आनंददायी शनिवार उपक्रम',
    'AEP',
    'वाचन/ग्रंथालय',
    'संगणक',
    'व्यावसायिक शिक्षण',
    'संस्कृत',
];

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && tachan_verify_csrf()) {
    $postMode = (string)($_POST['mode'] ?? 'teacher');
    $medium = (string)($_POST['medium'] ?? 'marathi');

    if ($postMode === 'teacher') {
        // 1. Apply Teacher Timetable Template
        if (isset($_POST['apply_teacher_template'])) {
            $tplKey = trim((string)($_POST['template_key'] ?? '6'));
            $tgrid = planner_get_teacher_template_grid($tplKey, $medium);
            $n = tachan_teacher_timetable_save($pdo, $sid, $teacherId, $tgrid, $medium);
            
            $tpls = planner_teacher_timetable_templates();
            $tplName = $tpls[$tplKey]['name'] ?? $tplKey;
            flash_set('success', "शिक्षकाच्या वेळापत्रकाला '{$tplName}' साचा यशस्वीरीत्या लागू झाला ({$n} तासिका जतन).");
            header('Location: ' . APP_URL . '/modules/tachan/timetable.php?mode=teacher&medium=' . urlencode($medium));
            exit;
        }

        // 2. Save Teacher Timetable Grid
        if (isset($_POST['save_teacher_grid']) && is_array($_POST['tcell'] ?? null)) {
            $cells = [];
            foreach ($_POST['tcell'] as $d => $ps) {
                $d = (int)$d;
                if ($d < 1 || $d > 6 || !is_array($ps)) continue;
                foreach ($ps as $p => $slot) {
                    $p = (int)$p;
                    if ($p < 1 || $p > $maxP) continue;
                    $sub = trim((string)($slot['subject'] ?? ''));
                    $info = trim((string)($slot['class_info'] ?? ''));
                    if ($sub === '' || $info === '') {
                        $cells[$d][$p] = ['subject' => ''];
                        continue;
                    }
                    $parts = explode('|', $info);
                    $std = $parts[0] ?? '6';
                    $cid = (int)($parts[1] ?? 0);
                    $cname = $parts[2] ?? ($stdLabels[(int)$std] ?? "इ. {$std} वी");

                    $cells[$d][$p] = [
                        'std' => $std,
                        'class_id' => $cid,
                        'class_name' => $cname,
                        'div' => '-',
                        'subject' => $sub,
                    ];
                }
            }
            $n = tachan_teacher_timetable_save($pdo, $sid, $teacherId, $cells, $medium);
            flash_set('success', "माझे वेळापत्रक यशस्वीरीत्या जतन झाले ({$n} तासिका). दैनिक टाचणामध्ये हे वेळापत्रक आपोआप दिसेल!");
            header('Location: ' . APP_URL . '/modules/tachan/timetable.php?mode=teacher&medium=' . urlencode($medium));
            exit;
        }
    } else {
        // CLASS TIMETABLE POST
        $cid = (int)($_POST['class_id'] ?? 0);
        if (isset($_POST['import'])) {
            $n = planner_import_timetable($pdo, $sid, $classes);
            flash_set('success', "सर्व वर्गांचे वेळापत्रक साचे तयार झाले ({$n} तासिका).");
        } elseif (isset($_POST['save_grid']) && is_array($_POST['cell'] ?? null)) {
            $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
            $repSql = $isSqlite ? 'INSERT OR REPLACE INTO timetable(school_id, class_id, day, period, subject) VALUES (?, ?, ?, ?, ?)' : 'REPLACE INTO timetable(school_id, class_id, day, period, subject) VALUES (?, ?, ?, ?, ?)';
            $rep = $pdo->prepare($repSql);
            $del = $pdo->prepare('DELETE FROM timetable WHERE school_id = ? AND class_id = ? AND day = ? AND period = ?');
            $n = 0;

            foreach ($_POST['cell'] as $d => $ps) {
                $d = (int)$d;
                if ($d < 1 || $d > 6 || !is_array($ps)) continue;
                foreach ($ps as $p => $sub) {
                    $p = (int)$p;
                    if ($p < 1 || $p > planner_period_count($d)) continue;
                    $sub = trim((string)$sub);
                    if ($sub === '') {
                        $del->execute([$sid, $cid, $d, $p]);
                        syncTenantDeleteToMySQL('timetable', ['school_id' => $sid, 'class_id' => $cid, 'day' => $d, 'period' => $p]);
                    } else {
                        $rep->execute([$sid, $cid, $d, $p, $sub]);
                        syncTenantRecordToMySQL('timetable', [
                            'school_id' => $sid,
                            'class_id'  => $cid,
                            'day'       => $d,
                            'period'    => $p,
                            'subject'   => $sub,
                        ], ['school_id', 'class_id', 'day', 'period']);
                        $n++;
                    }
                }
            }
            flash_set('success', "वर्ग वेळापत्रक यशस्वीरीत्या जतन झाले ({$n} तासिका).");
        } elseif (isset($_POST['apply_template'])) {
            $cls = null;
            foreach ($classes as $c) {
                if ((int)$c['id'] === $cid) {
                    $cls = $c;
                    break;
                }
            }
            if ($cls) {
                $pdo->prepare('DELETE FROM timetable WHERE school_id = ? AND class_id = ?')->execute([$sid, $cid]);
                syncTenantDeleteToMySQL('timetable', ['school_id' => $sid, 'class_id' => $cid]);

                $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
                $repSql = $isSqlite ? 'INSERT OR REPLACE INTO timetable(school_id, class_id, day, period, subject) VALUES (?, ?, ?, ?, ?)' : 'REPLACE INTO timetable(school_id, class_id, day, period, subject) VALUES (?, ?, ?, ?, ?)';
                $rep = $pdo->prepare($repSql);
                $count = 0;
                foreach (planner_timetable_for_std(planner_class_std($cls)) as $d => $ps) {
                    foreach ($ps as $p => $sub) {
                        $rep->execute([$sid, $cid, $d, $p, $sub]);
                        syncTenantRecordToMySQL('timetable', [
                            'school_id' => $sid,
                            'class_id'  => $cid,
                            'day'       => $d,
                            'period'    => $p,
                            'subject'   => $sub,
                        ], ['school_id', 'class_id', 'day', 'period']);
                        $count++;
                    }
                }
                flash_set('success', "या वर्गाला शासकीय तयार साचा लागू झाला ({$count} तासिका).");
            }
        }
        header('Location: ' . APP_URL . '/modules/tachan/timetable.php?mode=class&class_id=' . $cid . '&medium=' . urlencode($medium));
        exit;
    }
}

// Data loading depending on mode
if ($mode === 'teacher') {
    $teacherGrid = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
    $isTeacherTemplate = empty($teacherGrid);
    if ($isTeacherTemplate) {
        $defTpl = '6';
        if (($prefs['teach_type'] ?? '') === 'jod') {
            $defTpl = 'jod_6_7';
        }
        $teacherGrid = planner_get_teacher_template_grid($defTpl, $medium);
    }
} else {
    $sel = isset($_GET['class_id']) && (int)$_GET['class_id'] 
        ? (int)$_GET['class_id'] 
        : (int)($prefs['class_id'] ?? ($classes[0]['id'] ?? 0));

    $s = $pdo->prepare('SELECT * FROM timetable WHERE school_id = ? AND class_id = ?');
    $s->execute([$sid, $sel]);
    $grid = [];
    foreach ($s as $r) {
        $grid[(int)$r['day']][(int)$r['period']] = $r['subject'];
    }

    $selClass = null;
    foreach ($classes as $c) {
        if ((int)$c['id'] === $sel) {
            $selClass = $c;
            break;
        }
    }
    $selStd = $selClass ? planner_class_std($selClass) : 5;
    $isClassTemplate = empty($grid);
    if ($isClassTemplate) {
        $grid = planner_timetable_for_std($selStd);
    }

    $subjectOptions = array_values(array_unique(array_merge(
        planner_subjects_for_std($selStd),
        planner_extra_subjects($selStd),
        $commonSubjects
    )));
}

tachan_layout_start($mode === 'teacher' ? 'माझे वेळापत्रक (शिक्षकाचे तासिका वेळापत्रक)' : 'वर्ग वेळापत्रक', 'timetable');
?>

<!-- Mode Navigation Tabs -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <ul class="nav nav-pills p-1 bg-white rounded-pill shadow-sm border">
        <li class="nav-item">
            <a class="nav-link rounded-pill fw-bold <?= $mode === 'teacher' ? 'active' : '' ?>" 
               href="<?= APP_URL ?>/modules/tachan/timetable.php?mode=teacher&medium=<?= urlencode($medium) ?>"
               style="<?= $mode === 'teacher' ? 'background:#167A5B;' : 'color:#475569;' ?>">
                <i class="bi bi-person-lines-fill me-1"></i> माझे वेळापत्रक (शिक्षकाचे तासिका वेळापत्रक)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill fw-bold <?= $mode === 'class' ? 'active' : '' ?>" 
               href="<?= APP_URL ?>/modules/tachan/timetable.php?mode=class&medium=<?= urlencode($medium) ?>"
               style="<?= $mode === 'class' ? 'background:#059669;' : 'color:#475569;' ?>">
                <i class="bi bi-mortarboard-fill me-1"></i> वर्ग वेळापत्रक (शाळा वर्ग)
            </a>
        </li>
    </ul>

    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/modules/tachan/day.php" class="btn btn-primary btn-sm rounded-pill fw-bold shadow-sm" style="background:#167A5B; border-color:#167A5B;">
            <i class="bi bi-calendar2-check-fill me-1"></i> दैनिक टाचण पहा →
        </a>
    </div>
</div>

<?php if ($mode === 'teacher'): ?>
    <!-- TEACHER TIMETABLE VIEW -->
    
    <!-- Filter & Template Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center gap-3">
                <!-- Apply Ready-Made Template -->
                <form method="post" class="d-flex flex-wrap align-items-center gap-2" onsubmit="return confirm('निवडलेला शासकीय साचा तुमच्या वेळापत्रकावर लागू करायचा?')">
                    <?= tachan_csrf_field() ?>
                    <input type="hidden" name="mode" value="teacher">
                    
                    <span class="small fw-bold text-dark"><i class="bi bi-magic text-warning me-1"></i> तयार साचा:</span>
                    <select name="template_key" class="form-select form-select-sm rounded-3 fw-bold" style="max-width: 280px;">
                        <optgroup label="एक वर्ग (इयत्ता १ ते ८)">
                            <?php foreach (planner_teacher_timetable_templates() as $tk => $ti): if ($ti['type'] !== 'single') continue; ?>
                                <option value="<?= $tk ?>" <?= $tk === '6' ? 'selected' : '' ?>><?= e($ti['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="जोडवर्ग साचे (Jodvarg Combined)">
                            <?php foreach (planner_teacher_timetable_templates() as $tk => $ti): if ($ti['type'] !== 'jod') continue; ?>
                                <option value="<?= $tk ?>"><?= e($ti['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>

                    <select name="medium" class="form-select form-select-sm rounded-3" style="width: auto;">
                        <option value="marathi" <?= $medium === 'marathi' ? 'selected' : '' ?>>मराठी माध्यम</option>
                        <option value="semi_english" <?= $medium === 'semi_english' ? 'selected' : '' ?>>सेमी-इंग्रजी</option>
                    </select>

                    <button type="submit" name="apply_teacher_template" value="1" class="btn btn-outline-primary btn-sm rounded-pill fw-bold">
                        <i class="bi bi-arrow-down-circle me-1"></i> साचा लागू करा
                    </button>
                </form>

                <!-- Actions -->
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= APP_URL ?>/modules/tachan/timetable_print.php?mode=teacher&medium=<?= urlencode($medium) ?>" target="_blank" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark">
                        <i class="bi bi-printer-fill me-1"></i> माझे वेळापत्रक PDF
                    </a>
                    <a href="<?= APP_URL ?>/modules/tachan/class_pref.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                        <i class="bi bi-sliders me-1"></i> टाचण सेटिंग्ज
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isTeacherTemplate): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm py-2 px-3 mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            तुमचे वैयक्तिक वेळापत्रक अजून जतन केलेले नाही — तयार शासकीय साचा दाखवला आहे. आवश्यक असल्यास प्रत्येक तासिकेला वर्ग/जोडवर्ग व विषय बदलून खालील <strong>“माझे वेळापत्रक जतन करा”</strong> बटणावर क्लिक करा.
        </div>
    <?php endif; ?>

    <!-- Teacher Grid Form -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-person-check-fill text-warning me-2"></i>
                    शिक्षकाचे तासिका वेळापत्रक (सोमवार ते शनिवार)
                </h5>
                <div class="small text-white-50 mt-1">
                    शिक्षक: <?= e(resolve_teacher_name(null, $sid, $user)) ?> · <?= $medium === 'semi_english' ? 'सेमी-इंग्रजी' : 'मराठी माध्यम' ?>
                </div>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
                <i class="bi bi-shield-check"></i> दैनिक टाचणासाठी थेट जोडलेले
            </span>
        </div>

        <form method="post" class="p-3 p-md-4">
            <?= tachan_csrf_field() ?>
            <input type="hidden" name="mode" value="teacher">
            <input type="hidden" name="medium" value="<?= e($medium) ?>">

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center mb-0">
                    <thead class="table-dark text-nowrap">
                        <tr>
                            <th style="width: 50px;">तासिका</th>
                            <th style="width: 95px;">वेळ (सो–शु)</th>
                            <?php for ($d = 1; $d <= 5; $d++): ?>
                                <th style="min-width: 155px;"><?= e($days[$d]) ?></th>
                            <?php endfor; ?>
                            <th style="width: 95px;">वेळ (शनि)</th>
                            <th style="min-width: 155px;" class="bg-primary-subtle text-primary">शनिवार</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Assembly / Paripath Row -->
                        <tr class="table-light">
                            <td class="fw-bold text-muted">*</td>
                            <td class="small text-muted"><?= e(planner_pariphath_time(1)) ?></td>
                            <?php for ($d = 1; $d <= 5; $d++): ?>
                                <td class="text-center text-primary fw-bold small">
                                    परिपाठ, सुविचार व प्रार्थना
                                </td>
                            <?php endfor; ?>
                            <td class="small text-muted"><?= e(planner_pariphath_time(6)) ?></td>
                            <td class="text-center text-primary fw-bold small bg-primary-subtle">
                                परिपाठ व दिनविशेष
                            </td>
                        </tr>

                        <?php
                        $wk = planner_periods(1);
                        $sat = planner_periods(6);
                        $bw = planner_breaks(1);
                        $bs = planner_breaks(6);

                        for ($p = 1; $p <= $maxP; $p++):
                        ?>
                            <tr>
                                <td class="fw-bold bg-light text-muted fs-6"><?= $p ?></td>
                                <td class="small text-muted"><?= e($wk[$p] ?? '—') ?></td>
                                <?php for ($d = 1; $d <= 5; $d++): 
                                    $curSlot = $teacherGrid[$d][$p] ?? [];
                                    $curSub = trim((string)($curSlot['subject'] ?? ''));
                                    $curStd = trim((string)($curSlot['std'] ?? ''));
                                    $curCid = (int)($curSlot['class_id'] ?? 0);
                                    $curCname = trim((string)($curSlot['class_name'] ?? ''));

                                    // Match with classChoices key
                                    $curChoiceKey = '';
                                    if ($curCid > 0) {
                                        $curChoiceKey = "{$curStd}|{$curCid}|{$curCname}";
                                    } elseif ($curStd !== '') {
                                        $curChoiceKey = "{$curStd}|0|{$curCname}";
                                    }
                                    if (!isset($classChoices[$curChoiceKey]) && $curChoiceKey !== '') {
                                        $classChoices[$curChoiceKey] = $curCname;
                                    }
                                ?>
                                    <td class="p-1">
                                        <div class="d-flex flex-column gap-1">
                                            <!-- Class / Jodvarg selector -->
                                            <select name="tcell[<?= $d ?>][<?= $p ?>][class_info]" class="form-select form-select-sm border-secondary-subtle fs-7 fw-bold rounded-2 text-primary" title="इयत्ता किंवा जोडवर्ग">
                                                <option value="">— वर्ग निवडा —</option>
                                                <?php foreach ($classChoices as $k => $lbl): ?>
                                                    <option value="<?= e($k) ?>" <?= $curChoiceKey === $k ? 'selected' : '' ?>>
                                                        <?= e($lbl) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <!-- Subject selector -->
                                            <select name="tcell[<?= $d ?>][<?= $p ?>][subject]" class="form-select form-select-sm border-secondary-subtle fs-7 text-center rounded-2" title="विषय">
                                                <option value="">— रिकामा —</option>
                                                <?php 
                                                $subjList = $commonSubjects;
                                                if ($curSub !== '' && !in_array($curSub, $subjList, true)) {
                                                    $subjList[] = $curSub;
                                                }
                                                foreach ($subjList as $so): ?>
                                                    <option value="<?= e($so) ?>" <?= $curSub === $so ? 'selected' : '' ?>>
                                                        <?= e($disp($so)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                <?php endfor; ?>

                                <!-- Saturday column -->
                                <td class="small text-muted"><?= e($sat[$p] ?? '—') ?></td>
                                <td class="p-1 <?= isset($sat[$p]) ? 'bg-primary-subtle' : 'bg-light text-muted' ?>">
                                    <?php if (isset($sat[$p])): 
                                        $curSlot = $teacherGrid[6][$p] ?? [];
                                        $curSub = trim((string)($curSlot['subject'] ?? ''));
                                        $curStd = trim((string)($curSlot['std'] ?? ''));
                                        $curCid = (int)($curSlot['class_id'] ?? 0);
                                        $curCname = trim((string)($curSlot['class_name'] ?? ''));

                                        $curChoiceKey = '';
                                        if ($curCid > 0) {
                                            $curChoiceKey = "{$curStd}|{$curCid}|{$curCname}";
                                        } elseif ($curStd !== '') {
                                            $curChoiceKey = "{$curStd}|0|{$curCname}";
                                        }
                                        if (!isset($classChoices[$curChoiceKey]) && $curChoiceKey !== '') {
                                            $classChoices[$curChoiceKey] = $curCname;
                                        }
                                    ?>
                                        <div class="d-flex flex-column gap-1">
                                            <select name="tcell[6][<?= $p ?>][class_info]" class="form-select form-select-sm border-secondary-subtle fs-7 fw-bold rounded-2 text-primary">
                                                <option value="">— वर्ग —</option>
                                                <?php foreach ($classChoices as $k => $lbl): ?>
                                                    <option value="<?= e($k) ?>" <?= $curChoiceKey === $k ? 'selected' : '' ?>>
                                                        <?= e($lbl) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <select name="tcell[6][<?= $p ?>][subject]" class="form-select form-select-sm border-secondary-subtle fs-7 text-center rounded-2">
                                                <option value="">— रिकामा —</option>
                                                <?php 
                                                $subjList = $commonSubjects;
                                                if ($curSub !== '' && !in_array($curSub, $subjList, true)) {
                                                    $subjList[] = $curSub;
                                                }
                                                foreach ($subjList as $so): ?>
                                                    <option value="<?= e($so) ?>" <?= $curSub === $so ? 'selected' : '' ?>>
                                                        <?= e($disp($so)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <span class="small text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if (isset($bw[$p]) || isset($bs[$p])): ?>
                                <tr class="table-warning">
                                    <td colspan="7" class="fw-bold text-center small text-dark py-1">
                                        <i class="bi bi-cup-hot-fill me-1 text-warning"></i> <?= e($bw[$p] ?? '') ?>
                                    </td>
                                    <td colspan="2" class="fw-bold text-center small text-dark py-1">
                                        <?= e($bs[$p] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    वेळापत्रक जतन केल्यावर <strong>दैनिक टाचण (Daily Tachan)</strong> मध्ये तुमच्या प्रत्येक तासिकेचे अधिकृत पाठ, अध्ययन निष्पत्ती व कृती आपोआप तयार होतात!
                </div>
                <button type="submit" name="save_teacher_grid" value="1" class="btn btn-success btn-lg px-5 py-2 fw-bold rounded-pill shadow-sm">
                    <i class="bi bi-check-circle-fill me-2"></i> माझे वेळापत्रक जतन करा
                </button>
            </div>
        </form>
    </div>

    <!-- Period Counts Breakdown -->
    <?php
    $classCounts = [];
    $subCounts = [];
    $totalTeacherPeriods = 0;
    foreach ($teacherGrid as $d => $ps) {
        foreach ($ps as $p => $slot) {
            $sub = trim((string)($slot['subject'] ?? ''));
            $cname = trim((string)($slot['class_name'] ?? ''));
            if ($sub !== '') {
                $subName = $disp($sub);
                $subCounts[$subName] = ($subCounts[$subName] ?? 0) + 1;
                $clsName = $cname !== '' ? $cname : 'इतर';
                $classCounts[$clsName] = ($classCounts[$clsName] ?? 0) + 1;
                $totalTeacherPeriods++;
            }
        }
    }
    ksort($subCounts);
    ksort($classCounts);
    ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-mortarboard-fill text-primary me-2"></i>
                        वर्गनिहाय आठवडी तासिका संख्या (एकूण: <?= $totalTeacherPeriods ?>)
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-start">वर्ग / जोडवर्ग</th>
                                    <th>तासिका संख्या</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classCounts as $cn => $cnt): ?>
                                    <tr>
                                        <td class="text-start fw-bold"><?= e($cn) ?></td>
                                        <td class="fw-bold text-primary"><?= $cnt ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary fw-bold">
                                    <td class="text-start">एकूण तासिका</td>
                                    <td><?= $totalTeacherPeriods ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-bar-chart-fill text-success me-2"></i>
                        विषयनिहाय आठवडी तासिका संख्या
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-start">विषय</th>
                                    <th>तासिका</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subCounts as $sn => $cnt): ?>
                                    <tr>
                                        <td class="text-start"><?= e($sn) ?></td>
                                        <td class="fw-bold"><?= $cnt ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- CLASS TIMETABLE VIEW -->

    <!-- Filter & Quick Actions Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
                <!-- Selector Form -->
                <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="hidden" name="mode" value="class">
                    <div>
                        <select name="class_id" onchange="this.form.submit()" class="form-select form-select-sm rounded-3 fw-bold">
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $sel === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?> (<?= e(class_label(planner_class_std($c))) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <select name="medium" onchange="this.form.submit()" class="form-select form-select-sm rounded-3">
                            <option value="marathi" <?= $medium === 'marathi' ? 'selected' : '' ?>>मराठी माध्यम</option>
                            <option value="semi_english" <?= $medium === 'semi_english' ? 'selected' : '' ?>>सेमी-इंग्रजी</option>
                        </select>
                    </div>
                </form>

                <!-- Fast Actions -->
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" onsubmit="return confirm('या वर्गाचे सध्याचे वेळापत्रक तयार साच्याने बदलणार?')" class="d-inline">
                        <?= tachan_csrf_field() ?>
                        <input type="hidden" name="mode" value="class">
                        <input type="hidden" name="class_id" value="<?= $sel ?>">
                        <input type="hidden" name="medium" value="<?= e($medium) ?>">
                        <button type="submit" name="apply_template" value="1" class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="bi bi-magic me-1"></i> तयार साचा लागू करा (हा वर्ग)
                        </button>
                    </form>

                    <form method="post" onsubmit="return confirm('वेळापत्रक नसलेल्या सर्व वर्गांसाठी साचा तयार करायचा?')" class="d-inline">
                        <?= tachan_csrf_field() ?>
                        <input type="hidden" name="mode" value="class">
                        <input type="hidden" name="class_id" value="<?= $sel ?>">
                        <input type="hidden" name="medium" value="<?= e($medium) ?>">
                        <button type="submit" name="import" value="1" class="btn btn-outline-dark btn-sm rounded-pill">
                            <i class="bi bi-collection-fill me-1"></i> सर्व वर्गांचा साचा तयार करा
                        </button>
                    </form>

                    <a href="<?= APP_URL ?>/modules/tachan/timetable_print.php?class_id=<?= $sel ?>&medium=<?= urlencode($medium) ?>" target="_blank" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark">
                        <i class="bi bi-printer-fill me-1"></i> वर्ग वेळापत्रक PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isClassTemplate): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm py-2 px-3 mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            या वर्गाचे वेळापत्रक अजून जतन केलेले नाही — तयार शासकीय साचा दाखवला आहे. आवश्यक ते बदल करून खाली <strong>“वेळापत्रक जतन करा”</strong> बटणावर क्लिक करा.
        </div>
    <?php endif; ?>

    <!-- Class Grid Form -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-calendar3-range text-warning me-2"></i>
                    <?= e($selClass['name'] ?? 'वर्ग') ?> वेळापत्रक (सोमवार ते शनिवार)
                </h5>
                <div class="small text-white-50 mt-1">इयत्ता <?= e(class_label($selStd)) ?> · <?= $medium === 'semi_english' ? 'सेमी-इंग्रजी' : 'मराठी माध्यम' ?></div>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
                <i class="bi bi-shield-check"></i> सुरक्षित शाळा डेटाबेस
            </span>
        </div>

        <form method="post" class="p-3 p-md-4">
            <?= tachan_csrf_field() ?>
            <input type="hidden" name="mode" value="class">
            <input type="hidden" name="class_id" value="<?= $sel ?>">
            <input type="hidden" name="medium" value="<?= e($medium) ?>">

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center mb-0">
                    <thead class="table-dark text-nowrap">
                        <tr>
                            <th style="width: 50px;">तासिका</th>
                            <th style="width: 100px;">वेळ (सो–शु)</th>
                            <?php for ($d = 1; $d <= 5; $d++): ?>
                                <th style="min-width: 130px;"><?= e($days[$d]) ?></th>
                            <?php endfor; ?>
                            <th style="width: 100px;">वेळ (शनि)</th>
                            <th style="min-width: 130px;" class="bg-primary-subtle text-primary">शनिवार</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-light">
                            <td class="fw-bold text-muted">*</td>
                            <td class="small text-muted"><?= e(planner_pariphath_time(1)) ?></td>
                            <?php for ($d = 1; $d <= 5; $d++): ?>
                                <td class="text-center text-primary fw-bold small">परिपाठ व प्रार्थना</td>
                            <?php endfor; ?>
                            <td class="small text-muted"><?= e(planner_pariphath_time(6)) ?></td>
                            <td class="text-center text-primary fw-bold small bg-primary-subtle">परिपाठ</td>
                        </tr>

                        <?php
                        $wk = planner_periods(1);
                        $sat = planner_periods(6);
                        $bw = planner_breaks(1);
                        $bs = planner_breaks(6);

                        $cellSel = static function(int $d, int $p) use ($grid, $subjectOptions, $disp): void {
                            $cur = trim((string)($grid[$d][$p] ?? ''));
                            ?>
                            <select name="cell[<?= $d ?>][<?= $p ?>]" class="form-select form-select-sm border-secondary-subtle fs-7 text-center rounded-2">
                                <option value="">— रिकामा —</option>
                                <?php foreach ($subjectOptions as $so): ?>
                                    <option value="<?= e($so) ?>" <?= $cur === $so ? 'selected' : '' ?>>
                                        <?= e($disp($so)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php
                        };

                        for ($p = 1; $p <= $maxP; $p++):
                        ?>
                            <tr>
                                <td class="fw-bold bg-light text-muted"><?= $p ?></td>
                                <td class="small text-muted"><?= e($wk[$p] ?? '—') ?></td>
                                <?php for ($d = 1; $d <= 5; $d++): ?>
                                    <td class="p-1"><?php $cellSel($d, $p); ?></td>
                                <?php endfor; ?>
                                <td class="small text-muted"><?= e($sat[$p] ?? '—') ?></td>
                                <td class="p-1 <?= isset($sat[$p]) ? 'bg-primary-subtle' : 'bg-light text-muted' ?>">
                                    <?php if (isset($sat[$p])): ?>
                                        <?php $cellSel(6, $p); ?>
                                    <?php else: ?>
                                        <span class="small text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if (isset($bw[$p]) || isset($bs[$p])): ?>
                                <tr class="table-warning">
                                    <td colspan="7" class="fw-bold text-center small text-dark py-1">
                                        <i class="bi bi-cup-hot-fill me-1 text-warning"></i> <?= e($bw[$p] ?? '') ?>
                                    </td>
                                    <td colspan="2" class="fw-bold text-center small text-dark py-1">
                                        <?= e($bs[$p] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    बदल केल्यानंतर खालील <strong>वेळापत्रक जतन करा</strong> बटण दाबल्यास शाळा डेटाबेस व मुख्य सर्व्हरवर त्वरित नोंद होते.
                </div>
                <button type="submit" name="save_grid" value="1" class="btn btn-success btn-lg px-5 py-2 fw-bold rounded-pill shadow-sm">
                    <i class="bi bi-check-circle-fill me-2"></i> वेळापत्रक जतन करा
                </button>
            </div>
        </form>
    </div>

    <!-- Class Subject-wise Period Count Summary -->
    <?php
    $counts = [];
    foreach ($grid as $ps) {
        foreach ($ps as $x) {
            $x = trim((string)$x);
            if ($x !== '') {
                $name = $disp($x);
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }
    }
    ksort($counts);
    $totalPeriods = array_sum($counts);
    ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <h6 class="fw-bold text-dark mb-3">
                <i class="bi bi-bar-chart-fill text-primary me-2"></i>
                विषयनिहाय आठवडी तासिका संख्या (एकूण: <?= $totalPeriods ?>)
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered text-center align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">विषय</th>
                            <?php foreach ($counts as $sub => $cnt): ?>
                                <th><?= e((string)$sub) ?></th>
                            <?php endforeach; ?>
                            <th class="table-primary">एकूण</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="text-start table-light">तासिका</th>
                            <?php foreach ($counts as $cnt): ?>
                                <td class="fw-bold"><?= $cnt ?></td>
                            <?php endforeach; ?>
                            <td class="fw-bold table-primary fs-6"><?= $totalPeriods ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-3 mb-0">
                <strong>AEP :</strong> अतिरिक्त समृद्धीकरण तासिका (उपचारात्मक अध्यापन, स्पर्धा परीक्षा तयारी व सराव).
            </p>
        </div>
    </div>
<?php endif; ?>

<?php tachan_layout_end(); ?>
