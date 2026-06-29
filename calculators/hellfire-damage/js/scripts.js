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

    root.addEventListener('submit', (event) => {
      event.preventDefault();
      normalizeAllNumericFields();
      render();
    });

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

  // DevilutionX correction: Jester's uses Rnd[201].
  // This gives an exact total-damage multiplier range of ×0 to ×6,
  // with an average multiplier of ×2.
  const JESTER_MAX_MULT = 6;
  const JESTER_AVG_MULT = 2;

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

  const numericInputConfig = {
    characterLevel: { min: 1, max: 50, integer: true, fallback: 50 },
    strength: { min: 0, max: 999, integer: true, fallback: 250 },
    magic: { min: 0, max: 999, integer: true, fallback: 50 },
    dexterity: { min: 0, max: 999, integer: true, fallback: 60 },
    vitality: { min: 0, max: 999, integer: true, fallback: 100 },
    weaponMin: { min: 0, max: 999, integer: false, fallback: 6 },
    weaponMax: { min: 0, max: 999, integer: false, fallback: 15 },
    weaponPercent: { min: 0, max: 500, integer: false, fallback: 0 },
    flatDamage: { min: 0, max: 999, integer: false, fallback: 0 },
    targetHp: { min: 1, max: 99999, integer: true, fallback: 1000 }
  };

  const lastValidNumericValues = {};

  function parseNumericExpression(rawValue) {
    const source = String(rawValue).replace(/,/g, '').trim();

    if (source === '') {
      return NaN;
    }

    let index = 0;

    function skipSpaces() {
      while (source[index] === ' ' || source[index] === '\t') {
        index += 1;
      }
    }

    function parseNumber() {
      skipSpaces();
      const start = index;
      let hasDigit = false;
      let hasDot = false;

      while (index < source.length) {
        const char = source[index];

        if (char >= '0' && char <= '9') {
          hasDigit = true;
          index += 1;
          continue;
        }

        if (char === '.' && !hasDot) {
          hasDot = true;
          index += 1;
          continue;
        }

        break;
      }

      if (!hasDigit) {
        throw new Error('Expected number');
      }

      return Number(source.slice(start, index));
    }

    function parseFactor() {
      skipSpaces();

      if (source[index] === '+') {
        index += 1;
        return parseFactor();
      }

      if (source[index] === '-') {
        index += 1;
        return -parseFactor();
      }

      if (source[index] === '(') {
        index += 1;
        const value = parseExpression();
        skipSpaces();

        if (source[index] !== ')') {
          throw new Error('Expected closing parenthesis');
        }

        index += 1;
        return value;
      }

      return parseNumber();
    }

    function parseTerm() {
      let value = parseFactor();

      while (true) {
        skipSpaces();
        const operator = source[index];

        if (operator !== '*' && operator !== '/') {
          break;
        }

        index += 1;
        const right = parseFactor();

        if (operator === '*') {
          value *= right;
        } else {
          value /= right;
        }
      }

      return value;
    }

    function parseExpression() {
      let value = parseTerm();

      while (true) {
        skipSpaces();
        const operator = source[index];

        if (operator !== '+' && operator !== '-') {
          break;
        }

        index += 1;
        const right = parseTerm();
        value = operator === '+' ? value + right : value - right;
      }

      return value;
    }

    try {
      const value = parseExpression();
      skipSpaces();

      if (index !== source.length || !Number.isFinite(value)) {
        return NaN;
      }

      return value;
    } catch (_error) {
      return NaN;
    }
  }

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function constrainNumericValue(value, config) {
    let constrained = clamp(value, config.min, config.max);

    if (config.integer) {
      constrained = Math.trunc(constrained);
    }

    return constrained;
  }

  function numericValue(id) {
    const input = state[id];
    const config = numericInputConfig[id];
    const parsed = parseNumericExpression(input.value);

    if (Number.isFinite(parsed)) {
      const constrained = constrainNumericValue(parsed, config);
      lastValidNumericValues[id] = constrained;
      return constrained;
    }

    return lastValidNumericValues[id] ?? config.fallback;
  }

  function formatNumericFieldValue(value, config) {
    if (config.integer) {
      return String(Math.trunc(value));
    }

    return fmt(value);
  }

  function normalizeNumericField(id) {
    const input = state[id];

    if (!input || input.disabled) {
      return;
    }

    const config = numericInputConfig[id];
    const value = numericValue(id);
    input.value = formatNumericFieldValue(value, config);
  }

  function normalizeAllNumericFields() {
    Object.keys(numericInputConfig).forEach(normalizeNumericField);
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
    const clvl = numericValue('characterLevel');
    const weaponPercent = numericValue('weaponPercent');
    const slvl = clamp(Math.trunc(Number(state.spellLevel.value) || 0), 0, 20);

    return {
      cls: state.characterClass.value,
      clvl,
      str: numericValue('strength'),
      mag: numericValue('magic'),
      dex: numericValue('dexterity'),
      vit: numericValue('vitality'),
      weaponType: state.weaponType.value,
      hasShield: state.hasShield.checked,
      weaponMin: numericValue('weaponMin'),
      weaponMax: numericValue('weaponMax'),
      weaponPercent,
      flatDamage: numericValue('flatDamage'),
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
      targetHp: numericValue('targetHp')
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
        applied.push("Jester's: ×0 to ×6 (avg ×2)");
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

  function calculatePerilReturnRange(inputs) {
    const weaponRange = normalizeDamageInputs(inputs);
    const min = weaponRange.min * (1 + inputs.weaponPercent / 100) + inputs.flatDamage;
    const max = weaponRange.max * (1 + inputs.weaponPercent / 100) + inputs.flatDamage;

    return range(min, (min + max) / 2, max);
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
      notes.push('Jester\'s uses Rnd[201], giving an exact total-damage multiplier range of ×0 to ×6 with an average multiplier of ×2.');
    }

    if (inputs.peril) {
      const perilReturn = calculatePerilReturnRange(inputs);
      notes.push(`Peril return damage to player: ${fmt(perilReturn.min)} / ${fmt(perilReturn.avg)} / ${fmt(perilReturn.max)}. This uses weapon damage after +% and flat +damage only; it excludes character damage, monster category rules, critical hits, Civerb-style demon damage, Devastation, Jester, adjacent quarter damage, and Peril's outgoing ×2.`);
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

  const numericInputIds = new Set(Object.keys(numericInputConfig));

  numericInputIds.forEach((id) => {
    const input = $(id);

    input.addEventListener('input', render);
    input.addEventListener('change', () => {
      normalizeNumericField(id);
      render();
    });
    input.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') {
        return;
      }

      event.preventDefault();
      normalizeNumericField(id);
      render();
      input.blur();
    });
  });

  fields.forEach((id) => {
    if (id === 'characterClass' || id === 'weaponType' || id === 'autoSpecialDamage' || numericInputIds.has(id)) return;
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
