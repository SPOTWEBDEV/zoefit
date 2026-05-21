// assets/js/app.js — ZoeFeeds Main JS

'use strict';

// =============================================
// TOAST SYSTEM
// =============================================
const Toast = {
  container: null,
  init() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },
  show(message, type = 'info', duration = 4000) {
    this.init();
    const icons = { success: '✓', error: '✗', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span style="font-size:18px;flex-shrink:0">${icons[type] || icons.info}</span>
      <span style="font-size:14px;font-weight:500">${message}</span>
    `;
    this.container.appendChild(toast);
    setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s ease forwards';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },
  success: (m, d) => Toast.show(m, 'success', d),
  error:   (m, d) => Toast.show(m, 'error', d),
  info:    (m, d) => Toast.show(m, 'info', d),
};

// =============================================
// MODAL SYSTEM
// =============================================
const Modal = {
  open(id)  { document.getElementById(id)?.classList.add('open'); },
  close(id) { document.getElementById(id)?.classList.remove('open'); },
  closeAll() { document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open')); }
};
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) Modal.closeAll();
  if (e.target.dataset.closeModal) Modal.close(e.target.dataset.closeModal);
});

// =============================================
// MOBILE SIDEBAR TOGGLE
// =============================================
function toggleSidebar() {
  document.querySelector('.sidebar')?.classList.toggle('open');
}

// =============================================
// BALANCE TOGGLE
// =============================================
function toggleBalance() {
  const el = document.getElementById('balance-value');
  const btn = document.getElementById('balance-toggle');
  if (!el) return;
  const hidden = el.dataset.hidden === '1';
  el.textContent = hidden ? el.dataset.value : '••••';
  el.dataset.hidden = hidden ? '0' : '1';
  btn.textContent = hidden ? '👁' : '🙈';
}

// =============================================
// COUNTDOWN TIMERS
// =============================================
function initCountdowns() {
  document.querySelectorAll('[data-countdown]').forEach(el => {
    const target = new Date(el.dataset.countdown).getTime();
    const update = () => {
      const now = Date.now();
      const diff = target - now;
      if (diff <= 0) { el.innerHTML = '<span class="text-red-400">Draw Ended</span>'; return; }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      const parts = [
        { v: d, l: 'Days' }, { v: h, l: 'Hrs' }, { v: m, l: 'Min' }, { v: s, l: 'Sec' }
      ];
      el.innerHTML = parts.map(p => `
        <div class="text-center">
          <div class="countdown-timer">${String(p.v).padStart(2,'0')}</div>
          <div class="countdown-label">${p.l}</div>
        </div>
      `).join('<div class="countdown-timer mx-1 opacity-40">:</div>');
    };
    update();
    setInterval(update, 1000);
  });
}

// =============================================
// SLIDESHOW
// =============================================
function initSlideshow(container) {
  const slides = container.querySelectorAll('.slide');
  const dotsContainer = container.querySelector('.slide-dots');
  if (!slides.length) return;
  let current = 0;
  const goto = (i) => {
    slides[current].classList.remove('active');
    dotsContainer?.querySelectorAll('.slide-dot')[current]?.classList.remove('active');
    current = i;
    slides[current].classList.add('active');
    dotsContainer?.querySelectorAll('.slide-dot')[current]?.classList.add('active');
  };
  slides[0].classList.add('active');
  if (dotsContainer) {
    slides.forEach((_, i) => {
      const dot = document.createElement('div');
      dot.className = 'slide-dot' + (i === 0 ? ' active' : '');
      dot.onclick = () => goto(i);
      dotsContainer.appendChild(dot);
    });
  }
  setInterval(() => goto((current + 1) % slides.length), 4000);
}

// =============================================
// AJAX HELPER
// =============================================
const ZF = {
  csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

  async post(url, data = {}) {
    data._zf_csrf = this.csrfToken;
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(data),
    });
    const json = await resp.json();
    if (!resp.ok) throw new Error(json.error || 'Request failed');
    return json;
  },

  async get(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const resp = await fetch(`${url}?${qs}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return resp.json();
  }
};

// =============================================
// INFINITE SCROLL
// =============================================
class InfiniteScroll {
  constructor({ container, loader, fetchUrl, renderItem, params = {} }) {
    this.container = container;
    this.loader = loader;
    this.fetchUrl = fetchUrl;
    this.renderItem = renderItem;
    this.params = params;
    this.page = 1;
    this.loading = false;
    this.done = false;
    this.observer = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) this.load();
    }, { rootMargin: '200px' });
    if (this.loader) this.observer.observe(this.loader);
    this.load();
  }
  async load() {
    if (this.loading || this.done) return;
    this.loading = true;
    if (this.loader) this.loader.style.display = 'block';
    try {
      const data = await ZF.get(this.fetchUrl, { ...this.params, page: this.page });
      if (!data.items || data.items.length === 0) {
        this.done = true;
        if (this.loader) this.loader.innerHTML = '<p class="text-center text-sm text-gray-500 py-4">No more items</p>';
        return;
      }
      data.items.forEach(item => {
        const el = document.createElement('div');
        el.innerHTML = this.renderItem(item);
        this.container.appendChild(el.firstChild);
      });
      this.page++;
      if (!data.hasMore) {
        this.done = true;
        if (this.loader) this.loader.remove();
      }
    } catch (e) {
      Toast.error('Failed to load data');
    } finally {
      this.loading = false;
      if (this.loader && !this.done) this.loader.style.display = 'none';
    }
  }
}

// =============================================
// PHONE AUTO-FORMAT
// =============================================
document.querySelectorAll('input[data-phone]').forEach(input => {
  input.addEventListener('input', () => {
    let v = input.value.replace(/\D/g, '');
    if (v.startsWith('234') && v.length > 3) v = '0' + v.slice(3);
    input.value = v.slice(0, 11);
  });
});

// =============================================
// LIVE DRAW POLLING
// =============================================
class LiveDrawPoller {
  constructor(drawId, onUpdate) {
    this.drawId = drawId;
    this.onUpdate = onUpdate;
    this.interval = null;
    this.lastRevealed = '';
  }
  start(ms = 2000) {
    this.poll();
    this.interval = setInterval(() => this.poll(), ms);
  }
  stop() { clearInterval(this.interval); }
  async poll() {
    try {
      const data = await ZF.get(`${APP_URL}/ajax/draw-reveal.php`, { draw_id: this.drawId });
      if (data.revealed !== this.lastRevealed) {
        this.lastRevealed = data.revealed;
        this.onUpdate(data);
      }
      if (data.finalized) this.stop();
    } catch (e) {}
  }
}

// =============================================
// CODE DIGIT HIGHLIGHTER
// =============================================
function highlightCodeMatch(codeEl, userCode, revealedDigits) {
  const spans = codeEl.querySelectorAll('.code-char');
  for (let i = 0; i < revealedDigits.length && i < userCode.length; i++) {
    if (userCode[i] === revealedDigits[i]) {
      spans[i]?.classList.add('code-digit-match');
    } else {
      spans[i]?.classList.remove('code-digit-match');
    }
  }
}

function renderCodeWithSpans(code) {
  return code.split('').map((ch, i) =>
    `<span class="code-char" data-pos="${i}">${ch}</span>`
  ).join('');
}

// =============================================
// TRANSFER PIN MODAL
// =============================================
function initPinInput(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const inputs = container.querySelectorAll('.pin-digit');
  inputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/\D/g, '').slice(-1);
      if (inp.value && i < inputs.length - 1) inputs[i + 1].focus();
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && i > 0) inputs[i - 1].focus();
    });
  });
  return () => Array.from(inputs).map(i => i.value).join('');
}

// =============================================
// REAL-TIME NOTIFICATIONS
// =============================================
async function pollNotifications() {
  const badge = document.getElementById('notif-badge');
  if (!badge) return;
  try {
    const data = await ZF.get(`${APP_URL}/ajax/notifications.php`);
    const count = data.unread || 0;
    badge.textContent = count > 0 ? count : '';
    badge.style.display = count > 0 ? 'flex' : 'none';
  } catch (e) {}
}

// =============================================
// INIT
// =============================================
document.addEventListener('DOMContentLoaded', () => {
  initCountdowns();
  document.querySelectorAll('[data-slideshow]').forEach(initSlideshow);
  pollNotifications();
  setInterval(pollNotifications, 30000);
});

// Make global
window.APP_URL = '<?= APP_URL ?>';
window.Toast = Toast;
window.Modal = Modal;
window.ZF = ZF;
window.toggleBalance = toggleBalance;
window.toggleSidebar = toggleSidebar;
window.InfiniteScroll = InfiniteScroll;
window.LiveDrawPoller = LiveDrawPoller;
window.highlightCodeMatch = highlightCodeMatch;
window.renderCodeWithSpans = renderCodeWithSpans;
window.initPinInput = initPinInput;
