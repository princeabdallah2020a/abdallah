/* Wotoyi theme - home behaviours: dependent model select, marquee, animated counters, recently viewed. */
(function () {
  'use strict';
  var WY = window.WY || {}, M = WY.motion;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* make -> model (options come from the server as JSON in data-wy-models; nothing is invented client side) */
  var form = $('[data-wy-quicksearch]');
  if (form) {
    var make = $('[data-wy-make]', form), model = $('[data-wy-model]', form), map = {};
    try { map = JSON.parse(form.getAttribute('data-wy-models') || '{}'); } catch (e) {}
    if (make && model) {
      var fill = function () {
        var list = map[make.value] || [], keep = model.value;
        model.innerHTML = '';
        var o0 = document.createElement('option'); o0.value = ''; o0.textContent = model.getAttribute('data-all') || ''; model.appendChild(o0);
        list.forEach(function (m) { var o = document.createElement('option'); o.value = m; o.textContent = m; if (m === keep) o.selected = true; model.appendChild(o); });
        model.disabled = !list.length;
      };
      make.addEventListener('change', fill); fill();
    }
    /* offer toggle: rental shows the daily budget label when the server supplied one */
    $$('input[name="offer"]', form).forEach(function (r) { r.addEventListener('change', function () { form.dataset.offer = r.value; }); });
  }

  if (M) {
    $$('[data-wy-marquee]').forEach(function (s) { M.marquee(s); });
    $$('[data-wy-counter]').forEach(function (el) { var n = parseInt(el.getAttribute('data-wy-counter'), 10); if (!isNaN(n)) M.counter(el, n); });
  }

  /* recently viewed: rendered with DOM APIs from localStorage (never innerHTML) */
  var sec = $('[data-wy-section="recent"]');
  if (sec && WY.ui) {
    var items = WY.ui.recentRead().filter(function (x) { return x && typeof x.url === 'string' && /^(https?:)?\//.test(x.url) && typeof x.title === 'string'; });
    var track = $('[data-wy-recent]', sec);
    if (items.length && track) {
      items.forEach(function (it) {
        var a = document.createElement('a'); a.className = 'wy-vcard wy-vcard--compact'; a.href = it.url;
        var m = document.createElement('div'); m.className = 'wy-vcard__media';
        if (it.img && /^(https?:)?\//.test(it.img)) { var im = document.createElement('img'); im.src = it.img; im.alt = ''; im.loading = 'lazy'; im.decoding = 'async'; im.width = 660; im.height = 260; m.appendChild(im); }
        var b = document.createElement('div'); b.className = 'wy-vcard__body';
        var t = document.createElement('strong'); t.className = 'wy-vcard__title'; t.textContent = it.title; b.appendChild(t);
        if (it.price) { var p = document.createElement('span'); p.className = 'wy-price'; p.textContent = it.price; b.appendChild(p); }
        a.appendChild(m); a.appendChild(b); track.appendChild(a);
      });
      sec.hidden = false;
    }
  }
})();
