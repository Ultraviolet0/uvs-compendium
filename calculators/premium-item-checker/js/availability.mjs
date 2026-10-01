import { baseQlvlInRange, getGriswoldMagicCharacterLevels, formatCharacterLevels } from './rules.mjs';
import { premiumIndex } from './data.mjs';
import { townItemLevel } from '../../town-level.mjs';

// Max's Hellfire shopping differences and DevilutionX items.h agree on these
// underlying item-value limits. The final Hellfire retry may bypass them.
const hellfireVendorPriceLimit = 200000;
const diabloGriswoldPriceLimit = 140000;
const diabloWirtPriceLimit = 90000;

function getWirtCharacterLevels(baseQlvls, prefixQlvl, suffixQlvl) {
  const levels = [];
  for (let level = 1; level <= 50; level++) {
    const minimumAffixQlvl = Math.min(level, 25);
    const maximumAffixQlvl = Math.min(level * 2, 60);
    const validAffix = (qlvl) => qlvl === 0 ||
      (qlvl >= minimumAffixQlvl && qlvl <= maximumAffixQlvl);
    if (baseQlvlInRange(baseQlvls, 1, Math.min(level, 25)) && validAffix(prefixQlvl) &&
        validAffix(suffixQlvl)) levels.push(level);
  }
  return levels;
}

function getAdriaLevels(baseQlvl, prefixQlvl, suffixQlvl, charged, gameVersion, gameMode) {
  const levels = [];
  const highest = gameMode === 'single-player' ? 16 : 50;
  for (let level = 1; level <= highest; level++) {
    const itemLevel = townItemLevel({
      gameMode,
      characterLevel: level,
      dungeonLevel: level
    });
    if (baseQlvl > itemLevel) continue;
    if (charged) {
      if (suffixQlvl <= itemLevel && prefixQlvl <= itemLevel * 2) levels.push(level);
    } else if (gameVersion === 'diablo' &&
        (prefixQlvl === 0 || (prefixQlvl >= itemLevel && prefixQlvl <= itemLevel * 2)) &&
        (suffixQlvl === 0 || (suffixQlvl >= itemLevel && suffixQlvl <= itemLevel * 2))) {
      levels.push(level);
    }
  }
  return levels;
}

function calculateAvailability({ SelBasee, SelPref, SelSuff, baseQlvls, prelvl, suflvl, slvlmin, slvlmax, pricemin, premulti, sufmulti, gameVersion = 'hellfire', gameMode = 'multiplayer' }) {
  var clvl_min, clvl_max, clvl_dsp;
  var minusitem = false;
  var grisdsp = "", wirtdsp = "", adradsp = "";
  const availability = [];
  const hellfire = gameVersion === 'hellfire';
  const singlePlayer = gameMode === 'single-player';
  const minimumBaseQlvl = Math.min(...baseQlvls);
  const staff = SelBasee >= premiumIndex.firstStaffBase && SelBasee <= premiumIndex.lastStaffBase;
  const jewelry = SelBasee === 68 || SelBasee === 69;
  const premiumVendorBase = SelBasee > 0 && SelBasee <= premiumIndex.lastNormalBase &&
    (!staff || hellfire) && (!jewelry || singlePlayer);
  const griswoldPriceLimit = hellfire ? hellfireVendorPriceLimit : diabloGriswoldPriceLimit;
  const wirtPriceLimit = hellfire ? hellfireVendorPriceLimit : diabloWirtPriceLimit;
  const chargedStaff = SelSuff >= premiumIndex.firstChargedSpellSuffix &&
    SelSuff <= premiumIndex.lastChargedSpellSuffix;
  //-- Minus Item Check ------
  if ((premulti < 0) || (sufmulti < 0) || (SelSuff == 94) || (SelPref == 57)) {
    minusitem = true;
  }


  //-- Griswold ----------
  if (premiumVendorBase && !chargedStaff && (!jewelry || SelPref + SelSuff > 0) &&
      (slvlmin <= 30) && (pricemin <= griswoldPriceLimit) && (minusitem == false)) {
    if (SelPref + SelSuff == 0) {
      clvl_min = minimumBaseQlvl;
      if (minimumBaseQlvl > 16) {
        clvl_max = -1;
      } else if (singlePlayer) {
        const dungeonLevels = Array.from({ length: 16 }, (_, index) => index + 1)
          .filter((level) => baseQlvlInRange(baseQlvls, 1, townItemLevel({
            gameMode, characterLevel: 1, dungeonLevel: level
          })));
        clvl_min = dungeonLevels[0];
        clvl_max = dungeonLevels[dungeonLevels.length - 1];
      } else {
        if (clvl_min <= 6) {
          clvl_min = 1;
        } else {
          clvl_min = (clvl_min - 2) * 2;
        }
        clvl_max = 50;
      }
    } else {
      var griswoldLevels = getGriswoldMagicCharacterLevels(baseQlvls, slvlmin, slvlmax, gameVersion);
      clvl_min = griswoldLevels[0];
      clvl_max = griswoldLevels[griswoldLevels.length - 1];
    }

    if (clvl_min <= clvl_max) {
      if (SelPref + SelSuff > 0) {
        clvl_dsp = formatCharacterLevels(griswoldLevels);
      } else if (clvl_min < clvl_max) {
        clvl_dsp = clvl_min + " - " + clvl_max;
      } else {
        clvl_dsp = clvl_max;
      }
      const levelType = SelPref + SelSuff === 0 && singlePlayer
        ? 'Deepest dungeon level' : 'Character level';
      grisdsp = "\n    Griswold     " + (singlePlayer && SelPref + SelSuff === 0 ? 'Dungeon Level: ' : 'Char Level: ') + clvl_dsp;
      availability.push({ source: SelPref + SelSuff === 0 ? 'Griswold (basic items)' : 'Griswold',
        levelType, range: String(clvl_dsp) });
    }
  }

  //-- Wirt --------------
  if (premiumVendorBase && (!staff || chargedStaff) &&
      (SelPref + SelSuff > 0) && (minusitem == false) &&
      (hellfire || pricemin <= wirtPriceLimit)) {
    const wirtLevels = getWirtCharacterLevels(baseQlvls, prelvl, suflvl);
    if (wirtLevels.length) {
      clvl_dsp = formatCharacterLevels(wirtLevels);
      const source = pricemin <= wirtPriceLimit ? 'Wirt' : 'Wirt (rare retry fallback)';
      wirtdsp = source === 'Wirt'
        ? "\n    Wirt         Char Level: " + clvl_dsp
        : "\n    Wirt (rare retry fallback)    Char Level: " + clvl_dsp;
      availability.push({ source, levelType: 'Character level', range: String(clvl_dsp) });
    }
  }

  //-- Adria -------------
  if (staff && SelPref + SelSuff > 0 && (chargedStaff || !hellfire) &&
      pricemin <= griswoldPriceLimit && !minusitem) {
    const adriaLevels = getAdriaLevels(minimumBaseQlvl, prelvl, suflvl,
      chargedStaff, gameVersion, gameMode);
    if (adriaLevels.length) {
      clvl_dsp = formatCharacterLevels(adriaLevels);
      const levelType = singlePlayer ? 'Deepest dungeon level' : 'Character level';
      adradsp = '\n    Adria        ' + (singlePlayer ? 'Dungeon Level: ' : 'Char Level: ') + clvl_dsp;
      availability.push({ source: 'Adria', levelType, range: clvl_dsp });
    }
  }

  // Dungeon tiers share the same level intersection; only the base minimum differs.
  let dungeonDisplay = '';
  if (SelBasee > 0 && slvlmin <= 34) {
    const plainBase = SelPref + SelSuff === 0 && SelBasee <= premiumIndex.lastNormalBase;
    const dungeonSources = [
      ['Normal', minimumBaseQlvl, 'Normal     '],
      ['Nightmare', minimumBaseQlvl < 16 ? 1 : minimumBaseQlvl - 15, 'Nightmare  '],
      ['Hell', 1, 'Hell       ']
    ].filter(([source]) => !(gameVersion === 'diablo' && singlePlayer && source !== 'Normal'));
    for (const [source, minimumBaseLevel, paddedName] of dungeonSources) {
      const minimum = plainBase ? minimumBaseLevel : Math.max(slvlmin, minimumBaseLevel);
      const maximum = plainBase ? 34 : Math.min(slvlmax, 34);
      if (minimum > maximum) continue;
      const range = minimum === maximum ? String(minimum) : `${minimum} - ${maximum}`;
      dungeonDisplay += `\n    ${paddedName}Source Level: ${range}`;
      availability.push({ source, levelType: 'Item level (ilvl)', range });
    }
  }

  return {
    display: grisdsp + wirtdsp + adradsp + dungeonDisplay,
    entries: availability
  };
}

export { calculateAvailability, getWirtCharacterLevels, getAdriaLevels };
