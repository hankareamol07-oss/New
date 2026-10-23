<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../../includes/module_access.php';

$user = tachan_auth_check();
$access = require_module_access('tachan');
$sid = (int)$_SESSION['school_id'];
$pdo = db(); // queries school tenant SQLite

$school = getSchool();
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));

$h = (int)date('G');
$greet = $h < 12 ? 'सुप्रभात' : ($h < 17 ? 'शुभ दुपार' : 'शुभ संध्याकाळ');

$total = 0;
try {
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM lesson_plans WHERE school_id=? AND teacher_id=?');
    $cnt->execute([$sid, (int)$user['id']]);
    $total = (int)$cnt->fetchColumn();
} catch (Throwable $e) {}

$cards = [
    [
        'href' => APP_URL . '/modules/tachan/day.php',
        'icon' => 'bi-calendar2-check',
        'title' => 'दैनिक टाचण',
        'subtitle' => 'शिक्षकाचे व वर्गाचे तासिकानिहाय पाठ',
        'badge' => 'दैनिक',
        'color' => '#0284C7',
        'bg' => '#E0F2FE'
    ],
    [
        'href' => APP_URL . '/modules/tachan/weekly.php',
        'icon' => 'bi-calendar2-range',
        'title' => 'साप्ताहिक टाचण',
        'subtitle' => 'संपूर्ण आठवड्याचे एकत्रित नियोजन',
        'badge' => 'आठवडा',
        'color' => '#7C3AED',
        'bg' => '#EDE9FE'
    ],
    [
        'href' => APP_URL . '/modules/tachan/monthly.php',
        'icon' => 'bi-calendar-month-fill',
        'title' => 'मासिक टाचण',
        'subtitle' => 'महिनानिहाय सर्वसमावेशक पाठ टाचण वही',
        'badge' => '👑 PRO',
        'color' => '#E11D48',
        'bg' => '#FFE4E6'
    ],
    [
        'href' => APP_URL . '/modules/tachan/timetable.php',
        'icon' => 'bi-clock-history',
        'title' => 'वेळापत्रक (माझे / वर्ग)',
        'subtitle' => 'शिक्षकाचे व वर्गाचे तासिका नियोजन',
        'badge' => 'वेळापत्रक',
        'color' => '#059669',
        'bg' => '#D1FAE5'
    ],
    [
        'href' => APP_URL . '/modules/tachan/annual.php',
        'icon' => 'bi-book-half',
        'title' => 'वार्षिक नियोजन',
        'subtitle' => 'महिनानिहाय शासकीय अभ्यासक्रम घटक',
        'badge' => 'वार्षिक',
        'color' => '#D97706',
        'bg' => '#FEF3C7'
    ],
    [
        'href' => APP_URL . '/modules/tachan/calendar.php',
        'icon' => 'bi-calendar3-event-fill',
        'title' => 'शालेय कामकाज कॅलेंडर',
        'subtitle' => 'महिनानिहाय उपक्रम, दिनविशेष आणि शासकीय व शाळा सुट्ट्या',
        'badge' => 'कॅलेंडर',
        'color' => '#2563EB',
        'bg' => '#DBEAFE'
    ],
    [
        'href' => APP_URL . '/modules/tachan/holidays.php',
        'icon' => 'bi-sun-fill',
        'title' => 'सुट्ट्या व दिनविशेष',
        'subtitle' => 'शासकीय व स्थानिक सुट्ट्यांचे कॅलेंडर',
        'badge' => 'कॅलेंडर',
        'color' => '#DC2626',
        'bg' => '#FEE2E2'
    ],
    [
        'href' => APP_URL . '/modules/tachan/backdate.php',
        'icon' => 'bi-calendar-range',
        'title' => 'जुने टाचण',
        'subtitle' => 'मागील व भावी दिवसनिहाय टाचण',
        'badge' => 'दिवसनिहाय',
        'color' => '#0284C7',
        'bg' => '#E0F2FE'
    ],
    [
        'href' => APP_URL . '/modules/tachan/class_pref.php',
        'icon' => 'bi-sliders',
        'title' => 'टाचण सेटिंग्ज',
        'subtitle' => 'माझा वर्ग, माध्यम व जोडवर्ग निवड',
        'badge' => 'सेटिंग्ज',
        'color' => '#0F766E',
        'bg' => '#CCFBF1'
    ],
    [
        'href' => APP_URL . '/modules/tachan/add.php',
        'icon' => 'bi-pencil-plus',
        'title' => 'नवीन टाचण नोंद',
        'subtitle' => 'स्वतःची स्वतंत्र टाचण नोंद जोडा',
        'badge' => 'नवीन',
        'color' => '#167A5B',
        'bg' => '#DCF0E7'
    ],
];

$isPaid = is_tachan_module_purchased($sid);
if (!$isPaid) {
    // In trial mode, do not show old bulk download
    $cards = array_values(array_filter($cards, fn($c) => !str_contains($c['href'], 'backdate.php')));
}

tachan_layout_start('टाचण हब', 'home');
?>

<?php if (($access['type'] ?? '') === 'trial'): ?>
<div class="alert alert-warning border-warning d-flex flex-wrap align-items-center justify-content-between mb-4 shadow-sm rounded-3 p-3" role="alert">
    <div class="d-flex align-items-center gap-3">
        <i class="bi bi-clock-history fs-2 text-warning"></i>
        <div>
            <h6 class="fw-bold text-dark mb-1">५-दिवसीय मोफत चाचणी (Free Trial) सुरू आहे!</h6>
            <div class="small text-secondary">आपली चाचणी सुरू असून <strong><?= (int)$access['days_left'] ?> दिवस शिल्लक</strong> आहेत. अमर्यादित वापरासाठी कृपया योजना अपग्रेड करा.</div>
        </div>
    </div>
    <a href="<?= APP_URL ?>/subscription/plans.php?module=tachan" class="btn btn-sm btn-warning text-dark fw-bold text-nowrap shadow-sm mt-2 mt-sm-0">
        <i class="bi bi-stars me-1"></i> योजना अपग्रेड करा
    </a>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- Welcome Hero Card -->
    <div class="col-12">
        <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #0F4F3B 0%, #167A5B 65%, #1D9370 100%); border-radius: 16px;">
            <div class="card-body p-4 p-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <div class="text-white-50 small fw-bold mb-1">
                            <?= htmlspecialchars($greet) ?>,
                        </div>
                        <?php 
                        $uName = (string)($user['name'] ?? '');
                        $isSch = ($uName === $schoolName) || str_contains($uName, 'शाळा') || str_contains(strtolower($uName), 'school');
                        $displayUser = $isSch ? 'मुख्याध्यापक / शिक्षक' : ($uName ?: 'शिक्षक');
                        ?>
                        <h2 class="fw-bold mb-1 fs-3">
                            <?= htmlspecialchars($displayUser) ?>
                        </h2>
                        <div class="opacity-90 small">
                            <i class="bi bi-building me-1"></i> <?= htmlspecialchars($schoolName) ?>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
                        <span class="badge bg-white text-dark py-2 px-3 fs-6 shadow-sm">
                            <i class="bi bi-calendar-date text-warning me-1"></i> <?= htmlspecialchars(marathi_date_words(date('Y-m-d'))) ?>
                        </span>
                        <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-50 py-2 px-3 fs-6">
                            <i class="bi bi-check2-all me-1"></i> एकूण नोंदी: <b><?= $total ?></b>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Navigation 8-Grid -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">
        <i class="bi bi-grid-3x3-gap-fill text-warning me-1"></i> टाचण विभाग व साधने
    </h5>
    <span class="text-muted small">सर्व ८ विभाग उपलब्ध</span>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as $c): ?>
        <div class="col-12 col-sm-6 col-lg-3">
            <a href="<?= htmlspecialchars($c['href']) ?>" class="card tachan-card h-100 text-decoration-none text-dark p-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" 
                         style="width: 52px; height: 52px; background: <?= $c['bg'] ?>; color: <?= $c['color'] ?>;">
                        <i class="bi <?= $c['icon'] ?> fs-3"></i>
                    </div>
                    <span class="badge" style="background: <?= $c['bg'] ?>; color: <?= $c['color'] ?>; font-size: 0.72rem;">
                        <?= $c['badge'] ?>
                    </span>
                </div>
                <h6 class="fw-bold mb-1 text-dark fs-6"><?= htmlspecialchars($c['title']) ?></h6>
                <p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.82rem; line-height: 1.35;">
                    <?= htmlspecialchars($c['subtitle']) ?>
                </p>
                <div class="d-flex align-items-center fw-bold" style="color: <?= $c['color'] ?>; font-size: 0.82rem;">
                    उघडा <i class="bi bi-arrow-right ms-1"></i>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Helpful Info Card -->
<div class="card border-0 shadow-sm rounded-3" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
    <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-info-circle-fill fs-5"></i>
            </div>
            <div>
                <strong class="text-dark d-block">स्वतंत्र SQLite डेटाबेस व ऑफलाइन सुरक्षितता</strong>
                <small class="text-muted">आपल्या शाळेचा टाचण डेटा स्वतंत्र SQLite डेटाबेसमध्ये सुरक्षित असून मुख्य MySQL शी स्वयंचलित सिंक राहतो.</small>
            </div>
        </div>
        <a href="<?= APP_URL ?>/modules/tachan/day.php" class="btn btn-warning fw-bold text-white px-3 py-2 btn-sm" style="background: #167A5B; border-color: #167A5B; white-space: nowrap;">
            आजचे टाचण पहा <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>

<?php
tachan_layout_end();
