<?php

declare(strict_types=1);

$page_title = "Hellfire Shop Qlvl Calculator | UV's Compendium";
$page_description = "Calculate Hellfire shop item and affix qlvl ranges for Griswold, Wirt, and Adria.";
$base_path = '../../';
$current_page = 'shop-qlvl';
$page_scripts = ['calculators/shop-qlvl/js/scripts.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
?>
<section class="section-panel calculator-page flow-lg" aria-labelledby="shop-qlvl-title">
  <div class="flow">
    <p class="eyebrow">Calculator</p>
    <h1 id="shop-qlvl-title">Hellfire Shop Qlvl Calculator</h1>
    <p class="hero-copy">
      Check the base-item and affix qlvl ranges available from Hellfire shop sources based on character level.
    </p>
    <p class="warning-text">Original calculator by <a href="https://jsfiddle.net/23nzfd4p/show" target="_blank" rel="noopener noreferrer">@Royal#4121</a> hosted on <a href="https://mgpat-gm.github.io/calcs.html" target="_blank" rel="noopener noreferrer">Ghast's Grotto</a>. Updated for this compendium while preserving the calculator behavior and modifying for Hellfire rules.</p>
  </div>

  <form id="calc" class="calculator-panel calculator-form flow-lg" action="#" novalidate>
    <div class="calculator-grid calculator-grid-2 calculator-grid-align-end calculator-control-row">
      <div class="form-field calculator-field calculator-field-narrow">
        <label for="clvl">Character Level</label>
        <input id="clvl" name="clvl" type="number" min="1" max="50" inputmode="numeric" required placeholder="1-50">
      </div>

      <div class="calculator-actions">
        <button id="calculate" class="button button-primary" type="submit">Calculate</button>
      </div>
    </div>

    <section class="calculator-results qlvl-results-grid" aria-label="Shop qlvl results">
      <article class="result-card qlvl-result-card qlvl-result-card-large flow">
        <h2 class="result-heading">Griswold</h2>
        <label class="sr-only" for="grisresult">Griswold qlvl results</label>
        <textarea id="grisresult" class="result-output qlvl-output qlvl-output-tall" rows="17" readonly>Slot:   Base:   Affixes:

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
15:     7-25    15-30</textarea>
      </article>

      <article class="result-card qlvl-result-card flow">
        <h2 class="result-heading">Wirt</h2>
        <label class="sr-only" for="wirtresult">Wirt qlvl results</label>
        <textarea id="wirtresult" class="result-output qlvl-output" rows="2" readonly>Base items:  1-25
Affixes:     25-60</textarea>
      </article>

      <article class="result-card qlvl-result-card flow">
        <h2 class="result-heading">Adria <span class="text-muted">(MP)</span></h2>
        <label class="sr-only" for="adriaresult">Adria multiplayer qlvl results</label>
        <textarea id="adriaresult" class="result-output qlvl-output" rows="3" readonly>Base items and spells (of staffs or books):  1-16
Prefixes on staffs with spell:    1-32
Affixes on staffs without spell:  16-32</textarea>
      </article>
    </section>
  </form>
</section>
<?php require_once dirname(__DIR__, 2) . '/includes/public_footer.php'; ?>
