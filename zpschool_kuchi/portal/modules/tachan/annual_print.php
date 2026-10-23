<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/planner_seed.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';

$u = require_login();
$sid = (int) $u['school_id'];
$pdo = db();
$classes = school_classes($sid);
$byId = [];
foreach ($classes as $c) $byId[(int) $c['id']] = $c;
if (!$byId) { http_response_code(404); exit('वर्ग नाहीत'); }
$pref = tachan_prefs_get($pdo, $sid, (int) $u['id']);
$sel = isset($_GET['class_id']) && isset($byId[(int) $_GET['class_id']]) ? (int) $_GET['class_id'] : (int) ($pref['class_id'] ?: array_key_first($byId));
if (!isset($byId[$sel])) $sel = (int) array_key_first($byId);
$class = $byId[$sel];
$months = planner_months();
$m = isset($_GET['month']) ? (int) $_GET['month'] : 0;
if ($m !== 0 && !isset($months[$m])) $m = 0;
$matrix = !empty($_GET['matrix']);
$medium = isset($_GET['medium']) ? tachan_norm_medium((string) $_GET['medium']) : $pref['medium'];
$disp = static fn(string $s): string => tachan_display_subject($s, $medium);

$school = school_row($sid) ?: [];
$schoolName = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));
$teacher = resolve_teacher_name($class, $sid, $u);

$q = $pdo->prepare('SELECT subject,month,topics,week1,week2,week3,week4 FROM annual_plans WHERE school_id=? AND class_id=? ORDER BY id');
$q->execute([$sid, $sel]);
$by = []; $subjects = [];
foreach ($q->fetchAll() as $r) { $by[$r['subject']][(int) $r['month']] = $r; $subjects[$r['subject']] = true; }
$std = planner_class_std($class);
$order = [];
foreach (array_merge(planner_subjects_for_std($std), planner_extra_subjects($std)) as $s) if (isset($subjects[$s])) $order[] = $s;
foreach (array_keys($subjects) as $s) if (!in_array($s, $order, true)) $order[] = $s;

$weeks = static function (?array $r): array {
    if (!$r) return ['', '', '', ''];
    $w = [];
    for ($i = 1; $i <= 4; $i++) $w[] = (string) ($r['week' . $i] ?? '');
    if (implode('', $w) === '') $w[0] = (string) ($r['topics'] ?? '');
    return $w;
};
$page = $matrix ? 'A3 landscape' : ($m === 0 ? 'A4 landscape' : 'A4 portrait');
$title = $m === 0 ? 'वार्षिक अभ्यासक्रम नियोजन २०२६-२७' : 'मासिक अभ्यासक्रम नियोजन — ' . $months[$m] . ' २०२६-२७';
$mediumLabel = $medium === 'semi_english' ? 'सेमी-इंग्रजी' : 'मराठी';
$wk = ['पहिला आठवडा', 'दुसरा आठवडा', 'तिसरा आठवडा', 'चौथा आठवडा'];
?>
<!doctype html><html lang="mr"><head><meta charset="utf-8"><title><?= e($title) ?> — <?= e($class['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Martel:wght@600;700;800;900&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
@page{size:<?= $page ?>;margin:9mm}
body{font-family:'Mukta','Mukta',-apple-system,sans-serif;-webkit-font-smoothing:antialiased;color:#0f172a;margin:0;font-size:<?= $matrix ? '8.5px' : '10px' ?>;line-height:1.28}
.no-print{padding:8px;text-align:right}.no-print button{background:#D69E2E;border:0;border-radius:6px;padding:7px 14px;font-weight:700;cursor:pointer;color:#1A365D}
.head{text-align:center;border:1px solid #334155;padding:5px;margin-bottom:5px}
.head h1{margin:0;font-family:'Martel','Mukta',serif;font-weight:800;font-size:<?= $matrix ? '13.5px' : '16px' ?>}.head h2{margin:2px 0;font-family:'Mukta',sans-serif;font-size:<?= $matrix ? '11px' : '12.5px' ?>;font-weight:700}
.meta{display:flex;justify-content:space-between;font-size:<?= $matrix ? '8px' : '10px' ?>;margin-top:3px}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{border:1px solid #334155;padding:3px 4px;vertical-align:top;word-wrap:break-word}
th{background:#e2e8f0;text-align:center;font-weight:700}
td.sub{font-weight:700;background:#f8fafc}
.mb{margin-bottom:8px;page-break-inside:avoid}
.mtitle{background:#1A365D;color:#fff;font-weight:700;padding:3px 6px;font-size:11px}
.foot{display:flex;justify-content:space-between;margin-top:18px;font-weight:700}
.foot div{width:30%;text-align:center;border-top:1px solid #334155;padding-top:4px}
@media print{.no-print{display:none}}
</style></head><body>
<div class="no-print"><button onclick="window.print()">प्रिंट / PDF जतन करा</button></div>
<div class="head">
  <h1><?= e($schoolName) ?></h1>
  <h2><?= e($title) ?></h2>
  <div class="meta"><span>यू-डायस : <?= e($udise) ?></span><span>इयत्ता : <?= e($class['name']) ?> · माध्यम : <?= e($mediumLabel) ?></span><span>शिक्षक : <?= e($teacher) ?></span></div>
</div>

<?php if (!$order): ?>
<p>या वर्गासाठी वार्षिक नियोजन नोंदी नाहीत.</p>

<?php elseif ($matrix): /* A3: विषय × महिना, प्रत्येक सेलमध्ये ४ आठवडे */ ?>
<table>
  <thead><tr><th style="width:6%">विषय</th><?php foreach ($months as $num => $name): ?><th><?= e($name) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($order as $sub): ?>
    <tr><td class="sub"><?= e($disp($sub)) ?></td>
    <?php foreach ($months as $num => $_): $w = $weeks($by[$sub][$num] ?? null); ?>
      <td><?php $out = []; foreach ($w as $i => $v) if ($v !== '') $out[] = '<b>' . ($i + 1) . '.</b> ' . e($v); echo implode('<br>', $out); ?></td>
    <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php elseif ($m === 0): /* संपूर्ण वर्ष: प्रत्येक महिन्याचा तक्ता */ ?>
<?php foreach ($months as $num => $name): $any = false; foreach ($order as $sub) if (isset($by[$sub][$num])) { $any = true; break; } if (!$any) continue; ?>
<div class="mb">
  <div class="mtitle"><?= e($name) ?></div>
  <table>
    <thead><tr><th style="width:14%">विषय</th><?php foreach ($wk as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($order as $sub): if (!isset($by[$sub][$num])) continue; $w = $weeks($by[$sub][$num]); ?>
      <tr><td class="sub"><?= e($disp($sub)) ?></td><?php foreach ($w as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>

<?php else: /* एक महिना */ ?>
<table>
  <thead><tr><th style="width:16%">विषय</th><?php foreach ($wk as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($order as $sub): $w = $weeks($by[$sub][$m] ?? null); ?>
    <tr><td class="sub"><?= e($disp($sub)) ?></td><?php foreach ($w as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="foot"><div>वर्गशिक्षक</div><div>यू-डायस : <?= e($udise) ?></div><div>मुख्याध्यापक</div></div>
<?php if (isset($_GET['dl_pdf'])): ?><script>window.addEventListener('load',function(){window.print();});</script><?php endif; ?>
</body></html>
