<?php
/**
 * विद्यार्थी अतिरिक्त माहिती — उंची/वजन, SARAL/PEN, धर्म/जात, निकाल.
 */
$page_title = 'विद्यार्थी अतिरिक्त माहिती';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$db = getDB();
$school_id = $_SESSION['school_id'];
$year = academic_year();
$standards = cce_school_standards($school_id);
$std = (int)($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
$students = cce_students($school_id, $std);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cce_check_csrf();
    $allowed = array_column($students, 'id');
    $upsert = $db->prepare("
        INSERT INTO cce_student_extra
            (student_id, school_id, academic_year,
             height_1, weight_1, height_2, weight_2,
             cast_cat, religion, saral_id, pen,
             remark, result_status, next_std)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            academic_year=VALUES(academic_year),
            height_1=VALUES(height_1), weight_1=VALUES(weight_1),
            height_2=VALUES(height_2), weight_2=VALUES(weight_2),
            cast_cat=VALUES(cast_cat), religion=VALUES(religion),
            saral_id=VALUES(saral_id), pen=VALUES(pen),
            remark=VALUES(remark),
            result_status=VALUES(result_status),
            next_std=VALUES(next_std)
    ");
    $upd_student = $db->prepare("UPDATE students SET 
        height_sem1 = NULLIF(?, ''), weight_sem1 = NULLIF(?, ''),
        height_sem2 = NULLIF(?, ''), weight_sem2 = NULLIF(?, ''),
        saral_id = COALESCE(NULLIF(?, ''), saral_id),
        student_pen = COALESCE(NULLIF(?, ''), student_pen),
        caste_category = COALESCE(NULLIF(?, ''), caste_category),
        religion = COALESCE(NULLIF(?, ''), religion)
        WHERE id = ? AND school_id = ?");

    foreach (($_POST['ex'] ?? []) as $sid => $row) {
        $sid = (int)$sid;
        if (!in_array($sid, $allowed, true)) continue;
        $h1 = trim($row['height_1'] ?? '');
        $w1 = trim($row['weight_1'] ?? '');
        $h2 = trim($row['height_2'] ?? '');
        $w2 = trim($row['weight_2'] ?? '');
        $saral = trim($row['saral_id']  ?? '');
        $pen = trim($row['pen'] ?? '');
        $cat = trim($row['cast_cat']  ?? '');
        $rel = trim($row['religion'] ?? '');

        $upsert->execute([
            $sid, $school_id, $year,
            $h1, $w1,
            $h2, $w2,
            $cat, $rel,
            $saral, $pen,
            trim($row['remark']    ?? ''),
            trim($row['result_status'] ?? ''),
            trim($row['next_std']  ?? ''),
        ]);

        try {
            $upd_student->execute([$h1, $w1, $h2, $w2, $saral, $pen, $cat, $rel, $sid, $school_id]);
        } catch (Throwable $e) {}
    }
    flash('success', 'अतिरिक्त माहिती जतन केली.');
    redirect(APP_URL . "/cce/extra.php?std=$std");
}

$extra = [];
if ($students) {
    $ids = array_column($students, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $db->prepare("SELECT * FROM cce_student_extra WHERE student_id IN ($in)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $r) $extra[(int)$r['student_id']] = $r;
    } catch (PDOException $e) {}
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person-lines-fill"></i> विद्यार्थी अतिरिक्त माहिती</h2>
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
<?php if (!$students): ?>
    <div class="alert alert-warning">या इयत्तेत विद्यार्थी नाहीत.</div>
<?php else: ?>
<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle"></i>
    उंची (cm), वजन (kg) — <b>_1</b> = पहिल्या सत्राच्या सुरुवातीस, <b>_2</b> = दुसऱ्या सत्राच्या सुरुवातीस.
</div>
<form method="POST">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="std" value="<?= $std ?>">
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle" style="font-size:12px">
            <thead class="table-light text-center">
                <tr>
                    <th rowspan="2">क्र.</th>
                    <th rowspan="2" class="text-start" style="min-width:160px">नाव</th>
                    <th colspan="2">उंची</th>
                    <th colspan="2">वजन</th>
                    <th rowspan="2" style="min-width:90px">जात</th>
                    <th rowspan="2" style="min-width:90px">धर्म</th>
                    <th rowspan="2" style="min-width:110px">SARAL ID</th>
                    <th rowspan="2" style="min-width:90px">PEN</th>
                    <th rowspan="2" style="min-width:90px">निकाल</th>
                    <th rowspan="2" style="min-width:80px">पुढे</th>
                    <th rowspan="2" style="min-width:130px">शेरा</th>
                </tr>
                <tr>
                    <th>स.१</th><th>स.२</th><th>स.१</th><th>स.२</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $st):
                $ex = $extra[(int)$st['id']] ?? [];
                $f = function(string $field, int $w = 52) use ($st, $ex): string {
                    $v = htmlspecialchars($ex[$field] ?? '', ENT_QUOTES, 'UTF-8');
                    return "<td><input class=\"form-control form-control-sm\" style=\"width:{$w}px\" "
                         . "name=\"ex[{$st['id']}][{$field}]\" value=\"{$v}\"></td>";
                }; ?>
                <tr>
                    <td class="text-center"><?= sanitize($st['roll_no']) ?></td>
                    <td><?= sanitize($st['name_mr'] ?: $st['name']) ?></td>
                    <?= $f('height_1') . $f('height_2') ?>
                    <?= $f('weight_1') . $f('weight_2') ?>
                    <?= $f('cast_cat', 80) . $f('religion', 80) ?>
                    <?= $f('saral_id', 100) . $f('pen', 80) ?>
                    <td>
                        <select name="ex[<?= $st['id'] ?>][result_status]"
                                class="form-select form-select-sm" style="width:90px">
                            <option value="">—</option>
                            <?php foreach (['उत्तीर्ण','अनुत्तीर्ण','प्रविष्ट','इतर'] as $rs): ?>
                                <option value="<?= $rs ?>"
                                    <?= ($ex['result_status'] ?? '') === $rs ? 'selected' : '' ?>
                                ><?= $rs ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <?= $f('next_std', 65) . $f('remark', 120) ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button class="btn btn-primary mt-2"><i class="bi bi-save"></i> जतन करा</button>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
