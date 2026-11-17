/* Vehicle page: gallery, 360 placeholder viewer, grouped spec tabs (empty groups hidden), contact card + sticky bar, similar. Demo data. */
(function () {
  'use strict';
  var WY = window.WY, D = WY.D, U = WY.ui, M = WY.motion, t = function (k, v) { return WY.t(k, v); };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = WY.esc, id = new URLSearchParams(location.search).get('id');
  var v = WY.vehicle(id) || D.vehicles[0], dl = WY.dealer(v.dealer), name = WY.name(v), ref = 'WY-DEMO-' + String(v.id).padStart(4, '0');
  U.recent(v.id);
  var VIEWS = [{ s: 1, o: '50% 60%' }, { s: 2.2, o: '14% 62%' }, { s: 2.2, o: '86% 62%' }, { s: 2.7, o: '27% 86%' }], cur = 0;
  var imgSrc = D.img + v.img + '.svg';

  function build() {
    document.title = name + ' ' + v.ver + ' · Wotoyi';
    $('[data-wy-bc]').innerHTML = '<li><a href="index.html">' + t('bc.home') + '</a></li><li><a href="search.html?make=' + encodeURIComponent(v.mk) + '">' + esc(v.mk) + '</a></li><li><a href="search.html?make=' + encodeURIComponent(v.mk) + '&model=' + encodeURIComponent(v.md) + '">' + esc(v.md) + '</a></li><li aria-current="page">' + esc(name + ' ' + v.ver) + '</li>';
    $('[data-wy-media-badges]').innerHTML = U.badge('demo') + '<span class="wy-badge">' + t('v.illus') + '</span>';
    paintView();
    $('[data-wy-thumbs]').innerHTML = VIEWS.map(function (vw, i) { return '<button class="wy-thumb" type="button" role="listitem" data-view="' + i + '" aria-label="' + t('v.view', { n: i + 1 }) + '" aria-current="' + (i === cur) + '"><span class="wy-thumb__c"><img src="' + imgSrc + '" alt="" style="transform:scale(' + vw.s + ');transform-origin:' + vw.o + '" width="660" height="260"></span></button>'; }).join('');
    $('[data-wy-title]').innerHTML = esc(name) + ' <span class="wy-title__ver">' + esc(v.ver) + ' · ' + v.yr + '</span>';
    $('[data-wy-badges]').innerHTML = U.badges(v, true);
    $('[data-wy-prices]').innerHTML = (v.price ? '<div class="wy-price-block"><span class="wy-price wy-price--l">' + WY.price(v.price) + '</span>' + (v.neg ? '<span class="wy-caption wy-muted">' + t('v.neg') + '</span>' : '') + '</div>' : '') +
      (v.rent ? '<div class="wy-price-block"><span class="wy-caption wy-muted">' + t('v.rental') + '</span><span class="wy-price wy-price--l2">' + t('price.rentday', { p: WY.price(v.rent.day) }) + ' · ' + t('price.rentmonth', { p: WY.price(v.rent.month) }) + '</span></div>' : '');
    var kf = [['calendar', v.yr, 'f.year'], ['gauge', v.cond === 'neuf' ? t('cond.neuf') : WY.km(v.km), 'f.km'], ['fuel', t('fuel.' + v.fuel), 'f.fuel'], ['shifter', t('trans.' + v.tr), 'f.trans'], ['steering', t('drive.' + v.drive), 'f.drive'], ['pin', t('city.' + v.city), 'f.city']];
    $('[data-wy-keyfacts]').innerHTML = kf.map(function (k) { return '<li>' + WY.icon(k[0]) + '<span class="wy-keyfacts__v">' + k[1] + '</span><span class="wy-caption wy-muted">' + t(k[2]) + '</span></li>'; }).join('');
    var card = contactHTML(); $('[data-wy-contact]').innerHTML = card; $('[data-wy-contact-slot]').innerHTML = '<div class="wy-contact">' + card + '</div>';
    specs(); describe(); inspection(); dealerBlock(); similar(); U.paintFavs();
  }
  function paintView() {
    var vw = VIEWS[cur], el = $('[data-wy-main-img]');
    el.innerHTML = '<img src="' + imgSrc + '" alt="' + esc(t('img.alt', { name: name })) + '" width="660" height="260" fetchpriority="high" decoding="async" style="--vt:wy-v-' + v.id + ';transform:scale(' + vw.s + ');transform-origin:' + vw.o + '">';
    el.style.setProperty('--vt', 'wy-v-' + v.id); $('[data-wy-gcount]').textContent = (cur + 1) + ' / ' + VIEWS.length;
    $$('[data-view]').forEach(function (b) { b.setAttribute('aria-current', String(+b.dataset.view === cur)); });
  }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-view]'); if (b) { cur = +b.dataset.view; paintView(); } });
  $('[data-wy-stage]').tabIndex = 0;
  $('[data-wy-stage]').addEventListener('keydown', function (e) { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { cur = (cur + (e.key === 'ArrowRight' ? 1 : -1) * (document.dir === 'rtl' || document.documentElement.dir === 'rtl' ? -1 : 1) + VIEWS.length) % VIEWS.length; paintView(); } });
  var lb = $('[data-wy-lightbox]');
  $('[data-wy-lightbox-open]').addEventListener('click', function () { var vw = VIEWS[cur]; $('[data-wy-lightbox-img]').innerHTML = '<img src="' + imgSrc + '" alt="' + esc(t('img.alt', { name: name })) + '" style="transform:scale(' + vw.s + ');transform-origin:' + vw.o + '">'; lb.showModal(); });
  $('[data-wy-lightbox-close]').addEventListener('click', function () { lb.close(); });
  lb.addEventListener('click', function (e) { if (e.target === lb) lb.close(); });

  /* gallery tabs */
  $$('[data-wy-gtab]').forEach(function (b) { b.addEventListener('click', function () { $$('[data-wy-gtab]').forEach(function (x) { var on = x === b; x.setAttribute('aria-selected', String(on)); $('#gp-' + x.dataset.wyGtab).hidden = !on; }); }); });

  /* ---- contact ---- */
  function contactHTML() {
    var prices = (v.price ? '<p class="wy-price wy-price--l">' + WY.price(v.price) + '</p>' : '') + (v.rent ? '<p class="wy-price">' + t('price.rentday', { p: WY.price(v.rent.day) }) + '</p>' : '');
    var open = U.dealerOpen(dl);
    return prices + '<div class="wy-dealer-mini"><span class="wy-dcard__logo" aria-hidden="true">' + dl.initials + '</span><div><strong>' + esc(dl.name) + '</strong><span class="wy-caption wy-muted">' + t('city.' + dl.city) + ' · <span class="' + (open ? 'wy-open' : '') + '">' + t(open ? 'deal.open' : 'deal.closed') + '</span></span></div>' + (dl.verified ? U.badge('verified') : '') + '</div>' +
      '<a class="wy-btn wy-btn--whatsapp wy-btn--lg wy-btn--block" href="#" data-wy-wa>' + WY.icon('whatsapp') + '<span>WhatsApp</span></a>' +
      '<div class="wy-contact__row"><button class="wy-btn wy-btn--ghost" type="button" data-wy-call>' + WY.icon('phone') + t('v.call') + '</button><button class="wy-btn wy-btn--ghost" type="button" data-wy-lead-open>' + WY.icon('message') + t('v.message') + '</button></div>' +
      '<button class="wy-btn wy-btn--secondary wy-btn--block" type="button" data-wy-toast="v.insptoast">' + WY.icon('clipboard') + t('v.reqinsp') + '</button>' +
      '<button class="wy-btn wy-btn--ghost wy-btn--block" type="button" data-wy-ai-open>' + WY.icon('sparkle') + t('v.askai') + '</button>' +
      '<div class="wy-contact__tools"><button class="wy-btn wy-btn--icon wy-btn--ghost wy-fav wy-fav--plain" type="button" data-wy-fav="' + v.id + '" aria-pressed="false">' + WY.icon('heart') + '</button><button class="wy-btn wy-btn--icon wy-btn--ghost" type="button" data-wy-share aria-label="' + t('v.share') + '">' + WY.icon('share') + '</button><button class="wy-btn wy-btn--icon wy-btn--ghost" type="button" data-wy-toast="v.comparetoast" aria-label="' + t('v.compare') + '">' + WY.icon('compare') + '</button></div>' +
      '<p class="wy-caption wy-muted">' + t('v.ref') + ' ' + ref + '</p>';
  }
  var msg = function () { return t('wa.msg', { name: name + ' ' + v.ver + ' ' + v.yr, ref: ref }); };
  document.addEventListener('click', function (e) {
    var w = e.target.closest('[data-wy-wa]'); if (w) { e.preventDefault(); U.toast(t('wa.toast') + '<br><em>« ' + esc(msg()) + ' »</em>'); }
    if (e.target.closest('[data-wy-call]')) U.toast(t('call.toast'));
    if (e.target.closest('[data-wy-share]')) { var url = location.href; if (navigator.share) navigator.share({ title: name, url: url }).catch(function () {}); else if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () { U.toast(t('share.copied')); }, function () { U.toast(url); }); else U.toast(url); }
    if (e.target.closest('[data-wy-lead-open]')) { $('[data-wy-lead-msg]').value = msg(); $('[data-wy-lead]').showModal(); }
    if (e.target.closest('[data-wy-lead-close]')) $('[data-wy-lead]').close();
  });
  $('[data-wy-lead-form]').addEventListener('submit', function (e) { e.preventDefault(); $('[data-wy-lead]').close(); U.toast(t('lead.toast')); });

  /* ---- specs ---- */
  function row(k, val) { return val == null || val === '' ? '' : '<div><dt>' + t(k) + '</dt><dd>' + val + '</dd></div>'; }
  function yn(b) { return b ? t('yes') : null; }
  function specs() {
    var s = v.s || {}, h = v.hist || {}, c = v.com || {}, cons = v.cons || {};
    var G = [];
    G.push({ id: 'apercu', t: 'sg.overview', html: '<dl class="wy-spec-table">' + row('f.make', esc(v.mk)) + row('f.model', esc(v.md)) + row('f.version', esc(v.ver)) + row('f.year', v.yr) + row('f.body', t('body.' + v.body)) + row('spec.colour', t('col.' + v.col)) + row('f.cond', t('cond.' + v.cond)) + row('f.origin', esc(v.origin)) + row('f.customs', t('customs.' + v.customs)) + '</dl><p class="wy-caption wy-muted">' + t('sg.privacy') + '</p>' });
    var eng = row('f.fuel', t('fuel.' + v.fuel)) + row('spec.cc', s.cc && WY.nf(s.cc) + ' cm³') + row('spec.cyl', s.cyl) + row('spec.hp', s.hp && s.hp + ' ' + t('unit.hp')) + row('spec.torque', s.torque && s.torque + ' Nm') + row('f.trans', t('trans.' + v.tr)) + row('spec.gears', s.gears) + row('f.drive', t('drive.' + v.drive));
    if (s.cc || s.hp || s.torque || s.gears) G.push({ id: 'moteur', t: 'sg.engine', html: '<dl class="wy-spec-table">' + eng + '</dl>' });
    if (cons.euro || cons.comb) G.push({ id: 'conso', t: 'sg.cons', html: '<dl class="wy-spec-table">' + row('spec.euro', cons.euro) + row('spec.comb', cons.comb && cons.comb + ' L/100 km') + '</dl>' });
    G.push({ id: 'dimensions', t: 'sg.dims', html: '<dl class="wy-spec-table">' + row('spec.length', s.length && WY.nf(s.length) + ' mm') + row('spec.width', s.width && WY.nf(s.width) + ' mm') + row('spec.height', s.height && WY.nf(s.height) + ' mm') + row('spec.wheelbase', s.wheelbase && WY.nf(s.wheelbase) + ' mm') + row('spec.tank', s.tank && s.tank + ' L') + row('f.seats', v.seats) + row('f.doors', v.doors) + '</dl>' });
    if (v.eq && v.eq.length) { var cats = ['comfort', 'safety', 'media', 'exterior', 'interior'];
      G.push({ id: 'equipements', t: 'sg.equip', html: cats.map(function (cat) { var it = v.eq.filter(function (e) { return D.eq[e] === cat; }); return it.length ? '<div class="wy-equip"><h3 class="wy-h5">' + t('eqcat.' + cat) + '</h3><ul class="wy-equip-list">' + it.map(function (e) { return '<li>' + WY.icon('check') + t('eq.' + e) + '</li>'; }).join('') + '</ul></div>' : ''; }).join('') }); }
    var hist = row('f.owners', v.cond !== 'neuf' && v.owners ? v.owners : null) + row('f.service', yn(h.service)) + row('f.noacc', yn(h.accident)) + row('f.warr', yn(h.warranty));
    if (hist) G.push({ id: 'historique', t: 'sg.history', html: '<dl class="wy-spec-table">' + hist + '</dl>' + (h.accident ? '<p class="wy-caption wy-muted">' + t('sg.declared') + '</p>' : '') });
    var com = row('f.deliv', yn(c.deliv)) + row('f.fin', yn(c.fin)) + row('f.trade', yn(c.trade)) + row('v.negotiable', yn(v.neg)) + row('f.city', t('city.' + v.city));
    if (com) G.push({ id: 'conditions', t: 'sg.commercial', html: '<dl class="wy-spec-table">' + com + '</dl>' });
    G.push({ id: 'confiance', t: 'sg.trust', html: '<dl class="wy-spec-table">' + row('f.vdealer', dl.verified ? t('yes') : t('no')) + row('f.insp', v.insp ? t('yes') : t('no')) + row('sg.posted', t('sg.postedago', { n: v.posted })) + '</dl>' });
    var cur0 = (location.hash || '').replace('#specs-', ''), act = G.some(function (g) { return g.id === cur0; }) ? cur0 : G[0].id;
    $('[data-wy-tablist]').innerHTML = G.map(function (g) { return '<button class="wy-tab" role="tab" type="button" id="tab-' + g.id + '" aria-controls="tp-' + g.id + '" aria-selected="' + (g.id === act) + '" tabindex="' + (g.id === act ? 0 : -1) + '" data-tab="' + g.id + '">' + t(g.t) + '</button>'; }).join('');
    $('[data-wy-tabpanels]').innerHTML = G.map(function (g) { return '<div class="wy-tabpanel" role="tabpanel" id="tp-' + g.id + '" aria-labelledby="tab-' + g.id + '"' + (g.id === act ? '' : ' hidden') + ' tabindex="0">' + g.html + '</div>'; }).join('');
  }
  function activateTab(id, focus) { $$('[role=tab]', $('[data-wy-tablist]')).forEach(function (b) { var on = b.dataset.tab === id; b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1; if (on) { if (focus) b.focus(); b.scrollIntoView({ inline: 'nearest', block: 'nearest' }); } }); $$('[role=tabpanel]', $('[data-wy-tabpanels]')).forEach(function (p) { p.hidden = p.id !== 'tp-' + id; }); history.replaceState(history.state, '', location.pathname + location.search + '#specs-' + id); }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-tab]'); if (b) activateTab(b.dataset.tab); });
  document.addEventListener('keydown', function (e) { var b = e.target.closest && e.target.closest('[role=tab][data-tab]'); if (!b) return; var tabs = $$('[role=tab]', $('[data-wy-tablist]')), i = tabs.indexOf(b), rtl = document.documentElement.dir === 'rtl', n = null;
    if (e.key === 'ArrowRight') n = i + (rtl ? -1 : 1); else if (e.key === 'ArrowLeft') n = i + (rtl ? 1 : -1); else if (e.key === 'Home') n = 0; else if (e.key === 'End') n = tabs.length - 1; if (n === null) return; e.preventDefault(); activateTab(tabs[(n + tabs.length) % tabs.length].dataset.tab, true); });

  function describe() { $('[data-wy-desc]').innerHTML = '<p>' + esc(v.d) + '</p>'; var n = $('[data-wy-desc-note]'); n.textContent = WY.lang() === 'fr' ? t('v.descdemo') : t('v.descauto'); }
  function inspection() {
    $('[data-wy-insp]').innerHTML = v.insp ? '<p>' + U.badge('inspected') + ' ' + U.badge('demo') + '</p><p>' + t('v.inspdone') + '</p><button class="wy-btn wy-btn--ghost" type="button" data-wy-toast="v.pdftoast">' + WY.icon('clipboard') + t('v.pdf') + '</button><p class="wy-notice">' + WY.icon('info') + '<span>' + t('insp.disclaimer') + '</span></p>'
      : '<p>' + t('v.inspnone') + '</p><button class="wy-btn wy-btn--secondary" type="button" data-wy-toast="v.insptoast">' + WY.icon('clipboard') + t('v.reqinsp') + '</button><p class="wy-caption wy-muted">' + t('insp.pricenote') + '</p>';
  }
  function dealerBlock() {
    var n = D.vehicles.filter(function (x) { return x.dealer === dl.id; }).length, open = U.dealerOpen(dl);
    $('[data-wy-dealer]').innerHTML = '<article class="wy-dcard wy-dcard--wide"><span class="wy-dcard__logo" aria-hidden="true">' + dl.initials + '</span><div><h3 class="wy-h4">' + esc(dl.name) + '</h3><p class="wy-caption wy-muted">' + WY.icon('pin') + ' ' + t('city.' + dl.city) + '</p></div><div class="wy-badges">' + (dl.verified ? U.badge('verified') : '') + U.badge('demo') + '</div>' +
      '<p class="wy-caption ' + (open ? 'wy-open' : 'wy-muted') + '">' + WY.icon('clock') + ' ' + t(open ? 'deal.open' : 'deal.closed') + ' · ' + t('hours.range', { a: dl.hours[0], b: dl.hours[1] }) + ' <span class="wy-muted">(' + t('hours.demo') + ')</span></p>' +
      '<p class="wy-caption"><strong>' + n + '</strong> ' + t(n === 1 ? 'deal.listing' : 'deal.listings') + '</p><div class="wy-row"><a class="wy-btn wy-btn--ghost wy-btn--sm" href="search.html?dealer=' + dl.id + '">' + t('deal.see') + '</a><a class="wy-btn wy-btn--ghost wy-btn--sm" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(dl.name + ' ' + t('city.' + dl.city)) + '">' + WY.icon('pin') + t('v.route') + '</a></div></article>';
  }
  function similar() {
    var list = D.vehicles.filter(function (x) { return x.id !== v.id && (x.body === v.body || (v.price && x.price && Math.abs(x.price - v.price) / v.price < 0.4)); }).slice(0, 6);
    $('[data-wy-similar-wrap]').hidden = !list.length; if (list.length) U.cardsInto($('[data-wy-similar]'), list);
  }

  /* ---- sticky bar (phone): shown once the title block leaves the viewport ---- */
  var bar = $('[data-wy-ctabar]'), tb = $('[data-wy-titleblock]');
  new IntersectionObserver(function (es) { var out = !es[0].isIntersecting && es[0].boundingClientRect.top < 0; bar.hidden = false; bar.classList.toggle('is-in', out); document.documentElement.classList.toggle('wy-has-ctabar', out); }).observe(tb);

  /* ---- 360 viewer (placeholder: simulated rotation, no real frame sequence) ---- */
  (function () {
    var el = $('[data-wy-viewer360]'), disc = $('[data-wy-v360-disc]'), car = $('[data-wy-v360-car]'), prog = $('[data-wy-v360-prog]'), deg = $('[data-wy-v360-deg]'), angle = 0, vel = 0, drag = false, lx = 0, run = false;
    car.innerHTML = '<img src="' + imgSrc + '" alt="' + esc(t('img.alt', { name: name })) + '" width="660" height="260" draggable="false">';
    var step = M.tier() === 'lite' ? 30 : 10, C = 2 * Math.PI * 19; prog.style.strokeDasharray = C;
    function paint() { var a = ((angle % 360) + 360) % 360, snap = Math.round(a / step) * step % 360; disc.style.setProperty('--a', snap + 'deg'); car.style.setProperty('--ry', (Math.sin(snap * Math.PI / 180) * 16).toFixed(1) + 'deg'); car.style.setProperty('--tx', (Math.sin(snap * Math.PI / 180) * -3).toFixed(1) + '%'); prog.style.strokeDashoffset = C * (1 - a / 360); deg.textContent = Math.round(snap) + '°'; }
    function loop() { if (drag) { run = false; return; } if (Math.abs(vel) > 0.05) { angle += vel; vel *= 0.92; paint(); M.raf(loop); } else run = false; }
    function kick() { if (!run && M.tier() !== 'reduced') { run = true; M.raf(loop); } }
    el.addEventListener('pointerdown', function (e) { if (e.target.closest('button')) return; drag = true; lx = e.clientX; vel = 0; el.setPointerCapture(e.pointerId); el.classList.add('is-drag'); });
    el.addEventListener('pointermove', function (e) { if (!drag) return; var dx = e.clientX - lx; lx = e.clientX; var d = dx / 8 * step * (document.documentElement.dir === 'rtl' ? -1 : 1); angle += d; vel = d; paint(); });
    var end = function () { if (!drag) return; drag = false; el.classList.remove('is-drag'); kick(); };
    el.addEventListener('pointerup', end); el.addEventListener('pointercancel', end);
    el.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { angle += step; paint(); } else if (e.key === 'ArrowLeft') { angle -= step; paint(); } });
    $('[data-wy-v360-spin]').addEventListener('click', function () { if (M.tier() === 'reduced') { angle += 90; paint(); return; } var t0 = performance.now(), a0 = angle; (function s(t) { var p = Math.min(1, (t - t0) / 1800); angle = a0 + 360 * (1 - Math.pow(1 - p, 3)); paint(); if (p < 1) requestAnimationFrame(s); })(t0); });
    $('[data-wy-v360-fs]').addEventListener('click', function () { if (el.requestFullscreen) el.requestFullscreen().catch(function () {}); });
    paint();
  })();

  build(); document.addEventListener('wy:lang', function () { build(); });
  if (location.hash.indexOf('#specs-') === 0) setTimeout(function () { $('.wy-specs').scrollIntoView(); }, 50);
  M.scan();
})();
