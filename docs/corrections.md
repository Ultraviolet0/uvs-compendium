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

## Hellfire town-vendor availability limits

- **Mechanic/topic:** Vendor price limits and generation retries in the Premium Item Checker.
- **Affected game/version:** Hellfire and DevilutionX Hellfire mode; verify original Hellfire behavior separately before changing published results.
- **Incorrect or incomplete source behavior:** The checker still uses Diablo-era `140000` for Griswold and `90000` for Wirt, and its availability result does not model Hellfire's item-value and attribute checks. Adria also uses `140000`; her Hellfire limit was not audited here.
- **Proposed behavior:** Investigate the Hellfire `200000` price cap for Griswold and Wirt, plus the finite retry fallback that can leave an item exceeding a nominal limit. Decide how to present conditional availability when player inventory, class, and attributes matter.
- **Evidence or reasoning:** Max's [Hellfire shopping differences](../reference/hellfire-shopping-differences.pdf) describes the 200k caps, inventory and attribute restrictions, and retry fallback. DevilutionX defines `MaxVendorValueHf = 200000` and `MaxBoyValueHf = 200000` in [items.h](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.h); its [item-generation code](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.cpp) uses these in Hellfire mode. This differs from the inherited checker thresholds; no full original-Hellfire behavior audit was done in this task.
- **Status:** Proposed for separate review. No price or vendor-limit behavior changed in the premium-slot fix or refactor.
- **Affected Compendium components:** Hellfire Premium Item Checker vendor-availability rules and tests.

## Entry template

- **Mechanic/topic:**
- **Affected game/version:**
- **Incorrect or incomplete source behavior:**
- **Corrected behavior:**
- **Evidence or reasoning:** Link to reference section, game test, code, or owner instruction.
- **Status:** Proposed / Accepted by Rob (date and task or issue link) / Superseded.
- **Affected Compendium components:** Pages, calculators, data, and regression tests.
