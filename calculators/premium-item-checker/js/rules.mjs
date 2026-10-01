import { prefixx, suffixx, basee, premiumIndex } from './data.mjs';

// The levels of the 15 Hellfire premium items after a character-level refresh.
// Jarulf 1.62, section 3.9, gives the slot levels; the earlier slots retain
// items from previous levels. DevilutionX Source/items.cpp (SpawnPremium)
// confirms that only slots 11, 13, and 15 are regenerated on level-up.
const maxPremiumItemLevel = 30;
const maxCharacterLevel = 50;
const earlyOffsets = {
  1: [-1, -1, -1, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 3, 3],
  2: [-1, -1, -1, -1, 0, 0, 0, 0, 1, 1, 1, 2, 2, 2, 3],
  3: [-2, -1, -1, -1, -1, 0, 0, 0, 1, 1, 1, 1, 2, 2, 3],
  4: [-2, -2, -1, -1, -1, 0, 0, 0, 0, 1, 1, 1, 2, 2, 3],
  5: [-2, -2, -1, -1, -1, -1, 0, 0, 0, 1, 1, 1, 2, 2, 3]
};
const standardOffsets = [-2, -2, -2, -1, -1, -1, 0, 0, 0, 1, 1, 1, 2, 2, 3];

function getHellfirePremiumItemLevels(characterLevel) {
  const level = characterLevel;
  return (earlyOffsets[level] || standardOffsets).map((offset) =>
    Math.min(Math.max(level + offset, 1), maxPremiumItemLevel));
}

// Jarulf 1.62, 3.9: six Diablo premium slots after the level-up shift.
function getDiabloPremiumItemLevels(characterLevel) {
  return [-1, -1, 0, 0, 1, 2].map((offset) =>
    Math.min(Math.max(characterLevel + offset, 1), maxPremiumItemLevel));
}

function getGriswoldMagicCharacterLevels(baseQlvl, sourceLevelMin, sourceLevelMax, gameVersion = 'hellfire') {
  const characterLevels = [];
  for (let characterLevel = 1; characterLevel <= maxCharacterLevel; characterLevel++) {
    const itemLevels = gameVersion === 'diablo'
      ? getDiabloPremiumItemLevels(characterLevel)
      : getHellfirePremiumItemLevels(characterLevel);
    if (itemLevels.some((itemLevel) =>
      itemLevel >= sourceLevelMin && itemLevel <= sourceLevelMax &&
        baseQlvl >= Math.floor(itemLevel / 4) && baseQlvl <= itemLevel)) {
      characterLevels.push(characterLevel);
    }
  }
  return characterLevels;
}

const getHellfireGriswoldMagicCharacterLevels = (baseQlvl, sourceLevelMin, sourceLevelMax) =>
  getGriswoldMagicCharacterLevels(baseQlvl, sourceLevelMin, sourceLevelMax, 'hellfire');

function formatCharacterLevels(levels) {
  if (levels.length === 0) return '';
  const ranges = [];
  let start = levels[0];
  let end = start;
  for (const level of levels.slice(1)) {
    if (level === end + 1) {
      end = level;
      continue;
    }
    ranges.push(start === end ? String(start) : `${start} - ${end}`);
    start = end = level;
  }
  ranges.push(start === end ? String(start) : `${start} - ${end}`);
  return ranges.join(', ');
}



const excludedPrefixGroupA = new Set([22, 23, 49]);
const excludedSuffixGroupA = new Set([24, 26, 27, 31, 32, 33, 34, 75]);
const excludedPrefixGroupB = new Set([36, 37, 38, 39, 72, 73, 74, 80, 81]);
const excludedSuffixGroupB = new Set([29, 30, 36, 58, 72, 93, 94]);

function isExcludedAffixPair(prefixIndex, suffixIndex) {
  return (excludedPrefixGroupA.has(prefixIndex) && excludedSuffixGroupA.has(suffixIndex)) ||
    (excludedPrefixGroupB.has(prefixIndex) && excludedSuffixGroupB.has(suffixIndex));
}

function affixLevelsOverlap(prefixLevel, suffixLevel) {
  return (prefixLevel * 2 + 1 >= suffixLevel && suffixLevel * 2 + 1 >= prefixLevel) ||
    (prefixLevel >= 25 && suffixLevel >= 25);
}

function isBaseAvailable(baseIndex, prefixIndex, suffixIndex) {
  if (baseIndex === 0) return true;
  const kind = basee[baseIndex].kind.parm;
  return (prefixIndex === 0 || ((prefixx[prefixIndex].equip.parm >> kind) & 1) !== 0) &&
    (suffixIndex === 0 || ((suffixx[suffixIndex].equip.parm >> kind) & 1) !== 0);
}

function isPrefixAvailable(baseIndex, prefixIndex, suffixIndex) {
  if (prefixIndex === 0) return true;
  const prefix = prefixx[prefixIndex];
  const suffix = suffixx[suffixIndex];
  const kind = basee[baseIndex].kind.parm;
  return (suffixIndex === 0 || (suffix.equip.parm & prefix.equip.parm) !== 0) &&
    (baseIndex === 0 || ((prefix.equip.parm >> kind) & 1) !== 0) &&
    (suffixIndex === 0 || affixLevelsOverlap(prefix.level, suffix.level) ||
      (suffixIndex >= premiumIndex.firstChargedSpellSuffix &&
        suffixIndex <= premiumIndex.lastChargedSpellSuffix)) &&
    !isExcludedAffixPair(prefixIndex, suffixIndex);
}

function isSuffixAvailable(baseIndex, prefixIndex, suffixIndex) {
  if (suffixIndex === 0) return true;
  const prefix = prefixx[prefixIndex];
  const suffix = suffixx[suffixIndex];
  const kind = basee[baseIndex].kind.parm;
  return (prefixIndex === 0 || (prefix.equip.parm & suffix.equip.parm) !== 0) &&
    (baseIndex === 0 || ((suffix.equip.parm >> kind) & 1) !== 0) &&
    (prefixIndex === 0 || affixLevelsOverlap(prefix.level, suffix.level) ||
      (kind % 3 === 0 && suffixIndex >= premiumIndex.firstChargedSpellSuffix &&
        suffixIndex <= premiumIndex.lastChargedSpellSuffix)) &&
    !isExcludedAffixPair(prefixIndex, suffixIndex);
}

function availableIndices(items, predicate) {
  return [0, ...items.flatMap((item, index) => index > 0 && item && predicate(index) ? [index] : [])];
}

function isAllowedForGame(index, kind, gameVersion) {
  if (gameVersion !== 'diablo') return true;
  if (kind === 'base') return index <= 146;
  if (kind === 'prefix') return index <= 83;
  return index <= 121;
}

function getAvailableOptions({ baseIndex, prefixIndex, suffixIndex, gameVersion = 'hellfire', gameMode = 'multiplayer' }) {
  return {
    bases: availableIndices(basee, (index) => isAllowedForGame(index, 'base', gameVersion) && isBaseAvailable(index, prefixIndex, suffixIndex)),
    prefixes: availableIndices(prefixx, (index) => isAllowedForGame(index, 'prefix', gameVersion) && isPrefixAvailable(baseIndex, index, suffixIndex)),
    suffixes: availableIndices(suffixx, (index) => isAllowedForGame(index, 'suffix', gameVersion) &&
      !(gameMode === 'single-player' && (index === 100 || index === 102)) &&
      isSuffixAvailable(baseIndex, prefixIndex, index))
  };
}

export {
  getHellfirePremiumItemLevels,
  getDiabloPremiumItemLevels,
  getGriswoldMagicCharacterLevels,
  getHellfireGriswoldMagicCharacterLevels,
  formatCharacterLevels,
  isBaseAvailable,
  isPrefixAvailable,
  isSuffixAvailable,
  getAvailableOptions
};
