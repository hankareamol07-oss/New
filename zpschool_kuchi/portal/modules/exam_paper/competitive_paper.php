<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/patterns.php';
$access = require_module_access('que');
$canCreate = ep_can_create_paper();
if (!$canCreate['allowed']) {
    header('Location: index.php');
    exit;
}

$editPaper = null;
if (!empty($_GET['edit'])) {
    $editPaper = ep_paper((int)$_GET['edit']);
    if ($editPaper && $editPaper['paper_type'] !== 'competitive') {
        header('Location: create_paper.php?edit=' . (int)$editPaper['paper_id']);
        exit;
    }
    if ($editPaper) {
        $allIds = [];
        foreach ($editPaper['sections'] as $s) {
            $allIds = array_merge($allIds, $s['question_ids']);
        }
        $editPaper['questions'] = ep_questions_by_ids($allIds);
        unset($editPaper['paper_json']);
    }
}
$patterns = ep_exam_patterns();
$groups = [];
foreach ($patterns as $key => $p) {
    $groups[$p['group']][$key] = $p['name'];
}

$epPage = 'competitive';
$epTitle = $editPaper ? 'Edit Competitive Paper' : 'Competitive Exam Paper';
$epScripts = ['competitive_paper.js'];
require __DIR__ . '/includes/header.php';
?>
<div id="epApp"
     data-patterns='<?= h(json_encode($patterns, JSON_UNESCAPED_UNICODE)) ?>'
     data-tree='<?= h(json_encode(ep_competitive_tree(), JSON_UNESCAPED_UNICODE)) ?>'
     data-edit='<?= $editPaper ? h(json_encode($editPaper, JSON_UNESCAPED_UNICODE)) : '' ?>'>
<h3 class="mb-1"><?= $editPaper ? 'Edit Competitive Paper' : 'Competitive Exam Paper' ?> <small class="text-muted fs-6">Scholarship &middot; Navodaya (JNVST) &middot; NMMS</small></h3>
<p class="text-muted small mb-3">Pick an exam pattern (or Topic-wise practice), choose the source class / subject / topics for each section, then auto-fill with MCQs. Questions are numbered continuously and every question carries the same marks (e.g. 1.25 for JNVST).</p>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header p-1 bg-light">
        <ul class="nav nav-pills nav-fill small" id="modeTabs">
          <li class="nav-item">
            <button class="nav-link active py-2 fw-semibold" id="tabSingle" data-bs-toggle="pill" data-bs-target="#modeSingle" type="button"><i class="bi bi-bullseye text-danger"></i> एका घटकाची चाचणी<br><span class="text-muted" style="font-size:10.5px;">Single Topic Paper</span></button>
          </li>
          <li class="nav-item">
            <button class="nav-link py-2 fw-semibold" id="tabPattern" data-bs-toggle="pill" data-bs-target="#modePattern" type="button"><i class="bi bi-collection text-primary"></i> संपूर्ण परीक्षा पॅटर्न<br><span class="text-muted" style="font-size:10.5px;">Full Exam Pattern</span></button>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <div class="tab-content">
          <!-- TAB 1: Single Topic Paper -->
          <div class="tab-pane fade show active" id="modeSingle">
            <div class="mb-2">
              <label class="form-label small fw-bold mb-1">१. परीक्षा / वर्ग (Exam)</label>
              <select class="form-select form-select-sm" id="singleStd"></select>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold mb-1">२. विषय (Subject)</label>
              <select class="form-select form-select-sm" id="singleSub"></select>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-bold mb-1">३. घटक / पाठ (Topic / Chapter)</label>
              <select class="form-select form-select-sm" id="singleChapter"></select>
              <div id="singleTopicHint" class="form-text small text-muted"></div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-7">
                <label class="form-label small fw-bold mb-1">४. प्रश्न संख्या (Questions)</label>
                <div class="input-group input-group-sm">
                  <input type="number" class="form-control" id="singleCount" value="20" min="1" max="100">
                  <span class="input-group-text">प्रश्न</span>
                </div>
              </div>
              <div class="col-5">
                <label class="form-label small fw-bold mb-1">गुण/प्रश्न (Marks/Q)</label>
                <input type="number" step="0.25" class="form-control form-control-sm" id="singleMarksPerQ" value="2">
              </div>
            </div>
            <div class="d-flex gap-1 mb-3 flex-wrap">
              <span class="small text-muted align-self-center me-1">द्रुत संख्या:</span>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 btn-qcnt" data-cnt="10">10</button>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 btn-qcnt" data-cnt="15">15</button>
              <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 active btn-qcnt" data-cnt="20">20</button>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 btn-qcnt" data-cnt="25">25</button>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 btn-qcnt" data-cnt="30">30</button>
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 btn-qcnt" data-cnt="50">50</button>
            </div>
            <button class="btn btn-success w-100 fw-bold shadow-sm py-2" id="btnGenerateTopic"><i class="bi bi-lightning-charge-fill"></i> घटक चाचणी तयार करा (Generate)</button>
          </div>

          <!-- TAB 2: Full Exam Pattern -->
          <div class="tab-pane fade" id="modePattern">
            <label class="form-label small fw-bold mb-1">Exam pattern</label>
            <select class="form-select mb-2" id="pattern">
              <option value="">-- choose exam pattern --</option>
              <?php foreach ($groups as $g => $items): ?>
                <optgroup label="<?= h($g) ?>">
                <?php foreach ($items as $k => $n): ?><option value="<?= h($k) ?>"><?= h($n) ?></option><?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <div class="d-grid gap-2">
              <button class="btn btn-outline-primary btn-sm" id="addSection"><i class="bi bi-plus"></i> Add section</button>
              <button class="btn btn-success" id="autoFill"><i class="bi bi-magic"></i> Auto-fill all sections</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">2</span> Paper Details</div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label small mb-1">Exam name (printed as Sub.)</label><input class="form-control" id="examName" placeholder="e.g. JNV (Entrance Exam)"></div>
        <div class="mb-2"><label class="form-label small mb-1">Paper title</label><input class="form-control" id="title" placeholder="e.g. Chapterwise - Question Paper - 1"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Std. label</label><input class="form-control" id="stdLabel" placeholder="e.g. 5th"></div>
          <div class="col-6"><label class="form-label small mb-1">Time</label><input class="form-control" id="duration" placeholder="e.g. 1:30 Hrs"></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Exam date</label><input type="date" class="form-control" id="examDate"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Total marks</label><input type="number" step="0.25" class="form-control" id="totalMarks" placeholder="auto"></div>
          <div class="col-6">
            <label class="form-label small mb-1">Format (स्वरूप)</label>
            <select class="form-select" id="paperFormat">
              <option value="standard" selected>केवळ प्रश्नपत्रिका (Compact)</option>
              <option value="lines">सोडवण्यासाठी ओळी (Rough Lines)</option>
            </select>
          </div>
        </div>
        <div class="mb-3"><label class="form-label small mb-1">Instructions</label><textarea class="form-control" id="instructions" rows="2" placeholder="One per line"></textarea></div>
        <div class="d-grid gap-2">
          <button class="btn btn-primary btn-lg" id="savePaper"><i class="bi bi-save"></i> Save &amp; Preview Paper</button>
          <button class="btn btn-outline-success" id="saveAndQuiz"><i class="bi bi-phone-fill me-1"></i> सेव्ह करा व ऑनलाईन क्विझ सुरू करा</button>
        </div>
        <div id="saveMsg" class="small mt-2"></div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div id="sections"></div>
    <div id="emptyHint" class="alert alert-secondary">Choose an exam pattern to load its sections (e.g. JNVST: मानसिक क्षमता 20 Q + अंकगणित 10 Q + भाषा 10 Q, 1.25 marks each). For each section pick the source class, subject and topics, then click <b>Auto-fill</b>.</div>
  </div>
</div>
</div>

<div class="modal fade" id="browseModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Choose a question</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="input-group mb-3"><input class="form-control" id="browseSearch" placeholder="Search question text..."><button class="btn btn-outline-secondary" id="browseGo"><i class="bi bi-search"></i></button></div>
    <div id="browseList"></div>
    <div class="d-flex justify-content-between mt-2"><button class="btn btn-sm btn-outline-secondary" id="browsePrev">&laquo; Prev</button><span id="browseInfo" class="small text-muted"></span><button class="btn btn-sm btn-outline-secondary" id="browseNext">Next &raquo;</button></div>
  </div>
</div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
