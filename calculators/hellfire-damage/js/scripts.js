/*
  Hellfire Damage Calculator
  Grounding source: Jarulf's Guide to Diablo and Hellfire v1.62.
  Scope: player physical damage and practical direct spell damage ranges.
  Limits: no to-hit, AC, monster-specific HP/resistance lookup, PvP, life/mana steal,
  elemental item rider damage, full missile pathing, or Peril self-damage.
*/
(function () {
  'use strict';

  function initHellfireDamageCalculator() {
    const root = document.getElementById('hellfire-damage-calculator');

    if (!root) {
      return;
    }

  const $ = (id) => root.querySelector(`#${id}`);

  const fields = [
    'characterClass', 'characterLevel', 'strength', 'magic', 'dexterity', 'vitality',
    'weaponType', 'hasShield', 'weaponMin', 'weaponMax', 'weaponPercent',
    'flatDamage', 'autoSpecialDamage', 'bardHasSword', 'criticalHit',
    'civerbDemons', 'devastation', 'jester', 'quarterDamage', 'peril',
    'spellKey', 'spellLevel', 'targetHp'
  ];

  const state = Object.fromEntries(fields.map((id) => [id, $(id)]));

    if (Object.values(state).some((element) => element === null)) {
      return;
    }

    root.addEventListener('submit', (event) => event.preventDefault());

  const classLabels = {
    warrior: 'Warrior',
    rogue: 'Rogue',
    sorcerer: 'Sorcerer',
    monk: 'Monk',
    bard: 'Bard',
    barbarian: 'Barbarian'
  };

  const nakedMaxStats = {
    warrior: { str: 250, mag: 50, dex: 60, vit: 100 },
    rogue: { str: 55, mag: 70, dex: 250, vit: 80 },
    sorcerer: { str: 45, mag: 250, dex: 85, vit: 80 },
    monk: { str: 150, mag: 80, dex: 150, vit: 80 },
    bard: { str: 120, mag: 120, dex: 120, vit: 100 },
    barbarian: { str: 255, mag: 0, dex: 55, vit: 150 }
  };

  const categoryLabels = {
    animal: 'Animals',
    undead: 'Undead',
    demon: 'Demons'
  };

  const typeRuleLabels = {
    sword: {
      animal: 'Sword vs animal: ×1.5',
      undead: 'Sword vs undead: ×0.5',
      demon: 'Sword vs demon: ×1'
    },
    club: {
      animal: 'Club vs animal: ×0.5',
      undead: 'Club vs undead: ×1.5',
      demon: 'Club vs demon: ×1'
    },
    axe: allNeutralLabels('Axe'),
    bow: allNeutralLabels('Bow'),
    staff: allNeutralLabels('Staff'),
    unarmed: allNeutralLabels('Hands/feet'),
    shield: allNeutralLabels('Shield treated neutral')
  };

  const defaultDamage = {
    sword: [6, 15],
    club: [1, 8],
    axe: [4, 12],
    bow: [1, 4],
    staff: [2, 4]
  };

  // Jarulf's exact Jester implementation has two equally likely branches:
  // Rnd[100] / 100, or 5 * (Rnd[100] + 20) / 100.
  // That gives an exact average multiplier of (0.495 + 3.475) / 2 = 1.985.
  const JESTER_MAX_MULT = 5.95;
  const JESTER_AVG_MULT = 1.985;

  const spellDefinitions = {
    apocalypse: {
      label: 'Apocalypse',
      type: 'special / irresistible',
      unit: 'per cast roll',
      formula: 'Itt(clvl, Rnd[6] + 1)',
      ignoresResistance: true,
      calc: ({ clvl }) => range(clvl, 3.5 * clvl, 6 * clvl),
      notes: ['Works against all monsters, even triple-immune monsters. In Hellfire it only works on monsters in line of sight.']
    },
    bloodStar: {
      label: 'Blood Star',
      type: 'magic',
      unit: 'per star hit',
      formula: '3·slvl + [Mag/2] - [Mag/8]',
      calc: ({ slvl, mag }) => fixed(3 * slvl + Math.floor(mag / 2) - Math.floor(mag / 8)),
      notes: ['Costs 5 life to cast, or 5 extra mana if Mana Shield absorbs the cost.']
    },
    boneSpirit: {
      label: 'Bone Spirit',
      type: 'magic',
      unit: 'per spirit hit',
      formula: 'target monster HP / 3',
      usesTargetHp: true,
      calc: ({ targetHp }) => fixed(targetHp / 3),
      notes: ['Damage is based on the target monster’s current HP and is still reduced by magic resistance.']
    },
    chainLightning: {
      label: 'Chain Lightning',
      type: 'lightning',
      unit: 'per bolt hit/tick',
      formula: 'Rnd[clvl] + Rnd[2] + 2',
      calc: ({ clvl }) => range(2, ((clvl - 1) / 2) + 0.5 + 2, clvl + 2),
      notes: ['One aimed bolt plus one additional bolt for each monster in range. Each bolt tries to hit repeatedly for [slvl/2] + 6 ticks. High spell levels can cause gaps.']
    },
    chargedBolt: {
      label: 'Charged Bolt',
      type: 'lightning',
      unit: 'per bolt hit',
      formula: '1 to 1 + [Mag/4]',
      calc: ({ mag }) => range(1, (1 + (1 + Math.floor(mag / 4))) / 2, 1 + Math.floor(mag / 4)),
      notes: ['Number of bolts is 4 + [slvl/2].']
    },
    elemental: {
      label: 'Elemental',
      type: 'fire',
      unit: 'per direct hit',
      formula: 'Rec(slvl, 2·(Rnd[10]+Rnd[10]+clvl)+4) / 2',
      calc: ({ clvl, slvl }) => enumerateTwoD10((a, b) => rec(slvl, 2 * (a + b + clvl) + 4) / 2),
      notes: ['Basically a homing fireball with half Fireball direct damage; splash is 1/64 of the damage and attacks separately.']
    },
    fireball: {
      label: 'Fireball',
      type: 'fire',
      unit: 'per direct hit or splash hit',
      formula: 'Rec(slvl, 2·(Rnd[10]+Rnd[10]+clvl)+4)',
      calc: ({ clvl, slvl }) => enumerateTwoD10((a, b) => rec(slvl, 2 * (a + b + clvl) + 4)),
      notes: ['Splash damage in the target and adjacent hexes is equal to the fireball damage and gets its own hit check.']
    },
    firebolt: {
      label: 'Firebolt',
      type: 'fire',
      unit: 'per bolt hit',
      formula: 'Rnd[10] + slvl + [Mag/8] + 1',
      calc: ({ slvl, mag }) => {
        const base = slvl + Math.floor(mag / 8) + 1;
        return range(base, base + 4.5, base + 9);
      }
    },
    fireWall: {
      label: 'Fire Wall',
      type: 'fire',
      unit: 'per flame tick',
      formula: '(Rnd[10] + Rnd[10] + clvl + 2) / 8',
      calc: ({ clvl }) => enumerateTwoD10((a, b) => (a + b + clvl + 2) / 8),
      notes: ['The center flame is doubled. In Hellfire duration is 12 + 8·slvl seconds. Each flame tries to hit once every 0.05 seconds.']
    },
    flameWave: {
      label: 'Flame Wave',
      type: 'fire',
      unit: 'per flame hit',
      formula: 'Rnd[10] + clvl + 1',
      calc: ({ clvl }) => range(clvl + 1, clvl + 5.5, clvl + 10),
      notes: ['Number of flames is 5 + [slvl/2]. Flame Waves are treated as traps against players, including the caster.']
    },
    flashFront: {
      label: 'Flash, front/sides',
      type: 'magic',
      unit: 'per flash tick',
      formula: '[3·Rec(slvl, Itt(clvl, Rnd[20]+1))/2]/64',
      approximateAverage: true,
      calc: ({ clvl, slvl }) => {
        const min = Math.floor((3 * rec(slvl, clvl)) / 2) / 64;
        const avg = Math.floor((3 * rec(slvl, 10.5 * clvl)) / 2) / 64;
        const max = Math.floor((3 * rec(slvl, 20 * clvl)) / 2) / 64;
        return range(min, avg, max);
      },
      notes: ['Flash has two effects. This row is the front/sides effect. It tries to hit 19 times, once every 0.05 seconds. Average is approximated from the expected iterative roll.']
    },
    flashRear: {
      label: 'Flash, rear',
      type: 'magic',
      unit: 'per flash tick',
      formula: '[3·Rec(slvl, Itt(clvl, Rnd[2]+1))/2]/64',
      approximateAverage: true,
      calc: ({ clvl, slvl }) => {
        const min = Math.floor((3 * rec(slvl, clvl)) / 2) / 64;
        const avg = Math.floor((3 * rec(slvl, 1.5 * clvl)) / 2) / 64;
        const max = Math.floor((3 * rec(slvl, 2 * clvl)) / 2) / 64;
        return range(min, avg, max);
      },
      notes: ['Flash has two effects. This row is the rear effect. It tries to hit 19 times, once every 0.05 seconds. Average is approximated from the expected iterative roll.']
    },
    holyBolt: {
      label: 'Holy Bolt',
      type: 'holy / special',
      unit: 'per bolt hit',
      formula: '9 + clvl to 18 + clvl',
      holyBolt: true,
      calc: ({ clvl }) => range(9 + clvl, 13.5 + clvl, 18 + clvl),
      notes: ['Only works on undead monsters and Diablo. In Hellfire, Diablo and Bone Demons are resistant to Holy Bolt.']
    },
    immolation: {
      label: 'Immolation',
      type: 'fire',
      unit: 'per bolt hit',
      formula: 'Rec(slvl, (Itt(5, Rnd[6]) + clvl + 5) / 2)',
      calc: ({ clvl, slvl }) => enumerateFiveD6Zero((sum) => rec(slvl, (sum + clvl + 5) / 2)),
      notes: ['Does the same damage as Nova, but uses fireball-like bolts with splash behavior.']
    },
    inferno: {
      label: 'Inferno',
      type: 'fire',
      unit: 'per flame tick',
      formula: '(3·(Rnd[clvl] + Rnd[2]) + 6) / 16',
      calc: ({ clvl }) => enumerateRanges([clvl, 2], (a, b) => (3 * (a + b) + 6) / 16),
      notes: ['Fixed range 3. The three target locations last 1, 1.25, and 1.5 seconds and try to hit every 0.05 seconds.']
    },
    lightning: {
      label: 'Lightning',
      type: 'lightning',
      unit: 'per bolt hit/tick',
      formula: 'Rnd[clvl] + Rnd[2] + 2',
      calc: ({ clvl }) => range(2, ((clvl - 1) / 2) + 0.5 + 2, clvl + 2),
      notes: ['Lightning is stationary once created and tries to hit once every 0.05 seconds for [slvl/2] + 6 ticks.']
    },
    lightningWall: {
      label: 'Lightning Wall',
      type: 'lightning',
      unit: 'per bolt tick',
      formula: '(Rnd[10] + Rnd[10] + clvl + 2) / 4',
      calc: ({ clvl }) => enumerateTwoD10((a, b) => (a + b + clvl + 2) / 4),
      notes: ['The center lightning wall bolt is doubled. It does twice Fire Wall tick damage and lasts longer.']
    },
    nova: {
      label: 'Nova',
      type: 'lightning',
      unit: 'per bolt hit',
      formula: 'Rec(slvl, (Itt(5, Rnd[6]) + clvl + 5) / 2)',
      calc: ({ clvl, slvl }) => enumerateFiveD6Zero((sum) => rec(slvl, (sum + clvl + 5) / 2)),
      notes: ['Creates 92 bolts. In Hellfire, Nova is learnable normally.']
    },
    ringOfFire: {
      label: 'Ring of Fire',
      type: 'fire',
      unit: 'per flame tick',
      formula: '(Rnd[10] + Rnd[10] + clvl + 2) / 8',
      calc: ({ clvl }) => enumerateTwoD10((a, b) => (a + b + clvl + 2) / 8),
      notes: ['Does the same damage as Fire Wall and lasts the same amount of time. Creates 22 flames.']
    }
  };

  function allNeutralLabels(label) {
    return {
      animal: `${label}: ×1`,
      undead: `${label}: ×1`,
      demon: `${label}: ×1`
    };
  }

  function num(input, fallback = 0) {
    const parsed = Number(input.value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function fmt(value) {
    if (!Number.isFinite(value)) return '—';
    const near = Math.round(value);
    if (Math.abs(value - near) < 0.00001) return String(near);
    return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
  }

  function fmtRange(min, max) {
    return `${fmt(min)} – ${fmt(max)}`;
  }

  function fixed(value) {
    return { min: value, avg: value, max: value };
  }

  function range(min, avg, max) {
    return { min, avg, max };
  }

  function rec(slvl, slvl0) {
    let value = Math.floor(slvl0);
    for (let i = 0; i < slvl; i += 1) {
      value = Math.floor(value * 9 / 8);
    }
    return value;
  }

  function summarize(values) {
    if (!values.length) return fixed(0);
    let min = Infinity;
    let max = -Infinity;
    let sum = 0;
    values.forEach((value) => {
      min = Math.min(min, value);
      max = Math.max(max, value);
      sum += value;
    });
    return range(min, sum / values.length, max);
  }

  function enumerateTwoD10(fn) {
    const values = [];
    for (let a = 0; a < 10; a += 1) {
      for (let b = 0; b < 10; b += 1) {
        values.push(fn(a, b));
      }
    }
    return summarize(values);
  }

  function enumerateFiveD6Zero(fn) {
    const values = [];
    for (let a = 0; a < 6; a += 1) {
      for (let b = 0; b < 6; b += 1) {
        for (let c = 0; c < 6; c += 1) {
          for (let d = 0; d < 6; d += 1) {
            for (let e = 0; e < 6; e += 1) {
              values.push(fn(a + b + c + d + e));
            }
          }
        }
      }
    }
    return summarize(values);
  }

  function enumerateRanges(sizes, fn) {
    const values = [];
    function step(index, picked) {
      if (index >= sizes.length) {
        values.push(fn(...picked));
        return;
      }
      for (let i = 0; i < sizes[index]; i += 1) {
        picked.push(i);
        step(index + 1, picked);
        picked.pop();
      }
    }
    step(0, []);
    return summarize(values);
  }

  function isMeleeWeaponType(type) {
    return type !== 'bow';
  }

  function isWeaponPrefixType(type) {
    return type === 'sword' || type === 'club' || type === 'axe';
  }

  function canUseCritical(cls, type) {
    return (cls === 'warrior' || cls === 'barbarian') && isMeleeWeaponType(type);
  }

  function canUseJester(type) {
    return isWeaponPrefixType(type);
  }

  function canUseMeleeTotalItem(type) {
    return type === 'sword' || type === 'club' || type === 'axe' || type === 'staff';
  }

  function getInputs() {
    let clvl = clamp(Math.trunc(num(state.characterLevel, 1)), 1, 50);
    state.characterLevel.value = clvl;

    let weaponPercent = clamp(num(state.weaponPercent, 0), 0, 500);
    state.weaponPercent.value = weaponPercent;

    let slvl = clamp(Math.trunc(num(state.spellLevel, 1)), 0, 20);
    state.spellLevel.value = slvl;

    return {
      cls: state.characterClass.value,
      clvl,
      str: Math.max(0, num(state.strength, 0)),
      mag: Math.max(0, num(state.magic, 0)),
      dex: Math.max(0, num(state.dexterity, 0)),
      vit: Math.max(0, num(state.vitality, 0)),
      weaponType: state.weaponType.value,
      hasShield: state.hasShield.checked,
      weaponMin: Math.max(0, num(state.weaponMin, 0)),
      weaponMax: Math.max(0, num(state.weaponMax, 0)),
      weaponPercent,
      flatDamage: Math.max(0, num(state.flatDamage, 0)),
      autoSpecialDamage: state.autoSpecialDamage.checked,
      bardHasSword: state.bardHasSword.checked,
      criticalHit: state.criticalHit.checked,
      civerbDemons: state.civerbDemons.checked,
      devastation: state.devastation.checked,
      jester: state.jester.checked,
      quarterDamage: state.quarterDamage.checked,
      peril: state.peril.checked,
      spellKey: state.spellKey.value,
      slvl,
      targetHp: Math.max(1, num(state.targetHp, 1))
    };
  }

  function applyClassMaxStats() {
    const stats = nakedMaxStats[state.characterClass.value];
    if (!stats) return;
    state.strength.value = stats.str;
    state.magic.value = stats.mag;
    state.dexterity.value = stats.dex;
    state.vitality.value = stats.vit;
  }

  function normalizeDamageInputs(inputs) {
    let min = inputs.weaponMin;
    let max = inputs.weaponMax;

    if (inputs.autoSpecialDamage && (inputs.weaponType === 'unarmed' || inputs.weaponType === 'shield')) {
      const monkSpecialMin = Math.max(inputs.clvl / 2, 1);
      const monkSpecialMax = Math.max(inputs.clvl, inputs.weaponType === 'shield' ? 3 : 1);

      if (inputs.weaponType === 'unarmed') {
        min = inputs.cls === 'monk' ? monkSpecialMin : 1;
        max = inputs.cls === 'monk' ? monkSpecialMax : 1;
      }

      if (inputs.weaponType === 'shield') {
        min = inputs.cls === 'monk' ? monkSpecialMin : 1;
        max = inputs.cls === 'monk' ? monkSpecialMax : 3;
      }
    }

    if (min > max) [min, max] = [max, min];
    return { min, max };
  }

  function characterDamage(inputs) {
    const { cls, clvl, str, dex, vit, weaponType, hasShield, bardHasSword } = inputs;

    if (weaponType === 'bow') {
      switch (cls) {
        case 'warrior': return (str * clvl) / 200;
        case 'rogue': return ((str + dex) * clvl) / 200;
        case 'sorcerer': return (str * clvl) / 200;
        case 'monk': return ((str + dex) * clvl) / 600;
        case 'bard': return ((str + dex) * clvl) / 500;
        case 'barbarian': return (str * clvl) / 600;
        default: return 0;
      }
    }

    switch (cls) {
      case 'warrior':
        return (str * clvl) / 100;
      case 'rogue':
        return ((str + dex) * clvl) / 200;
      case 'sorcerer':
        return (str * clvl) / 100;
      case 'monk':
        if (weaponType === 'staff' || weaponType === 'unarmed') {
          return ((str + dex) * clvl) / 150;
        }
        return ((str + dex) * clvl) / 300;
      case 'bard':
        if (weaponType === 'sword' || bardHasSword) {
          return ((str + dex) * clvl) / 150;
        }
        return (str * clvl) / 100;
      case 'barbarian': {
        const base = (weaponType === 'axe' || weaponType === 'club')
          ? (str * clvl) / 75
          : (str * clvl) / 100;
        const shieldBlocksBonus = hasShield || weaponType === 'shield';
        const noShieldBonus = (!shieldBlocksBonus && weaponType !== 'staff') ? (vit * clvl) / 100 : 0;
        return base + noShieldBonus;
      }
      default:
        return 0;
    }
  }

  function monsterTypeMultiplier(weaponType, category) {
    if (weaponType === 'sword') {
      if (category === 'undead') return 0.5;
      if (category === 'animal') return 1.5;
    }

    if (weaponType === 'club') {
      if (category === 'undead') return 1.5;
      if (category === 'animal') return 0.5;
    }

    return 1;
  }

  function calculatePhysicalRange(inputs) {
    const weaponRange = normalizeDamageInputs(inputs);
    const charDmg = characterDamage(inputs);
    const weaponMinAfter = weaponRange.min * (1 + inputs.weaponPercent / 100) + inputs.flatDamage;
    const weaponMaxAfter = weaponRange.max * (1 + inputs.weaponPercent / 100) + inputs.flatDamage;
    const baseMin = weaponMinAfter + charDmg;
    const baseMax = weaponMaxAfter + charDmg;
    const baseAvg = (baseMin + baseMax) / 2;

    const rows = ['animal', 'undead', 'demon'].map((category) => {
      let min = baseMin;
      let avg = baseAvg;
      let max = baseMax;
      const applied = [];

      if (inputs.criticalHit && canUseCritical(inputs.cls, inputs.weaponType)) {
        min *= 2;
        avg *= 2;
        max *= 2;
        applied.push('Critical: ×2');
      }

      const typeMult = monsterTypeMultiplier(inputs.weaponType, category);
      min *= typeMult;
      avg *= typeMult;
      max *= typeMult;
      applied.push(typeRuleLabels[inputs.weaponType][category]);

      if (category === 'demon' && inputs.civerbDemons && inputs.weaponType !== 'bow') {
        min *= 3;
        avg *= 3;
        max *= 3;
        applied.push('+200% demon damage: ×3');
      }

      if (inputs.devastation && canUseMeleeTotalItem(inputs.weaponType)) {
        min *= 3;
        avg *= 3;
        max *= 3;
        applied.push('Devastation proc: ×3');
      }

      if (inputs.jester && canUseJester(inputs.weaponType)) {
        min = 0;
        avg *= JESTER_AVG_MULT;
        max *= JESTER_MAX_MULT;
        applied.push("Jester's: ×0 to ×6-ish (avg ×1.985; exact max ×5.95)");
      }

      if (inputs.quarterDamage) {
        min /= 4;
        avg /= 4;
        max /= 4;
        applied.push('Adjacent quarter damage: ÷4');
      }

      if (inputs.peril && canUseMeleeTotalItem(inputs.weaponType)) {
        min *= 2;
        avg *= 2;
        max *= 2;
        applied.push('Peril: ×2');
      }

      return { category, min, avg, max, applied: applied.join('; ') };
    });

    return {
      charDmg,
      weaponMinAfter,
      weaponMaxAfter,
      baseMin,
      baseAvg,
      baseMax,
      rows
    };
  }

  function calculateSpell(inputs) {
    const def = spellDefinitions[inputs.spellKey] || spellDefinitions.fireball;
    const base = def.calc(inputs);
    const rows = spellRowsFor(def, base);
    return { def, base, rows };
  }

  function scaleRange(base, mult) {
    return {
      min: base.min * mult,
      avg: base.avg * mult,
      max: base.max * mult
    };
  }

  function spellRowsFor(def, base) {
    if (def.ignoresResistance) {
      return [
        { label: 'All monsters', values: base, rule: 'Apocalypse ignores normal resistance/immunity checks.' }
      ];
    }

    if (def.holyBolt) {
      return [
        { label: 'Valid undead / Diablo, not resistant', values: base, rule: 'Full Holy Bolt damage.' },
        { label: 'Diablo or Bone Demon in Hellfire', values: scaleRange(base, 0.25), rule: 'Resistant to Holy Bolt: damage reduced by 75%.' },
        { label: 'Invalid target', values: fixed(0), rule: 'Holy Bolt only works on undead monsters and Diablo.' }
      ];
    }

    return [
      { label: 'No resistance', values: base, rule: 'Full damage.' },
      { label: 'Resistant', values: scaleRange(base, 0.25), rule: 'Resistant monsters take 25% damage.' },
      { label: 'Immune', values: fixed(0), rule: 'Immune monsters take no damage from this spell type.' }
    ];
  }

  function updateAvailability(inputs) {
    const type = inputs.weaponType;
    const cls = inputs.cls;

    const weaponIsTwoHanded = type === 'bow' || type === 'staff' || type === 'unarmed';
    if (type === 'shield') {
      state.hasShield.checked = true;
      state.hasShield.disabled = true;
    } else if (weaponIsTwoHanded) {
      state.hasShield.checked = false;
      state.hasShield.disabled = true;
    } else {
      state.hasShield.disabled = false;
    }
    $('shieldRow').classList.toggle('disabled', state.hasShield.disabled);

    const showBardSword = cls === 'bard' && type !== 'sword';
    $('bardSwordRow').style.display = showBardSword ? '' : 'none';
    if (!showBardSword) state.bardHasSword.checked = false;

    setCheckAvailability('criticalHit', 'criticalRow', canUseCritical(cls, type));
    setCheckAvailability('civerbDemons', 'civerbRow', type !== 'bow');
    setCheckAvailability('devastation', 'devastationRow', canUseMeleeTotalItem(type));
    setCheckAvailability('jester', 'jesterRow', canUseJester(type));
    setCheckAvailability('peril', 'perilRow', canUseMeleeTotalItem(type));

    const autoSpecial = state.autoSpecialDamage.checked && (type === 'unarmed' || type === 'shield');
    state.weaponMin.disabled = autoSpecial;
    state.weaponMax.disabled = autoSpecial;
  }

  function setCheckAvailability(inputId, rowId, enabled) {
    const input = $(inputId);
    input.disabled = !enabled;
    if (!enabled) input.checked = false;
    $(rowId).classList.toggle('disabled', !enabled);
  }

  function updateSpecialDamagePreview(inputs) {
    if (!state.autoSpecialDamage.checked) return;
    if (inputs.weaponType !== 'unarmed' && inputs.weaponType !== 'shield') return;
    const weaponRange = normalizeDamageInputs(inputs);
    state.weaponMin.value = fmt(weaponRange.min);
    state.weaponMax.value = fmt(weaponRange.max);
  }

  function render() {
    const inputs = getInputs();
    updateAvailability(inputs);
    const freshInputs = getInputs();
    updateSpecialDamagePreview(freshInputs);

    renderPhysical(freshInputs);
    renderSpell(freshInputs);
  }

  function renderPhysical(inputs) {
    const output = calculatePhysicalRange(inputs);

    $('characterDamageOut').textContent = fmt(output.charDmg);
    $('weaponDamageOut').textContent = fmtRange(output.weaponMinAfter, output.weaponMaxAfter);
    $('screenDamageOut').textContent = `${fmt(output.baseMin)} / ${fmt(output.baseAvg)} / ${fmt(output.baseMax)}`;

    $('resultRows').innerHTML = output.rows.map((row) => `
      <tr>
        <td data-label="Enemy category">${categoryLabels[row.category]}</td>
        <td class="damage-number" data-label="Minimum">${fmt(row.min)}</td>
        <td class="damage-number" data-label="Average">${fmt(row.avg)}</td>
        <td class="damage-number" data-label="Maximum">${fmt(row.max)}</td>
        <td data-label="Applied rule">${row.applied}</td>
      </tr>
    `).join('');

    renderPhysicalNotes(inputs);
  }

  function renderSpell(inputs) {
    const { def, base, rows } = calculateSpell(inputs);

    $('targetHpRow').style.display = def.usesTargetHp ? '' : 'none';
    $('spellMeta').innerHTML = `<strong>${def.label}</strong>: ${def.formula}`;
    $('spellNameOut').textContent = `${def.label} slvl ${inputs.slvl}`;
    $('spellTypeOut').textContent = `${def.type} / ${def.unit}`;
    $('spellBaseOut').textContent = `${fmt(base.min)} / ${fmt(base.avg)} / ${fmt(base.max)}`;

    $('spellRows').innerHTML = rows.map((row) => `
      <tr>
        <td data-label="Resistance case">${row.label}</td>
        <td class="damage-number" data-label="Minimum">${fmt(row.values.min)}</td>
        <td class="damage-number" data-label="Average">${fmt(row.values.avg)}</td>
        <td class="damage-number" data-label="Maximum">${fmt(row.values.max)}</td>
        <td data-label="Rule">${row.rule}</td>
      </tr>
    `).join('');

    renderSpellNotes(def, inputs);
  }

  function renderPhysicalNotes(inputs) {
    const notes = [];

    notes.push('Physical damage keeps fractional precision internally and is displayed without forced flooring. Diablo/Hellfire may round down in some UI contexts.');

    if (inputs.weaponType === 'bow') {
      notes.push('Bow attacks use bow character damage. Civerb-style demon damage, critical hit, Devastation, and Peril are disabled because these melee/item effects do not apply to bows in Jarulf\'s damage steps.');
    }

    if (inputs.cls === 'barbarian') {
      notes.push('Barbarian no-shield Vitality bonus is included for melee/non-bow attacks unless a shield is equipped or the weapon type is staff.');
    }

    if (inputs.jester) {
      notes.push('Jester\'s is usually described as ×0 to ×6; this calculator uses the exact Jarulf roll, with an average multiplier of ×1.985 and largest possible multiplier of ×5.95.');
    }

    if (inputs.peril) {
      notes.push('Peril self-damage is not calculated here. This only shows outgoing monster damage.');
    }

    $('notes').innerHTML = notes.map((note) => `<div class="damage-note">${note}</div>`).join('');
  }

  function renderSpellNotes(def, inputs) {
    const notes = [];

    notes.push('Spell output uses the Jarulf “real damage” formula where Jarulf gives one. For walls, beams, Flash, Chain Lightning, Lightning, Inferno, Ring of Fire, and similar spells, the numbers are per tick/hit, not a full-duration total.');

    if (!def.ignoresResistance && !def.holyBolt) {
      notes.push('The resistance table is generic. It does not look up individual monster resistances by difficulty; it simply applies full damage, resistant damage, and immune damage.');
    }

    if (def.approximateAverage) {
      notes.push('Flash average is approximate because Jarulf’s formula uses iterative random rolls across character level. Min and max follow the formula boundaries.');
    }

    if (def.notes) {
      def.notes.forEach((note) => notes.push(note));
    }

    if (inputs.slvl > 15) {
      notes.push('Spell levels above 15 are allowed here for testing and item-boosted edge cases. Normal learned book levels usually cap lower than that.');
    }

    $('spellNotes').innerHTML = notes.map((note) => `<div class="damage-note">${note}</div>`).join('');
  }

  function seedDefaultWeaponDamage(type) {
    if (defaultDamage[type]) {
      state.weaponMin.value = defaultDamage[type][0];
      state.weaponMax.value = defaultDamage[type][1];
    }
  }

  function populateSpellControls() {
    state.spellKey.innerHTML = Object.entries(spellDefinitions).map(([key, def]) => (
      `<option value="${key}">${def.label}</option>`
    )).join('');
    state.spellKey.value = 'fireball';

    const levels = [];
    for (let i = 0; i <= 20; i += 1) {
      levels.push(`<option value="${i}">${i}</option>`);
    }
    state.spellLevel.innerHTML = levels.join('');
    state.spellLevel.value = '15';
  }

  populateSpellControls();
  applyClassMaxStats();

  state.characterClass.addEventListener('change', () => {
    applyClassMaxStats();
    render();
  });

  state.weaponType.addEventListener('change', () => {
    seedDefaultWeaponDamage(state.weaponType.value);
    render();
  });

  state.autoSpecialDamage.addEventListener('change', render);

  fields.forEach((id) => {
    if (id === 'characterClass' || id === 'weaponType' || id === 'autoSpecialDamage') return;
    $(id).addEventListener('input', render);
    $(id).addEventListener('change', render);
  });

  render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHellfireDamageCalculator, { once: true });
  } else {
    initHellfireDamageCalculator();
  }
}());
