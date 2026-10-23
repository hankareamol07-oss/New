<?php
$page_title = 'विषय व्यवस्थापन';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$school_id = $_SESSION['school_id'];
$standards = cce_school_standards($school_id);
$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cce_check_csrf();
    $action = $_POST['action'] ?? '';
    $std = (int)($_POST['std'] ?? $std);

    if ($action === 'seed') {
        $n = cce_seed_subjects($school_id, $std);
        flash('success', $n ? ('डीफॉल्ट विषय जोडले : ' . to_mr_digits($n)) : 'या इयत्तेसाठी विषय आधीच आहेत.');
    } elseif ($action === 'add') {
        $name = trim($_POST['name_mr'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("INSERT INTO cce_subjects (school_id, std, subject_code, name_mr, short_name, fe_max, se_max, sort_order) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $school_id, $std, trim($_POST['subject_code'] ?? ''), $name,
                trim($_POST['short_name'] ?? '') ?: $name,
                max(0, (int)($_POST['fe_max'] ?? 40)), max(0, (int)($_POST['se_max'] ?? 60)),
                (int)($_POST['sort_order'] ?? 99),
            ]);
            $new_id = (int)$db->lastInsertId();

            try {
                $sqldb = cce_db($school_id);
                $stSqlite = $sqldb->prepare("INSERT OR REPLACE INTO cce_subjects (id, school_id, std, subject_code, name_mr, short_name, fe_max, se_max, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,1)");
                $stSqlite->execute([
                    $new_id, $school_id, $std, trim($_POST['subject_code'] ?? ''), $name,
                    trim($_POST['short_name'] ?? '') ?: $name,
                    max(0, (int)($_POST['fe_max'] ?? 40)), max(0, (int)($_POST['se_max'] ?? 60)),
                    (int)($_POST['sort_order'] ?? 99),
                ]);
            } catch (Throwable $e) {}

            flash('success', 'विषय जोडला.');
        }
    } elseif ($action === 'save') {
        $upd = $db->prepare("UPDATE cce_subjects SET name_mr = ?, short_name = ?, fe_max = ?, se_max = ?, sort_order = ?, is_active = ? WHERE id = ? AND school_id = ?");
        foreach (($_POST['subject'] ?? []) as $id => $row) {
            $upd->execute([
                trim($row['name_mr'] ?? ''), trim($row['short_name'] ?? ''),
                max(0, (int)($row['fe_max'] ?? 0)), max(0, (int)($row['se_max'] ?? 0)),
                (int)($row['sort_order'] ?? 0), isset($row['is_active']) ? 1 : 0,
                (int)$id, $school_id,
            ]);
        }

        try {
            $sqldb = cce_db($school_id);
            $updSqlite = $sqldb->prepare("UPDATE cce_subjects SET name_mr = ?, short_name = ?, fe_max = ?, se_max = ?, sort_order = ?, is_active = ? WHERE id = ? AND school_id = ?");
            foreach (($_POST['subject'] ?? []) as $id => $row) {
                $updSqlite->execute([
                    trim($row['name_mr'] ?? ''), trim($row['short_name'] ?? ''),
                    max(0, (int)($row['fe_max'] ?? 0)), max(0, (int)($row['se_max'] ?? 0)),
                    (int)($row['sort_order'] ?? 0), isset($row['is_active']) ? 1 : 0,
                    (int)$id, $school_id,
                ]);
            }
        } catch (Throwable $e) {}

        flash('success', 'विषय जतन केले.');
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM cce_subjects WHERE id = ? AND school_id = ?");
        $stmt->execute([(int)($_POST['id'] ?? 0), $school_id]);

        try {
            $sqldb = cce_db($school_id);
            $sqldb->prepare("DELETE FROM cce_subjects WHERE id = ? AND school_id = ?")->execute([(int)($_POST['id'] ?? 0), $school_id]);
        } catch (Throwable $e) {}

        flash('success', 'विषय काढला.');
    }
    redirect(APP_URL . '/cce/subjects.php?std=' . $std);
}

$subjects = cce_all_subjects($school_id, $std);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-list-check"></i> विषय व्यवस्थापन</h2>
    <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> मागे</a>
</div>

<form method="GET" class="mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-auto"><label class="col-form-label">इयत्ता</label></div>
        <div class="col-auto">
            <select name="std" class="form-select" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === $std ? 'selected' : '' ?>><?= std_label($i) ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</form>

<?php if (!$subjects): ?>
<div class="card mb-4">
    <div class="card-body">
        <p class="mb-2">या इयत्तेसाठी अद्याप विषय नाहीत. सातत्यपूर्ण मूल्यमापन सॉफ्टवेअरच्या विषय यादीमधून डीफॉल्ट विषय जोडा.</p>
        <form method="POST">
            <?= cce_csrf_field() ?>
            <input type="hidden" name="action" value="seed">
            <input type="hidden" name="std" value="<?= $std ?>">
            <button class="btn btn-success"><i class="bi bi-magic"></i> डीफॉल्ट विषय जोडा</button>
        </form>
    </div>
</div>
<?php else: ?>
<form method="POST">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="std" value="<?= $std ?>">
    <div class="table-responsive card">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px">क्रम</th>
                    <th>विषयाचे नाव</th>
                    <th style="width:150px">लघुरूप</th>
                    <th style="width:110px">आकारिक गुण</th>
                    <th style="width:110px">संकलित गुण</th>
                    <th style="width:80px">सुरू</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><input type="number" class="form-control form-control-sm" name="subject[<?= $s['id'] ?>][sort_order]" value="<?= (int)$s['sort_order'] ?>"></td>
                    <td><input type="text" class="form-control form-control-sm" name="subject[<?= $s['id'] ?>][name_mr]" value="<?= sanitize($s['name_mr']) ?>"></td>
                    <td><input type="text" class="form-control form-control-sm" name="subject[<?= $s['id'] ?>][short_name]" value="<?= sanitize($s['short_name']) ?>"></td>
                    <td><input type="number" class="form-control form-control-sm" name="subject[<?= $s['id'] ?>][fe_max]" value="<?= (int)$s['fe_max'] ?>"></td>
                    <td><input type="number" class="form-control form-control-sm" name="subject[<?= $s['id'] ?>][se_max]" value="<?= (int)$s['se_max'] ?>"></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input" name="subject[<?= $s['id'] ?>][is_active]" <?= $s['is_active'] ? 'checked' : '' ?>></td>
                    <td class="text-center">
                        <button type="submit" form="del<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('हा विषय काढायचा? त्याचे गुणही जातील.')"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> जतन करा</button>
</form>
<?php foreach ($subjects as $s): ?>
<form method="POST" id="del<?= $s['id'] ?>" class="d-none">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="std" value="<?= $std ?>">
    <input type="hidden" name="id" value="<?= $s['id'] ?>">
</form>
<?php endforeach; ?>
<?php endif; ?>

<div class="card mt-4">
    <div class="card-header">नवीन विषय जोडा</div>
    <div class="card-body">
        <form method="POST" class="row g-2">
            <?= cce_csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="std" value="<?= $std ?>">
            <div class="col-md-4"><input class="form-control" name="name_mr" placeholder="विषयाचे नाव" required></div>
            <div class="col-md-2"><input class="form-control" name="short_name" placeholder="लघुरूप"></div>
            <div class="col-md-2"><input class="form-control" type="number" name="fe_max" value="40" placeholder="आकारिक"></div>
            <div class="col-md-2"><input class="form-control" type="number" name="se_max" value="60" placeholder="संकलित"></div>
            <div class="col-md-2"><button class="btn btn-success w-100"><i class="bi bi-plus"></i> जोडा</button></div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
