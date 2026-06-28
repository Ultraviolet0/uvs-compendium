<?php
$premium_item_checker_heading_level = $premium_item_checker_heading_level ?? 'h1';

if (!in_array($premium_item_checker_heading_level, ['h1', 'h2', 'h3'], true)) {
  $premium_item_checker_heading_level = 'h1';
}
?>
<section class="section-panel calculator-page premium-item-checker-page flow-lg" aria-labelledby="premium-item-checker-title">
  <header class="flow">
    <p class="eyebrow">Calculator</p>
    <<?= $premium_item_checker_heading_level ?> id="premium-item-checker-title">Hellfire Premium Item Checker</<?= $premium_item_checker_heading_level ?>>
    <p class="hero-copy">Check valid Hellfire premium item combinations, affix data, source availability, and detailed price ranges.</p>
    <p class="warning-text">Original by <a href="https://web.archive.org/web/20120125114431/http://www.red-wolf.sakura.ne.jp/dia/premiumchk.html" target="_blank" rel="noopener noreferrer">King aka Red-Wolf</a>, hosted on <a href="https://mgpat-gm.github.io/calcs.html" target="_blank" rel="noopener noreferrer">Ghast's Grotto</a>. Updated for this compendium with Hellfire affixes, Hellfire unique items, and quest item rows.</p>
  </header>

  <form id="premium-item-checker" class="premium-checker-form" name="premium" action="#" novalidate>
    <section class="premium-checker-controls" aria-label="Premium item controls">
      <div class="form-field calculator-field">
        <label for="premium-prefix">Prefix</label>
        <select id="premium-prefix" name="prefixx"></select>
      </div>

      <div class="form-field calculator-field">
        <label for="premium-base-item">Base Item</label>
        <select id="premium-base-item" name="basee"></select>
      </div>

      <div class="form-field calculator-field">
        <label for="premium-suffix">Suffix</label>
        <select id="premium-suffix" name="suffixx"></select>
      </div>

      <div class="form-field calculator-field">
        <label for="premium-price-mode">Detailed Price</label>
        <select id="premium-price-mode" name="calcprice">
          <option selected>Off</option>
          <option>On</option>
        </select>
      </div>

      <button id="premium-reset" class="button button-secondary premium-reset-button" type="button">Reset</button>
    </section>

    <section class="premium-checker-results" aria-label="Premium item results" aria-live="polite">
      <section class="premium-result-section" aria-labelledby="premium-result-heading">
        <h3 id="premium-result-heading" class="result-heading">Result</h3>
        <pre id="display1" class="premium-output" aria-label="Result"></pre>
      </section>

      <section class="premium-result-section" aria-labelledby="premium-data-heading">
        <h3 id="premium-data-heading" class="result-heading">Item / Affix Data</h3>
        <pre id="display2" class="premium-output" aria-label="Item and affix data"></pre>
      </section>

      <section class="premium-result-section" aria-labelledby="premium-price-data-heading">
        <h3 id="premium-price-data-heading" class="result-heading">Detailed Price Data</h3>
        <pre id="display3" class="premium-output premium-price-output" aria-label="Detailed price data"></pre>
      </section>
    </section>
  </form>
</section>
