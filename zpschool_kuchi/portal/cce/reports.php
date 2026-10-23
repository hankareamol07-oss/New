<?php
$page_title = 'सातत्यपूर्ण मूल्यमापन अहवाल केंद्र (Reports)';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$standards = cce_school_standards((int)$_SESSION['school_id']);
$std      = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$semester = (int)($_GET['semester'] ?? 1) === 2 ? 2 : 1;
$label    = $standards[$std] ?? std_label($std);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="cce-page">

<div class="cce-dash-hero">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
      <div class="badge-udise mb-2 d-inline-block">
        सातत्यपूर्ण मूल्यमापन अहवाल केंद्र · सातत्यपूर्ण सर्वंकष मूल्यमापन
      </div>
      <h1>इयत्ता <?= sanitize($label) ?> — <?= $semester===1?'प्रथम सत्र (सत्र १)':'द्वितीय सत्र (सत्र २)' ?></h1>
      <p>अधिकृत सातत्यपूर्ण मूल्यमापन नोंदवही, निकालपत्रक आणि प्रगती पुस्तके थेट एका क्लिकवर तयार व प्रिंट करा</p>
    </div>
    <a href="<?= APP_URL ?>/cce/index.php" class="btn-hero-outline">
      <i class="bi bi-arrow-left"></i> मुख्य डॅशबोर्ड
    </a>
  </div>
</div>

<!-- SELECTION FILTER -->
<form method="GET" class="card shadow-sm mb-4" style="background:#FFFDFB; border: 1.5px solid #DCF0E7; border-radius:16px;">
  <div class="card-body p-3 p-md-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-bold" style="font-size:1.1rem; color:#0F4F3B;">इयत्ता / तुकडी निवडा</label>
        <select name="std" class="form-select fw-semibold" style="font-size:1.05rem; padding:10px 12px; border:1.5px solid #BFE3D3; border-radius:10px;" onchange="this.form.submit()">
          <?php if(!$standards): ?><option>विद्यार्थी उपलब्ध नाहीत</option><?php endif; ?>
          <?php foreach($standards as $n=>$l): ?><option value="<?= (int)$n ?>" <?= (int)$n===$std?'selected':'' ?>>इयत्ता <?= sanitize($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-bold" style="font-size:1.1rem; color:#0F4F3B;">सत्र निवडा</label>
        <select name="semester" class="form-select fw-semibold" style="font-size:1.05rem; padding:10px 12px; border:1.5px solid #BFE3D3; border-radius:10px;" onchange="this.form.submit()">
          <option value="1" <?= $semester===1?'selected':'' ?>>सत्र १ — प्रथम सत्र</option>
          <option value="2" <?= $semester===2?'selected':'' ?>>सत्र २ — द्वितीय सत्र</option>
        </select>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <a class="btn btn-primary flex-fill fw-bold py-2" style="font-size:1.05rem;" target="_blank" href="<?= APP_URL ?>/cce/result.php?std=<?= $std ?>&semester=<?= $semester ?>">
          <i class="bi bi-file-earmark-check"></i> निकालपत्रक
        </a>
        <a class="btn btn-outline-danger flex-fill fw-bold py-2" style="font-size:1.05rem;" target="_blank" href="<?= APP_URL ?>/cce/nondvahi.php?std=<?= $std ?>&semester=<?= $semester ?>">
          <i class="bi bi-journal-bookmark"></i> नोंदवही
        </a>
        <a class="btn flex-fill fw-bold py-2" style="font-size:1.05rem; background:#EFF8F4; border:1.5px solid #BFE3D3; color:#0F4F3B;" target="_blank" href="<?= APP_URL ?>/cce/pragati_pustak.php?std=<?= $std ?>&all=1&size=a4">
          <i class="bi bi-journal-album"></i> प्रगती पुस्तक
        </a>
      </div>
    </div>
  </div>
</form>

<!-- 4 OFFICIAL CORE CCE OUTPUTS (FEATURED) -->
<div class="mb-4">
  <h4 class="fw-bold mb-3" style="font-size:1.55rem; color:#0F4F3B;">
    <i class="bi bi-award-fill" style="color:#167A5B;"></i> अधिकृत मुख्य सातत्यपूर्ण मूल्यमापन अहवाल (Official Pro-Level Reports)
  </h4>
  <div class="row g-3">
    <!-- 1. Nondvahi -->
    <div class="col-md-6 col-xl-3">
      <a target="_blank" href="<?= APP_URL ?>/cce/nondvahi.php?std=<?= $std ?>&semester=<?= $semester ?>" class="d-flex flex-column justify-content-between p-3 bg-white rounded-3 h-100 text-decoration-none shadow-sm" style="border: 1.5px solid #FFCDD2; border-left: 5px solid #C62828 !important; border-radius:14px;">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-danger text-white fw-bold px-2 py-1" style="font-size:0.95rem;">अधिकृत नोंदवही</span>
            <span class="text-muted fw-semibold" style="font-size:0.95rem;">सत्र <?= $semester ?></span>
          </div>
          <h5 class="fw-bold text-dark mb-1" style="font-size:1.25rem;">📘 सातत्यपूर्ण मूल्यमापन नोंदवही</h5>
          <p class="text-muted mb-3" style="font-size:1rem; line-height:1.45;">कव्हर पान, अनुक्रमणिका, मूल्यमापन तक्ता व १३ वर्णनात्मक शेरे असलेले अधिकृत ३-पाने PDF रजिस्टर.</p>
        </div>
        <div class="d-flex justify-content-between align-items-center text-danger fw-bold pt-2 border-top" style="font-size:1.05rem;">
          <span>नोंदवही छापा / Save PDF</span>
          <i class="bi bi-box-arrow-up-right"></i>
        </div>
      </a>
    </div>

    <!-- 2. Sarvasamaveshak Nikal Patrak -->
    <div class="col-md-6 col-xl-3">
      <a target="_blank" href="<?= APP_URL ?>/cce/result.php?std=<?= $std ?>&semester=<?= $semester ?>" class="d-flex flex-column justify-content-between p-3 bg-white rounded-3 h-100 text-decoration-none shadow-sm" style="border: 1.5px solid #B2EBF2; border-left: 5px solid #00838F !important; border-radius:14px;">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-info text-dark fw-bold px-2 py-1" style="font-size:0.95rem;">A4 Landscape</span>
            <span class="text-muted fw-semibold" style="font-size:0.95rem;">सत्र <?= $semester ?></span>
          </div>
          <h5 class="fw-bold text-dark mb-1" style="font-size:1.25rem;">📊 सर्वसमावेशक निकाल पत्रक</h5>
          <p class="text-muted mb-3" style="font-size:1rem; line-height:1.45;">१५ विद्यार्थी/पान, आकारिक-संकलित गुण, टक्केवारी, श्रेणीनिहाय वर्गीकरण तक्ता व स्वाक्षऱ्या.</p>
        </div>
        <div class="d-flex justify-content-between align-items-center fw-bold pt-2 border-top" style="color:#00838F; font-size:1.05rem;">
          <span>निकालपत्रक छापा / PDF</span>
          <i class="bi bi-box-arrow-up-right"></i>
        </div>
      </a>
    </div>

    <!-- 3. Pragati Pustak A4 -->
    <div class="col-md-6 col-xl-3">
      <a target="_blank" href="<?= APP_URL ?>/cce/pragati_pustak.php?std=<?= $std ?>&all=1&size=a4" class="d-flex flex-column justify-content-between p-3 bg-white rounded-3 h-100 text-decoration-none shadow-sm" style="border: 1.5px solid #DCF0E7; border-left: 5px solid #167A5B !important; border-radius:14px;">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge text-white fw-bold px-2 py-1" style="background:#167A5B; font-size:0.95rem;">A4 बुकलेट</span>
            <span class="text-muted fw-semibold" style="font-size:0.95rem;">Sheet 1 व 2</span>
          </div>
          <h5 class="fw-bold text-dark mb-1" style="font-size:1.25rem;">🎒 प्रगती पुस्तक (A4 आकार)</h5>
          <p class="text-muted mb-3" style="font-size:1rem; line-height:1.45;">प्रत्येक A4 पानावर २ A5 पॅनेल्स. मध्यभागी घडी घालून अधिकृत पुस्तिकेसारखे वापरता येते.</p>
        </div>
        <div class="d-flex justify-content-between align-items-center fw-bold pt-2 border-top" style="color:#167A5B; font-size:1.05rem;">
          <span>A4 बुकलेट छापा</span>
          <i class="bi bi-box-arrow-up-right"></i>
        </div>
      </a>
    </div>

    <!-- 4. Pragati Pustak A5 -->
    <div class="col-md-6 col-xl-3">
      <a target="_blank" href="<?= APP_URL ?>/cce/pragati_pustak.php?std=<?= $std ?>&all=1&size=a5" class="d-flex flex-column justify-content-between p-3 bg-white rounded-3 h-100 text-decoration-none shadow-sm" style="border: 1.5px solid #C8E6C9; border-left: 5px solid #2E7D32 !important; border-radius:14px;">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-success text-white fw-bold px-2 py-1" style="font-size:0.95rem;">A5 बुकलेट</span>
            <span class="text-muted fw-semibold" style="font-size:0.95rem;">लहान आकार</span>
          </div>
          <h5 class="fw-bold text-dark mb-1" style="font-size:1.25rem;">🎒 प्रगती पुस्तक (A5 आकार)</h5>
          <p class="text-muted mb-3" style="font-size:1rem; line-height:1.45;">A5 आकाराच्या कागदावर थेट प्रमाणबद्ध प्रिंटिंग. Utsaah फॉन्ट व १५ मिमी स्वाक्षरी जागा.</p>
        </div>
        <div class="d-flex justify-content-between align-items-center text-success fw-bold pt-2 border-top" style="font-size:1.05rem;">
          <span>A5 बुकलेट छापा</span>
          <i class="bi bi-box-arrow-up-right"></i>
        </div>
      </a>
    </div>
  </div>
</div>

<!-- SECONDARY REPORTS & UTILITIES -->
<?php
$secondary_groups = [
    [
        'title' => 'सातत्यपूर्ण मूल्यमापन नोंदवही व प्रगती पुस्तके (Registers & Progress Cards)',
        'icon'  => 'bi-journal-bookmark-fill',
        'items' => [
            ['प्रथम सत्र प्रगतीपत्रक (Report-18)', 'दिवाळी/सत्र १ पालक बैठकीसाठी सिंगल शीट A4 प्रगतीपत्रक (हजेरी, श्रेणी व नोंदी)', "/cce/progress_card_term1.php?std=$std&all=1", 'bi-file-earmark-person'],
        ],
    ],
    [
        'title' => 'साधनेनिहाय व गुण-श्रेणी अहवाल (Marksheet Reports)',
        'icon'  => 'bi-tools',
        'items' => [
            ['साधनेनिहाय गुणनोंद (विद्यार्थीनिहाय Report-03)', 'विद्यार्थीनिहाय ८ आकारिक व ३ संकलित साधनांचे सविस्तर गुण व भारांश पत्रक', "/cce/tool_marksheet_student.php?std=$std&semester=$semester", 'bi-person-lines-fill'],
            ['साधनेनिहाय गुणनोंद (विषयनिहाय Report-11)', 'विषयवार सर्व विद्यार्थ्यांचे आकारिक व संकलित साधनांचे अधिकृत निकालपत्रक', "/cce/tool_marksheet_subject.php?std=$std&semester=$semester", 'bi-table'],
            ['गुण-श्रेणी निकालपत्रक (Report-08)', 'सत्रनिहाय विषयवार एकूण गुण, श्रेणी, उपस्थिती व एकवट सांख्यिकी तक्ता', "/cce/marks_grade_register.php?std=$std&semester=$semester", 'bi-card-checklist'],
        ],
    ],
    [
        'title' => 'शासकीय सांख्यिकी व श्रेणी तक्ते (Statistical Grade Reports)',
        'icon'  => 'bi-bar-chart-line',
        'items' => [
            ['A4 Portrait श्रेणी तक्ता (Report-05)', 'प्रत्येक विद्यार्थ्याचे विषयवार आकारिक व संकलित श्रेणी तक्ता (नोटीस बोर्ड / सभा)', "/cce/grade_table_portrait.php?std=$std&semester=$semester", 'bi-file-earmark-spreadsheet'],
            ['विषयनिहाय श्रेणी एकवट तक्ता (Report-06)', 'विषयवार अ-1 ते इ-2 श्रेणीनिहाय विद्यार्थी संख्या व सांख्यिकी मॅट्रिक्स', "/cce/subject_grade_summary.php?std=$std&semester=$semester", 'bi-pie-chart-fill'],
            ['एकवट मुले-मुली श्रेणी तक्ता (Report-07)', 'विषयवार मुले व मुली श्रेणीनिहाय (अ-1 ते इ-2) अधिकृत एकवट सांख्यिकी तक्ता', "/cce/gender_grade_table.php?std=$std&semester=$semester", 'bi-gender-ambiguous'],
            ['जातनिहाय श्रेणी तक्ता (Report-17)', 'SC, ST, VJ, NT, OBC, OPEN प्रवर्गानुसार मुले-मुली अधिकृत सांख्यिकी तक्ता', "/cce/caste_grade_table.php?std=$std&semester=$semester", 'bi-people-fill'],
            ['वर्गस्तर निकाल संक्षेप', 'वर्गनिहाय विषयवार गुण/श्रेणी, लिंग व संवर्ग संक्षेप', "/cce/class_grade_summary.php?std=$std&semester=$semester", 'bi-table'],
        ],
    ],
    [
        'title' => 'वार्षिक निकाल अहवाल (Annual Reports)',
        'icon'  => 'bi-award',
        'items' => [
            ['दोन्ही सत्र एकत्र सर्वसमावेशीत निकालपत्रक (Report-16)', 'सत्र १ व २ चे आकारिक व संकलित साधनांसह सविस्तर २-ओळी निकालपत्रक', "/cce/annual_comprehensive_register.php?std=$std", 'bi-journal-check'],
            ['दोन्ही सत्र एकत्र निकालपत्रक (Report-15)', 'सत्र १ व २ ची एकत्र बेरीज, वार्षिक टक्केवारी व अंतिम निकाल पत्रक', "/cce/annual_combined_register.php?std=$std", 'bi-file-earmark-spreadsheet'],
            ['५ वी व ८ वी वार्षिक परीक्षा गुणपत्रक (Report-12)', 'RTE ३५% उत्तीर्ण निकष, १५ गुण सवलत (Grace) व पुनर्परीक्षा गुणपत्रक', "/cce/std5_8_progress_card.php?std=5", 'bi-card-heading'],
            ['५ वी व ८ वी वार्षिक निकालपत्रक (Report-13)', 'RTE ३५% उत्तीर्ण निकष, १५ गुण सवलत (Grace) व पुनर्परीक्षा तक्ता', "/cce/std5_8_annual_register.php?std=5", 'bi-mortarboard-fill'],
            ['वार्षिक निकाल तक्ता (सविस्तर)', 'तुकडी निवडून सत्र १ व सत्र २ दोन्ही एकाच तक्त्यात एकत्रित', "/cce/annual_grade_table.php?std=$std&view=detailed", 'bi-calendar-range'],
            ['वार्षिक गुणपत्रक (विद्यार्थीनिहाय)', 'प्रत्येक विद्यार्थ्याची दोन्ही सत्रांची बेरीज व अंतिम शेरा', "/cce/annual_marksheet.php?std=$std&all=1", 'bi-file-earmark-medical'],
            ['एकत्रित वार्षिक निकाल डॅशबोर्ड', 'शाळास्तरावरील संपूर्ण वर्गांचा एकत्रित वार्षिक निकाल आढावा', "/cce/annual.php?std=$std", 'bi-award'],
        ],
    ],
    [
        'title' => 'वर्णनात्मक नोंदी व निष्पत्ती अहवाल',
        'icon'  => 'bi-chat-square-text',
        'items' => [
            ['वर्णनात्मक नोंदी अहवाल', 'दोन्ही सत्रांच्या घटकांतील नोंदी एकाच पत्रकावर प्रिंट करा', "/cce/narrative_report.php?std=$std&all=1", 'bi-file-text'],
            ['अध्ययन निष्पत्ती संपादणूक तक्ता', 'विद्यार्थीनिहाय अध्ययन निष्पत्ती गुण व संपादणूक ग्रिड', "/cce/outcomes_report.php?std=$std&semester=$semester", 'bi-bullseye'],
        ],
    ],
    [
        'title' => 'प्रशासकीय व सेटअप साधने',
        'icon'  => 'bi-gear',
        'items' => [
            ['विषय व्यवस्थापन', 'इयत्तानिहाय विषय, भाषा माध्यम व क्रम ठरवा', "/cce/subjects.php?std=$std", 'bi-book'],
            ['भारांश (कमाल गुण) निश्चिती', 'आकारिक (FE) व संकलित (SE) चे कमाल गुण सेट करा', "/cce/marks_config.php?std=$std", 'bi-sliders'],
            ['वर्गशिक्षक व मुख्याध्यापक', 'हजेरी पत्रक व निकालपत्रावरील शिक्षक व मुख्याध्यापक नावे', "/cce/teachers.php", 'bi-person-badge'],
        ],
    ],
];

foreach ($secondary_groups as $grp): ?>
<div class="mb-4">
  <h5 class="fw-bold mb-3" style="font-size:1.35rem; color:#0F4F3B;"><i class="bi <?= $grp['icon'] ?>" style="color:#167A5B;"></i> <?= sanitize($grp['title']) ?></h5>
  <div class="row g-3">
    <?php foreach ($grp['items'] as [$title, $sub, $url, $icon]): ?>
    <div class="col-md-6 col-xl-4">
      <a target="_blank" href="<?= APP_URL . $url ?>" class="d-flex gap-3 bg-white rounded-3 p-3 text-decoration-none h-100 shadow-sm transition-all" style="border:1.5px solid #DCF0E7; border-radius:12px; transition: transform 0.15s, border-color 0.15s;">
        <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:12px;background:#EFF8F4;color:#0F4F3B;font-size:1.3rem;">
          <i class="bi <?= $icon ?>"></i>
        </div>
        <div class="min-width-0">
          <div class="fw-bold text-dark mb-1" style="font-size:1.1rem;"><?= sanitize($title) ?></div>
          <div class="text-muted" style="font-size:0.96rem; line-height:1.4;"><?= sanitize($sub) ?></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

</div><!-- /.cce-page -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
