/* Wotoyi theme - core UI (all templates). Vanilla JS, no dependencies, loaded with defer.
   Overlays (menu drawer, AI panel), toasts, favourites, share, AI-fab avoidance. Theme toggle + motion live in motion.js. */
(function () {
  'use strict';
  var WY = window.WY = window.WY || {};
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var root = document.documentElement;
  var tier = function () { return root.dataset.motion || 'full'; };
  var I = window.wyI18n || {};                     /* strings injected by inc/assets.php (translatable) */
  var U = WY.ui = {};
  WY.nf = function (n) { try { return new Intl.NumberFormat(root.lang || 'fr', { numberingSystem: 'latn' }).format(n); } catch (e) { return String(n); } };

  /* ---- toast ---- */
  U.toast = function (msg) {
    var box = $('[data-wy-toasts]'); if (!box) return;
    var n = document.createElement('div'); n.className = 'wy-toast'; n.textContent = msg; box.appendChild(n);
    requestAnimationFrame(function () { n.classList.add('is-in'); });
    setTimeout(function () { n.classList.remove('is-in'); setTimeout(function () { n.remove(); }, 300); }, 3600);
  };

  /* ---- overlays: scrim, scroll lock, focus trap, Esc ---- */
  var scrim = $('[data-wy-scrim]'), stack = [];
  var FOC = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea,[tabindex]:not([tabindex="-1"])';
  U.overlay = function (el, o) {
    o = o || {}; var opener, api = { el: el, isOpen: false };
    api.open = function (from) {
      if (api.isOpen) return; api.isOpen = true; opener = from || document.activeElement;
      if (!o.noHidden) el.hidden = false;
      if (scrim && !o.noScrim) { scrim.hidden = false; requestAnimationFrame(function () { scrim.classList.add('is-in'); }); }
      void el.offsetWidth; el.classList.add('is-open'); root.classList.add('wy-lock'); stack.push(api);
      if (o.onOpen) o.onOpen();
      setTimeout(function () { var f = $(o.focus || FOC, el); if (f) f.focus({ preventScroll: true }); }, 30);
    };
    api.close = function (silent) {
      if (!api.isOpen) return; api.isOpen = false; el.classList.remove('is-open'); stack.splice(stack.indexOf(api), 1);
      if (!stack.length) { root.classList.remove('wy-lock'); if (scrim) { scrim.classList.remove('is-in'); setTimeout(function () { if (!stack.length) scrim.hidden = true; }, 280); } }
      setTimeout(function () { if (!api.isOpen && !o.noHidden) el.hidden = true; }, tier() === 'reduced' ? 0 : 300);
      if (o.onClose && !silent) o.onClose();
      if (opener && opener.focus) opener.focus({ preventScroll: true });
    };
    $$('[data-wy-close]', el).forEach(function (b) { b.addEventListener('click', function () { api.close(); }); });
    return api;
  };
  document.addEventListener('keydown', function (e) {
    var top = stack[stack.length - 1]; if (!top) return;
    if (e.key === 'Escape') { top.close(); return; }
    if (e.key === 'Tab') {
      var f = $$(FOC, top.el).filter(function (n) { return n.offsetParent !== null || n === document.activeElement; }); if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  if (scrim) scrim.addEventListener('click', function () { var top = stack[stack.length - 1]; if (top) top.close(); });

  var menu = $('[data-wy-menu]');
  if (menu) {
    var mo = U.overlay(menu, { onClose: function () { $$('[data-wy-menu-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); }); } });
    $$('[data-wy-menu-open]').forEach(function (b) { b.addEventListener('click', function () { mo.open(b); b.setAttribute('aria-expanded', 'true'); }); });
    matchMedia('(min-width: 1200px)').addEventListener('change', function (e) { if (e.matches && mo.isOpen) mo.close(); });
  }

  /* ---- AI panel: honest default = keyword search form (server-rendered). wotoyi-ai can replace the panel body. ---- */
  var ai = $('[data-wy-ai]');
  if (ai) {
    var aiO = U.overlay(ai, { focus: '#wy-ai-input' });
    document.addEventListener('click', function (e) {
      var o = e.target.closest && e.target.closest('[data-wy-ai-open]');
      if (o) { e.preventDefault(); aiO.open(o); var p = o.dataset.wyAiPrefill; if (p) { var i = $('#wy-ai-input'); if (i) i.value = p; } }
    });
    WY.openAI = function (q) { aiO.open(); var i = $('#wy-ai-input'); if (i && q) i.value = q; };
  }

  /* ---- favourites (localStorage; account sync is the marketplace plugin's job) ---- */
  var favs = []; try { favs = JSON.parse(localStorage.getItem('wy-favs') || '[]'); } catch (e) {}
  var paintFavs = function () {
    $$('[data-wy-favs-count]').forEach(function (n) { n.textContent = favs.length; n.hidden = !favs.length; });
    $$('[data-wy-fav]').forEach(function (b) { var on = favs.indexOf(b.dataset.wyFav) > -1; b.setAttribute('aria-pressed', String(on)); b.setAttribute('aria-label', on ? (I.favRemove || 'Retirer des favoris') : (I.favAdd || 'Ajouter aux favoris')); });
  };
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-wy-fav]');
    if (b) {
      e.preventDefault(); var id = b.dataset.wyFav, i = favs.indexOf(id);
      if (i > -1) favs.splice(i, 1); else { favs.push(id); b.classList.remove('is-pop'); void b.offsetWidth; b.classList.add('is-pop'); }
      try { localStorage.setItem('wy-favs', JSON.stringify(favs)); } catch (er) {} paintFavs(); return;
    }
    if (e.target.closest && e.target.closest('[data-wy-favs-open]')) {
      var base = $('[data-wy-favs-open]').getAttribute('data-href');
      if (base && favs.length) location.href = base + (base.indexOf('?') > -1 ? '&' : '?') + 'ids=' + encodeURIComponent(favs.join(','));
      else U.toast((I.favCount || '{n} favori(s)').replace('{n}', favs.length));
    }
    var sh = e.target.closest && e.target.closest('[data-wy-share]');
    if (sh) { var url = location.href, title = document.title;
      if (navigator.share) navigator.share({ title: title, url: url }).catch(function () {});
      else if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () { U.toast(I.copied || 'Lien copie'); }, function () { U.toast(url); }); else U.toast(url); }
  });
  paintFavs(); WY.paintFavs = paintFavs;

  /* ---- recently viewed (written by vehicle.js, read by home.js) ---- */
  U.recentRead = function () { try { var r = JSON.parse(localStorage.getItem('wy-recent') || '[]'); return Array.isArray(r) ? r : []; } catch (e) { return []; } };
  U.recentWrite = function (item) {
    var r = U.recentRead().filter(function (x) { return x && x.id !== item.id; }); r.unshift(item);
    try { localStorage.setItem('wy-recent', JSON.stringify(r.slice(0, 8))); } catch (e) {}
  };

  /* ---- AI-fab avoidance: the fab fades out whenever it would sit on top of an input, the contact card or a [data-wy-fab-avoid] element.
     Implementation: an IntersectionObserver whose root box is shrunk (rootMargin) to the fab's own rectangle. ---- */
  (function () {
    var f = $('.wy-ai-fab'); if (!f || !('IntersectionObserver' in window)) return;
    var sel = '[data-wy-fab-avoid],.wy-quicksearch,.wy-search--nl,.wy-contact,.wy-lead-form,.wy-filter-panel__foot,.wy-pagination,.wy-field,.wy-searchbar__in,.wy-segmented,[role="radiogroup"],.wy-btn,main button,input,textarea,select';
    var io, hits, timer;
    function mount() {
      if (io) io.disconnect(); hits = new Set();
      var wasAway = f.classList.contains('is-away'); f.classList.remove('is-away');
      var r = f.getBoundingClientRect(), W = innerWidth, H = innerHeight, m = 10;
      if (!r.width) { f.classList.toggle('is-away', wasAway); return; }
      var mt = Math.max(0, Math.round(r.top - m)), ml = Math.max(0, Math.round(r.left - m)), mr = Math.max(0, Math.round(W - r.right - m)), mb = Math.max(0, Math.round(H - r.bottom - m));
      io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) hits.add(e.target); else hits.delete(e.target); });
        f.classList.toggle('is-away', hits.size > 0);
      }, { rootMargin: '-' + mt + 'px -' + mr + 'px -' + mb + 'px -' + ml + 'px' });
      $$(sel).forEach(function (t) { if (!f.contains(t) && !t.closest('[data-wy-ai],[data-wy-menu],dialog,.wy-drawer')) io.observe(t); });
    }
    mount(); addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(mount, 150); });
    WY.fabRemount = mount;
    if (window.MutationObserver) { var mt; new MutationObserver(function () { clearTimeout(mt); mt = setTimeout(mount, 250); }).observe(document.body, { childList: true, subtree: true }); } /* late-rendered controls */
  })();

  /* ---- init ---- */
  var path = location.pathname.replace(/\/+$/, '');
  $$('[data-wy-bottom-nav] a').forEach(function (a) { try { if (new URL(a.href).pathname.replace(/\/+$/, '') === path) a.setAttribute('aria-current', 'page'); } catch (e) {} });
})();
