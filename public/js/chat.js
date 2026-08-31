(function () {
  'use strict';

  if (!window.YeonbunAuth || !window.YeonbunAuth.loggedIn) return; // 비로그인 화면엔 채팅 UI 자체가 없음

  var chatSessionId = null;
  var sending = false;

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function jsonFetch(url, options) {
    options = options || {};
    options.headers = Object.assign({
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken()
    }, options.headers || {});
    return fetch(url, options).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) {
        return { ok: res.ok, status: res.status, body: body };
      });
    });
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'class') node.className = attrs[k];
      else node.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(c); });
    return node;
  }

  function addBubble(role, text) {
    var log = document.getElementById('chat-log');
    var bubble = el('div', { class: 'chat-bubble chat-' + role }, [document.createTextNode(text)]);
    log.appendChild(bubble);
    log.scrollTop = log.scrollHeight;
  }

  function updateCreditsDisplay(credits) {
    if (typeof credits !== 'number') return;
    var roomLabel = document.getElementById('chat-credits');
    if (roomLabel) roomLabel.textContent = '남은 메시지 ' + credits + '개';
    var topbarLabel = document.getElementById('topbar-credits');
    if (topbarLabel) topbarLabel.textContent = '코인 ' + credits + '개';
  }

  function setBusy(isBusy) {
    sending = isBusy;
    document.getElementById('chat-send').disabled = isBusy;
    document.getElementById('chat-input').disabled = isBusy;
    document.getElementById('chat-typing').style.display = isBusy ? 'block' : 'none';
  }

  function showChatError(msg) {
    var box = document.getElementById('chat-error');
    box.textContent = msg;
    box.style.display = 'block';
  }

  function hideChatError() {
    document.getElementById('chat-error').style.display = 'none';
  }

  function showSetupView() {
    chatSessionId = null;
    document.getElementById('chat-setup').style.display = 'block';
    document.getElementById('chat-room').style.display = 'none';
    loadHistory();
  }

  function showRoomView() {
    document.getElementById('chat-setup').style.display = 'none';
    document.getElementById('chat-room').style.display = 'block';
  }

  function formatDate(iso) {
    try {
      var d = new Date(iso);
      return d.getFullYear() + '.' + (d.getMonth() + 1) + '.' + d.getDate() + ' ' +
        String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    } catch (e) { return iso; }
  }

  function loadHistory() {
    var box = document.getElementById('chat-history');
    box.innerHTML = '';
    jsonFetch('/chat').then(function (r) {
      if (!r.ok || !r.body.sessions || !r.body.sessions.length) return;
      box.appendChild(el('div', { class: 'field-label' }, [document.createTextNode('이전 상담')]));
      r.body.sessions.forEach(function (s) {
        var item = el('button', { class: 'chat-history-item', type: 'button' }, [
          document.createTextNode(s.name || '상담'),
          el('span', { class: 'meta' }, [document.createTextNode(formatDate(s.updated_at) + ' · ' + s.message_count + '개')])
        ]);
        item.addEventListener('click', function () { openSession(s.id); });
        box.appendChild(item);
      });
    });
  }

  function openSession(id) {
    hideChatError();
    jsonFetch('/chat/' + id).then(function (r) {
      if (!r.ok) { showChatError(r.body.error || '상담을 불러오지 못했어요.'); return; }
      chatSessionId = r.body.chat_session_id;
      document.getElementById('chat-log').innerHTML = '';
      (r.body.messages || []).forEach(function (m) { addBubble(m.role, m.content); });
      showRoomView();
    });
  }

  function startSession() {
    hideChatError();
    var ctx = (window.YeonbunApp && window.YeonbunApp.getSajuContext) ? window.YeonbunApp.getSajuContext() : null;
    var name = ctx && ctx.name ? ctx.name : null;

    document.getElementById('chat-start').disabled = true;
    document.getElementById('chat-start').textContent = '상담사를 연결하는 중…';

    jsonFetch('/chat/start', {
      method: 'POST',
      body: JSON.stringify({ name: name, saju_context: ctx })
    })
      .then(function (r) {
        if (r.status === 402 && r.body.needs_payment) { window.location.href = '/billing'; return; }
        if (!r.ok) throw new Error(r.body.error || '상담을 시작하지 못했어요.');
        chatSessionId = r.body.chat_session_id;
        document.getElementById('chat-log').innerHTML = '';
        addBubble('assistant', r.body.message);
        if (ctx) {
          addBubble('system', (name ? name + '님의 ' : '') + '사주 정보(일간 ' + ctx.dayElement + ', 오행 분포, 신살)를 상담사에게 전달했어요.');
        } else {
          addBubble('system', '사주 정보 없이 시작했어요. "연애의 나침반" 탭에서 먼저 계산하면 더 맞춤화된 상담을 받을 수 있어요.');
        }
        showRoomView();
      })
      .catch(function (e) { showChatError(e.message); })
      .finally(function () {
        document.getElementById('chat-start').disabled = false;
        document.getElementById('chat-start').textContent = '새 상담 시작하기';
      });
  }

  function sendMessage() {
    if (sending || !chatSessionId) return;
    var input = document.getElementById('chat-input');
    var text = input.value.trim();
    if (!text) return;
    hideChatError();
    addBubble('user', text);
    input.value = '';
    setBusy(true);

    jsonFetch('/chat/' + chatSessionId + '/message', {
      method: 'POST',
      body: JSON.stringify({ message: text })
    })
      .then(function (r) {
        if (r.status === 402 && r.body.needs_payment) { window.location.href = '/billing'; return; }
        if (!r.ok) throw new Error(r.body.error || 'AI 응답을 받아오지 못했어요.');
        addBubble('assistant', r.body.message);
        updateCreditsDisplay(r.body.credits);
      })
      .catch(function (e) { showChatError(e.message); })
      .finally(function () {
        setBusy(false);
        input.focus();
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var startBtn = document.getElementById('chat-start');
    if (!startBtn) return; // 로그인 안 된 화면엔 이 요소들이 없음

    startBtn.addEventListener('click', startSession);
    document.getElementById('chat-send').addEventListener('click', sendMessage);
    document.getElementById('chat-back').addEventListener('click', showSetupView);
    document.getElementById('chat-input').addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    document.getElementById('chat-room').style.display = 'none';
    loadHistory();
  });
})();
