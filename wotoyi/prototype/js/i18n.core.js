/* i18n runtime (appended to the generated string tables by _src/build.py). FR is the source language. */
(function () {
  var WY = window.WY = window.WY || {};
  var LOC = { fr: 'fr-FR', en: 'en-GB', ar: 'ar-u-nu-latn' }; /* Arabic UI keeps Western digits (DESIGN 1.3) */
  WY.lang = function () { var l = document.documentElement.lang; return WY_STR[l] ? l : 'fr'; };
  WY.t = function (k, vars) {
    var s = (WY_STR[WY.lang()] || {})[k];
    if (s == null) s = WY_STR.fr[k];
    if (s == null) return k;
    if (vars) s = s.replace(/\{(\w+)\}/g, function (m, n) { return vars[n] != null ? vars[n] : m; });
    return s;
  };
  WY.nf = function (n, o) { return new Intl.NumberFormat(LOC[WY.lang()], o || {}).format(n); };
  /* Prices: Intl number + "FCFA" (Intl XOF symbol differs per locale: "F CFA" / "XOF"; deviation noted in README) */
  WY.price = function (n) { return '<bdi>' + WY.nf(n, { maximumFractionDigits: 0 }) + ' FCFA</bdi>'; };
  WY.km = function (n) { return '<bdi>' + WY.nf(n, { maximumFractionDigits: 0 }) + ' km</bdi>'; };
  WY.apply = function (root) {
    root = root || document;
    root.querySelectorAll('[data-i18n]').forEach(function (el) { el.innerHTML = WY.t(el.dataset.i18n); });
    root.querySelectorAll('[data-i18n-attr]').forEach(function (el) {
      el.dataset.i18nAttr.split(';').forEach(function (p) { var i = p.indexOf(':'); if (i > 0) el.setAttribute(p.slice(0, i), WY.t(p.slice(i + 1))); });
    });
    if (root === document) {
      var tk = 'title.' + (document.body && document.body.dataset.page);
      if (WY_STR.fr[tk]) document.title = WY.t(tk);
      document.querySelectorAll('[data-wy-lang]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.wyLang === WY.lang())); });
    }
  };
  WY.setLang = function (l) {
    if (!WY_STR[l]) return;
    try { localStorage.setItem('wy-lang', l); } catch (e) {}
    var d = document.documentElement; d.lang = l; d.dir = l === 'ar' ? 'rtl' : 'ltr';
    WY.apply();
    document.dispatchEvent(new CustomEvent('wy:lang', { detail: l }));
  };
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-wy-lang]');
    if (b && !b.disabled) WY.setLang(b.dataset.wyLang);
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { if (WY.lang() !== 'fr') WY.apply(); else WY.apply(); });
  else WY.apply();
})();
