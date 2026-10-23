<?php
declare(strict_types=1);

/**
 * साधनेनिहाय गुणनोंद तक्ता — विषयनिहाय (Subject-wise Tool Marksheet)
 * Ported and enhanced from Vijeta CCE Report-11 (report-11.html / GunnondiExtra)
 * Displays all students for a selected subject with the 8 Formative + 3 Summative tools & भारांश row.
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();
$standards = cce_school_standards($school_id);
if (empty($standards)) {
    for ($i = 1; $i <= 8; $i++) $standards[$i] = std_label($i);
}

$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_GET['semester'] ?? 1) === 2 ? 2 : 1;
$subjects = cce_subjects($school_id, $std);
$subject_id = (int)($_GET['subject_id'] ?? ($subjects ? $subjects[0]['id'] : 0));

$current_subject = null;
foreach ($subjects as $sub) {
    if ((int)$sub['id'] === $subject_id) {
        $current_subject = $sub;
        break;
    }
}
if (!$current_subject && $subjects) {
    $current_subject = $subjects[0];
    $subject_id = (int)$current_subject['id'];
}

$students = cce_students($school_id, $std);

// Fetch all marks for this subject via dual-database matrix
$marks = [];
if ($students && $current_subject) {
    $student_ids = array_map('intval', array_column($students, 'id'));
    $matrix = cce_student_marks_matrix($school_id, $year, $semester, $student_ids);
    foreach ($student_ids as $sid) {
        if (isset($matrix[$sid][$subject_id])) {
            $marks[$sid] = $matrix[$sid][$subject_id];
        }
    }
}

$fe_max = (float)($current_subject['fe_max'] ?? ($std <= 2 ? 25 : 40));
$se_max = (float)($current_subject['se_max'] ?? ($std <= 2 ? 25 : 60));
$total_max = $fe_max + $se_max;

$class_teacher = cce_class_teacher_name($school_id, $std);
$headmaster = cce_headmaster_name($school_id, $semester);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>साधनेनिहाय गुणनोंद तक्ता (विषयनिहाय) — <?= sanitize($current_subject['name_mr'] ?? '') ?> — इयत्ता <?= std_label($std) ?></title>
    <link href="<?= APP_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= cce_theme_head_tags() ?>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Utsaah', 'Noto Sans Devanagari', 'Nirmala UI', sans-serif;
            background-color: #f4f6f9;
            color: #111;
            margin: 0;
            padding: 0;
        }

        .no-print-bar {
            background: #004d40;
            color: #fff;
            padding: 10px 16px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .sheet-container {
            max-width: 297mm;
            margin: 15px auto;
        }

        .sheet {
            background: #fff;
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto 20px auto;
            padding: 8mm 10mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .header-title {
            text-align: center;
            border-bottom: 2px solid #004d40;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .header-title h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #004d40;
            margin: 0;
        }
        .header-title h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #d81b60;
            margin: 2px 0 0 0;
        }

        .meta-grid {
            display: flex;
            justify-content: space-between;
            font-size: 0.92rem;
            line-height: 1.5;
            margin-bottom: 8px;
            background: #e0f2f1;
            padding: 6px 10px;
            border: 1px solid #b2dfdb;
            border-radius: 4px;
        }

        table.marks-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            margin-bottom: 12px;
        }

        table.marks-table th,
        table.marks-table td {
            border: 1px solid #37474f;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }

        table.marks-table thead tr.trth {
            background-color: #d4e6e4;
            font-weight: 700;
        }

        table.marks-table thead tr.trsub {
            background-color: #f1f8f6;
        }

        .rotate-col {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            white-space: nowrap;
            padding: 6px 1px !important;
            font-size: 0.82rem;
            height: 85px;
            max-width: 26px;
        }

        .student-name {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: 600;
            white-space: nowrap;
            width: 180px;
        }

        .row-bharansh {
            background-color: #fff9c4 !important;
            font-weight: 700;
            color: #b71c1c;
        }

        .total-cell {
            font-weight: 700;
            background-color: #fff8e1;
        }

        .grand-cell {
            font-weight: 700;
            background-color: #e8f5e9;
        }

        .grade-cell {
            font-weight: 700;
            color: #004d40;
        }

        .page-footer {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            text-align: center;
            font-size: 0.95rem;
            padding: 0 10mm;
        }

        .page-footer .sign-box {
            min-width: 50mm;
            border-top: 1px solid #222;
            padding-top: 4px;
            margin-top: 25px;
        }

        @media print {
            .no-print-bar { display: none !important; }
            body { background: #fff !important; }
            .sheet-container { margin: 0 !important; max-width: none !important; }
            .sheet {
                box-shadow: none !important;
                margin: 0 !important;
                width: 297mm !important;
                min-height: 210mm !important;
                padding: 8mm !important;
                page-break-after: always !important;
            }
            @page {
                size: A4 landscape;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left"></i> अहवाल केंद्र
            </a>
            <strong class="fs-6">साधनेनिहाय गुणनोंद तक्ता (विषयनिहाय Report-11)</strong>
        </div>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0">
            <select name="std" class="form-select form-select-sm" style="width:110px;" onchange="this.form.submit()">
                <?php foreach ($standards as $n => $l): ?>
                    <option value="<?= $n ?>" <?= $n === $std ? 'selected' : '' ?>><?= std_label($n) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="semester" class="form-select form-select-sm" style="width:120px;" onchange="this.form.submit()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>प्रथम सत्र</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>द्वितीय सत्र</option>
            </select>
            <select name="subject_id" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $subject_id ? 'selected' : '' ?>>
                        <?= sanitize($s['name_mr'] ?: $s['short_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-warning px-3" onclick="window.print()">
                <i class="bi bi-printer"></i> प्रिंट / PDF
            </button>
            <!-- Unified Theme & Font Switcher -->
            <?= cce_theme_toolbar_controls(false) ?>
        </form>
    </div>
</div>

<div class="sheet-container">
<div class="sheet">
    <div class="header-title">
        <h1><?= sanitize($school['name'] ?? 'प्राथमिक शाळा') ?></h1>
        <h2>सातत्यपूर्ण सर्वंकष मूल्यमापन (CCE) — विषयवार साधनेनिहाय गुणनोंद तक्ता</h2>
        <div style="font-size:0.85rem; color:#004d40; margin-top:2px;">
            विषय: <strong><?= sanitize($current_subject['name_mr'] ?? '-') ?></strong> · <?= $semester === 1 ? 'प्रथम सत्र' : 'द्वितीय सत्र' ?> · शैक्षणिक वर्ष: <?= sanitize($year) ?> · युडायस: <?= sanitize($school['udise_code'] ?? '-') ?>
        </div>
    </div>

    <div class="meta-grid">
        <div>इयत्ता: <strong><?= std_label($std) ?></strong></div>
        <div>विषय: <strong class="text-primary"><?= sanitize($current_subject['name_mr'] ?? '-') ?></strong></div>
        <div>सत्र: <strong><?= $semester === 1 ? 'प्रथम सत्र (सत्र १)' : 'द्वितीय सत्र (सत्र २)' ?></strong></div>
        <div>विद्यार्थी संख्या: <strong><?= to_mr_digits(count($students)) ?></strong></div>
        <div>कमाल गुण: <strong><?= to_mr_digits($total_max) ?></strong> (आकारिक: <?= to_mr_digits($fe_max) ?> + संकलित: <?= to_mr_digits($se_max) ?>)</div>
    </div>

    <table class="marks-table">
        <thead>
            <tr class="trth">
                <th rowspan="3" style="width:28px;">अ.क्र.</th>
                <th rowspan="3" class="student-name">विद्यार्थ्याचे नाव</th>
                <th rowspan="3" style="width:34px;">ह.क्र.</th>
                <th rowspan="3" style="width:34px;">लिंग</th>
                <th rowspan="3" style="width:38px;">प्रवर्ग</th>
                <th colspan="9">आकारिक मूल्यमापन (Formative Assessment)</th>
                <th colspan="4">संकलित मूल्यमापन (Summative)</th>
                <th rowspan="3" class="rotate-col">एकूण गुण</th>
                <th rowspan="3" class="rotate-col">टक्केवारी (%)</th>
                <th rowspan="3" class="rotate-col">श्रेणी</th>
            </tr>
            <tr class="trth">
                <th class="rotate-col">दैनंदिन निरीक्षण</th>
                <th class="rotate-col">तोंडी काम</th>
                <th class="rotate-col">प्रात्य./प्रयोग</th>
                <th class="rotate-col">उपक्रम/कृती</th>
                <th class="rotate-col">प्रकल्प</th>
                <th class="rotate-col">चाचणी (लेखी)</th>
                <th class="rotate-col">स्वाध्याय/वर्गकार्य</th>
                <th class="rotate-col">इतर साधने</th>
                <th rowspan="2" class="rotate-col total-cell">आकारिक एकूण</th>
                <th class="rotate-col">तोंडी</th>
                <th class="rotate-col">प्रात्यक्षिक</th>
                <th class="rotate-col">लेखी</th>
                <th rowspan="2" class="rotate-col total-cell">संकलित एकूण</th>
            </tr>
            <tr class="trsub">
                <th>१</th><th>२</th><th>३</th><th>४</th><th>५</th><th>६</th><th>७</th><th>८</th>
                <th>१</th><th>२</th><th>३</th>
            </tr>
        </thead>
        <tbody>
            <!-- BHARANSH (MAX MARKS) ROW -->
            <tr class="row-bharansh">
                <td colspan="5" style="text-align:right; padding-right:8px;">भारांश निश्चिती (कमाल गुण):</td>
                <td><?= !empty($current_subject['fe1_max']) ? to_mr_digits($current_subject['fe1_max']) : '-' ?></td>
                <td><?= !empty($current_subject['fe2_max']) ? to_mr_digits($current_subject['fe2_max']) : '-' ?></td>
                <td><?= !empty($current_subject['fe3_max']) ? to_mr_digits($current_subject['fe3_max']) : '-' ?></td>
                <td><?= !empty($current_subject['fe4_max']) ? to_mr_digits($current_subject['fe4_max']) : '-' ?></td>
                <td><?= !empty($current_subject['fe5_max']) ? to_mr_digits($current_subject['fe5_max']) : '-' ?></td>
                <td><?= !empty($current_subject['fe6_max']) ? to_mr_digits($current_subject['fe6_max']) : '-' ?></td>
                <td><?= !empty($current_subject['fe7_max']) ? to_mr_digits($current_subject['fe7_max']) : '-' ?></td>
                <td><?= !empty($current_subject['fe8_max']) ? to_mr_digits($current_subject['fe8_max']) : '-' ?></td>
                <td class="total-cell"><?= to_mr_digits($fe_max) ?></td>
                <td><?= !empty($current_subject['se1_max']) ? to_mr_digits($current_subject['se1_max']) : '-' ?></td>
                <td><?= !empty($current_subject['se2_max']) ? to_mr_digits($current_subject['se2_max']) : '-' ?></td>
                <td><?= !empty($current_subject['se3_max']) ? to_mr_digits($current_subject['se3_max']) : '-' ?></td>
                <td class="total-cell"><?= to_mr_digits($se_max) ?></td>
                <td class="grand-cell"><?= to_mr_digits($total_max) ?></td>
                <td>१००%</td>
                <td>-</td>
            </tr>

            <!-- STUDENTS ROWS -->
            <?php 
            $sr = 1;
            $class_total_obtained = 0;
            $class_count = 0;
            foreach ($students as $student):
                $sid = (int)$student['id'];
                $m = $marks[$sid] ?? [];
                $is_girl = cce_is_female_student($student);

                $fe_tot = isset($m['fe_total']) ? (float)$m['fe_total'] : 0;
                $se_tot = isset($m['se_total']) ? (float)$m['se_total'] : 0;
                $gtot = isset($m['grand_total']) ? (float)$m['grand_total'] : ($fe_tot + $se_tot);
                $pct = isset($m['percent']) ? (float)$m['percent'] : ($total_max > 0 ? round(($gtot / $total_max) * 100, 1) : 0);
                $grd = !empty($m['grade']) ? cce_normalize_grade($m['grade']) : cce_calc_grade($pct);

                $class_total_obtained += $gtot;
                $class_count++;
            ?>
            <tr>
                <td><?= to_mr_digits($sr++) ?></td>
                <td class="student-name"><?= sanitize($student['name_mr'] ?: $student['name']) ?></td>
                <td><?= to_mr_digits($student['roll_no']) ?></td>
                <td><?= $is_girl ? 'मुली' : 'मुलगा' ?></td>
                <td><?= sanitize($student['category'] ?? '-') ?></td>
                <td><?= !empty($m['fe1']) ? to_mr_digits($m['fe1']) : '-' ?></td>
                <td><?= !empty($m['fe2']) ? to_mr_digits($m['fe2']) : '-' ?></td>
                <td><?= !empty($m['fe3']) ? to_mr_digits($m['fe3']) : '-' ?></td>
                <td><?= !empty($m['fe4']) ? to_mr_digits($m['fe4']) : '-' ?></td>
                <td><?= !empty($m['fe5']) ? to_mr_digits($m['fe5']) : '-' ?></td>
                <td><?= !empty($m['fe6']) ? to_mr_digits($m['fe6']) : '-' ?></td>
                <td><?= !empty($m['fe7']) ? to_mr_digits($m['fe7']) : '-' ?></td>
                <td><?= !empty($m['fe8']) ? to_mr_digits($m['fe8']) : '-' ?></td>
                <td class="total-cell"><?= to_mr_digits($fe_tot) ?></td>
                <td><?= !empty($m['se1']) ? to_mr_digits($m['se1']) : '-' ?></td>
                <td><?= !empty($m['se2']) ? to_mr_digits($m['se2']) : '-' ?></td>
                <td><?= !empty($m['se3']) ? to_mr_digits($m['se3']) : '-' ?></td>
                <td class="total-cell"><?= to_mr_digits($se_tot) ?></td>
                <td class="grand-cell"><?= to_mr_digits($gtot) ?></td>
                <td><?= to_mr_digits($pct) ?>%</td>
                <td class="grade-cell"><?= sanitize($grd) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php 
            $avg_obtained = $class_count > 0 ? round($class_total_obtained / $class_count, 1) : 0;
            $avg_pct = $total_max > 0 ? round(($avg_obtained / $total_max) * 100, 1) : 0;
            $avg_grade = cce_grade_for_percent($avg_pct);
            ?>
            <tr style="background:#e0f2f1; font-weight:700;">
                <td colspan="5" style="text-align:right; padding-right:10px;">वर्ग सरासरी (Class Average):</td>
                <td colspan="8">विद्यार्थी संख्या: <?= to_mr_digits($class_count) ?></td>
                <td class="total-cell">-</td>
                <td colspan="3">सरासरी गुण व श्रेणी:</td>
                <td class="total-cell">-</td>
                <td class="grand-cell"><?= to_mr_digits($avg_obtained) ?></td>
                <td><?= to_mr_digits($avg_pct) ?>%</td>
                <td class="grade-cell fs-6"><?= sanitize($avg_grade) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="page-footer">
        <div class="sign-box">
            <div>विषय शिक्षक स्वाक्षरी</div>
            <small class="text-muted"><?= sanitize($current_subject['name_mr'] ?? '') ?></small>
        </div>
        <div class="sign-box">
            <div><?= sanitize($class_teacher ?: 'वर्गशिक्षक') ?></div>
            <small class="text-muted">वर्गशिक्षक स्वाक्षरी</small>
        </div>
        <div class="sign-box">
            <div><?= sanitize($headmaster ?: 'मुख्याध्यापक') ?></div>
            <small class="text-muted">मुख्याध्यापक स्वाक्षरी</small>
        </div>
    </div>
</div>
</div>

</body>
</html>
