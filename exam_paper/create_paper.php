<?php
require_once __DIR__ . '/includes/functions.php';

$editPaper = null;
if (!empty($_GET['edit'])) {
    $editPaper = ep_paper((int)$_GET['edit']);
    if ($editPaper) {
        $allIds = [];
        foreach ($editPaper['sections'] as $s) {
            $allIds = array_merge($allIds, $s['question_ids']);
        }
        $editPaper['questions'] = ep_questions_by_ids($allIds);
        unset($editPaper['paper_json']);
    }
}
$standards = ep_standards();

$epPage = 'create';
$epTitle = $editPaper ? 'Edit Paper' : 'Create Paper';
$epScripts = ['create_paper.js'];
require __DIR__ . '/includes/header.php';
?>
<div id="epApp" data-edit='<?= $editPaper ? h(json_encode($editPaper, JSON_UNESCAPED_UNICODE)) : '' ?>'>
<h3 class="mb-3"><?= $editPaper ? 'Edit Question Paper' : 'Create Question Paper' ?></h3>

<div class="row g-4">
  <!-- LEFT: setup -->
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">1</span> Class &amp; Subject</div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label small mb-1">Class / Standard</label>
          <select class="form-select" id="standard">
            <option value="">-- select class --</option>
            <?php foreach ($standards as $s): ?>
              <option value="<?= (int)$s['standard_id'] ?>"><?= h($s['name']) ?> (<?= h($s['medium']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Subject</label>
          <select class="form-select" id="subject" disabled><option value="">-- select subject --</option></select>
        </div>
        <div>
          <label class="form-label small mb-1 d-flex justify-content-between">Chapters
            <span><a href="#" id="chAll" class="small">all</a> / <a href="#" id="chNone" class="small">none</a></span>
          </label>
          <div id="chapters" class="border rounded p-2 bg-white" style="max-height:260px;overflow:auto"><span class="text-muted small">Select a subject first</span></div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">2</span> Paper Pattern</div>
      <div class="card-body">
        <label class="form-label small mb-1">Ready template</label>
        <select class="form-select mb-2" id="template" disabled><option value="">-- choose a template --</option></select>
        <div class="d-grid gap-2">
          <button class="btn btn-outline-primary btn-sm" id="addSection"><i class="bi bi-plus"></i> Add custom section</button>
          <button class="btn btn-success" id="autoFill"><i class="bi bi-magic"></i> Auto-fill all sections</button>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">3</span> Paper Details</div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label small mb-1">Paper Title</label><input class="form-control" id="title" placeholder="e.g. First Unit Test"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Exam Date</label><input type="date" class="form-control" id="examDate"></div>
          <div class="col-6"><label class="form-label small mb-1">Duration</label><input class="form-control" id="duration" placeholder="e.g. 1 Hour"></div>
        </div>
        <div class="mb-2"><label class="form-label small mb-1">Total Marks</label><input type="number" class="form-control" id="totalMarks" placeholder="auto"></div>
        <div class="mb-3"><label class="form-label small mb-1">Instructions</label><textarea class="form-control" id="instructions" rows="2" placeholder="One per line"></textarea></div>
        <div class="d-grid gap-2">
          <button class="btn btn-primary btn-lg" id="savePaper"><i class="bi bi-save"></i> Save &amp; Preview</button>
        </div>
        <div id="saveMsg" class="small mt-2"></div>
      </div>
    </div>
  </div>

  <!-- RIGHT: sections -->
  <div class="col-lg-8">
    <div id="sections"></div>
    <div id="emptyHint" class="alert alert-secondary">Choose a class, subject and chapters, then pick a ready template or add custom sections. Use <b>Auto-fill</b> to randomly pick questions, then swap any question you don't like.</div>
  </div>
</div>
</div>

<!-- Browse / replace modal -->
<div class="modal fade" id="browseModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Choose a question</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="input-group mb-3"><input class="form-control" id="browseSearch" placeholder="Search question text..."><button class="btn btn-outline-secondary" id="browseGo"><i class="bi bi-search"></i></button></div>
    <div id="browseList"></div>
    <div class="d-flex justify-content-between mt-2"><button class="btn btn-sm btn-outline-secondary" id="browsePrev">&laquo; Prev</button><span id="browseInfo" class="small text-muted"></span><button class="btn btn-sm btn-outline-secondary" id="browseNext">Next &raquo;</button></div>
  </div>
</div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
