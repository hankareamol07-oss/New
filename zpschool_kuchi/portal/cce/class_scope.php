<?php
/**
 * सातत्यपूर्ण मूल्यमापन वर्ग / तुकडी निवड (Class / Division Scope Selector)
 * सातत्यपूर्ण मूल्यमापन मध्ये कोणत्या तुकड्या व त्यांचे विद्यार्थी दिसावेत ते निवडा
 */
$page_title = 'सातत्यपूर्ण मूल्यमापन वर्ग / तुकडी निवडा';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

$standards = cce_school_standards($school_id);
if (empty($standards)) {
    for ($i = 1; $i <= 8; $i++) $standards[$i] = std_label($i);
}

// Fetch classes from database
$stmt = $db->prepare("SELECT c.*, t.name_mr AS teacher_name_mr, t.name AS teacher_name 
                      FROM classes c 
                      LEFT JOIN teachers t ON c.class_teacher_id = t.id 
                      WHERE c.school_id = ? 
                      ORDER BY c.std ASC, c.section ASC");
$stmt->execute([$school_id]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="cce-page" style="max-width: 820px; margin: 0 auto; padding: 1rem 0.5rem 3rem;">
    <!-- Top Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 bg-white p-3 rounded-3 border">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= APP_URL ?>/cce/index.php" class="text-secondary text-decoration-none" title="सातत्यपूर्ण मूल्यमापन डॅशबोर्डवर परत जा">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <div class="d-flex align-items-center gap-2">
                <div style="width:34px; height:34px; border-radius:8px; background:#F5F3FF; color:#7C3AED; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-check2-circle fs-6"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size:1.05rem;">वर्ग / तुकडी कार्यक्षेत्र</h5>
                    <div class="text-muted" style="font-size:0.8rem;">
                        <?= sanitize($school['name_mr'] ?: ($school['name'] ?: 'शाळा')) ?> · सन <?= sanitize($year) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/modules/classes/index.php" class="btn btn-sm btn-outline-primary fw-semibold">
                <i class="bi bi-gear-wide-connected me-1"></i> तुकडी व माध्यम सेटअप
            </a>
            <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-sm btn-outline-secondary">
                ← मागे
            </a>
        </div>
    </div>

    <!-- Active Class List -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-grid-fill text-primary me-2"></i> उपलब्ध वर्ग व तुकड्या
            </h6>
            <span class="badge bg-light text-secondary border">एकूण: <?= to_mr_digits(count($standards)) ?> इयत्ता</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 25%;">इयत्ता</th>
                            <th style="width: 20%;">विद्यार्थी संख्या</th>
                            <th style="width: 25%;">विषय संख्या</th>
                            <th class="text-end pe-3" style="width: 30%;">थेट कार्य</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($standards as $st_id => $st_lbl): 
                            $students = cce_students($school_id, $st_id);
                            $subjects = cce_subjects($school_id, $st_id);
                        ?>
                        <tr>
                            <td class="ps-3">
                                <span class="fw-bold text-dark">इयत्ता <?= sanitize($st_lbl) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="bi bi-people me-1 text-primary"></i><?= to_mr_digits(count($students)) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="bi bi-book me-1 text-success"></i><?= to_mr_digits(count($subjects)) ?> विषय
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $st_id ?>" class="btn btn-outline-primary" title="गुण नोंदणी">
                                        <i class="bi bi-pencil-square"></i> गुण
                                    </a>
                                    <a href="<?= APP_URL ?>/cce/remarks_entry.php?std=<?= $st_id ?>" class="btn btn-outline-secondary" title="वर्णनात्मक नोंदी">
                                        <i class="bi bi-chat-left-text"></i> शेरे
                                    </a>
                                    <a href="<?= APP_URL ?>/cce/nondvahi.php?std=<?= $st_id ?>" target="_blank" class="btn btn-outline-danger" title="सातत्यपूर्ण मूल्यमापन नोंदवही">
                                        <i class="bi bi-journal-bookmark"></i> नोंदवही
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Help Note -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border">
        <div class="d-flex align-items-center gap-3">
            <div style="width:36px; height:36px; border-radius:8px; background:#EFF6FF; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="bi bi-info-circle fs-5"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1 text-dark">तुकडी बदल किंवा वर्गशिक्षक नेमणूक?</h6>
                <p class="text-muted small mb-0">नवीन तुकडी जोडण्यासाठी किंवा वर्गशिक्षक व माध्यम बदलण्यासाठी <a href="<?= APP_URL ?>/modules/classes/index.php" class="fw-semibold text-primary">तुकडी व माध्यम सेटअप</a> वर जा.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
