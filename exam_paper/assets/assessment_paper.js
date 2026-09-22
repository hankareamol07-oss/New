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

  const app = $('#epApp');
  const tree = JSON.parse(app.dataset.tree);      // [{standard, subjects:[{name, subject, medium, n, books:[{book_id,title,chapters:[{chapter_id,no,title,n}]}]}]}]
  const editData = app.dataset.edit ? JSON.parse(app.dataset.edit) : null;

  const state = { paperId: null, sections: [], models: [] };
  // section: {q_no, sub, instruction, marks, qtype, count, items:[{bq_id, text, page_image, show_image, needs_figure, page}]}

  const stdSel = $('#standard'), subjSel = $('#subject');
  const curStd = () => tree.find(s => s.standard == stdSel.value);
  const curSubj = () => (curStd()?.subjects || []).find(s => s.name === subjSel.value);
  const chaptersOf = subj => (subj?.books || []).flatMap(b => b.chapters.map(c => ({ ...c, book: b.title })));
  const selectedChapters = () => $$('#chapters input:checked').map(i => +i.value);
  const usedIds = () => state.sections.flatMap(s => s.items.map(i => i.bq_id).filter(Boolean));
  const isEnglish = () => { const s = curSubj(); return s && (s.medium === 'English' || /english/i.test(s.subject)); };

  /* ---------- class / subject / chapters ---------- */
  stdSel.innerHTML = tree.map(s => `<option value="${s.standard}">इयत्ता ${mrNum(s.standard)} (Std ${s.standard})</option>`).join('');
  function fillSubjects() {
    const st = curStd();
    subjSel.innerHTML = (st?.subjects || []).map(s => `<option value="${esc(s.name)}">${esc(s.name)} — ${s.n} Q</option>`).join('');
    fillChapters();
  }
  function fillChapters(checked) {
    const chs = chaptersOf(curSubj());
    const half = Math.ceil(chs.length / 2);
    const testNo = +$('#testNo').value;
    $('#chapters').innerHTML = chs.length ? chs.map((c, i) => {
      const on = checked ? checked.includes(c.chapter_id) : ($('#examType').value === 'sankalit' ? (testNo === 1 ? i < half : i >= half) : true);
      return `<div class="form-check small"><input class="form-check-input" type="checkbox" value="${c.chapter_id}" id="ch${c.chapter_id}" ${on ? 'checked' : ''}>
        <label class="form-check-label" for="ch${c.chapter_id}">${c.no}. ${esc(c.title)} <span class="text-muted">(${c.n})</span></label></div>`;
    }).join('') : '<div class="text-muted small">No exercise questions extracted for this subject yet.</div>';
    loadModels();
    fillDefaults();
  }
  function fillDefaults() {
    const std = +stdSel.value, type = $('#examType').value, no = +$('#testNo').value, en = isEnglish();
    const t = $('#title');
    if (!t.dataset.touched) t.value = en ? `${type === 'sankalit' ? 'Summative' : 'Formative'} Evaluation Test - ${no}` : `${type === 'sankalit' ? 'संकलित' : 'आकारिक'} मूल्यमापन चाचणी - ${mrNum(no)}`;
    const sl = $('#stdLabel');
    if (!sl.dataset.touched) sl.value = en ? `Std. ${STD_EN[std] || std}` : `इयत्ता - ${STD_MR[std] || mrNum(std)}`;
    const d = $('#duration');
    if (!d.dataset.touched) d.value = type === 'sankalit' ? (en ? '2 Hrs' : '२ तास') : (en ? '1 Hr' : '१ तास');
  }
  ['title', 'stdLabel', 'duration'].forEach(id => $('#' + id).addEventListener('input', e => e.target.dataset.touched = '1'));
  stdSel.onchange = fillSubjects;
  subjSel.onchange = () => fillChapters();
  $('#examType').onchange = $('#testNo').onchange = () => fillChapters();
  $('#chAll').onclick = e => { e.preventDefault(); $$('#chapters input').forEach(i => i.checked = true); };
  $('#chNone').onclick = e => { e.preventDefault(); $$('#chapters input').forEach(i => i.checked = false); };
  $('#chFirst').onclick = e => { e.preventDefault(); const l = $$('#chapters input'); l.forEach((i, k) => i.checked = k < Math.ceil(l.length / 2)); };
  $('#chSecond').onclick = e => { e.preventDefault(); const l = $$('#chapters input'); l.forEach((i, k) => i.checked = k >= Math.ceil(l.length / 2)); };

  /* ---------- layouts (default + real paper models) ---------- */
  const SUBJECT_MR = { Marathi: 'मराठी', Maths: 'गणित', English: 'इंग्रजी', EVS: 'परिसर अभ्यास', Science: 'विज्ञान', Hindi: 'हिंदी', Geography: 'भूगोल', 'History & Civics': 'इतिहास' };
  async function loadModels() {
    const s = curSubj();
    if (!s) return;
    const type = $('#examType').value;
    const all = await api('paper_models', { query: `&exam_type=${type}&standard=${stdSel.value}` });
    const mr = SUBJECT_MR[s.subject.replace(/ Part \d/, '')] || s.subject;
    const isSemi = s.medium === 'English' && !/english/i.test(s.subject);
    state.models = all.filter(m => m.subject.includes(mr) || (isSemi && m.subject.includes('सेमी')) || m.subject.includes(s.subject));
    const others = all.filter(m => !state.models.includes(m));
    $('#layout').innerHTML = '<option value="default">Standard layout (auto by subject)</option>' +
      (state.models.length ? '<optgroup label="Real papers — this class & subject">' + state.models.map(m => `<option value="${m.model_id}">${m.subject} — चाचणी ${m.test_no} (${m.sections.length} sections${m.total_marks ? ', ' + m.total_marks + ' गुण' : ''})</option>`).join('') + '</optgroup>' : '') +
      (others.length ? '<optgroup label="Real papers — other subjects (same class)">' + others.map(m => `<option value="${m.model_id}">${m.subject} — चाचणी ${m.test_no} (${m.sections.length} sections)</option>`).join('') + '</optgroup>' : '');
    state.models = all;
  }
  const guessType = instr => {
    const t = instr.toLowerCase();
    const rules = [['fill_blank', /रिकाम्या|रिक्त|blank|गाळलेल/], ['true_false', /चूक|बरोबर|true|false/], ['match', /जोड्या|जोड़ी|match/], ['mcq', /पर्याय|option|alternative|correct/],
      ['one_word', /एका शब्दात|one word|कोण ते|who /], ['one_sentence', /एका वाक्यात|one sentence|एक वाक्य/], ['short_answer', /थोडक्यात|दोन-तीन|two or three|briefly/],
      ['vocabulary', /समानार्थी|विरुद्धार्थी|synonym|opposite|rhym|वाक्प्रचार|अनेकवचन|लिंग/], ['reason', /कारण|reason|why/], ['solve', /सोडवा|बेरीज|वजाबाकी|गुणाकार|भागाकार|solve|उदाहरण|संख्या/],
      ['draw', /आकृती|draw|चित्र/], ['descriptive', /लिहा|write|answer/]];
    return (rules.find(r => r[1].test(t)) || [''])[0];
  };
  $('#loadLayout').onclick = async e => {
    e.preventDefault();
    if (state.sections.length && !confirm('Replace current sections with this layout?')) return;
    const v = $('#layout').value;
    if (v === 'default') {
      const s = curSubj();
      const lay = await api('assessment_layout', { query: `&exam_type=${$('#examType').value}&subject=${encodeURIComponent(s?.subject || '')}&medium=${encodeURIComponent(s?.medium || 'Marathi')}` });
      state.sections = lay.sections.map(x => ({ ...x, items: [] }));
    } else {
      const m = state.models.find(x => x.model_id == v);
      state.sections = m.sections.map(x => ({
        q_no: x.q_no, sub: x.sub || '', instruction: x.instruction, marks: x.marks || 0, qtype: guessType(x.instruction),
        count: x.items.length || Math.max(1, x.marks || 1), items: x.items.map(t => ({ bq_id: null, text: t })),
      }));
      if (m.total_marks) $('#totalMarks').value = m.total_marks;
    }
    render();
  };

  /* ---------- sections ---------- */
  $('#addSection').onclick = e => {
    e.preventDefault();
    const last = state.sections[state.sections.length - 1];
    state.sections.push({ q_no: (last?.q_no || 0) + 1, sub: '', instruction: '', marks: 4, qtype: '', count: 4, items: [] });
    render();
  };
  // match (जोड्या लावा) questions are edited as: stem line + one "left | right" line per pair; the print view draws the table
  function matchText(q) { return Array.isArray(q.pairs) && q.pairs.length ? q.text + '\n' + q.pairs.map(p => `${p[0]} | ${p[1]}`).join('\n') : q.text; }
  function bookItem(q) { return { bq_id: q.bq_id, text: matchText(q), page_image: q.page_image, show_image: false, needs_figure: !!+q.needs_figure, page: q.page, qtype: q.qtype }; }
  async function fill(s, need) {
    if (need <= 0) return;
    const chs = selectedChapters();
    if (!chs.length) return alert('Tick at least one chapter');
    const rows = await api('book_random', { body: { chapter_ids: chs, qtype: s.qtype, count: need, exclude: usedIds() } });
    for (const q of rows) s.items.push(bookItem(q));
    if (rows.length < need) s.warn = `Only ${rows.length} of ${need} found in the selected chapters`;
  }
  $('#autoFill').onclick = async () => {
    if (!state.sections.length) return alert('Load a layout or add a section first');
    for (const s of state.sections) { s.items = s.items.filter(i => i.bq_id || i.text.trim()); await fill(s, s.count - s.items.length); }
    render();
  };

  function render() {
    const box = $('#sections');
    $('#emptyHint').style.display = state.sections.length ? 'none' : '';
    const total = state.sections.reduce((a, s) => a + (+s.marks || 0), 0);
    $('#marksInfo').textContent = state.sections.length ? `— ${state.sections.length} sections, ${total} marks` : '';
    box.innerHTML = state.sections.map((s, si) => `
      <div class="card shadow-sm mb-3 section" data-i="${si}">
        <div class="card-header py-2">
          <div class="row g-2 align-items-center">
            <div class="col-auto"><div class="input-group input-group-sm"><span class="input-group-text">प्र.</span><input class="form-control" style="width:48px" data-f="q_no" value="${esc(s.q_no)}"><input class="form-control" style="width:48px" data-f="sub" value="${esc(s.sub)}" placeholder="अ"></div></div>
            <div class="col"><input class="form-control form-control-sm fw-semibold" data-f="instruction" value="${esc(s.instruction)}" placeholder="Instruction, e.g. खालील प्रश्नांची एका वाक्यात उत्तरे लिहा."></div>
            <div class="col-auto"><div class="input-group input-group-sm"><input type="number" step="0.5" class="form-control" style="width:64px" data-f="marks" value="${esc(s.marks)}"><span class="input-group-text">गुण</span></div></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-danger" data-act="del-sec" title="Remove section"><i class="bi bi-trash"></i></button></div>
          </div>
          <div class="row g-2 align-items-center mt-1">
            <div class="col-auto"><select class="form-select form-select-sm" data-f="qtype">${Object.entries(QTYPES).map(([k, v]) => `<option value="${k}" ${k === s.qtype ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
            <div class="col-auto"><div class="input-group input-group-sm"><input type="number" class="form-control" style="width:60px" data-f="count" value="${esc(s.count)}"><span class="input-group-text">questions</span></div></div>
            <div class="col-auto"><button class="btn btn-sm btn-success" data-act="fill"><i class="bi bi-magic"></i> Fill</button> <button class="btn btn-sm btn-outline-secondary" data-act="browse"><i class="bi bi-search"></i> Browse</button> <button class="btn btn-sm btn-outline-secondary" data-act="add-item"><i class="bi bi-plus"></i> Own question</button></div>
            ${s.warn ? `<div class="col-auto small text-warning">${esc(s.warn)}</div>` : ''}
          </div>
        </div>
        <ol class="list-group list-group-numbered list-group-flush">
          ${s.items.map((it, ii) => `
          <li class="list-group-item d-flex gap-2 align-items-start" data-ii="${ii}">
            <textarea class="form-control form-control-sm flex-grow-1" rows="1" data-f="text">${esc(it.text)}</textarea>
            <div class="text-nowrap small">
              ${it.needs_figure ? `<label class="me-1" title="Question refers to a picture/figure — print the textbook page image below it"><input type="checkbox" data-f="show_image" ${it.show_image ? 'checked' : ''}> <i class="bi bi-image"></i></label>` : ''}
              ${it.page ? `<span class="text-muted me-1" title="Textbook page">p.${it.page}</span>` : ''}
              <button class="btn btn-sm btn-outline-secondary py-0" data-act="swap" title="Replace with another question"><i class="bi bi-shuffle"></i></button>
              <button class="btn btn-sm btn-outline-danger py-0" data-act="del-item"><i class="bi bi-x"></i></button>
            </div>
          </li>`).join('')}
        </ol>
      </div>`).join('');
    $$('textarea[data-f="text"]', box).forEach(t => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 2 + 'px'; });
  }

  $('#sections').addEventListener('input', e => {
    const sec = state.sections[+e.target.closest('.section').dataset.i];
    const li = e.target.closest('li');
    const f = e.target.dataset.f;
    if (!f) return;
    if (li) {
      const it = sec.items[+li.dataset.ii];
      it[f] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
      if (f === 'text' && it.bq_id && it.text !== e.target.value) it.edited = true;
      if (f === 'text') { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 2 + 'px'; }
    } else {
      sec[f] = ['marks', 'count', 'q_no'].includes(f) ? +e.target.value : e.target.value;
      if (f === 'marks') $('#marksInfo').textContent = `— ${state.sections.length} sections, ${state.sections.reduce((a, s) => a + (+s.marks || 0), 0)} marks`;
    }
  });
  $('#sections').addEventListener('click', async e => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const si = +btn.closest('.section').dataset.i, s = state.sections[si];
    const li = btn.closest('li');
    switch (btn.dataset.act) {
      case 'del-sec': state.sections.splice(si, 1); break;
      case 'fill': s.warn = ''; await fill(s, s.count - s.items.length); break;
      case 'add-item': s.items.push({ bq_id: null, text: '' }); break;
      case 'del-item': s.items.splice(+li.dataset.ii, 1); break;
      case 'swap': {
        const rows = await api('book_random', { body: { chapter_ids: selectedChapters(), qtype: s.qtype, count: 1, exclude: usedIds() } });
        if (!rows.length) return alert('No other question available in the selected chapters');
        const q = rows[0];
        s.items[+li.dataset.ii] = bookItem(q);
        break;
      }
      case 'browse': openBrowse(s); return;
    }
    render();
  });

  /* ---------- browse modal ---------- */
  const modal = new bootstrap.Modal($('#browseModal'));
  let browseSec = null, browsePage = 0;
  $('#browseType').innerHTML = Object.entries(QTYPES).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
  function openBrowse(s) { browseSec = s; browsePage = 0; $('#browseType').value = s.qtype; $('#browseSearch').value = ''; modal.show(); browse(); }
  async function browse() {
    const r = await api('book_browse', { body: { chapter_ids: selectedChapters(), standard: stdSel.value, qtype: $('#browseType').value, search: $('#browseSearch').value, page: browsePage } });
    const used = usedIds();
    $('#browseInfo').textContent = `${r.total} questions — page ${browsePage + 1} of ${Math.max(1, Math.ceil(r.total / r.size))}`;
    $('#browseList').innerHTML = r.items.length ? r.items.map(q => `
      <div class="border rounded p-2 mb-2 d-flex gap-2 align-items-start ${used.includes(q.bq_id) ? 'bg-light' : ''}">
        <div class="flex-grow-1"><div style="white-space:pre-wrap">${esc(matchText(q))}</div>
          <div class="small text-muted">${esc(q.chapter_title || '')} · p.${q.page} · ${QTYPES[q.qtype] || q.qtype}${q.instruction ? ' · ' + esc(q.instruction) : ''}${+q.needs_figure ? ' · <i class="bi bi-image"></i> figure' : ''}</div></div>
        <button class="btn btn-sm ${used.includes(q.bq_id) ? 'btn-secondary disabled' : 'btn-primary'}" data-add="${q.bq_id}">${used.includes(q.bq_id) ? 'Added' : 'Add'}</button>
      </div>`).join('') : '<div class="text-muted">No questions match.</div>';
    $$('[data-add]', $('#browseList')).forEach(b => b.onclick = () => {
      const q = r.items.find(x => x.bq_id == b.dataset.add);
      browseSec.items.push(bookItem(q));
      b.className = 'btn btn-sm btn-secondary disabled'; b.textContent = 'Added';
      render();
    });
  }
  $('#browseGo').onclick = () => { browsePage = 0; browse(); };
  $('#browseSearch').onkeydown = e => { if (e.key === 'Enter') { browsePage = 0; browse(); } };
  $('#browseType').onchange = () => { browsePage = 0; browse(); };
  $('#browsePrev').onclick = () => { if (browsePage > 0) { browsePage--; browse(); } };
  $('#browseNext').onclick = () => { browsePage++; browse(); };

  /* ---------- save ---------- */
  $('#savePaper').onclick = async () => {
    const s = curSubj();
    const body = {
      paper_id: state.paperId, exam_type: $('#examType').value, test_no: +$('#testNo').value, standard: +stdSel.value,
      subject: s?.subject || '', medium: s?.medium || '', title: $('#title').value, std_label: $('#stdLabel').value,
      exam_name: $('#examType').value === 'sankalit' ? 'संकलित मूल्यमापन' : 'आकारिक मूल्यमापन',
      duration: $('#duration').value, exam_date: $('#examDate').value, total_marks: $('#totalMarks').value,
      instructions: $('#instructions').value, student_fields: $('#studentFields').checked,
      chapter_ids: selectedChapters(),
      sections: state.sections.map(x => ({ ...x, chapter_ids: selectedChapters(), items: x.items.map(i => ({ bq_id: i.bq_id, text: i.text, page_image: i.page_image, show_image: i.show_image })) })),
    };
    const r = await api('save_assessment', { body });
    const msg = $('#saveMsg');
    if (r.status !== 'success') { msg.className = 'small mt-2 text-danger'; msg.textContent = r.message || 'Save failed'; return; }
    state.paperId = r.paper_id;
    msg.className = 'small mt-2 text-success';
    msg.innerHTML = `Saved. <a href="paper_view.php?id=${r.paper_id}" target="_blank">Open / print paper</a>`;
    window.open('paper_view.php?id=' + r.paper_id, '_blank');
  };

  /* ---------- edit mode ---------- */
  if (editData) {
    const m = editData.meta || {};
    state.paperId = editData.paper_id;
    $('#examType').value = m.exam_type || 'sankalit';
    $('#testNo').value = m.test_no || 1;
    if (tree.some(s => s.standard == m.standard)) stdSel.value = m.standard;
    fillSubjects();
    const subj = (curStd()?.subjects || []).find(s => s.subject === m.subject && s.medium === m.medium);
    if (subj) { subjSel.value = subj.name; fillChapters((m.sections || []).flatMap(x => x.chapter_ids || [])); }
    ['title', 'stdLabel', 'duration'].forEach(id => $('#' + id).dataset.touched = '1');
    $('#title').value = editData.title; $('#stdLabel').value = editData.std_label || ''; $('#duration').value = editData.duration || '';
    $('#examDate').value = editData.exam_date || ''; $('#totalMarks').value = editData.total_marks || ''; $('#instructions').value = editData.instructions || '';
    $('#studentFields').checked = m.student_fields !== false;
    state.sections = (m.sections || []).map(x => ({ ...x, count: x.items.length, items: x.items.map(i => ({ ...i, needs_figure: !!i.page_image })) }));
    render();
  } else {
    fillSubjects();
  }
})();
