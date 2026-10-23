<?php
$page_title = 'विद्यार्थी माहिती संपादन (HPC & सातत्यपूर्ण मूल्यमापन)';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/demo_data.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$student_id = intval($_GET['id'] ?? 0);
$return_param = trim($_GET['return'] ?? ($_POST['return_param'] ?? ''));

// Fetch student
$stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
$stmt->execute([$student_id, $school_id]);
$student = $stmt->fetch();
if (!$student) {
    flash('error', 'विद्यार्थी सापडला नाही');
    redirect(APP_URL . '/students/list.php');
}

// Fetch CCE extra details to merge
$cce_extra = [];
try {
    $st_ex = $db->prepare("SELECT * FROM cce_student_extra WHERE student_id = ? AND school_id = ?");
    $st_ex->execute([$student_id, $school_id]);
    $cce_extra = $st_ex->fetch() ?: [];
} catch (Exception $e) {}

// Pre-fill height & weight if present in cce_student_extra but empty in students
if ((float)($student['height_sem1'] ?? 0) <= 0 && !empty($cce_extra['height_1'])) {
    $student['height_sem1'] = (float)$cce_extra['height_1'];
}
if ((float)($student['weight_sem1'] ?? 0) <= 0 && !empty($cce_extra['weight_1'])) {
    $student['weight_sem1'] = (float)$cce_extra['weight_1'];
}
if ((float)($student['height_sem2'] ?? 0) <= 0 && !empty($cce_extra['height_2'])) {
    $student['height_sem2'] = (float)$cce_extra['height_2'];
}
if ((float)($student['weight_sem2'] ?? 0) <= 0 && !empty($cce_extra['weight_2'])) {
    $student['weight_sem2'] = (float)$cce_extra['weight_2'];
}
if (empty($student['gr_no']) && !empty($student['registration_no'])) {
    $student['gr_no'] = preg_replace('/\D/', '', $student['registration_no']) ?: $student['registration_no'];
}
if (empty($student['student_pen']) && !empty($cce_extra['pen'])) {
    $student['student_pen'] = $cce_extra['pen'];
}
if (empty($student['saral_id']) && !empty($cce_extra['saral_id'])) {
    $student['saral_id'] = $cce_extra['saral_id'];
}
if (empty($student['caste_category']) && !empty($cce_extra['cast_cat'])) {
    $student['caste_category'] = $cce_extra['cast_cat'];
}
if (empty($student['religion']) && !empty($cce_extra['religion'])) {
    $student['religion'] = $cce_extra['religion'];
}

// Teachers dropdown
$stmt = $db->prepare("SELECT id, name, name_mr, teacher_code FROM teachers WHERE school_id = ? AND is_active = 1");
$stmt->execute([$school_id]);
$teachers = $stmt->fetchAll();

// Existing interests
$stmt = $db->prepare("SELECT interest FROM student_interests WHERE student_id = ?");
$stmt->execute([$student_id]);
$existing_interests = array_column($stmt->fetchAll(), 'interest');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
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
        'grade' => $_POST['grade'] ?? 'इयत्ता १',
        'std' => !empty($_POST['std']) ? (int)$_POST['std'] : (int)preg_replace('/\D/', '', $_POST['grade'] ?? '1'),
        'section' => trim($_POST['section'] ?? 'अ'),
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
        
        // HPC Page 3 Favourites & Aspirations
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

    // Photo upload handling
    $photo = $student['photo'];
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
        // Sync student_pen and registration_no if either is provided
        if (empty($data['student_pen']) && !empty($data['registration_no'])) {
            $data['student_pen'] = $data['registration_no'];
        } elseif (!empty($data['student_pen']) && empty($data['registration_no'])) {
            $data['registration_no'] = $data['student_pen'];
        }
        
        $sql = "UPDATE students SET 
            name=?, name_mr=?, teacher_id=?, apaar_id=?, udid=?, roll_no=?, gr_no=?, registration_no=?, student_pen=?, saral_id=?,
            grade=?, std=?, section=?, date_of_birth=?, dob=?, age=?, gender=?, photo=?, blood_group=?, aadhar_no=?, aadhaar=?,
            height_sem1=?, weight_sem1=?, height_sem2=?, weight_sem2=?,
            religion=?, caste=?, caste_category=?, minority=?, handicap=?, bpl=?,
            birth_place=?, birth_taluka=?, birth_district=?,
            address_line1=?, address_line2=?, address=?, phone=?, father_mobile=?, mother_mobile=?,
            mother_name=?, mother_education=?, mother_occupation=?,
            father_name=?, father_education=?, father_occupation=?,
            guardian_name=?, guardian_relation=?, num_siblings=?, siblings_age=?,
            mother_tongue=?, medium_of_instruction=?, area_type=?,
            bank_name=?, bank_account=?, ifsc=?,
            favourite_color=?, favourite_food=?, favourite_flower=?, favourite_sport=?, favourite_animal=?, favourite_subject=?,
            aspiration=?, best_friend1=?, best_friend2=?, best_friend3=?
            WHERE id=? AND school_id=?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['name'], $data['name_mr'], $data['teacher_id'], $data['apaar_id'], $data['udid'],
            $data['roll_no'], $data['gr_no'], $data['registration_no'], $data['student_pen'], $data['saral_id'],
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
            $data['aspiration'], $data['best_friend1'], $data['best_friend2'], $data['best_friend3'],
            $student_id, $school_id
        ]);

        // Synchronize with cce_student_extra table so CCE gets exact same height, weight, caste, religion, saral, pen
        try {
            $year = academic_year();
            $upsert_extra = $db->prepare("
                INSERT INTO cce_student_extra
                    (student_id, school_id, academic_year,
                     height_1, weight_1, height_2, weight_2,
                     cast_cat, religion, saral_id, pen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    academic_year=VALUES(academic_year),
                    height_1=VALUES(height_1), weight_1=VALUES(weight_1),
                    height_2=VALUES(height_2), weight_2=VALUES(weight_2),
                    cast_cat=VALUES(cast_cat), religion=VALUES(religion),
                    saral_id=VALUES(saral_id), pen=VALUES(pen)
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

        // Update interests
        $db->prepare("DELETE FROM student_interests WHERE student_id = ?")->execute([$student_id]);
        $interests = $_POST['interests'] ?? [];
        foreach ($interests as $interest) {
            $db->prepare("INSERT INTO student_interests (student_id, interest) VALUES (?, ?)")->execute([$student_id, $interest]);
        }
        $other_interest = trim($_POST['other_interest'] ?? '');
        if ($other_interest) {
            $db->prepare("INSERT INTO student_interests (student_id, interest, other_details) VALUES (?, 'इतर', ?)")->execute([$student_id, $other_interest]);
        }

        flash('success', 'विद्यार्थी माहिती, उंची, वजन व सातत्यपूर्ण मूल्यमापन/HPC तपशील यशस्वीरीत्या अपडेट झाले!');
        
        // Smart Redirect based on return param
        $ret = trim($_POST['return_param'] ?? '');
        $std_val = $data['std'] ?: 1;
        if ($ret === 'hpc') {
            redirect(APP_URL . '/hpc/index.php?std=' . $std_val);
        } elseif ($ret === 'create') {
            redirect(APP_URL . '/hpc/create.php?student_id=' . $student_id);
        } elseif ($ret === 'stage_form') {
            redirect(APP_URL . '/hpc/stage_form.php?student_id=' . $student_id);
        } elseif ($ret === 'cce') {
            redirect(APP_URL . '/cce/extra.php?std=' . $std_val);
        } elseif (!empty($_POST['return_url']) && filter_var($_POST['return_url'], FILTER_VALIDATE_URL) === false && strpos($_POST['return_url'], 'javascript') === false) {
            redirect($_POST['return_url']);
        } else {
            redirect(APP_URL . '/students/list.php');
        }
    }
}

$s = $student;
$active_tab = ($_GET['tab'] ?? '') === 'extra' ? 'extra' : 'imp';
$back_target = APP_URL . '/students/list.php';
$back_text = 'विद्यार्थी यादी';
if ($return_param === 'hpc') {
    $back_target = APP_URL . '/hpc/index.php?std=' . (int)($s['std'] ?: 1);
    $back_text = 'HPC डॅशबोर्ड';
} elseif ($return_param === 'create') {
    $back_target = APP_URL . '/hpc/create.php?student_id=' . $student_id;
    $back_text = 'HPC पायाभूत कार्ड फॉर्म';
} elseif ($return_param === 'stage_form') {
    $back_target = APP_URL . '/hpc/stage_form.php?student_id=' . $student_id;
    $back_text = 'HPC स्टेज फॉर्म';
} elseif ($return_param === 'cce') {
    $back_target = APP_URL . '/cce/extra.php?std=' . (int)($s['std'] ?: 1);
    $back_text = 'सातत्यपूर्ण मूल्यमापन अतिरिक्त माहिती';
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

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h2 class="mb-1 fw-bold text-dark"><i class="bi bi-person-gear text-primary"></i> विद्यार्थी माहिती संपादन</h2>
        <div class="text-muted small">
            विद्यार्थी: <strong><?= sanitize($s['name_mr'] ?: $s['name']) ?></strong> | 
            रोल नं: <strong><?= sanitize($s['roll_no'] ?: '-') ?></strong> | 
            इयत्ता: <strong><?= sanitize($s['grade'] ?: ('इयत्ता ' . $s['std'])) ?></strong>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/modules/classes/index.php?tab=students&std=<?= (int)($s['std'] ?: 1) ?>" class="btn btn-sm btn-outline-primary fw-semibold">
            <i class="bi bi-mortarboard me-1"></i> वर्ग व्यवस्थापन हब
        </a>
        <a href="<?= $back_target ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= $back_text ?>ला परत जा
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-xs mb-3"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<!-- 2 OPTIONS SELECTOR HEADER (महत्त्वाची माहिती vs अतिरिक्त माहिती) -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px; background:#f8fafc; border:1px solid #e2e8f0 !important;">
    <div class="card-body p-2 p-md-3">
        <ul class="nav nav-pills nav-fill gap-2" id="studentEditTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link opt-tab-btn <?= $active_tab === 'imp' ? 'active' : '' ?> py-2.5 px-3 fw-bold d-flex flex-column flex-sm-row align-items-center justify-content-center gap-2" 
                        id="tab-imp-btn" data-bs-toggle="pill" data-bs-target="#tab-imp-pane" type="button" role="tab" aria-selected="<?= $active_tab === 'imp' ? 'true' : 'false' ?>">
                    <div class="d-flex align-items-center gap-1.5">
                        <i class="bi bi-star-fill text-warning fs-5"></i>
                        <span>पर्याय १: महत्त्वाची माहिती</span>
                    </div>
                    <span class="badge opt-badge bg-primary-subtle text-primary border px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                        आवश्यक / मूलभूत माहिती
                    </span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link opt-tab-btn opt-tab-extra <?= $active_tab === 'extra' ? 'active' : '' ?> py-2.5 px-3 fw-bold d-flex flex-column flex-sm-row align-items-center justify-content-center gap-2" 
                        id="tab-extra-btn" data-bs-toggle="pill" data-bs-target="#tab-extra-pane" type="button" role="tab" aria-selected="<?= $active_tab === 'extra' ? 'true' : 'false' ?>">
                    <div class="d-flex align-items-center gap-1.5">
                        <i class="bi bi-collection-fill text-info fs-5"></i>
                        <span>पर्याय २: अतिरिक्त माहिती</span>
                    </div>
                    <span class="badge opt-badge bg-secondary-subtle text-secondary border px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                        ऐच्छिक / HPC आवड-छंद व सविस्तर
                    </span>
                </button>
            </li>
        </ul>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" id="studentEditForm">
    <input type="hidden" name="return_param" value="<?= sanitize($return_param) ?>">
    <input type="hidden" name="return_url" value="<?= sanitize($_GET['return_url'] ?? '') ?>">

    <div class="tab-content" id="studentEditTabContent">
        
        <!-- ========================================================================= -->
        <!-- TAB 1: महत्त्वाची माहिती (IMPORTANT INFORMATION)                            -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade <?= $active_tab === 'imp' ? 'show active' : '' ?>" id="tab-imp-pane" role="tabpanel" tabindex="0">

            <!-- १. मूलभूत ओळख व शालेय नोंद -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center py-2.5 px-3" style="border-radius:12px 12px 0 0;">
                    <span><i class="bi bi-person-badge"></i> १. मूलभूत ओळख व शालेय नोंद</span>
                    <span class="badge bg-white text-primary">आवश्यक माहिती</span>
                </div>
                <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">विद्यार्थ्याचे नाव (मराठी) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name_mr" value="<?= sanitize($s['name_mr']) ?>" required placeholder="उदा. गौरी ज्ञानेश्वर पाटोळे">
                </div>
                <div class="col-md-4">
                    <label class="form-label">विद्यार्थ्याचे नाव (इंग्रजी)</label>
                    <input type="text" class="form-control" name="name" value="<?= sanitize($s['name']) ?>" placeholder="उदा. Gauri Dnyaneshwar Patole">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">लिंग <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender">
                        <option value="मुलगा" <?= ($s['gender'] === 'मुलगा' || $s['gender'] === 'M') ? 'selected' : '' ?>>मुलगा (Boy)</option>
                        <option value="मुलगी" <?= ($s['gender'] === 'मुलगी' || $s['gender'] === 'F') ? 'selected' : '' ?>>मुलगी (Girl)</option>
                        <option value="इतर" <?= ($s['gender'] === 'इतर') ? 'selected' : '' ?>>इतर (Other)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">हजेरी क्र. (Roll No)</label>
                    <input type="text" class="form-control" name="roll_no" value="<?= sanitize($s['roll_no']) ?>" placeholder="उदा. 14">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">जन. रजि. नं. (GR No)</label>
                    <input type="number" class="form-control" name="gr_no" value="<?= sanitize($s['gr_no'] ?: '') ?>" placeholder="उदा. 586">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">इयत्ता</label>
                    <select class="form-select" name="grade" id="grade_select" onchange="updateStdNum(this.value)">
                        <?php 
                        $all_grades = ['इयत्ता १','इयत्ता २','इयत्ता ३','इयत्ता ४','इयत्ता ५','इयत्ता ६','इयत्ता ७','इयत्ता ८','बालवाटिका १','बालवाटिका २','बालवाटिका ३'];
                        foreach ($all_grades as $g): ?>
                            <option value="<?= $g ?>" <?= ($s['grade'] === $g || (!empty($s['std']) && $s['std'] == preg_replace('/\D/', '', $g))) ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="std" id="std_num" value="<?= (int)($s['std'] ?: preg_replace('/\D/', '', $s['grade'] ?? '1') ?: 1) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">तुकडी (Section)</label>
                    <input type="text" class="form-control" name="section" value="<?= sanitize($s['section'] ?: 'अ') ?>" placeholder="उदा. अ">
                </div>
                <div class="col-md-3">
                    <label class="form-label">वर्गशिक्षक</label>
                    <select class="form-select" name="teacher_id">
                        <option value="">-- शिक्षक निवडा --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $s['teacher_id'] == $t['id'] ? 'selected' : '' ?>><?= sanitize($t['name_mr'] ?: $t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">जन्मतारीख (Date of Birth)</label>
                    <input type="date" class="form-control" name="date_of_birth" id="date_of_birth" value="<?= $s['date_of_birth'] ?: $s['dob'] ?>" onchange="calcAge(this.value)">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">वय (Age)</label>
                    <input type="number" class="form-control" name="age" id="age" value="<?= $s['age'] ?>" placeholder="उदा. 7">
                </div>
                <div class="col-md-3">
                    <label class="form-label">रक्तगट (Blood Group)</label>
                    <select class="form-select" name="blood_group">
                        <option value="">-- निवडा --</option>
                        <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= ($s['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">विद्यार्थी फोटो</label>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 55px; height: 55px; border-radius: 8px; border: 2px dashed #0d6efd; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc;">
                            <?php if ($s['photo']): ?>
                                <img src="<?= APP_URL . '/' . $s['photo'] ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="bi bi-camera fs-3 text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="photo" accept="image/*" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- २. उंची व वजन (Physical Measurements — HPC & सातत्यपूर्ण मूल्यमापन दोन्हीसाठी) -->
    <div class="card mb-4 border-0 shadow-sm" style="border-left: 4px solid #10b981 !important;">
        <div class="card-header bg-success-subtle text-success fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-heart-pulse-fill"></i> २. उंची व वजन (सर्व सत्रे - Physical Measurements)</span>
            <span class="badge bg-success text-white">HPC & सातत्यपूर्ण मूल्यमापन दोन्हीसाठी आवश्यक</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info py-2 px-3 small mb-3">
                <i class="bi bi-info-circle-fill"></i> येथे भरलेली उंची व वजन ही माहिती <strong>HPC समग्र प्रगतिपत्रक (पान २)</strong> आणि <strong>सातत्यपूर्ण मूल्यमापन प्रगतिपुस्तक</strong> या दोन्ही ठिकाणी आपोआप वापरली जाते.
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar-check"></i> सत्र १ (पहिले सत्र / Term 1)</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">सत्र १ उंची (सें.मी.)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" class="form-control" name="height_sem1" value="<?= (float)($s['height_sem1'] ?? 0) > 0 ? (float)$s['height_sem1'] : '' ?>" placeholder="उदा. 108.5">
                                    <span class="input-group-text bg-white small">सेमी</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">सत्र १ वजन (कि.ग्रॅ.)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" class="form-control" name="weight_sem1" value="<?= (float)($s['weight_sem1'] ?? 0) > 0 ? (float)$s['weight_sem1'] : '' ?>" placeholder="उदा. 17.0">
                                    <span class="input-group-text bg-white small">किग्रॅ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <h6 class="fw-bold text-success mb-3"><i class="bi bi-calendar-check-fill"></i> सत्र २ (दुसरे सत्र / Term 2)</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">सत्र २ उंची (सें.मी.)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" class="form-control" name="height_sem2" value="<?= (float)($s['height_sem2'] ?? 0) > 0 ? (float)$s['height_sem2'] : '' ?>" placeholder="उदा. 112.0">
                                    <span class="input-group-text bg-white small">सेमी</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">सत्र २ वजन (कि.ग्रॅ.)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" class="form-control" name="weight_sem2" value="<?= (float)($s['weight_sem2'] ?? 0) > 0 ? (float)$s['weight_sem2'] : '' ?>" placeholder="उदा. 18.5">
                                    <span class="input-group-text bg-white small">किग्रॅ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ३. शासकीय ओळख क्रमांक (Official IDs) -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-light text-dark fw-bold"><i class="bi bi-card-heading"></i> ३. शासकीय व ओळख क्रमांक (Official IDs)</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">APAAR ID (अपार आय.डी.)</label>
                    <input type="text" class="form-control" name="apaar_id" value="<?= sanitize($s['apaar_id'] ?: $s['apaar']) ?>" placeholder="उदा. 12 अंकी अपार आय.डी.">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Student PEN (पेन क्रमांक)</label>
                    <input type="text" class="form-control" name="student_pen" value="<?= sanitize($s['student_pen'] ?: $s['registration_no']) ?>" placeholder="उदा. PEN क्रमांक">
                </div>
                <div class="col-md-3">
                    <label class="form-label">SARAL ID / युडायस क्रमांक</label>
                    <input type="text" class="form-control" name="saral_id" value="<?= sanitize($s['saral_id'] ?: $s['udid']) ?>" placeholder="उदा. सरल आय.डी.">
                </div>
                <div class="col-md-3">
                    <label class="form-label">आधार क्रमांक (Aadhaar No)</label>
                    <input type="text" class="form-control" name="aadhar_no" maxlength="12" value="<?= sanitize($s['aadhar_no'] ?: $s['aadhaar']) ?>" placeholder="12 अंकी आधार क्रमांक">
                </div>
                <div class="col-md-3">
                    <label class="form-label">UDID (दिव्यांग युडीआयडी)</label>
                    <input type="text" class="form-control" name="udid" value="<?= sanitize($s['udid']) ?>" placeholder="UDID क्रमांक">
                </div>
                <div class="col-md-3">
                    <label class="form-label">नोंदणी क्रमांक (Reg. No)</label>
                    <input type="text" class="form-control" name="registration_no" value="<?= sanitize($s['registration_no']) ?>" placeholder="नोंदणी क्रमांक">
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons for Tab 1 -->
    <div class="card border-0 shadow-sm p-3 mb-4 bg-white" style="border-radius:14px;">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2">
            <button type="button" class="btn btn-outline-primary fw-bold py-2.5 px-3 d-inline-flex align-items-center justify-content-center gap-1.5" onclick="goToExtraTab()">
                <span>पुढील पायरी: अतिरिक्त माहिती पहा / भरा (Extra Info)</span>
                <i class="bi bi-arrow-right"></i>
            </button>
            <button type="submit" class="btn btn-primary btn-lg fw-bold py-2.5 px-4 shadow-sm d-inline-flex align-items-center justify-content-center gap-2" style="background:#4338ca; border-color:#4338ca;">
                <i class="bi bi-check-circle-fill"></i>
                <span>माहिती अपडेट करा (Update Student)</span>
            </button>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- TAB 2: अतिरिक्त माहिती (EXTRA INFORMATION - AS PER TIME UPDATE)          -->
<!-- ========================================================================= -->
<div class="tab-pane fade <?= $active_tab === 'extra' ? 'show active' : '' ?>" id="tab-extra-pane" role="tabpanel" tabindex="0">
    
    <div class="alert alert-secondary py-2 px-3 small d-flex align-items-center justify-content-between mb-3 shadow-xs rounded-3 bg-light border">
        <div>
            <i class="bi bi-clock-history me-1 text-secondary"></i>
            <strong>ऐच्छिक / सविस्तर माहिती:</strong> ही माहिती संपूर्ण प्रगती पत्रक, जनरल रजिस्टर व अधिकृत अहवालांवर छापण्यासाठी वापरली जाते.
        </div>
        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-bold text-secondary" onclick="goToImpTab()">
            &laquo; मागे: महत्त्वाची माहिती पहा
        </button>
    </div>

    <!-- ४. सामाजिक व वैयक्तिक तपशील (Social & Personal) -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-light text-dark fw-bold"><i class="bi bi-people"></i> ४. सामाजिक व वैयक्तिक माहिती (Social & Category)</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">धर्म (Religion)</label>
                    <input type="text" class="form-control" name="religion" value="<?= sanitize($s['religion'] ?? '') ?>" placeholder="उदा. हिंदू, मुस्लिम, इ.">
                </div>
                <div class="col-md-3">
                    <label class="form-label">जात (Caste)</label>
                    <input type="text" class="form-control" name="caste" value="<?= sanitize($s['caste'] ?? '') ?>" placeholder="उदा. मराठा, कुणबी, इ.">
                </div>
                <div class="col-md-3">
                    <label class="form-label">प्रवर्ग (Category)</label>
                    <select class="form-select" name="caste_category">
                        <?php 
                        $cats = ['open'=>'खुला (Open / General)', 'OBC'=>'इतर मागासवर्ग (OBC)', 'SC'=>'अनुसूचित जाती (SC)', 'ST'=>'अनुसूचित जमाती (ST)', 'VJ/NT-A'=>'विमुक्त जाती (VJ / NT-A)', 'NT-B'=>'भटक्या जमाती (NT-B)', 'NT-C'=>'भटक्या जमाती (NT-C)', 'NT-D'=>'भटक्या जमाती (NT-D)', 'SBC'=>'विशेष मागास प्रवर्ग (SBC)', 'SEBC'=>'सामाजिक व शैक्षणिक मागासवर्ग (SEBC)', 'EWS'=>'आर्थिक दुर्बल घटक (EWS)'];
                        foreach ($cats as $ck => $cv): 
                            $sel = strcasecmp(trim($s['caste_category'] ?? ''), $ck) === 0;
                        ?>
                            <option value="<?= $ck ?>" <?= $sel ? 'selected' : '' ?>><?= $cv ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">अल्पसंख्याक (Minority)</label>
                    <select class="form-select" name="minority">
                        <option value="नाही" <?= ($s['minority'] ?? '') === 'नाही' ? 'selected' : '' ?>>नाही</option>
                        <option value="होय" <?= ($s['minority'] ?? '') === 'होय' ? 'selected' : '' ?>>होय</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">दिव्यांग (Handicap / CWSN)</label>
                    <select class="form-select" name="handicap">
                        <option value="नाही" <?= empty($s['handicap']) || $s['handicap'] === 'नाही' ? 'selected' : '' ?>>नाही</option>
                        <option value="अस्थिव्यंग" <?= ($s['handicap'] ?? '') === 'अस्थिव्यंग' ? 'selected' : '' ?>>अस्थिव्यंग</option>
                        <option value="कर्णबधिर" <?= ($s['handicap'] ?? '') === 'कर्णबधिर' ? 'selected' : '' ?>>कर्णबधिर</option>
                        <option value="अंध" <?= ($s['handicap'] ?? '') === 'अंध' ? 'selected' : '' ?>>अंध</option>
                        <option value="अल्पदृष्टी" <?= ($s['handicap'] ?? '') === 'अल्पदृष्टी' ? 'selected' : '' ?>>अल्पदृष्टी</option>
                        <option value="मतिमंद" <?= ($s['handicap'] ?? '') === 'मतिमंद' ? 'selected' : '' ?>>मतिमंद</option>
                        <option value="इतर" <?= ($s['handicap'] ?? '') === 'इतर' ? 'selected' : '' ?>>इतर</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">दारिद्र्यरेषेखालील (BPL)</label>
                    <select class="form-select" name="bpl">
                        <option value="नाही" <?= empty($s['bpl']) || $s['bpl'] === 'नाही' ? 'selected' : '' ?>>नाही</option>
                        <option value="होय" <?= ($s['bpl'] ?? '') === 'होय' ? 'selected' : '' ?>>होय</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">जन्मगाव / ठिकाण</label>
                    <input type="text" class="form-control" name="birth_place" value="<?= sanitize($s['birth_place'] ?? '') ?>" placeholder="जन्मगाव">
                </div>
                <div class="col-md-2">
                    <label class="form-label">तालुका</label>
                    <input type="text" class="form-control" name="birth_taluka" value="<?= sanitize($s['birth_taluka'] ?? '') ?>" placeholder="तालुका">
                </div>
                <div class="col-md-2">
                    <label class="form-label">जिल्हा</label>
                    <input type="text" class="form-control" name="birth_district" value="<?= sanitize($s['birth_district'] ?? '') ?>" placeholder="जिल्हा">
                </div>
            </div>
        </div>
    </div>

    <!-- ५. कुटुंब व संपर्क माहिती -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-light text-dark fw-bold"><i class="bi bi-house-heart"></i> ५. कुटुंब व संपर्क माहिती</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label fw-bold">आईचे नाव</label><input type="text" class="form-control" name="mother_name" value="<?= sanitize($s['mother_name']) ?>" placeholder="उदा. विद्या"></div>
                <div class="col-md-4"><label class="form-label">आईचे शिक्षण</label><input type="text" class="form-control" name="mother_education" value="<?= sanitize($s['mother_education']) ?>" placeholder="उदा. बी.ए."></div>
                <div class="col-md-4"><label class="form-label">आईचा व्यवसाय</label><input type="text" class="form-control" name="mother_occupation" value="<?= sanitize($s['mother_occupation']) ?>" placeholder="उदा. गृहिणी"></div>

                <div class="col-md-4"><label class="form-label fw-bold">वडिलांचे नाव</label><input type="text" class="form-control" name="father_name" value="<?= sanitize($s['father_name']) ?>" placeholder="उदा. ज्ञानेश्वर"></div>
                <div class="col-md-4"><label class="form-label">वडिलांचे शिक्षण</label><input type="text" class="form-control" name="father_education" value="<?= sanitize($s['father_education']) ?>" placeholder="उदा. १० वी"></div>
                <div class="col-md-4"><label class="form-label">वडिलांचा व्यवसाय</label><input type="text" class="form-control" name="father_occupation" value="<?= sanitize($s['father_occupation']) ?>" placeholder="उदा. शेती / नोकरी"></div>

                <div class="col-md-4"><label class="form-label">पालकाचे नाव (इतर)</label><input type="text" class="form-control" name="guardian_name" value="<?= sanitize($s['guardian_name']) ?>" placeholder="इतर पालक असल्यास"></div>
                <div class="col-md-2"><label class="form-label">नाते</label><input type="text" class="form-control" name="guardian_relation" value="<?= sanitize($s['guardian_relation']) ?>" placeholder="उदा. आजोबा"></div>
                <div class="col-md-2"><label class="form-label">भावंडे संख्या</label><input type="number" class="form-control" name="num_siblings" value="<?= $s['num_siblings'] ?>" placeholder="0"></div>
                <div class="col-md-4"><label class="form-label">भावंडांचे वय</label><input type="text" class="form-control" name="siblings_age" value="<?= sanitize($s['siblings_age']) ?>" placeholder="उदा. 4 वर्षे"></div>

                <div class="col-md-6"><label class="form-label fw-bold">पत्ता - ओळ १</label><input type="text" class="form-control" name="address_line1" value="<?= sanitize($s['address_line1'] ?: $s['address']) ?>" placeholder="उदा. वैदवाडी"></div>
                <div class="col-md-6"><label class="form-label">पत्ता - ओळ २</label><input type="text" class="form-control" name="address_line2" value="<?= sanitize($s['address_line2']) ?>" placeholder="खेड, पुणे - ४१०५०१"></div>

                <div class="col-md-3"><label class="form-label">भ्रमणध्वनी (फोन)</label><input type="tel" class="form-control" name="phone" value="<?= sanitize($s['phone']) ?>" placeholder="फोन नंबर"></div>
                <div class="col-md-3"><label class="form-label">वडिलांचा मोबाईल</label><input type="tel" class="form-control" name="father_mobile" value="<?= sanitize($s['father_mobile']) ?>" placeholder="मोबाईल"></div>
                <div class="col-md-3"><label class="form-label">आईचा मोबाईल</label><input type="tel" class="form-control" name="mother_mobile" value="<?= sanitize($s['mother_mobile']) ?>" placeholder="मोबाईल"></div>
                <div class="col-md-3">
                    <label class="form-label">परिसर प्रकार</label>
                    <select class="form-select" name="area_type">
                        <option value="ग्रामीण" <?= ($s['area_type'] ?? '') === 'ग्रामीण' ? 'selected' : '' ?>>ग्रामीण</option>
                        <option value="शहरी" <?= ($s['area_type'] ?? '') === 'शहरी' ? 'selected' : '' ?>>शहरी</option>
                    </select>
                </div>

                <div class="col-md-6"><label class="form-label">मातृभाषा</label><input type="text" class="form-control" name="mother_tongue" value="<?= sanitize($s['mother_tongue'] ?: 'मराठी') ?>"></div>
                <div class="col-md-6"><label class="form-label">शिक्षणाचे माध्यम</label><input type="text" class="form-control" name="medium_of_instruction" value="<?= sanitize($s['medium_of_instruction'] ?: 'मराठी') ?>"></div>
            </div>
        </div>
    </div>

    <!-- ६. बँक खाते तपशील -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-light text-dark fw-bold"><i class="bi bi-bank"></i> ६. बँक खाते माहिती</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">बँकेचे नाव</label><input type="text" class="form-control" name="bank_name" value="<?= sanitize($s['bank_name'] ?? '') ?>" placeholder="उदा. बँक ऑफ महाराष्ट्र"></div>
                <div class="col-md-4"><label class="form-label">बँक खाते क्रमांक</label><input type="text" class="form-control" name="bank_account" value="<?= sanitize($s['bank_account'] ?? '') ?>" placeholder="खाते क्रमांक"></div>
                <div class="col-md-4"><label class="form-label">IFSC कोड</label><input type="text" class="form-control" name="ifsc" value="<?= sanitize($s['ifsc'] ?? '') ?>" placeholder="MAHB0000..."></div>
            </div>
        </div>
    </div>

    <!-- ७. HPC विशेष: आवडीनिवडी व परिसर (पान ३ साठी) -->
    <div class="card mb-4 border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
        <div class="card-header bg-warning-subtle text-dark fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-star-fill text-warning"></i> ७. HPC विशेष: आवडीनिवडी व परिसर (HPC पान ३)</span>
            <span class="badge bg-warning text-dark">HPC पान ३ वर थेट छापले जाते</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">🎨 आवडता रंग</label>
                    <select class="form-select" name="favourite_color">
                        <option value="">-- रंग निवडा --</option>
                        <?php foreach ($favourite_colors as $c): ?>
                            <option value="<?= $c ?>" <?= ($s['favourite_color'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">🍛 आवडता अन्नपदार्थ</label>
                    <select class="form-select" name="favourite_food">
                        <option value="">-- अन्नपदार्थ निवडा --</option>
                        <?php foreach ($favourite_foods as $f): ?>
                            <option value="<?= $f ?>" <?= ($s['favourite_food'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">🌺 आवडते फूल</label>
                    <select class="form-select" name="favourite_flower">
                        <option value="">-- फूल निवडा --</option>
                        <?php foreach ($favourite_flowers as $fl): ?>
                            <option value="<?= $fl ?>" <?= ($s['favourite_flower'] ?? '') === $fl ? 'selected' : '' ?>><?= $fl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">⚽ आवडता खेळ</label>
                    <select class="form-select" name="favourite_sport">
                        <option value="">-- खेळ निवडा --</option>
                        <?php foreach ($favourite_sports as $sp): ?>
                            <option value="<?= $sp ?>" <?= ($s['favourite_sport'] ?? '') === $sp ? 'selected' : '' ?>><?= $sp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">🐾 आवडता प्राणी</label>
                    <select class="form-select" name="favourite_animal">
                        <option value="">-- प्राणी निवडा --</option>
                        <?php foreach ($favourite_animals as $an): ?>
                            <option value="<?= $an ?>" <?= ($s['favourite_animal'] ?? '') === $an ? 'selected' : '' ?>><?= $an ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">📚 आवडता विषय</label>
                    <select class="form-select" name="favourite_subject">
                        <option value="">-- विषय निवडा --</option>
                        <?php foreach ($favourite_subjects as $sub): ?>
                            <option value="<?= $sub ?>" <?= ($s['favourite_subject'] ?? '') === $sub ? 'selected' : '' ?>><?= $sub ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">🌟 मोठे होऊन मला काय व्हायचे आहे? (स्वप्न / Aspiration)</label>
                    <select class="form-select" name="aspiration">
                        <option value="">-- स्वप्न निवडा --</option>
                        <?php foreach ($aspiration_options as $asp): ?>
                            <option value="<?= $asp ?>" <?= ($s['aspiration'] ?? '') === $asp ? 'selected' : '' ?>><?= $asp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"></div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">👫 जवळचा मित्र/मैत्रीण १</label>
                    <input type="text" class="form-control" name="best_friend1" value="<?= sanitize($s['best_friend1'] ?? '') ?>" placeholder="उदा. वंशिका">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">👫 जवळचा मित्र/मैत्रीण २</label>
                    <input type="text" class="form-control" name="best_friend2" value="<?= sanitize($s['best_friend2'] ?? '') ?>" placeholder="उदा. परी">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">👫 जवळचा मित्र/मैत्रीण ३</label>
                    <input type="text" class="form-control" name="best_friend3" value="<?= sanitize($s['best_friend3'] ?? '') ?>" placeholder="उदा. दिपाली">
                </div>
            </div>
        </div>
    </div>

    <!-- ८. माझी आवड (Interests Checkboxes) -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-light text-dark fw-bold"><i class="bi bi-heart-fill text-danger"></i> ८. माझी आवड (Interests - HPC पान ३)</div>
        <div class="card-body">
            <div class="row g-2">
                <?php
                $all_interests = ['वाचन','सर्जनशील लेखन','नृत्य','गायन','वाद्य वाजवणे','बागकाम','योग','चित्रकला','हस्तकला','खेळ','स्वयंपाक','घरकामात सहभाग'];
                foreach ($all_interests as $val):
                    $isSel = in_array($val, $existing_interests, true);
                ?>
                <div class="col-6 col-md-3">
                    <div class="p-2 border rounded d-flex align-items-center gap-2 <?= $isSel ? 'bg-primary-subtle border-primary' : 'bg-white' ?>">
                        <input type="checkbox" name="interests[]" value="<?= $val ?>" class="form-check-input mt-0" id="int_<?= md5($val) ?>" <?= $isSel ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold text-dark cursor-pointer mb-0" for="int_<?= md5($val) ?>"><?= $val ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3">
                <label class="form-label small fw-bold">इतर आवड (Other Interests)</label>
                <input type="text" class="form-control" name="other_interest" value="<?php
                    try {
                        $stmt3 = $db->prepare("SELECT other_details FROM student_interests WHERE student_id = ? AND interest = 'इतर'");
                        $stmt3->execute([$student_id]);
                        echo sanitize($stmt3->fetchColumn() ?: '');
                    } catch (Exception $e) {}
                ?>" placeholder="इतर कोणतीही आवड असल्यास येथे लिहा">
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
                        <span>संपूर्ण माहिती अपडेट करा (Save All)</span>
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
