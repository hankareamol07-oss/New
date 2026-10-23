<?php
/**
 * विजेता पोर्टल — स्मार्ट स्कूल हब: शाळा, वर्ग, विद्यार्थी व शिक्षक व्यवस्थापन
 * School Setup, Class Range (Std 1 to 4/5/7/8), Std-wise Students, Monthly Attendance, Teachers & Bonafide
 */
$page_title = 'वर्ग व विद्यार्थी व्यवस्थापन हब';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/cce_functions.php';
require_once __DIR__ . '/../../includes/tenant_db.php';
require_once __DIR__ . '/../../modules/lib/module_helpers.php';
require_once __DIR__ . '/../../includes/demo_data.php';
requireLogin();

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

// Teachers list for dropdown & hub filtering
$teachers_list = [];
try {
    $stmt_t = $db->prepare("SELECT id, name, name_mr, teacher_code, class_assigned, section, phone, role FROM teachers WHERE school_id = ? AND is_active = 1 ORDER BY name_mr ASC, name ASC");
    $stmt_t->execute([$school_id]);
    $teachers_list = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    try {
        $stmt_t = $db->prepare("SELECT id, name, name_mr, phone FROM teachers WHERE school_id = ? AND is_active = 1");
        $stmt_t->execute([$school_id]);
        $teachers_list = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) {
        $teachers_list = [];
    }
}
$all_teachers = $teachers_list;

// Download Sample CSV Template
if (($_GET['action'] ?? '') === 'download_student_csv_template') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vijeta_students_template.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'इयत्ता (Std: 1-8)', 'तुकडी (Division: अ/ब/क)', 'हजेरी क्रमांक (Roll No)', 'GR क्रमांक (GR No)',
        'विद्यार्थ्याचे नाव मराठीत (Name Marathi)', 'Name in English', 'लिंग (मुलगा/मुलगी/इतर)',
        'जन्मतारीख (YYYY-MM-DD)', 'वय (Age)', 'जन्मगाव (Birth Place)', 'जन्म तालुका', 'जन्म जिल्हा',
        'आईचे नाव (Mother Name)', 'आईचे शिक्षण', 'आईचा व्यवसाय',
        'वडिलांचे नाव (Father Name)', 'वडिलांचे शिक्षण', 'वडिलांचा व्यवसाय',
        'मोबाईल क्रमांक (Mobile)', 'पत्ता (Address)', 'परिसर (ग्रामीण/शहरी)',
        'धर्म (Religion)', 'जात (Caste)', 'प्रवर्ग (Category: Open/SC/ST/VJNT/OBC/SBC/EWS)',
        'मातृभाषा', 'शिक्षण माध्यम', 'आधार क्रमांक (12 Digit Aadhaar)', 'APAAR ID (12 Digit)', 'Student PEN', 'सरल आयडी', 'रक्तगट',
        'आवडता रंग', 'आवडता अन्नपदार्थ', 'आवडते फूल', 'आवडता खेळ', 'आवडता प्राणी', 'आवडता विषय', 'ध्येय (Aspiration)',
        'मित्र १', 'मित्र २', 'मित्र ३', 'इतर आवड व छंद'
    ]);
    fputcsv($out, [
        '1', 'अ', '1', '101',
        'पाटील आरव सचिन', 'Patil Aarav Sachin', 'मुलगा',
        '2018-05-15', '6', 'सातारा', 'कराड', 'सातारा',
        'सुनीता', 'पदवीधर', 'गृहिणी',
        'सचिन', 'पदविका', 'नोकरी',
        '9876543210', 'मु.पो. कराड', 'ग्रामीण',
        'हिंदू', 'मराठा', 'खुला / Open',
        'मराठी', 'मराठी', '123456789012', '123456789012', 'PEN12345', 'SARAL101', 'O+',
        'निळा', 'पुरणपोळी', 'गुलाब', 'खो-खो', 'ससा', 'गणित', 'डॉक्टर',
        'रोहन', 'आदित्य', '', 'चित्रे काढणे, वाचन'
    ]);
    fclose($out);
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Configured class range for this school (e.g. 1 to 4, 1 to 5, 1 to 7, 1 to 8)
$school_range = get_school_std_range($school_id);
$min_std = array_key_first($school_range) ?: 1;
$max_std = array_key_last($school_range) ?: 8;

$active_tab = trim($_GET['tab'] ?? 'students');
$allowed_tabs = ['school', 'students', 'attendance', 'teachers', 'classes', 'subjects', 'headmaster', 'rules'];
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'students';
}

// Active teachers (loaded early for teacher filter and dropdowns)
$all_teachers = [];
try {
    $t_stmt = $db->prepare("SELECT * FROM teachers WHERE school_id = ? AND is_active = 1 ORDER BY id ASC");
    $t_stmt->execute([$school_id]);
    $all_teachers = $t_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Teacher filter: if a teacher is selected, show only that teacher's classes/students
$filter_teacher_id = (int) ($_GET['teacher_id'] ?? 0);
$filtered_teacher = null;
$teacher_assigned_stds = [];

if ($filter_teacher_id > 0) {
    foreach ($all_teachers as $t_cand) {
        if ((int)$t_cand['id'] === $filter_teacher_id) {
            $filtered_teacher = $t_cand;
            break;
        }
    }
    if ($filtered_teacher) {
        // 1. From teachers.class_assigned
        if (!empty($filtered_teacher['class_assigned'])) {
            $t_std = std_from_grade($filtered_teacher['class_assigned']);
            if ($t_std >= $min_std && $t_std <= $max_std) {
                $teacher_assigned_stds[$t_std] = true;
            }
        }
        // 2. From classes.class_teacher_id
        try {
            $tc_stmt = $db->prepare("SELECT std, section FROM classes WHERE school_id = ? AND class_teacher_id = ?");
            $tc_stmt->execute([$school_id, $filter_teacher_id]);
            foreach ($tc_stmt->fetchAll(PDO::FETCH_ASSOC) as $tc_row) {
                $c_std = (int)$tc_row['std'];
                if ($c_std >= $min_std && $c_std <= $max_std) {
                    $teacher_assigned_stds[$c_std] = true;
                }
            }
        } catch (Throwable $e) {}
        // 3. From cce_subject_teachers
        try {
            $st_stmt = $db->prepare("SELECT DISTINCT std FROM cce_subject_teachers WHERE school_id = ? AND teacher_id = ?");
            $st_stmt->execute([$school_id, $filter_teacher_id]);
            foreach ($st_stmt->fetchAll(PDO::FETCH_COLUMN) as $sub_std) {
                $sub_std = (int)$sub_std;
                if ($sub_std >= $min_std && $sub_std <= $max_std) {
                    $teacher_assigned_stds[$sub_std] = true;
                }
            }
        } catch (Throwable $e) {}

        if (empty($_GET['std']) && !empty($teacher_assigned_stds)) {
            $selected_std = array_key_first($teacher_assigned_stds);
        } elseif (empty($_GET['std']) && !empty($filtered_teacher['class_assigned'])) {
            $t_std = std_from_grade($filtered_teacher['class_assigned']);
            if ($t_std >= $min_std && $t_std <= $max_std) {
                $selected_std = $t_std;
            }
        }
        if (empty($_GET['division']) && !empty($filtered_teacher['section'])) {
            $selected_div = $filtered_teacher['section'];
        }
    }
}

$selected_std = (int) ($_GET['std'] ?? ($selected_std ?? $min_std));
if ($selected_std < $min_std || $selected_std > $max_std) {
    $selected_std = $min_std;
}
$selected_div = trim((string) ($_GET['division'] ?? ($selected_div ?? '')));
$sel_month = (int) ($_GET['month'] ?? 0); // 0 = all months

$msg_success = '';
$msg_error = '';
$msg_warning = '';

// Months map
$months_map = cce_months(); // 6 => जून ... 5 => मे
$working_monthly = json_decode((string)($school['working_days_monthly'] ?? ''), true) ?: [];
$total_school_working_days = array_sum(array_map('intval', $working_monthly));

// -------------------------------------------------------------
// POST Actions Handler
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg_error = 'अवैध CSRF सुरक्षा टोकन!';
    } else {
        $action = $_POST['action'] ?? '';

        // =========================================================
        // 1. SAVE SCHOOL PROFILE & CLASS RANGE (शाळा प्रोफाईल व वर्ग रचना)
        // =========================================================
        if ($action === 'save_school_profile') {
            $name_mr = trim((string)($_POST['name_mr'] ?? ''));
            $name_en = trim((string)($_POST['name'] ?? '')) ?: $name_mr;
            $udise_code = trim((string)($_POST['udise_code'] ?? ''));
            $sanstha = trim((string)($_POST['sanstha'] ?? ''));
            $kendra = trim((string)($_POST['kendra'] ?? ''));
            $taluka = trim((string)($_POST['taluka'] ?? ''));
            $district = trim((string)($_POST['district'] ?? ''));
            $pin_code = trim((string)($_POST['pin_code'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));

            $lower_class = (int)($_POST['lower_class'] ?? 1);
            $upper_class = (int)($_POST['upper_class'] ?? 8);
            if ($lower_class < 1) $lower_class = 1;
            if ($upper_class < $lower_class) $upper_class = $lower_class;
            if ($upper_class > 12) $upper_class = 8;

            // Handle logo upload
            $logo_path = null;
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'], true)) {
                    $filename = 'logo_' . $school_id . '_' . time() . '.' . $ext;
                    $target_dir = __DIR__ . '/../../uploads/logos/';
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_dir . $filename)) {
                        $logo_path = 'uploads/logos/' . $filename;
                    }
                }
            }

            $upd_sql = "UPDATE schools SET 
                name_mr = ?, name = ?, udise_code = ?, sanstha = ?, kendra = ?, 
                taluka = ?, district = ?, pin_code = ?, phone = ?, email = ?, 
                lower_class = ?, upper_class = ?
                " . ($logo_path ? ", logo = ?" : "") . "
                WHERE id = ?";
            
            $params = [
                $name_mr, $name_en, $udise_code, $sanstha, $kendra,
                $taluka, $district, $pin_code, $phone, $email,
                $lower_class, $upper_class
            ];
            if ($logo_path) $params[] = $logo_path;
            $params[] = $school_id;

            $stmt = $db->prepare($upd_sql);
            $stmt->execute($params);

            // Auto-ensure class divisions exist for the configured range
            for ($s = $lower_class; $s <= $upper_class; $s++) {
                $chk = $db->prepare("SELECT id FROM classes WHERE school_id = ? AND std = ?");
                $chk->execute([$school_id, $s]);
                if (!$chk->fetch()) {
                    $ins_c = $db->prepare("INSERT INTO classes (school_id, std, name, section, medium) VALUES (?, ?, ?, 'अ', 'मराठी')");
                    $ins_c->execute([$school_id, $s, std_label($s)]);
                }
            }

            // Refresh school data & range in current request
            $school = getSchool();
            $school_range = get_school_std_range($school_id);
            $min_std = array_key_first($school_range) ?: 1;
            $max_std = array_key_last($school_range) ?: 8;

            $msg_success = "शाळा प्रोफाईल व वर्ग रचना (इ. $lower_class ते $upper_class) यशस्वीरित्या जतन झाली!";
            $active_tab = 'school';
        }

        // =========================================================
        // 2. SAVE / UPDATE TEACHER (शिक्षक माहिती जतन करा)
        // =========================================================
        elseif ($action === 'save_teacher') {
            $teacher_id = (int)($_POST['teacher_id'] ?? 0);
            $name_mr = trim((string)($_POST['name_mr'] ?? ''));
            $name_en = trim((string)($_POST['name'] ?? '')) ?: $name_mr;
            $teacher_code = trim((string)($_POST['teacher_code'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $role = trim((string)($_POST['role'] ?? 'teacher')) ?: 'teacher';
            $class_assigned = trim((string)($_POST['class_assigned'] ?? ''));
            $section = trim((string)($_POST['section'] ?? 'अ')) ?: 'अ';

            if (empty($name_mr) && empty($name_en)) {
                $msg_error = "शिक्षकाचे नाव आवश्यक आहे!";
            } else {
                if ($teacher_id > 0) {
                    $stmt = $db->prepare("UPDATE teachers SET name_mr = ?, name = ?, teacher_code = ?, phone = ?, email = ?, role = ?, class_assigned = ?, section = ? WHERE id = ? AND school_id = ?");
                    $stmt->execute([$name_mr, $name_en, $teacher_code, $phone, $email, $role, $class_assigned, $section, $teacher_id, $school_id]);
                    $target_tid = $teacher_id;
                    $msg_success = "शिक्षक माहिती (" . ($name_mr ?: $name_en) . ") अद्ययावत केली!";
                } else {
                    $stmt = $db->prepare("INSERT INTO teachers (school_id, name_mr, name, teacher_code, phone, email, role, class_assigned, section, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$school_id, $name_mr, $name_en, $teacher_code, $phone, $email, $role, $class_assigned, $section]);
                    $target_tid = (int)$db->lastInsertId();
                    $msg_success = "नवीन शिक्षक (" . ($name_mr ?: $name_en) . ") यशस्वीरित्या जोडले गेले!";
                }

                // If assigned as class teacher, sync to classes table
                if (!empty($class_assigned) && $target_tid > 0) {
                    $matched_std = std_from_grade($class_assigned);
                    if ($matched_std > 0) {
                        $upd_c = $db->prepare("UPDATE classes SET class_teacher_id = ? WHERE school_id = ? AND std = ? AND (section = ? OR section IS NULL OR section = '')");
                        $upd_c->execute([$target_tid, $school_id, $matched_std, $section]);
                    }
                }
                $active_tab = 'teachers';
            }
        }

        // =========================================================
        // 3. DELETE / DEACTIVATE TEACHER
        // =========================================================
        elseif ($action === 'delete_teacher') {
            $del_tid = (int)($_POST['teacher_id'] ?? 0);
            if ($del_tid > 0) {
                $db->prepare("UPDATE teachers SET is_active = 0 WHERE id = ? AND school_id = ?")->execute([$del_tid, $school_id]);
                $db->prepare("UPDATE classes SET class_teacher_id = NULL WHERE class_teacher_id = ? AND school_id = ?")->execute([$del_tid, $school_id]);
                $msg_success = "शिक्षक यशस्वीरित्या वगळण्यात आले.";
            }
            $active_tab = 'teachers';
        }

        // =========================================================
        // 4. SAVE / UPDATE STUDENT (CCE, HPC, Bonafide & आवड)
        // =========================================================
        elseif ($action === 'save_student') {
            $student_id = (int) ($_POST['student_id'] ?? 0);
            $std = (int) ($_POST['std'] ?? $min_std);
            if ($std < $min_std || $std > $max_std) $std = $min_std;
            $grade = "इयत्ता " . $std;
            $section = trim((string) ($_POST['section'] ?? 'अ')) ?: 'अ';
            $roll_no = trim((string) ($_POST['roll_no'] ?? ''));
            $gr_no = (int) ($_POST['gr_no'] ?? 0);
            $registration_no = trim((string) ($_POST['registration_no'] ?? '')) ?: (string)$gr_no;
            $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

            $name_mr = trim((string) ($_POST['name_mr'] ?? ''));
            $name_en = trim((string) ($_POST['name'] ?? '')) ?: $name_mr;
            $gender = trim((string) ($_POST['gender'] ?? 'मुलगा'));
            if (!in_array($gender, ['मुलगा', 'मुलगी', 'इतर'], true)) $gender = 'मुलगा';

            $dob = (!empty($_POST['date_of_birth']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date_of_birth'])) ? $_POST['date_of_birth'] : null;
            $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
            $admission_date = (!empty($_POST['admission_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['admission_date'])) ? $_POST['admission_date'] : null;
            $admission_class = !empty($_POST['admission_class']) ? (int) $_POST['admission_class'] : $std;

            $birth_place = trim((string) ($_POST['birth_place'] ?? ''));
            $birth_taluka = trim((string) ($_POST['birth_taluka'] ?? ''));
            $birth_district = trim((string) ($_POST['birth_district'] ?? ''));
            $birth_state = trim((string) ($_POST['birth_state'] ?? 'महाराष्ट्र')) ?: 'महाराष्ट्र';

            $mother_name = trim((string) ($_POST['mother_name'] ?? ''));
            $mother_education = trim((string) ($_POST['mother_education'] ?? ''));
            $mother_occupation = trim((string) ($_POST['mother_occupation'] ?? ''));
            $father_name = trim((string) ($_POST['father_name'] ?? ''));
            $father_education = trim((string) ($_POST['father_education'] ?? ''));
            $father_occupation = trim((string) ($_POST['father_occupation'] ?? ''));
            $guardian_name = trim((string) ($_POST['guardian_name'] ?? ''));
            $guardian_relation = trim((string) ($_POST['guardian_relation'] ?? ''));
            $num_siblings = !empty($_POST['num_siblings']) ? (int)$_POST['num_siblings'] : 0;
            $siblings_age = trim((string) ($_POST['siblings_age'] ?? ''));

            $phone = trim((string) ($_POST['phone'] ?? ''));
            $address_line1 = trim((string) ($_POST['address_line1'] ?? ''));
            $address_line2 = trim((string) ($_POST['address_line2'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ($address_line1 . ($address_line2 ? ', ' . $address_line2 : ''))));
            $area_type = trim((string) ($_POST['area_type'] ?? 'ग्रामीण')) ?: 'ग्रामीण';

            $religion = trim((string) ($_POST['religion'] ?? ''));
            $caste = trim((string) ($_POST['caste'] ?? ''));
            $caste_category = trim((string) ($_POST['caste_category'] ?? 'खुला / Open'));

            $mother_tongue = trim((string) ($_POST['mother_tongue'] ?? 'मराठी')) ?: 'मराठी';
            $medium_of_instruction = trim((string) ($_POST['medium_of_instruction'] ?? 'मराठी')) ?: 'मराठी';

            $apaar_id = trim((string) ($_POST['apaar_id'] ?? ''));
            $udid = trim((string) ($_POST['udid'] ?? ''));
            $student_pen = trim((string) ($_POST['student_pen'] ?? ''));
            $saral_id = trim((string) ($_POST['saral_id'] ?? ''));
            $aadhar_no = trim((string) ($_POST['aadhar_no'] ?? ''));
            $blood_group = trim((string) ($_POST['blood_group'] ?? ''));
            $nationality = trim((string) ($_POST['nationality'] ?? 'भारतीय')) ?: 'भारतीय';

            // आवडी, छंद व शारीरिक मोजमापे (CCE Area 11 & HPC Page 3)
            $favourite_color = trim((string) ($_POST['favourite_color'] ?? ''));
            $favourite_food = trim((string) ($_POST['favourite_food'] ?? ''));
            $favourite_flower = trim((string) ($_POST['favourite_flower'] ?? ''));
            $favourite_sport = trim((string) ($_POST['favourite_sport'] ?? ''));
            $favourite_animal = trim((string) ($_POST['favourite_animal'] ?? ''));
            $favourite_subject = trim((string) ($_POST['favourite_subject'] ?? ''));
            $aspiration = trim((string) ($_POST['aspiration'] ?? ''));
            $best_friend1 = trim((string) ($_POST['best_friend1'] ?? ''));
            $best_friend2 = trim((string) ($_POST['best_friend2'] ?? ''));
            $best_friend3 = trim((string) ($_POST['best_friend3'] ?? ''));
            $height_sem1 = !empty($_POST['height_sem1']) ? (float) $_POST['height_sem1'] : 0.0;
            $weight_sem1 = !empty($_POST['weight_sem1']) ? (float) $_POST['weight_sem1'] : 0.0;
            $height_sem2 = !empty($_POST['height_sem2']) ? (float) $_POST['height_sem2'] : 0.0;
            $weight_sem2 = !empty($_POST['weight_sem2']) ? (float) $_POST['weight_sem2'] : 0.0;

            $interests = $_POST['interests'] ?? [];
            if (!is_array($interests)) $interests = [];
            $other_interest = trim((string) ($_POST['other_interest'] ?? ''));

            $dob_words = '';
            if ($dob) {
                try {
                    $b_date = new DateTime($dob);
                    $now = new DateTime();
                    if ($age === null || $age === 0) {
                        $age = $now->diff($b_date)->y;
                    }
                    if (function_exists('marathi_date_words')) {
                        $dob_words = marathi_date_words($dob);
                    }
                } catch (Exception $e) {}
            }

            if (empty($name_mr) && empty($name_en)) {
                $msg_error = 'विद्यार्थ्याचे नाव (मराठीत किंवा इंग्रजीत) आवश्यक आहे!';
            } elseif ($student_id === 0 && !canAddStudent($school_id)) {
                $quota = getSchoolStudentQuota($school_id);
                $msg_error = "शाळेची विद्यार्थी मर्यादा पूर्ण झाली आहे ({$quota['max']} विद्यार्थी). कृपया नवीन विद्यार्थी जोडण्यासाठी योजना अपग्रेड करा किंवा ॲडमिनशी संपर्क साधा.";
            } else {
                $photo_path = null;
                if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $filename = 'stu_' . $school_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $target_dir = __DIR__ . '/../../uploads/photos/';
                        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $filename)) {
                            $photo_path = 'uploads/photos/' . $filename;
                        }
                    }
                }

                if ($student_id > 0) {
                    $upd_sql = "UPDATE students SET 
                        std = ?, grade = ?, section = ?, roll_no = ?, gr_no = ?, registration_no = ?, teacher_id = ?,
                        name_mr = ?, name = ?, gender = ?, date_of_birth = ?, dob = ?, age = ?, dob_words = ?,
                        birth_place = ?, birth_taluka = ?, birth_district = ?, birth_state = ?,
                        mother_name = ?, mother_education = ?, mother_occupation = ?,
                        father_name = ?, father_education = ?, father_occupation = ?,
                        guardian_name = ?, guardian_relation = ?, num_siblings = ?, siblings_age = ?,
                        phone = ?, address_line1 = ?, address_line2 = ?, address = ?, area_type = ?,
                        religion = ?, caste = ?, caste_category = ?, nationality = ?, mother_tongue = ?, medium_of_instruction = ?,
                        apaar_id = ?, udid = ?, student_pen = ?, saral_id = ?, aadhar_no = ?, blood_group = ?, admission_date = ?, admission_class = ?,
                        favourite_color = ?, favourite_food = ?, favourite_flower = ?, favourite_sport = ?, favourite_animal = ?, favourite_subject = ?, aspiration = ?,
                        best_friend1 = ?, best_friend2 = ?, best_friend3 = ?, height_sem1 = ?, weight_sem1 = ?, height_sem2 = ?, weight_sem2 = ?
                        " . ($photo_path ? ", photo = ?" : "") . "
                        WHERE id = ? AND school_id = ?";
                    
                    $params = [
                        $std, $grade, $section, $roll_no, $gr_no, $registration_no, $teacher_id,
                        $name_mr, $name_en, $gender, $dob, $dob, $age, $dob_words,
                        $birth_place, $birth_taluka, $birth_district, $birth_state,
                        $mother_name, $mother_education, $mother_occupation,
                        $father_name, $father_education, $father_occupation,
                        $guardian_name, $guardian_relation, $num_siblings, $siblings_age,
                        $phone, $address_line1, $address_line2, $address, $area_type,
                        $religion, $caste, $caste_category, $nationality, $mother_tongue, $medium_of_instruction,
                        $apaar_id, $udid, $student_pen, $saral_id, $aadhar_no, $blood_group, $admission_date, $admission_class,
                        $favourite_color, $favourite_food, $favourite_flower, $favourite_sport, $favourite_animal, $favourite_subject, $aspiration,
                        $best_friend1, $best_friend2, $best_friend3, $height_sem1, $weight_sem1, $height_sem2, $weight_sem2
                    ];
                    if ($photo_path) $params[] = $photo_path;
                    $params[] = $student_id;
                    $params[] = $school_id;

                    $stmt = $db->prepare($upd_sql);
                    $stmt->execute($params);

                    $target_sid = $student_id;
                    $msg_success = "विद्यार्थी माहिती (" . ($name_mr ?: $name_en) . ") यशस्वीरित्या अद्ययावत केली!";
                } else {
                    $ins_columns = [
                        'school_id', 'std', 'grade', 'section', 'roll_no', 'gr_no', 'registration_no', 'teacher_id',
                        'name_mr', 'name', 'gender', 'date_of_birth', 'dob', 'age', 'dob_words',
                        'birth_place', 'birth_taluka', 'birth_district', 'birth_state',
                        'mother_name', 'mother_education', 'mother_occupation',
                        'father_name', 'father_education', 'father_occupation',
                        'guardian_name', 'guardian_relation', 'num_siblings', 'siblings_age',
                        'phone', 'address_line1', 'address_line2', 'address', 'area_type',
                        'religion', 'caste', 'caste_category', 'nationality', 'mother_tongue', 'medium_of_instruction',
                        'apaar_id', 'udid', 'student_pen', 'saral_id', 'aadhar_no', 'blood_group', 'admission_date', 'admission_class',
                        'favourite_color', 'favourite_food', 'favourite_flower', 'favourite_sport', 'favourite_animal', 'favourite_subject', 'aspiration',
                        'best_friend1', 'best_friend2', 'best_friend3', 'height_sem1', 'weight_sem1', 'height_sem2', 'weight_sem2', 'photo', 'is_active'
                    ];

                    $params = [
                        $school_id, $std, $grade, $section, $roll_no, $gr_no, $registration_no, $teacher_id,
                        $name_mr, $name_en, $gender, $dob, $dob, $age, $dob_words,
                        $birth_place, $birth_taluka, $birth_district, $birth_state,
                        $mother_name, $mother_education, $mother_occupation,
                        $father_name, $father_education, $father_occupation,
                        $guardian_name, $guardian_relation, $num_siblings, $siblings_age,
                        $phone, $address_line1, $address_line2, $address, $area_type,
                        $religion, $caste, $caste_category, $nationality, $mother_tongue, $medium_of_instruction,
                        $apaar_id, $udid, $student_pen, $saral_id, $aadhar_no, $blood_group, $admission_date, $admission_class,
                        $favourite_color, $favourite_food, $favourite_flower, $favourite_sport, $favourite_animal, $favourite_subject, $aspiration,
                        $best_friend1, $best_friend2, $best_friend3, $height_sem1, $weight_sem1, $height_sem2, $weight_sem2, $photo_path, 1
                    ];

                    $placeholders = implode(', ', array_fill(0, count($params), '?'));
                    $ins_sql = "INSERT INTO students (" . implode(', ', $ins_columns) . ") VALUES ($placeholders)";

                    $stmt = $db->prepare($ins_sql);
                    $stmt->execute($params);
                    $target_sid = (int)$db->lastInsertId();
                    $msg_success = "नवीन विद्यार्थी (" . ($name_mr ?: $name_en) . ") इयत्ता " . std_label($std) . " मध्ये यशस्वीरित्या जोडला गेला!";
                }

                // Handle interests (Checkboxes + Other)
                if ($target_sid > 0) {
                    $db->prepare("DELETE FROM student_interests WHERE student_id = ?")->execute([$target_sid]);
                    foreach ($interests as $interest_val) {
                        $interest_val = trim((string)$interest_val);
                        if (!empty($interest_val)) {
                            $db->prepare("INSERT INTO student_interests (student_id, interest) VALUES (?, ?)")->execute([$target_sid, $interest_val]);
                        }
                    }
                    if (!empty($other_interest)) {
                        $db->prepare("INSERT INTO student_interests (student_id, interest, other_details) VALUES (?, 'इतर', ?)")->execute([$target_sid, $other_interest]);
                    }

                    // Synchronize with cce_student_extra table so CCE gets exact same height, weight, caste, religion, saral, pen
                    try {
                        $year = function_exists('academic_year') ? academic_year() : (date('m') >= 6 ? date('Y') . '-' . substr(date('Y') + 1, 2) : (date('Y') - 1) . '-' . substr(date('Y'), 2));
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
                            $target_sid, $school_id, $year,
                            $height_sem1 !== null ? (string)$height_sem1 : '',
                            $weight_sem1 !== null ? (string)$weight_sem1 : '',
                            $height_sem2 !== null ? (string)$height_sem2 : '',
                            $weight_sem2 !== null ? (string)$weight_sem2 : '',
                            $caste_category, $religion, $saral_id, $student_pen
                        ]);
                    } catch (Throwable $e) {}
                }

                // Auto-sync students to SQLite tenant database
                if (function_exists('sync_students_to_sqlite')) {
                    sync_students_to_sqlite($school_id);
                }

                $selected_std = $std;
                $active_tab = 'students';
            }
        }

        // =========================================================
        // 5. DELETE STUDENT
        // =========================================================
        elseif ($action === 'delete_student') {
            $del_id = (int) ($_POST['student_id'] ?? 0);
            if ($del_id > 0) {
                $db->prepare("UPDATE students SET is_active = 0 WHERE id = ? AND school_id = ?")->execute([$del_id, $school_id]);
                $msg_success = "विद्यार्थी यशस्वीरित्या वगळण्यात आला.";
            }
            $active_tab = 'students';
        }

        // =========================================================
        // 5b. UPLOAD STUDENTS CSV (विद्यार्थी CSV आयात करा)
        // =========================================================
        elseif ($action === 'upload_students_csv') {
            if (!empty($_FILES['csv_file']['tmp_name']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                if ($handle !== false) {
                    $row_idx = 0;
                    $imported_count = 0;
                    $inserted_new = 0;
                    $updated_count = 0;
                    $skipped_limit = 0;
                    $quota = getSchoolStudentQuota($school_id);
                    $current_count = $quota['count'];
                    $max_allowed = $quota['max'];
                    $is_unlimited = $quota['is_unlimited'];

                    $csv_std = (int)($_POST['csv_std'] ?? $selected_std);
                    if ($csv_std < $min_std || $csv_std > $max_std) $csv_std = $min_std;
                    $csv_div = trim((string)($_POST['csv_division'] ?? 'अ')) ?: 'अ';

                    while (($data = fgetcsv($handle, 3000, ",")) !== false) {
                        $row_idx++;
                        if ($row_idx === 1) continue; // Skip header
                        if (empty($data[2]) && empty($data[3]) && empty($data[4])) continue; // Skip empty row

                        $row_std = !empty($data[0]) ? (int)$data[0] : $csv_std;
                        if ($row_std < $min_std || $row_std > $max_std) $row_std = $csv_std;
                        $row_div = !empty($data[1]) ? trim((string)$data[1]) : $csv_div;
                        $roll_no = trim((string)($data[2] ?? ''));
                        $gr_no = (int)($data[3] ?? 0);
                        $name_mr = trim((string)($data[4] ?? ''));
                        $name_en = trim((string)($data[5] ?? '')) ?: $name_mr;
                        $gender = trim((string)($data[6] ?? 'मुलगा'));
                        if (!in_array($gender, ['मुलगा', 'मुलगी', 'इतर'], true)) $gender = 'मुलगा';

                        $dob = (!empty($data[7]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($data[7]))) ? trim($data[7]) : null;
                        $age = !empty($data[8]) ? (int)$data[8] : null;
                        $birth_place = trim((string)($data[9] ?? ''));
                        $birth_taluka = trim((string)($data[10] ?? ''));
                        $birth_district = trim((string)($data[11] ?? ''));

                        $mother_name = trim((string)($data[12] ?? ''));
                        $mother_education = trim((string)($data[13] ?? ''));
                        $mother_occupation = trim((string)($data[14] ?? ''));
                        $father_name = trim((string)($data[15] ?? ''));
                        $father_education = trim((string)($data[16] ?? ''));
                        $father_occupation = trim((string)($data[17] ?? ''));

                        $phone = trim((string)($data[18] ?? ''));
                        $address = trim((string)($data[19] ?? ''));
                        $area_type = trim((string)($data[20] ?? 'ग्रामीण'));

                        $religion = trim((string)($data[21] ?? 'हिंदू'));
                        $caste = trim((string)($data[22] ?? ''));
                        $caste_category = trim((string)($data[23] ?? 'खुला / Open'));
                        $mother_tongue = trim((string)($data[24] ?? 'मराठी'));
                        $medium = trim((string)($data[25] ?? 'मराठी'));

                        $aadhar_no = trim((string)($data[26] ?? ''));
                        $apaar_id = trim((string)($data[27] ?? ''));
                        $student_pen = trim((string)($data[28] ?? ''));
                        $saral_id = trim((string)($data[29] ?? ''));
                        $blood_group = trim((string)($data[30] ?? ''));

                        $favourite_color = trim((string)($data[31] ?? ''));
                        $favourite_food = trim((string)($data[32] ?? ''));
                        $favourite_flower = trim((string)($data[33] ?? ''));
                        $favourite_sport = trim((string)($data[34] ?? ''));
                        $favourite_animal = trim((string)($data[35] ?? ''));
                        $favourite_subject = trim((string)($data[36] ?? ''));
                        $aspiration = trim((string)($data[37] ?? ''));
                        $best_friend1 = trim((string)($data[38] ?? ''));
                        $best_friend2 = trim((string)($data[39] ?? ''));
                        $best_friend3 = trim((string)($data[40] ?? ''));
                        $interests_text = trim((string)($data[41] ?? ''));

                        $grade = "इयत्ता " . $row_std;
                        $dob_words = ($dob && function_exists('marathi_date_words')) ? marathi_date_words($dob) : '';

                        // Check if student with same GR exists in this school
                        $chk = null;
                        if ($gr_no > 0) {
                            $c_stmt = $db->prepare("SELECT id FROM students WHERE school_id = ? AND gr_no = ?");
                            $c_stmt->execute([$school_id, $gr_no]);
                            $chk = $c_stmt->fetchColumn();
                        }

                        if ($chk) {
                            $upd = $db->prepare("UPDATE students SET 
                                std = ?, grade = ?, section = ?, roll_no = ?, name_mr = ?, name = ?, gender = ?,
                                date_of_birth = ?, dob = ?, age = ?, dob_words = ?, birth_place = ?, birth_taluka = ?, birth_district = ?,
                                mother_name = ?, mother_education = ?, mother_occupation = ?, father_name = ?, father_education = ?, father_occupation = ?,
                                phone = ?, address = ?, area_type = ?, religion = ?, caste = ?, caste_category = ?,
                                mother_tongue = ?, medium_of_instruction = ?, aadhar_no = ?, apaar_id = ?, student_pen = ?, saral_id = ?, blood_group = ?,
                                favourite_color = ?, favourite_food = ?, favourite_flower = ?, favourite_sport = ?, favourite_animal = ?, favourite_subject = ?, aspiration = ?,
                                best_friend1 = ?, best_friend2 = ?, best_friend3 = ?
                                WHERE id = ? AND school_id = ?");
                            $upd->execute([
                                $row_std, $grade, $row_div, $roll_no, $name_mr, $name_en, $gender,
                                $dob, $dob, $age, $dob_words, $birth_place, $birth_taluka, $birth_district,
                                $mother_name, $mother_education, $mother_occupation, $father_name, $father_education, $father_occupation,
                                $phone, $address, $area_type, $religion, $caste, $caste_category,
                                $mother_tongue, $medium, $aadhar_no, $apaar_id, $student_pen, $saral_id, $blood_group,
                                $favourite_color, $favourite_food, $favourite_flower, $favourite_sport, $favourite_animal, $favourite_subject, $aspiration,
                                $best_friend1, $best_friend2, $best_friend3, $chk, $school_id
                            ]);
                            $target_sid = (int)$chk;
                            $updated_count++;
                        } else {
                            if (!$is_unlimited && ($current_count + $inserted_new) >= $max_allowed) {
                                $skipped_limit++;
                                continue;
                            }
                            $ins = $db->prepare("INSERT INTO students (
                                school_id, std, grade, section, roll_no, gr_no, registration_no,
                                name_mr, name, gender, date_of_birth, dob, age, dob_words, birth_place, birth_taluka, birth_district,
                                mother_name, mother_education, mother_occupation, father_name, father_education, father_occupation,
                                phone, address, area_type, religion, caste, caste_category,
                                mother_tongue, medium_of_instruction, aadhar_no, apaar_id, student_pen, saral_id, blood_group,
                                favourite_color, favourite_food, favourite_flower, favourite_sport, favourite_animal, favourite_subject, aspiration,
                                best_friend1, best_friend2, best_friend3, is_active
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, 1
                            )");
                            $ins->execute([
                                $school_id, $row_std, $grade, $row_div, $roll_no, $gr_no, (string)$gr_no,
                                $name_mr, $name_en, $gender, $dob, $dob, $age, $dob_words, $birth_place, $birth_taluka, $birth_district,
                                $mother_name, $mother_education, $mother_occupation, $father_name, $father_education, $father_occupation,
                                $phone, $address, $area_type, $religion, $caste, $caste_category,
                                $mother_tongue, $medium, $aadhar_no, $apaar_id, $student_pen, $saral_id, $blood_group,
                                $favourite_color, $favourite_food, $favourite_flower, $favourite_sport, $favourite_animal, $favourite_subject, $aspiration,
                                $best_friend1, $best_friend2, $best_friend3
                            ]);
                            $target_sid = (int)$db->lastInsertId();
                            $inserted_new++;
                        }

                        if ($target_sid > 0 && !empty($interests_text)) {
                            $db->prepare("DELETE FROM student_interests WHERE student_id = ?")->execute([$target_sid]);
                            $db->prepare("INSERT INTO student_interests (student_id, interest) VALUES (?, ?)")->execute([$target_sid, $interests_text]);
                        }

                        $imported_count++;
                    }
                    fclose($handle);

                    if (function_exists('sync_students_to_sqlite')) {
                        sync_students_to_sqlite($school_id);
                    }

                    $msg_success = "CSV आयात पूर्ण! $inserted_new नवीन विद्यार्थी जोडले" . ($updated_count > 0 ? ", $updated_count अद्ययावत केले." : ".");
                    if ($skipped_limit > 0) {
                        $msg_warning = "प्लॅनच्या विद्यार्थी मर्यादेमुळे ($max_allowed) $skipped_limit नवीन विद्यार्थी जोडले जाऊ शकले नाहीत. सर्व विद्यार्थी जोडण्यासाठी कृपया आपला प्लॅन अपग्रेड करा.";
                    }
                    $selected_std = $csv_std;
                    $active_tab = 'students';
                } else {
                    $msg_error = "CSV फाइल उघडण्यात त्रुटी आली.";
                }
            } else {
                $msg_error = "कृपया योग्य CSV फाइल निवडा.";
            }
        }

        // =========================================================
        // 6. SAVE MONTHLY ATTENDANCE
        // =========================================================
        elseif ($action === 'save_monthly_attendance') {
            $att_std = (int) ($_POST['std'] ?? $min_std);
            $months_to_save = $sel_month > 0 ? [$sel_month => $months_map[$sel_month]] : $months_map;

            if (!empty($_POST['working'])) {
                $wd = [];
                foreach ($months_map as $mn => $label) {
                    $wd[$mn] = max(0, (int)($_POST['working'][$mn] ?? 0));
                }
                $json = json_encode($wd, JSON_UNESCAPED_UNICODE);
                $db->prepare("UPDATE schools SET working_days_monthly = ? WHERE id = ?")->execute([$json, $school_id]);
                $working_monthly = $wd;
                $total_school_working_days = array_sum(array_map('intval', $working_monthly));
            }

            $att_stmt = $db->prepare("INSERT INTO attendance (student_id, academic_year, month, working_days, days_present) 
                                     VALUES (?, ?, ?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE working_days = VALUES(working_days), days_present = VALUES(days_present)");
            
            try {
                $t_db = cce_db($school_id);
                $t_att_stmt = $t_db->prepare("INSERT OR REPLACE INTO attendance (student_id, academic_year, month, working_days, days_present) VALUES (?, ?, ?, ?, ?)");
            } catch (Exception $e) { $t_att_stmt = null; }

            foreach (($_POST['p'] ?? []) as $sid => $m_data) {
                $sid = (int) $sid;
                foreach ($months_to_save as $m_num => $m_lbl) {
                    if (!isset($m_data[$m_num]) || $m_data[$m_num] === '') continue;
                    $pres = max(0, (int)$m_data[$m_num]);
                    $w_days = (int)($working_monthly[$m_num] ?? 0);
                    $att_stmt->execute([$sid, $year, $m_num, $w_days, $pres]);
                    if ($t_att_stmt) {
                        $t_att_stmt->execute([$sid, $year, $m_num, $w_days, $pres]);
                    }
                }
            }

            $msg_success = "इयत्ता " . std_label($att_std) . " ची मासिक उपस्थिती यशस्वीरित्या जतन झाली!";
            $active_tab = 'attendance';
            $selected_std = $att_std;
        }

        // =========================================================
        // 7. UPDATE CLASS TEACHERS & MEDIUMS
        // =========================================================
        elseif ($action === 'update_class_teachers') {
            $class_teachers = $_POST['class_teacher'] ?? [];
            $mediums = $_POST['medium'] ?? [];
            $stmt = $db->prepare("UPDATE classes SET class_teacher_id = ?, medium = ? WHERE id = ? AND school_id = ?");
            foreach ($class_teachers as $cid => $tid) {
                $cid = (int) $cid;
                $tid = !empty($tid) ? (int) $tid : null;
                $med = trim((string) ($mediums[$cid] ?? 'मराठी')) ?: 'मराठी';
                $stmt->execute([$tid, $med, $cid, $school_id]);

                // Auto-apply class teacher to unassigned subjects
                if ($tid > 0) {
                    $c_chk = $db->prepare("SELECT std, section FROM classes WHERE id = ? AND school_id = ?");
                    $c_chk->execute([$cid, $school_id]);
                    $c_info = $c_chk->fetch(PDO::FETCH_ASSOC);
                    if ($c_info) {
                        $c_std = (int)$c_info['std'];
                        $c_sec = ($c_info['section'] ?: 'अ');
                        $subs = cce_subjects($school_id, $c_std);
                        if (empty($subs) && function_exists('cce_seed_subjects')) {
                            cce_seed_subjects($school_id, $c_std);
                            $subs = cce_subjects($school_id, $c_std);
                        }
                        $st_chk = $db->prepare("SELECT subject_id FROM cce_subject_teachers WHERE school_id = ? AND academic_year = ? AND std = ? AND (division = ? OR division = '-')");
                        $st_chk->execute([$school_id, $year, $c_std, $c_sec]);
                        $existing_subs = $st_chk->fetchAll(PDO::FETCH_COLUMN) ?: [];
                        $ins_st = $db->prepare("INSERT INTO cce_subject_teachers (school_id, academic_year, std, division, subject_id, teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($subs as $s) {
                            $sid = (int)$s['id'];
                            if (!in_array($sid, $existing_subs)) {
                                $ins_st->execute([$school_id, $year, $c_std, $c_sec, $sid, $tid]);
                            }
                        }
                    }
                }
            }
            $msg_success = 'वर्गशिक्षक व माध्यम माहिती यशस्वीरित्या जतन झाली!';
            $active_tab = 'classes';
        }

        // =========================================================
        // 8. ADD NEW CLASS / DIVISION
        // =========================================================
        elseif ($action === 'add_class_division') {
            $std = (int) ($_POST['std'] ?? $min_std);
            $section = trim((string) ($_POST['section'] ?? 'अ'));
            $medium = trim((string) ($_POST['medium'] ?? 'मराठी'));
            $class_teacher_id = !empty($_POST['class_teacher_id']) ? (int) $_POST['class_teacher_id'] : null;

            $check = $db->prepare("SELECT id FROM classes WHERE school_id = ? AND std = ? AND section = ?");
            $check->execute([$school_id, $std, $section]);
            if ($check->fetch()) {
                $msg_error = "इयत्ता $std तुकडी '$section' आधीच उपलब्ध आहे.";
            } else {
                $ins = $db->prepare("INSERT INTO classes (school_id, std, name, section, medium, class_teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$school_id, $std, std_label($std), $section, $medium, $class_teacher_id]);
                
                // If class teacher is given, auto-apply to every subject
                if ($class_teacher_id > 0) {
                    $subs = cce_subjects($school_id, $std);
                    if (empty($subs) && function_exists('cce_seed_subjects')) {
                        cce_seed_subjects($school_id, $std);
                        $subs = cce_subjects($school_id, $std);
                    }
                    $ins_st = $db->prepare("INSERT INTO cce_subject_teachers (school_id, academic_year, std, division, subject_id, teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($subs as $s) {
                        $ins_st->execute([$school_id, $year, $std, $section, (int)$s['id'], $class_teacher_id]);
                    }
                }
                
                $msg_success = "नवीन वर्ग/तुकडी यशस्वीरित्या जोडली गेली व वर्गशिक्षक सर्व विषयांना लागू झाले!";
            }
            $active_tab = 'classes';
        }

        // =========================================================
        // 9. SAVE SUBJECT TEACHERS
        // =========================================================
        elseif ($action === 'save_subject_teachers') {
            $std = (int) ($_POST['std'] ?? $min_std);
            if ($std < $min_std || $std > $max_std) {
                $std = $min_std;
            }
            $division = trim((string) ($_POST['division'] ?? '')) ?: 'अ';
            $teachers = $_POST['teacher'] ?? [];

            // CRITICAL: Update active view variables so page re-renders on the exact same std & division
            $selected_std = $std;
            $selected_div = $division;

            $del = $db->prepare('DELETE FROM cce_subject_teachers WHERE school_id=? AND academic_year=? AND std=? AND (division=? OR division=?) AND subject_id=?');
            $ins = $db->prepare('INSERT INTO cce_subject_teachers (school_id, academic_year, std, division, subject_id, teacher_id) VALUES (?,?,?,?,?,?)');

            $subjects = cce_subjects($school_id, $std);
            if (empty($subjects) && function_exists('cce_seed_subjects')) {
                cce_seed_subjects($school_id, $std);
                $subjects = cce_subjects($school_id, $std);
            }
            foreach ($subjects as $s) {
                $sid = (int) $s['id'];
                $tid = (int) ($teachers[$sid] ?? 0);
                $del->execute([$school_id, $year, $std, $division, ($division === 'अ' ? '-' : $division), $sid]);
                if ($tid > 0) {
                    $ins->execute([$school_id, $year, $std, $division, $sid, $tid]);
                }
            }
            $msg_success = "इयत्ता " . std_label($std) . " (तुकडी: $division) चे विषय शिक्षक यशस्वीरित्या जतन झाले!";
            $active_tab = 'subjects';
        }

        // =========================================================
        // 10. SAVE HEADMASTER SETTINGS
        // =========================================================
        elseif ($action === 'save_headmaster_settings') {
            $sem1_hm = trim($_POST['sem1_headmaster_name'] ?? '');
            $sem2_hm = trim($_POST['sem2_headmaster_name'] ?? '');
            $res_date = trim($_POST['result_date'] ?? '') ?: null;
            $notice = trim($_POST['next_year_notice'] ?? '');

            $check = $db->prepare("SELECT id FROM school_defaults WHERE school_id = ?");
            $check->execute([$school_id]);
            if ($check->fetch()) {
                $upd = $db->prepare("UPDATE school_defaults SET sem1_headmaster_name = ?, sem2_headmaster_name = ?, result_date = ?, next_year_notice = ?, updated_at = NOW() WHERE school_id = ?");
                $upd->execute([$sem1_hm, $sem2_hm, $res_date, $notice, $school_id]);
            } else {
                $ins = $db->prepare("INSERT INTO school_defaults (school_id, sem1_headmaster_name, sem2_headmaster_name, result_date, next_year_notice, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $ins->execute([$school_id, $sem1_hm, $sem2_hm, $res_date, $notice]);
            }
            $msg_success = 'मुख्याध्यापक व निकाल सेटिंग्ज यशस्वीरित्या जतन झाल्या!';
            $active_tab = 'headmaster';
        }
    }
}

// -------------------------------------------------------------
// Data Loading & Counts
// -------------------------------------------------------------

// Active teachers
$t_stmt = $db->prepare("SELECT * FROM teachers WHERE school_id = ? AND is_active = 1 ORDER BY id ASC");
$t_stmt->execute([$school_id]);
$all_teachers = $t_stmt->fetchAll(PDO::FETCH_ASSOC);

// Classes & divisions bounded by school range
$c_stmt = $db->prepare("SELECT c.*, t.name_mr AS teacher_name_mr, t.name AS teacher_name 
                        FROM classes c 
                        LEFT JOIN teachers t ON c.class_teacher_id = t.id 
                        WHERE c.school_id = ? AND c.std >= ? AND c.std <= ?
                        ORDER BY c.std ASC, c.section ASC");
$c_stmt->execute([$school_id, $min_std, $max_std]);
$classes = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

// Standard-wise counts across configured school standards
$std_counts = [];
foreach ($school_range as $s_idx => $s_name) {
    $std_counts[$s_idx] = ['total' => 0, 'boys' => 0, 'girls' => 0];
}
$all_st_stmt = $db->prepare("SELECT id, std, grade, gender FROM students WHERE school_id = ? AND is_active = 1");
$all_st_stmt->execute([$school_id]);
foreach ($all_st_stmt->fetchAll(PDO::FETCH_ASSOC) as $s_row) {
    $st_num = (int)($s_row['std'] ?: std_from_grade($s_row['grade']));
    if (isset($std_counts[$st_num])) {
        $std_counts[$st_num]['total']++;
        if (in_array(trim((string)$s_row['gender']), ['मुलगी', 'F', 'स्त्री'], true)) {
            $std_counts[$st_num]['girls']++;
        } else {
            $std_counts[$st_num]['boys']++;
        }
    }
}

$school_quota = getSchoolStudentQuota($school_id);

// Students for selected standard & division
$mr_sel_std = function_exists('to_mr_digits') ? to_mr_digits($selected_std) : $selected_std;
$curr_students_stmt = $db->prepare("SELECT s.*, c.name AS class_name, c.section AS class_section 
                                    FROM students s 
                                    LEFT JOIN classes c ON (s.class_id = c.id OR (s.std = c.std AND BINARY s.section = BINARY c.section AND c.school_id = s.school_id))
                                    WHERE s.school_id = ? AND (s.std = ? OR s.grade LIKE ? OR s.grade LIKE ?) AND (s.is_active = 1 OR s.is_active IS NULL) 
                                    " . ($selected_div !== '' ? "AND (BINARY s.section = BINARY ? OR BINARY c.section = BINARY ?)" : "") . "
                                    ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.name_mr ASC");
$st_params = [$school_id, $selected_std, "%$selected_std%", "%$mr_sel_std%"];
if ($selected_div !== '') {
    $st_params[] = $selected_div;
    $st_params[] = $selected_div;
}
$curr_students_stmt->execute($st_params);
$active_students = $curr_students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Attendance map & Student Interests
$att_records = [];
if (!empty($active_students)) {
    $stu_ids = array_column($active_students, 'id');
    $in_clause = implode(',', array_map('intval', $stu_ids));
    $a_stmt = $db->query("SELECT student_id, month, working_days, days_present FROM attendance WHERE student_id IN ($in_clause) AND academic_year = " . $db->quote($year));
    while ($r = $a_stmt->fetch(PDO::FETCH_ASSOC)) {
        $att_records[(int)$r['student_id']][(int)$r['month']] = (int)$r['days_present'];
    }

    // Attach student interests (for edit modal checkboxes & other)
    $int_stmt = $db->query("SELECT student_id, interest, other_details FROM student_interests WHERE student_id IN ($in_clause)");
    $stu_interests_map = [];
    $stu_other_map = [];
    while ($ir = $int_stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$ir['student_id'];
        if ($ir['interest'] === 'इतर' && !empty($ir['other_details'])) {
            $stu_other_map[$sid] = $ir['other_details'];
        } else {
            $stu_interests_map[$sid][] = $ir['interest'];
        }
    }
    foreach ($active_students as &$st_ref) {
        $sid = (int)$st_ref['id'];
        $st_ref['interests'] = $stu_interests_map[$sid] ?? [];
        $st_ref['other_interest'] = $stu_other_map[$sid] ?? '';
    }
    unset($st_ref);
}

// Subject teachers
$cce_subjects_list = cce_subjects($school_id, $selected_std);
if (empty($cce_subjects_list) && function_exists('cce_seed_subjects')) {
    cce_seed_subjects($school_id, $selected_std);
    $cce_subjects_list = cce_subjects($school_id, $selected_std);
}
$sub_teachers_map = [];
$target_div = $selected_div ?: 'अ';
$stm_map = $db->prepare("SELECT subject_id, teacher_id, division FROM cce_subject_teachers WHERE school_id=? AND academic_year=? AND std=? AND (division=? OR division='-') ORDER BY (division = ?) DESC");
$stm_map->execute([$school_id, $year, $selected_std, $target_div, $target_div]);
foreach ($stm_map->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $sid = (int) $r['subject_id'];
    if (!isset($sub_teachers_map[$sid])) {
        $sub_teachers_map[$sid] = (int) $r['teacher_id'];
    }
}

// Fetch class teacher for this selected standard & division
$ct_lookup_stmt = $db->prepare("SELECT class_teacher_id FROM classes WHERE school_id = ? AND std = ? AND (section = ? OR section = '-' OR section IS NULL) ORDER BY (section = ?) DESC LIMIT 1");
$ct_lookup_stmt->execute([$school_id, $selected_std, $target_div, $target_div]);
$current_class_teacher_id = (int)$ct_lookup_stmt->fetchColumn();

// USER REQUIREMENT:
// "if class teacher is given then it should be apply auto to every subject but user can modifiy as he require"
if ($current_class_teacher_id > 0) {
    foreach ($cce_subjects_list as $sub) {
        $sid = (int)$sub['id'];
        if (empty($sub_teachers_map[$sid])) {
            $sub_teachers_map[$sid] = $current_class_teacher_id;
        }
    }
}

// Available sections for current std (for quick dropdown in Tab 6)
$sec_stmt = $db->prepare("SELECT DISTINCT section FROM classes WHERE school_id = ? AND std = ? ORDER BY section ASC");
$sec_stmt->execute([$school_id, $selected_std]);
$available_divisions = $sec_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
if (empty($available_divisions)) {
    $available_divisions = ['अ'];
}
if (!in_array($target_div, $available_divisions, true)) {
    $available_divisions[] = $target_div;
}

// Defaults
$def_stmt = $db->prepare("SELECT * FROM school_defaults WHERE school_id = ?");
$def_stmt->execute([$school_id]);
$defaults = $def_stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'sem1_headmaster_name' => '',
    'sem2_headmaster_name' => '',
    'result_date' => '',
    'next_year_notice' => ''
];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* 100% Mobile Responsive Styles for TechGuruji Class Management Hub */
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.hub-tabs-scroll {
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 4px;
}
.hub-tabs-scroll .nav {
    display: inline-flex;
    flex-wrap: nowrap;
}
.hub-tabs-scroll .nav-link {
    white-space: nowrap;
    font-size: 0.88rem;
    padding: 0.55rem 0.85rem;
    border-radius: 8px;
    flex-shrink: 0;
}

.std-pills-scroll {
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    padding: 2px 0;
}
.std-pills-scroll .btn {
    white-space: nowrap;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .hub-header-actions {
        width: 100% !important;
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .hub-header-actions > * {
        width: 100% !important;
    }
    .hub-header-actions .input-group {
        max-width: 100% !important;
    }
    .class-header-actions {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .class-header-actions > * {
        flex: 1 1 auto;
        text-align: center;
    }
}
</style>

<div class="container-fluid px-0">
    <!-- Top Hub Header with School Brand & Class Level Badge -->
    <div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($school['logo']) && file_exists(__DIR__ . '/../../' . ltrim($school['logo'], '/'))): ?>
                        <img src="<?= APP_URL ?>/<?= ltrim($school['logo'], '/') ?>" alt="School Logo" class="rounded-3 border shadow-sm" style="width: 58px; height: 58px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-3 shadow-sm" style="width: 58px; height: 58px;">
                            <?= mb_substr($school['name_mr'] ?: $school['name'] ?: 'शा', 0, 1) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="badge bg-primary"><i class="bi bi-mortarboard-fill"></i> TechGuruji वर्ग हब</span>
                            <span class="badge bg-success-subtle text-success border border-success">
                                <i class="bi bi-building-check"></i> शाळा रचना: इ. <?= $min_std ?> ते <?= $max_std ?> (<?= count($school_range) ?> वर्ग)
                            </span>
                            <span class="badge <?= $school_quota['can_add'] ? 'bg-info-subtle text-info border border-info' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                <i class="bi bi-people-fill me-1"></i> विद्यार्थी मर्यादा: <?= $school_quota['count'] ?> / <?= $school_quota['is_unlimited'] ? 'अमर्याद' : $school_quota['max'] ?> (<?= sanitize($school_quota['plan_name']) ?>)
                            </span>
                            <?php if (!empty($school['udise_code'])): ?>
                                <span class="badge bg-light text-dark border font-monospace">UDISE: <?= sanitize($school['udise_code']) ?></span>
                            <?php endif; ?>
                            <span class="badge bg-light text-muted border">शैक्षणिक वर्ष: <?= sanitize($year) ?></span>
                        </div>
                        <h3 class="mb-0 fw-bold text-dark">
                            <?= sanitize($school['name_mr'] ?: $school['name']) ?>
                        </h3>
                        <p class="text-muted small mb-0">
                            <?= sanitize(implode(', ', array_filter([$school['kendra'] ? 'केंद्र: ' . $school['kendra'] : '', $school['taluka'] ? 'ता. ' . $school['taluka'] : '', $school['district'] ? 'जि. ' . $school['district'] : '']))) ?>
                        </p>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center hub-header-actions">
                    <div class="input-group input-group-sm" style="min-width: 200px; max-width: 280px;">
                        <span class="input-group-text bg-light text-muted fw-bold"><i class="bi bi-filter"></i> शिक्षक</span>
                        <select class="form-select form-select-sm" onchange="filterByTeacher(this.value)">
                            <option value="0">सर्व शिक्षक व सर्व वर्ग</option>
                            <?php foreach ($all_teachers as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $filter_teacher_id == $t['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($t['name_mr'] ?: $t['name']) ?> (<?= sanitize($t['class_assigned'] ? $t['class_assigned'] . ' ' . $t['section'] : 'शिक्षक') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <a href="?tab=school" class="btn btn-outline-primary btn-sm fw-semibold">
                        <i class="bi bi-gear-fill me-1"></i> शाळा प्रोफाईल
                    </a>
                    <?php if ($school_quota['can_add']): ?>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm fw-semibold" onclick="openStudentModal(0, <?= $selected_std ?>)">
                            <i class="bi bi-person-plus-fill me-1"></i> नवीन विद्यार्थी
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-danger btn-sm shadow-sm fw-semibold" onclick="alert('शाळेची विद्यार्थी मर्यादा पूर्ण झाली आहे (जास्तीत जास्त: <?= $school_quota['max'] ?> विद्यार्थी). कृपया पुढील विद्यार्थी जोडण्यासाठी योजना अपग्रेड करा.')" title="विद्यार्थी मर्यादा पूर्ण झाली आहे">
                            <i class="bi bi-lock-fill me-1"></i> नवीन विद्यार्थी (मर्यादा पूर्ण)
                        </button>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/modules/classes/import_udise.php" class="btn btn-warning btn-sm shadow-sm fw-bold text-dark">
                        <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> UDISE+ आयात
                    </a>
                    <a href="<?= APP_URL ?>/modules/classes/bonafide.php" class="btn btn-outline-success btn-sm fw-semibold">
                        <i class="bi bi-file-earmark-check"></i> बोनाफाईड दाखला
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quota Limit Warning Banner (If Limit Reached) -->
    <?php if (!$school_quota['can_add']): ?>
        <div class="alert alert-danger border border-danger shadow-xs rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 py-2.5 px-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
                <div>
                    <strong class="text-danger d-block">शाळेची विद्यार्थी मर्यादा पूर्ण झाली आहे (<?= $school_quota['count'] ?> / <?= $school_quota['max'] ?> विद्यार्थी)</strong>
                    <span class="text-muted small">आपल्या '<?= sanitize($school_quota['plan_name']) ?>' योजनेनुसार नवीन विद्यार्थी नोंदणी मर्यादा संपली आहे. पुढील विद्यार्थी जोडण्यासाठी कृपया योजना अपग्रेड करा किंवा ॲडमिनशी संपर्क साधा.</span>
                </div>
            </div>
            <a href="<?= APP_URL ?>/subscription/plans.php" class="btn btn-danger btn-sm fw-bold shadow-xs px-3 py-2 text-nowrap">
                <i class="bi bi-arrow-up-circle-fill me-1"></i> योजना अपग्रेड करा ›
            </a>
        </div>
    <?php endif; ?>

    <!-- Prominent UDISE+ Excel Quick Import Banner -->
    <div class="alert alert-warning border border-warning shadow-xs rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 py-2.5 px-3">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-white rounded-circle p-1.5 shadow-xs text-success d-flex align-items-center justify-content-center">
                <i class="bi bi-file-earmark-excel-fill fs-4"></i>
            </div>
            <div>
                <strong class="text-dark d-block">UDISE+ वरून सर्व वर्गांचे विद्यार्थी थेट आयात करा</strong>
                <span class="text-muted small">UDISE+ Excel अपलोड करा आणि सर्व विद्यार्थ्यांचे मराठी युनिकोड नाव, जन्मतारीख व सर्व माहिती स्वयंचलित मिळवा.</span>
            </div>
        </div>
        <a href="<?= APP_URL ?>/modules/classes/import_udise.php" class="btn btn-dark btn-sm fw-bold shadow-xs px-3 py-2 text-nowrap">
            <i class="bi bi-cloud-arrow-up-fill text-warning me-1"></i> UDISE+ Excel आयात उघडा ›
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if ($msg_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= sanitize($msg_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= sanitize($msg_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($msg_warning)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= sanitize($msg_warning) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Teacher Filter Active Banner -->
    <?php if ($filter_teacher_id > 0 && $filtered_teacher): ?>
        <div class="alert alert-primary py-2 px-3 d-flex flex-wrap align-items-center justify-content-between mb-4 rounded-3 shadow-sm border border-primary">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-badge-fill fs-5 text-primary"></i>
                <div>
                    <span class="fw-bold">शिक्षक फिल्टर सक्रिय:</span> 
                    <strong><?= sanitize($filtered_teacher['name_mr'] ?: $filtered_teacher['name']) ?></strong>
                    <?php if (!empty($filtered_teacher['class_assigned'])): ?>
                        <span class="badge bg-white text-primary border ms-1">वर्ग: <?= sanitize($filtered_teacher['class_assigned']) ?> <?= sanitize($filtered_teacher['section']) ?></span>
                    <?php endif; ?>
                    <span class="text-muted small ms-2">(डॅशबोर्डवर फक्त या शिक्षकांचे वर्ग व विद्यार्थी दिसत आहेत)</span>
                </div>
            </div>
            <a href="?tab=<?= $active_tab ?>" class="btn btn-sm btn-outline-primary bg-white fw-semibold">
                <i class="bi bi-x-circle me-1"></i> सर्व शिक्षक व वर्ग दाखवा
            </a>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs (Horizontally scrollable on mobile) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-2 bg-light rounded-3">
            <div class="hub-tabs-scroll no-scrollbar">
                <ul class="nav nav-pills gap-1.5" id="classHubTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $active_tab === 'school' ? 'active bg-primary text-white shadow-sm' : 'bg-white text-dark border' ?>" 
                           href="?tab=school">
                            <i class="bi bi-building-gear me-1"></i> १. शाळा प्रोफाईल व वर्ग रचना
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $active_tab === 'students' ? 'active bg-primary text-white shadow-sm' : 'bg-white text-dark border' ?>" 
                           href="?tab=students&std=<?= $selected_std ?>">
                            <i class="bi bi-people-fill me-1"></i> २. इयत्तानिहाय विद्यार्थी
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $active_tab === 'attendance' ? 'active bg-primary text-white shadow-sm' : 'bg-white text-dark border' ?>" 
                           href="?tab=attendance&std=<?= $selected_std ?>">
                            <i class="bi bi-calendar-check-fill me-1 text-warning"></i> ३. मासिक उपस्थिती
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $active_tab === 'teachers' ? 'active bg-primary text-white shadow-sm' : 'bg-white text-dark border' ?>" 
                           href="?tab=teachers">
                            <i class="bi bi-person-badge-fill me-1"></i> ४. शिक्षक व्यवस्थापन (<?= count($all_teachers) ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $active_tab === 'classes' ? 'active bg-primary text-white shadow-sm' : 'bg-white text-dark border' ?>" 
                           href="?tab=classes">
                            <i class="bi bi-diagram-3 me-1"></i> ५. वर्ग व तुकड्या
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $active_tab === 'subjects' ? 'active bg-primary text-white shadow-sm' : 'bg-white text-dark border' ?>" 
                           href="?tab=subjects&std=<?= $selected_std ?>">
                            <i class="bi bi-book-half me-1"></i> ६. विषय शिक्षक वाटप
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $active_tab === 'headmaster' ? 'active bg-primary text-white shadow-sm' : 'bg-white text-dark border' ?>" 
                           href="?tab=headmaster">
                            <i class="bi bi-pen-fill me-1"></i> ७. मुख्याध्यापक सेटिंग्ज
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- TAB 1: SCHOOL PROFILE & CLASS RANGE (शाळा प्रोफाईल व वर्ग रचना) -->
    <!-- ============================================================== -->
    <?php if ($active_tab === 'school'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-building-gear text-primary me-2"></i> शाळेची मूलभूत माहिती व वर्ग रचना (SaaS Setup Hub)
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="save_school_profile">

                            <!-- CLASS RANGE SELECTOR (Crucial User Requirement) -->
                            <div class="p-3 mb-4 rounded-3 border" style="background: #f8fafc; border-left: 4px solid #0d6efd !important;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-sliders text-primary fs-5"></i>
                                    <h6 class="fw-bold text-primary mb-0">शाळेची वर्ग रचना / इयत्ता मर्यादा (Class Range)</h6>
                                </div>
                                <p class="text-muted small mb-3">
                                    तुमच्या शाळेतील सुरुवातीची व शेवटची इयत्ता निवडा. या निवडीनुसार संपूर्ण पोर्टल (CCE निकाल, HPC प्रगती पत्रक, दैनिक टाचण व हजेरी) मध्ये फक्त तेच वर्ग सक्रिय राहतील.
                                </p>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small">सुरुवातीची इयत्ता (Starting Class)</label>
                                        <select name="lower_class" class="form-select fw-bold" required>
                                            <option value="1" <?= $min_std === 1 ? 'selected' : '' ?>>इयत्ता १ (पहिली - प्राथमिक शाळा)</option>
                                            <option value="5" <?= $min_std === 5 ? 'selected' : '' ?>>इयत्ता ५ (पाचवी - उच्च प्राथमिक/माध्यमिक)</option>
                                            <option value="6" <?= $min_std === 6 ? 'selected' : '' ?>>इयत्ता ६ (सहावी - पूर्व माध्यमिक)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small">शेवटची इयत्ता (Last Class)</label>
                                        <select name="upper_class" class="form-select fw-bold" required>
                                            <option value="4" <?= $max_std === 4 ? 'selected' : '' ?>>इयत्ता ४ (चौथी) — प्राथमिक शाळा (इ. १ ते ४)</option>
                                            <option value="5" <?= $max_std === 5 ? 'selected' : '' ?>>इयत्ता ५ (पाचवी) — प्राथमिक शाळा (इ. १ ते ५)</option>
                                            <option value="7" <?= $max_std === 7 ? 'selected' : '' ?>>इयत्ता ७ (सातवी) — उच्च प्राथमिक शाळा (इ. १ ते ७)</option>
                                            <option value="8" <?= $max_std === 8 ? 'selected' : '' ?>>इयत्ता ८ (आठवी) — पूर्ण प्राथमिक शाळा (इ. १ ते ८)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- SCHOOL NAME & LOGO -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">शाळेचे नाव (मराठीत) <span class="text-danger">*</span></label>
                                    <input type="text" name="name_mr" class="form-control" 
                                           placeholder="उदा. जिल्हा परिषद प्राथमिक शाळा वाठार" 
                                           value="<?= sanitize($school['name_mr'] ?: $school['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">School Name (in English)</label>
                                    <input type="text" name="name" class="form-control" 
                                           placeholder="Z.P. Primary School Wathar" 
                                           value="<?= sanitize($school['name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">शाळेचा लोगो (School Logo)</label>
                                    <input type="file" name="logo" class="form-control" accept="image/*">
                                    <div class="form-text small">PNG, JPG किंवा WebP फॉरमॅट (बोनाफाईड व प्रगती पुस्तकावर छापला जाईल).</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">UDISE क्रमांक (११ अंकी)</label>
                                    <input type="text" name="udise_code" class="form-control font-monospace" 
                                           placeholder="उदा. 27350302306" 
                                           value="<?= sanitize($school['udise_code'] ?? '') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold small">संस्थेचे नाव (Sanstha Name - ऐच्छिक)</label>
                                    <input type="text" name="sanstha" class="form-control" 
                                           placeholder="उदा. जिल्हा परिषद शिक्षण विभाग / रयत शिक्षण संस्था" 
                                           value="<?= sanitize($school['sanstha'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- LOCATION & CONTACT -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">केंद्र (Kendra / Beat)</label>
                                    <input type="text" name="kendra" class="form-control" 
                                           value="<?= sanitize($school['kendra'] ?? '') ?>" placeholder="उदा. वाठार">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">तालुका (Taluka)</label>
                                    <input type="text" name="taluka" class="form-control" 
                                           value="<?= sanitize($school['taluka'] ?? '') ?>" placeholder="उदा. कराड">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">जिल्हा (District)</label>
                                    <input type="text" name="district" class="form-control" 
                                           value="<?= sanitize($school['district'] ?? '') ?>" placeholder="उदा. सातारा">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">पिन कोड (Pin Code)</label>
                                    <input type="text" name="pin_code" class="form-control font-monospace" 
                                           value="<?= sanitize($school['pin_code'] ?? '') ?>" placeholder="उदा. 415110">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">शाळा संपर्क फोन / मोबाईल</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= sanitize($school['phone'] ?? '') ?>" placeholder="उदा. 9876543210">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small">ईमेल पत्ता (Email)</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= sanitize($school['email'] ?? '') ?>" placeholder="उदा. school@gmail.com">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary px-4 shadow-sm fw-bold">
                                <i class="bi bi-save me-1"></i> शाळा माहिती व वर्ग रचना जतन करा
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-check-circle-fill text-success me-2"></i> वर्ग रचना कशी कार्य करते?</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info border-0 rounded-3 small mb-3">
                            <strong>सक्रिय रचना: इ. <?= $min_std ?> ते इ. <?= $max_std ?></strong><br>
                            तुमच्या शाळेत एकूण <strong><?= count($school_range) ?></strong> इयत्ता सक्रिय आहेत.
                        </div>

                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item px-0">
                                <strong>१. HPC प्रगती पत्रक (NEP 2020):</strong><br>
                                <span class="text-muted">जर शाळा इ. १ ते ४ असेल तर फक्त पायाभूत (१-२) व पूर्वतयारी (३-४) दिसेल; इ. ६ ते ८ चा Middle Stage आपोआप लपवला जाईल.</span>
                            </li>
                            <li class="list-group-item px-0">
                                <strong>२. CCE मूल्यमापन:</strong><br>
                                <span class="text-muted">गुण नोंद, हजेरी व सर्वसमावेशक निकालात फक्त शाळेच्या सक्रिय इयत्ता उपलब्ध राहतील.</span>
                            </li>
                            <li class="list-group-item px-0">
                                <strong>३. दैनिक टाचण (Lesson Planner):</strong><br>
                                <span class="text-muted">शिक्षकांना साप्ताहिक व दैनिक नियोजनात फक्त त्यांच्याच शाळेचे वर्ग दिसतील.</span>
                            </li>
                            <li class="list-group-item px-0">
                                <strong>४. बोनाफाईड दाखला:</strong><br>
                                <span class="text-muted">शाळेचे नाव, UDISE कोड, लोगो व पत्ता स्वयंचलित बोनाफाईड प्रमाणपत्रावर छापला जाईल.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    <!-- ============================================================== -->
    <!-- TAB 2: STD-WISE STUDENTS (इयत्तानिहाय विद्यार्थी व्यवस्थापन) -->
    <!-- ============================================================== -->
    <?php elseif ($active_tab === 'students'): ?>
        <!-- Standard Switcher Pills -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2 px-3 bg-white">
                <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2.5">
                    <div class="d-flex align-items-center gap-1.5 min-w-0" style="overflow: hidden;">
                        <span class="small fw-bold text-muted text-nowrap"><i class="bi bi-funnel-fill text-primary"></i> वर्ग:</span>
                        <div class="std-pills-scroll no-scrollbar">
                        <?php 
                        $displayed_range = ($filter_teacher_id > 0 && !empty($teacher_assigned_stds)) 
                            ? array_intersect_key($school_range, $teacher_assigned_stds) 
                            : $school_range;
                        if (empty($displayed_range)) $displayed_range = $school_range;
                        ?>
                        <?php foreach ($displayed_range as $s_num => $s_lbl): ?>
                            <a href="?tab=students&std=<?= $s_num ?><?= $filter_teacher_id ? '&teacher_id='.$filter_teacher_id : '' ?>" 
                               class="btn btn-sm <?= $selected_std === $s_num ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary' ?> rounded-pill px-3">
                                <?= $s_lbl ?>
                                <span class="badge <?= $selected_std === $s_num ? 'bg-white text-primary' : 'bg-secondary' ?> ms-1">
                                    <?= $std_counts[$s_num]['total'] ?? 0 ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($filter_teacher_id > 0): ?>
                            <a href="?tab=students&std=<?= $selected_std ?>" class="btn btn-sm btn-link text-decoration-none small text-muted text-nowrap">
                                (सर्व)
                            </a>
                        <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($selected_std <= 2): ?>
                            <a href="<?= APP_URL ?>/hpc/index.php?std=<?= $selected_std ?>" class="btn btn-sm btn-outline-warning text-dark fw-bold">
                                <i class="bi bi-card-checklist text-warning"></i> HPC प्रगती पत्रक
                            </a>
                        <?php else: ?>
                            <a href="<?= APP_URL ?>/cce/marks_entry.php?std=<?= $selected_std ?>" class="btn btn-sm btn-outline-success fw-bold">
                                <i class="bi bi-journal-plus"></i> CCE गुण नोंद
                            </a>
                        <?php endif; ?>
                        <a href="<?= APP_URL ?>/modules/classes/bonafide.php?std=<?= $selected_std ?>&all=1" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-printer"></i> बोनाफाईड
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class Summary & Student List -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex flex-column flex-lg-row justify-content-between align-items-stretch align-items-lg-center gap-3 border-bottom">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>
                        इयत्ता <?= std_label($selected_std) ?> — विद्यार्थी यादी
                    </h5>
                    <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1">
                        एकूण: <?= count($active_students) ?> (मुले: <?= $std_counts[$selected_std]['boys'] ?? 0 ?>, मुली: <?= $std_counts[$selected_std]['girls'] ?? 0 ?>)
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center class-header-actions">
                    <!-- View Mode Toggle: Cards vs Table -->
                    <div class="btn-group btn-group-sm shadow-xs" role="group" id="studentViewSwitcher">
                        <button type="button" class="btn btn-outline-primary" id="btnShowCards" onclick="setStudentView('cards')" title="कार्ड व्ह्यू (मोबाईलसाठी सोपे)">
                            <i class="bi bi-grid-fill me-1"></i> कार्ड
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btnShowTable" onclick="setStudentView('table')" title="टेबल व्ह्यू">
                            <i class="bi bi-table me-1"></i> टेबल
                        </button>
                    </div>

                    <?php if ($school_quota['can_add']): ?>
                        <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="openStudentModal(0, <?= $selected_std ?>)">
                            <i class="bi bi-plus-circle me-1"></i> नवीन विद्यार्थी
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-danger fw-semibold" onclick="alert('शाळेची विद्यार्थी मर्यादा पूर्ण झाली आहे (जास्तीत जास्त: <?= $school_quota['max'] ?> विद्यार्थी). कृपया पुढील विद्यार्थी जोडण्यासाठी योजना अपग्रेड करा.')" title="विद्यार्थी मर्यादा पूर्ण झाली आहे">
                            <i class="bi bi-lock-fill me-1"></i> नवीन विद्यार्थी (मर्यादा पूर्ण)
                        </button>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/modules/classes/import_udise.php" class="btn btn-sm btn-warning fw-bold text-dark shadow-xs">
                        <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> UDISE+ आयात
                    </a>
                    <button type="button" class="btn btn-sm btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#csvUploadModal">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV आयात
                    </button>
                    <a href="?action=download_student_csv_template" class="btn btn-sm btn-outline-secondary" title="नमुना CSV डाउनलोड करा">
                        <i class="bi bi-download"></i> नमुना CSV
                    </a>
                    <a href="?tab=attendance&std=<?= $selected_std ?><?= $filter_teacher_id ? '&teacher_id='.$filter_teacher_id : '' ?>" class="btn btn-sm btn-outline-warning text-dark fw-semibold">
                        <i class="bi bi-calendar-check me-1"></i> मासिक उपस्थिती
                    </a>
                </div>
            </div>

            <div class="card-body p-0">
                <!-- ============================================================== -->
                <!-- MOBILE VIEW: Touch-Friendly Student Cards                      -->
                <!-- Zero Horizontal Scrolling — Easy to Use on Mobile Screens       -->
                <!-- ============================================================== -->
                <div id="studentCardsContainer" class="p-2 bg-light bg-opacity-50">
                    <?php if (empty($active_students)): ?>
                        <div class="text-center py-5 text-muted bg-white rounded-3 p-4">
                            <i class="bi bi-people display-6 d-block mb-2 text-secondary"></i>
                            या इयत्तेमध्ये अद्याप कोणताही विद्यार्थी जोडलेला नाही.<br>
                            <button type="button" class="btn btn-primary btn-sm mt-3" onclick="openStudentModal(0, <?= $selected_std ?>)">
                                <i class="bi bi-plus-circle"></i> पहिला विद्यार्थी जोडा
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2.5">
                            <?php foreach ($active_students as $st): 
                                $is_girl = in_array(trim((string)$st['gender']), ['मुलगी', 'F', 'स्त्री'], true);
                                $dob_show = (!empty($st['date_of_birth']) && $st['date_of_birth'] !== '0000-00-00') ? date('d/m/Y', strtotime($st['date_of_birth'])) : ($st['dob'] ?: '—');
                            ?>
                            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                                <!-- Card Header: Roll No, Avatar, Name & Gender -->
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="text-center">
                                            <span class="badge bg-light text-dark border px-2 py-1 fw-bold fs-6 d-block" title="हजेरी क्रमांक">
                                                <?= sanitize($st['roll_no'] ?: '—') ?>
                                            </span>
                                            <span class="text-muted" style="font-size: 0.65rem;">हजेरी</span>
                                        </div>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold <?= $is_girl ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>" style="width: 40px; height: 40px; font-size: 0.95rem; flex-shrink: 0;">
                                            <?= mb_substr($st['name_mr'] ?: $st['name'] ?: 'वि', 0, 1) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-6 lh-sm"><?= sanitize($st['name_mr'] ?: $st['name']) ?></div>
                                            <?php if (!empty($st['name']) && $st['name'] !== $st['name_mr']): ?>
                                                <div class="small text-muted font-monospace"><?= sanitize($st['name']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($is_girl): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">मुलगी</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1">मुलगा</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Key Meta Badges -->
                                <div class="d-flex flex-wrap align-items-center gap-1.5 mb-2 small">
                                    <span class="badge bg-secondary-subtle text-secondary border font-monospace">
                                        GR: <?= sanitize($st['gr_no'] ?: $st['registration_no'] ?: '—') ?>
                                    </span>
                                    <?php if (!empty($st['caste_category'])): ?>
                                        <span class="badge bg-warning-subtle text-dark border">
                                            <?= sanitize($st['caste_category']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($dob_show) && $dob_show !== '—'): ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-cake2 text-warning me-1"></i><?= $dob_show ?> <?= !empty($st['age']) ? "($st[age] वर्षे)" : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Parents & Contact Bar -->
                                <?php if (!empty($st['phone']) || !empty($st['mother_name']) || !empty($st['father_name'])): ?>
                                <div class="p-2 rounded-2 bg-light mb-2 d-flex align-items-center justify-content-between gap-2 text-xs small">
                                    <div class="lh-sm">
                                        <?php if (!empty($st['mother_name'])): ?>
                                            <div><strong>आई:</strong> <?= sanitize($st['mother_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($st['father_name'])): ?>
                                            <div><strong>वडील:</strong> <?= sanitize($st['father_name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($st['phone'])): ?>
                                        <a href="tel:<?= sanitize($st['phone']) ?>" class="btn btn-sm btn-success px-2.5 py-1 text-nowrap fw-bold shadow-xs">
                                            <i class="bi bi-telephone-fill me-1"></i> कॉल
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Hobbies / Aspiration (if present) -->
                                <?php if (!empty($st['favourite_sport']) || !empty($st['aspiration'])): ?>
                                <div class="mb-2 small text-muted d-flex flex-wrap gap-1">
                                    <?php if (!empty($st['favourite_sport'])): ?>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-trophy text-warning me-1"></i><?= sanitize($st['favourite_sport']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($st['aspiration'])): ?>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-star-fill text-primary me-1"></i><?= sanitize($st['aspiration']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Mobile Touch Actions (Big 44px+ hit targets) -->
                                <div class="d-flex align-items-center gap-2 pt-2 border-top">
                                    <a href="<?= APP_URL ?>/modules/classes/bonafide.php?student_id=<?= $st['id'] ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-success flex-fill py-1.5 fw-semibold text-center">
                                        <i class="bi bi-file-earmark-check me-1"></i> बोनाफाईड
                                    </a>
                                    <button type="button" class="btn btn-sm btn-primary flex-fill py-1.5 fw-semibold" 
                                            onclick="editStudent(<?= htmlspecialchars(json_encode($st, JSON_UNESCAPED_UNICODE)) ?>)">
                                        <i class="bi bi-pencil-square me-1"></i> संपादित करा
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('तुम्हाला नक्की या विद्यार्थ्याला वगळायचे आहे का?');">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete_student">
                                        <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1.5 px-3" title="वगळा">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================================== -->
                <!-- FULL DATA TABLE VIEW (Responsive with Sticky Col & Smooth Touch)-->
                <!-- ============================================================== -->
                <div id="studentTableContainer" class="table-responsive">
                    <div class="d-lg-none alert alert-light border-bottom border-0 rounded-0 py-1.5 px-3 mb-0 text-muted small d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-arrows-expand me-1 text-primary"></i> सर्व १० स्तंभ पाहण्यासाठी उजवीकडे स्क्रोल करा</span>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" onclick="setStudentView('cards')">📱 कार्ड व्ह्यू पहा</button>
                    </div>
                    <table class="table table-hover align-middle mb-0" id="studentsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;" class="text-center">हजेरी क्र.</th>
                                <th style="width: 90px;">GR क्र.</th>
                                <th>विद्यार्थ्याचे नाव (मराठी व इंग्रजी)</th>
                                <th style="width: 90px;" class="text-center">लिंग</th>
                                <th>पालकांचे नाव / मोबाईल</th>
                                <th>जन्मतारीख व जन्मस्थळ</th>
                                <th>जात व प्रवर्ग</th>
                                <th>आयडी (APAAR / PEN / आधार)</th>
                                <th>आवड / छंद (HPC)</th>
                                <th style="width: 200px;" class="text-end">कृती</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_students)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="bi bi-people display-6 d-block mb-2 text-secondary"></i>
                                        या इयत्तेमध्ये अद्याप कोणताही विद्यार्थी जोडलेला नाही.<br>
                                        <button type="button" class="btn btn-primary btn-sm mt-3" onclick="openStudentModal(0, <?= $selected_std ?>)">
                                            <i class="bi bi-plus-circle"></i> पहिला विद्यार्थी जोडा
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_students as $st): 
                                    $is_girl = in_array(trim((string)$st['gender']), ['मुलगी', 'F', 'स्त्री'], true);
                                    $dob_show = (!empty($st['date_of_birth']) && $st['date_of_birth'] !== '0000-00-00') ? date('d/m/Y', strtotime($st['date_of_birth'])) : ($st['dob'] ?: '—');
                                ?>
                                <tr>
                                    <td class="text-center fw-bold fs-6">
                                        <span class="badge bg-light text-dark border"><?= sanitize($st['roll_no'] ?: '—') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border font-monospace">
                                            <?= sanitize($st['gr_no'] ?: $st['registration_no'] ?: '—') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php 
                                            $raw_photo = trim((string)($st['photo'] ?? ''));
                                            $photo_web = '';
                                            if ($raw_photo !== '') {
                                                $norm_p = str_replace('\\', '/', $raw_photo);
                                                $clean_p = ltrim($norm_p, '/');
                                                if (@file_exists(__DIR__ . '/../../' . $clean_p)) {
                                                    $photo_web = APP_URL . '/' . $clean_p;
                                                } elseif (@file_exists(__DIR__ . '/../../uploads/photos/' . basename($clean_p))) {
                                                    $photo_web = APP_URL . '/uploads/photos/' . basename($clean_p);
                                                } elseif (preg_match('/^https?:\/\//i', $raw_photo)) {
                                                    $photo_web = $raw_photo;
                                                } else {
                                                    $photo_web = APP_URL . '/' . (str_starts_with($clean_p, 'uploads/') ? $clean_p : 'uploads/photos/' . basename($clean_p));
                                                }
                                            }
                                            ?>
                                            <?php if (!empty($photo_web)): ?>
                                                <img src="<?= htmlspecialchars($photo_web, ENT_QUOTES, 'UTF-8') ?>" class="rounded-circle border" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                                <div class="rounded-circle align-items-center justify-content-center fw-bold <?= $is_girl ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>" style="display:none; width: 36px; height: 36px; font-size: 0.85rem;">
                                                    <?= mb_substr($st['name_mr'] ?: $st['name'] ?: 'वि', 0, 1) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold <?= $is_girl ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                    <?= mb_substr($st['name_mr'] ?: $st['name'] ?: 'वि', 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold text-dark"><?= sanitize($st['name_mr'] ?: $st['name']) ?></div>
                                                <?php if (!empty($st['name']) && $st['name'] !== $st['name_mr']): ?>
                                                    <div class="small text-muted font-monospace"><?= sanitize($st['name']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_girl): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">मुलगी</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1">मुलगा</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?php if (!empty($st['mother_name'])): ?>
                                                <div><strong>आई:</strong> <?= sanitize($st['mother_name']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($st['father_name'])): ?>
                                                <div><strong>वडील:</strong> <?= sanitize($st['father_name']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($st['phone'])): ?>
                                                <div class="text-muted"><i class="bi bi-telephone text-success"></i> <?= sanitize($st['phone']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div><strong><?= $dob_show ?></strong> <?= !empty($st['age']) ? "($st[age] वर्षे)" : '' ?></div>
                                            <div class="text-muted"><?= sanitize($st['birth_place'] ?: $st['village'] ?: '—') ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div><?= sanitize($st['religion'] ?: '—') ?> · <?= sanitize($st['caste'] ?: '—') ?></div>
                                            <span class="badge bg-warning-subtle text-dark border px-2 py-0">
                                                <?= sanitize($st['caste_category'] ?: 'खुला') ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-muted" style="font-size: 0.78rem;">
                                            <?php if (!empty($st['apaar_id'])): ?>
                                                <div>APAAR: <span class="font-monospace text-dark"><?= sanitize($st['apaar_id']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($st['student_pen'])): ?>
                                                <div>PEN: <span class="font-monospace text-dark"><?= sanitize($st['student_pen']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($st['aadhar_no'])): ?>
                                                <div>आधार: <span class="font-monospace text-dark"><?= sanitize($st['aadhar_no']) ?></span></div>
                                            <?php endif; ?>
                                            <?php if (empty($st['apaar_id']) && empty($st['student_pen']) && empty($st['aadhar_no'])): ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <?php if (!empty($st['favourite_sport']) || !empty($st['favourite_subject']) || !empty($st['aspiration'])): ?>
                                                <?php if (!empty($st['favourite_sport'])): ?>
                                                    <span class="badge bg-light text-dark border me-1"><i class="bi bi-trophy text-warning"></i> <?= sanitize($st['favourite_sport']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($st['aspiration'])): ?>
                                                    <span class="badge bg-light text-dark border"><i class="bi bi-star-fill text-primary"></i> <?= sanitize($st['aspiration']) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= APP_URL ?>/modules/classes/bonafide.php?student_id=<?= $st['id'] ?>" 
                                               target="_blank" class="btn btn-outline-success" title="बोनाफाईड प्रमाणपत्र प्रिंट करा">
                                                <i class="bi bi-file-earmark-check"></i> बोनाफाईड
                                            </a>
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editStudent(<?= htmlspecialchars(json_encode($st, JSON_UNESCAPED_UNICODE)) ?>)" 
                                                    title="माहिती संपादित करा">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('तुम्हाला नक्की या विद्यार्थ्याला वगळायचे आहे का?');">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="delete_student">
                                                <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="वगळा">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- ============================================================== -->
    <!-- TAB 3: MONTHLY ATTENDANCE (मासिक उपस्थिती नोंदणी) -->
    <!-- ============================================================== -->
    <?php elseif ($active_tab === 'attendance'): ?>
        <!-- Standard Switcher for Attendance -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2 px-3 bg-white">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                        <span class="small fw-bold text-muted me-2"><i class="bi bi-funnel-fill text-primary"></i> इयत्ता निवडा:</span>
                        <?php foreach ($school_range as $s_num => $s_lbl): ?>
                            <a href="?tab=attendance&std=<?= $s_num ?>&month=<?= $sel_month ?>" 
                               class="btn btn-sm <?= $selected_std === $s_num ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary' ?> rounded-pill px-3">
                                <?= $s_lbl ?>
                                <span class="badge <?= $selected_std === $s_num ? 'bg-white text-primary' : 'bg-secondary' ?> ms-1">
                                    <?= $std_counts[$s_num]['total'] ?? 0 ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#workingDaysModal">
                            <i class="bi bi-pencil-square text-warning"></i> शाळेचे कामाचे दिवस बदला
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Month Filter Toolbar -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-2 bg-light">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small fw-bold text-dark me-1"><i class="bi bi-calendar-month text-primary"></i> महिना:</span>
                    <a href="?tab=attendance&std=<?= $selected_std ?>&month=0" 
                       class="btn btn-sm <?= $sel_month === 0 ? 'btn-dark' : 'btn-outline-dark' ?> rounded-pill">
                        सर्व महिने (एकत्रित)
                    </a>
                    <?php foreach ($months_map as $m_num => $m_label): ?>
                        <a href="?tab=attendance&std=<?= $selected_std ?>&month=<?= $m_num ?>" 
                           class="btn btn-sm <?= $sel_month === $m_num ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill">
                            <?= $m_label ?> 
                            <span class="badge bg-light text-dark border ms-1" style="font-size:0.7rem;">
                                <?= (int)($working_monthly[$m_num] ?? 0) ?> दि.
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Attendance Entry Form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                <div>
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-calendar-check-fill text-warning me-2"></i>
                        इयत्ता <?= std_label($selected_std) ?> — मासिक उपस्थिती नोंद
                    </h5>
                    <span class="small text-muted">
                        <?= $sel_month > 0 ? $months_map[$sel_month] . " महिना (शाळेचे कामाचे दिवस: " . ((int)($working_monthly[$sel_month] ?? 0)) . ")" : "सर्व महिने (एकूण वार्षिक कामाचे दिवस: " . $total_school_working_days . ")" ?>
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="fillFullAttendance()">
                        <i class="bi bi-check-all"></i> सर्वांना पूर्ण उपस्थिती द्या
                    </button>
                    <button type="submit" form="attendanceForm" class="btn btn-sm btn-primary px-3 shadow-sm">
                        <i class="bi bi-save me-1"></i> उपस्थिती जतन करा
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="save_monthly_attendance">
                    <input type="hidden" name="std" value="<?= $selected_std ?>">
                    <input type="hidden" name="month" value="<?= $sel_month ?>">

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">रोल</th>
                                    <th style="width: 80px;">GR क्र.</th>
                                    <th style="text-align: left; min-width: 200px;">विद्यार्थ्याचे नाव</th>
                                    <th style="width: 70px;">लिंग</th>
                                    <?php if ($sel_month > 0): ?>
                                        <th style="width: 140px;"><?= $months_map[$sel_month] ?> उपस्थिती (कमाल <?= (int)($working_monthly[$sel_month] ?? 0) ?>)</th>
                                        <th style="width: 100px;">टक्केवारी (%)</th>
                                    <?php else: ?>
                                        <?php foreach ($months_map as $m_num => $m_lbl): ?>
                                            <th style="width: 75px; font-size: 0.85rem;" title="<?= $m_lbl ?> (कमाल <?= (int)($working_monthly[$m_num] ?? 0) ?> दिवस)">
                                                <?= $m_lbl ?><br>
                                                <span class="text-muted fw-normal" style="font-size:0.72rem;"><?= (int)($working_monthly[$m_num] ?? 0) ?> दि.</span>
                                            </th>
                                        <?php endforeach; ?>
                                        <th style="width: 90px; background: #e8f5e9;">एकूण</th>
                                        <th style="width: 90px; background: #e8f5e9;">टक्केवारी</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_students)): ?>
                                    <tr>
                                        <td colspan="15" class="py-5 text-muted">
                                            या इयत्तेत विद्यार्थी उपलब्ध नाहीत.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($active_students as $st): 
                                        $sid = (int) $st['id'];
                                        $is_girl = in_array(trim((string)$st['gender']), ['मुलगी', 'F', 'स्त्री'], true);
                                        $s_tot_pres = 0;
                                        $s_tot_work = 0;
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= sanitize($st['roll_no'] ?: '—') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= sanitize($st['gr_no'] ?: '—') ?></span></td>
                                        <td style="text-align: left;">
                                            <div class="fw-bold text-dark"><?= sanitize($st['name_mr'] ?: $st['name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $is_girl ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>" style="font-size:0.75rem;">
                                                <?= $is_girl ? 'मुलगी' : 'मुलगा' ?>
                                            </span>
                                        </td>

                                        <?php if ($sel_month > 0): 
                                            $cur_pres = $att_records[$sid][$sel_month] ?? '';
                                            $max_w = (int)($working_monthly[$sel_month] ?? 0);
                                            $pct = ($max_w > 0 && $cur_pres !== '') ? round(($cur_pres / $max_w) * 100, 1) : 0;
                                        ?>
                                            <td>
                                                <input type="number" 
                                                       name="p[<?= $sid ?>][<?= $sel_month ?>]" 
                                                       class="form-control form-control-sm text-center fw-bold att-input" 
                                                       data-max="<?= $max_w ?>"
                                                       min="0" max="<?= $max_w ?>" 
                                                       value="<?= $cur_pres ?>" 
                                                       placeholder="0">
                                            </td>
                                            <td>
                                                <span class="badge bg-info-subtle text-dark border px-2 py-1 fs-6">
                                                    <?= $pct ?>%
                                                </span>
                                            </td>
                                        <?php else: ?>
                                            <?php foreach ($months_map as $m_num => $m_lbl): 
                                                $cur_pres = $att_records[$sid][$m_num] ?? '';
                                                $max_w = (int)($working_monthly[$m_num] ?? 0);
                                                if ($cur_pres !== '') $s_tot_pres += (int)$cur_pres;
                                                $s_tot_work += $max_w;
                                            ?>
                                                <td class="p-1">
                                                    <input type="number" 
                                                           name="p[<?= $sid ?>][<?= $m_num ?>]" 
                                                           class="form-control form-control-sm text-center att-input px-1" 
                                                           data-max="<?= $max_w ?>"
                                                           min="0" max="<?= $max_w ?>" 
                                                           value="<?= $cur_pres ?>" 
                                                           style="font-size: 0.85rem;">
                                                </td>
                                            <?php endforeach; 
                                            $tot_pct = ($s_tot_work > 0) ? round(($s_tot_pres / $s_tot_work) * 100, 1) : 0;
                                            ?>
                                            <td class="fw-bold bg-light text-primary"><?= $s_tot_pres ?> / <?= $s_tot_work ?></td>
                                            <td class="fw-bold bg-light <?= $tot_pct >= 75 ? 'text-success' : 'text-danger' ?>">
                                                <?= $tot_pct ?>%
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            येथे भरलेली उपस्थिती थेट CCE गुणपत्रक, नोंदवही व HPC प्रगती पत्रकावर स्वयंचलित परावर्तित होते.
                        </div>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="bi bi-save me-1"></i> उपस्थिती जतन करा
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ============================================================== -->
    <!-- TAB 4: TEACHERS MANAGEMENT (शिक्षक व्यवस्थापन) -->
    <!-- ============================================================== -->
    <?php elseif ($active_tab === 'teachers'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-person-badge-fill text-primary me-2"></i> शाळेतील शिक्षक व कर्मचारी यादी
                    </h5>
                    <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1">
                        एकूण: <?= count($all_teachers) ?> शिक्षक
                    </span>
                </div>
                <button type="button" class="btn btn-sm btn-primary" onclick="openTeacherModal(0)">
                    <i class="bi bi-person-plus-fill me-1"></i> नवीन शिक्षक जोडा
                </button>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">अ.क्र.</th>
                                <th>शिक्षकाचे पूर्ण नाव</th>
                                <th>शिक्षक कोड / शालार्थ ID</th>
                                <th>पद / भूमिका</th>
                                <th>नेमलेला वर्ग (Class Teacher)</th>
                                <th>मोबाईल क्रमांक</th>
                                <th>ईमेल</th>
                                <th style="width: 140px;" class="text-end">कृती</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_teachers)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-x display-6 d-block mb-2 text-secondary"></i>
                                        शाळेमध्ये अद्याप कोणताही शिक्षक जोडलेला नाही.<br>
                                        <button type="button" class="btn btn-primary btn-sm mt-3" onclick="openTeacherModal(0)">
                                            <i class="bi bi-person-plus"></i> पहिला शिक्षक जोडा
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_teachers as $t_idx => $t): ?>
                                <tr>
                                    <td><?= $t_idx + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= sanitize($t['name_mr'] ?: $t['name']) ?></div>
                                        <?php if (!empty($t['name']) && $t['name'] !== $t['name_mr']): ?>
                                            <div class="small text-muted font-monospace"><?= sanitize($t['name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">
                                            <?= sanitize($t['teacher_code'] ?: '—') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $t['role'] === 'principal' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?> border px-2 py-1">
                                            <?= $t['role'] === 'principal' ? 'मुख्याध्यापक' : ($t['role'] === 'clerk' ? 'लिपिक' : 'शिक्षक') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($t['class_assigned'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                                <?= sanitize($t['class_assigned']) ?> <?= !empty($t['section']) ? '(तुकडी ' . $t['section'] . ')' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($t['phone'])): ?>
                                            <span class="text-dark"><i class="bi bi-telephone text-success me-1"></i><?= sanitize($t['phone']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= sanitize($t['email'] ?: '—') ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editTeacher(<?= htmlspecialchars(json_encode($t, JSON_UNESCAPED_UNICODE)) ?>)" 
                                                    title="संपादित करा">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('तुम्हाला नक्की या शिक्षकाला वगळायचे आहे का?');">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="delete_teacher">
                                                <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="वगळा">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- ============================================================== -->
    <!-- TAB 5: CLASSES & DIVISIONS (वर्ग व तुकड्या) -->
    <!-- ============================================================== -->
    <?php elseif ($active_tab === 'classes'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns-reverse text-primary me-2"></i> वर्ग व तुकड्यांची यादी (इ. <?= $min_std ?> ते <?= $max_std ?>)</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="bi bi-plus-circle"></i> नवीन तुकडी जोडा
                </button>
            </div>
            <div class="card-body p-0">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_class_teachers">

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">इयत्ता</th>
                                    <th>वर्गाचे नाव</th>
                                    <th style="width: 100px;">तुकडी</th>
                                    <th style="width: 130px;">माध्यम</th>
                                    <th style="width: 120px;">विद्यार्थी संख्या</th>
                                    <th>वर्गशिक्षक (Class Teacher)</th>
                                    <th style="width: 180px;" class="text-end">कृती</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($classes)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">कोणतेही वर्ग आढळले नाहीत.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classes as $c): 
                                        $c_std = (int)$c['std'];
                                        if ($filter_teacher_id > 0 && !isset($teacher_assigned_stds[$c_std]) && (int)$c['class_teacher_id'] !== $filter_teacher_id) {
                                            continue; // Show only that teacher's classes when teacher filter is active
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 fs-6">
                                                इ. <?= $c['std'] ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?= sanitize($c['name']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1">
                                                तुकडी <?= sanitize($c['section'] ?: 'अ') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <select name="medium[<?= $c['id'] ?>]" class="form-select form-select-sm">
                                                <option value="मराठी" <?= ($c['medium'] ?? '') === 'मराठी' ? 'selected' : '' ?>>मराठी</option>
                                                <option value="इंग्रजी" <?= ($c['medium'] ?? '') === 'इंग्रजी' ? 'selected' : '' ?>>इंग्रजी</option>
                                                <option value="उर्दू" <?= ($c['medium'] ?? '') === 'उर्दू' ? 'selected' : '' ?>>उर्दू</option>
                                                <option value="हिंदी" <?= ($c['medium'] ?? '') === 'हिंदी' ? 'selected' : '' ?>>हिंदी</option>
                                            </select>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                                <i class="bi bi-people"></i> <?= $std_counts[$c_std]['total'] ?? 0 ?> मुले
                                            </span>
                                        </td>
                                        <td>
                                            <select name="class_teacher[<?= $c['id'] ?>]" class="form-select form-select-sm">
                                                <option value="">-- वर्गशिक्षक निवडा --</option>
                                                <?php foreach ($all_teachers as $t): ?>
                                                    <option value="<?= $t['id'] ?>" <?= ((int)$c['class_teacher_id'] === (int)$t['id']) ? 'selected' : '' ?>>
                                                        <?= sanitize($t['name_mr'] ?: $t['name']) ?> (<?= sanitize($t['teacher_code']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="?tab=subjects&std=<?= $c['std'] ?>&division=<?= urlencode($c['section'] ?: 'अ') ?>" class="btn btn-sm btn-outline-info text-dark" title="या वर्गासाठी विषय शिक्षक नेमणूक करा">
                                                <i class="bi bi-person-video3"></i> विषय शिक्षक
                                            </a>
                                            <a href="?tab=students&std=<?= $c['std'] ?>&division=<?= urlencode($c['section'] ?: 'अ') ?>" class="btn btn-sm btn-outline-primary" title="विद्यार्थी यादी पहा">
                                                <i class="bi bi-people"></i> विद्यार्थी
                                            </a>
                                            <a href="?tab=attendance&std=<?= $c['std'] ?>" class="btn btn-sm btn-outline-warning text-dark" title="मासिक उपस्थिती">
                                                <i class="bi bi-calendar-check"></i> हजेरी
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            <i class="bi bi-info-circle"></i> वर्गशिक्षक बदलल्यानंतर खालील जतन करा बटण दाबा.
                        </span>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="bi bi-save me-1"></i> वर्गशिक्षक बदल जतन करा
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ============================================================== -->
    <!-- TAB 6: SUBJECT TEACHERS (विषय शिक्षक वाटप) -->
    <!-- ============================================================== -->
    <?php elseif ($active_tab === 'subjects'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body bg-light py-3 border-bottom">
                <form method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="subjects">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">इयत्ता निवडा</label>
                        <select name="std" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($school_range as $s_num => $s_lbl): ?>
                                <option value="<?= $s_num ?>" <?= $s_num === $selected_std ? 'selected' : '' ?>><?= $s_lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">तुकडी निवडा</label>
                        <select name="division" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($available_divisions as $av_div): ?>
                                <option value="<?= sanitize($av_div) ?>" <?= ($target_div === $av_div) ? 'selected' : '' ?>>तुकडी <?= sanitize($av_div) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> विषय यादी दाखवा</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <form method="POST" action="?tab=subjects&std=<?= $selected_std ?>&division=<?= urlencode($target_div) ?>">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="save_subject_teachers">
                    <input type="hidden" name="std" value="<?= $selected_std ?>">
                    <input type="hidden" name="division" value="<?= sanitize($target_div) ?>">

                    <?php
                    $ct_name = '';
                    foreach ($all_teachers as $t_item) {
                        if ((int)$t_item['id'] === $current_class_teacher_id) {
                            $ct_name = $t_item['name_mr'] ?: $t_item['name'];
                            break;
                        }
                    }
                    ?>
                    <div class="px-3 py-2 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="small text-muted">
                            <?php if ($current_class_teacher_id > 0): ?>
                                <i class="bi bi-info-circle-fill text-primary me-1"></i>
                                या वर्गाचे वर्गशिक्षक: <strong class="text-dark"><?= sanitize($ct_name) ?></strong>. वर्गशिक्षक सर्व विषयांना आपोआप लागू केले आहेत. आपण आवश्यकतेनुसार कोणत्याही विषयाचा शिक्षक बदलू शकता.
                            <?php else: ?>
                                <i class="bi bi-exclamation-circle text-warning me-1"></i>
                                या वर्गासाठी वर्गशिक्षक नेमलेला नाही. आपण खाली प्रत्येक विषयासाठी स्वतंत्र शिक्षक निवडू शकता किंवा 'वर्ग रचना व तुकड्या' टॅबमध्ये वर्गशिक्षक नेमू शकता.
                            <?php endif; ?>
                        </div>
                        <?php if ($current_class_teacher_id > 0): ?>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary bg-white shadow-sm" onclick="applyClassTeacher(<?= $current_class_teacher_id ?>)">
                                <i class="bi bi-check2-all me-1"></i>सर्व विषयांना वर्गशिक्षक लावा
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" onclick="clearAllSubjectTeachers()">
                                <i class="bi bi-x-circle me-1"></i>सर्व रिक्त करा
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 70px;">अ.क्र.</th>
                                    <th>विषयाचे नाव</th>
                                    <th>संक्षिप्त नाव</th>
                                    <th>आकारिक (FE) / संकलित (SE) गुण</th>
                                    <th style="width: 350px;">नेमलेले विषय शिक्षक (Subject Teacher)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cce_subjects_list)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            या इयत्तेसाठी कोणतेही विषय उपलब्ध नाहीत.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cce_subjects_list as $idx => $sub): 
                                        $sub_id = (int) $sub['id'];
                                        $assigned_tid = $sub_teachers_map[$sub_id] ?? 0;
                                    ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= sanitize($sub['name_mr']) ?></div>
                                            <span class="badge bg-light text-muted border"><?= sanitize($sub['subject_code']) ?></span>
                                        </td>
                                        <td><?= sanitize($sub['short_name'] ?: '-') ?></td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border">FE: <?= $sub['fe_max'] ?></span> + 
                                            <span class="badge bg-primary-subtle text-primary border">SE: <?= $sub['se_max'] ?></span>
                                        </td>
                                        <td>
                                            <select name="teacher[<?= $sub_id ?>]" class="form-select form-select-sm subject-teacher-select">
                                                <option value="0">-- शिक्षक नियुक्त नाही --</option>
                                                <?php foreach ($all_teachers as $t): ?>
                                                    <option value="<?= $t['id'] ?>" <?= ($assigned_tid === (int)$t['id']) ? 'selected' : '' ?>>
                                                        <?= sanitize($t['name_mr'] ?: $t['name']) ?> (<?= sanitize($t['teacher_code']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <i class="bi bi-info-circle"></i> हे विषय शिक्षक CCE मूल्यमापन, हजेरी आणि प्रगती पुस्तकात आपोआप दिसतील.
                        </div>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="bi bi-save me-1"></i> विषय शिक्षक वाटप जतन करा
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function applyClassTeacher(tid) {
            document.querySelectorAll('.subject-teacher-select').forEach(function(el) {
                el.value = tid;
            });
        }
        function clearAllSubjectTeachers() {
            document.querySelectorAll('.subject-teacher-select').forEach(function(el) {
                el.value = '0';
            });
        }
        </script>

    <!-- ============================================================== -->
    <!-- TAB 7: HEADMASTER SETTINGS -->
    <!-- ============================================================== -->
    <?php elseif ($active_tab === 'headmaster'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-pen text-primary me-2"></i> मुख्याध्यापक, निकाल दिनांक व शेरा सेटिंग्ज
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="save_headmaster_settings">

                            <div class="mb-3">
                                <label class="form-label fw-bold">सत्र १ मुख्याध्यापक / वर्गशिक्षक स्वाक्षरी नाव</label>
                                <input type="text" name="sem1_headmaster_name" class="form-control" 
                                       placeholder="उदा. श्री. आर. के. पाटील (मुख्याध्यापक)" 
                                       value="<?= sanitize($defaults['sem1_headmaster_name'] ?? '') ?>">
                                <div class="form-text">हे नाव प्रथम सत्राच्या प्रगती पुस्तक, निकाल व नोंदवहीवर स्वाक्षरीच्या ठिकाणी छापले जाईल.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">सत्र २ मुख्याध्यापक / वर्गशिक्षक स्वाक्षरी नाव</label>
                                <input type="text" name="sem2_headmaster_name" class="form-control" 
                                       placeholder="उदा. श्री. आर. के. पाटील (मुख्याध्यापक)" 
                                       value="<?= sanitize($defaults['sem2_headmaster_name'] ?? '') ?>">
                                <div class="form-text">हे नाव द्वितीय सत्राच्या प्रगती पुस्तक, निकाल व नोंदवहीवर छापले जाईल.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">वार्षिक निकाल जाहीर दिनांक (Result Date)</label>
                                <input type="date" name="result_date" class="form-control" style="max-width: 250px;" 
                                       value="<?= sanitize($defaults['result_date'] ?? '') ?>">
                                <div class="form-text">प्रगती पुस्तक आणि सर्वसमावेशक निकाल पत्रकावर छापला जाणारा अधिकृत दिनांक.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">नवीन शैक्षणिक वर्ष सुरू होण्याची सूचना / शेरा</label>
                                <textarea name="next_year_notice" class="form-control" rows="3" 
                                          placeholder="उदा. पुढील शैक्षणिक वर्ष १५ जून पासून सुरू होईल. सर्व पुस्तके पहिल्या दिवशी वाटप करण्यात येतील."><?= sanitize($defaults['next_year_notice'] ?? '') ?></textarea>
                                <div class="form-text">हा शेरा प्रगती पुस्तकाच्या शेवटच्या पानावर पालकांसाठी दिसेल.</div>
                            </div>

                            <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                <i class="bi bi-save me-1"></i> मुख्याध्यापक सेटिंग्ज जतन करा
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-check text-success me-2"></i> सेटिंग्जचा वापर कुठे होतो?</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item px-0">
                                <strong>१. प्रगती पुस्तक (Report Card):</strong><br>
                                <span class="text-muted">मुख्याध्यापक स्वाक्षरी, निकाल दिनांक व पुढील वर्षाची सूचना.</span>
                            </li>
                            <li class="list-group-item px-0">
                                <strong>२. नोंदवही (Nondvahi):</strong><br>
                                <span class="text-muted">सत्रनिहाय मुख्याध्यापक पडताळणी शिक्का व शेरा.</span>
                            </li>
                            <li class="list-group-item px-0">
                                <strong>३. सर्वसमावेशक निकाल (Result Sheet):</strong><br>
                                <span class="text-muted">शाळा प्रमुख म्हणून निकाल पत्रकाखालील अधिकृत स्वाक्षरी.</span>
                            </li>
                            <li class="list-group-item px-0">
                                <strong>४. HPC प्रगती पत्रक:</strong><br>
                                <span class="text-muted">इयत्ता १ ते ८ च्या रंगीत कार्डवरील शाळा प्रमाणन.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- MODAL: ADD / EDIT STUDENT (विजेता पोर्टल — CCE व HPC सुसंगत) -->
<!-- ============================================================== -->
<div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <form method="POST" enctype="multipart/form-data" id="studentForm" class="modal-content d-flex flex-column h-100" style="max-height: 92vh;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="save_student">
            <input type="hidden" name="student_id" id="m_student_id" value="0">

            <!-- Sticky Modal Header with Quick Save -->
            <div class="modal-header bg-primary text-white flex-shrink-0 py-2 px-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-mortarboard-fill fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0 text-white" id="studentModalTitle">
                        नवीन विद्यार्थी नोंदणी (विजेता पोर्टल — CCE व HPC)
                    </h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-warning btn-sm fw-bold shadow-sm px-3" title="माहिती त्वरित जतन करा">
                        <i class="bi bi-save me-1"></i> जतन करा
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Scrollable Modal Body -->
            <div class="modal-body p-3 p-md-4 flex-grow-1" style="overflow-y: auto !important; max-height: calc(92vh - 120px); -webkit-overflow-scrolling: touch;">
                
                <!-- SECTION 1: ACADEMIC & CLASS -->
                <div class="card border-0 shadow-none bg-light mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-mortarboard me-1"></i> १. शैक्षणिक व वर्ग माहिती
                        </h6>
                        <div class="row g-2 g-md-3">
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">इयत्ता (Standard) <span class="text-danger">*</span></label>
                                <select name="std" id="m_std" class="form-select form-select-sm" required>
                                    <?php foreach ($school_range as $s_num => $s_lbl): ?>
                                        <option value="<?= $s_num ?>" <?= $selected_std === $s_num ? 'selected' : '' ?>><?= $s_lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label fw-bold small">तुकडी (Division) <span class="text-danger">*</span></label>
                                <input type="text" name="section" id="m_section" class="form-control form-control-sm" value="अ" placeholder="उदा. अ" required>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">वर्गशिक्षक / संबंधित शिक्षक</label>
                                <select name="teacher_id" id="m_teacher_id" class="form-select form-select-sm">
                                    <option value="">-- शिक्षक निवडा --</option>
                                    <?php foreach ($all_teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= $filter_teacher_id == $t['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($t['name_mr'] ?: $t['name']) ?> (<?= sanitize($t['teacher_code'] ?: 'शिक्षक') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">हजेरी क्र. (Roll No.) <span class="text-danger">*</span></label>
                                <input type="text" name="roll_no" id="m_roll_no" class="form-control form-control-sm" placeholder="उदा. 1" required>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">GR क्र. (General Register) <span class="text-danger">*</span></label>
                                <input type="number" name="gr_no" id="m_gr_no" class="form-control form-control-sm" placeholder="उदा. 101" required>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">नोंदणी क्र. (Registration No.)</label>
                                <input type="text" name="registration_no" id="m_registration_no" class="form-control form-control-sm" placeholder="GR किंवा नोंदणी क्र.">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">प्रवेश दिनांक (Admission Date)</label>
                                <input type="date" name="admission_date" id="m_admission_date" class="form-control form-control-sm" value="<?= date('Y-06-15') ?>">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">दाखल झालेली इयत्ता</label>
                                <select name="admission_class" id="m_admission_class" class="form-select form-select-sm">
                                    <?php foreach ($school_range as $s_num => $s_lbl): ?>
                                        <option value="<?= $s_num ?>"><?= $s_lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">मातृभाषा</label>
                                <input type="text" name="mother_tongue" id="m_mother_tongue" class="form-control form-control-sm" value="मराठी">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">शिक्षण माध्यम</label>
                                <select name="medium_of_instruction" id="m_medium_of_instruction" class="form-select form-select-sm">
                                    <option value="मराठी" selected>मराठी</option>
                                    <option value="इंग्रजी">इंग्रजी</option>
                                    <option value="उर्दू">उर्दू</option>
                                    <option value="हिंदी">हिंदी</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: PERSONAL DETAILS -->
                <div class="card border-0 shadow-none bg-light mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-person-badge me-1"></i> २. वैयक्तिक माहिती
                        </h6>
                        <div class="row g-2 g-md-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small">विद्यार्थ्याचे पूर्ण नाव (मराठीत) <span class="text-danger">*</span></label>
                                <input type="text" name="name_mr" id="m_name_mr" class="form-control form-control-sm" placeholder="आडनाव नाव वडिलांचे नाव" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small">विद्यार्थ्याचे नाव (इंग्रजीत)</label>
                                <input type="text" name="name" id="m_name" class="form-control form-control-sm" placeholder="SURNAME FIRST MIDDLE">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">लिंग (Gender) <span class="text-danger">*</span></label>
                                <select name="gender" id="m_gender" class="form-select form-select-sm" required>
                                    <option value="मुलगा">मुलगा (Boy)</option>
                                    <option value="मुलगी">मुलगी (Girl)</option>
                                    <option value="इतर">इतर</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">जन्मतारीख (Date of Birth) <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_birth" id="m_date_of_birth" class="form-control form-control-sm" required onchange="calculateAge(this.value)">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label fw-bold small">वय (Age)</label>
                                <input type="number" name="age" id="m_age" class="form-control form-control-sm" placeholder="उदा. 6">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">जन्मगाव / शहर (Birth Place)</label>
                                <input type="text" name="birth_place" id="m_birth_place" class="form-control form-control-sm" placeholder="उदा. कराड">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">जन्म तालुका</label>
                                <input type="text" name="birth_taluka" id="m_birth_taluka" class="form-control form-control-sm" placeholder="उदा. कराड">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">जन्म जिल्हा</label>
                                <input type="text" name="birth_district" id="m_birth_district" class="form-control form-control-sm" placeholder="उदा. सातारा">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">विद्यार्थ्याचा फोटो</label>
                                <input type="file" name="photo" id="m_photo" class="form-control form-control-sm" accept="image/*">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">रक्तगट (Blood Group)</label>
                                <select name="blood_group" id="m_blood_group" class="form-select form-select-sm">
                                    <option value="">-- निवडा --</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: ADDRESS & CONTACT -->
                <div class="card border-0 shadow-none bg-light mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-geo-alt me-1"></i> ३. पत्ता व संपर्क
                        </h6>
                        <div class="row g-2 g-md-3">
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">पालकांचा मोबाईल क्रमांक (Mobile)</label>
                                <input type="tel" name="phone" id="m_phone" class="form-control form-control-sm" placeholder="उदा. 9876543210">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">पत्ता ओळ १ (Address Line 1)</label>
                                <input type="text" name="address_line1" id="m_address_line1" class="form-control form-control-sm" placeholder="घर क्र., गल्ली, परिसर">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">पत्ता ओळ २ (Address Line 2)</label>
                                <input type="text" name="address_line2" id="m_address_line2" class="form-control form-control-sm" placeholder="गाव / शहर, पिन कोड">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">परिसर प्रकार (Area Type)</label>
                                <select name="area_type" id="m_area_type" class="form-select form-select-sm">
                                    <option value="ग्रामीण" selected>ग्रामीण (Rural)</option>
                                    <option value="शहरी">शहरी (Urban)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: PARENTS & FAMILY -->
                <div class="card border-0 shadow-none bg-light mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-people me-1"></i> ४. पालक व कौटुंबिक माहिती
                        </h6>
                        <div class="row g-2 g-md-3">
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">आईचे नाव (Mother's Name) <span class="text-danger">*</span></label>
                                <input type="text" name="mother_name" id="m_mother_name" class="form-control form-control-sm" placeholder="उदा. सुनीता" required>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">आईचे शिक्षण</label>
                                <input type="text" name="mother_education" id="m_mother_education" class="form-control form-control-sm" placeholder="उदा. १० वी, बी.ए.">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">आईचा व्यवसाय</label>
                                <input type="text" name="mother_occupation" id="m_mother_occupation" class="form-control form-control-sm" placeholder="उदा. गृहिणी, नोकरी">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">वडिलांचे नाव (Father's Name)</label>
                                <input type="text" name="father_name" id="m_father_name" class="form-control form-control-sm" placeholder="उदा. रमेश">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">वडिलांचे शिक्षण</label>
                                <input type="text" name="father_education" id="m_father_education" class="form-control form-control-sm" placeholder="उदा. १२ वी, पदवीधर">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">वडिलांचा व्यवसाय</label>
                                <input type="text" name="father_occupation" id="m_father_occupation" class="form-control form-control-sm" placeholder="उदा. शेती, व्यवसाय">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">पालक नाव (इतर असल्यास)</label>
                                <input type="text" name="guardian_name" id="m_guardian_name" class="form-control form-control-sm" placeholder="पालक नाव">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label fw-bold small">नाते (Relation)</label>
                                <input type="text" name="guardian_relation" id="m_guardian_relation" class="form-control form-control-sm" placeholder="उदा. काका">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="form-label fw-bold small">भावंडे संख्या</label>
                                <input type="number" name="num_siblings" id="m_num_siblings" class="form-control form-control-sm" min="0" value="0">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">भावंडांचे वय</label>
                                <input type="text" name="siblings_age" id="m_siblings_age" class="form-control form-control-sm" placeholder="उदा. 4, 8">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: CASTE & OFFICIAL IDS -->
                <div class="card border-0 shadow-none bg-light mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-card-text me-1"></i> ५. जात, प्रवर्ग व अधिकृत ओळख क्रमांक (CCE व Bonafide साठी)
                        </h6>
                        <div class="row g-2 g-md-3">
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">धर्म (Religion)</label>
                                <select name="religion" id="m_religion" class="form-select form-select-sm">
                                    <option value="हिंदू">हिंदू</option>
                                    <option value="बौद्ध">बौद्ध</option>
                                    <option value="मुस्लिम">मुस्लिम</option>
                                    <option value="जैन">जैन</option>
                                    <option value="ख्रिश्चन">ख्रिश्चन</option>
                                    <option value="शीख">शीख</option>
                                    <option value="लिंगायत">लिंगायत</option>
                                    <option value="इतर">इतर</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">जात (Caste)</label>
                                <input type="text" name="caste" id="m_caste" class="form-control form-control-sm" placeholder="उदा. मराठा, कुणबी, इ.">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">प्रवर्ग (Category - CCE तक्त्यासाठी)</label>
                                <select name="caste_category" id="m_caste_category" class="form-select form-select-sm">
                                    <option value="खुला / Open">खुला / Open</option>
                                    <option value="SC">SC (अनु. जाती)</option>
                                    <option value="ST">ST (अनु. जमाती)</option>
                                    <option value="VJ/DT">VJ / DT (विमुक्त जाती)</option>
                                    <option value="NT-B">NT-B (भटक्या जमाती ब)</option>
                                    <option value="NT-C">NT-C (भटक्या जमाती क)</option>
                                    <option value="NT-D">NT-D (भटक्या जमाती ड)</option>
                                    <option value="OBC">OBC (इतर मागासवर्गीय)</option>
                                    <option value="SBC">SBC (विशेष मागास)</option>
                                    <option value="EWS">EWS (आर्थिक दुर्बल)</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">राष्ट्रीयत्व (Nationality)</label>
                                <select name="nationality" id="m_nationality" class="form-select form-select-sm">
                                    <option value="भारतीय">भारतीय (Indian)</option>
                                    <option value="इतर">इतर (Other)</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">आधार क्रमांक (Aadhaar No.)</label>
                                <input type="text" name="aadhar_no" id="m_aadhar_no" class="form-control form-control-sm font-monospace" maxlength="12" placeholder="12 अंकी आधार">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">APAAR ID (12 अंकी)</label>
                                <input type="text" name="apaar_id" id="m_apaar_id" class="form-control form-control-sm font-monospace" placeholder="उदा. 123456789012">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">विद्यार्थी PEN</label>
                                <input type="text" name="student_pen" id="m_student_pen" class="form-control form-control-sm font-monospace" placeholder="PEN क्रमांक">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">सरल आयडी (Saral ID)</label>
                                <input type="text" name="saral_id" id="m_saral_id" class="form-control form-control-sm font-monospace" placeholder="सरल आयडी">
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label fw-bold small">UDID क्रमांक (दिव्यांग)</label>
                                <input type="text" name="udid" id="m_udid" class="form-control form-control-sm font-monospace" placeholder="UDID क्र. (लागू असल्यास)">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 6: FAVOURITES (आवडते - 7 Items with Dropdown + Custom Type) -->
                <div class="card border-0 shadow-none bg-light mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="fw-bold text-success mb-0">
                                <i class="bi bi-heart-fill text-danger me-1"></i> ६. आवडते (HPC Page 3 आवडी, मित्र व मोजमापे)
                            </h6>
                            <span class="badge bg-success-subtle text-success border border-success">
                                <i class="bi bi-check2-circle"></i> ड्रॉपडाउन किंवा टाइप करा
                            </span>
                        </div>
                        <div class="row g-2 g-md-3">
                            <!-- 1. Color -->
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">🎨 आवडता रंग (Favourite Color)</label>
                                <select class="form-select form-select-sm" id="m_favourite_color_sel" onchange="onFavSelectChange(this, 'favourite_color')">
                                    <option value="">-- रंग निवडा --</option>
                                    <?php foreach ($favourite_colors as $fc): ?>
                                        <option value="<?= $fc ?>"><?= $fc ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__">✏️ इतर (टाइप करा)...</option>
                                </select>
                                <div id="m_favourite_color_custom_wrap" class="mt-1 d-none">
                                    <input type="text" class="form-control form-control-sm" id="m_favourite_color_custom" placeholder="इतर रंग टाइप करा" oninput="onFavCustomInput(this, 'favourite_color')">
                                </div>
                                <input type="hidden" name="favourite_color" id="m_favourite_color" value="">
                            </div>

                            <!-- 2. Food -->
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">🍛 आवडते अन्न / खाद्य (Food)</label>
                                <select class="form-select form-select-sm" id="m_favourite_food_sel" onchange="onFavSelectChange(this, 'favourite_food')">
                                    <option value="">-- अन्नपदार्थ निवडा --</option>
                                    <?php foreach ($favourite_foods as $ff): ?>
                                        <option value="<?= $ff ?>"><?= $ff ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__">✏️ इतर (टाइप करा)...</option>
                                </select>
                                <div id="m_favourite_food_custom_wrap" class="mt-1 d-none">
                                    <input type="text" class="form-control form-control-sm" id="m_favourite_food_custom" placeholder="इतर खाद्य टाइप करा" oninput="onFavCustomInput(this, 'favourite_food')">
                                </div>
                                <input type="hidden" name="favourite_food" id="m_favourite_food" value="">
                            </div>

                            <!-- 3. Flower -->
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">🌺 आवडते फूल (Flower)</label>
                                <select class="form-select form-select-sm" id="m_favourite_flower_sel" onchange="onFavSelectChange(this, 'favourite_flower')">
                                    <option value="">-- फूल निवडा --</option>
                                    <?php foreach ($favourite_flowers as $fl): ?>
                                        <option value="<?= $fl ?>"><?= $fl ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__">✏️ इतर (टाइप करा)...</option>
                                </select>
                                <div id="m_favourite_flower_custom_wrap" class="mt-1 d-none">
                                    <input type="text" class="form-control form-control-sm" id="m_favourite_flower_custom" placeholder="इतर फूल टाइप करा" oninput="onFavCustomInput(this, 'favourite_flower')">
                                </div>
                                <input type="hidden" name="favourite_flower" id="m_favourite_flower" value="">
                            </div>

                            <!-- 4. Sport -->
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">⚽ आवडता खेळ (Sport)</label>
                                <select class="form-select form-select-sm" id="m_favourite_sport_sel" onchange="onFavSelectChange(this, 'favourite_sport')">
                                    <option value="">-- खेळ निवडा --</option>
                                    <?php foreach ($favourite_sports as $sp): ?>
                                        <option value="<?= $sp ?>"><?= $sp ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__">✏️ इतर (टाइप करा)...</option>
                                </select>
                                <div id="m_favourite_sport_custom_wrap" class="mt-1 d-none">
                                    <input type="text" class="form-control form-control-sm" id="m_favourite_sport_custom" placeholder="इतर खेळ टाइप करा" oninput="onFavCustomInput(this, 'favourite_sport')">
                                </div>
                                <input type="hidden" name="favourite_sport" id="m_favourite_sport" value="">
                            </div>

                            <!-- 5. Animal -->
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">🐾 आवडता प्राणी (Animal)</label>
                                <select class="form-select form-select-sm" id="m_favourite_animal_sel" onchange="onFavSelectChange(this, 'favourite_animal')">
                                    <option value="">-- प्राणी निवडा --</option>
                                    <?php foreach ($favourite_animals as $an): ?>
                                        <option value="<?= $an ?>"><?= $an ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__">✏️ इतर (टाइप करा)...</option>
                                </select>
                                <div id="m_favourite_animal_custom_wrap" class="mt-1 d-none">
                                    <input type="text" class="form-control form-control-sm" id="m_favourite_animal_custom" placeholder="इतर प्राणी टाइप करा" oninput="onFavCustomInput(this, 'favourite_animal')">
                                </div>
                                <input type="hidden" name="favourite_animal" id="m_favourite_animal" value="">
                            </div>

                            <!-- 6. Subject -->
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-label fw-bold small">📚 आवडता विषय (Subject)</label>
                                <select class="form-select form-select-sm" id="m_favourite_subject_sel" onchange="onFavSelectChange(this, 'favourite_subject')">
                                    <option value="">-- विषय निवडा --</option>
                                    <?php foreach ($favourite_subjects as $sub): ?>
                                        <option value="<?= $sub ?>"><?= $sub ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__">✏️ इतर (टाइप करा)...</option>
                                </select>
                                <div id="m_favourite_subject_custom_wrap" class="mt-1 d-none">
                                    <input type="text" class="form-control form-control-sm" id="m_favourite_subject_custom" placeholder="इतर विषय टाइप करा" oninput="onFavCustomInput(this, 'favourite_subject')">
                                </div>
                                <input type="hidden" name="favourite_subject" id="m_favourite_subject" value="">
                            </div>

                            <!-- 7. Aspiration -->
                            <div class="col-12 col-sm-6 col-lg-6">
                                <label class="form-label fw-bold small">🌟 मोठे होऊन काय व्हायचे आहे? (Aspiration / ध्येय)</label>
                                <select class="form-select form-select-sm" id="m_aspiration_sel" onchange="onFavSelectChange(this, 'aspiration')">
                                    <option value="">-- ध्येय / स्वप्न निवडा --</option>
                                    <?php foreach ($aspiration_options as $asp): ?>
                                        <option value="<?= $asp ?>"><?= $asp ?></option>
                                    <?php endforeach; ?>
                                    <option value="__other__">✏️ इतर (टाइप करा)...</option>
                                </select>
                                <div id="m_aspiration_custom_wrap" class="mt-1 d-none">
                                    <input type="text" class="form-control form-control-sm" id="m_aspiration_custom" placeholder="इतर ध्येय टाइप करा" oninput="onFavCustomInput(this, 'aspiration')">
                                </div>
                                <input type="hidden" name="aspiration" id="m_aspiration" value="">
                            </div>

                            <!-- Measurements (सत्र १ व सत्र २) -->
                            <div class="col-12 col-sm-6 col-lg-6">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label fw-bold small text-primary">उंची (सें.मी.) - सत्र १</label>
                                        <input type="number" step="0.1" name="height_sem1" id="m_height_sem1" class="form-control form-control-sm" placeholder="उदा. 115">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-bold small text-primary">वजन (कि.ग्रॅ.) - सत्र १</label>
                                        <input type="number" step="0.1" name="weight_sem1" id="m_weight_sem1" class="form-control form-control-sm" placeholder="उदा. 18.5">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-6">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label fw-bold small text-success">उंची (सें.मी.) - सत्र २</label>
                                        <input type="number" step="0.1" name="height_sem2" id="m_height_sem2" class="form-control form-control-sm" placeholder="उदा. 118">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-bold small text-success">वजन (कि.ग्रॅ.) - सत्र २</label>
                                        <input type="number" step="0.1" name="weight_sem2" id="m_weight_sem2" class="form-control form-control-sm" placeholder="उदा. 19.5">
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small">👫 जवळचा मित्र/मैत्रीण १</label>
                                <input type="text" name="best_friend1" id="m_best_friend1" class="form-control form-control-sm" placeholder="मित्राचे नाव">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small">👫 जवळचा मित्र/मैत्रीण २</label>
                                <input type="text" name="best_friend2" id="m_best_friend2" class="form-control form-control-sm" placeholder="मित्राचे नाव">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small">👫 जवळचा मित्र/मैत्रीण ३</label>
                                <input type="text" name="best_friend3" id="m_best_friend3" class="form-control form-control-sm" placeholder="मित्राचे नाव">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 7: INTERESTS & HOBBIES (12 Checkboxes + Other) -->
                <div class="card border-0 shadow-none bg-light mb-2">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="fw-bold text-primary mb-0">
                                <i class="bi bi-star-fill text-warning me-1"></i> ७. आवड व छंद (Interests — एक किंवा अधिक निवडा)
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary">CCE व HPC आवड नोंद</span>
                        </div>
                        <div class="row g-2">
                            <?php
                            $all_interests = [
                                'वाचन' => 'वाचन (Reading)',
                                'सर्जनशील लेखन' => 'सर्जनशील लेखन (Creative Writing)',
                                'नृत्य' => 'नृत्य (Dancing)',
                                'गायन' => 'गायन (Singing)',
                                'वाद्य वाजवणे' => 'वाद्य वाजवणे (Musical Instrument)',
                                'बागकाम' => 'बागकाम (Gardening)',
                                'योग' => 'योग (Yoga)',
                                'चित्रकला' => 'चित्रकला (Art)',
                                'हस्तकला' => 'हस्तकला (Craft)',
                                'खेळ' => 'खेळ (Sports/Games)',
                                'स्वयंपाक' => 'स्वयंपाक (Cooking)',
                                'घरकामात सहभाग' => 'घरकामात सहभाग (Home Participation)',
                            ];
                            foreach ($all_interests as $val => $label):
                            ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="form-check p-2 border rounded bg-white h-100">
                                    <input class="form-check-input ms-1 interest-check" type="checkbox" name="interests[]" value="<?= $val ?>" id="int_<?= md5($val) ?>">
                                    <label class="form-check-label small fw-semibold ms-2" for="int_<?= md5($val) ?>">
                                        <?= $label ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <label class="form-label small fw-bold">इतर आवड / छंद (कृपया नमूद करा):</label>
                            <input type="text" name="other_interest" id="m_other_interest" class="form-control form-control-sm" placeholder="उदा. सायकल चालवणे, अभिनय, विज्ञान प्रयोग, इ.">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Sticky Modal Footer -->
            <div class="modal-footer bg-light flex-shrink-0 py-2 px-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">रद्द करा</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                    <i class="bi bi-check-circle me-1"></i> माहिती जतन करा
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: BULK CSV UPLOAD (विद्यार्थी CSV आयात करा) -->
<!-- ============================================================== -->
<div class="modal fade" id="csvUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="upload_students_csv">
            <div class="modal-header bg-success text-white py-2 px-3">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> विद्यार्थी CSV आयात करा (Bulk Upload)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info border-0 py-2 small mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i> तुम्ही एक्सेल/CSV फाइल द्वारे एकाच वेळी अनेक विद्यार्थ्यांची माहिती आयात करू शकता.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">डिफॉल्ट इयत्ता निवडा (Default Standard)</label>
                    <select name="csv_std" class="form-select form-select-sm" required>
                        <?php foreach ($school_range as $s_num => $s_lbl): ?>
                            <option value="<?= $s_num ?>" <?= $selected_std === $s_num ? 'selected' : '' ?>><?= $s_lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">डिफॉल्ट तुकडी (Default Division)</label>
                    <input type="text" name="csv_division" class="form-control form-select-sm" value="<?= sanitize($selected_div ?: 'अ') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">CSV फाइल निवडा (.csv format)</label>
                    <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                </div>
                <div class="p-2 mb-3 bg-warning-subtle rounded border border-warning small">
                    <span class="fw-bold text-dark"><i class="bi bi-magic me-1 text-primary"></i> UDISE+ वरून विद्यार्थी जोडायचे आहेत का?</span><br>
                    <span class="text-muted">UDISE+ वरील Excel किंवा CSV थेट अपलोड करा आणि स्वयंचलित मराठी युनिकोड नावे मिळवा:</span><br>
                    <a href="<?= APP_URL ?>/modules/classes/import_udise.php" class="btn btn-warning btn-sm mt-1 fw-bold text-dark">
                        <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> UDISE+ थेट आयात उघडा ›
                    </a>
                </div>
                <div class="p-2 bg-light rounded border small">
                    <span class="fw-bold text-dark"><i class="bi bi-download me-1 text-success"></i> नमुना फाइल हवी आहे का?</span><br>
                    <span class="text-muted">खालील बटणावर क्लिक करून नमुना टेम्पलेट डाउनलोड करा, त्यात विद्यार्थ्यांची माहिती भरा आणि नंतर अपलोड करा:</span><br>
                    <a href="?action=download_student_csv_template" class="btn btn-outline-success btn-sm mt-2 fw-semibold">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> नमुना CSV टेम्पलेट डाऊनलोड करा
                    </a>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">रद्द करा</button>
                <button type="submit" class="btn btn-success btn-sm fw-bold px-4">
                    <i class="bi bi-cloud-upload me-1"></i> CSV अपलोड करा
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: ADD / EDIT TEACHER (शिक्षक माहिती फॉर्म) -->
<!-- ============================================================== -->
<div class="modal fade" id="teacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save_teacher">
                <input type="hidden" name="teacher_id" id="t_teacher_id" value="0">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="teacherModalTitle">
                        <i class="bi bi-person-plus-fill me-1"></i> नवीन शिक्षक जोडा
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">शिक्षकाचे पूर्ण नाव (मराठीत) <span class="text-danger">*</span></label>
                            <input type="text" name="name_mr" id="t_name_mr" class="form-control" placeholder="उदा. श्री. सचिन रमेश पाटील" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Name in English</label>
                            <input type="text" name="name" id="t_name" class="form-control" placeholder="Shri. Sachin Ramesh Patil">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">शिक्षक कोड / शालार्थ ID</label>
                            <input type="text" name="teacher_code" id="t_teacher_code" class="form-control font-monospace" placeholder="उदा. TCH-101 किंवा शालार्थ क्र.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">पद / भूमिका (Role)</label>
                            <select name="role" id="t_role" class="form-select">
                                <option value="teacher">शिक्षक (Teacher)</option>
                                <option value="principal">मुख्याध्यापक (Headmaster / Principal)</option>
                                <option value="clerk">लिपिक (Clerk / Staff)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">वर्गशिक्षक नेमणूक (Class Assigned)</label>
                            <select name="class_assigned" id="t_class_assigned" class="form-select">
                                <option value="">-- नेमणूक नाही (विषय शिक्षक) --</option>
                                <?php foreach ($school_range as $s_num => $s_lbl): ?>
                                    <option value="<?= $s_lbl ?>"><?= $s_lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">तुकडी (Section)</label>
                            <input type="text" name="section" id="t_section" class="form-control" value="अ" placeholder="उदा. अ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">मोबाईल / फोन नंबर</label>
                            <input type="tel" name="phone" id="t_phone" class="form-control" placeholder="उदा. 9876543210">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">ईमेल आयडी (Email)</label>
                            <input type="email" name="email" id="t_email" class="form-control" placeholder="उदा. teacher@gmail.com">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करा</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i> शिक्षक माहिती जतन करा
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EDIT SCHOOL WORKING DAYS -->
<!-- ============================================================== -->
<div class="modal fade" id="workingDaysModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save_monthly_attendance">
                <input type="hidden" name="std" value="<?= $selected_std ?>">
                <input type="hidden" name="month" value="<?= $sel_month ?>">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-calendar-range me-1"></i> शाळेचे मासिक कामाचे दिवस बदला
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        खालील महिन्यांचे अधिकृत कामाचे दिवस बदला. हे दिवस सर्व विद्यार्थ्यांच्या उपस्थिती गणनेसाठी आधार मानले जातील.
                    </p>
                    <div class="row g-2">
                        <?php foreach ($months_map as $m_num => $m_lbl): ?>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1"><?= $m_lbl ?></label>
                                <input type="number" name="working[<?= $m_num ?>]" class="form-control form-control-sm text-center fw-bold" 
                                       min="0" max="31" value="<?= (int)($working_monthly[$m_num] ?? 0) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">रद्द करा</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold">
                        <i class="bi bi-save me-1"></i> कामाचे दिवस जतन करा
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: ADD CLASS / DIVISION -->
<!-- ============================================================== -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="add_class_division">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary"></i> नवीन वर्ग / तुकडी जोडा</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">इयत्ता निवडा</label>
                        <select name="std" class="form-select" required>
                            <?php foreach ($school_range as $s_num => $s_lbl): ?>
                                <option value="<?= $s_num ?>"><?= $s_lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">तुकडी (Division)</label>
                        <input type="text" name="section" class="form-control" placeholder="उदा. अ, ब, क" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">माध्यम (Medium)</label>
                        <select name="medium" class="form-select">
                            <option value="मराठी">मराठी</option>
                            <option value="इंग्रजी">इंग्रजी</option>
                            <option value="उर्दू">उर्दू</option>
                            <option value="हिंदी">हिंदी</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">वर्गशिक्षक नेमणूक (ऐच्छिक)</label>
                        <select name="class_teacher_id" class="form-select">
                            <option value="">-- नंतर निवडा --</option>
                            <?php foreach ($all_teachers as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= sanitize($t['name_mr'] ?: $t['name']) ?> (<?= sanitize($t['teacher_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करा</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> वर्ग जोडा</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter by teacher from Hub Header
function filterByTeacher(tid) {
    var url = new URL(window.location.href);
    if (parseInt(tid) > 0) {
        url.searchParams.set('teacher_id', tid);
        url.searchParams.delete('std');
        url.searchParams.delete('division');
    } else {
        url.searchParams.delete('teacher_id');
    }
    window.location.href = url.toString();
}

// Calculate age from Date of Birth
function calculateAge(dob) {
    if (!dob) return;
    var birthDate = new Date(dob);
    var today = new Date();
    var age = today.getFullYear() - birthDate.getFullYear();
    var m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    if (age >= 0) {
        document.getElementById('m_age').value = age;
    }
}

// 7 Favourites with Dropdown + Other Toggle
var favFields = ['favourite_color', 'favourite_food', 'favourite_flower', 'favourite_sport', 'favourite_animal', 'favourite_subject', 'aspiration'];

function onFavSelectChange(sel, fieldName) {
    var wrap = document.getElementById('m_' + fieldName + '_custom_wrap');
    var custom = document.getElementById('m_' + fieldName + '_custom');
    var hidden = document.getElementById('m_' + fieldName);
    if (sel.value === '__other__') {
        wrap.classList.remove('d-none');
        custom.focus();
        hidden.value = custom.value.trim();
    } else {
        wrap.classList.add('d-none');
        hidden.value = sel.value;
    }
}

function onFavCustomInput(custom, fieldName) {
    document.getElementById('m_' + fieldName).value = custom.value.trim();
}

function setFavField(fieldName, val) {
    val = (val || '').trim();
    var hidden = document.getElementById('m_' + fieldName);
    var sel = document.getElementById('m_' + fieldName + '_sel');
    var wrap = document.getElementById('m_' + fieldName + '_custom_wrap');
    var custom = document.getElementById('m_' + fieldName + '_custom');
    if (!hidden || !sel || !wrap || !custom) return;
    hidden.value = val;
    if (!val) {
        sel.value = '';
        wrap.classList.add('d-none');
        custom.value = '';
        return;
    }
    var exists = false;
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === val && val !== '__other__') {
            exists = true;
            break;
        }
    }
    if (exists) {
        sel.value = val;
        wrap.classList.add('d-none');
        custom.value = '';
    } else {
        sel.value = '__other__';
        wrap.classList.remove('d-none');
        custom.value = val;
    }
}

function openStudentModal(id, std) {
    var canAdd = <?= $school_quota['can_add'] ? 'true' : 'false' ?>;
    if (!canAdd) {
        alert('शाळेची विद्यार्थी मर्यादा पूर्ण झाली आहे (जास्तीत जास्त: <?= $school_quota['max'] ?> विद्यार्थी). कृपया पुढील विद्यार्थी जोडण्यासाठी योजना अपग्रेड करा.');
        return;
    }
    document.getElementById('studentForm').reset();
    document.getElementById('m_student_id').value = 0;
    document.getElementById('m_std').value = std || <?= $selected_std ?>;
    document.getElementById('m_section').value = '<?= sanitize($selected_div ?: 'अ') ?>';
    document.getElementById('m_teacher_id').value = '<?= $filter_teacher_id ?: '' ?>';
    document.getElementById('m_age').value = '';
    document.getElementById('m_admission_date').value = '<?= date('Y-06-15') ?>';
    document.getElementById('m_admission_class').value = std || <?= $selected_std ?>;
    document.getElementById('m_mother_tongue').value = 'मराठी';
    document.getElementById('m_medium_of_instruction').value = 'मराठी';
    document.getElementById('m_religion').value = 'हिंदू';
    document.getElementById('m_caste_category').value = 'खुला / Open';
    document.getElementById('m_nationality').value = 'भारतीय';
    document.getElementById('m_area_type').value = 'ग्रामीण';
    document.getElementById('m_num_siblings').value = '0';
    document.getElementById('m_gender').value = 'मुलगा';

    favFields.forEach(function(f) { setFavField(f, ''); });
    document.querySelectorAll('.interest-check').forEach(function(c) { c.checked = false; });
    document.getElementById('m_other_interest').value = '';

    document.getElementById('studentModalTitle').innerHTML = '<i class="bi bi-person-plus-fill me-1"></i> नवीन विद्यार्थी नोंदणी (इयत्ता ' + (std || <?= $selected_std ?>) + ')';
    var modal = new bootstrap.Modal(document.getElementById('studentModal'));
    modal.show();
}

function editStudent(data) {
    document.getElementById('studentForm').reset();
    document.getElementById('m_student_id').value = data.id || 0;
    document.getElementById('m_std').value = data.std || <?= $selected_std ?>;
    document.getElementById('m_section').value = data.section || 'अ';
    document.getElementById('m_teacher_id').value = data.teacher_id || '';
    document.getElementById('m_roll_no').value = data.roll_no || '';
    document.getElementById('m_gr_no').value = data.gr_no || data.registration_no || '';
    document.getElementById('m_registration_no').value = data.registration_no || data.gr_no || '';
    document.getElementById('m_admission_date').value = data.admission_date || '';
    document.getElementById('m_admission_class').value = data.admission_class || data.std || 1;
    document.getElementById('m_mother_tongue').value = data.mother_tongue || 'मराठी';
    document.getElementById('m_medium_of_instruction').value = data.medium_of_instruction || 'मराठी';

    document.getElementById('m_name_mr').value = data.name_mr || '';
    document.getElementById('m_name').value = data.name || '';
    document.getElementById('m_gender').value = data.gender || 'मुलगा';
    document.getElementById('m_date_of_birth').value = data.date_of_birth || data.dob || '';
    document.getElementById('m_age').value = data.age || '';
    document.getElementById('m_birth_place').value = data.birth_place || data.village || '';
    document.getElementById('m_birth_taluka').value = data.birth_taluka || '';
    document.getElementById('m_birth_district').value = data.birth_district || '';
    document.getElementById('m_blood_group').value = data.blood_group || '';

    document.getElementById('m_phone').value = data.phone || data.father_mobile || '';
    document.getElementById('m_address_line1').value = data.address_line1 || '';
    document.getElementById('m_address_line2').value = data.address_line2 || '';
    document.getElementById('m_area_type').value = data.area_type || 'ग्रामीण';

    document.getElementById('m_mother_name').value = data.mother_name || '';
    document.getElementById('m_mother_education').value = data.mother_education || '';
    document.getElementById('m_mother_occupation').value = data.mother_occupation || '';
    document.getElementById('m_father_name').value = data.father_name || '';
    document.getElementById('m_father_education').value = data.father_education || '';
    document.getElementById('m_father_occupation').value = data.father_occupation || '';
    document.getElementById('m_guardian_name').value = data.guardian_name || '';
    document.getElementById('m_guardian_relation').value = data.guardian_relation || '';
    document.getElementById('m_num_siblings').value = data.num_siblings || 0;
    document.getElementById('m_siblings_age').value = data.siblings_age || '';

    document.getElementById('m_religion').value = data.religion || 'हिंदू';
    document.getElementById('m_caste').value = data.caste || '';
    document.getElementById('m_caste_category').value = data.caste_category || 'खुला / Open';
    document.getElementById('m_nationality').value = data.nationality || 'भारतीय';

    document.getElementById('m_aadhar_no').value = data.aadhar_no || data.aadhaar || '';
    document.getElementById('m_apaar_id').value = data.apaar_id || data.apaar || '';
    document.getElementById('m_student_pen').value = data.student_pen || '';
    document.getElementById('m_saral_id').value = data.saral_id || '';
    document.getElementById('m_udid').value = data.udid || '';

    favFields.forEach(function(f) {
        setFavField(f, data[f] || '');
    });

    document.getElementById('m_best_friend1').value = data.best_friend1 || '';
    document.getElementById('m_best_friend2').value = data.best_friend2 || '';
    document.getElementById('m_best_friend3').value = data.best_friend3 || '';
    document.getElementById('m_height_sem1').value = (data.height_sem1 && data.height_sem1 > 0) ? data.height_sem1 : '';
    document.getElementById('m_weight_sem1').value = (data.weight_sem1 && data.weight_sem1 > 0) ? data.weight_sem1 : '';
    document.getElementById('m_height_sem2').value = (data.height_sem2 && data.height_sem2 > 0) ? data.height_sem2 : '';
    document.getElementById('m_weight_sem2').value = (data.weight_sem2 && data.weight_sem2 > 0) ? data.weight_sem2 : '';

    document.querySelectorAll('.interest-check').forEach(function(c) { c.checked = false; });
    if (Array.isArray(data.interests)) {
        data.interests.forEach(function(item) {
            var cb = document.querySelector('.interest-check[value="' + item + '"]');
            if (cb) cb.checked = true;
        });
    }
    document.getElementById('m_other_interest').value = data.other_interest || '';

    document.getElementById('studentModalTitle').innerHTML = '<i class="bi bi-pencil-square me-1"></i> विद्यार्थी माहिती संपादित करा (' + (data.name_mr || data.name) + ')';
    var modal = new bootstrap.Modal(document.getElementById('studentModal'));
    modal.show();
}

function openTeacherModal(id) {
    document.getElementById('t_teacher_id').value = 0;
    document.getElementById('t_name_mr').value = '';
    document.getElementById('t_name').value = '';
    document.getElementById('t_teacher_code').value = '';
    document.getElementById('t_phone').value = '';
    document.getElementById('t_email').value = '';
    document.getElementById('t_role').value = 'teacher';
    document.getElementById('t_class_assigned').value = '';
    document.getElementById('t_section').value = 'अ';
    document.getElementById('teacherModalTitle').innerHTML = '<i class="bi bi-person-plus-fill me-1"></i> नवीन शिक्षक जोडा';
    var modal = new bootstrap.Modal(document.getElementById('teacherModal'));
    modal.show();
}

function editTeacher(data) {
    document.getElementById('t_teacher_id').value = data.id || 0;
    document.getElementById('t_name_mr').value = data.name_mr || '';
    document.getElementById('t_name').value = data.name || '';
    document.getElementById('t_teacher_code').value = data.teacher_code || '';
    document.getElementById('t_phone').value = data.phone || '';
    document.getElementById('t_email').value = data.email || '';
    document.getElementById('t_role').value = data.role || 'teacher';
    document.getElementById('t_class_assigned').value = data.class_assigned || '';
    document.getElementById('t_section').value = data.section || 'अ';
    document.getElementById('teacherModalTitle').innerHTML = '<i class="bi bi-pencil-square me-1"></i> शिक्षक माहिती संपादित करा (' + (data.name_mr || data.name) + ')';
    var modal = new bootstrap.Modal(document.getElementById('teacherModal'));
    modal.show();
}

function fillFullAttendance() {
    var inputs = document.querySelectorAll('.att-input');
    inputs.forEach(function(inp) {
        var max = inp.getAttribute('data-max');
        if (max && parseInt(max) > 0) {
            inp.value = max;
        }
    });
}

function setStudentView(view) {
    try { localStorage.setItem('hpc_student_view_mode', view); } catch(e) {}
    applyStudentView(view);
}

function applyStudentView(view) {
    var cardsEl = document.getElementById('studentCardsContainer');
    var tableEl = document.getElementById('studentTableContainer');
    var btnCards = document.getElementById('btnShowCards');
    var btnTable = document.getElementById('btnShowTable');
    if (!cardsEl || !tableEl) return;

    if (view === 'table') {
        cardsEl.classList.add('d-none');
        tableEl.classList.remove('d-none');
        if (btnCards) { btnCards.classList.remove('btn-primary'); btnCards.classList.add('btn-outline-primary'); }
        if (btnTable) { btnTable.classList.remove('btn-outline-primary'); btnTable.classList.add('btn-primary'); }
    } else {
        cardsEl.classList.remove('d-none');
        tableEl.classList.add('d-none');
        if (btnCards) { btnCards.classList.remove('btn-outline-primary'); btnCards.classList.add('btn-primary'); }
        if (btnTable) { btnTable.classList.remove('btn-primary'); btnTable.classList.add('btn-outline-primary'); }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var isMobile = window.innerWidth < 992;
    var saved = null;
    try { saved = localStorage.getItem('hpc_student_view_mode'); } catch(e) {}
    var defaultView = saved ? saved : (isMobile ? 'cards' : 'table');
    applyStudentView(defaultView);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
