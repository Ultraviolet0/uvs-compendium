import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { chromium } from 'playwright';

const base = process.env.SITE_URL || 'http://localhost:8080';
let browser;
let context;

async function assertNoHorizontalOverflow(page, route, stage) {
  const layout = await page.evaluate(() => {
    const documentWidth = document.documentElement.scrollWidth;
    const viewportWidth = document.documentElement.clientWidth;
    const elements = [...document.querySelectorAll('*')].flatMap((element) => {
      const style = getComputedStyle(element);
      if (style.display === 'none' || style.visibility === 'hidden') return [];
      const rect = element.getBoundingClientRect();
      if (rect.right <= viewportWidth && rect.left >= 0
        && (element.scrollWidth <= element.clientWidth || style.overflowX !== 'visible')) return [];
      return [{
        tag: element.tagName.toLowerCase(), id: element.id,
        className: typeof element.className === 'string' ? element.className : '',
        left: rect.left, right: rect.right, width: rect.width,
        scrollWidth: element.scrollWidth, clientWidth: element.clientWidth,
        overflowX: style.overflowX, minWidth: style.minWidth
      }];
    });
    return { documentWidth, viewportWidth, elements };
  });
  assert.ok(layout.documentWidth <= layout.viewportWidth,
    `${route} (${stage}): document scrollWidth=${layout.documentWidth}, clientWidth=${layout.viewportWidth}; `
      + `out-of-viewport elements=${JSON.stringify(layout.elements)}`);
}

before(async () => {
  let ready = false;
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try {
      await fetch(base, { signal: AbortSignal.timeout(1000) });
      ready = true;
      break;
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 250));
    }
  }
  assert.ok(ready, `Local site did not start at ${base}`);
  browser = await chromium.launch(process.env.BROWSER_CHANNEL
    ? { channel: process.env.BROWSER_CHANNEL }
    : {});
  context = await browser.newContext();
});
after(async () => { await browser?.close(); });

const routes = [
  '/', '/calculators/', '/calculators/hellfire-item-price/',
  '/calculators/shop-qlvl/', '/calculators/premium-item-checker/',
  '/calculators/warrior-repair/', '/calculators/hellfire-damage/',
  '/guides/', '/guides/shopping/', '/guides/fast-character-development/',
  '/guides/max-shopping-video/', '/guides/template/'
];

test('public routes, shared layout, local assets, and browser scripts', async () => {
  for (const route of routes) {
    const page = await context.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('console', (message) => {
      if (message.type() === 'error') pageErrors.push(message.text());
    });
    const response = await page.goto(new URL(route, base).href, { waitUntil: 'load' });
    assert.equal(response.status(), 200, route);
    assert.equal(await page.locator('h1').count(), 1, route);
    assert.equal(await page.locator('header.site-sidebar').count(), 1, route);
    assert.equal(await page.locator('footer.site-footer').count(), 1, route);
    assert.equal(await page.locator('a.skip-link[href="#main-content"]').count(), 1, route);
    assert.deepEqual(pageErrors, [], route);
    const assets = await page.locator('link[rel="stylesheet"][href], link[rel="icon"][href], script[src], img[src], video[src], source[src], a[href$=".pdf"], a[href$=".mp4"]')
      .evaluateAll((nodes) => nodes.map((node) => node.href || node.src)
        .filter((url) => new URL(url).origin === location.origin));
    for (const asset of assets) {
      const result = await context.request.get(asset);
      assert.equal(result.status(), 200, `${route}: ${asset}`);
    }
    // Basic accessibility invariants without imposing a formatting rewrite.
    assert.equal(await page.locator('img:not([alt])').count(), 0, route);
    const unlabeled = await page.locator('input:not([type="hidden"]), select, textarea')
      .evaluateAll((nodes) => nodes.filter((node) => !node.labels?.length && !node.getAttribute('aria-label')).length);
    assert.equal(unlabeled, 0, route);
    await page.close();
  }
});

test('development files are denied by Apache', async () => {
  for (const path of [
    '/.git/config', '/.github/workflows/ci.yml', '/.dockerignore', '/.gitignore', '/.htaccess',
    '/.env.example', '/AGENTS.md', '/README.md', '/Dockerfile', '/compose.yaml',
    '/package.json', '/package-lock.json', '/docs/development.md',
    '/docs/game-data.md', '/docs/corrections.md', '/tests/site.test.mjs',
    '/scripts/check.mjs', '/includes/public_header.php', '/css/',
    '/calculators/breadcrumbs.php', '/calculators/shop-qlvl/calculator.php',
    '/calculators/hellfire-item-price/calculator.php'
  ]) {
    const response = await context.request.get(new URL(path, base).href);
    assert.ok([403, 404].includes(response.status()), `${path}: ${response.status()}`);
  }
  assert.equal((await context.request.get(new URL('/reference/jarulf162.pdf', base).href)).status(), 200);
});

test('premium modules are JavaScript and revalidate their cache', async () => {
  for (const module of ['scripts.mjs', 'rules.mjs', 'calculate.mjs', 'data.mjs', 'availability.mjs', 'price.mjs']) {
    const response = await context.request.get(new URL(`/calculators/premium-item-checker/js/${module}`, base).href);
    assert.equal(response.status(), 200, module);
    assert.match(response.headers()['content-type'], /^(?:text|application)\/javascript\b/i, module);
    assert.match(response.headers()['cache-control'], /(?:^|,)\s*no-cache\b/i, module);
  }
});

test('guide navigation and shared menu respond to interaction', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/guides/shopping/', base).href);
  const menu = page.locator('button[aria-controls="nav-guides-menu"]');
  assert.equal(await menu.getAttribute('aria-expanded'), 'true');
  await menu.click();
  assert.equal(await menu.getAttribute('aria-expanded'), 'false');
  await menu.click();
  assert.equal(await menu.getAttribute('aria-expanded'), 'true');
  const contentsLink = page.locator('[data-in-page-nav] a[href^="#"]').first();
  assert.ok(await contentsLink.count() > 0);
  const target = await contentsLink.getAttribute('href');
  await contentsLink.click();
  assert.equal(new URL(page.url()).hash, target);
  await page.close();
});

test('shop qlvl: known character-level outputs', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/shop-qlvl/', base).href);
  await page.locator('#clvl').fill('25');
  assert.match(await page.locator('#wirtresult').textContent(), /Base items:\s+1-25\s+Affixes:\s+25-50/);
  assert.match(await page.locator('#adriaresult').textContent(), /1-14\s+Prefixes.*1-28/s);
  await page.locator('#clvl').fill('50');
  assert.match(await page.locator('#wirtresult').textContent(), /25-60/);
  await page.close();
});

test('Warrior repair: documented deterministic path', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/warrior-repair/', base).href);
  assert.match(await page.locator('#warrior-repair-results').textContent(), /Single Repair Solution/);
  assert.equal(await page.locator('#warrior-repair-results tbody').first().locator('tr').first().locator('td').first().textContent(), '17-19');
  assert.equal(await page.locator('#warrior-repair-results tbody').first().locator('tr').first().locator('td').last().textContent(), '245');
  await page.locator('#repair-target').fill('250');
  assert.equal(await page.locator('#warrior-repair-results tbody').first().locator('tr').first().locator('td').first().textContent(), '42-50');
  await page.close();
});

test('item price: base value and resale update', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/hellfire-item-price/', base).href);
  await page.locator('#item-class').selectOption('Helm');
  await page.locator('#base-item').selectOption({ label: 'Skull Cap' });
  assert.equal((await page.locator('#Price').textContent()).trim(), '25');
  await page.locator('input[name="Source"][value="Sale"]').check();
  assert.equal((await page.locator('#Price').textContent()).trim(), '6');
  await page.close();
});

test('premium checker: base-item data populates from selection', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/premium-item-checker/', base).href);
  await page.locator('#premium-base-item').selectOption({ label: 'Cap' });
  assert.match(await page.locator('#display1').textContent(), /Cap\s+Armor Class: 1 - 3\s+DUR: 15/);
  assert.match(await page.locator('#display1').textContent(), /G\/A Price:\s+15\s+qlvl:\s+1/);
  await page.close();
});

test('premium checker: quest items have no magic-affix choices and plain bases name the basic shop', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/premium-item-checker/', base).href);
  await page.locator('#premium-base-item').selectOption({ label: 'Cap' });
  assert.equal(await page.locator('#premium-availability li').first().locator('strong').textContent(),
    'Griswold (basic items)');
  for (const name of ['Auric Amulet', 'Bovine Plate']) {
    await page.locator('#premium-base-item').selectOption({ label: name });
    assert.deepEqual(await page.locator('#premium-prefix option').evaluateAll((options) =>
      options.map((option) => option.value)), ['0']);
    assert.deepEqual(await page.locator('#premium-suffix option').evaluateAll((options) =>
      options.map((option) => option.value)), ['0']);
  }
  await page.close();
});

test('premium checker announces affix changes and incompatible selections cleared', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/premium-item-checker/', base).href);
  await page.locator('#premium-base-item').selectOption({ label: 'Bastard Sword' });
  await page.locator('#premium-prefix').selectOption({ label: "Knight's" });
  await page.locator('#premium-suffix').selectOption({ label: 'Speed' });
  assert.match(await page.locator('#premium-status').textContent(),
    /Knight's Bastard Sword of Speed/);
  await page.locator('#premium-suffix').selectOption({ label: 'Haste' });
  assert.match(await page.locator('#premium-status').textContent(),
    /Knight's Bastard Sword of Haste/);
  await page.locator('#premium-price-mode').selectOption({ label: 'On' });
  assert.match(await page.locator('#premium-status').textContent(), /Detailed prices on/);
  await page.evaluate(() => {
    const baseSelect = document.getElementById('premium-base-item');
    baseSelect.add(new Option('Auric Amulet', '157'));
    baseSelect.value = '157';
    baseSelect.dispatchEvent(new Event('change', { bubbles: true }));
  });
  assert.equal(await page.locator('#premium-prefix').inputValue(), '0');
  assert.equal(await page.locator('#premium-suffix').inputValue(), '0');
  assert.match(await page.locator('#premium-status').textContent(),
    /Incompatible prefix and suffix cleared/);
  await page.close();
});

test('premium checker: Hellfire Griswold +3 slot reaches affixes one level earlier', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/premium-item-checker/', base).href);
  await page.locator('#premium-base-item').selectOption({ label: 'Bastard Sword' });
  for (const [prefix, suffix, firstLevel] of [
    ["Knight's", 'Speed', 20],
    ["Knight's", 'Haste', 24],
    ["King's", 'Speed', 25],
    ["King's", 'Haste', 25]
  ]) {
    await page.locator('#premium-prefix').selectOption({ label: prefix });
    await page.locator('#premium-suffix').selectOption({ label: suffix });
    assert.equal(await page.locator('#premium-availability li').first().locator('.premium-availability-range').textContent(),
      `${firstLevel} - 50`, `${prefix} / ${suffix}`);
  }
  assert.equal(await page.locator('.premium-level-note').count(), 0);
  assert.match(await page.locator('#premium-level-hint').textContent(), /generation level \(ilvl\)/);
  await page.close();
});

test('premium checker: sub-30 Griswold source levels expire after level 31', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/premium-item-checker/', base).href);
  await page.locator('#premium-base-item').selectOption({ label: 'Helm' });
  await page.locator('#premium-prefix').selectOption({ label: 'Glorious' });
  assert.equal(await page.locator('#premium-availability li').first()
    .locator('.premium-availability-range').textContent(), '11 - 31');
  await page.close();
});

test('shopping guide shows the Wirt example and remains usable at narrow and desktop widths', async () => {
  for (const width of [390, 1440]) {
    const page = await context.newPage({ viewport: { width, height: 844 } });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(new URL('/guides/shopping/', base).href);
    const example = page.locator('#godly-plate-of-the-whale img');
    await example.scrollIntoViewIfNeeded();
    await page.waitForFunction(() => document.querySelector('#godly-plate-of-the-whale img')?.naturalWidth > 0);
    assert.match(await page.locator('#godly-plate-of-the-whale figcaption').textContent(), /offered by Wirt/);
    assert.match(await page.locator('#building-better-anchors').textContent(), /deterministic sequence/);
    await assertNoHorizontalOverflow(page, '/guides/shopping/', `${width}px example`);
    assert.deepEqual(errors, [], `${width}px guide`);
    await page.close();
  }
});

test('premium checker: Hellfire Wirt limits and retry fallback are described without an impossibility claim', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/premium-item-checker/', base).href);
  await page.locator('#premium-base-item').selectOption({ label: 'Full Plate Mail' });
  await page.locator('#premium-prefix').selectOption({ label: 'Godly' });
  assert.match(await page.locator('#premium-availability').textContent(), /Wirt.*30 - 50/);
  await page.locator('#premium-suffix').selectOption({ label: 'the Whale' });
  assert.match(await page.locator('#premium-availability').textContent(), /Wirt \(rare retry fallback\).*30 - 50/);
  assert.match(await page.locator('#premium-level-hint').textContent(), /rare Hellfire retries/);
  await page.locator('#premium-reset').click();
  await page.locator('#premium-base-item').selectOption({ label: 'Cap' });
  await page.locator('#premium-prefix').selectOption({ label: 'Holy' });
  await page.locator('#premium-suffix').selectOption({ label: 'Giants' });
  assert.match(await page.locator('#premium-availability').textContent(), /No source identified by the current model/);
  await page.close();
});

async function assertPremiumControlHeights(page, route) {
  const select = await page.locator('#premium-price-mode').boundingBox();
  const reset = await page.locator('#premium-reset').boundingBox();
  assert.ok(Math.abs(select.height - reset.height) <= 1,
    `${route}: price select ${select.height}px and reset button ${reset.height}px differ`);
}

test('premium checker: controls, reset, and combined page work by keyboard and on narrow screens', async () => {
  for (const route of ['/calculators/premium-item-checker/', '/calculators/']) {
    const page = await context.newPage();
    await page.setViewportSize({ width: 390, height: 844 });
    assert.deepEqual(page.viewportSize(), { width: 390, height: 844 });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(new URL(route, base).href);
    await assertPremiumControlHeights(page, route);
    await assertNoHorizontalOverflow(page, route, 'initial');
    assert.match(await page.locator('#display1').textContent(), /^$/);
    await page.locator('#premium-base-item').selectOption({ label: 'Bastard Sword' });
    await page.locator('#premium-prefix').selectOption({ label: "Knight's" });
    await page.locator('#premium-suffix').selectOption({ label: 'Speed' });
    assert.match(await page.locator('#display1').textContent(), /Knight's Sword of Speed/);
    assert.equal(await page.locator('#premium-availability li').first().locator('strong').textContent(), 'Griswold');
    await assertNoHorizontalOverflow(page, route, 'selected');
    await page.locator('#premium-prefix').focus();
    await page.keyboard.press('Tab');
    assert.ok(await page.locator('#premium-base-item').evaluate((el) => el === document.activeElement));
    await page.keyboard.press('Tab');
    assert.ok(await page.locator('#premium-suffix').evaluate((el) => el === document.activeElement));
    await page.locator('#premium-price-mode').selectOption({ label: 'On' });
    const detailedPrices = await page.locator('#display3').textContent();
    assert.match(detailedPrices, /Knight's/);
    assert.ok(detailedPrices.length > 200, `${route}: detailed price result is unexpectedly short`);
    await assertNoHorizontalOverflow(page, route, 'detailed prices');
    await page.locator('#premium-reset').focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('#premium-base-item').inputValue(), '0');
    assert.equal(await page.locator('#premium-price-mode').inputValue(), 'Off');
    assert.match(await page.locator('#premium-availability').textContent(), /Choose a base item/);
    assert.ok(await page.locator('#premium-base-item').evaluate((el) => el === document.activeElement));
    await assertNoHorizontalOverflow(page, route, 'after keyboard reset');
    assert.deepEqual(errors, [], route);
    await page.close();
  }
});

test('premium checker: standalone and combined layouts fit a desktop viewport with detailed results', async () => {
  for (const route of ['/calculators/premium-item-checker/', '/calculators/']) {
    const page = await context.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto(new URL(route, base).href);
    await assertPremiumControlHeights(page, route);
    await assertNoHorizontalOverflow(page, route, 'desktop initial');
    await page.locator('#premium-base-item').selectOption({ label: 'Bastard Sword' });
    await page.locator('#premium-prefix').selectOption({ label: "Knight's" });
    await page.locator('#premium-suffix').selectOption({ label: 'Speed' });
    await page.locator('#premium-price-mode').selectOption({ label: 'On' });
    const detailedPrices = await page.locator('#display3').textContent();
    assert.match(detailedPrices, /Knight's/);
    assert.ok(detailedPrices.length > 200, `${route}: detailed price result is unexpectedly short`);
    await assertNoHorizontalOverflow(page, route, 'desktop detailed prices');
    assert.deepEqual(errors, [], route);
    await page.close();
  }
});

test('damage calculator: class preset changes deterministic output', async () => {
  const page = await context.newPage();
  await page.goto(new URL('/calculators/hellfire-damage/', base).href);
  assert.equal((await page.locator('#characterDamageOut').textContent()).trim(), '125');
  await page.locator('#characterClass').selectOption('rogue');
  assert.equal((await page.locator('#characterDamageOut').textContent()).trim(), '76.25');
  await page.close();
});

test('legacy shop URL redirects to the canonical route', async () => {
  const response = await context.request.get(new URL('/shopqlvl/?test=1', base).href, { maxRedirects: 0 });
  assert.equal(response.status(), 301);
  assert.equal(response.headers().location, '/calculators/shop-qlvl/?test=1');
});
