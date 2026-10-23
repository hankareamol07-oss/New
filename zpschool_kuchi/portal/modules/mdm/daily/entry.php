<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';

$u = require_role(['admin', 'principal', 'clerk', 'teacher']);
$sid = (int) $u['school_id'];
$pdo = db();

mdm_ensure_seeded($pdo, $sid);

$date = (string) ($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));
$weekday_num = (int) date('N', strtotime($date)); // 1=Mon, 7=Sun

// Fetch pat for 1-5 and 6-8
$cur_m = (int) date('n', strtotime($date));
$cur_y = (int) date('Y', strtotime($date));

$pat_stmt = $pdo->prepare('SELECT enrolled FROM mdm_pat_history WHERE school_id=? AND class_group=? AND month=? AND year=?');
$pat_stmt->execute([$sid, '1-5', $cur_m, $cur_y]);
$pat_1to5 = (int)($pat_stmt->fetchColumn() ?: 0);
if ($pat_1to5 === 0) {
    $pat_1to5 = (int)$pdo->query("SELECT enrolled FROM mdm_pat_history WHERE school_id={$sid} AND class_group='1-5' ORDER BY year DESC, month DESC LIMIT 1")->fetchColumn();
}

$pat_stmt->execute([$sid, '6-8', $cur_m, $cur_y]);
$pat_6to8 = (int)($pat_stmt->fetchColumn() ?: 0);
if ($pat_6to8 === 0) {
    $pat_6to8 = (int)$pdo->query("SELECT enrolled FROM mdm_pat_history WHERE school_id={$sid} AND class_group='6-8' ORDER BY year DESC, month DESC LIMIT 1")->fetchColumn();
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf($_POST['csrf'] ?? null)) {
    $taster_name = trim((string) ($_POST['taster_name'] ?? ''));
    $taste_rating = trim((string) ($_POST['taste_rating'] ?? 'उत्कृष्ट'));
    $tasting_time = trim((string) ($_POST['tasting_time'] ?? '12:30'));
    $distribution_time = trim((string) ($_POST['distribution_time'] ?? '13:00'));

    foreach (['1-5', '6-8'] as $group) {
        $menu_id = (int) ($_POST['menu_id'][$group] ?? 0);
        $benef = max(0, (int) ($_POST['beneficiaries'][$group] ?? 0));
        $cooked = isset($_POST['cooked'][$group]) ? 1 : 0;
        $holiday = isset($_POST['holiday'][$group]) ? 1 : 0;
        $remarks = trim((string) ($_POST['remarks'][$group] ?? ''));
        $pat_count = $group === '6-8' ? $pat_6to8 : $pat_1to5;

        // Auto set cooked=0 if holiday
        if ($holiday) $cooked = 0;

        $p = $pdo->prepare('REPLACE INTO mdm_daily (school_id, date, class_group, menu_id, beneficiaries, cooked, holiday, remarks, locked, taster_name, taste_rating, tasting_time, distribution_time, pat_count) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, COALESCE((SELECT locked FROM mdm_daily WHERE school_id=? AND date=? AND class_group=?), 0), ?, ?, ?, ?, ?)');
        $p->execute([
            $sid, $date, $group, $menu_id ?: null, $benef, $cooked, $holiday, $remarks,
            $sid, $date, $group,
            $taster_name, $taste_rating, $tasting_time, $distribution_time, $pat_count
        ]);
    }

    // Sync to mdm_entries for legacy reports
    $days = $pdo->prepare('SELECT d.*, m.name as mname FROM mdm_daily d LEFT JOIN mdm_menus m ON m.id=d.menu_id WHERE d.school_id=? AND d.date=?');
    $days->execute([$sid, $date]);
    $old = $pdo->prepare('REPLACE INTO mdm_entries (school_id, date, class_group, present_count, rice_kg, dal_kg, oil_kg, menu, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($days->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $cons = mdm_consumption($pdo, (int)$d['menu_id'], $d['class_group'], (int)$d['beneficiaries']);
        $old->execute([
            $sid, $date, $d['class_group'], $d['beneficiaries'],
            (float)($cons['तांदूळ']['qty'] ?? 0),
            (float)($cons['मूगडाळ']['qty'] ?? ($cons['तूरडाळ']['qty'] ?? 0)),
            (float)($cons['तेल']['qty'] ?? 0),
            $d['mname'] ?? '',
            $d['remarks'] ?? ''
        ]);
    }

    flash_set('success', 'दैनिक आहार व चव नोंद यशस्वीरित्या जतन केली.');
    header('Location: ' . url('modules/mdm/daily/entry.php?date=' . urlencode($date)));
    exit;
}

// Fetch menus
$menus_stmt = $pdo->prepare('SELECT * FROM mdm_menus WHERE school_id=? AND active=1 ORDER BY weekday, id');
$menus_stmt->execute([$sid]);
$menus = $menus_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing daily records
$q = $pdo->prepare('SELECT * FROM mdm_daily WHERE school_id=? AND date=?');
$q->execute([$sid, $date]);
$existing = [];
$sample_taste = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $existing[$r['class_group']] = $r;
    if (!empty($r['taster_name'])) $sample_taste = $r;
}

$default_taster = $sample_taste['taster_name'] ?? '';
$default_rating = $sample_taste['taste_rating'] ?? 'उत्कृष्ट';
$default_ttime = $sample_taste['tasting_time'] ?? '12:30';
$default_dtime = $sample_taste['distribution_time'] ?? '13:00';

$marathi_day = mdm_marathi_day($date);

mdm_layout_start('दैनिक पोषण आहार नोंद — पोषण आहार');
?>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-xl sm:text-2xl font-bold text-navy">दैनिक पोषण आहार व लाभार्थी नोंद</h2>
        <p class="text-xs sm:text-sm text-slate-500">दररोजची विद्यार्थी उपस्थिती, शिजवलेला मेनू व चव नोंदणी</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= e(url('modules/mdm/menus/list.php')) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-teal text-white px-3.5 py-2 font-semibold hover:bg-teal/90 transition shadow-sm text-xs sm:text-sm">
            <span>🍲</span> मेनू यादी / नवीन जोडा →
        </a>
        <a href="<?= e(url('modules/mdm/daily/calendar.php')) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-navy text-white px-3.5 py-2 font-semibold hover:bg-navy/90 transition shadow-sm text-xs sm:text-sm">
            <span>📅</span> दिनदर्शिका
        </a>
    </div>
</div>

<!-- Date Selection Bar -->
<div class="bg-white border rounded-2xl p-4 mb-5 shadow-sm no-print">
    <form method="get" class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <label class="text-xs font-bold text-slate-700">नोंद दिनांक:</label>
            <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm font-bold text-navy bg-slate-50">
            <span class="px-3 py-1 rounded-lg bg-gold/20 text-navy font-bold text-xs">
                वार: <?= e($marathi_day) ?>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <a href="?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-bold hover:bg-slate-50">
                ← मागील दिवस
            </a>
            <a href="?date=<?= date('Y-m-d') ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200">
                आज
            </a>
            <a href="?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-bold hover:bg-slate-50">
                पुढील दिवस →
            </a>
        </div>
    </form>
</div>

<!-- Main Entry Form -->
<form method="post" id="dailyForm" class="space-y-6">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="date" value="<?= e($date) ?>">

    <!-- Class Groups Entry (1-5 and 6-8) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        
        <?php foreach (['1-5' => 'इयत्ता १ ली ते ५ वी (प्राथमिक)', '6-8' => 'इयत्ता ६ वी ते ८ वी (उच्च प्राथमिक)'] as $group => $label): 
            $r = $existing[$group] ?? [];
            $pat = $group === '6-8' ? $pat_6to8 : $pat_1to5;
            $ben_val = isset($r['beneficiaries']) ? (int)$r['beneficiaries'] : $pat;
            $is_cooked = !empty($r['cooked']) || !isset($r['cooked']);
            $is_holiday = !empty($r['holiday']);
            $menu_id_val = (int)($r['menu_id'] ?? 0);
            
            // Auto match weekday menu if not set
            if ($menu_id_val === 0 && !empty($menus)) {
                foreach ($menus as $m) {
                    if ((int)$m['weekday'] === $weekday_num) {
                        $menu_id_val = (int)$m['id'];
                        break;
                    }
                }
            }
        ?>
        <div class="bg-white border rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col justify-between" data-group="<?= $group ?>">
            <div>
                <div class="flex items-center justify-between pb-3 border-b mb-4">
                    <div>
                        <span class="px-2.5 py-0.5 rounded-full <?= $group === '1-5' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?> text-xs font-bold">
                            <?= $group === '1-5' ? 'इ. १ ते ५' : 'इ. ६ ते ८' ?>
                        </span>
                        <h3 class="text-base font-bold text-navy mt-1"><?= e($label) ?></h3>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-500">शाळा पट: <strong class="text-navy text-sm font-black"><?= $pat ?></strong></span>
                    </div>
                </div>

                <!-- Beneficiaries Input -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold text-slate-700">प्रत्यक्ष लाभार्थी संख्या</label>
                        <?php if ($pat > 0): ?>
                            <button type="button" onclick="fillPat('<?= $group ?>', <?= $pat ?>)" class="text-[11px] text-teal font-bold hover:underline">
                                सर्व पट भरा (<?= $pat ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                    <input type="number" min="0" max="5000" required name="beneficiaries[<?= $group ?>]" id="ben_<?= $group ?>" value="<?= $ben_val ?>" 
                           class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xl font-bold text-navy focus:border-teal focus:ring-2 focus:ring-teal/20"
                           oninput="updateCalc('<?= $group ?>')">
                </div>

                <!-- Menu Dropdown -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold text-slate-700">आजचा मेनू</label>
                        <a href="<?= e(url('modules/mdm/menus/list.php')) ?>" target="_blank" class="text-[11px] text-teal font-bold hover:underline">
                            + मेनू जोडा / बदला
                        </a>
                    </div>
                    <select name="menu_id[<?= $group ?>]" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50 focus:bg-white">
                        <option value="0">— मेनू निवडा —</option>
                        <?php foreach ($menus as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $menu_id_val === (int)$m['id'] ? 'selected' : '' ?>>
                                <?= e($m['name']) ?> (वार: <?= e($m['weekday'] ? mdm_marathi_day(date('Y-m-d', strtotime("Sunday +{$m['weekday']} days"))) : '-') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Checkboxes: Cooked & Holiday -->
                <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl mb-4 text-xs font-bold text-slate-700">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="cooked[<?= $group ?>]" value="1" <?= $is_cooked ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500">
                        <span>आहार शिजवला</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="holiday[<?= $group ?>]" value="1" <?= $is_holiday ? 'checked' : '' ?> class="rounded text-red-600 focus:ring-red-500">
                        <span>शाळेस सुट्टी</span>
                    </label>
                </div>

                <!-- Remarks -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">शेरा (ऐच्छिक)</label>
                    <input type="text" name="remarks[<?= $group ?>]" value="<?= e((string)($r['remarks'] ?? '')) ?>" placeholder="उदा. नियमित पोषण आहार वाटप" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs bg-white">
                </div>
            </div>

            <!-- Live Calculation Preview -->
            <div class="mt-4 pt-3 border-t text-[11px] text-slate-600 space-y-1 bg-slate-50/70 p-3 rounded-xl">
                <div class="flex justify-between">
                    <span>तांदूळ वापर:</span>
                    <strong class="text-navy calc-rice-<?= $group ?>">-</strong>
                </div>
                <div class="flex justify-between">
                    <span>डाळ / कडधान्य:</span>
                    <strong class="text-navy calc-dal-<?= $group ?>">-</strong>
                </div>
                <div class="flex justify-between">
                    <span>भाजीपाला व इंधन खर्च:</span>
                    <strong class="text-emerald-700 calc-grant-<?= $group ?>">-</strong>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>

    <!-- Taste Register Detail Section (चव नोंदवही) -->
    <div class="bg-white border rounded-2xl p-5 sm:p-6 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b mb-4">
            <div>
                <h3 class="text-base font-bold text-navy flex items-center gap-2">
                    <span>👅</span> आजची चव नोंद (चव रजिस्टर तपशील)
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">आहार वाटपापूर्वी चव घेतल्याची वेळ व अभिप्राय नोंदवा</p>
            </div>
            <a href="<?= e(url('modules/mdm/reports/chav_nondavahi.php')) ?>" class="text-xs text-teal font-bold hover:underline">
                संपूर्ण चव रजिस्टर पहा →
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div>
                <label class="block font-bold text-slate-700 mb-1">चव घेणाऱ्याचे नाव</label>
                <input type="text" name="taster_name" value="<?= e($default_taster) ?>" placeholder="उदा. श्री. संतोष कांबळे (शिक्षक)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-slate-50 focus:bg-white font-medium">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">चवीचा दर्जा / अभिप्राय</label>
                <select name="taste_rating" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-slate-50 focus:bg-white font-bold text-navy">
                    <option value="उत्कृष्ट" <?= $default_rating === 'उत्कृष्ट' ? 'selected' : '' ?>>उत्कृष्ट (Excellent)</option>
                    <option value="चांगला" <?= $default_rating === 'चांगला' ? 'selected' : '' ?>>चांगला (Good)</option>
                    <option value="समाधानकारक" <?= $default_rating === 'समाधानकारक' ? 'selected' : '' ?>>समाधानकारक (Satisfactory)</option>
                </select>
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">चव घेतल्याची वेळ</label>
                <input type="text" name="tasting_time" value="<?= e($default_ttime) ?>" placeholder="12:30 PM" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-slate-50 focus:bg-white">
            </div>
            <div>
                <label class="block font-bold text-slate-700 mb-1">विद्यार्थ्यांना वाटप वेळ</label>
                <input type="text" name="distribution_time" value="<?= e($default_dtime) ?>" placeholder="01:00 PM" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-slate-50 focus:bg-white">
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div class="flex justify-end">
        <button type="submit" class="w-full sm:w-auto rounded-xl bg-gold text-navy font-bold px-10 py-3.5 hover:brightness-105 transition shadow-lg text-base flex items-center justify-center gap-2">
            <span>💾</span> दैनिक आहार नोंद जतन करा
        </button>
    </div>
</form>

<script>
function fillPat(group, pat) {
    const input = document.getElementById('ben_' + group);
    if (input) {
        input.value = pat;
        updateCalc(group);
    }
}

function updateCalc(group) {
    const b = parseInt(document.getElementById('ben_' + group).value) || 0;
    const riceNorm = group === '6-8' ? 0.150 : 0.100;
    const dalNorm = group === '6-8' ? 0.030 : 0.020;
    const rate = group === '6-8' ? 3.88 : 2.59;

    const rice = (b * riceNorm).toFixed(3) + ' kg';
    const dal = (b * dalNorm).toFixed(3) + ' kg';
    const grant = '₹ ' + (b * rate).toFixed(2);

    const rEl = document.querySelector('.calc-rice-' + group);
    const dEl = document.querySelector('.calc-dal-' + group);
    const gEl = document.querySelector('.calc-grant-' + group);

    if (rEl) rEl.textContent = rice;
    if (dEl) dEl.textContent = dal;
    if (gEl) gEl.textContent = grant;
}

// Initial calculation
updateCalc('1-5');
updateCalc('6-8');
</script>

<?php layout_end(); ?>
