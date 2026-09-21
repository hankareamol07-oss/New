<?php
require_once __DIR__ . '/includes/functions.php';
$epPage = 'bank';
$epTitle = 'Question Bank';

$standardId = (int)($_GET['standard_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);
$chapterId = (int)($_GET['chapter_id'] ?? 0);
$type = trim($_GET['type'] ?? '');
$search = trim($_GET['q'] ?? '');
$onlyImages = !empty($_GET['images']);
$page = max(1, (int)($_GET['page'] ?? 1));
$size = 25;

$subjects = $standardId ? ep_subjects($standardId) : [];
$chapters = $subjectId ? ep_chapters($subjectId) : [];

$rows = [];
$total = 0;
if ($subjectId) {
    $where = ['c.subject_id = ?'];
    $params = [$subjectId];
    if ($chapterId) { $where[] = 'q.chapter_id = ?'; $params[] = $chapterId; }
    if ($type) { $where[] = 'q.question_type = ?'; $params[] = ep_normalize_type($type); }
    if ($search) { $where[] = '(q.markup LIKE ? OR q.passage LIKE ? OR q.answer_markup LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
    if ($onlyImages) { $where[] = '(q.has_image = 1 OR q.has_image2 = 1 OR q.has_answer_image = 1)'; }
    $sql = 'FROM ep_questions q JOIN ep_chapters c ON c.chapter_id = q.chapter_id WHERE ' . implode(' AND ', $where);
    $st = ep_db()->prepare("SELECT COUNT(*) $sql");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $st = ep_db()->prepare("SELECT q.*, c.name AS chapter_name $sql ORDER BY c.sort_order, q.question_number, q.question_id LIMIT $size OFFSET " . (($page - 1) * $size));
    $st->execute($params);
    $rows = array_map('ep_decode_question', $st->fetchAll());
}
$pages = max(1, (int)ceil($total / $size));
$qs = fn(array $o = []) => '?' . http_build_query(array_merge($_GET, $o));

require __DIR__ . '/includes/header.php';
?>
<h3 class="mb-3">Question Bank</h3>
<form class="card shadow-sm mb-3" method="get">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label small">Class</label>
      <select name="standard_id" class="form-select" onchange="this.form.subject_id.value='';this.form.chapter_id.value='';this.form.submit()">
        <option value="">-- class --</option>
        <?php foreach (ep_standards() as $s): ?>
          <option value="<?= $s['standard_id'] ?>" <?= $s['standard_id'] == $standardId ? 'selected' : '' ?>><?= h($s['name']) ?> (<?= h($s['medium']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Subject</label>
      <select name="subject_id" class="form-select" onchange="this.form.chapter_id.value='';this.form.submit()" <?= $subjects ? '' : 'disabled' ?>>
        <option value="">-- subject --</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= $s['subject_id'] ?>" <?= $s['subject_id'] == $subjectId ? 'selected' : '' ?>><?= h($s['name']) ?> (<?= $s['question_count'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small">Chapter</label>
      <select name="chapter_id" class="form-select" <?= $chapters ? '' : 'disabled' ?>>
        <option value="">-- all chapters --</option>
        <?php foreach ($chapters as $c): ?>
          <option value="<?= $c['chapter_id'] ?>" <?= $c['chapter_id'] == $chapterId ? 'selected' : '' ?>><?= h($c['name']) ?> (<?= $c['question_count'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Type</label>
      <select name="type" class="form-select">
        <option value="">-- all --</option>
        <?php foreach (['mcq' => 'MCQ', 'fillinblanks' => 'Fill in the blanks', 'descriptive' => 'Descriptive'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Search</label>
      <input name="q" class="form-control" value="<?= h($search) ?>" placeholder="text...">
    </div>
    <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
    <div class="col-12">
      <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="images" value="1" id="onlyImg" <?= $onlyImages ? 'checked' : '' ?>><label class="form-check-label small" for="onlyImg">Only questions with images</label></div>
    </div>
  </div>
</form>

<?php if ($subjectId): ?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <span class="text-muted"><?= number_format($total) ?> questions</span>
  <nav><ul class="pagination pagination-sm mb-0">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($qs(['page' => $page - 1])) ?>">&laquo;</a></li>
    <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $pages ?></span></li>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($qs(['page' => $page + 1])) ?>">&raquo;</a></li>
  </ul></nav>
</div>
<div class="table-responsive card shadow-sm">
<table class="table table-sm align-middle mb-0">
  <thead class="table-light"><tr><th style="width:70px">ID</th><th>Question</th><th style="width:28%">Answer</th><th>Chapter</th><th>Type</th><th>Q.No</th><th>Marks</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $q): ?>
    <tr>
      <td class="text-muted small"><?= $q['question_id'] ?></td>
      <td>
        <?php if ($q['passage']): ?><div class="fst-italic small text-muted"><?= ep_markup($q['passage']) ?></div><?php endif; ?>
        <?= ep_markup($q['markup']) ?>
        <?php if ($q['image_url']): ?><div><a href="<?= h($q['image_url']) ?>" target="_blank"><img src="<?= h($q['image_url']) ?>" class="ep-qimg mt-1"></a></div><?php endif; ?>
        <?php if ($q['markup2']): ?><div class="small"><?= ep_markup($q['markup2']) ?></div><?php endif; ?>
        <?php if ($q['image2_url']): ?><div><a href="<?= h($q['image2_url']) ?>" target="_blank"><img src="<?= h($q['image2_url']) ?>" class="ep-qimg mt-1"></a></div><?php endif; ?>
        <?php if ($q['markup3']): ?><div class="small"><?= ep_markup($q['markup3']) ?></div><?php endif; ?>
        <?php foreach ($q['sub_questions'] as $i => $sq): ?>
          <div class="ms-3 small"><?= $i + 1 ?>) <?= ep_markup($sq['markup']) ?>
            <?php if (!empty($sq['image_url'])): ?><div><img src="<?= h($sq['image_url']) ?>" class="ep-qimg mt-1"></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </td>
      <td class="small text-success">
        <?= ep_markup($q['answer_markup']) ?>
        <?php if ($q['answer_image_url']): ?><div><a href="<?= h($q['answer_image_url']) ?>" target="_blank"><img src="<?= h($q['answer_image_url']) ?>" class="ep-qimg mt-1"></a></div><?php endif; ?>
      </td>
      <td class="small"><?= h($q['chapter_name']) ?></td>
      <td><span class="badge text-bg-secondary"><?= h($q['question_type']) ?></span></td>
      <td><?= h($q['question_number']) ?></td>
      <td><?= (int)$q['marks'] ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No questions found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="alert alert-info">Select a class and subject to browse questions.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
