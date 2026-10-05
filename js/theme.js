/*
  Theme selection. Loaded synchronously in <head> so the saved theme is applied
  before first paint. Dark is the default; a signed-in member's saved preference
  wins, otherwise the choice stored in this browser is used.

  The header button shows a sun in dark mode and a moon in light mode; the icon
  follows html[data-theme] in CSS, so it is correct before first paint. The
  button needs this script, so it is revealed by html[data-theme-script] rather
  than rendered and then hidden.
*/
(() => {
  const root = document.documentElement;
  const storageKey = 'uvs-theme';
  const valid = (value) => value === 'dark' || value === 'light';

  const stored = () => {
    try {
      return localStorage.getItem(storageKey);
    } catch {
      return null;
    }
  };

  const initial = valid(root.dataset.themePreference) ? root.dataset.themePreference : stored();
  root.dataset.theme = valid(initial) ? initial : 'dark';
  root.dataset.themeScript = 'ready';

  const label = (theme) => (theme === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
  const describe = (toggle, theme) => {
    toggle.setAttribute('aria-label', label(theme));
    toggle.setAttribute('title', label(theme));
  };

  const apply = (theme) => {
    root.dataset.theme = theme;
    try {
      localStorage.setItem(storageKey, theme);
    } catch {
      // Storage can be unavailable (private mode); the choice then lasts for this page.
    }
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => describe(button, theme));
    document.querySelectorAll('.cf-turnstile').forEach((widget) => { widget.dataset.theme = theme; });
  };

  const persistToAccount = (theme, endpoint) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!token || !endpoint) return;
    const body = new URLSearchParams({ theme, _csrf: token });
    fetch(endpoint, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
    }).catch(() => {});
  };

  const init = () => {
    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
      describe(toggle, root.dataset.theme);
      toggle.addEventListener('click', () => {
        const next = root.dataset.theme === 'light' ? 'dark' : 'light';
        apply(next);
        persistToAccount(next, toggle.dataset.themeEndpoint);
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
