<?php
/**
 * Edit the chapter / lesson list of one textbook (names + PDF page ranges).
 * Exercise questions are re-linked to chapters by page after saving, so the
 * homework / paper builders show the corrected lesson names immediately.
 */
require_once __DIR__ . '/includes/functions.php';
$epPage = 'bookbank';
$epTitle = 'Edit Chapters';

$db = ep_db();
$bookId = (int)($_GET['book_id'] ?? $_POST['book_id'] ?? 0);
$st = $db->prepare('SELECT * FROM ep_books WHERE book_id = ?');
$st->execute([$bookId]);
$book = $st->fetch();
if (!$book) {
    http_response_code(404);
    exit('Book not found');
}
$maxPage = max(1, (int)$book['pages']);
$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['chapter_id'] ?? [];
    $titles = $_POST['title'] ?? [];
    $starts = $_POST['start_page'] ?? [];
    $ends = $_POST['end_page'] ?? [];
    $rows = [];
    foreach ($titles as $i => $title) {
        $title = trim((string)$title);
        $s = (int)($starts[$i] ?? 0);
        $e = (int)($ends[$i] ?? 0);
        if ($title === '' && $s === 0 && $e === 0) {
            continue;
        }
        if ($title === '') {
            $errors[] = 'Row ' . ($i + 1) . ': name is empty.';
        }
        if ($s < 1 || $e < $s || $e > $maxPage) {
            $errors[] = 'Row ' . ($i + 1) . " ($title): pages must be between 1 and $maxPage and start ≤ end.";
        }
        $rows[] = ['id' => (int)($ids[$i] ?? 0), 'title' => mb_substr($title, 0, 255), 'start' => $s, 'end' => $e];
    }
    if (!$rows) {
        $errors[] = 'At least one chapter is required.';
    }
    if (!$errors) {
        usort($rows, fn($a, $b) => $a['start'] <=> $b['start']);
        $db->beginTransaction();
        try {
            $keep = [];
            $upd = $db->prepare('UPDATE ep_book_chapters SET chapter_no = ?, title = ?, start_page = ?, end_page = ? WHERE chapter_id = ? AND book_id = ?');
            $ins = $db->prepare('INSERT INTO ep_book_chapters (book_id, chapter_no, title, start_page, end_page) VALUES (?,?,?,?,?)');
            foreach ($rows as $n => $r) {
                if ($r['id']) {
                    $upd->execute([$n + 1, $r['title'], $r['start'], $r['end'], $r['id'], $bookId]);
                    $keep[] = $r['id'];
                } else {
                    $ins->execute([$bookId, $n + 1, $r['title'], $r['start'], $r['end']]);
                    $keep[] = (int)$db->lastInsertId();
                }
            }
            $ph = implode(',', array_fill(0, count($keep), '?'));
            $del = $db->prepare("DELETE FROM ep_book_chapters WHERE book_id = ? AND chapter_id NOT IN ($ph)");
            $del->execute(array_merge([$bookId], $keep));
            // re-link every question of this book to the chapter whose page range contains it
            $db->prepare('UPDATE ep_book_questions q
                          JOIN ep_book_chapters c ON c.book_id = q.book_id AND q.page BETWEEN c.start_page AND c.end_page
                          SET q.chapter_id = c.chapter_id WHERE q.book_id = ?')->execute([$bookId]);
            $db->prepare('UPDATE ep_book_questions SET chapter_id = NULL WHERE book_id = ? AND chapter_id NOT IN (' . $ph . ')')
               ->execute(array_merge([$bookId], $keep));
            $db->commit();
            $saved = true;
        } catch (Throwable $e) {
            $db->rollBack();
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }
}

$st = $db->prepare('SELECT c.*, (SELECT COUNT(*) FROM ep_book_questions q WHERE q.chapter_id = c.chapter_id) n
                    FROM ep_book_chapters c WHERE book_id = ? ORDER BY chapter_no');
$st->execute([$bookId]);
$chapters = $st->fetchAll();
$st = $db->prepare('SELECT COUNT(*) FROM ep_book_questions WHERE book_id = ? AND chapter_id IS NULL');
$st->execute([$bookId]);
$unlinked = (int)$st->fetchColumn();

require __DIR__ . '/includes/header.php';
?>
<h3 class="mb-1">Chapters / Lessons <small class="text-muted fs-6">Std <?= (int)$book['standard'] ?> · <?= h($book['subject']) ?> (<?= h($book['medium']) ?>)</small></h3>
<p class="text-muted small">
  Rename lessons, fix page ranges, add missing lessons (e.g. units the OCR could not read) or split a "Lesson 1 (pp. 1-16)" block into real lesson names.
  Pages are <b>PDF page numbers</b> (1–<?= $maxPage ?>) — open a page image with the <i class="bi bi-image"></i> link to check.
  After saving, exercise questions are re-assigned to lessons by page, and the new names appear in the Homework / Paper builders.
</p>

<?php if ($saved): ?><div class="alert alert-success py-2">Saved. <?= count($chapters) ?> chapters; questions re-linked.<?= $unlinked ? " <b>$unlinked</b> questions fall outside all page ranges and are not shown under any chapter." : '' ?></div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" id="chForm" class="card shadow-sm">
  <input type="hidden" name="book_id" value="<?= $bookId ?>">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" id="chTable">
      <thead class="table-light"><tr><th style="width:3rem">#</th><th>Lesson / chapter name</th><th style="width:7rem">From page</th><th style="width:7rem">To page</th><th style="width:5rem" class="text-end">Q</th><th style="width:6rem"></th></tr></thead>
      <tbody>
      <?php foreach ($chapters as $i => $c): ?>
        <tr>
          <td class="text-muted row-no"><?= $i + 1 ?></td>
          <td><input type="hidden" name="chapter_id[]" value="<?= $c['chapter_id'] ?>"><input class="form-control form-control-sm" name="title[]" value="<?= h($c['title']) ?>" required></td>
          <td><input class="form-control form-control-sm" type="number" min="1" max="<?= $maxPage ?>" name="start_page[]" value="<?= (int)$c['start_page'] ?>"></td>
          <td><input class="form-control form-control-sm" type="number" min="1" max="<?= $maxPage ?>" name="end_page[]" value="<?= (int)$c['end_page'] ?>"></td>
          <td class="text-end small text-muted"><?= $c['n'] ?></td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" title="View first page" target="_blank" href="<?= EP_BASE_URL ?>/book_bank.php?standard=<?= (int)$book['standard'] ?>&book_id=<?= $bookId ?>&chapter_id=<?= $c['chapter_id'] ?>"><i class="bi bi-list-ul"></i></a>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)" title="Remove"><i class="bi bi-x"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-body d-flex gap-2 align-items-center">
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()"><i class="bi bi-plus"></i> Add lesson</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillEnds()" title="Set each 'To page' to the page before the next lesson starts"><i class="bi bi-arrows-collapse"></i> Auto-fill "To page"</button>
    <span class="flex-grow-1"></span>
    <a class="btn btn-light btn-sm" href="<?= EP_BASE_URL ?>/book_bank.php?standard=<?= (int)$book['standard'] ?>&book_id=<?= $bookId ?>">Back to bank</a>
    <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save chapters</button>
  </div>
</form>

<template id="rowTpl">
  <tr>
    <td class="text-muted row-no"></td>
    <td><input type="hidden" name="chapter_id[]" value="0"><input class="form-control form-control-sm" name="title[]" placeholder="e.g. 3.1 The Brave Little Girl" required></td>
    <td><input class="form-control form-control-sm" type="number" min="1" max="<?= $maxPage ?>" name="start_page[]"></td>
    <td><input class="form-control form-control-sm" type="number" min="1" max="<?= $maxPage ?>" name="end_page[]"></td>
    <td class="text-end small text-muted">–</td>
    <td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)" title="Remove"><i class="bi bi-x"></i></button></td>
  </tr>
</template>
<script>
const tbody = document.querySelector('#chTable tbody');
function renumber() { tbody.querySelectorAll('.row-no').forEach((td, i) => td.textContent = i + 1); }
function addRow() {
  const tr = document.getElementById('rowTpl').content.firstElementChild.cloneNode(true);
  const last = tbody.lastElementChild;
  if (last) tr.querySelector('[name="start_page[]"]').value = (parseInt(last.querySelector('[name="end_page[]"]').value) || 0) + 1;
  tbody.appendChild(tr); renumber(); tr.querySelector('[name="title[]"]').focus();
}
function delRow(btn) { if (tbody.children.length > 1) { btn.closest('tr').remove(); renumber(); } }
function fillEnds() {
  const rows = [...tbody.children].sort((a, b) => (+a.querySelector('[name="start_page[]"]').value || 0) - (+b.querySelector('[name="start_page[]"]').value || 0));
  rows.forEach((tr, i) => {
    const next = rows[i + 1] ? +rows[i + 1].querySelector('[name="start_page[]"]').value : <?= $maxPage + 1 ?>;
    tr.querySelector('[name="end_page[]"]').value = Math.max(+tr.querySelector('[name="start_page[]"]').value || 1, next - 1);
    tbody.appendChild(tr);
  });
  renumber();
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
