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

// Academic Year safe fallback
$academic_year_id = 1;
try {
    $ay_row = $pdo->query("SELECT id FROM academic_years WHERE school_id = {$sid} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!empty($ay_row['id'])) {
        $academic_year_id = (int) $ay_row['id'];
    }
} catch (Throwable $e) {
    $academic_year_id = 1;
}

// Quarter mapping
$quarters = [
    1 => '१ ली तिमाही - जून (सत्र १ आरंभ)',
    2 => '२ री तिमाही - सप्टेंबर (१ ले सत्र)',
    3 => '३ री तिमाही - डिसेंबर (२ रे सत्र)',
    4 => '४ थी तिमाही - मार्च (सत्र २ अखेर)'
];

$selected_quarter = (int) ($_GET['quarter'] ?? $_POST['quarter'] ?? 1);
if (!isset($quarters[$selected_quarter])) $selected_quarter = 1;

$selected_std = (string) ($_GET['std'] ?? $_POST['std'] ?? '1');

// Default measurement dates for quarters
$default_dates = [
    1 => date('Y') . '-06-25',
    2 => date('Y') . '-09-25',
    3 => date('Y') . '-12-24',
    4 => (date('Y') + 1) . '-03-25'
];
$measured_on = (string) ($_POST['measured_on'] ?? $_GET['measured_on'] ?? ($default_dates[$selected_quarter] ?? date('Y-m-d')));

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    $students_post = (array) ($_POST['student_id'] ?? []);
    $heights = (array) ($_POST['height_cm'] ?? []);
    $weights = (array) ($_POST['weight_kg'] ?? []);
    $bmis = (array) ($_POST['bmi'] ?? []);
    $categories = (array) ($_POST['category'] ?? []);
    $q_num = (int) ($_POST['quarter'] ?? $selected_quarter);
    $m_date = (string) ($_POST['measured_on'] ?? date('Y-m-d'));

    $stmt_check = $pdo->prepare('SELECT id FROM mdm_bmi WHERE school_id = ? AND student_id = ? AND quarter = ? LIMIT 1');
    $stmt_update = $pdo->prepare('UPDATE mdm_bmi SET measured_on = ?, height_cm = ?, weight_kg = ?, bmi = ?, category = ? WHERE id = ?');
    $stmt_insert = $pdo->prepare('INSERT INTO mdm_bmi (school_id, student_id, academic_year_id, measured_on, height_cm, weight_kg, bmi, category, quarter) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $saved_count = 0;
    foreach ($students_post as $i => $s_id_raw) {
        $s_id = (int) $s_id_raw;
        $h = (float) ($heights[$i] ?? 0);
        $w = (float) ($weights[$i] ?? 0);
        if ($h <= 0 && $w <= 0) continue; // Skip blank rows

        $b = (float) ($bmis[$i] ?? 0);
        if ($b <= 0 && $h > 0) {
            $h_m = $h / 100.0;
            $b = round($w / ($h_m * $h_m), 2);
        }
        $cat = (string) ($categories[$i] ?? '');
        if ($cat === '') {
            $cat = mdm_category($b);
        }

        $stmt_check->execute([$sid, $s_id, $q_num]);
        $existing_id = $stmt_check->fetchColumn();

        if ($existing_id) {
            $stmt_update->execute([$m_date, $h, $w, $b, $cat, (int)$existing_id]);
        } else {
            $stmt_insert->execute([$sid, $s_id, $academic_year_id, $m_date, $h, $w, $b, $cat, $q_num]);
        }
        $saved_count++;
    }

    flash_set('success', "एकूण {$saved_count} विद्यार्थ्यांची BMI नोंद यशस्वीरित्या जतन केली.");
    header('Location: ' . url('modules/mdm/bmi/entry.php?quarter=' . $q_num . '&std=' . urlencode($selected_std) . '&measured_on=' . urlencode($m_date)));
    exit;
}

// Fetch MDM Students (Isolated from general school DB)
$sql_students = "SELECT s.id, s.name, s.std, s.division, s.gender, s.dob, s.roll_no 
                 FROM mdm_students s 
                 WHERE s.school_id = ?";
$params = [$sid];

if ($selected_std !== 'all' && $selected_std !== '') {
    $sql_students .= " AND s.std = ?";
    $params[] = $selected_std;
}
$sql_students .= " ORDER BY CAST(s.std AS UNSIGNED), CAST(s.roll_no AS UNSIGNED), s.name";

$st_stmt = $pdo->prepare($sql_students);
$st_stmt->execute($params);
$students = $st_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing BMI data for selected quarter
$bmi_map = [];
$bmi_stmt = $pdo->prepare('SELECT * FROM mdm_bmi WHERE school_id = ? AND quarter = ?');
$bmi_stmt->execute([$sid, $selected_quarter]);
foreach ($bmi_stmt->fetchAll(PDO::FETCH_ASSOC) as $brow) {
    $bmi_map[(int)$brow['student_id']] = $brow;
}

// Available classes
$std_list = ['1', '2', '3', '4', '5', '6', '7', '8'];

mdm_layout_start('BMI नोंद (तिमाहीनिहाय) — पोषण आहार');
?>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-xl sm:text-2xl font-bold text-navy">विद्यार्थी BMI नोंदणी (४ त्रैमासिक)</h2>
        <p class="text-xs sm:text-sm text-slate-500">उंची (cm) व वजन (kg) टाकताच BMI व वर्गवारी आपोआप गणना होईल</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= e(url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-teal text-white px-3 py-2 font-semibold hover:bg-teal/90 transition shadow-sm text-xs sm:text-sm">
            <span>📥</span> विद्यार्थी आयात करा
        </a>
        <a href="<?= e(url('modules/mdm/bmi/report.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-navy text-white px-3 py-2 font-semibold hover:bg-navy/90 transition shadow-sm text-xs sm:text-sm">
            <span>📊</span> वार्षिक BMI अहवाल
        </a>
    </div>
</div>

<!-- Filters Form (Mobile Friendly) -->
<form method="get" class="bg-white border rounded-2xl p-4 sm:p-5 mb-5 shadow-sm no-print">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">त्रैमासिक (सत्र)</label>
            <select name="quarter" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50 focus:bg-white">
                <?php foreach ($quarters as $q_k => $q_v): ?>
                    <option value="<?= $q_k ?>" <?= $selected_quarter === $q_k ? 'selected' : '' ?>><?= e($q_v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">इयत्ता / वर्ग</label>
            <select name="std" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50 focus:bg-white">
                <option value="all" <?= $selected_std === 'all' ? 'selected' : '' ?>>सर्व इयत्ता (१ ते ८)</option>
                <?php foreach ($std_list as $st): ?>
                    <option value="<?= $st ?>" <?= $selected_std === $st ? 'selected' : '' ?>>इयत्ता <?= $st ?> ली</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-1">मापन दिनांक</label>
            <input type="date" name="measured_on" value="<?= e($measured_on) ?>" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <button type="submit" class="w-full rounded-xl bg-navy text-white px-4 py-2 text-sm font-bold hover:bg-navy/90 transition shadow-sm">
                फिल्टर करा
            </button>
        </div>
    </div>
</form>

<?php if (empty($students)): ?>
    <div class="bg-white border-2 border-dashed border-teal/40 rounded-2xl p-6 sm:p-10 text-center shadow-sm">
        <div class="w-14 h-14 bg-teal/10 rounded-full flex items-center justify-center mx-auto text-2xl mb-3">
            📥
        </div>
        <h3 class="text-base sm:text-lg font-bold text-navy">या इयत्तेत MDM साठी अद्याप विद्यार्थी जोडलेले नाहीत</h3>
        <p class="text-xs sm:text-sm text-slate-500 max-w-lg mx-auto mt-2 leading-relaxed">
            इतर सर्व मॉड्युलचे विद्यार्थी येथे गोंधळ करू नयेत म्हणून MDM विद्यार्थी यादी स्वतंत्र ठेवण्यात आली आहे. 
            तुम्ही शाळा पटामधून एका क्लिकवर आवश्यक विद्यार्थी आयात करू शकता.
        </p>
        <div class="mt-5 flex flex-wrap justify-center gap-3">
            <a href="<?= e(url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-2 rounded-xl bg-teal text-white font-bold px-6 py-2.5 hover:bg-teal/90 transition text-sm shadow">
                <span>📥</span> शाळा पटामधून विद्यार्थी आयात करा
            </a>
            <a href="<?= e(url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 text-slate-700 font-bold px-5 py-2.5 hover:bg-slate-200 transition text-sm">
                <span>➕</span> नवीन विद्यार्थी थेट जोडा
            </a>
        </div>
    </div>
<?php else: ?>

<form method="post" id="bmiForm" class="space-y-4">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="quarter" value="<?= $selected_quarter ?>">
    <input type="hidden" name="measured_on" value="<?= e($measured_on) ?>">

    <div class="bg-white border rounded-2xl p-4 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-bold">
                <?= e($quarters[$selected_quarter]) ?>
            </span>
            <span class="text-xs text-slate-500">विद्यार्थी: <strong><?= count($students) ?></strong></span>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= e(url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std))) ?>" class="text-xs font-bold text-teal hover:underline">
                + विद्यार्थी जोडा / आयात
            </a>
            <button type="submit" class="rounded-xl bg-gold text-navy font-bold px-5 py-2 hover:brightness-105 transition shadow-sm text-sm flex items-center gap-1.5">
                <span>💾</span> जतन करा
            </button>
        </div>
    </div>

    <!-- Desktop View Table (hidden on small mobile, visible on sm and up) -->
    <div class="hidden sm:block bg-white border rounded-2xl p-5 shadow-sm overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-700 text-xs font-bold uppercase tracking-wider border-b">
                    <th class="p-3 w-12 text-center">हजेरी</th>
                    <th class="p-3">विद्यार्थ्याचे नाव</th>
                    <th class="p-3 w-16 text-center">इयत्ता</th>
                    <th class="p-3 w-16 text-center">लिंग</th>
                    <th class="p-3 w-32 text-center">उंची (cm)</th>
                    <th class="p-3 w-32 text-center">वजन (kg)</th>
                    <th class="p-3 w-24 text-center">BMI</th>
                    <th class="p-3 w-44">आरोग्य वर्गवारी</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($students as $idx => $s): 
                    $sid_val = (int) $s['id'];
                    $bdata = $bmi_map[$sid_val] ?? [];
                    $h_val = !empty($bdata['height_cm']) ? (float)$bdata['height_cm'] : '';
                    $w_val = !empty($bdata['weight_kg']) ? (float)$bdata['weight_kg'] : '';
                    $bmi_val = !empty($bdata['bmi']) ? (float)$bdata['bmi'] : '';
                    $cat_val = $bdata['category'] ?? '';

                    $sname = $s['name'];
                    $roll = $s['roll_no'] ?: ($idx + 1);
                    $std_disp = $s['std'];
                    $gender_disp = ($s['gender'] ?? '') === 'F' ? 'मुली' : 'मुलगा';
                ?>
                <tr class="hover:bg-slate-50 transition" data-row="<?= $idx ?>">
                    <td class="p-3 text-center text-slate-500 font-semibold"><?= e((string)$roll) ?></td>
                    <td class="p-3 font-semibold text-slate-800">
                        <input type="hidden" name="student_id[]" value="<?= $sid_val ?>">
                        <?= e($sname) ?>
                    </td>
                    <td class="p-3 text-center text-slate-600 font-semibold"><?= e((string)$std_disp) ?></td>
                    <td class="p-3 text-center text-xs text-slate-500"><?= e($gender_disp) ?></td>
                    <td class="p-3 text-center">
                        <input type="number" step="0.1" min="40" max="220" inputmode="decimal" name="height_cm[]" value="<?= $h_val !== '' ? $h_val : '' ?>" 
                               placeholder="उंची cm" class="h-input w-24 text-center rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-teal focus:ring-1 focus:ring-teal"
                               oninput="calcBmi(<?= $idx ?>, 'table')">
                    </td>
                    <td class="p-3 text-center">
                        <input type="number" step="0.1" min="5" max="150" inputmode="decimal" name="weight_kg[]" value="<?= $w_val !== '' ? $w_val : '' ?>" 
                               placeholder="वजन kg" class="w-input w-24 text-center rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-teal focus:ring-1 focus:ring-teal"
                               oninput="calcBmi(<?= $idx ?>, 'table')">
                    </td>
                    <td class="p-3 text-center">
                        <input type="text" readonly name="bmi[]" value="<?= $bmi_val !== '' ? number_format((float)$bmi_val, 2) : '' ?>" 
                               class="bmi-input w-20 text-center rounded-lg bg-slate-100 border border-slate-200 px-2 py-1.5 text-sm font-bold text-navy">
                    </td>
                    <td class="p-3">
                        <input type="text" readonly name="category[]" value="<?= e($cat_val) ?>" 
                               class="cat-input w-full rounded-lg bg-slate-100 border border-slate-200 px-2 py-1.5 text-xs font-semibold text-slate-700">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile View Cards (visible on small mobile only) -->
    <div class="block sm:hidden space-y-3">
        <?php foreach ($students as $idx => $s): 
            $sid_val = (int) $s['id'];
            $bdata = $bmi_map[$sid_val] ?? [];
            $h_val = !empty($bdata['height_cm']) ? (float)$bdata['height_cm'] : '';
            $w_val = !empty($bdata['weight_kg']) ? (float)$bdata['weight_kg'] : '';
            $bmi_val = !empty($bdata['bmi']) ? (float)$bdata['bmi'] : '';
            $cat_val = $bdata['category'] ?? '';

            $sname = $s['name'];
            $roll = $s['roll_no'] ?: ($idx + 1);
            $std_disp = $s['std'];
            $gender_disp = ($s['gender'] ?? '') === 'F' ? 'मुली' : 'मुलगा';
        ?>
        <div class="bg-white border rounded-xl p-3.5 shadow-sm" data-m-row="<?= $idx ?>">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs">
                        <?= e((string)$roll) ?>
                    </span>
                    <span class="font-bold text-navy text-sm"><?= e($sname) ?></span>
                </div>
                <span class="text-[11px] px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-semibold">
                    इ. <?= e((string)$std_disp) ?> (<?= e($gender_disp) ?>)
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2 mt-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-0.5">उंची (cm)</label>
                    <input type="number" step="0.1" min="40" max="220" inputmode="decimal" name="m_height_cm[]" value="<?= $h_val !== '' ? $h_val : '' ?>" 
                           placeholder="उदा. 125.5" class="m-h-input w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-semibold"
                           oninput="syncMobileToDesktop(<?= $idx ?>)">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-0.5">वजन (kg)</label>
                    <input type="number" step="0.1" min="5" max="150" inputmode="decimal" name="m_weight_kg[]" value="<?= $w_val !== '' ? $w_val : '' ?>" 
                           placeholder="उदा. 24.5" class="m-w-input w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-semibold"
                           oninput="syncMobileToDesktop(<?= $idx ?>)">
                </div>
            </div>

            <div class="mt-2.5 flex items-center justify-between pt-2 border-t text-xs">
                <span class="text-slate-500">BMI: <strong class="m-bmi-badge text-navy font-bold"><?= $bmi_val !== '' ? number_format((float)$bmi_val, 2) : '-' ?></strong></span>
                <span class="m-cat-badge px-2 py-0.5 rounded text-[10px] font-bold <?= $cat_val ? 'bg-slate-100 text-slate-700' : 'text-slate-400' ?>">
                    <?= e($cat_val ?: 'नोंद प्रलंबित') ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Sticky Mobile & Desktop Submit Button -->
    <div class="sticky bottom-4 z-20 flex justify-end">
        <button type="submit" class="w-full sm:w-auto rounded-xl bg-gold text-navy font-bold px-8 py-3.5 hover:brightness-105 transition shadow-lg text-base flex items-center justify-center gap-2">
            <span>💾</span> सर्व BMI नोंदी जतन करा
        </button>
    </div>
</form>

<script>
function getCategoryInfo(bmi) {
    if (bmi < 16.0) {
        return { text: 'तीव्र कुपोषित (SAM)', class: 'bg-red-100 text-red-800 border border-red-300' };
    } else if (bmi < 18.5) {
        return { text: 'मध्यम कुपोषित (MAM)', class: 'bg-amber-100 text-amber-800 border border-amber-300' };
    } else if (bmi <= 24.9) {
        return { text: 'सामान्य / निरोगी (Normal)', class: 'bg-green-100 text-green-800 border border-green-300' };
    } else if (bmi <= 29.9) {
        return { text: 'जास्त वजन (Overweight)', class: 'bg-yellow-100 text-yellow-800 border border-yellow-300' };
    } else {
        return { text: 'लठ्ठपणा (Obese)', class: 'bg-purple-100 text-purple-800 border border-purple-300' };
    }
}

function calcBmi(idx, origin) {
    const tableRow = document.querySelector(`tr[data-row="${idx}"]`);
    const mobileCard = document.querySelector(`div[data-m-row="${idx}"]`);

    let h = 0, w = 0;
    if (origin === 'table' && tableRow) {
        h = parseFloat(tableRow.querySelector('.h-input').value) || 0;
        w = parseFloat(tableRow.querySelector('.w-input').value) || 0;
        if (mobileCard) {
            mobileCard.querySelector('.m-h-input').value = h ? h : '';
            mobileCard.querySelector('.m-w-input').value = w ? w : '';
        }
    } else if (origin === 'mobile' && mobileCard) {
        h = parseFloat(mobileCard.querySelector('.m-h-input').value) || 0;
        w = parseFloat(mobileCard.querySelector('.m-w-input').value) || 0;
        if (tableRow) {
            tableRow.querySelector('.h-input').value = h ? h : '';
            tableRow.querySelector('.w-input').value = w ? w : '';
        }
    }

    if (h > 0 && w > 0) {
        const hm = h / 100.0;
        const bmi = (w / (hm * hm));
        const bmiFormatted = bmi.toFixed(2);
        const catInfo = getCategoryInfo(bmi);

        if (tableRow) {
            tableRow.querySelector('.bmi-input').value = bmiFormatted;
            const catInput = tableRow.querySelector('.cat-input');
            catInput.value = catInfo.text;
            catInput.className = `cat-input w-full rounded-lg px-2 py-1.5 text-xs font-bold ${catInfo.class}`;
        }
        if (mobileCard) {
            mobileCard.querySelector('.m-bmi-badge').textContent = bmiFormatted;
            const mCat = mobileCard.querySelector('.m-cat-badge');
            mCat.textContent = catInfo.text;
            mCat.className = `m-cat-badge px-2 py-0.5 rounded text-[10px] font-bold ${catInfo.class}`;
        }
    } else {
        if (tableRow) {
            tableRow.querySelector('.bmi-input').value = '';
            const catInput = tableRow.querySelector('.cat-input');
            catInput.value = '';
            catInput.className = 'cat-input w-full rounded-lg bg-slate-100 border border-slate-200 px-2 py-1.5 text-xs font-semibold text-slate-700';
        }
        if (mobileCard) {
            mobileCard.querySelector('.m-bmi-badge').textContent = '-';
            const mCat = mobileCard.querySelector('.m-cat-badge');
            mCat.textContent = 'नोंद प्रलंबित';
            mCat.className = 'm-cat-badge px-2 py-0.5 rounded text-[10px] font-bold text-slate-400 bg-slate-100';
        }
    }
}

function syncMobileToDesktop(idx) {
    calcBmi(idx, 'mobile');
}
</script>
<?php endif; ?>

<?php layout_end(); ?>
