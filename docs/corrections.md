# Mechanics corrections record

Use one entry per discrepancy. `Proposed` means the evidence still needs review; `Accepted` means Rob confirmed it. Do not elevate an entry to source level 2 or change published mechanics solely because it is proposed. Include affected components and a regression case when practical.

## Hellfire shopping value threshold and resale rounding

- **Mechanic/topic:** Griswold and Wirt value anchors and Griswold resale quotes.
- **Affected game/version:** Hellfire shopping; the guide's examples use DevilutionX's integer arithmetic.
- **Incorrect or incomplete source behavior:** The shopping guide said a generated item must be worth *more than* 80% of its anchor and treated a resale quote as one exact underlying item value.
- **Corrected behavior:** Under the normal vendor filters, a generated item passes when its value is at least `floor(anchor value * 4 / 5)`, including equality. An identified magical item's resale quote is `floor(item value / 4)`, so a quote of `R` may conceal an actual value from `4R` through `4R + 3` (for ordinary positive values). The guide's resale ceilings account for the highest possible hidden value; exact item values permit tighter anchors.
- **Evidence or reasoning:** Maxpire reported the boundary and 1,003-gold example. DevilutionX [SpawnOnePremium and SpawnBoy](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.cpp) compute the 80% threshold with integer division and reject values below it. [StartSmithSell](https://github.com/diasurgical/DevilutionX/blob/master/Source/stores.cpp) uses integer division by four for identified magical equipment resale. Thus a 1,003-gold target can pass with a 1,254-gold anchor, but fails with 1,255.
- **Status:** Implemented in `fix/shopping-anchor-price-guidance` for review.
- **Affected Compendium components:** Shopping guide value-rule explanation and anchor resale examples.

## Decay affix classification

- **Mechanic/topic:** Decay affix classification.
- **Affected game/version:** Hellfire; verify specific DevilutionX versions when relevant.
- **Incorrect or incomplete source behavior:** Jarulf's Guide 1.62 classifies Decay as a prefix.
- **Corrected behavior:** Decay is a suffix.
- **Evidence or reasoning:** Rob explicitly confirmed this correction in the current development-foundation follow-up (September 30, 2026). The existing README and both affected calculators already reflect it.
- **Status:** Accepted by Rob on September 30, 2026. This correction takes precedence over Jarulf's Guide.
- **Affected Compendium components:** Hellfire Item Price Calculator; Hellfire Premium Item Checker; README.

## Hellfire staff spells at Wirt and in the Premium Item Checker

- **Mechanic/topic:** Charged-staff spell and prefix qlvs, catalog, and underlying staff value.
- **Affected game/version:** Hellfire; Diablo Wirt does not sell staves, and Hellfire-only spells remain unavailable in Diablo.
- **Incorrect or incomplete source behavior:** Shop Qlvl omitted Wirt's charged-staff ranges. Premium Checker omitted all ten Hellfire-only staff spells, treated a staff spell like an ordinary Wirt affix (incorrectly ending its availability as character level rose), understated the dungeon ilvl needed for a spell, and had six incorrect charge-value endpoints in its existing spell rows.
- **Corrected behavior:** For a Hellfire Wirt charged staff at character level `clvl`, spell qlvl may be `1..clvl` (limited by the published spell catalog to qlvl 20, Magi), and staff prefix qlvl may be `1..2*clvl`. The checker lists the Hellfire spells, computes Wirt levels from those independent bounds, uses `floor(ilvl/2)` for dungeon staff-spell eligibility, and calculates charged-staff value from charges and the spell multiplier. Adria's separate town-level limits and Diablo exclusions remain in force. These ranges show generation possibilities, not a guarantee that Wirt offers a staff; Hellfire class, inventory, attribute, and value retry checks still affect stock.
- **Evidence or reasoning:** [Jarulf 1.62, sections 3.2.3, 3.8, 3.9.4, and 3.13.2](../reference/jarulf162.pdf) lists the Hellfire spell qlvs, charges, price multipliers, and Wirt's item level. DevilutionX [SpawnBoy, GetItemBonus, GetStaffSpell, and GetStaffPrefix](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.cpp) pass `2*clvl` to staff generation, test spell qlvl against `clvl`, and select staff prefixes through `2*clvl`. Its [Hellfire spell table](https://github.com/diasurgical/DevilutionX/blob/master/mods/hf/txtdata/spells/spelldat.tsv) confirms the ten spells, Magi qlvl 20, charges, and staff costs.
- **Status:** Implemented in `fix/hellfire-staff-spells` for review, with direct and browser regression cases.
- **Affected Compendium components:** Shop Qlvl, Premium Item Checker spell data and availability, and tests.

## Jewelry base qlvl variants in premium source checks

- **Mechanic/topic:** Internal Ring and Amulet base qlvls used by source eligibility.
- **Affected game/version:** Diablo and Hellfire, especially single-player Griswold premiums.
- **Incorrect or incomplete source behavior:** The checker collapsed each visible jewelry type to one base qlvl (Ring 5, Amulet 8), falsely excluding some premium rings at high item levels.
- **Corrected behavior:** Ring retains one visible choice with base qlvls 5, 10, 15; Amulet retains one visible choice with base qlvls 8, 16. A source accepts the choice when any internal base qlvl satisfies its base-item window. The item summary lists all variants.
- **Evidence or reasoning:** [Jarulf 1.62, base-item table and section 3.9](../reference/jarulf162.pdf) lists these qlvls and Griswold's premium base window of floor(ilvl / 4) through ilvl. DevilutionX [itemdat.tsv](https://github.com/diasurgical/DevilutionX/blob/master/assets/txtdata/items/itemdat.tsv) has the corresponding separate jewelry rows. At ilvl 30, Ring qlvl 10 or 15 fits although qlvl 5 does not.
- **Status:** Implemented in `fix/premium-jewelry-qlvls` with direct and browser regression cases.
- **Affected Compendium components:** Premium Item Checker base data, source availability, item summary, and tests.

## Hellfire town-vendor availability limits and retries

- **Mechanic/topic:** Vendor price limits and generation retries in the Premium Item Checker.
- **Affected game/version:** Hellfire and DevilutionX Hellfire mode; verify original Hellfire behavior separately before changing published results.
- **Incorrect or incomplete source behavior:** The checker used Diablo-era `140000` for Griswold and `90000` for Wirt, then treated an empty modeled-source list as though the combination had no possible source.
- **Corrected behavior:** Hellfire uses a `200000` underlying vendor value limit; Diablo uses `140000` for Griswold/Adria and `90000` for Wirt. A level-eligible Hellfire Wirt combination above the normal cap is marked as a rare retry possibility. Wirt's displayed level range now checks each selected affix qlvl directly, including the qlvl-25 lower-bound cap, rather than applying dungeon Source Level limits to Wirt.
- **Evidence or reasoning:** Max's [Hellfire shopping differences](../reference/hellfire-shopping-differences.pdf) states both `200000` limits, the 150/250 attempt limits, and the conditions ignored on exhaustion. DevilutionX defines `MaxVendorValueHf = 200000` and `MaxBoyValueHf = 200000` in [items.h](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.h); [SpawnOnePremium and SpawnBoy](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.cpp) show the finite loops. Jarulf 1.62, sections 3.9 and 3.10, gives Wirt's base and affix levels and distinguishes these from Griswold's limits. Rob's Wirt screenshot of Godly Full Plate Mail of the Whale is a concrete counterexample to the old empty-source claim.
- **Status:** Implemented with explicit game/mode context in the two follow-up branches. Class, attribute, and carried-inventory conditions remain described as unmodeled; original Hellfire and DevilutionX retry edge cases still warrant game-specific review.
- **Affected Compendium components:** Hellfire Premium Item Checker vendor-availability rules and tests.

## Affix-pair alignment and exclusion review

- **Mechanic/topic:** Premium Item Checker affix-pair compatibility.
- **Affected game/version:** Diablo and Hellfire; establish separate rules if they differ.
- **Incorrect or incomplete source behavior:** Inherited alignment/exclusion logic may hide valid combinations such as Vicious items of the Moon, Stars, or Heavens.
- **Proposed behavior:** Audit the game rule and source data, then handle any confirmed correction in a separate mechanics change with boundary tests.
- **Evidence or reasoning:** Independent PR #3 review flagged these examples; the current refactor preserves the inherited result pending a dedicated source audit.
- **Status:** Proposed for separate review. No affix-pair mechanics changed in this PR stack.
- **Affected Compendium components:** Premium Item Checker selection rules and tests.

## Hellfire vendor item types by game mode

- **Mechanic/topic:** Griswold and Wirt item-type availability, including staves and single-player jewelry.
- **Affected game/version:** Diablo and Hellfire, single player and multiplayer.
- **Incorrect or incomplete source behavior:** One implicit Hellfire/multiplayer context could not express mode-specific vendor stock or single-player town levels.
- **Corrected behavior:** Premium Checker, Item Price, and Shop Qlvl have explicit game and play-mode controls. Diablo Griswold has six premium slots; Hellfire has fifteen. Griswold and Wirt sell jewelry in single player only, and staves in Hellfire only. In Hellfire, Griswold's premium staves have no spell while Wirt's staves are charged; Adria's Hellfire staves are charged. Heal Other and Resurrect staff spells are absent in single player. Basic Griswold and Adria item levels use character level in multiplayer and deepest dungeon level visited in single player. Diablo single player excludes Nightmare and Hell dungeon rows.
- **Evidence or reasoning:** [Jarulf 1.62, sections 2.7.1, 3.9–3.10](../reference/jarulf162.pdf), and DevilutionX [SpawnPremium/SpawnOnePremium/SpawnBoy](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.cpp), `PremiumItemOk`, `GetItemBonus`, `GetStaffSpell`, and `WitchItemOk`. Jarulf gives the 140k/90k Diablo and 200k Hellfire underlying vendor caps, Wirt's 150%/75% price modifiers, and SP/MP stock rules. DevilutionX confirms the separate staff-spell paths and multiplayer-only Heal Other/Resurrect generation.
- **Status:** Implemented in `feat/game-and-mode-toggles` for review, with direct and browser regression cases. Exact offer probability, Hellfire Wirt class/attribute/inventory retry state, quest triggers, and some unique acquisition conditions are outside this level-and-value model.
- **Affected Compendium components:** Premium Checker, Item Price, Shop Qlvl, shared town-level rule, and tests.

## Diablo and Hellfire damage scope

- **Mechanic/topic:** Game-specific class, spell, item-effect, and Holy Bolt damage options.
- **Affected game/version:** Diablo and Hellfire; player-count differences are outside the calculator's direct-damage inputs.
- **Corrected behavior:** The Damage Calculator uses a game selection. Diablo offers Warrior, Rogue, and Sorcerer; DevilutionX also permits optional Bard and Barbarian heroes in Diablo, labeled “DevX only” in the selector. Monk remains Hellfire-only. Diablo excludes Immolation, Lightning Wall, Ring of Fire, Devastation, Jester's, Peril, and the Hellfire adjacent quarter-damage option. Only Hellfire displays the 75%-reduced Holy Bolt case for Diablo and Bone Demons.
- **Evidence or reasoning:** [Jarulf 1.62, sections 2.1, 3.3, 4, 5.2, and 6.3](../reference/jarulf162.pdf) identifies Hellfire classes, spells and affixes and explicitly states the Hellfire Holy Bolt resistance. DevilutionX's [changelog](https://github.com/diasurgical/DevilutionX/blob/master/docs/CHANGELOG.md) lists Bard and Barbarian heroes in Diablo as an optional feature, and its [options definition](https://github.com/diasurgical/DevilutionX/blob/master/Source/options.h) includes class switches. The existing damage formulas are retained for spells shared by both games.
- **Status:** Game/mode controls are merged; optional DevilutionX Diablo class choices are implemented in `fix/calculator-urls-devx-classes` for review. The calculator remains a direct-damage model; it does not infer monster HP or resistances from single-player/multiplayer mode.
- **Affected Compendium components:** Damage Calculator controls, output notes, and browser tests.

## Entry template

- **Mechanic/topic:**
- **Affected game/version:**
- **Incorrect or incomplete source behavior:**
- **Corrected behavior:**
- **Evidence or reasoning:** Link to reference section, game test, code, or owner instruction.
- **Status:** Proposed / Accepted by Rob (date and task or issue link) / Superseded.
- **Affected Compendium components:** Pages, calculators, data, and regression tests.
