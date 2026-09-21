<?php
require_once __DIR__ . '/includes/functions.php';
$epPage = 'bookbank';
$epTitle = 'Textbook Exercise Bank';

$standard = (int)($_GET['standard'] ?? 0);
$bookId = (int)($_GET['book_id'] ?? 0);
$chapterId = (int)($_GET['chapter_id'] ?? 0);
$qtype = trim($_GET['qtype'] ?? '');
$search = trim($_GET['q'] ?? '');
$onlyFigures = !empty($_GET['figures']);
$page = max(1, (int)($_GET['page'] ?? 1));
$size = 25;

$db = ep_db();
$standards = $db->query('SELECT DISTINCT standard FROM ep_books ORDER BY standard')->fetchAll(PDO::FETCH_COLUMN);
$books = $standard ? $db->prepare('SELECT b.*, (SELECT COUNT(*) FROM ep_book_questions q WHERE q.book_id=b.book_id) n FROM ep_books b WHERE standard = ? ORDER BY subject, medium') : null;
if ($books) { $books->execute([$standard]); $books = $books->fetchAll(); }
$chapters = [];
if ($bookId) {
    $st = $db->prepare('SELECT c.*, (SELECT COUNT(*) FROM ep_book_questions q WHERE q.chapter_id=c.chapter_id) n FROM ep_book_chapters c WHERE book_id = ? ORDER BY chapter_no');
    $st->execute([$bookId]);
    $chapters = $st->fetchAll();
}
$qtypes = $db->query('SELECT qtype, COUNT(*) n FROM ep_book_questions GROUP BY qtype ORDER BY n DESC')->fetchAll();

$rows = [];
$total = 0;
if ($bookId || $search) {
    $where = [];
    $params = [];
    if ($bookId) { $where[] = 'q.book_id = ?'; $params[] = $bookId; }
    elseif ($standard) { $where[] = 'q.standard = ?'; $params[] = $standard; }
    if ($chapterId) { $where[] = 'q.chapter_id = ?'; $params[] = $chapterId; }
    if ($qtype) { $where[] = 'q.qtype = ?'; $params[] = $qtype; }
    if ($search) { $where[] = '(q.text LIKE ? OR q.instruction LIKE ?)'; array_push($params, "%$search%", "%$search%"); }
    if ($onlyFigures) { $where[] = 'q.needs_figure = 1'; }
    $sql = 'FROM ep_book_questions q LEFT JOIN ep_book_chapters c ON c.chapter_id = q.chapter_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $st = $db->prepare("SELECT COUNT(*) $sql");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $st = $db->prepare("SELECT q.*, c.title chapter_title, c.chapter_no $sql ORDER BY q.book_id, q.page, q.bq_id LIMIT $size OFFSET " . (($page - 1) * $size));
    $st->execute($params);
    $rows = array_map('ep_decode_book_question', $st->fetchAll());
}
$pages = max(1, (int)ceil($total / $size));
$qs = fn(array $o = []) => '?' . http_build_query(array_merge($_GET, $o));

require __DIR__ . '/includes/header.php';
?>
<h3 class="mb-1">Textbook Exercise Bank <small class="text-muted fs-6">Balbharati स्वाध्याय / Exercise questions (OCR)</small></h3>
<p class="text-muted small">Questions extracted from the textbook exercises below each chapter. Every question keeps its textbook page number; picture / figure questions carry a page image URL. OCR text may contain small errors — you can edit any question while building a paper.</p>
<form class="card shadow-sm mb-3" method="get">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-2">
      <label class="form-label small">Class</label>
      <select name="standard" class="form-select" onchange="this.form.book_id.value='';this.form.chapter_id.value='';this.form.submit()">
        <option value="">-- class --</option>
        <?php foreach ($standards as $s): ?><option value="<?= $s ?>" <?= $s == $standard ? 'selected' : '' ?>>Std <?= $s ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small">Textbook</label>
      <select name="book_id" class="form-select" onchange="this.form.chapter_id.value='';this.form.submit()" <?= $books ? '' : 'disabled' ?>>
        <option value="">-- all subjects --</option>
        <?php foreach ($books ?: [] as $b): ?><option value="<?= $b['book_id'] ?>" <?= $b['book_id'] == $bookId ? 'selected' : '' ?>><?= h($b['subject']) ?> (<?= h($b['medium']) ?>) — <?= $b['n'] ?> Q</option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small">Chapter</label>
      <select name="chapter_id" class="form-select" <?= $chapters ? '' : 'disabled' ?>>
        <option value="">-- all chapters --</option>
        <?php foreach ($chapters as $c): ?><option value="<?= $c['chapter_id'] ?>" <?= $c['chapter_id'] == $chapterId ? 'selected' : '' ?>><?= $c['chapter_no'] ?>. <?= h($c['title']) ?> (<?= $c['n'] ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Type</label>
      <select name="qtype" class="form-select">
        <option value="">-- all --</option>
        <?php foreach ($qtypes as $t): ?><option value="<?= h($t['qtype']) ?>" <?= $qtype === $t['qtype'] ? 'selected' : '' ?>><?= h($t['qtype']) ?> (<?= $t['n'] ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Search</label>
      <div class="input-group"><input name="q" class="form-control" value="<?= h($search) ?>" placeholder="text..."><button class="btn btn-primary">Go</button></div>
    </div>
    <div class="col-12">
      <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="figures" value="1" id="onlyFig" <?= $onlyFigures ? 'checked' : '' ?>><label class="form-check-label small" for="onlyFig">Only picture / figure questions</label></div>
    </div>
  </div>
</form>

<?php if ($bookId || $search): ?>
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
  <thead class="table-light"><tr><th style="width:70px">ID</th><th>Question</th><th style="width:22%">Exercise / instruction</th><th>Chapter</th><th>Type</th><th>Page</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $q): ?>
    <tr>
      <td class="text-muted small"><?= $q['bq_id'] ?></td>
      <td><?= ep_markup($q['text']) ?>
        <?php if ($q['page_image_url']): ?><div class="small mt-1"><a href="<?= h($q['page_image_url']) ?>" target="_blank"><i class="bi bi-image"></i> page image</a></div><?php endif; ?>
      </td>
      <td class="small text-muted"><?= h($q['block']) ?><?= $q['instruction'] ? ' — ' . h($q['instruction']) : '' ?></td>
      <td class="small"><?= $q['chapter_no'] ? $q['chapter_no'] . '. ' : '' ?><?= h($q['chapter_title'] ?? '') ?></td>
      <td><span class="badge text-bg-secondary"><?= h($q['qtype']) ?></span></td>
      <td class="small"><?= (int)$q['page'] ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No questions found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="alert alert-info">Select a class and textbook to browse exercise questions.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
