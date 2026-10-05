<?php
$premium_item_checker_heading_level = $premium_item_checker_heading_level ?? 'h1';

if (!in_array($premium_item_checker_heading_level, ['h1', 'h2', 'h3'], true)) {
  $premium_item_checker_heading_level = 'h1';
}
?>
<section class="section-panel calculator-page premium-item-checker-page flow-lg" aria-labelledby="premium-item-checker-title">
  <header class="flow">
    <p class="eyebrow">Calculator</p>
    <<?= $premium_item_checker_heading_level ?> id="premium-item-checker-title">Premium Item Checker</<?= $premium_item_checker_heading_level ?>>
    <p class="hero-copy">Check item combinations, affix data, possible sources, and detailed price ranges in Diablo or Hellfire.</p>
    <p class="warning-text">Original by <a href="https://web.archive.org/web/20120125114431/http://www.red-wolf.sakura.ne.jp/dia/premiumchk.html" target="_blank" rel="noopener noreferrer">King aka Red-Wolf</a>, hosted on <a href="https://mgpat-gm.github.io/calcs.html" target="_blank" rel="noopener noreferrer">Ghast's Grotto</a>. Updated for this compendium with Hellfire affixes, Hellfire unique items, and quest item rows.</p>
  </header>

  <form id="premium-item-checker" class="premium-checker-form" name="premium" action="#" novalidate>
    <div class="calculator-context">
      <div class="form-field calculator-field"><label for="premium-game">Game</label><select id="premium-game" name="gameVersion"><option value="hellfire">Hellfire</option><option value="diablo">Diablo</option></select></div>
      <div class="form-field calculator-field"><label for="premium-mode">Play mode</label><select id="premium-mode" name="gameMode"><option value="multiplayer">Multiplayer</option><option value="single-player">Single Player</option></select></div>
    </div>
    <fieldset class="premium-checker-controls">
      <legend>Choose an item combination</legend>
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

    </fieldset>

    <div class="premium-checker-actions">
      <div class="form-field calculator-field">
        <label for="premium-price-mode">Detailed price table</label>
        <select id="premium-price-mode" name="calcprice">
          <option selected>Off</option>
          <option>On</option>
        </select>
      </div>
      <button id="premium-reset" class="button button-secondary premium-reset-button" type="button">Reset choices</button>
    </div>
    <p id="premium-status" class="visually-hidden" role="status" aria-live="polite"></p>

    <section class="premium-checker-results" aria-label="Premium item results">
      <section class="premium-result-section" aria-labelledby="premium-result-heading">
        <h3 id="premium-result-heading" class="result-heading">Item and price</h3>
        <pre id="display1" class="premium-output" aria-label="Item and price"></pre>
      </section>

      <section class="premium-result-section" aria-labelledby="premium-availability-heading">
        <h3 id="premium-availability-heading" class="result-heading">Where it can appear</h3>
        <ul id="premium-availability" class="premium-availability" aria-describedby="premium-level-hint"></ul>
        <p class="premium-level-hint" id="premium-level-hint">Source Level is the item's generation level (ilvl). Vendor ranges show possible rolls, not guaranteed stock; class, stats, carried gear, and rare Hellfire retries can affect offers.</p>
      </section>

      <section class="premium-result-section" aria-labelledby="premium-data-heading">
        <h3 id="premium-data-heading" class="result-heading">Item and affix data</h3>
        <pre id="display2" class="premium-output" aria-label="Item and affix data"></pre>
      </section>

      <section class="premium-result-section" aria-labelledby="premium-price-data-heading">
        <h3 id="premium-price-data-heading" class="result-heading">Detailed price data</h3>
        <pre id="display3" class="premium-output premium-price-output" aria-label="Detailed price data"></pre>
      </section>
    </section>
  </form>
</section>
<script type="module" src="<?= site_url('calculators/premium-item-checker/js/scripts.mjs') ?>?v=<?= asset_version('calculators/premium-item-checker/js/scripts.mjs') ?>"></script>
