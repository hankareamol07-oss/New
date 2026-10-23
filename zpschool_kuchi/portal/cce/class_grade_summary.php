<?php
/** Printable CCE class grade summary, matching the compact school register layout. */
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$schoolId = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();
$standards = cce_school_standards($schoolId);
$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_GET['semester'] ?? 1) === 2 ? 2 : 1;
$subjects = cce_subjects($schoolId, $std);
$students = cce_students($schoolId, $std);
$months = cce_months();
$sem_months = $semester === 1 ? [6, 7, 8, 9, 10, 11] : [12, 1, 2, 3, 4, 5];
$marks = [];
$attendance = [];
if ($students) {
    $ids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id=? AND academic_year=? AND semester=? AND student_id IN ($in)");
    $stmt->execute(array_merge([$schoolId, $year, $semester], $ids));
    foreach ($stmt->fetchAll() as $row) $marks[(int)$row['student_id']][(int)$row['subject_id']] = $row;

    $stmt = $db->prepare("SELECT * FROM attendance WHERE academic_year=? AND student_id IN ($in)");
    $stmt->execute(array_merge([$year], $ids));
    foreach ($stmt->fetchAll() as $row) $attendance[(int)$row['student_id']][(int)$row['month']] = $row;
}
$summary = [];
foreach ($subjects as $subject) $summary[(int)$subject['id']] = [];
foreach ($students as $student) foreach ($subjects as $subject) {
    $mark = $marks[(int)$student['id']][(int)$subject['id']] ?? null;
    $grade = $mark['grade'] ?? '-';
    $summary[(int)$subject['id']][$grade] = ($summary[(int)$subject['id']][$grade] ?? 0) + 1;
}
$grades = [];
foreach ($summary as $counts) foreach (array_keys($counts) as $grade) if (!in_array($grade, $grades, true)) $grades[] = $grade;
?>
<!doctype html>
<html lang="mr"><head><meta charset="utf-8"><title>वर्ग गुण-श्रेणी संगणन तक्ता</title>
<link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#eef2f6;color:#111;font:12px 'Utsaah','Mukta','Nirmala UI',sans-serif}.toolbar{position:sticky;top:0;background:#17324d;color:#fff;padding:9px 14px;display:flex;gap:8px;align-items:center}.toolbar a,.toolbar button{border:0;border-radius:4px;background:#1d9b72;color:#fff;padding:6px 10px;text-decoration:none;font:inherit;cursor:pointer}.toolbar a{background:#60788d}.filters{padding:8px 14px;background:#fff}.filters select{padding:4px}.sheet{width:297mm;min-height:210mm;background:#fff;margin:12px auto;padding:8mm;box-shadow:0 1px 8px #0002}.head{position:relative;text-align:center;margin-bottom:8px}.head h1{font-size:17px;margin:0}.head h2{font-size:13px;margin:2px}.head p{margin:2px}.left,.right{position:absolute;top:0;text-align:left}.left{left:0}.right{right:0;text-align:right}table{border-collapse:collapse;width:100%}th,td{border:1px solid #5798ca;padding:3px 4px;vertical-align:middle}th{background:#d1dcea;font-weight:700;text-align:center}.center{text-align:center}.vertical{writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;height:55px}.summary{width:auto;margin-top:9px;min-width:52%}.summary td,.summary th{padding:4px 7px}.sign{display:flex;gap:50px;margin:22px 0 0 30px}.sign div{min-width:145px;border-top:1px solid #111;padding-top:4px;text-align:center}@media print{body{background:#fff}.toolbar,.filters{display:none}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}@page{size:A4 landscape;margin:7mm}}
</style></head><body>
<div class="toolbar"><button onclick="window.print()">प्रिंट / PDF जतन करा</button><a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>&semester=<?= $semester ?>">रिपोर्ट</a></div>
<div class="filters"><form method="get">इयत्ता <select name="std" onchange="this.form.submit()"><?php foreach($standards as $number=>$name): ?><option value="<?= $number ?>" <?= $number===$std?'selected':'' ?>><?= sanitize($name) ?></option><?php endforeach; ?></select> सत्र <select name="semester" onchange="this.form.submit()"><?php for($sem=1;$sem<=2;$sem++): ?><option value="<?= $sem ?>" <?= $semester===$sem?'selected':'' ?>>सत्र <?= to_mr_digits($sem) ?></option><?php endfor; ?></select></form></div>
<main class="sheet"><header class="head"><div class="left">युडायस: <b><?= sanitize($school['udise_code'] ?? '-') ?></b><br>शाळा: <b><?= sanitize($school['name_mr'] ?? $school['name'] ?? '') ?></b></div><div class="right">सन: <?= sanitize($year) ?><br>इयत्ता: <?= sanitize(std_label($std)) ?></div><p>वर्ग गुण-श्रेणी संगणन तक्ता — सारांश (सत्र <?= to_mr_digits($semester) ?>)</p><h1>सातत्यपूर्ण सर्वंकष मूल्यमापन</h1><p>सत्र <?= to_mr_digits($semester) ?></p></header>
<?php if (!$students || !$subjects): ?><p>या इयत्तेसाठी विद्यार्थी किंवा विषय उपलब्ध नाहीत.</p><?php else: ?>
<table><thead><tr><th rowspan="2">अ.नं.</th><th rowspan="2">विद्यार्थ्याचे नाव</th><th rowspan="2">लिंग</th><th rowspan="2">संवर्ग</th><th rowspan="2">हजर<br>दिवस</th><?php foreach($subjects as $subject): ?><th colspan="2"><?= sanitize($subject['short_name'] ?: $subject['name_mr']) ?></th><?php endforeach; ?><th colspan="3">एकूण</th></tr><tr><?php foreach($subjects as $subject): ?><th class="vertical">प्राप्त</th><th class="vertical">श्रेणी</th><?php endforeach; ?><th class="vertical">एकूण</th><th class="vertical">टक्के</th><th class="vertical">श्रेणी</th></tr></thead><tbody><?php foreach($students as $index=>$student): $total=0;$max=0; $att=$attendance[(int)$student['id']]??[]; $work=0;$present=0; foreach($sem_months as $mn){$work+=(int)($att[$mn]['working_days']??0);$present+=(int)($att[$mn]['days_present']??0);} ?><tr><td class="center"><?= to_mr_digits($index+1) ?></td><td><?= sanitize($student['name_mr'] ?: $student['name']) ?></td><td class="center"><?= sanitize($student['gender'] ?? '-') ?></td><td class="center"><?= sanitize($student['caste_category'] ?? $student['caste'] ?? '-') ?></td><td class="center"><?= $work ? (to_mr_digits($present).'/'.to_mr_digits($work)) : '-' ?></td><?php foreach($subjects as $subject): $mark=$marks[(int)$student['id']][(int)$subject['id']]??null;$got=(float)($mark['grand_total']??0);$subjectMax=(float)$subject['fe_max']+(float)$subject['se_max'];$total+=$got;$max+=$subjectMax; ?><td class="center"><?= $mark ? to_mr_digits(rtrim(rtrim((string)$got,'0'),'.')) : '-' ?></td><td class="center"><?= sanitize($mark['grade'] ?? '-') ?></td><?php endforeach; ?><?php $pct=$max?round($total/$max*100,2):0; ?><td class="center"><?= to_mr_digits(rtrim(rtrim(number_format($total,1,'.',''),'0'),'.')) ?></td><td class="center"><?= to_mr_digits(number_format($pct,2,'.','')) ?></td><td class="center"><?= sanitize(cce_grade_for_percent($pct)) ?></td></tr><?php endforeach; ?></tbody></table>
<h3>एकत्र सारणी (विषयनिहाय श्रेणी वितरण)</h3><table class="summary"><thead><tr><th>अ.नं.</th><th>विषय</th><?php foreach($grades as $grade): ?><th><?= sanitize($grade) ?></th><?php endforeach; ?><th>एकूण</th></tr></thead><tbody><?php foreach($subjects as $index=>$subject): $counts=$summary[(int)$subject['id']]; ?><tr><td class="center"><?= to_mr_digits($index+1) ?></td><td><?= sanitize($subject['name_mr']) ?></td><?php foreach($grades as $grade): ?><td class="center"><?= to_mr_digits($counts[$grade]??0) ?></td><?php endforeach; ?><td class="center"><?= to_mr_digits(array_sum($counts)) ?></td></tr><?php endforeach; ?></tbody></table><div class="sign"><div>वर्गशिक्षक स्वाक्षरी</div><div>मुख्याध्यापक स्वाक्षरी</div></div><?php endif; ?></main>
</body></html>
