import { getHellfireGriswoldMagicCharacterLevels, formatCharacterLevels } from './rules.mjs';

// Preserve the checker’s existing vendor limits while keeping them independent
// of DOM rendering and the item-price calculation.
// These inherited thresholds need a separate Hellfire review; see docs/corrections.md.
const legacyGriswoldPriceLimit = 140000;
const legacyWirtPriceLimit = 90000;
const legacyAdriaPriceLimit = 140000;

function calculateAvailability({ SelBasee, SelPref, SelSuff, baslvl, suflvl, slvlmin, slvlmax, pricemin, premulti, sufmulti }) {
  var clvl_min, clvl_max, clvl_dsp;
  var minusitem = false;
  var grisdsp = "", wirtdsp = "", adradsp = "";
  const availability = [];
  //-- Minus Item Check ------
  if ((premulti < 0) || (sufmulti < 0) || (SelSuff == 94) || (SelPref == 57)) {
    minusitem = true;
  }


  //-- Griswold ----------
  if ((SelBasee < 63) && (SelBasee > 0) && (slvlmin <= 30) && (pricemin <= legacyGriswoldPriceLimit) && (minusitem == false)) {
    if (SelPref + SelSuff == 0) {
      clvl_min = baslvl;
      if (baslvl > 16) {
        clvl_max = -1;
      } else {
        if (clvl_min <= 6) {
          clvl_min = 1;
        } else {
          clvl_min = (clvl_min - 2) * 2;
        }
        clvl_max = 50;
      }
    } else {
      var griswoldLevels = getHellfireGriswoldMagicCharacterLevels(baslvl, slvlmin, slvlmax);
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
      grisdsp = "\n    Griswold     Char Level: " + clvl_dsp;
      availability.push({ source: 'Griswold', levelType: 'Character level', range: String(clvl_dsp) });
    }
  }

  //-- Wirt --------------
  if ((SelBasee < 63) && (SelBasee > 0) && (SelPref + SelSuff > 0) && (pricemin <= legacyWirtPriceLimit) && (minusitem == false)) {
    clvl_min = Math.ceil(slvlmin * 0.5);
    clvl_max = Math.floor(slvlmax * 0.5);

    if (clvl_min < baslvl) {
      clvl_min = baslvl;
    }

    if (clvl_max >= 30) {
      clvl_max = 50;
    }

    if (clvl_min <= clvl_max) {
      if (clvl_min < clvl_max) {
        clvl_dsp = clvl_min + " - " + clvl_max;
      } else {
        clvl_dsp = clvl_max;
      }
      wirtdsp = "\n    Wirt         Char Level: " + clvl_dsp;
      availability.push({ source: 'Wirt', levelType: 'Character level', range: String(clvl_dsp) });
    }
  }

  //-- Adria -------------
  if ((SelBasee > 62) && (SelBasee < 68) && (SelPref + SelSuff > 0) && (slvlmin <= 32) && (pricemin <= legacyAdriaPriceLimit) && (minusitem == false)) {
    if (slvlmin > 4) {
      clvl_min = slvlmin - 4;
    } else {
      clvl_min = 1;
    }

    if (slvlmax < 12) {
      clvl_max = 0;
    } else {
      clvl_max = slvlmax - 4;
    }

    if (((SelSuff > 95) && (SelSuff <= 121)) && (suflvl > baslvl)) {
      baslvl = suflvl;
    }

    if (clvl_min + 4 < baslvl * 2) {
      clvl_min = baslvl * 2 - 4;
    }

    if (clvl_min < 9) {
      clvl_min = 1;
    }

    if (clvl_max > 28) {
      clvl_max = 50;
    }

    if (clvl_min <= clvl_max) {
      if (clvl_min < clvl_max) {
        clvl_dsp = clvl_min + " - " + clvl_max;
      } else {
        clvl_dsp = clvl_max;
      }
      adradsp = "\n    Adria        Char Level: " + clvl_dsp;
      availability.push({ source: 'Adria', levelType: 'Character level', range: String(clvl_dsp) });
    }
  }

  // Dungeon tiers share the same level intersection; only the base minimum differs.
  let dungeonDisplay = '';
  if (SelBasee > 0 && slvlmin <= 34) {
    const plainBase = SelPref + SelSuff === 0 && SelBasee < 70;
    const dungeonSources = [
      ['Normal', baslvl, 'Normal     '],
      ['Nightmare', baslvl < 16 ? 1 : baslvl - 15, 'Nightmare  '],
      ['Hell', 1, 'Hell       ']
    ];
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

export { calculateAvailability };
