/*
  Guide editor enhancements: formatting toolbar, server-rendered preview,
  autosave with conflict detection, unsaved-change warnings, and image/video/link
  dialogs. Without this script the editor is a plain Markdown textarea that
  still saves, submits, and uploads through ordinary forms.
*/
(() => {
  const form = document.getElementById('guide-form');
  const textarea = document.getElementById('body');
  if (!form || !textarea) return;

  const csrf = form.querySelector('input[name="_csrf"]')?.value || '';
  const status = form.querySelector('[data-editor-status]');
  const shell = form.querySelector('[data-editor-shell]');
  const toolbar = form.querySelector('[data-editor-toolbar]');
  const modes = form.querySelector('[data-editor-modes]');
  const preview = document.getElementById('editor-preview');
  const lockField = form.querySelector('input[name="lock_version"]');
  const autosaveUrl = form.dataset.autosaveUrl || '';
  const previewUrl = form.dataset.previewUrl || '';
  const uploadUrl = form.dataset.uploadUrl || '';
  const guideId = form.dataset.guideId || '0';

  let dirty = false;
  let saving = false;
  let conflict = false;
  let previewTimer = 0;
  let previewRequest = 0;

  const say = (message) => {
    if (status) status.textContent = message;
  };

  const markDirty = () => {
    dirty = true;
    if (!conflict) say('Unsaved changes');
  };

  form.addEventListener('input', (event) => {
    if (event.target.closest('dialog')) return;
    markDirty();
    if (shell?.dataset.mode === 'split') schedulePreview();
  });

  form.addEventListener('submit', (event) => {
    setTimeout(() => {
      if (!event.defaultPrevented) dirty = false;
    }, 0);
  });

  window.addEventListener('beforeunload', (event) => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });

  // ---- Text manipulation ----------------------------------------------------

  const replaceSelection = (before, after = '', placeholder = '') => {
    const { selectionStart: start, selectionEnd: end, value } = textarea;
    const selected = value.slice(start, end) || placeholder;
    textarea.setRangeText(before + selected + after, start, end, 'end');
    const cursorStart = start + before.length;
    textarea.setSelectionRange(cursorStart, cursorStart + selected.length);
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  };

  const prefixLines = (prefix, numbered = false) => {
    const { selectionStart: start, selectionEnd: end, value } = textarea;
    const lineStart = value.lastIndexOf('\n', start - 1) + 1;
    const lineEnd = value.indexOf('\n', end) === -1 ? value.length : value.indexOf('\n', end);
    const lines = value.slice(lineStart, lineEnd).split('\n');
    const result = lines.map((line, index) => (numbered ? `${index + 1}. ` : prefix) + line).join('\n');
    textarea.setRangeText(result, lineStart, lineEnd, 'select');
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  };

  // Blocks go after the current selection on their own lines; selected text is kept.
  const insertBlock = (text) => {
    const { selectionEnd: start, value } = textarea;
    const needsBreak = start > 0 && value[start - 1] !== '\n' ? '\n\n' : (start > 1 && value[start - 2] !== '\n' ? '\n' : '');
    textarea.setRangeText(`${needsBreak}${text}\n`, start, start, 'end');
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  };

  // ---- Dialogs --------------------------------------------------------------

  const openDialog = (id, onInsert) => {
    const dialog = document.getElementById(id);
    const dialogForm = dialog?.querySelector('form');
    if (!dialog || !dialogForm || typeof dialog.showModal !== 'function') return false;
    const error = dialog.querySelector('[data-dialog-error]');
    if (error) { error.hidden = true; error.textContent = ''; }
    const selection = [textarea.selectionStart, textarea.selectionEnd];
    let busy = false;

    const handleSubmit = async (event) => {
      if (event.submitter?.value !== 'insert') return;
      event.preventDefault();
      if (busy) return;
      busy = true;
      textarea.setSelectionRange(...selection);
      const problem = await onInsert(dialog);
      busy = false;
      if (problem) {
        if (error) { error.textContent = problem; error.hidden = false; }
        dialog.querySelector('input')?.focus();
        return;
      }
      dialog.close('insert');
    };
    const handleClose = () => {
      dialogForm.removeEventListener('submit', handleSubmit);
      dialog.removeEventListener('close', handleClose);
      textarea.focus();
    };
    dialogForm.addEventListener('submit', handleSubmit);
    dialog.addEventListener('close', handleClose);
    dialog.returnValue = '';
    dialog.showModal();
    dialog.querySelector('input')?.focus();
    return true;
  };

  const youtubeId = (url) => {
    try {
      const parsed = new URL(url);
      const host = parsed.hostname.toLowerCase();
      if (!['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'www.youtu.be',
        'youtube-nocookie.com', 'www.youtube-nocookie.com'].includes(host)) return null;
      const id = host.endsWith('youtu.be') ? parsed.pathname.slice(1)
        : parsed.pathname === '/watch' ? parsed.searchParams.get('v')
        : (parsed.pathname.match(/^\/(?:embed|shorts|live|v)\/([^/]+)/) || [])[1];
      return /^[A-Za-z0-9_-]{11}$/.test(id || '') ? id : null;
    } catch {
      return null;
    }
  };

  const uploadImage = async (file, alt) => {
    const body = new FormData();
    body.append('_csrf', csrf);
    body.append('image', file);
    body.append('alt', alt);
    const response = await fetch(uploadUrl, {
      method: 'POST', body, credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error || 'The upload failed.');
    return data;
  };

  const addMediaCard = (data, alt) => {
    const list = document.querySelector('[data-media-list]');
    if (!list) return;
    document.querySelector('[data-media-empty]')?.remove();
    const item = document.createElement('li');
    item.className = 'media-card';
    item.dataset.markdown = data.markdown;
    const image = document.createElement('img');
    image.src = data.url;
    image.alt = alt;
    image.width = data.width;
    image.height = data.height;
    const code = document.createElement('code');
    code.className = 'media-code';
    code.textContent = data.markdown;
    item.append(image, code);
    list.append(item);
  };

  const actions = {
    heading: () => prefixLines('## '),
    subheading: () => prefixLines('### '),
    bold: () => replaceSelection('**', '**', 'bold text'),
    italic: () => replaceSelection('_', '_', 'italic text'),
    bullets: () => prefixLines('- '),
    numbers: () => prefixLines('', true),
    quote: () => prefixLines('> '),
    code: () => replaceSelection('\n```\n', '\n```\n', 'formula or code'),
    callout: () => insertBlock('> [!TIP]\n> Your tip here.'),
    table: () => insertBlock('| Column | Column |\n| --- | --- |\n| Value | Value |'),
    link: () => {
      const selected = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd);
      const text = document.getElementById('link-text');
      if (text) text.value = selected;
      const opened = openDialog('link-dialog', (dialog) => {
        const label = dialog.querySelector('#link-text').value.trim() || 'link';
        const url = dialog.querySelector('#link-url').value.trim();
        if (!/^(https?:\/\/|mailto:|\/(?!\/)|#)/i.test(url)) return 'Use a full https:// address, a site path, or an email link.';
        textarea.setRangeText(`[${label.replace(/[[\]]/g, '')}](${url.replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\s/g, '%20')})`,
          textarea.selectionStart, textarea.selectionEnd, 'end');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        dialog.querySelector('#link-url').value = '';
        return null;
      });
      if (!opened) replaceSelection('[', '](https://)', 'link text');
    },
    youtube: () => {
      const opened = openDialog('youtube-dialog', (dialog) => {
        const url = dialog.querySelector('#youtube-url').value.trim();
        if (!youtubeId(url)) return 'Enter a YouTube video link such as https://www.youtube.com/watch?v=… or https://youtu.be/…';
        const caption = dialog.querySelector('#youtube-caption').value.trim();
        insertBlock(['```youtube', url, caption, '```'].filter(Boolean).join('\n'));
        dialog.querySelector('#youtube-url').value = '';
        dialog.querySelector('#youtube-caption').value = '';
        return null;
      });
      if (!opened) insertBlock('```youtube\nhttps://www.youtube.com/watch?v=VIDEO_ID\n```');
    },
    image: () => {
      if (!uploadUrl) return;
      openDialog('image-dialog', async (dialog) => {
        const fileInput = dialog.querySelector('#image-dialog-file');
        const altInput = dialog.querySelector('#image-dialog-alt');
        const file = fileInput.files?.[0];
        if (!file) return 'Choose an image file, or use Insert beside an uploaded image.';
        say('Uploading image…');
        try {
          const data = await uploadImage(file, altInput.value.trim());
          insertBlock(data.markdown);
          addMediaCard(data, altInput.value.trim());
          fileInput.value = '';
          altInput.value = '';
          say('Image uploaded and inserted.');
          return null;
        } catch (error) {
          say('');
          return error.message;
        }
      });
    },
  };

  // ---- Toolbar ----------------------------------------------------------------

  if (toolbar) {
    toolbar.hidden = false;
    const buttons = [...toolbar.querySelectorAll('button')];
    buttons.forEach((button, index) => {
      button.tabIndex = index === 0 ? 0 : -1;
      button.addEventListener('click', () => actions[button.dataset.action]?.());
    });
    toolbar.addEventListener('keydown', (event) => {
      const enabled = buttons.filter((button) => !button.disabled);
      const current = enabled.indexOf(document.activeElement);
      if (current === -1) return;
      let next = null;
      if (event.key === 'ArrowRight') next = enabled[(current + 1) % enabled.length];
      if (event.key === 'ArrowLeft') next = enabled[(current - 1 + enabled.length) % enabled.length];
      if (event.key === 'Home') next = enabled[0];
      if (event.key === 'End') next = enabled[enabled.length - 1];
      if (!next) return;
      event.preventDefault();
      buttons.forEach((button) => { button.tabIndex = -1; });
      next.tabIndex = 0;
      next.focus();
    });
  }

  textarea.addEventListener('keydown', (event) => {
    if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
    const key = event.key.toLowerCase();
    const map = { b: 'bold', i: 'italic', k: 'link' };
    if (map[key]) {
      event.preventDefault();
      actions[map[key]]();
    } else if (key === 's') {
      event.preventDefault();
      autosave(true);
    }
  });

  document.querySelectorAll('[data-insert-media]').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', () => {
      const markdown = button.closest('[data-markdown]')?.dataset.markdown;
      if (markdown) insertBlock(markdown);
    });
  });

  // ---- Preview ----------------------------------------------------------------

  const renderPreview = async () => {
    if (!preview || !previewUrl) return;
    const request = ++previewRequest;
    preview.setAttribute('aria-busy', 'true');
    const body = new URLSearchParams({ _csrf: csrf, body: textarea.value, guide_id: guideId });
    try {
      const response = await fetch(previewUrl, {
        method: 'POST', body, credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
      });
      const data = await response.json();
      if (request !== previewRequest) return;
      if (!response.ok) throw new Error(data.error || 'Preview failed.');
      // The server renders with escaped HTML input and validated links/media only.
      preview.innerHTML = data.html || '<p class="text-muted">Nothing to preview yet.</p>';
      if (data.warnings?.length) {
        const note = document.createElement('p');
        note.className = 'notice notice-warning';
        note.textContent = data.warnings.join(' ');
        preview.prepend(note);
      }
    } catch (error) {
      if (request === previewRequest) preview.textContent = error.message || 'Preview is unavailable right now.';
    } finally {
      preview.removeAttribute('aria-busy');
    }
  };

  const schedulePreview = () => {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(renderPreview, 600);
  };

  const setMode = (mode) => {
    if (!shell || !preview) return;
    shell.dataset.mode = mode;
    textarea.hidden = mode === 'preview';
    preview.hidden = mode === 'write';
    modes?.querySelectorAll('[data-mode]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.mode === mode));
    });
    if (toolbar) toolbar.hidden = mode === 'preview';
    if (mode !== 'write') renderPreview();
  };

  if (modes) {
    modes.hidden = false;
    modes.querySelectorAll('[data-mode]').forEach((button) => {
      button.addEventListener('click', () => setMode(button.dataset.mode));
    });
  }

  // ---- Autosave -----------------------------------------------------------------

  const autosave = async (manual = false) => {
    if (!autosaveUrl || saving || conflict || (!dirty && !manual)) return;
    saving = true;
    say('Saving…');
    const body = new FormData(form);
    body.set('intent', 'autosave');
    try {
      const response = await fetch(autosaveUrl, {
        method: 'POST', body, credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
      });
      const data = await response.json().catch(() => ({}));
      if (response.status === 409) {
        conflict = true;
        say('Not saved: this guide changed in another window. Copy your text, then reload the page.');
        return;
      }
      if (!response.ok) {
        say(`Not saved: ${data.error || 'please check the form.'}`);
        return;
      }
      if (lockField) lockField.value = String(data.lock_version);
      dirty = false;
      const time = new Date(data.saved_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      say(`Draft saved at ${time}.`);
    } catch {
      say('Not saved: the connection was interrupted. Your text is still here.');
    } finally {
      saving = false;
    }
  };

  if (autosaveUrl) {
    setInterval(() => autosave(false), 30000);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') autosave(false);
    });
  }
})();
