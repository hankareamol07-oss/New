<?php
declare(strict_types=1);

/**
 * Smart School Hub — Monthly Lesson Planner Register (मासिक टाचण वही)
 * 👑 STRICTLY RESTRICTED TO PREMIUM (PRO) SUBSCRIBERS ONLY
 * 
 * Aligned with NEP 2020 Maharashtra School Guidelines:
 * - 11 Academic Months (June to April)
 * - Complete Class Std 1 to 8 Official Balbharati Topics & 4-Week Breakdown
 * - Learning Outcomes (अध्ययन निष्पत्ती)
 * - Teaching-Learning Materials (शैक्षणिक साधने / TLM)
 * - Pedagogical Activities & Evaluation Methods (अध्यापन पद्धती व मूल्यमापन)
 * - Integrated Monthly Co-curricular Activities & Holidays
 * - One-Click Official A4 Printable Register (monthly_print.php)
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/planner_seed.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';
require_once __DIR__ . '/../../includes/module_access.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db();
$mysql = getMainDB();

$school = getSchool() ?: school_row($sid);
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
$udise = (string)($school['udise_code'] ?? ($school['udise'] ?? ''));

// ==========================================
// 1. PREMIUM / PRO ACCESS VERIFICATION
// ==========================================
$isPremium = is_tachan_module_purchased($sid);

if (!$isPremium) {
    tachan_layout_start('मासिक टाचण वही (PRO)', 'monthly');
    ?>
    <div class="container-fluid px-0 py-3">
        <!-- PRO Paywall Card -->
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden mx-auto" style="max-width: 960px; background: #ffffff;">
            <!-- Top Gradient Header -->
            <div class="p-4 p-md-5 text-center text-white position-relative" style="background: linear-gradient(135deg, #0B3D2E 0%, #167A5B 50%, #1D9370 100%);">
                <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                    <i class="bi bi-calendar-month-fill" style="font-size: 8rem; color: #FDE047;"></i>
                </div>
                
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill mb-3" style="background: rgba(253, 224, 71, 0.2); border: 1.5px solid #FDE047;">
                    <span style="font-size: 1.1rem;">👑</span>
                    <span class="fw-bold" style="color: #FEF08A; font-size: 0.95rem; letter-spacing: 0.5px;">प्रीमियम (PRO) विशेष सुविधा</span>
                </div>
                
                <h2 class="fw-extrabold mb-2 display-6" style="font-family: 'Martel', serif; color: #ffffff;">
                    मासिक पाठ टाचण नोंदवही
                </h2>
                <p class="mb-0 fs-5 opacity-90 mx-auto" style="max-width: 680px; font-weight: 500; color: #E6F4EA;">
                    इयत्ता १ली ते ८वी साठी महिनानिहाय सर्व विषयांचे घटक, ४ आठवड्यांची विभागणी, अध्ययन निष्पत्ती, शैक्षणिक साधने व अधिकृत A4 शासकीय रजिस्टर.
                </p>
            </div>

            <!-- Value Proposition Features -->
            <div class="card-body p-4 p-md-5">
                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-4 h-100 border text-center" style="background: #F8FAFC; border-color: #E2E8F0;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 52px; height: 52px; background: #E0F2FE; color: #0284C7; font-size: 1.5rem;">
                                <i class="bi bi-calendar3"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">११ महिन्यांचे नियोजन</h6>
                            <p class="text-muted small mb-0">जून ते एप्रिल प्रत्येक महिन्याचे घटक व ४ आठवड्यांची सूक्ष्म विभागणी.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-4 h-100 border text-center" style="background: #F8FAFC; border-color: #E2E8F0;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 52px; height: 52px; background: #DCFCE7; color: #16A34A; font-size: 1.5rem;">
                                <i class="bi bi-bullseye"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">अध्ययन निष्पत्ती (LO)</h6>
                            <p class="text-muted small mb-0">NEP 2020 निकषांनुसार प्रत्येक विषयाच्या अधिकृत अध्ययन निष्पत्तींची स्वयंचलित जोडणी.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-4 h-100 border text-center" style="background: #F8FAFC; border-color: #E2E8F0;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 52px; height: 52px; background: #FEF3C7; color: #D97706; font-size: 1.5rem;">
                                <i class="bi bi-tools"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">साधने व पद्धती (TLM)</h6>
                            <p class="text-muted small mb-0">अध्यापन साहित्य, फलक, प्रत्यक्ष कृती, सराव व मूल्यमापन साधनांचा समावेश.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-4 h-100 border text-center" style="background: #F8FAFC; border-color: #E2E8F0;">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 52px; height: 52px; background: #FCE7F3; color: #DB2777; font-size: 1.5rem;">
                                <i class="bi bi-printer-fill"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">A4 शासकीय प्रिंट / PDF</h6>
                            <p class="text-muted small mb-0">केंद्रप्रमुख, विस्तार अधिकारी व शिक्षण परिषद तपासणीसाठी अधिकृत स्वाक्षरीसह फॉरमॅट.</p>
                        </div>
                    </div>
                </div>

                <!-- Preview Teaser / Sample Watermark Table -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-light text-muted border px-3 py-1.5 rounded-pill fw-bold">
                            <i class="bi bi-eye-fill me-1"></i> नमुना पूर्वावलोकन (Sample Register Preview — इयत्ता ३री · जुलै महिना)
                        </span>
                        <span class="badge bg-warning text-dark px-2 py-1 rounded-pill fw-bold">🔒 केवळ PRO साठी सक्रिय</span>
                    </div>

                    <div class="table-responsive rounded-3 border position-relative" style="background: #ffffff; max-height: 230px; overflow: hidden;">
                        <table class="table table-bordered table-sm mb-0 align-middle" style="font-size: 0.82rem; filter: blur(0.6px); opacity: 0.85;">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 50px;">अ.क्र.</th>
                                    <th style="width: 100px;">विषय</th>
                                    <th style="width: 220px;">नियोजित घटक व उपघटक</th>
                                    <th style="width: 200px;">४ आठवड्यांचे नियोजन</th>
                                    <th>अपेक्षित अध्ययन निष्पत्ती</th>
                                    <th style="width: 140px;">शैक्षणिक साहित्य</th>
                                    <th style="width: 80px;">तासिका</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center fw-bold">१</td>
                                    <td class="fw-bold text-primary">मराठी</td>
                                    <td>शोधिसी मानवा, वर्षासहल, हिरवा वसा, गोष्ट तेनालीरामची</td>
                                    <td>१. प्रार्थना | २. चित्रवर्णन | ३. कविता | ४. कथा वाचन</td>
                                    <td>कवितेचे भावपूर्ण गायन, चित्रावरून स्वभाषेत ५-६ वाक्ये सांगणे, वाचन आकलन.</td>
                                    <td>पाठ्यपुस्तक, चित्रतक्ते, फलक</td>
                                    <td class="text-center fw-bold">२४</td>
                                </tr>
                                <tr>
                                    <td class="text-center fw-bold">२</td>
                                    <td class="fw-bold text-success">गणित</td>
                                    <td>सभोवताली शोधू आकार, त्रिमितीय आकार व घडणी, संख्यांशी खेळू या</td>
                                    <td>१. आकार ओळख | २. त्रिमितीय घडणी | ३. ३-अंकी संख्या | ४. स्थानिक किंमत</td>
                                    <td>त्रिमितीय वस्तूंच्या कडा व कोपरे ओळखणे; ३-अंकी संख्यांचे वाचन-लेखन व विस्तार.</td>
                                    <td>गणित पेटी, वस्तू, फलक</td>
                                    <td class="text-center fw-bold">२४</td>
                                </tr>
                                <tr>
                                    <td class="text-center fw-bold">३</td>
                                    <td class="fw-bold text-purple" style="color:#7C3AED;">इंग्रजी</td>
                                    <td>Unit 1: We are Happy, Word Family, The Picnic, First Aid Box</td>
                                    <td>1. Rhyme & Action | 2. Word Family | 3. Story | 4. First Aid Kit</td>
                                    <td>Recites rhymes with actions; frames simple 3-letter words; reads short story.</td>
                                    <td>Flash cards, Charts</td>
                                    <td class="text-center fw-bold">२०</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Watermark Overlay -->
                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center" style="background: rgba(255, 255, 255, 0.55); backdrop-filter: blur(1.5px);">
                            <div class="badge bg-dark px-3 py-2 rounded-pill shadow-sm mb-2" style="font-size: 0.9rem;">
                                <i class="bi bi-lock-fill text-warning me-1"></i> हे संपूर्ण महिनानिहाय रजिस्टर अनलॉक करण्यासाठी PRO आवश्यक आहे
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call To Action Upgrade Buttons -->
                <div class="text-center p-4 rounded-4" style="background: #F0FDF4; border: 1.5px dashed #86EFAC;">
                    <h5 class="fw-bold text-dark mb-2">मासिक टाचण नोंदवही आजच सक्रिय करा</h5>
                    <p class="text-muted small mb-4 mx-auto" style="max-width: 620px;">
                        मासिक टाचण, जुने टाचण व बल्क A4 प्रिंट या सुविधांसाठी स्वतंत्र <strong>दैनिक टाचण वही प्लॅन (₹४९९)</strong> किंवा <strong>शाळा ऑल-इन-वन पॅक (₹१,४९९)</strong> निवडा.
                    </p>

                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=24" class="btn btn-warning fw-bold px-4 py-2.5 rounded-pill shadow-sm text-dark d-inline-flex align-items-center gap-2" style="background: #F59E0B; border-color: #F59E0B; font-size: 0.95rem;">
                            <i class="bi bi-cart-check-fill"></i>
                            फक्त दैनिक टाचण वही मॉड्युल घ्या (₹४९९/वर्ष)
                        </a>

                        <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=4" class="btn btn-primary fw-bold px-4 py-2.5 rounded-pill shadow-sm d-inline-flex align-items-center gap-2" style="background: #167A5B; border-color: #167A5B; font-size: 0.95rem;">
                            <i class="bi bi-award-fill text-warning"></i>
                            शाळा ऑल-इन-वन पॅक (₹१,४९९/वर्ष)
                        </a>

                        <a href="https://wa.me/919421213070?text=<?= urlencode('नमस्कार, मला स्मार्ट स्कूल हब वरील मासिक टाचण वही (Monthly Tachan PRO) ॲक्टिव्हेट करायचे आहे. कृपया माहिती द्या.') ?>" target="_blank" class="btn btn-outline-success fw-bold px-4 py-2.5 rounded-pill d-inline-flex align-items-center gap-2">
                            <i class="bi bi-whatsapp"></i>
                            WhatsApp वर मदत मिळवा
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    tachan_layout_end();
    exit;
}

// ==========================================
// 2. PREMIUM (PRO) SUBSCRIBER WORKFLOW
// ==========================================

$classes = school_classes($sid);
if (!$classes) {
    flash_set('error', 'आधी वर्ग व्यवस्थापनातून वर्ग तयार करा.');
    header('Location: ' . APP_URL . '/modules/tachan/index.php');
    exit;
}

$byId = [];
foreach ($classes as $c) {
    $byId[(int)$c['id']] = $c;
}

$pref = tachan_prefs_get($pdo, $sid, (int)$user['id']);
$prefClass = ($pref['class_id'] && isset($byId[$pref['class_id']])) ? $pref['class_id'] : (int)$classes[0]['id'];
$sel = isset($_GET['class_id']) && isset($byId[(int)$_GET['class_id']]) ? (int)$_GET['class_id'] : $prefClass;
$class = $byId[$sel];
$std = planner_class_std($class);

$medium = isset($_GET['medium']) 
    ? tachan_norm_medium((string)$_GET['medium']) 
    : ($pref['medium'] ?: tachan_norm_medium((string)($class['medium'] ?? 'marathi')));

$months = planner_months();
$mSel = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if (!isset($months[$mSel])) {
    $mSel = 6; // Default to June
}

// Medium display helper
$disp = static fn(string $s): string => tachan_display_subject($s, $medium);

// Fetch custom teacher modifications from annual_plans
$planRows = [];
try {
    $stmtPlan = $pdo->prepare('SELECT * FROM annual_plans WHERE school_id = ? AND class_id = ? AND month = ?');
    $stmtPlan->execute([$sid, $sel, $mSel]);
    foreach ($stmtPlan->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $planRows[$pr['subject']] = $pr;
    }
} catch (Throwable $e) {}

// Official week-by-week curriculum bank
$allWeeks = planner_annual_weeks();
$stdWeeks = $allWeeks[$std][$mSel] ?? [];

// Resolve subject order
$coreSubjects = planner_subjects_for_std($std);
$extraSubjects = planner_extra_subjects($std);
$masterSubjects = array_unique(array_merge($coreSubjects, array_keys($stdWeeks), array_keys($planRows), $extraSubjects));

// Filter out empty or duplicate
$activeSubjects = [];
foreach ($masterSubjects as $sub) {
    if (trim($sub) === '') continue;
    $activeSubjects[] = $sub;
}

// Official content bank data for this std & month
$bankCache = [];
try {
    $stmtBank = $mysql->prepare('SELECT subject, topic, learning_outcome, materials, tl_activity, eval_tool FROM tachan_bank WHERE std = ? AND month_no = ? ORDER BY seq');
    $stmtBank->execute([$std, $mSel]);
    while ($brow = $stmtBank->fetch(PDO::FETCH_ASSOC)) {
        $bankCache[$brow['subject']][] = $brow;
    }
} catch (Throwable $e) {}

// Standard template fallback
$templates = planner_tachan_templates();

// Co-curricular activities for this month
$monthlyEvents = planner_calendar()[$mSel] ?? [];

// Public holidays for this month in year 2026-27
$allHolidays = planner_holidays();
$monthHolidays = [];
$yearNumber = ($mSel >= 6) ? 2026 : 2027;
$prefixDate = sprintf('%04d-%02d-', $yearNumber, $mSel);
foreach ($allHolidays as $h) {
    if (strpos($h[0], $prefixDate) === 0) {
        $monthHolidays[] = $h;
    }
}

// Calculate total working days in this month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $mSel, $yearNumber);
$workingDays = 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dt = sprintf('%04d-%02d-%02d', $yearNumber, $mSel, $d);
    $dow = (int)date('N', strtotime($dt));
    if ($dow === 7) continue; // Sunday
    // check holiday
    $isHol = false;
    foreach ($allHolidays as $h) {
        if ($h[0] === $dt) { $isHol = true; break; }
    }
    if (!$isHol) $workingDays++;
}

// Resolve Teacher Name
$teacherName = resolve_teacher_name($class, $sid, $user);

$printUrl = APP_URL . '/modules/tachan/monthly_print.php?' . http_build_query([
    'class_id' => $sel,
    'month'    => $mSel,
    'medium'   => $medium,
]);

tachan_layout_start('मासिक टाचण वही (PRO)', 'monthly');
?>

<div class="container-fluid px-0">
    <!-- Top Filter & Control Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark px-2.5 py-1.5 rounded-pill fw-bold" style="font-size: 0.85rem;">
                        👑 PRO सदस्य
                    </span>
                    <h4 class="mb-0 fw-bold text-dark" style="font-family: 'Martel', serif;">
                        मासिक पाठ टाचण नोंदवही
                    </h4>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e($printUrl) ?>" target="_blank" class="btn btn-dark btn-sm fw-bold rounded-pill px-3 py-2 shadow-sm d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-printer-fill text-warning"></i>
                        <span>A4 मासिक टाचण प्रिंट / PDF</span>
                    </a>
                    <a href="<?= APP_URL ?>/modules/tachan/annual.php?class_id=<?= $sel ?>&month=<?= $mSel ?>" class="btn btn-outline-secondary btn-sm fw-bold rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-pencil-square"></i>
                        <span>घटक संपादित करा</span>
                    </a>
                </div>
            </div>

            <!-- Month Selection Pill Ribbon -->
            <div class="mb-3">
                <label class="form-label small text-muted fw-bold mb-1.5 d-flex align-items-center gap-1">
                    <i class="bi bi-calendar-event"></i> महिना निवडा (जून ते एप्रिल):
                </label>
                <div class="d-flex flex-wrap gap-1.5">
                    <?php foreach ($months as $mNum => $mName): ?>
                        <?php 
                        $isActive = ($mNum === $mSel);
                        $mUrl = APP_URL . '/modules/tachan/monthly.php?' . http_build_query([
                            'class_id' => $sel,
                            'month'    => $mNum,
                            'medium'   => $medium,
                        ]);
                        ?>
                        <a href="<?= e($mUrl) ?>" class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3 py-1 fw-bold" style="<?= $isActive ? 'background:#167A5B; border-color:#167A5B;' : '' ?>">
                            <?= e($mName) ?> <?= ($mNum >= 6) ? '२०२६' : '२०२७' ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Filter Controls Form -->
            <form method="get" class="row g-2 align-items-end pt-2 border-top">
                <input type="hidden" name="month" value="<?= $mSel ?>">
                
                <!-- Class Selector -->
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">इयत्ता व तुकडी</label>
                    <select name="class_id" onchange="this.form.submit()" class="form-select form-select-sm rounded-3">
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $sel === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?> (<?= e(class_label(planner_class_std($c))) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Medium Selector -->
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">माध्यम</label>
                    <select name="medium" onchange="this.form.submit()" class="form-select form-select-sm rounded-3">
                        <option value="marathi" <?= $medium === 'marathi' ? 'selected' : '' ?>>मराठी माध्यम</option>
                        <option value="semi_english" <?= $medium === 'semi_english' ? 'selected' : '' ?>>सेमी-इंग्रजी</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 d-flex align-items-center justify-content-md-end text-muted small pt-2">
                    <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-3">
                        <i class="bi bi-clock-history me-1 text-primary"></i> अंदाजे कामकाजाचे दिवस: <strong><?= $workingDays ?> दिवस</strong>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Monthly Overview Strip -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%); border-left: 5px solid #167A5B !important;">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-7">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-white text-dark border shadow-2xs">इयत्ता: <?= e($class['name']) ?></span>
                        <span class="badge bg-white text-dark border shadow-2xs">माध्यम: <?= $medium === 'semi_english' ? 'सेमी-इंग्रजी' : 'मराठी' ?></span>
                        <span class="badge bg-white text-dark border shadow-2xs">शिक्षक: <?= e($teacherName) ?></span>
                    </div>
                    <h5 class="fw-bold text-dark mb-0">
                        <?= e($months[$mSel]) ?> <?= ($mSel >= 6) ? '२०२६' : '२०२७' ?> — सर्व विषयांचे मासिक पाठ टाचण नियोजन
                    </h5>
                </div>

                <div class="col-12 col-md-5">
                    <?php if (!empty($monthlyEvents)): ?>
                        <div class="p-2.5 rounded-3 bg-white border small">
                            <span class="fw-bold text-primary d-block mb-1">
                                <i class="bi bi-flag-fill me-1"></i> या महिन्यातील प्रमुख उपक्रम:
                            </span>
                            <span class="text-muted"><?= e(implode(' • ', array_slice($monthlyEvents, 0, 3))) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Master Monthly Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i>
                विषयनिहाय मासिक पाठ नियोजन तक्ता (<?= count($activeSubjects) ?> विषय)
            </h6>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-bold">
                NEP 2020 निकष सुसंगत
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 align-top" style="font-size: 0.86rem;">
                <thead style="background: #17324D; color: #ffffff;">
                    <tr class="text-center align-middle">
                        <th style="width: 45px; background: #17324D; color: #fff;">अ.क्र.</th>
                        <th style="width: 120px; background: #17324D; color: #fff;">विषय</th>
                        <th style="width: 220px; background: #17324D; color: #fff;">नियोजित घटक / उपघटक</th>
                        <th style="width: 220px; background: #17324D; color: #fff;">४ आठवड्यांची विभागणी</th>
                        <th style="background: #17324D; color: #fff;">अपेक्षित अध्ययन निष्पत्ती</th>
                        <th style="width: 150px; background: #17324D; color: #fff;">शैक्षणिक साधने (TLM)</th>
                        <th style="width: 170px; background: #17324D; color: #fff;">अध्यापन पद्धती व कृती</th>
                        <th style="width: 130px; background: #17324D; color: #fff;">मूल्यमापन पद्धती</th>
                        <th style="width: 70px; background: #17324D; color: #fff;">तासिका</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sr = 0;
                    $totalPeriods = 0;
                    foreach ($activeSubjects as $sub): 
                        $sr++;
                        $plan = $planRows[$sub] ?? null;
                        
                        // Weeks resolution
                        $w1 = trim((string)($plan['week1'] ?? ($stdWeeks[$sub][0] ?? '')));
                        $w2 = trim((string)($plan['week2'] ?? ($stdWeeks[$sub][1] ?? '')));
                        $w3 = trim((string)($plan['week3'] ?? ($stdWeeks[$sub][2] ?? '')));
                        $w4 = trim((string)($plan['week4'] ?? ($stdWeeks[$sub][3] ?? '')));
                        
                        // Topics resolution
                        $topic = trim((string)($plan['topics'] ?? ''));
                        if ($topic === '') {
                            $nonEmptyWeeks = array_filter([$w1, $w2, $w3, $w4]);
                            $topic = $nonEmptyWeeks ? implode(' | ', $nonEmptyWeeks) : $sub;
                        }

                        // Bank row match
                        $bankSub = tachan_bank_subject($std, $sub, $medium);
                        $matchedBank = $bankCache[$bankSub] ?? ($bankCache[$sub] ?? []);
                        
                        // Learning Outcomes
                        $loList = [];
                        $tlmList = [];
                        $actList = [];
                        $evalList = [];
                        
                        foreach ($matchedBank as $b) {
                            if (!empty($b['learning_outcome']) && !in_array($b['learning_outcome'], $loList, true)) {
                                $loList[] = $b['learning_outcome'];
                            }
                            if (!empty($b['materials']) && !in_array($b['materials'], $tlmList, true)) {
                                $tlmList[] = $b['materials'];
                            }
                            if (!empty($b['tl_activity']) && !in_array($b['tl_activity'], $actList, true)) {
                                $actList[] = $b['tl_activity'];
                            }
                            if (!empty($b['eval_tool']) && !in_array($b['eval_tool'], $evalList, true)) {
                                $evalList[] = $b['eval_tool'];
                            }
                        }

                        // Fallback template defaults
                        $tpl = $templates[$sub] ?? ($templates[planner_canonical_subject($sub)] ?? null);
                        
                        $loText = $loList ? implode('; ', array_slice($loList, 0, 2)) : ($tpl['objectives'] ?? 'घटकाचे वाचन, आकलन व संकल्पना स्पष्ट होणे.');
                        $tlmText = $tlmList ? implode(', ', array_slice($tlmList, 0, 2)) : 'पाठ्यपुस्तक, फलक, चित्रतक्ते, प्रत्यक्ष वस्तू.';
                        $actText = $actList ? implode('; ', array_slice($actList, 0, 2)) : ($tpl['activities'] ?? 'चर्चा, वाचन, फलकलेखन व स्वाध्याय सराव.');
                        $evalText = $evalList ? implode(', ', array_slice($evalList, 0, 2)) : 'निरीक्षण, तोंडी काम, स्वाध्याय.';

                        // Estimate planned periods
                        $periods = 20;
                        if (in_array($sub, ['मराठी', 'गणित'], true)) {
                            $periods = 24;
                        } elseif (in_array($sub, ['इंग्रजी', 'परिसर अभ्यास', 'विज्ञान'], true)) {
                            $periods = 20;
                        } elseif (in_array($sub, ['कला शिक्षण', 'शारीरिक शिक्षण', 'कार्यशिक्षण', 'ग्रंथालय'], true)) {
                            $periods = 8;
                        }
                        $totalPeriods += $periods;
                    ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $sr ?></td>
                            <td class="fw-bold" style="color: #0F172A;">
                                <?= e($disp($sub)) ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($topic) ?></div>
                            </td>
                            <td>
                                <div class="small" style="line-height: 1.45;">
                                    <?php if ($w1 !== ''): ?><div><strong class="text-primary">आ१:</strong> <?= e($w1) ?></div><?php endif; ?>
                                    <?php if ($w2 !== ''): ?><div><strong class="text-primary">आ२:</strong> <?= e($w2) ?></div><?php endif; ?>
                                    <?php if ($w3 !== ''): ?><div><strong class="text-primary">आ३:</strong> <?= e($w3) ?></div><?php endif; ?>
                                    <?php if ($w4 !== ''): ?><div><strong class="text-primary">आ४:</strong> <?= e($w4) ?></div><?php endif; ?>
                                    <?php if ($w1 === '' && $w2 === '' && $w3 === '' && $w4 === ''): ?>
                                        <span class="text-muted fst-italic">महिनानिहाय सर्वसमावेशक घटक</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-muted small" style="line-height: 1.45;">
                                    <?= e($loText) ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-muted small">
                                    <?= e($tlmText) ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-muted small">
                                    <?= e($actText) ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-muted small">
                                    <?= e($evalText) ?>
                                </div>
                            </td>
                            <td class="text-center fw-bold text-primary">
                                <?= $periods ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="8" class="text-end fw-bold text-dark pe-3">एकूण नियोजित तासिका:</th>
                        <th class="text-center fw-bold text-primary fs-6"><?= $totalPeriods ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Monthly Upakram & Holidays Row -->
    <div class="row g-3 mb-4">
        <!-- Upakram Card -->
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-bold text-primary mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-check-fill"></i>
                        मासिक शालेय व सहशालेय उपक्रम (<?= e($months[$mSel]) ?>)
                    </h6>
                    <?php if ($monthlyEvents): ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach ($monthlyEvents as $ev): ?>
                                <li class="list-group-item px-0 py-1.5 border-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-check2-circle text-success"></i>
                                    <span><?= e($ev) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted small mb-0">या महिन्यासाठी नियमित शालेय वेळापत्रक लागू राहील.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Holidays Card -->
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-bold text-danger mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-sun-fill"></i>
                        या महिन्यातील शासकीय सुट्ट्या व सण
                    </h6>
                    <?php if ($monthHolidays): ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach ($monthHolidays as $hol): ?>
                                <li class="list-group-item px-0 py-1.5 border-0 d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-dark"><?= e($hol[1]) ?></span>
                                    <span class="badge bg-light text-muted border"><?= date('d-m-Y', strtotime($hol[0])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted small mb-0">या महिन्यात कोणताही शासकीय सार्वजनिक सुट्टीचा दिवस नाही (नियमित रविवार व्यतिरिक्त).</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Signatures Strip -->
    <div class="card border-0 shadow-sm rounded-4 p-4 text-center mb-4 bg-white">
        <div class="row g-4 pt-2">
            <div class="col-4">
                <div class="border-top border-dark pt-2">
                    <span class="fw-bold text-dark d-block">विषय शिक्षक</span>
                    <small class="text-muted"><?= e($teacherName) ?></small>
                </div>
            </div>
            <div class="col-4">
                <div class="border-top border-dark pt-2">
                    <span class="fw-bold text-dark d-block">वर्ग शिक्षक</span>
                    <small class="text-muted">इयत्ता <?= e($class['name']) ?></small>
                </div>
            </div>
            <div class="col-4">
                <div class="border-top border-dark pt-2">
                    <span class="fw-bold text-dark d-block">मुख्याध्यापक</span>
                    <small class="text-muted">स्वाक्षरी व शाळा शिक्का</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
tachan_layout_end();
