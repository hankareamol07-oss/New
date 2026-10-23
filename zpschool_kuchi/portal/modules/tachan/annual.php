<?php
declare(strict_types=1);

/**
 * Smart School Hub — Annual Curriculum Planning (वार्षिक अभ्यासक्रम नियोजन)
 * Separate tenant SQLite storage with dual-sync to MySQL.
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/planner_seed.php';
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

planner_annual_ensure_columns($pdo);
$months = planner_months();
$pref = tachan_prefs_get($pdo, $sid, (int)$user['id']);

$sel = isset($_GET['class_id']) && isset($byId[(int)$_GET['class_id']]) 
    ? (int)$_GET['class_id'] 
    : (int)($pref['class_id'] ?: $classes[0]['id']);
if (!isset($byId[$sel])) {
    $sel = (int)$classes[0]['id'];
}

$mSel = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($mSel !== 0 && !isset($months[$mSel])) {
    $mSel = 6; // Default to June (start of academic year)
}
$view = ($mSel === 0) ? 'year' : 'month';

$backUrl = APP_URL . '/modules/tachan/annual.php?class_id=' . $sel . '&month=' . $mSel;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && tachan_verify_csrf()) {
    $cid = (int)($_POST['class_id'] ?? $sel);
    if (!isset($byId[$cid])) {
        $cid = $sel;
    }

    if (isset($_POST['import'])) {
        $n = planner_import_annual($pdo, $sid, (int)$user['id'], $classes);
        flash_set('success', "शासकीय वार्षिक नियोजन यशस्वीरीत्या आयात झाले ({$n} नोंदी अद्ययावत).");
    } elseif (isset($_POST['save_weeks']) && is_array($_POST['w'] ?? null)) {
        $m = (int)($_POST['month'] ?? 0);
        if (isset($months[$m])) {
            $find = $pdo->prepare('SELECT id FROM annual_plans WHERE school_id = ? AND class_id = ? AND subject = ? AND month = ? ORDER BY id LIMIT 1');
            $upd = $pdo->prepare('UPDATE annual_plans SET topics = ?, week1 = ?, week2 = ?, week3 = ?, week4 = ? WHERE id = ? AND school_id = ?');
            $ins = $pdo->prepare('INSERT INTO annual_plans(school_id, teacher_id, class_id, subject, month, topics, week1, week2, week3, week4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $n = 0;

            foreach ($_POST['w'] as $sub => $ws) {
                $sub = trim((string)$sub);
                if ($sub === '' || !is_array($ws)) continue;
                $w = [];
                for ($i = 1; $i <= 4; $i++) {
                    $w[] = trim((string)($ws[$i] ?? ''));
                }
                $topics = trim(implode(' | ', array_filter($w)));
                $find->execute([$sid, $cid, $sub, $m]);
                $id = (int)$find->fetchColumn();

                if ($id) {
                    $upd->execute([$topics !== '' ? $topics : $sub, $w[0], $w[1], $w[2], $w[3], $id, $sid]);
                } elseif ($topics !== '') {
                    $ins->execute([$sid, (int)$user['id'], $cid, $sub, $m, $topics, $w[0], $w[1], $w[2], $w[3]]);
                }

                // Dual-sync to MySQL
                syncTenantRecordToMySQL('annual_plans', [
                    'school_id'  => $sid,
                    'teacher_id' => (int)$user['id'],
                    'class_id'   => $cid,
                    'subject'    => $sub,
                    'month'      => $m,
                    'topics'     => $topics !== '' ? $topics : $sub,
                    'week1'      => $w[0],
                    'week2'      => $w[1],
                    'week3'      => $w[2],
                    'week4'      => $w[3],
                ], ['school_id', 'class_id', 'subject', 'month']);

                $n++;
            }
            flash_set('success', "{$months[$m]} : {$n} विषयांचे आठवडी नियोजन जतन झाले.");
        }
        $backUrl = APP_URL . '/modules/tachan/annual.php?class_id=' . $cid . '&month=' . $m;
    } elseif (isset($_POST['add_subject'])) {
        $sub = trim((string)($_POST['subject'] ?? ''));
        $m = (int)($_POST['month'] ?? 0);
        if ($sub !== '' && isset($months[$m])) {
            $have = $pdo->prepare('SELECT COUNT(*) FROM annual_plans WHERE school_id = ? AND class_id = ? AND subject = ? AND month = ?');
            $have->execute([$sid, $cid, $sub, $m]);
            if (!(int)$have->fetchColumn()) {
                $ins = $pdo->prepare('INSERT INTO annual_plans(school_id, teacher_id, class_id, subject, month, topics, week1, week2, week3, week4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$sid, (int)$user['id'], $cid, $sub, $m, $sub, '', '', '', '']);

                syncTenantRecordToMySQL('annual_plans', [
                    'school_id'  => $sid,
                    'teacher_id' => (int)$user['id'],
                    'class_id'   => $cid,
                    'subject'    => $sub,
                    'month'      => $m,
                    'topics'     => $sub,
                    'week1'      => '',
                    'week2'      => '',
                    'week3'      => '',
                    'week4'      => '',
                ], ['school_id', 'class_id', 'subject', 'month']);

                flash_set('success', 'विषय जोडला — आता आठवड्याचे नियोजन भरा.');
            } else {
                flash_set('error', 'हा विषय या महिन्यात आधीच जोडलेला आहे.');
            }
        }
        $backUrl = APP_URL . '/modules/tachan/annual.php?class_id=' . $cid . '&month=' . $m;
    }

    header('Location: ' . $backUrl);
    exit;
}

$q = $pdo->prepare('SELECT subject, month, topics, week1, week2, week3, week4 FROM annual_plans WHERE school_id = ? AND class_id = ? ORDER BY id');
$q->execute([$sid, $sel]);
$by = [];
$subjects = [];
foreach ($q->fetchAll() as $r) {
    $by[$r['subject']][(int)$r['month']] = $r;
    $subjects[$r['subject']] = true;
}

$std = planner_class_std($byId[$sel]);
$order = [];
foreach (array_merge(planner_subjects_for_std($std), planner_extra_subjects($std)) as $s) {
    if (isset($subjects[$s])) {
        $order[] = $s;
    }
}
foreach (array_keys($subjects) as $s) {
    if (!in_array($s, $order, true)) {
        $order[] = $s;
    }
}

$medium = $pref['medium'];
$disp = static fn(string $s): string => tachan_display_subject($s, $medium);
$cell = static function (array $row, int $i): string {
    $v = (string)($row['week' . $i] ?? '');
    if ($v !== '' || $i > 1) {
        return $v;
    }
    for ($k = 2; $k <= 4; $k++) {
        if ((string)($row['week' . $k] ?? '') !== '') {
            return '';
        }
    }
    return (string)($row['topics'] ?? '');
};

tachan_layout_start('वार्षिक अभ्यासक्रम नियोजन', 'annual');
?>

<style>
.annual-week-textarea {
    width: 100%;
    min-height: 80px;
    font-size: 0.82rem;
    border-radius: 8px;
    border: 1px solid #CBD5E1;
    padding: 6px 8px;
    resize: vertical;
}
.annual-week-textarea:focus {
    border-color: #167A5B;
    box-shadow: 0 0 0 3px rgba(22,122,91, 0.15);
    outline: none;
}
</style>

<!-- Filter & Control Bar -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
            <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
                <!-- Class -->
                <div>
                    <select name="class_id" onchange="this.form.submit()" class="form-select form-select-sm rounded-3 fw-bold">
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $sel === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?> (<?= e(class_label(planner_class_std($c))) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Month -->
                <div>
                    <select name="month" onchange="this.form.submit()" class="form-select form-select-sm rounded-3">
                        <option value="0" <?= $mSel === 0 ? 'selected' : '' ?>>संपूर्ण वर्ष (जून → एप्रिल)</option>
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $mSel === $num ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-2">
                <form method="post" onsubmit="return confirm('शासकीय वार्षिक नियोजन आयात करायचे का?')" class="d-inline">
                    <?= tachan_csrf_field() ?>
                    <button type="submit" name="import" value="1" class="btn btn-outline-primary btn-sm rounded-pill">
                        <i class="bi bi-cloud-arrow-down-fill me-1"></i> शासकीय नियोजन आयात करा
                    </button>
                </form>

                <?php if ($view === 'month'): ?>
                    <a target="_blank" href="<?= APP_URL ?>/modules/tachan/annual_print.php?class_id=<?= $sel ?>&month=<?= $mSel ?>" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark">
                        <i class="bi bi-printer-fill me-1"></i> महिना PDF
                    </a>
                <?php endif; ?>

                <a target="_blank" href="<?= APP_URL ?>/modules/tachan/annual_print.php?class_id=<?= $sel ?>&month=0" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> संपूर्ण वर्ष PDF
                </a>
            </div>
        </div>

        <!-- Fast Month Nav Pills -->
        <div class="d-flex flex-wrap gap-1 mt-3 pt-3 border-top overflow-x-auto">
            <a href="<?= APP_URL ?>/modules/tachan/annual.php?class_id=<?= $sel ?>&month=0" class="btn btn-sm rounded-pill px-3 <?= $mSel === 0 ? 'btn-primary text-white fw-bold' : 'btn-outline-secondary' ?>">
                संपूर्ण वर्ष
            </a>
            <?php foreach ($months as $num => $name): ?>
                <a href="<?= APP_URL ?>/modules/tachan/annual.php?class_id=<?= $sel ?>&month=<?= $num ?>" class="btn btn-sm rounded-pill px-3 <?= $mSel === $num ? 'btn-primary text-white fw-bold' : 'btn-outline-secondary' ?>">
                    <?= e($name) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($view === 'month'): ?>
    <!-- Monthly 4-Week Topic Editor -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-calendar-range text-warning me-2"></i>
                    <?= e($byId[$sel]['name']) ?> — <?= e($months[$mSel]) ?> अभ्यासक्रम नियोजन
                </h5>
                <div class="small text-white-50 mt-1">इयत्ता <?= e(class_label($std)) ?> · आठवडानिहाय घटक विभाजन</div>
            </div>

            <div class="d-flex gap-1">
                <?php
                $mk = array_keys($months);
                $ix = array_search($mSel, $mk, true);
                $pv = $mk[($ix + 11) % 12];
                $nx = $mk[($ix + 1) % 12];
                ?>
                <a href="<?= APP_URL ?>/modules/tachan/annual.php?class_id=<?= $sel ?>&month=<?= $pv ?>" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-chevron-left"></i> <?= e($months[$pv]) ?>
                </a>
                <a href="<?= APP_URL ?>/modules/tachan/annual.php?class_id=<?= $sel ?>&month=<?= $nx ?>" class="btn btn-outline-light btn-sm rounded-pill">
                    <?= e($months[$nx]) ?> <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <?php if (!$order): ?>
            <div class="card-body p-5 text-center text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                या वर्गासाठी वार्षिक नियोजनाच्या नोंदी नाहीत.<br>
                वरील <strong>"शासकीय नियोजन आयात करा"</strong> बटण दाबा किंवा खाली नवीन विषय जोडा.
            </div>
        <?php else: ?>
            <form method="post" class="p-3 p-md-4">
                <?= tachan_csrf_field() ?>
                <input type="hidden" name="class_id" value="<?= $sel ?>">
                <input type="hidden" name="month" value="<?= $mSel ?>">

                <div class="table-responsive">
                    <table class="table table-bordered align-top mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-center">
                                <th style="width: 140px;" class="text-start">विषय</th>
                                <th style="width: 21%;">पहिला आठवडा</th>
                                <th style="width: 21%;">दुसरा आठवडा</th>
                                <th style="width: 21%;">तिसरा आठवडा</th>
                                <th style="width: 21%;">चौथा आठवडा</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order as $sub):
                                $row = $by[$sub][$mSel] ?? ['topics' => '', 'week1' => '', 'week2' => '', 'week3' => '', 'week4' => ''];
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark pt-3">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-7 text-wrap text-start">
                                            <?= e($disp($sub)) ?>
                                        </span>
                                    </td>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <td class="p-1">
                                            <textarea name="w[<?= e($sub) ?>][<?= $i ?>]" class="annual-week-textarea" placeholder="आठवडा <?= $i ?> घटक..."><?= e($cell($row, $i)) ?></textarea>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top">
                    <span class="small text-muted">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        प्रत्येक सेल संपादित करता येतो; रिकामा ठेवल्यास त्या आठवड्यात घटक दाखवला जाणार नाही.
                    </span>
                    <button type="submit" name="save_weeks" value="1" class="btn btn-success px-5 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-check-circle-fill me-2"></i> आठवडी नियोजन जतन करा
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Add Custom Subject Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <h6 class="fw-bold text-dark mb-2">
                <i class="bi bi-plus-circle-fill text-primary me-2"></i>
                या महिन्यात नवीन विषय जोडा
            </h6>
            <form method="post" class="row g-2 align-items-center">
                <?= tachan_csrf_field() ?>
                <input type="hidden" name="class_id" value="<?= $sel ?>">
                <input type="hidden" name="month" value="<?= $mSel ?>">

                <div class="col-12 col-sm-8 col-md-5">
                    <input required name="subject" placeholder="विषयाचे नाव (उदा. संगणक, संगीत)" class="form-control form-control-sm rounded-3" list="sublist">
                    <datalist id="sublist">
                        <?php foreach (array_merge(planner_subjects_for_std($std), planner_extra_subjects($std)) as $s): ?>
                            <option value="<?= e($s) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-12 col-sm-4 col-md-2">
                    <button type="submit" name="add_subject" value="1" class="btn btn-outline-dark btn-sm rounded-3 w-100 fw-bold">
                        विषय जोडा
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- Full Year Grid (June to April) -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-calendar3 text-warning me-2"></i>
                    <?= e($byId[$sel]['name']) ?> — संपूर्ण वर्ष वार्षिक नियोजन (जून → एप्रिल)
                </h5>
                <div class="small text-white-50 mt-1">इयत्ता <?= e(class_label($std)) ?> · सर्व विषयांचे वार्षिक विहंगावलोकन</div>
            </div>
            <a target="_blank" href="<?= APP_URL ?>/modules/tachan/annual_print.php?class_id=<?= $sel ?>&month=0" class="btn btn-warning btn-sm rounded-pill fw-bold text-dark">
                <i class="bi bi-printer-fill me-1"></i> वार्षिक PDF
            </a>
        </div>

        <?php if (!$order): ?>
            <div class="card-body p-5 text-center text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                या वर्गासाठी वार्षिक नियोजनाच्या नोंदी नाहीत.<br>
                वरील <strong>"शासकीय नियोजन आयात करा"</strong> बटण दाबा.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-top mb-0 small">
                    <thead class="table-light text-nowrap text-center">
                        <tr>
                            <th style="min-width: 120px;" class="text-start">विषय</th>
                            <?php foreach ($months as $num => $name): ?>
                                <th style="min-width: 160px;">
                                    <a class="text-decoration-none fw-bold text-primary" href="<?= APP_URL ?>/modules/tachan/annual.php?class_id=<?= $sel ?>&month=<?= $num ?>">
                                        <?= e($name) ?> <i class="bi bi-pencil-square fs-8"></i>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order as $sub): ?>
                            <tr>
                                <td class="fw-bold bg-light text-primary text-nowrap">
                                    <?= e($disp($sub)) ?>
                                </td>
                                <?php foreach ($months as $num => $_):
                                    $r = $by[$sub][$num] ?? null;
                                ?>
                                    <td class="p-2">
                                        <?php if ($r):
                                            $ws = [];
                                            for ($i = 1; $i <= 4; $i++) {
                                                $v = $cell($r, $i);
                                                if ($v !== '') {
                                                    $ws[] = '<span class="fw-bold text-secondary">' . $i . '.</span> ' . e($v);
                                                }
                                            }
                                            echo implode('<br>', $ws);
                                        else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php tachan_layout_end(); ?>
