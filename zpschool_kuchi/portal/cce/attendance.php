<?php
$page_title = 'उपस्थिती नोंदणी';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$school_id = (int)$_SESSION['school_id'];
$db = cce_db($school_id);
$mysql = getMainDB();
$year = academic_year();
$standards = cce_school_standards($school_id);

$std = (int)($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
$sel_month = (int)($_REQUEST['month'] ?? 0);
$students = cce_students($school_id, $std);
$months = cce_months();

// School-level working days per month (JSON on schools.working_days_monthly)
$school = getSchool();
$working = json_decode((string)($school['working_days_monthly'] ?? ''), true) ?: [];
$total_school_working_days = array_sum(array_map('intval', $working));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    cce_check_csrf();
    $action = $_POST['action'] ?? 'save_attendance';

    // Handle school working days update
    if ($action === 'save_working_days' || isset($_POST['working'])) {
        $wd = [];
        foreach ($months as $mn => $label) {
            $wd[$mn] = max(0, (int)($_POST['working'][$mn] ?? 0));
        }
        $json = json_encode($wd, JSON_UNESCAPED_UNICODE);
        $stmt = $mysql->prepare("UPDATE schools SET working_days_monthly = ? WHERE id = ?");
        $stmt->execute([$json, $school_id]);
        try {
            $db->prepare("UPDATE school_info SET working_days_monthly = ? WHERE id = ?")->execute([$json, $school_id]);
        } catch (Exception $e) {}
        $working = $wd;
        $total_school_working_days = array_sum(array_map('intval', $working));
        if ($action === 'save_working_days') {
            flash('success', 'शाळेचे कामाचे दिवस यशस्वीरित्या जतन केले!');
            redirect(APP_URL . "/cce/attendance.php?std=$std&month=$sel_month");
        }
    }

    if ($action === 'save_attendance') {
        $allowed = array_column($students, 'id');
        $months_to_save = $sel_month > 0 ? [$sel_month => $months[$sel_month]] : $months;

        $ins = $db->prepare("INSERT OR REPLACE INTO attendance (student_id, academic_year, month, working_days, days_present)
                             VALUES (?,?,?,?,?)");

        foreach (($_POST['p'] ?? []) as $sid => $row) {
            $sid = (int)$sid;
            if (!in_array($sid, $allowed, true)) continue;
            foreach ($months_to_save as $mn => $label) {
                $val = trim((string)($row[$mn] ?? ''));
                if ($val === '') continue;
                $wd_days = (int)($working[$mn] ?? 0);
                $ins->execute([$sid, $year, $mn, $wd_days, max(0, (int)$val)]);
            }
        }
        flash('success', 'विद्यार्थ्यांची उपस्थिती जतन केली.');
        redirect(APP_URL . "/cce/attendance.php?std=$std&month=$sel_month");
    }
}

$att = [];
foreach ($students as $s) $att[(int)$s['id']] = cce_attendance((int)$s['id'], $year);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="cce-page">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h2 class="mb-1"><i class="bi bi-calendar-check" style="color:var(--cce-orange)"></i> मासिक उपस्थिती नोंदणी</h2>
        <div class="text-muted small">इयत्ता <?= sanitize(std_label($std)) ?> · शैक्षणिक वर्ष <?= sanitize($year) ?></div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-warning btn-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#workingDaysModal">
            <i class="bi bi-pencil-square"></i> शाळेचे कामाचे दिवस बदला
        </button>
        <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
    </div>
</div>

<!-- WORKING DAYS INFO CARD -->
<div class="card border-0 shadow-sm p-3 mb-3" style="background:#FFF8E1; border-left:4px solid #167A5B !important; border-radius:10px;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <span class="fw-bold" style="color:#0F4F3B;"><i class="bi bi-info-circle-fill"></i> शाळेचे अधिकृत कामाचे दिवस:</span>
            <span class="text-muted small ms-1">वार्षिक एकूण: <b><?= to_mr_digits($total_school_working_days) ?></b> दिवस (सर्व विद्यार्थ्यांसाठी एकच नोंद)</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($sel_month > 0): ?>
                <span class="badge bg-primary fs-6 px-3 py-2">
                    <?= $months[$sel_month] ?> चे कामाचे दिवस: <b><?= to_mr_digits((int)($working[$sel_month] ?? 0)) ?></b> दिवस
                </span>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#workingDaysModal">
                    महिनानिहाय कामाचे दिवस पहा / बदला
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FILTER BAR -->
<form method="GET" class="card card-body mb-3 border-0 shadow-sm py-2">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold">इयत्ता</label>
            <select name="std" class="form-select" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>" <?= $i === $std ? 'selected' : '' ?>><?= std_label($i) ?></option><?php endfor; ?>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label small fw-semibold text-primary">महिना निवडा (मोबाईलसाठी सोयीस्कर)</label>
            <select name="month" class="form-select border-primary" onchange="this.form.submit()">
                <option value="0" <?= $sel_month === 0 ? 'selected' : '' ?>>— सर्व महिने (वार्षिक तक्ता) —</option>
                <?php foreach ($months as $mn => $label): ?>
                    <option value="<?= $mn ?>" <?= $sel_month === $mn ? 'selected' : '' ?>>
                        <?= $label ?> (कामाचे दिवस: <?= to_mr_digits((int)($working[$mn] ?? 0)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-5 d-flex gap-2 align-items-end">
            <button class="btn btn-outline-primary"><i class="bi bi-filter"></i> दाखवा</button>
            <?php if ($sel_month > 0 && $students): ?>
                <button type="button" class="btn btn-outline-success ms-auto" onclick="markAllPresent(<?= (int)($working[$sel_month] ?? 0) ?>)">
                    <i class="bi bi-check-all"></i> सर्व विद्यार्थी पूर्ण हजर करा
                </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (!$students): ?>
    <div class="alert alert-warning">या इयत्तेत विद्यार्थी नाहीत.</div>
<?php else: ?>

<form method="POST">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="action" value="save_attendance">
    <input type="hidden" name="std" value="<?= $std ?>">
    <input type="hidden" name="month" value="<?= $sel_month ?>">

    <?php if ($sel_month > 0): 
        // SINGLE MONTH MOBILE-FRIENDLY TOUCH CARD VIEW
        $cur_wd = (int)($working[$sel_month] ?? 0);
    ?>
    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
        <span class="fw-bold" style="font-size:1.1rem; color:#0F4F3B;">
            <i class="bi bi-calendar-event"></i> <?= $months[$sel_month] ?> महिना उपस्थिती
        </span>
        <button type="button" class="btn btn-sm btn-success fw-bold" onclick="markAllPresent(<?= $cur_wd ?>)">
            <i class="bi bi-lightning-fill"></i> सर्व पूर्ण हजर (<?= to_mr_digits($cur_wd) ?> दिवस)
        </button>
    </div>

    <div class="row g-2">
        <?php foreach ($students as $st): 
            $val = $att[(int)$st['id']][$sel_month]['days_present'] ?? '';
            $st_id = (int)$st['id'];
        ?>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm p-3 h-100" style="background:#fff; border-radius:10px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <span class="badge bg-secondary me-1">ह.क्र. <?= to_mr_digits($st['roll_no']) ?></span>
                        <b class="fs-6"><?= sanitize($st['name_mr'] ?: $st['name']) ?></b>
                    </div>
                    <span class="small text-muted">कामाचे: <?= to_mr_digits($cur_wd) ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3 py-2 fw-bold" style="font-size:1.2rem; min-width:44px;" onclick="stepVal('att_<?= $st_id ?>', -1, <?= $cur_wd ?>)">−</button>
                    <input type="number" min="0" max="<?= $cur_wd ?>" class="form-control text-center att-input fw-bold" style="font-size:1.2rem; height:46px; border-radius:8px;" id="att_<?= $st_id ?>" name="p[<?= $st_id ?>][<?= $sel_month ?>]" value="<?= $val === '' ? '' : (int)$val ?>" placeholder="0">
                    <button type="button" class="btn btn-outline-secondary px-3 py-2 fw-bold" style="font-size:1.2rem; min-width:44px;" onclick="stepVal('att_<?= $st_id ?>', 1, <?= $cur_wd ?>)">+</button>
                    <button type="button" class="btn btn-outline-success px-2 py-2 fw-semibold" style="height:46px; white-space:nowrap;" onclick="setVal('att_<?= $st_id ?>', <?= $cur_wd ?>)">
                        पूर्ण (<?= to_mr_digits($cur_wd) ?>)
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: 
        // FULL 12-MONTH TABLE VIEW WITH STICKY STUDENT COLUMNS FOR MOBILE
    ?>
    <div class="table-responsive card border-0 shadow-sm mb-3" style="max-height:75vh; overflow:auto;">
        <table class="table table-sm table-bordered align-middle mb-0" id="attTable">
            <thead class="table-light text-center sticky-top" style="z-index:2;">
                <tr>
                    <th style="min-width:45px; position:sticky; left:0; background:#f8f9fa; z-index:3;">क्र.</th>
                    <th style="min-width:170px; text-align:left; position:sticky; left:45px; background:#f8f9fa; z-index:3;">विद्यार्थ्याचे नाव</th>
                    <?php foreach ($months as $mn => $label): ?>
                        <th style="min-width:58px;"><?= $label ?></th>
                    <?php endforeach; ?>
                    <th style="min-width:65px;">एकूण</th>
                </tr>
                <tr class="table-secondary">
                    <th colspan="2" class="text-end" style="position:sticky; left:0; background:#e9ecef; z-index:3;">
                        शाळेचे कामाचे दिवस:
                    </th>
                    <?php foreach ($months as $mn => $label): ?>
                        <th class="p-1">
                            <input class="form-control form-control-sm text-center fw-bold" type="number" min="0" max="31" name="working[<?= $mn ?>]" value="<?= (int)($working[$mn] ?? 0) ?>">
                        </th>
                    <?php endforeach; ?>
                    <th class="fw-bold"><?= to_mr_digits(array_sum(array_map('intval', $working))) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $st): 
                $rows = $att[(int)$st['id']]; 
                $tot = 0; 
                $st_id = (int)$st['id'];
            ?>
                <tr data-row>
                    <td class="text-center" style="position:sticky; left:0; background:#fff; z-index:1;"><?= sanitize($st['roll_no']) ?></td>
                    <td style="position:sticky; left:45px; background:#fff; z-index:1;"><b><?= sanitize($st['name_mr'] ?: $st['name']) ?></b></td>
                    <?php foreach ($months as $mn => $label): 
                        $v = $rows[$mn]['days_present'] ?? ''; 
                        $tot += (int)$v; 
                    ?>
                        <td class="p-1">
                            <input class="form-control form-control-sm text-center att" type="number" min="0" max="31" name="p[<?= $st_id ?>][<?= $mn ?>]" value="<?= $v === '' ? '' : (int)$v ?>">
                        </td>
                    <?php endforeach; ?>
                    <td class="text-center fw-bold rowtot" style="background:#FFF8E1;"><?= to_mr_digits($tot) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 sticky-bottom p-2 bg-white border rounded shadow-sm mt-3" style="z-index:4;">
        <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold" style="background:#167A5B; border-color:#167A5B;">
            <i class="bi bi-save"></i> उपस्थिती जतन करा
        </button>
        <?php if ($sel_month > 0): ?>
            <a href="<?= APP_URL ?>/cce/attendance.php?std=<?= $std ?>&month=0" class="btn btn-outline-secondary btn-lg">
                पूर्ण वार्षिक तक्ता पहा
            </a>
        <?php endif; ?>
    </div>
</form>

<script>
function stepVal(id, delta, max) {
    var el = document.getElementById(id);
    if (!el) return;
    var cur = parseInt(el.value, 10);
    if (isNaN(cur)) cur = 0;
    var next = cur + delta;
    if (next < 0) next = 0;
    if (max > 0 && next > max) next = max;
    el.value = next;
}
function setVal(id, val) {
    var el = document.getElementById(id);
    if (el) el.value = val;
}
function markAllPresent(days) {
    if (confirm("सर्व विद्यार्थ्यांना " + days + " दिवस उपस्थित करायचे आहे का?")) {
        document.querySelectorAll('.att-input').forEach(function(i) {
            i.value = days;
        });
    }
}
document.querySelectorAll('tr[data-row]').forEach(function (row) {
    row.addEventListener('input', function () {
        var t = 0;
        row.querySelectorAll('input.att').forEach(function (i) { 
            var v = parseInt(i.value, 10); 
            if (!isNaN(v)) t += v; 
        });
        row.querySelector('.rowtot').textContent = t;
    });
});
</script>
<?php endif; ?>

<!-- Working Days Modal (Accessible right from Attendance page) -->
<div class="modal fade" id="workingDaysModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= cce_csrf_field() ?>
        <input type="hidden" name="action" value="save_working_days">
        <div class="modal-header text-white" style="background: linear-gradient(135deg, #167A5B, #0F4F3B);">
          <h5 class="modal-title"><i class="bi bi-calendar3"></i> शाळेचे मासिक कामाचे दिवस निश्चित करा</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <p class="text-muted mb-3">हे दिवस सर्व विद्यार्थ्यांच्या मासिक उपस्थितीसाठी ग्राह्य धरले जातील. चालू शैक्षणिक वर्षाचे कामाचे दिवस येथे नोंदवावे.</p>
          <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
            <span class="small fw-semibold text-muted">जलद पर्याय:</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillAllWd(24)">सर्व महिने २४ दिवस</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillAllWd(22)">सर्व महिने २२ दिवस</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillAllWd(25)">सर्व महिने २५ दिवस</button>
          </div>
          <div class="row g-3">
            <?php foreach ($months as $mn => $lbl): ?>
            <div class="col-6 col-md-3">
              <label class="form-label small fw-bold mb-1"><?= $lbl ?>:</label>
              <div class="input-group input-group-sm">
                <input type="number" class="form-control text-center modal-wd-input" min="0" max="31" name="working[<?= $mn ?>]" value="<?= (int)($working[$mn] ?? 0) ?>" oninput="recalcModalWd()">
                <span class="input-group-text">दिवस</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="alert alert-info mt-3 mb-0 py-2 d-flex justify-content-between align-items-center">
            <span>वार्षिक एकूण कामाचे दिवस:</span>
            <b class="fs-5 text-primary" id="modalWdDisplay"><?= to_mr_digits($total_school_working_days) ?></b>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करा</button>
          <button type="submit" class="btn btn-primary fw-bold" style="background:#167A5B; border-color:#167A5B;">
            <i class="bi bi-check-circle"></i> कामाचे दिवस जतन करा
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function fillAllWd(days) {
  document.querySelectorAll('.modal-wd-input').forEach(function(i) { i.value = days; });
  recalcModalWd();
}
function recalcModalWd() {
  var t = 0;
  document.querySelectorAll('.modal-wd-input').forEach(function(i) {
    var v = parseInt(i.value, 10);
    if (!isNaN(v) && v > 0) t += v;
  });
  document.getElementById('modalWdDisplay').textContent = t;
}
</script>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
