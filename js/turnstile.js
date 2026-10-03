/*
  Loads Cloudflare Turnstile only on pages that render a widget, after matching
  the widget to the current site theme.
*/
(() => {
  const widgets = document.querySelectorAll('.cf-turnstile');
  if (widgets.length === 0) return;
  const theme = document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
  widgets.forEach((widget) => { widget.dataset.theme = theme; });
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
  script.async = true;
  script.defer = true;
  document.head.append(script);
})();
