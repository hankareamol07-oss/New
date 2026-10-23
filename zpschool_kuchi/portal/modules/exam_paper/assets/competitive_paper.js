(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const api = (action, opts = {}) => fetch('api.php?action=' + action + (opts.query || ''), {
    method: opts.body ? 'POST' : 'GET',
    headers: opts.body ? { 'Content-Type': 'application/json' } : {},
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  }).then(r => r.json());

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const markup = s => {
    const str = String(s ?? '').trim();
    if (/<(?:math|div|span|p|b|i|u|strong|em|img|table|thead|tbody|tr|td|th|br)\b/i.test(str)) {
      return str.replace(/\t+/g, '&emsp;&emsp;').replace(/ {3,}/g, '&emsp;&emsp;').replace(/\n/g, '<br>');
    }
    return esc(str).replace(/\t+/g, '&emsp;&emsp;').replace(/ {3,}/g, '&emsp;&emsp;').replace(/\n/g, '<br>');
  };

  const app = $('#epApp');
  const patterns = JSON.parse(app.dataset.patterns);
  const tree = JSON.parse(app.dataset.tree);          // [{standard_id,name,medium,subjects:[{subject_id,name,mcq,chapters:[{chapter_id,name,mcq}]}]}]
  const editData = app.dataset.edit ? JSON.parse(app.dataset.edit) : null;

  const state = { paperId: null, sections: [] };
  // section: {title, subtitle, count, marks_per_q, standard_id, subject_id, chapter_ids:[], questions:[]}

  const usedIds = () => state.sections.flatMap(s => s.questions.map(q => q.question_id));
  const stdOf = id => tree.find(s => s.standard_id == id);
  const subjOf = (sid, subId) => (stdOf(sid)?.subjects || []).find(s => s.subject_id == subId);
  const chapterOwner = chId => {
    for (const st of tree) for (const su of st.subjects) if (su.chapters.some(c => c.chapter_id == chId)) return { st, su };
    return null;
  };

  // Guess a source class + subject from the pattern hints (e.g. std "5" + subject containing "गणित").
  function guessSource(hintStd, hintSubj) {
    const stds = tree.filter(s => !hintStd.length || hintStd.some(h => String(s.standard_id) === String(h) || s.name.trim() === h || s.name.includes(h)));
    for (const h of hintSubj) {
      for (const st of stds) {
        const su = st.subjects.find(x => x.name.toLowerCase().includes(h.toLowerCase()));
        if (su) return { standard_id: st.standard_id, subject_id: su.subject_id };
      }
    }
    const st = stds[0] || tree[0];
    return { standard_id: st?.standard_id || '', subject_id: st?.subjects[0]?.subject_id || '' };
  }

  function newSection(def, pattern) {
    const src = guessSource(def.source_std || [], def.source_subject || []);
    const su = subjOf(src.standard_id, src.subject_id);
    return {
      title: def.title, subtitle: def.subtitle || '', count: def.count, marks_per_q: pattern.marks_per_q,
      standard_id: src.standard_id, subject_id: src.subject_id,
      chapter_ids: su ? su.chapters.map(c => c.chapter_id) : [], questions: [],
    };
  }

  // ---------- Single Topic Paper Generator ----------
  const singleStd = $('#singleStd');
  const singleSub = $('#singleSub');
  const singleChapter = $('#singleChapter');
  const singleCount = $('#singleCount');
  const singleMarksPerQ = $('#singleMarksPerQ');
  const btnGenerateTopic = $('#btnGenerateTopic');

  if (singleStd) {
    const compPriority = [72, 71, 46];
    const sortedTree = [...tree].sort((a, b) => {
      const aIdx = compPriority.indexOf(a.standard_id);
      const bIdx = compPriority.indexOf(b.standard_id);
      if (aIdx !== -1 && bIdx !== -1) return aIdx - bIdx;
      if (aIdx !== -1) return -1;
      if (bIdx !== -1) return 1;
      return a.name.localeCompare(b.name, 'mr');
    });

    singleStd.innerHTML = sortedTree.map(st => `<option value="${st.standard_id}">${esc(st.name)} (${esc(st.medium)})</option>`).join('');

    function updateSingleSubjects() {
      const sid = +singleStd.value;
      const st = stdOf(sid);
      const subs = st?.subjects || [];
      singleSub.innerHTML = subs.map(su => `<option value="${su.subject_id}">${esc(su.name)} (${su.mcq} प्रश्न)</option>`).join('');
      if (sid === 72 || sid === 71) {
        singleMarksPerQ.value = 2;
      } else if (sid === 46) {
        singleMarksPerQ.value = 1;
      } else {
        singleMarksPerQ.value = 1;
      }
      updateSingleChapters();
    }

    function updateSingleChapters() {
      const sid = +singleStd.value;
      const subId = +singleSub.value;
      const su = subjOf(sid, subId);
      const chs = su?.chapters || [];
      singleChapter.innerHTML = chs.map(c => `<option value="${c.chapter_id}" data-mcq="${c.mcq}">${esc(c.name)} (${c.mcq} प्रश्न)</option>`).join('');
      updateTopicHint();
    }

    function updateTopicHint() {
      const opt = singleChapter.selectedOptions[0];
      const mcq = opt ? +opt.dataset.mcq || 0 : 0;
      const hint = $('#singleTopicHint');
      if (hint) {
        hint.innerHTML = mcq > 0 
          ? `<i class="bi bi-check-circle text-success"></i> या घटकात एकूण <b>${mcq}</b> वस्तुनिष्ठ प्रश्न उपलब्ध आहेत.`
          : `<i class="bi bi-exclamation-triangle text-warning"></i> या घटकात प्रश्न उपलब्ध नाहीत.`;
      }
    }

    singleStd.addEventListener('change', updateSingleSubjects);
    singleSub.addEventListener('change', updateSingleChapters);
    singleChapter.addEventListener('change', updateTopicHint);
    updateSingleSubjects();

    $$('.btn-qcnt').forEach(btn => {
      btn.onclick = () => {
        $$('.btn-qcnt').forEach(b => b.classList.remove('btn-outline-primary', 'active').add('btn-outline-secondary'));
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-outline-primary', 'active');
        singleCount.value = btn.dataset.cnt;
      };
    });

    btnGenerateTopic.onclick = async () => {
      const sid = +singleStd.value;
      const subId = +singleSub.value;
      const chId = +singleChapter.value;
      const count = Math.max(1, +singleCount.value || 20);
      const marksPerQ = Math.max(0.25, +singleMarksPerQ.value || 1);

      if (!chId) return alert('कृपया एक घटक निवडा (Please select a topic)');
      const st = stdOf(sid);
      const su = subjOf(sid, subId);
      const ch = su?.chapters.find(c => c.chapter_id === chId);
      if (!ch) return alert('घटक आढळला नाही');

      if (ch.mcq < count && ch.mcq > 0) {
        if (!confirm(`या घटकात फक्त ${ch.mcq} प्रश्न उपलब्ध आहेत (तुम्ही ${count} विचारले आहेत). उपलब्ध सर्व ${ch.mcq} प्रश्न घेऊन पेपर तयार करायचा का?`)) {
          return;
        }
      }

      btnGenerateTopic.disabled = true;
      btnGenerateTopic.innerHTML = `<span class="spinner-border spinner-border-sm"></span> प्रश्न लोड होत आहेत...`;

      const cleanTitle = ch.name.replace(/^[a-zA-Z0-9\.\-\)\s]*\s*[-–]\s*/, '').replace(/^\d+[\.\)]\s*/, '');
      $('#examName').value = `${st.name} - ${su.name}`;
      $('#title').value = `घटक चाचणी: ${cleanTitle}`;
      $('#stdLabel').value = st.name;
      $('#duration').value = count <= 15 ? '20 Min' : (count <= 25 ? '30 Min' : (count <= 40 ? '45 Min' : '1:00 Hr'));
      $('#totalMarks').value = (Math.min(count, ch.mcq || count) * marksPerQ).toFixed(2).replace(/\.?0+$/, '');
      $('#instructions').value = `१. सर्व प्रश्न सोडविणे आवश्यक आहे.\n२. प्रत्येक प्रश्नास योग्य पर्याय निवडा.\n३. प्रत्येक अचूक उत्तरास ${marksPerQ} गुण दिले जातील.`;

      state.sections = [{
        title: `घटक: ${cleanTitle}`,
        subtitle: `सराव प्रश्नसंच (${ch.name})`,
        count: Math.min(count, ch.mcq || count),
        marks_per_q: marksPerQ,
        standard_id: sid,
        subject_id: subId,
        chapter_ids: [chId],
        questions: []
      }];

      await fill(state.sections[0], state.sections[0].count);
      render();

      btnGenerateTopic.disabled = false;
      btnGenerateTopic.innerHTML = `<i class="bi bi-lightning-charge-fill"></i> घटक चाचणी तयार करा (Generate)`;

      const secEl = $('#sections');
      if (secEl) secEl.scrollIntoView({ behavior: 'smooth' });
    };
  }

  $('#pattern').addEventListener('change', e => {
    const p = patterns[e.target.value];
    if (!p) return;
    if (state.sections.length && !confirm('Replace current sections with this pattern?')) { e.target.value = ''; return; }
    state.sections = p.sections.map(def => newSection(def, p));
    $('#examName').value = p.name.split(' - ')[0];
    if (!$('#title').value) $('#title').value = p.name.split(' - ').slice(1).join(' - ') || 'Practice Paper';
    $('#stdLabel').value = p.std_label;
    $('#duration').value = p.duration;
    $('#totalMarks').value = p.total_marks || '';
    render();
  });

  $('#addSection').onclick = e => {
    e.preventDefault();
    const perQ = state.sections[0]?.marks_per_q || 1;
    state.sections.push(newSection({ title: `Section ${state.sections.length + 1}`, subtitle: '', count: 10, source_std: [], source_subject: [] }, { marks_per_q: perQ }));
    render();
  };

  $('#autoFill').onclick = async () => {
    if (!state.sections.length) return alert('Choose an exam pattern or add a section first');
    for (const s of state.sections) await fill(s, Math.max(0, s.count - s.questions.length));
    render();
  };

  async function fill(s, need) {
    if (need <= 0) return;
    if (!s.chapter_ids.length) return alert(`"${s.title}": select at least one topic`);
    const got = await api('random', { body: { chapter_ids: s.chapter_ids, type: 'mcq', qno: '', count: need, exclude: usedIds() } });
    if (got.length < need) alert(`"${s.title}": only ${got.length} more MCQ available in the selected topics (wanted ${need}). Add more topics or another class/subject.`);
    s.questions.push(...got);
  }

  function questionHtml(q, idx) {
    return `
      <div class="ep-q border rounded p-2 mb-2 bg-white" data-qi="${idx}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="flex-grow-1">
            <span class="fw-semibold">${idx + 1}.</span> ${q.passage ? `<div class="fst-italic small mb-1">${markup(q.passage)}</div>` : ''}${markup(q.markup)}
            ${q.image_url ? `<div><img src="${q.image_url}" class="ep-qimg mt-1"></div>` : ''}
            ${q.markup2 ? `<div>${markup(q.markup2)}</div>` : ''}
            ${q.image2_url ? `<div><img src="${q.image2_url}" class="ep-qimg mt-1"></div>` : ''}
            ${q.markup3 ? `<div>${markup(q.markup3)}</div>` : ''}
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

  function sourceHtml(s) {
    const stdOpts = tree.map(st => `<option value="${st.standard_id}" ${st.standard_id == s.standard_id ? 'selected' : ''}>${esc(st.name)} (${esc(st.medium)})</option>`).join('');
    const subjOpts = (stdOf(s.standard_id)?.subjects || []).map(su => `<option value="${su.subject_id}" ${su.subject_id == s.subject_id ? 'selected' : ''}>${esc(su.name)} (${su.mcq} MCQ)</option>`).join('');
    const chs = subjOf(s.standard_id, s.subject_id)?.chapters || [];
    const isSingleCh = s.chapter_ids.length === 1;
    return `
      <div class="row g-2 mb-2">
        <div class="col-md-4"><select class="form-select form-select-sm" data-f="standard_id"><option value="">-- वर्ग / परीक्षा --</option>${stdOpts}</select></div>
        <div class="col-md-8"><select class="form-select form-select-sm" data-f="subject_id"><option value="">-- विषय --</option>${subjOpts}</select></div>
      </div>
      <div class="mb-2 bg-light p-2 rounded border">
        <label class="form-label small fw-semibold text-primary mb-1"><i class="bi bi-bullseye"></i> एकाच घटकावर प्रश्न विचारा (Single Topic):</label>
        <select class="form-select form-select-sm" data-act="pickSingle">
          <option value="">-- सर्व किंवा एकाधिक घटक निवडा (Multiple Topics) --</option>
          ${chs.map(c => `<option value="${c.chapter_id}" ${isSingleCh && s.chapter_ids[0] === c.chapter_id ? 'selected' : ''}>🎯 ${esc(c.name)} (${c.mcq} प्रश्न उपलब्ध)</option>`).join('')}
        </select>
      </div>
      <details class="mb-2" ${isSingleCh ? '' : (s.questions.length ? '' : 'open')}>
        <summary class="small">किंवा घटक चेकबॉक्स निवडा: <b>${s.chapter_ids.length}</b> / ${chs.length} selected
          &nbsp; <a href="#" data-act="chAll">all</a> / <a href="#" data-act="chNone">none</a></summary>
        <div class="border rounded p-2 bg-white ep-chapters mt-1">
          ${chs.map(c => `<div class="form-check"><input class="form-check-input" type="checkbox" data-ch="${c.chapter_id}" id="s${s._i}c${c.chapter_id}" ${s.chapter_ids.includes(c.chapter_id) ? 'checked' : ''}>
            <label class="form-check-label small" for="s${s._i}c${c.chapter_id}">${esc(c.name)} <span class="text-muted">(${c.mcq})</span></label></div>`).join('') || '<span class="text-muted small">No topics</span>'}
        </div>
      </details>`;
  }

  function render() {
    $('#emptyHint').style.display = state.sections.length ? 'none' : '';
    let qStart = 1;
    $('#sections').innerHTML = state.sections.map((s, si) => {
      s._i = si;
      const from = qStart, to = qStart + Math.max(s.count, s.questions.length) - 1;
      qStart = to + 1;
      return `
      <div class="card shadow-sm mb-3 ep-section" data-si="${si}">
        <div class="card-header py-2">
          <div class="row g-2 align-items-center">
            <div class="col-md-3"><input class="form-control form-control-sm" data-f="title" value="${esc(s.title)}" placeholder="Section title"></div>
            <div class="col-md-4"><input class="form-control form-control-sm" data-f="subtitle" value="${esc(s.subtitle)}" placeholder="Sub-title (e.g. मानसिक क्षमता परीक्षण)"></div>
            <div class="col-md-2"><div class="input-group input-group-sm"><span class="input-group-text">Q</span><input type="number" class="form-control" data-f="count" value="${s.count}" title="No. of questions"></div></div>
            <div class="col-md-2"><div class="input-group input-group-sm"><span class="input-group-text">Marks/Q</span><input type="number" step="0.25" class="form-control" data-f="marks_per_q" value="${s.marks_per_q}"></div></div>
            <div class="col-md-1 text-end">
              <button class="btn btn-sm btn-outline-success" data-act="fill" title="Fill section"><i class="bi bi-magic"></i></button>
              <button class="btn btn-sm btn-outline-danger" data-act="delsec" title="Delete section"><i class="bi bi-trash"></i></button>
            </div>
          </div>
          <div class="small text-muted mt-1">Q. ${from} to ${to} &middot; ${s.questions.length}/${s.count} filled &middot; ${(s.marks_per_q * s.count).toFixed(2).replace(/\.?0+$/, '')} marks</div>
        </div>
        <div class="card-body py-2">
          ${sourceHtml(s)}
          ${s.questions.map((q, qi) => questionHtml(q, qi)).join('') || '<div class="text-muted small">No questions yet - click the magic wand to auto-fill.</div>'}
          <button class="btn btn-sm btn-outline-primary" data-act="addq"><i class="bi bi-plus"></i> Add question</button>
        </div>
      </div>`;
    }).join('');
  }

  function handleSectionChange(e) {
    const secEl = e.target.closest('.ep-section');
    if (!secEl) return;
    const s = state.sections[+secEl.dataset.si];
    if (e.target.dataset.act === 'pickSingle') {
      const val = e.target.value;
      if (val) {
        s.chapter_ids = [+val];
        const ch = (subjOf(s.standard_id, s.subject_id)?.chapters || []).find(c => c.chapter_id == val);
        if (ch) {
          s.subtitle = ch.name;
        }
      } else {
        s.chapter_ids = (subjOf(s.standard_id, s.subject_id)?.chapters || []).map(c => c.chapter_id);
      }
      render();
      return;
    }
    if (e.target.dataset.ch) {
      const id = +e.target.dataset.ch;
      s.chapter_ids = e.target.checked ? [...new Set([...s.chapter_ids, id])] : s.chapter_ids.filter(x => x !== id);
      e.target.closest('details').querySelector('summary b').textContent = s.chapter_ids.length;
      return;
    }
    const f = e.target.dataset.f;
    if (!f) return;
    if (f === 'standard_id') {
      s.standard_id = +e.target.value;
      s.subject_id = stdOf(s.standard_id)?.subjects[0]?.subject_id || '';
      s.chapter_ids = (subjOf(s.standard_id, s.subject_id)?.chapters || []).map(c => c.chapter_id);
      render();
    } else if (f === 'subject_id') {
      s.subject_id = +e.target.value;
      s.chapter_ids = (subjOf(s.standard_id, s.subject_id)?.chapters || []).map(c => c.chapter_id);
      render();
    } else {
      s[f] = (f === 'count' || f === 'marks_per_q') ? +e.target.value : e.target.value;
    }
  }
  $('#sections').addEventListener('input', handleSectionChange);
  $('#sections').addEventListener('change', handleSectionChange);

  $('#sections').addEventListener('click', async e => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const si = +btn.closest('.ep-section').dataset.si;
    const s = state.sections[si];
    const qEl = btn.closest('.ep-q');
    const qi = qEl ? +qEl.dataset.qi : -1;
    switch (btn.dataset.act) {
      case 'chAll': e.preventDefault(); s.chapter_ids = (subjOf(s.standard_id, s.subject_id)?.chapters || []).map(c => c.chapter_id); break;
      case 'chNone': e.preventDefault(); s.chapter_ids = []; break;
      case 'delsec': state.sections.splice(si, 1); break;
      case 'remove': s.questions.splice(qi, 1); break;
      case 'fill': await fill(s, Math.max(0, s.count - s.questions.length)); break;
      case 'addq': await fill(s, 1); break;
      case 'swap': {
        const got = await api('random', { body: { chapter_ids: s.chapter_ids, type: 'mcq', qno: '', count: 1, exclude: usedIds() } });
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
    const res = await api('browse', { body: { chapter_ids: s.chapter_ids, type: 'mcq', search: $('#browseSearch').value, page: browse.page } });
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

  // ---------- save & quiz ----------
  async function doSavePaper(isQuiz = false) {
    const msg = $('#saveMsg');
    msg.textContent = isQuiz ? 'Saving & creating online quiz...' : 'Saving...';
    const fmt = $('#paperFormat')?.value || 'standard';
    const res = await api('save_competitive', { body: {
      paper_id: state.paperId, exam_name: $('#examName').value, std_label: $('#stdLabel').value,
      title: $('#title').value, exam_date: $('#examDate').value, duration: $('#duration').value,
      total_marks: $('#totalMarks').value, instructions: $('#instructions').value,
      format: fmt,
      sections: state.sections.map(s => ({ title: s.title, subtitle: s.subtitle, count: s.count, marks_per_q: s.marks_per_q, chapter_ids: s.chapter_ids, question_ids: s.questions.map(q => q.question_id) })),
    } });
    if (res.status === 'success') {
      state.paperId = res.paper_id;
      let quizInfo = '';
      if (isQuiz) {
        const qRes = await api('create_quiz_from_paper', { body: { paper_id: res.paper_id } });
        if (qRes.status === 'success') {
          quizInfo = `<div class="mt-2 p-2 bg-light border border-success rounded">
            <div class="fw-bold text-success"><i class="bi bi-phone-fill"></i> ऑनलाईन क्विझ लिंक तयार झाली!</div>
            <div class="small">विद्यार्थी कोड: <b class="badge bg-primary fs-6">${esc(qRes.code)}</b></div>
            <div class="mt-1"><a href="${esc(qRes.quiz_url)}" target="_blank" class="btn btn-sm btn-success fw-bold"><i class="bi bi-play-circle-fill"></i> थेट क्विझ उघडा (Open Quiz)</a></div>
          </div>`;
          window.open(qRes.quiz_url, '_blank');
        }
      }
      msg.innerHTML = `<div class="alert alert-success p-2 mt-2 mb-0">
        <div class="fw-bold mb-1"><i class="bi bi-check-circle-fill"></i> स्पर्धा परीक्षा पेपर सेव्ह झाला!</div>
        <div class="d-flex flex-wrap gap-2 mt-1">
          <a href="paper_view.php?id=${res.paper_id}" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-printer"></i> प्रश्नपत्रिका प्रिंट</a>
          <a href="omr_view.php?id=${res.paper_id}" target="_blank" class="btn btn-sm btn-danger"><i class="bi bi-grid-3x3-gap-fill"></i> OMR Sheet</a>
          <a href="omr_view.php?id=${res.paper_id}&key=1" target="_blank" class="btn btn-sm btn-warning text-dark"><i class="bi bi-check2-circle"></i> OMR उत्तरतालिका</a>
        </div>
        ${quizInfo}
      </div>`;
      if (!isQuiz) {
        window.open('paper_view.php?id=' + res.paper_id, '_blank');
      }
    } else {
      msg.innerHTML = `<span class="text-danger">${esc(res.message)}</span>`;
    }
  }

  $('#savePaper').onclick = () => doSavePaper(false);
  const btnQuiz = $('#saveAndQuiz');
  if (btnQuiz) {
    btnQuiz.onclick = () => doSavePaper(true);
  }

  // ---------- edit mode ----------
  if (editData) {
    state.paperId = editData.paper_id;
    const byId = Object.fromEntries(editData.questions.map(q => [q.question_id, q]));
    state.sections = editData.sections.map(s => {
      const owner = chapterOwner(s.chapter_ids?.[0] || s.question_ids[0] && byId[s.question_ids[0]]?.chapter_id);
      return {
        title: s.title, subtitle: s.subtitle || '', count: s.question_ids.length, marks_per_q: +s.marks_per_q || 1,
        standard_id: owner?.st.standard_id || '', subject_id: owner?.su.subject_id || '',
        chapter_ids: s.chapter_ids || [], questions: s.question_ids.map(id => byId[id]).filter(Boolean),
      };
    });
    $('#examName').value = editData.exam_name || '';
    $('#title').value = editData.title || '';
    $('#stdLabel').value = editData.std_label || '';
    $('#examDate').value = editData.exam_date || '';
    $('#duration').value = editData.duration || '';
    $('#totalMarks').value = editData.total_marks || '';
    $('#instructions').value = editData.instructions || '';
    render();
  } else {
    const urlParams = new URLSearchParams(window.location.search);
    const patternParam = urlParams.get('pattern');
    if (patternParam && patterns[patternParam]) {
      const tabEl = document.getElementById('tabPattern');
      if (tabEl) {
        if (window.bootstrap?.Tab) {
          new bootstrap.Tab(tabEl).show();
        } else {
          tabEl.click();
        }
      }
      const patSel = $('#pattern');
      if (patSel) {
        patSel.value = patternParam;
        const p = patterns[patternParam];
        state.sections = p.sections.map(def => newSection(def, p));
        $('#examName').value = p.name.split(' - ')[0];
        if (!$('#title').value) $('#title').value = p.name.split(' - ').slice(1).join(' - ') || 'Practice Paper';
        $('#stdLabel').value = p.std_label;
        $('#duration').value = p.duration;
        $('#totalMarks').value = p.total_marks || '';
        render();
      }
    }
  }
})();
