<?php


if (!function_exists('ico')) {
    /** Inline SVG icon (stroke style). */
    function ico(string $name, string $cls = 'w-4 h-4'): string {
        static $p = [
            'plus'      => '<path d="M12 5v14M5 12h14"/>',
            'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>',
            'stop'      => '<rect x="6" y="6" width="12" height="12" rx="2"/>',
            'play'      => '<path d="M6 4l14 8-14 8V4z"/>',
            'x'         => '<path d="M18 6L6 18M6 6l12 12"/>',
            'x-circle'  => '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>',
            'trash'     => '<path d="M3 6h18M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2m2 0v14a2 2 0 01-2 2H8a2 2 0 01-2-2V6h12zM10 11v6M14 11v6"/>',
            'trophy'    => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4z"/><path d="M17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3"/>',
            'eye'       => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
            'users'     => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
            'hash'      => '<path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"/>',
            'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'target'    => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
            'arrow-left'=> '<path d="M19 12H5M12 19l-7-7 7-7"/>',
            'alert'     => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/>',
            'check'     => '<path d="M20 6L9 17l-5-5"/>',
            'check-circle'=> '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>',
            'info'      => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
            'chevron'   => '<path d="M6 9l6 6 6-6"/>',
            'bolt'      => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
            'inbox'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>',
            'layers'    => '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>',
            'medal'     => '<circle cx="12" cy="15" r="6"/><path d="M8.5 9.5L7 2h10l-1.5 7.5"/>',
        ];
        return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
             . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
    }
}

if (!function_exists('sPill')) {
    /** Status pill with a coloured dot. */
    function sPill(string $status): string {
        $map = [
            'active'    => ['green',  'Active'],
            'pending'   => ['yellow', 'Pending'],
            'ended'     => ['orange', 'Ended'],
            'completed' => ['gray',   'Completed'],
            'cancelled' => ['red',    'Cancelled'],
        ];
        [$c, $l] = $map[$status] ?? ['gray', ucfirst($status)];
        return '<span class="s-pill s-pill-' . $c . ' dot">' . $l . '</span>';
    }
}

if (!function_exists('confirmAttrs')) {
    /**
     * Put the result inside a <form ...> tag to protect it with the custom confirmation dialog.
     * $tone: danger | warn | success
     */
    function confirmAttrs(string $title, string $text, string $label, string $tone = 'danger'): string {
        $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return 'data-confirm="1" data-confirm-title="' . $h($title) . '" data-confirm-text="' . $h($text)
             . '" data-confirm-label="' . $h($label) . '" data-confirm-tone="' . $h($tone) . '"';
    }
}
?>
<style>
  :root { --s-line: rgba(255,255,255,.08); --s-muted: #94a3b8; }
  body, button, input, select, textarea { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
  code { font-family: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace; }

  /* Cards */
  .s-card { background: rgba(255,255,255,.025); border: 1px solid var(--s-line); border-radius: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.25); }
  .s-card-hover { transition: border-color .15s, background .15s; }
  .s-card-hover:hover { border-color: rgba(255,255,255,.16); background: rgba(255,255,255,.035); }
  .s-section-title { font-size: 13px; font-weight: 600; color: #e2e8f0; padding: 14px 20px; border-bottom: 1px solid var(--s-line); display: flex; align-items: center; gap: 8px; }
  .s-eyebrow { font-size: 11.5px; font-weight: 500; letter-spacing: .02em; color: var(--s-muted); }

  /* Buttons */
  .s-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 500; line-height: 1.2; border: 1px solid transparent; cursor: pointer; transition: background .15s, border-color .15s, color .15s; white-space: nowrap; text-decoration: none; }
  .s-btn:focus-visible { outline: 2px solid #fb923c; outline-offset: 2px; }
  .s-btn[disabled] { opacity: .6; cursor: not-allowed; }
  .s-btn-sm { padding: 6px 11px; font-size: 12.5px; border-radius: 9px; }
  .s-btn-primary { background: #f97316; color: #fff; }
  .s-btn-primary:hover { background: #ea580c; }
  .s-btn-ghost { background: rgba(255,255,255,.03); border-color: var(--s-line); color: #e2e8f0; }
  .s-btn-ghost:hover { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.16); }
  .s-btn-success { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.28); color: #4ade80; }
  .s-btn-success:hover { background: rgba(34,197,94,.22); }
  .s-btn-warn { background: rgba(249,115,22,.12); border-color: rgba(249,115,22,.28); color: #fb923c; }
  .s-btn-warn:hover { background: rgba(249,115,22,.22); }
  .s-btn-danger { background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.28); color: #f87171; }
  .s-btn-danger:hover { background: rgba(239,68,68,.20); }
  .s-btn-violet { background: #7c3aed; color: #fff; }
  .s-btn-violet:hover { background: #6d28d9; }
  .s-btn-solid-danger  { background: #ef4444; color: #fff; } .s-btn-solid-danger:hover  { background: #dc2626; }
  .s-btn-solid-warn    { background: #f97316; color: #fff; } .s-btn-solid-warn:hover    { background: #ea580c; }
  .s-btn-solid-success { background: #22c55e; color: #052e16; } .s-btn-solid-success:hover { background: #16a34a; }

  /* Pills */
  .s-pill { display: inline-flex; align-items: center; gap: 6px; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 500; border: 1px solid transparent; white-space: nowrap; }
  .s-pill.dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
  .s-pill-green  { background: rgba(34,197,94,.10);  color: #4ade80; border-color: rgba(34,197,94,.22); }
  .s-pill-yellow { background: rgba(234,179,8,.10);  color: #facc15; border-color: rgba(234,179,8,.22); }
  .s-pill-orange { background: rgba(249,115,22,.10); color: #fb923c; border-color: rgba(249,115,22,.22); }
  .s-pill-red    { background: rgba(239,68,68,.10);  color: #f87171; border-color: rgba(239,68,68,.22); }
  .s-pill-gray   { background: rgba(148,163,184,.10);color: #cbd5e1; border-color: rgba(148,163,184,.20); }
  .s-pill-purple { background: rgba(168,85,247,.10); color: #c4b5fd; border-color: rgba(168,85,247,.22); }
  .s-pill-cyan   { background: rgba(34,211,238,.10); color: #67e8f9; border-color: rgba(34,211,238,.22); }
  .s-pill-blue   { background: rgba(59,130,246,.10); color: #93c5fd; border-color: rgba(59,130,246,.22); }

  /* Tabs + segmented control */
  .s-tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--s-line); overflow-x: auto; }
  .s-tab { display: inline-flex; align-items: center; gap: 7px; padding: 10px 12px; margin-bottom: -1px; font-size: 13px; font-weight: 500; color: var(--s-muted); border-bottom: 2px solid transparent; white-space: nowrap; transition: color .15s; }
  .s-tab:hover { color: #fff; }
  .s-tab.on { color: #fb923c; border-bottom-color: #f97316; }
  .s-count { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: rgba(255,255,255,.07); color: var(--s-muted); }
  .s-tab.on .s-count { background: rgba(249,115,22,.15); color: #fdba74; }
  .s-seg { display: inline-flex; padding: 3px; border-radius: 10px; background: rgba(255,255,255,.04); border: 1px solid var(--s-line); }
  .s-seg a { padding: 5px 11px; font-size: 12px; font-weight: 500; border-radius: 7px; color: var(--s-muted); white-space: nowrap; transition: .15s; }
  .s-seg a:hover { color: #fff; }
  .s-seg a.on { background: rgba(255,255,255,.10); color: #fff; }

  /* Stat tiles */
  .s-stat-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

  /* Progress */
  .s-progress { height: 6px; border-radius: 999px; background: rgba(255,255,255,.08); overflow: hidden; }
  .s-progress > div { height: 100%; border-radius: 999px; background: linear-gradient(90deg,#f97316,#fbbf24); transition: width .4s; }
  .s-progress > div.done { background: linear-gradient(90deg,#22c55e,#4ade80); }

  /* Meta row */
  .s-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12.5px; color: var(--s-muted); }
  .s-meta-item { display: inline-flex; align-items: center; gap: 6px; }

  /* Alerts */
  .s-alert { display: flex; gap: 12px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; border: 1px solid; }
  .s-alert-success { background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.25); color: #bbf7d0; }
  .s-alert-error   { background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.25); color: #fecaca; }
  .s-alert a { text-decoration: underline; font-weight: 600; }

  /* Thumb */
  .s-thumb { width: 72px; height: 72px; border-radius: 12px; flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; color: #64748b; background: linear-gradient(135deg,#1a2235,#0d1118); border: 1px solid var(--s-line); }

  /* Form controls (inside modals) */
  .s-label { display: block; font-size: 12.5px; font-weight: 500; color: #cbd5e1; margin-bottom: 6px; }
  .s-hint  { font-size: 11.5px; color: #64748b; margin-top: 5px; }
  .s-input { width: 100%; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.10); border-radius: 10px; padding: 9px 12px; font-size: 14px; color: #fff; color-scheme: dark; transition: border-color .15s, box-shadow .15s; }
  .s-input::placeholder { color: #475569; }
  .s-input:focus { outline: none; border-color: #fb923c; box-shadow: 0 0 0 3px rgba(249,115,22,.15); }
  select.s-input option { background: #111827; color: #fff; }
  .modal-box.s-modal { background: #0f1623 !important; border: 1px solid var(--s-line) !important; border-radius: 18px !important; box-shadow: 0 24px 60px rgba(0,0,0,.55) !important; }
  .s-type input:checked + div { border-color: #fb923c; background: rgba(249,115,22,.10); box-shadow: 0 0 0 1px rgba(251,146,60,.4); }

  /* ── Confirmation dialog ── */
  .cd-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(2,6,15,.72); backdrop-filter: blur(4px); opacity: 0; visibility: hidden; transition: opacity .18s, visibility .18s; }
  .cd-overlay.open { opacity: 1; visibility: visible; }
  .cd-box { width: 100%; max-width: 400px; background: #0f1623; border: 1px solid rgba(255,255,255,.10); border-radius: 18px; padding: 24px; box-shadow: 0 24px 60px rgba(0,0,0,.6); transform: translateY(8px) scale(.97); transition: transform .18s; text-align: center; }
  .cd-overlay.open .cd-box { transform: none; }
  .cd-icon { width: 48px; height: 48px; border-radius: 50%; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; }
  .cd-icon svg { width: 22px; height: 22px; }
  .cd-icon.danger  { background: rgba(239,68,68,.14);  color: #f87171; }
  .cd-icon.warn    { background: rgba(249,115,22,.14); color: #fb923c; }
  .cd-icon.success { background: rgba(34,197,94,.14);  color: #4ade80; }
  .cd-title { font-size: 17px; font-weight: 600; color: #fff; margin-bottom: 6px; }
  .cd-text  { font-size: 13.5px; line-height: 1.55; color: var(--s-muted); margin-bottom: 20px; word-break: break-word; }
  .cd-actions { display: flex; gap: 10px; }
  .cd-actions .s-btn { flex: 1; padding: 10px 14px; }
</style>

<!-- Custom confirmation dialog (replaces the browser confirm() popup) -->
<div class="cd-overlay" id="cd-overlay" aria-hidden="true">
  <div class="cd-box" role="alertdialog" aria-modal="true" aria-labelledby="cd-title" aria-describedby="cd-text">
    <div class="cd-icon" id="cd-icon"></div>
    <h3 class="cd-title" id="cd-title"></h3>
    <p class="cd-text" id="cd-text"></p>
    <div class="cd-actions">
      <button type="button" class="s-btn s-btn-ghost" id="cd-cancel">Go back</button>
      <button type="button" class="s-btn" id="cd-ok">Confirm</button>
    </div>
  </div>
</div>

<script>
(function () {
  var overlay = document.getElementById('cd-overlay');
  var iconEl  = document.getElementById('cd-icon');
  var titleEl = document.getElementById('cd-title');
  var textEl  = document.getElementById('cd-text');
  var okBtn   = document.getElementById('cd-ok');
  var noBtn   = document.getElementById('cd-cancel');
  var pending = null, lastFocus = null;

  var svg = function (p) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; };
  var ICONS = {
    danger:  svg('<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/>'),
    warn:    svg('<rect x="6" y="6" width="12" height="12" rx="2"/>'),
    success: svg('<path d="M6 4l14 8-14 8V4z"/>')
  };

  function open(form) {
    var tone = form.dataset.confirmTone || 'danger';
    if (!ICONS[tone]) tone = 'danger';
    pending   = form;
    lastFocus = document.activeElement;
    iconEl.className = 'cd-icon ' + tone;
    iconEl.innerHTML = ICONS[tone];
    titleEl.textContent = form.dataset.confirmTitle || 'Are you sure?';
    textEl.textContent  = form.dataset.confirmText  || '';
    okBtn.textContent   = form.dataset.confirmLabel || 'Confirm';
    okBtn.className     = 's-btn s-btn-solid-' + tone;
    okBtn.disabled = false;
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    setTimeout(function () { noBtn.focus(); }, 30);
  }

  function close() {
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    pending = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  // Any <form data-confirm> asks first
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.matches && f.matches('form[data-confirm]')) { e.preventDefault(); open(f); }
  });

  okBtn.addEventListener('click', function () {
    if (!pending) return;
    okBtn.disabled = true; okBtn.textContent = 'Please wait…';
    var f = pending; pending = null;
    f.submit();                       // .submit() does not re-trigger the listener above
  });
  noBtn.addEventListener('click', close);
  overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) {
    if (!overlay.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'Tab') {            // keep focus inside the dialog
      var list = [noBtn, okBtn].filter(function (b) { return !b.disabled; });
      var i = list.indexOf(document.activeElement);
      if (e.shiftKey && i <= 0) { e.preventDefault(); list[list.length - 1].focus(); }
      else if (!e.shiftKey && i === list.length - 1) { e.preventDefault(); list[0].focus(); }
    }
  });
})();
</script>