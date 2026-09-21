(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const api = (action, opts = {}) => fetch('api.php?action=' + action + (opts.query || ''), {
    method: opts.body ? 'POST' : 'GET',
    headers: opts.body ? { 'Content-Type': 'application/json' } : {},
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  }).then(r => r.json());
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const QTYPES = {
    '': 'any type', fill_blank: 'रिकाम्या जागा', true_false: 'चूक / बरोबर', match: 'जोड्या जुळवा', mcq: 'योग्य पर्याय', odd_one: 'गटात न बसणारा',
    one_word: 'एका शब्दात', one_sentence: 'एका वाक्यात', short_answer: 'थोडक्यात', reason: 'कारणे', difference: 'फरक', define: 'व्याख्या',
    explain: 'स्पष्ट करा', solve: 'सोडवा', draw: 'आकृती', vocabulary: 'शब्दसंपत्ती', grammar: 'व्याकरण', activity: 'कृती / उपक्रम', descriptive: 'वर्णनात्मक',
  };
  const MR_DIGITS = '०१२३४५६७८९';
  const mrNum = n => String(n).replace(/\d/g, d => MR_DIGITS[d]);
  const STD_MR = ['', 'पहिली', 'दुसरी', 'तिसरी', 'चौथी', 'पाचवी', 'सहावी', 'सातवी', 'आठवी'];
  const STD_EN = ['', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
  const OPT = ['A', 'B', 'C', 'D', 'E', 'F'];

  const app = $('#hwApp');
  const tree = JSON.parse(app.dataset.tree);
  const editData = app.dataset.edit ? JSON.parse(app.dataset.edit) : null;

  const state = { hwId: null, quizId: null, items: [], quiz: [], sources: [] };
  // items: [{bq_id, text, page_image, show_image, needs_figure, page}]
  // quiz:  [{kind:'mcq'|'text', text, options[], answer, question_id, bq_id}]

  const stdSel = $('#standard'), subjSel = $('#subject'), chSel = $('#chapter');
  const curStd = () => tree.find(s => s.standard == stdSel.value);
  const curSubj = () => (curStd()?.subjects || []).find(s => s.name === subjSel.value);
  const chaptersOf = subj => (subj?.books || []).flatMap(b => b.chapters.map(c => ({ ...c, book: b.title })));
  const curChapter = () => chaptersOf(curSubj()).find(c => c.chapter_id == chSel.value);
  const chapterIds = () => chSel.value ? [+chSel.value] : chaptersOf(curSubj()).map(c => c.chapter_id);
  const usedBq = () => state.items.map(i => i.bq_id).filter(Boolean).concat(state.quiz.map(q => q.bq_id).filter(Boolean));
  const usedQ = () => state.quiz.map(q => q.question_id).filter(Boolean);
  const isEnglish = () => { const s = curSubj(); return s && (s.medium === 'English' || /english/i.test(s.subject)); };

  /* ---------- class / subject / chapter ---------- */
  stdSel.innerHTML = tree.map(s => `<option value="${s.standard}">${s.standard}</option>`).join('');
  $('#hwDate').value = app.dataset.today;
  function fillSubjects() {
    subjSel.innerHTML = (curStd()?.subjects || []).map(s => `<option value="${esc(s.name)}">${esc(s.name)}</option>`).join('');
    fillChapters();
  }
  function fillChapters(selected) {
    const chs = chaptersOf(curSubj());
    chSel.innerHTML = '<option value="">— whole book —</option>' + chs.map(c => `<option value="${c.chapter_id}" ${selected == c.chapter_id ? 'selected' : ''}>${c.no}. ${esc(c.title)} (${c.n})</option>`).join('');
    const book = curSubj()?.books?.[0];
    const editLink = $('#editChapters');
    if (editLink) { editLink.href = `book_chapters.php?book_id=${book ? book.book_id : 0}`; editLink.classList.toggle('d-none', !book); }
    onChapter();
  }
  function onChapter() {
    const c = curChapter();
    const topic = $('#topic');
    if (!topic.dataset.touched) topic.value = c ? c.title : '';
    fillDefaults();
    loadSources();
  }
  function fillDefaults() {
    const std = +stdSel.value, en = isEnglish();
    const t = $('#title');
    if (!t.dataset.touched) t.value = en ? 'Daily Homework' : 'दैनिक गृहपाठ';
    const sl = $('#stdLabel');
    if (!sl.dataset.touched) sl.value = en ? `Std. ${STD_EN[std] || std}` : `इयत्ता ${STD_MR[std] || mrNum(std)}`;
    const qt = $('#quizTitle');
    if (!qt.dataset.touched) qt.value = (en ? 'Quiz: ' : 'प्रश्नमंजुषा: ') + ($('#topic').value || subjSel.value);
  }
  ['title', 'stdLabel', 'topic', 'quizTitle'].forEach(id => $('#' + id).addEventListener('input', e => { e.target.dataset.touched = '1'; if (id === 'topic') { delete $('#quizTitle').dataset.touched; fillDefaults(); } }));
  stdSel.onchange = fillSubjects;
  subjSel.onchange = () => fillChapters();
  chSel.onchange = () => { delete $('#topic').dataset.touched; onChapter(); };

  /* ---------- quiz MCQ sources (scraped bank) ---------- */
  async function loadSources() {
    const s = curSubj(), c = curChapter();
    const sel = $('#quizSource');
    sel.innerHTML = '<option value="">loading…</option>';
    state.sources = s ? await api('quiz_sources', { query: `&standard=${stdSel.value}&subject=${encodeURIComponent(s.subject)}&medium=${encodeURIComponent(s.medium)}&chapter=${encodeURIComponent(c ? c.title : '')}` }) : [];
    const opts = [];
    let best = null;
    for (const src of state.sources) {
      const grp = `${src.std_name} · ${src.subject_name} (${src.medium || ''})`;
      const all = src.chapters.map(x => x.chapter_id);
      opts.push(`<optgroup label="${esc(grp)}"><option value="${all.join(',')}">All chapters of ${esc(src.subject_name)} — ${src.mcq} MCQ</option>` +
        src.chapters.map(x => `<option value="${x.chapter_id}" ${x.score >= 0.34 ? 'class="fw-semibold"' : ''}>${esc(x.name)} — ${x.mcq} MCQ${x.score >= 0.34 ? ' ★' : ''}</option>`).join('') + '</optgroup>');
      for (const x of src.chapters) if (x.mcq >= 8 && x.score >= 0.34 && (!best || x.score > best.score)) best = x;
    }
    sel.innerHTML = opts.length ? opts.join('') : '<option value="">No MCQ bank for this class/subject — add questions manually or from textbook</option>';
    if (best) sel.value = String(best.chapter_id);
    $('#quizSourceInfo').textContent = state.sources.length
      ? (best ? `★ matched question-bank chapter "${best.name}" for auto MCQs. Change the source if it's wrong.` : 'No matching chapter found automatically — choose a bank chapter above, or add quiz questions manually / from the textbook.')
      : 'This class/subject has no MCQ bank. Use "MCQ" / "Typed answer" / "From textbook" to add quiz questions yourself.';
  }

  /* ---------- homework items ---------- */
  function bookItem(q) { return { bq_id: q.bq_id, text: q.text, page_image: q.page_image, show_image: false, needs_figure: !!+q.needs_figure, page: q.page, qtype: q.qtype }; }
  $('#hwAdd').onclick = () => { state.items.push({ bq_id: null, text: '' }); renderItems(); setTimeout(() => { const t = $$('#hwItems textarea').pop(); t && t.focus(); }); };
  $('#hwFromBook').onclick = async () => {
    const rows = await api('book_random', { body: { chapter_ids: chapterIds(), qtype: '', count: 5, exclude: usedBq() } });
    if (!rows.length) return alert('No exercise questions found for this chapter');
    rows.forEach(q => state.items.push(bookItem(q)));
    renderItems();
  };
  function renderItems() {
    const box = $('#hwItems');
    box.innerHTML = state.items.length ? `<ol class="list-group list-group-numbered list-group-flush">${state.items.map((it, i) => `
      <li class="list-group-item d-flex gap-2 align-items-start" data-i="${i}">
        <div class="flex-grow-1">
          <textarea class="form-control form-control-sm" rows="1" data-f="text" placeholder="Homework item…">${esc(it.text)}</textarea>
          <textarea class="form-control form-control-sm mt-1 border-success-subtle" rows="1" data-f="answer" placeholder="Answer / उत्तर (teacher's answer key only, not printed on homework)">${esc(it.answer || '')}</textarea>
        </div>
        <div class="text-nowrap small">
          ${it.needs_figure ? `<label class="me-1" title="Print the textbook page image below this item"><input type="checkbox" data-f="show_image" ${it.show_image ? 'checked' : ''}> <i class="bi bi-image"></i></label>` : ''}
          ${it.page ? `<span class="text-muted me-1">p.${it.page}</span>` : ''}
          ${it.bq_id ? `<button class="btn btn-sm btn-outline-secondary py-0" data-act="swap" title="Replace"><i class="bi bi-shuffle"></i></button>` : ''}
          <button class="btn btn-sm btn-outline-secondary py-0" data-act="up"><i class="bi bi-arrow-up"></i></button>
          <button class="btn btn-sm btn-outline-danger py-0" data-act="del"><i class="bi bi-x"></i></button>
        </div>
      </li>`).join('')}</ol>` : '<div class="text-muted small">No items yet — type an item, pick from the textbook स्वाध्याय, or browse.</div>';
    $$('textarea', box).forEach(t => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 2 + 'px'; });
  }
  $('#hwItems').addEventListener('input', e => {
    const li = e.target.closest('li'); if (!li) return;
    const it = state.items[+li.dataset.i], f = e.target.dataset.f;
    it[f] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
    if (f === 'text') { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 2 + 'px'; }
  });
  $('#hwItems').addEventListener('click', async e => {
    const btn = e.target.closest('[data-act]'); if (!btn) return;
    const i = +btn.closest('li').dataset.i;
    if (btn.dataset.act === 'del') state.items.splice(i, 1);
    if (btn.dataset.act === 'up' && i > 0) [state.items[i - 1], state.items[i]] = [state.items[i], state.items[i - 1]];
    if (btn.dataset.act === 'swap') {
      const rows = await api('book_random', { body: { chapter_ids: chapterIds(), qtype: state.items[i].qtype || '', count: 1, exclude: usedBq() } });
      if (!rows.length) return alert('No other question available');
      state.items[i] = bookItem(rows[0]);
    }
    renderItems();
  });

  /* ---------- quiz ---------- */
  $('#quizOn').onchange = () => { $('#quizBox').style.display = $('#quizOn').checked ? '' : 'none'; };
  $('#quizAuto').onclick = async () => {
    const src = $('#quizSource').value;
    if (!src) return alert('Choose an MCQ source chapter first (or add questions manually)');
    const need = +$('#quizCount').value - state.quiz.length;
    if (need <= 0) return alert(`Quiz already has ${state.quiz.length} questions`);
    const rows = await api('quiz_mcq', { body: { chapter_ids: src.split(','), count: need, exclude: usedQ() } });
    rows.forEach(q => state.quiz.push({ kind: 'mcq', text: q.text, options: q.options, answer: q.answer, question_id: q.question_id }));
    if (rows.length < need) alert(`Only ${rows.length} auto-scorable MCQs found; add the rest manually or pick another source chapter.`);
    renderQuiz();
  };
  $('#quizAddMcq').onclick = () => { state.quiz.push({ kind: 'mcq', text: '', options: ['', '', '', ''], answer: 0 }); renderQuiz(); };
  $('#quizAddText').onclick = () => { state.quiz.push({ kind: 'text', text: '', answer: '' }); renderQuiz(); };
  $('#quizFromBook').onclick = () => openBrowse('quiz');
  $('#hwBrowse').onclick = () => openBrowse('hw');

  function renderQuiz() {
    const box = $('#quizItems');
    const n = state.quiz.length;
    box.innerHTML = (n ? `<div class="small mb-2 ${n < 10 ? 'text-warning' : 'text-success'}">${n} question${n === 1 ? '' : 's'}${n < 10 ? ' — add at least 10' : ''}${n > 15 ? ' — more than 15, consider trimming' : ''}</div>` : '') +
      state.quiz.map((q, i) => `
      <div class="border rounded p-2 mb-2 quiz-q" data-i="${i}">
        <div class="d-flex gap-2 align-items-start">
          <span class="badge bg-secondary mt-1">${i + 1}</span>
          <textarea class="form-control form-control-sm" rows="1" data-f="text" placeholder="${q.kind === 'mcq' ? 'Question with options below' : 'Question — student types the answer'}">${esc(q.text)}</textarea>
          <button class="btn btn-sm btn-outline-danger py-0" data-act="del"><i class="bi bi-x"></i></button>
        </div>
        ${q.kind === 'mcq' ? `<div class="row g-1 mt-1 ms-4">${q.options.map((o, oi) => `
          <div class="col-md-6"><div class="input-group input-group-sm">
            <span class="input-group-text"><input type="radio" name="ans${i}" data-f="answer" value="${oi}" ${q.answer === oi ? 'checked' : ''} title="Correct answer"> &nbsp;${OPT[oi]}</span>
            <input class="form-control" data-f="opt" data-oi="${oi}" value="${esc(o)}" placeholder="Option ${OPT[oi]}">
          </div></div>`).join('')}</div>`
          : `<div class="input-group input-group-sm mt-1 ms-4" style="max-width:420px"><span class="input-group-text">Answer</span><input class="form-control" data-f="answer" value="${esc(q.answer)}" placeholder="exact expected answer (auto-checked)"></div>`}
      </div>`).join('');
    $$('textarea', box).forEach(t => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 2 + 'px'; });
  }
  $('#quizItems').addEventListener('input', e => {
    const q = state.quiz[+e.target.closest('.quiz-q').dataset.i], f = e.target.dataset.f;
    if (f === 'opt') q.options[+e.target.dataset.oi] = e.target.value;
    else if (f === 'answer') q.answer = q.kind === 'mcq' ? +e.target.value : e.target.value;
    else if (f === 'text') { q.text = e.target.value; e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 2 + 'px'; }
  });
  $('#quizItems').addEventListener('click', e => {
    const btn = e.target.closest('[data-act="del"]'); if (!btn) return;
    state.quiz.splice(+btn.closest('.quiz-q').dataset.i, 1);
    renderQuiz();
  });

  /* ---------- browse textbook modal (homework items or quiz text questions) ---------- */
  const modal = new bootstrap.Modal($('#browseModal'));
  let browseTarget = 'hw', browsePage = 0;
  $('#browseType').innerHTML = Object.entries(QTYPES).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
  function openBrowse(target) {
    browseTarget = target; browsePage = 0;
    $('#browseTitle').textContent = target === 'quiz' ? 'Add textbook question to quiz (you type the expected answer)' : 'Choose textbook questions for homework';
    $('#browseType').value = target === 'quiz' ? 'fill_blank' : '';
    $('#browseSearch').value = '';
    modal.show(); browse();
  }
  async function browse() {
    const r = await api('book_browse', { body: { chapter_ids: chapterIds(), standard: stdSel.value, qtype: $('#browseType').value, search: $('#browseSearch').value, page: browsePage } });
    const used = usedBq();
    $('#browseInfo').textContent = `${r.total} questions — page ${browsePage + 1} of ${Math.max(1, Math.ceil(r.total / r.size))}`;
    $('#browseList').innerHTML = r.items.length ? r.items.map(q => `
      <div class="border rounded p-2 mb-2 d-flex gap-2 align-items-start ${used.includes(q.bq_id) ? 'bg-light' : ''}">
        <div class="flex-grow-1"><div>${esc(q.text)}</div>
          <div class="small text-muted">${esc(q.chapter_title || '')} · p.${q.page} · ${QTYPES[q.qtype] || q.qtype}${+q.needs_figure ? ' · <i class="bi bi-image"></i> figure' : ''}</div></div>
        <button class="btn btn-sm ${used.includes(q.bq_id) ? 'btn-secondary disabled' : 'btn-primary'}" data-add="${q.bq_id}">${used.includes(q.bq_id) ? 'Added' : 'Add'}</button>
      </div>`).join('') : '<div class="text-muted">No questions match.</div>';
    $$('[data-add]', $('#browseList')).forEach(b => b.onclick = () => {
      const q = r.items.find(x => x.bq_id == b.dataset.add);
      if (browseTarget === 'quiz') { state.quiz.push({ kind: 'text', text: q.text, answer: '', bq_id: q.bq_id }); renderQuiz(); }
      else { state.items.push(bookItem(q)); renderItems(); }
      b.className = 'btn btn-sm btn-secondary disabled'; b.textContent = 'Added';
    });
  }
  $('#browseGo').onclick = () => { browsePage = 0; browse(); };
  $('#browseSearch').onkeydown = e => { if (e.key === 'Enter') { browsePage = 0; browse(); } };
  $('#browseType').onchange = () => { browsePage = 0; browse(); };
  $('#browsePrev').onclick = () => { if (browsePage > 0) { browsePage--; browse(); } };
  $('#browseNext').onclick = () => { browsePage++; browse(); };

  /* ---------- save ---------- */
  $('#save').onclick = async () => {
    const s = curSubj();
    const quizOn = $('#quizOn').checked;
    if (quizOn && state.quiz.length) {
      const bad = state.quiz.findIndex(q => !q.text.trim() || (q.kind === 'mcq' ? q.options.filter(o => o.trim()).length < 2 : !q.answer.trim()));
      if (bad >= 0) return alert(`Quiz question ${bad + 1} is incomplete (needs text, options / expected answer).`);
      if (state.quiz.length < 10 && !confirm(`Quiz has only ${state.quiz.length} questions (recommended 10–15). Save anyway?`)) return;
    }
    const body = {
      hw_id: state.hwId, quiz_id: state.quizId, hw_date: $('#hwDate').value, standard: +stdSel.value, division: $('#division').value, std_label: $('#stdLabel').value,
      subject: s?.subject || '', medium: s?.medium || '', chapter_id: +chSel.value || null, topic: $('#topic').value, teacher: $('#teacher').value,
      title: $('#title').value, note: $('#note').value,
      items: state.items.map(i => ({ bq_id: i.bq_id, text: i.text, answer: i.answer || '', page_image: i.page_image, show_image: i.show_image })),
      quiz: quizOn ? { title: $('#quizTitle').value, time_limit: +$('#quizTime').value, show_answers: $('#quizShowAns').checked, questions: state.quiz } : { questions: [] },
    };
    const r = await api('save_homework', { body });
    const msg = $('#saveMsg');
    if (r.status !== 'success') { msg.className = 'small text-danger'; msg.textContent = r.message || 'Save failed'; return; }
    state.hwId = r.hw_id; state.quizId = r.quiz_id;
    msg.className = 'small text-success';
    msg.innerHTML = `Saved. <a href="homework_view.php?id=${r.hw_id}" target="_blank">Open PDF</a> · <a href="homework_key.php?id=${r.hw_id}" target="_blank">Answer key</a> · <a href="homework.php">All homework</a>`;
    window.open('homework_view.php?id=' + r.hw_id, '_blank');
  };

  /* ---------- edit mode ---------- */
  if (editData) {
    state.hwId = editData.hw_id; state.quizId = editData.quiz_id;
    $('#hwDate').value = editData.hw_date;
    if (tree.some(s => s.standard == editData.standard)) stdSel.value = editData.standard;
    subjSel.innerHTML = (curStd()?.subjects || []).map(s => `<option value="${esc(s.name)}">${esc(s.name)}</option>`).join('');
    const subj = (curStd()?.subjects || []).find(s => s.subject === editData.subject && s.medium === editData.medium);
    if (subj) subjSel.value = subj.name;
    ['title', 'stdLabel', 'topic', 'quizTitle'].forEach(id => $('#' + id).dataset.touched = '1');
    fillChapters(editData.chapter_id);
    $('#topic').value = editData.topic || ''; $('#title').value = editData.title; $('#stdLabel').value = editData.std_label || '';
    $('#division').value = editData.division || ''; $('#teacher').value = editData.teacher || ''; $('#note').value = editData.note || '';
    state.items = editData.items.map(i => ({ ...i, needs_figure: !!i.page_image }));
    if (editData.quiz) {
      state.quiz = editData.quiz.questions;
      $('#quizTitle').value = editData.quiz.title; $('#quizTime').value = editData.quiz.time_limit; $('#quizShowAns').checked = !!+editData.quiz.show_answers;
      $('#quizCount').value = state.quiz.length <= 10 ? '10' : state.quiz.length <= 12 ? '12' : '15';
    } else { $('#quizOn').checked = false; $('#quizBox').style.display = 'none'; }
    renderItems(); renderQuiz();
  } else {
    fillSubjects();
    renderItems(); renderQuiz();
  }
})();
