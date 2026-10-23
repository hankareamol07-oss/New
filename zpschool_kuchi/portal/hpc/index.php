<?php
/**
 * Smart School Hub — Unified HPC Module (इयत्ता १ ते ८ — NEP 2020 / SCERT Maharashtra)
 * 3 Stages:
 * 1. पायाभूत स्तर (Foundational Stage) — std 1–2 (6 विकास क्षेत्रे / 69 क्षमता / 19-page booklet)
 * 2. पूर्वतयारी स्तर (Preparatory Stage) — std 3–5 (6 विषय / 48 ध्येये / 144 क्षमता)
 * 3. पूर्व-माध्यमिक स्तर (Middle Stage) — std 6–8 (9 विषय / 74 ध्येये / 181 क्षमता / Progress Wheel & NCrF)
 */
$page_title = 'HPC सर्वांगीण प्रगती पत्रक (इ. १ ते ८)';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cce_functions.php';
if (file_exists(__DIR__ . '/../includes/tenant_db.php')) {
    require_once __DIR__ . '/../includes/tenant_db.php';
}
if (file_exists(__DIR__ . '/../includes/hpc_curriculum.php')) {
    require_once __DIR__ . '/../includes/hpc_curriculum.php';
}
if (!function_exists('hpc_stage_for_std')) {
    function hpc_stage_for_std(int $std): string {
        if ($std <= 2) return 'foundational';
        if ($std <= 5) return 'preparatory';
        return 'middle';
    }
}
if (!function_exists('can_access_hpc_class')) {
    function can_access_hpc_class(int $std, ?int $school_id = null): array {
        return ['allowed' => ($std <= 2), 'is_higher' => ($std >= 3), 'message' => ($std <= 2 ? 'मोफत' : 'सशुल्क योजना आवश्यक')];
    }
}
requireLogin();

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

// Stages definition & metadata
$STAGES = [
    'foundational' => [
        'key' => 'foundational',
        'name_mr' => 'पायाभूत स्तर',
        'name_en' => 'Foundational Stage',
        'stds' => [1, 2],
        'default_std' => 1,
        'summary_mr' => '६ विकास क्षेत्रे · १३ ध्येये · ६९ क्षमता',
        'desc_mr' => 'इयत्ता १ व २ साठी पायाभूत स्तरावरील समग्र मूल्यमापन, ३-स्टेप रुब्रिक, शिक्षक अभिप्राय, कामगिरी आणि अधिकृत १९-पानी रंगीत प्रगतिपत्रक.',
        'badge_class' => 'bg-warning text-dark',
        'border_color' => '#f59e0b',
        'icon' => 'bi-star-fill text-warning',
        'subjects_count' => 6,
        'goals_count' => 13,
        'comp_count' => 69,
        'has_wheel' => false,
        'has_ncrf' => false,
    ],
    'preparatory' => [
        'key' => 'preparatory',
        'name_mr' => 'पूर्वतयारी स्तर',
        'name_en' => 'Preparatory Stage',
        'stds' => [3, 4, 5],
        'default_std' => 3,
        'summary_mr' => '६ विषय · ४८ ध्येये · १४४ क्षमता',
        'desc_mr' => 'इयत्ता ३ ते ५ साठी विषयनिहाय मूल्यमापन (६ विषय), AI रुब्रिक बँक, नवीन स्टेज फॉर्म आणि अधिकृत SCERT A4 अहवाल.',
        'badge_class' => 'bg-info text-dark',
        'border_color' => '#0284c7',
        'icon' => 'bi-book-half text-info',
        'subjects_count' => 6,
        'goals_count' => 48,
        'comp_count' => 144,
        'has_wheel' => false,
        'has_ncrf' => true,
    ],
    'middle' => [
        'key' => 'middle',
        'name_mr' => 'पूर्व-माध्यमिक स्तर',
        'name_en' => 'Middle Stage',
        'stds' => [6, 7, 8],
        'default_std' => 6,
        'summary_mr' => '९ विषय · ७४ ध्येये · १८१ क्षमता · प्रोग्रेस व्हील',
        'desc_mr' => 'इयत्ता ६ ते ८ साठी ९ विषय, बहुआयामी प्रोग्रेस व्हील (शिक्षक-स्वयं-सहाध्यायी), NCrF क्रेडिट्स, स्टेज फॉर्म व SCERT अहवाल.',
        'badge_class' => 'bg-success text-white',
        'border_color' => '#10b981',
        'icon' => 'bi-pie-chart-fill text-success',
        'subjects_count' => 9,
        'goals_count' => 74,
        'comp_count' => 181,
        'has_wheel' => true,
        'has_ncrf' => true,
    ],
];

// Filter stages and standards according to school's configured class range
$school_range = get_school_std_range($school_id);
$min_std = array_key_first($school_range) ?: 1;
$max_std = array_key_last($school_range) ?: 8;

foreach ($STAGES as $k => &$stg) {
    $stg['stds'] = array_values(array_filter($stg['stds'], fn($s) => $s >= $min_std && $s <= $max_std));
    if (!empty($stg['stds']) && !in_array($stg['default_std'], $stg['stds'], true)) {
        $stg['default_std'] = $stg['stds'][0];
    }
}
unset($stg);

// Exclude stages with no standards in this school's range
$STAGES = array_filter($STAGES, fn($stg) => !empty($stg['stds']));
if (empty($STAGES)) {
    $STAGES['foundational'] = [
        'key' => 'foundational', 'name_mr' => 'पायाभूत स्तर', 'name_en' => 'Foundational Stage',
        'stds' => [$min_std], 'default_std' => $min_std, 'summary_mr' => 'पायाभूत स्तर',
        'desc_mr' => '', 'badge_class' => 'bg-warning text-dark', 'border_color' => '#f59e0b',
        'icon' => 'bi-star-fill text-warning', 'subjects_count' => 6, 'goals_count' => 13, 'comp_count' => 69,
        'has_wheel' => false, 'has_ncrf' => false,
    ];
}

// Determine selected standard bounded by school range
$std = (int) ($_GET['std'] ?? 0);
$selected_stage = trim($_GET['stage'] ?? '');

$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    $std = in_array((int)($_GET['std'] ?? 0), $tScope['stds'], true) ? (int)$_GET['std'] : $tScope['std'];
    $stage_key = hpc_stage_for_std($std);
    $STAGES = array_filter($STAGES, function($stg) use ($tScope) {
        return !empty(array_intersect($stg['stds'], $tScope['stds']));
    });
    foreach ($STAGES as $k => &$stg) {
        $stg['stds'] = array_values(array_intersect($stg['stds'], $tScope['stds']));
        if (!in_array($stg['default_std'], $stg['stds'], true) && !empty($stg['stds'])) {
            $stg['default_std'] = $stg['stds'][0];
        }
    }
    unset($stg);
    if (!isset($STAGES[$stage_key])) {
        $stage_key = array_key_first($STAGES);
    }
} elseif ($std >= $min_std && $std <= $max_std) {
    $stage_key = hpc_stage_for_std($std);
    if (!isset($STAGES[$stage_key])) {
        $stage_key = array_key_first($STAGES);
    }
} elseif (isset($STAGES[$selected_stage])) {
    $stage_key = $selected_stage;
    $std = $STAGES[$stage_key]['default_std'];
} else {
    $stage_key = array_key_first($STAGES);
    $std = $STAGES[$stage_key]['default_std'];
}
$current_stage = $STAGES[$stage_key];

$hpc_access = can_access_hpc_class($std, $school_id);
$is_higher_locked = !$hpc_access['allowed'];

$found_access = can_access_hpc_class(1, $school_id);
$prep_access = can_access_hpc_class(3, $school_id);
$mid_access = can_access_hpc_class(6, $school_id);

$grade_labels = [
    1 => 'इयत्ता १ (पहिली)',
    2 => 'इयत्ता २ (दुसरी)',
    3 => 'इयत्ता ३ (तिसरी)',
    4 => 'इयत्ता ४ (चौथी)',
    5 => 'इयत्ता ५ (पाचवी)',
    6 => 'इयत्ता ६ (सहावी)',
    7 => 'इयत्ता ७ (सातवी)',
    8 => 'इयत्ता ८ (आठवी)',
];
$grade_short = [
    1 => 'इयत्ता १', 2 => 'इयत्ता २', 3 => 'इयत्ता ३', 4 => 'इयत्ता ४',
    5 => 'इयत्ता ५', 6 => 'इयत्ता ६', 7 => 'इयत्ता ७', 8 => 'इयत्ता ८',
];
$grade_str = $grade_short[$std] ?? ('इयत्ता ' . $std);

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$division_filter = trim($_GET['division'] ?? '');

// Fetch all students of this standard
$all_students = cce_students($school_id, $std);

// Fetch existing HPC cards for this school in academic year
$cards_by_student = [];
try {
    $card_stmt = $db->prepare("SELECT id, student_id, status, stage_key, std, print_format, updated_at FROM hpc_cards WHERE school_id = ? AND academic_year = ?");
    $card_stmt->execute([$school_id, $year]);
    foreach ($card_stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cards_by_student[(int) $c['student_id']] = $c;
    }
} catch (Throwable $e) {
    try {
        $db->exec("ALTER TABLE `hpc_cards` ADD COLUMN `stage_key` VARCHAR(16) NULL DEFAULT NULL AFTER `academic_year`");
    } catch (Throwable $e1) {}
    try {
        $db->exec("ALTER TABLE `hpc_cards` ADD COLUMN `std` TINYINT(4) NULL DEFAULT NULL AFTER `stage_key`");
    } catch (Throwable $e2) {}
    try {
        $db->exec("ALTER TABLE `hpc_cards` ADD COLUMN `print_format` VARCHAR(32) NULL DEFAULT NULL AFTER `status`");
    } catch (Throwable $e3) {}

    try {
        $card_stmt = $db->prepare("SELECT id, student_id, status, updated_at FROM hpc_cards WHERE school_id = ? AND academic_year = ?");
        $card_stmt->execute([$school_id, $year]);
        foreach ($card_stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $c['stage_key'] = $c['stage_key'] ?? hpc_stage_for_std($std);
            $c['std'] = $c['std'] ?? $std;
            $c['print_format'] = $c['print_format'] ?? 'default';
            $cards_by_student[(int) $c['student_id']] = $c;
        }
    } catch (Throwable $e4) {
        $cards_by_student = [];
    }
}

// School-wide Quota & Card Usage
$hpc_quota = getSchoolStudentQuota($school_id);
$quota_max = (int)$hpc_quota['max'];
$is_quota_unlimited = (bool)$hpc_quota['is_unlimited'];
$total_school_cards_count = count($cards_by_student);
$is_hpc_quota_full = (!$is_quota_unlimited && $total_school_cards_count >= $quota_max);

// Compute metrics for this standard
$total_count = count($all_students);
$completed_count = 0;
$draft_count = 0;
$pending_count = 0;

$filtered_students = [];
$divisions = [];

foreach ($all_students as $s) {
    $sid = (int) $s['id'];
    $c = $cards_by_student[$sid] ?? null;
    $sec = trim((string) ($s['section'] ?? ''));
    if ($sec !== '' && !in_array($sec, $divisions)) {
        $divisions[] = $sec;
    }
    
    $status = 'pending';
    if ($c) {
        $status = $c['status'] === 'completed' ? 'completed' : 'draft';
    }
    
    if ($status === 'completed') $completed_count++;
    elseif ($status === 'draft') $draft_count++;
    else $pending_count++;

    // Filtering
    if ($division_filter !== '' && $sec !== $division_filter) continue;
    if ($status_filter !== '' && $status !== $status_filter) continue;
    if ($search !== '') {
        $name = ($s['name_mr'] ?? '') . ' ' . ($s['name'] ?? '') . ' ' . ($s['roll_no'] ?? '');
        if (stripos($name, $search) === false) continue;
    }

    $filtered_students[] = [
        'student' => $s,
        'card' => $c,
        'status' => $status
    ];
}

if (!function_exists('computeStudentDataProgress')) {
    function computeStudentDataProgress($s): array {
        return ['is_extra_sparse' => false, 'pct' => 100, 'filled' => 10, 'total' => 10];
    }
}

$has_sparse_students = false;
$students_progress = [];
foreach ($all_students as $st_item) {
    $p = computeStudentDataProgress($st_item);
    $students_progress[(int)$st_item['id']] = $p;
    if ($p['is_extra_sparse']) {
        $has_sparse_students = true;
    }
}

$filled_count = $completed_count + $draft_count;
$progress_pct = $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Top Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge <?= $current_stage['badge_class'] ?> px-2 py-1">
                    <i class="bi <?= $current_stage['icon'] ?>"></i> <?= $current_stage['name_mr'] ?> (<?= $current_stage['name_en'] ?>)
                </span>
                <span class="badge bg-light text-muted border">NEP 2020 / SCERT महाराष्ट्र</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold"><?= $grade_str ?></span>
            </div>
            <h2 class="mb-1 text-dark fw-bold">
                <i class="bi bi-card-checklist text-primary"></i> HPC सर्वांगीण प्रगती पत्रक
            </h2>
            <p class="text-muted mb-0 small">
                <?= $current_stage['desc_mr'] ?>
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="<?= APP_URL ?>/students/add.php?std=<?= $std ?>&return=hpc" class="btn btn-primary fw-bold shadow-sm d-inline-flex align-items-center gap-1">
                <i class="bi bi-person-plus-fill"></i> विद्यार्थी नोंदणी
            </a>
            <a href="<?= APP_URL ?>/modules/classes/index.php?tab=classes&std=<?= $std ?>" class="btn btn-outline-secondary fw-semibold shadow-sm d-inline-flex align-items-center gap-1" title="वर्ग व तुकडी व्यवस्थापन">
                <i class="bi bi-people-fill"></i> वर्ग व्यवस्थापन
            </a>
            <?php if ($is_higher_locked): ?>
                <a href="<?= APP_URL ?>/subscription/plans.php" class="btn btn-warning text-dark fw-bold shadow-sm">
                    <i class="bi bi-gem me-1"></i> प्रो योजना निवडा (Upgrade)
                </a>
            <?php elseif ($stage_key === 'foundational'): ?>
                <a href="<?= APP_URL ?>/hpc/wizard.php?grade=<?= urlencode($grade_str) ?>" class="btn btn-warning fw-bold text-dark shadow-sm">
                    <i class="bi bi-lightning-charge-fill"></i> ३-पायऱ्या रुब्रिक विझार्ड
                </a>
                <a href="<?= APP_URL ?>/hpc/create.php" class="btn btn-outline-primary shadow-sm">
                    <i class="bi bi-plus-circle"></i> नवीन HPC फॉर्म
                </a>
                <a href="<?= APP_URL ?>/cards/print_hpc_19.php?grade=<?= urlencode($grade_str) ?>" target="_blank" class="btn btn-success shadow-sm" onclick="return handleBatchPrintWithExtraCheck(event, this.href, <?= $has_sparse_students ? 'true' : 'false' ?>);">
                    <i class="bi bi-printer-fill"></i> <?= $grade_str ?> बॅच प्रिंट (१९ पाने)
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/hpc/wizard.php?grade=<?= urlencode($grade_str) ?>" class="btn btn-outline-primary shadow-sm">
                    <i class="bi bi-list-task"></i> स्टेज विझार्ड
                </a>
                <a href="<?= APP_URL ?>/cards/print_hpc_stage.php?grade=<?= urlencode($grade_str) ?>" target="_blank" class="btn btn-success shadow-sm" onclick="return handleBatchPrintWithExtraCheck(event, this.href, <?= $has_sparse_students ? 'true' : 'false' ?>);">
                    <i class="bi bi-printer-fill"></i> <?= $grade_str ?> SCERT A4 बॅच प्रिंट
                </a>
                <a href="<?= APP_URL ?>/cards/print_hpc_stage.php?grade=<?= urlencode($grade_str) ?>&mode=compact" target="_blank" class="btn btn-outline-success shadow-sm" title="१ पान प्रति विषय संक्षिप्त मांडणी" onclick="return handleBatchPrintWithExtraCheck(event, this.href, <?= $has_sparse_students ? 'true' : 'false' ?>);">
                    <i class="bi bi-file-earmark-ruled"></i> संक्षिप्त प्रिंट
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3-STAGE MASTER TABS (Foundational, Preparatory, Middle) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-2 p-md-3 bg-light rounded-3">
            <!-- Stage Selector Cards / Tabs -->
            <div class="row g-2 mb-3">
                <?php 
                $colClass = count($STAGES) === 1 ? 'col-12' : (count($STAGES) === 2 ? 'col-12 col-md-6' : 'col-12 col-md-4');
                foreach ($STAGES as $sKey => $stgItem): 
                    $isActive = ($stage_key === $sKey);
                ?>
                <div class="<?= $colClass ?>">
                    <a href="?stage=<?= $sKey ?>&std=<?= $stgItem['default_std'] ?>" class="text-decoration-none d-block h-100">
                        <div class="card h-100 border-2 transition-card <?= $isActive ? 'border-primary shadow-sm bg-white' : 'border-light bg-white opacity-75' ?>">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge <?= $stgItem['badge_class'] ?> fw-bold">इ. <?= implode(', ', array_map('to_mr_digits', $stgItem['stds'])) ?></span>
                                    <?php if ($isActive): ?><span class="badge bg-primary">सक्रिय</span><?php endif; ?>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">
                                    <i class="bi <?= $stgItem['icon'] ?> me-1"></i> <?= $stgItem['name_mr'] ?> (<?= $stgItem['name_en'] ?>)
                                </h6>
                                <p class="small text-muted mb-0" style="font-size: 0.8rem; line-height: 1.3;">
                                    <?= $stgItem['summary_mr'] ?>
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>


            <!-- Standard Sub-pills under the active stage -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2 border-top">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small fw-bold text-dark me-1"><i class="bi bi-mortarboard-fill"></i> इयत्ता निवडा:</span>
                    <ul class="nav nav-pills" style="gap: 6px;">
                        <?php foreach ($current_stage['stds'] as $st_num): 
                            $is_active_std = ($std === $st_num);
                        ?>
                            <li class="nav-item">
                                <a class="nav-link fw-bold px-3 py-1 text-nowrap <?= $is_active_std ? 'active shadow-sm' : 'bg-white text-dark border' ?>" 
                                   style="<?= $is_active_std ? 'background-color:' . $current_stage['border_color'] . '; border-color:' . $current_stage['border_color'] . ';' : '' ?>"
                                   href="?std=<?= $st_num ?>">
                                    <?= $grade_labels[$st_num] ?? ('इयत्ता ' . $st_num) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= APP_URL ?>/students/add.php?std=<?= $std ?>&return=hpc" class="btn btn-sm btn-primary fw-bold ms-md-2 shadow-sm text-nowrap" style="border-radius:20px;">
                        <i class="bi bi-person-plus-fill me-1"></i> + नवीन विद्यार्थी नोंदणी
                    </a>
                    <a href="<?= APP_URL ?>/students/list.php?std=<?= $std ?>" class="btn btn-sm btn-outline-primary fw-semibold shadow-sm text-nowrap" style="border-radius:20px;">
                        <i class="bi bi-pie-chart-fill me-1"></i> माहिती प्रगती तक्ता
                    </a>
                </div>

                <div class="text-muted small">
                    <i class="bi bi-calendar3"></i> शैक्षणिक वर्ष: <strong><?= sanitize($year) ?></strong> | 
                    शाळा: <strong><?= sanitize($school['name_mr'] ?: $school['name']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_higher_locked): ?>
        <!-- LOCKED HIGHER CLASS HPC UPGRADE BANNER -->
        <div class="card border-warning shadow-sm my-4 text-center p-5 bg-white" style="border-radius:16px;">
            <div class="mb-3">
                <span class="rounded-circle bg-warning-subtle text-warning d-inline-flex p-3 fs-1">
                    <i class="bi bi-shield-lock-fill text-danger"></i>
                </span>
            </div>
            <h3 class="fw-bold text-dark mb-2">उच्च वर्ग HPC (इयत्ता ३ री ते ८ वी) साठी सशुल्क योजना आवश्यक आहे</h3>
            <p class="text-muted mx-auto mb-4" style="max-width: 620px; font-size: 1.05rem;">
                मोफत चाचणी योजनेमध्ये केवळ इयत्ता १ ली व २ री (पायाभूत स्तर) चे प्रगती पुस्तक उपलब्ध आहे. 
                इयत्ता ३ री ते ५ वी (पूर्वतयारी स्तर) व ६ वी ते ८ वी (पूर्व-माध्यमिक स्तर) मधील विद्यार्थ्यांचे HPC मूल्यमापन, SCERT अहवाल व PDF प्रिंट करण्यासाठी कृपया योग्य योजनेमध्ये अपग्रेड करा.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= APP_URL ?>/subscription/plans.php" class="btn btn-warning text-dark px-4 py-2 fw-bold shadow-sm">
                    <i class="bi bi-gem me-1"></i> योजना पहा व अपग्रेड करा
                </a>
                <a href="<?= APP_URL ?>/hpc/index.php?std=1" class="btn btn-outline-secondary px-4 py-2">
                    <i class="bi bi-arrow-left"></i> इयत्ता १ ली वर परत जा (मोफत)
                </a>
            </div>
        </div>
    <?php else: ?>
    <?php if ($is_hpc_quota_full): ?>
        <div class="alert alert-warning border-warning shadow-sm d-flex flex-wrap align-items-center justify-content-between p-3 mb-4" style="border-radius: 14px;">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-shield-lock-fill fs-2 text-warning flex-shrink-0"></i>
                <div>
                    <strong class="text-dark d-block">आपल्या योजनेनुसार HPC कार्ड मर्यादा पूर्ण झाली आहे!</strong>
                    <span class="text-muted small">
                        आपल्या <b><?= htmlspecialchars($hpc_quota['plan_name']) ?></b> नुसार कमाल <b><?= $quota_max ?> विद्यार्थ्यांचेच</b> HPC कार्ड तयार करता येतील (सध्या शाळेत एकूण <b><?= $total_school_cards_count ?> / <?= $quota_max ?></b> कार्ड्स तयार आहेत). ज्यांचे कार्ड आधीच तयार आहे ते आपण पाहू व प्रिंट करू शकता, परंतु नवीन विद्यार्थ्यांचे कार्ड तयार करण्यासाठी कृपया योजना अपग्रेड करा.
                    </span>
                </div>
            </div>
            <a href="<?= APP_URL ?>/subscription/plans.php" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm text-nowrap mt-2 mt-md-0">
                <i class="bi bi-gem me-1"></i> योजना अपग्रेड करा
            </a>
        </div>
    <?php endif; ?>

    <!-- Live KPI Metrics Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #0d6efd !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">एकूण विद्यार्थी (<?= $grade_str ?>)</div>
                            <div class="fs-3 fw-bold text-dark mt-1"><?= $total_count ?></div>
                        </div>
                        <div class="fs-1 text-primary opacity-25"><i class="bi bi-people-fill"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #198754 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">तयार HPC कार्ड्स (एकूण)</div>
                            <div class="fs-3 fw-bold text-success mt-1">
                                <?= $total_school_cards_count ?>
                                <span class="fs-6 fw-normal text-muted">/ <?= $is_quota_unlimited ? 'अमर्यादित' : $quota_max ?></span>
                                <?php if (!$is_quota_unlimited && $total_school_cards_count >= $quota_max): ?>
                                    <span class="badge bg-danger ms-1" style="font-size: 0.7rem; vertical-align: middle;">मर्यादा पूर्ण</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="fs-1 text-success opacity-25"><i class="bi bi-check-circle-fill"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #ffc107 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">मसुदा (Draft)</div>
                            <div class="fs-3 fw-bold text-warning mt-1"><?= $draft_count ?></div>
                        </div>
                        <div class="fs-1 text-warning opacity-25"><i class="bi bi-pencil-square"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6c757d !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">अपूर्ण / शिल्लक</div>
                            <div class="fs-3 fw-bold text-secondary mt-1"><?= $pending_count ?></div>
                        </div>
                        <div class="fs-1 text-secondary opacity-25"><i class="bi bi-clock-history"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-bold text-dark"><i class="bi bi-pie-chart-fill text-primary"></i> <?= $grade_str ?> मूल्यमापन प्रगती</span>
                <span class="small fw-bold text-primary"><?= $progress_pct ?>% पूर्ण (<?= $completed_count ?>/<?= $total_count ?> विद्यार्थी)</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress_pct ?>%;" aria-valuenow="<?= $progress_pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                <?php if ($total_count > 0 && $draft_count > 0): ?>
                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= round(($draft_count / $total_count) * 100) ?>%;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="std" value="<?= $std ?>">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="विद्यार्थ्याचे नाव किंवा रोल नंबर शोधा..." value="<?= sanitize($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">सर्व स्थिती (All)</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>पूर्ण (Completed)</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>मसुदा (Draft)</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>अपूर्ण / शिल्लक</option>
                    </select>
                </div>
                <?php if (!empty($divisions)): ?>
                <div class="col-md-2">
                    <select class="form-select" name="division" onchange="this.form.submit()">
                        <option value="">सर्व तुकड्या</option>
                        <?php foreach ($divisions as $div): ?>
                            <option value="<?= sanitize($div) ?>" <?= $division_filter === $div ? 'selected' : '' ?>>तुकडी <?= sanitize($div) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> फिल्टर</button>
                    <?php if ($search || $status_filter || $division_filter): ?>
                        <a href="?std=<?= $std ?>" class="btn btn-outline-secondary" title="रीसेट करा"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Student Cards Grid -->
    <?php if (empty($filtered_students)): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body text-muted">
                <i class="bi bi-people fs-1 d-block mb-3 opacity-50"></i>
                <h5><?= $grade_str ?> मध्ये कोणतेही विद्यार्थी सापडले नाहीत</h5>
                <p class="small text-muted mb-3">या निकषांसाठी कोणतेही विद्यार्थी उपलब्ध नाहीत किंवा अद्याप विद्यार्थी जोडलेले नाहीत.</p>
                <a href="<?= APP_URL ?>/students/add.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-person-plus"></i> नवीन विद्यार्थी जोडा
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($filtered_students as $item): 
                $s = $item['student'];
                $card = $item['card'];
                $status = $item['status'];
                $std_id = (int) $s['id'];
                $card_id = $card ? (int) $card['id'] : 0;
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 student-hpc-card position-relative">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <a href="<?= APP_URL ?>/students/edit.php?id=<?= $std_id ?>&return=hpc&std=<?= $std ?>" 
                               class="avatar-circle text-decoration-none <?= $status === 'completed' ? 'bg-success-subtle text-success border border-success' : ($status === 'draft' ? 'bg-warning-subtle text-warning border border-warning' : 'bg-light text-secondary border') ?>" 
                               style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; font-weight: bold;"
                               title="विद्यार्थी माहिती, उंची, वजन संपादित करा">
                                <?= sanitize($s['roll_no'] ?: mb_substr($s['name_mr'] ?: $s['name'], 0, 1)) ?>
                            </a>
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="mb-0 text-truncate fw-bold">
                                    <a href="<?= APP_URL ?>/students/edit.php?id=<?= $std_id ?>&return=hpc&std=<?= $std ?>" 
                                       class="text-dark text-decoration-none hover-primary d-inline-flex align-items-center gap-1"
                                       title="विद्यार्थी माहिती, उंची, वजन संपादित करा">
                                        <?= sanitize($s['name_mr'] ?: $s['name']) ?>
                                        <i class="bi bi-pencil-square text-primary small ms-1" style="font-size: 0.85rem;"></i>
                                    </a>
                                </h6>
                                <?php if (!empty($s['name_mr']) && $s['name_mr'] !== $s['name']): ?>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= sanitize($s['name']) ?></div>
                                <?php endif; ?>
                                <div class="text-muted small d-flex flex-wrap gap-2 mt-1">
                                    <span>रोल: <strong><?= sanitize($s['roll_no'] ?: '-') ?></strong></span>
                                    <span>तुकडी: <strong><?= sanitize($s['section'] ?: 'अ') ?></strong></span>
                                    <?php if (!empty($s['gr_no'])): ?>
                                        <span>GR: <strong><?= sanitize($s['gr_no']) ?></strong></span>
                                    <?php endif; ?>
                                    <?php 
                                      $h1_disp = (float)($s['height_sem1'] ?? 0);
                                      $w1_disp = (float)($s['weight_sem1'] ?? 0);
                                      if ($h1_disp > 0 || $w1_disp > 0):
                                    ?>
                                        <span class="text-success" title="सत्र १ उंची / वजन"><i class="bi bi-heart-pulse"></i> <?= $h1_disp > 0 ? ($h1_disp . ' cm') : '' ?><?= ($h1_disp > 0 && $w1_disp > 0) ? ' · ' : '' ?><?= $w1_disp > 0 ? ($w1_disp . ' kg') : '' ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($status === 'completed'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> पूर्ण</span>
                                <?php elseif ($status === 'draft'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-pencil"></i> मसुदा</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">अपूर्ण</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons Row tailored to Stage -->
                        <div class="d-flex flex-column gap-2 pt-2 border-top">
                            <?php if ($stage_key === 'foundational'): ?>
                                <!-- Foundational Actions: Unified Official Form (create.php) -->
                                <div class="d-flex gap-2">
                                    <?php if (!$card && $is_hpc_quota_full): ?>
                                        <a href="<?= APP_URL ?>/subscription/plans.php" 
                                           class="btn btn-sm btn-outline-danger flex-grow-1 fw-bold text-nowrap shadow-sm" 
                                           title="प्लॅन मर्यादा पूर्ण (कमाल <?= $quota_max ?> विद्यार्थी). पुढील विद्यार्थ्यांसाठी योजना अपग्रेड करा.">
                                            <i class="bi bi-lock-fill"></i> मर्यादा पूर्ण (कमाल <?= $quota_max ?>)
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= APP_URL ?>/hpc/create.php?student_id=<?= $std_id ?>" 
                                           class="btn btn-sm btn-primary flex-grow-1 fw-bold text-nowrap shadow-sm" 
                                           title="पायाभूत स्तर ६-डोमेन HPC कार्ड नोंद / संपादन करा">
                                            <i class="bi bi-pencil-square"></i> <?= $card ? 'HPC नोंद / संपादन' : 'HPC कार्ड तयार करा' ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= APP_URL ?>/students/edit.php?id=<?= $std_id ?>&return=hpc&std=<?= $std ?>" 
                                       class="btn btn-sm btn-outline-secondary px-2" 
                                       title="विद्यार्थी माहिती, उंची, वजन संपादन करा">
                                        <i class="bi bi-person-gear"></i>
                                    </a>
                                </div>
                                
                                <?php if ($card_id > 0): ?>
                                <div class="d-flex gap-2">
                                    <a href="<?= APP_URL ?>/cards/view.php?id=<?= $card_id ?>" 
                                       class="btn btn-sm btn-light border flex-fill" 
                                       title="HPC कार्ड पहा">
                                        <i class="bi bi-eye"></i> पहा
                                    </a>
                                    <a href="<?= APP_URL ?>/cards/print_hpc_19.php?id=<?= $card_id ?>&format=official19" 
                                       target="_blank" 
                                       class="btn btn-sm btn-success flex-fill" 
                                       title="अधिकृत १९-पानी रंगीत कार्ड प्रिंट करा">
                                        <i class="bi bi-printer-fill"></i> रंगीत प्रिंट (१९ पाने)
                                    </a>
                                </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- Preparatory (std 3-5) & Middle (std 6-8) Actions: stage_form.php + print_hpc_stage.php -->
                                <div class="d-flex gap-2">
                                    <?php if (!$card && $is_hpc_quota_full): ?>
                                        <a href="<?= APP_URL ?>/subscription/plans.php" 
                                           class="btn btn-sm btn-outline-danger flex-grow-1 fw-bold text-nowrap shadow-sm" 
                                           title="प्लॅन मर्यादा पूर्ण (कमाल <?= $quota_max ?> विद्यार्थी). पुढील विद्यार्थ्यांसाठी योजना अपग्रेड करा.">
                                            <i class="bi bi-lock-fill"></i> मर्यादा पूर्ण (कमाल <?= $quota_max ?>)
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= APP_URL ?>/hpc/stage_form.php?student_id=<?= $std_id ?>" 
                                           class="btn btn-sm btn-primary flex-grow-1 fw-bold text-nowrap shadow-sm" 
                                           title="विषयनिहाय मूल्यमापन व रुब्रिक नोंद करा">
                                            <i class="bi bi-pencil-square"></i> विषय मूल्यमापन (स्टेज फॉर्म)
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= APP_URL ?>/students/edit.php?id=<?= $std_id ?>&return=hpc&std=<?= $std ?>" 
                                       class="btn btn-sm btn-outline-secondary px-2" 
                                       title="विद्यार्थी माहिती, उंची, वजन संपादन करा">
                                        <i class="bi bi-person-gear"></i>
                                    </a>
                                </div>
                                
                                <?php if ($card_id > 0): ?>
                                <div class="d-flex gap-2">
                                    <a href="<?= APP_URL ?>/cards/view.php?id=<?= $card_id ?>" 
                                       class="btn btn-sm btn-light border flex-fill" 
                                       title="HPC कार्ड पहा">
                                        <i class="bi bi-eye"></i> पहा
                                    </a>
                                    <a href="<?= APP_URL ?>/cards/print_hpc_stage.php?id=<?= $card_id ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-success flex-fill" 
                                       title="SCERT A4 अधिकृत अहवाल प्रिंट करा">
                                        <i class="bi bi-printer-fill"></i> SCERT अहवाल
                                    </a>
                                    <a href="<?= APP_URL ?>/cards/print_hpc_stage.php?id=<?= $card_id ?>&mode=compact" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-success px-2" 
                                       title="संक्षिप्त छपाई (Compact)">
                                        <i class="bi bi-file-earmark-ruled"></i> संक्षिप्त
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; // end of !is_higher_locked ?>
</div>

<style>
.student-hpc-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    border-radius: 12px;
}
.student-hpc-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08) !important;
}
.transition-card {
    transition: all 0.2s ease;
    border-radius: 10px;
}
.transition-card:hover {
    transform: translateY(-2px);
    border-color: #0d6efd !important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
