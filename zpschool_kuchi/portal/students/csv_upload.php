<?php
$page_title = 'CSV बल्क अपलोड';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$school_id = $_SESSION['school_id'];
$db = getDB();

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle sample CSV download
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_sample.csv"');
    // BOM for Excel UTF-8
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'name_mr', 'name', 'roll_no', 'grade', 'section', 'gender',
        'date_of_birth', 'age', 'phone', 'address_line1',
        'mother_name', 'father_name', 'mother_tongue', 'area_type',
        'blood_group', 'aadhar_no', 'apaar_id'
    ]);
    // Sample row
    fputcsv($out, [
        'राम शिंदे', 'Ram Shinde', '1', 'इयत्ता १', 'अ', 'मुलगा',
        '2019-06-15', '6', '9876543210', 'सांगली',
        'सीता शिंदे', 'महेश शिंदे', 'मराठी', 'ग्रामीण',
        'B+', '123456789012', ''
    ]);
    fputcsv($out, [
        'प्रिया पाटील', 'Priya Patil', '2', 'इयत्ता १', 'अ', 'मुलगी',
        '2019-03-20', '6', '9876543211', 'मिरज',
        'अनिता पाटील', 'सुरेश पाटील', 'मराठी', 'शहरी',
        'A+', '234567890123', ''
    ]);
    fclose($out);
    exit;
}

$results = null;
$errors = [];
$success_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid CSRF token');
        redirect(APP_URL . '/students/csv_upload.php');
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'CSV फाइल अपलोड करा';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $errors[] = 'केवळ .csv फाइल स्वीकारली जाते';
        } else {
            $handle = fopen($file, 'r');
            if (!$handle) {
                $errors[] = 'फाइल वाचता आली नाही';
            } else {
                // Read header row
                $header = fgetcsv($handle);
                if (!$header) {
                    $errors[] = 'CSV हेडर रो सापडली नाही';
                } else {
                    // Normalize header
                    $header = array_map(function($h) {
                        return trim(strtolower(preg_replace('/[\x{FEFF}]/u', '', $h)));
                    }, $header);

                    $valid_fields = [
                        'name_mr', 'name', 'roll_no', 'grade', 'section', 'gender',
                        'date_of_birth', 'age', 'phone', 'address_line1', 'address_line2',
                        'mother_name', 'father_name', 'mother_education', 'father_education',
                        'mother_occupation', 'father_occupation', 'guardian_name', 'guardian_relation',
                        'mother_tongue', 'medium_of_instruction', 'area_type', 'blood_group',
                        'aadhar_no', 'apaar_id', 'udid', 'registration_no',
                        'num_siblings', 'siblings_age',
                        'favourite_color', 'favourite_food', 'favourite_flower',
                        'favourite_sport', 'favourite_animal', 'favourite_subject',
                        'aspiration', 'best_friend1', 'best_friend2', 'best_friend3'
                    ];

                    // Check if name_mr or name column exists
                    if (!in_array('name_mr', $header) && !in_array('name', $header)) {
                        $errors[] = 'CSV मध्ये name_mr किंवा name कॉलम असणे आवश्यक आहे';
                    }

                    if (empty($errors)) {
                        $row_num = 1;
                        $results = [];
                        while (($row = fgetcsv($handle)) !== false) {
                            $row_num++;
                            if (count($row) < 2) continue; // Skip empty rows

                            $data = [];
                            foreach ($header as $i => $col) {
                                if (in_array($col, $valid_fields) && isset($row[$i])) {
                                    $data[$col] = trim($row[$i]);
                                }
                            }

                            // Validate
                            $name_mr = $data['name_mr'] ?? '';
                            $name = $data['name'] ?? '';
                            if (empty($name_mr) && empty($name)) {
                                $results[] = ['row' => $row_num, 'status' => 'error', 'msg' => 'नाव रिकामे आहे'];
                                continue;
                            }



                            // Validate date format
                            $dob = null;
                            if (!empty($data['date_of_birth']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date_of_birth'])) {
                                $dob = $data['date_of_birth'];
                            }

                            // Validate gender
                            $gender = $data['gender'] ?? 'मुलगा';
                            if (!in_array($gender, ['मुलगा', 'मुलगी', 'इतर'])) {
                                $gender = 'मुलगा';
                            }

                            // Validate area_type
                            $area = $data['area_type'] ?? 'ग्रामीण';
                            if (!in_array($area, ['ग्रामीण', 'शहरी'])) {
                                $area = 'ग्रामीण';
                            }

                            try {
                                $stmt = $db->prepare("INSERT INTO students (school_id, name, name_mr, roll_no, grade, section, gender, date_of_birth, age, phone, address_line1, address_line2, mother_name, father_name, mother_education, father_education, mother_occupation, father_occupation, guardian_name, guardian_relation, mother_tongue, medium_of_instruction, area_type, blood_group, aadhar_no, apaar_id, udid, registration_no, num_siblings, siblings_age, favourite_color, favourite_food, favourite_flower, favourite_sport, favourite_animal, favourite_subject, aspiration, best_friend1, best_friend2, best_friend3) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                                $stmt->execute([
                                    $school_id,
                                    $name,
                                    $name_mr,
                                    $data['roll_no'] ?? '',
                                    $data['grade'] ?? 'इयत्ता १',
                                    $data['section'] ?? '',
                                    $gender,
                                    $dob,
                                    intval($data['age'] ?? 0) ?: null,
                                    $data['phone'] ?? '',
                                    $data['address_line1'] ?? '',
                                    $data['address_line2'] ?? '',
                                    $data['mother_name'] ?? '',
                                    $data['father_name'] ?? '',
                                    $data['mother_education'] ?? '',
                                    $data['father_education'] ?? '',
                                    $data['mother_occupation'] ?? '',
                                    $data['father_occupation'] ?? '',
                                    $data['guardian_name'] ?? '',
                                    $data['guardian_relation'] ?? '',
                                    $data['mother_tongue'] ?? 'मराठी',
                                    $data['medium_of_instruction'] ?? 'मराठी',
                                    $area,
                                    $data['blood_group'] ?? '',
                                    $data['aadhar_no'] ?? '',
                                    $data['apaar_id'] ?? '',
                                    $data['udid'] ?? '',
                                    $data['registration_no'] ?? '',
                                    intval($data['num_siblings'] ?? 0),
                                    $data['siblings_age'] ?? '',
                                    $data['favourite_color'] ?? '',
                                    $data['favourite_food'] ?? '',
                                    $data['favourite_flower'] ?? '',
                                    $data['favourite_sport'] ?? '',
                                    $data['favourite_animal'] ?? '',
                                    $data['favourite_subject'] ?? '',
                                    $data['aspiration'] ?? '',
                                    $data['best_friend1'] ?? '',
                                    $data['best_friend2'] ?? '',
                                    $data['best_friend3'] ?? ''
                                ]);
                                $success_count++;
                                $results[] = ['row' => $row_num, 'status' => 'success', 'msg' => sanitize($name_mr ?: $name) . ' जोडला'];
                            } catch (Exception $e) {
                                $results[] = ['row' => $row_num, 'status' => 'error', 'msg' => 'डेटाबेस त्रुटी: ' . sanitize($e->getMessage())];
                            }
                        }
                    }
                }
                fclose($handle);
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> CSV बल्क अपलोड</h2>
    <div>
        <a href="<?= APP_URL ?>/students/csv_upload.php?download_sample" class="btn btn-outline-success me-2"><i class="bi bi-download"></i> नमुना CSV डाउनलोड</a>
        <a href="<?= APP_URL ?>/students/list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> मागे</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($results !== null): ?>
    <div class="alert <?= $success_count > 0 ? 'alert-success' : 'alert-warning' ?>">
        <strong><?= $success_count ?> विद्यार्थी यशस्वीरित्या जोडले गेले!</strong>
    </div>
    <div class="card mb-4">
        <div class="card-header">अपलोड निकाल</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>रो</th><th>स्थिती</th><th>तपशील</th></tr></thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                    <tr class="<?= $r['status'] === 'success' ? 'table-success' : 'table-danger' ?>">
                        <td><?= $r['row'] ?></td>
                        <td><?= $r['status'] === 'success' ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                        <td><?= $r['msg'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white"><i class="bi bi-upload"></i> CSV फाइल अपलोड करा</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
                <label class="form-label">CSV फाइल निवडा (.csv)</label>
                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> अपलोड आणि जोडा</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-info-circle"></i> CSV फॉरमॅट सूचना</div>
    <div class="card-body">
        <p>CSV फाइलमध्ये खालील कॉलम असू शकतात (पहिली ओळ हेडर असणे आवश्यक):</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>कॉलम नाव</th><th>वर्णन</th><th>आवश्यक</th><th>उदाहरण</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>name_mr</code></td><td>नाव (मराठी)</td><td class="text-danger">होय*</td><td>राम शिंदे</td></tr>
                    <tr><td><code>name</code></td><td>नाव (इंग्रजी)</td><td>नाही</td><td>Ram Shinde</td></tr>
                    <tr><td><code>roll_no</code></td><td>रोल नंबर</td><td>नाही</td><td>1</td></tr>
                    <tr><td><code>grade</code></td><td>इयत्ता</td><td>नाही</td><td>इयत्ता १</td></tr>
                    <tr><td><code>section</code></td><td>तुकडी</td><td>नाही</td><td>अ</td></tr>
                    <tr><td><code>gender</code></td><td>लिंग</td><td>नाही</td><td>मुलगा / मुलगी / इतर</td></tr>
                    <tr><td><code>date_of_birth</code></td><td>जन्मतारीख</td><td>नाही</td><td>2019-06-15</td></tr>
                    <tr><td><code>age</code></td><td>वय</td><td>नाही</td><td>6</td></tr>
                    <tr><td><code>phone</code></td><td>फोन</td><td>नाही</td><td>9876543210</td></tr>
                    <tr><td><code>mother_name</code></td><td>आईचे नाव</td><td>नाही</td><td>सीता शिंदे</td></tr>
                    <tr><td><code>father_name</code></td><td>वडिलांचे नाव</td><td>नाही</td><td>महेश शिंदे</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted small">* name_mr किंवा name पैकी एक आवश्यक आहे. <a href="<?= APP_URL ?>/students/csv_upload.php?download_sample">नमुना CSV डाउनलोड करा</a> आणि त्यात बदल करा.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
