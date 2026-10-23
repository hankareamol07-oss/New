<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
require_once __DIR__ . '/../../lib/print_layout.php';

$u = require_login();
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

$school = school_row($sid) ?: [];
$school_name = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? 'प्राथमिक शाळा'));
$udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));
$taluka = (string) ($school['taluka'] ?? 'खंडाळा');
$district = (string) ($school['district'] ?? 'सातारा');
$kendra = (string) ($school['kendra'] ?? 'केंद्र शाळा');

$selected_std = (string) ($_GET['std'] ?? 'all');
$selected_year = (int) ($_GET['year'] ?? date('Y'));

// Fetch MDM Students (Isolated)
$sql = "SELECT s.id, s.name, s.std, s.division, s.gender, s.roll_no 
        FROM mdm_students s 
        WHERE s.school_id = ?";
$params = [$sid];
if ($selected_std !== 'all' && $selected_std !== '') {
    $sql .= " AND s.std = ?";
    $params[] = $selected_std;
}
$sql .= " ORDER BY CAST(s.std AS UNSIGNED), CAST(s.roll_no AS UNSIGNED), s.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all BMI records for this school
$bmi_records = $pdo->prepare("SELECT student_id, quarter, measured_on, height_cm, weight_kg, bmi, category FROM mdm_bmi WHERE school_id = ?");
$bmi_records->execute([$sid]);
$student_bmis = [];
foreach ($bmi_records->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $s_id = (int)$r['student_id'];
    $q = (int)($r['quarter'] ?: 0);
    if ($q < 1 || $q > 4) {
        $m = (int)date('n', strtotime($r['measured_on']));
        if ($m >= 5 && $m <= 7) $q = 1;
        elseif ($m >= 8 && $m <= 10) $q = 2;
        elseif ($m >= 11 || $m == 1) $q = 3;
        else $q = 4;
    }
    $student_bmis[$s_id][$q] = $r;
}

// Check Excel/CSV export
if (isset($_GET['excel'])) {
    $filename = 'MDM_BMI_Register_4_Quarters_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($out, ['प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना - शाळा स्तरावर ठेवायची BMI नोंदवही (४ त्रैमासिक)']);
    fputcsv($out, ['शाळेचे नाव:', $school_name, 'UDISE:', $udise, 'केंद्र:', $kendra, 'तालुका:', $taluka, 'जिल्हा:', $district]);
    fputcsv($out, [
        'अ.क्र.', 'विद्यार्थ्याचे नाव', 'इयत्ता', 'लिंग',
        'जून (वजन kg)', 'जून (उंची cm)', 'जून (BMI)',
        'सप्टेंबर (वजन kg)', 'सप्टेंबर (उंची cm)', 'सप्टेंबर (BMI)',
        'डिसेंबर (वजन kg)', 'डिसेंबर (उंची cm)', 'डिसेंबर (BMI)',
        'मार्च (वजन kg)', 'मार्च (उंची cm)', 'मार्च (BMI)',
        'शेवटची वर्गवारी'
    ]);

    foreach ($students as $idx => $s) {
        $sid_val = (int)$s['id'];
        $sname = $s['name'];
        $std_val = $s['std'];
        $gender_val = ($s['gender'] ?? '') === 'F' ? 'स्त्री' : 'पुरुष';

        $q1 = $student_bmis[$sid_val][1] ?? [];
        $q2 = $student_bmis[$sid_val][2] ?? [];
        $q3 = $student_bmis[$sid_val][3] ?? [];
        $q4 = $student_bmis[$sid_val][4] ?? [];

        $last_cat = $q4['category'] ?? ($q3['category'] ?? ($q2['category'] ?? ($q1['category'] ?? '—')));

        fputcsv($out, [
            $idx + 1, $sname, $std_val, $gender_val,
            $q1['weight_kg'] ?? '', $q1['height_cm'] ?? '', $q1['bmi'] ?? '',
            $q2['weight_kg'] ?? '', $q2['height_cm'] ?? '', $q2['bmi'] ?? '',
            $q3['weight_kg'] ?? '', $q3['height_cm'] ?? '', $q3['bmi'] ?? '',
            $q4['weight_kg'] ?? '', $q4['height_cm'] ?? '', $q4['bmi'] ?? '',
            $last_cat
        ]);
    }
    fclose($out);
    exit;
}

// Calculate summary stats
$stat_total = count($students);
$stat_normal = 0;
$stat_sam = 0;
$stat_mam = 0;
$stat_overweight = 0;
$stat_measured = 0;

foreach ($students as $s) {
    $sid_val = (int)$s['id'];
    $records = $student_bmis[$sid_val] ?? [];
    if (!empty($records)) {
        $stat_measured++;
        $latest = $records[4] ?? ($records[3] ?? ($records[2] ?? ($records[1] ?? [])));
        $bmi_f = (float)($latest['bmi'] ?? 0);
        if ($bmi_f > 0) {
            if ($bmi_f < 16.0) $stat_sam++;
            elseif ($bmi_f < 18.5) $stat_mam++;
            elseif ($bmi_f <= 24.9) $stat_normal++;
            else $stat_overweight++;
        }
    }
}

$std_list = ['1', '2', '3', '4', '5', '6', '7', '8'];

mdm_layout_start('वार्षिक BMI नोंदवही (४ त्रैमासिक) — पोषण आहार');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4 no-print">
    <div>
        <h2 class="text-2xl font-bold text-navy">शाळा स्तरावर ठेवायची BMI नोंदवही (४ त्रैमासिक)</h2>
        <p class="text-sm text-slate-500">प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना (इ. १ ते ५ व ६ ते ८)</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= e(url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-2 rounded-xl bg-teal text-white px-4 py-2 font-semibold hover:bg-teal/90 transition text-sm">
            <span>📥</span> विद्यार्थी आयात करा
        </a>
        <a href="<?= e(url('modules/mdm/bmi/entry.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-2 rounded-xl bg-navy text-white px-4 py-2 font-semibold hover:bg-navy/90 transition text-sm">
            <span>✏️</span> नवीन BMI नोंद
        </a>
        <a href="?std=<?= urlencode($selected_std) ?>&excel=1" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2 font-semibold hover:bg-emerald-700 transition text-sm">
            <span>📥</span> Excel / CSV
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-gold text-navy font-bold px-4 py-2 hover:brightness-105 transition text-sm">
            <span>🖨️</span> प्रिंट (A4 आडवे)
        </button>
    </div>
</div>

<!-- Filters (No Print) -->
<form method="get" class="bg-white border rounded-2xl p-4 mb-6 shadow-sm no-print flex flex-wrap items-center gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">इयत्ता निवडा</label>
        <select name="std" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold bg-slate-50">
            <option value="all" <?= $selected_std === 'all' ? 'selected' : '' ?>>सर्व इयत्ता (१ ते ८)</option>
            <?php foreach ($std_list as $st): ?>
                <option value="<?= $st ?>" <?= $selected_std === $st ? 'selected' : '' ?>>इयत्ता <?= $st ?> ली</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex-1 text-right text-xs text-slate-500 self-end">
        <span>नोंद झालेले विद्यार्थी: <strong class="text-teal font-bold"><?= $stat_measured ?></strong> / <?= $stat_total ?></span>
    </div>
</form>

<?php if (empty($students)): ?>
    <div class="bg-white border-2 border-dashed border-teal/40 rounded-2xl p-8 text-center shadow-sm">
        <h3 class="text-lg font-bold text-navy">या इयत्तेत MDM साठी कोणतेही विद्यार्थी जोडलेले नाहीत</h3>
        <p class="text-xs text-slate-500 max-w-lg mx-auto mt-2">
            MDM विद्यार्थी यादी स्वतंत्र ठेवण्यात आली आहे. इतर मॉड्युलमधील सर्व विद्यार्थी आपोआप येथे आणले जात नाहीत. 
            तुम्ही आवश्यक विद्यार्थी आयात करू शकता.
        </p>
        <div class="mt-5">
            <a href="<?= e(url('modules/mdm/bmi/import.php?std=' . urlencode($selected_std))) ?>" class="inline-flex items-center gap-2 rounded-xl bg-teal text-white font-bold px-6 py-2.5 hover:bg-teal/90 transition text-sm">
                <span>📥</span> शाळा पटामधून विद्यार्थी आयात करा →
            </a>
        </div>
    </div>
<?php else: ?>

<!-- Stats Cards (No Print) -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6 no-print">
    <div class="bg-white border border-green-200 rounded-xl p-4 shadow-sm">
        <p class="text-xs text-green-700 font-bold uppercase">सामान्य / निरोगी (Normal)</p>
        <p class="text-2xl font-black text-green-800 mt-1"><?= $stat_normal ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $stat_measured > 0 ? round(($stat_normal / $stat_measured) * 100, 1) : 0 ?>% प्रमाण</p>
    </div>
    <div class="bg-white border border-amber-200 rounded-xl p-4 shadow-sm">
        <p class="text-xs text-amber-700 font-bold uppercase">मध्यम कुपोषित (MAM)</p>
        <p class="text-2xl font-black text-amber-800 mt-1"><?= $stat_mam ?></p>
        <p class="text-xs text-slate-400 mt-1">लक्ष देण्याची गरज</p>
    </div>
    <div class="bg-white border border-red-200 rounded-xl p-4 shadow-sm">
        <p class="text-xs text-red-700 font-bold uppercase">तीव्र कुपोषित (SAM)</p>
        <p class="text-2xl font-black text-red-800 mt-1"><?= $stat_sam ?></p>
        <p class="text-xs text-slate-400 mt-1">आरोग्य तपासणी आवश्यक</p>
    </div>
    <div class="bg-white border border-purple-200 rounded-xl p-4 shadow-sm">
        <p class="text-xs text-purple-700 font-bold uppercase">जास्त वजन / लठ्ठ (Overweight)</p>
        <p class="text-2xl font-black text-purple-800 mt-1"><?= $stat_overweight ?></p>
        <p class="text-xs text-slate-400 mt-1">संतुलित आहार सल्ला</p>
    </div>
</div>

<!-- Print & Main Table -->
<div class="bg-white border rounded-2xl p-6 shadow-sm overflow-x-auto print:border-none print:p-0 print:shadow-none">
    
    <div class="text-center mb-6 pb-4 border-b">
        <h3 class="text-xl font-bold text-navy">प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना</h3>
        <h4 class="text-base font-semibold text-slate-700 mt-1">शाळा स्तरावर ठेवायची नोंदवही - ४ त्रैमासिक विद्यार्थी BMI अहवाल</h4>
        <div class="flex flex-wrap justify-between text-xs text-slate-600 mt-3 px-2">
            <span><strong>शाळेचे नाव:</strong> <?= e($school_name) ?></span>
            <span><strong>UDISE:</strong> <?= e($udise) ?></span>
            <span><strong>केंद्र:</strong> <?= e($kendra) ?></span>
            <span><strong>तालुका:</strong> <?= e($taluka) ?></span>
            <span><strong>जिल्हा:</strong> <?= e($district) ?></span>
        </div>
    </div>

    <table class="w-full text-xs text-center border-collapse border border-slate-400">
        <thead>
            <tr class="bg-slate-200 font-bold text-slate-800">
                <th rowspan="3" class="border border-slate-400 p-2 w-10">अ. क्र.</th>
                <th rowspan="3" class="border border-slate-400 p-2 text-left min-w-[160px]">विद्यार्थ्याचे नाव</th>
                <th rowspan="3" class="border border-slate-400 p-2 w-12">इयत्ता</th>
                <th rowspan="3" class="border border-slate-400 p-2 w-12">लिंग</th>
                <th colspan="3" class="border border-slate-400 p-1 bg-blue-50">१) जून</th>
                <th colspan="3" class="border border-slate-400 p-1 bg-amber-50">२) सप्टेंबर</th>
                <th colspan="3" class="border border-slate-400 p-1 bg-green-50">३) डिसेंबर</th>
                <th colspan="3" class="border border-slate-400 p-1 bg-purple-50">४) मार्च</th>
                <th rowspan="3" class="border border-slate-400 p-2 min-w-[110px]">आरोग्य वर्गवारी / शेरा</th>
            </tr>
            <tr class="bg-slate-100 font-semibold text-slate-700">
                <th class="border border-slate-400 p-1">वजन</th>
                <th class="border border-slate-400 p-1">उंची</th>
                <th class="border border-slate-400 p-1 font-bold">BMI</th>
                <th class="border border-slate-400 p-1">वजन</th>
                <th class="border border-slate-400 p-1">उंची</th>
                <th class="border border-slate-400 p-1 font-bold">BMI</th>
                <th class="border border-slate-400 p-1">वजन</th>
                <th class="border border-slate-400 p-1">उंची</th>
                <th class="border border-slate-400 p-1 font-bold">BMI</th>
                <th class="border border-slate-400 p-1">वजन</th>
                <th class="border border-slate-400 p-1">उंची</th>
                <th class="border border-slate-400 p-1 font-bold">BMI</th>
            </tr>
            <tr class="bg-slate-50 text-[10px] text-slate-500">
                <th class="border border-slate-400 p-0.5">Kg</th>
                <th class="border border-slate-400 p-0.5">Cm</th>
                <th class="border border-slate-400 p-0.5"></th>
                <th class="border border-slate-400 p-0.5">Kg</th>
                <th class="border border-slate-400 p-0.5">Cm</th>
                <th class="border border-slate-400 p-0.5"></th>
                <th class="border border-slate-400 p-0.5">Kg</th>
                <th class="border border-slate-400 p-0.5">Cm</th>
                <th class="border border-slate-400 p-0.5"></th>
                <th class="border border-slate-400 p-0.5">Kg</th>
                <th class="border border-slate-400 p-0.5">Cm</th>
                <th class="border border-slate-400 p-0.5"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $idx => $s): 
                $sid_val = (int)$s['id'];
                $sname = $s['name'];
                $std_val = $s['std'];
                $gender_val = ($s['gender'] ?? '') === 'F' ? 'स्त्री' : 'पुरुष';

                $q1 = $student_bmis[$sid_val][1] ?? [];
                $q2 = $student_bmis[$sid_val][2] ?? [];
                $q3 = $student_bmis[$sid_val][3] ?? [];
                $q4 = $student_bmis[$sid_val][4] ?? [];

                $latest_cat = $q4['category'] ?? ($q3['category'] ?? ($q2['category'] ?? ($q1['category'] ?? '—')));
            ?>
            <tr class="hover:bg-slate-50">
                <td class="border border-slate-400 p-1 font-semibold text-slate-600"><?= $idx + 1 ?></td>
                <td class="border border-slate-400 p-1 text-left font-semibold text-slate-800"><?= e($sname) ?></td>
                <td class="border border-slate-400 p-1"><?= e((string)$std_val) ?></td>
                <td class="border border-slate-400 p-1"><?= e($gender_val) ?></td>

                <!-- Q1 June -->
                <td class="border border-slate-400 p-1"><?= !empty($q1['weight_kg']) ? number_format((float)$q1['weight_kg'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1"><?= !empty($q1['height_cm']) ? number_format((float)$q1['height_cm'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1 font-bold text-navy"><?= !empty($q1['bmi']) ? number_format((float)$q1['bmi'], 2) : '-' ?></td>

                <!-- Q2 Sept -->
                <td class="border border-slate-400 p-1"><?= !empty($q2['weight_kg']) ? number_format((float)$q2['weight_kg'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1"><?= !empty($q2['height_cm']) ? number_format((float)$q2['height_cm'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1 font-bold text-navy"><?= !empty($q2['bmi']) ? number_format((float)$q2['bmi'], 2) : '-' ?></td>

                <!-- Q3 Dec -->
                <td class="border border-slate-400 p-1"><?= !empty($q3['weight_kg']) ? number_format((float)$q3['weight_kg'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1"><?= !empty($q3['height_cm']) ? number_format((float)$q3['height_cm'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1 font-bold text-navy"><?= !empty($q3['bmi']) ? number_format((float)$q3['bmi'], 2) : '-' ?></td>

                <!-- Q4 March -->
                <td class="border border-slate-400 p-1"><?= !empty($q4['weight_kg']) ? number_format((float)$q4['weight_kg'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1"><?= !empty($q4['height_cm']) ? number_format((float)$q4['height_cm'], 1) : '-' ?></td>
                <td class="border border-slate-400 p-1 font-bold text-navy"><?= !empty($q4['bmi']) ? number_format((float)$q4['bmi'], 2) : '-' ?></td>

                <!-- Category -->
                <td class="border border-slate-400 p-1 text-[11px] font-semibold text-slate-700"><?= e($latest_cat) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-8 pt-6 flex justify-between text-xs font-bold text-slate-700 print:mt-12">
        <div class="text-center">
            <p class="h-10"></p>
            <p>शालेय पोषण आहार प्रभारी</p>
        </div>
        <div class="text-center">
            <p class="h-10"></p>
            <p>आरोग्य तपासणी अधिकारी / वैद्यकीय अधिकारी</p>
        </div>
        <div class="text-center">
            <p class="h-10"></p>
            <p>मुख्याध्यापक (स्वाक्षरी व शिक्का)</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php layout_end(); ?>
