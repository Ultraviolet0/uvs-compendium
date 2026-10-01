import { prefixx, suffixx, basee } from './data.mjs';
import { calculatePremiumItem } from './calculate.mjs';
import {
  getAvailableOptions,
  isBaseAvailable,
  isPrefixAvailable,
  isSuffixAvailable
} from './rules.mjs';

const form = document.forms.premium;

if (form) {
  const controls = {
    prefix: form.elements.prefixx,
    base: form.elements.basee,
    suffix: form.elements.suffixx,
    price: form.elements.calcprice,
    gameVersion: form.elements.gameVersion,
    gameMode: form.elements.gameMode
  };
  const output = {
    summary: document.getElementById('display1'),
    availability: document.getElementById('premium-availability'),
    details: document.getElementById('display2'),
    price: document.getElementById('display3'),
    status: document.getElementById('premium-status'),
    hint: document.getElementById('premium-level-hint')
  };
  const selection = { baseIndex: 0, prefixIndex: 0, suffixIndex: 0 };

  function replaceOptions(select, indices, items, selectedIndex) {
    const options = indices.map((index) => new Option(items[index].name, String(index)));
    select.replaceChildren(...options);
    select.value = String(selectedIndex);
  }

  function refreshOptions() {
    const choices = getAvailableOptions({ ...selection,
      gameVersion: controls.gameVersion.value, gameMode: controls.gameMode.value });
    replaceOptions(controls.base, choices.bases, basee, selection.baseIndex);
    replaceOptions(controls.prefix, choices.prefixes, prefixx, selection.prefixIndex);
    replaceOptions(controls.suffix, choices.suffixes, suffixx, selection.suffixIndex);
  }

  function isSelectedValid(field) {
    const { baseIndex, prefixIndex, suffixIndex } = selection;
    if (field === 'base') return isBaseAvailable(baseIndex, prefixIndex, suffixIndex);
    if (field === 'prefix') return isPrefixAvailable(baseIndex, prefixIndex, suffixIndex);
    return isSuffixAvailable(baseIndex, prefixIndex, suffixIndex);
  }

  function keepValidSelection(changedField) {
    const cleared = [];
    const otherFields = {
      base: ['prefix', 'suffix'],
      prefix: ['base', 'suffix'],
      suffix: ['base', 'prefix']
    }[changedField];
    for (const field of otherFields) {
      if (!isSelectedValid(field) && selection[`${field}Index`] !== 0) {
        cleared.push(field);
        selection[`${field}Index`] = 0;
      }
    }
    return cleared;
  }

  function renderAvailability(items, hasSelection) {
    if (!hasSelection) {
      const prompt = document.createElement('li');
      prompt.className = 'premium-availability-empty';
      prompt.textContent = 'Choose a base item to see possible sources.';
      output.availability.replaceChildren(prompt);
      return;
    }
    if (items.length === 0) {
      const empty = document.createElement('li');
      empty.className = 'premium-availability-empty';
      empty.textContent = controls.gameVersion.value === 'hellfire'
        ? 'No source identified by the current model. Rare vendor retries or unmodeled conditions may still allow it.'
        : 'No source identified by the current Diablo level and value rules.';
      output.availability.replaceChildren(empty);
      return;
    }
    const rows = items.map(({ source, levelType, range }) => {
      const item = document.createElement('li');
      const name = document.createElement('strong');
      const level = document.createElement('span');
      const value = document.createElement('span');
      name.textContent = source;
      level.textContent = levelType;
      value.textContent = range;
      value.className = 'premium-availability-range';
      item.append(name, level, value);
      return item;
    });
    output.availability.replaceChildren(...rows);
  }

  function render(cleared = []) {
    const { baseIndex, prefixIndex, suffixIndex } = selection;
    const result = calculatePremiumItem(baseIndex, prefixIndex, suffixIndex,
      controls.price.selectedIndex === 1,
      { gameVersion: controls.gameVersion.value, gameMode: controls.gameMode.value });
    output.summary.textContent = result.itemSummary;
    output.details.textContent = result.display2;
    output.price.textContent = result.display3;
    const basicStockNote = controls.gameMode.value === 'single-player'
      ? ' Basic Griswold stock and Adria use deepest dungeon level visited.' : '';
    output.hint.textContent = controls.gameVersion.value === 'hellfire'
      ? `Source Level is the item generation level (ilvl). Vendor ranges show possible rolls; class, stats, carried gear, and rare Hellfire retries can affect offers.${basicStockNote}`
      : `Source Level is the item generation level (ilvl). Diablo vendor price caps apply; vendor ranges are possible rolls.${basicStockNote}`;
    renderAvailability(result.availability, baseIndex !== 0);
    const combination = [
      prefixIndex ? prefixx[prefixIndex].name : '',
      baseIndex ? basee[baseIndex].name : 'a base item',
      suffixIndex ? `of ${suffixx[suffixIndex].name}` : ''
    ].filter(Boolean).join(' ');
    const pendingAffixes = [
      prefixIndex ? `prefix ${prefixx[prefixIndex].name}` : '',
      suffixIndex ? `suffix ${suffixx[suffixIndex].name}` : ''
    ].filter(Boolean).join(', ');
    const message = baseIndex === 0
      ? pendingAffixes
        ? `Selected ${pendingAffixes}. Choose a base item to see availability.`
        : 'Choose a base item to see availability.'
      : `Result updated: ${combination}. Detailed prices ${controls.price.selectedIndex === 1 ? 'on' : 'off'}.`;
    output.status.textContent = cleared.length
      ? `${message} Incompatible ${cleared.join(' and ')} cleared.`
      : message;
  }

  for (const field of ['prefix', 'base', 'suffix']) {
    controls[field].addEventListener('change', () => {
      selection[`${field}Index`] = Number(controls[field].value);
      const cleared = keepValidSelection(field);
      refreshOptions();
      render(cleared);
    });
  }
  controls.price.addEventListener('change', () => render());
  function changeContext() {
    const choices = getAvailableOptions({ ...selection,
      gameVersion: controls.gameVersion.value, gameMode: controls.gameMode.value });
    if (!choices.bases.includes(selection.baseIndex)) selection.baseIndex = 0;
    if (!choices.prefixes.includes(selection.prefixIndex)) selection.prefixIndex = 0;
    if (!choices.suffixes.includes(selection.suffixIndex)) selection.suffixIndex = 0;
    refreshOptions();
    render();
  }
  controls.gameVersion.addEventListener('change', changeContext);
  controls.gameMode.addEventListener('change', changeContext);
  document.getElementById('premium-reset').addEventListener('click', () => {
    Object.assign(selection, { baseIndex: 0, prefixIndex: 0, suffixIndex: 0 });
    controls.price.selectedIndex = 0;
    refreshOptions();
    render();
    controls.base.focus();
  });
  form.addEventListener('submit', (event) => event.preventDefault());

  refreshOptions();
  render();
}
