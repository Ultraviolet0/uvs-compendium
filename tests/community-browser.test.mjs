import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { assertNoHorizontalOverflow, launchBrowser } from './support/browser.mjs';
import { ADMIN, activeMember, adminClient, appBase, clearRateLimits, resetDatabase, waitForApp } from './support/app.mjs';

let browser;
let admin;
const url = (path) => new URL(path, appBase).href;

before(async () => {
  await waitForApp();
  resetDatabase();
  admin = await adminClient();
  browser = await launchBrowser();
});
after(async () => { await browser?.close(); });

async function signIn(page, username, password) {
  await page.goto(url('/account/login/'));
  await page.fill('#identifier', username);
  await page.fill('#password', password);
  await Promise.all([page.waitForURL(/\/account\/$/), page.click('form button[type="submit"]')]);
}

function collectErrors(page) {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    // Embedded third-party frames (YouTube) cannot load offline; ignore their network noise.
    if (message.type() === 'error' && !/youtube|ERR_|Failed to load resource/i.test(message.text())) errors.push(message.text());
  });
  return errors;
}

test('dark is the default theme and the toggle switches, persists, and is accessible', async () => {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto(url('/'));
  assert.equal(await page.getAttribute('html', 'data-theme'), 'dark');
  const toggle = page.locator('[data-theme-toggle]');
  assert.equal(await toggle.isVisible(), true);
  assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
  assert.match(await toggle.textContent(), /Light theme/);
  await toggle.focus();
  await page.keyboard.press('Enter');
  assert.equal(await page.getAttribute('html', 'data-theme'), 'light');
  assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
  const background = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
  assert.notEqual(background, 'rgb(5, 5, 5)');

  for (const path of ['/calculators/', '/guides/shopping/', '/account/signup/', '/members/']) {
    await page.goto(url(path));
    assert.equal(await page.getAttribute('html', 'data-theme'), 'light', `${path} keeps the stored theme`);
  }
  await page.reload();
  assert.equal(await page.getAttribute('html', 'data-theme'), 'light');
  await page.locator('[data-theme-toggle]').click();
  assert.equal(await page.getAttribute('html', 'data-theme'), 'dark');
  await context.close();
});

test('the theme is applied before first paint to avoid a flash', async () => {
  const context = await browser.newContext();
  await context.addInitScript(() => localStorage.setItem('uvs-theme', 'light'));
  const page = await context.newPage();
  await context.addInitScript(() => {
    document.addEventListener('DOMContentLoaded', () => { window.themeAtDomReady = document.documentElement.dataset.theme; });
    window.themeBeforeStyles = null;
    new MutationObserver((records, observer) => {
      if (document.querySelector('link[rel="stylesheet"]')) {
        window.themeBeforeStyles = document.documentElement.dataset.theme;
        observer.disconnect();
      }
    }).observe(document, { childList: true, subtree: true });
  });
  await page.goto(url('/calculators/item-price/'), { waitUntil: 'load' });
  assert.equal(await page.evaluate(() => window.themeAtDomReady), 'light');
  assert.equal(await page.evaluate(() => window.themeBeforeStyles), 'light', 'theme is set before stylesheets apply');
  await context.close();
});

test('signed-in members keep their theme preference across browsers', async () => {
  clearRateLimits();
  await activeMember(admin, 'ThemeFan');
  const first = await browser.newContext();
  const page = await first.newPage();
  await signIn(page, 'ThemeFan', 'correct horse battery staple');
  const saved = page.waitForResponse((response) => response.url().endsWith('/account/theme/') && response.status() === 200);
  await page.locator('[data-theme-toggle]').click();
  await saved;
  await first.close();

  const second = await browser.newContext();
  const fresh = await second.newPage();
  await signIn(fresh, 'ThemeFan', 'correct horse battery staple');
  assert.equal(await fresh.getAttribute('html', 'data-theme'), 'light');
  await second.close();
});

test('signup through the browser and the pending dashboard', async () => {
  clearRateLimits();
  const context = await browser.newContext();
  const page = await context.newPage();
  const errors = collectErrors(page);
  await page.goto(url('/account/signup/'));
  await page.fill('#username', 'BrowserHero');
  await page.fill('#email', 'browserhero@example.test');
  await page.fill('#password', 'correct horse battery staple');
  await page.fill('#password_confirmation', 'correct horse battery staple');
  await page.check('#accept_privacy');
  assert.equal(await page.locator('#website').isVisible(), false, 'honeypot is hidden from people');
  await page.waitForTimeout(1100);
  await Promise.all([page.waitForURL(/\/account\/$/), page.click('button:has-text("Create account")')]);
  assert.match(await page.locator('.flash-stack').textContent(), /awaiting approval/);
  assert.match(await page.locator('h1').textContent(), /BrowserHero/);
  assert.equal(await page.locator('.nav-identity-name').textContent(), 'BrowserHero');
  assert.deepEqual(errors, []);

  await page.goto(url('/account/signup/'));
  assert.match(page.url(), /\/account\/$/, 'signed-in members skip the signup form');

  // Server-side validation messages are linked to their fields.
  const other = await browser.newContext();
  const form = await other.newPage();
  await form.goto(url('/account/signup/'));
  await form.fill('#username', 'BrowserHero');
  await form.fill('#email', 'browserhero2@example.test');
  await form.fill('#password', 'correct horse battery staple');
  await form.fill('#password_confirmation', 'correct horse battery staple');
  await form.check('#accept_privacy');
  await form.waitForTimeout(1100);
  await form.click('button:has-text("Create account")');
  await form.waitForSelector('.error-summary');
  assert.equal(await form.getAttribute('#username', 'aria-invalid'), 'true');
  assert.match(await form.getAttribute('#username', 'aria-describedby'), /username-error/);
  assert.equal(await form.evaluate(() => document.activeElement?.classList.contains('error-summary')), true);
  await other.close();
  await context.close();
});

test('guide editor: toolbar, preview, autosave, and unsaved-change protection', async () => {
  clearRateLimits();
  await activeMember(admin, 'EditorUser');
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = collectErrors(page);
  await signIn(page, 'EditorUser', 'correct horse battery staple');
  await page.goto(url('/account/guides/new/'));
  await page.fill('#title', 'Browser Written Guide');
  await page.fill('#summary', 'Written entirely through the browser editor in a test.');
  await page.fill('#body', 'Intro text');
  await Promise.all([page.waitForURL(/\/edit\/$/), page.click('button:has-text("Create draft")')]);

  const toolbar = page.locator('[role="toolbar"]');
  assert.equal(await toolbar.isVisible(), true);
  const body = page.locator('#body');
  await body.click();
  await body.press('End');
  await body.pressSequentially('\n\nimportant');
  await page.evaluate(() => {
    const textarea = document.getElementById('body');
    const start = textarea.value.lastIndexOf('important');
    textarea.setSelectionRange(start, start + 'important'.length);
  });
  await page.click('[data-action="bold"]');
  assert.match(await body.inputValue(), /\*\*important\*\*/);

  // Keyboard navigation within the toolbar uses arrow keys.
  await page.locator('[data-action="heading"]').focus();
  await page.keyboard.press('ArrowRight');
  assert.equal(await page.evaluate(() => document.activeElement?.dataset.action), 'subheading');

  await body.click();
  await body.press('Control+End');
  await page.click('[data-action="youtube"]');
  await page.fill('#youtube-url', 'https://evil.example/video');
  await page.click('#youtube-dialog button[value="insert"]');
  await page.waitForSelector('#youtube-dialog [data-dialog-error]:not([hidden])');
  await page.fill('#youtube-url', 'https://youtu.be/c8MaZZezeMQ');
  await page.click('#youtube-dialog button[value="insert"]');
  assert.match(await body.inputValue(), /\*\*important\*\*\n\n```youtube\nhttps:\/\/youtu\.be\/c8MaZZezeMQ\n```/);

  await page.click('.editor-mode[data-mode="preview"]');
  await page.waitForSelector('#editor-preview strong');
  assert.match(await page.locator('#editor-preview').innerHTML(), /youtube-nocookie\.com\/embed\/c8MaZZezeMQ/);
  assert.equal(await page.locator('.editor-mode[data-mode="preview"]').getAttribute('aria-pressed'), 'true');
  await page.click('.editor-mode[data-mode="write"]');

  await page.keyboard.press('Control+s');
  await page.waitForFunction(() => /Draft saved at|Not saved/.test(document.querySelector('[data-editor-status]')?.textContent || ''));
  assert.match(await page.locator('[data-editor-status]').textContent(), /Draft saved at/);

  await body.pressSequentially(' more');
  const warned = await page.evaluate(() => {
    const event = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(event);
    return event.defaultPrevented;
  });
  assert.equal(warned, true, 'leaving with unsaved changes asks for confirmation');
  assert.deepEqual(errors, []);
  await context.close();
});

const mobileRoutes = (guideSlug) => [
  '/account/login/', '/account/signup/', '/privacy/', '/members/', '/guides/',
  '/account/', '/account/profile/', '/account/security/', '/account/characters/', '/account/guides/', '/account/guides/new/',
  '/admin/', '/admin/users/', '/admin/guides/?filter=all', '/admin/settings/', '/admin/audit/',
  `/members/${ADMIN.username}/`, ...(guideSlug ? [`/guides/${guideSlug}/`] : []),
];

test('new pages have no horizontal overflow at phone and desktop widths in both themes', async () => {
  for (const [width, theme] of [[390, 'dark'], [390, 'light'], [1280, 'light']]) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    await context.addInitScript((value) => localStorage.setItem('uvs-theme', value), theme);
    const page = await context.newPage();
    const errors = collectErrors(page);
    await signIn(page, ADMIN.username, ADMIN.password);
    for (const path of mobileRoutes()) {
      const response = await page.goto(url(path));
      assert.equal(response.status(), 200, path);
      assert.equal(await page.locator('h1').count(), 1, path);
      await assertNoHorizontalOverflow(assert, page, `${path} @${width} ${theme}`);
      const unlabeled = await page.locator('input:not([type="hidden"]), select, textarea')
        .evaluateAll((nodes) => nodes.filter((node) => !node.closest('.form-honeypot') && !node.labels?.length && !node.getAttribute('aria-label')).map((node) => node.id || node.name));
      assert.deepEqual(unlabeled, [], path);
    }
    assert.deepEqual(errors, []);
    await context.close();
  }
});
