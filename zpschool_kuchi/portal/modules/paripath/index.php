<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/paripath_engine.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Handle saving custom blackboard notes (announcement, word of day, GK)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
    $notesData = [
        'announcement' => trim($_POST['announcement'] ?? ''),
        'word_of_day' => trim($_POST['word_of_day'] ?? ''),
        'word_meaning' => trim($_POST['word_meaning'] ?? ''),
        'gk_question' => trim($_POST['gk_question'] ?? ''),
        'gk_answer' => trim($_POST['gk_answer'] ?? '')
    ];
    paripath_save_custom_notes($date, $notesData);
    header("Location: index.php?date={$date}&saved=1");
    exit;
}

// Force sync action if user clicks Refresh/Sync button
if (isset($_GET['sync'])) {
    paripath_sync_date($date);
    header("Location: index.php?date={$date}");
    exit;
}

$assembly = paripath_get_full_assembly($date);
$panchang = $assembly['panchang'];
$dinvishesh = $assembly['dinvishesh'];
$suvichar = $assembly['suvichar'];
$subhashit = $assembly['subhashit'];
$notes = $assembly['custom_notes'];

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$todayDate = date('Y-m-d');
$isToday = ($date === $todayDate);

$school = isLoggedIn() ? getSchool() : null;
$schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? '') ?: 'शालेय परिपाठ केंद्र');

$page_title = "शालेय परिपाठ — " . $assembly['marathi_date'];
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.paripath-hero {
    background: linear-gradient(135deg, #0B3D2E 0%, #0F4F3B 50%, #1D9370 100%);
    color: white;
    border-radius: 16px;
    padding: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.3);
}
.paripath-hero::after {
    content: 'ॐ';
    position: absolute;
    right: 20px;
    bottom: -20px;
    font-size: 140px;
    opacity: 0.08;
    color: white;
    pointer-events: none;
    font-family: serif;
}
.card-paripath {
    border: none;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    background: #fff;
    overflow: hidden;
}
.card-paripath:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.card-paripath .card-header-custom {
    padding: 14px 18px;
    font-weight: 700;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}
.panchang-pill {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 14px;
    height: 100%;
}
.panchang-pill .label {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.panchang-pill .val {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin-top: 2px;
}
.din-item {
    border-left: 3px solid #1D9370;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 0 8px 8px 0;
    margin-bottom: 10px;
}
.din-item.int-day {
    border-left-color: #10b981;
    background: #f0fdf4;
}
.din-item.jayanti {
    border-left-color: #f59e0b;
    background: #fffbeb;
}
.din-item.punyatithi {
    border-left-color: #ef4444;
    background: #fef2f2;
}
.quick-action-btn {
    border-radius: 12px;
    font-weight: 600;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.2s ease;
    text-decoration: none;
}
.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.12);
}
.date-nav-btn {
    border-radius: 8px;
    font-weight: 600;
}
.marathi-shloka {
    font-family: 'Mukta', serif;
    font-size: 1.18rem;
    line-height: 1.8;
    color: #1e293b;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    border-radius: 10px;
    padding: 16px;
    text-align: center;
    font-weight: 600;
}
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Date Navigation & Controls -->
    <div class="row align-items-center mb-3 g-2">
        <div class="col-md-6 col-12">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="index.php?date=<?= $prevDate ?>" class="btn btn-outline-secondary date-nav-btn btn-sm">
                    <i class="bi bi-chevron-left"></i> काल
                </a>
                <a href="index.php?date=<?= $todayDate ?>" class="btn <?= $isToday ? 'btn-primary' : 'btn-outline-primary' ?> date-nav-btn btn-sm">
                    <i class="bi bi-calendar-event"></i> आज
                </a>
                <a href="index.php?date=<?= $nextDate ?>" class="btn btn-outline-secondary date-nav-btn btn-sm">
                    उद्या <i class="bi bi-chevron-right"></i>
                </a>
                
                <form action="index.php" method="GET" class="d-inline-flex align-items-center gap-1">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= $date ?>" onchange="this.form.submit()" style="border-radius:8px;">
                </form>

                <a href="index.php?date=<?= $date ?>&sync=1" class="btn btn-outline-success btn-sm date-nav-btn" title="paripath.in वरून पुन्हा सिंक करा">
                    <i class="bi bi-arrow-repeat"></i> सिंक
                </a>
            </div>
        </div>

        <div class="col-md-6 col-12 text-md-end text-start">
            <div class="d-inline-flex flex-wrap gap-2">
                <a href="blackboard.php?date=<?= $date ?>" target="_blank" class="btn btn-dark btn-sm quick-action-btn" style="background:#1e293b; color:#fff;">
                    <i class="bi bi-display text-warning"></i> फळा (TV/Projector)
                </a>
                <a href="print.php?date=<?= $date ?>" target="_blank" class="btn btn-outline-primary btn-sm quick-action-btn">
                    <i class="bi bi-printer"></i> परिपाठ प्रिंट
                </a>
                <a href="prayers.php" class="btn btn-outline-secondary btn-sm quick-action-btn">
                    <i class="bi bi-music-note-beamed text-danger"></i> सर्व प्रार्थना
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="border-radius:10px;">
        <i class="bi bi-check-circle-fill me-2"></i> शाळेची फलक नोंद व सामान्य ज्ञान माहिती यशस्वीरीत्या सेव्ह झाली!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Hero Banner: Date, Tithi & Suvichar Quick View -->
    <div class="paripath-hero mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8 col-12 mb-3 mb-lg-0">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-warning text-dark px-3 py-1 fw-bold fs-6">
                        <?= sanitize($assembly['marathi_day']) ?>
                    </span>
                    <span class="badge bg-white bg-opacity-25 text-white px-3 py-1">
                        <?= sanitize($panchang['hindu_month'] ?? 'भाद्रपद') ?> • <?= sanitize($panchang['paksha'] ?? '') ?>
                    </span>
                    <span class="badge bg-success px-3 py-1">
                        १००% मोफत परिपाठ
                    </span>
                </div>
                <h2 class="h3 fw-bold mb-2 text-white">
                    <?= sanitize($assembly['marathi_date']) ?>
                </h2>
                <div class="fs-6 opacity-90 mb-2">
                    <i class="bi bi-geo-alt-fill text-warning me-1"></i> <?= sanitize($schoolName) ?>
                </div>
                <div class="p-3 bg-white bg-opacity-10 rounded-3 mt-3 border border-white border-opacity-20">
                    <div class="small text-warning fw-bold mb-1">
                        <i class="bi bi-quote"></i> आजचा सुविचार
                    </div>
                    <div class="fs-5 fw-semibold">
                        "<?= sanitize($suvichar['text_mr'] ?? '') ?>"
                    </div>
                    <?php if (!empty($suvichar['author'])): ?>
                    <div class="small opacity-75 text-end mt-1">
                        — <?= sanitize($suvichar['author']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="bg-white text-dark p-3 rounded-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span class="fw-bold text-primary"><i class="bi bi-sun-fill text-warning"></i> सूर्योदय व सूर्यास्त</span>
                        <span class="badge bg-light text-dark border">महाराष्ट्र</span>
                    </div>
                    <div class="row text-center g-2">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted d-block">सूर्योदय</small>
                                <span class="fw-bold fs-6 text-warning-emphasis"><?= sanitize($panchang['sunrise'] ?? '06:24 AM') ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <small class="text-muted d-block">सूर्यास्त</small>
                                <span class="fw-bold fs-6 text-danger-emphasis"><?= sanitize($panchang['sunset'] ?? '06:33 PM') ?></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-2 bg-danger bg-opacity-10 rounded text-start">
                                <small class="text-danger fw-bold d-block"><i class="bi bi-exclamation-triangle"></i> राहू काळ:</small>
                                <span class="fw-semibold small text-danger"><?= sanitize($panchang['rahu_kaal'] ?? 'उपलब्ध नाही') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main 2-Column Content Grid -->
    <div class="row g-3">

        <!-- Left Column: Panchang, Suvichar Details & Subhashit -->
        <div class="col-lg-7 col-12">

            <!-- 1. Detailed Panchang Card -->
            <div class="card card-paripath mb-3">
                <div class="card-header-custom text-primary bg-primary bg-opacity-10">
                    <span><i class="bi bi-calendar3 me-2"></i> दैनिक पंचांग</span>
                    <a href="panchang.php?date=<?= $date ?>" class="btn btn-sm btn-link text-decoration-none fw-semibold p-0">
                        सविस्तर पंचांग <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-sm-4 col-6">
                            <div class="panchang-pill">
                                <div class="label">तिथी</div>
                                <div class="val text-primary"><?= sanitize($panchang['tithi'] ?? '-') ?></div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="panchang-pill">
                                <div class="label">नक्षत्र</div>
                                <div class="val"><?= sanitize($panchang['nakshatra'] ?? '-') ?></div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="panchang-pill">
                                <div class="label">वार</div>
                                <div class="val text-success"><?= sanitize($assembly['marathi_day']) ?></div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="panchang-pill">
                                <div class="label">महिना व पक्ष</div>
                                <div class="val"><?= sanitize($panchang['hindu_month'] ?? '-') ?> (<?= sanitize($panchang['paksha'] ?? '-') ?>)</div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="panchang-pill">
                                <div class="label">योग</div>
                                <div class="val"><?= sanitize($panchang['yoga'] ?? '-') ?></div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="panchang-pill">
                                <div class="label">करण</div>
                                <div class="val"><?= sanitize($panchang['karana'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($panchang['festivals_list']) || !empty($panchang['festivals'])): ?>
                    <div class="mt-3 p-2 bg-warning bg-opacity-10 rounded-2 border border-warning border-opacity-30">
                        <div class="small fw-bold text-warning-emphasis mb-1">
                            <i class="bi bi-stars"></i> आजचे सण / उत्सव / विशेष:
                        </div>
                        <div class="fw-semibold text-dark">
                            <?php 
                            if (!empty($panchang['festivals_list'])) {
                                foreach ($panchang['festivals_list'] as $f) {
                                    echo '<span class="badge bg-warning text-dark me-1">' . sanitize(is_array($f) ? ($f['name_mr'] ?? json_encode($f)) : $f) . '</span>';
                                }
                            } else {
                                echo sanitize((string)$panchang['festivals']);
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Sanskrit Subhashit / Dnyaneshwari Ovi Card -->
            <div class="card card-paripath mb-3">
                <div class="card-header-custom text-warning-emphasis bg-warning bg-opacity-10">
                    <span><i class="bi bi-book-half me-2"></i> संस्कृत सुभाषित / ज्ञानेश्वरी ओवी</span>
                    <span class="badge bg-warning text-dark"><?= sanitize($subhashit['granth'] ?? 'सुभाषित') ?></span>
                </div>
                <div class="card-body p-3">
                    <div class="marathi-shloka mb-3">
                        <?= nl2br(sanitize($subhashit['text_sa'] ?? '')) ?>
                    </div>

                    <div class="mb-2">
                        <div class="small fw-bold text-primary mb-1"><i class="bi bi-chat-left-quote"></i> मराठी भावार्थ:</div>
                        <div class="text-dark fw-semibold" style="line-height:1.6;">
                            <?= sanitize($subhashit['meaning_mr'] ?? '') ?>
                        </div>
                    </div>

                    <?php if (!empty($subhashit['explanation_mr'])): ?>
                    <div class="mb-2 pt-2 border-top">
                        <div class="small fw-bold text-secondary mb-1"><i class="bi bi-info-circle"></i> सविस्तर विवेचन:</div>
                        <div class="text-muted small" style="line-height:1.6;">
                            <?= sanitize($subhashit['explanation_mr']) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($subhashit['usage_mr'])): ?>
                    <div class="p-2 bg-success bg-opacity-10 rounded-2 border border-success border-opacity-20 mt-2">
                        <div class="small fw-bold text-success"><i class="bi bi-lightbulb-fill"></i> विद्यार्थ्यांसाठी आचरण व बोध:</div>
                        <div class="small text-dark mt-1"><?= sanitize($subhashit['usage_mr']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Today's Suvichar Meaning & Details -->
            <div class="card card-paripath mb-3">
                <div class="card-header-custom text-info-emphasis bg-info bg-opacity-10">
                    <span><i class="bi bi-lightbulb me-2"></i> सुविचार स्पष्टीकरण</span>
                    <span class="badge bg-info text-dark">सार्थ सुविचार</span>
                </div>
                <div class="card-body p-3">
                    <h5 class="fw-bold text-dark mb-2">
                        "<?= sanitize($suvichar['text_mr'] ?? '') ?>"
                    </h5>
                    <?php if (!empty($suvichar['text_en'])): ?>
                    <div class="text-muted fst-italic mb-3">
                        "<?= sanitize($suvichar['text_en']) ?>"
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($suvichar['meaning_mr'])): ?>
                    <div class="mb-2">
                        <strong class="text-primary small d-block">अर्थ:</strong>
                        <p class="mb-0 text-dark"><?= sanitize($suvichar['meaning_mr']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($suvichar['explanation_mr'])): ?>
                    <div class="pt-2 border-top">
                        <strong class="text-secondary small d-block">महत्त्व व बोध:</strong>
                        <p class="mb-0 text-muted small"><?= sanitize($suvichar['explanation_mr']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4. Assembly Routine Sequence Guide -->
            <div class="card card-paripath mb-3">
                <div class="card-header-custom text-secondary bg-light">
                    <span><i class="bi bi-list-ol me-2"></i> आदर्श शालेय परिपाठ क्रम</span>
                    <span class="badge bg-secondary">८ टप्पे</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 text-center">
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-danger mb-1">१</div>
                                <div class="small fw-bold">राष्ट्रगीत</div>
                            </div>
                        </div>
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-primary mb-1">२</div>
                                <div class="small fw-bold">प्रतिज्ञा</div>
                            </div>
                        </div>
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-success mb-1">३</div>
                                <div class="small fw-bold">संविधान</div>
                            </div>
                        </div>
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-warning text-dark mb-1">४</div>
                                <div class="small fw-bold">सुविचार</div>
                            </div>
                        </div>
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-info text-dark mb-1">५</div>
                                <div class="small fw-bold">पंचांग</div>
                            </div>
                        </div>
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-dark mb-1">६</div>
                                <div class="small fw-bold">दिनविशेष</div>
                            </div>
                        </div>
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-primary mb-1">७</div>
                                <div class="small fw-bold">प्रार्थना</div>
                            </div>
                        </div>
                        <div class="col-3 col-md">
                            <div class="p-2 border rounded bg-light">
                                <div class="badge bg-danger mb-1">८</div>
                                <div class="small fw-bold">पसायदान</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Dinvishesh, Blackboard Custom Notes & GK -->
        <div class="col-lg-5 col-12">

            <!-- 1. Dinvishesh Card -->
            <div class="card card-paripath mb-3">
                <div class="card-header-custom text-dark bg-light">
                    <span><i class="bi bi-clock-history text-primary me-2"></i> आजचे दिनविशेष (<?= count($dinvishesh) ?>)</span>
                    <a href="dinvishesh.php?date=<?= $date ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold">
                        सर्व पहा <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($dinvishesh)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-50"></i>
                        आजचे दिनविशेष उपलब्ध नाहीत.<br>
                        <a href="index.php?date=<?= $date ?>&sync=1" class="btn btn-sm btn-primary mt-2">
                            <i class="bi bi-arrow-repeat"></i> ऑनलाइन सिंक करा
                        </a>
                    </div>
                    <?php else: ?>
                    <div style="max-height: 520px; overflow-y: auto; padding-right: 4px;">
                        <?php 
                        // Show top 8 items on dashboard
                        $displayEvents = array_slice($dinvishesh, 0, 8);
                        foreach ($displayEvents as $ev): 
                            $catClass = match($ev['category']) {
                                'international_day' => 'int-day',
                                'birth_anniversary' => 'jayanti',
                                'death_anniversary' => 'punyatithi',
                                default => ''
                            };
                            $catBadge = match($ev['category']) {
                                'international_day' => '<span class="badge bg-success">दिनविशेष</span>',
                                'birth_anniversary' => '<span class="badge bg-warning text-dark">जयंती</span>',
                                'death_anniversary' => '<span class="badge bg-danger">पुण्यतिथी</span>',
                                default => '<span class="badge bg-primary">घटना</span>'
                            };
                        ?>
                        <div class="din-item <?= $catClass ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div><?= $catBadge ?></div>
                                <?php if (!empty($ev['event_year'])): ?>
                                <span class="badge bg-light text-secondary border fw-bold">
                                    सन <?= paripath_marathi_digits($ev['event_year']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="fw-bold text-dark fs-6">
                                <?= sanitize($ev['title_mr']) ?>
                            </div>
                            <?php if (!empty($ev['short_info_mr'])): ?>
                            <div class="small text-muted mt-1" style="line-height:1.5;">
                                <?= sanitize($ev['short_info_mr']) ?>
                            </div>
                            <?php elseif (!empty($ev['description_mr'])): ?>
                            <div class="small text-muted mt-1" style="line-height:1.5;">
                                <?= sanitize(mb_substr($ev['description_mr'], 0, 140)) ?>...
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($dinvishesh) > 8): ?>
                    <div class="text-center pt-2 border-top">
                        <a href="dinvishesh.php?date=<?= $date ?>" class="btn btn-sm btn-link text-decoration-none fw-bold">
                            + आणखी <?= count($dinvishesh) - 8 ?> दिनविशेष पहा
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Blackboard Custom Notes / Word of Day & GK -->
            <div class="card card-paripath mb-3">
                <div class="card-header-custom text-dark bg-light">
                    <span><i class="bi bi-pencil-square text-success me-2"></i> शालेय फळा नोंद व सामान्य ज्ञान</span>
                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#editNotesModal">
                        <i class="bi bi-pencil"></i> संपादन
                    </button>
                </div>
                <div class="card-body p-3">
                    <!-- Word of the Day -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary">आजचा शब्द</span>
                            <strong class="text-dark"><?= sanitize($notes['word_of_day'] ?: 'परिश्रम (Hard Work)') ?></strong>
                        </div>
                        <div class="small text-muted">
                            <?= sanitize($notes['word_meaning'] ?: 'कोणत्याही कार्यात यश मिळवण्यासाठी केलेले सततचे प्रयत्न.') ?>
                        </div>
                    </div>

                    <!-- General Knowledge -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-dark">सामान्य ज्ञान</span>
                            <span class="small fw-bold text-dark"><?= sanitize($notes['gk_question'] ?: 'भारताचे पहिले अंतराळवीर कोण होते?') ?></span>
                        </div>
                        <div class="small text-success fw-bold">
                            उत्तर: <?= sanitize($notes['gk_answer'] ?: 'विंग कमांडर राकेश शर्मा') ?>
                        </div>
                    </div>

                    <!-- Today's Announcement -->
                    <div class="p-3 bg-warning bg-opacity-10 rounded-3 border border-warning border-opacity-30">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-warning text-dark">शालेय सूचना / आजचा विचार</span>
                        </div>
                        <div class="small text-dark fw-semibold">
                            <?= !empty($notes['announcement']) ? nl2br(sanitize($notes['announcement'])) : 'आज परिपाठानंतर सर्व विद्यार्थ्यांनी वर्ग स्वच्छता करावी.' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Quick Links to Full Prayers -->
            <div class="card card-paripath mb-3">
                <div class="card-header-custom text-dark bg-light">
                    <span><i class="bi bi-music-note-list text-danger me-2"></i> प्रमुख शालेय प्रार्थना व गीते</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="prayers.php#national" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                        <span>🇮🇳 राष्ट्रगीत — जन-गण-मन</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="prayers.php#pledge" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                        <span>✋ राष्ट्रीय प्रतिज्ञा (मराठी व इंग्रजी)</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="prayers.php#preamble" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                        <span>📜 भारताचे संविधान — उद्देशिका</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="prayers.php#pasaydan" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                        <span>🌸 संत ज्ञानेश्वरांचे पसायदान</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="prayers.php#prayers" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                        <span>🎵 खरा तो एकची धर्म / तू बुद्धि दे</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- Modal for Editing Blackboard Custom Notes -->
<div class="modal fade" id="editNotesModal" tabindex="-1" aria-labelledby="editNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;">
            <form action="index.php?date=<?= $date ?>" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editNotesModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i> शालेय फळा नोंद संपादन
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="save_notes" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">आजचा शब्द (Word of the Day):</label>
                        <input type="text" name="word_of_day" class="form-control" value="<?= sanitize($notes['word_of_day'] ?? '') ?>" placeholder="उदा. परिश्रम (Hard Work)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">शब्दाचा अर्थ / वाक्यात उपयोग:</label>
                        <textarea name="word_meaning" class="form-control" rows="2" placeholder="शब्दाचा अर्थ किंवा विद्यार्थ्यांसाठी मार्गदर्शन"><?= sanitize($notes['word_meaning'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">सामान्य ज्ञान प्रश्न (GK Question):</label>
                        <input type="text" name="gk_question" class="form-control" value="<?= sanitize($notes['gk_question'] ?? '') ?>" placeholder="उदा. भारताचे पहिले पंतप्रधान कोण होते?">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">सामान्य ज्ञान उत्तर:</label>
                        <input type="text" name="gk_answer" class="form-control" value="<?= sanitize($notes['gk_answer'] ?? '') ?>" placeholder="उदा. पंडित जवाहरलाल नेहरू">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">शाळेची आजची महत्त्वाची सूचना / उपक्रम:</label>
                        <textarea name="announcement" class="form-control" rows="2" placeholder="उदा. आज दुपारी २ वाजता चित्रकला स्पर्धा होईल."><?= sanitize($notes['announcement'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करा</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-check2-circle"></i> सेव्ह करा
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
