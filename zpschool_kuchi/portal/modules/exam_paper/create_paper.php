<?php
require_once __DIR__ . '/includes/functions.php';
$access = require_module_access('que');
$canCreate = ep_can_create_paper();
if (!$canCreate['allowed']) {
    header('Location: index.php');
    exit;
}

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
$regularStandards = [];
$competitiveStandards = [];
foreach ($standards as $s) {
    if (ep_is_competitive_standard($s)) {
        $competitiveStandards[] = $s;
    } else {
        $regularStandards[] = $s;
    }
}

$initialParams = [
    'standard_id' => !empty($_GET['standard_id']) ? (int)$_GET['standard_id'] : null,
    'subject_id'  => !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : null,
    'format'      => !empty($_GET['format']) ? trim((string)$_GET['format']) : 'lines',
];

$epPage = 'create';
$epTitle = $editPaper ? 'Edit Paper' : 'Create Paper';
$epScripts = ['create_paper.js'];
require __DIR__ . '/includes/header.php';
?>
<div id="epApp" data-edit='<?= $editPaper ? h(json_encode($editPaper, JSON_UNESCAPED_UNICODE)) : '' ?>' data-params='<?= h(json_encode($initialParams, JSON_UNESCAPED_UNICODE)) ?>'>
<h3 class="mb-2"><?= $editPaper ? 'Edit Question Paper' : 'Create Question Paper' ?></h3>

<div class="alert alert-primary py-2 px-3 mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-sm" style="border-left: 5px solid #0d6efd;">
  <div>
    <i class="bi bi-info-circle-fill me-2 fs-5 text-primary align-middle"></i>
    <strong>इयत्ता १ ली ते ८ वी सर्व वर्ग व घटक चाचणी प्रश्न उपलब्ध आहेत.</strong>
    <span class="text-muted ms-1">सत्र १ किंवा २ चे संकलित / आकारिक मूल्यमापन पेपर (Summative/Formative Paper with Answer Lines) काढण्यासाठी:</span>
  </div>
  <a href="assessment_paper.php" class="btn btn-primary btn-sm fw-bold shadow-sm">
    <i class="bi bi-journal-check me-1"></i> संकलित / आकारिक मूल्यमापन चाचणी जनरेटर &rarr;
  </a>
</div>

<div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-sm" style="border-left: 5px solid #f59e0b;">
  <div>
    <i class="bi bi-trophy-fill me-2 fs-5 text-warning align-middle"></i>
    <strong>स्पर्धा परीक्षा वस्तुनिष्ठ चाचणी (Competitive Exam / MCQ Quiz Paper):</strong>
    <span class="text-muted ms-1">शिष्यवृत्ती / नवोदय / NMMS किंवा बहुपर्यायी वस्तुनिष्ठ (MCQ) सराव चाचणी काढण्यासाठी:</span>
  </div>
  <a href="competitive_paper.php" class="btn btn-warning btn-sm fw-bold shadow-sm text-dark">
    <i class="bi bi-ui-checks me-1"></i> स्पर्धा परीक्षा OMR क्विझ जनरेटर &rarr;
  </a>
</div>

<div class="row g-4">
  <!-- LEFT: setup -->
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">1</span> Class &amp; Subject</div>
      <div class="card-body">
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small mb-0 fw-bold">Class / Standard (इयत्ता)</label>
            <div class="btn-group btn-group-sm" role="group" id="stdFilterGroup" style="font-size: 11px;">
              <button type="button" class="btn btn-outline-secondary py-0 px-2 active" data-filter="all">सर्व</button>
              <button type="button" class="btn btn-outline-primary py-0 px-2" data-filter="regular">📚 शालेय इयत्ता</button>
              <button type="button" class="btn btn-outline-warning py-0 px-2 text-dark" data-filter="competitive">🏆 स्पर्धा परीक्षा</button>
            </div>
          </div>
          <select class="form-select" id="standard">
            <option value="">-- select class / वर्ग निवडा --</option>
            <optgroup label="📚 शालेय इयत्ता (School Standards)" id="grpRegular">
              <?php foreach ($regularStandards as $s): ?>
                <option value="<?= (int)$s['standard_id'] ?>" data-type="regular"><?= h(ep_format_standard_name($s)) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="🏆 स्पर्धा परीक्षा घटक (Competitive Exam Topics)" id="grpCompetitive">
              <?php foreach ($competitiveStandards as $s): ?>
                <option value="<?= (int)$s['standard_id'] ?>" data-type="competitive"><?= h(ep_format_standard_name($s)) ?></option>
              <?php endforeach; ?>
            </optgroup>
          </select>
          <div id="competitiveNotice" class="alert alert-warning py-2 px-3 mt-2 small d-none" style="font-size: 12px; border-left: 4px solid #f59e0b;">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div>
                <i class="bi bi-trophy-fill text-warning me-1"></i>
                <strong>हे घटक स्पर्धा परीक्षेचे आहेत (Competitive Exam Topics):</strong>
                <div class="text-muted mt-1">येथून आपण या घटकांचे प्रश्न वापरू शकता, किंवा संपूर्ण OMR MCQ सराव परीक्षेसाठी आमचा समर्पित स्पर्धा परीक्षा जनरेटर वापरू शकता.</div>
              </div>
              <a href="competitive_paper.php" class="btn btn-warning btn-sm text-nowrap fw-bold" style="font-size: 11.5px;">
                <i class="bi bi-box-arrow-up-right me-1"></i> स्पर्धा परीक्षा जनरेटर
              </a>
            </div>
          </div>
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
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label small mb-0 fw-semibold">Ready template</label>
          <a href="competitive_paper.php" class="small text-decoration-none fw-bold text-warning-emphasis" title="स्पर्धा परीक्षा क्विझ पेपर">
            <i class="bi bi-trophy-fill text-warning"></i> स्पर्धा परीक्षा क्विझ &rarr;
          </a>
        </div>
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
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Total Marks</label><input type="number" class="form-control" id="totalMarks" placeholder="auto"></div>
          <div class="col-6"><label class="form-label small mb-1">Format (स्वरूप)</label>
            <select class="form-select" id="paperFormat">
              <option value="lines">उत्तर-लेखन ओळींसह (Lines)</option>
              <option value="standard">केवळ प्रश्नपत्रिका (Compact)</option>
              <option value="quiz">वस्तुनिष्ठ क्विझ (MCQ Quiz / स्पर्धा परीक्षा)</option>
            </select>
          </div>
        </div>
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
