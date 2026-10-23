<?php
declare(strict_types=1);

/**
 * Print / Save as PDF: Student Login Credentials Sheet (UserID & Password)
 * जि. प. शाळा नं. २ कुची (UDISE: 27350302306)
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';

$user = requireRole(['teacher', 'admin']);
$scope = getTeacherClassScope($user);
$db = getDB();

$requested_std = isset($_GET['std']) && $_GET['std'] !== '' ? (int)$_GET['std'] : (int)($scope['stds'][0] ?? 6);
$requested_class_id = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : (int)($scope['class_ids'][0] ?? 0);

// Strict teacher scope check
if (!$scope['is_admin']) {
    if (!in_array($requested_std, $scope['stds'], true)) {
        $requested_std = (int)$scope['stds'][0];
    }
    if ($requested_class_id && !in_array($requested_class_id, $scope['class_ids'], true)) {
        $requested_class_id = (int)$scope['class_ids'][0];
    }
}

// Fetch class details
$classInfo = null;
if ($requested_class_id > 0) {
    $cStmt = $db->prepare("SELECT c.*, t.name as teacher_name, t.name_mr as teacher_name_mr FROM classes c LEFT JOIN teachers t ON c.class_teacher_id = t.id WHERE c.id = ? AND c.school_id = ?");
    $cStmt->execute([$requested_class_id, SCHOOL_ID]);
    $classInfo = $cStmt->fetch(PDO::FETCH_ASSOC);
}
if (!$classInfo) {
    $cStmt = $db->prepare("SELECT c.*, t.name as teacher_name, t.name_mr as teacher_name_mr FROM classes c LEFT JOIN teachers t ON c.class_teacher_id = t.id WHERE c.std = ? AND c.school_id = ? LIMIT 1");
    $cStmt->execute([$requested_std, SCHOOL_ID]);
    $classInfo = $cStmt->fetch(PDO::FETCH_ASSOC);
}

$className = $classInfo['name'] ?? ('इयत्ता ' . $requested_std . ' वी');
$teacherName = $classInfo['teacher_name_mr'] ?? ($classInfo['teacher_name'] ?? ($user['name'] ?? 'वर्गशिक्षक'));

// Query all active students in this class
$params = [SCHOOL_ID];
$where = ["s.school_id = ?", "s.is_active = 1"];

if (!empty($classInfo['id'])) {
    $where[] = "s.class_id = ?";
    $params[] = (int)$classInfo['id'];
} else {
    $where[] = "s.std = ?";
    $params[] = $requested_std;
}

$sql = "
    SELECT s.id, s.roll_no, s.name, s.name_mr, s.gender, s.student_pen, s.apaar_id, s.phone,
           u.username, u.mobile as user_mobile
    FROM students s
    LEFT JOIN users u ON u.linked_id = s.id AND u.role = 'student'
    WHERE " . implode(' AND ', $where) . "
    ORDER BY CAST(s.roll_no AS UNSIGNED) ASC, s.name ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$marathiStds = [
    1 => '१ ली', 2 => '२ री', 3 => '३ री', 4 => '४ थी',
    5 => '५ वी', 6 => '६ वी', 7 => '७ वी', 8 => '८ वी'
];
$stdLabel = $marathiStds[$requested_std] ?? ($requested_std . ' वी');
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विद्यार्थी लॉगिन ओळखपत्रक (UserID व Password) - इयत्ता <?= htmlspecialchars($stdLabel) ?> - <?= htmlspecialchars(SCHOOL_NAME_MR) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Outfit:wght@600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Mukta', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            font-size: 13.5px;
        }
        .sheet-container {
            max-width: 960px;
            margin: 20px auto;
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        .header-box {
            border-bottom: 2.5px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .school-title {
            font-size: 21px;
            font-weight: 800;
            color: #1e3a8a;
            margin-bottom: 2px;
        }
        .doc-title {
            font-size: 15px;
            font-weight: 700;
            color: #b91c1c;
            letter-spacing: 0.3px;
        }
        .meta-strip {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 14px;
            margin-bottom: 14px;
            font-size: 13px;
        }
        .cred-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cred-table th {
            background: #1e3a8a !important;
            color: #ffffff !important;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #0f172a;
            padding: 6px 4px;
            font-size: 12.5px;
        }
        .cred-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            vertical-align: middle;
            font-size: 12.5px;
        }
        .cred-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .uid-code {
            font-family: 'Outfit', monospace;
            font-weight: 700;
            color: #0369a1;
            background: #e0f2fe;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .pass-code {
            font-family: 'Outfit', monospace;
            font-weight: 700;
            color: #15803d;
            background: #dcfce7;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .footer-notice {
            border: 1px dashed #f59e0b;
            background: #fffbeb;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 11.5px;
            margin-top: 14px;
            color: #92400e;
        }
        .sign-block {
            margin-top: 30px;
            padding-top: 10px;
        }

        @media print {
            body {
                background: white;
                color: black;
                font-size: 11.5px;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .sheet-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
            }
            .header-box {
                border-bottom: 2px solid black;
            }
            .school-title {
                color: black;
            }
            .doc-title {
                color: black;
            }
            .cred-table th {
                background: #e2e8f0 !important;
                color: black !important;
                border: 1px solid black;
            }
            .cred-table td {
                border: 1px solid black;
            }
            .uid-code, .pass-code {
                background: none !important;
                color: black !important;
                padding: 0;
            }
            .footer-notice {
                border: 1px solid black;
                background: white;
                color: black;
            }
            @page {
                size: A4 portrait;
                margin: 8mm 10mm 10mm 10mm;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="sheet-container">

    <!-- Top Action Bar (hidden on print) -->
    <div class="no-print d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded-3 border">
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> विद्यार्थी लॉगिन ओळखपत्रक (PDF / Print)
            </span>
            <span class="text-muted ms-2">एकूण विद्यार्थी: <strong><?= count($students) ?></strong></span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-danger fw-bold shadow-sm px-3">
                <i class="bi bi-printer-fill me-1"></i> PDF म्हणून सेव्ह करा / प्रिंट करा
            </button>
            <a href="../../index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> डॅशबोर्ड
            </a>
        </div>
    </div>

    <!-- Official School Header -->
    <div class="header-box text-center">
        <div class="school-title">
            <?= htmlspecialchars(SCHOOL_NAME_MR) ?>
        </div>
        <div class="text-muted small fw-semibold">
            ता. <?= htmlspecialchars(TALUKA) ?>, जि. <?= htmlspecialchars(DISTRICT) ?> | UDISE कोड: <strong><?= htmlspecialchars(UDISE_CODE) ?></strong>
        </div>
        <div class="doc-title mt-1">
            <i class="bi bi-shield-lock-fill me-1"></i> विद्यार्थी पोर्टल व 'AI अभ्यास मित्र' — यूजरनेम (UserID) व पासवर्ड पत्रक
        </div>
    </div>

    <!-- Metadata Strip -->
    <div class="meta-strip d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><strong>इयत्ता:</strong> <?= htmlspecialchars($className) ?> (<?= htmlspecialchars($stdLabel) ?>)</div>
        <div><strong>तुकडी:</strong> <?= htmlspecialchars($classInfo['section'] ?? 'A') ?></div>
        <div><strong>वर्गशिक्षक:</strong> <?= htmlspecialchars($teacherName) ?></div>
        <div><strong>शैक्षणिक वर्ष:</strong> <?= htmlspecialchars(ACADEMIC_YEAR) ?></div>
        <div><strong>दिनांक:</strong> <?= date('d-m-Y') ?></div>
    </div>

    <!-- Credentials Table -->
    <table class="cred-table">
        <thead>
            <tr>
                <th style="width: 4%;">अ.क्र.</th>
                <th style="width: 6%;">ह.क्र.</th>
                <th style="width: 25%; text-align: left; padding-left: 8px;">विद्यार्थ्याचे नाव (मराठी)</th>
                <th style="width: 20%; text-align: left; padding-left: 8px;">नाव (English)</th>
                <th style="width: 14%;">लॉगिन UserID</th>
                <th style="width: 10%;">पासवर्ड</th>
                <th style="width: 11%;">PEN नंबर</th>
                <th style="width: 10%;">पालकांची स्वाक्षरी</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">या वर्गात कोणतेही विद्यार्थी उपलब्ध नाहीत.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($students as $idx => $s): 
                    $uid = $s['username'] ?: ($s['student_pen'] ?: ('kuchi' . $s['id']));
                    $defaultPass = '123456';
                    $rollNo = $s['roll_no'] ?: ($idx + 1);
                ?>
                <tr>
                    <td class="text-center fw-bold"><?= $idx + 1 ?></td>
                    <td class="text-center fw-bold text-primary"><?= htmlspecialchars((string)$rollNo) ?></td>
                    <td class="fw-bold" style="padding-left: 8px;">
                        <?= htmlspecialchars($s['name_mr'] ?: $s['name']) ?>
                    </td>
                    <td class="text-muted" style="padding-left: 8px;">
                        <?= htmlspecialchars($s['name']) ?>
                    </td>
                    <td class="text-center">
                        <span class="uid-code"><?= htmlspecialchars($uid) ?></span>
                    </td>
                    <td class="text-center">
                        <span class="pass-code"><?= htmlspecialchars($defaultPass) ?></span>
                    </td>
                    <td class="text-center small text-muted">
                        <?= htmlspecialchars($s['student_pen'] ?: '-') ?>
                    </td>
                    <td></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Notice Box -->
    <div class="footer-notice">
        <strong>📌 पालक व विद्यार्थ्यांसाठी महत्त्वाची सूचना:</strong>
        <ol class="mb-0 ps-3 mt-1">
            <li>हा UserID व पासवर्ड वापरून विद्यार्थी <strong>http://52.66.73.149/zpschool2kuchi/</strong> या पोर्टलवर व <strong>Android ॲप</strong> मध्ये लॉगिन करू शकतात.</li>
            <li>लॉगिन केल्यानंतर विद्यार्थी दररोजचा अभ्यास, ऑनलाइन प्रश्नमंजुषा (Quiz), वर्ग वेळापत्रक आणि <strong>'AI अभ्यास मित्र'</strong> सह २४ तास अभ्यास करू शकतात.</li>
            <li>पासवर्ड विसरल्यास वर्गशिक्षकांशी संपर्क साधून पासवर्ड पुन्हा '123456' वर रिसेट करून घेता येईल.</li>
        </ol>
    </div>

    <!-- Signature Block -->
    <div class="sign-block d-flex justify-content-between align-items-end px-4">
        <div class="text-center">
            <div style="min-width: 180px; border-top: 1.5px solid #64748b; padding-top: 5px;">
                <strong>वर्गशिक्षकांची स्वाक्षरी</strong><br>
                <small class="text-muted"><?= htmlspecialchars($teacherName) ?></small>
            </div>
        </div>
        <div class="text-center">
            <div style="min-width: 180px; border-top: 1.5px solid #64748b; padding-top: 5px;">
                <strong>मुख्याध्यापकांची स्वाक्षरी व शिक्का</strong><br>
                <small class="text-muted">जि. प. शाळा नं. २ कुची</small>
            </div>
        </div>
    </div>

</div>

</body>
</html>
