(() => {
  'use strict';

  const specialIlvl = (clvl) => {
    if (clvl < 10) return 6;
    if (clvl < 12) return 7;
    if (clvl < 14) return 8;
    if (clvl < 16) return 9;
    if (clvl < 18) return 10;
    if (clvl < 20) return 11;
    if (clvl < 22) return 12;
    if (clvl < 24) return 13;
    if (clvl < 26) return 14;
    if (clvl < 28) return 15;
    return 16;
  };

  const grisMinMaxIlvl = (qlvl) => Math.max(1, Math.min(qlvl, 30));

  const calcGriswoldsBaseQlvl = (ilvl) => {
    const cappedIlvl = grisMinMaxIlvl(ilvl);
    return `${grisMinMaxIlvl(Math.trunc(cappedIlvl / 4))}-${Math.min(25, cappedIlvl)}`;
  };

  const calcGriswoldsAffixQlvl = (ilvl) => {
    const cappedIlvl = grisMinMaxIlvl(ilvl);
    return `${grisMinMaxIlvl(Math.trunc(cappedIlvl / 2))}-${cappedIlvl}`;
  };

  const getHellfireGriswoldPremiumIlvl = (clvl, slot) => {
    // Hellfire expanded Griswold's premium items from 6 slots to 15 slots.
    // ilvl1 follows special slot tables for clvl 1 through 5; clvl 6+
    // uses the final general column from the Hellfire table.
    // Shop generation effectively caps Griswold premium-item results at clvl 30.
    const cappedClvl = Math.min(clvl, 30);
    const offsetsByClvl = {
      1: [-1, -1, -1, 0, 0, 0, 0, +1, +1, +1, +1, +2, +2, +3, +3],
      2: [-1, -1, -1, -1, 0, 0, 0, 0, +1, +1, +1, +2, +2, +2, +3],
      3: [-2, -1, -1, -1, -1, 0, 0, 0, +1, +1, +1, +1, +2, +2, +3],
      4: [-2, -2, -1, -1, -1, 0, 0, 0, 0, +1, +1, +1, +2, +2, +3],
      5: [-2, -2, -1, -1, -1, -1, 0, 0, 0, +1, +1, +1, +2, +2, +3]
    };

    const generalOffsets = [-2, -2, -2, -1, -1, -1, 0, 0, 0, +1, +1, +1, +2, +2, +3];
    const offsets = offsetsByClvl[cappedClvl] || generalOffsets;

    return cappedClvl + offsets[slot - 1];
  };

  const calcGriswoldsMinMaxQlvls = (clvl) => {
    const lines = [
      'Slot:   Base:   Affixes:',
      ''
    ];

    for (let slot = 1; slot <= 15; slot += 1) {
      const ilvl = getHellfireGriswoldPremiumIlvl(clvl, slot);
      lines.push(
        `${slot.toString().padEnd(2, ' ')}:     ${calcGriswoldsBaseQlvl(ilvl).padEnd(7, ' ')} ${calcGriswoldsAffixQlvl(ilvl)}`
      );
    }

    return lines.join('\n');
  };

  const calcWirtQlvls = (clvl) => `Base items:  1-${Math.min(clvl, 25)}\nAffixes:     ${Math.min(clvl, 25)}-${Math.min(2 * clvl, 60)}`;

  const calcAdriaQlvls = (clvl) => {
    const ilvl = specialIlvl(clvl);
    return `Base items and spells (of staves or books):  1-${ilvl}\nPrefixes on staves with spell:    1-${2 * ilvl}`;
  };

  const setResultText = (element, text) => {
    element.textContent = text;
  };

  const runQlvlCalculation = () => {
    const clvlInput = document.getElementById('clvl');
    const grisResult = document.getElementById('grisresult');
    const wirtResult = document.getElementById('wirtresult');
    const adriaResult = document.getElementById('adriaresult');

    if (!clvlInput || !grisResult || !wirtResult || !adriaResult) return;

    const clvl = Number.parseInt(clvlInput.value, 10);

    if (Number.isNaN(clvl)) return;

    const boundedClvl = Math.min(Math.max(clvl, 1), 50);
    clvlInput.value = String(boundedClvl);

    setResultText(grisResult, calcGriswoldsMinMaxQlvls(boundedClvl));
    setResultText(wirtResult, calcWirtQlvls(boundedClvl));
    setResultText(adriaResult, calcAdriaQlvls(boundedClvl));
  };

  const initQlvlCalculator = () => {
    const form = document.getElementById('calc');
    const clvlInput = document.getElementById('clvl');

    if (!form) return;

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      runQlvlCalculation();
    });

    clvlInput?.addEventListener('input', runQlvlCalculation);
    clvlInput?.addEventListener('change', runQlvlCalculation);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initQlvlCalculator, { once: true });
  } else {
    initQlvlCalculator();
  }
})();
