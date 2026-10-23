<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/gr_student.php';

$user = require_role(['admin','principal','clerk']);
$sid = (int) $user['school_id'];
$pdo = db();
$classes = school_classes($sid);
$student = $_POST;
$student['book_no'] = $student['book_no'] ?? 1;
$student['gr_no'] = $student['gr_no'] ?? next_gr_number($sid, (int) $student['book_no']);
$student['admission_date'] = $student['admission_date'] ?? date('Y-m-d');
$student['birth_state'] = $student['birth_state'] ?? 'महाराष्ट्र';
$student['birth_country'] = $student['birth_country'] ?? 'भारत';
$student['nationality'] = $student['nationality'] ?? 'भारतीय';
$student['mother_tongue'] = $student['mother_tongue'] ?? 'मराठी';
$student['medium'] = $student['medium'] ?? 'मराठी';
$student['caste_category'] = $student['caste_category'] ?? 'open';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'सत्र कालबाह्य. पुन्हा प्रयत्न करा.';
    } else {
        $payload = student_payload($_POST, (int) ($_POST['class_id'] ?? 0));
        if ($payload['first_name'] === '' || $payload['last_name'] === '' || $payload['dob'] === '' || !$payload['class_id']) {
            $errors[] = 'नाव, आडनाव, जन्मतारीख आणि वर्ग आवश्यक आहेत.';
        } elseif (!canAddStudent($sid)) {
            $errors[] = 'तुमच्या खात्याची विद्यार्थी मर्यादा पूर्ण झाली आहे. नवीन विद्यार्थी जोडण्यासाठी कृपया आपला प्लॅन अपग्रेड करा.';
        } else {
            try {
                $columns = ['school_id','class_id','book_no','gr_no','roll_no','first_name','father_name','surname','mother_name','dob','gender','caste','religion','aadhaar','address','phone','admission_date','previous_school','status','last_name','aadhar_no','admission_class','stream','admission_division','medium','board','prev_school_name','prev_school_class','nationality','mother_tongue','caste_category','father_mobile','mother_mobile','dob_words','birth_place','birth_taluka','birth_district','birth_state','birth_country','permanent_address','village','student_pen','saral_id'];
                $values = [$sid];
                foreach (array_slice($columns, 1) as $column) $values[] = $column === 'status' ? 'active' : ($column === 'surname' ? $payload['last_name'] : ($column === 'aadhaar' ? $payload['aadhar_no'] : ($column === 'address' ? $payload['permanent_address'] : ($column === 'phone' ? $payload['father_mobile'] : ($column === 'previous_school' ? $payload['prev_school_name'] : $payload[$column] ?? null)))));
                $pdo->prepare('INSERT INTO students(' . implode(',', $columns) . ') VALUES(' . implode(',', array_fill(0, count($columns), '?')) . ')')->execute($values);
                $studentId = (int) $pdo->lastInsertId();
                $year = current_academic_year($sid);
                if (!empty($year['id'])) $pdo->prepare('INSERT IGNORE INTO student_enrollments(school_id,student_id,academic_year_id,class_id,status,created_at) VALUES(?,?,?,?,?,?)')->execute([$sid, $studentId, $year['id'], $payload['class_id'], 'enrolled', date(DATE_ATOM)]);
                flash_set('success', 'विद्यार्थी नोंदवला.');
                header('Location: /modules/gr/students/index.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = str_contains($e->getMessage(), 'UNIQUE') ? 'या पुस्तकातील GR क्रमांक आधीपासून वापरला आहे.' : $e->getMessage();
            }
        }
    }
}
layout_start('विद्यार्थी नोंदणी', 'gr');
?>
<div class="max-w-6xl mx-auto"><div class="flex items-center justify-between mb-5"><div><h2 class="text-2xl font-bold text-navy">नवीन विद्यार्थी</h2><p class="text-slate-500 mt-1">जनरल रजिस्टरमध्ये विद्यार्थ्याची संपूर्ण नोंद करा.</p></div><a href="/modules/gr/students/index.php" class="text-teal font-bold">← यादी</a></div><?php if ($errors): ?><div class="mb-5 rounded-xl bg-red-50 text-red-700 p-4"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?><form method="post" class="bg-white border rounded-2xl p-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><?php foreach (student_form_fields($student) as $name => $definition) render_student_field($name, $definition, $student); ?><label class="text-sm font-semibold text-slate-700">वर्ग<select required name="class_id" class="w-full mt-2"><option value="">-- निवडा --</option><?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>" <?= (int) ($student['class_id'] ?? 0) === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-semibold text-slate-700">हजेरी क्रमांक<input type="number" name="roll_no" class="w-full mt-2" value="<?= e($student['roll_no'] ?? 1) ?>"></label><div class="sm:col-span-2 lg:col-span-3 flex gap-3 pt-3"><button class="bg-gold text-navy rounded-xl px-6 py-3 font-bold">✓ रजिस्टरमध्ये नोंद करा</button><a href="/modules/gr/students/index.php" class="border rounded-xl px-6 py-3">रद्द करा</a></div></form></div>
<?php layout_end(); ?>
