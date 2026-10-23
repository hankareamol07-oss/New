<?php
/**
 * विजेता पोर्टल — अधिकृत बोनाफाईड प्रमाणपत्र / दाखला (Official Bonafide Certificate)
 * वर्ग व्यवस्थापन अंतर्गत इयत्तानिहाय बोनाफाईड दाखला निर्मिती
 */
$page_title = 'बोनाफाईड प्रमाणपत्र';
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/cce_functions.php';
require_once __DIR__ . '/../../modules/lib/module_helpers.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();

// Helper for Marathi date words
if (!function_exists('bonafide_date_words')) {
    function bonafide_date_words($date_str) {
        if (empty($date_str) || $date_str === '0000-00-00') return '—';
        if (function_exists('marathi_date_words')) {
            return marathi_date_words($date_str);
        }
        return $date_str;
    }
}

$student_id = (int)($_GET['student_id'] ?? $_GET['id'] ?? 0);
$selected_std = (int)($_GET['std'] ?? 0);
$all_class = (int)($_GET['all'] ?? 0);

// Strict Teacher Scope Guard
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    if ($selected_std === 0 || !in_array($selected_std, $tScope['stds'], true)) {
        $selected_std = (int)$tScope['std'];
    }
}
$outward_no = trim($_GET['outward_no'] ?? '');
$issue_date = trim($_GET['issue_date'] ?? date('Y-m-d'));
$conduct = trim($_GET['conduct'] ?? 'उत्तम');
$nationality_override = trim($_GET['nationality'] ?? '');
$format = trim($_GET['format'] ?? 'full');
if ($format !== 'half') {
    $format = 'full';
}

// Purpose / Reason for Bonafide: Dropdown with custom option
$bonafide_reasons = [
    'शैक्षणिक व कार्यालयीन कामासाठी' => 'शैक्षणिक व कार्यालयीन कामासाठी',
    'शिष्यवृत्ती / विद्यावेतन अर्जासाठी' => 'शिष्यवृत्ती / विद्यावेतन अर्जासाठी',
    'बँक खाते उघडण्यासाठी' => 'बँक खाते उघडण्यासाठी',
    'आधार कार्ड नोंदणी / दुरुस्तीसाठी' => 'आधार कार्ड नोंदणी / दुरुस्तीसाठी',
    'एस.टी. बस सवलत पाससाठी' => 'एस.टी. बस सवलत पाससाठी',
    'शासकीय योजनेच्या लाभासाठी' => 'शासकीय योजनेच्या लाभासाठी',
    'जात प्रमाणपत्र / पडताळणीसाठी' => 'जात प्रमाणपत्र / पडताळणीसाठी',
    'रेशन कार्ड / उत्पन्नाचा दाखल्यासाठी' => 'रेशन कार्ड / उत्पन्नाचा दाखल्यासाठी',
    'पासपोर्ट अर्जासाठी' => 'पासपोर्ट अर्जासाठी',
    'क्रीडा / स्पर्धा सहभागासाठी' => 'क्रीडा / स्पर्धा सहभागासाठी',
    'वसतिगृह (हॉस्टेल) प्रवेशासाठी' => 'वसतिगृह (हॉस्टेल) प्रवेशासाठी',
    'इतर' => 'इतर (मॅन्युअली टाईप करा)...'
];

$purpose_select = trim($_GET['purpose_select'] ?? '');
$purpose_custom = trim($_GET['purpose_custom'] ?? '');
$purpose_raw = trim($_GET['purpose'] ?? '');

if (!empty($purpose_custom)) {
    $purpose = $purpose_custom;
    $is_custom_purpose = true;
    $purpose_select = 'इतर';
} elseif ($purpose_select === 'इतर') {
    $purpose = $purpose_custom ?: 'कार्यालयीन कामासाठी';
    $is_custom_purpose = true;
} elseif (!empty($purpose_select) && isset($bonafide_reasons[$purpose_select])) {
    $purpose = $purpose_select;
    $is_custom_purpose = false;
} elseif (!empty($purpose_raw)) {
    $purpose = $purpose_raw;
    if (isset($bonafide_reasons[$purpose]) && $purpose !== 'इतर') {
        $purpose_select = $purpose;
        $is_custom_purpose = false;
    } else {
        $purpose_select = 'इतर';
        $purpose_custom = $purpose;
        $is_custom_purpose = true;
    }
} else {
    $purpose = 'शैक्षणिक व कार्यालयीन कामासाठी';
    $purpose_select = 'शैक्षणिक व कार्यालयीन कामासाठी';
    $is_custom_purpose = false;
}

// Helper to normalize nationality to proper Marathi
if (!function_exists('bonafide_marathi_nationality')) {
    function bonafide_marathi_nationality($raw_nat) {
        $trimmed = trim((string)$raw_nat);
        if ($trimmed === '' || strcasecmp($trimmed, 'indian') === 0 || $trimmed === 'भारतीय' || strcasecmp($trimmed, 'bharat') === 0) {
            return 'भारतीय';
        }
        return $trimmed;
    }
}

/**
 * Robust helper to locate student photo on disk, convert to Base64 Data URI
 * (for 100% reliable print/PDF and zero mixed-content issues), or fallback to safe URL.
 */
if (!function_exists('bonafide_student_photo_src')) {
    function bonafide_student_photo_src($raw_photo) {
        $photo = trim((string)$raw_photo);
        if ($photo === '') {
            return null;
        }

        // 1. If it's already a Data URI, return directly
        if (str_starts_with($photo, 'data:image/')) {
            return $photo;
        }

        // 2. Normalize path separators (convert all backslashes to forward slashes)
        $normalized = str_replace('\\', '/', $photo);
        $clean = ltrim($normalized, '/');
        $basename = basename($clean);

        // 3. Compile candidate roots
        $possible_roots = [];
        if (defined('UPLOAD_DIR')) {
            $possible_roots[] = dirname(rtrim(UPLOAD_DIR, '/\\'));
        }
        $possible_roots[] = realpath(__DIR__ . '/../../');
        $possible_roots[] = dirname(dirname(__DIR__));
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $possible_roots[] = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
        }

        // 4. Candidate file paths on disk
        $candidate_files = [];
        foreach ($possible_roots as $root) {
            if (!$root) continue;
            $r = str_replace('\\', '/', $root);
            $candidate_files[] = $r . '/' . $clean;
            $candidate_files[] = $r . '/uploads/photos/' . $basename;
            $candidate_files[] = $r . '/uploads/' . $basename;
            $candidate_files[] = $r . '/uploads/logos/' . $basename;
            if (!str_starts_with($clean, 'uploads/')) {
                $candidate_files[] = $r . '/uploads/' . $clean;
            }
        }
        // Direct relative paths from __DIR__
        $candidate_files[] = __DIR__ . '/../../' . $clean;
        $candidate_files[] = __DIR__ . '/../../uploads/photos/' . $basename;
        $candidate_files[] = __DIR__ . '/../../uploads/' . $basename;
        if (defined('UPLOAD_DIR')) {
            $candidate_files[] = rtrim(UPLOAD_DIR, '/\\') . '/photos/' . $basename;
            $candidate_files[] = rtrim(UPLOAD_DIR, '/\\') . '/' . $basename;
            $candidate_files[] = rtrim(UPLOAD_DIR, '/\\') . '/' . $clean;
        }

        // 5. Check if any file exists on disk and is readable
        foreach ($candidate_files as $file) {
            if (!empty($file) && @file_exists($file) && @is_file($file) && @is_readable($file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $mime_types = [
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png'  => 'image/png',
                    'gif'  => 'image/gif',
                    'webp' => 'image/webp',
                    'bmp'  => 'image/bmp',
                    'svg'  => 'image/svg+xml'
                ];
                $mime = $mime_types[$ext] ?? 'image/jpeg';
                $data = @file_get_contents($file);
                if ($data !== false && strlen($data) > 0) {
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        }

        // 6. If not found on local disk, try web-accessible URL
        if (preg_match('/^https?:\/\//i', $photo)) {
            return $photo;
        }

        $web_rel = $clean;
        if (!str_starts_with($web_rel, 'uploads/')) {
            $web_rel = 'uploads/photos/' . $basename;
        }

        // Return relative path from /modules/classes/ which is ../../uploads/...
        return '../../' . $web_rel;
    }
}


$students = [];
if ($student_id > 0) {
    $stmt = $db->prepare("SELECT s.*, c.name AS class_name, c.section AS class_section 
                          FROM students s 
                          LEFT JOIN classes c ON (s.class_id = c.id OR (s.std = c.std AND BINARY s.section = BINARY c.section AND c.school_id = s.school_id))
                          WHERE s.id = ? AND s.school_id = ? AND s.is_active = 1");
    $stmt->execute([$student_id, $school_id]);
    $st = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($st) {
        $stStd = (int)($st['std'] ?: (function_exists('std_from_grade') ? std_from_grade($st['grade']) : 0));
        if ($tScope && !empty($tScope['stds']) && !in_array($stStd, $tScope['stds'], true)) {
            $st = null;
        } else {
            $students[] = $st;
            $selected_std = $stStd;
        }
    }
} elseif ($selected_std > 0 && $all_class === 1) {
    $stmt = $db->prepare("SELECT s.*, c.name AS class_name, c.section AS class_section 
                          FROM students s 
                          LEFT JOIN classes c ON (s.class_id = c.id OR (s.std = c.std AND BINARY s.section = BINARY c.section AND c.school_id = s.school_id))
                          WHERE s.school_id = ? AND (s.std = ? OR s.grade LIKE ?) AND s.is_active = 1 
                          ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.name_mr ASC");
    $stmt->execute([$school_id, $selected_std, "%$selected_std%"]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all classes for standard selection if no student
$classes_list = $db->prepare("SELECT std, section FROM classes WHERE school_id = ? ORDER BY std ASC, section ASC");
$classes_list->execute([$school_id]);
$all_classes = $classes_list->fetchAll(PDO::FETCH_ASSOC);

// If no student selected and not bulk printing, show standard/student picker interface
if (empty($students)):
    require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-file-earmark-check-fill text-success"></i> बोनाफाईड प्रमाणपत्र (Bonafide Certificate)
            </h3>
            <p class="text-muted small mb-0">विद्यार्थी निवडा आणि तत्काळ अधिकृत बोनाफाईड प्रमाणपत्र / दाखला प्रिंट करा.</p>
        </div>
        <a href="<?= APP_URL ?>/modules/classes/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> वर्ग व्यवस्थापनाकडे परत
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-filter-circle me-1"></i> इयत्ता निवडा</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">इयत्ता (Standard)</label>
                            <select name="std" class="form-select" onchange="this.form.submit()">
                                <?php if (!$tScope): ?><option value="">-- इयत्ता निवडा --</option><?php endif; ?>
                                <?php 
                                $bona_stds = ($tScope && !empty($tScope['stds'])) ? $tScope['stds'] : range(1, 8);
                                foreach ($bona_stds as $i): ?>
                                    <option value="<?= $i ?>" <?= $selected_std === $i ? 'selected' : '' ?>><?= std_label($i) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if ($selected_std > 0): ?>
                        <?php
                        $st_list = cce_students($school_id, $selected_std);
                        ?>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <span class="fw-bold small text-secondary">विद्यार्थी यादी (<?= count($st_list) ?> मुले)</span>
                                <div class="btn-group btn-group-sm">
                                    <a href="?std=<?= $selected_std ?>&all=1&format=full" target="_blank" class="btn btn-outline-success">
                                        <i class="bi bi-printer"></i> सर्व A4 Fit
                                    </a>
                                    <a href="?std=<?= $selected_std ?>&all=1&format=half" target="_blank" class="btn btn-outline-primary">
                                        <i class="bi bi-file-earmark-ruled"></i> अर्धे A4 (२ दाखले)
                                    </a>
                                </div>
                            </div>

                            <div class="input-group input-group-sm mb-2 shadow-sm">
                                <span class="input-group-text bg-white border"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" placeholder="विद्यार्थी शोधा (नाव किंवा हजेरी क्र.)..." oninput="filterBonafideStudents(this.value)">
                            </div>

                            <div class="list-group list-group-flush border rounded-2 shadow-sm" id="bonafideStudentList" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($st_list)): ?>
                                    <div class="p-3 text-center text-muted small">या इयत्तेत विद्यार्थी उपलब्ध नाहीत.</div>
                                <?php else: ?>
                                    <?php foreach ($st_list as $st): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                            <div>
                                                <div class="fw-bold text-dark"><?= sanitize($st['name_mr'] ?: $st['name']) ?></div>
                                                <div class="small text-muted">हजेरी क्र: <?= sanitize($st['roll_no']) ?> · GR क्र: <?= sanitize($st['gr_no'] ?: $st['registration_no'] ?: '-') ?></div>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?student_id=<?= $st['id'] ?>&format=full" class="btn btn-sm btn-outline-success" title="संपूर्ण A4 पान (Fit Page)">
                                                    A4 Fit
                                                </a>
                                                <a href="?student_id=<?= $st['id'] ?>&format=half" class="btn btn-sm btn-outline-primary" title="अर्धे A4 पान (शाळा + विद्यार्थी प्रत)">
                                                    अर्धे A4
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <script>
                        function filterBonafideStudents(q) {
                            q = (q || '').toLowerCase().trim();
                            document.querySelectorAll('#bonafideStudentList .list-group-item').forEach(function(item) {
                                var txt = item.textContent.toLowerCase();
                                item.style.display = txt.indexOf(q) !== -1 ? '' : 'none';
                            });
                        }
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-success"><i class="bi bi-info-circle me-1"></i> बोनाफाईड प्रमाणपत्राची वैशिष्ट्ये</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div><strong>A4 Fit Page किंवा अर्धे A4 (A4 वर २ दाखले)</strong>, कागद बचतीसह परिपूर्ण लेआउट पर्याय.</div>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div><strong>महाराष्ट्र शासन व शिक्षण विभाग निकषांनुसार</strong> अधिकृत नमुना.</div>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div><strong>जन्मतारीख अंकी व अक्षरी (मराठीत)</strong> अचूक स्वयंचलित रूपांतर.</div>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div><strong>GR क्र., APAAR ID, सरल / PEN व आधार क्रमांक</strong> थेट दाखल्यावर समाविष्ट.</div>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div><strong>पासपोर्ट फोटो चौकट व शाळेच्या शिक्क्यासाठी जागा</strong>, मुख्याध्यापक व वर्गशिक्षक स्वाक्षरी.</div>
                        </li>
                    </ul>

                    <div class="alert alert-info border-0 rounded-3 small">
                        <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                        <strong>टीप:</strong> वर्ग व्यवस्थापनामध्ये जाऊन कोणत्याही विद्यार्थ्याच्या ओळीवरील <strong>'बोनाफाईड'</strong> बटणावर क्लिक करून तुम्ही थेट दाखला प्रिंट करू शकता.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
endif;

// Default outward number if not provided
if (empty($outward_no)) {
    $outward_no = 'जा.क्र. / ' . sanitize($year) . ' / ' . str_pad((string)($students[0]['gr_no'] ?? $students[0]['roll_no'] ?? rand(101, 999)), 3, '0', STR_PAD_LEFT);
}

$issue_date_formatted = date('d/m/Y', strtotime($issue_date));
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>बोनाफाईड प्रमाणपत्र — <?= sanitize($students[0]['name_mr'] ?: $students[0]['name'] ?? 'विद्यार्थी') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiro+Devanagari+Marathi:ital@0;1&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Mukta:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tiro+Devanagari+Marathi:ital@0;1&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Mukta:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700;800;900&display=swap');

        /* High-Priority Local Font-Face Declarations */
        @font-face {
            font-family: 'Tiro Devanagari Marathi';
            src: url('../../assets/fonts/TiroDevanagariMarathi-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Tiro Devanagari Marathi';
            src: url('../../assets/fonts/TiroDevanagariMarathi-Italic.ttf') format('truetype');
            font-weight: 400;
            font-style: italic;
            font-display: swap;
        }
        @font-face {
            font-family: 'Mukta';
            src: url('../../assets/fonts/Poppins-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Mukta';
            src: url('../../assets/fonts/Poppins-SemiBold.ttf') format('truetype');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Mukta';
            src: url('../../assets/fonts/Poppins-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --primary-navy: #0f2b48;
            --border-maroon: #800000;
            --gold-accent: #b8860b;
            --cce-purple: #7C3AED;
            --cce-font: 'Tiro Devanagari Marathi', 'Mukta', 'Mukta', 'Mukta', serif;
            --school-font: 'Tiro Devanagari Marathi', serif;
            --en-font: 'Mukta', sans-serif;
        }

        body {
            background-color: #f1f5f9;
            font-family: var(--cce-font);
            color: #111;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-print-bar {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            border-bottom: 3px solid #7C3AED;
            padding: 10px 18px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            color: #ffffff;
        }

        /* Base A4 Page container */
        .bonafide-page {
            width: 210mm;
            background: #ffffff;
            box-sizing: border-box;
            position: relative;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            page-break-after: always;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .bonafide-page:last-child {
            page-break-after: avoid;
        }

        /* 1. Full Page Mode (A4 Fit Page) */
        .bonafide-page.format-full {
            max-height: 282mm;
            margin: 15px auto;
            padding: 4mm 8mm;
        }

        .bonafide-page.format-full .cert-outer-frame {
            border: 3.5px double #800000;
            padding: 4px;
            height: 100%;
            min-height: 270mm;
            max-height: 270mm;
            position: relative;
            box-sizing: border-box;
        }

        .bonafide-page.format-full .cert-inner-frame {
            border: 1.5px solid #0f2b48;
            padding: 10px 18px;
            height: 100%;
            min-height: 260mm;
            max-height: 260mm;
            position: relative;
            box-sizing: border-box;
            background: radial-gradient(circle at center, rgba(255,255,255,1) 0%, rgba(254,252,245,0.85) 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* 2. Half Page Mode (2 certificates on A4) */
        .bonafide-page.format-half {
            max-height: 282mm;
            margin: 15px auto;
            padding: 3mm 7mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cert-half-item {
            height: 133mm;
            max-height: 133mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        .cert-half-item .cert-outer-frame {
            border: 2px double #800000;
            padding: 2.5px;
            height: 100%;
            max-height: 100%;
            position: relative;
            box-sizing: border-box;
        }

        .cert-half-item .cert-inner-frame {
            border: 1px solid #0f2b48;
            padding: 4px 10px;
            height: 100%;
            max-height: 100%;
            position: relative;
            box-sizing: border-box;
            background: radial-gradient(circle at center, rgba(255,255,255,1) 0%, rgba(254,252,245,0.85) 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Cut Divider Line between half certificates */
        .cert-cut-divider {
            height: 5mm;
            margin: 1mm 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-top: 1.5px dashed #94a3b8;
            text-align: center;
        }

        .cert-cut-divider .cut-badge {
            background: #ffffff;
            padding: 0 10px;
            position: relative;
            top: -1px;
            font-size: 0.68rem;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 1.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Watermark */
        .cert-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            font-size: 64pt;
            font-weight: 800;
            color: #000;
            pointer-events: none;
            text-align: center;
            line-height: 1.2;
            width: 90%;
            z-index: 1;
            text-transform: uppercase;
        }

        /* Header elements (Full Page) */
        .cert-header {
            text-align: center;
            border-bottom: 2px solid #800000;
            padding-bottom: 8px;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }

        .sanstha-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .school-name-mr {
            font-size: 1.65rem;
            font-weight: 800;
            color: #800000;
            margin-bottom: 2px;
            line-height: 1.25;
            letter-spacing: 0.5px;
        }

        .school-name-en {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f2b48;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .school-subdetails {
            font-size: 0.82rem;
            color: #333;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .school-meta-tags {
            font-size: 0.78rem;
            font-weight: 600;
            color: #1a202c;
            display: flex;
            justify-content: center;
            gap: 14px;
            margin-top: 2px;
            flex-wrap: wrap;
        }

        /* Title Banner */
        .cert-title-wrap {
            text-align: center;
            margin: 8px 0 10px 0;
            position: relative;
            z-index: 2;
        }

        .cert-title-badge {
            display: inline-block;
            background: #800000;
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 800;
            padding: 4px 30px;
            border-radius: 30px;
            letter-spacing: 1px;
            box-shadow: 0 2px 6px rgba(128, 0, 0, 0.25);
            border: 2px solid #b8860b;
        }

        .cert-title-sub {
            display: block;
            font-size: 0.76rem;
            font-weight: 700;
            color: #0f2b48;
            margin-top: 3px;
            letter-spacing: 1.5px;
        }

        .copy-tag {
            font-size: 0.62rem;
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 700;
            display: inline-block;
            vertical-align: middle;
            margin-left: 6px;
            letter-spacing: normal;
        }

        /* Top Meta Row (Outward & Date & Photo Box) */
        .cert-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }

        .meta-left {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2d3748;
            line-height: 1.7;
        }

        .photo-box {
            width: 30mm;
            height: 35mm;
            border: 1.5px dashed #4a5568;
            background: #fafafa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 0.68rem;
            color: #718096;
            padding: 2px;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
            box-sizing: border-box;
        }

        .photo-box.has-photo {
            border: 1.5px solid #334155;
            background: #ffffff;
            padding: 1px;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 2px;
            display: block;
        }

        /* Certification Content Text */
        .cert-content {
            font-size: 0.98rem;
            line-height: 1.85;
            color: #1a202c;
            text-align: justify;
            position: relative;
            z-index: 2;
            margin-bottom: 8px;
        }

        .cert-content .fill-blank {
            font-weight: 700;
            color: #0f2b48;
            border-bottom: 1.5px dotted #718096;
            padding: 0 4px;
            display: inline-block;
        }

        .cert-content .highlight-val {
            font-weight: 800;
            color: #800000;
            font-size: 1.05rem;
        }

        /* Details Grid Table */
        .cert-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 8px 0;
            font-size: 0.88rem;
            position: relative;
            z-index: 2;
        }

        .cert-table td {
            padding: 4px 8px;
            border: 1px solid #cbd5e0;
            vertical-align: middle;
        }

        .cert-table td.label-col {
            font-weight: 700;
            color: #2d3748;
            background-color: #f8fafc;
            width: 25%;
        }

        .cert-table td.val-col {
            font-weight: 600;
            color: #0f2b48;
            width: 25%;
        }

        .cert-table td.val-col-wide {
            font-weight: 700;
            color: #0f2b48;
            width: 75%;
        }

        .cert-footnote {
            font-size: 0.88rem;
            line-height: 1.6;
            margin-top: 6px;
        }

        /* Signatures Section */
        .cert-signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 14px;
            padding-top: 6px;
            position: relative;
            z-index: 2;
        }

        .sig-block {
            text-align: center;
            width: 30%;
        }

        .sig-line {
            height: 32px;
            margin-bottom: 2px;
        }

        .sig-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f2b48;
            border-top: 1.5px solid #4a5568;
            padding-top: 4px;
        }

        .seal-block {
            text-align: center;
            border: 1.5px dashed #a0aec0;
            border-radius: 50%;
            height: 70px;
            width: 70px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            color: #718096;
            font-weight: 600;
        }

        /* Half Page Specific Overrides */
        .cert-half-item .cert-header {
            border-bottom: 1.5px solid #800000;
            padding-bottom: 1px;
            margin-bottom: 1px;
            text-align: center;
        }
        .cert-half-item .sanstha-name {
            font-size: 0.62rem;
            margin-bottom: 0px;
            line-height: 1.0;
        }
        .cert-half-item .school-name-mr {
            font-size: 1.02rem;
            font-weight: 800;
            margin-bottom: 0px;
            line-height: 1.15;
            font-family: var(--school-font);
        }
        .cert-half-item .school-name-en {
            font-size: 0.65rem;
            font-weight: 700;
            margin-bottom: 0px;
            line-height: 1.0;
            font-family: var(--en-font);
        }
        .cert-half-item .school-subdetails {
            font-size: 0.60rem;
            margin-bottom: 0px;
            line-height: 1.0;
        }
        .cert-half-item .school-meta-tags {
            font-size: 0.58rem;
            gap: 6px;
            margin-top: 1px;
        }

        .cert-half-item .cert-title-wrap {
            margin: 1px 0 2px 0;
            text-align: center;
        }
        .cert-half-item .cert-title-badge {
            font-size: 0.74rem;
            font-weight: 800;
            padding: 1.5px 12px;
            letter-spacing: 0.5px;
            font-family: var(--school-font);
        }
        .cert-half-item .cert-title-sub {
            font-size: 0.52rem;
            letter-spacing: 1px;
            margin-top: 0px;
            font-family: var(--en-font);
        }

        .cert-half-item .cert-meta-row {
            margin-bottom: 1px;
            align-items: center;
        }
        .cert-half-item .meta-left {
            font-size: 0.68rem;
            line-height: 1.25;
        }
        .cert-half-item .photo-box {
            width: 17mm;
            height: 20mm;
            font-size: 0.45rem;
            padding: 1px;
        }

        .cert-half-item .cert-content {
            font-size: 0.70rem;
            line-height: 1.35;
            margin-bottom: 2px;
        }
        .cert-half-item .cert-content .highlight-val {
            font-size: 0.72rem;
        }

        .cert-half-item .cert-table {
            margin: 1px 0 2px 0;
            font-size: 0.65rem;
        }
        .cert-half-item .cert-table td {
            padding: 1.2px 4px;
            line-height: 1.22;
        }

        .cert-half-item .cert-footnote {
            font-size: 0.62rem;
            line-height: 1.22;
            margin-top: 1px;
        }

        .cert-half-item .cert-signatures {
            margin-top: 2px;
            padding-top: 1px;
        }
        .cert-half-item .sig-line {
            height: 10px;
            margin-bottom: 0px;
        }
        .cert-half-item .sig-title {
            font-size: 0.62rem;
            padding-top: 1px;
        }
        .cert-half-item .seal-block {
            height: 30px;
            width: 30px;
            font-size: 0.42rem;
            line-height: 1.1;
        }
        .cert-half-item .cert-watermark {
            font-size: 32pt;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 4mm 6mm;
            }
            html, body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                font-family: var(--cce-font) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .school-name-mr, .cert-title-badge {
                font-family: var(--school-font) !important;
            }
            .no-print-bar, .d-print-none, .no-print {
                display: none !important;
            }
            .bonafide-page {
                box-shadow: none !important;
                margin: 0 auto !important;
                width: 100% !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .bonafide-page.format-full {
                height: 278mm !important;
                max-height: 278mm !important;
                padding: 2mm 5mm !important;
                page-break-after: always !important;
                break-after: page !important;
                overflow: hidden !important;
            }
            /* Single Half Page: Fits on exactly 1 page, zero overflow */
            .bonafide-page.format-half.is-single {
                height: auto !important;
                max-height: 136mm !important;
                padding: 1.5mm 5mm !important;
                page-break-after: avoid !important;
                break-after: avoid !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .bonafide-page.format-half.is-single .cert-cut-divider {
                display: none !important;
            }
            /* Bulk Half Page: 2 students per A4 sheet */
            .bonafide-page.format-half.is-pair {
                height: 278mm !important;
                max-height: 278mm !important;
                padding: 1.5mm 5mm !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .bonafide-page:last-child {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .cert-half-item {
                height: 133mm !important;
                max-height: 133mm !important;
                overflow: hidden !important;
            }
            .cert-cut-divider {
                display: flex !important;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar (Hidden on Print) -->
<div class="no-print-bar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= APP_URL ?>/modules/classes/index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left"></i> मागे
        </a>
        <span class="fw-bold text-white fs-6 d-flex align-items-center gap-1">
            <i class="bi bi-file-earmark-check-fill text-warning"></i> बोनाफाईड प्रमाणपत्र
        </span>
        <span class="badge text-white px-2 py-1 border border-light" style="background:#7C3AED; border-radius:20px;">
            <?= count($students) ?> विद्यार्थी
        </span>
    </div>

    <form method="GET" id="bonafideFilterForm" class="d-flex flex-wrap align-items-center gap-2 m-0">
        <?php if ($student_id > 0): ?>
            <input type="hidden" name="student_id" value="<?= $student_id ?>">
        <?php elseif ($selected_std > 0): ?>
            <input type="hidden" name="std" value="<?= $selected_std ?>">
            <input type="hidden" name="all" value="1">
        <?php endif; ?>

        <!-- मांडणी निवड (Layout Selector) -->
        <div class="input-group input-group-sm" style="width: 220px;">
            <span class="input-group-text bg-secondary text-white border-0 small py-1"><i class="bi bi-file-earmark-ruled me-1"></i> मांडणी:</span>
            <select name="format" id="ctrlFormat" class="form-select form-select-sm text-dark fw-bold bg-white" onchange="this.form.submit()">
                <option value="full" <?= $format === 'full' ? 'selected' : '' ?>>📄 संपूर्ण A4 पान (A4 Fit)</option>
                <option value="half" <?= $format === 'half' ? 'selected' : '' ?>>📑 अर्धे A4 पान (Single Half)</option>
            </select>
        </div>

        <!-- जावक क्र -->
        <div class="input-group input-group-sm" style="width: 170px;">
            <span class="input-group-text bg-secondary text-white border-0 small py-1">जावक:</span>
            <input type="text" name="outward_no" id="ctrlOutward" class="form-control form-control-sm text-dark fw-bold bg-white" value="<?= sanitize($outward_no) ?>">
        </div>

        <!-- दिनांक -->
        <div class="input-group input-group-sm" style="width: 155px;">
            <span class="input-group-text bg-secondary text-white border-0 small py-1">दिनांक:</span>
            <input type="date" name="issue_date" id="ctrlDate" class="form-control form-control-sm text-dark bg-white" value="<?= sanitize($issue_date) ?>">
        </div>

        <!-- राष्ट्रीयत्व -->
        <div class="input-group input-group-sm" style="width: 145px;">
            <span class="input-group-text bg-secondary text-white border-0 small py-1">राष्ट्रीयत्व:</span>
            <select name="nationality" id="ctrlNat" class="form-select form-select-sm text-dark fw-bold bg-white">
                <option value="भारतीय" <?= ($nationality_override === '' || $nationality_override === 'भारतीय') ? 'selected' : '' ?>>भारतीय</option>
                <option value="इतर" <?= ($nationality_override !== '' && $nationality_override !== 'भारतीय') ? 'selected' : '' ?>>इतर</option>
            </select>
        </div>

        <!-- बोनाफाईडचे कारण Dropdown -->
        <div class="input-group input-group-sm" style="width: 250px;">
            <span class="input-group-text bg-secondary text-white border-0 small py-1"><i class="bi bi-tag-fill text-warning me-1"></i> कारण:</span>
            <select name="purpose_select" id="ctrlPurposeSelect" class="form-select form-select-sm text-dark fw-bold bg-white">
                <?php foreach ($bonafide_reasons as $opt_val => $opt_label): ?>
                    <option value="<?= sanitize($opt_val) ?>" <?= ($purpose_select === $opt_val) ? 'selected' : '' ?>>
                        <?= sanitize($opt_label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- इतर / मॅन्युअली कारण टाईप करा -->
        <div class="input-group input-group-sm <?= $is_custom_purpose ? '' : 'd-none' ?>" id="customPurposeWrap" style="width: 220px;">
            <span class="input-group-text bg-warning text-dark fw-bold border-0 small py-1">मॅन्युअल:</span>
            <input type="text" name="purpose_custom" id="ctrlPurposeCustom" class="form-control form-control-sm text-dark fw-semibold bg-white" placeholder="इच्छित कारण टाईप करा..." value="<?= sanitize($is_custom_purpose ? $purpose : '') ?>">
        </div>

        <button type="submit" class="btn btn-outline-light btn-sm fw-bold" title="माहिती अपडेट करा">
            <i class="bi bi-arrow-repeat"></i>
        </button>

        <button type="button" onclick="window.print()" class="btn btn-sm text-white fw-bold px-3 shadow-sm rounded-pill" style="background:#10B981; border-color:#10B981;">
            <i class="bi bi-printer-fill me-1"></i> प्रिंट / PDF
        </button>
    </form>
</div>

<?php
/**
 * Render single bonafide certificate markup
 */
if (!function_exists('render_bonafide_single_cert')) {
    function render_bonafide_single_cert($stu, $school, $year, $outward_no, $issue_date_formatted, $nat_display, $purpose, $conduct, $is_half = false) {
        $s_name_mr = trim((string)($stu['name_mr'] ?: $stu['name']));
        $s_name_en = trim((string)$stu['name']);
        $s_gender = trim((string)($stu['gender'] ?? 'मुलगा'));
        $is_girl = ($s_gender === 'मुलगी' || $s_gender === 'F');

        $prefix = $is_girl ? 'कुमारी' : 'कुमार';
        $pronoun = $is_girl ? 'ही' : 'हा';
        $verb_sikhat = 'शिकत आहे';
        $verb_dakhal = $is_girl ? 'दाखल झाली' : 'दाखल झाला';

        $std_num = (int)($stu['std'] ?: std_from_grade($stu['grade']));
        $std_display = std_label($std_num);
        $sec_display = trim((string)($stu['section'] ?: $stu['class_section'] ?: 'अ'));

        $dob_raw = $stu['date_of_birth'] ?: $stu['dob'] ?: '';
        $dob_formatted = (!empty($dob_raw) && $dob_raw !== '0000-00-00') ? date('d/m/Y', strtotime($dob_raw)) : '—';
        $dob_words = trim((string)($stu['dob_words'] ?? ''));
        if (empty($dob_words) && !empty($dob_raw)) {
            $dob_words = bonafide_date_words($dob_raw);
        }

        $adm_date_raw = $stu['admission_date'] ?? '';
        $adm_date_formatted = (!empty($adm_date_raw) && $adm_date_raw !== '0000-00-00') ? date('d/m/Y', strtotime($adm_date_raw)) : '—';

        $birth_place_full = implode(', ', array_filter([
            $stu['birth_place'] ?? $stu['village'] ?? '',
            $stu['birth_taluka'] ?? '',
            $stu['birth_district'] ?? '',
            $stu['birth_state'] ?? 'महाराष्ट्र'
        ])) ?: '—';

        $caste_full = implode(' - ', array_filter([
            $stu['religion'] ?? '',
            $stu['caste'] ?? '',
            $stu['caste_category'] ? '(' . $stu['caste_category'] . ')' : ''
        ])) ?: '—';
    ?>
        <div class="cert-outer-frame">
            <div class="cert-inner-frame">
                <!-- Background Watermark -->
                <div class="cert-watermark">BONAFIDE<br>CERTIFICATE</div>

                <!-- School Header -->
                <div class="cert-header">
                    <?php if (!empty($school['sanstha'])): ?>
                        <div class="sanstha-name"><?= sanitize($school['sanstha']) ?></div>
                    <?php endif; ?>

                    <div class="school-name-mr"><?= sanitize($school['name_mr'] ?: $school['name']) ?></div>
                    
                    <?php if (!empty($school['name']) && $school['name'] !== $school['name_mr']): ?>
                        <div class="school-name-en"><?= sanitize($school['name']) ?></div>
                    <?php endif; ?>

                    <div class="school-subdetails">
                        <?= sanitize(implode(', ', array_filter([
                            $school['address_line1'] ?? '',
                            $school['village'] ?? '',
                            'ता. ' . ($school['taluka'] ?? ''),
                            'जि. ' . ($school['district'] ?? ''),
                            !empty($school['pin_code']) ? 'पिन: ' . $school['pin_code'] : ''
                        ]))) ?>
                    </div>

                    <div class="school-meta-tags">
                        <span><strong>UDISE क्र:</strong> <?= sanitize($school['udise_code'] ?: '—') ?></span>
                        <?php if (!empty($school['kendra'])): ?>
                            <span>• <strong>केंद्र:</strong> <?= sanitize($school['kendra']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($school['phone'])): ?>
                            <span>• <strong>मो.:</strong> <?= sanitize($school['phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($school['last_recognition_no'])): ?>
                            <span>• <strong>मान्यता क्र:</strong> <?= sanitize($school['last_recognition_no']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Certificate Title -->
                <div class="cert-title-wrap">
                    <div class="cert-title-badge">बोनाफाईड प्रमाणपत्र</div>
                    <span class="cert-title-sub">BONAFIDE CERTIFICATE</span>
                </div>

            <!-- Meta Row: Outward No, Date & Photo Box -->
            <div class="cert-meta-row">
                <div class="meta-left">
                    <div><strong>जावक क्रमांक:</strong> <span class="fill-blank cert-outward-text"><?= sanitize($outward_no) ?></span></div>
                    <div><strong>दिनांक:</strong> <span class="fill-blank cert-date-text"><?= sanitize($issue_date_formatted) ?></span></div>
                </div>

                <?php 
                $photo_src = bonafide_student_photo_src($stu['photo'] ?? ''); 
                $has_photo = !empty($photo_src);
                ?>
                <div class="photo-box <?= $has_photo ? 'has-photo' : '' ?>">
                    <?php if ($has_photo): ?>
                        <img src="<?= htmlspecialchars($photo_src, ENT_QUOTES, 'UTF-8') ?>" alt="विद्यार्थी फोटो" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                        <div class="photo-placeholder" style="display: none; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%;">
                            <i class="bi bi-person-bounding-box <?= $is_half ? 'fs-5' : 'fs-3' ?> mb-1 text-muted"></i>
                            <span>विद्यार्थी फोटो व शिक्का</span>
                        </div>
                    <?php else: ?>
                        <i class="bi bi-person-bounding-box <?= $is_half ? 'fs-5' : 'fs-3' ?> mb-1 text-muted"></i>
                        <span>विद्यार्थी फोटो व शिक्का</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Official Certification Body Text -->
            <div class="cert-content">
                प्रमाणित करण्यात येते की, <?= $prefix ?> <span class="highlight-val"><?= sanitize($s_name_mr) ?></span>
                <?php if (!empty($s_name_en) && $s_name_en !== $s_name_mr): ?>
                    (<span class="fw-bold"><?= sanitize($s_name_en) ?></span>)
                <?php endif; ?>
                <?php if (!empty($stu['mother_name'])): ?>
                    , आईचे नाव: <span class="fill-blank"><?= sanitize($stu['mother_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($stu['father_name'])): ?>
                    , वडिलांचे नाव: <span class="fill-blank"><?= sanitize($stu['father_name']) ?></span>
                <?php endif; ?>
                <?= $pronoun ?> या शाळेत चालू शैक्षणिक वर्ष <strong><?= sanitize($year) ?></strong> मध्ये 
                <strong>इयत्ता <?= sanitize($std_display) ?></strong> तुकडी <strong>'<?= sanitize($sec_display) ?>'</strong>, 
                हजेरी क्रमांक <strong><?= sanitize($stu['roll_no'] ?: '—') ?></strong> मध्ये नियमित <?= $verb_sikhat ?>.
            </div>

            <!-- Detailed Tabular Profile -->
            <table class="cert-table">
                <tr>
                    <td class="label-col">जनरल रजिस्टर (GR) क्र.</td>
                    <td class="val-col"><span class="highlight-val"><?= sanitize($stu['gr_no'] ?: $stu['registration_no'] ?: '—') ?></span></td>
                    <td class="label-col">हजेरी क्रमांक (Roll No.)</td>
                    <td class="val-col"><?= sanitize($stu['roll_no'] ?: '—') ?></td>
                </tr>
                <tr>
                    <td class="label-col">सरल / PEN क्रमांक</td>
                    <td class="val-col"><?= sanitize($stu['student_pen'] ?: $stu['saral_id'] ?: '—') ?></td>
                    <td class="label-col">APAAR ID / आधार क्र.</td>
                    <td class="val-col"><?= sanitize($stu['apaar_id'] ?: $stu['aadhar_no'] ?: '—') ?></td>
                </tr>
                <tr>
                    <td class="label-col">जन्मतारीख (अंकी)</td>
                    <td class="val-col"><strong><?= sanitize($dob_formatted) ?></strong></td>
                    <td class="label-col">शाळेत दाखल दिनांक</td>
                    <td class="val-col"><?= sanitize($adm_date_formatted) ?></td>
                </tr>
                <tr>
                    <td class="label-col">जन्मतारीख (अक्षरी)</td>
                    <td colspan="3" class="val-col-wide" style="color:#800000; font-weight:700;">
                        <?= sanitize($dob_words ?: '—') ?>
                    </td>
                </tr>
                <tr>
                    <td class="label-col">जन्मस्थळ</td>
                    <td colspan="3" class="val-col-wide"><?= sanitize($birth_place_full) ?></td>
                </tr>
                <tr>
                    <td class="label-col">धर्म व जात / प्रवर्ग</td>
                    <td class="val-col"><?= sanitize($caste_full) ?></td>
                    <td class="label-col">राष्ट्रीयत्व</td>
                    <td class="val-col"><strong class="cert-nat-text" style="color:#0f2b48; font-weight:800;"><?= sanitize($nat_display) ?></strong></td>
                </tr>
                <tr>
                    <td class="label-col">मातृभाषा</td>
                    <td class="val-col"><?= sanitize($stu['mother_tongue'] ?: 'मराठी') ?></td>
                    <td class="label-col">माध्यम</td>
                    <td class="val-col"><?= sanitize($stu['medium_of_instruction'] ?: 'मराठी') ?></td>
                </tr>
                <tr>
                    <td class="label-col">शाळेतील वर्तणूक</td>
                    <td class="val-col"><span class="badge bg-success-subtle text-success border border-success px-2 py-1"><?= sanitize($conduct) ?></span></td>
                    <td class="label-col">प्रवेश वर्ग</td>
                    <td class="val-col"><?= sanitize($stu['admission_class'] ? std_label((int)$stu['admission_class']) : ('इयत्ता ' . std_label($std_num))) ?></td>
                </tr>
            </table>

            <!-- Footnote / Purpose of Issue -->
            <div class="cert-footnote cert-content mt-1">
                सदरचे प्रमाणपत्र विद्यार्थ्याच्या / पालकांच्या अर्जावरून <strong class="cert-purpose-text" style="color:#800000; text-decoration: underline dotted;"><?= sanitize($purpose) ?></strong> देण्यात येत आहे. 
                शाळेच्या जनरल रजिस्टरमधील मूळ नोंदीवरून वरील माहिती अचूक व सत्य आहे.
            </div>

            <!-- Signatures Section -->
            <div class="cert-signatures">
                <div class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-title">वर्गशिक्षक स्वाक्षरी</div>
                </div>

                <div class="seal-block">
                    शाळेचा<br>गोल शिक्का
                </div>

                <div class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-title">मुख्याध्यापक स्वाक्षरी व शिक्का</div>
                </div>
            </div>
        </div>
    </div>
<?php
}
}
?>

<?php if ($format === 'full'): ?>
    <!-- 📄 1. संपूर्ण A4 पान (A4 Fit Page - 1 दाखला प्रति पान) -->
    <?php foreach ($students as $stu): 
        $s_nat = !empty($nationality_override) ? $nationality_override : bonafide_marathi_nationality($stu['nationality'] ?? '');
    ?>
    <div class="bonafide-page format-full">
        <?php render_bonafide_single_cert($stu, $school, $year, $outward_no, $issue_date_formatted, $s_nat, $purpose, $conduct, false); ?>
    </div>
    <?php endforeach; ?>

<?php else: ?>
    <!-- 📑 2. अर्धे A4 पान (Single Half Page - No duplicate) -->
    <?php 
    if (count($students) === 1): 
        $stu = $students[0];
        $s_nat = !empty($nationality_override) ? $nationality_override : bonafide_marathi_nationality($stu['nationality'] ?? '');
    ?>
        <div class="bonafide-page format-half is-single">
            <!-- Single Half Page Bonafide Certificate (No duplicate) -->
            <div class="cert-half-item">
                <?php render_bonafide_single_cert($stu, $school, $year, $outward_no, $issue_date_formatted, $s_nat, $purpose, $conduct, true); ?>
            </div>

            <!-- Scissor Cut Indicator for paper trimming (screen only) -->
            <div class="cert-cut-divider no-print d-print-none" style="margin-top: 6mm;">
                <span class="cut-badge"><i class="bi bi-scissors"></i> अर्ध्या पानाचा दाखला (Single Half Page Bonafide)</span>
            </div>
        </div>

    <?php 
    else: 
        // Bulk class printing: 2 students per A4 sheet, each student gets single certificate (NO duplicate)
        $chunks = array_chunk($students, 2);
        foreach ($chunks as $chunk):
            $stu1 = $chunk[0];
            $stu2 = $chunk[1] ?? null;
            $nat1 = !empty($nationality_override) ? $nationality_override : bonafide_marathi_nationality($stu1['nationality'] ?? '');
    ?>
        <div class="bonafide-page format-half is-pair">
            <!-- Student 1 -->
            <div class="cert-half-item">
                <?php render_bonafide_single_cert($stu1, $school, $year, $outward_no, $issue_date_formatted, $nat1, $purpose, $conduct, true); ?>
            </div>

            <!-- Scissor Cut Divider -->
            <div class="cert-cut-divider">
                <span class="cut-badge"><i class="bi bi-scissors"></i> येथून कापा (CUT HERE) <i class="bi bi-scissors"></i></span>
            </div>

            <!-- Student 2 (or blank if odd count - NO duplicate) -->
            <?php if ($stu2): 
                $nat2 = !empty($nationality_override) ? $nationality_override : bonafide_marathi_nationality($stu2['nationality'] ?? '');
            ?>
                <div class="cert-half-item">
                    <?php render_bonafide_single_cert($stu2, $school, $year, $outward_no, $issue_date_formatted, $nat2, $purpose, $conduct, true); ?>
                </div>
            <?php else: ?>
                <div class="cert-half-empty" style="height:133mm;"></div>
            <?php endif; ?>
        </div>
    <?php 
        endforeach;
    endif; 
    ?>
<?php endif; ?>

<script>
(function() {
    var ctrlPurposeSelect = document.getElementById('ctrlPurposeSelect');
    var customPurposeWrap = document.getElementById('customPurposeWrap');
    var ctrlPurposeCustom = document.getElementById('ctrlPurposeCustom');
    var ctrlOutward = document.getElementById('ctrlOutward');
    var ctrlDate = document.getElementById('ctrlDate');
    var ctrlNat = document.getElementById('ctrlNat');

    function updatePurposeText() {
        var selectedVal = ctrlPurposeSelect ? ctrlPurposeSelect.value : '';
        var finalPurpose = '';
        if (selectedVal === 'इतर') {
            if (customPurposeWrap) customPurposeWrap.classList.remove('d-none');
            finalPurpose = ctrlPurposeCustom ? ctrlPurposeCustom.value.trim() : '';
            if (!finalPurpose) finalPurpose = 'कार्यालयीन कामासाठी';
        } else {
            if (customPurposeWrap) customPurposeWrap.classList.add('d-none');
            finalPurpose = selectedVal;
        }
        document.querySelectorAll('.cert-purpose-text').forEach(function(el) {
            el.textContent = finalPurpose;
        });
    }

    if (ctrlPurposeSelect) {
        ctrlPurposeSelect.addEventListener('change', function() {
            if (this.value === 'इतर' && ctrlPurposeCustom) {
                ctrlPurposeCustom.focus();
            }
            updatePurposeText();
        });
    }

    if (ctrlPurposeCustom) {
        ctrlPurposeCustom.addEventListener('input', updatePurposeText);
    }

    if (ctrlOutward) {
        ctrlOutward.addEventListener('input', function() {
            var val = this.value;
            document.querySelectorAll('.cert-outward-text').forEach(function(el) {
                el.textContent = val;
            });
        });
    }

    if (ctrlDate) {
        ctrlDate.addEventListener('change', function() {
            var val = this.value;
            if (val) {
                var parts = val.split('-');
                if (parts.length === 3) {
                    var formatted = parts[2] + '/' + parts[1] + '/' + parts[0];
                    document.querySelectorAll('.cert-date-text').forEach(function(el) {
                        el.textContent = formatted;
                    });
                }
            }
        });
    }

    if (ctrlNat) {
        ctrlNat.addEventListener('change', function() {
            var val = this.value;
            if (val === 'इतर') {
                var customNat = prompt('राष्ट्रीयत्व प्रविष्ट करा (Enter Nationality):', 'भारतीय');
                if (customNat) {
                    document.querySelectorAll('.cert-nat-text').forEach(function(el) {
                        el.textContent = customNat;
                    });
                }
            } else {
                document.querySelectorAll('.cert-nat-text').forEach(function(el) {
                    el.textContent = 'भारतीय';
                });
            }
        });
    }
})();
</script>

</body>
</html>
