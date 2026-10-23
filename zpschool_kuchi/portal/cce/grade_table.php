<?php
/**
 * CCE class grade register, matching the wide Marathi class-register print layout.
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();
$standards = cce_school_standards($school_id);
$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_GET['semester'] ?? 1) === 2 ? 2 : 1;
$mode = ($_GET['mode'] ?? 'grade') === 'caste' ? 'caste' : 'grade';
$subjects = cce_subjects($school_id, $std);
$students = cce_students($school_id, $std);

$marks = [];
if ($students) {
    $ids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year, $semester], $ids));
    foreach ($stmt->fetchAll() as $row) $marks[(int)$row['student_id']][(int)$row['subject_id']] = $row;

    if (empty($marks)) {
        try {
            $sqlite = cce_db($school_id);
            $stmtS = $sqlite->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ? AND student_id IN ($in)");
            $stmtS->execute(array_merge([$school_id, $year, $semester], $ids));
            $s_rows = $stmtS->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($s_rows)) {
                foreach ($s_rows as $row) $marks[(int)$row['student_id']][(int)$row['subject_id']] = $row;
                if (function_exists('sync_marks_to_mysql')) sync_marks_to_mysql($school_id, $s_rows);
            }
        } catch (Throwable $e) {}
    }
}

function gt_num($value): string
{
    return to_mr_digits(rtrim(rtrim(number_format((float)$value, 1, '.', ''), '0'), '.'));
}

function student_caste(array $student): string
{
    foreach (['caste', 'caste_category', 'cast', 'category', 'cast_cat'] as $field) {
        if (!empty($student[$field])) return trim((string)$student[$field]);
    }
    return '-';
}

$student_rows = [];
$grade_counts = [];
$caste_counts = [];
foreach ($students as $student) {
    $sid = (int)$student['id'];
    $row = $marks[$sid] ?? [];
    $total = 0;
    $max = 0;
    $subject_rows = [];
    foreach ($subjects as $subject) {
        $mark = $row[(int)$subject['id']] ?? null;
        $subject_max = (float)$subject['fe_max'] + (float)$subject['se_max'];
        $subject_total = (float)($mark['grand_total'] ?? 0);
        $total += $subject_total;
        $max += $subject_max;
        $subject_rows[] = [
            'fe' => (float)($mark['fe_total'] ?? 0),
            'se' => (float)($mark['se_total'] ?? 0),
            'total' => $subject_total,
            'max' => $subject_max,
            'grade' => $mark['grade'] ?? cce_grade_for_percent($subject_max > 0 ? $subject_total / $subject_max * 100 : 0),
        ];
    }
    $percent = $max > 0 ? round($total / $max * 100, 2) : 0;
    $grade = cce_grade_for_percent($percent);
    $student_rows[] = compact('student', 'subject_rows', 'total', 'max', 'percent', 'grade');
    $grade_counts[$grade] = ($grade_counts[$grade] ?? 0) + 1;
    $caste = student_caste($student);
    $caste_counts[$caste][$grade] = ($caste_counts[$caste][$grade] ?? 0) + 1;
}

$mode_title = $mode === 'caste' ? 'जातीनुसार श्रेणी तक्ता' : 'श्रेणी तक्ता (वर्गस्तर)';
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($mode_title) ?> - <?= sanitize($school['name_mr'] ?? $school['name'] ?? '') ?></title>
<link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;700&display=swap" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
*{box-sizing:border-box}body{font-family:'Utsaah','Mukta','Nirmala UI',sans-serif;margin:0;color:#162231;background:#eef2f6;font-size:13px}.toolbar{position:sticky;top:0;z-index:1000;background:#17324d;color:#fff;padding:10px 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}.toolbar a,.toolbar button{border:0;background:#238b68;color:#fff;text-decoration:none;border-radius:6px;padding:8px 14px;font:inherit;font-size:14px;font-weight:700;min-height:40px;display:inline-flex;align-items:center;cursor:pointer}.toolbar a{background:#60788d}.filters{background:#fff;padding:10px 14px;border-bottom:1px solid #ccd6df}.filters select{padding:6px 10px;border-radius:6px;font-size:13px}.sheet{background:#fff;margin:12px auto;padding:9mm;width:297mm;min-height:210mm;box-shadow:0 1px 8px #0002}.top{position:relative;text-align:center;margin-bottom:7px}.top h1{font-size:16px;margin:0}.top h2{font-size:13px;margin:2px 0}.top p{margin:2px 0}.top .left,.top .right{position:absolute;top:0;font-size:10px;text-align:left}.top .left{left:0}.top .right{right:0;text-align:right}table{border-collapse:collapse;width:100%}th,td{border:1px solid #5896c5;padding:3px 4px;vertical-align:middle}th{background:#dce7f3;font-weight:700;text-align:center}td.center{text-align:center}.vertical{writing-mode:vertical-rl;transform:rotate(180deg);height:53px;white-space:nowrap}.summary{width:42%;margin-top:8px}.summary th,.summary td{padding:3px 5px}.sign{display:flex;justify-content:space-between;width:55%;margin-top:22px}.sign span{width:28%;border-top:1px solid #8796a3;padding-top:3px;text-align:center}
@media screen and (max-width: 991.98px) {
  html, body { overflow-x: hidden !important; width: 100% !important; }
  .toolbar { padding: 10px 12px; gap: 8px; width: 100%; }
  .toolbar a, .toolbar button { font-size: 14px; padding: 8px 12px; flex-grow: 1; justify-content: center; }
  .filters { padding: 10px 12px; font-size: 13px; }
  .sheet { width: 100% !important; max-width: 100% !important; min-height: auto !important; margin: 8px 0 !important; padding: 10px !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; border-radius: 8px; }
  .sheet table { min-width: 600px; }
}
@media print{body{background:#fff}.toolbar,.filters{display:none}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}@page{size:A4 landscape;margin:7mm}}
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">प्रिंट / PDF जतन करा</button><a href="<?= APP_URL ?>/cce/index.php">सातत्यपूर्ण मूल्यमापन डॅशबोर्ड</a><?= cce_theme_toolbar_controls(false) ?><span><?= sanitize($mode_title) ?> · <?= sanitize(std_label($std)) ?> · <?= sanitize(semester_label($semester)) ?></span></div>
<div class="filters"><form method="get"><label>इयत्ता <select name="std" onchange="this.form.submit()"><?php for($i=1;$i<=8;$i++): ?><option value="<?= $i ?>" <?= $std === $i ? 'selected' : '' ?>><?= sanitize(std_label($i)) ?></option><?php endfor; ?></select></label> <label>सत्र <select name="semester" onchange="this.form.submit()"><option value="1" <?= $semester === 1 ? 'selected' : '' ?>>सत्र १</option><option value="2" <?= $semester === 2 ? 'selected' : '' ?>>सत्र २</option></select></label> <input type="hidden" name="mode" value="<?= sanitize($mode) ?>"></form></div>
<section class="sheet">
<div class="top"><div class="left">युडायस: <?= sanitize($school['udise_code'] ?? '-') ?><br>शाळा: <?= sanitize($school['name_mr'] ?? $school['name'] ?? '') ?></div><div class="right">सन: <?= sanitize($year) ?><br>इयत्ता: <?= sanitize(std_label($std)) ?></div><h1><?= sanitize($school['name_mr'] ?? $school['name'] ?? '') ?></h1><h2>सातत्यपूर्ण सर्वंकष मूल्यमापन — <?= sanitize($mode_title) ?></h2><p><?= sanitize(semester_label($semester)) ?> · शैक्षणिक वर्ष <?= sanitize($year) ?></p></div>
<?php if (!$students): ?><p>या इयत्तेत विद्यार्थी नाहीत.</p><?php elseif (!$subjects): ?><p>या इयत्तेसाठी विषय निश्चित केलेले नाहीत.</p><?php else: ?>
<table><thead><tr><th rowspan="3">अ.क्र.</th><th rowspan="3">विद्यार्थ्याचे नाव</th><th rowspan="3">लिंग</th><th rowspan="3">जात</th><?php foreach($subjects as $subject): ?><th colspan="3"><?= sanitize($subject['short_name'] ?: $subject['name_mr']) ?></th><?php endforeach; ?><th colspan="3">एकूण</th></tr><tr><?php foreach($subjects as $subject): ?><th>आकारिक</th><th>संकलित</th><th>एकूण</th><?php endforeach; ?><th>गुण</th><th>%</th><th>श्रेणी</th></tr><tr><?php foreach($subjects as $subject): ?><th><?= gt_num($subject['fe_max']) ?></th><th><?= gt_num($subject['se_max']) ?></th><th><?= gt_num((float)$subject['fe_max']+(float)$subject['se_max']) ?></th><?php endforeach; ?><th>प्राप्त</th><th>कमाल</th><th></th></tr></thead><tbody>
<?php foreach($student_rows as $index=>$item): $student=$item['student']; ?><tr><td class="center"><?= to_mr_digits($index+1) ?></td><td><?= sanitize($student['name_mr'] ?: $student['name']) ?></td><td class="center"><?= sanitize($student['gender'] ?? '-') ?></td><td class="center"><?= sanitize(student_caste($student)) ?></td><?php foreach($item['subject_rows'] as $subject_row): ?><td class="center"><?= gt_num($subject_row['fe']) ?></td><td class="center"><?= gt_num($subject_row['se']) ?></td><td class="center"><?= gt_num($subject_row['total']) ?></td><?php endforeach; ?><td class="center"><?= gt_num($item['total']) ?></td><td class="center"><?= to_mr_digits(number_format($item['percent'],2,'.','')) ?></td><td class="center"><?= sanitize($item['grade']) ?></td></tr><?php endforeach; ?></tbody></table>
<h3>एकूण सारणी (विषयनिहाय श्रेणी वितरण)</h3><table class="summary"><thead><tr><th>अ.क्र.</th><th style="text-align:left">श्रेणी</th><th>विद्यार्थी संख्या</th></tr></thead><tbody><?php foreach($grade_counts as $grade=>$count): ?><tr><td class="center">-</td><td><?= sanitize($grade) ?></td><td class="center"><?= to_mr_digits($count) ?></td></tr><?php endforeach; ?><tr><th colspan="2">एकूण</th><th><?= to_mr_digits(count($students)) ?></th></tr></tbody></table>
<?php if($mode === 'caste'): ?><h3>जातीनुसार श्रेणी वितरण</h3><table class="summary"><thead><tr><th>जात</th><?php foreach(array_keys($grade_counts) as $grade): ?><th><?= sanitize($grade) ?></th><?php endforeach; ?><th>एकूण</th></tr></thead><tbody><?php foreach($caste_counts as $caste=>$grades): ?><tr><td><?= sanitize($caste) ?></td><?php $caste_total=0; foreach(array_keys($grade_counts) as $grade): $count=$grades[$grade]??0;$caste_total+=$count; ?><td class="center"><?= to_mr_digits($count) ?></td><?php endforeach; ?><td class="center"><?= to_mr_digits($caste_total) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<div class="sign"><span>वर्गशिक्षक स्वाक्षरी</span><span>मुख्याध्यापक स्वाक्षरी</span><span>दिनांक :</span></div>
<?php endif; ?></section>
</body></html>
