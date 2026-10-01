# Mechanics corrections record

Use one entry per discrepancy. `Proposed` means the evidence still needs review; `Accepted` means Rob confirmed it. Do not elevate an entry to source level 2 or change published mechanics solely because it is proposed. Include affected components and a regression case when practical.

## Decay affix classification

- **Mechanic/topic:** Decay affix classification.
- **Affected game/version:** Hellfire; verify specific DevilutionX versions when relevant.
- **Incorrect or incomplete source behavior:** Jarulf's Guide 1.62 classifies Decay as a prefix.
- **Corrected behavior:** Decay is a suffix.
- **Evidence or reasoning:** Rob explicitly confirmed this correction in the current development-foundation follow-up (September 30, 2026). The existing README and both affected calculators already reflect it.
- **Status:** Accepted by Rob on September 30, 2026. This correction takes precedence over Jarulf's Guide.
- **Affected Compendium components:** Hellfire Item Price Calculator; Hellfire Premium Item Checker; README.

## Hellfire town-vendor availability limits and retries

- **Mechanic/topic:** Vendor price limits and generation retries in the Premium Item Checker.
- **Affected game/version:** Hellfire and DevilutionX Hellfire mode; verify original Hellfire behavior separately before changing published results.
- **Incorrect or incomplete source behavior:** The checker used Diablo-era `140000` for Griswold and `90000` for Wirt, then treated an empty modeled-source list as though the combination had no possible source.
- **Corrected behavior:** The Hellfire checker now uses the `200000` underlying item-value limit for Griswold and Wirt. Where a Wirt combination exceeds that normal limit but meets the modeled item and affix level rules, it identifies the rare retry fallback instead of declaring the item impossible. Its result text states that class, attributes, carried gear, and game mode are not fully modeled. Adria's inherited `140000` remains unaudited.
- **Evidence or reasoning:** Max's [Hellfire shopping differences](../reference/hellfire-shopping-differences.pdf) states both `200000` limits, the 150/250 attempt limits, and the conditions ignored on exhaustion. DevilutionX defines `MaxVendorValueHf = 200000` and `MaxBoyValueHf = 200000` in [items.h](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.h); [SpawnOnePremium and SpawnBoy](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.cpp) show the finite loops. Jarulf 1.62, sections 3.9 and 3.10, gives Wirt's base and affix levels and distinguishes these from Griswold's limits. Rob's Wirt screenshot of Godly Full Plate Mail of the Whale is a concrete counterexample to the old empty-source claim.
- **Status:** Hellfire price limits and Wirt price-retry possibility verified and implemented in the follow-up branch. Full class, attribute, carried-inventory, Diablo, and multiplayer logic remains for the separate mode-system work; original Hellfire and DevilutionX edge cases still require game-specific regression review.
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
- **Affected game/version:** Hellfire Single Player and Multiplayer; compare with Diablo modes.
- **Incorrect or incomplete source behavior:** The current checker does not expose game and player-count modes, so its vendor item-type availability cannot express every mode-specific rule.
- **Proposed behavior:** Add explicit Diablo/Hellfire and Single Player/Multiplayer modes, then validate item-type availability against the appropriate game rules.
- **Evidence or reasoning:** Independent PR #3 review identified the mode dependency; the current refactor preserves inherited vendor behavior pending a separate correction.
- **Status:** Proposed for separate review. No vendor item-type mechanics changed in this PR stack.
- **Affected Compendium components:** Premium Item Checker vendor availability, mode controls, and tests.

## Entry template

- **Mechanic/topic:**
- **Affected game/version:**
- **Incorrect or incomplete source behavior:**
- **Corrected behavior:**
- **Evidence or reasoning:** Link to reference section, game test, code, or owner instruction.
- **Status:** Proposed / Accepted by Rob (date and task or issue link) / Superseded.
- **Affected Compendium components:** Pages, calculators, data, and regression tests.
