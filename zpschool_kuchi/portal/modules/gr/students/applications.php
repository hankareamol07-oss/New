<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_layout.php';
require_once __DIR__ . '/../../lib/gr_student.php';

$user = require_role(['admin','principal','clerk']);
$sid = (int) $user['school_id'];
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? 'rejected');
    if ($status === 'approve_all') {
        $pdo->prepare("UPDATE admission_applications SET status='approved' WHERE school_id=? AND status='pending'")->execute([$sid]);
        flash_set('success', 'सर्व प्रलंबित प्रवेश अर्ज आपोआप मंजूर केले (All approved).');
        header('Location: /modules/gr/students/applications.php');
        exit;
    }
    if ($status === 'approved') {
        $application = $pdo->prepare('SELECT * FROM admission_applications WHERE id=? AND school_id=?');
        $application->execute([$id, $sid]);
        $application = $application->fetch();
        if ($application) {
            $pdo->prepare('UPDATE admission_applications SET status=? WHERE id=? AND school_id=?')->execute(['approved', $id, $sid]);
            flash_set('success', 'प्रवेश अर्ज मंजूर झाला. विद्यार्थी नोंदणीसाठी GR मध्ये नोंद करा.');
        }
    } else {
        $pdo->prepare('UPDATE admission_applications SET status=? WHERE id=? AND school_id=?')->execute([$status === 'pending' ? 'pending' : 'rejected', $id, $sid]);
    }
    header('Location: /modules/gr/students/applications.php');
    exit;
}
$filter = in_array($_GET['status'] ?? 'pending', ['pending','approved','rejected','all'], true) ? (string) ($_GET['status'] ?? 'pending') : 'pending';
$sql = 'SELECT * FROM admission_applications WHERE school_id=?';
$args = [$sid];
if ($filter !== 'all') {
    $sql .= ' AND status=?';
    $args[] = $filter;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($args);

$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM admission_applications WHERE school_id = $sid AND status = 'pending'")->fetchColumn();

layout_start('प्रवेश अर्ज', 'gr');
?>
<div class="bg-white border rounded-2xl p-6">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-navy">प्रवेश अर्ज</h2>
            <p class="text-sm text-slate-500 mt-1">नवीन विद्यार्थ्यांचे ऑनलाईन/ऑफलाईन आलेले अर्ज</p>
        </div>
        <?php if ($pending_count > 0): ?>
        <form method="post" onsubmit="return confirm('सर्व <?= $pending_count ?> प्रलंबित अर्ज मंजूर करायचे आहेत का?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="status" value="approve_all">
            <button type="submit" class="rounded-lg px-4 py-2 bg-emerald-600 text-white font-bold hover:bg-emerald-700 text-sm shadow flex items-center gap-2">
                ✓ सर्व अर्ज मंजूर करा (Auto Approve All: <?= $pending_count ?>)
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="flex gap-2 flex-wrap mb-6">
        <?php foreach (['pending'=>'प्रलंबित','approved'=>'मंजूर','rejected'=>'नाकारलेले','all'=>'सर्व'] as $value => $label): ?>
            <a href="?status=<?= $value ?>" class="rounded-lg px-3 py-2 text-sm font-semibold <?= $filter === $value ? 'bg-teal text-white' : 'bg-slate-50 border text-slate-700' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap"><table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-3">अर्ज दिनांक</th><th class="p-3">विद्यार्थी</th><th class="p-3">प्रवेश इयत्ता</th><th class="p-3">पालक मोबाईल</th><th class="p-3">स्थिती</th><th class="p-3">कृती</th></tr></thead><tbody><?php $has = false; foreach ($stmt as $application): $has = true; ?><tr class="border-b"><td class="p-3"><?= e($application['applied_date']) ?></td><td class="p-3 font-semibold"><?= e(trim($application['first_name'] . ' ' . $application['father_name'] . ' ' . $application['last_name'])) ?></td><td class="p-3"><?= e(class_label((int) $application['desired_class'])) ?></td><td class="p-3"><?= e($application['father_mobile']) ?></td><td class="p-3"><?= e($application['status']) ?></td><td class="p-3 flex gap-2"><?php if ($application['status'] === 'pending'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $application['id'] ?>"><input type="hidden" name="status" value="approved"><button class="text-teal font-bold">मंजूर</button></form><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $application['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="text-red-600 font-bold">नकार</button></form><?php endif; ?></td></tr><?php endforeach; if (!$has): ?><tr><td colspan="6" class="p-10 text-center text-slate-500">या टॅबमध्ये कोणतेही अर्ज नाहीत.</td></tr><?php endif; ?></tbody></table></div></div>
<?php layout_end(); ?>
