<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/gr_student.php';

$user = require_role(['admin','principal','clerk']);
$sid = (int) $user['school_id'];
$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM students WHERE id=? AND school_id=?');
$stmt->execute([$id, $sid]);
$student = $stmt->fetch();
if (!$student) {
    http_response_code(404);
    exit('विद्यार्थी सापडला नाही');
}
$classes = school_classes($sid);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'सत्र कालबाह्य.';
    } else {
        $payload = student_payload($_POST, (int) ($_POST['class_id'] ?? 0));
        try {
            $columns = ['class_id','book_no','gr_no','roll_no','first_name','father_name','surname','mother_name','dob','gender','caste','religion','aadhaar','address','phone','admission_date','previous_school','last_name','aadhar_no','admission_class','stream','admission_division','medium','board','prev_school_name','prev_school_class','nationality','mother_tongue','caste_category','father_mobile','mother_mobile','dob_words','birth_place','birth_taluka','birth_district','birth_state','birth_country','permanent_address','village','student_pen','saral_id','status'];
            $values = [];
            foreach ($columns as $column) $values[] = $column === 'status' ? (($_POST['status'] ?? 'active') === 'left' ? 'left' : 'active') : ($column === 'surname' ? $payload['last_name'] : ($column === 'aadhaar' ? $payload['aadhar_no'] : ($column === 'address' ? $payload['permanent_address'] : ($column === 'phone' ? $payload['father_mobile'] : ($column === 'previous_school' ? $payload['prev_school_name'] : $payload[$column] ?? null)))));
            $set = implode(',', array_map(static fn(string $column): string => $column . '=?', $columns));
            $values[] = $id;
            $values[] = $sid;
            $pdo->prepare('UPDATE students SET ' . $set . ' WHERE id=? AND school_id=?')->execute($values);
            flash_set('success', 'विद्यार्थ्याची माहिती अद्ययावत केली.');
            header('Location: /modules/gr/students/index.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
    $student = array_merge($student, $_POST);
}
layout_start('विद्यार्थी संपादन', 'gr');
?>
<div class="max-w-6xl mx-auto"><div class="flex items-center justify-between mb-5"><div><h2 class="text-2xl font-bold text-navy">विद्यार्थी संपादन</h2><p class="text-slate-500 mt-1">GR क्र. <?= e($student['gr_no']) ?></p></div><a href="/modules/gr/students/index.php" class="text-teal font-bold">← यादी</a></div><?php if ($errors): ?><div class="mb-5 rounded-xl bg-red-50 text-red-700 p-4"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?><form method="post" class="bg-white border rounded-2xl p-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><?php foreach (student_form_fields($student) as $name => $definition) render_student_field($name, $definition, $student); ?><label class="text-sm font-semibold text-slate-700">वर्ग<select required name="class_id" class="w-full mt-2"><?php foreach ($classes as $class): ?><option value="<?= $class['id'] ?>" <?= (int) ($student['class_id'] ?? 0) === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach; ?></select></label><label class="text-sm font-semibold text-slate-700">हजेरी क्रमांक<input type="number" name="roll_no" class="w-full mt-2" value="<?= e($student['roll_no'] ?? 1) ?>"></label><label class="text-sm font-semibold text-slate-700">स्थिती<select name="status" class="w-full mt-2"><option value="active" <?= ($student['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option><option value="left" <?= ($student['status'] ?? '') === 'left' ? 'selected' : '' ?>>left</option></select></label><div class="sm:col-span-2 lg:col-span-3"><button class="bg-gold text-navy rounded-xl px-6 py-3 font-bold">जतन करा</button></div></form></div>
<?php layout_end(); ?>
