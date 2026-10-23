<?php
/**
 * सातत्यपूर्ण मूल्यमापन शैक्षणिक वर्ष व शाळा सेटिंग्ज (Academic Year & School Settings)
 * मुख्याध्यापक नाव (सत्रनिहाय), निकाल तारीख, पुढील वर्ष सूचना व शाळेचे कामाचे दिवस
 */
$page_title = 'सातत्यपूर्ण मूल्यमापन शैक्षणिक वर्ष सेटिंग्ज';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

// Fetch current school_defaults
$def_stmt = $db->prepare("SELECT * FROM school_defaults WHERE school_id = ?");
$def_stmt->execute([$school_id]);
$defaults = $def_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch working days
$months = cce_months();
$working_days_map = json_decode((string)($school['working_days_monthly'] ?? ''), true) ?: [];
$total_working_days = array_sum(array_map('intval', $working_days_map));

// Handle POST save
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    cce_check_csrf();
    
    $sem1_hm = trim($_POST['sem1_headmaster_name'] ?? '');
    $sem2_hm = trim($_POST['sem2_headmaster_name'] ?? '');
    $res_date = trim($_POST['result_date'] ?? '') ?: null;
    $notice = trim($_POST['next_year_notice'] ?? '');

    // Save school_defaults
    if ($defaults) {
        $upd = $db->prepare("UPDATE school_defaults SET sem1_headmaster_name = ?, sem2_headmaster_name = ?, result_date = ?, next_year_notice = ?, updated_at = NOW() WHERE school_id = ?");
        $upd->execute([$sem1_hm, $sem2_hm, $res_date, $notice, $school_id]);
    } else {
        $ins = $db->prepare("INSERT INTO school_defaults (school_id, sem1_headmaster_name, sem2_headmaster_name, result_date, next_year_notice, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->execute([$school_id, $sem1_hm, $sem2_hm, $res_date, $notice]);
    }

    // Save working days
    $wd = [];
    foreach ($months as $mn => $lbl) {
        $wd[$mn] = max(0, (int)($_POST['working'][$mn] ?? 0));
    }
    $wd_json = json_encode($wd, JSON_UNESCAPED_UNICODE);
    $upd_sch = $db->prepare("UPDATE schools SET working_days_monthly = ? WHERE id = ?");
    $upd_sch->execute([$wd_json, $school_id]);

    flash('success', 'शैक्षणिक वर्ष व स्वाक्षरी सेटिंग्ज यशस्वीरित्या जतन केल्या!');
    redirect(APP_URL . '/cce/settings.php');
}

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
                    <i class="bi bi-calendar-check fs-6"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="font-size:1.05rem;">शैक्षणिक वर्ष व स्वाक्षरी सेटिंग्ज</h5>
                    <div class="text-muted" style="font-size:0.8rem;">
                        <?= sanitize($school['name_mr'] ?: ($school['name'] ?: 'शाळा')) ?> · सन <?= sanitize($year) ?>
                    </div>
                </div>
            </div>
        </div>
        <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-sm btn-outline-secondary">
            ← मागे
        </a>
    </div>

    <form method="POST">
        <?= cce_csrf_field() ?>

        <!-- Card 1: Headmaster Settings -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-person-badge text-primary me-2"></i> मुख्याध्यापक स्वाक्षरी सेटिंग्ज (सत्रनिहाय)
                </h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">प्रगती पुस्तक, निकालपत्रके आणि सातत्यपूर्ण मूल्यमापन नोंदवहीवर छपाईसाठी खालील मुख्याध्यापकांचे नाव वापरले जाईल.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">मुख्याध्यापक नाव (प्रथम सत्र):</label>
                        <input type="text" class="form-control" name="sem1_headmaster_name" value="<?= sanitize($defaults['sem1_headmaster_name'] ?? '') ?>" placeholder="उदा. श्री. अ. ब. पाटील">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">मुख्याध्यापक नाव (द्वितीय सत्र):</label>
                        <input type="text" class="form-control" name="sem2_headmaster_name" value="<?= sanitize($defaults['sem2_headmaster_name'] ?? '') ?>" placeholder="उदा. श्री. अ. ब. पाटील">
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Notice & Result Date -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-info-circle text-info me-2"></i> निकाल व पुढील सत्र सूचना
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">वार्षिक निकाल जाहीर तारीख:</label>
                        <input type="date" class="form-control" name="result_date" value="<?= sanitize($defaults['result_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">पुढील शैक्षणिक वर्ष सुरू होण्याची सूचना / सुट्टी संपण्याची तारीख:</label>
                        <input type="text" class="form-control" name="next_year_notice" value="<?= sanitize($defaults['next_year_notice'] ?? 'नवीन शैक्षणिक वर्ष दिनांक १५ जून पासून सुरू होईल.') ?>" placeholder="उदा. नवीन शैक्षणिक वर्ष दिनांक १५ जून पासून सुरू होईल.">
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: School Working Days -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar3 text-warning me-2"></i> शाळेचे मासिक कामाचे दिवस (सर्व विद्यार्थ्यांसाठी एकच नोंद)
                </h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillAllWorkingDays(24)">२४ दिवस</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillAllWorkingDays(22)">२२ दिवस</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillAllWorkingDays(25)">२५ दिवस</button>
                </div>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">प्रत्येक विद्यार्थ्याचे कामाचे दिवस स्वतंत्र भरण्याची गरज नाही. शाळेचे मासिक कामाचे दिवस येथे एकदाच निश्चित करा.</p>
                <div class="row g-3">
                    <?php foreach ($months as $mn => $lbl): ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1"><?= $lbl ?>:</label>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control text-center wd-input" min="0" max="31" name="working[<?= $mn ?>]" value="<?= (int)($working_days_map[$mn] ?? 0) ?>" oninput="recalcWorkingTotal()">
                            <span class="input-group-text">दिवस</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-light border mt-3 mb-0 py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-secondary">वार्षिक एकूण कामाचे दिवस:</span>
                    <b class="fs-5 text-primary" id="workingTotalDisplay"><?= to_mr_digits($total_working_days) ?></b>
                </div>
            </div>
        </div>

        <div class="text-end">
            <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-light me-2">रद्द करा</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold" style="background:#7C3AED; border-color:#7C3AED;">
                <i class="bi bi-check-circle me-1"></i> बदल जतन करा
            </button>
        </div>
    </form>
</div>

<script>
function fillAllWorkingDays(days) {
    document.querySelectorAll('.wd-input').forEach(function(i) { i.value = days; });
    recalcWorkingTotal();
}
function recalcWorkingTotal() {
    var t = 0;
    document.querySelectorAll('.wd-input').forEach(function(i) {
        var v = parseInt(i.value, 10);
        if (!isNaN(v) && v > 0) t += v;
    });
    document.getElementById('workingTotalDisplay').textContent = t;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
