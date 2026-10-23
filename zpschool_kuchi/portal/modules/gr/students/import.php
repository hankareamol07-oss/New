<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/gr_student.php';

$user = require_role(['admin','principal','clerk']);
$sid = (int) $user['school_id'];
$pdo = db();
$headers = ['book_no','gr_no','admission_date','admission_class','stream','admission_division','medium','board','prev_school_name','prev_school_class','aadhar_no','first_name','father_name','last_name','mother_name','gender','nationality','mother_tongue','religion','caste','caste_category','father_mobile','mother_mobile','dob','birth_place','birth_taluka','birth_district','birth_state','birth_country','permanent_address','village','student_pen','saral_id','class_id','roll_no'];
if (($_GET['action'] ?? '') === 'template') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="gr-students-template.csv"');
    echo "\xEF\xBB\xBF" . implode(',', $headers) . "\n";
    exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null) && isset($_FILES['csv'])) {
    $handle = fopen((string) $_FILES['csv']['tmp_name'], 'rb');
    $rows = 0;
    $skipped_limit = 0;
    $quota = getSchoolStudentQuota($sid);
    $current_count = $quota['count'];
    $max_allowed = $quota['max'];
    $is_unlimited = $quota['is_unlimited'];

    if ($handle) {
        fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 12 || trim((string) ($row[11] ?? '')) === '') continue;
            $source = [];
            foreach ($headers as $index => $header) $source[$header] = trim((string) ($row[$index] ?? ''));
            $payload = student_payload($source, (int) ($source['class_id'] ?? 0));
            if (!$payload['class_id']) continue;

            if (!$is_unlimited && ($current_count + $rows) >= $max_allowed) {
                $skipped_limit++;
                continue;
            }

            $columns = ['school_id','class_id','book_no','gr_no','roll_no','first_name','father_name','surname','mother_name','dob','gender','caste','religion','aadhaar','address','phone','admission_date','previous_school','status','last_name','aadhar_no','admission_class','stream','admission_division','medium','board','prev_school_name','prev_school_class','nationality','mother_tongue','caste_category','father_mobile','mother_mobile','dob_words','birth_place','birth_taluka','birth_district','birth_state','birth_country','permanent_address','village','student_pen','saral_id'];
            $values = [$sid];
            foreach (array_slice($columns, 1) as $column) $values[] = $column === 'status' ? 'active' : ($column === 'surname' ? $payload['last_name'] : ($column === 'aadhaar' ? $payload['aadhar_no'] : ($column === 'address' ? $payload['permanent_address'] : ($column === 'phone' ? $payload['father_mobile'] : ($column === 'previous_school' ? $payload['prev_school_name'] : $payload[$column] ?? null)))));
            try {
                $pdo->prepare('INSERT INTO students(' . implode(',', $columns) . ') VALUES(' . implode(',', array_fill(0, count($columns), '?')) . ')')->execute($values);
                $rows++;
            } catch (Throwable) {
            }
        }
        fclose($handle);
    }
    if ($rows > 0) {
        flash_set('success', $rows . ' विद्यार्थी आयात केले.');
    }
    if ($skipped_limit > 0) {
        flash_set('error', 'प्लॅनच्या विद्यार्थी मर्यादेमुळे (' . $max_allowed . ') ' . $skipped_limit . ' विद्यार्थी वगळण्यात आले. कृपया प्लॅन अपग्रेड करा.');
    }
    header('Location: /modules/gr/students/import.php');
    exit;
}
layout_start('विद्यार्थी आयात', 'gr');
?>
<div class="max-w-4xl mx-auto bg-white border rounded-2xl p-7"><h2 class="text-2xl font-bold text-navy">जनरल रजिस्टर — विद्यार्थी आयात</h2><p class="text-slate-600 mt-2">CSV template मधील column क्रम जसा आहे तसाच ठेवा.</p><div class="flex gap-3 mt-6"><a href="?action=template" class="bg-teal text-white rounded-xl px-5 py-3">Template डाउनलोड करा</a><a href="/modules/gr/students/dakhal_import.php" class="bg-white border rounded-xl px-5 py-3">दाखल आयात</a></div><form method="post" enctype="multipart/form-data" class="border-t mt-7 pt-6"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input required type="file" name="csv" accept=".csv,.txt"><button class="ml-3 bg-gold text-navy rounded-xl px-5 py-3 font-bold">आयात करा</button></form><h3 class="font-bold text-navy mt-7">Template columns</h3><p class="text-xs text-slate-500 mt-2 break-all"><?= e(implode(', ', $headers)) ?></p></div>
<?php layout_end(); ?>
