<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/assessment_patterns.php';

$editPaper = null;
if (!empty($_GET['edit'])) {
    $editPaper = ep_paper((int)$_GET['edit']);
    if ($editPaper && $editPaper['paper_type'] !== 'assessment') {
        header('Location: ' . ($editPaper['paper_type'] === 'competitive' ? 'competitive_paper.php' : 'create_paper.php') . '?edit=' . (int)$editPaper['paper_id']);
        exit;
    }
    if ($editPaper) {
        unset($editPaper['paper_json']);
    }
}

$modelCounts = ep_db()->query('SELECT exam_type, COUNT(*) n FROM ep_paper_models GROUP BY exam_type')->fetchAll(PDO::FETCH_KEY_PAIR);

$epPage = 'assessment';
$epTitle = $editPaper ? 'Edit संकलित / आकारिक Paper' : 'संकलित / आकारिक मूल्यमापन चाचणी';
$epScripts = ['assessment_paper.js'];
require __DIR__ . '/includes/header.php';
?>
<div id="epApp"
     data-tree='<?= h(json_encode(ep_book_tree(), JSON_UNESCAPED_UNICODE)) ?>'
     data-edit='<?= $editPaper ? h(json_encode($editPaper, JSON_UNESCAPED_UNICODE)) : '' ?>'>
<h3 class="mb-1"><?= $editPaper ? 'Edit' : '' ?> संकलित / आकारिक मूल्यमापन चाचणी <small class="text-muted fs-6">Summative / Formative test from textbook exercises</small></h3>
<p class="text-muted small mb-3">Choose test type, class and textbook subject, tick the chapters covered (संकलित १ = first half, संकलित २ = second half by default), then <b>Auto-fill</b> every section with स्वाध्याय / Exercise questions from the Balbharati textbook. You can also load the layout of a real paper (<?= (int)($modelCounts['sankalit'] ?? 0) ?> संकलित, <?= (int)($modelCounts['aakarik'] ?? 0) ?> आकारिक samples) and edit any question text before saving. The <b>उत्तर-लेखन</b> format prints dotted answer lines / working space under every question (chosen automatically from the question type; change it per section with the <i class="bi bi-pencil-square"></i> answer-space box) and a तोंडी block for sections marked oral.</p>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">1</span> Test, Class &amp; Subject</div>
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col-7">
            <label class="form-label small mb-1">Test type</label>
            <select class="form-select" id="examType">
              <option value="sankalit">संकलित मूल्यमापन (Summative)</option>
              <option value="aakarik">आकारिक मूल्यमापन (Formative)</option>
            </select>
          </div>
          <div class="col-5">
            <label class="form-label small mb-1">Test no.</label>
            <select class="form-select" id="testNo"><option value="1">चाचणी १</option><option value="2">चाचणी २</option></select>
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-4"><label class="form-label small mb-1">Class</label><select class="form-select" id="standard"></select></div>
          <div class="col-8"><label class="form-label small mb-1">Subject (textbook)</label><select class="form-select" id="subject"></select></div>
        </div>
        <label class="form-label small mb-1">Question source</label>
        <select class="form-select mb-2" id="qSource" title="स्वाध्याय = textbook exercise questions; AI set = MiniShala-style typed practice questions generated per chapter (with answers)">
          <option value="">Both — स्वाध्याय + AI सराव प्रश्नसंच</option>
          <option value="book">स्वाध्याय only (textbook exercises)</option>
          <option value="typed">AI सराव प्रश्नसंच only (typed set: रिकाम्या जागा / एका शब्दात / कारण / आकृती …)</option>
        </select>
        <label class="form-label small mb-1 d-flex justify-content-between">Chapters covered <span><a href="#" id="chAll" class="small">all</a> &middot; <a href="#" id="chFirst" class="small">1st half</a> &middot; <a href="#" id="chSecond" class="small">2nd half</a> &middot; <a href="#" id="chNone" class="small">none</a></span></label>
        <div id="chapters" class="border rounded p-2 mb-2 bg-white" style="max-height:220px;overflow:auto"></div>
        <label class="form-label small mb-1">Paper layout</label>
        <select class="form-select mb-2" id="layout"><option value="default">Standard layout (auto by subject)</option></select>
        <div class="d-grid gap-2">
          <button class="btn btn-outline-primary btn-sm" id="loadLayout"><i class="bi bi-layout-text-window"></i> Load layout (replace sections)</button>
          <button class="btn btn-success" id="autoFill"><i class="bi bi-magic"></i> Auto-fill all sections from question bank</button>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">2</span> Paper Details</div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label small mb-1">Paper title</label><input class="form-control" id="title" placeholder="संकलित मूल्यमापन चाचणी - १"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Std. label</label><input class="form-control" id="stdLabel" placeholder="इयत्ता - पाचवी"></div>
          <div class="col-6"><label class="form-label small mb-1">Time</label><input class="form-control" id="duration" placeholder="२ तास"></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Exam date</label><input type="date" class="form-control" id="examDate"></div>
          <div class="col-6"><label class="form-label small mb-1">Total marks</label><input type="number" step="0.5" class="form-control" id="totalMarks" placeholder="auto"></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-7"><label class="form-label small mb-1">Paper format</label>
            <select class="form-select" id="paperFormat">
              <option value="lines">उत्तर-लेखन ओळींसह (answer lines &amp; space, minishala style)</option>
              <option value="standard">Compact — questions only</option>
            </select></div>
          <div class="col-5"><label class="form-label small mb-1">तोंडी गुण (oral)</label><input type="number" step="0.5" min="0" class="form-control" id="oralMarks" placeholder="0"></div>
        </div>
        <div class="mb-2"><label class="form-label small mb-1">Instructions (one per line)</label><textarea class="form-control" id="instructions" rows="2"></textarea></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="studentFields" checked><label class="form-check-label small" for="studentFields">Print विद्यार्थ्याचे नाव / हजेरी क्र. / गुण lines</label></div>
        <div class="d-grid"><button class="btn btn-primary btn-lg" id="savePaper"><i class="bi bi-save"></i> Save &amp; Preview</button></div>
        <div id="saveMsg" class="small mt-2"></div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-semibold">Questions <span class="text-muted small" id="marksInfo"></span></div>
      <button class="btn btn-outline-primary btn-sm" id="addSection"><i class="bi bi-plus"></i> Add question section</button>
    </div>
    <div id="sections"></div>
    <div id="emptyHint" class="alert alert-secondary">Pick class + subject, then click <b>Load layout</b>. Each section (प्र.१ अ, प्र.१ ब, ...) has an instruction, marks and its questions; use <b>Auto-fill</b> to pull matching exercise questions, or type your own.</div>
  </div>
</div>
</div>

<div class="modal fade" id="browseModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Choose textbook questions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-3">
      <div class="col-md-3"><select class="form-select" id="browseType"><option value="">All types</option></select></div>
      <div class="col-md-9"><div class="input-group"><input class="form-control" id="browseSearch" placeholder="Search question text..."><button class="btn btn-outline-secondary" id="browseGo"><i class="bi bi-search"></i></button></div></div>
    </div>
    <div id="browseList"></div>
    <div class="d-flex justify-content-between mt-2"><button class="btn btn-sm btn-outline-secondary" id="browsePrev">&laquo; Prev</button><span id="browseInfo" class="small text-muted"></span><button class="btn btn-sm btn-outline-secondary" id="browseNext">Next &raquo;</button></div>
  </div>
</div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
