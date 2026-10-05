<?php
$shop_qlvl_heading_level = $shop_qlvl_heading_level ?? 'h1';

if (!in_array($shop_qlvl_heading_level, ['h1', 'h2', 'h3'], true)) {
  $shop_qlvl_heading_level = 'h1';
}
?>
<section class="section-panel calculator-page shop-qlvl-page flow-lg" aria-labelledby="shop-qlvl-title">
  <p class="eyebrow">Calculator</p>
  <<?= $shop_qlvl_heading_level ?> id="shop-qlvl-title">Shop Qlvl Calculator</<?= $shop_qlvl_heading_level ?>>
  <p class="hero-copy">
    Check base-item and affix qlvl ranges in Diablo or Hellfire.
  </p>
  <p class="warning-text">Original calculator by <a href="https://jsfiddle.net/23nzfd4p/show" target="_blank" rel="noopener noreferrer">@Royal#4121</a> hosted on <a href="https://mgpat-gm.github.io/calcs.html" target="_blank" rel="noopener noreferrer">Ghast's Grotto</a>. Updated for this compendium while preserving the calculator behavior and modifying for Hellfire rules.</p>

  <form id="calc" class="qlvl-form" action="#" novalidate>
    <div class="calculator-context">
      <div class="form-field calculator-field"><label for="shop-game">Game</label><select id="shop-game" name="gameVersion"><option value="hellfire">Hellfire</option><option value="diablo">Diablo</option></select></div>
      <div class="form-field calculator-field"><label for="shop-mode">Play mode</label><select id="shop-mode" name="gameMode"><option value="multiplayer">Multiplayer</option><option value="single-player">Single Player</option></select></div>
    </div>
    <div class="form-field calculator-field calculator-field-narrow qlvl-level-field">
      <label for="clvl">Character Level</label>
      <input id="clvl" name="clvl" type="number" min="1" max="50" inputmode="numeric" required placeholder="1-50">
    </div>
    <div id="shop-depth-field" class="form-field calculator-field calculator-field-narrow qlvl-level-field" hidden>
      <label for="shop-depth">Deepest dungeon level visited this game</label>
      <input id="shop-depth" name="dungeonLevel" type="number" min="1" max="16" inputmode="numeric" value="1">
      <small class="text-muted">In Hellfire, visiting Hive or Crypt also gives the maximum town item level; enter 14 for that case.</small>
    </div>

    <div class="qlvl-results" aria-label="Shop qlvl results" aria-live="polite">
      <h3 class="result-heading">Griswold</h3>
      <pre id="grisresult" class="qlvl-output" aria-label="Griswold qlvl results">Slot:   Base:   Affixes:

1 :     7-25    14-28
2 :     7-25    14-28
3 :     7-25    14-28
4 :     7-25    14-29
5 :     7-25    14-29
6 :     7-25    14-29
7 :     7-25    15-30
8 :     7-25    15-30
9 :     7-25    15-30
10:     7-25    15-30
11:     7-25    15-30
12:     7-25    15-30
13:     7-25    15-30
14:     7-25    15-30
15:     7-25    15-30</pre>

      <h3 class="result-heading">Wirt</h3>
      <pre id="wirtresult" class="qlvl-output qlvl-output-wrap" aria-label="Wirt qlvl results">Base items:  1-25
Affixes:     25-60
Prefixes on staves with spell:  1-100
Spells on staves:  1-20</pre>

      <h3 class="result-heading">Adria <span id="shop-mode-label" class="text-muted">(MP)</span></h3>
      <pre id="adriaresult" class="qlvl-output qlvl-output-wrap" aria-label="Adria qlvl results">Base items and spells (of staves or books):  1-16
Prefixes on staves with spell:    1-32</pre>
      <p id="shop-vendor-note" class="warning-text">These are qlvl ranges, not guaranteed vendor offers.</p>
    </div>
  </form>
</section>
<script type="module" src="<?= site_url('calculators/shop-qlvl/js/scripts.mjs') ?>?v=<?= asset_version('calculators/shop-qlvl/js/scripts.mjs') ?>"></script>
