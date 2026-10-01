(() => {
  'use strict';

  const START_MIN = 2;
  const START_MAX = 254;
  const TARGET_MIN = 1;

  const loss = (maxDurability, level) => Math.max(Math.floor(maxDurability / (level + 9)), 1);

  const compressLevels = (levels) => {
    const sortedLevels = [...levels].sort((a, b) => a - b);

    if (sortedLevels.length === 0) return '';
    const ranges = [];
    let start = sortedLevels[0];
    let end = sortedLevels[0];

    for (let index = 1; index < sortedLevels.length; index += 1) {
      const level = sortedLevels[index];

      if (level === end + 1) {
        end = level;
      } else {
        ranges.push(start === end ? String(start) : `${start}-${end}`);
        start = level;
        end = level;
      }
    }

    ranges.push(start === end ? String(start) : `${start}-${end}`);
    return ranges.join(', ');
  };

  const createElement = (tagName, options = {}) => {
    const element = document.createElement(tagName);

    if (options.className) element.className = options.className;
    if (options.textContent !== undefined) element.textContent = options.textContent;

    return element;
  };

  const appendRow = (tableSection, cells, cellTag = 'td') => {
    const row = document.createElement('tr');

    cells.forEach((cellText) => {
      const cell = document.createElement(cellTag);
      cell.textContent = cellText;
      row.append(cell);
    });

    tableSection.append(row);
  };

  const createResultsTable = (headings) => {
    const table = createElement('table', { className: 'repair-results-table' });
    const thead = document.createElement('thead');
    const tbody = document.createElement('tbody');

    appendRow(thead, headings, 'th');
    table.append(thead, tbody);

    return { table, tbody };
  };

  const buildSingleStepSection = (target, levels) => {
    const section = createElement('section', { className: 'repair-result-section' });
    section.append(createElement('h3', { className: 'result-heading', textContent: 'Single Repair Solution' }));

    const tableWrapper = createElement('div', { className: 'repair-table-scroll' });
    const { table, tbody } = createResultsTable(['Warrior Level Range(s)', 'Result']);

    appendRow(tbody, [compressLevels(levels), String(target)]);
    tableWrapper.append(table);
    section.append(tableWrapper);

    return section;
  };

  const buildTwoStepSection = (target, groups) => {
    const section = createElement('section', { className: 'repair-result-section' });
    section.append(createElement('h3', { className: 'result-heading', textContent: 'Two Repair Solutions' }));

    const tableWrapper = createElement('div', { className: 'repair-table-scroll' });
    const { table, tbody } = createResultsTable([
      'First Repair',
      'Intermediate Max Durability',
      'Second Repair',
      'Final'
    ]);

    Object.keys(groups)
      .map(Number)
      .sort((a, b) => b - a)
      .forEach((midpoint) => {
        const group = groups[midpoint];
        appendRow(tbody, [
          `clvl ${compressLevels(new Set(group.first))}`,
          String(midpoint),
          `clvl ${compressLevels(new Set(group.second))}`,
          String(target)
        ]);
      });

    tableWrapper.append(table);
    section.append(tableWrapper);

    return section;
  };

  const findRepairPaths = (start, target) => {
    const singleStepLevels = [];
    const twoStepGroups = {};

    for (let level = 1; level <= 50; level += 1) {
      const result = start - loss(start, level);

      if (result === target) {
        singleStepLevels.push(level);
      }
    }

    for (let firstLevel = 1; firstLevel <= 50; firstLevel += 1) {
      const midpoint = start - loss(start, firstLevel);

      for (let secondLevel = 1; secondLevel <= 50; secondLevel += 1) {
        const finalDurability = midpoint - loss(midpoint, secondLevel);

        if (finalDurability !== target) continue;

        if (!twoStepGroups[midpoint]) {
          twoStepGroups[midpoint] = { first: [], second: [] };
        }

        twoStepGroups[midpoint].first.push(firstLevel);
        twoStepGroups[midpoint].second.push(secondLevel);
      }
    }

    return { singleStepLevels, twoStepGroups };
  };

  const getIntegerInputValue = (input) => {
    const rawValue = input.value.trim();

    if (rawValue === '') return null;

    const value = Number(rawValue);
    return Number.isInteger(value) ? value : Number.NaN;
  };

  const showMessage = (results, message) => {
    results.replaceChildren(createElement('p', {
      className: 'warning-text',
      textContent: message
    }));
  };

  const validateInputs = (start, target) => {
    if (start === null || target === null) {
      return 'Enter starting and target maximum durability values to see guaranteed repair paths.';
    }

    if (Number.isNaN(start) || start < START_MIN || start > START_MAX) {
      return `Starting maximum durability must be between ${START_MIN} and ${START_MAX}.`;
    }

    if (Number.isNaN(target) || target < TARGET_MIN) {
      return 'Target maximum durability must be at least 1.';
    }

    if (target >= start) {
      return 'Target maximum durability must be lower than the starting maximum durability.';
    }

    return '';
  };

  const updateFieldValidity = (startInput, targetInput, start, target) => {
    startInput.removeAttribute('aria-invalid');
    targetInput.removeAttribute('aria-invalid');

    if (start !== null && (Number.isNaN(start) || start < START_MIN || start > START_MAX)) {
      startInput.setAttribute('aria-invalid', 'true');
    }

    if (
      target !== null
      && (Number.isNaN(target) || target < TARGET_MIN || (Number.isInteger(start) && target >= start))
    ) {
      targetInput.setAttribute('aria-invalid', 'true');
    }
  };

  const updateTargetRange = (startInput, targetInput) => {
    const start = getIntegerInputValue(startInput);
    const maxTarget = Number.isInteger(start) && start >= START_MIN && start <= START_MAX
      ? start - 1
      : START_MAX - 1;

    targetInput.max = String(maxTarget);
  };

  const updateRepairPaths = (form) => {
    const startInput = form.querySelector('#repair-start');
    const targetInput = form.querySelector('#repair-target');
    const lossSummary = form.querySelector('#warrior-repair-loss');
    const results = form.querySelector('#warrior-repair-results');

    if (!startInput || !targetInput || !lossSummary || !results) return;

    updateTargetRange(startInput, targetInput);

    const start = getIntegerInputValue(startInput);
    const target = getIntegerInputValue(targetInput);
    const validationMessage = validateInputs(start, target);

    updateFieldValidity(startInput, targetInput, start, target);

    if (validationMessage !== '') {
      lossSummary.textContent = '';
      showMessage(results, validationMessage);
      return;
    }

    lossSummary.textContent = `Desired maximum durability loss: ${start - target}`;

    const { singleStepLevels, twoStepGroups } = findRepairPaths(start, target);
    const hasSingleStepSolutions = singleStepLevels.length > 0;
    const hasTwoStepSolutions = Object.keys(twoStepGroups).length > 0;

    results.replaceChildren();

    if (hasSingleStepSolutions) {
      results.append(buildSingleStepSection(target, singleStepLevels));
    }

    if (hasTwoStepSolutions) {
      results.append(buildTwoStepSection(target, twoStepGroups));
    }

    if (!hasSingleStepSolutions && !hasTwoStepSolutions) {
      showMessage(results, 'No guaranteed one-step or two-step solutions found.');
    }
  };

  const initWarriorRepairCalculator = () => {
    const form = document.getElementById('warrior-repair-calculator');

    if (!form) return;

    const startInput = form.querySelector('#repair-start');
    const targetInput = form.querySelector('#repair-target');

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      updateRepairPaths(form);
    });

    [startInput, targetInput].forEach((input) => {
      input?.addEventListener('input', () => updateRepairPaths(form));
      input?.addEventListener('change', () => updateRepairPaths(form));
    });

    updateRepairPaths(form);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWarriorRepairCalculator, { once: true });
  } else {
    initWarriorRepairCalculator();
  }
})();
