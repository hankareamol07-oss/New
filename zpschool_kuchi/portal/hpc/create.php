<?php
/**
 * समग्र प्रगतिपत्रक (HPC) – पायाभूत स्तर (Foundational Stage: Std 1–2)
 * अधिकृत SCERT महाराष्ट्र मार्गदर्शिका (STARS / PARAKH) व १९-पानी अधिकृत आराखड्यानुसार (विजेता पोर्टल).
 *
 * वैशिष्ट्ये:
 * - ६ विकास क्षेत्रे, १३ अभ्यासक्रम ध्येये (CG) व ६९ अधिकृत क्षमता (C-code)
 * - अधिकृत ३ कामगिरी स्तर: निर्झर (स्तर १), पर्वत (स्तर २), आकाश (स्तर ३)
 * - क्षमतानिहाय अधिकृत शिक्षक अभिप्राय बँक (cce_abhipray2)
 * - विकासक्षेत्रनिहाय कामगिरी बँक (cce_kamgiri - सामर्थ्य व सुधारणा)
 * - सत्र १ व सत्र २ स्वतंत्र मूल्यमापन, स्व-मूल्यांकन व सहाध्यायी मूल्यांकन
 * - शैक्षणिक वर्षाचा सारांश (पान १६) व वार्षिक अंतिम अभिप्राय (पान १७)
 */

$page_title = 'HPC कार्ड तयार करा — पायाभूत स्तर (इयत्ता १ व २)';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/demo_data.php';
require_once __DIR__ . '/../includes/hpc_fs_data.php';
require_once __DIR__ . '/../includes/hpc_curriculum.php';
requireLogin();

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$school = getSchool();
$student_id = intval($_GET['student_id'] ?? 0);

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get students for dropdown
$stmt = $db->prepare("SELECT id, name, name_mr, roll_no, grade, std, gender FROM students WHERE school_id = ? AND is_active = 1 ORDER BY CAST(roll_no AS UNSIGNED) ASC, name_mr ASC");
$stmt->execute([$school_id]);
$all_students = $stmt->fetchAll();

$student = null;
$hpc_card = null;
$assessments = [];
$attendance_data = [];
$std = 1;

if ($student_id) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
    $stmt->execute([$student_id, $school_id]);
    $student = $stmt->fetch();

    if ($student) {
        $std = (int) ($student['std'] ?? 0) ?: hpc_std_from_grade($student['grade'] ?? '');
        
        // Stage Guard: If std 3–8, check plan access; redirect to stage_form.php if paid, or subscription if free
        if ($std >= 3) {
            $chkAccess = can_access_hpc_class($std, $school_id);
            if (!$chkAccess['allowed']) {
                flash('error', $chkAccess['message'] ?? 'उच्च वर्गांच्या (इ. ३ ते ८) HPC मूल्यमापनासाठी सशुल्क योजना आवश्यक आहे. मोफत योजना फक्त इ. १ ली व २ री साठी उपलब्ध आहे.');
                redirect(APP_URL . '/subscription/plans.php');
            }
            flash('info', 'विद्यार्थी इयत्ता ' . $std . ' मधील असल्याने SCERT विषय मूल्यांकन फॉर्मवर पुनर्निर्देशित केले.');
            redirect(APP_URL . '/hpc/stage_form.php?student_id=' . $student_id);
        }

        // Check for existing HPC card
        $stmt = $db->prepare("SELECT * FROM hpc_cards WHERE student_id = ? AND school_id = ? AND academic_year = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$student_id, $school_id, academic_year()]);
        $hpc_card = $stmt->fetch();

        if (!$hpc_card) {
            $quota = getSchoolStudentQuota($school_id);
            if (!$quota['is_unlimited']) {
                $stCnt = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM hpc_cards WHERE school_id = ? AND academic_year = ?");
                $stCnt->execute([$school_id, academic_year()]);
                $used_cards = (int)$stCnt->fetchColumn();
                if ($used_cards >= (int)$quota['max']) {
                    flash('error', "आपल्या योजनेची कमाल विद्यार्थी मर्यादा ({$quota['max']} विद्यार्थी) पूर्ण झाली आहे. पुढील विद्यार्थ्यांचे नवीन HPC कार्ड तयार करण्यासाठी कृपया योजना अपग्रेड करा.");
                    redirect(APP_URL . '/hpc/index.php?std=' . $std);
                }
            }
        }

        if ($hpc_card) {
            $stmt = $db->prepare("SELECT * FROM hpc_domain_assessments WHERE hpc_card_id = ? ORDER BY domain_id ASC");
            $stmt->execute([$hpc_card['id']]);
            $temp = [];
            foreach ($stmt->fetchAll() as $a) {
                $temp[$a['domain_id']] = $a;
            }
            $assessments = $temp;
        }

        // Get attendance
        $stmt = $db->prepare("SELECT * FROM attendance WHERE student_id = ? AND academic_year = ?");
        $stmt->execute([$student_id, academic_year()]);
        foreach ($stmt->fetchAll() as $a) {
            $attendance_data[$a['month']] = $a;
        }
    }
}

// संदर्भ डेटा (अधिकृत SCERT पायाभूत स्तर)
$domains = hpc_fs_domains();
$fs_descriptors = hpc_fs_level_descriptors();
$domain_sample_questions = hpc_fs_domain_sample_questions();
$competency_questions = hpc_fs_competency_questions();
$is_girl = $student ? (trim((string)($student['gender'] ?? '')) === 'मुलगी' || trim((string)($student['gender'] ?? '')) === 'F') : false;

// अधिकृत अभिप्राय बँक (cce_abhipray2) - क्षमतानिहाय लोड करा
$all_abhipray = [];
try {
    $st_ab = $db->query("SELECT domain, capacity, num, notes, notes2 FROM cce_abhipray2 ORDER BY domain, capacity, num");
    foreach ($st_ab->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $txt = trim((string)($is_girl ? ($r['notes2'] ?: $r['notes']) : $r['notes']));
        if ($txt !== '') {
            $all_abhipray[$r['capacity']][] = $txt;
        }
    }
} catch (Exception $e) {}

// अधिकृत कामगिरी बँक (cce_kamgiri) - डोमेननिहाय सामर्थ्य व सुधारणा
$all_kamgiri = [];
try {
    $st_km = $db->query("SELECT cg, types, vidhan, vidhan2 FROM cce_kamgiri ORDER BY cg, types, id");
    foreach ($st_km->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $txt = trim((string)($is_girl ? ($r['vidhan2'] ?: $r['vidhan']) : $r['vidhan']));
        if ($txt !== '') {
            $all_kamgiri[$r['cg']][$r['types']][] = $txt;
        }
    }
} catch (Exception $e) {}

// Handle form submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $student) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token');
        redirect(APP_URL . '/hpc/create.php?student_id=' . $student_id);
    }
    
    $teacher_code = trim($_POST['teacher_code'] ?? '');
    $status = ($_POST['save_type'] ?? 'draft') === 'complete' ? 'completed' : 'draft';
    $final_annual_feedback = trim($_POST['final_annual_feedback'] ?? '');
    
    $summary_awareness = trim($_POST['summary_awareness'] ?? '');
    $summary_sensitivity = trim($_POST['summary_sensitivity'] ?? '');
    $summary_creativity = trim($_POST['summary_creativity'] ?? '');
    
    $summary_domain_1 = trim($_POST['summary_domain_1'] ?? '');
    $summary_domain_2 = trim($_POST['summary_domain_2'] ?? '');
    $summary_domain_3 = trim($_POST['summary_domain_3'] ?? '');
    $summary_domain_4 = trim($_POST['summary_domain_4'] ?? '');
    $summary_domain_5 = trim($_POST['summary_domain_5'] ?? '');
    $summary_domain_6 = trim($_POST['summary_domain_6'] ?? '');

    if (!$hpc_card) {
        $quota = getSchoolStudentQuota($school_id);
        if (!$quota['is_unlimited']) {
            $stCnt = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM hpc_cards WHERE school_id = ? AND academic_year = ?");
            $stCnt->execute([$school_id, academic_year()]);
            $used_cards = (int)$stCnt->fetchColumn();
            if ($used_cards >= (int)$quota['max']) {
                flash('error', "आपल्या योजनेची कमाल विद्यार्थी मर्यादा ({$quota['max']} विद्यार्थी) पूर्ण झाली आहे. पुढील विद्यार्थ्यांसाठी कृपया योजना अपग्रेड करा.");
                redirect(APP_URL . '/hpc/index.php?std=' . $std);
            }
        }
        $stmt = $db->prepare("INSERT INTO hpc_cards (
            student_id, school_id, academic_year, stage_key, std, print_format, 
            teacher_code, status, final_annual_feedback,
            summary_awareness, summary_sensitivity, summary_creativity,
            summary_domain_1, summary_domain_2, summary_domain_3,
            summary_domain_4, summary_domain_5, summary_domain_6
        ) VALUES (?, ?, ?, 'foundational', ?, 'hpc-19', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $student_id, $school_id, academic_year(), $std,
            $teacher_code, $status, $final_annual_feedback,
            $summary_awareness, $summary_sensitivity, $summary_creativity,
            $summary_domain_1, $summary_domain_2, $summary_domain_3,
            $summary_domain_4, $summary_domain_5, $summary_domain_6
        ]);
        $hpc_card_id = (int) $db->lastInsertId();
    } else {
        $hpc_card_id = (int) $hpc_card['id'];
        $stmt = $db->prepare("UPDATE hpc_cards SET 
            stage_key = 'foundational', std = ?, print_format = 'hpc-19',
            teacher_code = ?, status = ?, final_annual_feedback = ?,
            summary_awareness = ?, summary_sensitivity = ?, summary_creativity = ?,
            summary_domain_1 = ?, summary_domain_2 = ?, summary_domain_3 = ?,
            summary_domain_4 = ?, summary_domain_5 = ?, summary_domain_6 = ?
            WHERE id = ? AND school_id = ?");
        $stmt->execute([
            $std,
            $teacher_code, $status, $final_annual_feedback,
            $summary_awareness, $summary_sensitivity, $summary_creativity,
            $summary_domain_1, $summary_domain_2, $summary_domain_3,
            $summary_domain_4, $summary_domain_5, $summary_domain_6,
            $hpc_card_id, $school_id
        ]);
    }

    // Save physical measurements (Height & Weight - Satra 1 & 2)
    $h_s1 = !empty($_POST['height_sem1']) ? (float)$_POST['height_sem1'] : null;
    $w_s1 = !empty($_POST['weight_sem1']) ? (float)$_POST['weight_sem1'] : null;
    $h_s2 = !empty($_POST['height_sem2']) ? (float)$_POST['height_sem2'] : null;
    $w_s2 = !empty($_POST['weight_sem2']) ? (float)$_POST['weight_sem2'] : null;

    try {
        $db->prepare("UPDATE students SET 
            height_sem1 = ?, weight_sem1 = ?,
            height_sem2 = ?, weight_sem2 = ?
            WHERE id = ? AND school_id = ?
        ")->execute([$h_s1, $w_s1, $h_s2, $w_s2, $student_id, $school_id]);

        $db->prepare("INSERT INTO cce_student_extra 
            (student_id, school_id, academic_year, height_1, weight_1, height_2, weight_2)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                academic_year = VALUES(academic_year),
                height_1 = VALUES(height_1), weight_1 = VALUES(weight_1),
                height_2 = VALUES(height_2), weight_2 = VALUES(weight_2)
        ")->execute([$student_id, $school_id, academic_year(), $h_s1 !== null ? (string)$h_s1 : '', $w_s1 !== null ? (string)$w_s1 : '', $h_s2 !== null ? (string)$h_s2 : '', $w_s2 !== null ? (string)$w_s2 : '']);
    } catch (Throwable $e) {}

    // Save attendance (जून ते मे - १२ महिने)
    $months = [6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec', 1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may'];
    foreach ($months as $num => $name) {
        $working = intval($_POST["working_$name"] ?? 0);
        $present = intval($_POST["present_$name"] ?? 0);
        if ($working > 0 || $present > 0) {
            $stmt = $db->prepare("INSERT INTO attendance (student_id, academic_year, month, working_days, days_present) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE working_days = VALUES(working_days), days_present = VALUES(days_present)");
            $stmt->execute([$student_id, academic_year(), $num, $working, $present]);
        } else {
            $db->prepare("DELETE FROM attendance WHERE student_id = ? AND academic_year = ? AND month = ?")->execute([$student_id, academic_year(), $num]);
        }
    }

    // Save domain assessments (६ विकास क्षेत्रे)
    foreach ($domains as $domain_id => $domain) {
        $prefix = "domain_{$domain_id}_";

        $goals_selected = (array)($_POST[$prefix . 'goals'] ?? []);
        $comps_selected = (array)($_POST[$prefix . 'competencies'] ?? []);

        $goals_selected_t2 = (array)($_POST[$prefix . 'goals_term2'] ?? []);
        $comps_selected_t2 = (array)($_POST[$prefix . 'competencies_term2'] ?? []);

        // Auto-map Curricular Goals (CG-X) from selected competencies (C-X.Y)
        foreach ($comps_selected as $cc) {
            if (preg_match('/^C-(\d+)\.\d+$/', (string)$cc, $m)) {
                $gCode = 'CG-' . $m[1];
                if (!in_array($gCode, $goals_selected, true)) {
                    $goals_selected[] = $gCode;
                }
            }
        }
        foreach ($comps_selected_t2 as $cc2) {
            if (preg_match('/^C-(\d+)\.\d+$/', (string)$cc2, $m)) {
                $gCode = 'CG-' . $m[1];
                if (!in_array($gCode, $goals_selected_t2, true)) {
                    $goals_selected_t2[] = $gCode;
                }
            }
        }

        // Collect per-competency activities (Term 1)
        $comp_acts = [];
        foreach ($comps_selected as $cc) {
            $key = $prefix . 'comp_activity_' . $cc;
            if (!empty($_POST[$key])) {
                $comp_acts[$cc] = trim($_POST[$key]);
            }
        }
        // Collect per-competency activities (Term 2)
        $comp_acts_t2 = [];
        foreach ($comps_selected_t2 as $cc2) {
            $key2 = $prefix . 'comp_activity_t2_' . $cc2;
            if (!empty($_POST[$key2])) {
                $comp_acts_t2[$cc2] = trim($_POST[$key2]);
            }
        }

        $data = [
            'hpc_card_id'               => $hpc_card_id,
            'domain_id'                 => $domain_id,
            'domain_name'               => $domain['name'],
            'domain_name_mr'            => $domain['name_mr'],
            'curricular_goals'          => json_encode($goals_selected, JSON_UNESCAPED_UNICODE),
            'competencies'              => json_encode($comps_selected, JSON_UNESCAPED_UNICODE),
            'activity_mr'               => trim($_POST[$prefix . 'activity'] ?? ''),
            'assessment_questions_mr'   => trim($_POST[$prefix . 'assessment_questions'] ?? ''),
            'awareness_level'           => $_POST[$prefix . 'awareness'] ?? null,
            'sensitivity_level'         => $_POST[$prefix . 'sensitivity'] ?? null,
            'creativity_level'          => $_POST[$prefix . 'creativity'] ?? null,
            'teacher_feedback_mr'       => trim($_POST[$prefix . 'teacher_feedback'] ?? ''),
            'self_assessment'           => trim($_POST[$prefix . 'self_assessment'] ?? ''),
            'peer_assessment'           => trim($_POST[$prefix . 'peer_assessment'] ?? ''),
            'self_emoji'                => trim($_POST[$prefix . 'self_emoji'] ?? ''),
            'peer_emoji'                => trim($_POST[$prefix . 'peer_emoji'] ?? ''),
            'parent_observation_mr'     => trim($_POST[$prefix . 'parent_observation'] ?? ''),
            'competency_activities'     => json_encode($comp_acts, JSON_UNESCAPED_UNICODE),
            // Term 2
            'curricular_goals_term2'    => json_encode($goals_selected_t2, JSON_UNESCAPED_UNICODE),
            'competencies_term2'        => json_encode($comps_selected_t2, JSON_UNESCAPED_UNICODE),
            'activity_mr_term2'         => trim($_POST[$prefix . 'activity_term2'] ?? ''),
            'assessment_questions_mr_term2' => trim($_POST[$prefix . 'assessment_questions_term2'] ?? ''),
            'awareness_level_term2'     => $_POST[$prefix . 'awareness_term2'] ?? null,
            'sensitivity_level_term2'   => $_POST[$prefix . 'sensitivity_term2'] ?? null,
            'creativity_level_term2'    => $_POST[$prefix . 'creativity_term2'] ?? null,
            'teacher_feedback_mr_term2' => trim($_POST[$prefix . 'teacher_feedback_term2'] ?? ''),
            'self_assessment_term2'     => trim($_POST[$prefix . 'self_assessment_term2'] ?? ''),
            'peer_assessment_term2'     => trim($_POST[$prefix . 'peer_assessment_term2'] ?? ''),
            'self_emoji_term2'          => trim($_POST[$prefix . 'self_emoji_term2'] ?? ''),
            'peer_emoji_term2'          => trim($_POST[$prefix . 'peer_emoji_term2'] ?? ''),
            'parent_observation_mr_term2' => trim($_POST[$prefix . 'parent_observation_term2'] ?? ''),
            'competency_activities_term2' => json_encode($comp_acts_t2, JSON_UNESCAPED_UNICODE),
        ];

        $stmt = $db->prepare("SELECT id FROM hpc_domain_assessments WHERE hpc_card_id = ? AND domain_id = ?");
        $stmt->execute([$hpc_card_id, $domain_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE hpc_domain_assessments SET 
                curricular_goals=?, competencies=?, activity_mr=?, assessment_questions_mr=?, 
                awareness_level=?, sensitivity_level=?, creativity_level=?, teacher_feedback_mr=?, 
                self_assessment=?, peer_assessment=?, parent_observation_mr=?, competency_activities=?, 
                curricular_goals_term2=?, competencies_term2=?, activity_mr_term2=?, assessment_questions_mr_term2=?, 
                awareness_level_term2=?, sensitivity_level_term2=?, creativity_level_term2=?, teacher_feedback_mr_term2=?, 
                self_assessment_term2=?, peer_assessment_term2=?, parent_observation_mr_term2=?, competency_activities_term2=?, 
                self_emoji=?, peer_emoji=?, self_emoji_term2=?, peer_emoji_term2=? 
                WHERE id=?");
            $stmt->execute([
                $data['curricular_goals'], $data['competencies'], $data['activity_mr'],
                $data['assessment_questions_mr'], $data['awareness_level'], $data['sensitivity_level'],
                $data['creativity_level'], $data['teacher_feedback_mr'], $data['self_assessment'],
                $data['peer_assessment'], $data['parent_observation_mr'], $data['competency_activities'],
                $data['curricular_goals_term2'], $data['competencies_term2'], $data['activity_mr_term2'],
                $data['assessment_questions_mr_term2'], $data['awareness_level_term2'], $data['sensitivity_level_term2'],
                $data['creativity_level_term2'], $data['teacher_feedback_mr_term2'], $data['self_assessment_term2'],
                $data['peer_assessment_term2'], $data['parent_observation_mr_term2'], $data['competency_activities_term2'],
                $data['self_emoji'], $data['peer_emoji'], $data['self_emoji_term2'], $data['peer_emoji_term2'],
                $existing['id']
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO hpc_domain_assessments (
                hpc_card_id, domain_id, domain_name, domain_name_mr, 
                curricular_goals, competencies, activity_mr, assessment_questions_mr, 
                awareness_level, sensitivity_level, creativity_level, teacher_feedback_mr, 
                self_assessment, peer_assessment, parent_observation_mr, competency_activities, 
                curricular_goals_term2, competencies_term2, activity_mr_term2, assessment_questions_mr_term2, 
                awareness_level_term2, sensitivity_level_term2, creativity_level_term2, teacher_feedback_mr_term2, 
                self_assessment_term2, peer_assessment_term2, parent_observation_mr_term2, competency_activities_term2, 
                self_emoji, peer_emoji, self_emoji_term2, peer_emoji_term2
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $data['hpc_card_id'], $data['domain_id'], $data['domain_name'], $data['domain_name_mr'],
                $data['curricular_goals'], $data['competencies'], $data['activity_mr'],
                $data['assessment_questions_mr'], $data['awareness_level'], $data['sensitivity_level'],
                $data['creativity_level'], $data['teacher_feedback_mr'], $data['self_assessment'],
                $data['peer_assessment'], $data['parent_observation_mr'], $data['competency_activities'],
                $data['curricular_goals_term2'], $data['competencies_term2'], $data['activity_mr_term2'],
                $data['assessment_questions_mr_term2'], $data['awareness_level_term2'], $data['sensitivity_level_term2'],
                $data['creativity_level_term2'], $data['teacher_feedback_mr_term2'], $data['self_assessment_term2'],
                $data['peer_assessment_term2'], $data['parent_observation_mr_term2'], $data['competency_activities_term2'],
                $data['self_emoji'], $data['peer_emoji'], $data['self_emoji_term2'], $data['peer_emoji_term2']
            ]);
        }
    }

    // Save credit framework (Part C)
    $db->prepare("DELETE FROM hpc_credits WHERE hpc_card_id = ?")->execute([$hpc_card_id]);
    foreach ($domains as $domain_id => $domain) {
        $earned = floatval($_POST["credit_earned_$domain_id"] ?? 0);
        $earned_t2 = floatval($_POST["credit_earned_term2_$domain_id"] ?? 0);
        $stmt = $db->prepare("INSERT INTO hpc_credits (hpc_card_id, domain_name, domain_name_mr, credits, ncf_level, credit_points, credit_points_earned, credit_points_earned_term2) VALUES (?, ?, ?, 4.5, 0.2, 0.9, ?, ?)");
        $stmt->execute([$hpc_card_id, $domain['name'], $domain['name_mr'], $earned, $earned_t2]);
    }

    flash('success', $status === 'completed' ? 'HPC कार्ड पूर्ण झाले!' : 'HPC कार्ड मसुदा जतन झाला!');
    redirect(APP_URL . '/hpc/create.php?student_id=' . $student_id);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.rubric-cell {
    background: #ffffff;
    border: 2px solid transparent !important;
    border-radius: 6px;
    padding: 10px 12px;
}
.rubric-cell:hover {
    background: #f8fafc;
    border-color: #cbd5e1 !important;
}
.rubric-cell.selected-level-nirjhar {
    background: #eff6ff !important;
    border-color: #3b82f6 !important;
}
.rubric-cell.selected-level-parvat {
    background: #f0fdf4 !important;
    border-color: #22c55e !important;
}
.rubric-cell.selected-level-akash {
    background: #fffbeb !important;
    border-color: #f59e0b !important;
}
.emoji-radio-label {
    cursor: pointer;
    text-align: center;
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    min-width: 95px;
    background: #fff;
    transition: all 0.2s;
}
.emoji-radio-label:hover {
    border-color: #94a3b8;
}
.emoji-radio-label.selected {
    border-color: #10b981 !important;
    background: #ecfdf5 !important;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15);
}
.comp-badge-pick {
    cursor: pointer;
    transition: all 0.15s;
    font-size: 11.5px;
}
.comp-badge-pick:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h2 class="mb-0 text-primary"><i class="bi bi-card-checklist"></i> समग्र प्रगतिपत्रक (HPC) – पायाभूत स्तर</h2>
        <div class="text-muted small">इयत्ता १ व २ (Foundational Stage) · ६ विकास क्षेत्रे · ६९ क्षमता · अधिकृत ३ कामगिरी स्तर (निर्झर, पर्वत, आकाश)</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/hpc/index.php" class="btn btn-outline-secondary"><i class="bi bi-grid"></i> डॅशबोर्ड</a>
        <?php if ($student && $hpc_card): ?>
            <a href="<?= APP_URL ?>/cards/print_hpc_19.php?id=<?= $hpc_card['id'] ?>&format=official19" target="_blank" class="btn btn-success">
                <i class="bi bi-printer-fill"></i> अधिकृत १९-पानी PDF पहा
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Student Selection -->
<?php if (!$student): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white py-3">
        <h5 class="mb-0"><i class="bi bi-person-circle"></i> विद्यार्थी निवडा (इयत्ता १ व २)</h5>
    </div>
    <div class="card-body p-4">
        <?php if (empty($all_students)): ?>
            <div class="text-center text-muted py-4">
                <p>अद्याप कोणताही विद्यार्थी जोडला नाही.</p>
                <a href="<?= APP_URL ?>/students/add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> विद्यार्थी जोडा</a>
            </div>
        <?php else: ?>
            <form method="GET">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">विद्यार्थ्याचे नाव निवडा:</label>
                        <select class="form-select form-select-lg" name="student_id" required onchange="this.form.submit()">
                            <option value="">-- विद्यार्थी निवडा --</option>
                            <?php 
                            // Prioritize std 1 and std 2
                            $foundational_students = [];
                            $other_students = [];
                            $can_access_higher = can_access_hpc_class(3, $school_id)['allowed'];
                            foreach ($all_students as $s) {
                                $s_std = (int) ($s['std'] ?? 0) ?: hpc_std_from_grade($s['grade'] ?? '');
                                if ($s_std <= 2) $foundational_students[] = $s;
                                else $other_students[] = $s;
                            }
                            ?>
                            <optgroup label="पायाभूत स्तर (इयत्ता १ व २ - मोफत)">
                                <?php foreach ($foundational_students as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= sanitize($s['name_mr'] ?: $s['name']) ?> (रोल: <?= sanitize($s['roll_no'] ?: '-') ?>, <?= sanitize($s['grade']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if (!empty($other_students)): ?>
                            <optgroup label="इतर वर्ग (इयत्ता ३ ते ८ - <?= $can_access_higher ? 'स्टेज फॉर्म' : 'सशुल्क योजना' ?>)">
                                <?php foreach ($other_students as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $can_access_higher ? '' : 'disabled' ?>>
                                        <?= sanitize($s['name_mr'] ?: $s['name']) ?> (रोल: <?= sanitize($s['roll_no'] ?: '-') ?>, <?= sanitize($s['grade']) ?>) <?= $can_access_higher ? '↗' : '🔒 (सशुल्क)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mt-md-4">
                        <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-arrow-right"></i> HPC भरणे सुरू करा</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<!-- Student Info Banner -->
<div class="card mb-4 border-0 shadow-sm" style="border-left: 5px solid #2563eb !important; background: linear-gradient(135deg, #f8fafc, #eff6ff);">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-box text-center">
                    <?php if (!empty($student['photo'])): ?>
                        <img src="<?= APP_URL . '/' . $student['photo'] ?>" alt="" style="width:65px;height:75px;object-fit:cover;border-radius:8px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.15);">
                    <?php else: ?>
                        <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center shadow-sm" style="width:65px;height:75px;font-size:1.8rem;"><i class="bi bi-person"></i></div>
                    <?php endif; ?>
                </div>
                <div>
                    <h4 class="text-primary mb-1 fw-bold">
                        <a href="<?= APP_URL ?>/students/edit.php?id=<?= $student_id ?>&return=create" class="text-primary text-decoration-none hover-underline" title="विद्यार्थी माहिती, उंची, वजन संपादित करा">
                            <?= sanitize($student['name_mr'] ?: $student['name']) ?>
                            <i class="bi bi-pencil-square fs-6 text-primary ms-1"></i>
                        </a>
                    </h4>
                    <div class="d-flex flex-wrap gap-2 text-muted small align-items-center">
                        <span class="badge bg-primary-subtle text-primary border">इयत्ता: <strong><?= sanitize($student['grade']) ?></strong></span>
                        <span class="badge bg-light text-dark border">रोल नं: <strong><?= sanitize($student['roll_no'] ?: '-') ?></strong></span>
                        <span class="badge bg-light text-dark border">तुकडी: <strong><?= sanitize($student['section'] ?: '-') ?></strong></span>
                        <span class="badge bg-light text-dark border">लिंग: <strong><?= sanitize($student['gender'] ?: '-') ?></strong></span>
                        <span class="badge bg-light text-dark border">वर्ष: <strong><?= academic_year() ?></strong></span>
                        <?php if ($hpc_card && $hpc_card['status'] === 'completed'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> HPC पूर्ण</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-pencil"></i> मसुदा</span>
                        <?php endif; ?>
                        <a href="<?= APP_URL ?>/students/edit.php?id=<?= $student_id ?>&return=create" class="badge bg-info-subtle text-info-emphasis border text-decoration-none">
                            <i class="bi bi-person-gear"></i> माहिती/उंची-वजन संपादन
                        </a>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/students/edit.php?id=<?= $student_id ?>&return=create" class="btn btn-sm btn-outline-primary" title="विद्यार्थी माहिती, उंची, वजन संपादन करा">
                    <i class="bi bi-pencil-square"></i> विद्यार्थी संपादन
                </a>
                <a href="<?= APP_URL ?>/hpc/create.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left-right"></i> दुसरा विद्यार्थी</a>
                <?php if ($hpc_card): ?>
                    <a href="<?= APP_URL ?>/cards/print_hpc_19.php?id=<?= $hpc_card['id'] ?>&format=official19" target="_blank" class="btn btn-sm btn-success">
                        <i class="bi bi-printer-fill"></i> रंगीत कार्ड (१९ पाने)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- HPC Form -->
<form method="POST" id="hpcForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <!-- Main HPC Tabs -->
    <ul class="nav nav-pills hpc-tabs mb-4 p-2 bg-white rounded shadow-sm border" role="tablist" style="gap:6px;">
        <li class="nav-item"><a class="nav-link active fw-bold" data-bs-toggle="tab" href="#partB"><i class="bi bi-clipboard-data"></i> भाग B - ६ डोमेन मूल्यांकन (सत्र १ व २)</a></li>
        <li class="nav-item"><a class="nav-link fw-bold" data-bs-toggle="tab" href="#partSummary"><i class="bi bi-journal-text"></i> भाग C - शैक्षणिक वर्षाचा सारांश (पान १६)</a></li>
        <li class="nav-item"><a class="nav-link fw-bold" data-bs-toggle="tab" href="#partA1"><i class="bi bi-building"></i> भाग A(1) - शाळा व शिक्षक</a></li>
        <li class="nav-item"><a class="nav-link fw-bold" data-bs-toggle="tab" href="#partA2"><i class="bi bi-calendar-check"></i> भाग A(2) - उपस्थिती व आवड</a></li>
        <li class="nav-item"><a class="nav-link fw-bold" data-bs-toggle="tab" href="#partCredits"><i class="bi bi-award"></i> क्रेडिट्स (NCrF)</a></li>
    </ul>

    <div class="tab-content">

        <!-- ==================== PART B: DOMAIN ASSESSMENT ==================== -->
        <div class="tab-pane fade show active" id="partB">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-grid-3x3-gap-fill text-primary"></i> भाग B - विकास क्षेत्र मूल्यांकन (Domain Assessment)</h5>
                        <small class="text-muted">६ विकास क्षेत्रे · प्रत्येकासाठी सत्र १ व सत्र २ क्षमता निवड व ३-स्तरीय SCERT रुब्रिक (निर्झर, पर्वत, आकाश)</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted fw-bold">प्रगती:</span>
                        <span id="overallProgressText" class="badge bg-primary">0%</span>
                        <div class="progress" style="width:100px; height:8px;">
                            <div class="progress-bar bg-success" id="overallProgressBar" role="progressbar" style="width:0%"></div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <!-- Domain Quick Nav Buttons -->
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php 
                        $icons = ['1'=>'🏃','2'=>'💗','3'=>'🧠','4'=>'📖','5'=>'🎨','6'=>'📚'];
                        foreach ($domains as $did => $dom): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary domain-progress-btn" data-domain="<?= $did ?>" onclick="openDomainAccordion(<?= $did ?>)">
                                <span><?= $icons[$did] ?? '📋' ?></span>
                                <strong><?= $did ?>.</strong> <?= mb_substr($dom['name_mr'], 0, 14) ?>...
                                <span class="badge bg-secondary ms-1 domain-pct" id="domPct<?= $did ?>">0%</span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="accordion" id="domainAccordion">
                        <?php foreach ($domains as $domain_id => $domain):
                            $existing = $assessments[$domain_id] ?? [];
                            $existing_goals = !empty($existing['curricular_goals']) ? json_decode($existing['curricular_goals'], true) : [];
                            $existing_comps = !empty($existing['competencies']) ? json_decode($existing['competencies'], true) : [];
                            $existing_goals_t2 = !empty($existing['curricular_goals_term2']) ? json_decode($existing['curricular_goals_term2'], true) : [];
                            $existing_comps_t2 = !empty($existing['competencies_term2']) ? json_decode($existing['competencies_term2'], true) : [];
                            
                            $first_comp = !empty($existing_comps) ? $existing_comps[0] : array_key_first($domain['competencies']);
                            $first_comp_t2 = !empty($existing_comps_t2) ? $existing_comps_t2[0] : array_key_first($domain['competencies']);
                        ?>
                        <div class="accordion-item mb-3 border rounded shadow-sm overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $domain_id > 1 ? 'collapsed' : '' ?> bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#domain<?= $domain_id ?>">
                                    <span class="fs-5 me-2"><?= $icons[$domain_id] ?? '📋' ?></span>
                                    <div>
                                        <strong>क्षेत्र क्र. <?= $domain_id ?>: <?= $domain['name_mr'] ?></strong>
                                        <div class="text-muted small"><?= $domain['name'] ?> (<?= count($domain['competencies']) ?> क्षमता)</div>
                                    </div>
                                    <span class="badge bg-info ms-auto me-2 domain-header-pct" id="domHdrPct<?= $domain_id ?>">0%</span>
                                </button>
                            </h2>
                            <div id="domain<?= $domain_id ?>" class="accordion-collapse collapse <?= $domain_id == 1 ? 'show' : '' ?>" data-bs-parent="#domainAccordion">
                                <div class="accordion-body p-3 p-md-4">
                                    
                                    <!-- Nav tabs for Term 1 vs Term 2 inside domain -->
                                    <ul class="nav nav-tabs mb-4" id="domTab_<?= $domain_id ?>" role="tablist">
                                        <li class="nav-item">
                                            <button class="nav-link active fw-bold text-primary" data-bs-toggle="tab" data-bs-target="#dom_<?= $domain_id ?>_t1" type="button">
                                                📘 प्रथम सत्र (Term 1)
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link fw-bold text-success" data-bs-toggle="tab" data-bs-target="#dom_<?= $domain_id ?>_t2" type="button">
                                                📙 द्वितीय सत्र (Term 2)
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content">
                                        <!-- ================= TERM 1 ================= -->
                                        <div class="tab-pane fade show active" id="dom_<?= $domain_id ?>_t1">
                                            
                                            <!-- Curricular Goals -->
                                            <div class="mb-4 p-3 bg-light rounded border">
                                                <h6 class="text-primary fw-bold mb-2"><i class="bi bi-bullseye"></i> अभ्यासक्रम ध्येये (Curricular Goals) - किमान एक निवडा:</h6>
                                                <div class="row g-2">
                                                    <?php foreach ($domain['goals'] as $code => $goal): ?>
                                                    <div class="col-md-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="domain_<?= $domain_id ?>_goals[]" value="<?= $code ?>" id="goal_<?= $domain_id ?>_<?= $code ?>" <?= in_array($code, $existing_goals ?? []) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="goal_<?= $domain_id ?>_<?= $code ?>">
                                                                <strong class="text-dark"><?= $code ?>:</strong> <?= $goal ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Competencies Selection & Dropdowns -->
                                            <div class="mb-4">
                                                <h6 class="text-primary fw-bold mb-1"><i class="bi bi-check2-square"></i> क्षमता (Competencies) - प्रथम सत्रासाठी क्षमता निवडा:</h6>
                                                <p class="text-muted small mb-2">क्षमता निवडल्यावर त्या क्षमतेसाठीची अधिकृत कृती व शिक्षक अभिप्राय खाली आपोआप उपलब्ध होतील.</p>
                                                
                                                <!-- Quick Competency Dropdown (Term 1) -->
                                                <div class="mb-3 p-2 bg-primary-subtle rounded border border-primary-subtle">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label class="form-label mb-0 fw-bold small text-primary text-nowrap"><i class="bi bi-ui-checks"></i> 🎯 जलद क्षमता निवडा:</label>
                                                        <select class="form-select form-select-sm comp-quick-select" data-domain="<?= $domain_id ?>" data-term="1">
                                                            <option value="">-- क्षमता ड्रॉपडाउनमधून निवडा (Auto-selects Competency & Goal) --</option>
                                                            <?php foreach ($domain['competencies'] as $c_code => $c_desc): ?>
                                                                <option value="<?= $c_code ?>" <?= in_array($c_code, $existing_comps ?? []) ? 'selected' : '' ?>>
                                                                    <?= $c_code ?> : <?= mb_substr($c_desc, 0, 85) ?>...
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="row g-2">
                                                    <?php 
                                                    $existing_comp_activities = !empty($existing['competency_activities']) ? json_decode($existing['competency_activities'], true) : [];
                                                    foreach ($domain['competencies'] as $comp_code => $comp_desc): 
                                                        $is_checked = in_array($comp_code, $existing_comps ?? []);
                                                    ?>
                                                    <div class="col-12 competency-item border rounded p-2 mb-2 bg-white">
                                                        <div class="form-check">
                                                            <input class="form-check-input competency-checkbox" type="checkbox" name="domain_<?= $domain_id ?>_competencies[]" value="<?= $comp_code ?>" id="comp_<?= $domain_id ?>_<?= $comp_code ?>" data-comp-code="<?= $comp_code ?>" data-domain="<?= $domain_id ?>" data-term="1" <?= $is_checked ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="comp_<?= $domain_id ?>_<?= $comp_code ?>">
                                                                <strong class="badge bg-primary-subtle text-primary border"><?= $comp_code ?></strong>
                                                                <span class="ms-1 fw-semibold text-dark"><?= $comp_desc ?></span>
                                                            </label>
                                                        </div>

                                                        <!-- Per-competency activity dropdown -->
                                                        <div class="comp-activity-dropdown ms-4 mt-2 mb-1" id="comp_activity_<?= $domain_id ?>_<?= $comp_code ?>" style="<?= $is_checked ? '' : 'display:none;' ?>">
                                                            <?php if (isset($competency_activities[$comp_code])): ?>
                                                            <label class="form-label text-muted small fw-bold">📋 मूल्यांकनासाठी कृती/उपक्रम निवडा (<?= $comp_code ?>):</label>
                                                            <select class="form-select form-select-sm comp-activity-select" name="domain_<?= $domain_id ?>_comp_activity_<?= $comp_code ?>">
                                                                <option value="">-- कृती/उपक्रम निवडा --</option>
                                                                <?php foreach ($competency_activities[$comp_code] as $idx => $activity): ?>
                                                                    <option value="<?= htmlspecialchars($activity, ENT_QUOTES) ?>" <?= (($existing_comp_activities[$comp_code] ?? '') === $activity) ? 'selected' : '' ?>>
                                                                        <?= (($existing_comp_activities[$comp_code] ?? '') === $activity) ? '✅ ' : '' ?>📌 <?= $idx + 1 ?>. <?= mb_substr($activity, 0, 110) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Per-competency question dropdown -->
                                                        <div class="comp-question-dropdown ms-4 mt-1 mb-2" id="comp_question_<?= $domain_id ?>_<?= $comp_code ?>" style="<?= $is_checked ? '' : 'display:none;' ?>">
                                                            <?php if (!empty($competency_questions[$comp_code])): ?>
                                                            <label class="form-label text-muted small fw-bold"><i class="bi bi-question-circle text-primary"></i> ❓ या क्षमतेसाठी मूल्यमापन प्रश्न निवडा (<?= $comp_code ?>):</label>
                                                            <select class="form-select form-select-sm comp-question-select" data-target="domain_<?= $domain_id ?>_assessment_questions">
                                                                <option value="">-- प्रश्न निवडा (खाली जोडण्यासाठी) --</option>
                                                                <?php foreach ($competency_questions[$comp_code] as $qidx => $qtext): ?>
                                                                    <option value="<?= htmlspecialchars($qtext, ENT_QUOTES) ?>">
                                                                        ❓ <?= $qidx + 1 ?>. <?= mb_substr($qtext, 0, 110) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Activity & Questions Row -->
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <div class="card h-100 border">
                                                        <div class="card-header bg-light py-2">
                                                            <strong class="small text-primary"><i class="bi bi-pencil"></i> मूल्यमापनासाठी केलेली कृती/उपक्रम (Activity)</strong>
                                                        </div>
                                                        <div class="card-body p-2">
                                                            <textarea class="form-control" name="domain_<?= $domain_id ?>_activity" id="domain_<?= $domain_id ?>_activity" rows="3" placeholder="उपक्रमाचे वर्णन लिहा... (किंवा वरील क्षमता ड्रॉपडाउनमधून निवडा)"><?= sanitize($existing['activity_mr'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card h-100 border shadow-sm">
                                                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                            <strong class="small text-primary"><i class="bi bi-question-circle-fill text-warning"></i> मूल्यमापनाचे प्रश्न (Assessment Questions)</strong>
                                                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 btn-fill-domain-questions" data-domain="<?= $domain_id ?>" data-target="domain_<?= $domain_id ?>_assessment_questions" title="या क्षेत्रातील सर्व आदर्श प्रश्न भरा">
                                                                <i class="bi bi-magic"></i> आदर्श प्रश्न भरा
                                                            </button>
                                                        </div>
                                                        <div class="card-body p-2">
                                                            <div class="mb-2">
                                                                <label class="form-label text-muted small fw-bold mb-1">📋 प्रश्न ड्रॉपडाउन (निवडा व खाली आपोआप जोडले जाईल):</label>
                                                                <select class="form-select form-select-sm domain-question-dropdown" data-target="domain_<?= $domain_id ?>_assessment_questions">
                                                                    <option value="">-- या क्षेत्रातील आदर्श प्रश्न निवडा --</option>
                                                                    <?php if (!empty($domain_sample_questions[$domain_id])): ?>
                                                                        <?php foreach ($domain_sample_questions[$domain_id] as $dqidx => $dq): ?>
                                                                            <option value="<?= htmlspecialchars($dq, ENT_QUOTES) ?>">❓ <?= $dqidx + 1 ?>. <?= mb_substr($dq, 0, 95) ?></option>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </select>
                                                            </div>
                                                            <textarea class="form-control" name="domain_<?= $domain_id ?>_assessment_questions" id="domain_<?= $domain_id ?>_assessment_questions" rows="3" placeholder="प्रश्न लिहा किंवा वरील ड्रॉपडाउनमधून निवडा..."><?= sanitize($existing['assessment_questions_mr'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- SCERT OFFICIAL 3-LEVEL RUBRIC TABLE (सत्र १) -->
                                            <div class="mb-4">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="text-primary fw-bold mb-0">
                                                        <i class="bi bi-star-fill text-warning"></i> मूल्यांकन निकषसंच (रुब्रिक) – प्रथम सत्र
                                                    </h6>
                                                    <span class="badge bg-light text-muted border">अधिकृत ३ कामगिरी स्तर (SCERT)</span>
                                                </div>
                                                <p class="text-muted small mb-2">खालील प्रत्येक क्षमतेसाठी विद्यार्थ्याचा योग्य कामगिरी स्तर (निर्झर / पर्वत / आकाश) निवडा:</p>

                                                <div class="table-responsive">
                                                    <table class="table table-bordered rubric-table align-middle mb-0">
                                                        <thead class="table-light text-center">
                                                            <tr>
                                                                <th style="width:16%;background:#f8fafc;">क्षमता / घटक</th>
                                                                <th style="width:28%;background:#e0f2fe;color:#0369a1;">💧 निर्झर (Nirjhar)<br><small class="text-muted fw-normal">प्रारंभिक / स्तर १</small></th>
                                                                <th style="width:28%;background:#ecfdf5;color:#047857;">🏔 पर्वत (Parvat)<br><small class="text-muted fw-normal">मध्यम / स्तर २</small></th>
                                                                <th style="width:28%;background:#fef3c7;color:#b45309;">🌌 आकाश (Akash)<br><small class="text-muted fw-normal">प्रगत / स्तर ३</small></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $abilities_map = ['awareness' => 'जाणीवजागृती', 'sensitivity' => 'संवेदनशीलता', 'creativity' => 'सर्जनशीलता'];
                                                            foreach ($abilities_map as $ab_key => $ab_label): 
                                                                $cur_val = $existing[$ab_key . '_level'] ?? '';
                                                                if ($cur_val === 'pailu' || $cur_val === 'pravah') $cur_val = 'nirjhar';
                                                            ?>
                                                            <tr>
                                                                <td class="fw-bold bg-light" style="font-size: 13.5px;"><?= $ab_label ?></td>
                                                                <?php foreach (['nirjhar' => ['#e0f2fe', '#0369a1', '💧 निर्झर'], 'parvat' => ['#ecfdf5', '#047857', '🏔 पर्वत'], 'akash' => ['#fef3c7', '#b45309', '🌌 आकाश']] as $lvl_key => $lvl_meta): 
                                                                    $is_sel = ($cur_val === $lvl_key);
                                                                    $desc = $fs_descriptors[$ab_key][$lvl_key] ?? '';
                                                                ?>
                                                                <td class="rubric-cell <?= $is_sel ? 'selected-level-' . $lvl_key : '' ?>" style="vertical-align: top; cursor: pointer;" onclick="selectRubricRadio(this, 'domain_<?= $domain_id ?>_<?= $ab_key ?>', '<?= $lvl_key ?>')">
                                                                    <div class="form-check m-0 mb-1">
                                                                        <input class="form-check-input rubric-radio" type="radio" name="domain_<?= $domain_id ?>_<?= $ab_key ?>" id="r_<?= $domain_id ?>_<?= $ab_key ?>_<?= $lvl_key ?>" value="<?= $lvl_key ?>" <?= $is_sel ? 'checked' : '' ?>>
                                                                        <label class="form-check-label fw-bold small text-dark" for="r_<?= $domain_id ?>_<?= $ab_key ?>_<?= $lvl_key ?>">
                                                                            <?= $lvl_meta[2] ?>
                                                                        </label>
                                                                    </div>
                                                                    <div class="small text-muted" style="font-size: 11px; line-height: 1.4;">
                                                                        <?= $desc ?>
                                                                    </div>
                                                                </td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Teacher Feedback with Dynamic cce_abhipray2 Selector (Term 1) -->
                                            <div class="mb-4 p-3 bg-light rounded border">
                                                <h6 class="text-primary fw-bold mb-2">
                                                    <i class="bi bi-chat-left-dots-fill"></i> 👩‍🏫 शिक्षकांचा अभिप्राय (Teacher's Feedback) – प्रथम सत्र
                                                </h6>
                                                
                                                <!-- Dynamic Competency Abhipray Dropdown -->
                                                <div class="mb-2">
                                                    <label class="form-label text-muted small fw-bold">
                                                        📋 निवडलेल्या क्षमतेनुसार अधिकृत अभिप्राय निवडा (cce_abhipray2):
                                                    </label>
                                                    <select class="form-select form-select-sm dynamic-abhipray-select" id="abhipray_select_<?= $domain_id ?>_1" data-target="domain_<?= $domain_id ?>_teacher_feedback">
                                                        <option value="">-- अभिप्राय निवडा --</option>
                                                        <?php 
                                                        // Populate for the initially checked competency
                                                        $init_comp = !empty($existing_comps) ? $existing_comps[0] : array_key_first($domain['competencies']);
                                                        if (isset($all_abhipray[$init_comp])):
                                                            foreach ($all_abhipray[$init_comp] as $idx => $abh): ?>
                                                                <option value="<?= htmlspecialchars($abh, ENT_QUOTES) ?>">📌 <?= $idx + 1 ?>. <?= mb_substr($abh, 0, 95) ?>...</option>
                                                            <?php endforeach;
                                                        endif; ?>
                                                    </select>
                                                </div>
                                                
                                                <textarea class="form-control" name="domain_<?= $domain_id ?>_teacher_feedback" id="domain_<?= $domain_id ?>_teacher_feedback" rows="3" placeholder="शिक्षकांचा अभिप्राय लिहा... किंवा वरील ड्रॉपडाउनमधून निवडा"><?= sanitize($existing['teacher_feedback_mr'] ?? '') ?></textarea>
                                            </div>

                                            <!-- Self Assessment & Peer Assessment (Term 1) -->
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="card h-100 border">
                                                        <div class="card-header bg-light py-2">
                                                            <strong class="small text-primary"><i class="bi bi-emoji-smile"></i> स्वयंमूल्यांकन (Self Assessment)</strong>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                                <?php foreach ($self_emoji_options as $elabel => $eicon): 
                                                                    $is_e = (($existing['self_emoji'] ?? '') === $elabel);
                                                                ?>
                                                                <label class="emoji-radio-label <?= $is_e ? 'selected' : '' ?>">
                                                                    <input type="radio" name="domain_<?= $domain_id ?>_self_emoji" value="<?= htmlspecialchars($elabel, ENT_QUOTES) ?>" class="d-none emoji-radio" <?= $is_e ? 'checked' : '' ?>>
                                                                    <div style="font-size:24px;"><?= $eicon ?></div>
                                                                    <div style="font-size:11px;margin-top:2px;" class="fw-semibold"><?= $elabel ?></div>
                                                                </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card h-100 border">
                                                        <div class="card-header bg-light py-2">
                                                            <strong class="small text-primary"><i class="bi bi-people"></i> सहाध्यायी मूल्यांकन (Peer Assessment)</strong>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                                <?php foreach ($peer_emoji_options as $elabel => $eicon): 
                                                                    $is_pe = (($existing['peer_emoji'] ?? '') === $elabel);
                                                                ?>
                                                                <label class="emoji-radio-label <?= $is_pe ? 'selected' : '' ?>">
                                                                    <input type="radio" name="domain_<?= $domain_id ?>_peer_emoji" value="<?= htmlspecialchars($elabel, ENT_QUOTES) ?>" class="d-none emoji-radio" <?= $is_pe ? 'checked' : '' ?>>
                                                                    <div style="font-size:24px;"><?= $eicon ?></div>
                                                                    <div style="font-size:11px;margin-top:2px;" class="fw-semibold"><?= $elabel ?></div>
                                                                </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Parent Observation (Term 1) -->
                                            <div class="mb-3">
                                                <label class="form-label text-muted small fw-bold"><i class="bi bi-house-heart"></i> पालक/काळजीवाहक निरीक्षण (Parent Observation):</label>
                                                <select class="form-select form-select-sm" name="domain_<?= $domain_id ?>_parent_observation">
                                                    <option value="">-- पालक निरीक्षण निवडा --</option>
                                                    <?php if (isset($demo_parent_observation_options[$domain_id])): ?>
                                                        <?php foreach ($demo_parent_observation_options[$domain_id] as $opt): ?>
                                                            <option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>" <?= (($existing['parent_observation_mr'] ?? '') === $opt) ? 'selected' : '' ?>>
                                                                <?= (($existing['parent_observation_mr'] ?? '') === $opt) ? '✅ ' : '' ?><?= htmlspecialchars($opt) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>

                                        </div>

                                        <!-- ================= TERM 2 ================= -->
                                        <div class="tab-pane fade" id="dom_<?= $domain_id ?>_t2">
                                            
                                            <!-- Curricular Goals Term 2 -->
                                            <div class="mb-4 p-3 bg-light rounded border">
                                                <h6 class="text-success fw-bold mb-2"><i class="bi bi-bullseye"></i> अभ्यासक्रम ध्येये - सत्र २ (Curricular Goals - Term 2):</h6>
                                                <div class="row g-2">
                                                    <?php foreach ($domain['goals'] as $code => $goal): ?>
                                                    <div class="col-md-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="domain_<?= $domain_id ?>_goals_term2[]" value="<?= $code ?>" id="goal_t2_<?= $domain_id ?>_<?= $code ?>" <?= in_array($code, $existing_goals_t2 ?? []) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="goal_t2_<?= $domain_id ?>_<?= $code ?>">
                                                                <strong class="text-dark"><?= $code ?>:</strong> <?= $goal ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Competencies Selection & Dropdowns Term 2 -->
                                            <div class="mb-4">
                                                <h6 class="text-success fw-bold mb-1"><i class="bi bi-check2-square"></i> क्षमता - सत्र २ (Competencies - Term 2):</h6>
                                                <p class="text-muted small mb-2">द्वितीय सत्रासाठी क्षमता निवडा:</p>
                                                
                                                <!-- Quick Competency Dropdown (Term 2) -->
                                                <div class="mb-3 p-2 bg-success-subtle rounded border border-success-subtle">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label class="form-label mb-0 fw-bold small text-success text-nowrap"><i class="bi bi-ui-checks"></i> 🎯 जलद क्षमता निवडा:</label>
                                                        <select class="form-select form-select-sm comp-quick-select" data-domain="<?= $domain_id ?>" data-term="2">
                                                            <option value="">-- क्षमता ड्रॉपडाउनमधून निवडा (Auto-selects Competency & Goal) --</option>
                                                            <?php foreach ($domain['competencies'] as $c_code => $c_desc): ?>
                                                                <option value="<?= $c_code ?>" <?= in_array($c_code, $existing_comps_t2 ?? []) ? 'selected' : '' ?>>
                                                                    <?= $c_code ?> : <?= mb_substr($c_desc, 0, 85) ?>...
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row g-2">
                                                    <?php 
                                                    $existing_comp_activities_t2 = !empty($existing['competency_activities_term2']) ? json_decode($existing['competency_activities_term2'], true) : [];
                                                    foreach ($domain['competencies'] as $comp_code_t2 => $comp_desc_t2): 
                                                        $is_checked_t2 = in_array($comp_code_t2, $existing_comps_t2 ?? []);
                                                    ?>
                                                    <div class="col-12 competency-item border rounded p-2 mb-2 bg-white">
                                                        <div class="form-check">
                                                            <input class="form-check-input competency-checkbox" type="checkbox" name="domain_<?= $domain_id ?>_competencies_term2[]" value="<?= $comp_code_t2 ?>" id="comp_t2_<?= $domain_id ?>_<?= $comp_code_t2 ?>" data-comp-code="<?= $comp_code_t2 ?>" data-domain="<?= $domain_id ?>" data-term="2" <?= $is_checked_t2 ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="comp_t2_<?= $domain_id ?>_<?= $comp_code_t2 ?>">
                                                                <strong class="badge bg-success-subtle text-success border"><?= $comp_code_t2 ?></strong>
                                                                <span class="ms-1 fw-semibold text-dark"><?= $comp_desc_t2 ?></span>
                                                            </label>
                                                        </div>

                                                        <!-- Per-competency activity dropdown Term 2 -->
                                                        <div class="comp-activity-dropdown ms-4 mt-2 mb-1" id="comp_activity_t2_<?= $domain_id ?>_<?= $comp_code_t2 ?>" style="<?= $is_checked_t2 ? '' : 'display:none;' ?>">
                                                            <?php if (isset($competency_activities[$comp_code_t2])): ?>
                                                            <label class="form-label text-muted small fw-bold">📋 मूल्यांकनासाठी कृती/उपक्रम निवडा - सत्र २ (<?= $comp_code_t2 ?>):</label>
                                                            <select class="form-select form-select-sm comp-activity-select" name="domain_<?= $domain_id ?>_comp_activity_t2_<?= $comp_code_t2 ?>">
                                                                <option value="">-- कृती/उपक्रम निवडा --</option>
                                                                <?php foreach ($competency_activities[$comp_code_t2] as $idx => $activity_t2): ?>
                                                                    <option value="<?= htmlspecialchars($activity_t2, ENT_QUOTES) ?>" <?= (($existing_comp_activities_t2[$comp_code_t2] ?? '') === $activity_t2) ? 'selected' : '' ?>>
                                                                        <?= (($existing_comp_activities_t2[$comp_code_t2] ?? '') === $activity_t2) ? '✅ ' : '' ?>📌 <?= $idx + 1 ?>. <?= mb_substr($activity_t2, 0, 110) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Per-competency question dropdown Term 2 -->
                                                        <div class="comp-question-dropdown ms-4 mt-1 mb-2" id="comp_question_t2_<?= $domain_id ?>_<?= $comp_code_t2 ?>" style="<?= $is_checked_t2 ? '' : 'display:none;' ?>">
                                                            <?php if (!empty($competency_questions[$comp_code_t2])): ?>
                                                            <label class="form-label text-muted small fw-bold"><i class="bi bi-question-circle text-success"></i> ❓ या क्षमतेसाठी मूल्यमापन प्रश्न निवडा (<?= $comp_code_t2 ?>):</label>
                                                            <select class="form-select form-select-sm comp-question-select" data-target="domain_<?= $domain_id ?>_assessment_questions_term2">
                                                                <option value="">-- प्रश्न निवडा (खाली जोडण्यासाठी) --</option>
                                                                <?php foreach ($competency_questions[$comp_code_t2] as $qidx => $qtext): ?>
                                                                    <option value="<?= htmlspecialchars($qtext, ENT_QUOTES) ?>">
                                                                        ❓ <?= $qidx + 1 ?>. <?= mb_substr($qtext, 0, 110) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <!-- Activity & Questions Row Term 2 -->
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <div class="card h-100 border">
                                                        <div class="card-header bg-light py-2">
                                                            <strong class="small text-success"><i class="bi bi-pencil"></i> मूल्यमापनासाठी केलेली कृती - सत्र २</strong>
                                                        </div>
                                                        <div class="card-body p-2">
                                                            <textarea class="form-control" name="domain_<?= $domain_id ?>_activity_term2" id="domain_<?= $domain_id ?>_activity_term2" rows="3" placeholder="सत्र २ उपक्रम वर्णन..."><?= sanitize($existing['activity_mr_term2'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card h-100 border shadow-sm">
                                                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                            <strong class="small text-success"><i class="bi bi-question-circle-fill text-warning"></i> मूल्यमापनाचे प्रश्न - सत्र २ (Assessment Questions)</strong>
                                                            <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 btn-fill-domain-questions" data-domain="<?= $domain_id ?>" data-target="domain_<?= $domain_id ?>_assessment_questions_term2" title="या क्षेत्रातील सर्व आदर्श प्रश्न भरा">
                                                                <i class="bi bi-magic"></i> आदर्श प्रश्न भरा
                                                            </button>
                                                        </div>
                                                        <div class="card-body p-2">
                                                            <div class="mb-2">
                                                                <label class="form-label text-muted small fw-bold mb-1">📋 प्रश्न ड्रॉपडाउन (निवडा व खाली आपोआप जोडले जाईल):</label>
                                                                <select class="form-select form-select-sm domain-question-dropdown" data-target="domain_<?= $domain_id ?>_assessment_questions_term2">
                                                                    <option value="">-- या क्षेत्रातील आदर्श प्रश्न निवडा --</option>
                                                                    <?php if (!empty($domain_sample_questions[$domain_id])): ?>
                                                                        <?php foreach ($domain_sample_questions[$domain_id] as $dqidx => $dq): ?>
                                                                            <option value="<?= htmlspecialchars($dq, ENT_QUOTES) ?>">❓ <?= $dqidx + 1 ?>. <?= mb_substr($dq, 0, 95) ?></option>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </select>
                                                            </div>
                                                            <textarea class="form-control" name="domain_<?= $domain_id ?>_assessment_questions_term2" id="domain_<?= $domain_id ?>_assessment_questions_term2" rows="3" placeholder="सत्र २ प्रश्न लिहा किंवा वरील ड्रॉपडाउनमधून निवडा..."><?= sanitize($existing['assessment_questions_mr_term2'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- SCERT OFFICIAL 3-LEVEL RUBRIC TABLE (सत्र २) -->
                                            <div class="mb-4">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="text-success fw-bold mb-0">
                                                        <i class="bi bi-star-fill text-warning"></i> मूल्यांकन निकषसंच (रुब्रिक) – द्वितीय सत्र
                                                    </h6>
                                                    <span class="badge bg-light text-muted border">अधिकृत ३ कामगिरी स्तर (SCERT)</span>
                                                </div>
                                                <p class="text-muted small mb-2">सत्र २ साठी प्रत्येक क्षमतेचा योग्य स्तर निवडा:</p>

                                                <div class="table-responsive">
                                                    <table class="table table-bordered rubric-table align-middle mb-0">
                                                        <thead class="table-light text-center">
                                                            <tr>
                                                                <th style="width:16%;background:#f8fafc;">क्षमता / घटक</th>
                                                                <th style="width:28%;background:#e0f2fe;color:#0369a1;">💧 निर्झर (Nirjhar)<br><small class="text-muted fw-normal">प्रारंभिक / स्तर १</small></th>
                                                                <th style="width:28%;background:#ecfdf5;color:#047857;">🏔 पर्वत (Parvat)<br><small class="text-muted fw-normal">मध्यम / स्तर २</small></th>
                                                                <th style="width:28%;background:#fef3c7;color:#b45309;">🌌 आकाश (Akash)<br><small class="text-muted fw-normal">प्रगत / स्तर ३</small></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            foreach ($abilities_map as $ab_key => $ab_label): 
                                                                $cur_val_t2 = $existing[$ab_key . '_level_term2'] ?? '';
                                                                if ($cur_val_t2 === 'pailu' || $cur_val_t2 === 'pravah') $cur_val_t2 = 'nirjhar';
                                                            ?>
                                                            <tr>
                                                                <td class="fw-bold bg-light" style="font-size: 13.5px;"><?= $ab_label ?></td>
                                                                <?php foreach (['nirjhar' => ['#e0f2fe', '#0369a1', '💧 निर्झर'], 'parvat' => ['#ecfdf5', '#047857', '🏔 पर्वत'], 'akash' => ['#fef3c7', '#b45309', '🌌 आकाश']] as $lvl_key => $lvl_meta): 
                                                                    $is_sel_t2 = ($cur_val_t2 === $lvl_key);
                                                                    $desc_t2 = $fs_descriptors[$ab_key][$lvl_key] ?? '';
                                                                ?>
                                                                <td class="rubric-cell <?= $is_sel_t2 ? 'selected-level-' . $lvl_key : '' ?>" style="vertical-align: top; cursor: pointer;" onclick="selectRubricRadio(this, 'domain_<?= $domain_id ?>_<?= $ab_key ?>_term2', '<?= $lvl_key ?>')">
                                                                    <div class="form-check m-0 mb-1">
                                                                        <input class="form-check-input rubric-radio" type="radio" name="domain_<?= $domain_id ?>_<?= $ab_key ?>_term2" id="r_t2_<?= $domain_id ?>_<?= $ab_key ?>_<?= $lvl_key ?>" value="<?= $lvl_key ?>" <?= $is_sel_t2 ? 'checked' : '' ?>>
                                                                        <label class="form-check-label fw-bold small text-dark" for="r_t2_<?= $domain_id ?>_<?= $ab_key ?>_<?= $lvl_key ?>">
                                                                            <?= $lvl_meta[2] ?>
                                                                        </label>
                                                                    </div>
                                                                    <div class="small text-muted" style="font-size: 11px; line-height: 1.4;">
                                                                        <?= $desc_t2 ?>
                                                                    </div>
                                                                </td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Teacher Feedback Term 2 -->
                                            <div class="mb-4 p-3 bg-light rounded border">
                                                <h6 class="text-success fw-bold mb-2">
                                                    <i class="bi bi-chat-left-dots-fill"></i> 👩‍🏫 शिक्षकांचा अभिप्राय – द्वितीय सत्र
                                                </h6>
                                                
                                                <div class="mb-2">
                                                    <label class="form-label text-muted small fw-bold">
                                                        📋 निवडलेल्या क्षमतेनुसार अधिकृत अभिप्राय निवडा (cce_abhipray2):
                                                    </label>
                                                    <select class="form-select form-select-sm dynamic-abhipray-select" id="abhipray_select_<?= $domain_id ?>_2" data-target="domain_<?= $domain_id ?>_teacher_feedback_term2">
                                                        <option value="">-- अभिप्राय निवडा --</option>
                                                        <?php 
                                                        $init_comp_t2 = !empty($existing_comps_t2) ? $existing_comps_t2[0] : array_key_first($domain['competencies']);
                                                        if (isset($all_abhipray[$init_comp_t2])):
                                                            foreach ($all_abhipray[$init_comp_t2] as $idx => $abh): ?>
                                                                <option value="<?= htmlspecialchars($abh, ENT_QUOTES) ?>">📌 <?= $idx + 1 ?>. <?= mb_substr($abh, 0, 95) ?>...</option>
                                                            <?php endforeach;
                                                        endif; ?>
                                                    </select>
                                                </div>
                                                
                                                <textarea class="form-control" name="domain_<?= $domain_id ?>_teacher_feedback_term2" id="domain_<?= $domain_id ?>_teacher_feedback_term2" rows="3" placeholder="सत्र २ शिक्षक अभिप्राय लिहा..."><?= sanitize($existing['teacher_feedback_mr_term2'] ?? '') ?></textarea>
                                            </div>

                                            <!-- Self Assessment & Peer Assessment (Term 2) -->
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="card h-100 border">
                                                        <div class="card-header bg-light py-2">
                                                            <strong class="small text-success"><i class="bi bi-emoji-smile"></i> स्वयंमूल्यांकन - सत्र २</strong>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                                <?php foreach ($self_emoji_options as $elabel => $eicon): 
                                                                    $is_e2 = (($existing['self_emoji_term2'] ?? '') === $elabel);
                                                                ?>
                                                                <label class="emoji-radio-label <?= $is_e2 ? 'selected' : '' ?>">
                                                                    <input type="radio" name="domain_<?= $domain_id ?>_self_emoji_term2" value="<?= htmlspecialchars($elabel, ENT_QUOTES) ?>" class="d-none emoji-radio" <?= $is_e2 ? 'checked' : '' ?>>
                                                                    <div style="font-size:24px;"><?= $eicon ?></div>
                                                                    <div style="font-size:11px;margin-top:2px;" class="fw-semibold"><?= $elabel ?></div>
                                                                </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card h-100 border">
                                                        <div class="card-header bg-light py-2">
                                                            <strong class="small text-success"><i class="bi bi-people"></i> सहाध्यायी मूल्यांकन - सत्र २</strong>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                                <?php foreach ($peer_emoji_options as $elabel => $eicon): 
                                                                    $is_pe2 = (($existing['peer_emoji_term2'] ?? '') === $elabel);
                                                                ?>
                                                                <label class="emoji-radio-label <?= $is_pe2 ? 'selected' : '' ?>">
                                                                    <input type="radio" name="domain_<?= $domain_id ?>_peer_emoji_term2" value="<?= htmlspecialchars($elabel, ENT_QUOTES) ?>" class="d-none emoji-radio" <?= $is_pe2 ? 'checked' : '' ?>>
                                                                    <div style="font-size:24px;"><?= $eicon ?></div>
                                                                    <div style="font-size:11px;margin-top:2px;" class="fw-semibold"><?= $elabel ?></div>
                                                                </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Parent Observation (Term 2) -->
                                            <div class="mb-3">
                                                <label class="form-label text-muted small fw-bold"><i class="bi bi-house-heart"></i> पालक/काळजीवाहक निरीक्षण - सत्र २:</label>
                                                <select class="form-select form-select-sm" name="domain_<?= $domain_id ?>_parent_observation_term2">
                                                    <option value="">-- पालक निरीक्षण निवडा --</option>
                                                    <?php if (isset($demo_parent_observation_options[$domain_id])): ?>
                                                        <?php foreach ($demo_parent_observation_options[$domain_id] as $opt): ?>
                                                            <option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>" <?= (($existing['parent_observation_mr_term2'] ?? '') === $opt) ? 'selected' : '' ?>>
                                                                <?= (($existing['parent_observation_mr_term2'] ?? '') === $opt) ? '✅ ' : '' ?><?= htmlspecialchars($opt) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== PART SUMMARY: ANNUAL SUMMARY (PAN 16) ==================== -->
        <div class="tab-pane fade" id="partSummary">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-journal-check text-primary"></i> भाग C - शैक्षणिक वर्षाचा सारांश (पान १६)</h5>
                    <small class="text-muted">६ विकास क्षेत्रांनुसार प्रमुख कामगिरी वर्णन विधाने (बलस्थाने / सुधारणात्मक बाबी) व अंतिम वार्षिक अभिप्राय</small>
                </div>
                <div class="card-body p-4">
                    
                    <!-- 3 Abilities Overall Rating -->
                    <div class="p-3 bg-light rounded border mb-4">
                        <h6 class="text-primary fw-bold mb-3"><i class="bi bi-bar-chart-steps"></i> वार्षिक सर्वंकष कामगिरी स्तर (Overall Abilities Level):</h6>
                        <div class="row g-3">
                            <?php foreach (['awareness' => 'जाणीवजागृती', 'sensitivity' => 'संवेदनशीलता', 'creativity' => 'सर्जनशीलता'] as $ab_k => $ab_l): 
                                $s_val = $hpc_card['summary_' . $ab_k] ?? '';
                            ?>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark small"><?= $ab_l ?>:</label>
                                <div class="d-flex gap-2">
                                    <?php foreach (['nirjhar' => '💧 निर्झर', 'parvat' => '🏔 पर्वत', 'akash' => '🌌 आकाश'] as $lk => $ll): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="summary_<?= $ab_k ?>" id="sum_<?= $ab_k ?>_<?= $lk ?>" value="<?= $lk ?>" <?= ($s_val === $lk) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="sum_<?= $ab_k ?>_<?= $lk ?>"><?= $ll ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Domain 1 to 6 Performance Summary Statements -->
                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-list-check"></i> प्रत्येक विकास क्षेत्राचा कामगिरी सारांश:</h6>
                    <div class="row g-3 mb-4">
                        <?php 
                        $domain_cg_map = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '5.1'];
                        foreach ($domains as $did => $dom): 
                            $cg_key = $domain_cg_map[$did] ?? (string)$did;
                            $saved_sum = $hpc_card['summary_domain_' . $did] ?? '';
                            $strengths = $all_kamgiri[$cg_key]['strength'] ?? [];
                            $improvements = $all_kamgiri[$cg_key]['improvement'] ?? [];
                        ?>
                        <div class="col-md-6">
                            <div class="card h-100 border">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <strong class="small text-dark"><?= $icons[$did] ?? '📋' ?> क्षेत्र <?= $did ?> : <?= $dom['name_mr'] ?></strong>
                                </div>
                                <div class="card-body p-2">
                                    <!-- Quick Pick from cce_kamgiri -->
                                    <?php if (!empty($strengths) || !empty($improvements)): ?>
                                    <div class="mb-2">
                                        <select class="form-select form-select-sm kamgiri-quick-pick" data-target="summary_domain_<?= $did ?>_ta">
                                            <option value="">-- कामगिरी विधाने निवडा (cce_kamgiri) --</option>
                                            <?php if (!empty($strengths)): ?>
                                            <optgroup label="💪 सामर्थ्य / बलस्थाने">
                                                <?php foreach ($strengths as $st_idx => $st_txt): ?>
                                                    <option value="<?= htmlspecialchars($st_txt, ENT_QUOTES) ?>">💪 <?= $st_idx + 1 ?>. <?= mb_substr($st_txt, 0, 75) ?>...</option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>
                                            <?php if (!empty($improvements)): ?>
                                            <optgroup label="📈 सुधारणात्मक बाबी">
                                                <?php foreach ($improvements as $imp_idx => $imp_txt): ?>
                                                    <option value="<?= htmlspecialchars($imp_txt, ENT_QUOTES) ?>">📈 <?= $imp_idx + 1 ?>. <?= mb_substr($imp_txt, 0, 75) ?>...</option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <textarea class="form-control form-control-sm" name="summary_domain_<?= $did ?>" id="summary_domain_<?= $did ?>_ta" rows="3" placeholder="<?= $dom['name_mr'] ?> चा कामगिरी सारांश लिहा..."><?= sanitize($saved_sum) ?></textarea>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Final Annual Feedback (पान १७) -->
                    <div class="card border border-primary p-3 bg-light">
                        <h6 class="text-primary fw-bold mb-2"><i class="bi bi-chat-heart"></i> शिक्षकांचा अंतिम वार्षिक सर्वांगीण अभिप्राय (पान १७):</h6>
                        <div class="mb-2">
                            <label class="form-label text-muted small fw-bold">📋 नमुना वार्षिक अभिप्राय निवडा:</label>
                            <select class="form-select form-select-sm demo-dropdown" data-target="final_annual_feedback">
                                <option value="">-- नमुना अभिप्राय निवडा --</option>
                                <option value="विद्यार्थ्याने वर्षभरात शारीरिक, भावनिक व बोधात्मक क्षेत्रात उत्तम प्रगती दर्शविली आहे. नियमित उपस्थिती व सहभागामुळे प्रगती वेगाने होत आहे. पुढील वर्गासाठी शुभेच्छा!">📌 नमुना १: सर्वांगीण उत्तम प्रगती व शुभेच्छा...</option>
                                <option value="विद्यार्थी वर्गात अतिशय आनंदाने आणि उत्साहाने शिकतो. भाषा आणि गणित संकल्पना स्पष्ट होत आहेत. संवाद कौशल्य अधिक विकसित करावे. पुढील वाटचालीस शुभेच्छा!">📌 नमुना २: भाषा-गणित प्रगती व संवाद विकास...</option>
                                <option value="चित्रकला, हस्तकला व क्रीडा क्षेत्रात विद्यार्थ्याची आवड विशेष उल्लेखनीय आहे. अभ्यासात एकाग्रता व सराव वाढवल्यास अधिक यश मिळेल. हार्दिक शुभेच्छा!">📌 नमुना ३: कला-क्रीडा विशेष आवड व शुभेच्छा...</option>
                            </select>
                        </div>
                        <textarea class="form-control" name="final_annual_feedback" id="final_annual_feedback" rows="3" placeholder="शिक्षकांचा अंतिम वार्षिक अभिप्राय लिहा..."><?= sanitize($hpc_card['final_annual_feedback'] ?? '') ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <!-- ==================== PART A(1): SCHOOL & TEACHER INFO ==================== -->
        <div class="tab-pane fade" id="partA1">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-building text-primary"></i> भाग A(1) - शाळेची माहिती (School Information)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">शाळेचे नाव</label>
                            <input type="text" class="form-control" value="<?= sanitize($school['name_mr'] ?: $school['name']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">शाळेचा पत्ता</label>
                            <input type="text" class="form-control" value="<?= sanitize($school['address_line1']) ?>, <?= sanitize($school['village']) ?>, <?= sanitize($school['taluka']) ?>, <?= sanitize($school['district']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">UDISE कोड</label>
                            <input type="text" class="form-control" value="<?= sanitize($school['udise_code']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">शिक्षक कोड (Teacher Code)</label>
                            <input type="text" class="form-control" name="teacher_code" value="<?= sanitize($hpc_card['teacher_code'] ?? '') ?>" placeholder="शिक्षक कोड टाका">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">शैक्षणिक वर्ष</label>
                            <input type="text" class="form-control" value="<?= academic_year() ?>" disabled>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Physical Measurements: Height & Weight (उंची व वजन - सत्र १ व सत्र २) -->
            <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #10b981 !important;">
                <div class="card-header bg-success-subtle py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-success fw-bold"><i class="bi bi-heart-pulse-fill me-1"></i> शारीरिक मोजमाप: उंची व वजन (पान २ साठी)</h5>
                    <span class="badge bg-success text-white">सत्र १ व सत्र २ स्वतंत्र</span>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">येथे भरलेली उंची व वजन HPC कार्डच्या पान क्र. २ वर तसेच CCE मध्ये अधिकृतपणे नोंदवली जाईल.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-bold text-primary mb-3">📘 प्रथम सत्र (Term 1)</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">सत्र १ उंची (सें.मी.)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" name="height_sem1" value="<?= (float)($student['height_sem1'] ?? 0) > 0 ? (float)$student['height_sem1'] : '' ?>" placeholder="उदा. 108.5">
                                            <span class="input-group-text bg-white small">सेमी</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">सत्र १ वजन (कि.ग्रॅ.)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" name="weight_sem1" value="<?= (float)($student['weight_sem1'] ?? 0) > 0 ? (float)$student['weight_sem1'] : '' ?>" placeholder="उदा. 17.0">
                                            <span class="input-group-text bg-white small">किग्रॅ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-bold text-success mb-3">📙 द्वितीय सत्र (Term 2)</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">सत्र २ उंची (सें.मी.)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" name="height_sem2" value="<?= (float)($student['height_sem2'] ?? 0) > 0 ? (float)$student['height_sem2'] : '' ?>" placeholder="उदा. 112.0">
                                            <span class="input-group-text bg-white small">सेमी</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">सत्र २ वजन (कि.ग्रॅ.)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" name="weight_sem2" value="<?= (float)($student['weight_sem2'] ?? 0) > 0 ? (float)$student['weight_sem2'] : '' ?>" placeholder="उदा. 18.5">
                                            <span class="input-group-text bg-white small">किग्रॅ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== PART A(2): ATTENDANCE & INTEREST ==================== -->
        <div class="tab-pane fade" id="partA2">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-calendar-check text-primary"></i> भाग A(2) - मासिक उपस्थिती (पान २)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>महिना</th>
                                    <th>जून</th><th>जुलै</th><th>ऑगस्ट</th><th>सप्टें</th><th>ऑक्टो</th><th>नोव्हें</th>
                                    <th>डिसें</th><th>जाने</th><th>फेब्रु</th><th>मार्च</th><th>एप्रिल</th><th>मे</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold">कामाचे दिवस</td>
                                    <?php 
                                    $month_names = [6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec', 1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may'];
                                    foreach ($month_names as $m_num => $m_name): 
                                        $att = $attendance_data[$m_num] ?? [];
                                    ?>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center" name="working_<?= $m_name ?>" value="<?= $att['working_days'] ?? '' ?>" min="0" max="31">
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="fw-bold">उपस्थित दिवस</td>
                                    <?php foreach ($month_names as $m_num => $m_name): 
                                        $att = $attendance_data[$m_num] ?? [];
                                    ?>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center" name="present_<?= $m_name ?>" value="<?= $att['days_present'] ?? '' ?>" min="0" max="31">
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== PART C: CREDITS (NCrF) ==================== -->
        <div class="tab-pane fade" id="partCredits">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-award text-primary"></i> भाग C - क्रेडिट फ्रेमवर्क (NCrF Credit Framework)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>विकास क्षेत्र</th>
                                    <th>क्रेडिट्स (NCrF)</th>
                                    <th>NCF स्तर</th>
                                    <th>क्रेडिट गुण</th>
                                    <th>मिळालेले गुण (सत्र १)</th>
                                    <th>मिळालेले गुण (सत्र २)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $card_credits = [];
                                if ($hpc_card) {
                                    $stmt = $db->prepare("SELECT * FROM hpc_credits WHERE hpc_card_id = ?");
                                    $stmt->execute([$hpc_card['id']]);
                                    foreach ($stmt->fetchAll() as $c) {
                                        $card_credits[$c['domain_name']] = $c;
                                    }
                                }
                                foreach ($domains as $domain_id => $dom):
                                    $cd = $card_credits[$dom['name']] ?? [];
                                ?>
                                <tr>
                                    <td><strong><?= $domain_id ?>. <?= $dom['name_mr'] ?></strong></td>
                                    <td class="text-center">4.5</td>
                                    <td class="text-center">0.2</td>
                                    <td class="text-center">0.90</td>
                                    <td class="text-center">
                                        <input type="number" class="form-control form-control-sm text-center" name="credit_earned_<?= $domain_id ?>" step="0.01" min="0" max="0.90" value="<?= $cd['credit_points_earned'] ?? '' ?>" placeholder="0.00">
                                    </td>
                                    <td class="text-center">
                                        <input type="number" class="form-control form-control-sm text-center" name="credit_earned_term2_<?= $domain_id ?>" step="0.01" min="0" max="0.90" value="<?= $cd['credit_points_earned_term2'] ?? '' ?>" placeholder="0.00">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Sticky Bottom Save Bar -->
    <div class="card border-0 shadow-lg sticky-bottom mb-4" style="background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);">
        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="text-muted small">
                <i class="bi bi-info-circle text-primary"></i> 
                <strong><?= sanitize($student['name_mr'] ?: $student['name']) ?></strong> चे HPC कार्ड भरणे चालू आहे.
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="save_type" value="draft" class="btn btn-warning px-4 fw-bold">
                    <i class="bi bi-save"></i> मसुदा जतन करा (Save Draft)
                </button>
                <button type="submit" name="save_type" value="complete" class="btn btn-success px-4 fw-bold shadow-sm">
                    <i class="bi bi-check-circle-fill"></i> पूर्ण करा आणि जतन करा
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Client Side JSON Data & Scripts -->
<script>
const ALL_ABHIPRAY = <?= json_encode($all_abhipray, JSON_UNESCAPED_UNICODE) ?>;
const ALL_KAMGIRI = <?= json_encode($all_kamgiri, JSON_UNESCAPED_UNICODE) ?>;
const DOMAIN_SAMPLE_QUESTIONS = <?= json_encode($domain_sample_questions, JSON_UNESCAPED_UNICODE) ?>;

// Open specific domain accordion from top navigation
function openDomainAccordion(did) {
    var el = document.getElementById('domain' + did);
    if (!el) return;
    el.classList.add('show');
    var btn = document.querySelector('[data-bs-target="#domain' + did + '"]');
    if (btn) btn.classList.remove('collapsed');
    setTimeout(function() {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 150);
}

// Select Rubric Radio & Highlight Cell
function selectRubricRadio(cell, radioName, levelKey) {
    var radio = cell.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
    }
    // Update visual highlighting on all cells in same row
    var tr = cell.closest('tr');
    tr.querySelectorAll('.rubric-cell').forEach(function(c) {
        c.classList.remove('selected-level-nirjhar', 'selected-level-parvat', 'selected-level-akash');
    });
    cell.classList.add('selected-level-' + levelKey);
    updateAllProgress();
}

// Emoji radio visual feedback
document.querySelectorAll('.emoji-radio-label').forEach(function(label) {
    label.addEventListener('click', function() {
        var parent = this.closest('.d-flex');
        parent.querySelectorAll('.emoji-radio-label').forEach(function(l) {
            l.classList.remove('selected');
        });
        this.classList.add('selected');
        var r = this.querySelector('.emoji-radio');
        if (r) r.checked = true;
        updateAllProgress();
    });
});

// Competency Checkbox → show/hide activity dropdown AND update cce_abhipray2 dropdown!
document.querySelectorAll('.competency-checkbox').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var compCode = this.getAttribute('data-comp-code');
        var domainId = this.getAttribute('data-domain');
        var term = this.getAttribute('data-term') || '1';
        
        // Show/hide per-competency activity & question dropdowns
        var item = this.closest('.competency-item');
        var actDropdown = item ? item.querySelector('.comp-activity-dropdown') : null;
        if (actDropdown) {
            actDropdown.style.display = this.checked ? '' : 'none';
        }
        var qDropdown = item ? item.querySelector('.comp-question-dropdown') : null;
        if (qDropdown) {
            qDropdown.style.display = this.checked ? '' : 'none';
        }

        // When checked, auto-check matching Curricular Goal!
        if (this.checked) {
            var m = compCode.match(/^C-(\d+)\.\d+$/);
            if (m) {
                var gCode = 'CG-' + m[1];
                var goalCb = term === '2' 
                    ? (document.getElementById('goal_t2_' + domainId + '_' + gCode) || document.querySelector('input[name="domain_' + domainId + '_goals_term2[]"][value="' + gCode + '"]'))
                    : (document.getElementById('goal_' + domainId + '_' + gCode) || document.querySelector('input[name="domain_' + domainId + '_goals[]"][value="' + gCode + '"]'));
                if (goalCb && !goalCb.checked) {
                    goalCb.checked = true;
                }
            }
        }

        // When checked, update the Teacher Feedback dropdown with official remarks from cce_abhipray2
        if (this.checked && ALL_ABHIPRAY[compCode]) {
            var abhiSelect = document.getElementById('abhipray_select_' + domainId + '_' + term);
            if (abhiSelect) {
                abhiSelect.innerHTML = '<option value="">-- ' + compCode + ' अधिकृत अभिप्राय निवडा --</option>';
                ALL_ABHIPRAY[compCode].forEach(function(text, idx) {
                    var opt = document.createElement('option');
                    opt.value = text;
                    opt.textContent = '📌 ' + (idx + 1) + '. ' + (text.length > 95 ? text.substring(0, 95) + '...' : text);
                    abhiSelect.appendChild(opt);
                });
                // Flash the dropdown to indicate updated choices
                abhiSelect.style.backgroundColor = '#ecfdf5';
                setTimeout(function(){ abhiSelect.style.backgroundColor = ''; }, 800);
            }
        }
        updateAllProgress();
    });
});

// Quick Competency Dropdown → check competency checkbox, reveal details and auto-map
document.querySelectorAll('.comp-quick-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var compCode = this.value;
        var domainId = this.getAttribute('data-domain');
        var term = this.getAttribute('data-term') || '1';
        if (!compCode) return;
        var cbId = term === '2' ? ('comp_t2_' + domainId + '_' + compCode) : ('comp_' + domainId + '_' + compCode);
        var cb = document.getElementById(cbId);
        if (cb) {
            cb.checked = true;
            cb.dispatchEvent(new Event('change'));
            var item = cb.closest('.competency-item');
            if (item) {
                item.style.backgroundColor = '#f0fdf4';
                setTimeout(function() { item.style.backgroundColor = ''; }, 1200);
                item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });
});

// Dynamic Abhipray Dropdown (cce_abhipray2) → append to teacher feedback
document.querySelectorAll('.dynamic-abhipray-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        if (!this.value) return;
        var targetId = this.getAttribute('data-target');
        var ta = document.getElementById(targetId);
        if (ta) {
            if (ta.value.trim()) {
                ta.value = ta.value.trim() + '\n' + this.value;
            } else {
                ta.value = this.value;
            }
            ta.style.backgroundColor = '#dcfce7';
            setTimeout(function() { ta.style.backgroundColor = ''; }, 1000);
            this.selectedIndex = 0;
        }
    });
});

// Kamgiri Quick Pick (cce_kamgiri) → append to domain summary textarea
document.querySelectorAll('.kamgiri-quick-pick').forEach(function(sel) {
    sel.addEventListener('change', function() {
        if (!this.value) return;
        var targetId = this.getAttribute('data-target');
        var ta = document.getElementById(targetId);
        if (ta) {
            if (ta.value.trim()) {
                ta.value = ta.value.trim() + '\n' + this.value;
            } else {
                ta.value = this.value;
            }
            ta.style.backgroundColor = '#dcfce7';
            setTimeout(function() { ta.style.backgroundColor = ''; }, 1000);
            this.selectedIndex = 0;
        }
    });
});

// Per-competency activity dropdown → auto-append to Activity textarea
document.querySelectorAll('.comp-activity-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        if (!this.value) return;
        var accordionBody = this.closest('.accordion-body');
        if (!accordionBody) return;
        
        var selectName = this.getAttribute('name') || '';
        var isTerm2 = selectName.indexOf('_comp_activity_t2_') !== -1;
        var targetTa = accordionBody.querySelector(isTerm2 ? 'textarea[name$="_activity_term2"]' : 'textarea[name$="_activity"]');
        
        if (targetTa) {
            if (targetTa.value.trim()) {
                targetTa.value = targetTa.value.trim() + '\n' + this.value;
            } else {
                targetTa.value = this.value;
            }
            targetTa.style.backgroundColor = '#d4edda';
            setTimeout(function() { targetTa.style.backgroundColor = ''; }, 1000);
        }
    });
});

// Per-competency question dropdown → auto-append to Questions textarea
document.querySelectorAll('.comp-question-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        if (!this.value) return;
        var targetId = this.getAttribute('data-target');
        var targetTa = document.getElementById(targetId);
        if (targetTa) {
            if (targetTa.value.trim()) {
                targetTa.value = targetTa.value.trim() + '\n' + this.value;
            } else {
                targetTa.value = this.value;
            }
            targetTa.style.backgroundColor = '#fef3c7';
            setTimeout(function() { targetTa.style.backgroundColor = ''; }, 1000);
            this.selectedIndex = 0;
            updateAllProgress();
        }
    });
});

// Domain question dropdown (card header) → auto-append to Questions textarea
document.querySelectorAll('.domain-question-dropdown').forEach(function(sel) {
    sel.addEventListener('change', function() {
        if (!this.value) return;
        var targetId = this.getAttribute('data-target');
        var targetTa = document.getElementById(targetId);
        if (targetTa) {
            if (targetTa.value.trim()) {
                targetTa.value = targetTa.value.trim() + '\n' + this.value;
            } else {
                targetTa.value = this.value;
            }
            targetTa.style.backgroundColor = '#fef3c7';
            setTimeout(function() { targetTa.style.backgroundColor = ''; }, 1000);
            this.selectedIndex = 0;
            updateAllProgress();
        }
    });
});

// Auto-fill all standard domain questions button
document.querySelectorAll('.btn-fill-domain-questions').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var did = this.getAttribute('data-domain');
        var targetId = this.getAttribute('data-target');
        var targetTa = document.getElementById(targetId);
        if (targetTa && DOMAIN_SAMPLE_QUESTIONS[did]) {
            var qs = DOMAIN_SAMPLE_QUESTIONS[did].map(function(q, idx) {
                return (idx + 1) + ') ' + q;
            }).join('\n');
            targetTa.value = qs;
            targetTa.style.backgroundColor = '#dcfce7';
            setTimeout(function() { targetTa.style.backgroundColor = ''; }, 1000);
            updateAllProgress();
        }
    });
});

// Demo dropdown helper
document.querySelectorAll('.demo-dropdown').forEach(function(dropdown) {
    dropdown.addEventListener('change', function() {
        var targetId = this.getAttribute('data-target');
        var textarea = document.getElementById(targetId);
        if (textarea && this.value) {
            if (textarea.value.trim()) {
                textarea.value = textarea.value.trim() + '\n' + this.value;
            } else {
                textarea.value = this.value;
            }
            textarea.style.backgroundColor = '#d4edda';
            setTimeout(function() { textarea.style.backgroundColor = ''; }, 1000);
            this.selectedIndex = 0;
        }
    });
});

// Progress Indicator
function calcDomainProgress(domainId) {
    var accordion = document.getElementById('domain' + domainId);
    if (!accordion) return 0;
    var filled = 0, total = 0;
    
    // Checkboxes (goals / competencies)
    var cbs = accordion.querySelectorAll('input[type="checkbox"]');
    if (cbs.length > 0) {
        total++;
        var anyC = false;
        cbs.forEach(function(c){ if(c.checked) anyC = true; });
        if (anyC) filled++;
    }
    
    // Textareas
    accordion.querySelectorAll('textarea').forEach(function(ta) {
        total++;
        if (ta.value.trim()) filled++;
    });
    
    // Radio groups
    var radioGroups = {};
    accordion.querySelectorAll('input[type="radio"]').forEach(function(r) {
        if (!radioGroups[r.name]) radioGroups[r.name] = false;
        if (r.checked) radioGroups[r.name] = true;
    });
    Object.keys(radioGroups).forEach(function(g) {
        total++;
        if (radioGroups[g]) filled++;
    });
    
    return total > 0 ? Math.round((filled / total) * 100) : 0;
}

function updateAllProgress() {
    var totalPct = 0;
    for (var d = 1; d <= 6; d++) {
        var pct = calcDomainProgress(d);
        var badge = document.getElementById('domPct' + d);
        var hdrBadge = document.getElementById('domHdrPct' + d);
        var btn = document.querySelector('.domain-progress-btn[data-domain="' + d + '"]');
        if (badge) badge.textContent = pct + '%';
        if (hdrBadge) hdrBadge.textContent = pct + '%';
        
        var cls = pct === 0 ? 'bg-secondary' : pct < 50 ? 'bg-danger' : pct < 80 ? 'bg-warning text-dark' : 'bg-success';
        if (badge) badge.className = 'badge ms-1 domain-pct ' + cls;
        if (hdrBadge) hdrBadge.className = 'badge ms-auto me-2 domain-header-pct ' + cls;
        if (btn) {
            btn.className = 'btn btn-sm domain-progress-btn ' + (pct === 100 ? 'btn-success text-white' : pct > 0 ? 'btn-outline-primary' : 'btn-outline-secondary');
        }
        totalPct += pct;
    }
    var overall = Math.round(totalPct / 6);
    var bar = document.getElementById('overallProgressBar');
    var txt = document.getElementById('overallProgressText');
    if (bar) bar.style.width = overall + '%';
    if (txt) txt.textContent = overall + '%';
}

var hpcForm = document.getElementById('hpcForm');
if (hpcForm) {
    hpcForm.addEventListener('change', function() { setTimeout(updateAllProgress, 100); });
    hpcForm.addEventListener('input', function() { setTimeout(updateAllProgress, 200); });
    setTimeout(updateAllProgress, 300);
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
