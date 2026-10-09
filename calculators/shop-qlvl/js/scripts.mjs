import { getDiabloPremiumItemLevels, getHellfirePremiumItemLevels } from '../../premium-item-checker/js/rules.mjs';
import { townItemLevel } from '../../town-level.mjs';

function calculateShopQlvls(characterLevel, { gameVersion = 'hellfire', gameMode = 'multiplayer', dungeonLevel = 1 } = {}) {
  if (!['diablo', 'hellfire'].includes(gameVersion) ||
      !['single-player', 'multiplayer'].includes(gameMode)) throw new RangeError('Unsupported game context');
  const level = Math.min(50, Math.max(1, Math.trunc(characterLevel)));
  const slots = gameVersion === 'hellfire'
    ? getHellfirePremiumItemLevels(level) : getDiabloPremiumItemLevels(level);
  const griswold = ['Slot:   Base:   Affixes:', '', ...slots.map((itemLevel, index) =>
    `${String(index + 1).padEnd(2, ' ')}:     ${`${Math.max(1, Math.floor(itemLevel / 4))}-${Math.min(25, itemLevel)}`.padEnd(7, ' ')} ${Math.max(1, Math.floor(itemLevel / 2))}-${itemLevel}`)].join('\n');
  // Jarulf 3.13.2: Magi has the highest Hellfire staff-spell qlvl, 20.
  const wirt = `Base items:  1-${Math.min(level, 25)}\nAffixes:     ${Math.min(level, 25)}-${Math.min(level * 2, 60)}` +
    (gameVersion === 'hellfire'
      ? `\nPrefixes on staves with spell:  1-${Math.min(level * 2, 60)}\nSpells on staves:  1-${Math.min(level, 20)}`
      : '');
  const townLevel = townItemLevel({ gameMode, characterLevel: level, dungeonLevel });
  const adria = gameVersion === 'hellfire'
    ? `Base items and spells (of staves or books):  1-${townLevel}\nPrefixes on staves with spell:    1-${townLevel * 2}`
    : `Base items and spells (of staves or books):  1-${townLevel}\nPrefixes on staves with spell:    1-${townLevel * 2}\nAffixes on staves without spell:  ${townLevel}-${townLevel * 2}`;
  return { griswold, wirt, adria, townLevel, slotLevels: slots };
}

const form = typeof document === 'undefined' ? null : document.getElementById('calc');
if (form) {
  const levelInput = form.elements.clvl;
  const depthInput = form.elements.dungeonLevel;
  const depthField = document.getElementById('shop-depth-field');
  const render = () => {
    const gameMode = form.elements.gameMode.value;
    depthField.hidden = gameMode !== 'single-player';
    document.getElementById('shop-mode-label').textContent = gameMode === 'single-player' ? '(SP)' : '(MP)';
    document.getElementById('shop-vendor-note').textContent = form.elements.gameVersion.value === 'hellfire'
      ? 'These are qlvl ranges, not guaranteed stock; Hellfire class and carried-item restrictions can affect offers.'
      : 'These are Diablo qlvl ranges, not guaranteed stock; normal vendor price limits still apply.';
    if (levelInput.value === '') {
      for (const id of ['grisresult', 'wirtresult', 'adriaresult']) {
        document.getElementById(id).textContent = 'Enter a character level to see ranges.';
      }
      return;
    }
    const characterLevel = Math.min(50, Math.max(1, Number.parseInt(levelInput.value, 10) || 1));
    const dungeonLevel = Math.min(16, Math.max(1, Number.parseInt(depthInput.value, 10) || 1));
    levelInput.value = String(characterLevel);
    depthInput.value = String(dungeonLevel);
    const result = calculateShopQlvls(characterLevel, {
      gameVersion: form.elements.gameVersion.value, gameMode, dungeonLevel
    });
    document.getElementById('grisresult').textContent = result.griswold;
    document.getElementById('wirtresult').textContent = result.wirt;
    document.getElementById('adriaresult').textContent = result.adria;
  };
  form.addEventListener('submit', (event) => { event.preventDefault(); render(); });
  for (const control of [levelInput, depthInput, form.elements.gameVersion, form.elements.gameMode]) {
    control.addEventListener('input', render);
    control.addEventListener('change', render);
  }
  render();
}

export { townItemLevel, calculateShopQlvls };
