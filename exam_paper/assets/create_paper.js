(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const api = (action, opts = {}) => fetch('api.php?action=' + action + (opts.query || ''), {
    method: opts.body ? 'POST' : 'GET',
    headers: opts.body ? { 'Content-Type': 'application/json' } : {},
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  }).then(r => r.json());

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const markup = s => esc(String(s ?? '').trim()).replace(/\t+/g, '&emsp;&emsp;').replace(/ {3,}/g, '&emsp;&emsp;').replace(/\n/g, '<br>');

  const state = { paperId: null, templateId: null, sections: [] };
  // section: {title, marks, type, qno, count, questions: [questionObj]}

  const app = $('#epApp');
  const editData = app.dataset.edit ? JSON.parse(app.dataset.edit) : null;

  const selectedChapterIds = () => $$('#chapters input:checked').map(i => +i.value);
  const usedIds = () => state.sections.flatMap(s => s.questions.map(q => q.question_id));

  // ---------- loading hierarchy ----------
  $('#standard').addEventListener('change', async e => {
    const sel = $('#subject');
    sel.innerHTML = '<option value="">-- select subject --</option>';
    sel.disabled = true;
    resetChapters();
    if (!e.target.value) return;
    const subjects = await api('subjects', { query: '&standard_id=' + e.target.value });
    subjects.forEach(s => sel.add(new Option(`${s.name} (${s.question_count} questions)`, s.subject_id)));
    sel.disabled = false;
  });

  function resetChapters() {
    $('#chapters').innerHTML = '<span class="text-muted small">Select a subject first</span>';
    const t = $('#template');
    t.innerHTML = '<option value="">-- choose a template --</option>';
    t.disabled = true;
  }

  $('#subject').addEventListener('change', async e => {
    resetChapters();
    if (!e.target.value) return;
    await loadSubject(+e.target.value);
  });

  async function loadSubject(subjectId, checkedIds) {
    const [chapters, templates] = await Promise.all([
      api('chapters', { query: '&subject_id=' + subjectId }),
      api('templates', { query: '&subject_id=' + subjectId }),
    ]);
    $('#chapters').innerHTML = chapters.map(c => `
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="${c.chapter_id}" id="ch${c.chapter_id}" ${(!checkedIds || checkedIds.includes(c.chapter_id)) ? 'checked' : ''}>
        <label class="form-check-label small" for="ch${c.chapter_id}">${esc(c.name)} <span class="text-muted">(${c.question_count})</span></label>
      </div>`).join('') || '<span class="text-muted small">No chapters</span>';
    const t = $('#template');
    t.innerHTML = '<option value="">-- choose a template --</option>';
    window._templates = templates;
    templates.forEach(tp => t.add(new Option(`${tp.title} - ${tp.total_marks} marks`, tp.template_id)));
    t.disabled = false;
  }

  $('#chAll').onclick = e => { e.preventDefault(); $$('#chapters input').forEach(i => i.checked = true); };
  $('#chNone').onclick = e => { e.preventDefault(); $$('#chapters input').forEach(i => i.checked = false); };

  // ---------- templates & sections ----------
  $('#template').addEventListener('change', e => {
    const tp = (window._templates || []).find(t => t.template_id == e.target.value);
    if (!tp) return;
    if (state.sections.length && !confirm('Replace current sections with this template?')) { e.target.value = ''; return; }
    state.templateId = tp.template_id;
    state.sections = tp.questions.map(q => ({
      title: q.question_title, marks: q.marks, type: q.question_type, qno: q.question_number, count: q.no_of_questions, questions: [],
    }));
    if (!$('#title').value) $('#title').value = tp.title;
    $('#totalMarks').value = tp.total_marks;
    render();
  });

  $('#addSection').onclick = e => {
    e.preventDefault();
    state.sections.push({ title: `Q.${state.sections.length + 1}) Answer the following`, marks: 2, type: 'descriptive', qno: '2', count: 3, questions: [] });
    render();
  };

  $('#autoFill').onclick = async () => {
    const ch = selectedChapterIds();
    if (!ch.length) return alert('Select at least one chapter');
    if (!state.sections.length) return alert('Choose a template or add a section first');
    for (const s of state.sections) {
      const need = s.count - s.questions.length;
      if (need <= 0) continue;
      const got = await api('random', { body: { chapter_ids: ch, type: s.type, qno: s.qno, count: need, exclude: usedIds() } });
      s.questions.push(...got);
    }
    render();
  };

  function questionHtml(q, idx) {
    const subs = (q.sub_questions || []).map((sq, i) => `<div class="ms-3 small">${String.fromCharCode(105 + i)}) ${markup(sq.markup)}${sq.image_url ? `<div><img src="${sq.image_url}" class="ep-qimg mt-1"></div>` : ''}</div>`).join('');
    return `
      <div class="ep-q border rounded p-2 mb-2 bg-white">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="flex-grow-1">
            <span class="fw-semibold">${idx + 1}.</span> ${q.passage ? `<div class="fst-italic small mb-1">${markup(q.passage)}</div>` : ''}${markup(q.markup)}
            ${q.image_url ? `<div><img src="${q.image_url}" class="ep-qimg mt-1"></div>` : ''}
            ${q.markup2 ? `<div>${markup(q.markup2)}</div>` : ''}
            ${q.image2_url ? `<div><img src="${q.image2_url}" class="ep-qimg mt-1"></div>` : ''}
            ${q.markup3 ? `<div>${markup(q.markup3)}</div>` : ''}
            ${subs}
            <div class="text-success small mt-1"><i class="bi bi-check2"></i> ${markup(q.answer_markup) || '<span class="text-muted">no answer key</span>'}</div>
          </div>
          <div class="btn-group-vertical btn-group-sm">
            <button class="btn btn-outline-secondary" title="Swap for another random question" data-act="swap"><i class="bi bi-shuffle"></i></button>
            <button class="btn btn-outline-secondary" title="Browse & choose" data-act="browse"><i class="bi bi-list-ul"></i></button>
            <button class="btn btn-outline-danger" title="Remove" data-act="remove"><i class="bi bi-x"></i></button>
          </div>
        </div>
      </div>`;
  }

  function render() {
    const wrap = $('#sections');
    $('#emptyHint').style.display = state.sections.length ? 'none' : '';
    wrap.innerHTML = state.sections.map((s, si) => `
      <div class="card shadow-sm mb-3 ep-section" data-si="${si}">
        <div class="card-header py-2">
          <div class="row g-2 align-items-center">
            <div class="col-md-5"><input class="form-control form-control-sm" data-f="title" value="${esc(s.title)}" placeholder="Section title"></div>
            <div class="col-md-2"><div class="input-group input-group-sm"><span class="input-group-text">Marks</span><input type="number" class="form-control" data-f="marks" value="${s.marks}"></div></div>
            <div class="col-md-2"><select class="form-select form-select-sm" data-f="type">
              ${['mcq', 'fillinblanks', 'descriptive'].map(t => `<option value="${t}" ${s.type === t ? 'selected' : ''}>${t === 'mcq' ? 'MCQ' : t === 'fillinblanks' ? 'Fill blanks' : 'Descriptive'}</option>`).join('')}
            </select></div>
            <div class="col-md-1"><input class="form-control form-control-sm" data-f="qno" value="${esc(s.qno)}" title="Question number pattern (1a, 1b, 2 ...)"></div>
            <div class="col-md-1"><input type="number" class="form-control form-control-sm" data-f="count" value="${s.count}" title="No. of questions"></div>
            <div class="col-md-1 text-end">
              <button class="btn btn-sm btn-outline-success" data-act="fill" title="Fill section"><i class="bi bi-magic"></i></button>
              <button class="btn btn-sm btn-outline-danger" data-act="delsec" title="Delete section"><i class="bi bi-trash"></i></button>
            </div>
          </div>
        </div>
        <div class="card-body py-2">
          ${s.questions.map((q, qi) => questionHtml(q, qi).replace('class="ep-q ', `data-qi="${qi}" class="ep-q `)).join('') || '<div class="text-muted small">No questions yet - click the magic wand to auto-fill.</div>'}
          <button class="btn btn-sm btn-outline-primary" data-act="addq"><i class="bi bi-plus"></i> Add question</button>
        </div>
      </div>`).join('');
  }

  $('#sections').addEventListener('input', e => {
    const f = e.target.dataset.f;
    if (!f) return;
    const s = state.sections[+e.target.closest('.ep-section').dataset.si];
    s[f] = (f === 'marks' || f === 'count') ? +e.target.value : e.target.value;
  });

  $('#sections').addEventListener('click', async e => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const secEl = btn.closest('.ep-section');
    const si = +secEl.dataset.si;
    const s = state.sections[si];
    const qEl = btn.closest('.ep-q');
    const qi = qEl ? +qEl.dataset.qi : -1;
    const ch = selectedChapterIds();
    switch (btn.dataset.act) {
      case 'delsec': state.sections.splice(si, 1); break;
      case 'remove': s.questions.splice(qi, 1); break;
      case 'fill': case 'addq': {
        if (!ch.length) return alert('Select at least one chapter');
        const need = btn.dataset.act === 'addq' ? 1 : Math.max(0, s.count - s.questions.length);
        const got = await api('random', { body: { chapter_ids: ch, type: s.type, qno: s.qno, count: need || 1, exclude: usedIds() } });
        if (!got.length) alert('No more questions available for this type / question number in the selected chapters.');
        s.questions.push(...got);
        break;
      }
      case 'swap': {
        const got = await api('random', { body: { chapter_ids: ch, type: s.type, qno: s.qno, count: 1, exclude: usedIds() } });
        if (!got.length) return alert('No alternative question available.');
        s.questions[qi] = got[0];
        break;
      }
      case 'browse': openBrowse(si, qi); return;
    }
    render();
  });

  // ---------- browse modal ----------
  const browse = { si: 0, qi: -1, page: 0 };
  const modal = () => bootstrap.Modal.getOrCreateInstance($('#browseModal'));
  function openBrowse(si, qi) { browse.si = si; browse.qi = qi; browse.page = 0; $('#browseSearch').value = ''; loadBrowse(); modal().show(); }
  async function loadBrowse() {
    const s = state.sections[browse.si];
    const res = await api('browse', { body: { chapter_ids: selectedChapterIds(), type: s.type, qno: s.qno, search: $('#browseSearch').value, page: browse.page } });
    const used = new Set(usedIds());
    $('#browseList').innerHTML = res.items.map(q => `
      <div class="border rounded p-2 mb-2 ${used.has(q.question_id) ? 'bg-light opacity-50' : 'bg-white'}">
        <div class="d-flex justify-content-between gap-2">
          <div>${markup(q.markup)}${q.image_url ? `<div><img src="${q.image_url}" class="ep-qimg mt-1"></div>` : ''}<div class="text-success small">${markup(q.answer_markup)}</div></div>
          <div><button class="btn btn-sm btn-primary" data-pick="${q.question_id}" ${used.has(q.question_id) ? 'disabled' : ''}>Use</button></div>
        </div>
      </div>`).join('') || '<div class="text-muted">No questions found.</div>';
    window._browseItems = res.items;
    $('#browseInfo').textContent = `${res.total} questions - page ${res.page + 1} of ${Math.max(1, Math.ceil(res.total / res.size))}`;
  }
  $('#browseGo').onclick = () => { browse.page = 0; loadBrowse(); };
  $('#browseSearch').onkeydown = e => { if (e.key === 'Enter') { browse.page = 0; loadBrowse(); } };
  $('#browsePrev').onclick = () => { if (browse.page > 0) { browse.page--; loadBrowse(); } };
  $('#browseNext').onclick = () => { browse.page++; loadBrowse(); };
  $('#browseList').addEventListener('click', e => {
    const b = e.target.closest('[data-pick]');
    if (!b) return;
    const q = window._browseItems.find(x => x.question_id == b.dataset.pick);
    const s = state.sections[browse.si];
    if (browse.qi >= 0) s.questions[browse.qi] = q; else s.questions.push(q);
    modal().hide();
    render();
  });

  // ---------- save ----------
  $('#savePaper').onclick = async () => {
    const msg = $('#saveMsg');
    msg.textContent = 'Saving...';
    const res = await api('save_paper', { body: {
      paper_id: state.paperId, template_id: state.templateId,
      standard_id: $('#standard').value, subject_id: $('#subject').value,
      title: $('#title').value, exam_date: $('#examDate').value, duration: $('#duration').value,
      total_marks: $('#totalMarks').value, instructions: $('#instructions').value,
      sections: state.sections.map(s => ({ title: s.title, marks: s.marks, type: s.type, qno: s.qno, question_ids: s.questions.map(q => q.question_id) })),
    } });
    if (res.status === 'success') {
      state.paperId = res.paper_id;
      msg.innerHTML = `<span class="text-success">Saved.</span> <a href="paper_view.php?id=${res.paper_id}" target="_blank">Open printable paper</a>`;
      window.open('paper_view.php?id=' + res.paper_id, '_blank');
    } else {
      msg.innerHTML = `<span class="text-danger">${esc(res.message)}</span>`;
    }
  };

  // ---------- edit mode ----------
  (async function init() {
    if (!editData) return;
    state.paperId = editData.paper_id;
    state.templateId = editData.template_id;
    $('#standard').value = editData.standard_id;
    const subjects = await api('subjects', { query: '&standard_id=' + editData.standard_id });
    const sel = $('#subject');
    subjects.forEach(s => sel.add(new Option(`${s.name} (${s.question_count} questions)`, s.subject_id)));
    sel.disabled = false;
    sel.value = editData.subject_id;
    await loadSubject(+editData.subject_id);
    const byId = Object.fromEntries(editData.questions.map(q => [q.question_id, q]));
    state.sections = editData.sections.map(s => ({ ...s, count: s.question_ids.length, questions: s.question_ids.map(id => byId[id]).filter(Boolean) }));
    $('#title').value = editData.title || '';
    $('#examDate').value = editData.exam_date || '';
    $('#duration').value = editData.duration || '';
    $('#totalMarks').value = editData.total_marks || '';
    $('#instructions').value = editData.instructions || '';
    render();
  })();
})();
