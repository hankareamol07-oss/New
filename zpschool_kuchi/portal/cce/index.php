<?php
/**
 * सातत्यपूर्ण मूल्यमापन डॅशबोर्ड (सातत्यपूर्ण सर्वंकष मूल्यमापन)
 * स्वच्छ, सोपी आणि आधुनिक डिजिटल कार्यप्रणाली
 */
$page_title = 'सातत्यपूर्ण मूल्यमापन';
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/module_access.php';
requireLogin();
require_module_access('cce');

$school_id = (int)$_SESSION['school_id'];
$year = academic_year();
$school = getSchool();
$standards = cce_school_standards($school_id);
if (empty($standards)) {
    $standards = get_school_std_range($school_id);
}

// Strict Teacher Scope Filter: Only assigned class for teachers
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    $allowed_map = array_flip($tScope['stds']);
    $standards = array_filter($standards, function($k) use ($allowed_map) {
        return isset($allowed_map[$k]);
    }, ARRAY_FILTER_USE_KEY);
}
$s_name = $school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद शाळा');

$db = getMainDB();

// 1. Fetch active teachers
$teachers_stmt = $db->prepare("SELECT id, name, name_mr, teacher_code, role FROM teachers WHERE school_id = ? AND is_active = 1 ORDER BY name_mr, name");
$teachers_stmt->execute([$school_id]);
$teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch classes & class teachers
$classes_stmt = $db->prepare("SELECT id, std, name, section, class_teacher_id FROM classes WHERE school_id = ? ORDER BY std, section");
$classes_stmt->execute([$school_id]);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

$teacher_class_map = [];
$std_sections_map = [];
foreach ($classes as $c) {
    $c_std = (int)$c['std'];
    $sec = $c['section'] ?: 'अ';
    $std_sections_map[$c_std][] = $sec;
    if (!empty($c['class_teacher_id'])) {
        $teacher_class_map[(int)$c['class_teacher_id']] = [
            'std' => $c_std,
            'section' => $sec,
            'name' => $c['name']
        ];
    }
}

// 3. Fetch subject teachers
$sub_t_stmt = $db->prepare("
    SELECT cst.teacher_id, cst.std, cst.division, cs.name_mr AS subject_name 
    FROM cce_subject_teachers cst
    JOIN cce_subjects cs ON cst.subject_id = cs.id
    WHERE cst.school_id = ? AND cst.academic_year = ?
    ORDER BY cst.std, cs.sort_order
");
$sub_t_stmt->execute([$school_id, $year]);
$teacher_subjects_map = [];
foreach ($sub_t_stmt->fetchAll(PDO::FETCH_ASSOC) as $st) {
    $tid = (int)$st['teacher_id'];
    $div = ($st['division'] === '-' ? 'अ' : $st['division']) ?: 'अ';
    $teacher_subjects_map[$tid][] = [
        'std' => (int)$st['std'],
        'division' => $div,
        'subject' => $st['subject_name']
    ];
}

// 4. Resolve selected teacher & active standard/division
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
$selected_teacher_id = 0;
if ($tScope && !empty($tScope['teacher_id'])) {
    $selected_teacher_id = (int)$tScope['teacher_id'];
    $_SESSION['cce_teacher_id'] = $selected_teacher_id;
} elseif (isset($_GET['teacher_id'])) {
    $selected_teacher_id = (int)$_GET['teacher_id'];
    $_SESSION['cce_teacher_id'] = $selected_teacher_id;
} elseif (isset($_SESSION['cce_teacher_id'])) {
    $selected_teacher_id = (int)$_SESSION['cce_teacher_id'];
}

$selected_teacher = null;
if ($selected_teacher_id > 0) {
    foreach ($teachers as $t) {
        if ((int)$t['id'] === $selected_teacher_id) {
            $selected_teacher = $t;
            break;
        }
    }
}

if ($tScope && !empty($tScope['stds'])) {
    if (isset($_GET['std']) && in_array((int)$_GET['std'], $tScope['stds'], true)) {
        $selected_std = (int)$_GET['std'];
    } else {
        $selected_std = (int)$tScope['stds'][0];
    }
    $selected_div = trim((string)($_GET['division'] ?? 'अ')) ?: 'अ';
} elseif (isset($_GET['std'])) {
    $selected_std = (int)$_GET['std'];
    $selected_div = trim((string)($_GET['division'] ?? 'अ')) ?: 'अ';
    $_SESSION['cce_selected_std'] = $selected_std;
    $_SESSION['cce_selected_div'] = $selected_div;
} elseif ($selected_teacher && isset($teacher_class_map[$selected_teacher_id])) {
    $selected_std = $teacher_class_map[$selected_teacher_id]['std'];
    $selected_div = $teacher_class_map[$selected_teacher_id]['section'];
} elseif ($selected_teacher && !empty($teacher_subjects_map[$selected_teacher_id])) {
    $selected_std = $teacher_subjects_map[$selected_teacher_id][0]['std'];
    $selected_div = $teacher_subjects_map[$selected_teacher_id][0]['division'];
} elseif (!empty($_SESSION['cce_selected_std'])) {
    $selected_std = (int)$_SESSION['cce_selected_std'];
    $selected_div = $_SESSION['cce_selected_div'] ?? 'अ';
} else {
    $selected_std = array_key_first($standards) ?? 1;
    $selected_div = 'अ';
}

if (!isset($standards[$selected_std])) {
    $selected_std = array_key_first($standards) ?? 1;
}

$is_pro = function_exists('isSchoolPro') ? isSchoolPro($school_id) : true;
$cce_class_access = can_access_cce_class($selected_std, $school_id);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Clean & Minimalist Dashboard Styles */
.cce-min-wrap {
    max-width: 860px;
    margin: 0 auto;
    padding: 0.5rem 0.5rem 3rem;
    font-family: 'Mukta', 'Mukta', -apple-system, BlinkMacSystemFont, sans-serif;
}
.cce-topbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.cce-topbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex: 1 1 auto;
}
.cce-back-btn {
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    text-decoration: none !important;
    transition: background-color 0.15s, color 0.15s;
    flex-shrink: 0;
}
.cce-back-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.cce-brand-badge {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: #167A5B;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
    box-shadow: 0 2px 6px rgba(22, 122, 91, 0.25);
}
.cce-selector-card {
    background: #ffffff;
    border: 1.5px solid #DDD6FE;
    border-radius: 14px;
    padding: 14px 18px;
    box-shadow: 0 2px 10px rgba(22, 122, 91, 0.06);
    margin-bottom: 24px;
}
.cce-sec-title {
    font-size: 0.96rem;
    font-weight: 600;
    color: #334155;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    padding-left: 2px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cce-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
    margin-bottom: 0.5rem;
}
@media (max-width: 640px) {
    .cce-grid {
        grid-template-columns: 1fr;
    }
}
.cce-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 18px;
    text-decoration: none !important;
    color: inherit;
    transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
}
.cce-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    transform: translateY(-1.5px);
    color: inherit;
}
.cce-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: #EFF8F4;
    color: #167A5B;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
}
.cce-card-content {
    min-width: 0;
    flex: 1;
}
.cce-card-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 3px;
    line-height: 1.3;
}
.cce-card-desc {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.45;
}
#mgmtChevron {
    transition: transform 0.25s ease;
}
[aria-expanded="true"] #mgmtChevron {
    transform: rotate(180deg);
}
</style>

<div class="cce-min-wrap">

    <!-- TOP HEADER -->
    <div class="cce-topbar">
        <div class="cce-topbar-left">
            <a href="<?= APP_URL ?>/dashboard.php" class="cce-back-btn" title="मुख्य डॅशबोर्डवर परत जा">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <div class="cce-brand-badge">
                <i class="bi bi-bar-chart-steps"></i>
            </div>
            <div class="min-w-0">
                <div class="fw-bold text-slate-900 text-truncate" style="font-size:1.05rem; color:#0f172a;">
                    सातत्यपूर्ण मूल्यमापन
                </div>
                <div class="text-truncate" style="font-size:0.8rem; color:#64748b;">
                    <strong class="text-slate-800" style="color:#1e293b;"><?= sanitize($s_name) ?></strong> · <?= sanitize($year) ?>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= APP_URL ?>/cce/export_csv.php" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1" style="font-size:0.8rem; border-radius:8px;">
                <i class="bi bi-file-earmark-excel text-success"></i> Excel एक्सपोर्ट
            </a>
            <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="btn btn-sm fw-semibold d-inline-flex align-items-center gap-1" style="font-size:0.8rem; border-radius:8px; background:#EFF8F4; color:#167A5B; border:1px solid #DDD6FE;">
                <i class="bi bi-printer-fill"></i> अहवाल केंद्र
            </a>
        </div>
    </div>

    <!-- TEACHER & CLASS SELECTOR BAR (शिक्षक व वर्ग निवड) -->
    <div class="cce-selector-card">
        <form method="GET" id="cceContextForm" class="m-0">
            <div class="row g-2 align-items-end">
                <!-- 1. Teacher Selector Dropdown (Hidden for Class Teachers, Available for Headmaster) -->
                <?php if ($tScope): ?>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center gap-1">
                            <i class="bi bi-person-badge-fill text-primary"></i> शिक्षक:
                        </label>
                        <div class="form-control form-control-sm bg-light fw-bold text-dark border shadow-xs d-flex align-items-center gap-2" style="border-radius:8px;">
                            <i class="bi bi-person-check-fill text-success"></i>
                            <span><?= sanitize($selected_teacher['name_mr'] ?: ($selected_teacher['name'] ?? 'शिक्षक')) ?></span>
                            <span class="badge bg-primary-subtle text-primary ms-auto"><?= $tScope['class_name'] ?></span>
                        </div>
                        <input type="hidden" name="teacher_id" value="<?= $selected_teacher_id ?>">
                    </div>
                <?php else: ?>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center gap-1">
                            <i class="bi bi-person-badge-fill text-primary"></i> शिक्षक निवडा (मुख्याध्यापक: संगीता पाटील):
                        </label>
                        <select name="teacher_id" class="form-select form-select-sm fw-semibold shadow-sm" onchange="this.form.submit()" style="border-radius:8px;">
                            <option value="0">-- सर्व वर्ग (मुख्याध्यापक नियंत्रण) --</option>
                            <?php foreach ($teachers as $t): 
                                $tid = (int)$t['id'];
                                $ct_info = $teacher_class_map[$tid] ?? null;
                                $t_label = ($t['name_mr'] ?: $t['name']);
                                if ($ct_info) {
                                    $t_label .= " (वर्गशिक्षक: " . std_label($ct_info['std']) . " '" . $ct_info['section'] . "')";
                                } elseif (!empty($teacher_subjects_map[$tid])) {
                                    $t_label .= " (विषय शिक्षक)";
                                }
                            ?>
                                <option value="<?= $tid ?>" <?= ($selected_teacher_id === $tid) ? 'selected' : '' ?>>
                                    <?= sanitize($t_label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- 2. Standard & Division Selector -->
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center gap-1">
                        <i class="bi bi-mortarboard-fill" style="color:#167A5B;"></i> चालू वर्ग व तुकडी:
                    </label>
                    <div class="input-group input-group-sm shadow-sm" style="border-radius:8px; overflow:hidden;">
                        <select name="std" class="form-select fw-semibold" onchange="this.form.submit()">
                            <?php foreach ($standards as $s_num => $s_lbl): 
                                $s_acc = can_access_cce_class($s_num, $school_id);
                                $is_pro_std = !$s_acc['allowed'];
                            ?>
                                <option value="<?= $s_num ?>" <?= ($s_num === $selected_std) ? 'selected' : '' ?>>
                                    इयत्ता <?= $s_lbl ?> <?= $is_pro_std ? '🔒 (सशुल्क PRO)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="division" class="form-select fw-semibold" onchange="this.form.submit()" style="max-width:90px;">
                            <?php 
                            $available_divs = $std_sections_map[$selected_std] ?? ['अ'];
                            if (!in_array($selected_div, $available_divs, true)) {
                                $available_divs[] = $selected_div;
                            }
                            foreach ($available_divs as $d_val): ?>
                                <option value="<?= sanitize($d_val) ?>" <?= ($d_val === $selected_div) ? 'selected' : '' ?>>
                                    तुकडी <?= sanitize($d_val) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 3. Continue Work & Student Registration Buttons -->
                <div class="col-md-3 d-flex gap-1.5">
                    <?php if ($cce_class_access['allowed']): ?>
                        <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" 
                           class="btn btn-sm btn-primary flex-fill fw-bold d-inline-flex align-items-center justify-content-center gap-1 shadow-sm"
                           style="background:#167A5B; border-color:#167A5B; border-radius:8px; height:31px;">
                            <span>काम सुरू करा</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?= APP_URL ?>/subscription/plans.php?module=cce" 
                           class="btn btn-sm btn-warning flex-fill fw-bold d-inline-flex align-items-center justify-content-center gap-1 shadow-sm text-dark"
                           style="border-radius:8px; height:31px;">
                            <i class="bi bi-stars"></i>
                            <span>अपग्रेड करा</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/students/add.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>&return=cce" 
                       class="btn btn-sm fw-bold d-inline-flex align-items-center justify-content-center gap-1 shadow-sm text-nowrap"
                       title="या वर्गात नवीन विद्यार्थी नोंदवा"
                       style="background:#EFF8F4; color:#167A5B; border:1.5px solid #DDD6FE; border-radius:8px; height:31px; font-size:0.8rem;">
                        <i class="bi bi-person-plus-fill"></i>
                        <span>+ विद्यार्थी</span>
                    </a>
                </div>
            </div>

            <!-- Active Teacher Context Badge & Details -->
            <div class="mt-2.5 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size:0.8rem; color:#475569;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?php if ($selected_teacher): ?>
                        <span class="text-dark fw-bold">
                            <i class="bi bi-person-circle text-primary me-1"></i><?= sanitize($selected_teacher['name_mr'] ?: $selected_teacher['name']) ?>
                        </span>
                        <?php if (isset($teacher_class_map[$selected_teacher_id])): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-check-circle-fill me-1"></i>वर्गशिक्षक: इयत्ता <?= std_label($teacher_class_map[$selected_teacher_id]['std']) ?> '<?= sanitize($teacher_class_map[$selected_teacher_id]['section']) ?>'
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($teacher_subjects_map[$selected_teacher_id])): 
                            $subs_text = [];
                            foreach ($teacher_subjects_map[$selected_teacher_id] as $ts) {
                                if ($ts['std'] === $selected_std) {
                                    $subs_text[] = $ts['subject'];
                                }
                            }
                            if (!empty($subs_text)):
                        ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bi bi-book-half me-1"></i>विषय: <?= sanitize(implode(', ', $subs_text)) ?>
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>विशिष्ट शिक्षकाचे वर्ग व विषय आपोआप पाहण्यासाठी वरील ड्रॉपडाउनमधून शिक्षक निवडा.
                        </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= APP_URL ?>/students/add.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>&return=cce" 
                       class="badge text-decoration-none py-1.5 px-2.5 d-inline-flex align-items-center gap-1.5"
                       style="background:#DCF0E7; color:#13644B; border:1px solid #DDD6FE; font-size:0.75rem;">
                        <i class="bi bi-person-plus-fill"></i>
                        <strong>+ विद्यार्थी नोंदणी</strong>
                    </a>
                    <span class="badge bg-purple-subtle text-purple border" style="background:#EFF8F4; color:#167A5B; border-color:#DDD6FE;">
                        निवडलेला वर्ग: <strong>इयत्ता <?= std_label($selected_std) ?> '<?= sanitize($selected_div) ?>'</strong>
                    </span>
                </div>
            </div>
        </form>
    </div>

    <!-- Upgrade Banner for Locked Standards (इ. ६ ते ८ on Free Plan) -->
    <?php if (!$cce_class_access['allowed']): ?>
    <div class="alert alert-warning border-warning d-flex flex-wrap align-items-center justify-content-between mb-4 shadow-sm p-3 rounded-3" role="alert">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-lock-fill fs-2 text-warning"></i>
            <div>
                <h6 class="fw-bold mb-1 text-dark">इयत्ता <?= std_label($selected_std) ?> सातत्यपूर्ण मूल्यमापन केवळ सशुल्क योजनेत (PRO) उपलब्ध आहे!</h6>
                <div class="small text-secondary"><?= sanitize($cce_class_access['message']) ?> मोफत योजनेमध्ये इयत्ता १ ते ५ मोफत उपलब्ध आहे.</div>
            </div>
        </div>
        <a href="<?= APP_URL ?>/subscription/plans.php?module=cce" class="btn btn-sm btn-warning text-dark fw-bold text-nowrap shadow-sm mt-2 mt-sm-0">
            <i class="bi bi-stars me-1"></i> योजना अपग्रेड करा (PRO)
        </a>
    </div>
    <?php endif; ?>

    <!-- QUICK ACTIONS FOR SELECTED CLASS: विद्यार्थी नोंदणी व वर्ग व्यवस्थापन -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2.5 px-3 mb-4 bg-white border rounded-3 shadow-xs" style="border-color:#DDD6FE !important; background: linear-gradient(to right, #ffffff, #faf5ff) !important;">
        <div class="d-flex align-items-center gap-2">
            <span class="badge" style="background:#DCF0E7; color:#13644B; border:1px solid #DDD6FE; font-size:0.85rem; padding: 6px 10px;">
                <i class="bi bi-mortarboard-fill me-1"></i> इयत्ता <?= std_label($selected_std) ?> '<?= sanitize($selected_div) ?>'
            </span>
            <span class="small fw-semibold text-secondary d-none d-sm-inline">
                विद्यार्थी व्यवस्थापन व नोंदणी:
            </span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= APP_URL ?>/students/add.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>&return=cce" 
               class="btn btn-sm btn-primary fw-bold shadow-sm d-inline-flex align-items-center gap-1.5"
               style="background:#167A5B; border-color:#167A5B; border-radius:8px; padding:6px 14px;">
                <i class="bi bi-person-plus-fill fs-6"></i>
                <span>+ नवीन विद्यार्थी नोंदणी</span>
            </a>
            <a href="<?= APP_URL ?>/students/list.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" 
               class="btn btn-sm fw-semibold shadow-sm d-inline-flex align-items-center gap-1"
               style="background:#ffffff; color:#167A5B; border:1.5px solid #DDD6FE; border-radius:8px; padding:6px 12px;">
                <i class="bi bi-pie-chart-fill"></i>
                <span>विद्यार्थी प्रगती तक्ता</span>
            </a>
            <a href="<?= APP_URL ?>/modules/classes/index.php?tab=classes&std=<?= $selected_std ?>" 
               class="btn btn-sm btn-outline-secondary shadow-sm d-inline-flex align-items-center gap-1"
               style="border-radius:8px; padding:6px 12px;" 
               title="वर्ग व तुकडी व्यवस्थापन हब">
                <i class="bi bi-gear-wide-connected"></i>
                <span class="d-none d-md-inline">वर्ग व्यवस्थापन</span>
            </a>
        </div>
    </div>

    <!-- 1. गुण नोंदणी (MARKS ENTRY) -->
    <h2 class="cce-sec-title">गुण नोंदणी</h2>
    <div class="cce-grid">
        <a href="<?= APP_URL ?>/cce/marks_config.php?std=<?= $selected_std ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-sliders"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">भारांश (कमाल गुण) सेटअप</div>
                <div class="cce-card-desc">विषय व सत्रानुसार आकारिक / संकलितचे कमाल गुण ठरवा — गुण नोंदणीपूर्वी आवश्यक</div>
            </div>
        </a>

        <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">गुण नोंदणी</div>
                <div class="cce-card-desc">तुकडीतील प्रत्येक विद्यार्थ्याचे प्राप्त गुण भरा</div>
            </div>
        </a>

        <a href="<?= APP_URL ?>/cce/outcomes.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-bullseye"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">अध्ययन निष्पत्ती (स्तर नोंद व संक्षिप्त अहवाल)</div>
                <div class="cce-card-desc">विद्यार्थीनिहाय स्तर १-४ नोंदणी, संक्षिप्त वर्ग अहवाल व रेडिओ तक्ता</div>
            </div>
        </a>
    </div>

    <!-- 3. वर्णनात्मक नोंदी (REMARKS & DESCRIPTIVE NOTES) -->
    <h2 class="cce-sec-title">वर्णनात्मक नोंदी</h2>
    <div class="cce-grid">
        <a href="<?= APP_URL ?>/cce/remarks_entry.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">वर्णनात्मक नोंदी</div>
                <div class="cce-card-desc">प्रत्येक विद्यार्थ्याच्या सर्व विषयांसाठी वर्णनात्मक शेरे नोंदवा</div>
            </div>
        </a>
    </div>

    <!-- 4. निकालपत्रके (RESULTS & REGISTERS) -->
    <h2 class="cce-sec-title">निकालपत्रके</h2>
    <div class="cce-grid">
        <a href="<?= APP_URL ?>/cce/class_grade_summary.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-table"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">वर्गस्तर निकालपत्रके</div>
                <div class="cce-card-desc">तुकडी व सत्र निवडून विद्यार्थीनिहाय विषयवार गुण/श्रेणी + लिंग/संवर्ग/हजर दिवस/एकूण एका तक्त्यात</div>
            </div>
        </a>

        <a href="<?= APP_URL ?>/cce/annual_grade_table.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-award"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">वार्षिक निकालपत्रके</div>
                <div class="cce-card-desc">तुकडी निवडून सत्र १ व सत्र २ दोन्ही एकाच तक्त्यात — संक्षिप्त किंवा सविस्तर (आकारिक/संकलित तपशीलसह)</div>
            </div>
        </a>
    </div>

    <!-- 5. नोंदवही (CLASS REGISTERS) -->
    <h2 class="cce-sec-title">नोंदवही</h2>
    <div class="cce-grid">
        <a href="<?= APP_URL ?>/cce/nondvahi.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">नोंदवही</div>
                <div class="cce-card-desc">तुकडी व सत्र निवडून संपूर्ण नोंदवही PDF तयार करा — कव्हर, अनुक्रमणिका, मूल्यमापन नोंदी, वर्णनात्मक नोंदी, गुण-श्रेणी तक्ता</div>
            </div>
        </a>

        <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-printer"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">सर्व अहवाल केंद्र</div>
                <div class="cce-card-desc">नोंदवही मुखपृष्ठ, अनुक्रमणिका, साधनेनिहाय तक्ते, श्रेणी तक्ता व सर्व १८+ अहवाल एकाच ठिकाणी</div>
            </div>
        </a>
    </div>

    <!-- 6. प्रगती पुस्तक / पत्रक (PROGRESS CARDS & BOOKLETS) -->
    <h2 class="cce-sec-title">प्रगती पत्रक</h2>
    <div class="cce-grid">
        <a href="<?= APP_URL ?>/cce/pragati_pustak.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>&all=1&size=a4" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-journal-album"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">प्रगती पुस्तक (बुकलेट A4/A5)</div>
                <div class="cce-card-desc">तुकडी निवडून प्रत्येक विद्यार्थ्याचे अधिकृत बुकलेट (कव्हर, आतील पाने, सत्र १ व २ गुण-श्रेणी, उपस्थिती)</div>
            </div>
        </a>

        <a href="<?= APP_URL ?>/cce/progress_card.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>&all=1" class="cce-card">
            <div class="cce-card-icon">
                <i class="bi bi-file-earmark-person"></i>
            </div>
            <div class="cce-card-content">
                <div class="cce-card-title">प्रगती पत्रक छापा</div>
                <div class="cce-card-desc">तुकडी निवडून प्रत्येक विद्यार्थ्याचे 2-पानी वार्षिक प्रगती पत्रक (वैयक्तिक माहिती + श्रेणी/आरोग्य/उपस्थिती) तयार करा</div>
            </div>
        </a>
    </div>

    <!-- 6. व्यवस्थापन (COLLAPSIBLE MANAGEMENT AT BOTTOM - ONCE-A-YEAR SETUP) -->
    <div class="mt-4 pt-2">
        <div class="card border rounded-3 shadow-xs overflow-hidden" style="background:#f8fafc; border-color:#e2e8f0 !important;">
            <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between border-0" 
                 role="button" 
                 data-bs-toggle="collapse" 
                 data-bs-target="#cceManagementCollapse" 
                 aria-expanded="false" 
                 aria-controls="cceManagementCollapse"
                 style="user-select: none; cursor: pointer;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-2 d-flex align-items-center justify-content-center" style="width:36px;height:36px;border-radius:10px;background:#DCF0E7;color:#167A5B;">
                        <i class="bi bi-gear-fill fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size:0.95rem;">व्यवस्थापन व प्राथमिक सेटअप (कदाचितच लागणारे पर्याय)</h6>
                        <small class="text-muted" style="font-size:0.75rem;">तुकडी माध्यम, वर्ग निवड, वर्ष व उपस्थिती सेटिंग्ज (सत्राच्या सुरुवातीला एकदाच सेट करा)</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border px-2 py-1 small d-none d-sm-inline">५ पर्याय</span>
                    <i class="bi bi-chevron-down text-secondary" id="mgmtChevron"></i>
                </div>
            </div>
            
            <div class="collapse" id="cceManagementCollapse">
                <div class="card-body p-3 pt-1 border-top bg-white">
                    <div class="cce-grid mt-2 mb-0">
                        <a href="<?= APP_URL ?>/students/add.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>&return=cce" class="cce-card">
                            <div class="cce-card-icon" style="background:#DCF0E7; color:#167A5B;">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <div class="cce-card-content">
                                <div class="cce-card-title">विद्यार्थी नोंदणी व यादी</div>
                                <div class="cce-card-desc">या वर्गात नवीन विद्यार्थी जोडा, माहिती अद्ययावत करा किंवा संपूर्ण यादी पहा</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/modules/classes/index.php?tab=classes&std=<?= $selected_std ?>" class="cce-card">
                            <div class="cce-card-icon">
                                <i class="bi bi-gear-wide-connected"></i>
                            </div>
                            <div class="cce-card-content">
                                <div class="cce-card-title">तुकडी व माध्यम सेटअप</div>
                                <div class="cce-card-desc">गुण नोंदणीपूर्वी प्रत्येक तुकडीचे माध्यम इथे सेट केलेले असावे</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/cce/class_scope.php?std=<?= $selected_std ?>" class="cce-card">
                            <div class="cce-card-icon">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div class="cce-card-content">
                                <div class="cce-card-title">वर्ग/तुकडी निवडा</div>
                                <div class="cce-card-desc">सातत्यपूर्ण मूल्यमापन मध्ये कोणत्या तुकड्या व त्यांचे विद्यार्थी दिसावेत ते निवडा</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/cce/settings.php" class="cce-card">
                            <div class="cce-card-icon">
                                <i class="bi bi-calendar3"></i>
                            </div>
                            <div class="cce-card-content">
                                <div class="cce-card-title">शैक्षणिक वर्ष सेटिंग्ज</div>
                                <div class="cce-card-desc">मुख्याध्यापक नाव (सत्रनिहाय), कामाचे दिवस व स्वाक्षरी सेटिंग्ज</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/cce/attendance.php?std=<?= $selected_std ?>&division=<?= urlencode($selected_div) ?>" class="cce-card">
                            <div class="cce-card-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="cce-card-content">
                                <div class="cce-card-title">मासिक उपस्थिती</div>
                                <div class="cce-card-desc">सत्रनिहाय विद्यार्थ्यांचे हजर दिवस व उपस्थिती नोंदी</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
