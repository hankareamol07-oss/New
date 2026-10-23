<?php
/** Print-ready annual individual marksheet (both CCE semesters). */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$db = getDB();
$schoolId = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();
$standards = cce_school_standards($schoolId);
$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$all = !empty($_GET['all']);
$studentId = (int)($_GET['student_id'] ?? 0);
if ($all) {
    $students = cce_students($schoolId, $std);
} else {
    if (!$studentId) {
        $s_list = cce_students($schoolId, $std);
        if (!empty($s_list)) {
            $student = $s_list[0];
            $studentId = (int)$student['id'];
        }
    } else {
        $stmt = $db->prepare('SELECT * FROM students WHERE id=? AND school_id=? AND is_active=1');
        $stmt->execute([$studentId, $schoolId]);
        $student = $stmt->fetch();
    }
    if (!$student) {
        $s_list = cce_students($schoolId, $std);
        if (!empty($s_list)) {
            $student = $s_list[0];
            $studentId = (int)$student['id'];
        } else {
            http_response_code(404);
            exit('या इयत्तेत विद्यार्थी सापडले नाहीत. <a href="index.php">मागे जा</a>');
        }
    }
    $students = [$student];
    $std = std_from_grade($student['grade'] ?? '') ?: $std;
}
$subjects = cce_subjects($schoolId, $std);
$marks = [];
if ($students) {
    $ids = array_map('intval', array_column($students, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id=? AND academic_year=? AND student_id IN ($in)");
    $stmt->execute(array_merge([$schoolId, $year], $ids));
    foreach ($stmt->fetchAll() as $mark) $marks[(int)$mark['student_id']][(int)$mark['subject_id']][(int)$mark['semester']] = $mark;
}
function am_num($value): string { return to_mr_digits(rtrim(rtrim(number_format((float)$value, 1, '.', ''), '0'), '.')); }
?>
<!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>वार्षिक परीक्षा गुणपत्रक</title>
<link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;700&display=swap" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
*{box-sizing:border-box}body{margin:0;background:#edf1f5;color:#090909;font:13px 'Utsaah','Mukta','Nirmala UI',sans-serif}.toolbar{position:sticky;top:0;z-index:1000;background:#17324d;color:#fff;padding:10px 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}.toolbar a,.toolbar button{background:#1d9b72;color:#fff;border:0;border-radius:6px;padding:8px 14px;text-decoration:none;font:inherit;font-size:14px;font-weight:700;min-height:40px;display:inline-flex;align-items:center;cursor:pointer}.toolbar a{background:#536f86}.sheet{width:210mm;min-height:297mm;margin:12px auto;padding:7mm;background:#fff;box-shadow:0 1px 8px #0003}.card{min-height:282mm;border:3px solid #111;border-radius:17px;padding:13mm 12mm;position:relative}.title{text-align:center;margin:0 0 10mm}.title h1{font-size:26px;color:#e6007e;margin:0}.title h2{font-size:17px;font-weight:500;margin:1px}.meta{display:flex;justify-content:space-between;line-height:1.8;font-size:14px;margin-bottom:5px}.meta .right{text-align:right}.meta b{font-size:15px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border:1px solid #4b97d1;padding:8px 7px;vertical-align:middle}th{background:#d2ddeb;text-align:center;font-weight:700;font-size:14px}td.center{text-align:center}.totals{display:flex;justify-content:space-between;font-size:14px;font-weight:600;margin-top:8px}.result{display:flex;justify-content:space-between;font-size:14px;margin-top:5px}.sign{position:absolute;left:12mm;right:12mm;bottom:13mm;display:flex;justify-content:space-between;font-size:12px}.sign div{min-width:45mm;text-align:center}.sign .line{height:12mm;border-bottom:1px solid #111;margin-bottom:3px}
@media screen and (max-width: 991.98px) {
  html, body { overflow-x: hidden !important; width: 100% !important; }
  .toolbar { padding: 10px 12px; gap: 8px; width: 100%; }
  .toolbar a, .toolbar button { font-size: 14px; padding: 8px 12px; flex-grow: 1; justify-content: center; }
  .sheet { width: 100% !important; max-width: 100% !important; min-height: auto !important; margin: 8px 0 !important; padding: 10px !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; border-radius: 8px; }
  .card { min-height: auto !important; padding: 12px !important; border-radius: 10px; }
  .sheet table { min-width: 500px; }
  .sign { position: static !important; margin-top: 16px !important; }
}
@media print{body{background:#fff}.toolbar{display:none}.sheet{width:210mm;min-height:297mm;margin:0;padding:7mm;box-shadow:none;page-break-after:always}.sheet:last-child{page-break-after:auto}@page{size:A4;margin:0}}
</style></head><body>
<div class="toolbar"><button onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button><a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>">← रिपोर्ट</a><?= cce_theme_toolbar_controls(false) ?></div>
<?php foreach ($students as $student): $studentMarks=$marks[(int)$student['id']]??[];$total=0;$max=0; ?>
<section class="sheet"><div class="card"><header class="title"><h1>● गुणपत्रक ●</h1><h2>वार्षिक परीक्षा</h2></header>
<div class="meta"><div>शाळेचे नाव : <b><?= sanitize($school['name_mr'] ?? $school['name'] ?? '') ?></b><br>नाव : <b><?= sanitize($student['name_mr'] ?: $student['name']) ?></b><br>हजेरी क्रमांक : <b><?= sanitize($student['roll_no'] ?? '-') ?></b></div><div class="right">Udise: <b><?= sanitize($school['udise_code'] ?? '-') ?></b><br>सन : <b><?= sanitize($year) ?></b><br>इयत्ता: <b><?= sanitize(std_label($std)) ?>, तुकडी: <?= sanitize($student['section'] ?? '-') ?></b></div></div>
<table><thead><tr><th style="width:10%">अ.नं.</th><th>विषय</th><th>किमान आवश्यक गुण</th><th>एकूण गुण प्राप्त</th><th>सवलतीचे गुण</th><th>शेरा<br>(उत्तीर्ण/अनुत्तीर्ण)</th></tr></thead><tbody><?php foreach($subjects as $index=>$subject): $sem1=$studentMarks[(int)$subject['id']][1]??null;$sem2=$studentMarks[(int)$subject['id']][2]??null;$got=(float)($sem1['grand_total']??0)+(float)($sem2['grand_total']??0);$subjectMax=2*((float)$subject['fe_max']+(float)$subject['se_max']);$grade=cce_grade_for_percent($subjectMax?($got/$subjectMax*100):0);$total+=$got;$max+=$subjectMax; ?><tr><td class="center"><?= to_mr_digits($index+1) ?></td><td><?= sanitize($subject['name_mr']) ?></td><td class="center">श्रेणी</td><td class="center"><b><?= $got ? am_num($got) : '-' ?></b></td><td class="center">-</td><td class="center"><?= sanitize($grade) ?></td></tr><?php endforeach; ?></tbody></table>
<?php $pct=$max?round($total/$max*100,2):0;$overall=cce_grade_for_percent($pct);$passed=$pct>=35; ?><div class="totals"><span>एकूण गुण : <?= am_num($total) ?> / <?= am_num($max) ?></span><span>शेकडा गुण : <?= to_mr_digits(number_format($pct,2,'.','')) ?>%</span></div><div class="result"><span>निकाल दिनांक :</span><span>शेरा : <?= $passed ? 'उत्तीर्ण' : 'अनुत्तीर्ण' ?> (<?= sanitize($overall) ?>)</span></div><footer class="sign"><div><div class="line"></div>वर्गशिक्षक स्वाक्षरी<br><?= sanitize(cce_class_teacher_name($schoolId,$std) ?? '') ?></div><div><div class="line"></div>मुख्याध्यापक स्वाक्षरी</div></footer></div></section>
<?php endforeach; ?></body></html>
