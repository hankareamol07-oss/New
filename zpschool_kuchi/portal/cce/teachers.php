<?php
/**
 * विषय शिक्षक नेमणूक — प्रत्येक इयत्तेच्या विषयाला शिक्षक जोडणे.
 */
$page_title = 'विषय शिक्षक नेमणूक';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$year = academic_year();
$standards = cce_school_standards($school_id);
$std = (int) ($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
$division = trim((string) ($_REQUEST['division'] ?? '-')) ?: '-';

$teachers = $db->prepare('SELECT id, name_mr, name, teacher_code FROM teachers WHERE school_id = ? AND is_active = 1 ORDER BY id');
$teachers->execute([$school_id]);
$teachers = $teachers->fetchAll();

$subjects = cce_subjects($school_id, $std);
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cce_check_csrf();
    $del = $db->prepare('DELETE FROM cce_subject_teachers WHERE school_id=? AND academic_year=? AND std=? AND (division=? OR division=?) AND subject_id=?');
    $ins = $db->prepare('INSERT INTO cce_subject_teachers (school_id, academic_year, std, division, subject_id, teacher_id) VALUES (?,?,?,?,?,?)');
    foreach ($subjects as $s) {
        $tid = (int) ($_POST['teacher'][$s['id']] ?? 0);
        $del->execute([$school_id, $year, $std, $division, ($division === 'अ' ? '-' : $division), (int) $s['id']]);
        if ($tid > 0) {
            $ins->execute([$school_id, $year, $std, $division, (int) $s['id'], $tid]);
        }
    }
    $saved = true;
}

$map = [];
$stmt = $db->prepare("SELECT subject_id, teacher_id, division FROM cce_subject_teachers WHERE school_id=? AND academic_year=? AND std=? AND (division=? OR division='-') ORDER BY (division = ?) DESC");
$stmt->execute([$school_id, $year, $std, $division, $division]);
foreach ($stmt->fetchAll() as $r) {
    $sid = (int) $r['subject_id'];
    if (!isset($map[$sid])) {
        $map[$sid] = (int) $r['teacher_id'];
    }
}

// Auto-apply class teacher to unassigned subjects
$ct_stmt = $db->prepare("SELECT class_teacher_id FROM classes WHERE school_id = ? AND std = ? AND (section = ? OR section = '-' OR section IS NULL) ORDER BY (section = ?) DESC LIMIT 1");
$ct_stmt->execute([$school_id, $std, $division, $division]);
$ct_id = (int)$ct_stmt->fetchColumn();

if ($ct_id > 0) {
    foreach ($subjects as $s) {
        $sid = (int)$s['id'];
        if (empty($map[$sid])) {
            $map[$sid] = $ct_id;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-badge"></i> विषय शिक्षक नेमणूक <small class="text-muted fs-6">(<?= sanitize($year) ?>)</small></h2>
    <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
</div>

<?php if ($saved): ?><div class="alert alert-success">नेमणूक जतन झाली.</div><?php endif; ?>

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
        <div class="col-md-4">
            <label class="form-label small">तुकडी</label>
            <input type="text" name="division" class="form-control" value="<?= sanitize($division) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-outline-primary"><i class="bi bi-search"></i> दाखवा</button>
        </div>
    </div>
</form>

<?php if (!$teachers): ?>
    <div class="alert alert-warning">शिक्षक नोंदवलेले नाहीत. <a class="alert-link" href="<?= APP_URL ?>/teachers/list.php">शिक्षक जोडा</a>.</div>
<?php elseif (!$subjects): ?>
    <div class="alert alert-warning">या इयत्तेसाठी विषय नाहीत. <a class="alert-link" href="<?= APP_URL ?>/cce/subjects.php?std=<?= $std ?>">विषय जोडा</a>.</div>
<?php else: ?>
<form method="POST" class="card">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="std" value="<?= $std ?>">
    <input type="hidden" name="division" value="<?= sanitize($division) ?>">
    <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
            <tr><th style="width:60px">क्र.</th><th>विषय</th><th style="width:40%">शिक्षक</th></tr>
        </thead>
        <tbody>
        <?php foreach ($subjects as $i => $s): ?>
            <tr>
                <td class="text-center"><?= to_mr_digits($i + 1) ?></td>
                <td><?= sanitize($s['name_mr']) ?></td>
                <td>
                    <select class="form-select form-select-sm" name="teacher[<?= (int) $s['id'] ?>]">
                        <option value="0">— निवडा —</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (($map[(int) $s['id']] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>>
                                <?= sanitize(($t['name_mr'] ?: $t['name']) . ($t['teacher_code'] ? ' (' . $t['teacher_code'] . ')' : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="card-footer text-end">
        <button class="btn btn-primary"><i class="bi bi-save"></i> जतन करा</button>
    </div>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
