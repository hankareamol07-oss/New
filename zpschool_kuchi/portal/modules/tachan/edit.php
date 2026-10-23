<?php
declare(strict_types=1);

/**
 * Smart School Hub — Edit Lesson Plan (टाचण संपादन)
 * Separate tenant SQLite storage with dual-sync to MySQL.
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$s = $pdo->prepare('SELECT * FROM lesson_plans WHERE id = ? AND school_id = ?');
$s->execute([$id, $sid]);
$row = $s->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('टाचण नोंद सापडली नाही.');
}

$classes = school_classes($sid);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && tachan_verify_csrf()) {
    $date = posted_date();
    $period = (int)($_POST['period'] ?? $row['period']);
    $cid = (int)($_POST['class_id'] ?? $row['class_id']);
    $subject = trim((string)($_POST['subject'] ?? ''));
    $topic = trim((string)($_POST['topic'] ?? ''));
    $objectives = trim((string)($_POST['objectives'] ?? ''));
    $activities = trim((string)($_POST['activities'] ?? ''));
    $eval_tool = trim((string)($_POST['eval_tool'] ?? ''));
    $materials = trim((string)($_POST['materials'] ?? ''));
    $learning_outcome = trim((string)($_POST['learning_outcome'] ?? ''));
    $homework = trim((string)($_POST['homework'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    $className = trim((string)($_POST['class_name'] ?? ($row['class_name'] ?? '')));

    // Update in tenant SQLite
    $p = $pdo->prepare('UPDATE lesson_plans SET date = ?, period = ?, class_id = ?, class_name = ?, subject = ?, topic = ?, objectives = ?, activities = ?, eval_tool = ?, materials = ?, learning_outcome = ?, homework = ?, notes = ? WHERE id = ? AND school_id = ?');
    $p->execute([
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
        $id,
        $sid,
    ]);

    // Dual-sync update to MySQL
    syncTenantRecordToMySQL('lesson_plans', [
        'id'               => $id,
        'school_id'        => $sid,
        'teacher_id'       => (int)($row['teacher_id'] ?: $user['id']),
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
        'src'              => (string)($row['src'] ?? 'manual'),
    ], ['id']);

    flash_set('success', 'टाचण यशस्वीरीत्या अद्ययावत झाले.');
    header('Location: ' . APP_URL . '/modules/tachan/day.php?date=' . $date . '&class_id=' . $cid);
    exit;
}

tachan_layout_start('टाचण संपादन', 'day');
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
                <div>
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-pencil-fill text-warning me-2"></i> दैनिक पाठ टाचण संपादन
                    </h5>
                    <div class="small text-white-50 mt-1">
                        <?= e(date('d-m-Y', strtotime((string)$row['date']))) ?> · तासिका <?= (int)$row['period'] ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= APP_URL ?>/modules/tachan/print.php?id=<?= $id ?>" target="_blank" class="btn btn-warning btn-sm rounded-pill text-dark fw-bold">
                        <i class="bi bi-printer me-1"></i> प्रिंट
                    </a>
                    <a href="<?= APP_URL ?>/modules/tachan/day.php?date=<?= $row['date'] ?>&class_id=<?= $row['class_id'] ?>" class="btn btn-outline-light btn-sm rounded-pill">
                        ← परत
                    </a>
                </div>
            </div>

            <form method="post" class="p-3 p-md-4">
                <?= tachan_csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">

                <!-- Top Row: Date, Period, Class -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-dark small mb-1">दिनांक</label>
                        <input type="date" name="date" value="<?= e($row['date']) ?>" required class="form-control rounded-3">
                    </div>

                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-dark small mb-1">तासिका क्रमांक</label>
                        <select name="period" class="form-select rounded-3">
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <option value="<?= $i ?>" <?= (int)$row['period'] === $i ? 'selected' : '' ?>>तासिका <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-dark small mb-1">वर्ग (शाळा)</label>
                        <select name="class_id" class="form-select rounded-3">
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (int)$row['class_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-md-12">
                        <label class="form-label fw-bold text-dark small mb-1">वर्ग / जोडवर्ग नाव (उदा. इ. ६ वी, जोडवर्ग ६+७)</label>
                        <input type="text" name="class_name" value="<?= e($row['class_name'] ?? '') ?>" placeholder="उदा. इ. ६ वी किंवा जोडवर्ग ६+७" class="form-control rounded-3">
                    </div>
                </div>

                <!-- Subject & Topic -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-dark small mb-1">विषय</label>
                        <input name="subject" value="<?= e($row['subject']) ?>" required class="form-control rounded-3">
                    </div>

                    <div class="col-12 col-md-8">
                        <label class="form-label fw-bold text-dark small mb-1">घटक / पाठाचे नाव <span class="text-danger">*</span></label>
                        <input name="topic" value="<?= e($row['topic']) ?>" required class="form-control rounded-3">
                    </div>
                </div>

                <!-- 5 Columns Textareas -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">अध्ययन-अध्यापन कृती</label>
                        <textarea name="activities" rows="3" class="form-control rounded-3"><?= e($row['activities']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">मूल्यमापन साधने व तंत्रे</label>
                        <textarea name="eval_tool" rows="3" class="form-control rounded-3"><?= e($row['eval_tool']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">आवश्यक शैक्षणिक साहित्य (TLM)</label>
                        <textarea name="materials" rows="2" class="form-control rounded-3"><?= e($row['materials']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">अध्ययन निष्पत्ती</label>
                        <textarea name="learning_outcome" rows="2" class="form-control rounded-3"><?= e($row['learning_outcome']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">गृहपाठ</label>
                        <textarea name="homework" rows="2" class="form-control rounded-3"><?= e($row['homework']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark small mb-1">शेरा / उद्दिष्टे</label>
                        <textarea name="objectives" rows="2" class="form-control rounded-3"><?= e($row['objectives']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="<?= APP_URL ?>/modules/tachan/day.php?date=<?= $row['date'] ?>&class_id=<?= $row['class_id'] ?>" class="btn btn-outline-secondary px-4 rounded-pill">
                        रद्द करा
                    </a>
                    <button type="submit" class="btn btn-success px-5 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-check-circle-fill me-2"></i> बदल जतन करा
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php tachan_layout_end(); ?>
