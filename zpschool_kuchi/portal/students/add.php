<?php
$page_title = 'विद्यार्थी जोडा (HPC & सातत्यपूर्ण मूल्यमापन)';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/demo_data.php';
requireLogin();

$school_id = (int)$_SESSION['school_id'];


$db = getDB();
$errors = [];

// Get teachers for dropdown
$stmt = $db->prepare("SELECT id, name, name_mr, teacher_code FROM teachers WHERE school_id = ? AND is_active = 1 ORDER BY name_mr, name");
$stmt->execute([$school_id]);
$teachers = $stmt->fetchAll();

// Pre-fill parameters if coming from CCE, HPC, or Class Hub
$pre_std = (int)($_GET['std'] ?? 0);
$pre_div = trim($_GET['division'] ?? '');
$default_grade = ($pre_std >= 1 && $pre_std <= 8) ? ('इयत्ता ' . $pre_std) : 'इयत्ता १';
$default_section = $pre_div !== '' ? $pre_div : 'अ';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'school_id' => $school_id,
        'name' => trim($_POST['name'] ?? ''),
        'name_mr' => trim($_POST['name_mr'] ?? ''),
        'teacher_id' => !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null,
        'apaar_id' => trim($_POST['apaar_id'] ?? ''),
        'udid' => trim($_POST['udid'] ?? ''),
        'roll_no' => trim($_POST['roll_no'] ?? ''),
        'gr_no' => !empty($_POST['gr_no']) ? (int)$_POST['gr_no'] : null,
        'registration_no' => trim($_POST['registration_no'] ?? ''),
        'student_pen' => trim($_POST['student_pen'] ?? ''),
        'saral_id' => trim($_POST['saral_id'] ?? ''),
        'grade' => $_POST['grade'] ?? $default_grade,
        'std' => !empty($_POST['std']) ? (int)$_POST['std'] : (int)preg_replace('/\D/', '', $_POST['grade'] ?? '1'),
        'section' => trim($_POST['section'] ?? $default_section),
        'date_of_birth' => (!empty($_POST['date_of_birth']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date_of_birth'])) ? $_POST['date_of_birth'] : null,
        'age' => !empty($_POST['age']) ? (int)$_POST['age'] : null,
        'gender' => $_POST['gender'] ?? 'मुलगा',
        'blood_group' => trim($_POST['blood_group'] ?? ''),
        'aadhar_no' => trim($_POST['aadhar_no'] ?? ''),
        
        // Physical Measurements (उंची व वजन सर्व सत्रे)
        'height_sem1' => !empty($_POST['height_sem1']) ? (float)$_POST['height_sem1'] : null,
        'weight_sem1' => !empty($_POST['weight_sem1']) ? (float)$_POST['weight_sem1'] : null,
        'height_sem2' => !empty($_POST['height_sem2']) ? (float)$_POST['height_sem2'] : null,
        'weight_sem2' => !empty($_POST['weight_sem2']) ? (float)$_POST['weight_sem2'] : null,

        // Social & Personal
        'religion' => trim($_POST['religion'] ?? ''),
        'caste' => trim($_POST['caste'] ?? ''),
        'caste_category' => trim($_POST['caste_category'] ?? 'open'),
        'minority' => trim($_POST['minority'] ?? 'नाही'),
        'handicap' => trim($_POST['handicap'] ?? 'नाही'),
        'bpl' => trim($_POST['bpl'] ?? 'नाही'),
        'birth_place' => trim($_POST['birth_place'] ?? ''),
        'birth_taluka' => trim($_POST['birth_taluka'] ?? ''),
        'birth_district' => trim($_POST['birth_district'] ?? ''),

        // Family & Contact
        'address_line1' => trim($_POST['address_line1'] ?? ''),
        'address_line2' => trim($_POST['address_line2'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'father_mobile' => trim($_POST['father_mobile'] ?? ''),
        'mother_mobile' => trim($_POST['mother_mobile'] ?? ''),
        'mother_name' => trim($_POST['mother_name'] ?? ''),
        'mother_education' => trim($_POST['mother_education'] ?? ''),
        'mother_occupation' => trim($_POST['mother_occupation'] ?? ''),
        'father_name' => trim($_POST['father_name'] ?? ''),
        'father_education' => trim($_POST['father_education'] ?? ''),
        'father_occupation' => trim($_POST['father_occupation'] ?? ''),
        'guardian_name' => trim($_POST['guardian_name'] ?? ''),
        'guardian_relation' => trim($_POST['guardian_relation'] ?? ''),
        'num_siblings' => !empty($_POST['num_siblings']) ? (int)$_POST['num_siblings'] : 0,
        'siblings_age' => trim($_POST['siblings_age'] ?? ''),
        'mother_tongue' => trim($_POST['mother_tongue'] ?? 'मराठी'),
        'medium_of_instruction' => trim($_POST['medium_of_instruction'] ?? 'मराठी'),
        'area_type' => $_POST['area_type'] ?? 'ग्रामीण',

        // Banking
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'bank_account' => trim($_POST['bank_account'] ?? ''),
        'ifsc' => trim($_POST['ifsc'] ?? ''),

        // HPC Favourites
        'favourite_color' => trim($_POST['favourite_color'] ?? ''),
        'favourite_food' => trim($_POST['favourite_food'] ?? ''),
        'favourite_flower' => trim($_POST['favourite_flower'] ?? ''),
        'favourite_sport' => trim($_POST['favourite_sport'] ?? ''),
        'favourite_animal' => trim($_POST['favourite_animal'] ?? ''),
        'favourite_subject' => trim($_POST['favourite_subject'] ?? ''),
        'aspiration' => trim($_POST['aspiration'] ?? ''),
        'best_friend1' => trim($_POST['best_friend1'] ?? ''),
        'best_friend2' => trim($_POST['best_friend2'] ?? ''),
        'best_friend3' => trim($_POST['best_friend3'] ?? ''),
    ];

    if (empty($data['name']) && empty($data['name_mr'])) {
        $errors[] = 'विद्यार्थ्याचे नाव आवश्यक आहे';
    }

    // Photo upload
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actual_type = $finfo->file($_FILES['photo']['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($actual_type, $allowed)) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($ext, $allowed_ext)) {
                $filename = 'student_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_dir = __DIR__ . '/../uploads/photos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                    $photo = 'uploads/photos/' . $filename;
                }
            }
        }
    }

    if (empty($errors)) {
        if (empty($data['student_pen']) && !empty($data['registration_no'])) {
            $data['student_pen'] = $data['registration_no'];
        } elseif (!empty($data['student_pen']) && empty($data['registration_no'])) {
            $data['registration_no'] = $data['student_pen'];
        }

        $sql = "INSERT INTO students (
            school_id, teacher_id, name, name_mr, apaar_id, udid, roll_no, gr_no, registration_no, student_pen, saral_id,
            grade, std, section, date_of_birth, dob, age, gender, photo, blood_group, aadhar_no, aadhaar,
            height_sem1, weight_sem1, height_sem2, weight_sem2,
            religion, caste, caste_category, minority, handicap, bpl,
            birth_place, birth_taluka, birth_district,
            address_line1, address_line2, address, phone, father_mobile, mother_mobile,
            mother_name, mother_education, mother_occupation,
            father_name, father_education, father_occupation,
            guardian_name, guardian_relation, num_siblings, siblings_age,
            mother_tongue, medium_of_instruction, area_type,
            bank_name, bank_account, ifsc,
            favourite_color, favourite_food, favourite_flower, favourite_sport, favourite_animal, favourite_subject,
            aspiration, best_friend1, best_friend2, best_friend3
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?
        )";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['school_id'], $data['teacher_id'], $data['name'], $data['name_mr'],
            $data['apaar_id'], $data['udid'], $data['roll_no'], $data['gr_no'], $data['registration_no'], $data['student_pen'], $data['saral_id'],
            $data['grade'], $data['std'], $data['section'], $data['date_of_birth'], $data['date_of_birth'], $data['age'], $data['gender'], $photo, $data['blood_group'], $data['aadhar_no'], $data['aadhar_no'],
            $data['height_sem1'], $data['weight_sem1'], $data['height_sem2'], $data['weight_sem2'],
            $data['religion'], $data['caste'], $data['caste_category'], $data['minority'], $data['handicap'], $data['bpl'],
            $data['birth_place'], $data['birth_taluka'], $data['birth_district'],
            $data['address_line1'], $data['address_line2'], $data['address_line1'] ?: $data['address_line2'], $data['phone'], $data['father_mobile'], $data['mother_mobile'],
            $data['mother_name'], $data['mother_education'], $data['mother_occupation'],
            $data['father_name'], $data['father_education'], $data['father_occupation'],
            $data['guardian_name'], $data['guardian_relation'], $data['num_siblings'], $data['siblings_age'],
            $data['mother_tongue'], $data['medium_of_instruction'], $data['area_type'],
            $data['bank_name'], $data['bank_account'], $data['ifsc'],
            $data['favourite_color'], $data['favourite_food'], $data['favourite_flower'], $data['favourite_sport'], $data['favourite_animal'], $data['favourite_subject'],
            $data['aspiration'], $data['best_friend1'], $data['best_friend2'], $data['best_friend3']
        ]);

        $student_id = (int)$db->lastInsertId();

        // Sync with cce_student_extra table
        try {
            $year = academic_year();
            $upsert_extra = $db->prepare("
                INSERT INTO cce_student_extra
                    (student_id, school_id, academic_year,
                     height_1, weight_1, height_2, weight_2,
                     cast_cat, religion, saral_id, pen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    height_1 = VALUES(height_1), weight_1 = VALUES(weight_1),
                    height_2 = VALUES(height_2), weight_2 = VALUES(weight_2),
                    cast_cat = VALUES(cast_cat), religion = VALUES(religion),
                    saral_id = VALUES(saral_id), pen = VALUES(pen)
            ");
            $upsert_extra->execute([
                $student_id, $school_id, $year,
                $data['height_sem1'] !== null ? (string)$data['height_sem1'] : '',
                $data['weight_sem1'] !== null ? (string)$data['weight_sem1'] : '',
                $data['height_sem2'] !== null ? (string)$data['height_sem2'] : '',
                $data['weight_sem2'] !== null ? (string)$data['weight_sem2'] : '',
                $data['caste_category'], $data['religion'], $data['saral_id'], $data['student_pen']
            ]);
        } catch (Exception $e) {}

        // Handle interests
        $interests = $_POST['interests'] ?? [];
        $other_interest = trim($_POST['other_interest'] ?? '');
        foreach ($interests as $interest) {
            $stmt = $db->prepare("INSERT INTO student_interests (student_id, interest) VALUES (?, ?)");
            $stmt->execute([$student_id, $interest]);
        }
        if (!empty($other_interest)) {
            $stmt = $db->prepare("INSERT INTO student_interests (student_id, interest, other_details) VALUES (?, 'इतर', ?)");
            $stmt->execute([$student_id, $other_interest]);
        }

        flash('success', 'विद्यार्थी यशस्वीरीत्या जोडला गेला!');
        
        $ret = trim($_POST['return_param'] ?? ($_GET['return'] ?? ''));
        $std_target = $data['std'] ?: 1;
        $div_target = $data['section'] ?: 'अ';
        if ($ret === 'hpc') {
            redirect(APP_URL . '/hpc/index.php?std=' . $std_target . '&division=' . urlencode($div_target));
        } elseif ($ret === 'cce') {
            redirect(APP_URL . '/cce/index.php?std=' . $std_target . '&division=' . urlencode($div_target));
        } elseif ($ret === 'classes') {
            redirect(APP_URL . '/modules/classes/index.php?tab=students&std=' . $std_target . '&division=' . urlencode($div_target));
        } elseif ($ret === 'marks_entry') {
            redirect(APP_URL . '/cce/marks_entry.php?std=' . $std_target . '&division=' . urlencode($div_target));
        } else {
            redirect(APP_URL . '/students/list.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.opt-tab-btn {
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    transition: all 0.2s ease;
    border-radius: 12px !important;
}
.opt-tab-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}
.opt-tab-btn.active {
    background: linear-gradient(135deg, #4338ca 0%, #1D9370 100%) !important;
    border-color: #4338ca !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25) !important;
}
.opt-tab-btn.active .opt-badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}
.opt-tab-extra.active {
    background: linear-gradient(135deg, #1D9370 0%, #38bdf8 100%) !important;
    border-color: #1D9370 !important;
    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25) !important;
}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-0 fw-bold d-flex align-items-center gap-2" style="font-size:1.45rem;">
            <i class="bi bi-person-plus-fill text-primary"></i> नवीन विद्यार्थी नोंदणी (HPC & सातत्यपूर्ण मूल्यमापन)
        </h2>
        <p class="text-muted small mb-0 mt-0.5">
            कामास सुरुवात करण्यासाठी प्रथम <strong>महत्त्वाची माहिती</strong> भरा. उर्वरित अतिरिक्त माहिती वेळेनुसार नंतरही अद्ययावत करू शकता.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= APP_URL ?>/modules/classes/import_udise.php" class="btn btn-sm btn-warning fw-bold text-dark shadow-xs">
            <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> UDISE+ आयात (Auto मराठी)
        </a>
        <a href="<?= APP_URL ?>/modules/classes/index.php?tab=students<?= $pre_std ? '&std=' . $pre_std : '' ?>" class="btn btn-sm btn-outline-primary fw-semibold">
            <i class="bi bi-mortarboard me-1"></i> वर्ग व्यवस्थापन हब
        </a>
        <a href="<?= APP_URL ?>/students/list.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> मागे
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-xs mb-3">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<!-- 2 OPTIONS SELECTOR HEADER (महत्त्वाची माहिती vs अतिरिक्त माहिती) -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0 !important;">
    <div class="card-body p-2 p-md-3">
        <ul class="nav nav-pills nav-fill gap-2" id="studentAddTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link opt-tab-btn active py-2.5 px-3 fw-bold d-flex flex-column flex-sm-row align-items-center justify-content-center gap-2" 
                        id="tab-imp-btn" data-bs-toggle="pill" data-bs-target="#tab-imp-pane" type="button" role="tab" aria-selected="true">
                    <div class="d-flex align-items-center gap-1.5">
                        <i class="bi bi-star-fill text-warning fs-5"></i>
                        <span>पर्याय १: महत्त्वाची माहिती</span>
                    </div>
                    <span class="badge opt-badge bg-primary-subtle text-primary border px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                        आवश्यक / कामाला सुरुवात करण्यासाठी
                    </span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link opt-tab-btn opt-tab-extra py-2.5 px-3 fw-bold d-flex flex-column flex-sm-row align-items-center justify-content-center gap-2" 
                        id="tab-extra-btn" data-bs-toggle="pill" data-bs-target="#tab-extra-pane" type="button" role="tab" aria-selected="false">
                    <div class="d-flex align-items-center gap-1.5">
                        <i class="bi bi-collection-fill text-info fs-5"></i>
                        <span>पर्याय २: अतिरिक्त माहिती</span>
                    </div>
                    <span class="badge opt-badge bg-secondary-subtle text-secondary border px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                        ऐच्छिक / सोयीनुसार नंतर भरा
                    </span>
                </button>
            </li>
        </ul>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" id="studentAddForm">
    <input type="hidden" name="return_param" value="<?= sanitize($_GET['return'] ?? '') ?>">

    <div class="tab-content" id="studentAddTabContent">
        
        <!-- ========================================================================= -->
        <!-- TAB 1: महत्त्वाची माहिती (IMPORTANT INFORMATION - START WORK FAST)         -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade show active" id="tab-imp-pane" role="tabpanel" tabindex="0">
            
            <div class="alert alert-info py-2 px-3 small d-flex align-items-center justify-content-between mb-3 shadow-xs rounded-3">
                <div>
                    <i class="bi bi-info-circle-fill me-1 text-primary"></i>
                    <strong>टीप:</strong> गुण नोंदणी (सातत्यपूर्ण मूल्यमापन) व प्राथमिक HPC कार्ड तयार करण्यासाठी केवळ ही महत्त्वाची माहिती पुरेशी आहे.
                </div>
                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-bold text-primary" onclick="goToExtraTab()">
                    अतिरिक्त माहिती पाहण्यासाठी येथे क्लिक करा &raquo;
                </button>
            </div>

            <!-- १. मूलभूत ओळख व शालेय नोंद -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-primary text-white fw-bold py-2.5 px-3 d-flex align-items-center justify-content-between" style="border-radius:12px 12px 0 0;">
                    <span><i class="bi bi-person-badge-fill me-1"></i> १. मूलभूत ओळख व वर्ग नोंद</span>
                    <span class="badge bg-white text-primary">आवश्यक</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">विद्यार्थ्याचे नाव (मराठी) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-semibold" name="name_mr" value="<?= sanitize($_POST['name_mr'] ?? '') ?>" required placeholder="उदा. पाटील आरव सचिन">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">विद्यार्थ्याचे नाव (इंग्रजी)</label>
                            <input type="text" class="form-control" name="name" value="<?= sanitize($_POST['name'] ?? '') ?>" placeholder="उदा. Patil Aarav Sachin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">लिंग <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" name="gender">
                                <option value="मुलगा" <?= ($_POST['gender'] ?? '') === 'मुलगा' ? 'selected' : '' ?>>मुलगा (Boy)</option>
                                <option value="मुलगी" <?= ($_POST['gender'] ?? '') === 'मुलगी' ? 'selected' : '' ?>>मुलगी (Girl)</option>
                                <option value="इतर" <?= ($_POST['gender'] ?? '') === 'इतर' ? 'selected' : '' ?>>इतर (Other)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">इयत्ता (Class) <span class="text-danger">*</span></label>
                            <select class="form-select fw-semibold" name="grade" onchange="updateStdNum(this.value)">
                                <?php 
                                $grades_arr = ['इयत्ता १','इयत्ता २','इयत्ता ३','इयत्ता ४','इयत्ता ५','इयत्ता ६','इयत्ता ७','इयत्ता ८','बालवाटिका १','बालवाटिका २','बालवाटिका ३'];
                                foreach ($grades_arr as $g): 
                                    $g_sel = (isset($_POST['grade']) && $_POST['grade'] === $g) || (!isset($_POST['grade']) && $default_grade === $g);
                                ?>
                                    <option value="<?= $g ?>" <?= $g_sel ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="std" id="std_num" value="<?= (int)($_POST['std'] ?? ($pre_std ?: 1)) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted mb-1">तुकडी (Division)</label>
                            <input type="text" class="form-control fw-semibold" name="section" value="<?= sanitize($_POST['section'] ?? $default_section) ?>" placeholder="उदा. अ">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted mb-1">हजेरी क्र. (Roll No)</label>
                            <input type="text" class="form-control" name="roll_no" value="<?= sanitize($_POST['roll_no'] ?? '') ?>" placeholder="उदा. 1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted mb-1">GR क्र. (GR No)</label>
                            <input type="number" class="form-control" name="gr_no" value="<?= sanitize($_POST['gr_no'] ?? '') ?>" placeholder="उदा. 101">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">वर्गशिक्षक</label>
                            <select class="form-select" name="teacher_id">
                                <option value="">-- शिक्षक निवडा --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= ($_POST['teacher_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= sanitize($t['name_mr'] ?: $t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">जन्मतारीख (DOB)</label>
                            <input type="date" class="form-control" name="date_of_birth" id="date_of_birth" value="<?= sanitize($_POST['date_of_birth'] ?? '') ?>" onchange="calcAge(this.value)">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted mb-1">वय (Age)</label>
                            <input type="number" class="form-control" name="age" id="age" value="<?= sanitize($_POST['age'] ?? '') ?>" placeholder="उदा. 6">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">रक्तगट (Blood Group)</label>
                            <select class="form-select" name="blood_group">
                                <option value="">-- निवडा --</option>
                                <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                                    <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">विद्यार्थी फोटो (पर्यायी)</label>
                            <input type="file" name="photo" accept="image/*" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <!-- २. प्राथमिक कुटुंब व संपर्क -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-light text-dark fw-bold py-2.5 px-3" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-people-fill text-primary me-1"></i> २. प्राथमिक पालक व संपर्क माहिती
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">आईचे नाव (Mother's Name)</label>
                            <input type="text" class="form-control" name="mother_name" value="<?= sanitize($_POST['mother_name'] ?? '') ?>" placeholder="उदा. सुनीता">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">वडिलांचे नाव (Father's Name)</label>
                            <input type="text" class="form-control" name="father_name" value="<?= sanitize($_POST['father_name'] ?? '') ?>" placeholder="उदा. सचिन">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">मोबाईल क्रमांक (Mobile Number)</label>
                            <input type="tel" class="form-control" name="phone" value="<?= sanitize($_POST['phone'] ?? ($_POST['father_mobile'] ?? '')) ?>" placeholder="उदा. 9876543210">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ३. महत्त्वाचे शासकीय क्रमांक (Official IDs) -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-light text-dark fw-bold py-2.5 px-3" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-card-heading text-primary me-1"></i> ३. शासकीय ओळख क्रमांक (Official IDs)
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">आधार क्रमांक (Aadhaar No)</label>
                            <input type="text" class="form-control" name="aadhar_no" maxlength="12" value="<?= sanitize($_POST['aadhar_no'] ?? '') ?>" placeholder="12 अंकी आधार क्र.">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">APAAR ID</label>
                            <input type="text" class="form-control" name="apaar_id" value="<?= sanitize($_POST['apaar_id'] ?? '') ?>" placeholder="12 अंकी APAAR ID">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">Student PEN (पेन क्रमांक)</label>
                            <input type="text" class="form-control" name="student_pen" value="<?= sanitize($_POST['student_pen'] ?? '') ?>" placeholder="उदा. PEN12345">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted mb-1">सरल आयडी (SARAL ID)</label>
                            <input type="text" class="form-control" name="saral_id" value="<?= sanitize($_POST['saral_id'] ?? '') ?>" placeholder="सरल आय.डी.">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ४. उंची व वजन (Physical Measurements) -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px; border-left: 4px solid #10b981 !important;">
                <div class="card-header bg-success-subtle text-success fw-bold py-2.5 px-3 d-flex justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span><i class="bi bi-heart-pulse-fill me-1"></i> ४. शारीरिक मोजमाप: उंची व वजन (HPC व सातत्यपूर्ण मूल्यमापन साठी)</span>
                    <span class="badge bg-success text-white">सत्र १ व सत्र २</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="p-3 border rounded-3 bg-light">
                                <h6 class="fw-bold text-primary mb-2.5"><i class="bi bi-calendar-check me-1"></i> प्रथम सत्र (Term 1)</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-muted mb-1">सत्र १ उंची (सें.मी.)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.1" class="form-control" name="height_sem1" value="<?= sanitize($_POST['height_sem1'] ?? '') ?>" placeholder="उदा. 108.5">
                                            <span class="input-group-text bg-white">सेमी</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-muted mb-1">सत्र १ वजन (कि.ग्रॅ.)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.1" class="form-control" name="weight_sem1" value="<?= sanitize($_POST['weight_sem1'] ?? '') ?>" placeholder="उदा. 17.0">
                                            <span class="input-group-text bg-white">किग्रॅ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="p-3 border rounded-3 bg-light">
                                <h6 class="fw-bold text-success mb-2.5"><i class="bi bi-calendar-check-fill me-1"></i> द्वितीय सत्र (Term 2)</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-muted mb-1">सत्र २ उंची (सें.मी.)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.1" class="form-control" name="height_sem2" value="<?= sanitize($_POST['height_sem2'] ?? '') ?>" placeholder="उदा. 112.0">
                                            <span class="input-group-text bg-white">सेमी</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-muted mb-1">सत्र २ वजन (कि.ग्रॅ.)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.1" class="form-control" name="weight_sem2" value="<?= sanitize($_POST['weight_sem2'] ?? '') ?>" placeholder="उदा. 18.5">
                                            <span class="input-group-text bg-white">किग्रॅ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons for Tab 1 -->
            <div class="card border-0 shadow-sm p-3 mb-4 bg-white" style="border-radius:14px;">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2">
                    <button type="button" class="btn btn-outline-primary fw-bold py-2.5 px-3 d-inline-flex align-items-center justify-content-center gap-1.5" onclick="goToExtraTab()">
                        <span>पुढील पायरी: अतिरिक्त माहिती भरा (Extra Info)</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg fw-bold py-2.5 px-4 shadow-sm d-inline-flex align-items-center justify-content-center gap-2" style="background:#4338ca; border-color:#4338ca;">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>विद्यार्थी जतन करा (Save Student)</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- TAB 2: अतिरिक्त माहिती (EXTRA INFORMATION - AS PER TIME UPDATE)          -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade" id="tab-extra-pane" role="tabpanel" tabindex="0">
            
            <div class="alert alert-secondary py-2 px-3 small d-flex align-items-center justify-content-between mb-3 shadow-xs rounded-3 bg-light border">
                <div>
                    <i class="bi bi-clock-history me-1 text-secondary"></i>
                    <strong>ऐच्छिक माहिती:</strong> ही माहिती संपूर्ण प्रगती पत्रक, जनरल रजिस्टर व अधिकृत अहवालांवर छापण्यासाठी लागते. वेळेनुसार नंतरही भरता येते.
                </div>
                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-bold text-secondary" onclick="goToImpTab()">
                    &laquo; मागे: महत्त्वाची माहिती पहा
                </button>
            </div>

            <!-- ५. सामाजिक व वैयक्तिक तपशील -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-light text-dark fw-bold py-2.5 px-3" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-people text-info me-1"></i> ५. सामाजिक व वैयक्तिक तपशील
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">धर्म (Religion)</label>
                            <input type="text" class="form-control" name="religion" value="<?= sanitize($_POST['religion'] ?? 'हिंदू') ?>" placeholder="उदा. हिंदू">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">जात (Caste)</label>
                            <input type="text" class="form-control" name="caste" value="<?= sanitize($_POST['caste'] ?? 'मराठा') ?>" placeholder="उदा. मराठा">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">प्रवर्ग (Category)</label>
                            <select class="form-select" name="caste_category">
                                <?php 
                                $cats = ['open'=>'खुला (Open / General)', 'OBC'=>'इतर मागासवर्ग (OBC)', 'SC'=>'अनुसूचित जाती (SC)', 'ST'=>'अनुसूचित जमाती (ST)', 'VJ/NT-A'=>'विमुक्त जाती (VJ / NT-A)', 'NT-B'=>'भटक्या जमाती (NT-B)', 'NT-C'=>'भटक्या जमाती (NT-C)', 'NT-D'=>'भटक्या जमाती (NT-D)', 'SBC'=>'विशेष मागास प्रवर्ग (SBC)', 'SEBC'=>'सामाजिक व शैक्षणिक मागासवर्ग (SEBC)', 'EWS'=>'आर्थिक दुर्बल घटक (EWS)'];
                                foreach ($cats as $ck => $cv): 
                                    $sel = ($_POST['caste_category'] ?? 'open') === $ck;
                                ?>
                                    <option value="<?= $ck ?>" <?= $sel ? 'selected' : '' ?>><?= $cv ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">अल्पसंख्याक</label>
                            <select class="form-select" name="minority">
                                <option value="नाही">नाही</option>
                                <option value="होय">होय</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">दिव्यांग (CWSN)</label>
                            <select class="form-select" name="handicap">
                                <option value="नाही">नाही</option>
                                <option value="अस्थिव्यंग">अस्थिव्यंग</option>
                                <option value="कर्णबधिर">कर्णबधिर</option>
                                <option value="अंध">अंध</option>
                                <option value="अल्पदृष्टी">अल्पदृष्टी</option>
                                <option value="मतिमंद">मतिमंद</option>
                                <option value="इतर">इतर</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">दारिद्र्यरेषेखालील (BPL)</label>
                            <select class="form-select" name="bpl">
                                <option value="नाही">नाही</option>
                                <option value="होय">होय</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">जन्मगाव</label>
                            <input type="text" class="form-control" name="birth_place" value="<?= sanitize($_POST['birth_place'] ?? '') ?>" placeholder="जन्मगाव">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">तालुका</label>
                            <input type="text" class="form-control" name="birth_taluka" value="<?= sanitize($_POST['birth_taluka'] ?? '') ?>" placeholder="तालुका">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">जिल्हा</label>
                            <input type="text" class="form-control" name="birth_district" value="<?= sanitize($_POST['birth_district'] ?? '') ?>" placeholder="जिल्हा">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ६. सविस्तर कुटुंब व पत्ता -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-light text-dark fw-bold py-2.5 px-3" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-house-heart text-info me-1"></i> ६. सविस्तर कुटुंब व पत्ता माहिती
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">आईचे शिक्षण</label>
                            <input type="text" class="form-control" name="mother_education" value="<?= sanitize($_POST['mother_education'] ?? '') ?>" placeholder="उदा. पदवीधर">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">आईचा व्यवसाय</label>
                            <input type="text" class="form-control" name="mother_occupation" value="<?= sanitize($_POST['mother_occupation'] ?? '') ?>" placeholder="उदा. गृहिणी">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">आईचा स्वतंत्र मोबाईल</label>
                            <input type="tel" class="form-control" name="mother_mobile" value="<?= sanitize($_POST['mother_mobile'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">वडिलांचे शिक्षण</label>
                            <input type="text" class="form-control" name="father_education" value="<?= sanitize($_POST['father_education'] ?? '') ?>" placeholder="उदा. पदविका">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">वडिलांचा व्यवसाय</label>
                            <input type="text" class="form-control" name="father_occupation" value="<?= sanitize($_POST['father_occupation'] ?? '') ?>" placeholder="उदा. शेती / नोकरी">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">वडिलांचा स्वतंत्र मोबाईल</label>
                            <input type="tel" class="form-control" name="father_mobile" value="<?= sanitize($_POST['father_mobile'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">पालकाचे नाव (इतर)</label>
                            <input type="text" class="form-control" name="guardian_name" value="<?= sanitize($_POST['guardian_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">नाते</label>
                            <input type="text" class="form-control" name="guardian_relation" value="<?= sanitize($_POST['guardian_relation'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">भावंडे संख्या</label>
                            <input type="number" class="form-control" name="num_siblings" value="<?= sanitize($_POST['num_siblings'] ?? '0') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">भावंडांचे वय</label>
                            <input type="text" class="form-control" name="siblings_age" value="<?= sanitize($_POST['siblings_age'] ?? '') ?>" placeholder="उदा. 4 वर्षे, 8 वर्षे">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">पत्ता - ओळ १</label>
                            <input type="text" class="form-control" name="address_line1" value="<?= sanitize($_POST['address_line1'] ?? '') ?>" placeholder="मु. पो. ...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">पत्ता - ओळ २</label>
                            <input type="text" class="form-control" name="address_line2" value="<?= sanitize($_POST['address_line2'] ?? '') ?>" placeholder="तालुका, जिल्हा">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">मातृभाषा</label>
                            <input type="text" class="form-control" name="mother_tongue" value="<?= sanitize($_POST['mother_tongue'] ?? 'मराठी') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">शिक्षण माध्यम</label>
                            <input type="text" class="form-control" name="medium_of_instruction" value="<?= sanitize($_POST['medium_of_instruction'] ?? 'मराठी') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">परिसर प्रकार</label>
                            <select class="form-select" name="area_type">
                                <option value="ग्रामीण" <?= ($_POST['area_type'] ?? 'ग्रामीण') === 'ग्रामीण' ? 'selected' : '' ?>>ग्रामीण</option>
                                <option value="शहरी" <?= ($_POST['area_type'] ?? '') === 'शहरी' ? 'selected' : '' ?>>शहरी</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ७. बँक खाते माहिती -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-light text-dark fw-bold py-2.5 px-3" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-bank text-info me-1"></i> ७. बँक खाते माहिती
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">बँकेचे नाव</label>
                            <input type="text" class="form-control" name="bank_name" value="<?= sanitize($_POST['bank_name'] ?? '') ?>" placeholder="उदा. बँक ऑफ महाराष्ट्र">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">बँक खाते क्रमांक</label>
                            <input type="text" class="form-control" name="bank_account" value="<?= sanitize($_POST['bank_account'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">IFSC कोड</label>
                            <input type="text" class="form-control" name="ifsc" value="<?= sanitize($_POST['ifsc'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ८. HPC विशेष: आवडीनिवडी व परिसर (पान ३) -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px; border-left: 4px solid #f59e0b !important;">
                <div class="card-header bg-warning-subtle text-dark fw-bold py-2.5 px-3 d-flex justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span><i class="bi bi-star-fill text-warning me-1"></i> ८. HPC विशेष: आवडीनिवडी व परिसर (HPC अधिकृत पान ३)</span>
                    <span class="badge bg-warning text-dark">HPC पान ३ साठी</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">🎨 आवडता रंग</label>
                            <select class="form-select" name="favourite_color">
                                <option value="">-- रंग निवडा --</option>
                                <?php foreach ($favourite_colors as $c): ?>
                                    <option value="<?= $c ?>" <?= ($_POST['favourite_color'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">🍛 आवडता अन्नपदार्थ</label>
                            <select class="form-select" name="favourite_food">
                                <option value="">-- अन्नपदार्थ निवडा --</option>
                                <?php foreach ($favourite_foods as $f): ?>
                                    <option value="<?= $f ?>" <?= ($_POST['favourite_food'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">🌺 आवडते फूल</label>
                            <select class="form-select" name="favourite_flower">
                                <option value="">-- फूल निवडा --</option>
                                <?php foreach ($favourite_flowers as $fl): ?>
                                    <option value="<?= $fl ?>" <?= ($_POST['favourite_flower'] ?? '') === $fl ? 'selected' : '' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">⚽ आवडता खेळ</label>
                            <select class="form-select" name="favourite_sport">
                                <option value="">-- खेळ निवडा --</option>
                                <?php foreach ($favourite_sports as $sp): ?>
                                    <option value="<?= $sp ?>" <?= ($_POST['favourite_sport'] ?? '') === $sp ? 'selected' : '' ?>><?= $sp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">🐾 आवडता प्राणी</label>
                            <select class="form-select" name="favourite_animal">
                                <option value="">-- प्राणी निवडा --</option>
                                <?php foreach ($favourite_animals as $an): ?>
                                    <option value="<?= $an ?>" <?= ($_POST['favourite_animal'] ?? '') === $an ? 'selected' : '' ?>><?= $an ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">📚 आवडता विषय</label>
                            <select class="form-select" name="favourite_subject">
                                <option value="">-- विषय निवडा --</option>
                                <?php foreach ($favourite_subjects as $sub): ?>
                                    <option value="<?= $sub ?>" <?= ($_POST['favourite_subject'] ?? '') === $sub ? 'selected' : '' ?>><?= $sub ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted mb-1">🌟 मोठे होऊन मला काय व्हायचे आहे? (स्वप्न)</label>
                            <select class="form-select" name="aspiration">
                                <option value="">-- स्वप्न निवडा --</option>
                                <?php foreach ($aspiration_options as $asp): ?>
                                    <option value="<?= $asp ?>" <?= ($_POST['aspiration'] ?? '') === $asp ? 'selected' : '' ?>><?= $asp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"></div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">👫 जवळचा मित्र/मैत्रीण १</label>
                            <input type="text" class="form-control" name="best_friend1" value="<?= sanitize($_POST['best_friend1'] ?? '') ?>" placeholder="उदा. रोहन">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">👫 जवळचा मित्र/मैत्रीण २</label>
                            <input type="text" class="form-control" name="best_friend2" value="<?= sanitize($_POST['best_friend2'] ?? '') ?>" placeholder="उदा. आदित्य">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted mb-1">👫 जवळचा मित्र/मैत्रीण ३</label>
                            <input type="text" class="form-control" name="best_friend3" value="<?= sanitize($_POST['best_friend3'] ?? '') ?>" placeholder="उदा. सानिका">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ९. माझी आवड (Interests) -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-light text-dark fw-bold py-2.5 px-3" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-heart-fill text-danger me-1"></i> ९. माझी आवड व छंद (Interests)
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <?php
                        $all_interests = ['वाचन','सर्जनशील लेखन','नृत्य','गायन','वाद्य वाजवणे','बागकाम','योग','चित्रकला','हस्तकला','खेळ','स्वयंपाक','घरकामात सहभाग'];
                        $post_ints = $_POST['interests'] ?? [];
                        foreach ($all_interests as $val):
                        ?>
                        <div class="col-6 col-md-3">
                            <div class="p-2 border rounded-2 d-flex align-items-center gap-2 bg-white">
                                <input type="checkbox" name="interests[]" value="<?= $val ?>" class="form-check-input mt-0" id="add_int_<?= md5($val) ?>" <?= in_array($val, $post_ints) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold text-dark cursor-pointer mb-0" for="add_int_<?= md5($val) ?>"><?= $val ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-muted mb-1">इतर आवड / छंद</label>
                        <input type="text" class="form-control" name="other_interest" value="<?= sanitize($_POST['other_interest'] ?? '') ?>" placeholder="इतर आवड असल्यास लिहा (उदा. पोहणे, किल्ले बनवणे)">
                    </div>
                </div>
            </div>

            <!-- Action Buttons for Tab 2 -->
            <div class="card border-0 shadow-sm p-3 mb-4 bg-white" style="border-radius:14px;">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2">
                    <button type="button" class="btn btn-outline-secondary fw-bold py-2.5 px-3 d-inline-flex align-items-center justify-content-center gap-1.5" onclick="goToImpTab()">
                        <i class="bi bi-arrow-left"></i>
                        <span>मागे: महत्त्वाची माहिती</span>
                    </button>
                    <button type="submit" class="btn btn-success btn-lg fw-bold py-2.5 px-4 shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>संपूर्ण माहिती जतन करा (Save All)</span>
                    </button>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
function goToExtraTab() {
    var tabTrigger = new bootstrap.Tab(document.getElementById('tab-extra-btn'));
    tabTrigger.show();
    window.scrollTo({ top: 120, behavior: 'smooth' });
}

function goToImpTab() {
    var tabTrigger = new bootstrap.Tab(document.getElementById('tab-imp-btn'));
    tabTrigger.show();
    window.scrollTo({ top: 120, behavior: 'smooth' });
}

function updateStdNum(grade) {
    const num = grade.replace(/\D/g, '') || '1';
    document.getElementById('std_num').value = num;
}

function calcAge(dobStr) {
    if (!dobStr) return;
    const dob = new Date(dobStr);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    if (age >= 3 && age <= 18) {
        document.getElementById('age').value = age;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
