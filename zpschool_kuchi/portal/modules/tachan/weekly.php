<?php
declare(strict_types=1);

/**
 * Smart School Hub — Weekly Lesson Planner (साप्ताहिक टाचण वही)
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db();

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

$medium = isset($_GET['medium']) 
    ? tachan_norm_medium((string)$_GET['medium']) 
    : ($pref['medium'] ?: tachan_norm_medium((string)($class['medium'] ?? 'marathi')));

// Week: Monday of the given date (default current or upcoming Monday)
$base = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date']) 
    ? (string)$_GET['date'] 
    : date('Y-m-d');
$monday = date('Y-m-d', strtotime('monday this week', strtotime($base)));
$prevW = date('Y-m-d', strtotime($monday . ' -7 day'));
$nextW = date('Y-m-d', strtotime($monday . ' +7 day'));
$thisW = date('Y-m-d', strtotime('monday this week'));
$isTachanPaid = is_tachan_module_purchased($sid);

$mediums = ['marathi' => 'मराठी माध्यम', 'semi_english' => 'सेमी-इंग्रजी'];
$dnames = [1 => 'सोमवार', 2 => 'मंगळवार', 3 => 'बुधवार', 4 => 'गुरुवार', 5 => 'शुक्रवार', 6 => 'शनिवार'];

$holq = $pdo->prepare('SELECT name FROM holidays WHERE school_id = ? AND date = ?');
$days = [];
for ($i = 0; $i < 6; $i++) {
    $d = date('Y-m-d', strtotime($monday . ' +' . $i . ' day'));
    $holq->execute([$sid, $d]);
    $hol = $holq->fetchColumn();
    $rows = $hol !== false ? [] : tachan_day_rows($pdo, $sid, (int)$user['id'], $class, $d, true, $medium);
    $days[] = [
        'date' => $d,
        'dow'  => $i + 1,
        'hol'  => $hol,
        'rows' => $rows,
    ];
}

$printQuery = http_build_query([
    'class_id' => $sel,
    'medium'   => $medium,
    'date'     => $monday,
]);

tachan_layout_start('साप्ताहिक टाचण', 'weekly');
?>

<!-- Filter & Control Bar -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <form method="get" class="row g-2 align-items-center">
            <!-- Class Selector -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted fw-bold mb-1">वर्ग निवडा</label>
                <select name="class_id" onchange="this.form.submit()" class="form-select form-select-sm rounded-3">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $sel === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> (<?= e(class_label(planner_class_std($c))) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Medium Selector -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted fw-bold mb-1">माध्यम</label>
                <select name="medium" onchange="this.form.submit()" class="form-select form-select-sm rounded-3">
                    <?php foreach ($mediums as $mv => $ml): ?>
                        <option value="<?= $mv ?>" <?= $medium === $mv ? 'selected' : '' ?>><?= e($ml) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Selector -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small text-muted fw-bold mb-1">आठवड्याची तारीख</label>
                <input type="date" name="date" value="<?= e($monday) ?>" onchange="this.form.submit()" class="form-control form-control-sm rounded-3">
            </div>

            <!-- Action Buttons -->
            <div class="col-12 col-sm-6 col-md-3 d-flex gap-2 align-items-end pt-sm-4">
                <?php if ($isTachanPaid || $monday === $thisW): ?>
                    <a href="<?= APP_URL ?>/modules/tachan/weekly_print.php?<?= $printQuery ?>" target="_blank" class="btn btn-warning btn-sm w-100 fw-bold shadow-sm text-dark d-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-printer-fill"></i> प्रिंट / PDF
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 fw-bold shadow-sm text-dark d-flex align-items-center justify-content-center gap-1" data-bs-toggle="modal" data-bs-target="#weeklyTrialModal">
                        <i class="bi bi-lock-fill text-warning"></i> प्रिंट / PDF (सशुल्क)
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <!-- Fast Week Nav Pills -->
        <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top align-items-center justify-content-between">
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/modules/tachan/weekly.php?class_id=<?= $sel ?>&medium=<?= $medium ?>&date=<?= $prevW ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-chevron-left"></i> मागील आठवडा
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/weekly.php?class_id=<?= $sel ?>&medium=<?= $medium ?>&date=<?= $thisW ?>" class="btn btn-outline-primary btn-sm rounded-pill <?= $monday === $thisW ? 'active' : '' ?>">
                    चालू आठवडा
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/weekly.php?class_id=<?= $sel ?>&medium=<?= $medium ?>&date=<?= $nextW ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                    पुढील आठवडा <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <div class="badge bg-light text-dark border px-3 py-2 fs-7 rounded-pill">
                <i class="bi bi-calendar3 me-1 text-primary"></i>
                <?= e(marathi_date_words($monday)) ?> ते <?= e(marathi_date_words(date('Y-m-d', strtotime($monday . ' +5 day')))) ?>
            </div>
        </div>
    </div>
</div>

<!-- Class & Week Info Strip -->
<div class="card border-0 shadow-sm rounded-4 mb-4 text-white" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
    <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <div>
            <div class="text-warning fw-bold small text-uppercase mb-1">
                <i class="bi bi-mortarboard-fill me-1"></i> <?= e($class['name']) ?> · <?= e(class_label(planner_class_std($class))) ?> · <?= e($mediums[$medium]) ?>
            </div>
            <h4 class="mb-0 fw-bold">
                आठवडा : <?= e(marathi_date_words($monday)) ?> ते <?= e(marathi_date_words(date('Y-m-d', strtotime($monday . ' +5 day')))) ?>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/modules/tachan/day.php?class_id=<?= $sel ?>&date=<?= $monday ?>" class="btn btn-light btn-sm fw-bold">
                <i class="bi bi-arrow-right-circle"></i> दैनिक टाचणावर जा
            </a>
        </div>
    </div>
</div>

<!-- Day by Day Lesson Plans Table -->
<div class="d-flex flex-column gap-3">
    <?php foreach ($days as $d): ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-light py-3 px-3 px-md-4 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-pill px-3 py-1"><?= e($dnames[$d['dow']]) ?></span>
                    <strong class="text-dark fs-6"><?= e(marathi_date_words($d['date'])) ?></strong>
                    <?php if ($d['date'] === date('Y-m-d')): ?>
                        <span class="badge bg-danger rounded-pill">आज</span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($d['hol'] !== false): ?>
                        <span class="badge bg-warning text-dark px-3 py-2 fw-bold rounded-pill">
                            <i class="bi bi-sun-fill me-1"></i> सुट्टी : <?= e((string)$d['hol']) ?>
                        </span>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary-subtle text-dark border rounded-pill">
                                <?= count($d['rows']) ?> तासिका
                            </span>
                            <a href="<?= APP_URL ?>/modules/tachan/day.php?class_id=<?= $sel ?>&date=<?= $d['date'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2 rounded-pill small">
                                <i class="bi bi-eye"></i> सविस्तर
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($d['hol'] === false && !empty($d['rows'])): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th style="width: 75px;" class="text-center">तासिका</th>
                                <th style="width: 170px;">विषय</th>
                                <th>घटक / पाठांश</th>
                                <th class="d-none d-md-table-cell">अध्ययन-अध्यापन कृती</th>
                                <th style="width: 80px;" class="text-center no-print">कृती</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($d['rows'] as $r): ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted bg-light">
                                        <?= (int)$r['period'] ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-7">
                                            <?= e(tachan_display_subject((string)$r['subject'], $medium)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($r['topic']) ?></div>
                                        <?php if (!empty($r['learning_outcome'])): ?>
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-bullseye text-primary"></i> <?= e($r['learning_outcome']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell small text-secondary">
                                        <?= e((string)($r['activities'] ?? '—')) ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <a href="<?= APP_URL ?>/modules/tachan/edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="संपादित करा">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($d['hol'] === false): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-calendar-x fs-3 d-block mb-1 text-secondary"></i>
                    या वाराचे वेळापत्रक किंवा टाचण नोंद उपलब्ध नाही.
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal for Past/Future Week Print in Trial -->
<div class="modal fade" id="weeklyTrialModal" tabindex="-1" aria-labelledby="weeklyTrialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-danger text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="weeklyTrialModalLabel">
                    <i class="bi bi-lock-fill me-1"></i> स्वतंत्र टाचण मॉड्युल आवश्यक
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="display-4 text-danger mb-3">🔒</div>
                <h5 class="fw-bold text-dark mb-2">मागील व भावी आठवडे प्रिंटसाठी स्वतंत्र मॉड्युल आवश्यक</h5>
                <p class="text-muted small mb-4" style="line-height: 1.7;">
                    मागील (जुने) किंवा पुढील सर्व आठवड्यांचे एकत्रित नियोजन डाऊनलोड व प्रिंट करण्यासाठी कृपया <strong>स्वतंत्र दैनिक टाचण वही मॉड्युल (₹४९९)</strong> किंवा <strong>ऑल-इन-वन शाळा पॅक</strong> खरेदी करा.<br>
                    <strong>(या विशेष सुविधेसाठी कोणतीही मोफत चाचणी लागू नाही.)</strong>
                </p>
                <div class="d-flex flex-column gap-2">
                    <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=24" class="btn btn-warning text-dark fw-bold rounded-pill py-2.5 shadow-sm">
                        <i class="bi bi-cart-check-fill me-1"></i> फक्त दैनिक टाचण वही मॉड्युल घ्या (&#8377;४९९/वर्ष)
                    </a>
                    <a href="<?= APP_URL ?>/subscription/checkout.php?plan_id=4" class="btn btn-outline-primary fw-bold rounded-pill py-2">
                        <i class="bi bi-rocket-takeoff-fill me-1"></i> संपूर्ण शाळा ऑल-इन-वन पॅक (&#8377;१,४९९/वर्ष)
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-4 border-0">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">बंद करा</button>
            </div>
        </div>
    </div>
</div>

<?php tachan_layout_end(); ?>
