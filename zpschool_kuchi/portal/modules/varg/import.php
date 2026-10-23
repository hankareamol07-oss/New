<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';

$user = require_role(['admin','principal','teacher']);
$sid = (int) $user['school_id'];
$pdo = db();
$headers = ['इयत्ता','तुकडी','माध्यम','शाखा','वर्गशिक्षक फोन'];
if (($_GET['action'] ?? '') === 'template') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="varg-template.csv"');
    echo "\xEF\xBB\xBF" . implode(',', $headers) . "\n";
    echo "पहिली,अ,मराठी,,\n";
    exit;
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null) && isset($_FILES['csv'])) {
    $handle = fopen((string) $_FILES['csv']['tmp_name'], 'rb');
    $rows = 0;
    if ($handle) {
        fgetcsv($handle);
        $teachers = [];
        $teacherStmt = getMainDB()->prepare('SELECT id FROM users WHERE school_id=? AND phone=?');
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || trim((string) $row[1]) === '') {
                continue;
            }
            $std = array_search(trim((string) $row[0]), class_labels(), true);
            $std = $std === false ? (int) $row[0] : (int) $std;
            $section = trim((string) $row[1]);
            $medium = trim((string) ($row[2] ?? 'मराठी')) ?: 'मराठी';
            $stream = trim((string) ($row[3] ?? ''));
            $teacherId = null;
            if (!empty($row[4])) {
                $teacherStmt->execute([$sid, trim((string) $row[4])]);
                $teacherId = $teacherStmt->fetchColumn() ?: null;
            }
            $exists = $pdo->prepare('SELECT id FROM classes WHERE school_id=? AND std=? AND section=?');
            $exists->execute([$sid, $std, $section]);
            if (!$exists->fetchColumn()) {
                $pdo->prepare('INSERT INTO classes(school_id,std,name,section,class_teacher_id,medium,stream) VALUES(?,?,?,?,?,?,?)')->execute([$sid, $std, class_label($std) . ' ' . $section, $section, $teacherId, $medium, $stream]);
                $rows++;
            }
        }
        fclose($handle);
    }
    flash_set('success', $rows . ' तुकड्या आयात केल्या.');
    header('Location: /modules/varg/import.php');
    exit;
}
layout_start('वर्ग आयात', 'varg');
?>
<div class="max-w-3xl mx-auto bg-white border rounded-2xl p-7"><h2 class="text-2xl font-bold text-navy">वर्ग व्यवस्थापन — आयात</h2><p class="text-slate-600 mt-2">CSV मध्ये प्रत्येक तुकडीसाठी एक ओळ भरा. नमुना डाउनलोड करून वापरा.</p><div class="flex flex-wrap gap-3 mt-6"><a href="/modules/varg/import.php?action=template" class="bg-white border rounded-xl px-5 py-3 text-teal font-bold">Template डाउनलोड करा</a><a href="/modules/varg/index.php" class="bg-teal text-white rounded-xl px-5 py-3">वर्ग व्यवस्थापन</a></div><form method="post" enctype="multipart/form-data" class="mt-8 border-t pt-6"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label class="block text-sm font-semibold">Excel/CSV फाइल<input required type="file" name="csv" accept=".csv,.txt" class="block mt-2"></label><button class="mt-5 bg-gold text-navy rounded-xl px-5 py-3 font-bold">आयात करा</button></form><div class="mt-8"><h3 class="font-bold text-navy">Template columns</h3><div class="overflow-auto mt-3"><table class="w-full text-sm"><thead><tr class="border-b"><?php foreach ($headers as $header): ?><th class="p-2 text-left"><?= e($header) ?></th><?php endforeach; ?></tr></thead><tbody><tr><td class="p-2">पहिली</td><td class="p-2">अ</td><td class="p-2">मराठी</td><td class="p-2"></td><td class="p-2"></td></tr></tbody></table></div></div></div>
<?php layout_end(); ?>
