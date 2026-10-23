<?php
declare(strict_types=1);

/**
 * साधनेनिहाय गुणनोंद तक्ता — विद्यार्थीनिहाय (Student-wise Tool Marksheet)
 * Ported and enhanced from Vijeta CCE Report-03 (report-03.html)
 * Displays 8 Formative (FE) tools + 3 Summative (SE) tools breakdown with भारांश कमाल गुण.
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
$all = !empty($_GET['all']);
$student_id = (int)($_GET['student_id'] ?? 0);

$students = cce_students($school_id, $std);
if (!$all) {
    if (!$student_id && $students) {
        $student_id = (int)$students[0]['id'];
    }
    $target_students = [];
    foreach ($students as $s) {
        if ((int)$s['id'] === $student_id) {
            $target_students[] = $s;
            break;
        }
    }
    if (empty($target_students) && $students) {
        $target_students = [$students[0]];
        $student_id = (int)$students[0]['id'];
    }
} else {
    $target_students = $students;
}

$subjects = cce_subjects($school_id, $std);

// Fetch all marks for these students in this std/semester
$student_ids = array_map('intval', array_column($target_students, 'id'));
$marks = [];
if ($student_ids && $subjects) {
    $marks = cce_student_marks_matrix($school_id, $year, $semester, $student_ids);
}

$class_teacher = cce_class_teacher_name($school_id, $std);
$headmaster = cce_headmaster_name($school_id, $semester);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>साधनेनिहाय गुणनोंद तक्ता (विद्यार्थीनिहाय) — इयत्ता <?= std_label($std) ?></title>
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
            background: #1a237e;
            color: #fff;
            padding: 10px 16px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .sheet-container {
            max-width: 210mm;
            margin: 15px auto;
        }

        .sheet {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 20px auto;
            padding: 10mm 12mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .header-title {
            text-align: center;
            border-bottom: 2px solid #0d47a1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .header-title h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0d47a1;
            margin: 0;
        }
        .header-title h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #c2185b;
            margin: 2px 0 0 0;
        }

        .meta-grid {
            display: flex;
            justify-content: space-between;
            font-size: 0.92rem;
            line-height: 1.5;
            margin-bottom: 8px;
            background: #f8f9fa;
            padding: 6px 10px;
            border: 1px solid #cfd8dc;
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
            border: 1px solid #455a64;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }

        table.marks-table thead tr.trth {
            background-color: #e8eaf6;
            font-weight: 700;
        }

        table.marks-table thead tr.trsub {
            background-color: #f5f5f5;
        }

        .rotate-col {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            white-space: nowrap;
            padding: 6px 1px !important;
            font-size: 0.82rem;
            height: 90px;
            max-width: 24px;
        }

        .sub-name {
            text-align: left !important;
            padding-left: 6px !important;
            font-weight: 600;
            white-space: nowrap;
        }

        .type-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #37474f;
            width: 38px;
        }

        .row-obtained {
            background-color: #ffffff;
            font-weight: 500;
        }

        .row-max {
            background-color: #fafafa;
            color: #546e7a;
            font-size: 0.8rem;
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
            color: #0d47a1;
        }

        .page-footer {
            margin-top: 20px;
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
                width: 210mm !important;
                min-height: 297mm !important;
                padding: 10mm !important;
                page-break-after: always !important;
            }
            @page {
                size: A4 portrait;
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
            <strong class="fs-6">साधनेनिहाय गुणनोंद तक्ता (विद्यार्थीनिहाय Report-03)</strong>
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
            <select name="student_id" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
                <option value="0" <?= $all ? 'selected' : '' ?>>-- सर्व विद्यार्थी (छापा) --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (!$all && (int)$s['id'] === $student_id) ? 'selected' : '' ?>>
                        <?= sanitize($s['roll_no']) ?>. <?= sanitize($s['name_mr'] ?: $s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$all): ?>
                <input type="hidden" name="all" value="0">
                <a href="<?= APP_URL ?>/cce/tool_marksheet_student.php?std=<?= $std ?>&semester=<?= $semester ?>&all=1" class="btn btn-sm btn-warning">
                    <i class="bi bi-collection"></i> संपूर्ण वर्ग छापा
                </a>
            <?php else: ?>
                <input type="hidden" name="all" value="1">
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-success px-3" onclick="window.print()">
                <i class="bi bi-printer"></i> प्रिंट / PDF
            </button>
            <!-- Unified Theme & Font Switcher -->
            <?= cce_theme_toolbar_controls(false) ?>
        </form>
    </div>
</div>

<div class="sheet-container">
<?php if (empty($target_students)): ?>
    <div class="alert alert-warning m-4 text-center">या इयत्तेत विद्यार्थी उपलब्ध नाहीत.</div>
<?php endif; ?>

<?php foreach ($target_students as $student): 
    $sid = (int)$student['id'];
    $is_girl = cce_is_female_student($student);
    $total_obtained = 0;
    $total_maximum = 0;
?>
<div class="sheet">
    <div class="header-title">
        <h1><?= sanitize($school['name'] ?? 'प्राथमिक शाळा') ?></h1>
        <h2>सातत्यपूर्ण सर्वंकष मूल्यमापन (CCE) — साधनेनिहाय गुणनोंद तक्ता</h2>
        <div style="font-size:0.85rem; color:#455a64; margin-top:2px;">
            <?= $semester === 1 ? 'प्रथम सत्र' : 'द्वितीय सत्र' ?> · शैक्षणिक वर्ष: <?= sanitize($year) ?> · युडायस: <?= sanitize($school['udise_code'] ?? '-') ?>
        </div>
    </div>

    <div class="meta-grid">
        <div>विद्यार्थ्याचे नाव: <strong><?= sanitize($student['name_mr'] ?: $student['name']) ?></strong></div>
        <div>इयत्ता: <strong><?= std_label($std) ?></strong> (<?= sanitize($student['section'] ?? '-') ?>)</div>
        <div>हजेरी क्र.: <strong><?= to_mr_digits($student['roll_no']) ?></strong></div>
        <div>लिंग: <strong><?= $is_girl ? 'मुलगी' : 'मुलगा' ?></strong></div>
        <div>प्रवर्ग: <strong><?= sanitize($student['category'] ?? '-') ?></strong></div>
    </div>

    <table class="marks-table">
        <thead>
            <tr class="trth">
                <th rowspan="3" style="width:24px;">अ.क्र.</th>
                <th rowspan="3" style="width:130px;">विषय</th>
                <th rowspan="3" style="width:38px;">गुण प्रकार</th>
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
            <?php 
            $sub_index = 1;
            foreach ($subjects as $sub): 
                $sub_id = (int)$sub['id'];
                $m = $marks[$sid][$sub_id] ?? [];
                $fe_max = (float)($sub['fe_max'] ?? ($std <= 2 ? 25 : 40));
                $se_max = (float)($sub['se_max'] ?? ($std <= 2 ? 25 : 60));
                $sub_max = $fe_max + $se_max;

                $fe_tot = isset($m['fe_total']) ? (float)$m['fe_total'] : 0;
                $se_tot = isset($m['se_total']) ? (float)$m['se_total'] : 0;
                $gtot = isset($m['grand_total']) ? (float)$m['grand_total'] : ($fe_tot + $se_tot);
                $pct = isset($m['percent']) ? (float)$m['percent'] : ($sub_max > 0 ? round(($gtot / $sub_max) * 100, 1) : 0);
                $grd = !empty($m['grade']) ? cce_normalize_grade($m['grade']) : cce_calc_grade($pct);

                $total_obtained += $gtot;
                $total_maximum += $sub_max;
            ?>
            <!-- ROW 1: OBTAINED MARKS -->
            <tr class="row-obtained">
                <td rowspan="2"><?= to_mr_digits($sub_index++) ?></td>
                <td rowspan="2" class="sub-name"><?= sanitize($sub['name_mr'] ?: $sub['short_name']) ?></td>
                <td class="type-label text-primary">प्राप्त</td>
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
                <td rowspan="2" class="grand-cell"><?= to_mr_digits($gtot) ?></td>
                <td rowspan="2"><?= to_mr_digits($pct) ?>%</td>
                <td rowspan="2" class="grade-cell"><?= sanitize($grd) ?></td>
            </tr>
            <!-- ROW 2: MAXIMUM MARKS (BHARANSH) -->
            <tr class="row-max">
                <td class="type-label text-muted">पैकी</td>
                <td><?= !empty($sub['fe1_max']) ? to_mr_digits($sub['fe1_max']) : '-' ?></td>
                <td><?= !empty($sub['fe2_max']) ? to_mr_digits($sub['fe2_max']) : '-' ?></td>
                <td><?= !empty($sub['fe3_max']) ? to_mr_digits($sub['fe3_max']) : '-' ?></td>
                <td><?= !empty($sub['fe4_max']) ? to_mr_digits($sub['fe4_max']) : '-' ?></td>
                <td><?= !empty($sub['fe5_max']) ? to_mr_digits($sub['fe5_max']) : '-' ?></td>
                <td><?= !empty($sub['fe6_max']) ? to_mr_digits($sub['fe6_max']) : '-' ?></td>
                <td><?= !empty($sub['fe7_max']) ? to_mr_digits($sub['fe7_max']) : '-' ?></td>
                <td><?= !empty($sub['fe8_max']) ? to_mr_digits($sub['fe8_max']) : '-' ?></td>
                <td class="total-cell"><?= to_mr_digits($fe_max) ?></td>
                <td><?= !empty($sub['se1_max']) ? to_mr_digits($sub['se1_max']) : '-' ?></td>
                <td><?= !empty($sub['se2_max']) ? to_mr_digits($sub['se2_max']) : '-' ?></td>
                <td><?= !empty($sub['se3_max']) ? to_mr_digits($sub['se3_max']) : '-' ?></td>
                <td class="total-cell"><?= to_mr_digits($se_max) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php 
            $overall_pct = $total_maximum > 0 ? round(($total_obtained / $total_maximum) * 100, 2) : 0;
            $overall_grade = cce_grade_for_percent($overall_pct);
            ?>
            <tr style="background:#e3f2fd; font-weight:700;">
                <td colspan="3" style="text-align:right; padding-right:10px;">एकूण बेरीज:</td>
                <td colspan="8">प्राप्त / पैकी एकूण गुण:</td>
                <td class="total-cell"><?= to_mr_digits($total_obtained) ?> / <?= to_mr_digits($total_maximum) ?></td>
                <td colspan="4">एकंदर टक्केवारी व श्रेणी:</td>
                <td class="grand-cell"><?= to_mr_digits($total_obtained) ?></td>
                <td><?= to_mr_digits($overall_pct) ?>%</td>
                <td class="grade-cell fs-6"><?= sanitize($overall_grade) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="page-footer">
        <div class="sign-box">
            <div><?= sanitize($class_teacher ?: 'वर्गशिक्षक') ?></div>
            <small class="text-muted">वर्गशिक्षक स्वाक्षरी</small>
        </div>
        <div class="sign-box">
            <div>पालक स्वाक्षरी</div>
            <small class="text-muted">पालक</small>
        </div>
        <div class="sign-box">
            <div><?= sanitize($headmaster ?: 'मुख्याध्यापक') ?></div>
            <small class="text-muted">मुख्याध्यापक स्वाक्षरी</small>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

</body>
</html>
