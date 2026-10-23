<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/patterns.php';
$access = require_module_access('que');

$canCreate = ['allowed' => true, 'is_limited' => false, 'count' => 0, 'max' => 5];
$is_limited = false;
$paper_count = 0;
$max_papers = 5;
$is_paper_limit_reached = false;
$school_id = (int)($_SESSION['school_id'] ?? 0);
$allPapers = [];
$stats = ['std' => 0, 'sub' => 0, 'ch' => 0, 'q' => 0];
$bookQ = 0;

try {
    // Delete action
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        $delStmt = ep_db()->prepare('DELETE FROM ep_papers WHERE paper_id = ? AND (school_id = ? OR (school_id IS NULL AND ? = 0))');
        $delStmt->execute([$delId, $school_id, $school_id]);
        $tabParam = !empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '';
        header('Location: index.php' . $tabParam);
        exit;
    }

    $canCreate = ep_can_create_paper();
    $is_limited = $canCreate['is_limited'] ?? false;
    $paper_count = $canCreate['count'] ?? 0;
    $max_papers = $canCreate['max'] ?? 5;
    $is_paper_limit_reached = !$canCreate['allowed'];

    // All papers query for this school
    $paper_stmt = ep_db()->prepare('SELECT p.paper_id, p.title, p.paper_type, p.exam_name, p.std_label, p.exam_date, p.total_marks, p.created_at, p.paper_json, s.name AS standard_name, s.medium, sub.name AS subject_name
        FROM ep_papers p LEFT JOIN ep_standards s ON s.standard_id=p.standard_id LEFT JOIN ep_subjects sub ON sub.subject_id=p.subject_id
        WHERE (p.school_id = ? OR (p.school_id IS NULL AND ? = 0))
        ORDER BY p.created_at DESC');
    $paper_stmt->execute([$school_id, $school_id]);
    $allPapers = $paper_stmt->fetchAll();

    // Stats
    $stats_row = ep_db()->query('SELECT (SELECT COUNT(*) FROM ep_standards) std, (SELECT COUNT(*) FROM ep_subjects) sub, (SELECT COUNT(*) FROM ep_chapters) ch, (SELECT COUNT(*) FROM ep_questions) q')->fetch();
    if ($stats_row) {
        $stats = $stats_row;
    }
    $bookQ = (int)ep_db()->query('SELECT COUNT(*) FROM ep_book_questions')->fetchColumn();
} catch (Throwable $e) {
    error_log("Exam Paper index error: " . $e->getMessage());
}

// Partition papers into the 4 dedicated sub-dashboards
$dailyPapers = [];
$competitivePapers = [];
$assessmentPapers = [];
$subjectwisePapers = [];

foreach ($allPapers as $p) {
    $ptype = $p['paper_type'] ?? '';
    $pdata = json_decode($p['paper_json'], true) ?: [];
    $isDaily = ($ptype === 'daily_study') || ($p['exam_name'] === 'दैनंदिन स्वाध्याय') || (($pdata['exam_type'] ?? '') === 'daily_study');

    if ($isDaily) {
        $dailyPapers[] = $p;
    } elseif ($ptype === 'competitive') {
        $competitivePapers[] = $p;
    } elseif ($ptype === 'assessment') {
        $assessmentPapers[] = $p;
    } else {
        $subjectwisePapers[] = $p;
    }
}

// Active Tab
$activeTab = $_GET['tab'] ?? 'daily';
$validTabs = ['daily', 'competitive', 'assessment', 'subjectwise', 'all'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'daily';
}

$bookTree = ep_book_tree();
$standards = ep_standards();

$epPage = 'papers';
$epTitle = 'प्रश्नपत्रिका डॅशबोर्ड (Exam Paper Dashboard)';
require __DIR__ . '/includes/header.php';
?>

<style>
.dashboard-nav-card {
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
}
.dash-pill-btn {
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.96rem;
    padding: 12px 18px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    display: flex;
    align-items: center;
    gap: 10px;
}
.dash-pill-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.dash-pill-btn.active {
    background: linear-gradient(135deg, #1e40af 0%, #0284c7 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 6px 18px rgba(2,132,199,0.35) !important;
}
.dash-pill-btn .badge {
    font-size: 0.78rem;
    padding: 4px 8px;
}
.dash-pill-btn.active .badge {
    background: rgba(255,255,255,0.25) !important;
    color: #ffffff !important;
}
.sub-dash-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 20px 24px;
    margin-bottom: 24px;
}
.exam-card {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    background: #fff;
    overflow: hidden;
}
.exam-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
.exam-badge-header {
    padding: 14px 18px;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.generator-box {
    background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
    border: 2px dashed #93c5fd;
    border-radius: 14px;
    padding: 24px;
}
.format-badge-lines {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    font-weight: 600;
}
.format-badge-compact {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    font-weight: 600;
}
</style>

<!-- ACCESS / TRIAL BANNERS -->
<?php if (($access['type'] ?? '') === 'trial'): ?>
<div class="alert alert-warning border-warning d-flex flex-wrap align-items-center justify-content-between mb-4 shadow-sm rounded-3 p-3" role="alert">
    <div class="d-flex align-items-center gap-3">
        <i class="bi bi-clock-history fs-2 text-warning flex-shrink-0"></i>
        <div>
            <h5 class="fw-bold text-dark mb-1">१०-दिवसीय मोफत चाचणी (Free Trial) सुरू आहे!</h5>
            <div class="small text-secondary">
                आपली प्रश्नपत्रिका चाचणी सुरू असून <strong><?= (int)$access['days_left'] ?> दिवस शिल्लक</strong> आहेत.
                <span class="badge bg-dark text-warning ms-1">वापर: <?= $paper_count ?> / <?= $max_papers ?> प्रश्नपत्रिका</span>
            </div>
        </div>
    </div>
    <a href="<?= APP_URL ?>/subscription/plans.php?module=que" class="btn btn-sm btn-warning text-dark fw-bold text-nowrap shadow-sm mt-2 mt-sm-0">
        <i class="bi bi-stars me-1"></i> योजना अपग्रेड करा
    </a>
</div>
<?php endif; ?>

<?php if ($is_paper_limit_reached): ?>
<div class="alert alert-danger border-danger d-flex flex-wrap align-items-center justify-content-between mb-4 shadow-sm rounded-3 p-3" role="alert">
    <div class="d-flex align-items-center gap-3">
        <i class="bi bi-exclamation-octagon-fill fs-2 text-danger flex-shrink-0"></i>
        <div>
            <h5 class="fw-bold text-dark mb-1">मोफत प्रश्नपत्रिका मर्यादा पूर्ण झाली आहे (५/५)!</h5>
            <div class="small text-muted">आपण मोफत चाचणी अंतर्गत ५ पैकी ५ प्रश्नपत्रिका तयार केल्या आहेत. १ वर्ष अमर्यादित प्रश्नपत्रिका निर्मितीसाठी कृपया ऑल-इन-वन (₹१,४९९) किंवा प्रश्नपत्रिका स्वतंत्र योजना (₹४९९) निवडा.</div>
        </div>
    </div>
    <a href="<?= APP_URL ?>/subscription/plans.php?module=que" class="btn btn-sm btn-danger text-white fw-bold text-nowrap shadow-sm mt-2 mt-sm-0">
        <i class="bi bi-lightning-charge-fill me-1"></i> अमर्यादितसाठी अपग्रेड करा
    </a>
</div>
<?php endif; ?>

<!-- MULTI-SUB-DASHBOARD NAVIGATION TABS -->
<div class="dashboard-nav-card p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-ui-checks-grid text-primary"></i>
                प्रश्नपत्रिका व स्वाध्याय निर्मिती केंद्र
            </h4>
            <div class="text-muted small mt-1">
                शाळा: <strong><?= h(ep_setting('school_name')) ?></strong> 
                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1"><i class="bi bi-shield-check"></i> वैयक्तिक शाळा खाते सक्रिय</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="settings.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i> शाळा सेटिंग्ज</a>
        </div>
    </div>

    <!-- 4 Dedicated Tabs Navigation -->
    <div class="nav nav-pills nav-fill gap-2" role="tablist">
        <a href="index.php?tab=daily" class="nav-link dash-pill-btn <?= $activeTab === 'daily' ? 'active' : 'bg-light text-dark' ?>">
            <i class="bi bi-calendar2-check-fill fs-5 text-warning"></i>
            <div class="text-start">
                <div class="fw-bold">१. दैनंदिन प्रश्न स्वाध्याय</div>
                <small class="opacity-75" style="font-size:11px;">दैनिक गृहपाठ, स्वाध्याय पत्रके</small>
            </div>
            <span class="badge bg-secondary ms-auto"><?= count($dailyPapers) ?></span>
        </a>

        <a href="index.php?tab=competitive" class="nav-link dash-pill-btn <?= $activeTab === 'competitive' ? 'active' : 'bg-light text-dark' ?>">
            <i class="bi bi-trophy-fill fs-5 text-primary"></i>
            <div class="text-start">
                <div class="fw-bold">२. स्पर्धा परीक्षा</div>
                <small class="opacity-75" style="font-size:11px;">शिष्यवृत्ती, नवोदय, NMMS</small>
            </div>
            <span class="badge bg-secondary ms-auto"><?= count($competitivePapers) ?></span>
        </a>

        <a href="index.php?tab=assessment" class="nav-link dash-pill-btn <?= $activeTab === 'assessment' ? 'active' : 'bg-light text-dark' ?>">
            <i class="bi bi-journal-check fs-5 text-success"></i>
            <div class="text-start">
                <div class="fw-bold">३. आकारिक व संकलित</div>
                <small class="opacity-75" style="font-size:11px;">CCE सत्र परीक्षा</small>
            </div>
            <span class="badge bg-secondary ms-auto"><?= count($assessmentPapers) ?></span>
        </a>

        <a href="index.php?tab=subjectwise" class="nav-link dash-pill-btn <?= $activeTab === 'subjectwise' ? 'active' : 'bg-light text-dark' ?>">
            <i class="bi bi-book-half fs-5 text-danger"></i>
            <div class="text-start">
                <div class="fw-bold">४. विषयनिहाय घटक चाचणी</div>
                <small class="opacity-75" style="font-size:11px;">इ. १ ते ८ (ओळींसह / कॉम्पॅक्ट)</small>
            </div>
            <span class="badge bg-secondary ms-auto"><?= count($subjectwisePapers) ?></span>
        </a>

        <a href="index.php?tab=all" class="nav-link dash-pill-btn <?= $activeTab === 'all' ? 'active' : 'bg-light text-dark' ?>" style="max-width: 170px;">
            <i class="bi bi-folder-fill fs-5 text-secondary"></i>
            <div class="text-start">
                <div class="fw-bold">सर्व पेपर</div>
                <small class="opacity-75" style="font-size:11px;">इतिहास</small>
            </div>
            <span class="badge bg-secondary ms-auto"><?= count($allPapers) ?></span>
        </a>
    </div>
</div>

<!-- ========================================================================= -->
<!-- SUB-DASHBOARD 1: दैनंदिन प्रश्न स्वाध्याय (DAILY QUESTION STUDY) -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'daily'): ?>
<div class="sub-dash-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-calendar2-check-fill"></i> १</span>
            दैनंदिन प्रश्न स्वाध्याय व सराव (Daily Question Study)
        </h4>
        <div class="text-muted">
            दररोज वर्गात सोडवण्यासाठी किंवा गृहपाठासाठी पाठ्यपुस्तकातील स्वाध्याय प्रश्नांची पत्रके एका क्लिकवर तयार करा.
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="homework_new.php" class="btn btn-outline-primary btn-sm fw-semibold"><i class="bi bi-house-check me-1"></i> दैनिक गृहपाठ तयार करा</a>
        <a href="create_paper.php" class="btn btn-outline-danger btn-sm fw-semibold"><i class="bi bi-book-half me-1"></i> नवीन घटक चाचणी</a>
    </div>
</div>

<!-- Quick Daily Worksheet Generator Widget -->
<div class="generator-box mb-4 shadow-sm">
    <div class="row align-items-center mb-3">
        <div class="col-md-8">
            <h5 class="fw-bold text-primary mb-1"><i class="bi bi-lightning-charge-fill text-warning"></i> झटपट स्वाध्याय पत्रक निर्मिती (Quick Worksheet Generator)</h5>
            <div class="text-muted small">इयत्ता, विषय आणि घटक निवडा — संगणक थेट योग्य स्वाध्याय प्रश्न निवडून विद्यार्थ्यांसाठी उत्तर-लेखन ओळींसह किंवा कॉम्पॅक्ट पत्रक तयार करेल.</div>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2 fw-semibold">
                <i class="bi bi-stars"></i> प्रश्न प्रकार मिक्स न होता अचूक गटबद्ध
            </span>
        </div>
    </div>

    <form id="dailyStudyForm" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-bold mb-1">१. इयत्ता (Class)</label>
            <select class="form-select" id="dailyStd" required>
                <?php foreach ($bookTree as $st): ?>
                    <option value="<?= (int)$st['standard'] ?>">इयत्ता <?= (int)$st['standard'] ?> ली/वी</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-bold mb-1">२. विषय (Subject)</label>
            <select class="form-select" id="dailySub" required>
                <!-- Populated via JS -->
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label small fw-bold mb-1">३. पाठ / घटक (Chapter / Topic)</label>
            <select class="form-select" id="dailyChapter" required>
                <!-- Populated via JS -->
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label small fw-bold mb-1">४. प्रश्नांची संख्या (Question Count)</label>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm flex-fill btn-daily-cnt active" data-cnt="5">५ प्रश्न</button>
                <button type="button" class="btn btn-outline-primary btn-sm flex-fill btn-daily-cnt" data-cnt="10">१० प्रश्न</button>
                <button type="button" class="btn btn-outline-primary btn-sm flex-fill btn-daily-cnt" data-cnt="15">१५ प्रश्न</button>
                <button type="button" class="btn btn-outline-primary btn-sm flex-fill btn-daily-cnt" data-cnt="20">२० प्रश्न</button>
                <input type="hidden" id="dailyCount" value="5">
            </div>
        </div>

        <div class="col-md-5">
            <label class="form-label small fw-bold mb-1">५. पत्रकाचे स्वरूप (Writing Lines Option)</label>
            <select class="form-select" id="dailyFormat">
                <option value="lines" selected>✍️ उत्तर-लेखन ओळींसह (Dotted Lines under Questions)</option>
                <option value="standard">📄 केवळ प्रश्नपत्रिका (Compact - No Writing Lines)</option>
            </select>
            <div class="form-text small" style="font-size:11px;">
                <i class="bi bi-info-circle"></i> टीप: बहुपर्यायी प्रश्नांसाठी (MCQs) रिकाम्या ओळी न येता फक्त पर्याय कंस <b>[ &nbsp;&nbsp; ]</b> येतो.
            </div>
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm py-2" id="btnGenDaily">
                <i class="bi bi-printer-fill me-1"></i> स्वाध्याय पत्रक तयार करा
            </button>
        </div>
    </form>
    <div id="dailyStudyMsg" class="mt-2"></div>
</div>

<!-- Quick Tools Cards Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary p-3 fs-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                    <i class="bi bi-house-door-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">दैनिक गृहपाठ (Daily Homework)</h6>
                    <p class="text-muted small mb-1">विद्यार्थ्यांना दररोजचा स्वाध्याय द्या व ऑनलाईन क्विझ कोड तयार करा.</p>
                    <a href="homework_new.php" class="btn btn-link btn-sm p-0 fw-semibold text-decoration-none">नवीन गृहपाठ जोडा &rarr;</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger-subtle text-danger p-3 fs-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                    <i class="bi bi-book-half"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">विषयनिहाय घटक चाचणी (Unit Test)</h6>
                    <p class="text-muted small mb-1">इ. १ ते ८ मधील घटकांची उत्तर-लेखन ओळींसह किंवा कॉम्पॅक्ट चाचणी बनवा.</p>
                    <a href="create_paper.php" class="btn btn-link btn-sm p-0 fw-semibold text-decoration-none text-danger">घटक चाचणी बनवा &rarr;</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success-subtle text-success p-3 fs-3 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                    <i class="bi bi-journal-check"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">सत्र १ व २ संकलित / आकारिक परीक्षा</h6>
                    <p class="text-muted small mb-1">CCE मूल्यमापनानुसार प्रथम व द्वितीय सत्र लेखी व तोंडी परीक्षा पेपर्स.</p>
                    <a href="assessment_paper.php" class="btn btn-link btn-sm p-0 fw-semibold text-decoration-none text-success">सत्र परीक्षा बनवा &rarr;</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Saved Daily Study Papers Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-files me-1 text-primary"></i> तयार केलेली दैनंदिन स्वाध्याय पत्रके (Saved Daily Worksheets)</h6>
        <span class="badge bg-light text-dark border"><?= count($dailyPapers) ?> पत्रके</span>
    </div>
    <div class="table-responsive">
        <?php if (!$dailyPapers): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                अद्याप कोणतेही दैनंदिन स्वाध्याय पत्रक तयार केलेले नाही.<br>
                वरील <strong>"झटपट स्वाध्याय पत्रक निर्मिती"</strong> बॉक्समधून इयत्ता व घटक निवडून पहिले स्वाध्याय पत्रक तयार करा.
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>पत्रक शीर्षक</th>
                        <th>इयत्ता</th>
                        <th>विषय</th>
                        <th>दिनांक</th>
                        <th>स्वरूप</th>
                        <th>गुण</th>
                        <th class="text-end">कृती (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dailyPapers as $p): 
                    $pm = json_decode($p['paper_json'], true) ?: [];
                    $fmt = $pm['format'] ?? 'lines';
                ?>
                    <tr>
                        <td><?= (int)$p['paper_id'] ?></td>
                        <td>
                            <strong class="text-dark"><?= h($p['title']) ?></strong>
                            <div class="small text-muted"><?= h($p['exam_name']) ?></div>
                        </td>
                        <td><span class="badge bg-secondary"><?= h($p['std_label'] ?: ($pm['standard'] ? 'इ. ' . $pm['standard'] : '')) ?></span></td>
                        <td><?= h($pm['subject'] ?? $p['subject_name']) ?></td>
                        <td><?= $p['exam_date'] ? h(date('d-m-Y', strtotime($p['exam_date']))) : '-' ?></td>
                        <td>
                            <?php if ($fmt === 'lines'): ?>
                                <span class="badge format-badge-lines"><i class="bi bi-pencil"></i> ओळींसह</span>
                            <?php else: ?>
                                <span class="badge format-badge-compact"><i class="bi bi-file-text"></i> कॉम्पॅक्ट</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="fw-bold text-primary"><?= (float)$p['total_marks'] ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&format=lines" target="_blank" title="उत्तर-लेखन ओळींसह प्रिंट करा">
                                <i class="bi bi-pencil-square"></i> ओळींसह प्रिंट
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&format=standard" target="_blank" title="कॉम्पॅक्ट प्रिंट करा">
                                <i class="bi bi-printer"></i> कॉम्पॅक्ट
                            </a>
                            <a class="btn btn-sm btn-outline-success" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&key=1" target="_blank" title="उत्तरतालिका">
                                <i class="bi bi-key"></i> उत्तरतालिका
                            </a>
                            <a class="btn btn-sm btn-outline-primary" href="export_docx.php?id=<?= (int)$p['paper_id'] ?>" title="Word (.docx) फाईल डाऊनलोड करा" style="background:#2b579a; color:#fff; border-color:#2b579a;">
                                <i class="bi bi-file-earmark-word"></i> Word (.docx)
                            </a>
                            <form method="post" class="d-inline" onsubmit="return confirm('हे स्वाध्याय पत्रक हटवायचे आहे का?')">
                                <input type="hidden" name="delete_id" value="<?= (int)$p['paper_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="हटवा"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


<!-- ========================================================================= -->
<!-- SUB-DASHBOARD 2: स्पर्धा परीक्षा (COMPETITIVE EXAM - EXAMWISE DISTRIBUTION) -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'competitive'): ?>
<div class="sub-dash-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white px-2 py-1"><i class="bi bi-trophy-fill"></i> २</span>
            स्पर्धा परीक्षा दालन (Competitive Exam - Examwise Distribution)
        </h4>
        <div class="text-muted">
            महाराष्ट्र राज्य परीक्षा परिषद (MSCE Pune) व नवोदय अधिकृत पॅटर्ननुसार विषय व विभागनिहाय गुण विभागणी.
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="competitive_paper.php" class="btn btn-primary btn-sm fw-semibold"><i class="bi bi-plus-circle me-1"></i> सानुकूल स्पर्धा पेपर बनवा</a>
    </div>
</div>

<!-- EXAM DISTRIBUTION CARDS GRID -->
<div class="row g-4 mb-4">
    <!-- Card 1: 5th Scholarship -->
    <div class="col-lg-6">
        <div class="exam-card h-100 shadow-sm">
            <div class="exam-badge-header bg-primary text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-award-fill fs-5 text-warning"></i>
                    <span>४ थी पूर्व उच्च प्राथमिक शिष्यवृत्ती परीक्षा (Pre-UPSS)</span>
                </div>
                <span class="badge bg-white text-primary">इयत्ता ४ थी</span>
            </div>
            <div class="p-3">
                <div class="small text-muted mb-2">परीक्षेचे स्वरूप: २ स्वतंत्र पेपर | एकूण १५० प्रश्न | ३०० गुण</div>
                
                <!-- Paper 1 -->
                <div class="border rounded p-2 mb-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">पेपर क्रमांक १: प्रथम भाषा व गणित</strong>
                        <span class="badge bg-primary">७५ प्रश्न | १५० गुण (९० मिनिटे)</span>
                    </div>
                    <div class="small text-muted mb-2">
                        &bull; विभाग १: प्रथम भाषा (मराठी/उर्दू/हिंदी) - २५ प्रश्न (५० गुण)<br>
                        &bull; विभाग २: गणित - ५० प्रश्न (१०० गुण) &middot; प्रत्येक प्रश्नास २ गुण
                    </div>
                    <a href="competitive_paper.php?pattern=sch4_p1" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-lightning-charge-fill me-1"></i> पेपर १ तयार करा (प्रथम भाषा व गणित)
                    </a>
                </div>

                <!-- Paper 2 -->
                <div class="border rounded p-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">पेपर क्रमांक २: तृतीय भाषा व बुद्धिमत्ता चाचणी</strong>
                        <span class="badge bg-primary">७५ प्रश्न | १५० गुण (९० मिनिटे)</span>
                    </div>
                    <div class="small text-muted mb-2">
                        &bull; विभाग १: तृतीय भाषा (इंग्रजी) - २५ प्रश्न (५० गुण)<br>
                        &bull; विभाग २: बुद्धिमत्ता चाचणी - ५० प्रश्न (१०० गुण) &middot; प्रत्येक प्रश्नास २ गुण
                    </div>
                    <a href="competitive_paper.php?pattern=sch4_p2" class="btn btn-outline-primary btn-sm fw-semibold">
                        <i class="bi bi-lightning-charge-fill me-1"></i> पेपर २ तयार करा (इंग्रजी व बुद्धिमत्ता)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: 8th Scholarship -->
    <div class="col-lg-6">
        <div class="exam-card h-100 shadow-sm">
            <div class="exam-badge-header bg-dark text-white" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%) !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-award-fill fs-5 text-warning"></i>
                    <span>७ वी पूर्व माध्यमिक शिष्यवृत्ती परीक्षा (Pre-MSS)</span>
                </div>
                <span class="badge bg-white text-dark">इयत्ता ७ वी</span>
            </div>
            <div class="p-3">
                <div class="small text-muted mb-2">परीक्षेचे स्वरूप: २ स्वतंत्र पेपर | एकूण १५० प्रश्न | ३०० गुण</div>
                
                <!-- Paper 1 -->
                <div class="border rounded p-2 mb-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">पेपर क्रमांक १: प्रथम भाषा व गणित</strong>
                        <span class="badge bg-dark">७५ प्रश्न | १५० गुण (९० मिनिटे)</span>
                    </div>
                    <div class="small text-muted mb-2">
                        &bull; विभाग १: प्रथम भाषा (मराठी) - २५ प्रश्न (५० गुण)<br>
                        &bull; विभाग २: गणित - ५० प्रश्न (१०० गुण) &middot; प्रत्येक प्रश्नास २ गुण
                    </div>
                    <a href="competitive_paper.php?pattern=sch7_p1" class="btn btn-dark btn-sm fw-semibold">
                        <i class="bi bi-lightning-charge-fill me-1"></i> पेपर १ तयार करा (प्रथम भाषा व गणित)
                    </a>
                </div>

                <!-- Paper 2 -->
                <div class="border rounded p-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">पेपर क्रमांक २: तृतीय भाषा व बुद्धिमत्ता चाचणी</strong>
                        <span class="badge bg-dark">७५ प्रश्न | १५० गुण (९० मिनिटे)</span>
                    </div>
                    <div class="small text-muted mb-2">
                        &bull; विभाग १: तृतीय भाषा (इंग्रजी) - २५ प्रश्न (५० गुण)<br>
                        &bull; विभाग २: बुद्धिमत्ता चाचणी - ५० प्रश्न (१०० गुण) &middot; प्रत्येक प्रश्नास २ गुण
                    </div>
                    <a href="competitive_paper.php?pattern=sch7_p2" class="btn btn-outline-dark btn-sm fw-semibold">
                        <i class="bi bi-lightning-charge-fill me-1"></i> पेपर २ तयार करा (इंग्रजी व बुद्धिमत्ता)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: 5th Navodaya (JNVST) -->
    <div class="col-lg-6">
        <div class="exam-card h-100 shadow-sm border-warning">
            <div class="exam-badge-header bg-warning text-dark">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-mortarboard-fill fs-5"></i>
                    <span>जवाहर नवोदय विद्यालय प्रवेश परीक्षा (JNVST - ५ वी)</span>
                </div>
                <span class="badge bg-dark text-warning">८० प्रश्न / १०० गुण</span>
            </div>
            <div class="p-3">
                <div class="small text-muted mb-2">अधिकृत ३-विभाग पॅटर्न &middot; २ तास (१२० मिनिटे) &middot; १.२५ गुण/प्रश्न &middot; नो निगेटिव्ह मार्किंग</div>
                <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered mb-0 small text-center">
                        <thead class="table-warning">
                            <tr><th>विभाग</th><th>विषय / क्षमता चाचणी</th><th>प्रश्न</th><th>गुण</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>विभाग १</td><td class="text-start">मानसिक क्षमता चाचणी (Mental Ability)</td><td>४०</td><td>५०</td></tr>
                            <tr><td>विभाग २</td><td class="text-start">अंकगणित चाचणी (Arithmetic)</td><td>२०</td><td>२५</td></tr>
                            <tr><td>विभाग ३</td><td class="text-start">भाषा चाचणी (Language Comprehension)</td><td>२०</td><td>२५</td></tr>
                            <tr class="table-light fw-bold"><td>एकूण</td><td class="text-start">संपूर्ण नवोदय प्रवेश परीक्षा</td><td>८०</td><td>१००</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2">
                    <a href="competitive_paper.php?pattern=jnv5_full" class="btn btn-warning text-dark fw-bold btn-sm flex-fill">
                        <i class="bi bi-star-fill me-1"></i> संपूर्ण ८० प्रश्न पेपर तयार करा (100 Marks)
                    </a>
                    <a href="competitive_paper.php?pattern=jnv5_practice" class="btn btn-outline-warning text-dark btn-sm flex-fill">
                        <i class="bi bi-play-circle me-1"></i> ४० प्रश्न सराव संच (50 Marks)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: 8th NMMS -->
    <div class="col-lg-6">
        <div class="exam-card h-100 shadow-sm border-info">
            <div class="exam-badge-header bg-info text-dark">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-briefcase-fill fs-5"></i>
                    <span>८ वी राष्ट्रीय आर्थिक दुर्बल घटक शिष्यवृत्ती (NMMS)</span>
                </div>
                <span class="badge bg-dark text-info">१८० प्रश्न / १८० गुण</span>
            </div>
            <div class="p-3">
                <div class="small text-muted mb-2">महाराष्ट्र राज्य परीक्षा परिषद (MSCE) पॅटर्न &middot; प्रत्येकी ९० गुण</div>

                <!-- MAT -->
                <div class="border rounded p-2 mb-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">पेपर १: मानसिक क्षमता चाचणी (MAT)</strong>
                        <span class="badge bg-info text-dark">९० प्रश्न | ९० गुण</span>
                    </div>
                    <div class="small text-muted mb-2">बुद्धिमत्ता, कार्यकारणभाव, तर्क, पृथक्करण आणि क्रमसंबंध आधारित प्रश्न.</div>
                    <a href="competitive_paper.php?pattern=nmms_mat" class="btn btn-info text-dark fw-bold btn-sm">
                        <i class="bi bi-lightning-charge-fill me-1"></i> MAT पेपर तयार करा (९० प्रश्न)
                    </a>
                </div>

                <!-- SAT -->
                <div class="border rounded p-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-dark">पेपर २: शालेय क्षमता चाचणी (SAT)</strong>
                        <span class="badge bg-info text-dark">९० प्रश्न | ९० गुण</span>
                    </div>
                    <div class="small text-muted mb-2">
                        &bull; सामान्य विज्ञान: ३५ प्रश्न (३५ गुण) &middot; &bull; समाजशास्त्र: ३५ प्रश्न (३५ गुण) &middot; &bull; गणित: २० प्रश्न (२० गुण)
                    </div>
                    <a href="competitive_paper.php?pattern=nmms_sat" class="btn btn-outline-info text-dark fw-bold btn-sm">
                        <i class="bi bi-lightning-charge-fill me-1"></i> SAT पेपर तयार करा (विज्ञान ३५ + समाजशास्त्र ३५ + गणित २०)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Single Topic Practice Tests Card -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body p-3">
        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-bullseye text-danger me-1"></i> घटकनिहाय स्पर्धा परीक्षा सराव चाचण्या (Single Topic Practice Tests):</h6>
        <div class="d-flex flex-wrap gap-2">
            <a href="competitive_paper.php?pattern=topic_sch4_20" class="btn btn-sm btn-outline-primary bg-white">
                <i class="bi bi-play-circle me-1"></i> ५ वी शिष्यवृत्ती घटक चाचणी (२० प्रश्न / ४० गुण)
            </a>
            <a href="competitive_paper.php?pattern=topic_sch7_20" class="btn btn-sm btn-outline-dark bg-white">
                <i class="bi bi-play-circle me-1"></i> ८ वी शिष्यवृत्ती घटक चाचणी (२० प्रश्न / ४० गुण)
            </a>
            <a href="competitive_paper.php?pattern=topic_nmms8_25" class="btn btn-sm btn-outline-info text-dark bg-white">
                <i class="bi bi-play-circle me-1"></i> ८ वी NMMS घटक चाचणी (२५ प्रश्न / २५ गुण)
            </a>
            <a href="competitive_paper.php" class="btn btn-sm btn-outline-secondary bg-white">
                <i class="bi bi-sliders me-1"></i> इतर घटक व प्रश्न संख्या निवडून पेपर बनवा
            </a>
        </div>
    </div>
</div>

<!-- Saved Competitive Papers Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy me-1 text-primary"></i> तयार केलेल्या स्पर्धा परीक्षा प्रश्नपत्रिका (Saved Competitive Papers)</h6>
        <span class="badge bg-light text-dark border"><?= count($competitivePapers) ?> पेपर</span>
    </div>
    <div class="table-responsive">
        <?php if (!$competitivePapers): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-trophy fs-1 d-block mb-2 text-secondary"></i>
                अद्याप कोणतीही स्पर्धा परीक्षा प्रश्नपत्रिका तयार केलेली नाही.<br>
                वरील कोणत्याही परीक्षा कार्डवरील <strong>"पेपर तयार करा"</strong> बटनावर क्लिक करा.
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>परीक्षा नाव / शीर्षक</th>
                        <th>वर्ग</th>
                        <th>दिनांक</th>
                        <th>एकूण गुण</th>
                        <th>तयार केले</th>
                        <th class="text-end">कृती (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($competitivePapers as $p): ?>
                    <tr>
                        <td><?= (int)$p['paper_id'] ?></td>
                        <td>
                            <strong class="text-dark"><?= h($p['title']) ?></strong>
                            <div class="small text-muted"><?= h($p['exam_name']) ?></div>
                        </td>
                        <td><span class="badge bg-warning text-dark"><?= h($p['std_label']) ?></span></td>
                        <td><?= $p['exam_date'] ? h(date('d-m-Y', strtotime($p['exam_date']))) : '-' ?></td>
                        <td><span class="fw-bold text-primary"><?= (float)$p['total_marks'] ?></span></td>
                        <td><small class="text-muted"><?= h(date('d-m-Y H:i', strtotime($p['created_at']))) ?></small></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>" target="_blank" title="प्रिंट पेपर"><i class="bi bi-printer"></i> प्रिंट</a>
                            <a class="btn btn-sm btn-outline-success" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&answers=1" target="_blank" title="उत्तरतालिका"><i class="bi bi-key"></i> उत्तरतालिका</a>
                            <a class="btn btn-sm btn-outline-danger" href="omr_view.php?id=<?= (int)$p['paper_id'] ?>" target="_blank" title="ओ.एम.आर. उत्तरपत्रिका (OMR Sheet)"><i class="bi bi-grid-3x3-gap-fill"></i> OMR Sheet</a>
                            <a class="btn btn-sm btn-outline-secondary" href="competitive_paper.php?edit=<?= (int)$p['paper_id'] ?>" title="बदल करा"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline" onsubmit="return confirm('ही स्पर्धा परीक्षा प्रश्नपत्रिका हटवायची आहे का?')">
                                <input type="hidden" name="delete_id" value="<?= (int)$p['paper_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="हटवा"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


<!-- ========================================================================= -->
<!-- SUB-DASHBOARD 3: आकारिक व संकलित मूल्यमापन (AKARIK & SANKALIT EXAM) -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'assessment'): ?>
<div class="sub-dash-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="badge bg-success text-white px-2 py-1"><i class="bi bi-journal-check"></i> ३</span>
            आकारिक व संकलित मूल्यमापन (CCE - Continuous &amp; Comprehensive Evaluation)
        </h4>
        <div class="text-muted">
            महाराष्ट्र राज्य प्राथमिक शिक्षण परिषद पॅटर्ननुसार सत्र १ व २ लेखी व तोंडी परीक्षा प्रश्नपत्रिका निर्मिती.
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="assessment_paper.php" class="btn btn-success btn-sm fw-semibold"><i class="bi bi-plus-circle me-1"></i> नवीन आकारिक/संकलित पेपर</a>
    </div>
</div>

<!-- Assessment Blueprint & Distribution Guide -->
<div class="row g-4 mb-4">
    <!-- Summative (संकलित) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-success">
            <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-bar-graph me-1"></i> संकलित मूल्यमापन (Summative Assessment)</span>
                <span class="badge bg-white text-success">सत्र १ व सत्र २</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">सत्र १ (प्रथम सत्र) परीक्षा पहिल्या ५०% अभ्यासक्रमावर आणि सत्र २ (द्वितीय सत्र) परीक्षा उर्वरित ५०% अभ्यासक्रमावर घेतली जाते. यामध्ये लेखी व तोंडी परीक्षा एकत्रित असतात.</p>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered small text-center mb-0">
                        <thead class="table-light">
                            <tr><th>इयत्ता</th><th>लेखी गुण</th><th>तोंडी गुण</th><th>एकूण गुण</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>इ. १ ली व २ री</td><td>१० गुण</td><td>१० गुण</td><td class="fw-bold">२० गुण</td></tr>
                            <tr><td>इ. ३ री ते ५ वी</td><td>३० / ४० गुण</td><td>१० गुण</td><td class="fw-bold">४० / ५० गुण</td></tr>
                            <tr><td>इ. ६ वी ते ८ वी</td><td>४० / ५० गुण</td><td>१० गुण</td><td class="fw-bold">५० / ६० गुण</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2">
                    <a href="assessment_paper.php?exam_type=sankalit&test_no=1" class="btn btn-success btn-sm flex-fill fw-semibold">
                        <i class="bi bi-calendar-event me-1"></i> प्रथम सत्र संकलित १ बनवा (Term 1)
                    </a>
                    <a href="assessment_paper.php?exam_type=sankalit&test_no=2" class="btn btn-outline-success btn-sm flex-fill fw-semibold">
                        <i class="bi bi-calendar2-check me-1"></i> द्वितीय सत्र संकलित २ बनवा (Term 2)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Formative (आकारिक) -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-primary">
            <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pencil-square me-1"></i> आकारिक मूल्यमापन (Formative Assessment)</span>
                <span class="badge bg-white text-primary">चाचणी १ व २</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">आकारिक मूल्यमापनामध्ये दैनंदिन निरीक्षण, तोंडी काम, प्रात्यक्षिक/प्रयोग, उपक्रम/प्रकल्प आणि स्वाध्याय/वर्गकार्य या ५ साधनांचा समावेश होतो. उत्तर-लेखन ओळींसह छापता येते.</p>
                <div class="p-2 border rounded bg-light mb-3 small">
                    <strong>५ प्रमुख मूल्यमापन साधने:</strong>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <span class="badge bg-secondary-subtle text-secondary border">१. दैनंदिन निरीक्षण</span>
                        <span class="badge bg-secondary-subtle text-secondary border">२. तोंडी काम</span>
                        <span class="badge bg-secondary-subtle text-secondary border">३. प्रात्यक्षिक / प्रयोग</span>
                        <span class="badge bg-secondary-subtle text-secondary border">४. उपक्रम / प्रकल्प</span>
                        <span class="badge bg-secondary-subtle text-secondary border">५. स्वाध्याय / चाचणी</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="assessment_paper.php?exam_type=aakarik&test_no=1" class="btn btn-primary btn-sm flex-fill fw-semibold">
                        <i class="bi bi-pencil me-1"></i> आकारिक मूल्यमापन चाचणी १ बनवा
                    </a>
                    <a href="assessment_paper.php?exam_type=aakarik&test_no=2" class="btn btn-outline-primary btn-sm flex-fill fw-semibold">
                        <i class="bi bi-pencil me-1"></i> आकारिक मूल्यमापन चाचणी २ बनवा
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Saved Assessment Papers Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-check me-1 text-success"></i> तयार केलेल्या संकलित व आकारिक प्रश्नपत्रिका (Saved Assessment Papers)</h6>
        <span class="badge bg-light text-dark border"><?= count($assessmentPapers) ?> पेपर</span>
    </div>
    <div class="table-responsive">
        <?php if (!$assessmentPapers): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                अद्याप कोणतीही संकलित किंवा आकारिक प्रश्नपत्रिका तयार केलेली नाही.<br>
                वरील कार्ड्समधून <strong>"प्रथम सत्र संकलित १"</strong> किंवा <strong>"आकारिक चाचणी"</strong> बटनावर क्लिक करा.
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>प्रश्नपत्रिका शीर्षक</th>
                        <th>प्रकार</th>
                        <th>इयत्ता</th>
                        <th>विषय</th>
                        <th>दिनांक</th>
                        <th>गुण (लेखी+तोंडी)</th>
                        <th class="text-end">कृती (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assessmentPapers as $p): 
                    $pm = json_decode($p['paper_json'], true) ?: [];
                    $isAakarik = ($pm['exam_type'] ?? '') === 'aakarik';
                    $fmt = $pm['format'] ?? 'lines';
                ?>
                    <tr>
                        <td><?= (int)$p['paper_id'] ?></td>
                        <td><strong class="text-dark"><?= h($p['title']) ?></strong></td>
                        <td>
                            <?php if ($isAakarik): ?>
                                <span class="badge bg-primary">आकारिक चाचणी <?= (int)($pm['test_no'] ?? 1) ?></span>
                            <?php else: ?>
                                <span class="badge bg-success">संकलित सत्र <?= (int)($pm['test_no'] ?? 1) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= h($p['std_label'] ?: 'इ. ' . ($pm['standard'] ?? '')) ?></span></td>
                        <td><?= h($pm['subject'] ?? '') ?> <small class="text-muted">(<?= h($pm['medium'] ?? '') ?>)</small></td>
                        <td><?= $p['exam_date'] ? h(date('d-m-Y', strtotime($p['exam_date']))) : '-' ?></td>
                        <td>
                            <span class="fw-bold text-dark"><?= (float)$p['total_marks'] ?></span>
                            <?php if (!empty($pm['oral_marks'])): ?>
                                <small class="text-muted">(लेखी: <?= (float)$p['total_marks'] - (float)$pm['oral_marks'] ?>, तोंडी: <?= (float)$pm['oral_marks'] ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&format=lines" target="_blank" title="उत्तर-लेखन ओळींसह प्रिंट करा">
                                <i class="bi bi-pencil-square"></i> ओळींसह प्रिंट
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&format=standard" target="_blank" title="कॉम्पॅक्ट प्रिंट करा">
                                <i class="bi bi-printer"></i> कॉम्पॅक्ट
                            </a>
                            <a class="btn btn-sm btn-outline-success" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&key=1" target="_blank" title="उत्तरतालिका">
                                <i class="bi bi-key"></i> उत्तरतालिका
                            </a>
                            <a class="btn btn-sm btn-outline-primary" href="export_docx.php?id=<?= (int)$p['paper_id'] ?>" title="Word (.docx) फाईल डाऊनलोड करा" style="background:#2b579a; color:#fff; border-color:#2b579a;">
                                <i class="bi bi-file-earmark-word"></i> Word (.docx)
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="assessment_paper.php?edit=<?= (int)$p['paper_id'] ?>" title="बदल करा"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline" onsubmit="return confirm('ही संकलित/आकारिक प्रश्नपत्रिका हटवायची आहे का?')">
                                <input type="hidden" name="delete_id" value="<?= (int)$p['paper_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="हटवा"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


<!-- ========================================================================= -->
<!-- SUB-DASHBOARD 4: विषयनिहाय घटक चाचणी (SUBJECT-WISE UNIT TEST) -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'subjectwise'): ?>
<div class="sub-dash-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-book-half"></i> ४</span>
            विषयनिहाय घटक चाचणी व सराव परीक्षा (Subject-wise Unit Tests)
        </h4>
        <div class="text-muted">
            इयत्ता १ ली ते ८ वी सर्व विषयांच्या घटक चाचण्या, सराव परीक्षा व सेमिस्टर पेपर्स (उत्तर-लेखन ओळींसह किंवा कॉम्पॅक्ट).
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="create_paper.php" class="btn btn-danger btn-sm fw-semibold"><i class="bi bi-plus-circle me-1"></i> नवीन घटक चाचणी तयार करा</a>
    </div>
</div>

<!-- Highlight Banner: Writing Lines vs Compact Options -->
<div class="alert alert-primary border-primary shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-pencil-square fs-2 text-primary flex-shrink-0"></i>
        <div>
            <h6 class="fw-bold text-dark mb-1">विद्यार्थी उत्तर-लेखन ओळींचे विशेष वैशिष्ट्य (Student Writing Lines Feature):</h6>
            <div class="small text-secondary">
                घटक चाचणी तयार करताना आपण २ पर्यायांपैकी एक निवडू शकता:<br>
                <strong>१) ✍️ उत्तर-लेखन ओळींसह (Lines Format):</strong> प्रश्नांखाली विद्यार्थ्यांना उत्तरे लिहिण्यासाठी आवश्यकतेनुसार ठिपक्यांच्या ओळी (Dotted Lines) किंवा मोकळी जागा दिली जाते. <span class="badge bg-warning text-dark">महत्त्वाचे:</span> <strong>बहुपर्यायी प्रश्नांसाठी (MCQs) रिकाम्या ओळी येत नाहीत, फक्त पर्याय लिहिण्यासाठी कंस [ &nbsp;&nbsp; ] दिला जातो.</strong><br>
                <strong>२) 📄 केवळ प्रश्नपत्रिका (Compact Format):</strong> कागदाची बचत करण्यासाठी कोणतीही रिकामी जागा न सोडता प्रश्नपत्रिका छापली जाते (जेव्हा विद्यार्थी स्वतंत्र उत्तरपत्रिकेत उत्तरे लिहितात).
            </div>
        </div>
    </div>
</div>

<!-- Class & Subject Grid Matrix -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-grid-3x3-gap-fill text-danger me-1"></i> इयत्ता व विषय निवडून थेट चाचणी सुरू करा (Quick Launch Matrix)</h6>
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            <?php 
            $groupedStds = [];
            foreach ($standards as $s) {
                if (preg_match('/^(?:इ\.?\s*)?(\d+)/u', $s['name'], $m)) {
                    $cNum = (int)$m[1];
                    if ($cNum >= 1 && $cNum <= 8) {
                        $groupedStds[$cNum][] = $s;
                    }
                }
            }
            ksort($groupedStds);
            ?>

            <?php foreach ($groupedStds as $cNum => $stList): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0">इयत्ता <?= $cNum ?> ली/वी</h6>
                            <span class="badge bg-primary">Std <?= $cNum ?></span>
                        </div>
                        <div class="d-grid gap-1 mt-2">
                            <?php foreach ($stList as $stItem): 
                                $subs = ep_subjects((int)$stItem['standard_id']);
                                if (!$subs) continue;
                            ?>
                                <div class="small fw-semibold text-secondary mt-1"><?= h(ep_format_standard_name($stItem)) ?>:</div>
                                <?php foreach ($subs as $subItem): ?>
                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                        <a href="create_paper.php?standard_id=<?= (int)$stItem['standard_id'] ?>&subject_id=<?= (int)$subItem['subject_id'] ?>&format=lines" class="btn btn-outline-primary text-start text-truncate" title="<?= h($subItem['name']) ?> - ओळींसह चाचणी">
                                            ✍️ <?= h($subItem['name']) ?>
                                        </a>
                                        <a href="create_paper.php?standard_id=<?= (int)$stItem['standard_id'] ?>&subject_id=<?= (int)$subItem['subject_id'] ?>&format=standard" class="btn btn-outline-secondary px-2" title="कॉम्पॅक्ट स्वरूपात">
                                            📄
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Saved Subject-wise Papers Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-book me-1 text-danger"></i> तयार केलेल्या विषयनिहाय घटक चाचण्या (Saved Subject-wise Tests)</h6>
        <span class="badge bg-light text-dark border"><?= count($subjectwisePapers) ?> चाचण्या</span>
    </div>
    <div class="table-responsive">
        <?php if (!$subjectwisePapers): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                अद्याप कोणतीही विषयनिहाय घटक चाचणी तयार केलेली नाही.<br>
                वरील मॅट्रिक्समधून कोणत्याही विषयावर क्लिक करा किंवा <strong>"नवीन घटक चाचणी तयार करा"</strong> बटनावर क्लिक करा.
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>चाचणी शीर्षक</th>
                        <th>इयत्ता</th>
                        <th>विषय</th>
                        <th>दिनांक</th>
                        <th>स्वरूप</th>
                        <th>गुण</th>
                        <th class="text-end">कृती (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($subjectwisePapers as $p): 
                    $pm = json_decode($p['paper_json'], true) ?: [];
                    $fmt = $pm['format'] ?? 'lines';
                ?>
                    <tr>
                        <td><?= (int)$p['paper_id'] ?></td>
                        <td><strong class="text-dark"><?= h($p['title']) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= h($p['standard_name']) ?> <small>(<?= h($p['medium']) ?>)</small></span></td>
                        <td><?= h($p['subject_name']) ?></td>
                        <td><?= $p['exam_date'] ? h(date('d-m-Y', strtotime($p['exam_date']))) : '-' ?></td>
                        <td>
                            <?php if ($fmt === 'lines'): ?>
                                <span class="badge format-badge-lines"><i class="bi bi-pencil"></i> ओळींसह</span>
                            <?php else: ?>
                                <span class="badge format-badge-compact"><i class="bi bi-file-text"></i> कॉम्पॅक्ट</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="fw-bold text-primary"><?= (float)$p['total_marks'] ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&format=lines" target="_blank" title="उत्तर-लेखन ओळींसह प्रिंट करा">
                                <i class="bi bi-pencil-square"></i> ओळींसह प्रिंट
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&format=standard" target="_blank" title="कॉम्पॅक्ट प्रिंट करा">
                                <i class="bi bi-printer"></i> कॉम्पॅक्ट
                            </a>
                            <a class="btn btn-sm btn-outline-success" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&answers=1" target="_blank" title="उत्तरतालिका">
                                <i class="bi bi-key"></i> उत्तरतालिका
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="create_paper.php?edit=<?= (int)$p['paper_id'] ?>" title="बदल करा"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline" onsubmit="return confirm('ही घटक चाचणी हटवायची आहे का?')">
                                <input type="hidden" name="delete_id" value="<?= (int)$p['paper_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="हटवा"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


<!-- ========================================================================= -->
<!-- SUB-DASHBOARD 5: सर्व प्रश्नपत्रिका (ALL SAVED PAPERS MASTER VIEW) -->
<!-- ========================================================================= -->
<?php if ($activeTab === 'all'): ?>
<div class="sub-dash-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <span class="badge bg-secondary text-white px-2 py-1"><i class="bi bi-folder-fill"></i></span>
            सर्व तयार केलेल्या प्रश्नपत्रिका (All Saved Papers)
        </h4>
        <div class="text-muted">
            शाळेने आतापर्यंत तयार केलेल्या सर्व स्वाध्याय, स्पर्धा परीक्षा, संकलित व विषयनिहाय प्रश्नपत्रिकांचा संपूर्ण संग्रह.
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="create_paper.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i> नवीन पेपर</a>
        <a href="competitive_paper.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-trophy me-1"></i> स्पर्धा परीक्षा</a>
        <a href="assessment_paper.php" class="btn btn-outline-success btn-sm"><i class="bi bi-journal-check me-1"></i> संकलित / आकारिक</a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="table-responsive">
        <?php if (!$allPapers): ?>
            <div class="p-4 text-center text-muted">
                कोणतीही प्रश्नपत्रिका आढळली नाही. वरील डॅशबोर्ड टॅब्समधून आपली पहिली प्रश्नपत्रिका तयार करा.
            </div>
        <?php else: ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>शीर्षक</th>
                        <th>प्रकार</th>
                        <th>वर्ग / इयत्ता</th>
                        <th>विषय</th>
                        <th>दिनांक</th>
                        <th>गुण</th>
                        <th class="text-end">कृती (Actions)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allPapers as $p): 
                    $ptype = $p['paper_type'] ?? '';
                    $pm = json_decode($p['paper_json'], true) ?: [];
                    $isDaily = ($ptype === 'daily_study') || ($p['exam_name'] === 'दैनंदिन स्वाध्याय') || (($pm['exam_type'] ?? '') === 'daily_study');
                ?>
                    <tr>
                        <td><?= (int)$p['paper_id'] ?></td>
                        <td><strong class="text-dark"><?= h($p['title']) ?></strong></td>
                        <td>
                            <?php if ($isDaily): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-calendar2-check"></i> दैनंदिन स्वाध्याय</span>
                            <?php elseif ($ptype === 'competitive'): ?>
                                <span class="badge bg-primary"><i class="bi bi-trophy"></i> स्पर्धा परीक्षा</span>
                            <?php elseif ($ptype === 'assessment'): ?>
                                <span class="badge bg-success"><i class="bi bi-journal-check"></i> <?= ($pm['exam_type'] ?? '') === 'aakarik' ? 'आकारिक' : 'संकलित' ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-book"></i> घटक चाचणी</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ptype === 'competitive'): ?>
                                <?= h($p['std_label']) ?>
                            <?php elseif ($ptype === 'assessment' || $isDaily): ?>
                                <?= h($p['std_label'] ?: 'इ. ' . ($pm['standard'] ?? '')) ?>
                            <?php else: ?>
                                <?= h($p['standard_name']) ?> <small class="text-muted">(<?= h($p['medium']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ptype === 'competitive'): ?>
                                <?= h($p['exam_name']) ?>
                            <?php elseif ($ptype === 'assessment' || $isDaily): ?>
                                <?= h($pm['subject'] ?? '') ?>
                            <?php else: ?>
                                <?= h($p['subject_name']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['exam_date'] ? h(date('d-m-Y', strtotime($p['exam_date']))) : '-' ?></td>
                        <td><span class="fw-bold text-primary"><?= (float)$p['total_marks'] ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>" target="_blank" title="प्रिंट पेपर"><i class="bi bi-printer"></i> प्रिंट</a>
                            <a class="btn btn-sm btn-outline-success" href="paper_view.php?id=<?= (int)$p['paper_id'] ?>&answers=1&key=1" target="_blank" title="उत्तरतालिका"><i class="bi bi-key"></i> उत्तरतालिका</a>
                            <a class="btn btn-sm btn-outline-info" href="api.php?action=create_quiz_from_paper&paper_id=<?= (int)$p['paper_id'] ?>&redirect=1" target="_blank" title="मोबाईल / ऑनलाईन क्विझ"><i class="bi bi-phone"></i> क्विझ</a>
                            <?php if ($ptype === 'assessment' || $isDaily): ?>
                                <a class="btn btn-sm btn-outline-primary" href="export_docx.php?id=<?= (int)$p['paper_id'] ?>" title="Word (.docx) फाईल डाऊनलोड करा" style="background:#2b579a; color:#fff; border-color:#2b579a;"><i class="bi bi-file-earmark-word"></i> Word</a>
                            <?php endif; ?>
                            <?php if ($ptype === 'competitive'): ?>
                                <a class="btn btn-sm btn-outline-danger" href="omr_view.php?id=<?= (int)$p['paper_id'] ?>" target="_blank" title="OMR Sheet"><i class="bi bi-grid-3x3-gap-fill"></i> OMR</a>
                            <?php endif; ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('हा पेपर हटवायचा आहे का?')">
                                <input type="hidden" name="delete_id" value="<?= (int)$p['paper_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="हटवा"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- EMBEDDED JAVASCRIPT FOR QUICK DAILY WORKSHEET GENERATOR -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bookTree = <?= json_encode($bookTree, JSON_UNESCAPED_UNICODE) ?>;
    const stdSel = document.getElementById('dailyStd');
    const subSel = document.getElementById('dailySub');
    const chSel = document.getElementById('dailyChapter');
    const cntInput = document.getElementById('dailyCount');
    const form = document.getElementById('dailyStudyForm');
    const msgBox = document.getElementById('dailyStudyMsg');
    const btnGen = document.getElementById('btnGenDaily');

    if (!stdSel || !subSel || !chSel) return;

    function populateSubjects() {
        const curStd = bookTree.find(s => s.standard == stdSel.value);
        subSel.innerHTML = '';
        if (!curStd || !curStd.subjects || !curStd.subjects.length) {
            subSel.innerHTML = '<option value="">-- विषय उपलब्ध नाहीत --</option>';
            chSel.innerHTML = '<option value="">-- घटक उपलब्ध नाहीत --</option>';
            return;
        }
        curStd.subjects.forEach(su => {
            const opt = document.createElement('option');
            opt.value = su.name;
            opt.textContent = `${su.name} (${su.medium}) — ${su.n} प्रश्न`;
            subSel.appendChild(opt);
        });
        populateChapters();
    }

    function populateChapters() {
        const curStd = bookTree.find(s => s.standard == stdSel.value);
        const curSub = (curStd?.subjects || []).find(s => s.name === subSel.value);
        chSel.innerHTML = '';
        if (!curSub || !curSub.books || !curSub.books.length) {
            chSel.innerHTML = '<option value="">-- घटक उपलब्ध नाहीत --</option>';
            return;
        }
        const chapters = curSub.books.flatMap(b => b.chapters || []);
        if (!chapters.length) {
            chSel.innerHTML = '<option value="">-- या विषयात घटक उपलब्ध नाहीत --</option>';
            return;
        }
        chapters.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.chapter_id;
            opt.textContent = `${c.no ? c.no + '. ' : ''}${c.title} (${c.n} स्वाध्याय प्रश्न)`;
            chSel.appendChild(opt);
        });
    }

    stdSel.addEventListener('change', populateSubjects);
    subSel.addEventListener('change', populateChapters);
    populateSubjects();

    // Question count buttons
    document.querySelectorAll('.btn-daily-cnt').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.btn-daily-cnt').forEach(b => b.classList.remove('active', 'btn-primary'));
            document.querySelectorAll('.btn-daily-cnt').forEach(b => b.classList.add('btn-outline-primary'));
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('active', 'btn-primary');
            cntInput.value = btn.dataset.cnt;
        });
    });

    // Form submission
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const chId = +chSel.value;
            if (!chId) {
                alert('कृपया एक घटक निवडा (Please select a chapter)');
                return;
            }
            const count = +cntInput.value || 5;
            const format = document.getElementById('dailyFormat').value;

            btnGen.disabled = true;
            btnGen.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> स्वाध्याय पत्रक तयार होत आहे...';
            msgBox.innerHTML = '';

            try {
                const res = await fetch('api.php?action=generate_daily_study', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        chapter_id: chId,
                        count: count,
                        format: format,
                        exam_date: new Date().toISOString().split('T')[0]
                    })
                }).then(r => r.json());

                if (res.status === 'success') {
                    msgBox.innerHTML = `<div class="alert alert-success mt-2 py-2 d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-check-circle-fill me-1"></i> स्वाध्याय पत्रक यशस्वीरित्या तयार झाले!</div>
                        <a href="paper_view.php?id=${res.paper_id}&format=${res.format}" target="_blank" class="btn btn-sm btn-success fw-bold">
                            <i class="bi bi-printer-fill me-1"></i> आताच प्रिंट करा
                        </a>
                    </div>`;
                    window.open(`paper_view.php?id=${res.paper_id}&format=${res.format}`, '_blank');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msgBox.innerHTML = `<div class="alert alert-danger mt-2 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${res.message || 'Error occurred'}</div>`;
                }
            } catch (err) {
                msgBox.innerHTML = `<div class="alert alert-danger mt-2 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> नेटवर्क त्रुटी: ${err.message}</div>`;
            } finally {
                btnGen.disabled = false;
                btnGen.innerHTML = '<i class="bi bi-printer-fill me-1"></i> स्वाध्याय पत्रक तयार करा';
            }
        });
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
