<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/paripath_engine.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$category = $_GET['category'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$allEvents = paripath_get_dinvishesh($date);

// Filter by category
$filteredEvents = $allEvents;
if ($category !== 'all') {
    $filteredEvents = array_filter($filteredEvents, fn($e) => ($e['category'] ?? '') === $category);
}

// Filter by search query if provided
if ($search !== '') {
    $filteredEvents = array_filter($filteredEvents, function($e) use ($search) {
        return (mb_stripos($e['title_mr'] ?? '', $search) !== false)
            || (mb_stripos($e['short_info_mr'] ?? '', $search) !== false)
            || (mb_stripos($e['description_mr'] ?? '', $search) !== false)
            || (strpos((string)($e['event_year'] ?? ''), $search) !== false);
    });
}

// Count by category
$counts = [
    'all' => count($allEvents),
    'international_day' => 0,
    'historical_event' => 0,
    'birth_anniversary' => 0,
    'death_anniversary' => 0
];
foreach ($allEvents as $e) {
    $cat = $e['category'] ?? 'historical_event';
    if (isset($counts[$cat])) {
        $counts[$cat]++;
    }
}

$marathiDate = paripath_marathi_date($date);
$marathiDay = paripath_marathi_day($date);

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$todayDate = date('Y-m-d');

$page_title = "आजचे दिनविशेष — " . $marathiDate;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-lg-4 py-3">

    <!-- Breadcrumb & Nav -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">डॅशबोर्ड</a></li>
                    <li class="breadcrumb-item"><a href="index.php?date=<?= $date ?>">शालेय परिपाठ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">दिनविशेष दालन</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0 text-primary">
                <i class="bi bi-clock-history me-2"></i> दिनविशेष दालन (इतिहास व दिन)
            </h3>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="dinvishesh.php?date=<?= $prevDate ?>&category=<?= $category ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-left"></i> काल
            </a>
            <a href="dinvishesh.php?date=<?= $todayDate ?>&category=<?= $category ?>" class="btn <?= $date===$todayDate ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
                आज
            </a>
            <a href="dinvishesh.php?date=<?= $nextDate ?>&category=<?= $category ?>" class="btn btn-outline-secondary btn-sm">
                उद्या <i class="bi bi-chevron-right"></i>
            </a>
            <input type="date" value="<?= $date ?>" onchange="location.href='dinvishesh.php?date='+this.value+'&category=<?= $category ?>'" class="form-control form-control-sm" style="width:auto; border-radius:8px;">
            <a href="blackboard.php?date=<?= $date ?>" target="_blank" class="btn btn-dark btn-sm">
                <i class="bi bi-display"></i> फळा मोड
            </a>
        </div>
    </div>

    <!-- Date Title & Filter Tabs -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius:14px;">
        <div class="card-body p-3">
            <div class="row align-items-center g-3">
                <div class="col-lg-6 col-12">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary fs-6 px-3 py-2 fw-bold">
                            <?= sanitize($marathiDate) ?> (<?= sanitize($marathiDay) ?>)
                        </span>
                        <span class="text-muted small">एकूण <?= count($allEvents) ?> नोंदी उपलब्ध</span>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <form action="dinvishesh.php" method="GET" class="d-flex gap-2">
                        <input type="hidden" name="date" value="<?= $date ?>">
                        <input type="hidden" name="category" value="<?= $category ?>">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="घटनेचे नाव किंवा वर्ष शोधा..." value="<?= sanitize($search) ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search"></i> शोधा
                            </button>
                            <?php if ($search !== ''): ?>
                            <a href="dinvishesh.php?date=<?= $date ?>&category=<?= $category ?>" class="btn btn-outline-secondary btn-sm">
                                क्लिअर
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <hr class="my-3 opacity-25">

            <!-- Category Pills -->
            <ul class="nav nav-pills flex-wrap gap-2">
                <li class="nav-item">
                    <a class="nav-link <?= $category === 'all' ? 'active' : '' ?>" href="dinvishesh.php?date=<?= $date ?>&category=all&q=<?= urlencode($search) ?>" style="border-radius:20px; font-weight:600;">
                        सर्व (<?= $counts['all'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $category === 'international_day' ? 'active' : '' ?>" href="dinvishesh.php?date=<?= $date ?>&category=international_day&q=<?= urlencode($search) ?>" style="border-radius:20px; font-weight:600;">
                        🌍 आंतरराष्ट्रीय / राष्ट्रीय दिन (<?= $counts['international_day'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $category === 'historical_event' ? 'active' : '' ?>" href="dinvishesh.php?date=<?= $date ?>&category=historical_event&q=<?= urlencode($search) ?>" style="border-radius:20px; font-weight:600;">
                        🏛️ ऐतिहासिक घटना (<?= $counts['historical_event'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $category === 'birth_anniversary' ? 'active' : '' ?>" href="dinvishesh.php?date=<?= $date ?>&category=birth_anniversary&q=<?= urlencode($search) ?>" style="border-radius:20px; font-weight:600;">
                        🎂 जयंती (<?= $counts['birth_anniversary'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $category === 'death_anniversary' ? 'active' : '' ?>" href="dinvishesh.php?date=<?= $date ?>&category=death_anniversary&q=<?= urlencode($search) ?>" style="border-radius:20px; font-weight:600;">
                        🕊️ पुण्यतिथी / स्मृतिदिन (<?= $counts['death_anniversary'] ?>)
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Events List -->
    <?php if (empty($filteredEvents)): ?>
    <div class="card shadow-sm border-0 text-center py-5" style="border-radius:14px;">
        <div class="card-body">
            <i class="bi bi-calendar-x text-muted fs-1 d-block mb-3"></i>
            <h5 class="fw-bold text-dark">या श्रेणीमध्ये कोणत्याही नोंदी आढळल्या नाहीत.</h5>
            <p class="text-muted small">कृपया दुसरी श्रेणी निवडा किंवा ऑनलाइन सिंक करा.</p>
            <a href="dinvishesh.php?date=<?= $date ?>&category=all" class="btn btn-outline-primary btn-sm">
                सर्व दिनविशेष पहा
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($filteredEvents as $ev): 
            $catBadge = match($ev['category']) {
                'international_day' => ['badge' => 'bg-success', 'label' => 'आंतरराष्ट्रीय / राष्ट्रीय दिन', 'border' => '#10b981'],
                'birth_anniversary' => ['badge' => 'bg-warning text-dark', 'label' => 'जयंती / जन्मदिवस', 'border' => '#f59e0b'],
                'death_anniversary' => ['badge' => 'bg-danger', 'label' => 'पुण्यतिथी / स्मृतीदिन', 'border' => '#ef4444'],
                default => ['badge' => 'bg-primary', 'label' => 'ऐतिहासिक घटना', 'border' => '#1D9370']
            };
        ?>
        <div class="col-lg-6 col-12">
            <div class="card shadow-sm border-0 h-100" style="border-radius:14px; border-left: 5px solid <?= $catBadge['border'] ?> !important;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <span class="badge <?= $catBadge['badge'] ?> px-2 py-1">
                            <?= $catBadge['label'] ?>
                        </span>
                        <?php if (!empty($ev['event_year'])): ?>
                        <span class="badge bg-light text-dark border fs-6 fw-bold">
                            सन <?= paripath_marathi_digits($ev['event_year']) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <h5 class="fw-bold text-dark mb-2">
                        <?= sanitize($ev['title_mr']) ?>
                    </h5>

                    <?php if (!empty($ev['title_en'])): ?>
                    <div class="text-muted small fst-italic mb-2">
                        <?= sanitize($ev['title_en']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($ev['short_info_mr'])): ?>
                    <p class="text-secondary small mb-2 fw-semibold" style="line-height:1.6;">
                        <?= sanitize($ev['short_info_mr']) ?>
                    </p>
                    <?php endif; ?>

                    <?php if (!empty($ev['description_mr'])): ?>
                    <div class="small text-muted pt-2 border-top" style="line-height:1.6;">
                        <?= nl2br(sanitize($ev['description_mr'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
