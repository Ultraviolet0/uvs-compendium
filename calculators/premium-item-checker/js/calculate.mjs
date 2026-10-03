import { prefixx, suffixx, basee, uniq, premiumIndex, isChargedSpellSuffix } from "./data.mjs";
import { calculateAvailability } from "./availability.mjs";
import { formatDetailedPrice } from "./price.mjs";

function calculatePremiumItem(SelBasee, SelPref, SelSuff, detailedPrice = false,
  { gameVersion = 'hellfire', gameMode = 'multiplayer' } = {}) {
  if (!['diablo', 'hellfire'].includes(gameVersion) ||
      !['single-player', 'multiplayer'].includes(gameMode)) {
    throw new RangeError('Unsupported game context');
  }
  if (gameVersion === 'diablo' && (SelBasee > 146 || SelPref > 83 || SelSuff > 121)) {
    throw new RangeError('Hellfire-only item or affix in Diablo context');
  }
  if (gameMode === 'single-player' && (SelSuff === 100 || SelSuff === 102)) {
    throw new RangeError('Multiplayer-only staff spell in single-player context');
  }
  var tmpbase;
  var dspbase;
  var dsppref;
  var dspsuff;

  var dsp2pref;
  var dsp2suff;
  var dsp2base;

  var baseefc;
  var prefefc;
  var suffefc;
  var totalefc;

  var pricemin;
  var pricemax;

  var slvlmin;
  var slvlmax;
  var slvldsp;

  var premulti = prefixx[SelPref].multi;
  var preaddmin = prefixx[SelPref].addmin;
  var preaddmax = prefixx[SelPref].addmax;
  var prerange = preaddmax - preaddmin;
  var premin = prefixx[SelPref].min;
  var prestep = prefixx[SelPref].step;

  var sufmulti = suffixx[SelSuff].multi;
  var sufaddmin = suffixx[SelSuff].addmin;
  var sufaddmax = suffixx[SelSuff].addmax;
  var sufrange = sufaddmax - sufaddmin;
  var sufmin = suffixx[SelSuff].min;
  var sufstep = suffixx[SelSuff].step;

  var prelvl = prefixx[SelPref].level;
  var suflvl = suffixx[SelSuff].level;
  const baseQlvls = basee[SelBasee].qlvls;
  const baseQlvlDisplay = baseQlvls.join(', ');

  var psmulti = premulti + sufmulti;
  var multi_sign = 1;

  if (psmulti == 0) {
    psmulti = 1;
  } else {
    if (psmulti < 0) {
      multi_sign = -1;
      psmulti = multi_sign / psmulti;
    }
  }

  //-- Source Level --------------
  if (prelvl > suflvl) {
    slvlmin = prelvl;
    if (suflvl > 0) {
      slvlmax = suflvl * 2 + 1;
    } else {
      slvlmax = prelvl * 2 + 1;
    }
  } else {
    slvlmin = suflvl;
    if (prelvl > 0) {
      slvlmax = prelvl * 2 + 1;
    } else {
      if (suflvl > 0) {
        slvlmax = suflvl * 2 + 1;
      } else {
        slvlmax = suflvl;
      }
    }
  }

  if (isChargedSpellSuffix(SelSuff)) {
    // Staff spells use floor(ilvl / 2), except ilvl 1 still permits qlvl 1.
    // Their prefix is selected independently up to ilvl.
    slvlmin = Math.max(prelvl, suflvl === 1 ? 1 : suflvl * 2);
    slvlmax = 60;
  } else if (slvlmax >= 50) {
    slvlmax = 60;
  }

  if (SelBasee >= premiumIndex.firstUniqueBase) {
    slvlmin = uniq[SelBasee - premiumIndex.firstUniqueBase].minlvl;
    slvlmax = uniq[SelBasee - premiumIndex.firstUniqueBase].maxlvl;
  }

  if ((slvlmin == slvlmax) || (slvlmin == 0)) {
    slvldsp = slvlmin;
  } else {
    slvldsp = slvlmin + " - " + slvlmax;
  }

  //-- Prefix Data ---------------
  if (SelPref == 0) {
    dsppref = "";
    dsp2pref = "";
    prefefc = "";
  } else {
    if (SelBasee == 0) {
      dsppref = "";
      prefefc = "";
    } else {
      dsppref = prefixx[SelPref].name + " ";
      prefefc = "    " + prefixx[SelPref].effect;
    }
    dsp2pref = prefixx[SelPref].name + "\n    " + prefixx[SelPref].effect + "    " + prefixx[SelPref].equip.str + "\n    qlvl: " + prefixx[SelPref].level;
    dsp2pref += "    Multiplier: " + premulti + "    Base-Max: " + preaddmin + " - " + preaddmax + "\n\n";
  }

  //-- Suffix Data ---------------
  if (SelSuff == 0) {
    dspsuff = "";
    dsp2suff = "";
    suffefc = "";
  } else {
    if (SelBasee == 0) {
      dspsuff = "";
      suffefc = "";
    } else {
      dspsuff = " of " + suffixx[SelSuff].name;
      suffefc = "    " + suffixx[SelSuff].effect;
    }
    dsp2suff = suffixx[SelSuff].name + "\n    " + suffixx[SelSuff].effect + "    " + suffixx[SelSuff].equip.str + "\n    qlvl: " + suffixx[SelSuff].level;
    dsp2suff += "    Multiplier: " + sufmulti + "    Base-Max: " + sufaddmin + " - " + sufaddmax + "\n\n";
  }

  //-- Item Data -----------------
  if (SelBasee == 0) {
    dspbase = "";
    dsp2base = "";
    baseefc = "";
    totalefc = "";
    pricemin = basee[SelBasee].price;
    pricemax = basee[SelBasee].price;
  } else {
    if (SelPref + SelSuff == 0) {
      totalefc = "\n";
      dsp2base = "";
      pricemin = basee[SelBasee].price;
      pricemax = basee[SelBasee].price;
      if (SelBasee >= premiumIndex.firstUniqueBase) {
        dspbase = basee[SelBasee].name + " (" + uniq[SelBasee - premiumIndex.firstUniqueBase].basee + ")";
        dspbase += "\n    " + basee[SelBasee].effect + "\n    G/A Price:  " + basee[SelBasee].price + "    Source Level:  " + slvldsp + "    Base qlvl:  " + baseQlvlDisplay;
        baseefc = "\n\n    " + uniq[SelBasee - premiumIndex.firstUniqueBase].effect + "\n";
      } else {
        const qlvlLabel = baseQlvls.length > 1 ? 'Base qlvl:  ' : 'qlvl:  ';
        dspbase = basee[SelBasee].name + "\n    " + basee[SelBasee].effect + "\n    G/A Price:  " + basee[SelBasee].price + "    " + qlvlLabel + baseQlvlDisplay;
        baseefc = "";
      }
    } else {
      tmpbase = dsppref + basee[SelBasee].name + dspsuff;
      if (tmpbase.length > 16) {
        dspbase = basee[SelBasee].kind.name;
      } else {
        dspbase = basee[SelBasee].name;
      }
      baseefc = "\n    " + basee[SelBasee].effect + "\n";
      if (isChargedSpellSuffix(SelSuff)) {
        pricemin = multi_sign * Math.floor((basee[SelBasee].price + sufaddmin) * psmulti) + preaddmin;
        pricemax = multi_sign * Math.floor((basee[SelBasee].price + sufaddmax) * psmulti) + preaddmax;
      } else {
        pricemin = multi_sign * Math.floor(basee[SelBasee].price * psmulti) + preaddmin + sufaddmin;
        pricemax = multi_sign * Math.floor(basee[SelBasee].price * psmulti) + preaddmax + sufaddmax;
      }
      dsp2base = basee[SelBasee].name + "\n    qlvl: " + baseQlvlDisplay + "    " + basee[SelBasee].effect + "    G/A Price:  " + basee[SelBasee].price;
      totalefc = "\n\n    G/A Price: " + pricemin + " - " + pricemax + "    Source Level: " + slvldsp + "    Base qlvl:  " + baseQlvlDisplay + "\n";
    }
  }


  const vendorAvailability = calculateAvailability({
    SelBasee, SelPref, SelSuff, baseQlvls, prelvl, suflvl, slvlmin, slvlmax, pricemin,
    premulti, sufmulti, gameVersion, gameMode
  });

  var dsp1;

  if (SelBasee >= premiumIndex.firstUniqueBase) {
    dsp1 = dsppref + dspbase + dspsuff + baseefc;
  } else {
    dsp1 = dsppref + dspbase + dspsuff + baseefc + prefefc + suffefc + totalefc;
  }

  const itemSummary = dsp1;
  dsp1 += vendorAvailability.display;
  const display1 = dsp1;
  const display2 = dsp2pref + dsp2suff + dsp2base;
  const display3 = formatDetailedPrice({
    SelBasee, SelPref, SelSuff, detailedPrice, pricemin, psmulti, multi_sign,
    prerange, sufrange, premin, sufmin, prestep, sufstep
  });

  return { display1, display2, display3, itemSummary, availability: vendorAvailability.entries };


}



export { calculatePremiumItem };
