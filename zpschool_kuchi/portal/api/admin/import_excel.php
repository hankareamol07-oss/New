<?php
/**
 * POST /api/admin/import_excel.php
 * SARAL / UDISE+ Excel / CSV Student Importer
 */

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth_guard.php';
require_once __DIR__ . '/../../includes/udise_helper.php';

$user = requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('केवळ POST विनंती स्वीकारली जाते.', 405);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    apiError('कृपया Excel (.xlsx) किंवा CSV फाईल निवडा.', 400);
}

$file = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($ext, ['csv', 'xlsx'])) {
    apiError('केवळ CSV किंवा XLSX फाईल अपलोड करता येईल.', 400);
}

$imported = 0;
$errors = [];

// For CSV processing directly in PHP
if ($ext === 'csv') {
    $handle = fopen($file, 'r');
    if ($handle !== false) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0]) && empty($row[1])) continue;
            $name = trim($row[0] ?? $row[1] ?? '');
            $std = (int)($row[2] ?? 1);
            $sec = trim($row[3] ?? 'A') ?: 'A';
            $roll = trim($row[4] ?? '1');
            $gender = trim($row[5] ?? 'मुलगा');
            $pen = trim($row[6] ?? '');

            if (empty($name)) continue;

            $name_mr = function_exists('udise_transliterate_name') ? udise_transliterate_name($name) : $name;

            try {
                // Find class
                $st = $db->prepare("SELECT id, class_teacher_id FROM classes WHERE std = ? AND school_id = ? LIMIT 1");
                $st->execute([$std, SCHOOL_ID]);
                $cls = $st->fetch();
                $cid = $cls ? (int)$cls['id'] : 1;
                $tid = $cls ? (int)$cls['class_teacher_id'] : null;

                $ins = $db->prepare("
                    INSERT INTO students (school_id, class_id, teacher_id, name, name_mr, roll_no, grade, std, section, gender, student_pen, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE name = VALUES(name), name_mr = VALUES(name_mr), class_id = VALUES(class_id)
                ");
                $grade_name = "इयत्ता {$std} ली";
                $ins->execute([SCHOOL_ID, $cid, $tid, $name, $name_mr, $roll, $grade_name, $std, $sec, $gender, $pen]);
                $imported++;
            } catch (Throwable $e) {
                $errors[] = "$name: " . $e->getMessage();
            }
        }
        fclose($handle);
    }
} else {
    // For XLSX, call our python helper to parse and return JSON
    $script = dirname(__DIR__) . '/../../database/export_students_json.py';
    $tmpJson = dirname(__DIR__) . '/../../database/tmp_import.json';
    $pyCmd = "python -c \"import openpyxl, json; wb=openpyxl.load_workbook(r'{$file}', data_only=True); st=wb.active; rows=[]; " .
             "roman_to_std={'I':1,'II':2,'III':3,'IV':4,'V':5,'VI':6,'VII':7,'VIII':8}; " .
             "for r in st.iter_rows(min_row=4, values_only=True): " .
             "    if r[2]: rows.append({'std': roman_to_std.get(str(r[0]).strip(), 1), 'name': str(r[2]).strip(), 'gender': 'मुलगी' if 'female' in str(r[3]).lower() else 'मुलगा', 'pen': str(r[5]).strip() if r[5] else ''}); " .
             "with open(r'{$tmpJson}', 'w', encoding='utf-8') as f: json.dump(rows, f)\"";
    @shell_exec($pyCmd);

    if (file_exists($tmpJson)) {
        $rows = json_decode(file_get_contents($tmpJson), true);
        @unlink($tmpJson);
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $name = $r['name'];
                $std = (int)$r['std'];
                $gender = $r['gender'];
                $pen = $r['pen'];
                $name_mr = function_exists('udise_transliterate_name') ? udise_transliterate_name($name) : $name;

                try {
                    $st = $db->prepare("SELECT id, class_teacher_id FROM classes WHERE std = ? AND school_id = ? LIMIT 1");
                    $st->execute([$std, SCHOOL_ID]);
                    $cls = $st->fetch();
                    $cid = $cls ? (int)$cls['id'] : 1;
                    $tid = $cls ? (int)$cls['class_teacher_id'] : null;

                    $ins = $db->prepare("
                        INSERT INTO students (school_id, class_id, teacher_id, name, name_mr, roll_no, grade, std, section, gender, student_pen, is_active)
                        VALUES (?, ?, ?, ?, ?, '1', ?, ?, 'A', ?, ?, 1)
                        ON DUPLICATE KEY UPDATE name = VALUES(name), name_mr = VALUES(name_mr)
                    ");
                    $grade_name = "इयत्ता {$std} ली";
                    $ins->execute([SCHOOL_ID, $cid, $tid, $name, $name_mr, $grade_name, $std, $gender, $pen]);
                    $imported++;
                } catch (Throwable $e) {
                    $errors[] = "$name: " . $e->getMessage();
                }
            }
        }
    }
}

apiSuccess([
    'imported_count' => $imported,
    'errors_count'   => count($errors),
    'sample_errors'  => array_slice($errors, 0, 5),
], "Excel आयात यशस्वी! $imported विद्यार्थ्यांची नोंद झाली.");
