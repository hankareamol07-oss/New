<?php
declare(strict_types=1);

/**
 * Smart School Hub — Holidays & School Calendar Events (शालेय सुट्ट्या व दिनविशेष)
 * Separate tenant SQLite storage with dual-sync to MySQL.
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/planner_seed.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$pdo = db();
$manage = in_array($user['role'] ?? 'teacher', ['admin', 'principal', 'headmaster'], true) || !empty($_SESSION['admin_logged_in']);

planner_holidays_ensure_columns($pdo);

$mn = [1 => 'जानेवारी', 'फेब्रुवारी', 'मार्च', 'एप्रिल', 'मे', 'जून', 'जुलै', 'ऑगस्ट', 'सप्टेंबर', 'ऑक्टोबर', 'नोव्हेंबर', 'डिसेंबर'];
$ym = isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['ym']) ? (string)$_GET['ym'] : date('Y-m');
[$Y, $M] = array_map('intval', explode('-', $ym));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && tachan_verify_csrf()) {
    $ym = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['ym'] ?? '')) ? (string)$_POST['ym'] : $ym;

    if (isset($_POST['save_cal'])) {
        // Teacher's selected holidays for this month (only kind='user')
        [$Y, $M] = array_map('intval', explode('-', $ym));
        $picked = array_values(array_filter(
            array_map('strval', (array)($_POST['d'] ?? [])),
            static fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && str_starts_with($d, $ym)
        ));

        // Get existing user holidays for this month to sync deletion
        $oldUserHols = $pdo->prepare("SELECT date FROM holidays WHERE school_id = ? AND kind = 'user' AND date LIKE ?");
        $oldUserHols->execute([$sid, $ym . '-%']);
        $oldDates = $oldUserHols->fetchAll(PDO::FETCH_COLUMN);

        $del = $pdo->prepare("DELETE FROM holidays WHERE school_id = ? AND kind = 'user' AND date LIKE ?");
        $del->execute([$sid, $ym . '-%']);
        foreach ($oldDates as $od) {
            syncTenantDeleteToMySQL('holidays', ['school_id' => $sid, 'date' => $od, 'kind' => 'user']);
        }

        $govt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE school_id = ? AND date = ? AND kind <> 'user'");
        $ins = $pdo->prepare("INSERT OR REPLACE INTO holidays(school_id, date, name, kind) VALUES (?, ?, ?, 'user')");
        $n = 0;

        foreach ($picked as $d) {
            $govt->execute([$sid, $d]);
            if ((int)$govt->fetchColumn() > 0) continue;
            $ins->execute([$sid, $d, 'माझी सुट्टी']);
            syncTenantRecordToMySQL('holidays', [
                'school_id' => $sid,
                'date'      => $d,
                'name'      => 'माझी सुट्टी',
                'kind'      => 'user',
            ], ['school_id', 'date']);
            $n++;
        }
        flash_set('success', "{$mn[$M]} {$Y} : {$n} सुट्ट्या यशस्वीरीत्या जतन झाल्या.");
    } elseif ($manage && isset($_POST['import'])) {
        $n = planner_import_holidays($pdo, $sid);
        flash_set('success', "शासकीय सुट्ट्या आयात झाल्या ({$n} नवीन).");
    } elseif ($manage && isset($_POST['import_upakram'])) {
        $n = planner_import_upakram($pdo, $sid);
        flash_set('success', "दिनविशेष / उपक्रम आयात झाले ({$n} नवीन).");
    } elseif ($manage && isset($_POST['delete'])) {
        $delId = (int)$_POST['delete'];
        $find = $pdo->prepare('SELECT date FROM holidays WHERE id = ? AND school_id = ?');
        $find->execute([$delId, $sid]);
        $hDate = $find->fetchColumn();

        $d = $pdo->prepare('DELETE FROM holidays WHERE id = ? AND school_id = ?');
        $d->execute([$delId, $sid]);
        if ($hDate) {
            syncTenantDeleteToMySQL('holidays', ['school_id' => $sid, 'date' => $hDate]);
        }
        flash_set('success', 'सुट्टी वगळली.');
    } elseif ($manage && isset($_POST['delete_ev'])) {
        $evId = (int)$_POST['delete_ev'];
        $d = $pdo->prepare('DELETE FROM calendar_events WHERE id = ? AND school_id = ?');
        $d->execute([$evId, $sid]);
        syncTenantDeleteToMySQL('calendar_events', ['id' => $evId, 'school_id' => $sid]);
        flash_set('success', 'दिनविशेष वगळला.');
    } elseif ($manage && isset($_POST['add_ev'])) {
        $evDate = posted_date();
        $title = trim((string)$_POST['title']);
        if ($title !== '') {
            $p = $pdo->prepare('INSERT INTO calendar_events(school_id, date, title, event_type, source) VALUES (?, ?, ?, ?, ?)');
            $p->execute([$sid, $evDate, $title, 'उपक्रम', 'शाळा']);
            $lastId = (int)$pdo->lastInsertId();

            syncTenantRecordToMySQL('calendar_events', [
                'id'         => $lastId,
                'school_id'  => $sid,
                'date'       => $evDate,
                'title'      => $title,
                'event_type' => 'उपक्रम',
                'source'     => 'शाळा',
            ], ['id']);
            flash_set('success', 'दिनविशेष / उपक्रम जोडला.');
        }
    } elseif ($manage && isset($_POST['name'])) {
        $hDate = posted_date();
        $hName = trim((string)$_POST['name']);
        if ($hName !== '') {
            $p = $pdo->prepare("INSERT OR REPLACE INTO holidays(school_id, date, name, kind) VALUES (?, ?, ?, 'govt')");
            $p->execute([$sid, $hDate, $hName]);

            syncTenantRecordToMySQL('holidays', [
                'school_id' => $sid,
                'date'      => $hDate,
                'name'      => $hName,
                'kind'      => 'govt',
            ], ['school_id', 'date']);
            flash_set('success', 'शाळा सुट्टी जोडली.');
        }
    }

    header('Location: ' . APP_URL . '/modules/tachan/holidays.php?ym=' . $ym);
    exit;
}

$hq = $pdo->prepare('SELECT id, date, name, kind FROM holidays WHERE school_id = ? AND date LIKE ? ORDER BY date');
$hq->execute([$sid, $ym . '-%']);
$hol = [];
foreach ($hq as $r) {
    $hol[substr((string)$r['date'], 0, 10)] = $r;
}

$eq = $pdo->prepare('SELECT id, date, title, event_type FROM calendar_events WHERE school_id = ? AND date LIKE ? ORDER BY date');
$eq->execute([$sid, $ym . '-%']);
$events = $eq->fetchAll(PDO::FETCH_ASSOC);

$prev = date('Y-m', mktime(0, 0, 0, $M - 1, 1, $Y));
$next = date('Y-m', mktime(0, 0, 0, $M + 1, 1, $Y));
$first = mktime(0, 0, 0, $M, 1, $Y);
$days = (int)date('t', $first);
$startDow = (int)date('w', $first); // 0 = Sunday
$dn = ['र', 'सो', 'मं', 'बु', 'गु', 'शु', 'श'];

tachan_layout_start('शालेय सुट्ट्या व दिनविशेष', 'holidays');
?>

<style>
.holiday-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
}
.holiday-dh {
    text-align: center;
    font-weight: 700;
    color: #1E293B;
    padding: 6px 0;
    font-size: 0.9rem;
}
.holiday-dh.sun {
    color: #DC2626;
}
.holiday-cell {
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    min-height: 70px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    cursor: pointer;
    background: #ffffff;
    user-select: none;
    position: relative;
    transition: all 0.15s ease;
    padding: 4px;
}
.holiday-cell:hover {
    border-color: #CBD5E1;
    transform: translateY(-1px);
}
.holiday-cell small {
    font-weight: 400;
    font-size: 0.68rem;
    color: #475569;
    text-align: center;
    line-height: 1.15;
    margin-top: 2px;
}
.holiday-cell.sun {
    background: #FFF1F2;
    color: #E11D48;
    cursor: default;
    border-color: #FECDD3;
}
.holiday-cell.govt {
    background: #DBEAFE;
    border-color: #BFDBFE;
    color: #1E40AF;
    cursor: default;
}
.holiday-cell:has(input:checked) {
    background: #167A5B;
    color: #ffffff;
    border-color: #0F4F3B;
    box-shadow: 0 2px 8px rgba(22,122,91, 0.35);
}
.holiday-cell:has(input:checked) small {
    color: #ffffff;
}
.holiday-cell input {
    display: none;
}
.holiday-legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
}
.holiday-legend i {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    display: inline-block;
    border: 1px solid #CBD5E1;
}
</style>

<!-- Month Navigation & Legend -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= APP_URL ?>/modules/tachan/holidays.php?ym=<?= $prev ?>" class="btn btn-outline-secondary btn-sm rounded-circle" style="width: 36px; height: 36px;">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <h4 class="mb-0 fw-bold text-dark">
                    <?= e($mn[$M]) ?> <?= $Y ?>
                </h4>
                <a href="<?= APP_URL ?>/modules/tachan/holidays.php?ym=<?= $next ?>" class="btn btn-outline-secondary btn-sm rounded-circle" style="width: 36px; height: 36px;">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <div class="holiday-legend d-flex flex-wrap gap-3 text-secondary">
                <span><i style="background: #167A5B;"></i> माझी सुट्टी (क्लिक करून संपादन करा)</span>
                <span><i style="background: #DBEAFE;"></i> शासकीय / शाळा सुट्टी</span>
                <span><i style="background: #FFF1F2;"></i> रविवार</span>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Grid Card -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-calendar-event text-warning me-2"></i>
                <?= e($mn[$M]) ?> <?= $Y ?> शालेय सुट्ट्या कॅलेंडर
            </h5>
            <div class="small text-white-50 mt-1">सुट्टीच्या दिवशी दैनिक व साप्ताहिक पाठ टाचण तयार होत नाही</div>
        </div>
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
            <i class="bi bi-shield-check"></i> सुरक्षित शाळा डेटाबेस
        </span>
    </div>

    <form method="post" class="p-3 p-md-4">
        <?= tachan_csrf_field() ?>
        <input type="hidden" name="ym" value="<?= e($ym) ?>">

        <div class="holiday-grid mb-4">
            <?php foreach ($dn as $i => $d): ?>
                <div class="holiday-dh <?= $i === 0 ? 'sun' : '' ?>"><?= e($d) ?></div>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $startDow; $i++): ?>
                <div></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $days; $d++):
                $date = sprintf('%04d-%02d-%02d', $Y, $M, $d);
                $dow = ($startDow + $d - 1) % 7;
                $h = $hol[$date] ?? null;
                $cls = $dow === 0 ? 'sun' : ($h ? (($h['kind'] ?? 'govt') === 'user' ? 'user' : 'govt') : '');
            ?>
                <label class="holiday-cell <?= $cls ?>">
                    <?php if ($dow !== 0 && $cls !== 'govt'): ?>
                        <input type="checkbox" name="d[]" value="<?= $date ?>" <?= $cls === 'user' ? 'checked' : '' ?>>
                    <?php endif; ?>
                    <span class="fs-6"><?= $d ?></span>
                    <?php if ($h && $cls === 'govt'): ?>
                        <small><?= e($h['name']) ?></small>
                    <?php endif; ?>
                </label>
            <?php endfor; ?>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 pt-3 border-top">
            <div class="small text-muted">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                तारखेवर क्लिक करून तुमची सुट्टी चालू/बंद करा, त्यानंतर <strong>"सुट्ट्या जतन करा"</strong> दाबा.
            </div>
            <button type="submit" name="save_cal" value="1" class="btn btn-success px-5 py-2 fw-bold rounded-pill shadow-sm">
                <i class="bi bi-check-circle-fill me-2"></i> सुट्ट्या जतन करा
            </button>
        </div>
    </form>
</div>

<!-- Events & Administrative Operations -->
<div class="row g-4">
    <!-- Events of the Month -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-light py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-flag-fill text-primary me-2"></i>
                    या महिन्यातील दिनविशेष व उपक्रम (<?= count($events) ?>)
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <?php if (!$events): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x fs-2 d-block mb-1 text-secondary"></i>
                        या महिन्यात दिनविशेष / उपक्रम नोंदवलेले नाहीत.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($events as $r): ?>
                            <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark"><?= e($r['title']) ?></strong>
                                    <span class="badge bg-secondary-subtle text-secondary ms-1 fs-8"><?= e($r['event_type']) ?></span>
                                    <div class="small text-muted">
                                        <?= e(date('d', strtotime((string)$r['date']))) ?> <?= e($mn[$M]) ?>
                                    </div>
                                </div>
                                <?php if ($manage): ?>
                                    <form method="post" onsubmit="return confirm('वगळायचे का?')" class="d-inline">
                                        <?= tachan_csrf_field() ?>
                                        <input type="hidden" name="ym" value="<?= e($ym) ?>">
                                        <button type="submit" name="delete_ev" value="<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill small">
                                            वगळा
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Administrative Actions -->
    <?php if ($manage): ?>
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-light py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-cloud-arrow-down-fill text-primary me-2"></i> शासकीय आयात
                    </h6>
                </div>
                <div class="card-body p-3 p-md-4 d-flex flex-column gap-2">
                    <form method="post">
                        <?= tachan_csrf_field() ?>
                        <input type="hidden" name="ym" value="<?= e($ym) ?>">
                        <button type="submit" name="import" value="1" class="btn btn-outline-dark btn-sm w-100 fw-bold rounded-3 py-2 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-building me-2"></i> २०२६ शासकीय सुट्ट्या आयात करा</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </form>
                    <form method="post">
                        <?= tachan_csrf_field() ?>
                        <input type="hidden" name="ym" value="<?= e($ym) ?>">
                        <button type="submit" name="import_upakram" value="1" class="btn btn-outline-primary btn-sm w-100 fw-bold rounded-3 py-2 text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-stars me-2"></i> दिनविशेष / उपक्रम आयात करा</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Add Single Holiday -->
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-light py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-plus-circle-fill text-warning me-2"></i> नवीन सुट्टी जोडा
                    </h6>
                </div>
                <form method="post" class="card-body p-3 p-md-4">
                    <?= tachan_csrf_field() ?>
                    <input type="hidden" name="ym" value="<?= e($ym) ?>">
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">दिनांक</label>
                        <input type="date" name="date" value="<?= e($ym) ?>-01" class="form-control form-control-sm rounded-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">सुट्टीचे नाव</label>
                        <input required name="name" placeholder="उदा. स्थानिक जत्रा सुट्टी" class="form-control form-control-sm rounded-3">
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold rounded-pill text-dark">
                        सुट्टी जोडा
                    </button>
                </form>
            </div>

            <!-- Add Single Event -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-light py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-bookmark-plus-fill text-primary me-2"></i> दिनविशेष / उपक्रम जोडा
                    </h6>
                </div>
                <form method="post" class="card-body p-3 p-md-4">
                    <?= tachan_csrf_field() ?>
                    <input type="hidden" name="ym" value="<?= e($ym) ?>">
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1">दिनांक</label>
                        <input type="date" name="date" value="<?= e($ym) ?>-01" class="form-control form-control-sm rounded-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">उपक्रमाचे शीर्षक</label>
                        <input required name="title" placeholder="उदा. विज्ञान दिन प्रदर्शन" class="form-control form-control-sm rounded-3">
                    </div>
                    <button type="submit" name="add_ev" value="1" class="btn btn-primary btn-sm w-100 fw-bold rounded-pill" style="background:#167A5B; border-color:#167A5B;">
                        उपक्रम जोडा
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php tachan_layout_end(); ?>
