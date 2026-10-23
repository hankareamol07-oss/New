<?php
$page_title = 'नोंदी बँक';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();

$sources = [
    'sanch'     => ['label' => 'संच — क्षमतानिहाय कृती व पातळी (मुलगा)', 'table' => 'cce_sanch'],
    'sanch2'    => ['label' => 'संच — क्षमतानिहाय कृती व पातळी (मुलगी)', 'table' => 'cce_sanch2'],
    'kamgiri'   => ['label' => 'कामगिरी विधाने', 'table' => 'cce_kamgiri'],
    'abhipray'  => ['label' => 'अभिप्राय (क्षेत्रनिहाय)', 'table' => 'cce_abhipray'],
    'abhipray2' => ['label' => 'अभिप्राय — क्षमतानिहाय', 'table' => 'cce_abhipray2'],
];

$src = $_GET['src'] ?? 'sanch';
if (!isset($sources[$src])) $src = 'sanch';
$q = trim((string) ($_GET['q'] ?? ''));
$std = (int) ($_GET['std'] ?? 0);

$rows = [];
$error = '';
try {
    $table = $sources[$src]['table'];
    $where = [];
    $args = [];
    if (in_array($src, ['sanch', 'sanch2'], true)) {
        $cols = 'std, vikas AS gat, capacity AS kod, nirzar1, nirzar2, nirzar3, parvat1, parvat2, parvat3, akash1, akash2, akash3';
        if ($std) { $where[] = 'std = ?'; $args[] = $std; }
        if ($q !== '') { $where[] = '(capacity LIKE ? OR vikas LIKE ? OR nirzar1 LIKE ? OR parvat1 LIKE ? OR akash1 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
    } elseif ($src === 'kamgiri') {
        $cols = 'cg AS kod, domain AS gat, types AS std, vidhan AS nirzar1, vidhan2 AS nirzar2';
        if ($q !== '') { $where[] = '(cg LIKE ? OR domain LIKE ? OR vidhan LIKE ? OR vidhan2 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
    } elseif ($src === 'abhipray') {
        $cols = 'kshetra AS std, vishay AS gat, notes AS nirzar1, notes2 AS nirzar2';
        if ($q !== '') { $where[] = '(vishay LIKE ? OR notes LIKE ? OR notes2 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
    } else {
        $cols = 'domain AS std, capacity AS kod, num AS gat, notes AS nirzar1, notes2 AS nirzar2';
        if ($q !== '') { $where[] = '(capacity LIKE ? OR notes LIKE ? OR notes2 LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
    }
    $sql = "SELECT $cols FROM `$table`" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' LIMIT 400';
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'ही बँक उपलब्ध नाही.';
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-journal-text"></i> नोंदी बँक (SCERT डेटासेट)</h2>
    <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
</div>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small">बँक निवडा</label>
            <select name="src" class="form-select" onchange="this.form.submit()">
                <?php foreach ($sources as $k => $s): ?>
                    <option value="<?= $k ?>" <?= $k === $src ? 'selected' : '' ?>><?= sanitize($s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">इयत्ता</label>
            <select name="std" class="form-select">
                <option value="0">सर्व</option>
                <?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>" <?= $i === $std ? 'selected' : '' ?>><?= std_label($i) ?></option><?php endfor; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">शोध</label>
            <input type="text" name="q" class="form-control" value="<?= sanitize($q) ?>" placeholder="क्षमता कोड किंवा मजकूर...">
        </div>
        <div class="col-md-1 d-grid">
            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
        </div>
    </div>
</form>

<?php if ($error): ?>
    <div class="alert alert-warning"><?= sanitize($error) ?></div>
<?php elseif (!$rows): ?>
    <div class="alert alert-info">या शोधासाठी नोंदी सापडल्या नाहीत.</div>
<?php else: ?>
<div class="mb-2 text-muted small">एकूण नोंदी: <?= to_mr_digits(count($rows)) ?> (जास्तीत जास्त ४०० दाखवल्या जातात) — मजकुरावर क्लिक करून कॉपी करा.</div>
<div class="row g-3">
    <?php foreach ($rows as $r): ?>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header py-2 small fw-bold d-flex justify-content-between">
                <span><?= sanitize((string) ($r['kod'] ?? '')) ?> <?= sanitize((string) ($r['gat'] ?? '')) ?></span>
                <?php if (!empty($r['std'])): ?><span class="badge bg-secondary"><?= sanitize((string) $r['std']) ?></span><?php endif; ?>
            </div>
            <div class="card-body small">
                <?php
                $labels = ['nirzar1' => 'निर्झर — जागरूकता', 'nirzar2' => 'निर्झर — संवेदनशीलता', 'nirzar3' => 'निर्झर — सर्जनशीलता',
                           'parvat1' => 'पर्वत — जागरूकता', 'parvat2' => 'पर्वत — संवेदनशीलता', 'parvat3' => 'पर्वत — सर्जनशीलता',
                           'akash1'  => 'आकाश — जागरूकता', 'akash2' => 'आकाश — संवेदनशीलता', 'akash3' => 'आकाश — सर्जनशीलता'];
                foreach ($labels as $col => $lab):
                    $txt = trim((string) ($r[$col] ?? ''));
                    if ($txt === '') continue; ?>
                    <div class="mb-2">
                        <div class="text-primary" style="font-size:11px;"><?= sanitize($lab) ?></div>
                        <div class="copy-text" style="cursor:copy;" title="कॉपी करण्यासाठी क्लिक करा"><?= sanitize($txt) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.copy-text').forEach(function (el) {
    el.addEventListener('click', function () {
        navigator.clipboard.writeText(el.textContent.trim());
        el.style.background = '#d4edda';
        setTimeout(function () { el.style.background = ''; }, 700);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
