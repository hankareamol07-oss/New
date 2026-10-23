<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_layout.php';

$user = require_role(['admin', 'principal', 'clerk']);
$sid = (int) $user['school_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $error = 'CSV फाइल निवडा.';
    } else {
        $pdo = db();
        $quota = getSchoolStudentQuota($sid);
        $current_count = $quota['count'];
        $max_allowed = $quota['max'];
        $is_unlimited = $quota['is_unlimited'];

        $p = $pdo->prepare('INSERT INTO students(school_id,class_id,book_no,gr_no,roll_no,first_name,father_name,surname,mother_name,admission_date,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $fh = fopen($_FILES['csv']['tmp_name'], 'rb');
        $n = 0;
        $skipped_limit = 0;

        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) >= 5 && is_numeric($row[0])) {
                if (!$is_unlimited && ($current_count + $n) >= $max_allowed) {
                    $skipped_limit++;
                    continue;
                }
                $p->execute([$sid, (int)$row[0], 1, (int)$row[1], (int)$row[2], $row[3], $row[4] ?? '', $row[5] ?? '', $row[6] ?? '', date('Y-m-d'), 'active']);
                $n++;
            }
        }
        fclose($fh);

        if ($n > 0) {
            flash_set('success', "$n विद्यार्थी आयात झाले.");
        }
        if ($skipped_limit > 0) {
            flash_set('error', "प्लॅनच्या विद्यार्थी मर्यादेमुळे ($max_allowed) $skipped_limit विद्यार्थी वगळण्यात आले. कृपया प्लॅन अपग्रेड करा.");
        }
        header('Location: /modules/gr/index.php');
        exit;
    }
}

layout_start('CSV Bulk Import', 'gr');
?>
<div class="bg-white border rounded-2xl p-7 max-w-2xl">
    <p class="text-slate-600 mb-5">CSV स्तंभ: class_id, gr_no, roll_no, नाव, वडिलांचे नाव, आडनाव, आईचे नाव</p>
    <?php if ($error): ?>
        <p class="bg-red-50 text-red-700 p-3 rounded-xl mb-4"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input required type="file" name="csv" accept=".csv">
        <button class="bg-gold rounded-xl px-5 py-3 font-bold">आयात करा</button>
    </form>
</div>
<?php layout_end(); ?>
