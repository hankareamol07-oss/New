<?php
declare(strict_types=1);

/**
 * Smart School Hub — Add Manual Lesson Plan (नवीन दैनिक टाचण नोंद)
 * Separate tenant SQLite storage with dual-sync to MySQL.
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../data/planner_data.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db();
$classes = school_classes($sid);

if (!$classes) {
    flash_set('error', 'आधी वर्ग व्यवस्थापनातून वर्ग तयार करा.');
    header('Location: ' . APP_URL . '/modules/tachan/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && tachan_verify_csrf()) {
    $date = posted_date();
    $period = (int)($_POST['period'] ?? 1);
    $cid = (int)($_POST['class_id'] ?? 0);
    $subject = trim((string)($_POST['subject'] ?? ''));
    $topic = trim((string)($_POST['topic'] ?? ''));
    $objectives = trim((string)($_POST['objectives'] ?? ''));
    $activities = trim((string)($_POST['activities'] ?? ''));
    $eval_tool = trim((string)($_POST['eval_tool'] ?? ''));
    $materials = trim((string)($_POST['materials'] ?? ''));
    $learning_outcome = trim((string)($_POST['learning_outcome'] ?? ''));
    $homework = trim((string)($_POST['homework'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    $className = trim((string)($_POST['class_name'] ?? ''));
    if ($className === '' && isset($byId[$cid])) {
        $className = $byId[$cid]['name'];
    }

    // Insert into tenant SQLite
    $p = $pdo->prepare('INSERT INTO lesson_plans(school_id, teacher_id, date, period, class_id, class_name, subject, topic, objectives, activities, eval_tool, materials, learning_outcome, homework, notes, src) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "manual")');
    $p->execute([
        $sid,
        (int)$user['id'],
        $date,
        $period,
        $cid,
        $className,
        $subject,
        $topic,
        $objectives,
        $activities,
        $eval_tool,
        $materials,
        $learning_outcome,
        $homework,
        $notes,
    ]);
    $lastId = (int)$pdo->lastInsertId();

    // Dual-sync to MySQL
    syncTenantRecordToMySQL('lesson_plans', [
        'id'               => $lastId,
        'school_id'        => $sid,
        'teacher_id'       => (int)$user['id'],
        'date'             => $date,
        'period'           => $period,
        'class_id'         => $cid,
        'class_name'       => $className,
        'subject'          => $subject,
        'topic'            => $topic,
        'objectives'       => $objectives,
        'activities'       => $activities,
        'eval_tool'        => $eval_tool,
        'materials'        => $materials,
        'learning_outcome' => $learning_outcome,
        'homework'         => $homework,
        'notes'            => $notes,
        'src'              => 'manual',
    ], ['id']);

    flash_set('success', 'दैनिक टाचण यशस्वीरीत्या जतन झाले.');
    header('Location: ' . APP_URL . '/modules/tachan/day.php?date=' . $date . '&class_id=' . $cid);
    exit;
}

$tpl = planner_tachan_templates();
$topics = planner_annual_topics();
$months = planner_months();
$curMonth = (int)date('n');

tachan_layout_start('नवीन दैनिक टाचण', 'day');
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
                <div>
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-pencil-square text-warning me-2"></i> नवीन दैनिक टाचण नोंदवा
                    </h5>
                    <div class="small text-white-50 mt-1">स्वतःचे हस्तलिखित / सानुकूल पाठ टाचण तयार करा</div>
                </div>
                <a href="<?= APP_URL ?>/modules/tachan/day.php" class="btn btn-outline-light btn-sm rounded-pill">
                    ← दैनिक टाचणावर परत
                </a>
            </div>

            <form method="post" class="p-3 p-md-4">
                <?= tachan_csrf_field() ?>

                <!-- Basic Details Row -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-dark small mb-1">दिनांक</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required class="form-control rounded-3">
                    </div>

                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-dark small mb-1">तासिका क्रमांक</label>
                        <select name="period" class="form-select rounded-3">
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <option value="<?= $i ?>">तासिका <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-dark small mb-1">वर्ग</label>
                        <select name="class_id" class="form-select rounded-3">
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-md-12">
                        <label class="form-label fw-bold text-dark small mb-1">वर्ग / जोडवर्ग नाव (उदा. इ. ६ वी, जोडवर्ग ६+७)</label>
                        <input type="text" name="class_name" value="<?= e($_GET['class_name'] ?? '') ?>" placeholder="उदा. इ. ६ वी किंवा जोडवर्ग ६+७" class="form-control rounded-3">
                    </div>
                </div>

                <!-- Subject & Topic Row -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-bold text-dark small mb-1">विषय</label>
                        <select id="subj" name="subject" class="form-select rounded-3">
                            <?php foreach ($tpl as $s => $_x): ?>
                                <option value="<?= e($s) ?>"><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-7">
                        <label class="form-label fw-bold text-dark small mb-1">घटक / पाठाचे नाव <span class="text-danger">*</span></label>
                        <input required id="topic" name="topic" placeholder="उदा. आमचे गाव, बेरीज सराव..." class="form-control rounded-3">
                    </div>
                </div>

                <div class="mb-3 d-flex justify-content-end">
                    <button type="button" onclick="fillTpl()" class="btn btn-outline-primary btn-sm rounded-pill">
                        <i class="bi bi-magic me-1"></i> साचा आपोआप भरा (<?= e($months[$curMonth] ?? '') ?>)
                    </button>
                </div>

                <!-- Detailed 5-Column Text Areas -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">अध्ययन-अध्यापन कृती</label>
                        <textarea id="f_activities" name="activities" rows="3" class="form-control rounded-3" placeholder="उदा. चित्राचे निरीक्षण करून चर्चा घडवून आणली..."></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">मूल्यमापन साधने व तंत्रे</label>
                        <textarea id="f_eval_tool" name="eval_tool" rows="3" class="form-control rounded-3" placeholder="उदा. तोंडी प्रश्न, निरीक्षण, स्वाध्याय..."></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">आवश्यक शैक्षणिक साहित्य (TLM)</label>
                        <textarea id="f_materials" name="materials" rows="2" class="form-control rounded-3" placeholder="उदा. पाठ्यपुस्तक, चित्रपट्टी, तक्ता..."></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">अध्ययन निष्पत्ती</label>
                        <textarea id="f_learning_outcome" name="learning_outcome" rows="2" class="form-control rounded-3" placeholder="उदा. विद्यार्थी चित्रातील प्रसंग स्वतःच्या शब्दांत सांगतात..."></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">गृहपाठ</label>
                        <textarea id="f_homework" name="homework" rows="2" class="form-control rounded-3" placeholder="उदा. ५ नवीन शब्दांचे वाक्य तयार करा..."></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">शेरा / उद्दिष्टे</label>
                        <textarea id="f_objectives" name="objectives" rows="2" class="form-control rounded-3" placeholder="उदा. उपचारात्मक अध्यापनाची गरज आहे..."></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="<?= APP_URL ?>/modules/tachan/day.php" class="btn btn-outline-secondary px-4 rounded-pill">
                        रद्द करा
                    </a>
                    <button type="submit" class="btn btn-success px-5 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-check-circle-fill me-2"></i> टाचण जतन करा
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const TPL = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>;
const TOPICS = <?= json_encode($topics, JSON_UNESCAPED_UNICODE) ?>;
const M = <?= $curMonth ?>;

function fillTpl() {
    const s = document.getElementById('subj').value;
    const t = TPL[s] || {};
    document.getElementById('f_objectives').value = t.objectives || '';
    document.getElementById('f_activities').value = t.activities || '';
    document.getElementById('f_eval_tool').value = t.eval_tool || 'तोंडी प्रश्न व स्वाध्याय';
    document.getElementById('f_materials').value = t.materials || 'पाठ्यपुस्तक, फलक, शैक्षणिक तक्ते';
    document.getElementById('f_homework').value = t.homework || '';
    if (!document.getElementById('topic').value) {
        document.getElementById('topic').value = ((TOPICS[s] || {})[M] || '').split(',')[0] || '';
    }
}
</script>

<?php tachan_layout_end(); ?>
