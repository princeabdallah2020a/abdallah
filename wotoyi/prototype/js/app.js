/* Wotoyi prototype - shared UI (cards, favourites, overlays, AI panel, toasts). Vanilla JS, no dependencies. */
(function () {
  'use strict';
  var WY = window.WY = window.WY || {};
  var D = WY.D, t = function (k, v) { return WY.t(k, v); };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var tier = function () { return document.documentElement.dataset.motion; };
  var U = WY.ui = {};

  /* ---- helpers ---- */
  WY.vehicle = function (id) { return D.vehicles.filter(function (v) { return v.id === +id; })[0]; };
  WY.dealer = function (id) { return D.dealers.filter(function (d) { return d.id === id; })[0]; };
  WY.name = function (v) { return v.mk + ' ' + v.md; };
  WY.icon = function (n, cls) { return '<svg class="wy-i ' + (cls || '') + '" aria-hidden="true"><use href="#i-' + n + '"/></svg>'; };
  WY.esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  U.badge = function (kind) {
    var m = { demo: ['demo', 'demo.badge'], sponsored: ['sponsored', 'badge.sponsored'], inspected: ['inspected', 'badge.inspected', 'clipboard'], verified: ['verified', 'badge.verified', 'shield'], venant: ['venant', 'badge.venant'], cleared: ['cleared', 'badge.cleared', 'check'], neuf: ['cleared', 'cond.neuf'] }[kind];
    return '<span class="wy-badge wy-badge--' + m[0] + '">' + (m[2] ? WY.icon(m[2]) : '') + t(m[1]) + '</span>';
  };
  U.badges = function (v, full) {
    var o = [U.badge('demo')];
    if (v.spons) o.push(U.badge('sponsored'));
    if (v.insp) o.push(U.badge('inspected'));
    if (full && WY.dealer(v.dealer).verified) o.push(U.badge('verified'));
    if (v.cond === 'venant') o.push(U.badge('venant')); else if (full && v.customs === 'cleared') o.push(U.badge('cleared'));
    return o.join('');
  };
  U.priceHTML = function (v, big) {
    var h = '';
    if (v.price) h += '<span class="wy-price' + (big ? ' wy-price--l' : '') + '">' + WY.price(v.price) + '</span>';
    if (v.rent) h += '<span class="wy-price wy-price--rent' + (big ? ' wy-price--l2' : '') + '">' + t('price.rentday', { p: WY.price(v.rent.day) }) + '</span>';
    return h;
  };

  U.rentPrice = function (v) { return '<span class="wy-price">' + t('price.rentday', { p: WY.price(v.rent.day) }) + '</span><span class="wy-price wy-price--sub">' + t('price.rentmonth', { p: WY.price(v.rent.month) }) + '</span>'; };

  /* ---- favourites ---- */
  var favs = []; try { favs = JSON.parse(localStorage.getItem('wy-favs') || '[]'); } catch (e) {}
  function saveFavs() { try { localStorage.setItem('wy-favs', JSON.stringify(favs)); } catch (e) {} paintFavs(); }
  function paintFavs() {
    $$('[data-wy-favs-count]').forEach(function (n) { n.textContent = favs.length; n.hidden = !favs.length; });
    $$('[data-wy-fav]').forEach(function (b) { var on = favs.indexOf(+b.dataset.wyFav) > -1; b.setAttribute('aria-pressed', String(on)); b.setAttribute('aria-label', t(on ? 'fav.remove' : 'fav.add')); });
  }
  U.paintFavs = paintFavs;
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-wy-fav]');
    if (b) { e.preventDefault(); var id = +b.dataset.wyFav, i = favs.indexOf(id); if (i > -1) favs.splice(i, 1); else { favs.push(id); b.classList.remove('is-pop'); void b.offsetWidth; b.classList.add('is-pop'); } saveFavs(); return; }
    if (e.target.closest && e.target.closest('[data-wy-favs-open]')) U.toast(t('fav.count', { n: favs.length }));
  });
  U.favs = function () { return favs; };

  /* ---- card ---- */
  U.card = function (v, o) {
    o = o || {}; var dl = WY.dealer(v.dealer), name = WY.name(v), href = 'vehicle.html?id=' + v.id;
    return '<article class="wy-vcard' + (o.cls ? ' ' + o.cls : '') + '" data-id="' + v.id + '">' +
      '<div class="wy-vcard__sheen" aria-hidden="true"></div>' +
      '<div class="wy-vcard__media" style="--vt:wy-v-' + v.id + '"><img src="' + D.img + v.img + '.svg" width="660" height="260" alt="' + WY.esc(t('img.alt', { name: name })) + '" loading="' + (o.eager ? 'eager' : 'lazy') + '" decoding="async"><div class="wy-vcard__badges">' + U.badges(v) + '</div></div>' +
      '<button class="wy-fav" type="button" data-wy-fav="' + v.id + '" aria-pressed="false" aria-label="' + t('fav.add') + '">' + WY.icon('heart') + '<span class="wy-fav__p" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span></button>' +
      '<div class="wy-vcard__body"><h3 class="wy-vcard__title"><a href="' + href + '">' + WY.esc(name) + '</a></h3><p class="wy-vcard__ver"><bdi>' + WY.esc(v.ver) + '</bdi></p>' +
      '<ul class="wy-vcard__meta"><li>' + v.yr + '</li><li>' + (v.cond === 'neuf' ? t('cond.neuf') : WY.km(v.km)) + '</li><li>' + t('fuel.' + v.fuel) + '</li><li>' + t('trans.' + v.tr) + '</li></ul>' +
      '<div class="wy-vcard__prices">' + (o.rent ? U.rentPrice(v) : U.priceHTML(v)) + '</div>' +
      '<p class="wy-vcard__loc">' + WY.icon('pin') + '<span>' + t('city.' + v.city) + ' · ' + WY.esc(dl.name) + '</span></p></div></article>';
  };
  U.cardsInto = function (el, list, o) { el.innerHTML = list.map(function (v) { return U.card(v, o); }).join(''); paintFavs(); U.dedupeVT(); };
  /* view-transition-name must be unique per page: keep only the first card of a given vehicle */
  U.dedupeVT = function () { var seen = {}; $$('.wy-vcard__media[style*="--vt"]').forEach(function (m) { var n = m.style.getPropertyValue('--vt'); if (seen[n]) m.style.removeProperty('--vt'); else seen[n] = 1; }); };

  /* ---- toast ---- */
  U.toast = function (msg) {
    var box = $('[data-wy-toasts]'); if (!box) return; var n = document.createElement('div'); n.className = 'wy-toast'; n.innerHTML = msg; box.appendChild(n);
    requestAnimationFrame(function () { n.classList.add('is-in'); });
    setTimeout(function () { n.classList.remove('is-in'); setTimeout(function () { n.remove(); }, 300); }, 3600);
  };
  document.addEventListener('click', function (e) {
    var d = e.target.closest && e.target.closest('[data-demo-link]'); if (d) { e.preventDefault(); U.toast(t('demo.outofscope')); return; }
    var k = e.target.closest && e.target.closest('[data-wy-toast]'); if (k) { e.preventDefault(); U.toast(t(k.dataset.wyToast)); }
  });

  /* ---- overlays (drawer / sheet): scrim, scroll lock, focus trap, Esc ---- */
  var scrim = $('[data-wy-scrim]'), openStack = [];
  var FOC = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea,[tabindex]:not([tabindex="-1"])';
  U.overlay = function (el, o) {
    o = o || {}; var opener, api = { el: el, isOpen: false };
    api.open = function (from) {
      if (api.isOpen) return; api.isOpen = true; opener = from || document.activeElement;
      if (!o.noHidden) el.hidden = false;
      if (scrim && !o.noScrim) { scrim.hidden = false; scrim.style.opacity = ''; requestAnimationFrame(function () { scrim.classList.add('is-in'); }); }
      void el.offsetWidth; el.classList.add('is-open'); document.documentElement.classList.add('wy-lock');
      openStack.push(api);
      if (o.onOpen) o.onOpen();
      setTimeout(function () { var f = $(o.focus || FOC, el); if (f) f.focus({ preventScroll: true }); }, 30);
    };
    api.close = function (silent) {
      if (!api.isOpen) return; api.isOpen = false; el.classList.remove('is-open'); openStack.splice(openStack.indexOf(api), 1);
      if (!openStack.length) { document.documentElement.classList.remove('wy-lock'); if (scrim) { scrim.classList.remove('is-in'); setTimeout(function () { if (!openStack.length) scrim.hidden = true; }, 280); } }
      setTimeout(function () { if (!api.isOpen && !o.noHidden) el.hidden = true; }, tier() === 'reduced' ? 0 : 300);
      if (o.onClose && !silent) o.onClose();
      if (opener && opener.focus) opener.focus({ preventScroll: true });
    };
    $$('[data-wy-close]', el).forEach(function (b) { b.addEventListener('click', function () { api.close(); }); });
    return api;
  };
  document.addEventListener('keydown', function (e) {
    var top = openStack[openStack.length - 1]; if (!top) return;
    if (e.key === 'Escape') { top.close(); return; }
    if (e.key === 'Tab' && !(top.el.dataset.trap === 'off')) {
      var f = $$(FOC, top.el).filter(function (n) { return n.offsetParent !== null || n === document.activeElement; }); if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  if (scrim) scrim.addEventListener('click', function () { var top = openStack[openStack.length - 1]; if (top) top.close(); });

  /* menu drawer */
  var menu = $('[data-wy-menu]'); if (menu) { var mo = U.overlay(menu, { onClose: function () { var b = $('[data-wy-menu-open]'); if (b) b.setAttribute('aria-expanded', 'false'); } });
    $$('[data-wy-menu-open]').forEach(function (b) { b.addEventListener('click', function () { mo.open(b); b.setAttribute('aria-expanded', 'true'); }); });
    matchMedia('(min-width: 1200px)').addEventListener('change', function (e) { if (e.matches && mo.isOpen) mo.close(); }); }

  /* ---- natural-language parser (rule-based: honest fallback of the AI gateway) ---- */
  U.parseNL = function (q) {
    var raw = q.toLowerCase(), s = raw.normalize('NFD').replace(/[̀-ͯ]/g, ''), p = {}, has = function (re) { return re.test(s) || re.test(raw); };
    if (has(/\bsuv\b|suv/)) p.body = 'suv'; else if (has(/berline|sedan|saloon|سيدان/)) p.body = 'berline'; else if (has(/pick-?up|بيك/)) p.body = 'pickup'; else if (has(/\b4x4\b|دفع رباعي/)) p.body = '4x4'; else if (has(/citadine|hatchback|city car/)) p.body = 'citadine';
    if (has(/diesel|gasoil|ديزل/)) p.fuel = 'diesel'; else if (has(/hybride|hybrid|هجين/)) p.fuel = 'hybride'; else if (has(/electrique|electric|كهربائي/)) p.fuel = 'electrique'; else if (has(/essence|petrol|gasoline|بنزين/)) p.fuel = 'essence';
    if (has(/automatique|automatic|\bauto\b|اوتوماتيك|أوتوماتيك/)) p.tr = 'auto'; else if (has(/manuelle|manual|يدوي/)) p.tr = 'manuelle';
    if (has(/dakar|داكار/)) p.city = 'dakar'; else if (has(/thies|تييس/)) p.city = 'thies'; else if (has(/saint[- ]louis|سان لوي/)) p.city = 'saintlouis'; else if (has(/mbour|مبور/)) p.city = 'mbour';
    if (has(/location|louer|\brent|hire|إيجار|ايجار/)) p.offer = 'rent';
    var m = s.match(/(?:moins de|max(?:imum)?|under|below|jusqu'?a|inferieur a|<)\s*(\d+(?:[.,]\d+)?)\s*(millions?|m\b)/) || raw.match(/(\d+(?:[.,]\d+)?)\s*مليون/);
    if (m) p.max = Math.round(parseFloat(m[1].replace(',', '.')) * 1e6);
    var y = s.match(/(?:apres|depuis|after|since|from)\s*(20\d\d)/); if (y) p.ymin = y[1];
    D.vehicles.forEach(function (v) { if (s.indexOf(v.mk.toLowerCase()) > -1) p.make = v.mk; });
    return p;
  };
  U.paramsToURL = function (p) { var u = new URLSearchParams(); Object.keys(p).forEach(function (k) { u.set(k, p[k]); }); return 'search.html' + (u.toString() ? '?' + u.toString() : ''); };
  U.paramsSummary = function (p) {
    var o = []; if (p.offer === 'rent') o.push(t('offer.rent')); if (p.make) o.push(p.make); if (p.body) o.push(t('body.' + p.body)); if (p.fuel) o.push(t('fuel.' + p.fuel)); if (p.tr) o.push(t('trans.' + p.tr));
    if (p.max) o.push('≤ ' + WY.price(p.max)); if (p.ymin) o.push('≥ ' + p.ymin); if (p.city) o.push(t('city.' + p.city)); return o;
  };

  /* ---- AI panel ---- */
  var ai = $('[data-wy-ai]'), aiO;
  if (ai) {
    aiO = U.overlay(ai, { focus: '#ai-input' });
    var promptsBox = $('[data-wy-ai-prompts]', ai), log = $('[data-wy-ai-log]', ai), form = $('[data-wy-ai-form]', ai), inp = $('#ai-input');
    var PR = [['ai.p.fr', 'fr'], ['ai.p.en', 'en'], ['ai.p.ar', 'ar']];
    var chips = function () { return PR.map(function (p) { return '<button class="wy-chip" type="button" data-wy-prompt="' + p[0] + '" lang="' + p[1] + '">' + WY.esc(t(p[0])) + '</button>'; }).join('') + '<span class="wy-chip wy-chip--disabled" aria-disabled="true">' + t('ai.p.wo') + '</span>'; };
    U.aiChips = chips;
    var paint = function () { promptsBox.innerHTML = chips(); var sb = $('[data-wy-ai-showprompts]'); if (sb) sb.innerHTML = chips(); };
    paint(); document.addEventListener('wy:lang', paint);
    var say = function (cls, html) { var d = document.createElement('div'); d.className = 'wy-ai-msg wy-ai-msg--' + cls; d.innerHTML = html; log.appendChild(d); log.scrollTop = log.scrollHeight; ai.querySelector('.wy-drawer__scroll').scrollTop = 1e6; };
    var ask = function (q) {
      if (!q.trim()) return; say('me', WY.esc(q)); var p = U.parseNL(q), sum = U.paramsSummary(p);
      if (!sum.length) say('bot', WY.esc(t('ai.nomatch')));
      else say('bot', WY.esc(t('ai.understood')) + '<ul class="wy-chips wy-chips--tags">' + sum.map(function (x) { return '<li class="wy-chip">' + x + '</li>'; }).join('') + '</ul><a class="wy-btn wy-btn--primary wy-btn--sm" href="' + U.paramsToURL(p) + '"><span class="wy-btn__label">' + t('ai.see') + '</span></a>');
    };
    form.addEventListener('submit', function (e) { e.preventDefault(); ask(inp.value); inp.value = ''; });
    U.askAI = function (q) { aiO.open(); inp.value = ''; ask(q); };
    document.addEventListener('click', function (e) {
      var o = e.target.closest && e.target.closest('[data-wy-ai-open]'); if (o) { aiO.open(o); return; }
      var pr = e.target.closest && e.target.closest('[data-wy-prompt]'); if (pr) { if (!aiO.isOpen) aiO.open(pr); ask(t(pr.dataset.wyPrompt)); }
    });
  }

  /* ---- misc ---- */
  paintFavs(); document.addEventListener('wy:lang', paintFavs);
  var path = location.pathname.split('/').pop() || 'index.html';
  $$('[data-wy-bottom-nav] a').forEach(function (a) { if (a.getAttribute('href') === path) a.setAttribute('aria-current', 'page'); });
  U.recent = function (id) { var r = []; try { r = JSON.parse(localStorage.getItem('wy-recent') || '[]'); } catch (e) {} if (id) { r = [+id].concat(r.filter(function (x) { return x !== +id; })).slice(0, 8); try { localStorage.setItem('wy-recent', JSON.stringify(r)); } catch (e) {} } return r; };
  U.dealerOpen = function (d) { var h = new Date().getUTCHours(), day = new Date().getUTCDay(); return day !== 0 && h >= d.hours[0] && h < d.hours[1]; }; /* Dakar = UTC+0 */

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
})();
