<?php
declare(strict_types=1);

/**
 * Smart School Hub — School Working Calendar (शालेय कामकाज कॅलेंडर)
 * Month-wise activities, observances, and school holidays.
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/planner_seed.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db();

$months = planner_months();
$cal = planner_calendar();

$s = $pdo->prepare('SELECT date, name FROM holidays WHERE school_id = ? ORDER BY date');
$s->execute([$sid]);
$hol = [];
foreach ($s as $r) {
    $m = (int)date('n', strtotime((string)$r['date']));
    $hol[$m][] = $r;
}

tachan_layout_start('शालेय कामकाज कॅलेंडर', 'annual');
?>

<!-- Header & Fast Links Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-calendar3-event text-primary me-2"></i> शैक्षणिक वर्ष कामकाज कॅलेंडर
                </h5>
                <div class="small text-muted mt-1">महिनानिहाय उपक्रम, दिनविशेष आणि शासकीय व शाळा सुट्ट्या</div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/modules/tachan/annual.php" class="btn btn-outline-primary btn-sm rounded-pill">
                    <i class="bi bi-book me-1"></i> वार्षिक नियोजन
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/holidays.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-sun me-1"></i> सुट्ट्या कॅलेंडर
                </a>
                <button onclick="window.print()" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark shadow-sm">
                    <i class="bi bi-printer-fill me-1"></i> प्रिंट करा
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 12 Months Cards Grid -->
<div class="row g-4">
    <?php foreach ($months as $num => $name):
        $isCurrentMonth = ((int)date('n') === $num);
    ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden <?= $isCurrentMonth ? 'border-primary border-2' : '' ?>">
                <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center <?= $isCurrentMonth ? 'bg-primary text-white' : 'bg-light text-dark' ?>">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-calendar2-week me-2"></i> <?= e($name) ?>
                    </h6>
                    <?php if ($isCurrentMonth): ?>
                        <span class="badge bg-warning text-dark rounded-pill fw-bold">चालू महिना</span>
                    <?php endif; ?>
                </div>

                <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between">
                    <!-- Activities & Observances -->
                    <div>
                        <div class="small fw-bold text-uppercase text-secondary mb-2">
                            <i class="bi bi-stars text-warning me-1"></i> नियोजित उपक्रम व दिनविशेष
                        </div>
                        <ul class="list-unstyled mb-3 small">
                            <?php foreach (($cal[$num] ?? []) as $item): ?>
                                <li class="mb-1 d-flex align-items-start gap-2">
                                    <i class="bi bi-check2 text-primary mt-1"></i>
                                    <span><?= e($item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Holidays in Month -->
                    <?php if (!empty($hol[$num])): ?>
                        <div class="pt-3 border-top mt-2">
                            <div class="small fw-bold text-danger mb-2">
                                <i class="bi bi-sun-fill me-1"></i> या महिन्यातील सुट्ट्या
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($hol[$num] as $h): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill small py-1 px-2">
                                        <?= e(date('d', strtotime((string)$h['date']))) ?> : <?= e($h['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer bg-white border-0 px-4 pb-3 pt-0 text-end">
                    <a href="<?= APP_URL ?>/modules/tachan/annual.php?month=<?= $num ?>" class="small text-decoration-none fw-bold text-primary">
                        या महिन्याचे अभ्यासक्रम नियोजन →
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php tachan_layout_end(); ?>
