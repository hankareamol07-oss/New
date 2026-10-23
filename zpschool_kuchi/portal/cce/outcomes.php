<?php
/**
 * अध्ययन निष्पत्ती — सातत्यपूर्ण मूल्यमापन स्तर १-४ नोंद स्क्रीन (checkbox ऐवजी प्रत्येक निष्पत्तीसाठी स्तर).
 */
$page_title = 'अध्ययन निष्पत्ती';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$school_id = $_SESSION['school_id'];
$db = cce_db($school_id);
$mysql = getMainDB();
$year = academic_year();
$standards = cce_school_standards($school_id);

$std = (int)($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_REQUEST['semester'] ?? 1) === 2 ? 2 : 1;
$student_id = (int)($_REQUEST['student_id'] ?? 0);
$sub_filter = $_REQUEST['sub'] ?? '';

$students = cce_students($school_id, $std);
if (!$student_id && $students) $student_id = (int)$students[0]['id'];
$student = null;
foreach ($students as $s) if ((int)$s['id'] === $student_id) $student = $s;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $student) {
    cce_check_csrf();
    $visible = array_filter(array_map('intval', explode(',', $_POST['visible_ids'] ?? '')));
    $levels = $_POST['level'] ?? []; // [outcome_id => '1'..'4' or '']

    if ($visible) {
        $in = implode(',', array_fill(0, count($visible), '?'));
        $del = $db->prepare("DELETE FROM cce_student_outcomes WHERE student_id = ? AND academic_year = ? AND semester = ? AND outcome_id IN ($in)");
        $del->execute(array_merge([$student_id, $year, $semester], $visible));
    }
    $chosen = [];
    foreach ($visible as $oid) {
        $lvl = (int)($levels[$oid] ?? 0);
        if ($lvl >= 1 && $lvl <= 4) $chosen[$oid] = $lvl;
    }
    if ($chosen) {
        $in = implode(',', array_fill(0, count($chosen), '?'));
        $texts = [];
        $stmt = $mysql->prepare("SELECT id, learningoutcome FROM cce_learning_outcomes WHERE id IN ($in)");
        $stmt->execute(array_keys($chosen));
        foreach ($stmt->fetchAll() as $r) $texts[(int)$r['id']] = $r['learningoutcome'];

        $ins = $db->prepare("INSERT OR REPLACE INTO cce_student_outcomes (school_id, student_id, academic_year, semester, outcome_id, outcome_text, achieved, level)
                             VALUES (?,?,?,?,?,?,1,?)");
        foreach ($chosen as $oid => $lvl) {
            $ins->execute([$school_id, $student_id, $year, $semester, $oid, $texts[$oid] ?? '', $lvl]);
        }
    }
    flash('success', 'अध्ययन निष्पत्ती जतन केल्या.');
    redirect(APP_URL . "/cce/outcomes.php?std=$std&semester=$semester&student_id=$student_id&sub=" . urlencode($sub_filter));
}

$outcomes = cce_learning_outcomes($std, $sub_filter === '' ? null : $sub_filter);
$subs = [];
try {
    $stmt = $mysql->prepare("SELECT DISTINCT sub FROM cce_learning_outcomes WHERE std = ? AND medium = 'MA' ORDER BY sub");
    $stmt->execute([$std]);
    $subs = array_column($stmt->fetchAll(), 'sub');
} catch (PDOException $e) {
}

$selected = []; // [outcome_id => level]
if ($student) {
    $stmt = $db->prepare("SELECT outcome_id, level FROM cce_student_outcomes WHERE student_id = ? AND academic_year = ? AND semester = ? AND achieved = 1");
    $stmt->execute([$student_id, $year, $semester]);
    foreach ($stmt->fetchAll() as $r) $selected[(int)$r['outcome_id']] = (int)($r['level'] ?? 4);
}
$done_count = 0;
foreach ($outcomes as $o) if (isset($selected[(int)$o['id']])) $done_count++;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="mb-0"><i class="bi bi-bullseye"></i> अध्ययन निष्पत्ती — स्तर नोंद</h2>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= APP_URL ?>/cce/outcomes_report.php?std=<?= $std ?>&sub=<?= urlencode((string)$sub_filter) ?>&semester=<?= $semester ?>&view=short" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-bar-chart-steps"></i> संक्षिप्त अहवाल (Short)
        </a>
        <a href="<?= APP_URL ?>/cce/outcomes_report.php?std=<?= $std ?>&sub=<?= urlencode((string)$sub_filter) ?>&semester=<?= $semester ?>&student_id=<?= $student_id ?>&view=student" class="btn btn-outline-success btn-sm">
            <i class="bi bi-person-badge"></i> विद्यार्थी अहवाल (Student)
        </a>
        <a href="<?= APP_URL ?>/cce/outcomes_report.php?std=<?= $std ?>&sub=<?= urlencode((string)$sub_filter) ?>&semester=<?= $semester ?>&view=matrix" class="btn btn-outline-info btn-sm">
            <i class="bi bi-grid-3x3"></i> वर्गस्तर ग्रिड (Matrix)
        </a>
        <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
    </div>
</div>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-2">
            <label class="form-label small">इयत्ता</label>
            <select name="std" class="form-select" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>" <?= $i === $std ? 'selected' : '' ?>><?= std_label($i) ?></option><?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">सत्र</label>
            <select name="semester" class="form-select" onchange="this.form.submit()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>प्रथम</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>द्वितीय</option>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small">विद्यार्थी नाव निवडा</label>
            <select name="student_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $student_id ? 'selected' : '' ?>>RollNo <?= sanitize($s['roll_no']) ?> - <?= sanitize($s['name_mr'] ?: $s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">विषय</label>
            <select name="sub" class="form-select" onchange="this.form.submit()">
                <option value="">सर्व विषय</option>
                <?php foreach ($subs as $sb): ?>
                    <option value="<?= sanitize($sb) ?>" <?= (string)$sb === (string)$sub_filter ? 'selected' : '' ?>><?= sanitize(cce_outcome_subject_name($std, $sb)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if (!$student): ?>
    <div class="alert alert-warning">या इयत्तेत विद्यार्थी नाहीत.</div>
<?php elseif (!$outcomes): ?>
    <div class="alert alert-warning">या इयत्तेसाठी अध्ययन निष्पत्ती आयात झालेल्या नाहीत. <code>sql/cce_master.sql</code> आयात करा.</div>
<?php else: ?>
<form method="POST" id="loForm">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="std" value="<?= $std ?>">
    <input type="hidden" name="semester" value="<?= $semester ?>">
    <input type="hidden" name="student_id" value="<?= $student_id ?>">
    <input type="hidden" name="sub" value="<?= sanitize($sub_filter) ?>">
    <input type="hidden" name="visible_ids" value="<?= implode(',', array_column($outcomes, 'id')) ?>">

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" style="background:#d7eef8">
            <span>RollNo: <b><?= sanitize($student['roll_no']) ?></b> — <?= sanitize($student['name_mr'] ?: $student['name']) ?></span>
            <div class="d-flex gap-2 align-items-center">
                <input class="form-control form-control-sm" id="loSearch" placeholder="शोधा..." style="width:160px">
                <select id="bulkLevel" class="form-select form-select-sm" style="width:90px">
                    <option value="1">स्तर १</option>
                    <option value="2">स्तर २</option>
                    <option value="3">स्तर ३</option>
                    <option value="4" selected>स्तर ४</option>
                </select>
                <button type="button" id="bulkApply" class="btn btn-sm btn-outline-primary text-nowrap">सर्व निष्पत्तींना लागू करा</button>
            </div>
        </div>
        <div class="table-responsive" style="max-height:65vh; overflow:auto">
            <table class="table table-sm table-bordered align-middle mb-0" id="loList">
                <thead class="table-light text-center" style="position:sticky;top:0;z-index:1">
                    <tr>
                        <th style="width:70px">क्रमांक</th>
                        <th style="text-align:left">अध्ययन निष्पत्ती</th>
                        <th style="width:70px">स्तर १</th>
                        <th style="width:70px">स्तर २</th>
                        <th style="width:70px">स्तर ३</th>
                        <th style="width:70px">स्तर ४</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($outcomes as $o): $oid = (int)$o['id']; $cur = $selected[$oid] ?? 0; ?>
                    <tr data-row>
                        <td class="text-center"><span class="badge bg-secondary"><?= sanitize(trim((string)$o['no'])) ?></span></td>
                        <td class="lo-text"><?= sanitize($o['learningoutcome']) ?></td>
                        <?php for ($lvl = 1; $lvl <= 4; $lvl++): ?>
                            <td class="text-center"><input class="form-check-input lvl-radio" type="radio" name="level[<?= $oid ?>]" value="<?= $lvl ?>" <?= $cur === $lvl ? 'checked' : '' ?>></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>लागू अध्ययन निष्पत्ती : <b><?= to_mr_digits(count($outcomes)) ?></b></span>
            <span>केलेल्या नोंदी : <b id="doneCount"><?= to_mr_digits($done_count) ?></b></span>
            <button class="btn btn-primary"><i class="bi bi-save"></i> जतन करा</button>
        </div>
    </div>
</form>
<script>
document.getElementById('loSearch').addEventListener('input', function () {
    var q = this.value.trim();
    document.querySelectorAll('#loList tbody tr[data-row]').forEach(function (row) {
        row.style.display = (q === '' || row.querySelector('.lo-text').textContent.indexOf(q) !== -1) ? '' : 'none';
    });
});
document.getElementById('bulkApply').addEventListener('click', function () {
    var lvl = document.getElementById('bulkLevel').value;
    document.querySelectorAll('#loList tbody tr[data-row]').forEach(function (row) {
        if (row.style.display === 'none') return;
        var radio = row.querySelector('input.lvl-radio[value="' + lvl + '"]');
        if (radio) radio.checked = true;
    });
    updateDoneCount();
});
function updateDoneCount() {
    var n = 0;
    document.querySelectorAll('#loList tbody tr[data-row]').forEach(function (row) {
        if (row.querySelector('input.lvl-radio:checked')) n++;
    });
    document.getElementById('doneCount').textContent = n;
}
document.getElementById('loForm').addEventListener('change', function (e) {
    if (e.target.classList.contains('lvl-radio')) updateDoneCount();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
