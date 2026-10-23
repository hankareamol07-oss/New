/* TechEdu Bot – chat frontend */
(function () {
  const messages = document.getElementById('messages');
  const form     = document.getElementById('chatForm');
  const input    = document.getElementById('msgInput');
  const sendBtn  = document.getElementById('sendBtn');
  const typing   = document.getElementById('typing');
  const status   = document.getElementById('statusText');
  const chips    = document.getElementById('chips');

  function now() {
    return new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
  }

  function formatText(text) {
    let t = text
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/\*([^*\n]+)\*/g, '<b>$1</b>')
      .replace(/\n/g, '<br>');
    return t;
  }

  function addMsg(text, who) {
    const div = document.createElement('div');
    div.className = 'msg ' + who;
    div.setAttribute('data-time', now());
    const bub = document.createElement('div');
    bub.className = 'bubble';
    bub.innerHTML = formatText(text);
    div.appendChild(bub);
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  function renderChips(chipList) {
    chips.innerHTML = '';
    if (!chipList || !chipList.length) return;
    chipList.forEach(label => {
      const btn = document.createElement('button');
      const val  = label.split(' ')[0];
      btn.className = 'chip' + (val === 'submit' ? ' chip-submit' : '');
      btn.dataset.value = val;
      btn.textContent = label;
      chips.appendChild(btn);
    });
  }

  async function send(msg) {
    if (!msg.trim()) return;
    addMsg(msg, 'user');
    input.value = '';
    sendBtn.disabled = true;
    renderChips([]);
    typing.classList.remove('hidden');
    status.textContent = 'Typing... ✍️';
    messages.scrollTop = messages.scrollHeight;

    try {
      const fd = new FormData();
      fd.append('msg', msg);
      const res  = await fetch('/', { method: 'POST', body: fd });
      const data = await res.json();
      typing.classList.add('hidden');
      status.textContent = 'Online ✅';
      if (data.reply) addMsg(data.reply, 'bot');
      renderChips(data.chips || []);
    } catch (e) {
      typing.classList.add('hidden');
      status.textContent = 'Online ✅';
      addMsg('माफ करा, काहीतरी चूक झाली. पुन्हा प्रयत्न करा.', 'bot');
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  }

  form.addEventListener('submit', e => { e.preventDefault(); send(input.value); });

  // Sidebar feature cards + report button
  document.querySelectorAll('.feat-card, .report-btn').forEach(card => {
    card.addEventListener('click', () => send(card.dataset.send));
  });

  chips.addEventListener('click', e => {
    const btn = e.target.closest('.chip');
    if (btn) send(btn.dataset.value);
  });

  // Auto-greeting on load
  async function initGreeting() {
    const fd = new FormData();
    fd.append('msg', 'hi');
    try {
      const res  = await fetch('/', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.reply) addMsg(data.reply, 'bot');
      renderChips(data.chips || []);
    } catch (e) { /* silent */ }
  }
  initGreeting();

  messages.scrollTop = messages.scrollHeight;
})();
