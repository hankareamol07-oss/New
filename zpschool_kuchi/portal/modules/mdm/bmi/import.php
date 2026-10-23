<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_role(['admin', 'principal', 'clerk', 'teacher']);
$sid = (int) $u['school_id'];
$pdo = db();

// Ensure mdm_students table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS mdm_students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    school_id INTEGER NOT NULL,
    roll_no TEXT DEFAULT '',
    name TEXT NOT NULL,
    std TEXT NOT NULL,
    division TEXT DEFAULT '',
    gender TEXT DEFAULT 'M',
    dob TEXT DEFAULT '',
    source_student_id INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$selected_std = (string) ($_GET['std'] ?? $_POST['std'] ?? '1');
$std_list = ['1', '2', '3', '4', '5', '6', '7', '8'];

// Handle Manual Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_manual' && valid_csrf($_POST['csrf'] ?? null)) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $roll = trim((string) ($_POST['roll_no'] ?? ''));
    $std = trim((string) ($_POST['std'] ?? '1'));
    $div = trim((string) ($_POST['division'] ?? ''));
    $gender = (string) ($_POST['gender'] ?? 'M');

    if ($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO mdm_students (school_id, roll_no, name, std, division, gender) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$sid, $roll, $name, $std, $div, $gender]);
        flash_set('success', "विद्यार्थी '{$name}' MDM मध्ये जोडला गेला.");
    }
    header('Location: ' . url('modules/mdm/bmi/import.php?std=' . urlencode($std)));
    exit;
}

// Handle Import from Main School DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_selected' && valid_csrf($_POST['csrf'] ?? null)) {
    $sel_ids = (array) ($_POST['import_student_ids'] ?? []);
    $imported_count = 0;

    $stmt_find = $pdo->prepare("SELECT id, name, name_mr, first_name, father_name, last_name, std, grade, roll_no, gender, dob 
                                FROM students 
                                WHERE school_id = ? AND id = ? LIMIT 1");
    $stmt_check = $pdo->prepare("SELECT id FROM mdm_students WHERE school_id = ? AND source_student_id = ? LIMIT 1");
    $stmt_ins = $pdo->prepare("INSERT INTO mdm_students (school_id, roll_no, name, std, division, gender, dob, source_student_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($sel_ids as $st_id_raw) {
        $st_id = (int) $st_id_raw;
        $stmt_find->execute([$sid, $st_id]);
        $s = $stmt_find->fetch(PDO::FETCH_ASSOC);
        if (!$s) continue;

        // Check if already in mdm_students
        $stmt_check->execute([$sid, $st_id]);
        if ($stmt_check->fetchColumn()) continue;

        $sname = trim(($s['first_name'] ?? '') . ' ' . ($s['father_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
        if ($sname === '') $sname = $s['name_mr'] ?: ($s['name'] ?? 'विद्यार्थी #' . $st_id);

        $std_val = $s['std'] ?: ($s['grade'] ?? $selected_std);
        $roll_val = (string)($s['roll_no'] ?? '');
        $gender_val = ($s['gender'] ?? '') === 'F' ? 'F' : 'M';
        $dob_val = (string)($s['dob'] ?? '');

        $stmt_ins->execute([$sid, $roll_val, $sname, $std_val, '', $gender_val, $dob_val, $st_id]);
        $imported_count++;
    }

    flash_set('success', "एकूण {$imported_count} विद्यार्थी शाळा डेटाबेसमधून MDM मध्ये यशस्वीरित्या आयात केले.");
    header('Location: ' . url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std)));
    exit;
}

// Handle Delete from MDM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_mdm' && valid_csrf($_POST['csrf'] ?? null)) {
    $del_id = (int) ($_POST['delete_id'] ?? 0);
    if ($del_id > 0) {
        $pdo->prepare('DELETE FROM mdm_students WHERE school_id = ? AND id = ?')->execute([$sid, $del_id]);
        // Also remove their BMI records
        $pdo->prepare('DELETE FROM mdm_bmi WHERE school_id = ? AND student_id = ?')->execute([$sid, $del_id]);
        flash_set('success', 'विद्यार्थी MDM यादीतून वगळण्यात आला.');
    }
    header('Location: ' . url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std)));
    exit;
}

// Fetch MDM Students for selected std
$mdm_stmt = $pdo->prepare('SELECT * FROM mdm_students WHERE school_id = ? AND std = ? ORDER BY CAST(roll_no AS UNSIGNED), name ASC');
$mdm_stmt->execute([$sid, $selected_std]);
$mdm_students = $mdm_stmt->fetchAll(PDO::FETCH_ASSOC);

$already_imported = [];
foreach ($mdm_students as $ms) {
    if (!empty($ms['source_student_id'])) {
        $already_imported[(int)$ms['source_student_id']] = true;
    }
}

// Fetch Main School Database Students for selected std
$school_stmt = $pdo->prepare("SELECT id, name, name_mr, first_name, father_name, last_name, std, grade, roll_no, gender, dob 
                              FROM students 
                              WHERE school_id = ? AND (std = ? OR grade = ?) AND (status = 'active' OR is_active = 1)
                              ORDER BY CAST(roll_no AS UNSIGNED), first_name, name");
$school_stmt->execute([$sid, $selected_std, $selected_std]);
$school_students = $school_stmt->fetchAll(PDO::FETCH_ASSOC);

mdm_layout_start('MDM विद्यार्थी आयात व व्यवस्थापन — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-navy">MDM विद्यार्थी व्यवस्थापन व आयात</h2>
        <p class="text-sm text-slate-500">पोषण आहार व BMI साठी स्वतंत्र विद्यार्थी यादी (शाळा डेटाबेसमधून ऐच्छिक आयात)</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= e(url('modules/mdm/bmi/entry.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-2 rounded-xl bg-gold text-navy font-bold px-4 py-2 hover:brightness-105 transition text-sm">
            <span>⚖️</span> BMI नोंद पृष्ठाकडे जा →
        </a>
    </div>
</div>

<!-- Class Filter -->
<div class="bg-white border rounded-2xl p-4 mb-6 shadow-sm flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <label class="text-xs font-bold text-slate-700">इयत्ता निवडा:</label>
        <div class="flex flex-wrap gap-1.5">
            <?php foreach ($std_list as $st): ?>
                <a href="?std=<?= $st ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $selected_std === $st ? 'bg-navy text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                    इयत्ता <?= $st ?> ली
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="text-xs text-slate-500">
        या इयत्तेत MDM मध्ये नोंद झालेले: <strong class="text-teal font-bold"><?= count($mdm_students) ?></strong> विद्यार्थी
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- Column 1: Import from School Database (7 cols) -->
    <div class="lg:col-span-7 space-y-6">
        <div class="bg-white border rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b mb-4">
                <div>
                    <h3 class="font-bold text-navy text-base flex items-center gap-2">
                        <span>📥</span> शाळा मुख्य डेटाबेसमधून विद्यार्थी आयात करा
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">इयत्ता <?= e($selected_std) ?> ली च्या शाळेतील विद्यार्थ्यांची यादी</p>
                </div>
                <button type="button" onclick="toggleSelectAll()" class="text-xs text-teal font-bold hover:underline">
                    सर्व निवडा / अननिवडा
                </button>
            </div>

            <form method="post" id="importForm">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="import_selected">
                <input type="hidden" name="std" value="<?= e($selected_std) ?>">

                <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead class="sticky top-0 bg-slate-100 border-b">
                            <tr class="text-slate-700 font-bold">
                                <th class="p-2.5 w-10 text-center">निवड</th>
                                <th class="p-2.5 w-12 text-center">हजेरी</th>
                                <th class="p-2.5">विद्यार्थ्याचे नाव (शाळा पट)</th>
                                <th class="p-2.5 w-14 text-center">लिंग</th>
                                <th class="p-2.5 w-24 text-center">स्थिती</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($school_students)): ?>
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-slate-400">शाळा डेटाबेसमध्ये इयत्ता <?= e($selected_std) ?> चे विद्यार्थी आढळले नाहीत.</td>
                                </tr>
                            <?php else: foreach ($school_students as $idx => $ss): 
                                $ss_id = (int)$ss['id'];
                                $is_in = isset($already_imported[$ss_id]);
                                $sname = trim(($ss['first_name'] ?? '') . ' ' . ($ss['father_name'] ?? '') . ' ' . ($ss['last_name'] ?? ''));
                                if ($sname === '') $sname = $ss['name_mr'] ?: ($ss['name'] ?? 'विद्यार्थी #' . $ss_id);
                                $roll = $ss['roll_no'] ?: ($idx + 1);
                                $gender = ($ss['gender'] ?? '') === 'F' ? 'मुली' : 'मुलगा';
                            ?>
                            <tr class="hover:bg-slate-50 <?= $is_in ? 'bg-slate-50/60' : '' ?>">
                                <td class="p-2.5 text-center">
                                    <?php if ($is_in): ?>
                                        <span class="text-emerald-600 font-black text-xs">✓</span>
                                    <?php else: ?>
                                        <input type="checkbox" name="import_student_ids[]" value="<?= $ss_id ?>" class="import-chk rounded border-slate-300 text-teal focus:ring-teal">
                                    <?php endif; ?>
                                </td>
                                <td class="p-2.5 text-center text-slate-500 font-semibold"><?= e((string)$roll) ?></td>
                                <td class="p-2.5 font-semibold text-slate-800"><?= e($sname) ?></td>
                                <td class="p-2.5 text-center text-slate-500"><?= e($gender) ?></td>
                                <td class="p-2.5 text-center">
                                    <?php if ($is_in): ?>
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">आयात केला</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px]">उपलब्ध</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-3 border-t flex justify-end">
                    <button type="submit" class="rounded-xl bg-teal text-white font-bold px-6 py-2.5 hover:bg-teal/90 transition shadow-sm text-xs flex items-center gap-2">
                        <span>📥</span> निवडलेले विद्यार्थी MDM मध्ये आयात करा
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: Manual Add & Current MDM List (5 cols) -->
    <div class="lg:col-span-5 space-y-6">

        <!-- Manual Add Form -->
        <div class="bg-white border rounded-2xl p-5 shadow-sm">
            <h3 class="font-bold text-navy text-sm flex items-center gap-2 pb-2 border-b mb-3">
                <span>➕</span> MDM मध्ये नवीन विद्यार्थी थेट जोडा
            </h3>
            <form method="post" class="space-y-3">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_manual">
                <input type="hidden" name="std" value="<?= e($selected_std) ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">विद्यार्थ्याचे पूर्ण नाव</label>
                    <input type="text" name="name" required placeholder="उदा. राहुल सचिन पाटील" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs bg-white">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">हजेरी क्र.</label>
                        <input type="text" name="roll_no" placeholder="उदा. १" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">तुकडी</label>
                        <input type="text" name="division" placeholder="A" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">लिंग</label>
                        <select name="gender" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs bg-white">
                            <option value="M">मुलगा</option>
                            <option value="F">मुलगी</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full rounded-lg bg-navy text-white font-bold py-2 text-xs hover:bg-navy/90 transition shadow-sm mt-2">
                    + विद्यार्थी जोडा
                </button>
            </form>
        </div>

        <!-- Current MDM Students Roster -->
        <div class="bg-white border rounded-2xl p-5 shadow-sm">
            <h3 class="font-bold text-navy text-sm flex items-center justify-between pb-2 border-b mb-3">
                <span class="flex items-center gap-2"><span>📋</span> MDM विद्यार्थी यादी (इ. <?= e($selected_std) ?>)</span>
                <span class="text-xs text-teal font-bold"><?= count($mdm_students) ?> विद्यार्थी</span>
            </h3>

            <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 font-bold border-b">
                            <th class="p-2 w-10 text-center">हजेरी</th>
                            <th class="p-2">नाव</th>
                            <th class="p-2 w-12 text-center">वगळा</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($mdm_students)): ?>
                            <tr>
                                <td colspan="3" class="p-4 text-center text-slate-400">या इयत्तेत कोणतेही विद्यार्थी नाहीत. डावीकडून आयात करा.</td>
                            </tr>
                        <?php else: foreach ($mdm_students as $ms): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-2 text-center text-slate-500 font-semibold"><?= e((string)$ms['roll_no']) ?></td>
                                <td class="p-2 font-semibold text-slate-800"><?= e($ms['name']) ?></td>
                                <td class="p-2 text-center">
                                    <form method="post" onsubmit="return confirm('हा विद्यार्थी MDM यादीतून वगळायचा आहे का?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_mdm">
                                        <input type="hidden" name="std" value="<?= e($selected_std) ?>">
                                        <input type="hidden" name="delete_id" value="<?= $ms['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-bold" title="वगळा">✕</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
let selectAllState = false;
function toggleSelectAll() {
    selectAllState = !selectAllState;
    document.querySelectorAll('.import-chk').forEach(cb => cb.checked = selectAllState);
}
</script>

<?php layout_end(); ?>
