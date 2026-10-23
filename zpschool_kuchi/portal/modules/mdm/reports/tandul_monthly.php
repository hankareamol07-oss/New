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

$data = mdm_month_data($pdo, $sid, $year, $month, $group);
$school = $data['school'];
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));

// Excel export
if (isset($_GET['excel'])) {
    $filename = "tandul_part1_{$group}_{$month}_{$year}.csv";
    $csvHeaders = ['अ.क्र.', 'दिनांक', 'वार', 'पट संख्या', 'मागील शिल्लक (Kg)', 'चालू महिना प्राप्त (Kg)', 'लोकसहभाग/उसनवार (Kg)', 'एकूण तांदूळ (Kg)', 'लाभार्थी संख्या', 'शिजवलेला तांदूळ (Kg)', 'उसनवार परत/खराब (Kg)', 'महिना अखेर शिल्लक (Kg)', 'मुख्याध्यापक स्वाक्षरी'];
    $csvRows = [];
    foreach ($data['days'] as $d) {
        $csvRows[] = [
            $d['day_no'],
            $d['date'],
            $d['weekday'],
            $d['pat'],
            number_format($d['rice_opening'], 3, '.', ''),
            $d['rice_received'] > 0 ? number_format($d['rice_received'], 3, '.', '') : '0',
            $d['rice_borrowed'] > 0 ? number_format($d['rice_borrowed'], 3, '.', '') : '0',
            number_format($d['rice_total_available'], 3, '.', ''),
            $d['beneficiaries'],
            number_format($d['rice_cooked'], 3, '.', ''),
            $d['rice_returned'] > 0 ? number_format($d['rice_returned'], 3, '.', '') : '0',
            number_format($d['rice_closing'], 3, '.', ''),
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
<title>दैनिक खर्च नोंदवही भाग - १ (तांदळाचे हिशोब) — <?= e($data['group_label']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Martel:wght@700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 portrait; margin: 8mm; }
* { box-sizing: border-box; }
body { font-family: 'Mukta', system-ui, sans-serif; color: #000; background: #fff; margin: 0; padding: 10px; font-size: 10.5pt; line-height: 1.25; }
.no-print { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px; margin: -10px -10px 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
.btn { font-family: inherit; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
.btn-primary { background: #1e3a8a; color: #fff; border: 1px solid #1e3a8a; }
.btn-outline { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
.btn-success { background: #166534; color: #fff; border: 1px solid #166534; }
.select-ctl { font-family: inherit; font-size: 13px; padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; }

.sheet { max-width: 210mm; margin: 0 auto; }
.head-title { text-align: center; margin-bottom: 4px; }
.head-title h1 { font-family: 'Martel', serif; font-size: 13.5pt; margin: 0 0 2px; font-weight: 800; }
.head-title h2 { font-size: 11pt; margin: 0; font-weight: 700; }
.meta-box { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-top: 6px; margin-bottom: 6px; font-size: 9.5pt; }
.meta-box td { border: 1px solid #000; padding: 3px 6px; }

.rpt-table { width: 100%; border: 1px solid #000; border-collapse: collapse; font-size: 8.5pt; }
.rpt-table th, .rpt-table td { border: 1px solid #000; padding: 2px 3px; text-align: center; }
.rpt-table th { background: #f1f5f9; font-weight: 700; }
.rpt-table td.r { text-align: right; }
.rpt-table tr.sunday-row { background: #fff1f2; color: #be123c; }
.rpt-table tr.total-row { font-weight: 700; background: #f8fafc; font-size: 9pt; }

.sign-area { margin-top: 15px; display: flex; justify-content: space-between; text-align: center; font-size: 10pt; font-weight: 700; page-break-inside: avoid; }
.sign-box { min-width: 160px; border-top: 1px solid #000; padding-top: 4px; }

@media print {
  .no-print { display: none !important; }
  body { padding: 0; }
  .sheet { max-width: 100%; }
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
    <a href="?class_group=<?= $group ?>&month=<?= $month ?>&year=<?= $year ?>&excel=1" class="btn btn-success">📥 Excel (CSV)</a>
    <button onclick="window.print()" class="btn btn-primary">🖨️ प्रिंट / PDF</button>
    <a href="<?= url('modules/mdm/reports/index.php') ?>" class="btn btn-outline">सर्व अहवाल</a>
    <a href="<?= url('modules/mdm/dashboard.php') ?>" class="btn btn-outline">डॅशबोर्ड</a>
  </div>
</div>

<div class="sheet">
  <div class="head-title">
    <h1>प्रधानमंत्री पोषण शक्ती निर्माण योजना (<?= e($data['group_label']) ?>)</h1>
    <h2>शाळा स्तरावर ठेवण्याची दैनंदिन खर्च नोंदवही भाग - १ (तांदळाचे हिशोब)</h2>
  </div>

  <table class="meta-box">
    <tr>
      <td colspan="2"><b>प्रती विद्यार्थी तांदूळ =</b> <?= number_format($data['rice_norm'], 3) ?> Kg</td>
      <td colspan="2"><b>माहे :</b> <?= e($data['month_name']) ?> <?= $year ?></td>
    </tr>
    <tr>
      <td><b>शाळा :</b> <?= e($schoolName) ?></td>
      <td><b>केंद्र :</b> <?= e((string)($school['center'] ?? ($school['village'] ?? '—'))) ?></td>
      <td><b>तालुका :</b> <?= e((string)($school['taluka'] ?? '—')) ?></td>
      <td><b>जिल्हा :</b> <?= e((string)($school['district'] ?? '—')) ?></td>
    </tr>
  </table>

  <table class="rpt-table">
    <thead>
      <tr>
        <th style="width:25px;">अ.क्र.</th>
        <th style="width:75px;">दिनांक</th>
        <th style="width:35px;">वार</th>
        <th style="width:40px;">पट संख्या</th>
        <th>मागील महिना<br>शिल्लक तांदूळ</th>
        <th>चालू महिना<br>प्राप्त तांदूळ</th>
        <th>लोकसहभाग /<br>उसनवार</th>
        <th>एकूण तांदूळ<br>(५+६+७)</th>
        <th style="width:45px;">लाभार्थी संख्या<br>(ताटे)</th>
        <th>शिजवलेला<br>तांदूळ (Kg)</th>
        <th>उसनवार परत /<br>खराब होणे</th>
        <th>महिना अखेर<br>शिल्लक तांदूळ</th>
        <th style="width:65px;">मुख्याध्यापक<br>स्वाक्षरी</th>
      </tr>
      <tr style="font-size:7.5pt;background:#f8fafc;color:#475569;">
        <th>१</th><th>२</th><th>३</th><th>४</th><th>५</th><th>६</th><th>७</th><th>८</th><th>९</th><th>१०</th><th>११</th><th>१२</th><th>१३</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data['days'] as $d): ?>
      <tr class="<?= $d['is_sunday'] ? 'sunday-row' : '' ?>">
        <td><?= $d['day_no'] ?></td>
        <td><?= date('d/m/Y', strtotime($d['date'])) ?></td>
        <td><?= $d['weekday'] ?></td>
        <td><?= $d['pat'] ?></td>
        <td class="r"><?= number_format($d['rice_opening'], 3) ?></td>
        <td class="r"><?= $d['rice_received'] > 0 ? number_format($d['rice_received'], 3) : '०' ?></td>
        <td class="r"><?= $d['rice_borrowed'] > 0 ? number_format($d['rice_borrowed'], 3) : '०' ?></td>
        <td class="r fw-bold"><?= number_format($d['rice_total_available'], 3) ?></td>
        <td class="fw-bold"><?= $d['beneficiaries'] > 0 ? $d['beneficiaries'] : '०' ?></td>
        <td class="r"><?= $d['rice_cooked'] > 0 ? number_format($d['rice_cooked'], 3) : '०' ?></td>
        <td class="r"><?= $d['rice_returned'] > 0 ? number_format($d['rice_returned'], 3) : '०' ?></td>
        <td class="r fw-bold"><?= number_format($d['rice_closing'], 3) ?></td>
        <td></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td colspan="4">एकूण</td>
        <td class="r"><?= number_format($data['commodities'][1]['opening'], 3) ?></td>
        <td class="r"><?= number_format($data['commodities'][1]['received'], 3) ?></td>
        <td class="r"><?= number_format($data['commodities'][1]['borrowed'], 3) ?></td>
        <td class="r"><?= number_format($data['commodities'][1]['total_available'], 3) ?></td>
        <td><?= (int)$data['total_beneficiaries'] ?></td>
        <td class="r"><?= number_format($data['total_cooked_rice'], 3) ?></td>
        <td class="r"><?= number_format($data['commodities'][1]['returned'], 3) ?></td>
        <td class="r"><?= number_format($data['commodities'][1]['closing'], 3) ?></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <div class="sign-area">
    <div><b>एकूण लाभार्थी ताटे :</b> <?= (int)$data['total_beneficiaries'] ?></div>
    <div class="sign-box">अध्यक्ष<br>शाळा व्यवस्थापन समिती</div>
    <div class="sign-box">मुख्याध्यापक<br><?= e($schoolName) ?></div>
  </div>
</div>

</body>
</html>
