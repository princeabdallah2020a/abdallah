/* Wotoyi theme - vehicle page: gallery + lightbox, 360 frame viewer, deep-linkable spec tabs, sticky phone CTA bar, lead dialog, recently-viewed record.
   All content is server-rendered; this file only adds behaviour. */
(function () {
  'use strict';
  var WY = window.WY || {}, U = WY.ui, M = WY.motion;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var rtl = function () { return document.documentElement.dir === 'rtl'; };

  /* gallery */
  var gal = $('[data-wy-gallery]');
  if (gal) {
    var thumbs = $$('[data-wy-thumb]', gal), main = $('[data-wy-main-img] img', gal), count = $('[data-wy-gcount]', gal), cur = 0;
    var show = function (i) {
      if (!thumbs.length || !main) return; cur = (i + thumbs.length) % thumbs.length; var t = thumbs[cur];
      main.src = t.dataset.src; main.removeAttribute('srcset'); if (t.dataset.alt != null) main.alt = t.dataset.alt;
      thumbs.forEach(function (x, k) { x.setAttribute('aria-current', String(k === cur)); }); if (count) count.textContent = (cur + 1) + ' / ' + thumbs.length;
    };
    thumbs.forEach(function (t, i) { t.addEventListener('click', function () { show(i); }); });
    var stage = $('[data-wy-stage]', gal);
    if (stage) stage.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') show(cur + (rtl() ? -1 : 1)); else if (e.key === 'ArrowLeft') show(cur + (rtl() ? 1 : -1)); });
    var lb = $('[data-wy-lightbox]'), open = $('[data-wy-lightbox-open]', gal);
    if (lb && open) {
      open.addEventListener('click', function () { var box = $('[data-wy-lightbox-img]', lb), im = document.createElement('img'); im.src = (thumbs[cur] && (thumbs[cur].dataset.full || thumbs[cur].dataset.src)) || (main && main.src); im.alt = main ? main.alt : ''; box.textContent = ''; box.appendChild(im); if (lb.showModal) lb.showModal(); });
      $$('[data-wy-lightbox-close]', lb).forEach(function (b) { b.addEventListener('click', function () { lb.close(); }); });
      lb.addEventListener('click', function (e) { if (e.target === lb) lb.close(); });
    }
    $$('[data-wy-gtab]', gal).forEach(function (b) { b.addEventListener('click', function () { $$('[data-wy-gtab]', gal).forEach(function (x) { var on = x === b; x.setAttribute('aria-selected', String(on)); var p = document.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = !on; }); }); });

    /* 360: real image sequence only (data-frames = JSON array of URLs from the server); frames load on first interaction */
    var v = $('[data-wy-viewer360]', gal);
    if (v) {
      var frames = []; try { frames = JSON.parse(v.getAttribute('data-frames') || '[]'); } catch (e) {}
      var img = $('[data-wy-v360-img]', v), deg = $('[data-wy-v360-deg]', v), prog = $('[data-wy-v360-prog]', v), loaded = false, idx = 0, acc = 0, lx = 0, drag = false, vel = 0, run = false, C = 2 * Math.PI * 19;
      if (prog) prog.style.strokeDasharray = C;
      var step = (M && M.tier() === 'lite') ? 2 : 1, n = frames.length;
      var paint = function () { if (!n) return; var i = ((idx % n) + n) % n; img.src = frames[i]; if (deg) deg.textContent = Math.round(i / n * 360) + '°'; if (prog) prog.style.strokeDashoffset = C * (1 - i / n); };
      var load = function () { if (loaded || !n) return; loaded = true; frames.forEach(function (u, k) { if (k % step) return; var p = new Image(); p.decoding = 'async'; p.src = u; }); };
      var kick = function () { if (run || (M && M.tier() === 'reduced')) return; run = true; (function loop() { if (drag || Math.abs(vel) < 0.05) { run = false; return; } acc += vel; vel *= 0.92; var s = Math.trunc(acc / 8); if (s) { idx += s * step; acc -= s * 8; paint(); } requestAnimationFrame(loop); })(); };
      v.addEventListener('pointerdown', function (e) { if (e.target.closest('button')) return; load(); drag = true; lx = e.clientX; vel = 0; v.setPointerCapture(e.pointerId); v.classList.add('is-drag'); });
      v.addEventListener('pointermove', function (e) { if (!drag) return; var dx = (e.clientX - lx) * (rtl() ? -1 : 1); lx = e.clientX; vel = dx; acc += dx; var s = Math.trunc(acc / 8); if (s) { idx += s * step; acc -= s * 8; paint(); } });
      var end = function () { if (!drag) return; drag = false; v.classList.remove('is-drag'); kick(); };
      v.addEventListener('pointerup', end); v.addEventListener('pointercancel', end);
      v.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { load(); idx += step; paint(); } else if (e.key === 'ArrowLeft') { load(); idx -= step; paint(); } });
      var spin = $('[data-wy-v360-spin]', v); if (spin) spin.addEventListener('click', function () { load(); if (M && M.tier() === 'reduced') { idx += Math.round(n / 4); paint(); return; } vel = 24; kick(); });
      var fs = $('[data-wy-v360-fs]', v); if (fs) fs.addEventListener('click', function () { if (v.requestFullscreen) v.requestFullscreen().catch(function () {}); });
      /* preload when scrolled into view and idle */
      if ('IntersectionObserver' in window) new IntersectionObserver(function (es, o) { if (es[0].isIntersecting) { (window.requestIdleCallback || setTimeout)(load); o.disconnect(); } }).observe(v);
      paint();
    }
  }

  /* spec tabs: panels are server-rendered (SEO); JS only toggles hidden + hash */
  var list = $('[data-wy-tablist]');
  if (list) {
    var tabs = $$('[role=tab]', list);
    var act = function (id, focus) {
      tabs.forEach(function (b) { var on = b.dataset.tab === id; b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1; if (on && focus) b.focus(); });
      $$('[role=tabpanel]', $('[data-wy-tabpanels]')).forEach(function (p) { p.hidden = p.id !== 'tp-' + id; });
      try { history.replaceState(null, '', '#specs-' + id); } catch (e) {}
    };
    tabs.forEach(function (b) { b.addEventListener('click', function () { act(b.dataset.tab); }); });
    list.addEventListener('keydown', function (e) {
      var b = e.target.closest('[role=tab]'); if (!b) return; var i = tabs.indexOf(b), nx = null, r = rtl();
      if (e.key === 'ArrowRight') nx = i + (r ? -1 : 1); else if (e.key === 'ArrowLeft') nx = i + (r ? 1 : -1); else if (e.key === 'Home') nx = 0; else if (e.key === 'End') nx = tabs.length - 1;
      if (nx === null) return; e.preventDefault(); act(tabs[(nx + tabs.length) % tabs.length].dataset.tab, true);
    });
    var h = (location.hash || '').replace('#specs-', ''); if (h && tabs.some(function (b) { return b.dataset.tab === h; })) { act(h); setTimeout(function () { list.scrollIntoView(); }, 50); }
  }

  /* sticky phone bar: shown once the title block leaves the viewport */
  var bar = $('[data-wy-ctabar]'), tb = $('[data-wy-titleblock]');
  if (bar && tb && 'IntersectionObserver' in window) new IntersectionObserver(function (es) {
    var out = !es[0].isIntersecting && es[0].boundingClientRect.top < 0; bar.hidden = false; bar.classList.toggle('is-in', out); document.documentElement.classList.toggle('wy-has-ctabar', out);
    if (WY.fabRemount) setTimeout(WY.fabRemount, 320);
  }).observe(tb);

  /* lead dialog: single-use token (GET /leads/token?vehicle=<uuid>, >= min_age s) then POST /leads as JSON. A fresh token is fetched on every open and after every failure. */
  var dlg = $('[data-wy-lead]');
  if (dlg && dlg.showModal && window.fetch) {
    var form = $('[data-wy-lead-form]', dlg), msg = $('[data-wy-lead-msg]', dlg), send = $('[data-wy-lead-send]', dlg);
    var ref = dlg.getAttribute('data-wy-lead-ref'), tok = null, tokAt = 0, minAge = 3, busy = false;
    var say = function (k, bad) { msg.textContent = dlg.getAttribute('data-m-' + k) || ''; msg.classList.toggle('wy-error', !!bad); };
    var getToken = function () {
      tok = null; send.disabled = true;
      return fetch(dlg.getAttribute('data-wy-token-url') + '?vehicle=' + encodeURIComponent(ref), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
        .then(function (r) { if (!r.ok) throw 0; return r.json(); })
        .then(function (j) { tok = j.token; tokAt = Date.now(); minAge = +j.min_age || 3; send.disabled = false; })
        .catch(function () { send.disabled = false; });
    };
    $$('[data-wy-lead-open]').forEach(function (b) { b.hidden = false; b.addEventListener('click', function () { say('', false); msg.textContent = ''; dlg.showModal(); getToken(); }); });
    $$('[data-wy-lead-close]', dlg).forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); if (busy) return;
      var f = form.elements, phone = f.phone.value.trim(), email = f.email.value.trim();
      if (!phone && !email) { say('contact', true); return; }
      if (!f.consent.checked) { say('consent', true); return; }
      busy = true; send.disabled = true;
      var go = function () {
        if (!tok) return getToken().then(go2);
        var wait = tokAt + (minAge * 1000) + 300 - Date.now();
        if (wait > 0) { say('wait', false); return new Promise(function (r) { setTimeout(r, wait); }).then(go2); }
        return go2();
      };
      var go2 = function () {
        if (!tok) { busy = false; send.disabled = false; say('fail', true); return; }
        var body = { vehicle: ref, token: tok, name: f.name.value.trim(), phone: phone, email: email, message: f.message.value.trim(), website: f.website.value, consent: true, locale: dlg.getAttribute('data-wy-locale') || 'fr', channel: 'web' };
        tok = null;
        return fetch(dlg.getAttribute('data-wy-post-url'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) })
          .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { s: r.status, j: j }; }); })
          .then(function (x) {
            busy = false;
            if (x.s === 201) { say('ok', false); form.reset(); send.hidden = true; if (U && U.toast) U.toast(dlg.getAttribute('data-m-ok')); return; }
            say(x.s === 429 ? 'limit' : (x.s === 422 ? 'invalid' : 'fail'), true); getToken();
          });
      };
      go().catch(function () { busy = false; send.disabled = false; say('fail', true); });
    });
    dlg.addEventListener('close', function () { send.hidden = false; tok = null; });
  }

  /* recently viewed */
  var rec = $('[data-wy-record]');
  if (rec && U) { try { U.recentWrite(JSON.parse(rec.getAttribute('data-wy-record'))); } catch (e) {} }
})();
