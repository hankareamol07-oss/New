<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/mdm_helpers.php';
require_once __DIR__ . '/../../includes/module_access.php';
$u = require_role(['admin', 'principal', 'clerk', 'teacher']);
require_module_access('mdm');
$sid = (int) $u['school_id'];
$pdo = db();
mdm_ensure_seeded($pdo, $sid);

$today = date('Y-m-d');
$daily = $pdo->prepare('SELECT * FROM mdm_daily WHERE school_id=? AND date=? ORDER BY class_group');
$daily->execute([$sid, $today]);
$rows = $daily->fetchAll();

// Enrolled pat
$patRows = [];
foreach (['1-5', '6-8'] as $g) {
    $minStd = $g === '1-5' ? 1 : 6;
    $maxStd = $g === '1-5' ? 5 : 8;
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM students s JOIN classes c ON c.id=s.class_id WHERE s.school_id=? AND s.status="active" AND c.std BETWEEN ? AND ?');
        $st->execute([$sid, $minStd, $maxStd]);
        $patRows[$g] = (int)$st->fetchColumn();
    } catch (Throwable $e) {
        $patRows[$g] = 0;
    }
}

$curMonth = (int)date('n');
$curYear = (int)date('Y');
$monthName = mdm_marathi_months()[$curMonth] ?? date('M');

mdm_layout_start('डॅशबोर्ड — प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना');
?>
<div class="mb-6 bg-gradient-to-r from-[#0B3D2E] via-[#0F4F3B] to-[#167A5B] text-white p-6 rounded-3xl shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <span class="inline-block bg-white/20 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-2">PM POSHAN SCHEME</span>
      <h1 class="text-2xl sm:text-3xl font-black">प्रधानमंत्री पोषण शक्ती निर्माण आहार योजना</h1>
      <p class="text-orange-100 text-sm mt-1">इयत्ता १ ली ते ५ वी व ६ वी ते ८ वी दैनिक खर्च, धान्य साठा व शासकीय अहवाल नोंदवही</p>
    </div>
    <div class="text-right">
      <span class="text-xs text-orange-200">चालू महिना</span>
      <div class="text-2xl font-black"><?= e($monthName) ?> <?= e((string)$curYear) ?></div>
    </div>
  </div>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white border rounded-2xl p-5 shadow-sm">
    <span class="text-slate-500 text-sm">पट संख्या (इ. १ ते ५)</span>
    <strong class="block text-3xl text-navy mt-1"><?= (int)($patRows['1-5'] ?? 0) ?></strong>
    <span class="text-xs text-slate-400 mt-1 block">प्रती विद्यार्थी तांदूळ: १०० ग्रॅम</span>
  </div>
  <div class="bg-white border rounded-2xl p-5 shadow-sm">
    <span class="text-slate-500 text-sm">पट संख्या (इ. ६ ते ८)</span>
    <strong class="block text-3xl text-navy mt-1"><?= (int)($patRows['6-8'] ?? 0) ?></strong>
    <span class="text-xs text-slate-400 mt-1 block">प्रती विद्यार्थी तांदूळ: १५० ग्रॅम</span>
  </div>
  <div class="bg-white border rounded-2xl p-5 shadow-sm">
    <span class="text-slate-500 text-sm">आजची नोंद (<?= date('d/m/Y') ?>)</span>
    <strong class="block text-3xl <?= count($rows) >= 2 ? 'text-teal' : 'text-amber-500' ?> mt-1"><?= count($rows) ?> / २ गट</strong>
    <span class="text-xs <?= count($rows) >= 2 ? 'text-teal' : 'text-amber-600 font-bold' ?> mt-1 block">
      <?= count($rows) >= 2 ? 'दोन्ही गटांची नोंद पूर्ण' : 'आजची नोंद बाकी आहे' ?>
    </span>
  </div>
  <div class="bg-white border rounded-2xl p-5 shadow-sm">
    <span class="text-slate-500 text-sm">अनुदान दर / विद्यार्थी</span>
    <strong class="block text-2xl text-amber-600 mt-1">₹ २.५९ / ₹ ३.८८</strong>
    <span class="text-xs text-slate-400 mt-1 block">भाजीपाला ३८% · इंधन ३४% · पूरक २८%</span>
  </div>
</div>

<?php if (count($rows) < 2): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 flex flex-wrap items-center justify-between gap-3 shadow-sm">
  <div class="flex items-center gap-3">
    <span class="text-2xl">⚠️</span>
    <div>
      <h3 class="font-bold text-amber-900">आजची पोषण आहार नोंद अपूर्ण आहे</h3>
      <p class="text-amber-700 text-sm">आजच्या लाभार्थ्यांची ताटांची संख्या व मेनू त्वरित नोंदवा.</p>
    </div>
  </div>
  <a class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-5 py-2.5 rounded-xl shadow-sm transition" href="<?= url('modules/mdm/daily/entry.php') ?>?date=<?= $today ?>">आजची नोंद करा →</a>
</div>
<?php endif; ?>

<h2 class="text-lg font-bold text-navy mb-3">दैनिक व्यवस्थापन व साठा</h2>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <a href="<?= url('modules/mdm/daily/entry.php') ?>" class="bg-white border hover:border-orange-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <div class="text-2xl mb-2">📝</div>
    <h3 class="text-base font-bold text-navy">दैनिक नोंद (Daily Entry)</h3>
    <p class="text-slate-500 text-xs mt-1">दररोजचे लाभार्थी, मेनू व शिजवलेला आहार नोंदवा.</p>
  </a>
  <a href="<?= url('modules/mdm/daily/calendar.php') ?>" class="bg-white border hover:border-orange-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <div class="text-2xl mb-2">📅</div>
    <h3 class="text-base font-bold text-navy">मासिक दिनदर्शिका (Calendar)</h3>
    <p class="text-slate-500 text-xs mt-1">संपूर्ण महिन्याचे दिवसनिहाय लाभार्थी विहंगावलोकन.</p>
  </a>
  <a href="<?= url('modules/mdm/balance/receipt.php') ?>" class="bg-white border hover:border-orange-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <div class="text-2xl mb-2">📦</div>
    <h3 class="text-base font-bold text-navy">धान्य साठा पावती (Stock Receipts)</h3>
    <p class="text-slate-500 text-xs mt-1">२१ वस्तूंची प्रारंभिक शिल्लक, प्राप्त माल व उसनवार नोंद.</p>
  </a>
  <a href="<?= url('modules/mdm/bmi/entry.php') ?>" class="bg-white border hover:border-orange-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <div class="text-2xl mb-2">⚖️</div>
    <h3 class="text-base font-bold text-navy">BMI नोंद (Height & Weight)</h3>
    <p class="text-slate-500 text-xs mt-1">जून, सप्टेंबर, डिसेंबर, मार्च त्रैमासिक वजन-उंची नोंद.</p>
  </a>
  <a href="<?= url('modules/mdm/bmi/import.php') ?>" class="bg-white border hover:border-teal-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <div class="text-2xl mb-2">📥</div>
    <h3 class="text-base font-bold text-navy">विद्यार्थी आयात (Import Students)</h3>
    <p class="text-slate-500 text-xs mt-1">शाळा पटामधून ऐच्छिक आयात किंवा स्वतंत्र विद्यार्थी यादी व्यवस्थापन.</p>
  </a>
</div>

<h2 class="text-lg font-bold text-navy mb-3">शासकीय नोंदवह्या व प्रपत्रे (Excel Formats)</h2>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
  <a href="<?= url('modules/mdm/reports/prapatr_b.php') ?>" class="bg-white border hover:border-teal-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <span class="inline-block bg-teal-50 text-teal-700 text-xs font-bold px-2 py-0.5 rounded mb-2">प्रपत्र ब</span>
    <h3 class="text-base font-bold text-navy">मासिक अहवाल (२ प्रतीत)</h3>
    <p class="text-slate-500 text-xs mt-1">केंद्रप्रमुखांना द्यावयाचा २१ धान्यादी वस्तूंचा मासिक हिशोब व अनुदान विभाजन.</p>
  </a>
  <a href="<?= url('modules/mdm/reports/tandul_monthly.php') ?>" class="bg-white border hover:border-teal-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <span class="inline-block bg-blue-50 text-blue-700 text-xs font-bold px-2 py-0.5 rounded mb-2">नोंदवही भाग - १</span>
    <h3 class="text-base font-bold text-navy">तांदूळ हिशोब नोंदवही</h3>
    <p class="text-slate-500 text-xs mt-1">दैनिक पट, शिल्लक, प्राप्त, उसनवार व शिजवलेला तांदूळ हिशोब.</p>
  </a>
  <a href="<?= url('modules/mdm/reports/nodvahi2.php') ?>" class="bg-white border hover:border-teal-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <span class="inline-block bg-purple-50 text-purple-700 text-xs font-bold px-2 py-0.5 rounded mb-2">नोंदवही भाग - २</span>
    <h3 class="text-base font-bold text-navy">दैनंदिन धान्य व खर्च नोंदवही</h3>
    <p class="text-slate-500 text-xs mt-1">२१ धान्यादी वस्तूंचा दिवसनिहाय प्रत्यक्ष वापर व खर्च तक्ता.</p>
  </a>
  <a href="<?= url('modules/mdm/reports/chav_nondavahi.php') ?>" class="bg-white border hover:border-teal-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <span class="inline-block bg-emerald-50 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded mb-2">चव रजिस्टर</span>
    <h3 class="text-base font-bold text-navy">शाळा स्तरावर चव नोंदवही</h3>
    <p class="text-slate-500 text-xs mt-1">चव घेतल्याची वेळ, वाटप वेळ, मेनू, चव घेणाऱ्याचे नाव व अभिप्राय.</p>
  </a>
  <a href="<?= url('modules/mdm/bmi/report.php') ?>" class="bg-white border hover:border-teal-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <span class="inline-block bg-indigo-50 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded mb-2">BMI नोंदवही</span>
    <h3 class="text-base font-bold text-navy">विद्यार्थी वजन-उंची नोंदवही</h3>
    <p class="text-slate-500 text-xs mt-1">४ सत्रांची (जून, सप्टेंबर, डिसेंबर, मार्च) विद्यार्थीनिहाय BMI नोंदवही.</p>
  </a>
  <a href="<?= url('modules/mdm/reports/annual_cert.php') ?>" class="bg-white border hover:border-teal-500 rounded-2xl p-5 transition shadow-sm hover:shadow">
    <span class="inline-block bg-rose-50 text-rose-700 text-xs font-bold px-2 py-0.5 rounded mb-2">वार्षिक प्रमाणपत्र</span>
    <h3 class="text-base font-bold text-navy">वार्षिक उपयोगिता प्रमाणपत्र</h3>
    <p class="text-slate-500 text-xs mt-1">३१ मार्च अखेर २१ धान्यादी वस्तूंचे वार्षिक उपयोगिता प्रमाणपत्र (UC).</p>
  </a>
</div>

<div class="text-center pt-2">
  <a href="<?= url('modules/mdm/reports/index.php') ?>" class="text-sm font-bold text-teal hover:underline">सर्व अहवाल व नोंदवह्या पहा (View All Reports) →</a>
</div>
<?php layout_end(); ?>
