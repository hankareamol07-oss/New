<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/mdm_helpers.php';
require_role(['admin', 'principal', 'clerk', 'teacher']);
mdm_layout_start('अहवाल व नोंदवह्या — पोषण आहार');

$reports = [
    'prapatr_b' => ['title' => 'प्रपत्र ब (मासिक अहवाल)', 'desc' => 'केंद्रप्रमुखांना दरमहा १ तारखेस द्यावयाचा २१ वस्तूंचा अधिकृत अहवाल (२ प्रतीत)'],
    'tandul_monthly' => ['title' => 'दैनिक नोंदवही भाग - १ (तांदूळ)', 'desc' => 'दिवसनिहाय पट, शिल्लक, प्राप्त, उसनवार व शिजवलेला तांदूळ हिशोब नोंदवही'],
    'nodvahi2' => ['title' => 'दैनिक नोंदवही भाग - २ (धान्य व खर्च)', 'desc' => '२१ धान्यादी वस्तू व भाजीपाल्याचा दिवसनिहाय प्रत्यक्ष वापर व खर्च नोंदवही'],
    'chav_nondavahi' => ['title' => 'चव रजिस्टर (शाळा स्तर)', 'desc' => 'चव घेतल्याची वेळ, वाटप वेळ, मेनू, चव घेणाऱ्याचे नाव व अभिप्राय नोंदवही'],
    'annual_cert' => ['title' => 'वार्षिक उपयोगिता प्रमाणपत्र (UC)', 'desc' => '३१ मार्च अखेर २१ धान्यादी वस्तूंचे वार्षिक उपयोगिता प्रमाणपत्र'],
    'annual_summary' => ['title' => 'वार्षिक सारांश (१२ महिने)', 'desc' => 'एप्रिल ते मार्च १२ महिन्यांचा महिनानिहाय तांदूळ व लाभार्थी सारांश'],
    'demand_forecast' => ['title' => 'पुढील महिन्याची धान्य मागणी', 'desc' => '२० दिवसांचा साठा राहील या नियमानुसार पुढील महिन्यासाठी मागणी पत्र'],
    'bmi_report' => ['title' => '४ त्रैमासिक BMI नोंदवही', 'desc' => 'जून, सप्टेंबर, डिसेंबर व मार्च या ४ त्रैमासिकांमधील विद्यार्थ्यांची उंची, वजन व BMI', 'href' => url('modules/mdm/bmi/report.php')],
    'karar_nama' => ['title' => 'स्वयंपाकी / मदतनीस करारनामा', 'desc' => 'शालेय पोषण आहार स्वयंपाकी व मदतनीस नियुक्ती व मानधन करार'],
    'yearly_report' => ['title' => 'वार्षिक एकत्रित अहवाल', 'desc' => 'वार्षिक एकूण धान्य वापर, खर्च व लाभार्थी एकत्रित सांख्यिकी'],
];
?>
<div class="mb-6">
  <h1 class="text-2xl font-black text-navy">शासकीय नोंदवह्या व अहवाल</h1>
  <p class="text-slate-500 text-sm mt-1">महाराष्ट्र शासन प्रधानमंत्री पोषण शक्ती निर्माण योजनेनुसार सर्व अधिकृत प्रपत्रे व नोंदवह्या</p>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($reports as $path => $r): ?>
    <a class="bg-white border hover:border-orange-500 rounded-2xl p-5 shadow-sm hover:shadow transition flex flex-col justify-between" href="<?= e($r['href'] ?? url('modules/mdm/reports/' . $path . '.php')) ?>">
      <div>
        <h3 class="font-bold text-base text-navy"><?= e($r['title']) ?></h3>
        <p class="text-slate-500 text-xs mt-2"><?= e($r['desc']) ?></p>
      </div>
      <span class="inline-flex items-center text-teal font-bold text-xs mt-4">
        नोंदवही उघडा <span class="ml-1">→</span>
      </span>
    </a>
  <?php endforeach; ?>
</div>
<?php layout_end(); ?>
