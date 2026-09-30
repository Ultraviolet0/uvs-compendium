# Game-data sources and corrections

For Diablo, Hellfire, and DevilutionX mechanics, use this precedence:

1. Explicit instructions and corrections from Rob, including the current task.
2. Project-maintained corrections Rob has accepted in [the corrections record](corrections.md).
3. [Jarulf's Guide 1.62](../reference/jarulf162.pdf).
4. Existing Compendium behavior and content, where it does not conflict with a higher source.
5. Other reputable external sources when needed to fill a gap or specifically requested.

Rob's confirmed knowledge supersedes Jarulf when he identifies an error. Jarulf is a core reference, not an infallible source. Record the precise game and version, because Diablo, Hellfire, and DevilutionX can differ. Keep evidence close to each correction, and ask Rob to confirm a disputed rule before changing published mechanics.

Rob has confirmed that Decay is a **Hellfire suffix**, despite its prefix classification in Jarulf's Guide. This accepted correction is recorded in [the corrections record](corrections.md).

The existing `reference/` directory contains Jarulf 1.62, Max's Hellfire shopping differences, and Max's Diablo I/Hellfire shrine reference. The latter two are useful topic references, subject to the hierarchy above. PDFs in `reference/` are public site resources; this documentation and its corrections record are development files.

## Hellfire Griswold premium levels

Jarulf 1.62, section 3.9 (printed pages 46–49), defines 15 Hellfire premium slots, compared with six in Diablo. The nominal Hellfire slot offsets include two `clvl + 3` positions. When the character gains a level, older items move through the inventory and only slots 11, 13, and 15 are newly generated; this leaves one current `clvl + 3` item in slot 15. Buying an item can regenerate its own slot at its nominal offset. Source/item level (`ilvl`) is the level used to generate an item, not the item's base or affix `qlvl`. For Griswold's premium items, a base item's qlvl must be between `floor(ilvl / 4)` and `ilvl`, while each affix qlvl must be between `floor(ilvl / 2)` and `ilvl`. The generation ilvl is capped at 30.

The project reference `hellfire-shopping-differences.pdf` identifies the Hellfire inventory change but does not give the complete post-level-up slot sequence. The [DevilutionX `SpawnPremium` and `ReplacePremium` implementation](https://github.com/diasurgical/DevilutionX/blob/master/Source/items.cpp) was used to confirm the shift and refill behavior, including the distinction between a level-up and buying a slot. The Premium Item Checker reports character levels at which an eligible slot can generate a selected combination; a listed level is a possibility, not a guarantee of an offer.
