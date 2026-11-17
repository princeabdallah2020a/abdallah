/* Search page: full filter panel (8 groups), live count, URL state, chips, sort, grid/list, pagination, sheet/drawer (demo data). */
(function () {
  'use strict';
  var WY = window.WY, D = WY.D, U = WY.ui, M = WY.motion, t = function (k, v) { return WY.t(k, v); };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var V = D.vehicles, PAGE = 9, S = {}, openGroups = { essential: true };
  var META = ['sort', 'view', 'page'];
  var csv = function (k) { return S[k] ? S[k].split(',') : []; };
  var num = function (k) { return S[k] !== undefined && S[k] !== '' ? +S[k] : null; };

  function readURL() { S = {}; new URLSearchParams(location.search).forEach(function (v, k) { if (v !== '') S[k] = v; }); if (S.dealer && !WY.dealer(S.dealer)) delete S.dealer; }
  function writeURL() { var u = new URLSearchParams(); Object.keys(S).forEach(function (k) { u.set(k, S[k]); }); history.replaceState(history.state, '', location.pathname + (u.toString() ? '?' + u.toString() : '')); }

  /* ---- matching ---- */
  function matches(v, st, skip) {
    st = st || S; var g = function (k) { return skip && skip.indexOf(k) > -1 ? undefined : st[k]; };
    var c = function (k) { var x = g(k); return x ? x.split(',') : []; }, n = function (k) { var x = g(k); return x !== undefined && x !== '' ? +x : null; };
    var dl = WY.dealer(v.dealer), s = v.s || {}, h = v.hist || {}, com = v.com || {}, o = g('offer') || 'both';
    if (g('q')) { var hay = (WY.name(v) + ' ' + v.ver + ' ' + v.yr + ' ' + t('city.' + v.city) + ' ' + t('fuel.' + v.fuel) + ' ' + t('body.' + v.body)).toLowerCase(); if (g('q').toLowerCase().split(/\s+/).some(function (w) { return hay.indexOf(w) < 0; })) return false; }
    if (o === 'sale' && !v.price) return false; if (o === 'rent' && !v.rent) return false;
    if (g('make') && v.mk !== g('make')) return false; if (g('model') && v.md !== g('model')) return false;
    if (g('ver') && v.ver.toLowerCase().indexOf(g('ver').toLowerCase()) < 0) return false;
    if (c('body').length && c('body').indexOf(v.body) < 0) return false;
    if ((n('min') !== null || n('max') !== null) && (!v.price || (n('min') !== null && v.price < n('min')) || (n('max') !== null && v.price > n('max')))) return false;
    if (n('rmax') !== null && (!v.rent || v.rent.month > n('rmax'))) return false;
    if ((n('ymin') !== null && v.yr < n('ymin')) || (n('ymax') !== null && v.yr > n('ymax'))) return false;
    if ((n('kmmin') !== null && v.km < n('kmmin')) || (n('kmmax') !== null && v.km > n('kmmax'))) return false;
    if (c('city').length && c('city').indexOf(v.city) < 0) return false;
    if (c('fuel').length && c('fuel').indexOf(v.fuel) < 0) return false;
    if (g('tr') && v.tr !== g('tr')) return false; if (c('drive').length && c('drive').indexOf(v.drive) < 0) return false;
    if ((n('ccmin') !== null || n('ccmax') !== null) && (!s.cc || (n('ccmin') !== null && s.cc < n('ccmin')) || (n('ccmax') !== null && s.cc > n('ccmax')))) return false;
    if ((n('hpmin') !== null || n('hpmax') !== null) && (!s.hp || (n('hpmin') !== null && s.hp < n('hpmin')) || (n('hpmax') !== null && s.hp > n('hpmax')))) return false;
    if (n('seats') !== null && v.seats < n('seats')) return false; if (n('doors') !== null && v.doors !== n('doors')) return false;
    if (c('col').length && c('col').indexOf(v.col) < 0) return false;
    if (c('cond').length && c('cond').indexOf(v.cond) < 0) return false; if (g('origin') && v.origin !== g('origin')) return false;
    if (c('customs').length && c('customs').indexOf(v.customs) < 0) return false; if (n('owners') !== null && v.owners > n('owners')) return false;
    if (g('noacc') && !h.accident) return false; if (g('service') && !h.service) return false; if (g('warr') && !h.warranty) return false;
    if (g('insp') && !v.insp) return false; if (g('vdealer') && !dl.verified) return false; if (g('dealer') && v.dealer !== g('dealer')) return false;
    if (g('deliv') && !com.deliv) return false; if (g('fin') && !com.fin) return false; if (g('trade') && !com.trade) return false;
    if (c('eq').length && !c('eq').every(function (e) { return v.eq.indexOf(e) > -1; })) return false;
    if (n('since') !== null && v.posted > n('since')) return false;
    return true;
  }
  function sorted(list) {
    var by = S.sort || 'relevance', pr = function (v) { return v.price || (v.rent ? v.rent.month * 12 : 0); };
    var f = { relevance: function (a, b) { return (b.spons - a.spons) || (a.posted - b.posted); }, 'price-asc': function (a, b) { return pr(a) - pr(b); }, 'price-desc': function (a, b) { return pr(b) - pr(a); },
      'year-desc': function (a, b) { return b.yr - a.yr; }, 'km-asc': function (a, b) { return a.km - b.km; }, 'new': function (a, b) { return a.posted - b.posted; } }[by] || function () { return 0; };
    return list.slice().sort(f);
  }

  /* ---- field builders ---- */
  var esc = WY.esc, L = function (k) { return t(k); };
  function lab(key, inner, cls) { return '<div class="wy-field' + (cls ? ' ' + cls : '') + '"><label class="wy-field__l">' + L(key) + '</label>' + inner + '</div>'; }
  function opts(list, cur, lbl, anyKey) { return (anyKey ? '<option value="">' + L(anyKey) + '</option>' : '') + list.map(function (x) { var v = x.v !== undefined ? x.v : x; return '<option value="' + esc(v) + '"' + (String(cur) === String(v) ? ' selected' : '') + '>' + esc(lbl ? lbl(x) : (x.l || x)) + '</option>'; }).join(''); }
  function selectF(k, key, list, lbl, anyKey, extra) { return lab(key, '<select class="wy-select" data-k="' + k + '"' + (extra || '') + '>' + opts(list, S[k] || '', lbl, anyKey || 'f.any') + '</select>'); }
  function minmax(kmin, kmax, key, ph1, ph2) { return lab(key, '<div class="wy-minmax"><input class="wy-input" type="number" inputmode="numeric" data-k="' + kmin + '" value="' + esc(S[kmin] || '') + '" placeholder="' + ph1 + '" aria-label="' + L(key) + ' min"><span aria-hidden="true">–</span><input class="wy-input" type="number" inputmode="numeric" data-k="' + kmax + '" value="' + esc(S[kmax] || '') + '" placeholder="' + ph2 + '" aria-label="' + L(key) + ' max"></div>'); }
  function chips(k, list, lbl, icon) { var cur = csv(k); return '<div class="wy-chips" role="group">' + list.map(function (x) { var v = x.v || x; return '<button class="wy-chip' + (icon ? ' wy-chip--icon' : '') + '" type="button" data-k="' + k + '" data-v="' + esc(v) + '" aria-pressed="' + (cur.indexOf(v) > -1) + '">' + (icon ? WY.icon(icon(v)) : '') + esc(lbl(x)) + '</button>'; }).join('') + '</div>'; }
  function check(k, key) { return '<label class="wy-check"><input type="checkbox" data-k="' + k + '" data-bool="1"' + (S[k] ? ' checked' : '') + '><span>' + L(key) + '</span></label>'; }
  function rangeF() {
    var MAX = 60e6, lo = num('min') !== null ? num('min') : 0, hi = num('max') !== null ? num('max') : MAX;
    return lab('f.price', '<div class="wy-range" data-wy-range style="--lo:' + (lo / MAX * 100) + '%;--hi:' + (hi / MAX * 100) + '%"><div class="wy-range__track"></div><input type="range" min="0" max="' + MAX + '" step="500000" value="' + lo + '" data-range="min" aria-label="' + L('f.price') + ' min"><input type="range" min="0" max="' + MAX + '" step="500000" value="' + hi + '" data-range="max" aria-label="' + L('f.price') + ' max"></div>' +
      '<div class="wy-minmax"><input class="wy-input" type="number" inputmode="numeric" data-k="min" step="500000" value="' + esc(S.min || '') + '" placeholder="0" aria-label="' + L('f.price') + ' min"><span aria-hidden="true">–</span><input class="wy-input" type="number" inputmode="numeric" data-k="max" step="500000" value="' + esc(S.max || '') + '" placeholder="60 000 000" aria-label="' + L('f.price') + ' max"></div><p class="wy-caption wy-muted">FCFA</p>');
  }
  function groups() {
    var makes = Array.from(new Set(V.map(function (v) { return v.mk; }))).sort(), origins = Array.from(new Set(V.map(function (v) { return v.origin; }))).sort();
    var models = Array.from(new Set(V.filter(function (v) { return !S.make || v.mk === S.make; }).map(function (v) { return v.md; }))).sort();
    var rent = (S.offer || 'both') !== 'sale';
    var eqCats = ['comfort', 'safety', 'media', 'exterior', 'interior'];
    var eqHTML = '<input class="wy-input" type="search" data-eqsearch placeholder="' + L('f.eqsearch') + '" aria-label="' + L('f.eqsearch') + '">' + eqCats.map(function (cat) {
      var items = Object.keys(D.eq).filter(function (e) { return D.eq[e] === cat; }); return '<fieldset class="wy-eqcat"><legend>' + L('eqcat.' + cat) + '</legend>' + items.map(function (e) { var on = csv('eq').indexOf(e) > -1;
        return '<label class="wy-check" data-eqitem="' + esc(L('eq.' + e).toLowerCase()) + '"><input type="checkbox" data-k="eq" data-v="' + e + '"' + (on ? ' checked' : '') + '><span>' + L('eq.' + e) + '</span></label>'; }).join('') + '</fieldset>'; }).join('');
    return [
      { id: 'essential', t: 'g.essential', keys: ['offer', 'make', 'model', 'ver', 'body', 'min', 'max', 'rmax', 'ymin', 'ymax', 'kmmin', 'kmmax', 'city'], html:
        lab('f.offer', '<div class="wy-segmented wy-segmented--full" role="radiogroup">' + ['both', 'sale', 'rent'].map(function (o) { return '<label><input type="radio" name="f-offer" data-k="offer" value="' + o + '"' + ((S.offer || 'both') === o ? ' checked' : '') + '><span>' + L('offer.' + o) + '</span></label>'; }).join('') + '</div>') +
        lab('f.make', '<input class="wy-input" list="dl-make" data-k="make" value="' + esc(S.make || '') + '" placeholder="' + L('f.allmakes') + '" autocomplete="off"><datalist id="dl-make">' + makes.map(function (m) { return '<option value="' + m + '">'; }).join('') + '</datalist>') +
        lab('f.model', '<select class="wy-select" data-k="model"' + (S.make ? '' : ' disabled') + '>' + opts(models, S.model || '', null, 'f.allmodels') + '</select>', 'js-model') +
        lab('f.version', '<input class="wy-input" type="text" data-k="ver" value="' + esc(S.ver || '') + '" autocomplete="off">') +
        lab('f.body', chips('body', D.bodies, function (b) { return L('body.' + b); }, function (b) { return D.bodyIcon[b]; })) + rangeF() +
        '<div class="js-rent"' + (rent ? '' : ' hidden') + '>' + selectF('rmax', 'f.budgetmonth', [5e5, 1e6, 1.5e6, 2e6].map(function (x) { return { v: x, l: '≤ ' + WY.nf(x) + ' FCFA' }; }), function (x) { return x.l; }, 'f.any') + '</div>' +
        minmax('ymin', 'ymax', 'f.year', '2015', '2025') + minmax('kmmin', 'kmmax', 'f.km', '0', '150000') + lab('f.city', chips('city', D.cities, function (c) { return L('city.' + c); })) },
      { id: 'engine', t: 'g.engine', keys: ['fuel', 'tr', 'drive', 'ccmin', 'ccmax', 'hpmin', 'hpmax'], html:
        lab('f.fuel', chips('fuel', D.fuels, function (f) { return L('fuel.' + f); })) +
        selectF('tr', 'f.trans', ['auto', 'manuelle'], function (x) { return L('trans.' + x); }) + lab('f.drive', chips('drive', ['fwd', 'rwd', '4x4', 'awd'], function (x) { return L('drive.' + x); })) +
        minmax('ccmin', 'ccmax', 'f.cc', '1000', '4000') + minmax('hpmin', 'hpmax', 'f.hp', '80', '300') + '<p class="wy-caption wy-muted">' + L('f.unknownexcl') + '</p>' },
      { id: 'body', t: 'g.body', keys: ['seats', 'doors', 'col'], html:
        selectF('seats', 'f.seats', [5, 7], function (x) { return '≥ ' + x; }) + selectF('doors', 'f.doors', [4, 5], function (x) { return String(x); }) +
        lab('f.color', '<div class="wy-swatches">' + Object.keys(D.colors).map(function (c) { return '<button class="wy-swatch" type="button" data-k="col" data-v="' + c + '" aria-pressed="' + (csv('col').indexOf(c) > -1) + '"><i style="background:' + D.colors[c] + '"></i><span>' + L('col.' + c) + '</span></button>'; }).join('') + '</div>') },
      { id: 'state', t: 'g.state', keys: ['cond', 'origin', 'customs', 'owners', 'noacc', 'service', 'warr'], html:
        lab('f.cond', chips('cond', ['neuf', 'occasion', 'venant'], function (x) { return L('cond.' + x); })) + selectF('origin', 'f.origin', origins, function (x) { return x; }) +
        lab('f.customs', chips('customs', ['cleared', 'transit'], function (x) { return L('customs.' + x); })) + selectF('owners', 'f.owners', [1, 2], function (x) { return '≤ ' + x; }) +
        check('noacc', 'f.noacc') + check('service', 'f.service') + check('warr', 'f.warr') },
      { id: 'trust', t: 'g.trust', keys: ['insp', 'vdealer', 'dealer'], html: check('insp', 'f.insp') + check('vdealer', 'f.vdealer') + selectF('dealer', 'f.dealer', D.dealers.map(function (d) { return { v: d.id, l: d.name }; }), function (x) { return x.l; }) },
      { id: 'commercial', t: 'g.commercial', keys: ['deliv', 'fin', 'trade'], html: check('deliv', 'f.deliv') + check('fin', 'f.fin') + check('trade', 'f.trade') },
      { id: 'equip', t: 'g.equip', keys: ['eq'], html: eqHTML },
      { id: 'media', t: 'g.media', keys: ['since'], html: selectF('since', 'f.since', [1, 7, 30], function (x) { return L('since.' + x); }) + '<p class="wy-caption wy-muted">' + L('f.mediaNote') + '</p>' }
    ];
  }

  /* ---- panel ---- */
  var body = $('[data-wy-filter-groups]'), panel = $('[data-wy-filters]');
  function groupCount(g) { return chipList().filter(function (c) { return c.keys.some(function (k) { return g.keys.indexOf(k) > -1; }); }).length; }
  function renderPanel() {
    var scroll = body.scrollTop, focused = document.activeElement && document.activeElement.dataset ? document.activeElement : null;
    body.innerHTML = groups().map(function (g) { var n = groupCount(g), id = 'fg-' + g.id;
      return '<section class="wy-filter-group' + (openGroups[g.id] ? ' is-open' : '') + '" data-g="' + g.id + '"><h3><button class="wy-filter-group__h" type="button" aria-expanded="' + !!openGroups[g.id] + '" aria-controls="' + id + '"><span>' + L(g.t) + '</span>' + (n ? '<span class="wy-count-dot" aria-label="' + n + '">' + n + '</span>' : '') + WY.icon('chevron-down', 'wy-chev') + '</button></h3><div class="wy-filter-group__b" id="' + id + '" role="group" ' + (openGroups[g.id] ? '' : 'hidden') + '><div class="wy-filter-group__in">' + g.html + '</div></div></section>'; }).join('');
    body.scrollTop = scroll;
  }
  function syncPanel() { /* light update: group counts + dependent fields without losing focus */
    $$('.wy-filter-group', body).forEach(function (sec, i) { var g = groups()[i], n = groupCount(g), h = $('.wy-filter-group__h', sec), dot = $('.wy-count-dot', h);
      if (n && !dot) { dot = document.createElement('span'); dot.className = 'wy-count-dot'; h.insertBefore(dot, $('.wy-chev', h)); } if (dot) { dot.textContent = n; dot.hidden = !n; } });
    var rent = $('.js-rent', body); if (rent) rent.hidden = (S.offer || 'both') === 'sale';
  }
  body.addEventListener('click', function (e) {
    var h = e.target.closest('.wy-filter-group__h');
    if (h) { var sec = h.closest('.wy-filter-group'), id = sec.dataset.g, on = !sec.classList.contains('is-open'); openGroups[id] = on; sec.classList.toggle('is-open', on); h.setAttribute('aria-expanded', on); $('.wy-filter-group__b', sec).hidden = !on; return; }
    var c = e.target.closest('button[data-k][data-v]');
    if (c) { var k = c.dataset.k, a = csv(k), i = a.indexOf(c.dataset.v); if (i > -1) a.splice(i, 1); else a.push(c.dataset.v); set(k, a.join(',')); c.setAttribute('aria-pressed', String(i < 0)); }
  });
  function set(k, v) { if (v === '' || v == null) delete S[k]; else S[k] = String(v); if (k !== 'page') delete S.page; commit(k); }
  body.addEventListener('input', function (e) {
    var r = e.target.dataset.range; if (r) { var box = e.target.closest('[data-wy-range]'), lo = +$('[data-range=min]', box).value, hi = +$('[data-range=max]', box).value; if (r === 'min' && lo > hi) { lo = hi; e.target.value = lo; } if (r === 'max' && hi < lo) { hi = lo; e.target.value = hi; }
      box.style.setProperty('--lo', lo / 60e6 * 100 + '%'); box.style.setProperty('--hi', hi / 60e6 * 100 + '%'); set('min', lo > 0 ? lo : ''); set('max', hi < 60e6 ? hi : '');
      var f = $('input[data-k=min]', body), g = $('input[data-k=max]', body); if (f) f.value = S.min || ''; if (g) g.value = S.max || ''; return; }
    if (e.target.hasAttribute('data-eqsearch')) { var q = e.target.value.toLowerCase(); $$('[data-eqitem]', body).forEach(function (el) { el.hidden = q && el.dataset.eqitem.indexOf(q) < 0; }); return; }
    var k = e.target.dataset.k; if (!k) return;
    if (e.target.type === 'checkbox') return;
    if (e.target.type === 'radio') return;
    if (e.target.tagName === 'SELECT') return;
    if (k === 'make') { var ok = V.some(function (v) { return v.mk.toLowerCase() === e.target.value.toLowerCase(); }); if (!ok && e.target.value) return; if (ok) e.target.value = V.filter(function (v) { return v.mk.toLowerCase() === e.target.value.toLowerCase(); })[0].mk; }
    set(k, e.target.value.trim()); if (k === 'make') { delete S.model; refreshModels(); } if (k === 'min' || k === 'max') { var box2 = $('[data-wy-range]', body); if (box2) { $('[data-range=min]', box2).value = S.min || 0; $('[data-range=max]', box2).value = S.max || 60e6; box2.style.setProperty('--lo', (S.min || 0) / 60e6 * 100 + '%'); box2.style.setProperty('--hi', (S.max || 60e6) / 60e6 * 100 + '%'); } }
  });
  body.addEventListener('change', function (e) {
    var k = e.target.dataset.k; if (!k) return; var el = e.target;
    if (el.type === 'checkbox') { if (el.dataset.bool) set(k, el.checked ? '1' : ''); else { var a = csv(k), i = a.indexOf(el.dataset.v); if (el.checked && i < 0) a.push(el.dataset.v); if (!el.checked && i > -1) a.splice(i, 1); set(k, a.join(',')); } }
    else if (el.type === 'radio') { set(k, el.value === 'both' ? '' : el.value); }
    else if (el.tagName === 'SELECT' || el.type === 'text' || el.type === 'number') { set(k, el.value); }
    if (k === 'make' && el.type !== 'text') { delete S.model; }
  });
  function refreshModels() {
    var sel = $('[data-k=model]', body); if (!sel) return; var models = Array.from(new Set(V.filter(function (v) { return !S.make || v.mk === S.make; }).map(function (v) { return v.md; }))).sort();
    sel.disabled = !S.make; sel.innerHTML = opts(models, S.model || '', null, 'f.allmodels');
  }
  $$('[data-wy-clear]').forEach(function (b) { b.addEventListener('click', function () { Object.keys(S).forEach(function (k) { if (META.indexOf(k) < 0 && k !== 'q') delete S[k]; }); delete S.q; var kw = $('[data-wy-kw]'); kw.value = ''; renderPanel(); commit('clear'); }); });

  /* ---- active chips ---- */
  function chipList() {
    var o = [], add = function (keys, label) { o.push({ keys: keys, label: label }); }, l = function (k, p) { return t(p + '.' + k); };
    if (S.q) add(['q'], '“' + S.q + '”');
    if (S.offer) add(['offer'], t('offer.' + S.offer)); if (S.make) add(['make'], S.make); if (S.model) add(['model'], S.model); if (S.ver) add(['ver'], S.ver);
    csv('body').forEach(function (b) { add(['body:' + b], l(b, 'body')); });
    if (S.min || S.max) add(['min', 'max'], t('f.price') + ': ' + (S.min ? WY.nf(+S.min) : '0') + ' – ' + (S.max ? WY.nf(+S.max) : '∞')); if (S.rmax) add(['rmax'], t('f.budgetmonth') + ' ≤ ' + WY.nf(+S.rmax));
    if (S.ymin || S.ymax) add(['ymin', 'ymax'], t('f.year') + ': ' + (S.ymin || '…') + ' – ' + (S.ymax || '…')); if (S.kmmin || S.kmmax) add(['kmmin', 'kmmax'], t('f.km') + ': ' + (S.kmmin ? WY.nf(+S.kmmin) : '0') + ' – ' + (S.kmmax ? WY.nf(+S.kmmax) : '∞'));
    csv('city').forEach(function (c) { add(['city:' + c], l(c, 'city')); }); csv('fuel').forEach(function (c) { add(['fuel:' + c], l(c, 'fuel')); }); if (S.tr) add(['tr'], l(S.tr, 'trans')); csv('drive').forEach(function (c) { add(['drive:' + c], l(c, 'drive')); });
    if (S.ccmin || S.ccmax) add(['ccmin', 'ccmax'], 'cm³ ' + (S.ccmin || '…') + ' – ' + (S.ccmax || '…')); if (S.hpmin || S.hpmax) add(['hpmin', 'hpmax'], t('f.hp') + ' ' + (S.hpmin || '…') + ' – ' + (S.hpmax || '…'));
    if (S.seats) add(['seats'], t('f.seats') + ' ≥ ' + S.seats); if (S.doors) add(['doors'], t('f.doors') + ' ' + S.doors); csv('col').forEach(function (c) { add(['col:' + c], l(c, 'col')); });
    csv('cond').forEach(function (c) { add(['cond:' + c], l(c, 'cond')); }); if (S.origin) add(['origin'], S.origin); csv('customs').forEach(function (c) { add(['customs:' + c], l(c, 'customs')); }); if (S.owners) add(['owners'], t('f.owners') + ' ≤ ' + S.owners);
    ['noacc', 'service', 'warr', 'insp', 'vdealer', 'deliv', 'fin', 'trade'].forEach(function (k) { if (S[k]) add([k], t('f.' + k)); }); if (S.dealer) add(['dealer'], WY.dealer(S.dealer).name);
    csv('eq').forEach(function (e) { add(['eq:' + e], t('eq.' + e)); }); if (S.since) add(['since'], t('since.' + S.since));
    return o;
  }
  function removeChip(keys) { keys.forEach(function (k) { if (k.indexOf(':') > -1) { var p = k.split(':'), a = csv(p[0]); a.splice(a.indexOf(p[1]), 1); if (a.length) S[p[0]] = a.join(','); else delete S[p[0]]; } else delete S[k]; }); delete S.page; }
  function paintChips() {
    var list = chipList(), box = $('[data-wy-active]');
    box.innerHTML = list.map(function (c, i) { return '<button class="wy-chip wy-chip--removable" type="button" data-i="' + i + '">' + esc(c.label) + WY.icon('close') + '<span class="wy-visually-hidden">' + L('search.remove') + '</span></button>'; }).join('') + (list.length > 1 ? '<button class="wy-link" type="button" data-all>' + L('search.clear') + '</button>' : '');
    box.hidden = !list.length; $$('[data-wy-filter-count]').forEach(function (n) { n.textContent = '(' + list.length + ')'; });
    box.onclick = function (e) { var b = e.target.closest('button'); if (!b) return; if (b.hasAttribute('data-all')) { $('[data-wy-clear]').click(); return; } removeChip(chipList()[+b.dataset.i].keys); $('[data-wy-kw]').value = S.q || ''; renderPanel(); commit('chip'); };
  }

  /* ---- results ---- */
  var results = $('[data-wy-results]'), empty = $('[data-wy-empty]'), pag = $('[data-wy-pagination]'), timer, lastN = -1;
  function paintCount() { var n = V.filter(function (v) { return matches(v); }).length; lastN = n; $$('[data-wy-count]').forEach(function (e) { e.textContent = WY.nf(n); }); $$('[data-wy-count-label]').forEach(function (e) { e.textContent = t(n === 1 ? 'search.result' : 'search.results'); }); return n; }
  function renderResults(skel) {
    var list = sorted(V.filter(function (v) { return matches(v); })), pages = Math.max(1, Math.ceil(list.length / PAGE)), p = Math.min(Math.max(1, +S.page || 1), pages), slice = list.slice((p - 1) * PAGE, p * PAGE);
    results.hidden = !list.length; empty.hidden = !!list.length; results.setAttribute('aria-busy', 'false');
    U.cardsInto(results, slice, { cls: results.dataset.view === 'list' ? 'wy-vcard--list' : '', eager: true });
    if (!list.length) paintEmpty();
    pag.hidden = pages < 2; pag.innerHTML = pages < 2 ? '' : Array.from({ length: pages }, function (_, i) { return '<a class="wy-page" href="?' + pagehref(i + 1) + '" data-page="' + (i + 1) + '"' + (i + 1 === p ? ' aria-current="page"' : '') + '>' + (i + 1) + '</a>'; }).join('');
    M.scan(results);
  }
  function pagehref(n) { var u = new URLSearchParams(location.search); u.set('page', n); return u.toString(); }
  pag.addEventListener('click', function (e) { var a = e.target.closest('[data-page]'); if (!a) return; e.preventDefault(); S.page = a.dataset.page; if (S.page === '1') delete S.page; writeURL(); renderResults(); window.scrollTo({ top: Math.max(0, $('.wy-search-layout').offsetTop - 140), behavior: 'auto' }); });
  function skeleton() { var n = 6; results.hidden = false; empty.hidden = true; results.setAttribute('aria-busy', 'true'); results.innerHTML = Array.from({ length: n }, function () { return '<div class="wy-vcard wy-skeleton" aria-hidden="true"><div class="wy-skeleton__m"></div><div class="wy-skeleton__l"></div><div class="wy-skeleton__l wy-skeleton__l--s"></div></div>'; }).join(''); }
  function paintEmpty() {
    var cl = chipList(), gains = cl.map(function (c) { var skipKeys = c.keys.map(function (k) { return k.split(':')[0]; }); return { c: c, n: V.filter(function (v) { return matches(v, S, skipKeys); }).length }; }).filter(function (x) { return x.n > 0; }).sort(function (a, b) { return b.n - a.n; }).slice(0, 3);
    var box = $('[data-wy-empty-chips]'); box.innerHTML = gains.map(function (x) { return '<button class="wy-chip" type="button" data-rm="' + cl.indexOf(x.c) + '">' + L('empty.remove') + ' ' + esc(x.c.label) + ' (+' + x.n + ')</button>'; }).join('');
    box.onclick = function (e) { var b = e.target.closest('[data-rm]'); if (!b) return; removeChip(chipList()[+b.dataset.rm].keys); $('[data-wy-kw]').value = S.q || ''; renderPanel(); commit('chip'); };
  }
  function commit(why) {
    writeURL(); paintCount(); paintChips(); syncPanel(); updateSave();
    clearTimeout(timer); var anim = M.tier() !== 'reduced';
    if (anim) skeleton();
    timer = setTimeout(function () { renderResults(); }, anim ? 320 : 0);
  }

  /* sort, view, keyword */
  var sortSel = $('[data-wy-sort]'); sortSel.addEventListener('change', function () { S.sort = sortSel.value; if (S.sort === 'relevance') delete S.sort; delete S.page; writeURL(); renderResults(); });
  $$('[data-wy-view]').forEach(function (b) { b.addEventListener('click', function () { results.dataset.view = b.dataset.wyView; S.view = b.dataset.wyView; if (S.view === 'grid') delete S.view; writeURL(); $$('[data-wy-view]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); renderResults(); }); });
  var kw = $('[data-wy-kw]'), kwT; kw.addEventListener('input', function () { clearTimeout(kwT); kwT = setTimeout(function () { var v = kw.value.trim(); if (v) S.q = v; else delete S.q; delete S.page; commit('q'); }, 250); });
  $('[data-wy-kw-form]').addEventListener('submit', function (e) { e.preventDefault(); var p = U.parseNL(kw.value); if (Object.keys(p).length && /\s/.test(kw.value.trim())) { Object.keys(p).forEach(function (k) { S[k] = String(p[k]); }); delete S.q; kw.value = ''; renderPanel(); commit('nl'); } });

  /* saved search */
  function savedKey() { return location.search || '?'; }
  function updateSave() { var list = []; try { list = JSON.parse(localStorage.getItem('wy-saved') || '[]'); } catch (e) {} var on = list.indexOf(savedKey()) > -1; $$('[data-wy-save]').forEach(function (b) { b.setAttribute('aria-pressed', String(on)); }); $$('[data-wy-save-label]').forEach(function (l) { l.textContent = t(on ? 'search.saved' : 'search.save'); }); }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-wy-save]'); if (!b) return; var list = []; try { list = JSON.parse(localStorage.getItem('wy-saved') || '[]'); } catch (er) {} var k = savedKey(), i = list.indexOf(k); if (i > -1) list.splice(i, 1); else list.push(k); try { localStorage.setItem('wy-saved', JSON.stringify(list)); } catch (er) {} updateSave(); U.toast(t(i > -1 ? 'search.unsavedToast' : 'search.savedToast')); });

  /* ---- panel as drawer (768-1199) / bottom sheet (<768) / static (>=1200) ---- */
  var scrimEl = $('[data-wy-scrim]'), sheet, ov, lg = matchMedia('(min-width: 1200px)');
  sheet = M.sheet(panel, { grips: [$('[data-wy-grip]'), $('[data-wy-sheet-head]')], scrim: scrimEl, onClose: function () { ov.close(); } });
  ov = U.overlay(panel, { noHidden: true, focus: '[data-wy-filters] .wy-filter-group__h', onClose: function () { sheet.reset(); var b = $('[data-wy-filters-open]'); if (b) b.setAttribute('aria-expanded', 'false'); if (history.state && history.state.wyFilters) history.back(); } });
  $('[data-wy-filters-open]').addEventListener('click', function (e) { if (lg.matches) return; ov.open(e.currentTarget); e.currentTarget.setAttribute('aria-expanded', 'true'); history.pushState({ wyFilters: 1 }, ''); if (innerWidth < 768) sheet.open(); });
  addEventListener('popstate', function () { if (ov.isOpen) { history.replaceState(null, ''); ov.close(true); sheet.reset(); } });
  lg.addEventListener('change', function (e) { if (e.matches && ov.isOpen) { ov.close(true); sheet.reset(); } });
  panel.setAttribute('role', 'dialog'); lg.matches ? panel.removeAttribute('aria-modal') : panel.setAttribute('aria-modal', 'true');

  /* ---- init ---- */
  readURL(); results.dataset.view = S.view === 'list' ? 'list' : 'grid'; sortSel.value = S.sort || 'relevance'; kw.value = S.q || '';
  $$('[data-wy-view]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.wyView === results.dataset.view)); });
  renderPanel(); paintCount(); paintChips(); updateSave(); renderResults();
  document.addEventListener('wy:lang', function () { renderPanel(); paintCount(); paintChips(); updateSave(); renderResults(); });
})();
