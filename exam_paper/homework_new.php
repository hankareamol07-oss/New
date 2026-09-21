<?php
/** Daily homework builder (homework items + optional topic quiz). */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/quiz.php';

$edit = !empty($_GET['edit']) ? ep_homework((int)$_GET['edit']) : null;

$epPage = 'homework';
$epTitle = $edit ? 'Edit Homework' : 'New Homework';
$epScripts = ['homework.js'];
require __DIR__ . '/includes/header.php';
?>
<div id="hwApp"
     data-tree='<?= h(json_encode(ep_book_tree(), JSON_UNESCAPED_UNICODE)) ?>'
     data-edit='<?= $edit ? h(json_encode($edit, JSON_UNESCAPED_UNICODE)) : '' ?>'
     data-today="<?= date('Y-m-d') ?>">
<h3 class="mb-1"><?= $edit ? 'Edit' : 'New' ?> दैनिक गृहपाठ <small class="text-muted fs-6">Daily homework + topic quiz</small></h3>
<p class="text-muted small mb-3">Pick class, subject and today's chapter, add homework items (type them or pick स्वाध्याय questions from the textbook), then build a <b>10–15 question quiz</b> on the topic. The printed PDF ends with the quiz link + QR code students open on a phone.</p>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">1</span> Class, Subject &amp; Chapter</div>
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small mb-1">Date</label><input type="date" class="form-control" id="hwDate"></div>
          <div class="col-3"><label class="form-label small mb-1">Class</label><select class="form-select" id="standard"></select></div>
          <div class="col-3"><label class="form-label small mb-1">Division</label><input class="form-control" id="division" placeholder="अ"></div>
        </div>
        <label class="form-label small mb-1">Subject (textbook)</label>
        <select class="form-select mb-2" id="subject"></select>
        <label class="form-label small mb-1">Chapter / पाठ (आजचा टाचण विषय)</label>
        <select class="form-select mb-2" id="chapter"></select>
        <label class="form-label small mb-1">Topic printed on sheet</label>
        <input class="form-control mb-2" id="topic">
        <div class="row g-2">
          <div class="col-6"><label class="form-label small mb-1">Teacher</label><input class="form-control" id="teacher"></div>
          <div class="col-6"><label class="form-label small mb-1">Class label</label><input class="form-control" id="stdLabel"></div>
        </div>
      </div>
    </div>
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><span class="badge bg-primary me-1">2</span> Sheet details</div>
      <div class="card-body">
        <label class="form-label small mb-1">Title</label>
        <input class="form-control mb-2" id="title">
        <label class="form-label small mb-1">Note for students / parents</label>
        <textarea class="form-control" id="note" rows="3" placeholder="उदा. उद्या वर्गात तपासले जाईल. पालकांनी सही करावी."></textarea>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><span class="badge bg-primary me-1">3</span> Homework items</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-success" id="hwFromBook"><i class="bi bi-magic"></i> 5 from textbook स्वाध्याय</button>
          <button class="btn btn-outline-primary" id="hwBrowse"><i class="bi bi-search"></i> Browse textbook</button>
          <button class="btn btn-outline-secondary" id="hwAdd"><i class="bi bi-plus"></i> Type item</button>
        </div>
      </div>
      <div class="card-body" id="hwItems"><div class="text-muted small">No items yet.</div></div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><span class="badge bg-primary me-1">4</span> Topic quiz <small class="text-muted fw-normal">(10–15 questions, auto-scored online)</small></span>
        <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="quizOn" checked><label class="form-check-label small" for="quizOn">Attach quiz</label></div>
      </div>
      <div class="card-body" id="quizBox">
        <div class="row g-2 align-items-end mb-2">
          <div class="col-md-5">
            <label class="form-label small mb-1">MCQ source (question bank chapter)</label>
            <select class="form-select form-select-sm" id="quizSource"></select>
          </div>
          <div class="col-md-2"><label class="form-label small mb-1">Questions</label><select class="form-select form-select-sm" id="quizCount"><option>10</option><option>12</option><option selected>15</option></select></div>
          <div class="col-md-2"><label class="form-label small mb-1">Time (min)</label><input type="number" class="form-control form-control-sm" id="quizTime" value="15" min="0"></div>
          <div class="col-md-3 d-grid"><button class="btn btn-success btn-sm" id="quizAuto"><i class="bi bi-magic"></i> Auto-fill MCQs</button></div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2 small">
          <input class="form-control form-control-sm w-auto" id="quizTitle" placeholder="Quiz title">
          <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="quizShowAns" checked><label class="form-check-label" for="quizShowAns">Show correct answers to student after submit</label></div>
          <div class="ms-auto btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" id="quizAddMcq"><i class="bi bi-plus"></i> MCQ</button>
            <button class="btn btn-outline-secondary" id="quizAddText"><i class="bi bi-plus"></i> Typed answer</button>
            <button class="btn btn-outline-primary" id="quizFromBook"><i class="bi bi-book"></i> From textbook</button>
          </div>
        </div>
        <div id="quizSourceInfo" class="small text-muted mb-2"></div>
        <div id="quizItems"></div>
      </div>
    </div>

    <div class="d-flex gap-2 align-items-center">
      <button class="btn btn-primary btn-lg" id="save"><i class="bi bi-save"></i> Save &amp; open PDF</button>
      <span id="saveMsg" class="text-muted small"></span>
    </div>
  </div>
</div>
</div>

<div class="modal fade" id="browseModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="browseTitle">Choose textbook questions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-2">
      <div class="col-md-3"><select class="form-select" id="browseType"><option value="">All types</option></select></div>
      <div class="col-md-9"><div class="input-group"><input class="form-control" id="browseSearch" placeholder="Search question text..."><button class="btn btn-outline-secondary" id="browseGo"><i class="bi bi-search"></i></button></div></div>
    </div>
    <div id="browseList"></div>
    <div class="d-flex justify-content-between mt-2"><button class="btn btn-sm btn-outline-secondary" id="browsePrev">&laquo; Prev</button><span id="browseInfo" class="small text-muted"></span><button class="btn btn-sm btn-outline-secondary" id="browseNext">Next &raquo;</button></div>
  </div>
</div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
