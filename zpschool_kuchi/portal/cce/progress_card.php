<?php
/**
 * प्रगती पत्रक — सातत्यपूर्ण मूल्यमापन ९.० अधिकृत २-पानी छपाईयोग्य प्रगती पत्रक.
 * प्रत्येक विद्यार्थ्यासाठी २ पाने: (१) वैयक्तिक माहिती + उपस्थिती,
 * (२) दोन्ही सत्रांचे गुण-श्रेणी व वर्णनात्मक नोंदी.
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$db = getDB(); $school_id = $_SESSION['school_id']; $school = getSchool(); $year = academic_year();
$all = !empty($_GET['all']); $student_id = (int)($_GET['student_id'] ?? 0); $std = (int)($_GET['std'] ?? 0);
$standards = cce_school_standards($school_id);
if ($std <= 0 && !empty($standards)) {
    $std = (int)(array_key_first($standards) ?? 1);
}
if ($student_id <= 0 && !$all) {
    $all = true;
}
if ($all) { $list = cce_students($school_id, $std); } else {
    $stmt = $db->prepare("SELECT * FROM students WHERE id=? AND school_id=?");
    $stmt->execute([$student_id, $school_id]); $s = $stmt->fetch();
    if (!$s) { http_response_code(404); exit("Not found"); }
    $list = [$s]; $std = std_from_grade($s['grade'] ?? '') ?: $std;
}
if (!$list) exit("No students.");
$subjects = cce_subjects($school_id, $std); $months = cce_months(); $areas = cce_note_areas();

function pmc($db, $sid, $year, $sem) {
    $s = $db->prepare("SELECT * FROM cce_marks WHERE student_id=? AND academic_year=? AND semester=?");
    $s->execute([$sid, $year, $sem]);
    $out = [];
    foreach ($s->fetchAll() as $r) $out[(int)$r['subject_id']] = $r;
    if (empty($out) && function_exists('cce_db')) {
        try {
            $sq = cce_db();
            $s2 = $sq->prepare("SELECT * FROM cce_marks WHERE student_id=? AND academic_year=? AND semester=?");
            $s2->execute([$sid, $year, $sem]);
            $rows = $s2->fetchAll();
            foreach ($rows as $r) $out[(int)$r['subject_id']] = $r;
            if (!empty($rows) && function_exists('sync_marks_to_mysql')) {
                sync_marks_to_mysql($_SESSION['school_id'] ?? 0, $rows);
            }
        } catch (Throwable $e) {}
    }
    return $out;
}
function pnt($db, $sid, $year, $sem) { $s = $db->prepare("SELECT slot_no,note_text FROM cce_student_notes WHERE student_id=? AND academic_year=? AND semester=?"); $s->execute([$sid, $year, $sem]); $out = []; foreach ($s->fetchAll() as $r) if (trim((string)$r['note_text']) !== '') $out[(int)$r['slot_no']] = $r['note_text']; return $out; }
function plo($db, $sid, $year, $sem) { $s = $db->prepare("SELECT outcome_text FROM cce_student_outcomes WHERE student_id=? AND academic_year=? AND semester=? AND achieved=1 ORDER BY id"); $s->execute([$sid, $year, $sem]); return array_column($s->fetchAll(), 'outcome_text'); }
function pex($db, $sid) { try { $s = $db->prepare("SELECT * FROM cce_student_extra WHERE student_id=?"); $s->execute([$sid]); return $s->fetch() ?: []; } catch (PDOException $e) { return []; } }
function pat($db, $sid, $year) { $s = $db->prepare("SELECT * FROM attendance WHERE student_id=? AND academic_year=?"); $s->execute([$sid, $year]); $out = []; foreach ($s->fetchAll() as $r) $out[(int)$r['month']] = $r; return $out; }
function pnum($v) { $v = (float)$v; return to_mr_digits(rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.')); }
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>प्रगती पत्रक</title>
<link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
<?= cce_theme_head_tags() ?>
<style>
*{box-sizing:border-box}
body{margin:0;background:#eef2f6;font-family:'Utsaah','Mukta','Nirmala UI',sans-serif;color:#14212e;font-size:13px}
.toolbar{position:sticky;top:0;z-index:1000;background:#17324d;color:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.toolbar button,.toolbar a{border:0;border-radius:6px;padding:8px 14px;background:#1d9b72;color:#fff;text-decoration:none;font:inherit;font-size:14px;font-weight:700;cursor:pointer;min-height:40px;display:inline-flex;align-items:center}
.toolbar a{background:#55718b}
.sheet{width:210mm;min-height:297mm;margin:14px auto;background:#fff;box-shadow:0 1px 8px #0003;position:relative;page-break-after:always;padding:10mm}
.sheet:last-child{page-break-after:auto}
@media screen and (max-width: 991.98px) {
  html, body { overflow-x: hidden !important; width: 100% !important; }
  .toolbar { padding: 10px 12px; gap: 8px; width: 100%; }
  .toolbar a, .toolbar button { font-size: 14px; padding: 8px 12px; flex-grow: 1; justify-content: center; }
  .sheet { width: 100% !important; max-width: 100% !important; min-height: auto !important; margin: 8px 0 !important; padding: 12px 10px !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; border-radius: 8px; }
  .sheet table { min-width: 580px; }
}

/* ---- Page 1: student info + attendance ---- */
.hd{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px double #17324d;padding-bottom:6px;margin-bottom:8px}
.hd .sch h2{margin:0;font-size:16px;color:#17324d}
.hd .sch p{margin:2px 0;font-size:11px;color:#546E7A}
.hd .badge2{background:#E91E8C;color:#fff;font-weight:700;font-size:12px;padding:8px 12px;border-radius:8px;text-align:center;line-height:1.3}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:3px 18px;font-size:12px;margin-bottom:10px}
.info-grid .row{display:flex;justify-content:space-between;border-bottom:1px dotted #CFD8DC;padding:3px 0}
.info-grid .row b{color:#455A64;font-weight:600}
.sec{font-size:13px;font-weight:700;background:#e8f1f8;border-left:4px solid #1d6fd1;padding:4px 8px;margin:12px 0 6px}
table.att{width:100%;border-collapse:collapse;font-size:10.5px}
table.att th,table.att td{border:1px solid #90A4AE;padding:4px 3px;text-align:center}
table.att th{background:#CFE8F3}
.legend2{margin-top:10px}
.legend2 table{width:100%;border-collapse:collapse;font-size:9.5px}
.legend2 th,.legend2 td{border:1px solid #B0BEC5;padding:4px 2px;text-align:center}
.legend2 th{background:#ECEFF1}
.sign3{display:flex;justify-content:space-between;margin-top:16px;font-size:11px}
.sign3 div{width:30%;text-align:center;border-top:1px solid #78909C;padding-top:4px}

/* ---- Page 2: results + narrative ---- */
table.res{width:100%;border-collapse:collapse;font-size:9px}
table.res th,table.res td{border:1px solid #4B97D1;padding:3px 3px;text-align:center;vertical-align:middle}
table.res thead th{background:#D2DDEB;font-weight:700}
table.res td.subj{text-align:left;font-weight:600}
table.res tfoot td{background:#E3F2FD;font-weight:700}
table.narr{width:100%;border-collapse:collapse;font-size:9.5px;margin-top:4px}
table.narr th,table.narr td{border:1px solid #90A4AE;padding:4px 5px;vertical-align:top}
table.narr th{background:#ECEFF1;text-align:center}
table.narr td.subj{font-weight:600;white-space:nowrap}
.sign{display:flex;justify-content:space-between;margin-top:14px;font-size:11px}
.sign div{width:30%;text-align:center;border-top:1px solid #78909C;padding-top:4px}

@media print{
  body{background:#fff}
  .toolbar{display:none}
  .sheet{margin:0;box-shadow:none;width:210mm;min-height:297mm}
  @page{size:A4;margin:0}
}
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">🖨 छापा / PDF</button>
  <a href="<?= APP_URL ?>/cce/reports.php?std=<?= $std ?>">← रिपोर्ट</a>

  <!-- Unified Theme & Font Switcher -->
  <?= cce_theme_toolbar_controls(false) ?>

  <span style="margin-left:auto;opacity:.8">इयत्ता <?= sanitize(std_label($std)) ?> — वार्षिक प्रगती पत्रक — <?= sanitize($year) ?></span>
</div>

<?php
$grade_legend = [['९१ ते १००','अ-१'],['८१ ते ९०','अ-२'],['७१ ते ८०','ब-१'],['६१ ते ७०','ब-२'],['५१ ते ६०','क-१'],['४१ ते ५०','क-२'],['३३ ते ४०','ड'],['२१ ते ३२','इ-१'],['२१ पेक्षा कमी','इ-२']];
$cs_labels = ['health_pe'=>'शारीरिक शिक्षण व आरोग्य','work_exp'=>'कार्यानुभव','art_ed'=>'कला शिक्षण','attitude'=>'अभिवृत्ती व मूल्ये'];

foreach ($list as $student):
  $sid = (int)$student['id'];
  $m1 = pmc($db, $sid, $year, 1); $m2 = pmc($db, $sid, $year, 2);
  $n1 = pnt($db, $sid, $year, 1); $n2 = pnt($db, $sid, $year, 2);
  $extra = pex($db, $sid);
  $att = pat($db, $sid, $year);

  $grand = 0; $maxgrand = 0;
  foreach ($subjects as $s) {
      $g1 = (float)($m1[(int)$s['id']]['grand_total'] ?? 0); $g2 = (float)($m2[(int)$s['id']]['grand_total'] ?? 0);
      $grand += $g1 + $g2; $maxgrand += ((float)$s['fe_max'] + (float)$s['se_max']) * 2;
  }
  $opct = $maxgrand > 0 ? round($grand / $maxgrand * 100, 2) : 0;
  $ograde = cce_grade_for_percent($opct);

  $work = 0; $pres = 0;
  foreach ($months as $mn => $lbl) { $work += (int)($att[$mn]['working_days'] ?? 0); $pres += (int)($att[$mn]['days_present'] ?? 0); }

  $h1 = $extra['height_1'] ?? ''; $h2 = $extra['height_2'] ?? ''; $w1 = $extra['weight_1'] ?? ''; $w2 = $extra['weight_2'] ?? '';
?>

<!-- ═══ PAGE 1: वैयक्तिक माहिती + उपस्थिती ═══ -->
<section class="sheet">
  <div class="hd">
    <div class="sch">
      <h2><?= sanitize($school['name_mr'] ?? $school['name'] ?? '') ?></h2>
      <p>युडायस: <?= sanitize($school['udise_code'] ?? '—') ?></p>
    </div>
    <div class="badge2">प्रगती<br>पत्रक</div>
  </div>
  <div class="info-grid">
    <div class="row"><span>हजेरी क्र.</span><b><?= sanitize($student['roll_no'] ?? '—') ?></b></div>
    <div class="row"><span>रजि. नं.</span><b><?= sanitize($student['registration_no'] ?? '—') ?></b></div>
    <div class="row"><span>माझे नाव</span><b><?= sanitize($student['name_mr'] ?: $student['name']) ?></b></div>
    <div class="row"><span>लिंग</span><b><?= sanitize($student['gender'] ?? '—') ?></b></div>
    <div class="row"><span>इयत्ता</span><b><?= sanitize(std_label($std)) ?></b></div>
    <div class="row"><span>तुकडी</span><b><?= sanitize($student['section'] ?? '—') ?></b></div>
    <div class="row"><span>जन्मतारीख</span><b><?= sanitize($student['date_of_birth'] ?? $student['dob'] ?? '—') ?></b></div>
    <div class="row"><span>मातृभाषा</span><b><?= sanitize($student['mother_tongue'] ?? '—') ?></b></div>
    <div class="row"><span>माध्यम</span><b><?= sanitize($student['medium_of_instruction'] ?? $student['medium'] ?? '—') ?></b></div>
    <div class="row"><span>आईचे नाव</span><b><?= sanitize($student['mother_name'] ?? '—') ?></b></div>
    <div class="row"><span>वडिलांचे नाव</span><b><?= sanitize($student['father_name'] ?? '—') ?></b></div>
    <div class="row"><span>वजन (स.१ / स.२)</span><b><?= sanitize($w1 ?: '—') ?> / <?= sanitize($w2 ?: '—') ?> kg</b></div>
    <div class="row"><span>उंची (स.१ / स.२)</span><b><?= sanitize($h1 ?: '—') ?> / <?= sanitize($h2 ?: '—') ?> cm</b></div>
    <div class="row"><span>एकूण %  /  श्रेणी</span><b><?= to_mr_digits(number_format($opct, 2, '.', '')) ?>% — <?= sanitize($ograde) ?></b></div>
  </div>

  <div class="sec">📅 उपस्थिती (<?= sanitize($year) ?>)</div>
  <table class="att">
    <tr><th>महिना</th><?php foreach ($months as $mn => $lbl): ?><th><?= sanitize($lbl) ?></th><?php endforeach; ?><th>एकूण</th></tr>
    <tr><th>कामाचे दिवस</th><?php foreach ($months as $mn => $lbl): ?><td><?= isset($att[$mn]) ? to_mr_digits((int)$att[$mn]['working_days']) : '—' ?></td><?php endforeach; ?><td><b><?= to_mr_digits($work) ?></b></td></tr>
    <tr><th>हजर दिवस</th><?php foreach ($months as $mn => $lbl): ?><td><?= isset($att[$mn]) ? to_mr_digits((int)$att[$mn]['days_present']) : '—' ?></td><?php endforeach; ?><td><b><?= to_mr_digits($pres) ?></b></td></tr>
  </table>

  <div class="legend2">
    <table>
      <tr><?php foreach ($grade_legend as $g): ?><th><?= sanitize($g[0]) ?></th><?php endforeach; ?></tr>
      <tr><?php foreach ($grade_legend as $g): ?><td><?= sanitize($g[1]) ?></td><?php endforeach; ?></tr>
    </table>
  </div>

  <div class="sign3">
    <div>वर्गशिक्षक स्वाक्षरी</div>
    <div>पालक स्वाक्षरी</div>
    <div>मुख्याध्यापक स्वाक्षरी</div>
  </div>
</section>

<!-- ═══ PAGE 2: दोन्ही सत्रांचे गुण-श्रेणी व वर्णनात्मक नोंदी ═══ -->
<section class="sheet">
  <div class="hd">
    <div class="sch">
      <h2>सातत्यपूर्ण सर्वंकष मूल्यमापन</h2>
      <p><?= sanitize($student['name_mr'] ?: $student['name']) ?> — इयत्ता <?= sanitize(std_label($std)) ?> — हजेरी क्र. <?= sanitize($student['roll_no'] ?? '—') ?></p>
    </div>
    <div class="badge2" style="background:#1565C0">सन<br><?= sanitize($year) ?></div>
  </div>

  <table class="res">
    <thead>
      <tr>
        <th rowspan="2" style="text-align:left">विषय</th>
        <th colspan="4">प्रथम सत्र</th>
        <th colspan="4">द्वितीय सत्र</th>
      </tr>
      <tr><th>आकारिक</th><th>संकलित</th><th>एकूण</th><th>श्रेणी</th><th>आकारिक</th><th>संकलित</th><th>एकूण</th><th>श्रेणी</th></tr>
    </thead>
    <tbody>
    <?php $t1 = 0; $t2 = 0; $mx1 = 0; $mx2 = 0; foreach ($subjects as $sb): $sid2 = (int)$sb['id']; $a = $m1[$sid2] ?? null; $b = $m2[$sid2] ?? null; $smax = (float)$sb['fe_max'] + (float)$sb['se_max']; $t1 += (float)($a['grand_total'] ?? 0); $t2 += (float)($b['grand_total'] ?? 0); $mx1 += $smax; $mx2 += $smax; ?>
      <tr>
        <td class="subj"><?= sanitize($sb['name_mr']) ?></td>
        <td><?= $a ? pnum($a['fe_total']) : '—' ?></td><td><?= $a ? pnum($a['se_total']) : '—' ?></td><td><b><?= $a ? pnum($a['grand_total']) : '—' ?></b></td><td><?= sanitize($a['grade'] ?? '—') ?></td>
        <td><?= $b ? pnum($b['fe_total']) : '—' ?></td><td><?= $b ? pnum($b['se_total']) : '—' ?></td><td><b><?= $b ? pnum($b['grand_total']) : '—' ?></b></td><td><?= sanitize($b['grade'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td class="subj">एकूण</td>
        <td colspan="2">—</td><td><?= pnum($t1) ?>/<?= pnum($mx1) ?></td><td><?= sanitize(cce_grade_for_percent($mx1 ? $t1/$mx1*100 : 0)) ?></td>
        <td colspan="2">—</td><td><?= pnum($t2) ?>/<?= pnum($mx2) ?></td><td><?= sanitize(cce_grade_for_percent($mx2 ? $t2/$mx2*100 : 0)) ?></td>
      </tr>
    </tfoot>
  </table>

  <div class="sec">📝 वर्णनात्मक नोंदी</div>
  <table class="narr">
    <thead><tr><th style="width:20%">तपशील</th><th style="width:40%">प्रथम सत्र</th><th style="width:40%">द्वितीय सत्र</th></tr></thead>
    <tbody>
      <?php foreach ($subjects as $index => $sb): $slot = $index + 1; if ($slot > 13) break; ?>
      <tr><td class="subj"><?= sanitize($areas[$slot] ?? $sb['name_mr']) ?></td><td><?= sanitize($n1[$slot] ?? '—') ?></td><td><?= sanitize($n2[$slot] ?? '—') ?></td></tr>
      <?php endforeach; ?>
      <?php for ($slot = 10; $slot <= 13; $slot++): ?>
      <tr><td class="subj"><?= sanitize($areas[$slot] ?? '') ?></td><td><?= sanitize($n1[$slot] ?? '—') ?></td><td><?= sanitize($n2[$slot] ?? '—') ?></td></tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <div class="sign">
    <div>वर्गशिक्षक स्वाक्षरी</div>
    <div>पालक स्वाक्षरी</div>
    <div>मुख्याध्यापक स्वाक्षरी</div>
  </div>
</section>

<?php endforeach; ?>
</body>
</html>
