<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function module_data(string $key): array
{
    $all = [
        'gr'=>['emoji'=>'📘','title'=>'जनरल रजिस्टर (GR)','subtitle'=>'शाळेची संपूर्ण विद्यार्थी नोंद डिजिटल','features'=>['विद्यार्थी नोंदणी','दाखला व प्रमाणपत्रे','CSV Bulk Import','पालक अर्ज मंजुरी','बहु-बुक व्यवस्थापन','सुरक्षित डेटा'],'steps'=>['विद्यार्थी माहिती भरा','नोंदवही अद्ययावत करा','दाखला किंवा अहवाल मिळवा'],'price'=>'पहिले ५ विद्यार्थी मोफत'],
        'cce'=>['emoji'=>'📊','title'=>'सातत्यपूर्ण मूल्यमापन','subtitle'=>'शाळांसाठी डिजिटल गुणनोंदणी व निकालपत्रक','features'=>['गुण नोंदणी','वर्णनात्मक नोंदी','वर्गस्तर व वार्षिक निकालपत्रके','नोंदवही PDF','प्रगती पत्रक छापा','गोपनीय व सुरक्षित'],'steps'=>['भारांश सेटअप करा','गुण व नोंदी भरा','निकालपत्रक/PDF तयार करा'],'price'=>'पहिले ५ विद्यार्थी मोफत'],
        'hpc'=>['emoji'=>'🌱','title'=>'Holistic Progress Card (HPC)','subtitle'=>'शाळांसाठी डिजिटल प्रगतीपत्रक','features'=>['Digital HPC Entries','Instant PDF Reports','आपल्या मराठी भाषेत','Academic Year Tracking','नवीन NEP 2020 नुसार','गोपनीय व सुरक्षित'],'steps'=>['विद्यार्थी निवडा','रुब्रिक व अभिप्राय भरा','PDF तयार करा'],'price'=>'पहिले ५ विद्यार्थी मोफत'],
        'mdm'=>['emoji'=>'🍲','title'=>'पोषण आहार (MDM)','subtitle'=>'शाळेची दैनिक नोंद सोपी, जलद, अचूक','features'=>['दैनिक नोंद','PDF अहवाल','मासिक कॅलेंडर','धान्य साठा','मोबाईल-फर्स्ट','डेटा सुरक्षित'],'steps'=>['मेनू व वेळापत्रक ठरवा','दैनिक नोंद करा','मासिक अहवाल मिळवा'],'price'=>'Trial · वार्षिक योजना'],
        'que'=>['emoji'=>'❓','title'=>'प्रश्नपत्रिका संच','subtitle'=>'तयार, सराव, संकलित मूल्यमापन प्रश्नपत्रिका','features'=>['इयत्तानिहाय संच','सत्रनिहाय व परीक्षा प्रकार','काही पूर्ण मोफत','सोपी कार्ट पद्धत','PDF डाउनलोड','अनेक संच'],'steps'=>['यादी पहा','कार्टमध्ये टाका','डाउनलोड करा'],'price'=>'सदस्यता नाही — फक्त गरज तेवढी खरेदी'],
        'tachan'=>['emoji'=>'📝','title'=>'दैनिक टाचण','subtitle'=>'महाराष्ट्रातील शिक्षकांसाठी सर्वोत्तम साधन','features'=>['दैनिक टाचण','वार्षिक अभ्यासक्रम','वेळापत्रक','सुट्ट्या व्यवस्थापन','PDF अहवाल','सुरक्षित व खाजगी'],'steps'=>['माहिती भरा','अभ्यासक्रम नियोजन करा','अहवाल मिळवा'],'price'=>'सरळ, पारदर्शक किंमत'],
        'attendance'=>['emoji'=>'✅','title'=>'उपस्थिती व्यवस्थापन','subtitle'=>'दररोजची उपस्थिती एका क्लिकवर नोंदवा','features'=>['वर्गनिहाय उपस्थिती','P/A/L स्थिती','मासिक अहवाल','कर्मचारी उपस्थिती','जलद bulk save','सुरक्षित डेटा'],'steps'=>['वर्ग आणि तारीख निवडा','उपस्थिती नोंदवा','अहवाल पाहा'],'price'=>'पहिले ५ विद्यार्थी मोफत'],
        'classes'=>['emoji'=>'🏫','title'=>'वर्ग व विषय व्यवस्थापन','subtitle'=>'वर्ग, विषय आणि वेळापत्रक एका ठिकाणी','features'=>['वर्ग व तुकडी CRUD','वर्गशिक्षक नेमणूक','विषय व्यवस्थापन','साप्ताहिक वेळापत्रक','प्रिंट सुविधा','भूमिकेनुसार प्रवेश'],'steps'=>['वर्ग तयार करा','विषय जोडा','वेळापत्रक भरा'],'price'=>'शाळेसाठी मोफत सुरुवात'],
        'fee'=>['emoji'=>'💳','title'=>'शुल्क व्यवस्थापन','subtitle'=>'शुल्क आकारणी, पावत्या आणि अहवाल सोपे करा','features'=>['शुल्क संरचना','विद्यार्थीनिहाय वसुली','स्वयंचलित पावती','रोख/UPI/चेक','थकबाकी अहवाल','प्रिंट पावती'],'steps'=>['शुल्क शीर्षके ठरवा','पेमेंट नोंदवा','पावती व अहवाल मिळवा'],'price'=>'शाळेसाठी सोपी वार्षिक योजना'],
    ];
    return $all[$key] ?? $all['gr'];
}

function render_module_landing(string $key): void
{
    require_once __DIR__ . '/../../includes/tg_shell.php';
    $data = module_data($key);
    $b = defined('APP_URL') ? APP_URL : '';
    $icons = ['bi-lightning-charge', 'bi-folder2-open', 'bi-file-earmark-text', 'bi-graph-up-arrow', 'bi-stars', 'bi-shield-lock'];
    ?><!doctype html>
<html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($data['title']) ?> — TechGuruji</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php tg_head_assets($b); ?>
</head><body class="tg-public">
<nav class="navbar bg-white border-bottom"><div class="container">
  <a class="navbar-brand fw-bold" href="<?= $b ?>/index.php" style="color:var(--tg-800)"><i class="bi bi-mortarboard-fill me-1"></i>TechGuruji</a>
  <div class="d-flex gap-2"><a class="btn btn-outline-success" href="<?= $b ?>/auth/login.php">लॉगिन</a><a class="btn btn-success" href="<?= $b ?>/auth/register.php">मोफत नोंदणी</a></div>
</div></nav>
<section class="tg-landing-hero text-center"><div class="container tg-reveal" style="max-width:820px">
  <span class="tg-eyebrow"><i class="bi bi-grid-1x2"></i><?= e($data['title']) ?></span>
  <h1 class="tg-display mt-3"><?= e($data['subtitle']) ?></h1>
  <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
    <a href="<?= $b ?>/auth/login.php" class="btn btn-success btn-lg px-4">लॉगिन करा</a>
    <a href="#how" class="btn btn-light btn-lg px-4 border">कसे काम करते?</a>
  </div>
</div></section>
<section class="container py-5">
  <h2 class="fw-bold text-center mb-4">तुमच्या शाळेला हवे ते सगळे</h2>
  <div class="row g-3">
  <?php foreach ($data['features'] as $i => $f): ?>
    <div class="col-sm-6 col-lg-4"><div class="tg-feature"><i class="bi <?= $icons[$i % 6] ?>"></i><div><h3 class="h6 fw-bold mb-1"><?= e($f) ?></h3><p class="text-muted small mb-0">दैनंदिन कामासाठी सोपी, सुरक्षित आणि वेगवान सुविधा.</p></div></div></div>
  <?php endforeach; ?>
  </div>
</section>
<section id="how" class="container pb-5">
  <h2 class="fw-bold text-center mb-4">फक्त ३ टप्प्यांत सुरुवात</h2>
  <div class="row g-3">
  <?php foreach ($data['steps'] as $i => $st): ?>
    <div class="col-md-4"><div class="tg-step"><div class="tg-step-no"><?= ['१','२','३'][$i] ?? $i + 1 ?></div><h3 class="h6 fw-bold mt-2 mb-0"><?= e($st) ?></h3></div></div>
  <?php endforeach; ?>
  </div>
  <div class="tg-cta mt-5 text-white"><div><h2 class="h4 fw-bold mb-1">आजच सुरुवात करा</h2><p class="mb-0 opacity-75"><?= e($data['price']) ?></p></div><a href="<?= $b ?>/auth/register.php" class="btn btn-warning btn-lg fw-bold">मोफत सुरू करा</a></div>
</section>
<footer class="border-top py-4 text-center text-muted small">© <?= date('Y') ?> TechGuruji</footer>
</body></html>
<?php
}
