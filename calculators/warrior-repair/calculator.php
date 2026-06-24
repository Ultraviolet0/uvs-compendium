<?php
$warrior_repair_heading_level = $warrior_repair_heading_level ?? 'h1';

if (!in_array($warrior_repair_heading_level, ['h1', 'h2', 'h3'], true)) {
  $warrior_repair_heading_level = 'h1';
}
?>
<section class="section-panel calculator-page warrior-repair-page flow-lg" aria-labelledby="warrior-repair-title">
  <header class="flow">
    <p class="eyebrow">Calculator</p>
    <<?= $warrior_repair_heading_level ?> id="warrior-repair-title">Warrior Repair Calculator</<?= $warrior_repair_heading_level ?>>
    <p class="hero-copy">Find guaranteed one-step and two-step Warrior Repair paths for reducing an item's maximum durability to a specific target.</p>
    <p class="warning-text">Adapted from Sunya's Diablo / DevilutionX Durability Planner v6. Assumes the item is repaired to full durability, damaged by exactly 1 point, then repaired by a Warrior.</p>
  </header>

  <form id="warrior-repair-calculator" class="warrior-repair-form" action="#" novalidate>
    <div class="calculator-grid warrior-repair-controls">
      <div class="form-field calculator-field">
        <label for="repair-start">Starting Maximum Durability</label>
        <input id="repair-start" name="start" type="number" value="254" min="2" max="254" inputmode="numeric" required>
      </div>

      <div class="form-field calculator-field">
        <label for="repair-target">Target Maximum Durability</label>
        <input id="repair-target" name="target" type="number" value="245" min="1" max="253" inputmode="numeric" required>
      </div>
    </div>

    <p id="warrior-repair-loss" class="warrior-repair-loss text-muted" aria-live="polite"></p>

    <div id="warrior-repair-results" class="warrior-repair-results" aria-live="polite"></div>
  </form>

  <details class="calculator-details">
    <summary>Mechanics &amp; Examples</summary>
    <div class="calculator-details-body flow">
      <p>Full Warrior Repair formula:</p>
      <pre class="mechanics-code">1. x += clvl + Rnd[clvl]
2. loss = max(floor(MaxDur / (clvl + 9)), 1)
3. MaxDur -= loss
4. Repeat until item becomes fully repaired</pre>

      <p>Planner simplification:</p>
      <pre class="mechanics-code">Fully repair item
→ Damage by exactly 1 durability
→ Use Warrior Repair</pre>

      <p>With only 1 durability missing:</p>
      <pre class="mechanics-code">Missing Durability = 1 ≤ clvl</pre>

      <p>Therefore exactly one repair cycle is guaranteed and the result becomes deterministic.</p>

      <p>Examples:</p>
      <ul>
        <li>254 → 245 using clvl 17–19.</li>
        <li>254 → 250 using clvl 42–50.</li>
        <li>254 → 250 → 245 using clvl 42–50 then clvl 33–41.</li>
        <li>246 → 222 using clvl 1.</li>
      </ul>

      <p>Only guaranteed outcomes are shown.</p>
    </div>
  </details>

  <details class="calculator-details">
    <summary>Disclaimer &amp; Potential Discrepancies</summary>
    <div class="calculator-details-body flow">
      <ul>
        <li>Based on Jarulf's Guide and DevilutionX community research.</li>
        <li>Assumes exactly 1 durability missing before every Repair use.</li>
        <li>Searches deterministic one-cycle repairs only.</li>
        <li>Searches one-step and two-step repair sequences.</li>
        <li>Does not model Hidden Shrine overflow behavior.</li>
        <li>Does not model save/reload normalization behavior.</li>
        <li>Does not model mod-specific code changes.</li>
      </ul>
      <p class="warning-text">
        Thanks to <a href="<?= site_url('reference/jarulf162.pdf') ?>" target="_blank" rel="noopener noreferrer">Jarulf's Guide</a> and the <a href="https://discord.gg/invite/devilutionx" target="_blank" rel="noopener noreferrer">DevilutionX community</a>.
      </p>
    </div>
  </details>
</section>
