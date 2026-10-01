<?php
$shop_qlvl_heading_level = $shop_qlvl_heading_level ?? 'h1';

if (!in_array($shop_qlvl_heading_level, ['h1', 'h2', 'h3'], true)) {
  $shop_qlvl_heading_level = 'h1';
}
?>
<section class="section-panel calculator-page shop-qlvl-page flow-lg" aria-labelledby="shop-qlvl-title">
  <p class="eyebrow">Calculator</p>
  <<?= $shop_qlvl_heading_level ?> id="shop-qlvl-title">Hellfire Shop Qlvl Calculator</<?= $shop_qlvl_heading_level ?>>
  <p class="hero-copy">
    Check the base-item and affix qlvl ranges available from Hellfire shop sources based on character level.
  </p>
  <p class="warning-text">Original calculator by <a href="https://jsfiddle.net/23nzfd4p/show" target="_blank" rel="noopener noreferrer">@Royal#4121</a> hosted on <a href="https://mgpat-gm.github.io/calcs.html" target="_blank" rel="noopener noreferrer">Ghast's Grotto</a>. Updated for this compendium while preserving the calculator behavior and modifying for Hellfire rules.</p>

  <form id="calc" class="qlvl-form" action="#" novalidate>
    <div class="form-field calculator-field calculator-field-narrow qlvl-level-field">
      <label for="clvl">Character Level</label>
      <input id="clvl" name="clvl" type="number" min="1" max="50" inputmode="numeric" required placeholder="1-50">
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
      <pre id="wirtresult" class="qlvl-output" aria-label="Wirt qlvl results">Base items:  1-25
Affixes:     25-60</pre>

      <h3 class="result-heading">Adria <span class="text-muted">(MP)</span></h3>
      <pre id="adriaresult" class="qlvl-output qlvl-output-wrap" aria-label="Adria multiplayer qlvl results">Base items and spells (of staves or books):  1-16
Prefixes on staves with spell:    1-32</pre>
    </div>
  </form>
</section>
