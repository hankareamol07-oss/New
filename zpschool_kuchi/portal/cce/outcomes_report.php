<?php
/**
 * अध्ययन निष्पत्तीनिहाय विद्यार्थीनिहाय प्रगती अहवाल (Student-wise Radio Button Report)
 * Features:
 * 1. Default Student-wise 4-level Radio Button Table (स्तर १ | स्तर २ | स्तर ३ | स्तर ४)
 * 2. Authentic filled radio button on achieved level
 * 3. All Students batch print option (सर्व विद्यार्थी एकाखाली एक)
 * 4. Proper Marathi Subject Name in filter and header
 * 5. Quick toggles for Short Class Report and Matrix Grid
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$school_id = (int)$_SESSION['school_id'];
$db = cce_db($school_id);
$mysql = getMainDB();
$school = getSchool();
$year = academic_year();
$standards = cce_school_standards($school_id);
if (empty($standards)) {
    for ($i = 1; $i <= 8; $i++) $standards[$i] = std_label($i);
}

$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_GET['semester'] ?? 0); // 0 = Both, 1 = Sem 1, 2 = Sem 2
$view = trim((string)($_GET['view'] ?? 'student')); // Default to 'student' (student-wise radio table)

// Fetch students of this standard
$students = cce_students($school_id, $std);

// Student selection: default to first student if not specified
$student_param = $_GET['student_id'] ?? null;
if ($student_param === 'all') {
    $student_id = 'all'; // Print all students sequentially
} elseif ($student_param !== null && (int)$student_param > 0) {
    $student_id = (int)$student_param;
} else {
    $student_id = !empty($students) ? (int)$students[0]['id'] : 0;
}

// Fetch distinct subjects for this standard from cce_learning_outcomes
$subs = [];
try {
    $stmt = $mysql->prepare("SELECT DISTINCT sub FROM cce_learning_outcomes WHERE std = ? AND medium = 'MA' ORDER BY sub");
    $stmt->execute([$std]);
    $subs = array_column($stmt->fetchAll(), 'sub');
} catch (PDOException $e) {}

$sub_param = $_GET['sub'] ?? null;
if ($sub_param === '0' || $sub_param === 0) {
    $sub_no = 0; // सर्व विषय एकत्र
} else {
    $sub_no = (int)($sub_param ?? ($subs[0] ?? 1));
    if ($sub_no !== 0 && !in_array($sub_no, array_map('intval', $subs), true) && !empty($subs)) {
        $sub_no = (int)$subs[0];
    }
}

if ($sub_no === 0) {
    $sub_name = 'सर्व विषय एकत्र';
    $outcomes = cce_learning_outcomes($std, null);
} else {
    $sub_name = cce_outcome_subject_name($std, $sub_no);
    $outcomes = cce_learning_outcomes($std, $sub_no);
}

$valid_oids = array_flip(array_column($outcomes, 'id'));

// Fetch achieved levels from tenant SQLite DB
$achieved = [];
if ($students && $outcomes) {
    $ids = array_column($students, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $sc  = $semester > 0 ? " AND semester = $semester" : '';
    $stmt = $db->prepare("SELECT student_id, outcome_id, semester, level
        FROM cce_student_outcomes
        WHERE academic_year = ? AND achieved = 1 AND student_id IN ($in) $sc");
    $stmt->execute(array_merge([$year], $ids));
    foreach ($stmt->fetchAll() as $r) {
        $oid = (int)$r['outcome_id'];
        if (isset($valid_oids[$oid])) {
            $achieved[(int)$r['student_id']][$oid][(int)$r['semester']] = (int)($r['level'] ?? 4);
        }
    }
}

$sem_list = $semester > 0 ? [$semester] : [1, 2];
$sl = [1 => 'प्रथम', 2 => 'द्वितीय'];

// Determine which student(s) to render
$render_students = [];
if ($student_id === 'all') {
    $render_students = $students;
} else {
    foreach ($students as $st) {
        if ((int)$st['id'] === (int)$student_id) {
            $render_students = [$st];
            break;
        }
    }
    if (empty($render_students) && !empty($students)) {
        $render_students = [$students[0]];
    }
}

// Helper for authentic radio button display
if (!function_exists('render_radio_level_cell')) {
    function render_radio_level_cell($target_lvl, $current_lvl) {
        $is_checked = ($current_lvl === $target_lvl);
        $html = '<td class="text-center ' . ($is_checked ? 'lvl-' . $target_lvl . ' ht-radio' : '') . '" style="padding:5px 4px; vertical-align:middle;">';
        if ($is_checked) {
            $html .= '<span class="radio-badge lvl-' . $target_lvl . '" title="स्तर ' . to_mr_digits($target_lvl) . ' प्राप्त">';
            $html .= '<svg width="17" height="17" viewBox="0 0 16 16" fill="none" style="vertical-align:middle;flex-shrink:0;">'
                   . '<circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="2.2" fill="#fff"/>'
                   . '<circle cx="8" cy="8" r="4" fill="currentColor"/>'
                   . '</svg>';
            $html .= '<span style="font-weight:700; margin-left:3px;">✓ स्तर ' . to_mr_digits($target_lvl) . '</span>';
            $html .= '</span>';
        } else {
            $html .= '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" style="vertical-align:middle;opacity:0.35;">'
                   . '<circle cx="8" cy="8" r="7" stroke="#94a3b8" stroke-width="1.5" fill="#fff"/>'
                   . '</svg>';
        }
        $html .= '</td>';
        return $html;
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<title>अध्ययन निष्पत्ती स्तर अहवाल — <?= sanitize(std_label($std)) ?> · <?= sanitize($sub_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<?= cce_theme_head_tags() ?>
<style>
* { box-sizing: border-box; }
body {
    font-family: 'Mukta', -apple-system, sans-serif;
    margin: 0;
    background: #eef2f6;
    font-size: 11px;
    color: #14212e;
}

/* Sticky Navigation Toolbar */
.tb {
    position: sticky;
    top: 0;
    z-index: 20;
    background: #17324d;
    color: #fff;
    padding: 8px 16px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.tb button, .tb a.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #238b68;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    font-size: 11px;
}
.tb a.btn-action:hover, .tb button:hover { background: #1c6f53; }
.btn-back { background: #60788d !important; }
.btn-back:hover { background: #475a6a !important; }

/* Filter Bar */
.fl {
    background: #fff;
    padding: 8px 16px;
    border-bottom: 1px solid #cbd5e1;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.fl label {
    font-weight: 600;
    font-size: 11px;
    color: #334155;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.fl select {
    padding: 4px 8px;
    border: 1px solid #b8c4ce;
    border-radius: 4px;
    font-size: 11px;
    background: #fff;
}

/* Printable Container Sheet */
.sh {
    width: 297mm;
    max-width: 98%;
    background: #fff;
    margin: 12px auto;
    padding: 8mm 12mm;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    border-radius: 4px;
    page-break-after: always;
}
.sh:last-child {
    page-break-after: avoid;
}

.hdr-title {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    text-align: center;
}
.sub-banner {
    display: inline-block;
    background: #eef2ff;
    color: #4338ca;
    padding: 2px 12px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 11px;
    margin: 3px auto 6px auto;
    border: 1px solid #c7d2fe;
}

/* Header Metadata Table */
table.mt {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    font-size: 11px;
}
table.mt td {
    padding: 2px 4px;
    border: none;
}

/* Main Radio Outcomes Table */
table.mn {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    vertical-align: middle;
}
table.mn th, table.mn td {
    border: 1px solid #a0b4c8;
    text-align: center;
    padding: 4px 5px;
}
table.mn thead th {
    background: #d7eef8;
    color: #0f172a;
    font-weight: 700;
}
td.lo {
    text-align: left;
    font-size: 9.5px;
    max-width: 260px;
    white-space: normal;
    padding: 3px 6px;
    line-height: 1.35;
}
.sl {
    font-size: 9px;
    background: #eef2f8;
    font-weight: 600;
}

/* Level Color Themes */
.lvl-4 { background: #e8f5e9 !important; color: #1b5e20 !important; }
.lvl-3 { background: #e0f2f1 !important; color: #004d40 !important; }
.lvl-2 { background: #fff8e1 !important; color: #b78103 !important; }
.lvl-1 { background: #fbe9e7 !important; color: #0F4F3B !important; }

.radio-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 10px;
}
.ht-radio {
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
}

/* Summary Card */
.lvl-lgd {
    display: flex;
    gap: 16px;
    justify-content: center;
    align-items: center;
    margin-top: 10px;
    font-size: 10px;
    padding: 6px 12px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    flex-wrap: wrap;
}
.lvl-tag {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 9.5px;
    border: 1px solid rgba(0,0,0,0.1);
}
.sig {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 0 10px;
}

/* Print Optimization */
@media print {
    .tb, .fl { display: none !important; }
    body { background: #fff !important; }
    .sh {
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        width: 100% !important;
        border-radius: 0 !important;
    }
    th, td, .lvl-tag, .lvl-4, .lvl-3, .lvl-2, .lvl-1, .sub-banner, .radio-badge {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    @page {
        size: A4 landscape;
        margin: 6mm 8mm;
    }
}
</style>
</head>
<body>

<!-- TOP NAVIGATION TOOLBAR -->
<div class="tb">
    <button onclick="window.print()">
        <i class="bi bi-printer-fill"></i> 🖨 छापा / PDF
    </button>
    <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>" class="btn-action btn-back">
        <i class="bi bi-arrow-left"></i> अहवाल केंद्र
    </a>
    <a href="<?= APP_URL ?>/cce/outcomes.php?std=<?= $std ?>&sub=<?= urlencode((string)$sub_no) ?>&student_id=<?= is_numeric($student_id) ? $student_id : '' ?>" class="btn-action">
        <i class="bi bi-pencil-square"></i> स्तर नोंदणी (Data Entry)
    </a>

    <!-- Quick Switcher to Matrix and Short Summary -->
    <div style="margin-left: 10px; display:inline-flex; gap:6px;">
        <a href="<?= APP_URL ?>/cce/outcomes_report.php?std=<?= $std ?>&sub=<?= $sub_no ?>&view=student&student_id=<?= $student_id ?>" 
           class="btn-action" style="<?= $view === 'student' ? 'background:#1D9370;' : 'background:#475569;' ?>">
            <i class="bi bi-ui-radios"></i> 👤 विद्यार्थीनिहाय रेडिओ तक्ता
        </a>
        <a href="<?= APP_URL ?>/cce/outcomes_report.php?std=<?= $std ?>&sub=<?= $sub_no ?>&view=short" 
           class="btn-action" style="<?= $view === 'short' ? 'background:#1D9370;' : 'background:#475569;' ?>">
            <i class="bi bi-table"></i> 📊 संक्षिप्त अहवाल (Short)
        </a>
        <a href="<?= APP_URL ?>/cce/outcomes_report.php?std=<?= $std ?>&sub=<?= $sub_no ?>&view=matrix" 
           class="btn-action" style="<?= $view === 'matrix' ? 'background:#1D9370;' : 'background:#475569;' ?>">
            <i class="bi bi-grid-3x3"></i> 📑 वर्गस्तर ग्रिड
        </a>
    </div>

    <!-- Unified Theme & Font Switcher -->
    <?= cce_theme_toolbar_controls(false) ?>

    <span style="opacity:.85; font-size:12px; margin-left:auto;">
        <i class="bi bi-bullseye"></i> <?= sanitize(std_label($std)) ?> · <b><?= sanitize($sub_name) ?></b>
    </span>
</div>

<!-- FILTERS BAR -->
<div class="fl">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="view" value="<?= sanitize($view) ?>">

        <!-- Standard -->
        <label>
            इयत्ता:
            <select name="std" onchange="this.form.submit()">
                <?php foreach ($standards as $n => $name): ?>
                    <option value="<?= $n ?>" <?= $std === $n ? 'selected' : '' ?>><?= sanitize($name) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- Subject with Marathi names -->
        <label>
            विषय:
            <select name="sub" onchange="this.form.submit()" style="font-weight:700; color:#167A5B;">
                <?php foreach ($subs as $sn): 
                    $sn_int = (int)$sn;
                    $lbl = cce_outcome_subject_name($std, $sn_int);
                ?>
                    <option value="<?= $sn_int ?>" <?= $sub_no === $sn_int ? 'selected' : '' ?>>
                        <?= sanitize($lbl) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- Semester -->
        <label>
            सत्र:
            <select name="semester" onchange="this.form.submit()">
                <option value="0" <?= $semester === 0 ? 'selected' : '' ?>>दोन्ही सत्रे (१ व २)</option>
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>प्रथम सत्र</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>द्वितीय सत्र</option>
            </select>
        </label>

        <!-- Student Selector -->
        <?php if ($view === 'student'): ?>
        <label>
            विद्यार्थी:
            <select name="student_id" onchange="this.form.submit()" style="font-weight:700; color:#0f172a; max-width:240px;">
                <option value="all" <?= $student_id === 'all' ? 'selected' : '' ?>>★ सर्व विद्यार्थी (एकाखाली एक छापा)</option>
                <?php foreach ($students as $st): 
                    $sid_val = (int)$st['id'];
                    $has_data = !empty($achieved[$sid_val]);
                ?>
                    <option value="<?= $sid_val ?>" <?= (string)$student_id === (string)$sid_val ? 'selected' : '' ?>>
                        हजेरी <?= sanitize($st['roll_no'] ?: '-') ?> — <?= sanitize($st['name_mr'] ?: $st['name']) ?> <?= $has_data ? '(नोंद भरलेली ✔)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>

    </form>
</div>

<?php if (!$outcomes): ?>
    <div class="sh">
        <p style="text-align:center; padding:30px; color:#64748b;">या इयत्तेसाठी अध्ययन निष्पत्ती आयात झालेल्या नाहीत.</p>
    </div>
<?php elseif (!$students): ?>
    <div class="sh">
        <p style="text-align:center; padding:30px; color:#64748b;">या इयत्तेत नोंदलेले विद्यार्थी नाहीत.</p>
    </div>

<?php elseif ($view === 'short'): ?>

    <!-- ============================================================= -->
    <!-- SHORT SUMMARY REPORT                                          -->
    <!-- ============================================================= -->
    <div class="sh">
        <div class="text-center mb-1">
            <div class="hdr-title">अध्ययन निष्पत्तीनिहाय संक्षिप्त विद्यार्थी प्रगती तक्ता</div>
            <div class="sub-banner"><i class="bi bi-book-half me-1"></i> विषय: <?= sanitize($sub_name) ?></div>
        </div>

        <table class="mt">
            <tr>
                <td style="width:40%">शाळेचे नाव: <b><?= sanitize($school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद शाळा')) ?></b></td>
                <td style="text-align:center; width:30%">विषय: <b style="color:#167A5B"><?= sanitize($sub_name) ?></b></td>
                <td style="text-align:right; width:30%">वर्ष: <b><?= sanitize($year) ?></b></td>
            </tr>
            <tr>
                <td>युडायस: <b><?= sanitize($school['udise_code'] ?: '-') ?></b></td>
                <td style="text-align:center">विद्यार्थी संख्या: <b><?= to_mr_digits(count($students)) ?></b> · निष्पत्ती: <b><?= to_mr_digits(count($outcomes)) ?></b></td>
                <td style="text-align:right">इयत्ता: <b><?= sanitize(std_label($std)) ?></b></td>
            </tr>
        </table>

        <table class="mn">
            <thead>
                <tr>
                    <th style="width:32px">अ.क्र.</th>
                    <th style="width:45px">हजेरी</th>
                    <th style="text-align:left; min-width:180px">विद्यार्थ्याचे नाव</th>
                    <th style="width:50px">सत्र</th>
                    <th style="width:65px">स्तर १<br><small style="font-weight:normal">आरंभिक</small></th>
                    <th style="width:65px">स्तर २<br><small style="font-weight:normal">प्रगतीपथावर</small></th>
                    <th style="width:65px">स्तर ३<br><small style="font-weight:normal">साध्य</small></th>
                    <th style="width:65px">स्तर ४<br><small style="font-weight:normal">प्रगत</small></th>
                    <th style="width:75px">एकूण साध्य</th>
                    <th style="width:60px">संपादणूक %</th>
                    <th style="width:120px">प्रभावी स्तर (रेडिओ)</th>
                    <th style="min-width:120px">शेरा</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tot_lo = count($outcomes);
                $cnt = 0;
                foreach ($students as $st):
                    $sid = (int)$st['id'];
                    $st_map = $achieved[$sid] ?? [];
                    $st_name = $st['name_mr'] ?: $st['name'];

                    foreach ($sem_list as $sem):
                        $cnt++;
                        $l1 = 0; $l2 = 0; $l3 = 0; $l4 = 0;
                        foreach ($outcomes as $lo) {
                            $lvl = $st_map[(int)$lo['id']][$sem] ?? null;
                            if ($lvl === 1) $l1++;
                            elseif ($lvl === 2) $l2++;
                            elseif ($lvl === 3) $l3++;
                            elseif ($lvl === 4) $l4++;
                        }
                        $tot_ach = $l1 + $l2 + $l3 + $l4;
                        $pct = $tot_lo > 0 ? round(($tot_ach / $tot_lo) * 100) : 0;
                        $overall = null;
                        $rem = 'नोंद बाकी';
                        if ($tot_ach > 0) {
                            $avg = (int)round(($l1 * 1 + $l2 * 2 + $l3 * 3 + $l4 * 4) / $tot_ach);
                            $overall = max(1, min(4, $avg));
                            if ($overall === 4) $rem = 'उत्कृष्ट संपादणूक (नैपुण्य)';
                            elseif ($overall === 3) $rem = 'अपेक्षित उद्दिष्टे साध्य';
                            elseif ($overall === 2) $rem = 'मदतीने प्रगतीपथावर';
                            else $rem = 'विशेष मार्गदर्शनाची गरज';
                        }
                ?>
                <tr>
                    <td><?= to_mr_digits($cnt) ?></td>
                    <td style="font-weight:700"><?= to_mr_digits($st['roll_no'] ?: '-') ?></td>
                    <td style="text-align:left; font-weight:600">
                        <a href="<?= APP_URL ?>/cce/outcomes_report.php?std=<?= $std ?>&sub=<?= $sub_no ?>&semester=<?= $sem ?>&student_id=<?= $sid ?>&view=student" style="color:#0f172a; text-decoration:none;">
                            <?= sanitize($st_name) ?> <i class="bi bi-arrow-right-short text-primary"></i>
                        </a>
                    </td>
                    <td class="sl"><?= $sl[$sem] ?></td>
                    <td class="<?= $l1 > 0 ? 'lvl-1' : '' ?>"><?= to_mr_digits($l1) ?></td>
                    <td class="<?= $l2 > 0 ? 'lvl-2' : '' ?>"><?= to_mr_digits($l2) ?></td>
                    <td class="<?= $l3 > 0 ? 'lvl-3' : '' ?>"><?= to_mr_digits($l3) ?></td>
                    <td class="<?= $l4 > 0 ? 'lvl-4' : '' ?>"><?= to_mr_digits($l4) ?></td>
                    <td style="font-weight:700"><?= to_mr_digits($tot_ach) ?> / <?= to_mr_digits($tot_lo) ?></td>
                    <td style="font-weight:700"><?= to_mr_digits($pct) ?>%</td>
                    <td>
                        <?php if ($overall): ?>
                            <span class="radio-badge lvl-<?= $overall ?>">
                                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" style="vertical-align:middle;flex-shrink:0;">
                                    <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="2.2" fill="#fff"/>
                                    <circle cx="8" cy="8" r="4" fill="currentColor"/>
                                </svg>
                                <span>स्तर <?= to_mr_digits($overall) ?></span>
                            </span>
                        <?php else: ?>
                            <span style="color:#cbd5e1">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:left; font-size:9.5px;"><?= sanitize($rem) ?></td>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>

        <div class="sig">
            <span>वर्गशिक्षक स्वाक्षरी</span>
            <span>मुख्याध्यापक स्वाक्षरी</span>
            <span>दिनांक : _____/_____/२०____</span>
        </div>
    </div>

<?php elseif ($view === 'matrix'): ?>

    <!-- ============================================================= -->
    <!-- CLASS MATRIX VIEW                                             -->
    <!-- ============================================================= -->
    <div class="sh">
        <div class="text-center mb-1">
            <div class="hdr-title">अध्ययन निष्पत्तीनिहाय संपादणूक प्रगतिदर्शक नोंदतक्ता (वर्गस्तर ग्रिड)</div>
            <div class="sub-banner"><i class="bi bi-book-half me-1"></i> विषय: <?= sanitize($sub_name) ?></div>
        </div>

        <table class="mt">
            <tr>
                <td style="width:40%">शाळेचे नाव: <b><?= sanitize($school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद शाळा')) ?></b></td>
                <td style="text-align:center; width:30%">विषय: <b style="color:#167A5B"><?= sanitize($sub_name) ?></b></td>
                <td style="text-align:right; width:30%">वर्ष: <b><?= sanitize($year) ?></b></td>
            </tr>
            <tr>
                <td>युडायस: <b><?= sanitize($school['udise_code'] ?: '-') ?></b></td>
                <td style="text-align:center">विद्यार्थी संख्या: <b><?= to_mr_digits(count($students)) ?></b> · निष्पत्ती: <b><?= to_mr_digits(count($outcomes)) ?></b></td>
                <td style="text-align:right">इयत्ता: <b><?= sanitize(std_label($std)) ?></b></td>
            </tr>
        </table>

        <table class="mn">
            <thead>
                <tr>
                    <th rowspan="2" style="width:30px">क्र.</th>
                    <th rowspan="2" style="min-width:180px">अध्ययन निष्पत्ती</th>
                    <th rowspan="2" style="width:44px">सत्र</th>
                    <?php foreach ($students as $st): ?>
                        <th class="rh" title="<?= sanitize($st['name_mr'] ?: $st['name']) ?>">
                            <?= to_mr_digits($st['roll_no'] ?: '-') ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outcomes as $lo):
                    $total_rows = count($sem_list);
                    $first = true;
                    foreach ($sem_list as $sem):
                ?>
                <tr>
                    <?php if ($first): $first = false; ?>
                        <td rowspan="<?= $total_rows ?>"><?= sanitize(trim((string)$lo['no'])) ?></td>
                        <td class="lo" rowspan="<?= $total_rows ?>"><?= sanitize(mb_substr((string)$lo['learningoutcome'], 0, 150)) ?></td>
                    <?php endif; ?>
                    <td class="sl"><?= $sl[$sem] ?></td>

                    <?php foreach ($students as $st):
                        $lvl = $achieved[(int)$st['id']][(int)$lo['id']][$sem] ?? null;
                    ?>
                        <td class="<?= $lvl ? 'lvl-' . $lvl : '' ?>">
                            <?php if ($lvl): ?>
                                <span class="radio-badge lvl-<?= $lvl ?>" title="स्तर <?= to_mr_digits($lvl) ?>">
                                    <svg width="11" height="11" viewBox="0 0 16 16" fill="none" style="vertical-align:middle;flex-shrink:0;">
                                        <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="2.5" fill="#fff"/>
                                        <circle cx="8" cy="8" r="4" fill="currentColor"/>
                                    </svg>
                                    <span><?= to_mr_digits($lvl) ?></span>
                                </span>
                            <?php else: ?>
                                <span style="color:#cbd5e1">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>

        <div class="sig">
            <span>वर्गशिक्षक स्वाक्षरी</span>
            <span>मुख्याध्यापक स्वाक्षरी</span>
            <span>दिनांक : _____/_____/२०____</span>
        </div>
    </div>

<?php else: ?>

    <!-- ============================================================= -->
    <!-- DEFAULT VIEW: STUDENT-WISE RADIO BUTTON TABLE                 -->
    <!-- ============================================================= -->
    <?php foreach ($render_students as $st_curr):
        $sid = (int)$st_curr['id'];
        $st_name = $st_curr['name_mr'] ?: $st_curr['name'];
        $roll = $st_curr['roll_no'] ?: '-';
        $st_map = $achieved[$sid] ?? [];
    ?>
    <div class="sh">

        <div class="text-center mb-1">
            <div class="hdr-title">विद्यार्थीनिहाय अध्ययन निष्पत्ती संपादन नोंदपत्रक</div>
            <div class="sub-banner"><i class="bi bi-book-half me-1"></i> विषय: <?= sanitize($sub_name) ?></div>
        </div>

        <table class="mt">
            <tr>
                <td style="width:38%">शाळेचे नाव: <b><?= sanitize($school['name_mr'] ?: ($school['name'] ?: 'जिल्हा परिषद शाळा')) ?></b></td>
                <td style="text-align:center; width:34%">विषय: <b style="color:#167A5B"><?= sanitize($sub_name) ?></b></td>
                <td style="text-align:right; width:28%">वर्ष: <b><?= sanitize($year) ?></b></td>
            </tr>
            <tr>
                <td>युडायस: <b><?= sanitize($school['udise_code'] ?: '-') ?></b></td>
                <td style="text-align:center">
                    विद्यार्थी: <b style="font-size:12px; color:#0f172a;"><?= sanitize($st_name) ?></b> (हजेरी क्र. <b><?= to_mr_digits($roll) ?></b>)
                </td>
                <td style="text-align:right">इयत्ता: <b><?= sanitize(std_label($std)) ?></b></td>
            </tr>
        </table>

        <div style="margin-bottom:6px; font-weight:700; color:#334155; font-size:11px; display:flex; justify-content:space-between;">
            <span>● अध्ययन निष्पत्तीनिहाय स्तर १ ते ४ संपादणूक नोंद:</span>
            <span style="font-weight:normal; color:#64748b;">(🔘 भरलेले गोल चिन्ह = विद्यार्थ्याने साध्य केलेला स्तर)</span>
        </div>

        <table class="mn">
            <thead>
                <tr>
                    <th style="width:36px">क्र.</th>
                    <th style="width:72px">निष्पत्ती क्र.</th>
                    <th style="text-align:left; min-width:260px">अध्ययन निष्पत्ती</th>
                    <th style="width:48px">सत्र</th>
                    <th style="width:85px">स्तर १<br><small style="font-weight:normal;opacity:.85">आरंभिक</small></th>
                    <th style="width:85px">स्तर २<br><small style="font-weight:normal;opacity:.85">प्रगतीपथावर</small></th>
                    <th style="width:85px">स्तर ३<br><small style="font-weight:normal;opacity:.85">साध्य</small></th>
                    <th style="width:85px">स्तर ४<br><small style="font-weight:normal;opacity:.85">प्रगत</small></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $lvl_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
                $tot_checked = 0;

                foreach ($outcomes as $idx => $lo):
                    $first = true;
                    $row_cnt = count($sem_list);
                    foreach ($sem_list as $sem):
                        $lvl = $st_map[(int)$lo['id']][$sem] ?? null;
                        if ($lvl) {
                            $lvl_counts[$lvl] = ($lvl_counts[$lvl] ?? 0) + 1;
                            $tot_checked++;
                        }
                ?>
                <tr>
                    <?php if ($first): $first = false; ?>
                        <td rowspan="<?= $row_cnt ?>"><?= to_mr_digits($idx + 1) ?></td>
                        <td rowspan="<?= $row_cnt ?>" style="font-weight:700"><?= sanitize(trim((string)$lo['no'])) ?></td>
                        <td rowspan="<?= $row_cnt ?>" class="lo"><?= sanitize($lo['learningoutcome']) ?></td>
                    <?php endif; ?>
                    <td class="sl"><?= $sl[$sem] ?></td>

                    <!-- 4 Authentic Radio Button Level Columns -->
                    <?= render_radio_level_cell(1, $lvl) ?>
                    <?= render_radio_level_cell(2, $lvl) ?>
                    <?= render_radio_level_cell(3, $lvl) ?>
                    <?= render_radio_level_cell(4, $lvl) ?>
                </tr>
                <?php endforeach; endforeach; ?>
            </tbody>
        </table>

        <!-- Summary Legend for this student -->
        <div class="lvl-lgd">
            <b>स्तर संपादणूक सारांश:</b>
            <span class="lvl-tag lvl-1">स्तर १ (आरंभिक): <b><?= to_mr_digits($lvl_counts[1]) ?></b></span>
            <span class="lvl-tag lvl-2">स्तर २ (प्रगतीपथावर): <b><?= to_mr_digits($lvl_counts[2]) ?></b></span>
            <span class="lvl-tag lvl-3">स्तर ३ (साध्य): <b><?= to_mr_digits($lvl_counts[3]) ?></b></span>
            <span class="lvl-tag lvl-4">स्तर ४ (प्रगत): <b><?= to_mr_digits($lvl_counts[4]) ?></b></span>
            <span style="font-weight:700; color:#0f172a; margin-left:12px;">
                एकूण संपादणूक: <b><?= to_mr_digits($tot_checked) ?> / <?= to_mr_digits(count($outcomes) * count($sem_list)) ?></b>
            </span>
        </div>

        <div class="sig">
            <span>वर्गशिक्षक स्वाक्षरी</span>
            <span>मुख्याध्यापक स्वाक्षरी</span>
            <span>दिनांक : _____/_____/२०____</span>
        </div>

    </div>
    <?php endforeach; ?>

<?php endif; ?>

</body>
</html>
