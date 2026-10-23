<?php
declare(strict_types=1);

/**
 * मुख्याध्यापक / Admin: शिक्षक माहिती व्यवस्थापन व संपादन (Teacher Details Management)
 * School: जि. प. शाळा नं. २ कुची
 */

$page_title = 'शिक्षक माहिती व्यवस्थापन';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/cce_functions.php';

requireLogin();

// Ensure only Admin / Headmaster can access
if (!isAdmin()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$db = getDB();
$school_id = (int)($_SESSION['school_id'] ?? SCHOOL_ID);
$msg = '';
$err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_teacher') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $name_mr = trim($_POST['name_mr'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'teacher');
        $std_assigned = (int)($_POST['std_assigned'] ?? 0);
        $section = trim($_POST['section'] ?? 'A') ?: 'A';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $new_password = trim($_POST['new_password'] ?? '');

        if ($teacher_id <= 0 || empty($name) || empty($phone)) {
            $err = 'कृपया शिक्षकांचे नाव आणि मोबाईल क्रमांक अचूक भरा.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Format class_assigned string
                $class_assigned = $std_assigned > 0 ? "इयत्ता {$std_assigned} वी" : 'लागू नाही';
                if ($role === 'admin' || $role === 'principal' || $role === 'headmaster') {
                    $class_assigned = 'मुख्याध्यापक';
                }

                // 2. Update teachers table
                $updTeacher = $db->prepare("
                    UPDATE teachers 
                    SET name = ?, name_mr = ?, phone = ?, email = ?, role = ?, class_assigned = ?, section = ?, is_active = ?
                    WHERE id = ? AND school_id = ?
                ");
                $updTeacher->execute([$name, $name_mr, $phone, $email, $role, $class_assigned, $section, $is_active, $teacher_id, $school_id]);

                // 3. Update users table login
                if (!empty($new_password)) {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $updUser = $db->prepare("
                        UPDATE users 
                        SET name = ?, phone = ?, mobile = ?, email = ?, role = ?, is_active = ?, password_hash = ?
                        WHERE linked_id = ? AND school_id = ? AND role != 'student'
                    ");
                    $updUser->execute([$name_mr ?: $name, $phone, $phone, $email, $role, $is_active, $hash, $teacher_id, $school_id]);
                } else {
                    $updUser = $db->prepare("
                        UPDATE users 
                        SET name = ?, phone = ?, mobile = ?, email = ?, role = ?, is_active = ?
                        WHERE linked_id = ? AND school_id = ? AND role != 'student'
                    ");
                    $updUser->execute([$name_mr ?: $name, $phone, $phone, $email, $role, $is_active, $teacher_id, $school_id]);
                }

                // 4. Update class teacher mapping
                if ($std_assigned > 0) {
                    // Update classes table
                    $updClass = $db->prepare("UPDATE classes SET class_teacher_id = ? WHERE school_id = ? AND std = ?");
                    $updClass->execute([$teacher_id, $school_id, $std_assigned]);

                    // Upsert teacher_classes
                    $updTC = $db->prepare("
                        INSERT INTO teacher_classes (school_id, teacher_id, class_id, std, division, academic_year, is_class_teacher)
                        VALUES (?, ?, (SELECT id FROM classes WHERE school_id = ? AND std = ? LIMIT 1), ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE std = VALUES(std), division = VALUES(division), is_class_teacher = 1
                    ");
                    $updTC->execute([$school_id, $teacher_id, $school_id, $std_assigned, $std_assigned, $section, ACADEMIC_YEAR]);
                }

                $db->commit();
                $msg = "शिक्षक '{$name_mr}' यांची माहिती यशस्वीरीत्या अपडेट केली!";
            } catch (Throwable $e) {
                $db->rollBack();
                $err = 'अपडेट करताना त्रुटी: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'add_teacher') {
        $name = trim($_POST['name'] ?? '');
        $name_mr = trim($_POST['name_mr'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'teacher');
        $std_assigned = (int)($_POST['std_assigned'] ?? 0);
        $section = trim($_POST['section'] ?? 'A') ?: 'A';
        $password = trim($_POST['password'] ?? 'Teacher@Kuchi2026');

        if (empty($name) || empty($phone)) {
            $err = 'शिक्षकांचे नाव आणि मोबाईल क्रमांक आवश्यक आहे.';
        } else {
            try {
                $db->beginTransaction();

                $teacher_code = 'TP' . rand(10000000, 99999999);
                $class_assigned = $std_assigned > 0 ? "इयत्ता {$std_assigned} वी" : 'लागू नाही';
                if ($role === 'admin' || $role === 'headmaster') {
                    $class_assigned = 'मुख्याध्यापक';
                }

                $insT = $db->prepare("
                    INSERT INTO teachers (school_id, teacher_code, name, name_mr, phone, email, role, class_assigned, section, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $insT->execute([$school_id, $teacher_code, $name, $name_mr, $phone, $email, $role, $class_assigned, $section]);
                $newTid = (int)$db->lastInsertId();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insU = $db->prepare("
                    INSERT INTO users (school_id, name, phone, mobile, email, password_hash, linked_id, role, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $insU->execute([$school_id, $name_mr ?: $name, $phone, $phone, $email, $hash, $newTid, $role]);

                if ($std_assigned > 0) {
                    $db->prepare("UPDATE classes SET class_teacher_id = ? WHERE school_id = ? AND std = ?")
                       ->execute([$newTid, $school_id, $std_assigned]);
                }

                $db->commit();
                $msg = "नवीन शिक्षक '{$name_mr}' यशस्वीरीत्या जोडले!";
            } catch (Throwable $e) {
                $db->rollBack();
                $err = 'नवीन शिक्षक जोडताना त्रुटी: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_teacher') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $current_user_linked_id = (int)($_SESSION['user']['linked_id'] ?? 0);

        if ($teacher_id <= 0) {
            $err = 'अवैध शिक्षक आयडी.';
        } elseif ($teacher_id === $current_user_linked_id) {
            $err = 'तुम्ही स्वतःचे चालू मुख्याध्यापक खाते थेट हटवू शकत नाही.';
        } else {
            try {
                $db->beginTransaction();

                // Get teacher name for log
                $tn = $db->prepare("SELECT name_mr, name FROM teachers WHERE id = ? AND school_id = ?");
                $tn->execute([$teacher_id, $school_id]);
                $tRow = $tn->fetch();
                $teacherName = $tRow ? ($tRow['name_mr'] ?: $tRow['name']) : "ID #$teacher_id";

                // 1. Unassign from classes
                $db->prepare("UPDATE classes SET class_teacher_id = NULL WHERE class_teacher_id = ? AND school_id = ?")
                   ->execute([$teacher_id, $school_id]);

                // 2. Remove teacher_classes
                $db->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND school_id = ?")
                   ->execute([$teacher_id, $school_id]);

                // 3. Remove CCE assignments
                $db->prepare("DELETE FROM cce_subject_teachers WHERE teacher_id = ? AND school_id = ?")
                   ->execute([$teacher_id, $school_id]);

                // 4. Delete user login
                $db->prepare("DELETE FROM users WHERE linked_id = ? AND school_id = ? AND role != 'student'")
                   ->execute([$teacher_id, $school_id]);

                // 5. Delete teacher record
                $db->prepare("DELETE FROM teachers WHERE id = ? AND school_id = ?")
                   ->execute([$teacher_id, $school_id]);

                $db->commit();
                $msg = "शिक्षक '{$teacherName}' यांचे खाते यशस्वीरीत्या हटवले आहे.";
            } catch (Throwable $e) {
                $db->rollBack();
                $err = 'शिक्षक हटवताना त्रुटी: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all teachers of this school
$teachers = $db->query("
    SELECT t.*, 
           tc.std as assigned_std,
           (SELECT COUNT(*) FROM students s WHERE s.class_id IN (SELECT id FROM classes c WHERE c.class_teacher_id = t.id) AND s.is_active = 1) as student_count
    FROM teachers t
    LEFT JOIN teacher_classes tc ON t.id = tc.teacher_id AND tc.is_class_teacher = 1
    WHERE t.school_id = {$school_id}
    ORDER BY t.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-3">
    <!-- Breadcrumb & Title -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/index.php">मुख्यपृष्ठ</a></li>
                    <li class="breadcrumb-item active">शिक्षक व्यवस्थापन</li>
                </ol>
            </nav>
            <h2 class="h4 fw-bold mb-0 text-primary">
                <i class="bi bi-people-fill me-2"></i>शिक्षक माहिती संपादन व व्यवस्थापन
            </h2>
            <small class="text-muted">शाळेतील सर्व शिक्षकांची माहिती, संपर्क, वर्ग वाटप व पासवर्ड व्यवस्थापन</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/cce/teachers.php" class="btn btn-outline-primary">
                <i class="bi bi-person-badge me-1"></i>विषय शिक्षक वाटप (CCE)
            </a>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="bi bi-person-plus-fill me-1"></i>नवीन शिक्षक जोडा
            </button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Teachers Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                शाळा शिक्षक यादी <span class="badge bg-primary rounded-pill ms-2"><?= count($teachers) ?> शिक्षक</span>
            </h5>
            <span class="text-muted small">एकूण ७ वर्ग (इ. १ ली ते ७ वी)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">शिक्षक</th>
                        <th>मोबाईल (लॉगिन ID)</th>
                        <th>पद / भूमिका</th>
                        <th>वर्गशिक्षक वाटप</th>
                        <th>विद्यार्थी</th>
                        <th>स्थिती</th>
                        <th class="text-end pe-4">कृती (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $t): 
                        $stdNum = 0;
                        if (preg_match('/(\d+)/', $t['class_assigned'] ?? '', $m)) {
                            $stdNum = (int)$m[1];
                        } elseif (!empty($t['assigned_std'])) {
                            $stdNum = (int)$t['assigned_std'];
                        }
                    ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 44px; height: 44px;">
                                        <?= mb_substr($t['name_mr'] ?: $t['name'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($t['name_mr'] ?: $t['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($t['name']) ?> · <code class="text-secondary"><?= htmlspecialchars($t['teacher_code'] ?? '') ?></code></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold"><i class="bi bi-phone me-1 text-muted"></i><?= htmlspecialchars($t['phone']) ?></span>
                            </td>
                            <td>
                                <?php if ($t['role'] === 'admin' || $t['role'] === 'headmaster' || $t['role'] === 'principal'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">मुख्याध्यापक (Headmaster)</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">पदवीधर / उपशिक्षक</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($stdNum > 0): ?>
                                    <span class="badge bg-primary px-3 py-1 rounded-pill">
                                        इयत्ता <?= $stdNum ?> वी (तुकडी <?= htmlspecialchars($t['section'] ?: 'A') ?>)
                                    </span>
                                <?php elseif (stripos($t['class_assigned'] ?? '', 'मुख्या') !== false): ?>
                                    <span class="badge bg-secondary px-2 py-1">शाळा प्रशासन (सर्व वर्ग)</span>
                                <?php else: ?>
                                    <span class="text-muted small">वर्ग नेमणूक नाही</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($stdNum > 0): ?>
                                    <span class="badge bg-light text-dark border"><?= (int)$t['student_count'] ?> विद्यार्थी</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">सक्रिय (Active)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">बंद (Inactive)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" 
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                        'id' => $t['id'],
                                        'name' => $t['name'],
                                        'name_mr' => $t['name_mr'],
                                        'phone' => $t['phone'],
                                        'email' => $t['email'] ?? '',
                                        'role' => $t['role'],
                                        'std_assigned' => $stdNum,
                                        'section' => $t['section'] ?: 'A',
                                        'is_active' => (int)$t['is_active']
                                    ], JSON_UNESCAPED_UNICODE)) ?>)">
                                    <i class="bi bi-pencil-square me-1"></i>माहिती बदला
                                </button>
                                <?php if ((int)$t['id'] !== (int)($_SESSION['user']['linked_id'] ?? 0)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger fw-bold ms-1"
                                        onclick="confirmDeleteTeacher(<?= (int)$t['id'] ?>, '<?= htmlspecialchars(addslashes($t['name_mr'] ?: $t['name'])) ?>')">
                                        <i class="bi bi-trash3 me-1"></i>हटवा
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= EDIT TEACHER MODAL ================= -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content shadow">
            <input type="hidden" name="action" value="update_teacher">
            <input type="hidden" name="teacher_id" id="edit_teacher_id">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square me-2"></i>शिक्षक माहिती संपादन (Edit Teacher Details)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">शिक्षकांचे नाव (इंग्रजी) *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                        <small class="text-muted">उदा. AMOL KISAN HANKARE</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">शिक्षकांचे नाव (मराठीत) *</label>
                        <input type="text" name="name_mr" id="edit_name_mr" class="form-control" required>
                        <small class="text-muted">उदा. अमोल किसन हंकारे</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">मोबाईल क्रमांक (लॉगिन ID) *</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" maxlength="15" required>
                        <small class="text-muted">हा क्रमांक शिक्षक लॉगिनसाठी वापरला जातो.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ईमेल पत्ता (ऐच्छिक)</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">पद / भूमिका (Role)</label>
                        <select name="role" id="edit_role" class="form-select">
                            <option value="teacher">शिक्षक (Teacher)</option>
                            <option value="admin">मुख्याध्यापक / ॲडमिन (Headmaster / Admin)</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">वर्गशिक्षक पद (Assigned Class)</label>
                        <select name="std_assigned" id="edit_std_assigned" class="form-select">
                            <option value="0">कोणताही वर्ग नाही / लागू नाही</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>">इयत्ता <?= $i ?> वी (Class <?= $i ?>)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">तुकडी (Division)</label>
                        <input type="text" name="section" id="edit_section" class="form-control" value="A">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-bold text-danger">पासवर्ड रीसेट करा (नवीन पासवर्ड)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="बदलायचा नसेल तर रिकामे ठेवा">
                        <small class="text-muted">शिक्षकांना नवीन पासवर्ड द्यायचा असल्यास येथे टाईप करा (उदा. Teacher@Kuchi2026)</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-center pt-4">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                            <label class="form-check-label fs-6 fw-bold" for="edit_is_active">खाते सक्रिय (Active)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">रद्द करा</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">
                    <i class="bi bi-save me-1"></i>माहिती जतन करा
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= ADD TEACHER MODAL ================= -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content shadow">
            <input type="hidden" name="action" value="add_teacher">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-plus-fill me-2"></i>नवीन शिक्षक जोडा (Add New Teacher)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">शिक्षकांचे नाव (इंग्रजी) *</label>
                        <input type="text" name="name" class="form-control" placeholder="उदा. SUNIL RAMESH PATIL" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">शिक्षकांचे नाव (मराठीत) *</label>
                        <input type="text" name="name_mr" class="form-control" placeholder="उदा. सुनील रमेश पाटील" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">मोबाईल क्रमांक (लॉगिन ID) *</label>
                        <input type="text" name="phone" class="form-control" maxlength="15" placeholder="१० अंकी मोबाईल नंबर" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ईमेल पत्ता (ऐच्छिक)</label>
                        <input type="email" name="email" class="form-control" placeholder="teacher@zpsangli.in">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">पद / भूमिका (Role)</label>
                        <select name="role" class="form-select">
                            <option value="teacher" selected>शिक्षक (Teacher)</option>
                            <option value="admin">मुख्याध्यापक (Headmaster)</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">वर्गशिक्षक पद (Assigned Class)</label>
                        <select name="std_assigned" class="form-select">
                            <option value="0">लागू नाही</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>">इयत्ता <?= $i ?> वी (Class <?= $i ?>)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">तुकडी (Division)</label>
                        <input type="text" name="section" class="form-control" value="A">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold">प्रारंभिक पासवर्ड (Default Password)</label>
                        <input type="text" name="password" class="form-control" value="Teacher@Kuchi2026">
                        <small class="text-muted">शिक्षकांना पहिला लॉगिन करण्यासाठी हा पासवर्ड द्यावा.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">रद्द करा</button>
                <button type="submit" class="btn btn-success fw-bold px-4">
                    <i class="bi bi-check-circle me-1"></i>शिक्षक नोंदणी पूर्ण करा
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= DELETE TEACHER MODAL ================= -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content shadow border-danger">
            <input type="hidden" name="action" value="delete_teacher">
            <input type="hidden" name="teacher_id" id="delete_teacher_id">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-trash3-fill me-2"></i>शिक्षक खाते हटवणे (Delete Teacher)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-1 d-block mb-3"></i>
                <h5 class="fw-bold mb-2">तुम्हाला नक्की हे खाते हटवायचे आहे का?</h5>
                <p class="text-muted mb-3">
                    शिक्षक: <strong class="text-dark" id="delete_teacher_name"></strong>
                </p>
                <div class="alert alert-warning py-2 small text-start">
                    <i class="bi bi-info-circle me-1"></i>या शिक्षकांचे लॉगिन खाते, वर्गशिक्षक पद आणि विषय नेमणूक तात्काळ काढून टाकली जाईल.
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-center">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">रद्द करा</button>
                <button type="submit" class="btn btn-danger fw-bold px-4">
                    <i class="bi bi-trash3 me-1"></i>होय, कायमचे हटवा
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(t) {
    document.getElementById('edit_teacher_id').value = t.id;
    document.getElementById('edit_name').value = t.name || '';
    document.getElementById('edit_name_mr').value = t.name_mr || '';
    document.getElementById('edit_phone').value = t.phone || '';
    document.getElementById('edit_email').value = t.email || '';
    document.getElementById('edit_role').value = t.role || 'teacher';
    document.getElementById('edit_std_assigned').value = t.std_assigned || 0;
    document.getElementById('edit_section').value = t.section || 'A';
    document.getElementById('edit_is_active').checked = (t.is_active === 1);

    const modal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
    modal.show();
}

function confirmDeleteTeacher(id, name) {
    document.getElementById('delete_teacher_id').value = id;
    document.getElementById('delete_teacher_name').textContent = name;
    const modal = new bootstrap.Modal(document.getElementById('deleteTeacherModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
