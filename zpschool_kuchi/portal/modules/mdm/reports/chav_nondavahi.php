<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_login();
$sid = (int)$u['school_id'];
$pdo = db();

$group = (string)($_GET['class_group'] ?? '1-5');
if (!in_array($group, ['1-5', '6-8'], true)) $group = '1-5';

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) $month = (int)date('n');

// Handle update of tasting entries if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    $tDates = (array)($_POST['date'] ?? []);
    $tTimes = (array)($_POST['tasting_time'] ?? []);
    $dTimes = (array)($_POST['distribution_time'] ?? []);
    $tNames = (array)($_POST['taster_name'] ?? []);
    $tRatings = (array)($_POST['taste_rating'] ?? []);

    $upd = $pdo->prepare('UPDATE mdm_daily 
                          SET tasting_time=?, distribution_time=?, taster_name=?, taste_rating=? 
                          WHERE school_id=? AND class_group=? AND date=?');
    foreach ($tDates as $i => $dStr) {
        $upd->execute([
            trim((string)($tTimes[$i] ?? '')),
            trim((string)($dTimes[$i] ?? '')),
            trim((string)($tNames[$i] ?? '')),
            trim((string)($tRatings[$i] ?? '')),
            $sid, $group, $dStr
        ]);
    }
    flash_set('success', 'चव नोंदवही माहिती जतन झाली.');
    header("Location: chav_nondavahi.php?class_group=$group&month=$month&year=$year");
    exit;
}

$data = mdm_month_data($pdo, $sid, $year, $month, $group);
$school = $data['school'];
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));

// Excel export
if (isset($_GET['excel'])) {
    $filename = "chav_register_{$group}_{$month}_{$year}.csv";
    $csvHeaders = ['अ.क्र.', 'दिनांक', 'वार', 'चव घेतल्याची वेळ', 'विद्यार्थ्यांना आहार वाटप वेळ', 'आजचा मेनू', 'चव घेणाऱ्याचे नाव', 'चवीबद्दल अभिप्राय', 'चव घेणाऱ्याची सही'];
    $csvRows = [];
    foreach ($data['days'] as $d) {
        $csvRows[] = [
            $d['day_no'],
            $d['date'],
            $d['weekday'],
            $d['tasting_time'],
            $d['distribution_time'],
            $d['menu_name'],
            $d['taster_name'],
            $d['taste_rating'],
            '',
        ];
    }
    mdm_report_csv($filename, $csvHeaders, $csvRows);
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>चव रजिस्टर — शाळा स्तरावर ठेवायची नोंदवही (<?= e($data['group_label']) ?>)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Martel:wght@700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 portrait; margin: 8mm; }
* { box-sizing: border-box; }
body { font-family: 'Mukta', system-ui, sans-serif; color: #000; background: #fff; margin: 0; padding: 10px; font-size: 10.5pt; line-height: 1.25; }
.no-print { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px; margin: -10px -10px 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; font-size: 10pt; }
.btn { font-family: inherit; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
.btn-primary { background: #1e3a8a; color: #fff; border: 1px solid #1e3a8a; }
.btn-outline { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
.btn-success { background: #166534; color: #fff; border: 1px solid #166534; }
.select-ctl { font-family: inherit; font-size: 13px; padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; }

.sheet { max-width: 210mm; margin: 0 auto; }
.head-title { text-align: center; margin-bottom: 4px; }
.head-title h1 { font-family: 'Martel', serif; font-size: 14pt; margin: 0 0 2px; font-weight: 800; }
.head-title h2 { font-size: 11.5pt; margin: 0; font-weight: 700; }
.meta-box { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-top: 6px; margin-bottom: 6px; font-size: 9.5pt; }
.meta-box td { border: 1px solid #000; padding: 3px 6px; }

.rpt-table { width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 8.5pt; }
.rpt-table th, .rpt-table td { border: 1px solid #000; padding: 3px 4px; text-align: center; }
.rpt-table th { background: #f1f5f9; font-weight: 700; }
.rpt-table td.l { text-align: left; }
.rpt-table tr.sunday-row { background: #fff1f2; color: #be123c; }

.cell-input { width: 100%; border: 0; background: transparent; font-family: inherit; font-size: 8.5pt; text-align: center; padding: 2px; }
.cell-input:focus { outline: 1px solid #0284c7; background: #f0f9ff; }

.sign-area { margin-top: 20px; display: flex; justify-content: flex-end; text-align: center; font-size: 10pt; font-weight: 700; page-break-inside: avoid; }
.sign-box { min-width: 180px; border-top: 1px solid #000; padding-top: 4px; }

@media print {
  .no-print { display: none !important; }
  body { padding: 0; }
  .sheet { max-width: 100%; }
  .cell-input { border: 0 !important; }
}
</style>
</head>
<body>

<div class="no-print">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
    <label class="small fw-bold">गट:
      <select name="class_group" class="select-ctl" onchange="this.form.submit()">
        <option value="1-5" <?= $group === '1-5' ? 'selected' : '' ?>>इयत्ता १ ली ते ५ वी (Primary)</option>
        <option value="6-8" <?= $group === '6-8' ? 'selected' : '' ?>>इयत्ता ६ वी ते ८ वी (Upper Primary)</option>
      </select>
    </label>
    <label class="small fw-bold">महिना:
      <select name="month" class="select-ctl" onchange="this.form.submit()">
        <?php foreach (mdm_marathi_months() as $mNum => $mLabel): ?>
          <option value="<?= $mNum ?>" <?= $month === $mNum ? 'selected' : '' ?>><?= $mLabel ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="small fw-bold">वर्ष:
      <input type="number" name="year" value="<?= $year ?>" class="select-ctl" style="width:80px;" onchange="this.form.submit()">
    </label>
    <button type="submit" class="btn btn-outline">पहा</button>
  </form>

  <div style="display:flex;gap:8px;">
    <button type="submit" form="chavForm" class="btn btn-primary">💾 बदल जतन करा</button>
    <a href="?class_group=<?= $group ?>&month=<?= $month ?>&year=<?= $year ?>&excel=1" class="btn btn-success">📥 Excel (CSV)</a>
    <button onclick="window.print()" class="btn btn-outline">🖨️ प्रिंट / PDF</button>
    <a href="<?= url('modules/mdm/reports/index.php') ?>" class="btn btn-outline">सर्व अहवाल</a>
    <a href="<?= url('modules/mdm/dashboard.php') ?>" class="btn btn-outline">डॅशबोर्ड</a>
  </div>
</div>

<div class="sheet">
  <div class="head-title">
    <h1>प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना (<?= e($data['group_label']) ?>)</h1>
    <h2>चव रजिस्टर - शाळा स्तरावर ठेवायची नोंदवही</h2>
  </div>

  <table class="meta-box">
    <tr>
      <td><b>शाळेचे नाव :</b> <?= e($schoolName) ?></td>
      <td><b>केंद्र :</b> <?= e((string)($school['center'] ?? ($school['village'] ?? '—'))) ?></td>
      <td><b>तालुका :</b> <?= e((string)($school['taluka'] ?? '—')) ?></td>
      <td><b>जिल्हा :</b> <?= e((string)($school['district'] ?? '—')) ?></td>
    </tr>
    <tr>
      <td colspan="4" style="text-align:center;font-weight:700;background:#f8fafc;">
        माहे : <?= e($data['month_name']) ?> <?= $year ?>
      </td>
    </tr>
  </table>

  <form id="chavForm" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <table class="rpt-table">
      <thead>
        <tr>
          <th style="width:30px;">अ.क्र.</th>
          <th style="width:75px;">दिनांक</th>
          <th style="width:35px;">वार</th>
          <th style="width:85px;">चव घेतल्याची<br>वेळ</th>
          <th style="width:85px;">विद्यार्थ्यांना आहार<br>वाटप वेळ</th>
          <th>आजचा मेनू</th>
          <th>चव घेणाऱ्याचे<br>नाव</th>
          <th style="width:85px;">चवीबद्दल<br>अभिप्राय</th>
          <th style="width:75px;">चव घेणाऱ्याची<br>सही</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data['days'] as $i => $d): ?>
        <tr class="<?= $d['is_sunday'] ? 'sunday-row' : '' ?>">
          <td><?= $d['day_no'] ?></td>
          <td><?= date('d/m/Y', strtotime($d['date'])) ?></td>
          <td><?= $d['weekday'] ?></td>
          <?php if ($d['is_sunday']): ?>
            <td colspan="6" style="background:#fff1f2;font-style:italic;">रविवार सुट्टी</td>
          <?php elseif ($d['is_holiday']): ?>
            <td colspan="6" style="background:#fef3c7;font-style:italic;">शासकीय सुट्टी</td>
          <?php else: ?>
            <input type="hidden" name="date[]" value="<?= e($d['date']) ?>">
            <td><input class="cell-input" name="tasting_time[]" value="<?= e($d['tasting_time']) ?>"></td>
            <td><input class="cell-input" name="distribution_time[]" value="<?= e($d['distribution_time']) ?>"></td>
            <td class="l fw-semibold"><?= e($d['menu_name']) ?></td>
            <td><input class="cell-input" name="taster_name[]" value="<?= e($d['taster_name']) ?>"></td>
            <td><input class="cell-input" name="taste_rating[]" value="<?= e($d['taste_rating']) ?>"></td>
            <td></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <div class="sign-area">
    <div class="sign-box">मुख्याध्यापक सही व शिक्का<br><?= e($schoolName) ?></div>
  </div>
</div>

</body>
</html>
