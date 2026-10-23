<?php
/**
 * सहशालेय क्रियाकलाप मूल्यांकन (Co-Scholastic Assessment)
 * Maharashtra CCE: Health/Physical Ed, Work Experience,
 * Art Education, Attitude & Values — graded A+/A/B/C/D per semester.
 */
$page_title = 'सहशालेय मूल्यांकन';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$school_id = $_SESSION['school_id'];
$year = academic_year();
$standards = cce_school_standards($school_id);

$std = (int)($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_REQUEST['semester'] ?? 1) === 2 ? 2 : 1;

// Auto-create table if not yet migrated
try {
    $db->query("SELECT 1 FROM cce_coscholastic LIMIT 1");
} catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS `cce_coscholastic` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        student_id INT NOT NULL,
        academic_year VARCHAR(10) NOT NULL,
        semester TINYINT NOT NULL DEFAULT 1,
        health_pe   VARCHAR(5) DEFAULT NULL,
        work_exp    VARCHAR(5) DEFAULT NULL,
        art_ed      VARCHAR(5) DEFAULT NULL,
        attitude    VARCHAR(5) DEFAULT NULL,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_cs (student_id, academic_year, semester),
        KEY idx_school_cs (school_id, academic_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$students = cce_students($school_id, $std);
$areas = [
    'health_pe' => 'शारीरिक शिक्षण व आरोग्य',
    'work_exp'  => 'कार्यानुभव',
    'art_ed'    => 'कला शिक्षण',
    'attitude'  => 'अभिवृत्ती व मूल्ये',
];
$valid_grades = ['A+', 'A', 'B', 'C', 'D'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cce_check_csrf();
    $allowed = array_column($students, 'id');
    $stmt = $db->prepare("INSERT INTO cce_coscholastic
        (school_id, student_id, academic_year, semester, health_pe, work_exp, art_ed, attitude)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            health_pe=VALUES(health_pe), work_exp=VALUES(work_exp),
            art_ed=VALUES(art_ed), attitude=VALUES(attitude)");
    foreach (($_POST['cs'] ?? []) as $sid => $row) {
        $sid = (int)$sid;
        if (!in_array($sid, $allowed, true)) continue;
        $g = function($k) use ($row, $valid_grades) {
            $v = $row[$k] ?? '';
            return in_array($v, $valid_grades, true) ? $v : null;
        };
        $stmt->execute([$school_id, $sid, $year, $semester,
            $g('health_pe'), $g('work_exp'), $g('art_ed'), $g('attitude')]);
    }
    flash('success', 'सहशालेय गुण जतन केले.');
    redirect(APP_URL . "/cce/coscholastic.php?std=$std&semester=$semester");
}

$existing = [];
if ($students) {
    $ids = array_column($students, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM cce_coscholastic WHERE school_id=? AND academic_year=? AND semester=? AND student_id IN ($in)");
    $stmt->execute(array_merge([$school_id, $year, $semester], $ids));
    foreach ($stmt->fetchAll() as $r) $existing[(int)$r['student_id']] = $r;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-star-half"></i> सहशालेय मूल्यांकन</h2>
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
        <div class="col-md-4">
            <label class="form-label small">सत्र</label>
            <select name="semester" class="form-select" onchange="this.form.submit()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>प्रथम सत्र</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>द्वितीय सत्र</option>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <span class="small text-muted">श्रेणी: A+ / A / B / C / D</span>
        </div>
    </div>
</form>

<?php if (!$students): ?>
    <div class="alert alert-warning">या इयत्तेत विद्यार्थी नाहीत.</div>
<?php else: ?>
<form method="POST">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="std" value="<?= $std ?>">
    <input type="hidden" name="semester" value="<?= $semester ?>">
    <div class="table-responsive card">
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light text-center">
                <tr>
                    <th style="min-width:46px">क्र.</th>
                    <th style="min-width:190px" class="text-start">विद्यार्थ्याचे नाव</th>
                    <?php foreach ($areas as $label): ?>
                        <th style="min-width:150px"><?= sanitize($label) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $st):
                $row = $existing[(int)$st['id']] ?? []; ?>
                <tr>
                    <td class="text-center"><?= sanitize($st['roll_no']) ?></td>
                    <td><?= sanitize($st['name_mr'] ?: $st['name']) ?></td>
                    <?php foreach ($areas as $key => $label): ?>
                        <td class="text-center">
                            <select name="cs[<?= $st['id'] ?>][<?= $key ?>]"
                                    class="form-select form-select-sm text-center">
                                <option value="">—</option>
                                <?php foreach ($valid_grades as $g): ?>
                                    <option value="<?= $g ?>" <?= ($row[$key] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> जतन करा</button>
</form>

<div class="card mt-4 border-info">
    <div class="card-header bg-info bg-opacity-10 small fw-bold">
        <i class="bi bi-info-circle text-info"></i> श्रेणी स्पष्टीकरण (शासन निकषानुसार)
    </div>
    <div class="card-body small">
        <div class="row g-2 mb-1">
            <div class="col-md-3"><span class="badge bg-success">A+</span> अत्युत्तम (Outstanding)</div>
            <div class="col-md-3"><span class="badge bg-primary">A</span> खूप चांगले (Excellent)</div>
            <div class="col-md-3"><span class="badge bg-info text-dark">B</span> चांगले (Good)</div>
            <div class="col-md-3"><span class="badge bg-warning text-dark">C</span> समाधानकारक</div>
        </div>
        <div><span class="badge bg-danger">D</span> सुधारणा आवश्यक (Needs Improvement)</div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
