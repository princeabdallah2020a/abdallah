/* Home page: quick search, shortcuts, featured, marquee, rentals, services, dealers, figures, recent (demo data). */
(function () {
  'use strict';
  var WY = window.WY, D = WY.D, U = WY.ui, t = function (k, v) { return WY.t(k, v); };
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var V = D.vehicles;

  /* ---- quick search ---- */
  var form = $('[data-wy-quicksearch]'), selMake = $('[data-wy-make]'), selModel = $('[data-wy-model]'), selBud = $('[data-wy-budget]'), selCity = $('[data-wy-city]');
  var offer = function () { return form.elements.offer.value; };
  function match() {
    var o = offer(), mk = selMake.value, md = selModel.value, mx = +selBud.value || 0, ct = selCity.value;
    return V.filter(function (v) { var pr = o === 'rent' ? (v.rent && v.rent.month) : v.price;
      return pr && (!mk || v.mk === mk) && (!md || v.md === md) && (!mx || pr <= mx) && (!ct || v.city === ct); });
  }
  function count() {
    var n = match().length;
    $$('[data-wy-count]', form).forEach(function (e) { e.textContent = WY.nf(n); });
    $$('[data-wy-count-label]', form).forEach(function (e) { e.textContent = t(n === 1 ? 'qs.vehicle' : 'qs.vehicles'); });
  }
  function fillSelects() {
    var keep = { mk: selMake.value, md: selModel.value, bud: selBud.value, ct: selCity.value };
    var makes = Array.from(new Set(V.map(function (v) { return v.mk; }))).sort();
    selMake.innerHTML = '<option value="">' + t('f.allmakes') + '</option>' + makes.map(function (m) { return '<option>' + m + '</option>'; }).join(''); selMake.value = keep.mk;
    var models = Array.from(new Set(V.filter(function (v) { return !selMake.value || v.mk === selMake.value; }).map(function (v) { return v.md; }))).sort();
    selModel.disabled = !selMake.value; selModel.innerHTML = '<option value="">' + t('f.allmodels') + '</option>' + (selMake.value ? models.map(function (m) { return '<option>' + m + '</option>'; }).join('') : ''); if (selMake.value) selModel.value = keep.md;
    var rent = offer() === 'rent', steps = rent ? [500000, 1000000, 1500000, 2000000] : [5e6, 1e7, 15e6, 2e7, 3e7, 5e7];
    selBud.name = rent ? 'rmax' : 'max'; $('[data-wy-budget-label]').textContent = t(rent ? 'f.budgetmonth' : 'f.budgetmax');
    selBud.innerHTML = '<option value="">' + t('f.nolimit') + '</option>' + steps.map(function (s) { return '<option value="' + s + '">≤ ' + WY.price(s) + '</option>'; }).join(''); if (steps.indexOf(+keep.bud) > -1) selBud.value = keep.bud;
    selCity.innerHTML = '<option value="">' + t('f.allcities') + '</option>' + D.cities.map(function (c) { return '<option value="' + c + '">' + t('city.' + c) + '</option>'; }).join(''); selCity.value = keep.ct;
    count();
  }
  form.addEventListener('change', function (e) { if (e.target === selMake) selModel.value = ''; fillSelects(); });
  form.addEventListener('submit', function () { $$('select', form).forEach(function (s) { if (!s.value) s.disabled = true; }); setTimeout(function () { $$('select', form).forEach(function (s) { s.disabled = s === selModel && !selMake.value; }); }, 0); });
  var nl = $('[data-wy-nl]');
  nl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); U.askAI(nl.value); nl.value = ''; } });
  nl.addEventListener('focus', function () { /* opens on Enter / click on hint, not on focus (avoids stealing focus) */ });
  $('.wy-search__hint', form).addEventListener('click', function () { if (nl.value) { U.askAI(nl.value); nl.value = ''; } else { document.querySelector('[data-wy-ai-open]').click(); } });

  /* ---- sections ---- */
  function show(name, on) { var s = $('[data-wy-section="' + name + '"]'); if (s) s.hidden = !on; }
  function render() {
    fillSelects();
    /* 2. body shortcuts: only types with count > 0 */
    var sc = D.bodies.map(function (b) { return { k: b, n: V.filter(function (v) { return v.body === b; }).length, href: 'search.html?body=' + b, label: t('body.' + b), icon: D.bodyIcon[b] }; });
    var eco = V.filter(function (v) { return ['hybride', 'phev', 'electrique'].indexOf(v.fuel) > -1; }).length;
    sc.push({ k: 'eco', n: eco, href: 'search.html?fuel=hybride,phev,electrique', label: t('body.eco'), icon: 'bolt' });
    $('[data-wy-shortcuts]').innerHTML = sc.filter(function (x) { return x.n > 0; }).map(function (x) {
      return '<li><a class="wy-shortcut" href="' + x.href + '">' + WY.icon(x.icon) + '<span class="wy-shortcut__l">' + x.label + '</span><span class="wy-shortcut__n">' + WY.nf(x.n) + '</span></a></li>'; }).join('');
    /* 3. featured (sponsored) */
    var feat = V.filter(function (v) { return v.spons; }); show('featured', feat.length);
    U.cardsInto($('[data-wy-featured]'), feat, { eager: true });
    /* 6. rentals */
    var rent = V.filter(function (v) { return v.rent; }); show('rental', rent.length);
    U.cardsInto($('[data-wy-rentals]'), rent, { rent: true });
    /* 7. services */
    show('services', D.services.length);
    $('[data-wy-services]').innerHTML = D.services.map(function (s) { var d = WY.dealer(s.dealer);
      return '<article class="wy-scard">' + WY.icon(s.icon) + '<h3 class="wy-h4">' + t('serv.' + s.id + '.t') + '</h3><p>' + t('serv.' + s.id + '.p') + '</p><p class="wy-caption wy-muted">' + WY.icon('pin') + ' ' + t('city.' + s.city) + ' · ' + WY.esc(d.name) + '</p></article>'; }).join('');
    /* 9. verified dealers (>= 3) */
    var vd = D.dealers.filter(function (d) { return d.verified; }); show('dealers', vd.length >= 3);
    $('[data-wy-dealers]').innerHTML = vd.map(function (d) { var n = V.filter(function (v) { return v.dealer === d.id; }).length;
      return '<article class="wy-dcard"><span class="wy-dcard__logo" aria-hidden="true">' + d.initials + '</span><div><h3 class="wy-h4">' + WY.esc(d.name) + '</h3><p class="wy-caption wy-muted">' + WY.icon('pin') + ' ' + t('city.' + d.city) + '</p></div>' +
        '<div class="wy-badges">' + U.badge('verified') + U.badge('demo') + '</div><p class="wy-caption"><strong>' + WY.nf(n) + '</strong> ' + t(n === 1 ? 'deal.listing' : 'deal.listings') + '</p>' +
        '<p class="wy-caption ' + (U.dealerOpen(d) ? 'wy-open' : 'wy-muted') + '">' + WY.icon('clock') + ' ' + t(U.dealerOpen(d) ? 'deal.open' : 'deal.closed') + '</p>' +
        '<a class="wy-link wy-link--arrow" href="search.html?dealer=' + d.id + '">' + t('deal.see') + ' ' + WY.icon('arrow-right', 'wy-flip') + '</a></article>'; }).join('');
    /* 13. recently viewed */
    var rc = U.recent().map(WY.vehicle).filter(Boolean); show('recent', rc.length); if (rc.length) U.cardsInto($('[data-wy-recent]'), rc);
  }
  render(); document.addEventListener('wy:lang', render);

  /* 4. marquee (makes with listings, typography only) */
  var mk = Array.from(new Set(V.map(function (v) { return v.mk; }))).sort();
  $('[data-wy-marquee-list]').innerHTML = mk.map(function (m) { return '<li><a href="search.html?make=' + encodeURIComponent(m) + '">' + m + '</a></li>'; }).join('');
  WY.motion.marquee($('[data-wy-marquee]'));

  /* 11. figures: counts computed from the demo dataset only; whole block hidden if any is 0 */
  var fig = { vehicles: V.length, dealers: D.dealers.filter(function (d) { return d.verified; }).length, cities: new Set(V.map(function (v) { return v.city; })).size };
  show('figures', fig.vehicles && fig.dealers && fig.cities);
  $$('[data-wy-figure]').forEach(function (el) { WY.motion.counter(el, fig[el.dataset.wyFigure]); });
  WY.motion.scan();
})();
