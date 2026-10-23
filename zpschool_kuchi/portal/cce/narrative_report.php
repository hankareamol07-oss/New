<?php
/** Two-semester narrative CCE report for an individual student or an entire class. */
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
    if ($studentId > 0) {
        $stmt = $db->prepare('SELECT * FROM students WHERE id=? AND school_id=? AND is_active=1');
        $stmt->execute([$studentId, $schoolId]);
        $student = $stmt->fetch();
    } else {
        $list_tmp = cce_students($schoolId, $std);
        $student = $list_tmp[0] ?? null;
    }
    if (!$student) { http_response_code(404); exit('Student not found.'); }
    $students = [$student];
    $std = std_from_grade($student['grade'] ?? '') ?: $std;
}
$subjects = cce_subjects($schoolId, $std);
$ids = array_map('intval', array_column($students, 'id'));
$notes = [];
$marks = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT student_id,semester,slot_no,note_text FROM cce_student_notes WHERE school_id=? AND academic_year=? AND student_id IN ($in)");
    $stmt->execute(array_merge([$schoolId, $year], $ids));
    foreach ($stmt->fetchAll() as $note) $notes[(int)$note['student_id']][(int)$note['semester']][(int)$note['slot_no']] = $note['note_text'];
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE school_id=? AND academic_year=? AND student_id IN ($in)");
    $stmt->execute(array_merge([$schoolId, $year], $ids));
    foreach ($stmt->fetchAll() as $mark) $marks[(int)$mark['student_id']][(int)$mark['semester']][(int)$mark['subject_id']] = $mark;
}
$areas = cce_note_areas();
?>
<!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>वर्णनात्मक नोंदी — प्रगती पत्रक</title>
<link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;700&display=swap" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef2f6;color:#080808;font:13px 'Utsaah','Mukta','Nirmala UI',sans-serif}.toolbar{position:sticky;top:0;z-index:1000;background:#17324d;color:white;padding:10px 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}.toolbar button,.toolbar a{border:0;border-radius:6px;background:#1d9b72;color:#fff;padding:8px 14px;text-decoration:none;font:inherit;font-size:14px;font-weight:700;min-height:40px;display:inline-flex;align-items:center;cursor:pointer}.toolbar a{background:#536f86}.page{width:210mm;min-height:297mm;margin:12px auto;padding:10mm;background:#fff;box-shadow:0 1px 8px #0003;position:relative;page-break-after:always}.page:last-child{page-break-after:auto}.title{text-align:center;font-weight:700;font-size:21px;margin:0 0 7mm}.title span{color:#ed008c}.meta{display:flex;justify-content:space-between;font-size:13px;line-height:1.8;margin-bottom:3mm}.meta .right{text-align:right}.meta b{font-size:14px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #111;padding:5px 6px;vertical-align:top}th{background:#f2f2f2;text-align:center;font-weight:700}.center{text-align:center}.subject{width:25%}.grade{width:9%;text-align:center}.narrative{width:28%;min-height:22mm;white-space:pre-wrap;line-height:1.45}.extras{margin-top:4mm}.extras td{min-height:12mm}.health{margin-top:4mm}.health td,.health th{padding:4px}.sign{position:absolute;bottom:12mm;left:10mm;right:10mm;display:flex;justify-content:space-between}.sign div{text-align:center;min-width:42mm}.sign .line{height:11mm;border-bottom:1px solid #111;margin-bottom:3px}
@media screen and (max-width: 991.98px) {
  html, body { overflow-x: hidden !important; width: 100% !important; }
  .toolbar { padding: 10px 12px; gap: 8px; width: 100%; }
  .toolbar a, .toolbar button { font-size: 14px; padding: 8px 12px; flex-grow: 1; justify-content: center; }
  .page { width: 100% !important; max-width: 100% !important; min-height: auto !important; margin: 8px 0 !important; padding: 10px !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; border-radius: 8px; }
  .page table { min-width: 580px; }
  .sign { position: static !important; margin-top: 16px !important; }
}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:210mm;min-height:297mm;padding:10mm;box-shadow:none;page-break-after:always}@page{size:A4;margin:0}}
</style></head><body><div class="toolbar"><button onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button><a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>">← रिपोर्ट</a><?= cce_theme_toolbar_controls(false) ?></div>
<?php foreach ($students as $student): $sid=(int)$student['id']; ?><section class="page"><h1 class="title"><span>●</span> सातत्यपूर्ण सर्वंकष मूल्यमापन <span>●</span></h1><div class="meta"><div>विद्यार्थ्याचे नाव : <b><?= sanitize($student['name_mr'] ?: $student['name']) ?></b><br>इयत्ता : <b><?= sanitize($student['grade'] ?? '') ?></b> &nbsp; तुकडी : <b><?= sanitize($student['section'] ?? '-') ?></b></div><div class="right">सन : <b><?= sanitize($year) ?></b><br>हजेरी क्रमांक : <b><?= sanitize($student['roll_no'] ?? '-') ?></b><br>युडायस : <b><?= sanitize($school['udise_code'] ?? '-') ?></b></div></div>
<table><thead><tr><th style="width:8%">अ. नं.</th><th class="subject">विषय</th><th class="grade">श्रेणी<br>सत्र १</th><th class="grade">श्रेणी<br>सत्र २</th><th class="narrative">वर्णनात्मक नोंदी<br>प्रथम सत्र</th><th class="narrative">वर्णनात्मक नोंदी<br>द्वितीय सत्र</th></tr></thead><tbody><?php foreach($subjects as $index=>$subject): 
    $slot = cce_subject_slot($subject);
    $one = $marks[$sid][1][(int)$subject['id']] ?? [];
    $two = $marks[$sid][2][(int)$subject['id']] ?? [];
    $n1 = $notes[$sid][1][$slot] ?? '';
    $n2 = $notes[$sid][2][$slot] ?? '';
    if ($n1 !== '') $n1 = cce_apply_gender_inflection($n1, $student, $slot === 3 ? 3 : 1);
    if ($n2 !== '') $n2 = cce_apply_gender_inflection($n2, $student, $slot === 3 ? 3 : 1);
?><tr><td class="center"><?= to_mr_digits($index+1) ?></td><td><?= sanitize($subject['name_mr']) ?></td><td class="grade"><?= sanitize($one['grade'] ?? '-') ?></td><td class="grade"><?= sanitize($two['grade'] ?? '-') ?></td><td class="narrative"><?= sanitize($n1 ?: '-') ?></td><td class="narrative"><?= sanitize($n2 ?: '-') ?></td></tr><?php endforeach; ?></tbody></table>
<table class="extras"><thead><tr><th style="width:25%">वर्णनात्मक नोंदी</th><th>प्रथम सत्र</th><th>द्वितीय सत्र</th></tr></thead><tbody><?php for($slot=10;$slot<=13;$slot++): 
    $en1 = $notes[$sid][1][$slot] ?? '';
    $en2 = $notes[$sid][2][$slot] ?? '';
    if ($en1 !== '') $en1 = cce_apply_gender_inflection($en1, $student, 1);
    if ($en2 !== '') $en2 = cce_apply_gender_inflection($en2, $student, 1);
?><tr><td><?= sanitize($areas[$slot] ?? '') ?></td><td class="narrative"><?= sanitize($en1 ?: '-') ?></td><td class="narrative"><?= sanitize($en2 ?: '-') ?></td></tr><?php endfor; ?></tbody></table>
<table class="health"><thead><tr><th rowspan="2" style="width:30%">आरोग्यविषयक माहिती<br>(वजन, उंची)</th><th colspan="2">प्रथम सत्र</th><th colspan="2">द्वितीय सत्र</th></tr><tr><th>वजन (किग्रॅ)</th><th>उंची (सेमी)</th><th>वजन (किग्रॅ)</th><th>उंची (सेमी)</th></tr></thead><tbody><tr><td></td><td>-</td><td>-</td><td>-</td><td>-</td></tr></tbody></table><footer class="sign"><div><div class="line"></div>वर्गशिक्षक स्वाक्षरी</div><div><div class="line"></div>पालक स्वाक्षरी</div><div><div class="line"></div>मुख्याध्यापक स्वाक्षरी</div></footer></section><?php endforeach; ?></body></html>
