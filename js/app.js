/*
  Progressive enhancements for community pages. Every form works without this
  script; it only adds focus management, confirmations, and character counters.
*/
(() => {
  const init = () => {
    const summary = document.querySelector('[data-error-summary]');
    if (summary) {
      summary.focus();
    }

    document.addEventListener('submit', (event) => {
      const form = event.target;
      const message = event.submitter?.dataset.confirm || form.dataset?.confirm;
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });

    document.querySelectorAll('[data-counter-for]').forEach((counter) => {
      const field = document.getElementById(counter.dataset.counterFor);
      const limit = Number(field?.getAttribute('maxlength'));
      if (!field || !limit) return;
      const update = () => {
        const remaining = limit - field.value.length;
        counter.textContent = `${remaining.toLocaleString()} characters left`;
        counter.classList.toggle('is-low', remaining < limit * 0.1);
      };
      field.addEventListener('input', update);
      update();
    });

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
      const target = document.getElementById(button.dataset.copyTarget);
      if (!target || !navigator.clipboard) return;
      button.hidden = false;
      button.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(target.textContent.trim());
          button.textContent = 'Copied';
        } catch {
          button.textContent = 'Copy failed';
        }
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
