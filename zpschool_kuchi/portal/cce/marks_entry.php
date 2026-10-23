<?php
$page_title = 'गुण नोंदणी';
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/module_access.php';
requireLogin();
require_module_access('cce');

$school_id = $_SESSION['school_id'];
$db = cce_db($school_id);
$year = academic_year();
$standards = cce_school_standards($school_id);
$tech = cce_technique_labels();

$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    $allowed_map = array_flip($tScope['stds']);
    $standards = array_filter($standards, function($k) use ($allowed_map) {
        return isset($allowed_map[$k]);
    }, ARRAY_FILTER_USE_KEY);
}

$std = (int)($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
if ($tScope && !empty($tScope['stds']) && !in_array($std, $tScope['stds'], true)) {
    $std = $tScope['stds'][0];
}

// Standard level paywall check
$cce_std_check = can_access_cce_class($std, (int)$school_id);
if (!$cce_std_check['allowed']) {
    flash('error', $cce_std_check['message'] ?? 'या इयत्तेसाठी सशुल्क योजना आवश्यक आहे.');
    redirect(APP_URL . '/cce/index.php?std=1');
    exit;
}
$semester = (int)($_REQUEST['semester'] ?? 1) === 2 ? 2 : 1;
$subject_id = (int)($_REQUEST['subject_id'] ?? 0);
$student_id = (int)($_REQUEST['student_id'] ?? 0);
$division = trim((string)($_REQUEST['division'] ?? ''));

// Detect mobile device from User-Agent
$is_mobile_device = false;
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $is_mobile_device = (bool)preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $_SERVER['HTTP_USER_AGENT']);
}

// User explicit view choice takes first precedence, then student_id > 0, then mobile UA -> vertical, desktop -> table
$explicit_view = isset($_REQUEST['view']) && in_array($_REQUEST['view'], ['vertical', 'table'], true) ? $_REQUEST['view'] : null;
if ($explicit_view !== null) {
    $view_mode = $explicit_view;
} elseif ($student_id > 0) {
    $view_mode = 'vertical';
} elseif ($is_mobile_device) {
    $view_mode = 'vertical';
} else {
    $view_mode = 'table';
}

$subjects = cce_subjects($school_id, $std);

// If teacher is assigned specific subjects via cce_subject_teachers, filter to those subjects
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['teacher_id'])) {
    try {
        $st_chk = getMainDB()->prepare("SELECT subject_id FROM cce_subject_teachers WHERE school_id = ? AND teacher_id = ? AND std = ?");
        $st_chk->execute([$school_id, $tScope['teacher_id'], $std]);
        $assigned_ids = $st_chk->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($assigned_ids)) {
            $assigned_map = array_map('intval', $assigned_ids);
            $filtered_subs = array_values(array_filter($subjects, fn($s) => in_array((int)$s['id'], $assigned_map, true)));
            if (!empty($filtered_subs)) {
                $subjects = $filtered_subs;
            }
        }
    } catch (Throwable $e) {}
}
if (!$subject_id && $subjects) $subject_id = (int)$subjects[0]['id'];
$subject = null;
foreach ($subjects as $s) if ((int)$s['id'] === $subject_id) $subject = $s;
if ($subject === null && !empty($subjects)) {
    $subject = $subjects[0];
    $subject_id = (int)$subject['id'];
}

$students = cce_students($school_id, $std, $division);

// In vertical mode, if student_id is not specified or 0, default to first student if available
$curr_index = -1;
$curr_student = null;
$prev_student = null;
$next_student = null;

if (!empty($students)) {
    if ($student_id > 0) {
        foreach ($students as $idx => $st) {
            if ((int)$st['id'] === $student_id) {
                $curr_index = $idx;
                $curr_student = $st;
                break;
            }
        }
    }
    // If vertical view was requested but no valid student selected, select the first student
    if ($curr_student === null && $view_mode === 'vertical') {
        $curr_index = 0;
        $curr_student = $students[0];
        $student_id = (int)$curr_student['id'];
    }
    if ($curr_student !== null) {
        if ($curr_index > 0) $prev_student = $students[$curr_index - 1];
        if ($curr_index < count($students) - 1) $next_student = $students[$curr_index + 1];
    }
}

// POST Save handling
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $subject) {
    cce_check_csrf();
    $sql = "INSERT OR REPLACE INTO cce_marks (school_id, student_id, subject_id, academic_year, semester,
                fe1,fe2,fe3,fe4,fe5,fe6,fe7,fe8,se1,se2,se3,fe_total,se_total,grand_total,percent,grade)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $db->prepare($sql);
    $allowed = array_column(cce_students($school_id, $std), 'id');

    $mysql_stmt = null;
    try {
        $mysql = getMainDB();
        $mysql_sql = "INSERT INTO cce_marks (school_id, student_id, subject_id, academic_year, semester,
                    fe1,fe2,fe3,fe4,fe5,fe6,fe7,fe8,se1,se2,se3,fe_total,se_total,grand_total,percent,grade)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    fe1=VALUES(fe1), fe2=VALUES(fe2), fe3=VALUES(fe3), fe4=VALUES(fe4),
                    fe5=VALUES(fe5), fe6=VALUES(fe6), fe7=VALUES(fe7), fe8=VALUES(fe8),
                    se1=VALUES(se1), se2=VALUES(se2), se3=VALUES(se3),
                    fe_total=VALUES(fe_total), se_total=VALUES(se_total),
                    grand_total=VALUES(grand_total), percent=VALUES(percent), grade=VALUES(grade)";
        $mysql_stmt = $mysql->prepare($mysql_sql);
    } catch (Throwable $e) {}

    $saved_count = 0;
    foreach (($_POST['m'] ?? []) as $sid => $row) {
        $sid = (int)$sid;
        if (!in_array($sid, $allowed, true)) continue;
        $vals = [];
        for ($i = 1; $i <= 8; $i++) {
            $vals['fe' . $i] = (isset($row['fe' . $i]) && $row['fe' . $i] !== '' ? (float)$row['fe' . $i] : null);
        }
        for ($i = 1; $i <= 3; $i++) {
            $vals['se' . $i] = (isset($row['se' . $i]) && $row['se' . $i] !== '' ? (float)$row['se' . $i] : null);
        }
        $t = cce_compute_totals($vals, $subject['fe_max'], $subject['se_max'], $subject);
        $params = array_merge(
            [$school_id, $sid, $subject_id, $year, $semester],
            array_values($vals),
            [$t['fe_total'], $t['se_total'], $t['grand_total'], $t['percent'], $t['grade']]
        );
        $stmt->execute($params);
        if ($mysql_stmt) {
            try { $mysql_stmt->execute($params); } catch (Throwable $e) {}
        }
        $saved_count++;
    }

    $submit_action = $_POST['submit_action'] ?? 'save';
    $next_id = (int)($_POST['next_student_id'] ?? 0);
    $target_student_id = (int)($_POST['current_student_id'] ?? $student_id);

    if ($saved_count > 0) {
        flash('success', 'गुण यशस्वीरित्या जतन केले.');
    } else {
        flash('info', 'नोंद जतन झाली.');
    }

    $div_param = $division !== '' ? '&division=' . urlencode($division) : '';
    if ($submit_action === 'save_next' && $next_id > 0 && in_array($next_id, $allowed, true)) {
        redirect(APP_URL . "/cce/marks_entry.php?std=$std&semester=$semester&subject_id=$subject_id&student_id=$next_id&view=vertical" . $div_param);
    } elseif ($target_student_id > 0 && ($view_mode === 'vertical' || isset($_POST['current_student_id']))) {
        redirect(APP_URL . "/cce/marks_entry.php?std=$std&semester=$semester&subject_id=$subject_id&student_id=$target_student_id&view=vertical" . $div_param);
    } else {
        redirect(APP_URL . "/cce/marks_entry.php?std=$std&semester=$semester&subject_id=$subject_id" . ($view_mode === 'table' ? '&view=table' : '') . $div_param);
    }
}

$existing = [];
if ($subject_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND subject_id = ? AND academic_year = ? AND semester = ?");
        $stmt->execute([$school_id, $subject_id, $year, $semester]);
        foreach ($stmt->fetchAll() as $r) $existing[(int)$r['student_id']] = $r;
    } catch (Throwable $e) {}

    // Fallback and sync from MySQL if SQLite had no marks
    if (empty($existing)) {
        try {
            $mysql = getMainDB();
            $stmtM = $mysql->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND subject_id = ? AND academic_year = ? AND semester = ?");
            $stmtM->execute([$school_id, $subject_id, $year, $semester]);
            $mysql_marks = $stmtM->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($mysql_marks)) {
                $insSql = "INSERT OR REPLACE INTO cce_marks (school_id, student_id, subject_id, academic_year, semester,
                            fe1,fe2,fe3,fe4,fe5,fe6,fe7,fe8,se1,se2,se3,fe_total,se_total,grand_total,percent,grade)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $insStmt = $db->prepare($insSql);
                foreach ($mysql_marks as $r) {
                    $existing[(int)$r['student_id']] = $r;
                    try {
                        $insStmt->execute([
                            $r['school_id'], $r['student_id'], $r['subject_id'], $r['academic_year'], $r['semester'],
                            $r['fe1'], $r['fe2'], $r['fe3'], $r['fe4'], $r['fe5'], $r['fe6'], $r['fe7'], $r['fe8'],
                            $r['se1'], $r['se2'], $r['se3'],
                            $r['fe_total'], $r['se_total'], $r['grand_total'], $r['percent'], $r['grade']
                        ]);
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
    }
}

/** Whether at least one technique-level max is configured for this subject */
$has_technique_maxes = false;
if ($subject) {
    for ($i = 1; $i <= 8; $i++) if ($subject['fe' . $i . '_max'] !== null) $has_technique_maxes = true;
    for ($i = 1; $i <= 3; $i++) if ($subject['se' . $i . '_max'] !== null) $has_technique_maxes = true;
}

// Grades list for JavaScript real-time preview
$grades_master = [];
try {
    $grades_master = getMainDB()->query("SELECT gradetext, marks FROM cce_grades ORDER BY marks DESC")->fetchAll();
} catch (Exception $e) {}
if (!$grades_master) {
    $grades_master = [
        ['gradetext' => 'अ-१', 'marks' => 91],
        ['gradetext' => 'अ-२', 'marks' => 81],
        ['gradetext' => 'ब-१', 'marks' => 71],
        ['gradetext' => 'ब-२', 'marks' => 61],
        ['gradetext' => 'क-१', 'marks' => 51],
        ['gradetext' => 'क-२', 'marks' => 41],
        ['gradetext' => 'ड', 'marks' => 33],
        ['gradetext' => 'इ-१', 'marks' => 21],
        ['gradetext' => 'इ-२', 'marks' => 0],
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* CCE Marks Entry Modern & Mobile Styles */
.cce-marks-header {
    background: linear-gradient(135deg, #EFF8F4 0%, #DCF0E7 100%);
    border-radius: 12px;
    padding: 12px 16px;
    border: 1px solid #BFE3D3;
}
.cce-filter-card {
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}
.cce-select-styled {
    border-radius: 8px;
    font-size: 0.9rem;
    padding: 7px 10px;
    border: 1.5px solid #E0E0E0;
    transition: all 0.2s ease;
}
.cce-select-styled:focus {
    border-color: #167A5B;
    box-shadow: 0 0 0 0.2rem rgba(22,122,91, 0.15);
}
.view-toggle-pill {
    border-radius: 30px;
    padding: 3px;
    background: #F5F5F5;
    display: inline-flex;
    max-width: 100%;
}
.view-toggle-btn {
    border-radius: 25px;
    padding: 5px 12px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    color: #616161;
    transition: all 0.2s;
    white-space: nowrap;
}
.view-toggle-btn.active {
    background: #167A5B;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(22,122,91,0.3);
}

/* Vertical Entry Layout */
.v-student-card {
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #E2E8F0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.v-score-summary {
    background: linear-gradient(135deg, #1A365D 0%, #2B6CB0 100%);
    color: #fff;
    border-radius: 12px;
    padding: 12px;
    overflow: hidden;
}
.v-score-box {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(4px);
    border-radius: 8px;
    padding: 6px 4px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    min-width: 0;
}
.v-score-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    opacity: 0.9;
    margin-bottom: 2px;
    white-space: nowrap;
}
.v-score-val {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.1;
}
.v-score-max {
    font-size: 0.68rem;
    opacity: 0.8;
}

.v-section-title {
    font-weight: 700;
    font-size: 0.95rem;
    padding: 8px 12px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.v-section-fe {
    background: #FFF8E1;
    color: #B78103;
    border-left: 5px solid #FFA000;
}
.v-section-se {
    background: #EBF8FF;
    color: #2B6CB0;
    border-left: 5px solid #3182CE;
}

.v-tool-row {
    padding: 8px 10px;
    border-bottom: 1px solid #F1F5F9;
    transition: background-color 0.15s ease;
}
.v-tool-row:hover {
    background-color: #F8FAFC;
}
.v-tool-row:last-child {
    border-bottom: none;
}
.v-input-mark {
    width: 76px;
    min-height: 42px;
    font-size: 1.15rem;
    font-weight: 700;
    text-align: center;
    border-radius: 8px;
    border: 2px solid #CBD5E1;
    padding: 4px;
    flex-shrink: 0;
    transition: all 0.2s;
}
.v-input-mark:focus {
    border-color: #167A5B;
    box-shadow: 0 0 0 0.2rem rgba(22,122,91,0.18);
    background-color: #FFFDF9;
}
.v-input-mark.is-invalid {
    border-color: #E53E3E;
    background-color: #FFF5F5;
}

/* Sticky Action Bar */
.sticky-save-bar {
    position: sticky;
    bottom: 10px;
    z-index: 100;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(10px);
    border: 1.5px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    padding: 8px 12px;
}

/* Horizontal Table Styles with High Contrast Headers */
.sticky-roll { position: sticky; left: 0; background: #fff; z-index: 2; }
.sticky-name { position: sticky; left: 45px; background: #fff; z-index: 2; border-right: 2px solid #dee2e6 !important; }
thead .sticky-roll, thead .sticky-name, thead th.th-dark { 
    background: #17324d !important; 
    color: #ffffff !important; 
    z-index: 4; 
    border-color: #2a4a6e !important; 
}
.cce-marks-table thead th { vertical-align: middle !important; padding: 6px 4px; }
.cce-marks-table thead th.fe-main-head {
    background: #DCF0E7 !important;
    color: #0F4F3B !important;
    font-weight: 800 !important;
    font-size: 0.95rem;
    border-color: #BFE3D3 !important;
}
.cce-marks-table thead th.se-main-head {
    background: #B3E5FC !important;
    color: #01579B !important;
    font-weight: 800 !important;
    font-size: 0.95rem;
    border-color: #81D4FA !important;
}
.cce-marks-table thead th.fe-subhead {
    background: #FFF8E1 !important;
    color: #3E2723 !important;
    font-weight: 700 !important;
    border-color: #FFE082 !important;
    font-size: 0.8rem;
    line-height: 1.25;
}
.cce-marks-table thead th.fe-subhead span {
    color: #3E2723 !important;
    font-weight: 700 !important;
}
.cce-marks-table thead th.fe-subhead small {
    color: #13644B !important;
    font-weight: 800 !important;
    font-size: 0.72rem;
}
.cce-marks-table thead th.se-subhead {
    background: #E1F5FE !important;
    color: #0D47A1 !important;
    font-weight: 700 !important;
    border-color: #81D4FA !important;
    font-size: 0.8rem;
    line-height: 1.25;
}
.cce-marks-table thead th.se-subhead span {
    color: #0D47A1 !important;
    font-weight: 700 !important;
}
.cce-marks-table thead th.se-subhead small {
    color: #0277BD !important;
    font-weight: 800 !important;
    font-size: 0.72rem;
}
.cce-marks-table tbody td.sticky-roll,
.cce-marks-table tbody td.sticky-name {
    background: #ffffff !important;
    color: #212121 !important;
}
.cce-marks-table input.mark { min-width: 48px; font-size: 14px; min-height: 38px; text-align: center; font-weight: 600; padding: 2px; }

@media (max-width: 576px) {
  .sticky-name { max-width: 125px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .table-responsive { max-height: 75vh; }
  .v-input-mark { width: 70px; font-size: 1.05rem; min-height: 40px; }
  .v-score-val { font-size: 1.15rem; }
  .v-score-summary { padding: 8px 6px; }
  .view-toggle-pill { width: 100%; display: flex; }
  .view-toggle-btn { flex: 1 1 50%; text-align: center; padding: 5px 4px; font-size: 0.78rem; }
  .cce-marks-header { padding: 10px 12px; }
}
</style>

<div class="cce-page mb-5">

  <!-- Header Banner -->
  <div class="cce-marks-header mb-3 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="mb-0 fw-bold text-dark d-flex align-items-center fs-4">
        <i class="bi bi-pencil-square text-warning me-2"></i> गुण नोंदणी
      </h3>
      <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
    </div>

    <div class="text-secondary small d-flex flex-wrap gap-1 mb-2">
      <span class="badge bg-white text-dark border">इयत्ता <?= sanitize(std_label($std)) ?></span>
      <span class="badge bg-white text-dark border"><?= sanitize($subject ? $subject['name_mr'] : 'विषय निवडलेला नाही') ?></span>
      <span class="badge bg-white text-dark border"><?= $semester === 1 ? 'प्रथम सत्र' : 'द्वितीय सत्र' ?></span>
    </div>
    
    <!-- Mode Toggle Switch (Full width on mobile, inline on desktop) -->
    <div class="view-toggle-pill shadow-sm w-100 d-flex">
      <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $std ?>&semester=<?= $semester ?>&subject_id=<?= $subject_id ?>&student_id=<?= $student_id ?: ($students[0]['id'] ?? 0) ?>&view=vertical<?= $division !== '' ? '&division=' . urlencode($division) : '' ?>" 
         class="view-toggle-btn flex-fill text-center <?= $view_mode === 'vertical' ? 'active' : '' ?>">
        <i class="bi bi-phone"></i> उभी नोंद (मोबाईल)
      </a>
      <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $std ?>&semester=<?= $semester ?>&subject_id=<?= $subject_id ?>&student_id=0&view=table<?= $division !== '' ? '&division=' . urlencode($division) : '' ?>" 
         class="view-toggle-btn flex-fill text-center <?= $view_mode === 'table' ? 'active' : '' ?>">
        <i class="bi bi-table"></i> सर्व विद्यार्थी टेबल (लॅपटॉप)
      </a>
    </div>
  </div>

  <!-- Top Filter Bar with Student Dropdown -->
  <form method="GET" class="card cce-filter-card border-0 mb-3" id="cceFilterForm">
    <input type="hidden" name="view" value="<?= sanitize($view_mode) ?>">
    <?php if ($division !== ''): ?><input type="hidden" name="division" value="<?= sanitize($division) ?>"><?php endif; ?>
    <div class="card-body p-2 p-md-3">
      <div class="row g-2 align-items-end">
        
        <!-- Standard -->
        <div class="col-6 col-md-2">
          <label class="form-label small fw-bold text-secondary mb-1">
            <i class="bi bi-mortarboard text-warning"></i> इयत्ता
          </label>
          <select name="std" class="form-select cce-select-styled" onchange="this.form.student_id.value='0'; this.form.submit()" <?= count($standards) <= 1 ? 'disabled style="background-color: #f1f5f9; cursor: not-allowed;"' : '' ?>>
            <?php foreach ($standards as $s_id => $s_lbl): ?>
              <option value="<?= $s_id ?>" <?= (int)$s_id === $std ? 'selected' : '' ?>>इयत्ता <?= sanitize($s_lbl) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (count($standards) <= 1): ?>
            <input type="hidden" name="std" value="<?= $std ?>">
          <?php endif; ?>
        </div>

        <!-- Semester -->
        <div class="col-6 col-md-2">
          <label class="form-label small fw-bold text-secondary mb-1">
            <i class="bi bi-calendar3 text-primary"></i> सत्र
          </label>
          <select name="semester" class="form-select cce-select-styled" onchange="this.form.submit()">
            <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>प्रथम सत्र</option>
            <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>द्वितीय सत्र</option>
          </select>
        </div>

        <!-- Subject -->
        <div class="col-12 col-md-4">
          <label class="form-label small fw-bold text-secondary mb-1">
            <i class="bi bi-journal-bookmark text-success"></i> विषय निवडा
          </label>
          <select name="subject_id" class="form-select cce-select-styled" onchange="this.form.submit()">
            <?php foreach ($subjects as $s): ?>
              <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $subject_id ? 'selected' : '' ?>>
                <?= sanitize($s['name_mr']) ?> (कमाल: <?= (int)($s['fe_max'] + $s['se_max']) ?> गुण)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Student Dropdown -->
        <div class="col-12 col-md-4">
          <label class="form-label small fw-bold text-secondary mb-1">
            <i class="bi bi-person-badge text-info"></i> विद्यार्थी निवडा (उभी नोंद)
          </label>
          <select name="student_id" class="form-select cce-select-styled" onchange="if(this.value!='0'){ this.form.view.value='vertical'; } else { this.form.view.value='table'; } this.form.submit()">
            <option value="0" <?= $student_id === 0 ? 'selected' : '' ?>>📊 सर्व विद्यार्थी (टेबल व्ह्यू)</option>
            <?php foreach ($students as $idx => $st): ?>
              <option value="<?= $st['id'] ?>" <?= (int)$st['id'] === $student_id ? 'selected' : '' ?>>
                <?= ($st['roll_no'] ? 'ह.क्र. ' . sanitize($st['roll_no']) : 'क्र. ' . ($idx + 1)) ?> — <?= sanitize($st['name_mr'] ?: $st['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
    </div>
  </form>

  <?php if (!$subjects): ?>
    <div class="alert alert-warning shadow-sm">
      <i class="bi bi-exclamation-triangle-fill me-2"></i> या इयत्तेसाठी विषय उपलब्ध नाहीत. 
      <a href="<?= APP_URL ?>/cce/subjects.php?std=<?= $std ?>" class="alert-link">विषय जोडा / सक्रिय करा</a>.
    </div>
  <?php elseif (!$students): ?>
    <div class="alert alert-warning shadow-sm">
      <i class="bi bi-person-x-fill me-2"></i> या इयत्तेत कोणतेही सक्रिय विद्यार्थी नोंदवलेले नाहीत.
      <a href="<?= APP_URL ?>/students/add.php" class="alert-link">विद्यार्थी जोडा</a>.
    </div>
  <?php else: ?>

    <?php if (!$has_technique_maxes): ?>
      <div class="alert alert-warning py-2 small shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <i class="bi bi-sliders me-1"></i> या विषयासाठी तंत्रनिहाय भारांश (कमाल गुण) अद्याप निश्चित केलेले नाहीत. सर्व ८ आकारिक व ३ संकलित रकाने उपलब्ध आहेत.
        </div>
        <a href="<?= APP_URL ?>/cce/marks_config.php?std=<?= $std ?>" class="btn btn-warning btn-sm fw-bold">
          <i class="bi bi-gear-fill"></i> भारांश निश्चिती करा
        </a>
      </div>
    <?php endif; ?>

    <!-- VIEW MODE 1: VERTICAL MARKS ENTRY (Ideal for Mobile and Single Student) -->
    <?php if ($view_mode === 'vertical' && $curr_student !== null): 
        $st_marks = $existing[(int)$curr_student['id']] ?? [];
        $total_max = (float)$subject['fe_max'] + (float)$subject['se_max'];
    ?>
      <div class="v-student-card mb-4">
        
        <!-- Student Header Bar -->
        <div class="p-3 bg-light border-bottom">
          <div class="d-flex align-items-center gap-2 mb-2">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
              <?= sanitize($curr_student['roll_no'] ?: ($curr_index + 1)) ?>
            </div>
            <div class="flex-grow-1" style="min-width:0;">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="mb-0 fw-bold text-dark fs-5 text-truncate"><?= sanitize($curr_student['name_mr'] ?: $curr_student['name']) ?></h4>
                <span class="badge bg-secondary-subtle text-secondary small flex-shrink-0">ह.क्र. <?= sanitize($curr_student['roll_no'] ?: ($curr_index + 1)) ?></span>
              </div>
              <div class="text-muted small text-truncate">
                <?= sanitize($curr_student['name']) ?> 
                <?php if ($curr_student['gr_no']): ?> | GR No: <?= sanitize($curr_student['gr_no']) ?><?php endif; ?>
                | इयत्ता <?= sanitize(std_label($std)) ?>
              </div>
            </div>
          </div>

          <!-- Navigation toolbar inside student header -->
          <div class="d-flex justify-content-between align-items-center pt-2 border-top gap-2">
            <span class="badge bg-white text-dark border px-2 py-1 fs-6 shadow-sm">
              विद्यार्थी <?= ($curr_index + 1) ?> / <?= count($students) ?>
            </span>
            <div class="btn-group shadow-sm">
              <a href="<?= $prev_student ? APP_URL . "/cce/marks_entry.php?std=$std&semester=$semester&subject_id=$subject_id&student_id={$prev_student['id']}&view=vertical" . ($division !== '' ? '&division=' . urlencode($division) : '') : '#' ?>" 
                 class="btn btn-outline-secondary btn-sm <?= !$prev_student ? 'disabled' : '' ?>" title="मागील विद्यार्थी">
                <i class="bi bi-chevron-left"></i> मागील
              </a>
              <a href="<?= $next_student ? APP_URL . "/cce/marks_entry.php?std=$std&semester=$semester&subject_id=$subject_id&student_id={$next_student['id']}&view=vertical" . ($division !== '' ? '&division=' . urlencode($division) : '') : '#' ?>" 
                 class="btn btn-outline-secondary btn-sm <?= !$next_student ? 'disabled' : '' ?>" title="पुढील विद्यार्थी">
                पुढील <i class="bi bi-chevron-right"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- Vertical Marks Form -->
        <form method="POST" id="verticalMarksForm">
          <?= cce_csrf_field() ?>
          <input type="hidden" name="std" value="<?= $std ?>">
          <input type="hidden" name="semester" value="<?= $semester ?>">
          <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
          <input type="hidden" name="current_student_id" value="<?= $curr_student['id'] ?>">
          <input type="hidden" name="next_student_id" value="<?= $next_student ? $next_student['id'] : 0 ?>">
          <input type="hidden" name="view" value="vertical">
          <?php if ($division !== ''): ?><input type="hidden" name="division" value="<?= sanitize($division) ?>"><?php endif; ?>
          <input type="hidden" name="submit_action" id="vSubmitAction" value="save">

          <div class="p-2 p-md-3">
            
            <!-- Real-Time Scorecard -->
            <div class="v-score-summary mb-3 shadow-sm">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-2">
                <div class="fw-bold small text-white-50">
                  <i class="bi bi-calculator"></i> थेट निकाल व श्रेणी (Live Calculations)
                </div>
                <div class="text-white small">
                  विषय: <b class="text-warning"><?= sanitize($subject['name_mr']) ?></b> (<?= semester_label($semester) ?>)
                </div>
              </div>

              <div class="row g-2">
                <div class="col-4 col-md">
                  <div class="v-score-box">
                    <div class="v-score-label">आकारिक</div>
                    <div class="v-score-val text-warning" id="v_fe_total">-</div>
                    <div class="v-score-max">/<?= to_mr_digits((int)$subject['fe_max']) ?></div>
                  </div>
                </div>
                <div class="col-4 col-md">
                  <div class="v-score-box">
                    <div class="v-score-label">संकलित</div>
                    <div class="v-score-val text-info" id="v_se_total">-</div>
                    <div class="v-score-max">/<?= to_mr_digits((int)$subject['se_max']) ?></div>
                  </div>
                </div>
                <div class="col-4 col-md">
                  <div class="v-score-box">
                    <div class="v-score-label">एकूण</div>
                    <div class="v-score-val text-white" id="v_grand_total">-</div>
                    <div class="v-score-max">/<?= to_mr_digits((int)$total_max) ?></div>
                  </div>
                </div>
                <div class="col-6 col-md">
                  <div class="v-score-box">
                    <div class="v-score-label">टक्केवारी</div>
                    <div class="v-score-val text-light" id="v_percent">-%</div>
                    <div class="v-score-max">100 पैकी</div>
                  </div>
                </div>
                <div class="col-6 col-md">
                  <div class="v-score-box">
                    <div class="v-score-label">श्रेणी (Grade)</div>
                    <div class="v-score-val text-success" id="v_grade">-</div>
                    <div class="v-score-max">प्रगती प्रत</div>
                  </div>
                </div>
              </div>

              <div id="v_over_warning" class="alert alert-danger py-1 px-2 mt-2 mb-0 small d-none">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> गुण कमाल मर्यादेपेक्षा जास्त आहेत!
              </div>
            </div>

            <!-- Two Columns on Tablet/Desktop, Stacked on Mobile -->
            <div class="row g-3">
              
              <!-- Formative Assessment Section (FE 1..8) -->
              <div class="col-12 col-lg-7">
                <div class="card border h-100 shadow-sm rounded-3">
                  <div class="v-section-title v-section-fe">
                    <div>
                      <i class="bi bi-pencil-fill me-2"></i> १. आकारिक मूल्यमापन (Formative)
                    </div>
                    <span class="badge bg-warning text-dark border">कमाल: <?= to_mr_digits((int)$subject['fe_max']) ?> गुण</span>
                  </div>
                  
                  <div class="p-1 p-sm-2">
                    <?php for ($i = 1; $i <= 8; $i++): 
                        $tmax = $subject['fe' . $i . '_max'];
                        $inapplicable = $has_technique_maxes && $tmax === null;
                        $val = isset($st_marks['fe' . $i]) && $st_marks['fe' . $i] !== null ? rtrim(rtrim((string)$st_marks['fe' . $i], '0'), '.') : '';
                    ?>
                      <div class="v-tool-row d-flex align-items-center justify-content-between gap-2 <?= $inapplicable ? 'opacity-50 bg-light' : '' ?>">
                        <div class="flex-grow-1" style="min-width:0;">
                          <div class="fw-bold text-dark fs-6 d-flex align-items-center">
                            <span class="badge bg-secondary-subtle text-secondary me-2 rounded-circle flex-shrink-0" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:0.72rem;"><?= $i ?></span>
                            <span class="text-truncate"><?= sanitize($tech['fe'][$i]) ?></span>
                          </div>
                          <div class="text-muted small ms-3 ps-2">
                            <?php if ($tmax !== null): ?>
                              <span class="badge bg-light text-dark border" style="font-size:0.7rem;">कमाल: <?= to_mr_digits((int)$tmax) ?> गुण</span>
                            <?php elseif ($inapplicable): ?>
                              <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem;">लागू नाही</span>
                            <?php else: ?>
                              <span class="badge bg-light text-secondary" style="font-size:0.7rem;">ऐच्छिक</span>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div>
                          <?php if ($inapplicable): ?>
                            <input type="text" class="form-control form-control-sm text-center bg-light text-muted" value="—" disabled style="width:72px;">
                          <?php else: ?>
                            <input type="number" 
                                   step="0.5" 
                                   min="0" 
                                   <?= $tmax !== null ? 'max="' . (float)$tmax . '"' : '' ?> 
                                   data-kind="fe" 
                                   data-max="<?= $tmax !== null ? (float)$tmax : (float)$subject['fe_max'] ?>" 
                                   name="m[<?= $curr_student['id'] ?>][fe<?= $i ?>]" 
                                   value="<?= sanitize($val) ?>" 
                                   placeholder="0.0" 
                                   inputmode="decimal" 
                                   class="form-control v-input-mark v-calc-input">
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endfor; ?>
                  </div>
                </div>
              </div>

              <!-- Summative Assessment Section (SE 1..3) -->
              <div class="col-12 col-lg-5">
                <div class="card border h-100 shadow-sm rounded-3">
                  <div class="v-section-title v-section-se">
                    <div>
                      <i class="bi bi-file-earmark-text-fill me-2"></i> २. संकलित मूल्यमापन (Summative)
                    </div>
                    <span class="badge bg-primary text-white">कमाल: <?= to_mr_digits((int)$subject['se_max']) ?> गुण</span>
                  </div>

                  <div class="p-1 p-sm-2">
                    <?php if ((float)$subject['se_max'] <= 0): ?>
                      <div class="p-3 text-center text-muted">
                        <i class="bi bi-info-circle fs-3 text-info d-block mb-1"></i>
                        <p class="mb-0 fw-semibold small">या विषयासाठी संकलित मूल्यमापन लागू नाही (कमाल ० गुण).</p>
                        <small class="text-muted" style="font-size:0.75rem;">मूल्यमापन फक्त आकारिक पद्धतीने होते.</small>
                      </div>
                    <?php else: ?>
                      <?php for ($i = 1; $i <= 3; $i++): 
                          $tmax = $subject['se' . $i . '_max'];
                          $inapplicable = $has_technique_maxes && $tmax === null;
                          $val = isset($st_marks['se' . $i]) && $st_marks['se' . $i] !== null ? rtrim(rtrim((string)$st_marks['se' . $i], '0'), '.') : '';
                      ?>
                        <div class="v-tool-row d-flex align-items-center justify-content-between gap-2 <?= $inapplicable ? 'opacity-50 bg-light' : '' ?>">
                          <div class="flex-grow-1" style="min-width:0;">
                            <div class="fw-bold text-dark fs-6 d-flex align-items-center">
                              <span class="badge bg-primary-subtle text-primary me-2 rounded-circle flex-shrink-0" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:0.72rem;"><?= $i ?></span>
                              <span class="text-truncate"><?= sanitize($tech['se'][$i]) ?></span>
                            </div>
                            <div class="text-muted small ms-3 ps-2">
                              <?php if ($tmax !== null): ?>
                                <span class="badge bg-light text-dark border" style="font-size:0.7rem;">कमाल: <?= to_mr_digits((int)$tmax) ?> गुण</span>
                              <?php elseif ($inapplicable): ?>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem;">लागू नाही</span>
                              <?php else: ?>
                                <span class="badge bg-light text-secondary" style="font-size:0.7rem;">चाचणी</span>
                              <?php endif; ?>
                            </div>
                          </div>

                          <div>
                            <?php if ($inapplicable): ?>
                              <input type="text" class="form-control form-control-sm text-center bg-light text-muted" value="—" disabled style="width:72px;">
                            <?php else: ?>
                              <input type="number" 
                                     step="0.5" 
                                     min="0" 
                                     <?= $tmax !== null ? 'max="' . (float)$tmax . '"' : '' ?> 
                                     data-kind="se" 
                                     data-max="<?= $tmax !== null ? (float)$tmax : (float)$subject['se_max'] ?>" 
                                     name="m[<?= $curr_student['id'] ?>][se<?= $i ?>]" 
                                     value="<?= sanitize($val) ?>" 
                                     placeholder="0.0" 
                                     inputmode="decimal" 
                                     class="form-control v-input-mark v-calc-input">
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endfor; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

            </div>

          </div>

          <!-- Sticky Bottom Action Bar -->
          <div class="sticky-save-bar d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center flex-wrap gap-2 w-100 w-md-auto">
              <?php if ($next_student): ?>
                <button type="button" onclick="submitVerticalForm('save_next')" class="btn btn-warning text-white fw-bold px-3 py-2 btn-lg shadow-sm flex-grow-1 flex-md-grow-0" style="background:#167A5B; border-color:#167A5B;">
                  <i class="bi bi-save2"></i> जतन करा व पुढील &rarr;
                </button>
              <?php endif; ?>
              
              <button type="button" onclick="submitVerticalForm('save')" class="btn btn-primary fw-bold px-3 py-2 btn-lg shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="bi bi-check-circle"></i> गुण जतन करा
              </button>
            </div>

            <div class="d-flex align-items-center justify-content-between gap-2 w-100 w-md-auto mt-1 mt-md-0">
              <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $std ?>&semester=<?= $semester ?>&subject_id=<?= $subject_id ?>&student_id=0&view=table<?= $division !== '' ? '&division=' . urlencode($division) : '' ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-table"></i> सर्व विद्यार्थी टेबल
              </a>
              <span class="text-muted small text-truncate" style="max-width:160px;">
                ह.क्र. <?= sanitize($curr_student['roll_no'] ?: ($curr_index + 1)) ?>: <?= sanitize($curr_student['name_mr'] ?: $curr_student['name']) ?>
              </span>
            </div>
          </div>

        </form>

      </div>

    <!-- VIEW MODE 2: ALL STUDENTS HORIZONTAL TABLE -->
    <?php else: ?>

      <div class="alert alert-info py-2 small shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          आकारिक मूल्यमापन कमाल : <b><?= to_mr_digits((int)$subject['fe_max']) ?></b> &nbsp;|&nbsp;
          संकलित मूल्यमापन कमाल : <b><?= to_mr_digits((int)$subject['se_max']) ?></b> &nbsp;|&nbsp;
          एकूण कमाल : <b><?= to_mr_digits((int)($subject['fe_max'] + $subject['se_max'])) ?></b> &nbsp;|&nbsp;
          श्रेणी <?= sanitize($subject['name_mr']) ?> — <?= semester_label($semester) ?>
        </div>
        <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $std ?>&semester=<?= $semester ?>&subject_id=<?= $subject_id ?>&student_id=<?= $students[0]['id'] ?? 0 ?>&view=vertical<?= $division !== '' ? '&division=' . urlencode($division) : '' ?>" class="btn btn-warning btn-sm text-white fw-bold">
          <i class="bi bi-phone"></i> मोबाईल उभी नोंदणी वापरा
        </a>
      </div>

      <form method="POST">
        <?= cce_csrf_field() ?>
        <input type="hidden" name="std" value="<?= $std ?>">
        <input type="hidden" name="semester" value="<?= $semester ?>">
        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
        <input type="hidden" name="view" value="table">
        <?php if ($division !== ''): ?><input type="hidden" name="division" value="<?= sanitize($division) ?>"><?php endif; ?>
        <input type="hidden" name="submit_action" value="save">

        <div class="table-responsive card border-0 shadow-sm" style="overflow:auto; border-radius:12px;">
          <table class="table table-sm table-bordered align-middle mb-0 cce-marks-table" id="marksTable">
            <thead class="text-center sticky-top" style="z-index:3;">
              <tr>
                <th rowspan="2" class="sticky-roll th-dark" style="min-width:45px;">क्र.</th>
                <th rowspan="2" class="sticky-name th-dark" style="min-width:180px;text-align:left;">विद्यार्थ्याचे नाव</th>
                <th rowspan="2" class="th-dark" style="min-width:85px;">उभी नोंद</th>
                <th colspan="8" class="fe-main-head">आकारिक मूल्यमापन (कमाल <?= to_mr_digits((int)$subject['fe_max']) ?> गुण)</th>
                <th colspan="3" class="se-main-head">संकलित (कमाल <?= to_mr_digits((int)$subject['se_max']) ?> गुण)</th>
                <th rowspan="2" class="th-dark" style="min-width:60px;">एकूण</th>
                <th rowspan="2" class="th-dark" style="min-width:55px;">%</th>
                <th rowspan="2" class="th-dark" style="min-width:55px;">श्रेणी</th>
              </tr>
              <tr>
                <?php for ($i=1;$i<=8;$i++): $max = $subject['fe'.$i.'_max']; ?>
                  <th class="fe-subhead" title="<?= sanitize($tech['fe'][$i]) ?>">
                    <span><?= sanitize($tech['fe'][$i]) ?></span>
                    <?php if ($max !== null): ?><br><small>(<?= to_mr_digits((int)$max) ?>)</small><?php endif; ?>
                  </th>
                <?php endfor; ?>
                <?php for ($i=1;$i<=3;$i++): $max = $subject['se'.$i.'_max']; ?>
                  <th class="se-subhead" title="<?= sanitize($tech['se'][$i]) ?>">
                    <span><?= sanitize($tech['se'][$i]) ?></span>
                    <?php if ($max !== null): ?><br><small>(<?= to_mr_digits((int)$max) ?>)</small><?php endif; ?>
                  </th>
                <?php endfor; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $idx => $st): $m = $existing[(int)$st['id']] ?? []; ?>
                <tr data-row>
                  <td class="text-center sticky-roll fw-bold"><?= sanitize($st['roll_no'] ?: ($idx + 1)) ?></td>
                  <td class="sticky-name">
                    <div class="fw-bold text-dark"><?= sanitize($st['name_mr'] ?: $st['name']) ?></div>
                    <div class="text-muted small" style="font-size:0.75rem;"><?= sanitize($st['name']) ?></div>
                  </td>
                  <td class="text-center p-1">
                    <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $std ?>&semester=<?= $semester ?>&subject_id=<?= $subject_id ?>&student_id=<?= $st['id'] ?>&view=vertical<?= $division !== '' ? '&division=' . urlencode($division) : '' ?>" 
                       class="btn btn-outline-warning btn-sm py-1 px-2 fw-bold" style="font-size:0.78rem; white-space:nowrap;" title="या विद्यार्थ्याची उभी नोंदणी">
                      <i class="bi bi-phone"></i> उभी नोंद
                    </a>
                  </td>
                  <?php for ($i = 1; $i <= 8; $i++): $max = $subject['fe'.$i.'_max']; $inapplicable = $has_technique_maxes && $max === null; ?>
                    <td class="p-1">
                      <input class="form-control form-control-sm text-center mark" 
                             data-kind="fe" 
                             type="number" 
                             step="0.5" 
                             min="0" 
                             <?= $max!==null?'max="'.(float)$max.'"':'' ?> 
                             name="m[<?= $st['id'] ?>][fe<?= $i ?>]" 
                             value="<?= $m ? sanitize(rtrim(rtrim((string)$m['fe'.$i], '0'), '.')) : '' ?>" 
                             <?= $inapplicable?'disabled placeholder="—"':'' ?>
                             inputmode="decimal">
                    </td>
                  <?php endfor; ?>
                  <?php for ($i = 1; $i <= 3; $i++): $max = $subject['se'.$i.'_max']; $inapplicable = $has_technique_maxes && $max === null; ?>
                    <td class="p-1">
                      <input class="form-control form-control-sm text-center mark" 
                             data-kind="se" 
                             type="number" 
                             step="0.5" 
                             min="0" 
                             <?= $max!==null?'max="'.(float)$max.'"':'' ?> 
                             name="m[<?= $st['id'] ?>][se<?= $i ?>]" 
                             value="<?= $m ? sanitize(rtrim(rtrim((string)$m['se'.$i], '0'), '.')) : '' ?>" 
                             <?= $inapplicable?'disabled placeholder="—"':'' ?>
                             inputmode="decimal">
                    </td>
                  <?php endfor; ?>
                  <td class="text-center fw-bold total" style="background:#FFF8E1;">-</td>
                  <td class="text-center pct">-</td>
                  <td class="text-center fw-bold grd"><?= sanitize($m['grade'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="sticky-bottom p-3 bg-white border rounded-3 shadow-sm mt-3 d-flex justify-content-between align-items-center" style="z-index:10;">
          <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold" style="background:#167A5B; border-color:#167A5B;">
            <i class="bi bi-save"></i> सर्व गुण जतन करा
          </button>
          <span class="text-muted small d-none d-md-inline">
            <i class="bi bi-info-circle"></i> टीप: गुण भरल्यानंतर 'सर्व गुण जतन करा' बटणावर क्लिक करा. मोबाईलवर सोप्या नोंदीसाठी 'उभी नोंद' वापरा.
          </span>
        </div>
      </form>

    <?php endif; ?>

  <?php endif; ?>

</div>

<!-- Real-time calculation and client-side scripts -->
<script>
// Responsive Auto-Switch: If the page was opened without an explicit ?view= parameter in the URL:
// - On mobile devices (screen width <= 768px), auto-route to vertical entry mode (with first student if not set)
// - On laptop/desktop (> 768px), stay on table mode as is.
(function () {
    try {
        var urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('view')) {
            var isMobileWidth = window.innerWidth <= 768;
            var currentView = <?= json_encode($view_mode) ?>;
            if (isMobileWidth && currentView !== 'vertical') {
                urlParams.set('view', 'vertical');
                <?php if (!empty($students)): ?>
                if (!urlParams.has('student_id') || urlParams.get('student_id') === '0') {
                    urlParams.set('student_id', '<?= (int)($curr_student ? $curr_student['id'] : $students[0]['id']) ?>');
                }
                <?php endif; ?>
                window.location.replace(window.location.pathname + '?' + urlParams.toString());
                return;
            } else if (!isMobileWidth && currentView === 'vertical' && (!urlParams.has('student_id') || urlParams.get('student_id') === '0')) {
                urlParams.set('view', 'table');
                window.location.replace(window.location.pathname + '?' + urlParams.toString());
                return;
            }
        }
    } catch (e) {
        console.warn('Auto-view switch error:', e);
    }
})();

(function () {
    var FE_MAX = <?= (float)($subject['fe_max'] ?? 0) ?>;
    var SE_MAX = <?= (float)($subject['se_max'] ?? 0) ?>;
    var TOTAL_MAX = FE_MAX + SE_MAX;
    var GRADES_TABLE = <?= json_encode(array_map(function($g) {
        return ['grade' => $g['gradetext'], 'min' => (float)$g['marks']];
    }, $grades_master)) ?>;

    function getGrade(pct) {
        for (var i = 0; i < GRADES_TABLE.length; i++) {
            if (pct >= GRADES_TABLE[i].min) {
                return GRADES_TABLE[i].grade;
            }
        }
        return '—';
    }

    // 1. Vertical Single-Student Live Recalculation
    var vForm = document.getElementById('verticalMarksForm');
    if (vForm) {
        function recalcVertical() {
            var feSum = 0, seSum = 0;
            var feOver = false, seOver = false;

            vForm.querySelectorAll('.v-calc-input').forEach(function (inp) {
                var v = parseFloat(inp.value);
                if (isNaN(v) || v < 0) v = 0;
                var kind = inp.getAttribute('data-kind');
                var toolMax = parseFloat(inp.getAttribute('data-max'));

                if (!isNaN(toolMax) && v > toolMax) {
                    inp.classList.add('is-invalid');
                } else {
                    inp.classList.remove('is-invalid');
                }

                if (kind === 'fe') feSum += v;
                if (kind === 'se') seSum += v;
            });

            if (FE_MAX > 0 && feSum > FE_MAX) feOver = true;
            if (SE_MAX > 0 && seSum > SE_MAX) seOver = true;

            var clampedFe = FE_MAX > 0 ? Math.min(feSum, FE_MAX) : feSum;
            var clampedSe = SE_MAX > 0 ? Math.min(seSum, SE_MAX) : seSum;
            var grandTotal = Math.round((clampedFe + clampedSe) * 10) / 10;
            var pct = TOTAL_MAX > 0 ? Math.min(100, Math.round(((clampedFe + clampedSe) / TOTAL_MAX) * 10000) / 100) : 0;
            var grade = getGrade(pct);

            var feEl = document.getElementById('v_fe_total');
            var seEl = document.getElementById('v_se_total');
            var grandEl = document.getElementById('v_grand_total');
            var pctEl = document.getElementById('v_percent');
            var grdEl = document.getElementById('v_grade');
            var warnEl = document.getElementById('v_over_warning');

            if (feEl) feEl.textContent = feSum > 0 ? Math.round(feSum * 10) / 10 : '०';
            if (seEl) seEl.textContent = seSum > 0 ? Math.round(seSum * 10) / 10 : '०';
            if (grandEl) grandEl.textContent = grandTotal > 0 ? grandTotal : '०';
            if (pctEl) pctEl.textContent = (pct > 0 ? pct.toFixed(2) : '०.००') + '%';
            if (grdEl) grdEl.textContent = grandTotal > 0 ? grade : '—';

            if (warnEl) {
                if (feOver || seOver) {
                    warnEl.classList.remove('d-none');
                } else {
                    warnEl.classList.add('d-none');
                }
            }
        }

        vForm.querySelectorAll('.v-calc-input').forEach(function (inp) {
            inp.addEventListener('input', recalcVertical);
            inp.addEventListener('change', recalcVertical);
        });

        // Initial calculation on page load
        recalcVertical();
    }

    // 2. Table Multi-Student Recalculation
    var marksTable = document.getElementById('marksTable');
    if (marksTable) {
        function sumRow(row, kind) {
            var t = 0;
            row.querySelectorAll('input.mark[data-kind="' + kind + '"]').forEach(function (i) {
                var v = parseFloat(i.value);
                if (!isNaN(v) && v > 0) t += v;
            });
            return t;
        }

        function recalcRow(row) {
            var fe = sumRow(row, 'fe'), se = sumRow(row, 'se');
            var over = (FE_MAX > 0 && fe > FE_MAX) || (SE_MAX > 0 && se > SE_MAX);
            var clampedFe = FE_MAX > 0 ? Math.min(fe, FE_MAX) : fe;
            var clampedSe = SE_MAX > 0 ? Math.min(se, SE_MAX) : se;
            var total = Math.round((clampedFe + clampedSe) * 10) / 10;
            var pct = TOTAL_MAX > 0 ? Math.min(100, Math.round(((clampedFe + clampedSe) / TOTAL_MAX) * 10000) / 100) : 0;
            var grade = getGrade(pct);

            var totalCell = row.querySelector('.total');
            var pctCell = row.querySelector('.pct');
            var grdCell = row.querySelector('.grd');

            if (totalCell) totalCell.textContent = total > 0 ? total : '-';
            if (pctCell) pctCell.textContent = total > 0 ? pct.toFixed(2) : '-';
            if (grdCell && total > 0) grdCell.textContent = grade;
            row.classList.toggle('table-danger', over);
            if (totalCell) totalCell.title = over ? 'कमाल गुणांपेक्षा जास्त नोंद' : '';
        }

        marksTable.querySelectorAll('tr[data-row]').forEach(function (row) {
            recalcRow(row);
            row.addEventListener('input', function () { recalcRow(row); });
        });
    }

})();

function submitVerticalForm(action) {
    var form = document.getElementById('verticalMarksForm');
    var actionInput = document.getElementById('vSubmitAction');
    if (form && actionInput) {
        actionInput.value = action;
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
