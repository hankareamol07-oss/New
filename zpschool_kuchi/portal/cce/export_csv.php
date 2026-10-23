<?php
/**
 * Smart School Hub — शाळा डेटा CSV / Excel निर्यात (Export CSV)
 * Microsoft Excel व Google Sheets सुसंगत (UTF-8 BOM सह)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cce_functions.php';
require_once __DIR__ . '/../includes/tenant_db.php';
require_once __DIR__ . '/../includes/module_access.php';
requireLogin();
require_module_access('cce');

$db = getDB();
$school_id = (int) $_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

$export_type = $_GET['export'] ?? '';

// Handle Direct CSV Download
if ($export_type !== '') {
    $std = (int) ($_GET['std'] ?? 0);
    $sem = (int) ($_GET['sem'] ?? 1);
    
    // Clean output buffer to prevent corrupted CSV
    if (ob_get_level()) ob_end_clean();

    // 1. Export Students Master CSV
    if ($export_type === 'students') {
        $filename = 'Students_' . ($school['udise_code'] ?? $school_id) . ($std > 0 ? "_Std_$std" : '_All') . '_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Marathi Excel display

        // Header Row
        fputcsv($out, [
            'अ.क्र.',
            'रोल नं.',
            'विद्यार्थ्याचे नाव (मराठी)',
            'विद्यार्थ्याचे नाव (इंग्रजी)',
            'इयत्ता',
            'तुकडी',
            'लिंग',
            'जन्मतारीख',
            'जनरल रजिस्टर क्र. (GR)',
            'आधार क्र.',
            'पालकांचा फोन',
            'पत्ता'
        ]);

        if ($std > 0) {
            $students = cce_students($school_id, $std);
        } else {
            $sqldb = cce_db($school_id);
            $st = $sqldb->prepare("SELECT * FROM students WHERE school_id = ? AND is_active = 1 ORDER BY grade, CAST(roll_no AS UNSIGNED), name_mr");
            $st->execute([$school_id]);
            $students = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        $i = 1;
        foreach ($students as $s) {
            fputcsv($out, [
                $i++,
                $s['roll_no'] ?: '-',
                $s['name_mr'] ?: $s['name'],
                $s['name'] ?: '-',
                $s['grade'] ?: '-',
                $s['section'] ?: 'अ',
                $s['gender'] ?: '-',
                $s['dob'] ?: '-',
                $s['gr_no'] ?: '-',
                $s['aadhaar'] ?? ($s['aadhar_no'] ?? '-'),
                $s['phone'] ?? ($s['father_mobile'] ?? '-'),
                $s['address'] ?? '-'
            ]);
        }
        fclose($out);
        exit;
    }

    // 2. Export CCE Marks CSV
    elseif ($export_type === 'marks') {
        $target_std = $std > 0 ? $std : 1;
        $filename = 'CCE_Marks_' . ($school['udise_code'] ?? $school_id) . "_Std_{$target_std}_Sem_{$sem}_" . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'अ.क्र.',
            'रोल नं.',
            'विद्यार्थ्याचे नाव',
            'इयत्ता',
            'तुकडी',
            'सत्र',
            'विषय',
            'आकारिक गुण (FE)',
            'संकलित गुण (SE)',
            'एकूण गुण',
            'टक्केवारी (%)',
            'श्रेणी (Grade)'
        ]);

        $students = cce_students($school_id, $target_std);
        $subjects = cce_subjects($school_id, $target_std);

        $sqldb = cce_db($school_id);
        $m_stmt = $sqldb->prepare("SELECT * FROM cce_marks WHERE school_id = ? AND academic_year = ? AND semester = ?");
        $m_stmt->execute([$school_id, $year, $sem]);
        $marks_lookup = [];
        foreach ($m_stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $marks_lookup[$m['student_id'] . '_' . $m['subject_id']] = $m;
        }

        $i = 1;
        foreach ($students as $st) {
            foreach ($subjects as $sub) {
                $m = $marks_lookup[$st['id'] . '_' . $sub['id']] ?? null;
                fputcsv($out, [
                    $i++,
                    $st['roll_no'] ?: '-',
                    $st['name_mr'] ?: $st['name'],
                    std_label($target_std),
                    $st['section'] ?: 'अ',
                    'सत्र ' . $sem,
                    $sub['name_mr'],
                    $m ? ($m['fe_total'] ?? '-') : '-',
                    $m ? ($m['se_total'] ?? '-') : '-',
                    $m ? ($m['grand_total'] ?? '-') : '-',
                    $m ? ($m['percent'] ? number_format($m['percent'], 1) . '%' : '-') : '-',
                    $m ? ($m['grade'] ?? '-') : '-'
                ]);
            }
        }
        fclose($out);
        exit;
    }

    // 3. Export Attendance CSV
    elseif ($export_type === 'attendance') {
        $filename = 'Attendance_' . ($school['udise_code'] ?? $school_id) . '_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'अ.क्र.',
            'रोल नं.',
            'विद्यार्थ्याचे नाव',
            'इयत्ता',
            'तुकडी',
            'महिना',
            'शाळेचे कामाचे दिवस',
            'हजर दिवस',
            'गैरहजर दिवस',
            'उपस्थिती %'
        ]);

        $months = cce_months();
        $sqldb = cce_db($school_id);
        $att_stmt = $sqldb->prepare("SELECT * FROM attendance WHERE school_id = ? AND academic_year = ?");
        $att_stmt->execute([$school_id, $year]);
        $att_lookup = [];
        foreach ($att_stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $att_lookup[$a['student_id'] . '_' . $a['month']] = $a;
        }

        $all_stds = $sqldb->query("SELECT * FROM students WHERE school_id = $school_id AND is_active = 1 ORDER BY grade, CAST(roll_no AS UNSIGNED)")->fetchAll(PDO::FETCH_ASSOC);

        $i = 1;
        foreach ($all_stds as $st) {
            foreach ($months as $m_num => $m_name) {
                $a = $att_lookup[$st['id'] . '_' . $m_num] ?? null;
                $work_days = $a ? (int) $a['working_days'] : 0;
                $pres_days = $a ? (int) $a['present_days'] : 0;
                $abs_days = max(0, $work_days - $pres_days);
                $pct = $work_days > 0 ? round(($pres_days / $work_days) * 100, 1) : 0;

                fputcsv($out, [
                    $i++,
                    $st['roll_no'] ?: '-',
                    $st['name_mr'] ?: $st['name'],
                    $st['grade'] ?: '-',
                    $st['section'] ?: 'अ',
                    $m_name,
                    $work_days,
                    $pres_days,
                    $abs_days,
                    $work_days > 0 ? $pct . '%' : '-'
                ]);
            }
        }
        fclose($out);
        exit;
    }
}

$page_title = 'डेटा निर्यात (CSV / Excel Export)';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-success px-2 py-1"><i class="bi bi-file-earmark-spreadsheet-fill"></i> Excel / CSV एक्सपोर्ट</span>
                <span class="badge bg-light text-muted border">शैक्षणिक वर्ष: <?= sanitize($year) ?></span>
            </div>
            <h2 class="mb-1 text-dark fw-bold">
                <i class="bi bi-download text-success me-2"></i> शाळा डेटा CSV / Excel निर्यात
            </h2>
            <p class="text-muted mb-0 small">
                शाळेतील विद्यार्थ्यांची माहिती, सातत्यपूर्ण मूल्यमापन गुण आणि हजेरीचा अधिकृत डेटा Microsoft Excel व Google Sheets मध्ये १-क्लिकमध्ये डाऊनलोड करा.
            </p>
        </div>
        <div>
            <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> डॅशबोर्डकडे परत
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- 1. Students CSV Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #0d6efd !important;">
                <div class="card-body p-4">
                    <div class="fs-1 text-primary mb-3"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-bold text-dark">१. विद्यार्थी यादी (Students CSV)</h5>
                    <p class="text-muted small mb-4">
                        सर्व विद्यार्थ्यांची माहिती: रोल नंबर, नाव, जात, जन्मतारीख, GR क्रमांक, आधार व पालकांचा संपर्क क्रमांक.
                    </p>
                    
                    <form method="GET" action="">
                        <input type="hidden" name="export" value="students">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">इयत्ता निवडा</label>
                            <select name="std" class="form-select form-select-sm">
                                <option value="0">सर्व इयत्ता (इ. १ ते ८)</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>"><?= std_label($i) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 shadow-sm">
                            <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> विद्यार्थी CSV डाऊनलोड
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. CCE Marks CSV Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #198754 !important;">
                <div class="card-body p-4">
                    <div class="fs-1 text-success mb-3"><i class="bi bi-journal-bookmark-fill"></i></div>
                    <h5 class="fw-bold text-dark">२. सातत्यपूर्ण मूल्यमापन निकाल व गुण (Marks CSV)</h5>
                    <p class="text-muted small mb-4">
                        इयत्तानिहाय सर्व विषयांचे आकारिक (FE), संकलित (SE), एकूण गुण, टक्केवारी आणि अंतिम श्रेणी अहवाल.
                    </p>
                    
                    <form method="GET" action="">
                        <input type="hidden" name="export" value="marks">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">इयत्ता निवडा</label>
                                <select name="std" class="form-select form-select-sm">
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?= $i ?>"><?= std_label($i) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">सत्र निवडा</label>
                                <select name="sem" class="form-select form-select-sm">
                                    <option value="1">सत्र १</option>
                                    <option value="2">सत्र २</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 shadow-sm">
                            <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> सातत्यपूर्ण मूल्यमापन गुण CSV डाऊनलोड
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. Attendance CSV Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid #0dcaf0 !important;">
                <div class="card-body p-4">
                    <div class="fs-1 text-info mb-3"><i class="bi bi-calendar-check-fill"></i></div>
                    <h5 class="fw-bold text-dark">३. मासिक हजेरी (Attendance CSV)</h5>
                    <p class="text-muted small mb-4">
                        सर्व १२ महिन्यांचा उपस्थिती अहवाल: शाळेचे कामाचे दिवस, हजर दिवस, गैरहजर दिवस आणि सरासरी टक्केवारी.
                    </p>
                    
                    <form method="GET" action="">
                        <input type="hidden" name="export" value="attendance">
                        <div class="mb-3">
                            <div class="small text-muted mb-2">शैक्षणिक वर्ष: <strong><?= sanitize($year) ?></strong></div>
                            <div class="small text-muted">सर्व १२ महिन्यांचा एकत्रित हजेरी तक्ता तयार होईल.</div>
                        </div>
                        <button type="submit" class="btn btn-info text-white w-100 shadow-sm mt-3">
                            <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> हजेरी CSV डाऊनलोड
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Excel Compatibility Tips Card -->
    <div class="card border-0 shadow-sm mt-4 bg-light">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-lightbulb-fill text-warning me-2"></i> Excel मध्ये मराठी फॉन्ट माहिती:</h6>
            <p class="text-muted small mb-0">
                डाऊनलोड केलेल्या CSV फाईलमध्ये UTF-8 BOM समाविष्ट असल्यामुळे Microsoft Excel (2016, 2019, 2021, Office 365) किंवा Google Sheets मध्ये उघडल्यास मराठी अक्षरे एकदम स्पष्ट आणि अचूक दिसतील.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
