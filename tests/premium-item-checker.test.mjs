import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const source = readFileSync('calculators/premium-item-checker/js/scripts.js', 'utf8');
const checker = vm.createContext({});
vm.runInContext(source, checker, { filename: 'premium-item-checker/js/scripts.js' });

const slotLevels = (characterLevel) =>
  Array.from(checker.GetHellfirePremiumItemLevels(characterLevel));
const availableLevels = (baseQlvl, minIlvl, maxIlvl) =>
  Array.from(checker.GetHellfireGriswoldMagicCharacterLevels(baseQlvl, minIlvl, maxIlvl));

test('Hellfire premium inventory retains older slots and has one current +3 slot after level-up', () => {
  const levels = slotLevels(23);
  assert.equal(levels.length, 15);
  assert.deepEqual(levels.slice(-3), [25, 25, 26]);
  assert.equal(levels.filter((level) => level === 26).length, 1);
  assert.deepEqual(slotLevels(1).slice(-2), [4, 4]);
  assert.deepEqual(slotLevels(2).slice(-2), [4, 5]);
});

test('all 15 post-level-up Hellfire premium slots cap after their offsets', () => {
  const expected = new Map([
    [29, [27, 27, 27, 28, 28, 28, 29, 29, 29, 30, 30, 30, 30, 30, 30]],
    [30, [28, 28, 28, 29, 29, 29, 30, 30, 30, 30, 30, 30, 30, 30, 30]],
    [31, [29, 29, 29, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30]],
    [32, Array(15).fill(30)],
    [50, Array(15).fill(30)]
  ]);
  for (const [characterLevel, levels] of expected) {
    assert.deepEqual(slotLevels(characterLevel), levels, `character level ${characterLevel}`);
  }
});

test('sub-30 source levels leave the Hellfire premium inventory at their upper boundaries', () => {
  const ilvl27 = availableLevels(10, 27, 27);
  assert.equal(ilvl27.includes(29), true);
  assert.equal(ilvl27.includes(30), false);

  const ilvl28 = availableLevels(10, 28, 28);
  assert.equal(ilvl28.includes(30), true);
  assert.equal(ilvl28.includes(31), false);
  assert.equal(ilvl28.includes(50), false);

  const ilvl29 = availableLevels(10, 29, 29);
  assert.equal(ilvl29.includes(31), true);
  assert.equal(ilvl29.includes(32), false);
  assert.equal(ilvl29.includes(50), false);

  assert.equal(availableLevels(10, 30, 30).includes(50), true);
});

test('the +3 slot changes Knight, King, Speed, and Haste availability at exact boundaries', () => {
  const knightsSpeed = availableLevels(10, 23, 39);
  assert.equal(knightsSpeed.includes(19), false);
  assert.equal(knightsSpeed.includes(20), true);
  assert.equal(knightsSpeed.at(-1), 50);

  const knightsHaste = availableLevels(10, 27, 47);
  assert.equal(knightsHaste.includes(23), false);
  assert.equal(knightsHaste.includes(24), true);

  const kingsSpeed = availableLevels(10, 28, 39);
  assert.equal(kingsSpeed.includes(24), false);
  assert.equal(kingsSpeed.includes(25), true);
  const kingsHaste = availableLevels(10, 28, 47);
  assert.equal(kingsHaste.includes(24), false);
  assert.equal(kingsHaste.includes(25), true);
});

test('base qlvl and the 30 ilvl cap still constrain Griswold availability', () => {
  assert.equal(availableLevels(3, 28, 30).length, 0);
  assert.equal(availableLevels(10, 31, 60).length, 0);
  assert.deepEqual(availableLevels(10, 27, 27), [24, 25, 26, 27, 28, 29]);
});
