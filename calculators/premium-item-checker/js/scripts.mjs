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
    price: form.elements.calcprice
  };
  const output = {
    summary: document.getElementById('display1'),
    availability: document.getElementById('premium-availability'),
    details: document.getElementById('display2'),
    price: document.getElementById('display3'),
    status: document.getElementById('premium-status')
  };
  const selection = { baseIndex: 0, prefixIndex: 0, suffixIndex: 0 };

  function replaceOptions(select, indices, items, selectedIndex) {
    const options = indices.map((index) => new Option(items[index].name, String(index)));
    select.replaceChildren(...options);
    select.value = String(selectedIndex);
  }

  function refreshOptions() {
    const choices = getAvailableOptions(selection);
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
    const otherFields = {
      base: ['prefix', 'suffix'],
      prefix: ['base', 'suffix'],
      suffix: ['base', 'prefix']
    }[changedField];
    for (const field of otherFields) {
      if (!isSelectedValid(field)) selection[`${field}Index`] = 0;
    }
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
      empty.textContent = 'No listed source qualifies for this combination under the current checker rules.';
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

  function render() {
    const { baseIndex, prefixIndex, suffixIndex } = selection;
    const result = calculatePremiumItem(baseIndex, prefixIndex, suffixIndex,
      controls.price.selectedIndex === 1);
    output.summary.textContent = result.itemSummary;
    output.details.textContent = result.display2;
    output.price.textContent = result.display3;
    renderAvailability(result.availability, baseIndex !== 0);
    output.status.textContent = baseIndex === 0
      ? 'Choose a base item to see availability.'
      : `Result updated for ${basee[baseIndex].name}.`;
  }

  for (const field of ['prefix', 'base', 'suffix']) {
    controls[field].addEventListener('change', () => {
      selection[`${field}Index`] = Number(controls[field].value);
      keepValidSelection(field);
      refreshOptions();
      render();
    });
  }
  controls.price.addEventListener('change', render);
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
