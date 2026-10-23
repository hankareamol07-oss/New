<?php
/** CCE annual class register (निकाल पत्रक — वार्षिक): two rows per student, one per semester. */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
require_once __DIR__ . '/../includes/module_access.php';
requireLogin();
require_module_access('cce');
$db=getDB(); $schoolId=(int)$_SESSION['school_id']; $school=getSchool(); $year=academic_year();
$standards=cce_school_standards($schoolId); $std=(int)($_GET['std']??array_key_first($standards)??1);
$cce_std_check = can_access_cce_class($std, $schoolId);
if (!$cce_std_check['allowed']) {
    flash('error', $cce_std_check['message'] ?? 'या इयत्तेसाठी सशुल्क योजना आवश्यक आहे.');
    redirect(APP_URL . '/cce/index.php?std=1');
    exit;
}
$subjects=cce_subjects($schoolId,$std); $students=cce_students($schoolId,$std); $marks=[];
if($students){
    $ids=array_map('intval',array_column($students,'id'));
    $in=implode(',',array_fill(0,count($ids),'?'));
    try {
        $q=$db->prepare("SELECT * FROM cce_marks WHERE school_id=? AND academic_year=? AND student_id IN ($in)");
        $q->execute(array_merge([$schoolId,$year],$ids));
        foreach($q->fetchAll() as $row)$marks[(int)$row['student_id']][(int)$row['semester']][(int)$row['subject_id']]=$row;
    } catch(Throwable $e){}
    try {
        $sq=cce_db($schoolId);
        $qS=$sq->prepare("SELECT * FROM cce_marks WHERE school_id=? AND academic_year=? AND student_id IN ($in)");
        $qS->execute(array_merge([$schoolId,$year],$ids));
        foreach($qS->fetchAll(PDO::FETCH_ASSOC) as $row){
            $sid=(int)$row['student_id']; $sem=(int)$row['semester']; $sub=(int)$row['subject_id'];
            if(!isset($marks[$sid][$sem][$sub])){ $marks[$sid][$sem][$sub]=$row; }
        }
    } catch(Throwable $e){}
}
$attendance=[];
if($students){
    try {
        $stmt=$db->prepare("SELECT * FROM attendance WHERE academic_year=? AND student_id IN ($in)");
        $stmt->execute(array_merge([$year],$ids));
        foreach($stmt->fetchAll() as $row)$attendance[(int)$row['student_id']][(int)$row['month']]=$row;
    } catch(Throwable $e){}
    if(empty($attendance)){
        try {
            $sq=cce_db($schoolId);
            $stmtA=$sq->prepare("SELECT * FROM attendance WHERE academic_year=? AND student_id IN ($in)");
            $stmtA->execute(array_merge([$year],$ids));
            foreach($stmtA->fetchAll(PDO::FETCH_ASSOC) as $row)$attendance[(int)$row['student_id']][(int)$row['month']]=$row;
        } catch(Throwable $e){}
    }
}
$subjectMaxTotal=0; foreach($subjects as $s) $subjectMaxTotal += (float)$s['fe_max']+(float)$s['se_max'];
$classTeacher = cce_class_teacher_name($schoolId,$std) ?: '';
$headmaster = cce_headmaster_name($schoolId, 2) ?: '';
function ar_number($value){return to_mr_digits(rtrim(rtrim(number_format((float)$value,1,'.',''),'0'),'.'));}
?>
<!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>वार्षिक निकालपत्रक</title><link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;700&display=swap" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef3f5;font:13px 'Utsaah','Noto Sans Devanagari','Nirmala UI',sans-serif;color:#111}.bar{position:sticky;top:0;background:#17324d;padding:10px 14px;display:flex;gap:10px;z-index:1000;align-items:center;flex-wrap:wrap}.bar a,.bar button{padding:8px 14px;border:0;border-radius:6px;background:#1d9b72;color:#fff;text-decoration:none;font:inherit;font-size:14px;font-weight:700;cursor:pointer;min-height:40px;display:inline-flex;align-items:center}.bar a{background:#58758d}.page{width:297mm;min-height:210mm;margin:12px auto;background:#fff;padding:7mm;box-shadow:0 1px 8px #0003}.heading{position:relative;text-align:center;margin-bottom:5px}.heading h1{font-size:18px;color:#d31572;margin:0}.heading p{margin:1px;font-size:11px}.heading .left,.heading .right{position:absolute;top:0;text-align:left;font-size:10px}.heading .left{left:0}.heading .right{right:0;text-align:right}table{border-collapse:collapse;width:100%}th,td{border:1px solid #00a9c2;padding:2px 3px;vertical-align:middle}th{background:#d8f4f8;text-align:center;font-weight:700}.vertical{writing-mode:vertical-rl;transform:rotate(180deg);height:45px;white-space:nowrap}.center{text-align:center}.name{min-width:120px;text-align:left}.sem{white-space:nowrap}.footer{display:flex;justify-content:space-between;margin-top:14px;font-size:11px}.sign-name{font-weight:700;margin-top:2px}
@media screen and (max-width: 991.98px) {
  html, body { overflow-x: hidden !important; width: 100% !important; }
  .bar { padding: 10px 12px; gap: 8px; width: 100%; }
  .bar a, .bar button { font-size: 14px; padding: 8px 12px; flex-grow: 1; justify-content: center; }
  .page { width: 100% !important; max-width: 100% !important; min-height: auto !important; margin: 8px 0 !important; padding: 10px !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; border-radius: 8px; }
  .page table { min-width: 650px; }
}
@media print{body{background:#fff}.bar{display:none}.page{width:297mm;min-height:210mm;margin:0;padding:7mm;box-shadow:none}@page{size:A4 landscape;margin:0}}
</style></head><body><div class="bar"><button onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button><a href="<?=APP_URL?>/cce/reports.php?std=<?=$std?>">← रिपोर्ट</a><?= cce_theme_toolbar_controls(false) ?></div><main class="page"><header class="heading"><div class="left">युडायस : <b><?=sanitize($school['udise_code']??'-')?></b><br>शाळा : <b><?=sanitize($school['name_mr']??$school['name']??'')?></b></div><div class="right">वर्गशिक्षक : <b><?=sanitize($classTeacher ?: '-')?></b><br>इयत्ता : <b><?=sanitize(std_label($std))?></b> तुकडी : <b>-</b></div><h1>● निकाल पत्रक ●</h1><p>वार्षिक | सन : <b><?=sanitize($year)?></b></p></header>
<?php if(!$students||!$subjects):?><p>विद्यार्थी किंवा विषय उपलब्ध नाहीत.</p><?php else:?><table><thead><tr><th rowspan="3">अ. नं.</th><th rowspan="3">विद्यार्थ्याचे नाव</th><th rowspan="3">लिंग</th><th rowspan="3">हजर<br>दिन</th><th rowspan="3">सत्र</th><?php foreach($subjects as $subject):?><th colspan="4"><?=sanitize($subject['short_name']?:$subject['name_mr'])?></th><?php endforeach;?><th colspan="3">एकूण</th></tr><tr><?php foreach($subjects as $subject):?><th class="vertical">अ</th><th class="vertical">ब</th><th class="vertical">अ+ब</th><th class="vertical">श्रेणी</th><?php endforeach;?><th class="vertical">एकूण गुण</th><th class="vertical">शेकडा गुण</th><th class="vertical">श्रेणी</th></tr><tr><?php foreach($subjects as $subject):?><th><?=ar_number($subject['fe_max'])?></th><th><?=ar_number($subject['se_max'])?></th><th><?=ar_number((float)$subject['fe_max']+(float)$subject['se_max'])?></th><th></th><?php endforeach;?><th><?=ar_number($subjectMaxTotal)?></th><th></th><th></th></tr></thead><tbody><?php foreach($students as $index=>$student): $sid=(int)$student['id']; $att=$attendance[$sid]??[]; $hajarDin=0; foreach($att as $a) $hajarDin+=(int)($a['days_present']??0); for($semester=1;$semester<=2;$semester++):$total=0;$max=0;?><tr><?php if($semester===1):?><td class="center" rowspan="2"><?=to_mr_digits($index+1)?></td><td class="name" rowspan="2"><?=sanitize($student['name_mr']?:$student['name'])?></td><td class="center" rowspan="2"><?=sanitize($student['gender']??'-')?></td><td class="center" rowspan="2"><?=$hajarDin?to_mr_digits($hajarDin):'-'?></td><?php endif;?><td class="sem"><?= $semester===1?'प्रथम':'द्वितीय' ?></td><?php foreach($subjects as $subject):$mark=$marks[(int)$student['id']][$semester][(int)$subject['id']]??null;$fe=(float)($mark['fe_total']??0);$se=(float)($mark['se_total']??0);$got=(float)($mark['grand_total']??0);$subjectMax=(float)$subject['fe_max']+(float)$subject['se_max'];$total+=$got;$max+=$subjectMax;?><td class="center"><?=$mark?ar_number($fe):'-'?></td><td class="center"><?=$mark?ar_number($se):'-'?></td><td class="center"><?=$mark?ar_number($got):'-'?></td><td class="center"><?=sanitize($mark['grade']??'-')?></td><?php endforeach;$pct=$max?round($total/$max*100,1):0;?><td class="center"><b><?=ar_number($total)?></b></td><td class="center"><b><?=to_mr_digits(number_format($pct,1,'.',''))?></b></td><td class="center"><b><?=sanitize(cce_grade_for_percent($pct))?></b></td></tr><?php endfor;endforeach;?></tbody></table><footer class="footer"><span>पृष्ठ : 1 / 1 | इयत्ता : <?=sanitize(std_label($std))?> | वार्षिक निकाल</span><span style="text-align:center">वर्गशिक्षक स्वाक्षरी<div class="sign-name"><?=sanitize($classTeacher ?: '-')?></div></span><span style="text-align:center">मुख्याध्यापक स्वाक्षरी<div class="sign-name"><?=sanitize($headmaster ?: '-')?></div></span></footer><?php endif;?></main></body></html>
