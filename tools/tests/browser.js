/**
 * Browser tests (Playwright + Chromium) for the interactive parts of Phase 1:
 * AJAX filtering, brand → model dependency, sorting, pagination, history,
 * photo gallery, mobile navigation. Requires demo data to be imported.
 *
 * Usage: node tools/tests/browser.js <base-url> [screenshot-dir]
 */
const { chromium, devices } = require('playwright');

const base = (process.argv[2] || '').replace(/\/$/, '');
const shots = process.argv[3] || '';
let pass = 0, fail = 0;
const check = (label, ok, detail = '') => {
	ok ? pass++ : fail++;
	console.log(`  ${ok ? 'ok  ' : 'FAIL'}  ${label}${ok || !detail ? '' : ' — ' + detail}`);
};

(async () => {
	const browser = await chromium.launch();
	const errors = [];
	const watch = (page) => {
		page.on('pageerror', (e) => errors.push(e.message));
		page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
		page.on('response', (r) => r.status() >= 400 && errors.push(`${r.status()} ${r.url()}`));
	};
	const shot = async (page, name, fullPage = true) => shots && page.screenshot({ path: `${shots}/${name}.png`, fullPage });

	// ----- Desktop -----
	const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 } });
	const page = await ctx.newPage();
	watch(page);

	console.log('\n== Home ==');
	await page.goto(base + '/');
	await page.waitForLoadState('networkidle');
	check('Hero search form is visible', await page.locator('.amc-filters--hero').isVisible());
	check('Home shows vehicle cards', (await page.locator('.amc-card').count()) >= 12);
	check('Fonts are self-hosted and loaded', await page.evaluate(() => document.fonts.check('16px Inter') && document.fonts.check('600 16px Sora')));
	check('No request to third-party hosts', await page.evaluate((b) => performance.getEntriesByType('resource').every((r) => r.name.startsWith(b)), base));
	check('Model select disabled until a brand is chosen', await page.locator('.amc-filters--hero select[name=model]').isDisabled());
	await page.selectOption('.amc-filters--hero select[name=brand]', 'toyota');
	const models = await page.locator('.amc-filters--hero select[name=model] option').allTextContents();
	check('Choosing a brand loads its models', models.includes('Corolla') && models.includes('Hilux') && !models.includes('X5'), models.join(','));
	await shot(page, 'home-desktop');
	await page.selectOption('.amc-filters--hero select[name=type]', 'sale');
	await Promise.all([page.waitForNavigation(), page.click('.amc-filters--hero button[type=submit]')]);
	check('Hero search opens the catalog with filters', page.url().includes('/cars/') && page.url().includes('brand=toyota') && page.url().includes('type=sale'), page.url());
	check('Catalog results match the hero search', (await page.locator('.amc-card__title').allTextContents()).every((t) => t.includes('Toyota')));

	console.log('\n== Catalog (AJAX) ==');
	await page.goto(base + '/cars/');
	const total = await page.locator('.amc-results__count').textContent();
	// A full reload would wipe this marker; AJAX updates keep it.
	await page.evaluate(() => { window.__amcMarker = true; });
	await Promise.all([
		page.waitForResponse((r) => r.url().includes('/marketplace/v1/catalog')),
		page.selectOption('[data-amc-filters] select[name=body]', 'suv'),
	]);
	await page.waitForFunction(() => document.querySelector('[data-amc-results]').getAttribute('aria-busy') === 'false');
	const filtered = await page.locator('.amc-results__count').textContent();
	check('Filter updates results without page reload', filtered !== total && page.url().includes('body=suv'), `${total} → ${filtered} @ ${page.url()}`);
	await Promise.all([
		page.waitForResponse((r) => r.url().includes('sort=price_asc')),
		page.selectOption('[data-amc-sort]', 'price_asc'),
	]);
	await page.waitForFunction(() => document.querySelector('[data-amc-results]').getAttribute('aria-busy') === 'false');
	const prices = (await page.locator('.amc-card__price').allTextContents()).map((p) => parseInt(p.replace(/\D/g, ''), 10));
	check('Sorting by lowest price works', prices.length > 1 && prices.every((p, i) => i === 0 || prices[i - 1] <= p), prices.join(','));
	check('Sort choice is kept in the URL and the select', page.url().includes('sort=price_asc') && (await page.locator('[data-amc-sort]').inputValue()) === 'price_asc');
	await page.fill('[data-amc-filters] input[name=price_max]', '15000000');
	await page.waitForResponse((r) => r.url().includes('price_max=15000000'));
	await page.waitForFunction(() => document.querySelector('[data-amc-results]').getAttribute('aria-busy') === 'false');
	const capped = (await page.locator('.amc-card__price').allTextContents()).map((p) => parseInt(p.replace(/\D/g, ''), 10));
	check('Typing a maximum price filters (debounced)', capped.length > 0 && capped.every((p) => p <= 15000000), capped.join(','));
	await shot(page, 'catalog-filtered-desktop');
	await Promise.all([
		page.waitForResponse((r) => r.url().includes('/marketplace/v1/catalog')),
		page.click('[data-amc-filters] [data-amc-reset]'),
	]);
	await page.waitForFunction(() => document.querySelector('[data-amc-results]').getAttribute('aria-busy') === 'false');
	check('Reset restores the full catalog', (await page.locator('.amc-results__count').textContent()) === total && !page.url().includes('?'), page.url());
	await Promise.all([
		page.waitForResponse((r) => r.url().includes('pg=2')),
		page.click('.amds-pagination [data-amc-page="2"]'),
	]);
	await page.waitForFunction(() => document.querySelector('[data-amc-results]').getAttribute('aria-busy') === 'false');
	check('Pagination loads page 2', page.url().includes('pg=2') && (await page.locator('.amds-pagination .is-current').textContent()) === '2');
	check('No full page reload during AJAX interactions', await page.evaluate(() => window.__amcMarker === true));
	await Promise.all([page.waitForEvent('load'), page.goBack()]);
	check('Back button returns to the previous results', !page.url().includes('pg=2'), page.url());
	await page.goto(base + '/cars/?brand=bmw');
	check('Deep link pre-selects filters', (await page.locator('[data-amc-filters] select[name=brand]').inputValue()) === 'bmw' && (await page.locator('.amc-card').count()) === 1);
	await page.goto(base + '/cars/?brand=does-not-exist');
	check('Empty state is shown when nothing matches', await page.locator('.amds-empty').isVisible());

	console.log('\n== Vehicle page ==');
	await page.goto(base + '/cars-for-sale/');
	await page.locator('.amc-card__title a').first().click();
	await page.waitForLoadState('load');
	check('Card opens the vehicle page', page.url().includes('/car/'));
	const first = await page.locator('[data-amc-gallery-main]').getAttribute('src');
	await page.click('[data-amc-gallery-next]');
	const second = await page.locator('[data-amc-gallery-main]').getAttribute('src');
	check('Gallery: next button changes the photo', first !== second && (await page.locator('[data-amc-gallery-index]').textContent()) === '2');
	await page.locator('[data-amc-gallery-thumb]').nth(2).click();
	check('Gallery: thumbnail click changes the photo', (await page.locator('[data-amc-gallery-main]').getAttribute('src')) !== second);
	check('Contact buttons present', (await page.locator('.amc-summary__actions a').count()) === 2);
	await shot(page, 'vehicle-desktop');

	console.log('\n== Other pages ==');
	for (const [path, name] of [['/dealers/', 'dealers-desktop'], ['/dealer/almadies-auto/', 'dealer-desktop'], ['/join-dealer/', 'join-desktop'], ['/contact/', 'contact-desktop'], ['/faq/', 'faq-desktop']]) {
		await page.goto(base + path);
		await page.waitForLoadState('networkidle');
		await shot(page, name);
	}
	await page.goto(base + '/dealer/almadies-auto/');
	await Promise.all([
		page.waitForResponse((r) => r.url().includes('dealer_page=almadies-auto')),
		page.selectOption('[data-amc-sort]', 'price_desc'),
	]);
	await page.waitForFunction(() => document.querySelector('[data-amc-results]').getAttribute('aria-busy') === 'false');
	const dealerNames = await page.locator('[data-amc-results] .amc-card__dealer').allTextContents();
	check('Dealer page sorting stays within the dealer', dealerNames.length > 0 && dealerNames.every((n) => n.includes('Almadies Auto')) && page.url().includes('/dealer/almadies-auto/'), page.url());
	await ctx.close();

	// ----- Mobile -----
	console.log('\n== Mobile ==');
	const mctx = await browser.newContext({ ...devices['Pixel 7'] });
	const m = await mctx.newPage();
	watch(m);
	for (const [path, name] of [['/', 'home-mobile'], ['/cars/', 'catalog-mobile'], ['/dealer/almadies-auto/', 'dealer-mobile']]) {
		await m.goto(base + path);
		await m.waitForLoadState('networkidle');
		const overflow = await m.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
		check(`${path}: no horizontal scroll on mobile`, overflow <= 0, `${overflow}px`);
		await shot(m, name);
	}
	await m.goto(base + '/');
	check('Mobile: navigation is collapsed', !(await m.locator('#am-nav').isVisible()));
	await m.click('[data-am-nav-toggle]');
	check('Mobile: menu button opens navigation', await m.locator('#am-nav').isVisible());
	await shot(m, 'nav-mobile', false);
	await m.keyboard.press('Escape');
	check('Mobile: Escape closes navigation', !(await m.locator('#am-nav').isVisible()));
	await m.goto(base + '/cars-for-rent/');
	await m.locator('.amc-card__title a').first().click();
	await m.waitForLoadState('networkidle');
	const order = await m.evaluate(() => {
		const y = (s) => document.querySelector(s).getBoundingClientRect().top;
		return y('.amc-gallery') < y('.amc-summary') && y('.amc-summary') < y('.amc-specs');
	});
	check('Mobile vehicle page: photos, then price/contact, then details', order);
	check('Mobile vehicle page: no horizontal scroll', (await m.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)) <= 0);
	await shot(m, 'vehicle-mobile');
	await mctx.close();

	console.log('\n== Console ==');
	check('No JavaScript error or failed request', errors.length === 0, errors.slice(0, 5).join(' | '));

	await browser.close();
	console.log(`\n${pass} passed, ${fail} failed`);
	process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
