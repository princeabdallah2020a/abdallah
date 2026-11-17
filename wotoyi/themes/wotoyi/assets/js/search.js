/* Wotoyi theme - search page: filter panel (sidebar >=1200px, drawer 768-1199, bottom sheet <768), accordions, dual range, view toggle,
   optional live count (only when the marketplace plugin gives a count endpoint in data-wy-count-url). Forms work without JS (plain GET). */
(function () {
  'use strict';
  var WY = window.WY || {}, U = WY.ui, M = WY.motion;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var form = $('[data-wy-search-form]'), panel = $('[data-wy-filters]');
  if (!form || !panel || !U) return;

  /* accordions */
  $$('.wy-filter-group__h', panel).forEach(function (h) {
    var body = document.getElementById(h.getAttribute('aria-controls')), sec = h.closest('.wy-filter-group');
    h.addEventListener('click', function () { var on = h.getAttribute('aria-expanded') !== 'true'; h.setAttribute('aria-expanded', String(on)); sec.classList.toggle('is-open', on); if (body) body.hidden = !on; });
  });

  /* dual range */
  $$('[data-wy-range]', panel).forEach(function (box) {
    var lo = $('[data-range=min]', box), hi = $('[data-range=max]', box), max = +lo.max || 1;
    var out = box.parentNode.querySelector('[data-wy-range-out]');
    var paint = function () {
      var a = +lo.value, b = +hi.value; if (a > b) { if (document.activeElement === lo) { a = b; lo.value = a; } else { b = a; hi.value = b; } }
      box.style.setProperty('--lo', (a / max * 100) + '%'); box.style.setProperty('--hi', (b / max * 100) + '%');
      if (out) out.textContent = WY.nf(a) + ' - ' + WY.nf(b);
      /* only submit a bound when it differs from the full range */
      lo.name = a > 0 ? lo.dataset.name : ''; hi.name = b < max ? hi.dataset.name : '';
    };
    lo.addEventListener('input', paint); hi.addEventListener('input', paint); paint();
  });

  /* equipment search inside a group */
  var eqs = $('[data-wy-eq-search]', panel);
  if (eqs) eqs.addEventListener('input', function () { var q = eqs.value.trim().toLowerCase(); $$('[data-wy-eqitem]', panel).forEach(function (l) { l.hidden = q && l.dataset.wyEqitem.indexOf(q) < 0; }); });

  /* view toggle */
  var res = $('[data-wy-results]');
  $$('[data-wy-view]').forEach(function (b) { b.addEventListener('click', function () {
    $$('[data-wy-view]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); if (res) res.dataset.view = b.dataset.wyView; try { localStorage.setItem('wy-view', b.dataset.wyView); } catch (e) {} }); });
  try { var v = localStorage.getItem('wy-view'); if (v && res) { res.dataset.view = v; $$('[data-wy-view]').forEach(function (x) { x.setAttribute('aria-pressed', String(x.dataset.wyView === v)); }); } } catch (e) {}

  /* sort changes submit immediately */
  var sort = $('[data-wy-sort]'); if (sort) sort.addEventListener('change', function () { form.submit(); });

  /* clear */
  $$('[data-wy-clear]').forEach(function (b) { b.addEventListener('click', function () { location.href = form.getAttribute('action') || location.pathname; }); });

  /* panel as overlay below 1200px */
  var openers = $$('[data-wy-filters-open]'), sheet = null, scrim = $('[data-wy-scrim]');
  var ov = U.overlay(panel, { noHidden: true, focus: '.wy-filter-group__h', onClose: function () { if (sheet) sheet.reset(); openers.forEach(function (o) { o.setAttribute('aria-expanded', 'false'); }); } });
  openers.forEach(function (b) { b.addEventListener('click', function () { if (matchMedia('(min-width:1200px)').matches) return; ov.open(b); b.setAttribute('aria-expanded', 'true'); if (sheet && innerWidth < 768) sheet.open(); }); });
  if (M && M.sheet) sheet = M.sheet(panel, { grips: [$('[data-wy-grip]', panel), $('[data-wy-sheet-head]', panel)].filter(Boolean), scrim: scrim, onClose: function () { ov.close(); } });
  matchMedia('(min-width:1200px)').addEventListener('change', function (e) { if (e.matches && ov.isOpen) ov.close(); });

  /* live count (optional) */
  var url = form.getAttribute('data-wy-count-url');
  if (url) {
    var t, ctl;
    var upd = function () {
      var qs = new URLSearchParams(new FormData(form)); qs.delete('page'); if (ctl) ctl.abort(); ctl = window.AbortController ? new AbortController() : null;
      fetch(url + (url.indexOf('?') > -1 ? '&' : '?') + qs.toString(), { signal: ctl && ctl.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { if (!j) return; var n = typeof j.total === 'number' ? j.total : (typeof j.count === 'number' ? j.count : null); if (n === null) return; $$('[data-wy-count-live]').forEach(function (el) { el.textContent = WY.nf(n); }); })
        .catch(function () {});
    };
    form.addEventListener('change', function () { clearTimeout(t); t = setTimeout(upd, 350); });
  }
})();
