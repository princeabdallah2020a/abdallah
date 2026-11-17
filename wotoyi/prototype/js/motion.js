/* Wotoyi prototype - motion engine (vanilla, no dependencies). DESIGN.md section 3.
   Tiers: full / lite / reduced (html[data-motion], set before first paint by the head script).
   Features: theme toggle (View Transition circle reveal), reveal-on-scroll, split text, tilt + sheen, counters, marquee,
   magnetic buttons, parallax, aurora pause, hscroll drag, pinned story, bottom-sheet physics, cursor, H1 sheen.
   Not included (stated plainly in README): Lenis smooth scroll (no vendored copy / no CDN), hover video, real 360 frames. */
(function () {
  'use strict';
  var WY = window.WY = window.WY || {};
  var root = document.documentElement;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var tier = function () { return root.dataset.motion || 'full'; };
  var fine = matchMedia('(hover: hover) and (pointer: fine)').matches;
  var EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';
  var M = WY.motion = { tier: tier };

  /* ---- shared rAF scheduler (one loop for cursor / tilt / magnetic) ---- */
  var queue = [], raf = 0;
  function frame(t) { raf = 0; var q = queue; queue = []; for (var i = 0; i < q.length; i++) q[i](t); }
  M.raf = function (fn) { queue.push(fn); if (!raf) raf = requestAnimationFrame(frame); };

  /* ---- theme ---- */
  var mqDark = matchMedia('(prefers-color-scheme: dark)');
  function resolve(p) { return p === 'auto' ? (mqDark.matches ? 'dark' : 'light') : p; }
  function syncThemeUI() {
    var p = root.dataset.themePref || 'auto';
    $$('[data-wy-theme-radio]').forEach(function (r) { r.checked = r.value === p; });
    $$('[data-wy-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', String(root.dataset.theme === 'dark')); });
  }
  M.setTheme = function (pref, origin) {
    var apply = function () {
      try { localStorage.setItem('wy-theme', pref); } catch (e) {}
      root.dataset.themePref = pref; root.dataset.theme = resolve(pref); syncThemeUI();
    };
    if (tier() !== 'full' || !document.startViewTransition) { apply(); return; }
    var x = origin ? origin.x : innerWidth / 2, y = origin ? origin.y : 0;
    var r = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
    root.classList.add('wy-theme-vt');
    var vt = document.startViewTransition(apply);
    vt.ready.then(function () {
      root.animate({ clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + r + 'px at ' + x + 'px ' + y + 'px)'] },
        { duration: 420, easing: 'cubic-bezier(0.65, 0, 0.35, 1)', pseudoElement: '::view-transition-new(root)' });
    }).catch(function () {});
    vt.finished.finally(function () { root.classList.remove('wy-theme-vt'); });
  };
  mqDark.addEventListener('change', function () { if ((root.dataset.themePref || 'auto') === 'auto') { root.dataset.theme = resolve('auto'); syncThemeUI(); } });
  document.addEventListener('click', function (e) {
    var tg = e.target.closest && e.target.closest('[data-wy-theme-toggle]');
    if (tg) { var b = tg.getBoundingClientRect(); M.setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', { x: b.left + b.width / 2, y: b.top + b.height / 2 }); }
    var mo = e.target.closest && e.target.closest('[data-wy-motion]');
    if (mo) { try { if (mo.dataset.wyMotion === 'auto') localStorage.removeItem('wy-motion'); else localStorage.setItem('wy-motion', mo.dataset.wyMotion); } catch (er) {} location.reload(); }
  });
  document.addEventListener('change', function (e) {
    var r = e.target.closest && e.target.closest('[data-wy-theme-radio]');
    if (r) { var lab = r.closest('label').getBoundingClientRect(); M.setTheme(r.value, { x: lab.left + lab.width / 2, y: lab.top + lab.height / 2 }); }
  });

  /* ---- split text (word level only: never letters, keeps Arabic shaping) ---- */
  function splitWords(el) {
    if (el.dataset.split) return; el.dataset.split = '1'; el.classList.add('wy-words');
    var n = 0;
    (function walk(node, inEm) {
      Array.prototype.slice.call(node.childNodes).forEach(function (c) {
        if (c.nodeType === 3) {
          var parts = c.textContent.split(/(\s+)/), frag = document.createDocumentFragment();
          parts.forEach(function (p) {
            if (!p) return;
            if (/^\s+$/.test(p)) { frag.appendChild(document.createTextNode(' ')); return; }
            var w = document.createElement('span'), i = document.createElement('span');
            w.className = 'wy-split__w'; i.textContent = p; i.style.setProperty('--i', Math.min(n++, 8));
            if (inEm) i.className = 'wy-em'; w.appendChild(i); frag.appendChild(w);
          });
          node.replaceChild(frag, c);
        } else if (c.nodeType === 1) walk(c, inEm || c.classList.contains('wy-em') || c.tagName === 'EM');
      });
    })(el, false);
  }

  /* ---- reveal / split / counters via IntersectionObserver ---- */
  var io;
  function observe(el) { if (!io) io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } }); }, { threshold: 0.15, rootMargin: '0px 0px -4% 0px' }); io.observe(el); }
  M.scan = function (scope) {
    scope = scope || document;
    $$('[data-motion~="split"]', scope).forEach(function (el) { if (!el.dataset.splitDone) { el.dataset.splitDone = '1'; splitWords(el); observe(el); } });
    if (!root.classList.contains('wy-sd')) $$('[data-motion~="reveal"]', scope).forEach(function (el) { if (!el.dataset.revDone) { el.dataset.revDone = '1'; observe(el); } });
  };

  var cio, nfmt = function (n) { return WY.nf ? WY.nf(n) : String(n); };
  M.counter = function (el, value) {
    var v = value != null ? value : +el.dataset.count; el.dataset.count = v;
    var fin = nfmt(v);
    el.innerHTML = '<span class="wy-visually-hidden">' + fin + '</span><span aria-hidden="true" data-n>' + fin + '</span>';
    if (tier() !== 'full') return;
    var n = $('[data-n]', el), r = el.getBoundingClientRect();
    if (r.top < innerHeight) return;               /* in view at load: keep the server value, no fake count */
    n.textContent = nfmt(0);
    if (!cio) cio = new IntersectionObserver(function (es) { es.forEach(function (e) {
      if (!e.isIntersecting) return; cio.unobserve(e.target);
      var node = $('[data-n]', e.target), end = +e.target.dataset.count, t0 = performance.now();
      (function step(t) { var p = Math.min(1, (t - t0) / 1200), k = 1 - Math.pow(1 - p, 4); node.textContent = nfmt(Math.round(end * k)); if (p < 1) requestAnimationFrame(step); })(t0);
    }); }, { threshold: 0.4 });
    cio.observe(el);
  };

  /* ---- marquee: second copy is aria-hidden ---- */
  M.marquee = function (section) {
    var viewport = $('.wy-marquee__viewport', section), ul = $('.wy-marquee__track', section);
    if (!ul || ul.parentNode.classList.contains('wy-marquee__run')) return;
    var run = document.createElement('div'); run.className = 'wy-marquee__run'; ul.parentNode.insertBefore(run, ul); run.appendChild(ul);
    var copies = 1; while (ul.scrollWidth * copies < viewport.clientWidth * 1.2 && copies < 6) { copies++; Array.prototype.slice.call(ul.children).forEach(function (li) { ul.appendChild(li.cloneNode(true)).setAttribute('aria-hidden', 'true'); }); }
    var dup = ul.cloneNode(true); dup.setAttribute('aria-hidden', 'true'); $$('a', dup).forEach(function (a) { a.tabIndex = -1; }); run.appendChild(dup);
    $$('li[aria-hidden] a', ul).forEach(function (a) { a.tabIndex = -1; });
  };

  /* ---- hscroll: buttons + drag (fine pointer) ---- */
  function dirSign(el) { return getComputedStyle(el).direction === 'rtl' ? -1 : 1; }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-wy-prev],[data-wy-next]'); if (!b) return;
    var wrap = b.closest('.wy-hscroll'), tr = $('.wy-hscroll__track', wrap); if (!tr) return;
    var s = (b.hasAttribute('data-wy-next') ? 1 : -1) * dirSign(tr);
    tr.scrollBy({ left: s * tr.clientWidth * 0.8, behavior: tier() === 'reduced' ? 'auto' : 'smooth' });
  });
  (function () {
    var tr, sx, sl, moved, id;
    document.addEventListener('pointerdown', function (e) {
      if (!fine || e.button !== 0 || !e.target.closest) return; var t = e.target.closest('.wy-hscroll__track'); if (!t) return;
      tr = t; sx = e.clientX; sl = t.scrollLeft; moved = false; id = e.pointerId;
    });
    document.addEventListener('pointermove', function (e) {
      if (!tr) return; var dx = e.clientX - sx;
      if (!moved && Math.abs(dx) > 6) { moved = true; tr.classList.add('is-dragging'); try { tr.setPointerCapture(id); } catch (er) {} }
      if (moved) tr.scrollLeft = sl - dx;
    });
    function end() { if (tr && moved) { var t = tr; setTimeout(function () { t.classList.remove('is-dragging'); }, 0); } tr = null; }
    document.addEventListener('pointerup', end); document.addEventListener('pointercancel', end);
    document.addEventListener('click', function (e) { if (moved && e.target.closest && e.target.closest('.wy-hscroll__track')) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
  })();

  /* ---- tilt + sheen on vehicle cards (lazy, fine pointer, full tier only) ---- */
  var tiltOK = function () { return fine && tier() === 'full'; };
  document.addEventListener('pointerover', function (e) {
    if (!tiltOK() || !e.target.closest) return; var c = e.target.closest('.wy-vcard'); if (!c || c.dataset.tilt) return; c.dataset.tilt = '1';
    var ticking = false, last;
    c.addEventListener('pointermove', function (ev) {
      last = ev; if (ticking) return; ticking = true;
      M.raf(function () { ticking = false; var r = c.getBoundingClientRect(), px = (last.clientX - r.left) / r.width, py = (last.clientY - r.top) / r.height;
        c.classList.add('is-tilting');
        c.style.setProperty('--ry', ((px - 0.5) * 12).toFixed(2) + 'deg'); c.style.setProperty('--rx', ((0.5 - py) * 12).toFixed(2) + 'deg');
        c.style.setProperty('--sx', ((px - 0.5) * r.width * 0.9).toFixed(0)); c.style.setProperty('--sy', ((py - 0.5) * r.height * 0.9).toFixed(0)); });
    });
    c.addEventListener('pointerleave', function () { c.classList.remove('is-tilting'); c.style.setProperty('--rx', '0deg'); c.style.setProperty('--ry', '0deg'); });
  });

  /* ---- magnetic buttons ---- */
  (function () {
    var els = [], t0 = 0, last;
    function collect() { els = $$('[data-motion~="magnetic"]'); t0 = performance.now(); }
    document.addEventListener('pointermove', function (e) {
      if (!tiltOK()) return; last = e; if (performance.now() - t0 > 1500) collect();
      M.raf(function () {
        els.forEach(function (el) {
          var r = el.getBoundingClientRect(); if (r.bottom < 0 || r.top > innerHeight) return;
          var cx = r.left + r.width / 2, cy = r.top + r.height / 2, dx = last.clientX - cx, dy = last.clientY - cy, d = Math.hypot(dx, dy), reach = 80 + Math.max(r.width, r.height) / 2;
          var lab = el.querySelector('.wy-btn__label');
          if (d < reach) { var k = 1 - d / reach, mx = Math.max(-6, Math.min(6, dx * 0.12 * (1 + k))), my = Math.max(-6, Math.min(6, dy * 0.12 * (1 + k)));
            el.style.translate = mx.toFixed(1) + 'px ' + my.toFixed(1) + 'px'; if (lab) lab.style.translate = (mx * 0.5).toFixed(1) + 'px ' + (my * 0.5).toFixed(1) + 'px'; el.classList.add('is-magnet'); }
          else if (el.classList.contains('is-magnet')) { el.style.translate = ''; if (lab) lab.style.translate = ''; el.classList.remove('is-magnet'); }
        });
      });
    }, { passive: true });
  })();

  /* ---- custom cursor (adornment only; system cursor stays) ---- */
  function initCursor() {
    if (!tiltOK()) return;
    var dot = document.createElement('i'), ring = document.createElement('i');
    dot.className = 'wy-cursor__dot'; ring.className = 'wy-cursor__ring'; ring.innerHTML = '<span></span>';
    [dot, ring].forEach(function (n) { n.setAttribute('aria-hidden', 'true'); document.body.appendChild(n); });
    var tx = 0, ty = 0, x = 0, y = 0, on = false, running = false;
    function loop() { x += (tx - x) * 0.18; y += (ty - y) * 0.18; ring.style.transform = 'translate3d(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px,0)'; if (Math.abs(tx - x) + Math.abs(ty - y) > 0.3) M.raf(loop); else running = false; }
    document.addEventListener('pointermove', function (e) {
      if (e.pointerType === 'touch') return;
      if (!on) { on = true; root.classList.add('wy-has-cursor'); x = tx = e.clientX; y = ty = e.clientY; }
      tx = e.clientX; ty = e.clientY; dot.style.transform = 'translate3d(' + tx + 'px,' + ty + 'px,0)';
      var t = e.target.closest ? e.target : null, card = t && t.closest('.wy-vcard'), form = t && t.closest('input,select,textarea,form,[data-lenis-prevent],dialog');
      ring.classList.toggle('is-card', !!card && !form); ring.classList.toggle('is-off', !!form);
      if (card) ring.firstChild.textContent = WY.t ? WY.t('cursor.view') : 'Voir';
      if (!running) { running = true; M.raf(loop); }
    }, { passive: true });
    document.addEventListener('pointerleave', function () { root.classList.remove('wy-has-cursor'); on = false; });
  }

  /* ---- aurora pause off-screen / hidden tab ---- */
  function initAurora() {
    var els = $$('.wy-aurora'); if (!els.length) return;
    var o = new IntersectionObserver(function (es) { es.forEach(function (e) { e.target.classList.toggle('is-paused', !e.isIntersecting); }); });
    els.forEach(function (a) { o.observe(a); });
    document.addEventListener('visibilitychange', function () { root.classList.toggle('wy-hidden', document.hidden); });
  }

  /* ---- pinned storytelling: IO sentinels set data-step (works with or without scroll-driven CSS) ---- */
  function initStory() {
    $$('[data-wy-story]').forEach(function (sec) {
      var mq = matchMedia('(min-width: 768px)');
      var pin = function () { sec.classList.toggle('is-pinned', tier() === 'full' && mq.matches); }; pin(); mq.addEventListener('change', pin);
      var steps = $$('.wy-story__steps li', sec), sents = $$('.wy-story__sentinels i', sec);
      var set = function (n) { sec.dataset.step = n; steps.forEach(function (li, i) { li.classList.toggle('is-active', i + 1 === n); li.classList.toggle('is-done', i + 1 < n); }); };
      set(1);
      var o = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) set(sents.indexOf(e.target) + 1); }); }, { rootMargin: '-50% 0px -50% 0px' });
      sents.forEach(function (s) { o.observe(s); });
      var vo = new IntersectionObserver(function (es) { es.forEach(function (e) { if (!sec.classList.contains('is-pinned')) { sec.dataset.step = 3; } }); });
      vo.observe(sec);
    });
  }

  /* ---- bottom sheet physics (pointer drag on grip/header, snap 50% / 92% / closed by velocity) ---- */
  M.sheet = function (el, opts) {
    var grips = opts.grips || [], onClose = opts.onClose, scrim = opts.scrim, state = 'open', y = 0, startY, startT, base, id, dragging = false, lastY, lastT, v = 0;
    var vh = function () { return innerHeight; };
    function snaps() { var h = el.getBoundingClientRect().height || vh() * 0.92; return { open: 0, half: Math.max(0, h - vh() * 0.5), closed: h }; }
    function set(py, anim) {
      y = py; el.style.transition = anim && tier() !== 'reduced' ? 'transform 280ms cubic-bezier(0.65,0,0.35,1)' : 'none'; el.style.transform = 'translateY(' + py + 'px)';
      if (scrim) { var h = snaps().closed; scrim.style.opacity = Math.max(0, Math.min(1, 1 - py / h)); }
    }
    function go(s) { var sn = snaps(); state = s; set(sn[s], true); if (s === 'closed') setTimeout(function () { if (state === 'closed') onClose(); }, tier() === 'reduced' ? 0 : 280); }
    function down(e) { if (innerWidth >= 768) return; if (e.target.closest('button,a,input,select')) return; dragging = true; id = e.pointerId; startY = lastY = e.clientY; startT = lastT = performance.now(); base = y; v = 0; try { e.currentTarget.setPointerCapture(id); } catch (er) {} }
    function move(e) { if (!dragging) return; var dy = e.clientY - startY; set(Math.max(0, base + dy), false); var t = performance.now(); v = (e.clientY - lastY) / Math.max(1, t - lastT); lastY = e.clientY; lastT = t; }
    function up() { if (!dragging) return; dragging = false; var sn = snaps(), order = ['open', 'half', 'closed'], cur = order.indexOf(state);
      if (v > 0.6) go(order[Math.min(2, cur + 1)]); else if (v < -0.6) go(order[Math.max(0, cur - 1)]);
      else { var best = 'open', bd = 1e9; order.forEach(function (k) { var d = Math.abs(sn[k] - y); if (d < bd) { bd = d; best = k; } }); go(best); } }
    grips.forEach(function (g) { g.addEventListener('pointerdown', down); g.addEventListener('pointermove', move); g.addEventListener('pointerup', up); g.addEventListener('pointercancel', up); });
    return { open: function () { state = 'open'; el.style.transition = 'none'; el.style.transform = 'translateY(' + snaps().closed + 'px)'; el.getBoundingClientRect(); set(0, true); },
             close: function () { go('closed'); }, reset: function () { el.style.transform = ''; el.style.transition = ''; if (scrim) scrim.style.opacity = ''; y = 0; state = 'open'; } };
  };

  /* ---- init ---- */
  function init() {
    syncThemeUI();
    var lab = $('[data-wy-motion-now]'); if (lab) lab.textContent = '→ ' + tier();
    $$('[data-wy-motion]').forEach(function (b) { var saved = null; try { saved = localStorage.getItem('wy-motion') || 'auto'; } catch (e) {} b.setAttribute('aria-pressed', String(b.dataset.wyMotion === saved)); });
    if (tier() === 'full' && window.CSS && CSS.supports('animation-timeline', 'view()')) root.classList.add('wy-sd');
    $$('[data-wy-parallax]').forEach(function (el) { el.style.setProperty('--wy-par', el.dataset.wyParallax); });
    M.scan(); initAurora(); initStory(); initCursor();
    var h = $('[data-wy-sheen]'); if (h && tier() === 'full') addEventListener('load', function () { setTimeout(function () { h.classList.add('is-sheen'); }, 300); });
    /* header: glass after 24px (sentinel) */
    var hd = $('[data-wy-header]'), sn = $('[data-wy-sentinel]');
    if (hd && sn) new IntersectionObserver(function (es) { hd.toggleAttribute('data-scrolled', !es[0].isIntersecting); }, { rootMargin: '-24px 0px 0px 0px' }).observe(sn);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
