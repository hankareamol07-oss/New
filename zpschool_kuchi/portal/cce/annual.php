<?php
/**
 * वार्षिक निकाल पत्रक — दोन्ही सत्रांची बेरीज, श्रेणी व निकाल; वर्गनिहाय छपाईयोग्य.
 */
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/cce_theme_helper.php';
requireLogin();

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$school = getSchool();
$year = academic_year();
$standards = cce_school_standards($school_id);
$std = (int) ($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
$print = !empty($_GET['print']);

$subjects = cce_subjects($school_id, $std);
$students = cce_students($school_id, $std);

$marks = [];
if ($students) {
    $ids = array_column($students, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM cce_marks WHERE academic_year = ? AND student_id IN ($in)");
    $stmt->execute(array_merge([$year], $ids));
    foreach ($stmt->fetchAll() as $r) {
        $marks[(int) $r['student_id']][(int) $r['subject_id']][(int) $r['semester']] = $r;
    }
}

/** एका विद्यार्थ्याचा वार्षिक सारांश */
function annual_row(array $subjects, array $row): array
{
    $out = ['sub' => [], 'total' => 0.0, 'max' => 0.0];
    foreach ($subjects as $s) {
        $sid = (int) $s['id'];
        $t = (float) ($row[$sid][1]['grand_total'] ?? 0) + (float) ($row[$sid][2]['grand_total'] ?? 0);
        $m = ((float) $s['fe_max'] + (float) $s['se_max']) * 2;
        $avg = $m > 0 ? round($t / $m * 100, 1) : 0.0;
        $out['sub'][$sid] = ['total' => $t, 'max' => $m, 'pct' => $avg, 'grade' => cce_grade_for_percent($avg)];
        $out['total'] += $t;
        $out['max'] += $m;
    }
    $out['pct'] = $out['max'] > 0 ? round($out['total'] / $out['max'] * 100, 2) : 0.0;
    $out['grade'] = cce_grade_for_percent($out['pct']);
    $out['result'] = $out['pct'] >= 35 ? 'उत्तीर्ण' : 'अनुत्तीर्ण';
    return $out;
}

$num = function ($v) { return to_mr_digits(rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.')); };

if ($print) {
    ?><!DOCTYPE html><html lang="mr"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>वार्षिक निकाल पत्रक — <?= sanitize(std_label($std)) ?></title>
    <link href="<?= APP_URL ?>/assets/css/cce.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;700&display=swap" rel="stylesheet">
    <?= cce_theme_head_tags() ?>
    <style>
      @page { size: A4 landscape; margin: 10mm; }
      body { font-family: 'Utsaah', 'Mukta', sans-serif; margin: 12px; color: #14212e; font-size: 13px; }
      h1 { font-size: 18px; text-align: center; margin: 0; }
      h2 { font-size: 14px; text-align: center; margin: 4px 0 12px; font-weight: 500; }
      table { width: 100%; border-collapse: collapse; font-size: 11px; }
      th, td { border: 1px solid #334155; padding: 4px 5px; }
      th { background: #f1f5f9; text-align: center; }
      td.n { text-align: center; }
      .sig { display: flex; justify-content: space-between; margin-top: 28px; font-size: 12px; }
      .no-print { text-align: right; margin-bottom: 8px; display:flex; gap:10px; justify-content:flex-end; align-items:center; flex-wrap:wrap; padding:8px; background:#17324d; border-radius:8px; }
      .no-print button { background:#10b981; color:#fff; border:0; border-radius:6px; padding:8px 14px; font-size:14px; font-weight:700; cursor:pointer; min-height:40px; }
      @media screen and (max-width: 991.98px) {
        html, body { overflow-x: hidden !important; width: 100% !important; margin: 6px !important; }
        .no-print { justify-content: flex-start; }
        .no-print button { width: 100%; }
        table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 600px; }
      }
      @media print { .no-print { display: none !important; } }
    </style></head><body>
    <div class="no-print"><button onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button><?= cce_theme_toolbar_controls(false) ?></div>
    <h1><?= sanitize($school['name_mr'] ?? $school['name'] ?? '') ?></h1>
    <h2>वार्षिक निकाल पत्रक — <?= sanitize(std_label($std)) ?> — शै. वर्ष <?= sanitize($year) ?></h2>
    <table>
      <thead><tr>
        <th>क्र.</th><th style="text-align:left">विद्यार्थ्याचे नाव</th>
        <?php foreach ($subjects as $s): ?><th><?= sanitize($s['short_name'] ?: $s['name_mr']) ?></th><?php endforeach; ?>
        <th>एकूण</th><th>%</th><th>श्रेणी</th><th>निकाल</th>
      </tr></thead>
      <tbody>
      <?php foreach ($students as $st): $a = annual_row($subjects, $marks[(int) $st['id']] ?? []); ?>
        <tr>
          <td class="n"><?= to_mr_digits($st['roll_no']) ?></td>
          <td><?= sanitize($st['name_mr'] ?: $st['name']) ?></td>
          <?php foreach ($subjects as $s): ?>
            <td class="n"><?= $num($a['sub'][(int) $s['id']]['total']) ?></td>
          <?php endforeach; ?>
          <td class="n"><?= $num($a['total']) ?> / <?= $num($a['max']) ?></td>
          <td class="n"><?= to_mr_digits(number_format($a['pct'], 2, '.', '')) ?></td>
          <td class="n"><?= sanitize($a['grade']) ?></td>
          <td class="n"><?= sanitize($a['result']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="sig"><span>वर्गशिक्षक</span><span>मुख्याध्यापक</span><span>दिनांक :</span></div>
    </body></html><?php
    exit;
}

$page_title = 'वार्षिक निकाल';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-award"></i> वार्षिक निकाल <small class="text-muted fs-6">(<?= sanitize($year) ?>)</small></h2>
    <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
</div>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label small">इयत्ता</label>
            <select name="std" class="form-select" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === $std ? 'selected' : '' ?>><?= std_label($i) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-8 d-flex align-items-end gap-2">
            <a class="btn btn-success" target="_blank" href="<?= APP_URL ?>/cce/annual.php?std=<?= $std ?>&print=1">
                <i class="bi bi-printer"></i> निकाल पत्रक छापा
            </a>
            <a class="btn btn-outline-success" target="_blank" href="<?= APP_URL ?>/cce/progress_card.php?std=<?= $std ?>&semester=2&all=1">
                <i class="bi bi-files"></i> वर्गाची प्रगती पत्रके
            </a>
        </div>
    </div>
</form>

<?php if (!$students): ?>
    <div class="alert alert-warning">या इयत्तेत विद्यार्थी नाहीत.</div>
<?php elseif (!$subjects): ?>
    <div class="alert alert-warning">विषय नाहीत. <a class="alert-link" href="<?= APP_URL ?>/cce/subjects.php?std=<?= $std ?>">विषय जोडा</a>.</div>
<?php else: ?>
<div class="table-responsive card">
    <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light text-center">
            <tr>
                <th>क्र.</th><th class="text-start" style="min-width:180px">विद्यार्थी</th>
                <?php foreach ($subjects as $s): ?><th title="<?= sanitize($s['name_mr']) ?>"><?= sanitize($s['short_name'] ?: $s['name_mr']) ?></th><?php endforeach; ?>
                <th>एकूण</th><th>%</th><th>श्रेणी</th><th>निकाल</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $st): $a = annual_row($subjects, $marks[(int) $st['id']] ?? []); ?>
            <tr>
                <td class="text-center"><?= to_mr_digits($st['roll_no']) ?></td>
                <td><?= sanitize($st['name_mr'] ?: $st['name']) ?></td>
                <?php foreach ($subjects as $s): ?>
                    <td class="text-center"><?= $num($a['sub'][(int) $s['id']]['total']) ?></td>
                <?php endforeach; ?>
                <td class="text-center fw-bold"><?= $num($a['total']) ?></td>
                <td class="text-center"><?= to_mr_digits(number_format($a['pct'], 2, '.', '')) ?></td>
                <td class="text-center"><span class="badge bg-primary"><?= sanitize($a['grade']) ?></span></td>
                <td class="text-center"><span class="badge bg-<?= $a['result'] === 'उत्तीर्ण' ? 'success' : 'danger' ?>"><?= sanitize($a['result']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
