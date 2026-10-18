/**
 * Al-Falah Marketing — Hybrid AI Navigation Assistant
 * Priority 1: local site knowledge (via API)
 * Priority 2: live web search fallback (DuckDuckGo / Wikipedia / optional LLM)
 */
(function () {
  if (window.__alfalahAI) return;
  window.__alfalahAI = true;

  function apiBase() {
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
      var s = scripts[i].src || '';
      if (s.indexOf('ai-assistant.js') !== -1) {
        return s.replace(/ai-assistant\.js.*$/, '') + 'api/ai-assistant.php';
      }
    }
    // fallback relative to page depth
    var path = location.pathname || '';
    if (/\/(blog|services)\//.test(path)) return '../api/ai-assistant.php';
    return 'api/ai-assistant.php';
  }

  var API = apiBase();
  var WA = 'https://wa.me/224611351302?text=' + encodeURIComponent(
    'Hello Al-Falah, I would like a confidential institutional consultation.'
  );

  var UI = {
    en: {
      title: 'Al-Falah Guide',
      subtitle: 'Knowledge Base · Live Web',
      status: 'Agent online · Knowledge Base & Live Web',
      placeholder: 'Ask about Al-Falah or anything else…',
      greeting: 'Hello. I answer from Al-Falah’s knowledge base first (services, institutions, contact). For broader technical or market questions, I can look up live web information while keeping a professional institutional tone.',
      searching: 'Looking up live sources…',
      suggestions: ['Institutional platforms', 'Book a consultation', 'What is digital governance?', 'Our services'],
      err: 'Connection issue. Please try again or use <a href="contact.html">Contact</a> / <a href="' + WA + '" target="_blank" rel="noopener">WhatsApp</a>.'
    },
    fr: {
      title: 'Guide Al-Falah',
      subtitle: 'Base de connaissances · Web live',
      status: 'Agent en ligne · Base de connaissances & Web',
      placeholder: 'Question sur Al-Falah ou sujet général…',
      greeting: 'Bonjour. Je priorise la base Al-Falah (services, institutions, contact). Pour les questions techniques ou de marché plus larges, je peux consulter le web en direct, dans un ton institutionnel professionnel.',
      searching: 'Recherche de sources en direct…',
      suggestions: ['Plateformes institutionnelles', 'Réserver une consultation', 'Qu’est-ce que la gouvernance digitale ?', 'Nos services'],
      err: 'Problème de connexion. Réessayez ou utilisez <a href="contact.html">Contact</a> / <a href="' + WA + '" target="_blank" rel="noopener">WhatsApp</a>.'
    }
  };

  function lang() {
    try {
      var L = (localStorage.getItem('alfalah-lang') || document.documentElement.lang || 'en').toLowerCase();
      return L.indexOf('fr') === 0 ? 'fr' : 'en';
    } catch (e) {
      return 'en';
    }
  }

  var css = [
    '#alfalah-ai-root{position:fixed;bottom:20px;right:20px;z-index:9998;font-family:Inter,system-ui,sans-serif}',
    '@media(max-width:640px){#alfalah-ai-root{bottom:88px;right:12px;left:12px}}',
    '#alfalah-ai-btn{width:56px;height:56px;border-radius:16px;border:1px solid rgba(13,147,115,0.35);background:linear-gradient(145deg,#0D9373,#0a7a5f);color:#fff;box-shadow:0 8px 32px rgba(13,147,115,0.35);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .2s,box-shadow .2s;margin-left:auto}',
    '#alfalah-ai-btn:hover{transform:translateY(-2px);box-shadow:0 12px 36px rgba(13,147,115,0.45)}',
    '#alfalah-ai-btn svg{width:26px;height:26px}',
    '#alfalah-ai-panel{display:none;flex-direction:column;width:min(380px,100%);height:min(540px,72vh);background:#0D0F11;border:1px solid rgba(255,255,255,0.1);border-radius:20px;box-shadow:0 24px 64px rgba(0,0,0,0.55);overflow:hidden;margin-bottom:12px}',
    '#alfalah-ai-panel.open{display:flex}',
    '#alfalah-ai-head{padding:12px 14px;background:#13171F;border-bottom:1px solid rgba(255,255,255,0.08)}',
    '#alfalah-ai-head .row{display:flex;align-items:center;gap:12px}',
    '#alfalah-ai-head .ico{width:36px;height:36px;border-radius:10px;background:rgba(13,147,115,0.2);display:flex;align-items:center;justify-content:center;color:#55D799;flex-shrink:0}',
    '#alfalah-ai-head h3{margin:0;font-size:14px;font-weight:600;color:#FAFAFA}',
    '#alfalah-ai-head .sub{margin:0;font-size:11px;color:#94A3B8}',
    '#alfalah-ai-status{display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:4px 10px;border-radius:999px;background:rgba(13,147,115,0.12);border:1px solid rgba(13,147,115,0.28);font-size:10px;color:#55D799;letter-spacing:0.02em}',
    '#alfalah-ai-status .dot{width:6px;height:6px;border-radius:50%;background:#55D799;box-shadow:0 0 0 0 rgba(85,215,153,0.5);animation:alfalahPulse 2s infinite}',
    '@keyframes alfalahPulse{0%{box-shadow:0 0 0 0 rgba(85,215,153,0.45)}70%{box-shadow:0 0 0 6px rgba(85,215,153,0)}100%{box-shadow:0 0 0 0 rgba(85,215,153,0)}}',
    '#alfalah-ai-close{margin-left:auto;background:transparent;border:0;color:#94A3B8;cursor:pointer;padding:6px;border-radius:8px;font-size:14px}',
    '#alfalah-ai-close:hover{color:#fff;background:rgba(255,255,255,0.06)}',
    '#alfalah-ai-msgs{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px}',
    '.alfalah-msg{max-width:92%;padding:10px 12px;border-radius:14px;font-size:13px;line-height:1.45}',
    '.alfalah-msg a{color:#55D799;text-decoration:underline}',
    '.alfalah-msg.bot{align-self:flex-start;background:#181C25;border:1px solid rgba(255,255,255,0.06);color:#E2E8F0}',
    '.alfalah-msg.user{align-self:flex-end;background:rgba(13,147,115,0.25);border:1px solid rgba(13,147,115,0.35);color:#FAFAFA}',
    '.alfalah-msg.meta{font-size:10px;color:#64748b;margin-top:6px}',
    '.alfalah-msg.typing{opacity:0.85;font-style:italic;color:#94A3B8}',
    '#alfalah-ai-sugs{display:flex;flex-wrap:wrap;gap:6px;padding:0 14px 10px}',
    '#alfalah-ai-sugs button{font-size:11px;padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.1);background:#13171F;color:#94A3B8;cursor:pointer}',
    '#alfalah-ai-sugs button:hover{border-color:rgba(13,147,115,0.5);color:#55D799}',
    '#alfalah-ai-form{display:flex;gap:8px;padding:12px 14px;border-top:1px solid rgba(255,255,255,0.08);background:#13171F}',
    '#alfalah-ai-form input{flex:1;min-width:0;background:#0D0F11;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:10px 12px;color:#FAFAFA;font-size:13px;outline:none}',
    '#alfalah-ai-form input:focus{border-color:rgba(13,147,115,0.5)}',
    '#alfalah-ai-form button[type=submit]{background:#0D9373;border:0;color:#fff;border-radius:12px;padding:0 14px;font-weight:600;font-size:13px;cursor:pointer}',
    '#alfalah-ai-form button[type=submit]:hover{background:#0b7d62}',
    '#alfalah-ai-form button[type=submit]:disabled{opacity:0.5;cursor:not-allowed}'
  ].join('');

  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var root = document.createElement('div');
  root.id = 'alfalah-ai-root';
  root.innerHTML =
    '<div id="alfalah-ai-panel" role="dialog" aria-label="Al-Falah navigation assistant">' +
      '<div id="alfalah-ai-head">' +
        '<div class="row">' +
          '<div class="ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a7 7 0 0 1 7 7v1h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1v1a7 7 0 0 1-14 0v-1H4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h1V9a7 7 0 0 1 7-7z"/><circle cx="9" cy="10" r="1" fill="currentColor"/><circle cx="15" cy="10" r="1" fill="currentColor"/></svg></div>' +
          '<div><h3 id="alfalah-ai-title"></h3><p class="sub" id="alfalah-ai-sub"></p></div>' +
          '<button type="button" id="alfalah-ai-close" aria-label="Close">✕</button>' +
        '</div>' +
        '<div id="alfalah-ai-status"><span class="dot" aria-hidden="true"></span><span id="alfalah-ai-status-text"></span></div>' +
      '</div>' +
      '<div id="alfalah-ai-msgs"></div>' +
      '<div id="alfalah-ai-sugs"></div>' +
      '<form id="alfalah-ai-form">' +
        '<input id="alfalah-ai-input" type="text" autocomplete="off" maxlength="500" />' +
        '<button type="submit" id="alfalah-ai-send">→</button>' +
      '</form>' +
    '</div>' +
    '<button type="button" id="alfalah-ai-btn" aria-label="Open Al-Falah assistant">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' +
    '</button>';
  document.body.appendChild(root);

  var panel = document.getElementById('alfalah-ai-panel');
  var msgs = document.getElementById('alfalah-ai-msgs');
  var sugs = document.getElementById('alfalah-ai-sugs');
  var input = document.getElementById('alfalah-ai-input');
  var sendBtn = document.getElementById('alfalah-ai-send');
  var greeted = false;
  var busy = false;
  var conversationHistory = [];

  function addMsg(html, who, meta) {
    var d = document.createElement('div');
    d.className = 'alfalah-msg ' + who;
    d.innerHTML = html + (meta ? '<div class="meta">' + meta + '</div>' : '');
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
    return d;
  }

  function applyLang() {
    var L = UI[lang()];
    document.getElementById('alfalah-ai-title').textContent = L.title;
    document.getElementById('alfalah-ai-sub').textContent = L.subtitle;
    document.getElementById('alfalah-ai-status-text').textContent = L.status;
    input.placeholder = L.placeholder;
    sugs.innerHTML = '';
    L.suggestions.forEach(function (s) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = s;
      b.addEventListener('click', function () { ask(s); });
      sugs.appendChild(b);
    });
  }

  function sourceLabel(source, label) {
    if (label) return label;
    var fr = lang() === 'fr';
    if (source === 'web') return fr ? 'Recherche web en direct' : 'Live web search';
    if (source === 'hybrid') return fr ? 'Hybride (local + web)' : 'Hybrid (local + web)';
    return fr ? 'Base de connaissances Al-Falah' : 'Al-Falah knowledge base';
  }

  function ask(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    var L = UI[lang()];
    addMsg(text.replace(/</g, '&lt;'), 'user');
    input.value = '';
    busy = true;
    sendBtn.disabled = true;
    var typing = addMsg(L.searching, 'bot typing');

    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, lang: lang(), history: conversationHistory })
    })
      .then(function (r) {
        return r.json().then(function (j) {
          return { ok: r.ok, j: j };
        });
      })
      .then(function (res) {
        typing.remove();
        if (!res.ok || !res.j || !res.j.reply) {
          addMsg(L.err, 'bot');
          return;
        }
        conversationHistory.push({ role: 'user', content: text });
        conversationHistory.push({ role: 'assistant', content: res.j.reply.replace(/<[^>]+>/g, ' ') });
        if (conversationHistory.length > 12) conversationHistory = conversationHistory.slice(-12);
        addMsg(res.j.reply, 'bot', sourceLabel(res.j.source, res.j.label));
      })
      .catch(function () {
        typing.remove();
        // Offline fallback: minimal local hints
        addMsg(L.err, 'bot');
      })
      .finally(function () {
        busy = false;
        sendBtn.disabled = false;
        input.focus();
      });
  }

  function openPanel() {
    applyLang();
    panel.classList.add('open');
    if (!greeted) {
      addMsg(UI[lang()].greeting, 'bot');
      greeted = true;
    }
    input.focus();
  }

  document.getElementById('alfalah-ai-btn').addEventListener('click', function () {
    if (panel.classList.contains('open')) panel.classList.remove('open');
    else openPanel();
  });
  document.getElementById('alfalah-ai-close').addEventListener('click', function () {
    panel.classList.remove('open');
  });
  document.getElementById('alfalah-ai-form').addEventListener('submit', function (e) {
    e.preventDefault();
    ask(input.value);
  });
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (t && (t.id === 'lang-en' || t.id === 'lang-fr' || (t.closest && t.closest('.lang-switch')))) {
      setTimeout(applyLang, 50);
    }
  });
})();
