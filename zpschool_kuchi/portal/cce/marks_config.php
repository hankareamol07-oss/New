<?php
/**
 * भारांश (कमाल गुण) सेटअप — CCE "Set Maximum Marks" screen equivalent.
 * विषय व सत्रानुसार आकारिक / संकलितचे कमाल गुण ठरवा — गुण नोंदणीपूर्वी आवश्यक.
 */
$page_title = 'भारांश निश्चिती';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$standards = cce_school_standards($school_id);
$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$tech = cce_technique_labels();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cce_check_csrf();
    $std = (int)($_POST['std'] ?? $std);
    $upd = $db->prepare("UPDATE cce_subjects SET
            fe1_max=?, fe2_max=?, fe3_max=?, fe4_max=?, fe5_max=?, fe6_max=?, fe7_max=?, fe8_max=?,
            se1_max=?, se2_max=?, se3_max=?, fe_max=?, se_max=?
        WHERE id = ? AND school_id = ?");
    foreach (($_POST['s'] ?? []) as $subject_id => $row) {
        $fe = []; $feSum = 0;
        for ($i = 1; $i <= 8; $i++) {
            $v = trim((string)($row['fe' . $i] ?? ''));
            $fe[$i] = $v === '' ? null : max(0, (int)$v);
            $feSum += $fe[$i] ?? 0;
        }
        $se = []; $seSum = 0;
        for ($i = 1; $i <= 3; $i++) {
            $v = trim((string)($row['se' . $i] ?? ''));
            $se[$i] = $v === '' ? null : max(0, (int)$v);
            $seSum += $se[$i] ?? 0;
        }
        $upd->execute([
            $fe[1], $fe[2], $fe[3], $fe[4], $fe[5], $fe[6], $fe[7], $fe[8],
            $se[1], $se[2], $se[3], $feSum, $seSum,
            (int)$subject_id, $school_id,
        ]);
    }

    try {
        $sqldb = cce_db($school_id);
        $updS = $sqldb->prepare("UPDATE cce_subjects SET
                fe1_max=?, fe2_max=?, fe3_max=?, fe4_max=?, fe5_max=?, fe6_max=?, fe7_max=?, fe8_max=?,
                se1_max=?, se2_max=?, se3_max=?, fe_max=?, se_max=?
            WHERE id = ? AND school_id = ?");
        foreach (($_POST['s'] ?? []) as $subject_id => $row) {
            $fe = []; $feSum = 0;
            for ($i = 1; $i <= 8; $i++) {
                $v = trim((string)($row['fe' . $i] ?? ''));
                $fe[$i] = $v === '' ? null : max(0, (int)$v);
                $feSum += $fe[$i] ?? 0;
            }
            $se = []; $seSum = 0;
            for ($i = 1; $i <= 3; $i++) {
                $v = trim((string)($row['se' . $i] ?? ''));
                $se[$i] = $v === '' ? null : max(0, (int)$v);
                $seSum += $se[$i] ?? 0;
            }
            $updS->execute([
                $fe[1], $fe[2], $fe[3], $fe[4], $fe[5], $fe[6], $fe[7], $fe[8],
                $se[1], $se[2], $se[3], $feSum, $seSum,
                (int)$subject_id, $school_id,
            ]);
        }
    } catch (Throwable $e) {}

    flash('success', 'भारांश जतन केला.');
    redirect(APP_URL . '/cce/marks_config.php?std=' . $std);
}

$subjects = cce_subjects($school_id, $std);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="cce-page">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-1"><i class="bi bi-sliders" style="color:var(--cce-blue)"></i> भारांश (कमाल गुण) सेटअप</h2>
        <div class="text-muted small">विषय व सत्रानुसार आकारिक / संकलितचे कमाल गुण ठरवा — गुण नोंदणीपूर्वी आवश्यक</div>
    </div>
    <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
</div>

<form method="GET" class="mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-auto"><label class="col-form-label">इयत्ता</label></div>
        <div class="col-auto">
            <select name="std" class="form-select" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === $std ? 'selected' : '' ?>><?= std_label($i) ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</form>

<?php if (!$subjects): ?>
<div class="alert alert-warning">या इयत्तेसाठी अद्याप विषय नाहीत. <a href="<?= APP_URL ?>/cce/subjects.php?std=<?= $std ?>" class="alert-link">विषय जोडा</a>.</div>
<?php else: ?>
<form method="POST" id="weightForm">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="std" value="<?= $std ?>">
    <div class="table-responsive card border-0 shadow-sm">
        <table class="table table-sm table-bordered align-middle mb-0" id="weightTable" style="font-size:12px">
            <thead class="text-center table-light">
                <tr>
                    <th rowspan="2" style="min-width:60px">विषय क्र.</th>
                    <th rowspan="2" style="min-width:150px;text-align:left">विषय</th>
                    <th colspan="9" style="background:#fce4ec">अ. आकारिक मूल्यमापन</th>
                    <th colspan="4" style="background:#e3f2fd">ब. संकलित मूल्यमापन</th>
                    <th rowspan="2">एकूण<br>(अ+ब)</th>
                </tr>
                <tr>
                    <?php foreach ($tech['fe'] as $i => $label): ?><th style="background:#fce4ec;min-width:60px"><?= sanitize($label) ?></th><?php endforeach; ?>
                    <th style="background:#f8bbd0;font-weight:700">एकूण (अ)</th>
                    <?php foreach ($tech['se'] as $i => $label): ?><th style="background:#e3f2fd;min-width:60px"><?= sanitize($label) ?></th><?php endforeach; ?>
                    <th style="background:#bbdefb;font-weight:700">एकूण (ब)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $index => $s): ?>
                <tr data-row>
                    <td class="text-center"><?= to_mr_digits($index + 1) ?></td>
                    <td><?= sanitize($s['name_mr']) ?></td>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <td><input type="number" min="0" class="form-control form-control-sm text-center fe-in" name="s[<?= $s['id'] ?>][fe<?= $i ?>]" value="<?= $s['fe'.$i.'_max'] !== null ? (int)$s['fe'.$i.'_max'] : '' ?>"></td>
                    <?php endfor; ?>
                    <td class="text-center fw-bold fe-sum" style="background:#fce4ec">0</td>
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <td><input type="number" min="0" class="form-control form-control-sm text-center se-in" name="s[<?= $s['id'] ?>][se<?= $i ?>]" value="<?= $s['se'.$i.'_max'] !== null ? (int)$s['se'.$i.'_max'] : '' ?>"></td>
                    <?php endfor; ?>
                    <td class="text-center fw-bold se-sum" style="background:#e3f2fd">0</td>
                    <td class="text-center fw-bold grand-sum">0</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="alert alert-secondary small mt-3">
        <i class="bi bi-info-circle"></i> रकाना रिकामा ठेवल्यास ते तंत्र या विषयासाठी लागू नाही असे समजले जाईल — गुण नोंदणी पानावर तो रकाना दिसणार नाही.
    </div>
    <button class="btn btn-primary mt-1"><i class="bi bi-save"></i> भारांश जतन करा</button>
</form>
<script>
(function () {
    function recalc(row) {
        var fe = 0, se = 0;
        row.querySelectorAll('.fe-in').forEach(function (i) { var v = parseFloat(i.value); if (!isNaN(v)) fe += v; });
        row.querySelectorAll('.se-in').forEach(function (i) { var v = parseFloat(i.value); if (!isNaN(v)) se += v; });
        row.querySelector('.fe-sum').textContent = fe;
        row.querySelector('.se-sum').textContent = se;
        row.querySelector('.grand-sum').textContent = fe + se;
    }
    document.querySelectorAll('#weightTable tr[data-row]').forEach(function (row) {
        recalc(row);
        row.addEventListener('input', function () { recalc(row); });
    });
})();
</script>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
