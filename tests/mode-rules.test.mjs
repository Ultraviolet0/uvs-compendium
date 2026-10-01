import assert from 'node:assert/strict';
import { test } from 'node:test';
import { calculatePremiumItem } from '../calculators/premium-item-checker/js/calculate.mjs';
import { getAvailableOptions, getDiabloPremiumItemLevels } from '../calculators/premium-item-checker/js/rules.mjs';
import { getWirtCharacterLevels } from '../calculators/premium-item-checker/js/availability.mjs';
import { townItemLevel } from '../calculators/town-level.mjs';
import { calculateShopQlvls } from '../calculators/shop-qlvl/js/scripts.mjs';

const context = (gameVersion, gameMode) => ({ gameVersion, gameMode });
const vendors = (item) => item.availability.filter(({ source }) =>
  /^(?:Griswold|Wirt|Adria)/.test(source));

test('Diablo uses six post-level-up premium slots with an individual ilvl 30 cap', () => {
  assert.deepEqual(getDiabloPremiumItemLevels(29), [28, 28, 29, 29, 30, 30]);
  assert.deepEqual(getDiabloPremiumItemLevels(30), [29, 29, 30, 30, 30, 30]);
  assert.deepEqual(getDiabloPremiumItemLevels(31), [30, 30, 30, 30, 30, 30]);
  assert.equal(calculateShopQlvls(29, context('diablo', 'multiplayer')).slotLevels.length, 6);
  assert.equal(calculateShopQlvls(29, context('hellfire', 'multiplayer')).slotLevels.length, 15);
});

test('town stock uses character level in multiplayer and dungeon depth in single player', () => {
  assert.equal(townItemLevel({ gameMode: 'multiplayer', characterLevel: 25 }), 14);
  assert.equal(townItemLevel({ gameMode: 'single-player', dungeonLevel: 5 }), 7);
  assert.equal(townItemLevel({ gameMode: 'single-player', dungeonLevel: 14 }), 16);
  const multiplayer = calculateShopQlvls(25, context('hellfire', 'multiplayer'));
  const singlePlayer = calculateShopQlvls(25, { ...context('hellfire', 'single-player'), dungeonLevel: 5 });
  assert.equal(multiplayer.townLevel, 14);
  assert.equal(singlePlayer.townLevel, 7);
  assert.match(multiplayer.adria, /Prefixes on staves with spell:\s+1-28/);
  assert.match(singlePlayer.adria, /Prefixes on staves with spell:\s+1-14/);
  assert.doesNotMatch(multiplayer.adria, /without spell/);
  assert.match(calculateShopQlvls(25, context('diablo', 'multiplayer')).adria, /without spell/);
});

test('Hellfire-only content is unavailable to Diablo selections and calculations', () => {
  const choices = getAvailableOptions({ baseIndex: 0, prefixIndex: 0, suffixIndex: 0, gameVersion: 'diablo' });
  assert.equal(choices.bases.includes(147), false);
  assert.equal(choices.prefixes.includes(84), false);
  assert.equal(choices.suffixes.includes(122), false);
  assert.throws(() => calculatePremiumItem(147, 0, 0, false, context('diablo', 'multiplayer')), RangeError);
  assert.throws(() => calculatePremiumItem(39, 84, 0, false, context('diablo', 'multiplayer')), RangeError);
});

test('single-player jewelry, Hellfire staves, and Adria staff rules change actual sources', () => {
  const ringSp = vendors(calculatePremiumItem(68, 2, 0, false, context('diablo', 'single-player')));
  const ringMp = vendors(calculatePremiumItem(68, 2, 0, false, context('diablo', 'multiplayer')));
  assert.deepEqual(ringSp.map(({ source }) => source), ['Griswold', 'Wirt']);
  assert.deepEqual(ringMp, []);
  const plainStaffHf = vendors(calculatePremiumItem(63, 0, 0, false, context('hellfire', 'multiplayer')));
  const plainStaffD = vendors(calculatePremiumItem(63, 0, 0, false, context('diablo', 'single-player')));
  assert.deepEqual(plainStaffHf.map(({ source }) => source), ['Griswold (basic items)']);
  assert.deepEqual(plainStaffD, []);
  const chargedStaff = vendors(calculatePremiumItem(63, 0, 112, false, context('hellfire', 'multiplayer')));
  assert.ok(chargedStaff.some(({ source }) => source === 'Adria'));
  assert.ok(!chargedStaff.some(({ source }) => source === 'Griswold'));
  const singlePlayerChoices = getAvailableOptions({ baseIndex: 63, prefixIndex: 0,
    suffixIndex: 0, ...context('hellfire', 'single-player') });
  assert.equal(singlePlayerChoices.suffixes.includes(100), false);
  assert.equal(singlePlayerChoices.suffixes.includes(102), false);
  assert.throws(() => calculatePremiumItem(63, 0, 102, false,
    context('hellfire', 'single-player')), RangeError);
});

test('jewelry variants allow high-level Griswold premiums only in single player', () => {
  for (const [gameVersion, firstLevel] of [['hellfire', 27], ['diablo', 28]]) {
    for (const [baseIndex, qlvls] of [[68, '5, 10, 15'], [69, '8, 16']]) {
      const singlePlayer = calculatePremiumItem(baseIndex, 28, 35, false,
        context(gameVersion, 'single-player'));
      const multiplayer = calculatePremiumItem(baseIndex, 28, 35, false,
        context(gameVersion, 'multiplayer'));
      assert.deepEqual(vendors(singlePlayer), [
        { source: 'Griswold', levelType: 'Character level', range: `${firstLevel} - 50` },
        { source: 'Wirt', levelType: 'Character level', range: '15 - 50' }
      ]);
      assert.deepEqual(vendors(multiplayer), []);
      assert.match(singlePlayer.itemSummary, new RegExp(`Base qlvl:\\s+${qlvls}`));
      assert.match(singlePlayer.display2, new RegExp(`qlvl:\\s+${qlvls}`));
    }
  }
  assert.match(calculatePremiumItem(39, 62, 86).itemSummary, /Base qlvl:\s+10\b/);
  assert.match(calculatePremiumItem(68, 0, 0).itemSummary, /Base qlvl:\s+5, 10, 15/);
  assert.match(calculatePremiumItem(69, 0, 0).itemSummary, /Base qlvl:\s+8, 16/);
});

test('Wirt has separate Diablo/Hellfire price caps and exact affix qlvl boundaries', () => {
  assert.deepEqual(getWirtCharacterLevels(10, 23, 19), Array.from({ length: 8 }, (_, i) => i + 12));
  const diablo = vendors(calculatePremiumItem(23, 77, 45, false, context('diablo', 'multiplayer')));
  const hellfire = vendors(calculatePremiumItem(23, 77, 45, false, context('hellfire', 'multiplayer')));
  assert.deepEqual(diablo, []);
  assert.deepEqual(hellfire.map(({ source }) => source), ['Wirt (rare retry fallback)']);
});

test('Diablo single player excludes unavailable higher difficulties', () => {
  const sources = calculatePremiumItem(20, 0, 0, false, context('diablo', 'single-player'))
    .availability.map(({ source }) => source);
  assert.ok(sources.includes('Normal'));
  assert.ok(!sources.includes('Nightmare'));
  assert.ok(!sources.includes('Hell'));
});
