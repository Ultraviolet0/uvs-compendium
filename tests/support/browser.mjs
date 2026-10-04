import { chromium } from 'playwright';

/**
 * Launches Chromium for browser tests. BROWSER_CHANNEL selects an installed
 * browser (for example msedge); BROWSER_EXECUTABLE points at a specific binary.
 */
export function launchBrowser() {
  if (process.env.BROWSER_EXECUTABLE) return chromium.launch({ executablePath: process.env.BROWSER_EXECUTABLE });
  if (process.env.BROWSER_CHANNEL) return chromium.launch({ channel: process.env.BROWSER_CHANNEL });
  return chromium.launch();
}

export async function assertNoHorizontalOverflow(assert, page, label) {
  const layout = await page.evaluate(() => ({
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: document.documentElement.clientWidth,
    offenders: [...document.querySelectorAll('body *')].filter((element) => {
      const style = getComputedStyle(element);
      if (style.display === 'none' || style.visibility === 'hidden' || element.closest('dialog:not([open])')) return false;
      const rect = element.getBoundingClientRect();
      return rect.right > document.documentElement.clientWidth + 1;
    }).slice(0, 5).map((element) => `${element.tagName.toLowerCase()}.${element.className}`),
  }));
  assert.ok(layout.documentWidth <= layout.viewportWidth,
    `${label}: scrollWidth=${layout.documentWidth} clientWidth=${layout.viewportWidth} ${JSON.stringify(layout.offenders)}`);
}
