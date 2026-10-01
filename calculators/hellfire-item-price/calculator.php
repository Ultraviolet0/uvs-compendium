<?php
$hellfire_item_price_heading_level = $hellfire_item_price_heading_level ?? 'h1';

if (!in_array($hellfire_item_price_heading_level, ['h1', 'h2', 'h3'], true)) {
  $hellfire_item_price_heading_level = 'h1';
}
?>
<section class="section-panel calculator-page hellfire-item-price-page flow-lg" aria-labelledby="hellfire-item-price-title">
  <header class="flow">
    <p class="eyebrow">Calculator</p>
    <<?= $hellfire_item_price_heading_level ?> id="hellfire-item-price-title">Hellfire Item Price Calculator</<?= $hellfire_item_price_heading_level ?>>
    <p class="hero-copy">Estimate Hellfire item prices by selecting an item class, base item, optional prefix or suffix, variable affix values, and vendor source.</p>
    <p class="warning-text">Original calculator by <a href="https://web.archive.org/web/20120530085023/http://www.dpsyche.com/price.html" target="_blank" rel="noopener noreferrer">Eso aka the_Langolier</a> hosted on <a href="https://mgpat-gm.github.io/calcs.html" target="_blank" rel="noopener noreferrer">Ghast's Grotto</a>. Updated for this compendium while preserving the calculator behavior and adding Hellfire affixes.</p>
  </header>

  <form id="hellfire-price-calculator" class="hellfire-price-form flow-lg" name="SelectItm">
    <div class="calculator-grid hellfire-class-row">
      <div class="form-field calculator-field calculator-field-narrow">
        <label for="item-class">Item Class</label>
        <select id="item-class" name="Clas">
          <option value="invalid" selected>Select this first</option>
          <option value="Helm">Helm</option>
          <option value="Armor">Armor</option>
          <option value="Shield">Shield</option>
          <option value="Sword">Sword</option>
          <option value="Axe">Axe</option>
          <option value="Club">Club</option>
          <option value="Bow">Bow</option>
          <option value="Staff">Staff</option>
          <option value="Jewelry">Jewelry</option>
          <option value="Unique">Unique</option>
        </select>
      </div>
    </div>

    <div class="calculator-grid calculator-grid-3">
      <div class="form-field calculator-field">
        <label for="item-prefix">Prefix</label>
        <select id="item-prefix" name="Prefx">
          <option value="None" selected>None</option>
        </select>
      </div>

      <div class="form-field calculator-field">
        <label for="base-item">Base Item</label>
        <select id="base-item" name="Bse">
          <option>None</option>
        </select>
      </div>

      <div class="form-field calculator-field">
        <label for="item-suffix">Suffix</label>
        <select id="item-suffix" name="Suffx">
          <option value="None" selected>None</option>
        </select>
      </div>
    </div>

    <div class="calculator-grid calculator-grid-3 calculator-grid-align-end">
      <div class="form-field calculator-field">
        <label for="prefix-value">Prefix Value</label>
        <select id="prefix-value" name="Pvalue" disabled>
          <option value=" " selected> </option>
          <option value=" ">12345</option>
        </select>
      </div>

      <fieldset class="calculator-field calculator-source-group">
        <legend class="sr-only">Source</legend>
        <div class="choice-stack">
          <label id="Adria" class="choice-option calculator-source-option">
            <input name="Source" type="radio" value="Adria">
            <span>Adria</span>
          </label>
          <label id="Gris" class="choice-option calculator-source-option">
            <input name="Source" type="radio" value="Gris" checked>
            <span>Griswold</span>
          </label>
          <label id="Wirt" class="choice-option calculator-source-option is-disabled">
            <input name="Source" type="radio" value="Wirt" disabled>
            <span>Wirt</span>
          </label>
          <label id="Sale" class="choice-option calculator-source-option">
            <input name="Source" type="radio" value="Sale">
            <span>Resale</span>
          </label>
        </div>
      </fieldset>

      <div class="form-field calculator-field">
        <label for="suffix-value">Suffix Value</label>
        <select id="suffix-value" name="Svalue" disabled>
          <option value=" " selected> </option>
          <option value=" ">12345</option>
        </select>
      </div>
    </div>

    <section class="price-result" aria-labelledby="price-result-label" aria-live="polite">
      <p id="price-result-label" class="result-label">Price</p>
      <output id="Price" class="result-value" name="Price">0</output>
    </section>
  </form>
</section>
